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
 * Library code for the zero grades local plugin.
 *
 * @package	local_zerogrades
 * @copyright	2019 Nick Stefanski <nmstefanski@gmail.com>
 * @license	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once $CFG->dirroot.'/grade/lib.php';
require_once $CFG->libdir.'/accesslib.php';
require_once $CFG->libdir.'/grade/grade_item.php';
require_once $CFG->dirroot.'/mod/forum/classes/grades/forum_gradeitem.php';
require_once $CFG->dirroot.'/lib/gradelib.php';
//use context_module;
//use grade_item;
use mod_forum\grades\forum_gradeitem;

/**
 * Remove grade override for specific grade
 *
 * @param string $itemmodule
 * @param int $iteminstance
 * @param int $courseid
 * @param int $userid
 * @return bool
 */
function zg_remove_override($itemmodule, $iteminstance, $courseid, $userid, $itemnumber = 0) {
	
	try {
	    if (!$grade_item = grade_item::fetch(array('itemmodule'=>$itemmodule,'itemnumber'=>$itemnumber,
			'iteminstance'=>$iteminstance,'courseid'=>$courseid))) {
		    //print_error('cannotfindgradeitem'); // Function deprecated.
		    throw new moodle_exception('cannotfindgradeitem');
		}
		$grade_grade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $grade_item->id));
		
		if($grade_grade->finalgrade == $grade_item->grademin && $grade_grade->overridden > 0){
			$grade_grade->set_overridden(0);
			$grade_item->force_regrading(); //need to regrade since we're unoverriding
			return true;
		}
	} catch (moodle_exception $e){
		return false;
	}
	
	return false;
}

/**
 * Automatically grade forums using a specific custom scale, giving points for all posts above a certain threshold
 *
 * @param int $forumid
 * @param int $courseid
 * @param int $userid
 * @return bool
 */
function zg_autograde_forum($forumid, $courseid, $userid){
	global $DB;
	$nick = $DB->get_record('user', array('id'=>'4'));//DEBUG
	
	try {
		$wfg = 2;
		while ($wfg > 0 && !$grade_item) {
			$wfg--;
			$grade_item = grade_item::fetch(array('itemmodule'=>'forum','itemnumber'=>$wfg,
				'iteminstance'=>$forumid,'courseid'=>$courseid));
		}
		if (!$grade_item) {
			//print_error('cannotfindgradeitem'); // Function deprecated.
			throw new moodle_exception('cannotfindgradeitem');
		}
		
		//check scaleid == 19 ("Like [4]")
		if (abs($grade_item->scaleid) == 19 || abs($grade_item->scaleid) == 46) {
			//get all forum posts by user in forum
			$sql = "SELECT p.* FROM mdl_forum_posts p JOIN mdl_forum_discussions d ON p.discussion = d.id
					WHERE p.userid = ? and d.forum = ?";
			$posts = $DB->get_records_sql($sql, [$userid,$forumid]);
			$wordcount = 10;
			$postcount = 4;
			
			foreach($posts as $post){
				//get word counts
				$post_wordcount = $post->wordcount ? $post->wordcount : count_words($post->message);
				if($post_wordcount >= $wordcount){
					$ct++;
				}
			}
			
			//apply finalgrade if student has enough posts with enough words
			if($ct >= $postcount){
				$finalgrade = $grade_item->grademax;
			} elseif($ct >= 1) {
				$finalgrade = ($grade_item->grademax - $grade_item->grademin) * $ct / $postcount + $grade_item->grademin;
			} else {
				//if no posts, override to current grade:
				// if the forum is overdue, it should have a zero grade (see scheduled task), which we want to keep
				// else, we want to override with a NULL grade to stop the automatic aggregation from taking effect
				$grade_grade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $grade_item->id));
				$finalgrade = $grade_grade->finalgrade;
			}
			
			//TK if wfg...
			if($wfg){
			//  if ct > 0, call zg_remove_override
				if($ct){
					$override = zg_remove_override('forum', $forumid, $courseid, $userid, 1);
				}
			//  then, call $grade_item->update_raw_grade
				$vaultfactory = mod_forum\local\container::get_vault_factory();
				$forumvault = $vaultfactory->get_forum_vault();
				$forum = $forumvault->get_from_id($forumid);
				$forumgradeitem = forum_gradeitem::load_from_forum_entity($forum);
				$gradeduser = \core_user::get_user($userid);
				$gg = new stdClass;
				$gg->grade = $finalgrade;
				return $forumgradeitem->store_grade_from_formdata($gradeduser, $gradeduser, $gg);
			//else...
			} else {
				return $grade_item->update_final_grade($userid, $finalgrade, 'local_zerogrades');
			}
		} else if (abs($grade_item->scaleid) == 84) {
			// Remove any overrides.
			$grade_grade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $grade_item->id));
			
			if($grade_grade->finalgrade == $grade_item->grademin && $grade_grade->overridden > 0){
				$grade_grade->set_overridden(0);
				$grade_item->force_regrading(); //need to regrade since we're unoverriding
				return true;
			}
		}
	} catch (moodle_exception $e){
		return false;
	}
	
	return false;
	
}

/**
 * Create grade overrides for given date range
 *
 * @param int $timebegin unix timestamp
 * @param int $timeend unix timestamp
 */
