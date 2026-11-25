<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$cmid   = required_param('cmid', PARAM_INT);
$action = optional_param('action', 'unspecified_action', PARAM_ALPHAEXT);

$cm     = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
require_login($course, false, $cm);

$config = $DB->get_record('local_antishot_cm', ['cmid' => $cmid, 'enabled' => 1]);
if (!$config) {
    echo json_encode(['status' => 'disabled']);
    exit;
}

$userid        = (int)$USER->id;
$maxviolations = (int)$config->maxviolations;
$lockscope     = (int)$config->lockscope;

// Ghi log hành vi vi phạm vào database (chống ghi lặp trong vòng 1 giây)
$recent = $DB->record_exists_select(
    'local_antishot_logs',
    'cmid = ? AND userid = ? AND timecreated >= ?',
    [$cmid, $userid, time() - 5]
);

if (!$recent) {
    $log              = new stdClass();
    $log->cmid        = $cmid;
    $log->courseid    = $course->id;
    $log->userid      = $userid;
    $log->action      = substr($action, 0, 50);
    $log->userip      = getremoteaddr();
    $log->timecreated = time();
    $DB->insert_record('local_antishot_logs', $log);
}

// Đếm tổng số lần vi phạm của học viên tại học liệu này
$total = $DB->count_records('local_antishot_logs', [
    'cmid'   => $cmid,
    'userid' => $userid
]);

$islocked = false;

// Nếu có giới hạn (> 0) và tổng số lần vi phạm >= giới hạn -> Kích hoạt khóa
if ($maxviolations > 0 && $total >= $maxviolations) {
    $islocked = true;

    $lockconditions = ($lockscope === 2)
        ? ['userid' => $userid, 'courseid' => $course->id, 'lockscope' => 2]
        : ['userid' => $userid, 'cmid' => $cmid, 'lockscope' => 1];

    if (!$DB->record_exists('local_antishot_locks', $lockconditions)) {
        $lock              = new stdClass();
        $lock->userid      = $userid;
        $lock->courseid    = $course->id;
        $lock->cmid        = $cmid;
        $lock->lockscope   = $lockscope;
        $lock->timecreated = time();
        $DB->insert_record('local_antishot_locks', $lock);
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'            => $islocked ? 'locked' : 'logged',
    'currentViolations' => $total,
    'maxViolations'     => $maxviolations,
    'lockScope'         => $lockscope,
    'isLocked'          => $islocked
]);
exit;