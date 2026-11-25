<?php
// Chặn truy cập trực tiếp từ trình duyệt
defined('MOODLE_INTERNAL') || die();

/*
 * Đăng ký Hook: Trước khi Moodle xuất phần Footer (cuối trang) ra trình duyệt, 
 * nó sẽ gọi đến class hook_callbacks của plugin chúng ta để bơm thêm code JS và CSS.
 */
$callbacks = [
    [
        'hook' => \core\hook\output\before_footer_html_generation::class,
        'callback' => [\local_antishot\hook_callbacks::class, 'before_footer'],
        'priority' => 500, // Độ ưu tiên (số càng nhỏ càng chạy sớm, 500 là mức trung bình chuẩn)
    ],
];