function zg_create_overrides_in_range($timebegin, $timeend) {
	global $DB;
	
	$asql = "SELECT concat(cm.id,'-',gg.groupid) as uuid, cm.id AS cmid, cm.course, gi.id AS itemid, gi.itemmodule, cm.instance, gg.groupid
				FROM {course_modules} cm
				JOIN {modules} m ON cm.module = m.id
				JOIN {grade_items} gi ON gi.iteminstance = cm.instance AND gi.itemmodule = m.name
				LEFT JOIN {assign} a ON a.id = cm.instance AND cm.module = 1
				LEFT JOIN {groupings_groups} gg on cm.groupingid = gg.groupingid
				WHERE gi.itemmodule IN ('assign','forum','hsuforum','quiz','hvp','lti') AND cm.visible = 1
					AND (gi.itemnumber = 1 OR gi.itemmodule <> 'forum')
					AND ( (cm.completionexpected >= $timebegin AND cm.completionexpected < $timeend)
					OR (a.duedate >= $timebegin AND a.duedate < $timeend) )";
	$activities = $DB->get_records_sql($asql);
	mtrace("... found " . count($activities) . " activities between $timebegin and $timeend ");

	$courses = array();

	//for each, get all users in that context who have not submitted
	foreach($activities as $activity) {
		mtrace("... working on $activity->itemmodule $activity->cmid");
		$context = context_module::instance($activity->cmid);
		list($esql, $params) = get_enrolled_sql($context, 'mod/assign:submit', $activity->groupid, $onlyactive = true);

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
			case "forum":	// Detect students with posts OR grades.
				$submitsql = "SELECT COUNT(p.id) + COUNT(gg.id) FROM {grade_items} gi
					LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id AND gg.finalgrade is not null
					LEFT JOIN {forum_discussions} d ON gi.iteminstance = d.forum
					LEFT JOIN {forum_posts} p ON p.discussion = d.id AND p.userid = u.id 
					WHERE gi.iteminstance = $activity->instance AND gi.itemmodule = '$activity->itemmodule' AND gi.itemnumber = 1";
				break;
			case "hvp":
			case "lti":
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
			$params = array('id' => $activity->itemid, 'courseid' => $activity->course);
			$params['itemnumber'] = ($activity->itemmodule == 'forum') ? 1 : 0;

			if (!$grade_item = grade_item::fetch($params)) {
				mtrace("... could not fetch grade item");
			} else {
				//set override
				foreach($users AS $user){
					$grade_item->update_final_grade($user->id, $finalgrade = 0, 'local_zerogrades');
					mtrace("... set zero grade override for user $user->id");
					$grade_item->force_regrading(); // TK force regrade just in case
				}
				//add course to list for grade recalc
				$courses[$activity->course] = $activity->course;
			}
		}
	}

	mtrace("... recalculating gradebooks as needed");
	foreach($courses as $courseid => $course) {
		mtrace("... checking for course $courseid");
		try {
			$course = $DB->get_record('course', array('id' => $courseid));
			grade_regrade_final_grades_if_required($course);
			mtrace("... check complete for course $courseid");
		} catch (moodle_exception $exception) {
			mtrace("error: " . $exception->getMessage() );
		}
	}
}

function zg_zoom_archive_forum($cmid, $userid) {
	global $DB;
	$scaleid = 84; // Set constant.
	
	// Get forum id / cm instance / grade item iteminstance  
	//   from first forum activity in same section with given scaleid.
	$sql = "select gi.id, gi.iteminstance, gi.grademax, gi.scaleid,
		  cm2.id as cmid, cm2.completion, cm2.completionpassgrade, cmc.id as cmc
		from {course_modules} cm
		join {course_sections} cs on cm.section = cs.id
		join {course_modules} cm2 on cm2.section = cs.id
		join {modules} m on cm2.module = m.id
		join {grade_items} gi on gi.itemmodule = m.name 
		  and gi.iteminstance = cm2.instance
		left join {course_modules_completion} cmc 
		  on cm2.id = cmc.coursemoduleid and cmc.userid = ?
		where m.name = 'forum' and cm.id = ? and gi.scaleid = ?";
	try {
		$gi = $DB->get_record_sql($sql, [$userid,$cmid,$scaleid]);
		
		if ($gi && $gi->iteminstance && $gi->grademax) {
			$forumid = $gi->iteminstance;
			$finalgrade = $gi->grademax;
			
			$vaultfactory = mod_forum\local\container::get_vault_factory();
			$forumvault = $vaultfactory->get_forum_vault();
			$forum = $forumvault->get_from_id($forumid);
			$forumgradeitem = forum_gradeitem::load_from_forum_entity($forum);
			$gradeduser = \core_user::get_user($userid);
			$gg = new stdClass;
			$gg->grade = $finalgrade;
			$result = $forumgradeitem->store_grade_from_formdata($gradeduser, $gradeduser, $gg);
			$grade_item = grade_item::fetch(array('id'=>$gi->id));
			$grade_item->force_regrading(); // TK force regrade just in case

			// update completion if applicable
			if ($gi->completion == 2) {
				$cmc = (object) array('coursemoduleid' => $gi->cmid, 
						      'userid' => $userid,
						      'completionstate' => $gi->completionpassgrade + 1,
						      'overrideby' => 1,
						      'timemodified' => time() );
				if ($gi->cmc > 0) {
					// update cmc
					$cmc->id = $gi->cmc;
					$DB->update_record('course_modules_completion', $cmc);
				} else {
					// insert new cmc
					$DB->insert_record('course_modules_completion', $cmc);
				}
			}
			return $result;
		} else {
			return false;
		}
	} catch(moodle_exception $e) {
		return false;
	}
}
