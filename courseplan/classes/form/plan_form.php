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
 * Form for creating and editing a course plan.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plan_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        global $DB;

        $mform = $this->_form;
        $id = $this->_customdata['id'] ?? 0;

        $mform->addElement('header', 'general', get_string('general'));

        $mform->addElement('hidden', 'id', $id);
        $mform->setType('id', PARAM_INT);

        // Plan Name.
        $mform->addElement('text', 'name', get_string('plan_name', 'local_courseplan'), ['size' => '50']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Short Name / Course Code.
        $mform->addElement('text', 'shortname', get_string('plan_code', 'local_courseplan'), ['size' => '20']);
        $mform->setType('shortname', PARAM_TEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');

        // Course Category.
        $categories = $DB->get_records_menu('course_categories', null, 'sortorder ASC', 'id, name');
        if (empty($categories)) {
            $categories = [1 => 'Default category'];
        }
        $mform->addElement('select', 'categoryid', get_string('category'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->setDefault('categoryid', 1);

        // Summary.
        $mform->addElement('textarea', 'summary', get_string('summary'), ['rows' => 4, 'cols' => 50]);
        $mform->setType('summary', PARAM_TEXT);

        // -------------------------------------------------------------------------
        // Nhập cấu trúc giáo trình từ file Excel / CSV (Tùy chọn)
        // -------------------------------------------------------------------------
        $mform->addElement('header', 'import_section', 'Nhập danh sách bài giảng từ file Excel (Tùy chọn)');

        $templateUrlXlsx = (new \moodle_url('/local/courseplan/download_template.php', ['format' => 'xlsx']))->out(false);
        $templateUrlCsv  = (new \moodle_url('/local/courseplan/download_template.php', ['format' => 'csv']))->out(false);

        $helpHtml = '
        <div style="font-family: \'Times New Roman\', Times, serif; font-size: 15px; color: #000000; line-height: 1.6; border: 1px solid #000000; padding: 12px 15px; margin-bottom: 15px; background: #ffffff; border-radius: 4px;">
            <div style="font-weight: bold; margin-bottom: 6px;">Hướng dẫn định dạng file Excel:</div>
            <div style="margin-bottom: 4px;">• Cột hỗ trợ: STT | Tên môn (tùy chọn) | Chương | Tên bài | Tên giáo viên | Cần nộp file (Có/Không).</div>
            <div style="margin-bottom: 4px;">• Cột Cần nộp file: Trong file Excel đã tích hợp sẵn hộp chọn thả xuống (Select Box) để chọn nhanh <strong>Có</strong> hoặc <strong>Không</strong>.</div>
            <div style="margin-bottom: 4px;">• Cột Trạng thái không cần nhập (hệ thống tự động gán mặc định là Chưa làm).</div>
            <div style="margin-bottom: 10px;">• Hệ thống tự động ghép Tên giáo viên với tài khoản đã cấp trên Moodle (dựa theo Họ tên, Username hoặc Email).</div>
            <div>
                <a href="' . $templateUrlXlsx . '" class="btn btn-sm" style="font-family: \'Times New Roman\', Times, serif; font-size: 14px; font-weight: bold; background: #217346; color: #ffffff; border: 1px solid #217346; padding: 5px 14px; border-radius: 4px; text-decoration: none; display: inline-block;">Tải file mẫu Excel (.xlsx)</a>
                <a href="' . $templateUrlCsv . '" class="btn btn-sm" style="font-family: \'Times New Roman\', Times, serif; font-size: 14px; font-weight: bold; background: #2b579a; color: #ffffff; border: 1px solid #2b579a; padding: 5px 14px; border-radius: 4px; text-decoration: none; display: inline-block; margin-left: 8px;">Tải file mẫu CSV</a>
            </div>
        </div>';

        $mform->addElement('static', 'import_help', '', $helpHtml);

        $filepickeroptions = [
            'subdirs' => 0,
            'maxbytes' => 20971520, // 20MB
            'maxfiles' => 1,
            'accepted_types' => ['.xlsx', '.xls', '.csv'],
        ];
        $mform->addElement('filepicker', 'excelfile', 'File Excel giáo trình', null, $filepickeroptions);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

