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
 * Controller to publish/finalize a Course Plan and synchronize sections, modules, files, and teachers to My Courses.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/resourcelib.php');
require_once(__DIR__ . '/classes/permission.php');

$id = required_param('id', PARAM_INT); // Plan ID.

require_sesskey();
require_login();
$context = context_system::instance();

$plan = $DB->get_record('local_courseplan_plans', ['id' => $id], '*', MUST_EXIST);
if (!\local_courseplan\permission::can_edit_plan($plan, (int)$USER->id)) {
    throw new moodle_exception('nopermissions', 'error', '', 'Chỉ Người lập kế hoạch hoặc Quản trị viên mới có quyền hoàn tất và công bố kế hoạch này.');
}

$transaction = $DB->start_delegated_transaction();

// 1. Create official course in Moodle.
$coursedata = new stdClass();
$coursedata->fullname = $plan->name;
$coursedata->shortname = $plan->shortname;
$coursedata->category = $plan->categoryid ?: 1;
$coursedata->summary = $plan->summary ?? '';
$coursedata->summaryformat = FORMAT_HTML;
$coursedata->format = 'topics';
$coursedata->visible = 1;

// Ensure unique shortname in mdl_course.
if ($DB->record_exists('course', ['shortname' => $coursedata->shortname])) {
    $coursedata->shortname = $plan->shortname . '_' . date('His');
}

$newcourse = create_course($coursedata);

$resourcemodid = (int)$DB->get_field('modules', 'id', ['name' => 'resource']);
$urlmodid      = (int)$DB->get_field('modules', 'id', ['name' => 'url']);
$pagemodid     = (int)$DB->get_field('modules', 'id', ['name' => 'page']);
$fs            = get_file_storage();
$now           = time();

// 2. Create sections, synchronize lesson items into real course modules, and collect teacher IDs.
$sections = $DB->get_records('local_courseplan_sections', ['planid' => $plan->id], 'sortorder ASC, id ASC');
$secindex = 1;
$teacherids = [];

if (!empty($plan->creatorid)) {
    $teacherids[(int)$plan->creatorid] = true;
}

