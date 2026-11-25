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

use context_system;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Access and permission helper for local_courseplan.
 *
 * Enforces visibility and participation rules:
 * - Only administrators and teachers can see/access Course Plans.
 * - In plan list, teachers only see plans they participate in (as creator/lead or assigned to a lesson).
 * - Site admins and plan managers can see all plans.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission {

    /**
     * Check if a user is a site administrator or has manage capability.
     *
     * @param int|null $userid
     * @return bool
     */
    public static function is_admin(?int $userid = null): bool {
        global $USER;
        $uid = $userid !== null ? (int)$userid : (int)$USER->id;
        if (empty($uid)) {
            return false;
        }
        if (is_siteadmin($uid)) {
            return true;
        }
        $context = context_system::instance();
        return has_capability('local/courseplan:manage', $context, $uid);
    }

    /**
     * Check if a user is an administrator or a teacher.
     *
     * @param int|null $userid
     * @return bool
     */
    public static function is_admin_or_teacher(?int $userid = null): bool {
        global $DB, $USER;
        $uid = $userid !== null ? (int)$userid : (int)$USER->id;
        if (empty($uid)) {
            return false;
        }

        if (self::is_admin($uid)) {
            return true;
        }

        $context = context_system::instance();
        if (has_capability('local/courseplan:contribute', $context, $uid) ||
            has_capability('local/courseplan:view', $context, $uid)) {
            return true;
        }

        // Check if user has teacher/manager/coursecreator role in system or any course.
        $sql = "SELECT 1
                  FROM {role_assignments} ra
                  JOIN {role} r ON ra.roleid = r.id
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('editingteacher', 'teacher', 'coursecreator', 'manager')";
        if ($DB->record_exists_sql($sql, ['userid' => $uid])) {
            return true;
        }

        // Check if user is already a creator or assignee in any course plan.
        if ($DB->record_exists('local_courseplan_plans', ['creatorid' => $uid])) {
            return true;
        }
        if ($DB->record_exists('local_courseplan_items', ['assigneeid' => $uid])) {
            return true;
        }

        return false;
    }

    /**
     * Check if a user can create new course plans.
     *
     * @param int|null $userid
     * @return bool
     */
    public static function can_create_plan(?int $userid = null): bool {
        return self::is_admin_or_teacher($userid);
    }

    /**
     * Check if a user is a participant of a specific plan (Creator/Lead OR assigned to at least 1 lesson).
     *
     * @param stdClass|int $plan
     * @param int|null $userid
     * @return bool
     */
    public static function is_plan_participant($plan, ?int $userid = null): bool {
        global $DB, $USER;
        $uid = $userid !== null ? (int)$userid : (int)$USER->id;
        if (empty($uid)) {
            return false;
        }

        if (is_numeric($plan)) {
            $plan = $DB->get_record('local_courseplan_plans', ['id' => (int)$plan], '*', MUST_EXIST);
        }

        // User is the creator/leader.
        if ((int)$plan->creatorid === $uid) {
            return true;
        }

        // User is assigned to at least one lesson in this plan.
        if ($DB->record_exists('local_courseplan_items', ['planid' => $plan->id, 'assigneeid' => $uid])) {
            return true;
        }

        return false;
    }

    /**
     * Check if a user can view a specific plan.
     *
     * @param stdClass|int $plan
     * @param int|null $userid
     * @return bool
     */
    public static function can_view_plan($plan, ?int $userid = null): bool {
        if (self::is_admin($userid)) {
            return true;
        }
        return self::is_plan_participant($plan, $userid);
    }

    /**
     * Check if a user can edit/delete a specific plan or its sections/items.
     *
     * @param stdClass|int $plan
     * @param int|null $userid
     * @return bool
     */
    public static function can_edit_plan($plan, ?int $userid = null): bool {
        global $DB, $USER;
        $uid = $userid !== null ? (int)$userid : (int)$USER->id;
        if (empty($uid)) {
            return false;
        }

        if (self::is_admin($uid)) {
            return true;
        }

        if (is_numeric($plan)) {
            $plan = $DB->get_record('local_courseplan_plans', ['id' => (int)$plan], 'id, creatorid', MUST_EXIST);
        }

        return ((int)$plan->creatorid === $uid);
    }

    /**
     * Get the list of plans visible to the given user.
     * Site admins & managers see all plans.
     * Teachers see only plans they participate in (creator or assigned to lesson).
     *
     * @param int|null $userid
     * @return array Array of plan records indexed by id.
     */
    public static function get_user_plans(?int $userid = null): array {
        global $DB, $USER;
        $uid = $userid !== null ? (int)$userid : (int)$USER->id;
        if (empty($uid)) {
            return [];
        }

        if (self::is_admin($uid)) {
            return $DB->get_records('local_courseplan_plans', null, 'timecreated DESC');
        }

        // Teacher only sees plans where they are creator OR assigned to at least one lesson.
        $sql = "SELECT DISTINCT p.*
                  FROM {local_courseplan_plans} p
             LEFT JOIN {local_courseplan_items} i ON i.planid = p.id
                 WHERE p.creatorid = :uid1
                    OR i.assigneeid = :uid2
              ORDER BY p.timecreated DESC";

        return $DB->get_records_sql($sql, ['uid1' => $uid, 'uid2' => $uid]);
    }
}

