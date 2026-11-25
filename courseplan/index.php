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
 * Screen 1: Course Syllabus Plans List (Danh sách Kế hoạch - Bảng chuẩn Moodle).
 * Only visible to Admin and Teachers; teachers only see plans they participate in.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/permission.php');

require_login();
$context = context_system::instance();

// 1. Permission check: Only Admin and Teachers can access this page.
if (!\local_courseplan\permission::is_admin_or_teacher((int)$USER->id)) {
    throw new moodle_exception('nopermissions', 'error', '', 'Chỉ Quản trị viên và Giáo viên mới có quyền truy cập Kế hoạch giáo trình.');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseplan/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('plan_list_heading', 'local_courseplan'));
$PAGE->set_heading(get_string('plan_list_heading', 'local_courseplan'));
$PAGE->set_other_editing_capability(['local/courseplan:manage', 'local/courseplan:contribute']);

// Seed initial demo plans if database is completely empty and user is admin.
if (\local_courseplan\permission::is_admin((int)$USER->id)) {
    \local_courseplan\sample_seeder::ensure_seeded((int)$USER->id);
}

$isediting = $PAGE->user_is_editing();
$canmanage = \local_courseplan\permission::can_create_plan((int)$USER->id);

// 2. Fetch plans: Admin sees all; Teacher sees only plans they participate in.
$plans = \local_courseplan\permission::get_user_plans((int)$USER->id);

// Build standard Moodle html_table with mandatory "Người lập kế hoạch" column.
$table = new html_table();
$table->head = [
    get_string('plan_code', 'local_courseplan'),
    get_string('plan_name', 'local_courseplan'),
    get_string('creator', 'local_courseplan'),
    get_string('progress', 'local_courseplan'),
    get_string('plan_status', 'local_courseplan'),
    get_string('created_date', 'local_courseplan'),
    get_string('actions', 'local_courseplan'),
];
$table->attributes['class'] = 'generaltable table table-hover align-middle';
$table->data = [];

foreach ($plans as $plan) {
    $creator = $DB->get_record('user', ['id' => $plan->creatorid]);
    if ($creator) {
        $creatorcell = html_writer::div(
            $OUTPUT->user_picture($creator, ['size' => 35, 'class' => 'me-2']) .
            html_writer::tag('span', fullname($creator), ['class' => 'fw-semibold']),
            'd-flex align-items-center'
        );
    } else {
        $creatorcell = '-';
    }

    $totalitems = $DB->count_records('local_courseplan_items', ['planid' => $plan->id]);
    $doneitems = $DB->count_records('local_courseplan_items', ['planid' => $plan->id, 'status' => 3]);
    $percent = $totalitems > 0 ? (int)round(($doneitems / $totalitems) * 100) : 0;
    $barcolor = ($percent === 100) ? 'bg-success' : 'bg-primary';

    $progresscell = html_writer::div(
        html_writer::div(
            html_writer::div($percent . '%', "progress-bar {$barcolor}", [
                'role' => 'progressbar',
                'style' => "width: {$percent}%;",
                'aria-valuenow' => $percent,
                'aria-valuemin' => 0,
                'aria-valuemax' => 100,
            ]),
            'progress mb-1',
            ['style' => 'height: 16px; min-width: 140px;']
        ) .
        html_writer::tag('small', "{$doneitems}/{$totalitems} hoàn tất", ['class' => 'text-muted']),
        ''
    );

    if ((int)$plan->status === 1) {
        $statusbadge = html_writer::tag(
            'span',
            get_string('plan_completed_badge', 'local_courseplan'),
            ['class' => 'badge bg-success']
        );
    } else {
        $statusbadge = html_writer::tag(
            'span',
            get_string('plan_inprogress_badge', 'local_courseplan'),
            ['class' => 'badge bg-info text-dark']
        );
    }

    $viewurl = new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]);
    $namelink = html_writer::link($viewurl, format_string($plan->name), ['class' => 'aalink fw-bold']) .
        html_writer::tag('div', format_string($plan->summary ?? ''), ['class' => 'small text-muted text-truncate', 'style' => 'max-width: 320px;']);

    $actionhtml = html_writer::link(
        $viewurl,
        get_string('view_plan', 'local_courseplan'),
        ['class' => 'btn btn-outline-primary btn-sm me-1']
    );

    if ((int)$plan->status === 1 && !empty($plan->courseid) && $DB->record_exists('course', ['id' => $plan->courseid])) {
        $courseurl = new moodle_url('/course/view.php', ['id' => $plan->courseid]);
        $actionhtml .= html_writer::link(
            $courseurl,
            $OUTPUT->pix_icon('i/course', 'Course'),
            ['class' => 'btn btn-outline-success btn-sm me-1', 'title' => get_string('plan_completed_badge', 'local_courseplan')]
        );
    }

    // Only plan creator or site admin can edit/delete this plan in edit mode.
    if ($isediting && \local_courseplan\permission::can_edit_plan($plan, (int)$USER->id)) {
        $editurl = new moodle_url('/local/courseplan/edit_plan.php', ['id' => $plan->id]);
        $deleteurl = new moodle_url('/local/courseplan/edit_plan.php', [
            'id' => $plan->id,
            'action' => 'delete',
            'sesskey' => sesskey(),
        ]);
        $actionhtml .= html_writer::link(
            $editurl,
            $OUTPUT->pix_icon('t/edit', get_string('edit')),
            ['class' => 'btn btn-icon btn-sm me-1', 'title' => get_string('edit')]
        );
        $actionhtml .= html_writer::link(
            $deleteurl,
            $OUTPUT->pix_icon('t/delete', get_string('delete')),
            [
                'class' => 'btn btn-icon btn-sm text-danger',
                'title' => get_string('delete'),
                'onclick' => "return confirm('Bạn có chắc chắn muốn xóa kế hoạch này?');",
            ]
        );
    }

    $table->data[] = [
        html_writer::tag('span', s($plan->shortname), ['class' => 'badge bg-secondary']),
        $namelink,
        $creatorcell,
        $progresscell,
        $statusbadge,
        userdate($plan->timecreated, '%d/%m/%Y'),
        html_writer::div($actionhtml, 'd-flex align-items-center text-nowrap'),
    ];
}

$hasplans = !empty($plans);
$emptymessage = \local_courseplan\permission::is_admin((int)$USER->id)
    ? 'Chưa có kế hoạch giáo trình nào trong hệ thống.'
    : 'Bạn hiện chưa tham gia kế hoạch nào. Vui lòng bấm "+ Thêm kế hoạch mới" (khi bật Chế độ chỉnh sửa) hoặc liên hệ Quản trị viên để được phân công bài giảng.';

$templatecontext = [
    'isediting' => $isediting,
    'canmanage' => $canmanage,
    'newplanurl' => (new moodle_url('/local/courseplan/edit_plan.php'))->out(false),
    'hasplans' => $hasplans,
    'emptymessage' => $emptymessage,
    'tablehtml' => html_writer::table($table),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_courseplan/plan_list', $templatecontext);
echo $OUTPUT->footer();
