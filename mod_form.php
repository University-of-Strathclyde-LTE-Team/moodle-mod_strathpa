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


require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_strathpa_mod_form extends moodleform_mod {

    public function definition() {
        global $CFG, $COURSE;
        $mform =& $this->_form;

        //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), array('size'=>'48'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');

        $assignments = array();
        $assignments[0] = get_string('noassignment', 'strathpa');
        if ($raw_assignments = get_coursemodules_in_course('assignment', $COURSE->id)) {
            foreach ($raw_assignments as $a) {
                $assignments[$a->id] = $a->name;
            }
        }

        $mform->addElement('select', 'assignment',
                get_string('assignment', 'strathpa'),
                $assignments,
                array('optional' => true)
        );
        $mform->addHelpButton('assignment', 'assignment', 'strathpa');

        $this->add_intro_editor(true, get_string('introduction', 'strathpa'));

        $mform->addElement('selectyesno', 'canedit', get_string('canedit', 'strathpa'));
        $mform->addElement('header', 'additionalinfo', get_string('additionalinfoheader', 'strathpa'));
        $mform->addElement('html', get_string('additionalinfo', 'strathpa'));

        $mform->addElement('header', 'advancedsettings', 'Advanced');
        $options=array();
        $options[0]  = get_string('oncefrequency', 'strathpa');

        $options[1]  = get_string('weeklyfrequency', 'strathpa');

        $mform->addElement('select', 'frequency', get_string('frequency', 'strathpa'), $options);

        $mform->addElement('date_time_selector', 'timeavailable',
                get_string('availablefrom', 'strathpa'),
                array('optional' => true)
        );
        $mform->addElement('date_time_selector', 'timedue',
                get_string('submissiondate', 'strathpa'),
                array('optional' => true)
        );

        $mform->setAdvanced('advancedsettings');
        $mform->addElement('text', 'lowerbound', get_string('lowerbound', 'strathpa'), array('value' => '2.5'));
        $mform->addHelpButton('lowerbound', 'lowerbound', 'strathpa');
        $mform->addElement('text', 'upperbound', get_string('upperbound', 'strathpa'), array('value' => '3.5'));
        $mform->addHelpButton('upperbound', 'upperbound', 'strathpa');

        $mform->addRule('lowerbound', 'Must be numeric', 'numeric', null, 'client');
        $mform->addRule('upperbound', 'Must be numeric', 'numeric', null, 'client');

        $this->standard_coursemodule_elements();

        $this->add_action_buttons();
    }
}
