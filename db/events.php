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
 * Zendesk Integration plugin event handler definition.
 *
 * @package		local_zerogrades
 * @category	event
 * @copyright	2019 Nick Stefanski <nmstefanski@gmail.com>
 * @license		http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// List of observers.
$observers = array(

    array(
        'eventname'   => '\mod_assign\event\assessable_submitted',
        'callback'    => 'local_zerogrades_observer::assessable_submitted',
    ),
    array(
        'eventname'   => '\mod_quiz\event\attempt_submitted',
        'callback'    => 'local_zerogrades_observer::attempt_submitted',
    ),
    array(
        'eventname'   => '\mod_forum\event\post_created',
        'callback'    => 'local_zerogrades_observer::post_created',
    ),
    array(
        'eventname'   => '\mod_forum\event\post_updated',
        'callback'    => 'local_zerogrades_observer::post_updated',
    ),
    array(
        'eventname'   => '\mod_forum\event\discussion_created',
        'callback'    => 'local_zerogrades_observer::discussion_created',
    ),
    array(
        'eventname'   => '\mod_forum\event\discussion_updated',
        'callback'    => 'local_zerogrades_observer::discussion_updated',
    ),
);
