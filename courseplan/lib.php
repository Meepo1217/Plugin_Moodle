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
 * Library functions and file serving for local_courseplan.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serves files associated with local_courseplan.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_courseplan_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    require_login();
    require_capability('local/courseplan:view', $context);

    if ($filearea !== 'attachment') {
        return false;
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_courseplan', 'attachment', $itemid, $filepath, $filename);

    // If this is a seeded demo filename that hasn't been physically created yet, generate it on demand.
    if ((!$file || $file->is_directory()) && $filepath === '/') {
        $item = $DB->get_record('local_courseplan_items', ['id' => $itemid]);
        if ($item && $item->filename === $filename) {
            $filerecord = [
                'contextid' => $context->id,
                'component' => 'local_courseplan',
                'filearea'  => 'attachment',
                'itemid'    => $itemid,
                'filepath'  => '/',
                'filename'  => $filename,
            ];
            $content = "Tài liệu biên soạn mẫu cho bài giảng: {$item->title}\nFile: {$filename}\n";
            $file = $fs->create_file_from_string($filerecord, $content);
        }
    }

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}