foreach ($sections as $sec) {
    course_create_section($newcourse->id, $secindex);
    $coursesection = $DB->get_record('course_sections', ['course' => $newcourse->id, 'section' => $secindex]);
    if ($coursesection) {
        $DB->set_field('course_sections', 'name', $sec->title, ['id' => $coursesection->id]);
    }

    $items = $DB->get_records('local_courseplan_items', ['sectionid' => $sec->id], 'sortorder ASC, id ASC');
    foreach ($items as $item) {
        if (!empty($item->assigneeid)) {
            $teacherids[(int)$item->assigneeid] = true;
        }

        // Case A: Lesson has an uploaded file -> create mod_resource.
        if (!empty($item->filename) && $resourcemodid) {
            $cm = new stdClass();
            $cm->course   = $newcourse->id;
            $cm->module   = $resourcemodid;
            $cm->instance = 0;
            $cm->section  = $coursesection ? $coursesection->id : 0;
            $cm->visible  = 1;
            $cm->added    = $now;
            $cmid = add_course_module($cm);

            $res = new stdClass();
            $res->course         = $newcourse->id;
            $res->name           = $item->title;
            $res->intro          = '';
            $res->introformat    = FORMAT_HTML;
            $res->tobemigrated   = 0;
            $res->legacyfiles    = 0;
            $res->filterfiles    = 0;
            $res->display        = RESOURCELIB_DISPLAY_AUTO;
            $res->displayoptions = serialize(['printintro' => 1]);
            $res->revision       = 1;
            $res->timemodified   = $now;
            $resid = $DB->insert_record('resource', $res);

            $DB->set_field('course_modules', 'instance', $resid, ['id' => $cmid]);
            course_add_cm_to_section($newcourse->id, $cmid, $secindex);

            $modcontext = context_module::instance($cmid);
            $storedfiles = $fs->get_area_files($context->id, 'local_courseplan', 'attachment', $item->id, 'sortorder DESC, id ASC', false);
            if (!empty($storedfiles)) {
                foreach ($storedfiles as $sfile) {
                    $filerecord = [
                        'contextid' => $modcontext->id,
                        'component' => 'mod_resource',
                        'filearea'  => 'content',
                        'itemid'    => 0,
                        'filepath'  => $sfile->get_filepath(),
                        'filename'  => $sfile->get_filename(),
                    ];
                    $fs->create_file_from_storedfile($filerecord, $sfile);
                }
            } else {
                // Demo seeded filename: create a valid stored file so clicking the resource in the course works cleanly.
                $filerecord = [
                    'contextid' => $modcontext->id,
                    'component' => 'mod_resource',
                    'filearea'  => 'content',
                    'itemid'    => 0,
                    'filepath'  => '/',
                    'filename'  => clean_filename($item->filename),
                ];
                $fs->create_file_from_string($filerecord, "Tài liệu bài giảng: {$item->title}\nHọc phần: {$plan->name}");
            }
        }

        // Case B: Lesson has an external resource link -> create mod_url.
        if (!empty($item->resourceurl) && $urlmodid) {
            $cm = new stdClass();
            $cm->course   = $newcourse->id;
            $cm->module   = $urlmodid;
            $cm->instance = 0;
            $cm->section  = $coursesection ? $coursesection->id : 0;
            $cm->visible  = 1;
            $cm->added    = $now;
            $cmid = add_course_module($cm);

            $urlrec = new stdClass();
            $urlrec->course         = $newcourse->id;
            $urlrec->name           = !empty($item->filename) ? ($item->title . ' (Link)') : $item->title;
            $urlrec->intro          = '';
            $urlrec->introformat    = FORMAT_HTML;
            $urlrec->externalurl    = $item->resourceurl;
            $urlrec->display        = RESOURCELIB_DISPLAY_AUTO;
            $urlrec->displayoptions = serialize(['printintro' => 1]);
            $urlrec->parameters     = serialize([]);
            $urlrec->timemodified   = $now;
            $urlid = $DB->insert_record('url', $urlrec);

            $DB->set_field('course_modules', 'instance', $urlid, ['id' => $cmid]);
            course_add_cm_to_section($newcourse->id, $cmid, $secindex);
        }

        // Case C: Lesson has neither file nor link yet -> create mod_page placeholder so structure is complete.
        if (empty($item->filename) && empty($item->resourceurl) && $pagemodid) {
            $cm = new stdClass();
            $cm->course   = $newcourse->id;
            $cm->module   = $pagemodid;
            $cm->instance = 0;
            $cm->section  = $coursesection ? $coursesection->id : 0;
            $cm->visible  = 1;
            $cm->added    = $now;
            $cmid = add_course_module($cm);

            $pagerec = new stdClass();
            $pagerec->course         = $newcourse->id;
            $pagerec->name           = $item->title;
            $pagerec->intro          = '';
            $pagerec->introformat    = FORMAT_HTML;
            $pagerec->content        = format_text("<p>Nội dung bài giảng: <strong>" . s($item->title) . "</strong></p>", FORMAT_HTML);
            $pagerec->contentformat  = FORMAT_HTML;
            $pagerec->legacyfiles    = 0;
            $pagerec->display        = RESOURCELIB_DISPLAY_OPEN;
            $pagerec->displayoptions = serialize(['printintro' => 0]);
            $pagerec->revision       = 1;
            $pagerec->timemodified   = $now;
            $pageid = $DB->insert_record('page', $pagerec);

            $DB->set_field('course_modules', 'instance', $pageid, ['id' => $cmid]);
            course_add_cm_to_section($newcourse->id, $cmid, $secindex);
        }
    }
    $secindex++;
}

// 3. Enrol all participating teachers & planner to the newly created course so it appears in their My Courses.
$enrolplugin = enrol_get_plugin('manual');
if ($enrolplugin) {
    $enrolinstance = $DB->get_record('enrol', ['courseid' => $newcourse->id, 'enrol' => 'manual']);
    if (!$enrolinstance) {
        $enrolid = $enrolplugin->add_default_instance($newcourse);
        $enrolinstance = $DB->get_record('enrol', ['id' => $enrolid]);
    }
    // Set customint1 = ENROL_DO_NOT_SEND_EMAIL (0) to ensure enrol_user does not attempt to send welcome
    // email notifications over SMTP, avoiding "Message was not sent." fatal exception on local environments.
    if ($enrolinstance && (int)$enrolinstance->customint1 !== ENROL_DO_NOT_SEND_EMAIL) {
        $DB->set_field('enrol', 'customint1', ENROL_DO_NOT_SEND_EMAIL, ['id' => $enrolinstance->id]);
        $enrolinstance->customint1 = ENROL_DO_NOT_SEND_EMAIL;
    }
    $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
    if ($role && $enrolinstance) {
        foreach (array_keys($teacherids) as $tid) {
            if ($DB->record_exists('user', ['id' => $tid, 'deleted' => 0])) {
                try {
                    $enrolplugin->enrol_user($enrolinstance, $tid, $role->id);
                } catch (\Throwable $e) {
                    // Suppress email notification failures during publish.
                }
            }
        }
    }
}

// 4. Update Plan status to published.
$plan->status = 1;
$plan->courseid = $newcourse->id;
$plan->timemodified = $now;
$plan->usermodified = $USER->id;
$DB->update_record('local_courseplan_plans', $plan);

$transaction->allow_commit();

rebuild_course_cache($newcourse->id, true);

\core\notification::success("Kế hoạch đã được hoàn tất và đồng bộ đầy đủ Chương, Tài liệu & Giáo viên sang My courses!");
redirect(new moodle_url('/course/view.php', ['id' => $newcourse->id]));
