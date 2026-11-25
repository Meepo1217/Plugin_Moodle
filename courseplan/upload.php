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
 * Controller to upload teaching materials or link for a lesson item.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/classes/permission.php');
require_once(__DIR__ . '/classes/form/upload_form.php');

$id = required_param('id', PARAM_INT); // Item ID.

require_login();
$context = context_system::instance();

$item = $DB->get_record('local_courseplan_items', ['id' => $id], '*', MUST_EXIST);
$plan = $DB->get_record('local_courseplan_plans', ['id' => $item->planid], '*', MUST_EXIST);

// Verify that the user is either the Plan Leader/Manager or the Assigned Teacher for this item.
$isleader = \local_courseplan\permission::can_edit_plan($plan, (int)$USER->id);
$isassigned = ((int)$item->assigneeid === (int)$USER->id);
if (!$isleader && !$isassigned) {
    throw new moodle_exception('nopermissions', 'error', new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]), 'Chỉ Trưởng nhóm hoặc giáo viên được phân công mới có quyền tải lên file/link cho bài giảng này.');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseplan/upload.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');

$title = get_string('upload_or_link', 'local_courseplan') . ': ' . format_string($item->title);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($plan->name));

$PAGE->navbar->add(get_string('nav_title', 'local_courseplan'), new moodle_url('/local/courseplan/index.php'));
$PAGE->navbar->add(format_string($plan->shortname), new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]));
$PAGE->navbar->add($title);

$filemanageroptions = [
    'subdirs' => 0,
    'maxbytes' => 104857600, // 100MB
    'maxfiles' => 5,
    'accepted_types' => ['*'],
    'context' => $context,
];

// Prepare draft area.
$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $context->id, 'local_courseplan', 'attachment', $item->id, $filemanageroptions);

$data = new stdClass();
$data->id = $item->id;
$data->attachments = $draftitemid;
$data->resourceurl = $item->resourceurl;

$mform = new \local_courseplan\form\upload_form(null, ['id' => $id, 'filemanageroptions' => $filemanageroptions]);
$mform->set_data($data);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]));
} else if ($formdata = $mform->get_data()) {
    // Save draft area files to permanent file storage.
    file_save_draft_area_files($formdata->attachments, $context->id, 'local_courseplan', 'attachment', $item->id, $filemanageroptions);

    // Get the first uploaded file name to display on the board.
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'local_courseplan', 'attachment', $item->id, 'id DESC', false);
    $filename = '';
    if (!empty($files)) {
        $firstfile = reset($files);
        $filename = $firstfile->get_filename();
    }

    $item->filename = $filename;
    $item->resourceurl = trim($formdata->resourceurl ?? '');
    $item->timemodified = time();
    $item->usermodified = $USER->id;

    // If teacher uploaded a file and status was "To do", automatically advance to "In progress".
    if ((int)$item->status === 0 && (!empty($filename) || !empty($item->resourceurl))) {
        $item->status = 1;
    }

    $DB->update_record('local_courseplan_items', $item);

    \core\notification::success(get_string('changessaved'));
    redirect(new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]));
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
