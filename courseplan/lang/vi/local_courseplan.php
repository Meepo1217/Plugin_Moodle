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
 * Vietnamese language strings for local_courseplan.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Thao tác';
$string['add_comment'] = 'Gửi bình luận';
$string['add_item'] = 'Thêm bài giảng';
$string['add_plan'] = 'Tạo kế hoạch biên soạn mới';
$string['add_section'] = 'Thêm chương / mục mới';
$string['assignee'] = 'GV phụ trách';
$string['attachment_link'] = 'File đính kèm / Link';
$string['back_to_list'] = 'Quay lại Danh sách Kế hoạch';
$string['comment_placeholder'] = 'Nhập nội dung trao đổi, góp ý chỉnh sửa...';
$string['comments'] = 'Bình luận';
$string['complete_plan'] = '✔ Hoàn tất Kế hoạch';
$string['complete_plan_confirm_100'] = 'Tiến độ đã đạt 100%. Bạn có muốn chốt kế hoạch và tự động xuất bản (publish) thành khóa học chính thức trong My courses không?';
$string['complete_plan_force_btn'] = 'Đồng ý & Vẫn hoàn tất';
$string['complete_plan_warning_title'] = 'Cảnh báo: Kế hoạch chưa đạt 100%';
$string['complete_plan_warning_body'] = 'Tiến độ hiện tại mới đạt {$a->progress}% (còn {$a->incomplete} bài giảng chưa hoàn tất). Bạn có chắc chắn vẫn muốn chốt kế hoạch và đồng bộ sang My courses ngay bây giờ?';
$string['courseplan:contribute'] = 'Tải tài liệu, cập nhật trạng thái và bình luận tại bài giảng được phân công';
$string['courseplan:manage'] = 'Quản lý khung chương trình, phân công giáo viên và chốt kế hoạch';
$string['courseplan:view'] = 'Xem danh sách kế hoạch và bảng theo dõi tiến độ';
$string['created_date'] = 'Ngày lập';
$string['creator'] = 'Người lập kế hoạch';
$string['dashboard_overview'] = 'Tiến độ biên soạn tổng thể';
$string['drag_to_reorder'] = 'Kéo thả để đổi vị trí';
$string['editmode_leader_hint'] = 'Đang BẬT Edit Mode (Trưởng nhóm): Đã kích hoạt thêm/sửa/xóa cấu trúc, phân công giáo viên, cài đặt bắt buộc nộp file và kéo thả đổi vị trí.';
$string['editmode_member_hint'] = 'Đang BẬT Edit Mode (Giáo viên phụ trách): Chỉ mở khóa khu vực tải file/chèn link và đổi trạng thái tại đúng bài giảng bạn được phân công.';
$string['editmode_off_hint'] = 'Đang TẮT Edit Mode: Giao diện sạch sẽ chỉ để xem tiến độ, tải file và bình luận. Hãy bật Edit Mode ở góc trên để thao tác.';
$string['file_required_badge'] = 'Bắt buộc nộp file';
$string['file_required_error'] = 'Ràng buộc nộp file (Quality Gate): Bài giảng này yêu cầu bắt buộc đính kèm tài liệu (PDF, PPTX...) hoặc chèn link trước khi chuyển sang trạng thái "Chờ duyệt"!';
$string['item_title'] = 'Tên bài giảng';
$string['nav_title'] = 'Biên soạn Giáo trình';
$string['no_attachment'] = 'Chưa có tài liệu';
$string['plan_code'] = 'Mã học phần';
$string['plan_completed_badge'] = 'Đã đồng bộ My Courses';
$string['plan_inprogress_badge'] = 'Đang biên soạn';
$string['plan_list_heading'] = 'Danh sách Kế hoạch Biên soạn Giáo trình';
$string['plan_name'] = 'Tên kế hoạch / Học phần';
$string['plan_status'] = 'Trạng thái';
$string['pluginname'] = 'Quản lý Biên soạn Giáo trình (local_courseplan)';
$string['privacy:metadata'] = 'Plugin Quản lý Biên soạn Giáo trình lưu trữ kế hoạch, phân công nhiệm vụ và bình luận trao đổi của giáo viên.';
$string['progress'] = 'Tiến độ';
$string['role_leader'] = 'Người lập kế hoạch (Trưởng nhóm)';
$string['role_member'] = 'Giáo viên phụ trách (Thành viên)';
$string['role_switch_label'] = 'Chế độ xem vai trò:';
$string['status_done'] = 'Hoàn tất';
$string['status_inprogress'] = 'Đang thực hiện';
$string['status_review'] = 'Chờ duyệt';
$string['status_todo'] = 'Chưa thực hiện';
$string['total_items_summary'] = 'Đã hoàn tất {$a->done} / {$a->total} bài giảng (còn {$a->remaining} mục chưa xong)';
$string['upload_or_link'] = 'Tải file / Chèn link';
$string['view_plan'] = 'Xem chi tiết';
$string['report'] = 'Report';
$string['import_excel'] = 'Nhập từ Excel';
$string['download_template'] = 'Tải file mẫu Excel';
$string['import_excel_help'] = 'Nhập danh sách bài giảng và cấu trúc chương từ file Excel hoặc CSV';




