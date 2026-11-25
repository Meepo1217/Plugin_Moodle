<?php
require_once(__DIR__ . '/../../config.php');

$cmid         = required_param('cmid', PARAM_INT);
$action       = optional_param('action', '', PARAM_ALPHA);
$targetuserid = optional_param('userid', 0, PARAM_INT);

$cm     = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/antishot:manage', $context);

$baseurl = new moodle_url('/local/antishot/report.php', ['cmid' => $cmid]);
$PAGE->set_url($baseurl);
$PAGE->set_title('Quản lý vi phạm & Mở khóa học viên');
$PAGE->set_heading($course->fullname);

// Xử lý mở khóa
if ($action === 'unlock' && $targetuserid > 0 && confirm_sesskey()) {
    // 1. Xóa triệt để mọi bản ghi khóa của học viên này
    $DB->delete_records('local_antishot_locks', ['userid' => $targetuserid]);

    // 2. Xóa sạch toàn bộ log vi phạm tại học liệu này để bộ đếm quay về 0
    $DB->delete_records('local_antishot_logs', ['cmid' => $cmid, 'userid' => $targetuserid]);

    redirect($baseurl, 'Đã mở khóa và xóa sạch vi phạm, học viên bắt đầu lại từ 0 lần!', null, \core\output\notification::NOTIFY_SUCCESS);
}

$config        = $DB->get_record('local_antishot_cm', ['cmid' => $cmid]);
$maxviolations = (int)($config->maxviolations ?? 0);
$lockscope     = (int)($config->lockscope ?? 1);

echo $OUTPUT->header();
echo $OUTPUT->heading('📋 Quản lý vi phạm chụp màn hình: ' . format_string($cm->name));

$maxlabel   = $maxviolations > 0 ? "<strong>{$maxviolations} lần</strong>" : "<strong>Không giới hạn</strong>";
$scopelabel = $lockscope === 2 ? "<span class='badge bg-danger text-white'>Khóa toàn bộ</span>" : "<span class='badge bg-warning text-dark'>Chỉ khóa bài giảng này</span>";

echo html_writer::div(
    "Cấu hình hiện tại: Giới hạn vi phạm: {$maxlabel} | Hình thức phạt: {$scopelabel}",
    'alert alert-info mb-4'
);

echo html_writer::tag('h4', '1. Danh sách học viên vi phạm & Mở khóa');

$summarysql = "
    SELECT l.userid, u.firstname, u.lastname, u.username, u.email,
           COUNT(l.id) AS total_violations,
           MAX(l.timecreated) AS last_time
    FROM {local_antishot_logs} l
    JOIN {user} u ON u.id = l.userid
    WHERE l.cmid = ?
    GROUP BY l.userid, u.firstname, u.lastname, u.username, u.email
    ORDER BY total_violations DESC, last_time DESC
";
$students = $DB->get_records_sql($summarysql, [$cmid]);

if (empty($students)) {
    echo $OUTPUT->notification('Chưa có học viên nào vi phạm trên học liệu này.', 'notifysuccess');
} else {
    $table = new html_table();
    $table->head = ['Họ và tên', 'Username / MSSV', 'Số lần vi phạm', 'Vi phạm gần nhất', 'Trạng thái hiện tại', 'Hành động'];
    $table->attributes['class'] = 'generaltable table table-bordered table-striped';

    foreach ($students as $st) {
        $lockedglobal = $DB->record_exists('local_antishot_locks', ['userid' => $st->userid, 'lockscope' => 2]);
        $lockedcm     = $DB->record_exists('local_antishot_locks', ['userid' => $st->userid, 'cmid' => $cmid, 'lockscope' => 1]);

        if ($lockedglobal) {
            $status = '<span class="badge bg-danger text-white p-2">🚫 Đang bị KHÓA TOÀN BỘ</span>';
        } else if ($lockedcm) {
            $status = '<span class="badge bg-danger text-white p-2">🚫 Đang bị KHÓA BÀI NÀY</span>';
        } else {
            $limitstr = $maxviolations > 0 ? $maxviolations : '∞';
            $status = '<span class="badge bg-success text-white p-2">Đang học (' . $st->total_violations . '/' . $limitstr . ')</span>';
        }

        $unlockurl = new moodle_url('/local/antishot/report.php', [
            'cmid'    => $cmid,
            'action'  => 'unlock',
            'userid'  => $st->userid,
            'sesskey' => sesskey()
        ]);

        $actionbtn = html_writer::link(
            $unlockurl,
            '🔓 Mở khóa & Reset lỗi',
            [
                'class'   => 'btn btn-sm btn-primary fw-bold',
                'onclick' => "return confirm('Bạn có chắc chắn muốn mở khóa và xóa sạch vi phạm cho học viên này không?');"
            ]
        );

        $table->data[] = [
            fullname($st),
            s($st->username),
            '<strong>' . $st->total_violations . '</strong>',
            userdate($st->last_time, '%d/%m/%Y %H:%M:%S'),
            $status,
            $actionbtn
        ];
    }
    echo html_writer::table($table);
}

echo html_writer::tag('h4', '2. Nhật ký chi tiết từng lần vi phạm', ['class' => 'mt-5']);

$detailsql = "
    SELECT l.id, l.action, l.userip, l.timecreated, u.firstname, u.lastname, u.username
    FROM {local_antishot_logs} l
    JOIN {user} u ON u.id = l.userid
    WHERE l.cmid = ?
    ORDER BY l.timecreated DESC
";
$logs = $DB->get_records_sql($detailsql, [$cmid], 0, 200);

if (!empty($logs)) {
    $logtable = new html_table();
    $logtable->head = ['Thời gian', 'Học viên', 'Username', 'Thao tác vi phạm', 'Địa chỉ IP'];
    $logtable->attributes['class'] = 'generaltable table table-sm table-hover';

    foreach ($logs as $log) {
        $logtable->data[] = [
            userdate($log->timecreated, '%d/%m/%Y %H:%M:%S'),
            fullname($log),
            s($log->username),
            '<code>' . s($log->action) . '</code>',
            s($log->userip)
        ];
    }
    echo html_writer::table($logtable);
} else {
    echo $OUTPUT->notification('Chưa có lịch sử log nào.', 'notifysuccess');
}

echo $OUTPUT->footer();