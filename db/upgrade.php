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
function xmldb_strathpa_upgrade($oldversion=0) {
    global $CFG, $THEME, $DB;
    $dbman = $DB->get_manager();
    $result = true;
    /*
    if ($result && $oldversion < 2010091407) {

    /// Define field lowerbound to be added to strathpa
        $table = new xmldb_table('strathpa');
        $field = new xmldb_field('lowerbound');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '3, 2', XMLDB_UNSIGNED, null, null, null, null, '2.5', 'canedit');

    /// Launch add field lowerbound
        $result = $result && add_field($table, $field);


    /// Define field upperbound to be added to strathpa
        $table = new XMLDBTable('strathpa');
        $field = new XMLDBField('upperbound');
        $field->setAttributes(XMLDB_TYPE_NUMBER, '3, 2', XMLDB_UNSIGNED, null, null, null, null, '3.5', 'lowerbound');

    /// Launch add field upperbound
        $result = $result && add_field($table, $field);
    }
    */
    if ($result && $oldversion < 2010120304) {

        // Define table classcatalogue to be created
        $table = new xmldb_table('peerassessment');

        $field = new xmldb_field('course', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, null, null, null, 'cachelastupdated');

        // Conditionally launch add field course
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, 'small', null, null, null, null, 'course');

        // Conditionally launch add field intro
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, 0, 'intro');

        // Conditionally launch add field introformat
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // classcatalogue savepoint reached
        upgrade_mod_savepoint(true, 2010120304, 'peerassessment');
    }
    //upgrade to 2012032700. At which point the mod becomes strathpa
    if ($result && $oldversion < 2012032700) {
        // Define table strathpa to be renamed to NEWNAMEGOESHERE
        $table = new xmldb_table('peerassessment');

        // Launch rename table for strathpa
        $dbman->rename_table($table, 'strathpa');

        $rtable = new xmldb_table('peerassessment_ratings');
        // Launch rename table for strathpa
        $dbman->rename_table($rtable, 'strathpa_ratings');

        $ctable = new xmldb_table('peerassessment_comments');

        // Launch rename table for strathpa
        $dbman->rename_table($ctable, 'strathpa_comments');
        // strathpa savepoint reached
        upgrade_mod_savepoint(true, 2012032700, 'strathpa');
    }

    return $result;

}

