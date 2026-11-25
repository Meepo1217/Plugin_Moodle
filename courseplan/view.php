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
 * Screen 2: Course Plan Detail reusing Moodle's native course view structure.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/permission.php');

$id = required_param('id', PARAM_INT);

require_login();
$context = context_system::instance();

$plan = $DB->get_record('local_courseplan_plans', ['id' => $id], '*', MUST_EXIST);

// Only administrators and participants (creator or assigned teachers) can view this plan.
if (!\local_courseplan\permission::can_view_plan($plan, (int)$USER->id)) {
    throw new moodle_exception('nopermissions', 'error', new moodle_url('/local/courseplan/index.php'), 'Bạn không có quyền truy cập kế hoạch này vì bạn không tham gia kế hoạch.');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseplan/view.php', ['id' => $id]));
$PAGE->set_pagelayout('course');
$PAGE->set_title(format_string($plan->name));
$PAGE->set_heading(format_string($plan->name));
$PAGE->set_other_editing_capability(['local/courseplan:manage', 'local/courseplan:contribute']);
$PAGE->navbar->add(get_string('nav_title', 'local_courseplan'), new moodle_url('/local/courseplan/index.php'));
$PAGE->navbar->add(format_string($plan->shortname));

$isediting = $PAGE->user_is_editing();
$isleader = \local_courseplan\permission::can_edit_plan($plan, (int)$USER->id);

$creator = $DB->get_record('user', ['id' => $plan->creatorid]);
$creatoravatar = $creator ? $OUTPUT->user_picture($creator, ['size' => 28]) : '';
$creatorname = $creator ? fullname($creator) : '-';

$sections = $DB->get_records('local_courseplan_sections', ['planid' => $plan->id], 'sortorder ASC, id ASC');
$allitems = $DB->get_records('local_courseplan_items', ['planid' => $plan->id], 'sortorder ASC, id ASC');

$totalitems = count($allitems);
$donecount = 0;

$itemsbysection = [];
foreach ($allitems as $item) {
    if ((int)$item->status === 3) {
        $donecount++;
    }
    $itemsbysection[$item->sectionid][] = $item;
}

$progress = $totalitems > 0 ? (int)round(($donecount / $totalitems) * 100) : 0;
$remainingcount = $totalitems - $donecount;

$statuslabels = [
    0 => get_string('status_todo', 'local_courseplan'),
    1 => get_string('status_inprogress', 'local_courseplan'),
    2 => get_string('status_review', 'local_courseplan'),
    3 => get_string('status_done', 'local_courseplan'),
];
$statusbadges = [
    0 => 'bg-secondary text-white',
    1 => 'bg-info text-dark',
    2 => 'bg-warning text-dark',
    3 => 'bg-success text-white',
];

$fileiconurl = $OUTPUT->image_url('monologo', 'mod_resource')->out(false);

$sectionsdata = [];
foreach ($sections as $sec) {
    $secitems = $itemsbysection[$sec->id] ?? [];
    $formatteditems = [];

    foreach ($secitems as $item) {
        $assignee = $DB->get_record('user', ['id' => $item->assigneeid]);
        $assigneeavatar = $assignee ? $OUTPUT->user_picture($assignee, ['size' => 28]) : '';
        $assigneename = $assignee ? fullname($assignee) : '-';

        $isassignedtome = ((int)$item->assigneeid === (int)$USER->id);
        $canmanageitem = $isediting && $isleader;
        $caneditwork = $isediting && ($isleader || $isassignedtome);

        $hasattachment = !empty($item->filename) || !empty($item->resourceurl);
        $st = (int)$item->status;

        $comments = $DB->get_records('local_courseplan_comments', ['itemid' => $item->id], 'timecreated ASC');
        $commentsdata = [];
        foreach ($comments as $cm) {
            $cuser = $DB->get_record('user', ['id' => $cm->userid]);
            $commentsdata[] = [
                'useravatar' => $cuser ? $OUTPUT->user_picture($cuser, ['size' => 24]) : '',
                'username' => $cuser ? fullname($cuser) : '-',
                'content' => format_string($cm->content),
                'timeago' => userdate($cm->timecreated, '%d/%m/%Y %H:%M'),
            ];
        }

        // File download URL via Moodle File API.
        $filedownloadurl = '';
        if (!empty($item->filename)) {
            $filedownloadurl = moodle_url::make_pluginfile_url(
                $context->id,
                'local_courseplan',
                'attachment',
                $item->id,
                '/',
                $item->filename
            )->out(false);
        }

        $formatteditems[] = [
            'id' => (int)$item->id,
            'title' => format_string($item->title),
            'fileiconurl' => $fileiconurl,
            'assigneeavatar' => $assigneeavatar,
            'assigneename' => $assigneename,
            'requirefile' => !empty($item->requirefile),
            'filename' => s($item->filename ?? ''),
            'filedownloadurl' => $filedownloadurl,
            'resourceurl' => s($item->resourceurl ?? ''),
            'uploadurl' => (new moodle_url('/local/courseplan/upload.php', ['id' => $item->id]))->out(false),
            'edititemurl' => (new moodle_url('/local/courseplan/edit_item.php', ['planid' => $plan->id, 'id' => $item->id]))->out(false),
            'deleteitemurl' => (new moodle_url('/local/courseplan/edit_item.php', ['planid' => $plan->id, 'id' => $item->id, 'action' => 'delete', 'sesskey' => sesskey()]))->out(false),
            'hasattachment' => $hasattachment,
            'status' => $st,
            'statuslabel' => $statuslabels[$st] ?? $statuslabels[0],
            'statusbadgeclass' => $statusbadges[$st] ?? $statusbadges[0],
            'is_todo' => ($st === 0),
            'is_inprogress' => ($st === 1),
            'is_review' => ($st === 2),
            'is_done' => ($st === 3),
            'canmanageitem' => $canmanageitem,
            'caneditwork' => $caneditwork,
            'isleader' => $isleader,
            'commentcount' => count($commentsdata),
            'comments' => $commentsdata,
        ];
    }

    $sectionsdata[] = [
        'sectionid' => (int)$sec->id,
        'sectiontitle' => format_string($sec->title),
        'additemurl' => (new moodle_url('/local/courseplan/edit_item.php', ['planid' => $plan->id, 'sectionid' => $sec->id]))->out(false),
        'editsectionurl' => (new moodle_url('/local/courseplan/edit_section.php', ['planid' => $plan->id, 'id' => $sec->id]))->out(false),
        'deletesectionurl' => (new moodle_url('/local/courseplan/edit_section.php', ['planid' => $plan->id, 'id' => $sec->id, 'action' => 'delete', 'sesskey' => sesskey()]))->out(false),
        'items' => $formatteditems,
    ];
}

$publishedcourseurl = '';
if ((int)$plan->status === 1 && !empty($plan->courseid) && $DB->record_exists('course', ['id' => $plan->courseid])) {
    $publishedcourseurl = (new moodle_url('/course/view.php', ['id' => $plan->courseid]))->out(false);
}

$templatecontext = [
    'planid' => (int)$plan->id,
    'planname' => format_string($plan->name),
    'planshortname' => s($plan->shortname),
    'plansummary' => format_string($plan->summary ?? ''),
    'creatoravatar' => $creatoravatar,
    'creatorname' => $creatorname,
    'progress' => $progress,
    'is100' => ($progress === 100),
    'remainingcount' => $remainingcount,
    'summarytext' => get_string('total_items_summary', 'local_courseplan', (object)[
        'done' => $donecount,
        'total' => $totalitems,
        'remaining' => $remainingcount,
    ]),
    'isediting' => $isediting,
    'isleader' => $isleader,
    'reporturl' => (new moodle_url('/local/courseplan/report.php', ['id' => $plan->id]))->out(false),
    'importurl' => (new moodle_url('/local/courseplan/import.php', ['planid' => $plan->id]))->out(false),
    'publishedcourseurl' => $publishedcourseurl,
    'addsectionurl' => (new moodle_url('/local/courseplan/edit_section.php', ['planid' => $plan->id]))->out(false),
    'publishurl' => (new moodle_url('/local/courseplan/publish.php', ['id' => $plan->id, 'sesskey' => sesskey()]))->out(false),
    'sections' => $sectionsdata,
];

$jsWarningTitle = json_encode(get_string('complete_plan_warning_title', 'local_courseplan'));
$jsForceBtn = json_encode(get_string('complete_plan_force_btn', 'local_courseplan'));
$jsConfirm100 = json_encode(get_string('complete_plan_confirm_100', 'local_courseplan'));
$jsFileError = json_encode(get_string('file_required_error', 'local_courseplan'));
$jsReportUrl = json_encode((new moodle_url('/local/courseplan/report.php', ['id' => $plan->id]))->out(false));
$sesskey = sesskey();

$inlinejs = <<<JS
require(['jquery', 'core/notification', 'core/toast', 'core/sortable_list'], function($, Notification, Toast, SortableList) {
    // 0. Click progress bar container to navigate to Report page.
    document.querySelectorAll('.progressbar_container, .js-report-link').forEach(function(el) {
        el.style.cursor = 'pointer';
        el.addEventListener('click', function(e) {
            window.location.href = {$jsReportUrl};
        });
    });

    // 1. AJAX Status Change with Backend Quality Gate verification.
    document.querySelectorAll('.js-status-select').forEach(function(selectEl) {
        selectEl.addEventListener('change', function() {
            var self = this;
            var itemid = this.getAttribute('data-itemid');
            var newStatus = parseInt(this.value, 10);
            var oldStatus = parseInt(this.getAttribute('data-currentstatus'), 10);

            var formData = new FormData();
            formData.append('action', 'updatestatus');
            formData.append('itemid', itemid);
            formData.append('status', newStatus);
            formData.append('sesskey', '{$sesskey}');

            fetch(M.cfg.wwwroot + '/local/courseplan/ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) {
                    self.value = oldStatus;
                    Notification.alert('Quality Gate', data.error || {$jsFileError}, 'OK');
                } else {
                    self.setAttribute('data-currentstatus', newStatus);
                    Toast.add('Đã cập nhật trạng thái bài giảng: ' + self.options[self.selectedIndex].text, {type: 'success'});
                    var bar = document.querySelector('.progressbar_container .progress-bar');
                    if (bar) {
                        bar.style.width = data.progress + '%';
                        bar.setAttribute('aria-valuenow', data.progress);
                    }
                    var ptext = document.querySelector('.progressbar_container .fw-bold');
                    if (ptext) {
                        ptext.textContent = data.progress + '%';
                    }
                    var cbtn = document.getElementById('btn-complete-plan');
                    if (cbtn) {
                        cbtn.setAttribute('data-progress', data.progress);
                        cbtn.setAttribute('data-incomplete', data.remaining);
                    }
                }
            })
            .catch(function() {
                self.value = oldStatus;
                Toast.add('Có lỗi xảy ra khi kết nối máy chủ.', {type: 'danger'});
            });
        });
    });

    // 2. Complete Plan Button -> Check 100% and Sync to My Courses.
    var completeBtn = document.getElementById('btn-complete-plan');
    if (completeBtn) {
        completeBtn.addEventListener('click', function() {
            var progress = parseInt(this.getAttribute('data-progress'), 10);
            var incomplete = parseInt(this.getAttribute('data-incomplete'), 10);
            var planName = this.getAttribute('data-planname');
            var publishUrl = this.getAttribute('data-publishurl');

            if (progress < 100) {
                var warningBody = 'Tiến độ hiện tại mới đạt <strong>' + progress + '%</strong> ' +
                    '(còn <strong>' + incomplete + ' bài giảng</strong> chưa hoàn tất).<br><br>' +
                    'Bạn có chắc chắn muốn chốt kế hoạch <strong>' + planName + '</strong> và tự động xuất bản thành khóa học thực tế trong <strong>My courses</strong>?';
                Notification.saveCancel(
                    {$jsWarningTitle},
                    warningBody,
                    {$jsForceBtn},
                    function() {
                        window.location.href = publishUrl;
                    }
                );
            } else {
                Notification.saveCancel(
                    'Hoàn tất Kế hoạch (100%)',
                    {$jsConfirm100},
                    'Hoàn tất & Xuất bản',
                    function() {
                        window.location.href = publishUrl;
                    }
                );
            }
        });
    }

    // 3. AJAX Comment Submission.
    document.querySelectorAll('.js-send-comment').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var itemid = this.getAttribute('data-itemid');
            var input = document.getElementById('comment-input-' + itemid);
            if (!input || !input.value.trim()) {
                return;
            }

            var formData = new FormData();
            formData.append('action', 'addcomment');
            formData.append('itemid', itemid);
            formData.append('content', input.value.trim());
            formData.append('sesskey', '{$sesskey}');

            fetch(M.cfg.wwwroot + '/local/courseplan/ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    var list = document.getElementById('comment-list-' + itemid);
                    var itemDiv = document.createElement('div');
                    itemDiv.className = 'd-flex align-items-start mb-2 pb-2 border-bottom';
                    itemDiv.innerHTML = '<div class="small flex-grow-1"><div><strong>' + data.username +
                        '</strong> <span class="text-muted ms-2">' + data.timeago + '</span></div>' +
                        '<div class="mt-1">' + data.content + '</div></div>';
                    list.appendChild(itemDiv);
                    input.value = '';
                    Toast.add('Đã lưu bình luận phản hồi!', {type: 'success'});
                } else {
                    Toast.add(data.error || 'Lỗi khi gửi bình luận.', {type: 'danger'});
                }
            });
        });
    });

    // 4. Moodle Core SortableList for Drag & Drop Reordering of Lessons and Sections (Edit Mode).
    if (document.querySelector('.js-item-drag')) {
        var itemSortable = new SortableList('.js-sortable-items', {
            targetListSelector: '.js-sortable-items',
            moveHandlerSelector: '.js-item-drag'
        });
        itemSortable.getElementName = function(element) {
            return $.Deferred().resolve(element.attr('data-name') || '');
        };

        $('.js-sortable-items > *').on(SortableList.EVENTS.DROP, function(evt, info) {
            if (!info.positionChanged) {
                return;
            }
            var targetUl = info.targetList[0];
            var sectionId = targetUl.getAttribute('data-sectionid');
            var itemIds = [];
            targetUl.querySelectorAll('li.js-item-li').forEach(function(li) {
                itemIds.push(li.getAttribute('data-id'));
            });

            var formData = new FormData();
            formData.append('action', 'reorderitems');
            formData.append('sectionid', sectionId);
            formData.append('itemids', itemIds.join(','));
            formData.append('sesskey', '{$sesskey}');

            fetch(M.cfg.wwwroot + '/local/courseplan/ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    Toast.add('Đã lưu vị trí bài giảng mới!', {type: 'success'});
                }
            });
        });
    }

    if (document.querySelector('.js-section-drag')) {
        var secSortable = new SortableList('.js-sortable-sections', {
            moveHandlerSelector: '.js-section-drag'
        });
        secSortable.getElementName = function(element) {
            return $.Deferred().resolve(element.attr('data-sectionname') || '');
        };

        $('.js-sortable-sections > li.js-section-li').on(SortableList.EVENTS.DROP, function(evt, info) {
            if (!info.positionChanged) {
                return;
            }
            var secIds = [];
            document.querySelectorAll('.js-sortable-sections > li.js-section-li').forEach(function(li) {
                secIds.push(li.getAttribute('data-sectionid'));
            });

            var formData = new FormData();
            formData.append('action', 'reordersections');
            formData.append('sectionids', secIds.join(','));
            formData.append('sesskey', '{$sesskey}');

            fetch(M.cfg.wwwroot + '/local/courseplan/ajax.php', {
                method: 'POST',
                body: formData
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    Toast.add('Đã lưu thứ tự chương mới!', {type: 'success'});
                }
            });
        });
    }
});
JS;

$PAGE->requires->js_init_code($inlinejs);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_courseplan/plan_view', $templatecontext);
echo $OUTPUT->footer();
