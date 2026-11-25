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
 * AJAX endpoints for local_courseplan (Status change with Quality Gate, Comments, and Drag & Drop Reordering).
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/permission.php');

$action = required_param('action', PARAM_ALPHA);

require_sesskey();
require_login();
$context = context_system::instance();

header('Content-Type: application/json; charset=utf-8');

try {
    if ($action === 'updatestatus') {
        $itemid = required_param('itemid', PARAM_INT);
        $newstatus = required_param('status', PARAM_INT);

        if (!in_array($newstatus, [0, 1, 2, 3], true)) {
            throw new moodle_exception('invalidparameter', 'debug');
        }

        $item = $DB->get_record('local_courseplan_items', ['id' => $itemid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_courseplan_plans', ['id' => $item->planid], '*', MUST_EXIST);

        $isleader = \local_courseplan\permission::can_edit_plan($plan, (int)$USER->id);
        $isassigned = ((int)$item->assigneeid === (int)$USER->id);

        if (!$isleader && !$isassigned) {
            throw new moodle_exception('nopermissions', 'error', '', 'Chỉ trưởng nhóm hoặc giáo viên được phân công mới có quyền đổi trạng thái bài này.');
        }

        // Only the Course Planner (Leader) can approve a lesson to status 3 (Completed).
        if ($newstatus === 3 && !$isleader) {
            echo json_encode([
                'success' => false,
                'error' => 'Chỉ Người lập kế hoạch (Trưởng nhóm) mới có quyền duyệt sang trạng thái Hoàn tất!',
                'oldstatus' => (int)$item->status,
            ]);
            exit;
        }

        // Quality Gate Check: If required file/link is missing, block changing to "Waiting for Review" (2) or "Completed" (3).
        $hasattachment = !empty($item->filename) || !empty($item->resourceurl);
        if (!empty($item->requirefile) && !$hasattachment && ($newstatus === 2 || $newstatus === 3)) {
            echo json_encode([
                'success' => false,
                'error' => get_string('file_required_error', 'local_courseplan'),
                'oldstatus' => (int)$item->status,
            ]);
            exit;
        }

        $item->status = $newstatus;
        $item->timemodified = time();
        $item->usermodified = $USER->id;
        $DB->update_record('local_courseplan_items', $item);

        // Recalculate plan progress.
        $total = $DB->count_records('local_courseplan_items', ['planid' => $plan->id]);
        $done = $DB->count_records('local_courseplan_items', ['planid' => $plan->id, 'status' => 3]);
        $progress = $total > 0 ? (int)round(($done / $total) * 100) : 0;

        echo json_encode([
            'success' => true,
            'status' => $newstatus,
            'progress' => $progress,
            'done' => $done,
            'total' => $total,
            'remaining' => ($total - $done),
        ]);
        exit;

    } else if ($action === 'addcomment') {
        $itemid = required_param('itemid', PARAM_INT);
        $content = required_param('content', PARAM_TEXT);

        if (trim($content) === '') {
            throw new moodle_exception('invalidcomment', 'error');
        }

        $item = $DB->get_record('local_courseplan_items', ['id' => $itemid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_courseplan_plans', ['id' => $item->planid], '*', MUST_EXIST);

        if (!\local_courseplan\permission::can_view_plan($plan, (int)$USER->id)) {
            throw new moodle_exception('nopermissions', 'error', '', 'Bạn không tham gia kế hoạch này.');
        }

        $now = time();
        $record = new stdClass();
        $record->itemid = $itemid;
        $record->userid = $USER->id;
        $record->content = trim($content);
        $record->timecreated = $now;
        $commentid = $DB->insert_record('local_courseplan_comments', $record);

        echo json_encode([
            'success' => true,
            'commentid' => $commentid,
            'username' => fullname($USER),
            'timeago' => userdate($now, '%d/%m/%Y %H:%M'),
            'content' => s($record->content),
        ]);
        exit;

    } else if ($action === 'reorderitems') {
        $sectionid = required_param('sectionid', PARAM_INT);
        $itemidsraw = required_param('itemids', PARAM_SEQUENCE);

        $sec = $DB->get_record('local_courseplan_sections', ['id' => $sectionid], '*', MUST_EXIST);
        $plan = $DB->get_record('local_courseplan_plans', ['id' => $sec->planid], '*', MUST_EXIST);
        if (!\local_courseplan\permission::can_edit_plan($plan, (int)$USER->id)) {
            throw new moodle_exception('nopermissions', 'error', '', 'Chỉ trưởng nhóm hoặc quản trị viên mới có quyền sắp xếp.');
        }

        $itemids = array_filter(array_map('intval', explode(',', $itemidsraw)));
        $transaction = $DB->start_delegated_transaction();
        $sort = 1;
        $now = time();
        foreach ($itemids as $iid) {
            if ($DB->record_exists('local_courseplan_items', ['id' => $iid, 'planid' => $plan->id])) {
                $DB->update_record('local_courseplan_items', (object)[
                    'id' => $iid,
                    'sectionid' => $sectionid,
                    'sortorder' => $sort++,
                    'timemodified' => $now,
                    'usermodified' => $USER->id,
                ]);
            }
        }
        $transaction->allow_commit();

        echo json_encode(['success' => true]);
        exit;

    } else if ($action === 'reordersections') {
        $sectionidsraw = required_param('sectionids', PARAM_SEQUENCE);
        $sectionids = array_filter(array_map('intval', explode(',', $sectionidsraw)));

        if (!empty($sectionids)) {
            $firstsec = $DB->get_record('local_courseplan_sections', ['id' => reset($sectionids)], '*', MUST_EXIST);
            $plan = $DB->get_record('local_courseplan_plans', ['id' => $firstsec->planid], '*', MUST_EXIST);
            if (!\local_courseplan\permission::can_edit_plan($plan, (int)$USER->id)) {
                throw new moodle_exception('nopermissions', 'error', '', 'Chỉ trưởng nhóm hoặc quản trị viên mới có quyền sắp xếp.');
            }

            $transaction = $DB->start_delegated_transaction();
            $sort = 1;
            $now = time();
            foreach ($sectionids as $sid) {
                if ($DB->record_exists('local_courseplan_sections', ['id' => $sid, 'planid' => $plan->id])) {
                    $DB->update_record('local_courseplan_sections', (object)[
                        'id' => $sid,
                        'sortorder' => $sort++,
                        'timemodified' => $now,
                    ]);
                }
            }
            $transaction->allow_commit();
        }

        echo json_encode(['success' => true]);
        exit;

    } else {
        throw new moodle_exception('invalidaction', 'error');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ]);
    exit;
}
