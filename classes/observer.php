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
 * Event observers used in Zendesk Integration plugin
 *
 * @package    local_zerogrades
 * @copyright  2019 Nick Stefanski <nmstefanski@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_zerogrades.
 */
class local_zerogrades_observer {
	
    /**
     * Triggered via assessable_submitted event.
     *
     * @param \mod_assign\event\assessable_submitted $event
     * @return void
     */
    public static function assessable_submitted(\mod_assign\event\assessable_submitted $event) {
		global $CFG, $DB;
        $assign_submission = $DB->get_record('assign_submission', ['id' => $event->objectid ]);
		require_once($CFG->dirroot.'/local/zerogrades/locallib.php');
		$result = zg_remove_override('assign', $assign_submission->assignment, $event->courseid, $event->userid);
    }

    /**
     * Triggered via user_updated event.
     *
     * @param \mod_quiz\event\attempt_submitted $event
	 * @return void
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
		global $CFG, $DB;
		//$other = (object) @unserialize($event->other); //not sure why this is failing... replace with DB call
        $quiz_attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid ]);
        require_once($CFG->dirroot.'/local/zerogrades/locallib.php');
		$result = zg_remove_override('quiz', $quiz_attempt->quiz, $event->courseid, $event->userid);
    }
}
