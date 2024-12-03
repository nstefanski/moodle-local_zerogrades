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
 * Definition of scheduled tasks.
 *
 * @package		local_zerogrades
 * @copyright	2019 Nick Stefanski <nmstefanski@gmail.com>
 * @license		http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zerogrades\task;
		
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->libdir.'/grade/grade_item.php';
require_once $CFG->libdir.'/grade/grade_grade.php';
use grade_item;
use grade_grade;

defined('MOODLE_INTERNAL') || die();

class remove_grade_overrides extends \core\task\scheduled_task {

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('remove_grade_overrides', 'local_zerogrades');
    }
	
	public function execute() {
		mtrace("... removing zero grade overrides ...");
		
		global $CFG, $DB;
		
		//get overridden activities
		$sql = "SELECT gg.id, gi.id AS itemid, gi.courseid, gg.userid
				FROM {grade_grades} gg
				JOIN {grade_items} gi ON gg.itemid = gi.id
				WHERE gg.rawgrade IS NOT NULL
					AND gg.finalgrade = gi.grademin
					AND gg.overridden > 0
					AND gi.itemmodule IN('assign','forum','hsuforum','quiz','hvp','lti')";
		$activities = $DB->get_records_sql($sql);
		
		mtrace("... found " . count($activities) . " with overridden grades");
		
		foreach($activities as $activity) {
			//get the grade_item
			if (!$grade_item = grade_item::fetch(array('id' => $activity->itemid, 'courseid' => $activity->courseid))) {
			    mtrace("... could not fetch grade item $activity->itemid in course $activity->courseid");
			}
			
			//remove override
			if (!$grade_grade = grade_grade::fetch(array('userid' => $activity->userid, 'itemid' => $grade_item->id))) {
				mtrace("... could not fetch grade for user $activity->userid for grade item $grade_item->id");
			} else {
				$grade_grade->set_overridden(0);
				$grade_item->force_regrading(); //need to regrade since we're unoverriding
				mtrace("... removed override for user $activity->userid on grade item $grade_item->id");
			}
		}
		
		return true;
	}

}
