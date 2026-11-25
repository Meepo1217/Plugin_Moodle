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
 * Controller to import course plan structure from Excel / CSV into an existing plan.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/classes/permission.php');
require_once(__DIR__ . '/classes/excel_importer.php');
require_once(__DIR__ . '/classes/form/import_form.php');

$planid = required_param('planid', PARAM_INT);

require_login();
$context = context_system::instance();

$plan = $DB->get_record('local_courseplan_plans', ['id' => $planid], '*', MUST_EXIST);

// Permission check: Planner/Leader or Site Admin.
if (!\local_courseplan\permission::can_edit_plan($plan, (int)$USER->id)) {
    throw new moodle_exception('nopermissions', 'error', new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]), 'Chỉ Người lập kế hoạch hoặc Quản trị viên mới có quyền nhập dữ liệu từ Excel.');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseplan/import.php', ['planid' => $planid]));
$PAGE->set_pagelayout('standard');

$title = 'Nhập bài giảng từ Excel: ' . format_string($plan->name);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($plan->name));

$PAGE->navbar->add(get_string('nav_title', 'local_courseplan'), new moodle_url('/local/courseplan/index.php'));
$PAGE->navbar->add(format_string($plan->shortname), new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]));
$PAGE->navbar->add('Nhập từ Excel');

$mform = new \local_courseplan\form\import_form(null, ['planid' => $planid]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]));
} else if ($data = $mform->get_data()) {
    $excelfile = $mform->save_temp_file('excelfile');
    if ($excelfile) {
        try {
            $stats = \local_courseplan\excel_importer::import_into_plan($excelfile, $plan->id);
            $msg = "Đã nhập thành công <strong>{$stats['sections_count']} chương</strong> và <strong>{$stats['items_count']} bài giảng</strong> từ file Excel (trạng thái: Chưa làm).";
            if (!empty($stats['matched_teachers'])) {
                $msg .= "<br>• Đã tự động ghép và phân công " . count($stats['matched_teachers']) . " giáo viên phụ trách.";
            }
            if (!empty($stats['unmatched_teachers'])) {
                $msg .= "<br>• Chú ý: Có " . count($stats['unmatched_teachers']) . " tên giáo viên chưa khớp tài khoản (" . implode(', ', $stats['unmatched_teachers']) . ").";
            }
            \core\notification::success($msg);
        } catch (\Throwable $e) {
            \core\notification::error('Lỗi khi xử lý file Excel: ' . $e->getMessage());
        } finally {
            @unlink($excelfile);
        }
    }

    redirect(new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]));
}

echo $OUTPUT->header();

$backurl = new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]);
echo html_writer::div(
    html_writer::link($backurl, '&larr; Quay lại Kế hoạch', ['class' => 'btn btn-outline-secondary btn-sm mb-3']),
    'mb-3'
);

$mform->display();

echo $OUTPUT->footer();
