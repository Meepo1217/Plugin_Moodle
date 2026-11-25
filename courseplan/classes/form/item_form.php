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
 * Form for creating and editing a lesson task in a course plan.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $planid    = $this->_customdata['planid'] ?? 0;
        $sectionid = $this->_customdata['sectionid'] ?? 0;
        $id        = $this->_customdata['id'] ?? 0;

        $mform->addElement('header', 'general', get_string('general'));

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'planid', $planid);
        $mform->setType('planid', PARAM_INT);

        $sections = $DB->get_records_menu('local_courseplan_sections', ['planid' => $planid], 'sortorder ASC', 'id, title');
        $mform->addElement('select', 'sectionid', get_string('add_section', 'local_courseplan'), $sections);
        $mform->setType('sectionid', PARAM_INT);
        $mform->setDefault('sectionid', $sectionid);

        $mform->addElement('text', 'title', get_string('item_title', 'local_courseplan'), ['size' => '50']);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        // Teacher Assignment (Assignee) using Moodle's standard fullname() helper.
        $users = $DB->get_records_select(
            'user',
            'deleted = 0 AND suspended = 0 AND id > 1',
            null,
            'firstname ASC, lastname ASC',
            'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename'
        );
        $teachermenu = [0 => get_string('none')];
        foreach ($users as $u) {
            $teachermenu[$u->id] = fullname($u);
        }
        $mform->addElement('select', 'assigneeid', get_string('assignee', 'local_courseplan'), $teachermenu);
        $mform->setType('assigneeid', PARAM_INT);

        // Quality Gate: Require file or link.
        $mform->addElement('advcheckbox', 'requirefile', get_string('file_required_badge', 'local_courseplan'), 'Yêu cầu bắt buộc nộp file hoặc link');
        $mform->setDefault('requirefile', 1);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
