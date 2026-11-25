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

use core\hook\navigation\primary_extend;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/permission.php');

/**
 * Hook callbacks for local_courseplan.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add "Course Plans" link to the primary navigation bar.
     * Only visible to administrators and teachers.
     *
     * @param primary_extend $hook
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return;
        }

        // Only show to admin and teachers.
        if (!permission::is_admin_or_teacher((int)$USER->id)) {
            return;
        }

        $primaryview = $hook->get_primaryview();
        $primaryview->add(
            get_string('nav_title', 'local_courseplan'),
            new moodle_url('/local/courseplan/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_courseplan_nav'
        );
    }
}
