<?php
// Chặn người dùng gõ trực tiếp đường dẫn file này trên thanh địa chỉ trình duyệt
defined('MOODLE_INTERNAL') || die();

/** @var stdClass $plugin */
// 1. Tên định danh của plugin
$plugin->component = 'local_antishot';

// 2. Mã phiên bản nâng cấp để tạo bảng Log và Khóa
$plugin->version   = 2026110401;

// 3. Phiên bản Moodle tối thiểu (Moodle 4.5 và 5.0 trở lên)
$plugin->requires  = 2024100700;

// 4. Trạng thái ổn định của mã nguồn
$plugin->maturity  = MATURITY_STABLE;

// 5. Số hiệu phiên bản hiển thị
$plugin->release   = '2.0.0';