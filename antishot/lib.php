<?php
// Chặn truy cập trực tiếp từ trình duyệt
defined('MOODLE_INTERNAL') || die();

/**
 * Chèn mục đóng/mở (Header) và các tùy chọn vào form cài đặt của mọi Module học liệu.
 */
function local_antishot_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;

    // 1. Tạo thanh tiêu đề đóng/mở (Accordion Header)
    $mform->addElement('header', 'antishot_header', get_string('antishot_header', 'local_antishot'));

    // 2. Tùy chọn Bật/Tắt chống chụp màn hình
    $mform->addElement('selectyesno', 'antishot_enabled', get_string('enable_antishot', 'local_antishot'));
    $mform->addHelpButton('antishot_enabled', 'enable_antishot', 'local_antishot');
    $mform->setDefault('antishot_enabled', 0);

    // 3. Tùy chọn Watermark định danh
    $mform->addElement('selectyesno', 'antishot_watermark', get_string('enable_watermark', 'local_antishot'));
    $mform->addHelpButton('antishot_watermark', 'enable_watermark', 'local_antishot');
    $mform->setDefault('antishot_watermark', 1);
    $mform->hideIf('antishot_watermark', 'antishot_enabled', 'eq', 0);

    // 4. Tùy chọn Giới hạn số lần vi phạm (Không giới hạn hoặc 1..10 lần)
    $maxoptions = [0 => get_string('unlimited', 'local_antishot')];
    foreach ([1, 2, 3, 4, 5, 7, 10] as $n) {
        $maxoptions[$n] = get_string('violation_times', 'local_antishot', $n);
    }
    $mform->addElement('select', 'antishot_maxviolations', get_string('maxviolations', 'local_antishot'), $maxoptions);
    $mform->addHelpButton('antishot_maxviolations', 'maxviolations', 'local_antishot');
    $mform->setDefault('antishot_maxviolations', 3);
    $mform->hideIf('antishot_maxviolations', 'antishot_enabled', 'eq', 0);

    // 5. Tùy chọn Hình thức khóa khi vượt ngưỡng (1: Chỉ khóa bài đó | 2: Khóa toàn bộ)
    $scopeoptions = [
        1 => get_string('lockscope_cm', 'local_antishot'),
        2 => get_string('lockscope_all', 'local_antishot'),
    ];
    $mform->addElement('select', 'antishot_lockscope', get_string('lockscope', 'local_antishot'), $scopeoptions);
    $mform->addHelpButton('antishot_lockscope', 'lockscope', 'local_antishot');
    $mform->setDefault('antishot_lockscope', 1);
    $mform->hideIf('antishot_lockscope', 'antishot_enabled', 'eq', 0);
    $mform->hideIf('antishot_lockscope', 'antishot_maxviolations', 'eq', 0);

    // 6. Nếu đang chỉnh sửa học liệu đã có sẵn: hiện nút Xem Log & Mở khóa + nạp cấu hình từ DB
    $cm = $formwrapper->get_coursemodule();
    if ($cm && !empty($cm->id)) {
        $reporturl = new moodle_url('/local/antishot/report.php', ['cmid' => $cm->id]);
        $btnhtml = html_writer::div(
            '<a href="' . $reporturl->out() . '" target="_blank" class="btn btn-outline-danger btn-sm fw-bold">' .
            '📋 Xem nhật ký chụp màn hình & Mở khóa học viên' .
            '</a>',
            'mt-2 mb-2'
        );
        $mform->addElement('static', 'antishot_report_btn', '', $btnhtml);

        $record = $DB->get_record('local_antishot_cm', ['cmid' => $cm->id]);
        if ($record) {
            $mform->setDefault('antishot_enabled', $record->enabled);
            $mform->setDefault('antishot_watermark', $record->watermark);
            $mform->setDefault('antishot_maxviolations', $record->maxviolations ?? 0);
            $mform->setDefault('antishot_lockscope', $record->lockscope ?? 1);
        }
    }
}

/**
 * Tự động lưu cấu hình vào Database khi giảng viên bấm Lưu.
 */
function local_antishot_coursemodule_edit_post_actions($data, $course) {
    global $DB;

    if (!isset($data->coursemodule)) {
        return $data;
    }

    $cmid          = (int)$data->coursemodule;
    $enabled       = !empty($data->antishot_enabled) ? 1 : 0;
    $watermark     = !empty($data->antishot_watermark) ? 1 : 0;
    $maxviolations = isset($data->antishot_maxviolations) ? (int)$data->antishot_maxviolations : 0;
    $lockscope     = isset($data->antishot_lockscope) ? (int)$data->antishot_lockscope : 1;

    $existing = $DB->get_record('local_antishot_cm', ['cmid' => $cmid]);
    if ($existing) {
        $existing->enabled       = $enabled;
        $existing->watermark     = $watermark;
        $existing->maxviolations = $maxviolations;
        $existing->lockscope     = $lockscope;
        $existing->timemodified  = time();
        $DB->update_record('local_antishot_cm', $existing);
    } else {
        $record = new stdClass();
        $record->cmid          = $cmid;
        $record->enabled       = $enabled;
        $record->watermark     = $watermark;
        $record->maxviolations = $maxviolations;
        $record->lockscope     = $lockscope;
        $record->timemodified  = time();
        $DB->insert_record('local_antishot_cm', $record);
    }

    return $data;
}

/**
 * Tự động dọn dẹp dữ liệu khi giảng viên xóa một học liệu khỏi khóa học.
 */
function local_antishot_pre_course_module_delete($cm) {
    global $DB;
    $DB->delete_records('local_antishot_cm', ['cmid' => $cm->id]);
    $DB->delete_records('local_antishot_logs', ['cmid' => $cm->id]);
    $DB->delete_records('local_antishot_locks', ['cmid' => $cm->id]);
}