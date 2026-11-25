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
 * Controller to create, edit or delete a Course Plan.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/classes/permission.php');
require_once(__DIR__ . '/classes/form/plan_form.php');

$id     = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', 'edit', PARAM_ALPHA);

require_login();
$context = context_system::instance();

if ($id) {
    $plan = $DB->get_record('local_courseplan_plans', ['id' => $id], '*', MUST_EXIST);
    if (!\local_courseplan\permission::can_edit_plan($plan, (int)$USER->id)) {
        throw new moodle_exception('nopermissions', 'error', '', 'Chỉ Người lập kế hoạch hoặc Quản trị viên mới có quyền chỉnh sửa/xóa kế hoạch này.');
    }
} else {
    if (!\local_courseplan\permission::can_create_plan((int)$USER->id)) {
        throw new moodle_exception('nopermissions', 'error', '', 'Chỉ Quản trị viên và Giáo viên mới có quyền tạo kế hoạch mới.');
    }
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseplan/edit_plan.php', ['id' => $id, 'action' => $action]));
$PAGE->set_pagelayout('standard');

if ($action === 'delete' && $id && confirm_sesskey()) {
    $transaction = $DB->start_delegated_transaction();
    $fs = get_file_storage();
    $items = $DB->get_records('local_courseplan_items', ['planid' => $id], '', 'id');
    if (!empty($items)) {
        $itemids = array_keys($items);
        [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_courseplan_comments', "itemid {$insql}", $inparams);
        foreach ($itemids as $iid) {
            $fs->delete_area_files($context->id, 'local_courseplan', 'attachment', $iid);
        }
    }
    $DB->delete_records('local_courseplan_items', ['planid' => $id]);
    $DB->delete_records('local_courseplan_sections', ['planid' => $id]);
    $DB->delete_records('local_courseplan_plans', ['id' => $id]);
    $transaction->allow_commit();

    \core\notification::success(get_string('changessaved'));
    redirect(new moodle_url('/local/courseplan/index.php'));
}

$title = $id ? get_string('edit') : get_string('add_plan', 'local_courseplan');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$PAGE->navbar->add(get_string('nav_title', 'local_courseplan'), new moodle_url('/local/courseplan/index.php'));
$PAGE->navbar->add($title);

$mform = new \local_courseplan\form\plan_form(null, ['id' => $id]);

if ($id && isset($plan)) {
    $mform->set_data($plan);
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/courseplan/index.php'));
} else if ($data = $mform->get_data()) {
    $now = time();
    if (empty($data->id)) {
        $record = new stdClass();
        $record->name = $data->name;
        $record->shortname = $data->shortname;
        $record->categoryid = $data->categoryid;
        $record->summary = $data->summary;
        $record->creatorid = $USER->id;
        $record->status = 0;
        $record->courseid = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $record->usermodified = $USER->id;

        $planid = $DB->insert_record('local_courseplan_plans', $record);

        // Process Excel file import if uploaded.
        $importSuccess = false;
        $importStats = null;
        $excelfile = $mform->save_temp_file('excelfile');
        if ($excelfile) {
            require_once(__DIR__ . '/classes/excel_importer.php');
            try {
                $importStats = \local_courseplan\excel_importer::import_into_plan($excelfile, $planid);
                $importSuccess = true;
            } catch (\Throwable $e) {
                \core\notification::warning('Lỗi khi đọc file Excel: ' . $e->getMessage() . '. Đã tạo kế hoạch với chương mặc định.');
            } finally {
                @unlink($excelfile);
            }
        }

        if (!$importSuccess || empty($importStats['items_count'])) {
            // Auto create first default section if no items were imported.
            if (!$DB->record_exists('local_courseplan_sections', ['planid' => $planid])) {
                $section = new stdClass();
                $section->planid = $planid;
                $section->title = 'Chương 1: Giới thiệu & Tổng quan học phần';
                $section->sortorder = 1;
                $section->timecreated = $now;
                $section->timemodified = $now;
                $DB->insert_record('local_courseplan_sections', $section);
            }
            \core\notification::success(get_string('changessaved'));
        } else {
            $msg = "Đã tạo kế hoạch và nhập thành công <strong>{$importStats['sections_count']} chương</strong>, <strong>{$importStats['items_count']} bài giảng</strong> từ file Excel (trạng thái: Chưa làm).";
            if (!empty($importStats['matched_teachers'])) {
                $msg .= "<br>• Đã tự động ghép và phân công " . count($importStats['matched_teachers']) . " giáo viên phụ trách.";
            }
            if (!empty($importStats['unmatched_teachers'])) {
                $msg .= "<br>• Chú ý: " . count($importStats['unmatched_teachers']) . " tên giáo viên chưa khớp tài khoản (" . implode(', ', $importStats['unmatched_teachers']) . ").";
            }
            \core\notification::success($msg);
        }

        redirect(new moodle_url('/local/courseplan/view.php', ['id' => $planid]));
    } else {
        $plan = $DB->get_record('local_courseplan_plans', ['id' => $data->id], '*', MUST_EXIST);
        $plan->name = $data->name;
        $plan->shortname = $data->shortname;
        $plan->categoryid = $data->categoryid;
        $plan->summary = $data->summary;
        $plan->timemodified = $now;
        $plan->usermodified = $USER->id;

        $DB->update_record('local_courseplan_plans', $plan);

        // Process Excel file import if uploaded when editing.
        $excelfile = $mform->save_temp_file('excelfile');
        if ($excelfile) {
            require_once(__DIR__ . '/classes/excel_importer.php');
            try {
                $importStats = \local_courseplan\excel_importer::import_into_plan($excelfile, $plan->id);
                \core\notification::success("Đã nhập bổ sung từ Excel: <strong>{$importStats['sections_count']} chương</strong>, <strong>{$importStats['items_count']} bài giảng</strong>.");
            } catch (\Throwable $e) {
                \core\notification::error('Lỗi khi đọc file Excel: ' . $e->getMessage());
            } finally {
                @unlink($excelfile);
            }
        } else {
            \core\notification::success(get_string('changessaved'));
        }

        redirect(new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]));
    }
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
