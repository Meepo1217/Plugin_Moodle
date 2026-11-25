<?php
// Chặn truy cập trực tiếp từ trình duyệt
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // 1. Quyền được phép thấy và cấu hình Bật/Tắt chống chụp màn hình
    'local/antishot:manage' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW, // Giảng viên soạn bài được quyền bật/tắt
            'manager' => CAP_ALLOW,        // Quản lý hệ thống được quyền bật/tắt
        ],
    ],

    // 2. Quyền miễn trừ (Bypass) - Không bị làm mờ màn hình khi xem nội dung
    'local/antishot:bypass' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'teacher' => CAP_ALLOW,        // Giảng viên chấm bài (Non-editing teacher) không bị mờ
            'editingteacher' => CAP_ALLOW, // Giảng viên soạn bài không bị mờ
            'manager' => CAP_ALLOW,        // Quản lý không bị mờ
        ],
    ],
];