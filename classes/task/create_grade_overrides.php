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

global $CFG, $DB;
		
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->libdir.'/accesslib.php';
require_once $CFG->libdir.'/grade/grade_item.php';
use context_module;
use grade_item;

defined('MOODLE_INTERNAL') || die();

class create_grade_overrides extends \core\task\scheduled_task {

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('create_grade_overrides', 'local_zerogrades');
    }
	
	public function execute() {
		mtrace("... creating zero grade overrides ...");
		
		global $CFG, $DB;
		
		//get all assigns/activities past due/expected
		$y = strtotime("-1 days");
		$timebegin = mktime(0, 0, 0, date("m",$y), date("d",$y), date("Y",$y));
		$timeend = mktime(0,0,0);
		
		$asql = "SELECT cm.id AS cmid, cm.course, gi.id AS itemid, gi.itemmodule, cm.instance
				FROM {course_modules} cm
				JOIN {modules} m ON cm.module = m.id
				JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = m.name
				LEFT JOIN {assign} a ON a.id = cm.instance AND cm.module = 1
				WHERE gi.itemmodule IN ('assign','forum','hsuforum','quiz','hvp') AND cm.visible = 1
					AND ( (cm.completionexpected >= $timebegin AND cm.completionexpected < $timeend)
					OR (a.duedate >= $timebegin AND a.duedate < $timeend) )";
		$activities = $DB->get_records_sql($asql);
		mtrace("... found " . count($activities) . " activities past due / past expected completion");
		
		//for each, get all users in that context who have not submitted
		foreach($activities as $activity) {
			mtrace("... working on $activity->itemmodule $activity->cmid");
			$context = context_module::instance($activity->cmid);
			list($esql, $params) = get_enrolled_sql($context, 'mod/assign:submit', $groupid = 0, $onlyactive = true);
			
			//get correct table for user submissions / attempts / posts
			switch ($activity->itemmodule){
				case "assign":
					$submitsql = "SELECT COUNT(*) FROM {assign_submission}
						WHERE userid = u.id AND assignment = $activity->instance AND status = 'submitted'";
					break;
				case "quiz":
					$submitsql = "SELECT COUNT(*) FROM {quiz_attempts}
						WHERE userid = u.id AND quiz = $activity->instance AND state = 'finished'";
					break;
				case "forum":	//override null grades only -- students who have made enough posts should have a grade by now
				/*	$submitsql = "SELECT COUNT(*) FROM {grade_grades} gg JOIN {grade_items} gi ON gg.itemid = gi.id
						WHERE gi.itemmodule = '$activity->itemmodule' AND gg.finalgrade IS NOT NULL
							AND gg.userid = u.id AND gi.iteminstance = $activity->instance";*/
				case "hsuforum":
					$submitsql = "SELECT COUNT(*) FROM {" . $activity->itemmodule . "_posts} p
						JOIN {". $activity->itemmodule . "_discussions} d ON p.discussion = d.id
						WHERE p.userid = u.id AND d.forum = $activity->instance";
					break;
				case "hvp":
					$submitsql = "SELECT COUNT(*) FROM {grade_grades} gg JOIN {grade_items} gi ON gg.itemid = gi.id
						WHERE gi.itemmodule = '$activity->itemmodule' AND gg.rawgrade IS NOT NULL
							AND gg.userid = u.id AND gi.iteminstance = $activity->instance";
					break;
				default:
					$submitsql = "1";	//no results
			}
			
			$usql = "SELECT DISTINCT u.id
					FROM {user} u
					JOIN ($esql) je ON je.id = u.id
					WHERE ($submitsql) = 0";
			//mtrace("... debug sql: $usql");
			
			$users = $DB->get_records_sql($usql, $params);
			mtrace("... found " . count($users) . " users for $activity->itemmodule $activity->cmid");
			
			if ($users) {
				//get the grade_item
				if (!$grade_item = grade_item::fetch(array('id' => $activity->itemid, 'courseid' => $activity->course))) {
				    mtrace("... could not fetch grade item");
				}
				
				//set override
				foreach($users AS $user){
					$grade_item->update_final_grade($user->id, $finalgrade = 0, 'local_zerogrades');
					mtrace("... set zero grade override for user $user->id");
				}
			}
		}
		
		return true;
	}

}