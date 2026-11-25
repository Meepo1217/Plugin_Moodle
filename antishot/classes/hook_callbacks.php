<?php
namespace local_antishot;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {
    public static function before_footer(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $USER, $DB;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        if (strpos($PAGE->pagetype, 'admin-') === 0 || $PAGE->pagetype === 'course-modedit') {
            return;
        }

        $userid   = (int)$USER->id;
        $courseid = !empty($PAGE->course->id) ? (int)$PAGE->course->id : 0;
        $cmid     = !empty($PAGE->cm->id) ? (int)$PAGE->cm->id : 0;

        $context = $cmid ? \context_module::instance($cmid) : \context_system::instance();
        if (has_capability('local/antishot:bypass', $context) || is_siteadmin()) {
            return;
        }

        // 1. Kiểm tra trạng thái khóa cứng ở server
        $globallock = $DB->record_exists('local_antishot_locks', [
            'userid'    => $userid,
            'courseid'  => $courseid,
            'lockscope' => 2
        ]);

        $cmlock = ($cmid > 0) ? $DB->record_exists('local_antishot_locks', [
            'userid'    => $userid,
            'cmid'      => $cmid,
            'lockscope' => 1
        ]) : false;

        if ($globallock || $cmlock) {
            $isglobal = $globallock;
            $locktitle = $isglobal ? '🚫 TÀI KHOẢN HỌC TẬP ĐÃ BỊ KHÓA TOÀN BỘ' : '🚫 BÀI GIẢNG NÀY ĐÃ BỊ KHÓA TRUY CẬP';
            $lockdesc  = 'Bạn đã vi phạm quy định chống chụp màn hình quá số lần cho phép.';
            $backurl   = ($courseid > 1 && !$isglobal)
                ? new \moodle_url('/course/view.php', ['id' => $courseid])
                : new \moodle_url('/login/logout.php', ['sesskey' => sesskey()]);
            $backlabel = ($courseid > 1 && !$isglobal) ? 'Quay lại khóa học' : 'Đăng xuất';

            $lockhtml = '
            <style>
                #page-content, #region-main, .activity-header, iframe { display: none !important; }
                #antishot-server-lock {
                    position: fixed; inset: 0; z-index: 2147483647;
                    background: #111827; color: #fff;
                    display: flex; align-items: center; justify-content: center;
                    font-family: sans-serif; text-align: center; padding: 20px;
                }
            </style>
            <div id="antishot-server-lock">
                <div style="max-width: 580px; background: #1f2937; border: 2px solid #ef4444; border-radius: 12px; padding: 35px;">
                    <h2 style="color: #f87171; margin-top: 0;">' . $locktitle . '</h2>
                    <p style="font-size: 16px; color: #e5e7eb; margin: 18px 0;">' . $lockdesc . '</p>
                    <div style="background: rgba(239,68,68,0.15); border: 1px solid #ef4444; padding: 12px; border-radius: 8px; color: #fca5a5; margin-bottom: 24px;">
                        Vui lòng liên hệ Giảng viên phụ trách để được mở khóa.
                    </div>
                    <a href="' . $backurl->out() . '" style="display: inline-block; padding: 10px 24px; background: #ef4444; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold;">' . $backlabel . '</a>
                </div>
            </div>';

            $hook->add_html($lockhtml);
            return;
        }

        if ($cmid <= 0) {
            return;
        }

        $config = $DB->get_record('local_antishot_cm', [
            'cmid'    => $cmid,
            'enabled' => 1
        ]);

        if (!$config) {
            return;
        }

        $currentviolations = $DB->count_records('local_antishot_logs', [
            'cmid'   => $cmid,
            'userid' => $userid
        ]);

        $userlabel = fullname($USER) . ' (' . $USER->username . ') - IP: ' . getremoteaddr();

        $jsconfig = json_encode([
            'watermark'         => (bool)$config->watermark,
            'userText'          => $userlabel,
            'cmid'              => $cmid,
            'courseid'          => $courseid,
            'sesskey'           => sesskey(),
            'ajaxUrl'           => (new \moodle_url('/local/antishot/ajax_log.php'))->out(false),
            'maxViolations'     => (int)$config->maxviolations,
            'lockScope'         => (int)$config->lockscope,
            'currentViolations' => $currentviolations,
        ]);

        $html  = '<script>window.MOODLE_ANTISHOT_CONFIG = ' . $jsconfig . ';</script>';
        $html .= '<script src="' . (new \moodle_url('/local/antishot/assets/antishot.js'))->out() . '"></script>';

        $hook->add_html($html);
    }
}