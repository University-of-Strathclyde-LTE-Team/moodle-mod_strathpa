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
 *
 * @package 	moodle-mod
 * @subpackage 	strathpa
 * @copyright 	2012 University of Strathclyde
 * @license    	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */



require_once($CFG->libdir.'/pagelib.php');

define('PAGE_STRATHPA_VIEW', 'mod-strathpa-view');

page_map_class(PAGE_STRATHPA_VIEW, 'page_strathpa');

$definedpages = array(PAGE_STRATHPA_VIEW);    //@todo what does this do?

/**
 * Class that models the behavior of a chat
 *
 * @author Jon Papaioannou
 * @package pages
 */

class page_strathpa extends page_generic_activity {

    public function init_quick($data) {
        if (empty($data->pageid)) {
            error('Cannot quickly initialize page: empty course id');
        }
        $this->activityname = 'strathpa';
        parent::init_quick($data);
    }

    public function get_type() {
        return PAGE_STRATHPA_VIEW;
    }
}
