<?php
defined('MOODLE_INTERNAL') || die();

/** @var array $string */
$string['pluginname'] = 'Anti-Screenshot Protection (Chống chụp màn hình)';
$string['antishot_header'] = 'Anti-Screenshot (Chống chụp màn hình)';

$string['enable_antishot'] = 'Bật chống chụp màn hình';
$string['enable_antishot_help'] = 'Chặn phím PrintScreen, khóa chuột phải và tự động làm mờ khi mở công cụ cắt màn hình (Win+Shift+S) hoặc chuyển cửa sổ.';

$string['enable_watermark'] = 'Hiển thị Watermark định danh';
$string['enable_watermark_help'] = 'Phủ thông tin Họ tên, Username và IP của người học lên màn hình.';

$string['maxviolations'] = 'Số lần được phép vi phạm (n lần)';
$string['maxviolations_help'] = 'Chọn số lần tối đa học viên được phép vi phạm trước khi bị khóa. Chọn "Không giới hạn" nếu chỉ muốn cảnh báo và ghi log.';
$string['unlimited'] = 'Không giới hạn (Chỉ ghi log & làm mờ)';
$string['violation_times'] = '{$a} lần';

$string['lockscope'] = 'Hình thức khóa khi vượt quá số lần vi phạm';
$string['lockscope_help'] = 'Chọn 1 trong 2 hình thức: Chỉ khóa riêng bài giảng đang học hoặc Khóa toàn bộ không cho truy cập hệ thống học liệu cho đến khi giảng viên mở khóa.';
$string['lockscope_cm'] = '1. Chỉ khóa bài giảng/học liệu này';
$string['lockscope_all'] = '2. Khóa toàn bộ (Chặn truy cập toàn bộ cho đến khi giảng viên mở khóa)';

$string['antishot:manage'] = 'Cấu hình bật/tắt và mở khóa chống chụp màn hình';
$string['antishot:bypass'] = 'Được miễn trừ không bị làm mờ và không bị khóa màn hình';