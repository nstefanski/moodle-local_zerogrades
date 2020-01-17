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
 * @package		local_zerogrades
 * @copyright	2019 Nick Stefanski <nmstefanski@gmail.com>
 * @license		http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extract all the placeholder names from the SQL.
 * @param string $itemmodule
 * @param int $iteminstance
 * @param int $courseid
 * @param int $userid
 * @return bool
 */
function zg_remove_override($itemmodule, $iteminstance, $courseid, $userid) {
	global $CFG;
	require_once $CFG->dirroot.'/grade/lib.php';
	
	try {
	    if (!$grade_item = grade_item::fetch(array('itemmodule'=>$itemmodule,'iteminstance'=>$iteminstance,'courseid'=>$courseid))) {
	    	//email_to_user($nick, $nick, "Can't get grade item for $itemmodule, $iteminstance, $courseid, $userid", "", "" );
		    print_error('cannotfindgradeitem');
		}
		$grade_grade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $grade_item->id));
		//email_to_user($nick, $nick, "Got Grade", serialize($grade_grade), serialize($grade_grade) );
		
		if($grade_grade->finalgrade == 0 && $grade_grade->overridden > 0){
			$grade_grade->set_overridden(0);
			$grade_item->force_regrading(); //need to regrade since we're unoverriding
			return true;
		}
	} catch (moodle_exception $e){
		return false;
	}
	
	return false;
}

function zg_autograde_forum($forumid, $courseid, $userid){
	global $CFG, $DB;
	require_once $CFG->dirroot.'/grade/lib.php';
	//$nick = $DB->get_record('user', array('id'=>'4'));//DEBUG
	
	try {
	    if (!$grade_item = grade_item::fetch(array('itemmodule'=>'forum','iteminstance'=>$forumid,'courseid'=>$courseid))) {
	    	//email_to_user($nick, $nick, "Can't get grade item for forum, $iteminstance, $courseid, $userid", "", "" );
		    print_error('cannotfindgradeitem');
		}
		//email_to_user($nick, $nick, "Forum grade item $forumid, $courseid, $userid, $grade_item->scaleid", serialize($grade_item), serialize($grade_item) );
		// grade item not needed?
		//$grade_grade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $grade_item->id));
		
		//check scaleid == 18 ("Like []")
		if(abs($grade_item->scaleid) == 18){
			//get all forum posts by user in forum
			$sql = "SELECT p.* FROM mdl_forum_posts p JOIN mdl_forum_discussions d ON p.discussion = d.id
					WHERE p.userid = ? and d.forum = ?";
			$posts = $DB->get_records_sql($sql, [$userid,$forumid]);
			$wordcount = 10;
			$postcount = 4;
			
			foreach($posts as $post){
				//get word counts
				if(count_words($post->message) >= $wordcount){
					$ct++;
				}
			}
			
			//apply finalgrade if student has enough posts with enough words
			if($ct >= $postcount){
				$finalgrade = $grade_item->grademax;
			} else {
				//if not enough posts, override to current grade:
				// if the forum is overdue, it should have a zero grade (see scheduled task), which we want to keep
				// else, we want to override with a NULL grade to stop the automatic aggregation from taking effect
				$grade_grade = grade_grade::fetch(array('userid' => $userid, 'itemid' => $grade_item->id));
				$finalgrade = $grade_grade->finalgrade;
			}
			
			return $grade_item->update_final_grade($userid, $finalgrade, 'local_zerogrades');
		}
	} catch (moodle_exception $e){
		return false;
	}
	
	return false;
	
}