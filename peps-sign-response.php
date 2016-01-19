<?php

require('../../../../config.php');

global $DB, $CFG;

require_once('../../../../stork2/storkSignResponse.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

// Read stork saml response
$stork_attributes = parseStorkResponse();

if ($stork_attributes) {
	$stork_token = $stork_attributes['eIdentifier'];

	$grade = unserialize($_SESSION['grade']);
	$event_params = unserialize($_SESSION['event_params']);
	$nextpageparams = unserialize($_SESSION['nextpageparams']);
	$cmid = $_SESSION['cmid'];

	$esign = $DB->get_record('assignfeedback_esign', array('grade' => $grade->id));

	$esign->signedtoken = $stork_token;
	$esign->timesigned = time();
	$DB->update_record('assignfeedback_esign', $esign);

	//Build a new assign object.
	$context = context_module::instance($cmid);
	$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
	$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
	$assignment = new assign($context, $cm, $course);

	$event = \assignfeedback_esign\event\grade_signed::create_from_grade($assignment, $grade);
    $event->trigger();

    $assignment->update_grade($grade);

	if ($nextpageparams) {
		$nextpageurl = new moodle_url('/mod/assign/view.php', $nextpageparams);
		redirect($nextpageurl);
	} else {
		redirect('../../view.php?id='.$cmid);
	}
}
