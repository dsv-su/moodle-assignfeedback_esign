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
 * The assignfeedback_esign e-signature created event.
 *
 * @package    assignfeedback_esign
 * @copyright  2016 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace assignfeedback_esign\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The assignfeedback_esign e-signed event class.
 *
 * @package    assignfeedback_esign
 * @since      Moodle 2.7
 * @copyright  2016 Pavel Sokolov <pavel.m.sokolov@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_signed extends \mod_assign\event\submission_graded {
    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/assign/view.php', array('id' => $this->contextinstanceid));
    }

    /**
     * Returns localised general event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventgradesigned', 'assignfeedback_esign');
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' has signed the grade with id '$this->objectid' for the user with " .
            "id '$this->relateduserid' for the assignment with course module id '$this->contextinstanceid'.";;
    }
}
