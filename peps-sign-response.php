<?php

require('../../../../config.php');

global $DB, $CFG, $PAGE;

require_once('../../../../stork2/storkSignResponse.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

// Read stork saml response
$stork_attributes = parseStorkResponse();

$cmid = (isset($_SESSION['cmid']) ? ($_SESSION['cmid']) : null);
$grade = (isset($_SESSION['assign'.$cmid]['grade']) ? unserialize($_SESSION['assign'.$cmid]['grade']) : null);
$nextpageparams = (isset($_SESSION['assign'.$cmid]['nextpageparams']) ? unserialize($_SESSION['assign'.$cmid]['nextpageparams']) : null);
$data = (isset($_SESSION['assign'.$cmid]['data']) ? unserialize($_SESSION['assign'.$cmid]['data']) : null);
$esignforall = (isset($_SESSION['assign'.$cmid]['esignforall']) ? ($_SESSION['assign'.$cmid]['esignforall']) : null);

unset($_SESSION['assign'.$cmid]);
unset($_SESSION['cmid']);

if ($stork_attributes) {
	$stork_token = $stork_attributes['eIdentifier'];

	$_SESSION['assign'.$cmid]['feedback_token'] = $stork_token;

	if($esignforall) {
		redirect('../../view.php?id='.$cmid.'&action=grading', get_string('esignforalladded', 'assignfeedback_esign'));
	}

	$esign = $DB->get_record('assignfeedback_esign', array('grade' => $grade->id));

	$esign->signedtoken = $stork_token;
	$esign->timesigned = time();
	$DB->update_record('assignfeedback_esign', $esign);

	//Build a new assign object.
	$context = context_module::instance($cmid);
	$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
	$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

	$PAGE->set_context($context);
	$PAGE->set_url('/mod/assign/view.php', array('id' => $cmid));
	$PAGE->set_course($course);
	$PAGE->set_cm($cm);
	$PAGE->set_title(get_string('pluginname', 'assignfeedback_esign'));
	$PAGE->set_pagelayout('standard');

	$assignment = new assign($context, $cm, $course);

	$event = \assignfeedback_esign\event\grade_signed::create_from_grade($assignment, $grade);
	$event->trigger();
	$assignment->save_grade($grade->userid, $data);

	if ($nextpageparams) {
		$nextpageurl = new moodle_url('/mod/assign/view.php', $nextpageparams);
		redirect($nextpageurl);
	} else {
		redirect('../../view.php?id='.$cmid);
	}
}
