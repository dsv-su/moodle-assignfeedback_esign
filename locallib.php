<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the definition for the library class for e-signature assign feedback plugin
 *
 *
 * @package   assignfeedback_esign
 * @copyright 2016 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die();

/**
 * library class for e-signature feedback plugin extending feedback plugin base class
 *
 * @copyright 2016 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_feedback_esign extends assign_feedback_plugin {

    /**
     * Get the name of the signature feedback plugin
     * @return string
     */
    public function get_name() {
        return get_string('pluginname', 'assignfeedback_esign');
    }

    /**
     * Get form elements for the grading page
     *
     * @param stdClass|null $grade
     * @param MoodleQuickForm $mform
     * @param stdClass $data
     * @return bool true if elements were added to the form
     */
    public function get_form_elements($grade, MoodleQuickForm $mform, stdClass $data) {
        $choices = get_string_manager()->get_list_of_countries();
        $choices = array('' => get_string('selectacountry') . '...') + $choices;
        $mform->addElement('select', 'country', 'Country for E-signature', $choices);
        $mform->addElement('static', 'description', '', get_string('savechanges', 'assignfeedback_esign'));
        $mform->setDefault('country', 'SE');
        $mform->addRule('country', get_string('selectacountry'), 'required', '', 'client', false, false);
        return true;
    }

    /**
     * Saving the signature into the database
     *
     * @param stdClass $grade
     * @param stdClass $data
     * @return bool
     */
    public function save(stdClass $grade, stdClass $data) {
        global $DB, $USER;

        $user = $DB->get_record('user', array('id' => $grade->grader));

        if ($grade->grade) {
            // Check which files to sign, and which signatures to delete.

            $cmid = $this->assignment->get_course_module()->id;
            $esign = $this->get_signature($grade);
            if (isset($_SESSION['assign'.$cmid]['feedback_token']) && $_SESSION['assign'.$cmid]['feedback_token']) {
                $this->process_initial_esigning($grade, true);
                unset($_SESSION['assign'.$cmid]['feedback_token']);
                return true;
            } else {
                $this->process_initial_esigning($grade);
            }

            $nextpageparams = array();
            $nextpageparams['id'] = $cmid;
            $nextpageparams['action'] = 'grading';

            // Handle 'save and show next' button.
            if (optional_param('saveandshownext', null, PARAM_RAW)) {
                $nextpageparams['action'] = 'grade';
                $nextpageparams['rownum'] = optional_param('rownum', 0, PARAM_INT) + 1;
                $nextpageparams['useridlistid'] = optional_param('useridlistid',
                    $this->assignment->get_useridlist_key_id(), PARAM_ALPHANUM);
            }

            $_SESSION['assign'.$cmid] = array();
            $_SESSION['assign'.$cmid]['data'] = serialize($data);
            $_SESSION['assign'.$cmid]['grade'] = serialize($grade);
            $_SESSION['assign'.$cmid]['nextpageparams'] = serialize($nextpageparams);
            $_SESSION['cmid'] = $cmid;

            redirect('feedback/esign/peps-sign-request.php?country='.$data->country);
        } else {
            return true;
        }
    }

    /**
     * Saves the initial signature in the feedback table, and signs it if needed.
     *
     * @param stdClass $grade
     * @param bool $showviewlink Set to true to show a link to view the full feedback
     * @return string
     */
    public function process_initial_esigning($grade, $feedbacktoken = false) {
        global $DB;
        $user = $DB->get_record('user', array('id' => $grade->grader));
        $esign = $this->get_signature($grade);
        if (!$esign) {
            $esign = new stdClass();
            $esign->signedtoken = 'empty_token';
            $esign->contextid = $this->assignment->get_context()->id;
            $esign->component = 'assignfeedback_esign';
            $esign->grade = $grade->id;
            $esign->userid = $grade->grader;
            $esign->signee = fullname($user);
            $esign->timesigned = time();

            $DB->insert_record('assignfeedback_esign', $esign);
        }

        if ($feedbacktoken) {
            $esign = $DB->get_record('assignfeedback_esign', array('grade' => $grade->id));
            $esign->signedtoken = $_SESSION['assign'.$this->assignment->get_course_module()->id]['feedback_token'];
            $esign->timesigned = time();
            $DB->update_record('assignfeedback_esign', $esign);
            $event = \assignfeedback_esign\event\grade_signed::create_from_grade($this->assignment, $grade);
            $event->trigger();
        }

        return;
    }

    /**
     * Display the comment in the feedback table
     *
     * @param stdClass $grade
     * @param bool $showviewlink Set to true to show a link to view the full feedback
     * @return string
     */
    public function view_summary(stdClass $grade, & $showviewlink) {
        global $DB;
        // Never show a link to view full submission.
        $showviewlink = false;
        // Let's try to display signed feedback info.
        return $this->print_esign_summary($grade);
    }

    /**
     * Get the signature from the database
     *
     * @param int $gradeid
     * @return stdClass|false The feedback signature for the given grade if it exists. False if it doesn't.
     */
    private function get_signature($grade) {
        global $DB;

        if (!$grade) {
            return false;
        }

        $esign = $DB->get_record('assignfeedback_esign', array('grade' => $grade->id));
        if ($esign) {
            return $esign;
        } else {
            return false;
        }
    }

    /**
     * The assignment has been deleted - cleanup
     *
     * @return bool
     */
    public function delete_instance() {
        global $DB;
        $DB->delete_records('assignfeedback_esign', array(
            'contextid' => $this->assignment->get_context()->id
        ));
        return true;
    }

    /**
     * Returns true if there are no feedback signatures for the given grade
     *
     * @param stdClass $grade
     * @return bool
     */
    public function is_empty(stdClass $grade) {
        if ($this->get_signature($grade)) {
            return ($this->get_signature($grade)->signedtoken == 'empty_token');
        } else {
            return true;
        }
    }

    /**
     * Override to indicate a plugin supports quickgrading.
     *
     * @return boolean - True if the plugin supports quickgrading
     */
    public function supports_quickgrading() {
        return true;
    }

    /**
     * Save quickgrading changes.
     *
     * @param int $userid The user id in the table this quickgrading element relates to
     * @param stdClass $grade The grade
     * @return boolean - true if the grade changes were saved correctly
     */
    public function save_quickgrading_changes($userid, $grade) {
        global $DB, $OUTPUT;

        $user = $DB->get_record('user', array('id' => $grade->grader));

        if (isset($_SESSION['assign'.$this->assignment->get_course_module()->id]['feedback_token']) &&
            $_SESSION['assign'.$this->assignment->get_course_module()->id]['feedback_token']) {
            $this->process_initial_esigning($grade, true);
        } else {
            global $OUTPUT;
            $nextpageparams = array();
            $nextpageparams['id'] = $this->assignment->get_course_module()->id;
            $nextpageparams['action'] = 'grading';
            echo $OUTPUT->header();
            echo $OUTPUT->heading(get_string('error'), 3);
            echo $OUTPUT->notification($OUTPUT->error_text(get_string('error_missingtoken', 'assignfeedback_esign')));
            $button = new single_button(new moodle_url('/mod/assign/view.php', $nextpageparams), get_string('back'), 'get');
            $button->class = 'continuebutton';
            echo $OUTPUT->render($button);
            echo $OUTPUT->footer();
            exit;
            return false;
        }

        return true;
    }

    /**
     * Return a list of the grading actions supported by this plugin.
     *
     * A grading action is a page that is not specific to a user but to the whole assignment.
     * @return array - An array of action and description strings.
     *                 The action will be passed to grading_action.
     */
    public function get_grading_actions() {
        return array('addesign' => get_string('addesign', 'assignfeedback_esign'));
    }


    /**
     * Called by the assignment module when someone chooses something from the
     * grading navigation or batch operations list.
     *
     * @param string $action - The page to view
     * @return string - The html response
     */
    public function view_page($action) {

        global $OUTPUT;
        if ($action == 'addesign') {
            global $CFG, $DB, $USER;

            require_capability('mod/assign:grade', $this->assignment->get_context());
            require_once($CFG->dirroot . '/mod/assign/feedback/esign/esignform.php');

            $formparams = array('cm' => $this->assignment->get_course_module()->id,
                    'context' => $this->assignment->get_context());

            $mform = new assignfeedback_esign_esign_form(null, $formparams);

            if ($mform->is_cancelled()) {
                unset($_SESSION['assign'.$this->assignment->get_course_module()->id]['esignforall']);
                redirect(new moodle_url('view.php',
                                        array('id' => $this->assignment->get_course_module()->id,
                                              'action' => 'grading')));
                return;
            } else if ($data = $mform->get_data()) {
                $_SESSION['assign'.$this->assignment->get_course_module()->id]['esignforall'] = true;
                $_SESSION['cmid'] = $this->assignment->get_course_module()->id;
                redirect('feedback/esign/peps-sign-request.php?country='.$data->country);

                return;
            } else {

                $header = new assign_header($this->assignment->get_instance(),
                                            $this->assignment->get_context(),
                                            false,
                                            $this->assignment->get_course_module()->id,
                                            get_string('addesign', 'assignfeedback_esign'));
                $o = '';
                $o .= $this->assignment->get_renderer()->render($header);
                $o .= $this->assignment->get_renderer()->render(new assign_form('esign', $mform));
                $o .= $this->assignment->get_renderer()->render_footer();
            }

            return $o;

        }

        return '';
    }

    /**
     * Get quickgrading form elements as html.
     *
     * @param int $userid The user id in the table this quickgrading element relates to
     * @param mixed $grade grade or null - The grade data.
     *                     May be null if there are no grades for this user (yet)
     * @return mixed - A html string containing the html form elements required for
     *                 quickgrading or false to indicate this plugin does not support quickgrading
     */
    public function get_quickgrading_html($userid, $grade) {
        if ($grade) {
            return $this->print_esign_summary($grade);
        } else {
            return false;
        }
    }

    /**
     * Returns signed string from the DB for the given grade as html.
     *
     * @param mixed $grade grade or null - The grade data.
     *                     May be null if there are no grades for this user (yet)
     * @return mixed - A html string containing the html form elements required for
     *                 quickgrading or false to indicate this plugin does not support quickgrading
     */
    public function print_esign_summary($grade) {
        $esign = $this->get_signature($grade);
        if ($esign && $esign->signedtoken <> 'empty_token') {
            $esign->timesigned = userdate($esign->timesigned);
            $output = get_string('signedby', 'assignfeedback_esign', $esign);
            return $output;
        } else {
            return '';
        }
    }

}
