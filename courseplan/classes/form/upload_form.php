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
 * Form for uploading teaching materials or attaching links.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $id = $this->_customdata['id'] ?? 0;
        $filemanageroptions = $this->_customdata['filemanageroptions'] ?? [];

        $mform->addElement('header', 'general', get_string('upload_or_link', 'local_courseplan'));

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        // Moodle Core Filemanager.
        $mform->addElement('filemanager', 'attachments', 'Tài liệu biên soạn (PDF, PPTX, DOCX, ZIP)', null, $filemanageroptions);

        // Or URL Link.
        $mform->addElement('url', 'resourceurl', 'Hoặc chèn Link học liệu / tài liệu trực tuyến', ['size' => '50']);
        $mform->setType('resourceurl', PARAM_URL);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

