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
        $mform->addElement('static', 'description', '',
            'By saving changes you will be redirected to your PEPS provider in order to complete e-signing of the grading action.');
        $mform->setDefault('country', 'SE');
        $mform->addRule('country', 'Please choose your country', 'required', '', 'client', false, false);
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
        global $DB;
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
        $esign = $this->get_signature($grade);
        if ($esign) {
            $output = 'The submission is signed by teacher '.$esign->signee.
            ' on '.userdate($esign->timesigned);
            return $output;
        }
        return false;
    }

    /**
     * Get the signature from the database
     *
     * @param int $gradeid
     * @return stdClass|false The feedback signature for the given grade if it exists. False if it doesn't.
     */
    private function get_signature($grade) {
        global $DB;
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
        return true;
    }
}