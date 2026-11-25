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
 * Controller to create, edit or delete a section in a course plan.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/classes/permission.php');
require_once(__DIR__ . '/classes/form/section_form.php');

$planid = required_param('planid', PARAM_INT);
$id     = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', 'edit', PARAM_ALPHA);

require_login();
$context = context_system::instance();

$plan = $DB->get_record('local_courseplan_plans', ['id' => $planid], '*', MUST_EXIST);
if (!\local_courseplan\permission::can_edit_plan($plan, (int)$USER->id)) {
    throw new moodle_exception('nopermissions', 'error', '', 'Chỉ Người lập kế hoạch hoặc Quản trị viên mới có quyền thêm/sửa/xóa chương này.');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseplan/edit_section.php', ['planid' => $planid, 'id' => $id, 'action' => $action]));
$PAGE->set_pagelayout('standard');

if ($action === 'delete' && $id && confirm_sesskey()) {
    $transaction = $DB->start_delegated_transaction();
    $fs = get_file_storage();
    $items = $DB->get_records('local_courseplan_items', ['sectionid' => $id, 'planid' => $planid], '', 'id');
    if (!empty($items)) {
        $itemids = array_keys($items);
        [$insql, $inparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_courseplan_comments', "itemid {$insql}", $inparams);
        foreach ($itemids as $iid) {
            $fs->delete_area_files($context->id, 'local_courseplan', 'attachment', $iid);
        }
    }
    $DB->delete_records('local_courseplan_items', ['sectionid' => $id, 'planid' => $planid]);
    $DB->delete_records('local_courseplan_sections', ['id' => $id, 'planid' => $planid]);
    $transaction->allow_commit();

    \core\notification::success(get_string('changessaved'));
    redirect(new moodle_url('/local/courseplan/view.php', ['id' => $planid]));
}

$title = $id ? get_string('edit') : get_string('add_section', 'local_courseplan');
$PAGE->set_title($title);
$PAGE->set_heading(format_string($plan->name));

$PAGE->navbar->add(get_string('nav_title', 'local_courseplan'), new moodle_url('/local/courseplan/index.php'));
$PAGE->navbar->add(format_string($plan->shortname), new moodle_url('/local/courseplan/view.php', ['id' => $planid]));
$PAGE->navbar->add($title);

$mform = new \local_courseplan\form\section_form(null, ['planid' => $planid, 'id' => $id]);

if ($id) {
    $sec = $DB->get_record('local_courseplan_sections', ['id' => $id, 'planid' => $planid], '*', MUST_EXIST);
    $mform->set_data($sec);
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/courseplan/view.php', ['id' => $planid]));
} else if ($data = $mform->get_data()) {
    $now = time();
    if (empty($data->id)) {
        $maxsort = $DB->get_field_sql('SELECT MAX(sortorder) FROM {local_courseplan_sections} WHERE planid = ?', [$planid]);
        $record = new stdClass();
        $record->planid = $planid;
        $record->title = $data->title;
        $record->sortorder = $maxsort ? ($maxsort + 1) : 1;
        $record->timecreated = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_courseplan_sections', $record);
    } else {
        $sec = $DB->get_record('local_courseplan_sections', ['id' => $data->id, 'planid' => $planid], '*', MUST_EXIST);
        $sec->title = $data->title;
        $sec->timemodified = $now;
        $DB->update_record('local_courseplan_sections', $sec);
    }
    \core\notification::success(get_string('changessaved'));
    redirect(new moodle_url('/local/courseplan/view.php', ['id' => $planid]));
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
