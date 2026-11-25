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

namespace local_courseplan\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing a course plan section.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $planid = $this->_customdata['planid'] ?? 0;
        $id = $this->_customdata['id'] ?? 0;

        $mform->addElement('header', 'general', get_string('general'));

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'planid', $planid);
        $mform->setType('planid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('add_section', 'local_courseplan'), ['size' => '50']);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

