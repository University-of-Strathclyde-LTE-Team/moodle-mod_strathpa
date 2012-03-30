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
  $capabilities = array(
    'mod/strathpa:usepeerassessment' => array(
      'riskbitmask' =>  0,
      'captype'     =>  'read',
      'contextlevel'=>  CONTEXT_MODULE,
      'archetypes'      =>  array(
      )
    ),
    'mod/strathpa:recordrating' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'write',
      'contextlevel'=>  CONTEXT_MODULE,
      'archetypes'      =>  array(
        'student'   =>  CAP_ALLOW,
      )
    ),
    'mod/strathpa:viewreport' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'read',
      'contextlevel'=>  CONTEXT_MODULE,
      'archetypes'      =>  array(
          'manager' =>CAP_ALLOW,
          'teacher' => CAP_ALLOW
      )
    ),
    'mod/strathpa:deleteratings' => array(
      'riskbitmask' =>  RISK_PERSONAL,
      'captype'     =>  'write',
      'contextlevel'=>  CONTEXT_MODULE,
      'archetypes'      =>  array(
          'manager' =>CAP_ALLOW,
          'teacher' => CAP_ALLOW
      )
    ),
  );
