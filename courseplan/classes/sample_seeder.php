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

namespace local_courseplan;

use stdClass;

/**
 * Seeds initial demo plans for UI preview if the database is empty.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sample_seeder {
    /**
     * Ensure sample data exists in local_courseplan tables.
     *
     * @param int $currentuserid
     */
    public static function ensure_seeded(int $currentuserid): void {
        global $DB;

        if ($DB->count_records('local_courseplan_plans') > 0) {
            return;
        }

        $now = time();

        // Find up to 3 users in the system to represent teachers, fallback to current user.
        $users = array_values($DB->get_records_select('user', 'deleted = 0 AND id > 1', null, 'id ASC', 'id', 0, 3));
        $u1 = $currentuserid;
        $u2 = isset($users[1]) ? (int)$users[1]->id : $currentuserid;
        $u3 = isset($users[2]) ? (int)$users[2]->id : $currentuserid;

        // Plan 1: In progress (50% completed)
        $plan1 = new stdClass();
        $plan1->name = 'Lập trình Web Nâng cao với PHP & Moodle API';
        $plan1->shortname = 'IT4090-2026';
        $plan1->categoryid = 1;
        $plan1->summary = 'Kế hoạch biên soạn giáo trình chuẩn hóa cho học phần Lập trình Web Nâng cao học kỳ 1.';
        $plan1->creatorid = $u1;
        $plan1->status = 0;
        $plan1->courseid = 0;
        $plan1->timecreated = $now - 86400 * 5;
        $plan1->timemodified = $now;
        $plan1->usermodified = $u1;
        $plan1id = $DB->insert_record('local_courseplan_plans', $plan1);

        // Sections for Plan 1
        $s1 = new stdClass();
        $s1->planid = $plan1id;
        $s1->title = 'Chương 1: Kiến trúc hệ thống & Chuẩn Frankenstyle';
        $s1->sortorder = 1;
        $s1->timecreated = $now;
        $s1->timemodified = $now;
        $s1id = $DB->insert_record('local_courseplan_sections', $s1);

        $s2 = new stdClass();
        $s2->planid = $plan1id;
        $s2->title = 'Chương 2: Xây dựng Giao diện với Mustache & Core UI';
        $s2->sortorder = 2;
        $s2->timecreated = $now;
        $s2->timemodified = $now;
        $s2id = $DB->insert_record('local_courseplan_sections', $s2);

        $items = [
            [
                'sectionid' => $s1id,
                'title' => 'Bài 1.1: Tổng quan kiến trúc Moodle 5.0 & Cấu trúc thư mục',
                'assigneeid' => $u1,
                'requirefile' => 1,
                'filename' => 'Bai_1_1_Kien_Truc_Moodle5.pdf',
                'resourceurl' => 'https://docs.moodle.org/dev/Main_Page',
                'status' => 3, // Hoàn tất
                'sortorder' => 1,
            ],
            [
                'sectionid' => $s1id,
                'title' => 'Bài 1.2: Thiết kế Cơ sở dữ liệu với XMLDB & Phân quyền Capabilities',
                'assigneeid' => $u2,
                'requirefile' => 1,
                'filename' => 'Slide_Bai_1_2_XMLDB_Access.pptx',
                'resourceurl' => '',
                'status' => 3, // Hoàn tất
                'sortorder' => 2,
            ],
            [
                'sectionid' => $s2id,
                'title' => 'Bài 2.1: Tích hợp MoodleForm, FileManager và Progress Bar',
                'assigneeid' => $u1,
                'requirefile' => 1,
                'filename' => 'Bai_Giang_2_1_CoreUI_Draft.pdf',
                'resourceurl' => '',
                'status' => 2, // Chờ duyệt
                'sortorder' => 1,
            ],
            [
                'sectionid' => $s2id,
                'title' => 'Bài 2.2: Lập trình AMD JavaScript, Modal Factory & Web Services',
                'assigneeid' => $u3,
                'requirefile' => 1,
                'filename' => '',
                'resourceurl' => '',
                'status' => 1, // Đang thực hiện (chưa nộp file -> test Quality Gate!)
                'sortorder' => 2,
            ],
        ];

        $firstitemid = 0;
        foreach ($items as $itemdata) {
            $rec = (object)$itemdata;
            $rec->planid = $plan1id;
            $rec->timecreated = $now;
            $rec->timemodified = $now;
            $rec->usermodified = $u1;
            $id = $DB->insert_record('local_courseplan_items', $rec);
            if (!$firstitemid) {
                $firstitemid = $id;
            }
        }

        // Sample comments
        if ($firstitemid) {
            $c1 = new stdClass();
            $c1->itemid = $firstitemid;
            $c1->userid = $u1;
            $c1->content = 'Đã cập nhật bổ sung sơ đồ luồng xử lý dữ liệu ở trang 12 theo góp ý của tổ bộ môn.';
            $c1->timecreated = $now - 3600 * 4;
            $DB->insert_record('local_courseplan_comments', $c1);
        }

        // Plan 2: 100% Completed example
        $plan2 = new stdClass();
        $plan2->name = 'Nhập môn Trí tuệ Nhân tạo & Khoa học Dữ liệu';
        $plan2->shortname = 'AI2026-01';
        $plan2->categoryid = 1;
        $plan2->summary = 'Giáo trình chuẩn hóa gồm 2 chuyên đề cốt lõi đã nghiệm thu 100%.';
        $plan2->creatorid = $u2;
        $plan2->status = 0;
        $plan2->courseid = 0;
        $plan2->timecreated = $now - 86400 * 12;
        $plan2->timemodified = $now - 3600;
        $plan2->usermodified = $u2;
        $plan2id = $DB->insert_record('local_courseplan_plans', $plan2);

        $s21 = new stdClass();
        $s21->planid = $plan2id;
        $s21->title = 'Chuyên đề 1: Cơ sở Toán học & Học máy cơ bản';
        $s21->sortorder = 1;
        $s21->timecreated = $now;
        $s21->timemodified = $now;
        $s21id = $DB->insert_record('local_courseplan_sections', $s21);

        foreach ([1, 2] as $idx) {
            $rec = new stdClass();
            $rec->planid = $plan2id;
            $rec->sectionid = $s21id;
            $rec->title = "Bài 1.{$idx}: Tài liệu chuẩn hóa chuyên đề {$idx}";
            $rec->assigneeid = $u1;
            $rec->requirefile = 1;
            $rec->filename = "Giao_Trinh_AI_Bai_1_{$idx}.pdf";
            $rec->resourceurl = '';
            $rec->status = 3;
            $rec->sortorder = $idx;
            $rec->timecreated = $now;
            $rec->timemodified = $now;
            $rec->usermodified = $u2;
            $DB->insert_record('local_courseplan_items', $rec);
        }
    }
}

