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

global $CFG;
require_once $CFG->dirroot.'/local/zerogrades/locallib.php';

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
		
		//get all assigns/activities past due/expected
		$y = strtotime("-1 days");
		$timebegin = mktime(0, 0, 0, date("m",$y), date("d",$y), date("Y",$y));
		$timeend = mktime(0,0,0);
		//look for dates from yesterday 00:00:00 AM to yesterday 23:59:59 PM
		
		zg_create_overrides_in_range($timebegin, $timeend);
		
		return true;
	}

}
