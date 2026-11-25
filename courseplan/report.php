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
 * Report page for local_courseplan (Báo cáo tiến độ bài giảng có phân trang & sắp xếp).
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/permission.php');

$id     = required_param('id', PARAM_INT); // Plan ID.
$export = optional_param('export', '', PARAM_ALPHA); // excel or word.

require_login();
$context = context_system::instance();

$plan = $DB->get_record('local_courseplan_plans', ['id' => $id], '*', MUST_EXIST);

// Permission check: Only admin and plan participants can view the report.
if (!\local_courseplan\permission::can_view_plan($plan, (int)$USER->id)) {
    throw new moodle_exception('nopermissions', 'error', new moodle_url('/local/courseplan/index.php'), 'Bạn không có quyền xem báo cáo của kế hoạch này.');
}

$pagetitle = 'Report';

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseplan/report.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($pagetitle . ' - ' . format_string($plan->name));
$PAGE->set_heading($pagetitle);

// Breadcrumbs navigation.
$PAGE->navbar->add(get_string('nav_title', 'local_courseplan'), new moodle_url('/local/courseplan/index.php'));
$PAGE->navbar->add(format_string($plan->shortname), new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]));
$PAGE->navbar->add($pagetitle);

// Query all items and their sections ordered strictly by section and item order.
$sql = "SELECT i.id, i.planid, i.sectionid, i.title, i.assigneeid, i.filename, i.resourceurl, i.status,
               s.title AS sectiontitle, s.sortorder AS sectionsortorder
          FROM {local_courseplan_items} i
          JOIN {local_courseplan_sections} s ON i.sectionid = s.id
         WHERE i.planid = :planid
      ORDER BY s.sortorder ASC, s.id ASC, i.sortorder ASC, i.id ASC";

$items = $DB->get_records_sql($sql, ['planid' => $plan->id]);

// Preload user names for assignees.
$assigneeids = [];
foreach ($items as $it) {
    if (!empty($it->assigneeid)) {
        $assigneeids[$it->assigneeid] = true;
    }
}

$usermap = [];
if (!empty($assigneeids)) {
    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($assigneeids), SQL_PARAMS_NAMED);
    $users = $DB->get_records_select('user', "id $insql", $inparams, '', 'id, firstname, lastname');
    foreach ($users as $u) {
        $usermap[$u->id] = fullname($u);
    }
}

// Preload comment counts to determine interaction.
$commentcounts = $DB->get_records_sql_menu(
    "SELECT itemid, COUNT(id) AS cnt
       FROM {local_courseplan_comments}
      WHERE itemid IN (SELECT id FROM {local_courseplan_items} WHERE planid = :planid)
   GROUP BY itemid",
    ['planid' => $plan->id]
);

$creator = $DB->get_record('user', ['id' => $plan->creatorid]);
$creatorname = $creator ? fullname($creator) : '-';

// Compute statistics per teacher for the column chart & summary.
$teacherstats = [];
foreach ($items as $item) {
    if (!empty($item->assigneeid)) {
        $tname = $usermap[$item->assigneeid] ?? ('Giáo viên #' . $item->assigneeid);
        $tkey = 'u_' . $item->assigneeid;
    } else {
        $tname = 'Chưa phân công';
        $tkey = 'unassigned';
    }

    if (!isset($teacherstats[$tkey])) {
        $teacherstats[$tkey] = [
            'name' => $tname,
            'total' => 0,
            'done' => 0,
            'pending' => 0,
        ];
    }

    $teacherstats[$tkey]['total']++;
    if ((int)$item->status === 3) {
        $teacherstats[$tkey]['done']++;
    } else {
        $teacherstats[$tkey]['pending']++;
    }
}

// --------------------------------------------------------------------------------
// Export Handler (Excel / Word) with extra "Tên môn" column inserted between STT and Chương.
// --------------------------------------------------------------------------------
if ($export === 'excel' || $export === 'word') {
    $now = date('d/m/Y');
    $cleanCode = clean_param($plan->shortname, PARAM_FILE);
    if (empty($cleanCode)) {
        $cleanCode = 'Plan_' . $plan->id;
    }

    if ($export === 'excel') {
        $filename = 'Bao_cao_' . $cleanCode . '_' . date('Ymd_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    } else {
        $filename = 'Bao_cao_' . $cleanCode . '_' . date('Ymd_His') . '.doc';
        header('Content-Type: application/msword; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM for Microsoft Excel and Word Vietnamese support.

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'body, table, td, th, div, p { font-family: "Times New Roman", Times, serif; color: #000000; font-size: 13pt; }';
    echo 'table { border-collapse: collapse; width: 100%; margin-top: 15px; }';
    echo 'th { border: 1px solid #000000; padding: 8px 10px; font-weight: bold; text-align: center; background-color: #f2f2f2; }';
    echo 'td { border: 1px solid #000000; padding: 8px 10px; }';
    echo '.text-center { text-align: center; }';
    echo '.title { font-size: 18pt; font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 5px; }';
    echo '.subtitle { font-size: 13pt; text-align: center; margin-bottom: 20px; font-style: italic; }';
    echo '.meta { font-size: 13pt; margin-bottom: 15px; line-height: 1.6; }';
    echo '</style></head><body>';

    echo '<div class="title">BÁO CÁO TIẾN ĐỘ BIÊN SOẠN GIÁO TRÌNH</div>';
    echo '<div class="subtitle">(Học phần: ' . s($plan->name) . ' - Mã: ' . s($plan->shortname) . ')</div>';

    echo '<div class="meta">';
    echo '<div><strong>Người lập kế hoạch:</strong> ' . s($creatorname) . '</div>';
    echo '<div><strong>Ngày xuất báo cáo:</strong> ' . $now . '</div>';
    echo '<div><strong>Tổng số bài giảng:</strong> ' . count($items) . ' bài</div>';
    echo '</div>';

    // Summary table by Teacher in exported file.
    if (!empty($teacherstats)) {
        echo '<div style="margin-top: 20px; margin-bottom: 8px; font-weight: bold; text-transform: uppercase;">THỐNG KÊ TIẾN ĐỘ THEO GIÁO VIÊN:</div>';
        echo '<table style="margin-bottom: 25px;">';
        echo '<thead><tr>';
        echo '<th style="width: 50px;">STT</th>';
        echo '<th>Giáo viên</th>';
        echo '<th style="width: 170px;">Số bài trong kế hoạch</th>';
        echo '<th style="width: 140px;">Đã hoàn thành</th>';
        echo '<th style="width: 150px;">Chưa hoàn thành</th>';
        echo '<th style="width: 120px;">Tỷ lệ (%)</th>';
        echo '</tr></thead><tbody>';

        $sumTotalExp = 0;
        $sumDoneExp = 0;
        $sumPendingExp = 0;
        foreach ($teacherstats as $tstat) {
            $sumTotalExp += $tstat['total'];
            $sumDoneExp += $tstat['done'];
            $sumPendingExp += $tstat['pending'];
        }
        $sumPctExp = $sumTotalExp > 0 ? round(($sumDoneExp / $sumTotalExp) * 100) : 0;

        echo '<tr style="font-weight: bold; background-color: #f2f2f2;">';
        echo '<td class="text-center">-</td>';
        echo '<td>Tổng cộng</td>';
        echo '<td class="text-center">' . $sumTotalExp . '</td>';
        echo '<td class="text-center">' . $sumDoneExp . '</td>';
        echo '<td class="text-center">' . $sumPendingExp . '</td>';
        echo '<td class="text-center">' . $sumPctExp . '%</td>';
        echo '</tr>';

        $sttTeacher = 1;
        foreach ($teacherstats as $tstat) {
            $pct = $tstat['total'] > 0 ? round(($tstat['done'] / $tstat['total']) * 100) : 0;
            echo '<tr>';
            echo '<td class="text-center">' . $sttTeacher++ . '</td>';
            echo '<td>' . s($tstat['name']) . '</td>';
            echo '<td class="text-center">' . $tstat['total'] . '</td>';
            echo '<td class="text-center">' . $tstat['done'] . '</td>';
            echo '<td class="text-center">' . $tstat['pending'] . '</td>';
            echo '<td class="text-center">' . $pct . '%</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    echo '<div style="margin-top: 20px; margin-bottom: 8px; font-weight: bold; text-transform: uppercase;">CHI TIẾT DANH SÁCH BÀI GIẢNG:</div>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th style="width: 50px;">STT</th>';
    echo '<th style="width: 250px;">Tên môn</th>'; // Cột Tên môn chèn giữa STT và Chương!
    echo '<th style="width: 250px;">Chương</th>';
    echo '<th style="width: 320px;">Tên bài</th>';
    echo '<th style="width: 180px;">Tên giáo viên</th>';
    echo '<th style="width: 140px;">Trạng thái</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    $stt = 1;
    foreach ($items as $item) {
        $teachername = !empty($item->assigneeid) ? ($usermap[$item->assigneeid] ?? 'Chưa xác định') : 'Chưa phân công';

        $hasfileorlink = (!empty($item->filename) || !empty($item->resourceurl));
        $hasinteraction = !empty($commentcounts[$item->id]) || $hasfileorlink;

        if ((int)$item->status === 3) {
            $statusdisplay = 'Hoàn thành';
        } else if ((int)$item->status === 1 || (int)$item->status === 2 || $hasinteraction) {
            $statusdisplay = 'Đang hoàn thành';
        } else {
            $statusdisplay = 'Chưa làm';
        }

        echo '<tr>';
        echo '<td class="text-center">' . $stt++ . '</td>';
        echo '<td>' . s($plan->name) . '</td>'; // Tên môn
        echo '<td>' . s($item->sectiontitle) . '</td>';
        echo '<td>' . s($item->title) . '</td>';
        echo '<td>' . s($teachername) . '</td>';
        echo '<td class="text-center">' . $statusdisplay . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</body></html>';
    exit;
}

echo $OUTPUT->header();

// Back button.
$backurl = new moodle_url('/local/courseplan/view.php', ['id' => $plan->id]);
echo html_writer::div(
    html_writer::link($backurl, '&larr; Quay lại Kế hoạch', ['class' => 'btn btn-outline-secondary btn-sm', 'style' => "font-family: 'Times New Roman', Times, serif;"]),
    'mb-3'
);

// Report Container formatted strictly with Times New Roman and plain black/white text.
echo '<div class="report-wrapper p-4 bg-white border rounded" style="font-family: \'Times New Roman\', Times, serif; color: #000000; line-height: 1.6;">';

// Document Header.
echo '<div style="text-align: center; margin-bottom: 25px;">';
echo '<h2 style="font-family: \'Times New Roman\', Times, serif; font-size: 24px; font-weight: bold; text-transform: uppercase; margin-bottom: 8px; color: #000000;">BÁO CÁO TIẾN ĐỘ BIÊN SOẠN GIÁO TRÌNH</h2>';
echo '<p style="font-size: 16px; margin-bottom: 0; color: #000000;">(Học phần: <strong>' . s($plan->name) . '</strong> - Mã: <strong>' . s($plan->shortname) . '</strong>)</p>';
echo '</div>';

echo '<div style="font-size: 16px; margin-bottom: 15px; color: #000000;">';
echo '<div><strong>Người lập kế hoạch:</strong> ' . s($creatorname) . '</div>';
echo '<div><strong>Ngày xuất báo cáo:</strong> ' . date('d/m/Y') . '</div>';
echo '</div>';

// --------------------------------------------------------------------------------
// Column Chart: Teacher Progress (Giáo viên x Số bài: Kế hoạch / Đã xong / Chưa xong)
// --------------------------------------------------------------------------------
if (!empty($teacherstats)) {
    $labels = [];
    $dataTotal = [];
    $dataDone = [];
    $dataPending = [];

    // Calculate totals across the entire plan.
    $sumTotal = 0;
    $sumDone = 0;
    $sumPending = 0;
    foreach ($teacherstats as $tstat) {
        $sumTotal += $tstat['total'];
        $sumDone += $tstat['done'];
        $sumPending += $tstat['pending'];
    }

    // Giá trị Tổng đứng đầu mặc định ở trục dưới (X-axis).
    $labels[] = 'Tổng';
    $dataTotal[] = $sumTotal;
    $dataDone[] = $sumDone;
    $dataPending[] = $sumPending;

    // Tiếp theo là các giáo viên.
    foreach ($teacherstats as $tstat) {
        $labels[] = $tstat['name'];
        $dataTotal[] = $tstat['total'];
        $dataDone[] = $tstat['done'];
        $dataPending[] = $tstat['pending'];
    }

    $chart = new \core\chart_bar();
    $chart->set_title(''); // Title is displayed in H3 above.

    $series1 = new \core\chart_series('Số bài trong kế hoạch', $dataTotal);
    $series1->set_color('#2b579a'); // Xanh dương

    $series2 = new \core\chart_series('Đã hoàn thành', $dataDone);
    $series2->set_color('#217346'); // Xanh lá

    $series3 = new \core\chart_series('Chưa hoàn thành', $dataPending);
    $series3->set_color('#6c757d'); // Màu xám (Gray)

    $chart->add_series($series1);
    $chart->add_series($series2);
    $chart->add_series($series3);
    $chart->set_labels($labels);

    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label('Số bài');
    $maxVal = !empty($dataTotal) ? max($dataTotal) : 1;
    $yaxis->set_stepsize(max(1, ceil($maxVal / 5)));
    $yaxis->set_min(0);

    echo '<div class="report-chart-box mb-4 p-3 border rounded" style="background-color: #ffffff; border: 1px solid #000000 !important; font-family: \'Times New Roman\', Times, serif;">';
    echo '<h3 style="font-family: \'Times New Roman\', Times, serif; font-size: 18px; font-weight: bold; text-align: center; margin-bottom: 12px; color: #000000; text-transform: uppercase;">BIỂU ĐỒ TIẾN ĐỘ BIÊN SOẠN THEO GIÁO VIÊN</h3>';

    // Explicit color legend bar.
    echo '<div style="display: flex; justify-content: center; align-items: center; gap: 20px; flex-wrap: wrap; margin-bottom: 15px; font-size: 14px;">';
    echo '<div style="display: flex; align-items: center; gap: 6px;"><span style="display: inline-block; width: 14px; height: 14px; background-color: #2b579a; border-radius: 2px;"></span> <strong>Số bài trong kế hoạch</strong></div>';
    echo '<div style="display: flex; align-items: center; gap: 6px;"><span style="display: inline-block; width: 14px; height: 14px; background-color: #217346; border-radius: 2px;"></span> <strong>Đã hoàn thành</strong></div>';
    echo '<div style="display: flex; align-items: center; gap: 6px;"><span style="display: inline-block; width: 14px; height: 14px; background-color: #6c757d; border-radius: 2px;"></span> <strong>Chưa hoàn thành</strong></div>';
    echo '</div>';

    // Chart container.
    echo '<style>.report-chart-box .chart-area .chart-image { max-width: 820px; height: 350px !important; margin: 0 auto; }</style>';
    echo '<div style="max-width: 850px; margin: 0 auto;">';
    echo $OUTPUT->render($chart, false);
    echo '</div>';

    // Summary table by Teacher underneath chart, matching user design with "Tỷ lệ (%)" column added.
    echo '<div style="max-width: 850px; margin: 25px auto 10px auto;">';
    echo '<table style="width: 100%; border-collapse: collapse; font-family: \'Times New Roman\', Times, serif; font-size: 15px; color: #000000; background-color: #ffffff;">';
    echo '<thead>';
    echo '<tr style="border-bottom: 1px solid #dee2e6;">';
    echo '<th style="padding: 10px 14px; text-align: left; font-weight: bold; border: none; border-bottom: 1px solid #dee2e6; width: 25%;"></th>';
    echo '<th style="padding: 10px 14px; text-align: left; font-weight: bold; border: none; border-bottom: 1px solid #dee2e6; width: 22%;">Số bài trong kế hoạch</th>';
    echo '<th style="padding: 10px 14px; text-align: left; font-weight: bold; border: none; border-bottom: 1px solid #dee2e6; width: 18%;">Đã hoàn thành</th>';
    echo '<th style="padding: 10px 14px; text-align: left; font-weight: bold; border: none; border-bottom: 1px solid #dee2e6; width: 18%;">Chưa hoàn thành</th>';
    echo '<th style="padding: 10px 14px; text-align: left; font-weight: bold; border: none; border-bottom: 1px solid #dee2e6; width: 17%;">Tỷ lệ (%)</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    // Hàng Tổng cộng đứng đầu bảng.
    $sumPct = $sumTotal > 0 ? round(($sumDone / $sumTotal) * 100) : 0;
    echo '<tr style="border-bottom: 2px solid #dee2e6; background-color: #f8f9fa;">';
    echo '<td style="padding: 12px 14px; font-weight: bold; text-align: left; border: none;">Tổng cộng</td>';
    echo '<td style="padding: 12px 14px; text-align: left; border: none; font-weight: bold;">' . $sumTotal . '</td>';
    echo '<td style="padding: 12px 14px; text-align: left; border: none; font-weight: bold;">' . $sumDone . '</td>';
    echo '<td style="padding: 12px 14px; text-align: left; border: none; font-weight: bold;">' . $sumPending . '</td>';
    echo '<td style="padding: 12px 14px; text-align: left; border: none; font-weight: bold;">' . $sumPct . '%</td>';
    echo '</tr>';

    foreach ($teacherstats as $tstat) {
        $pct = $tstat['total'] > 0 ? round(($tstat['done'] / $tstat['total']) * 100) : 0;
        echo '<tr style="border-bottom: 1px solid #e9ecef;">';
        echo '<td style="padding: 12px 14px; font-weight: bold; text-align: left; border: none;">' . s($tstat['name']) . '</td>';
        echo '<td style="padding: 12px 14px; text-align: left; border: none;">' . $tstat['total'] . '</td>';
        echo '<td style="padding: 12px 14px; text-align: left; border: none;">' . $tstat['done'] . '</td>';
        echo '<td style="padding: 12px 14px; text-align: left; border: none;">' . $tstat['pending'] . '</td>';
        echo '<td style="padding: 12px 14px; text-align: left; border: none;">' . $pct . '%</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';

    echo '</div>'; // End report-chart-box.
}

// Interactive toolbar: Quick search, Export buttons & Page-size selector.
$excelexporturl = (new moodle_url('/local/courseplan/report.php', ['id' => $plan->id, 'export' => 'excel']))->out(false);
$wordexporturl  = (new moodle_url('/local/courseplan/report.php', ['id' => $plan->id, 'export' => 'word']))->out(false);

echo '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; font-family: \'Times New Roman\', Times, serif; font-size: 15px;">';
echo '<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">';
echo '<div><strong>Tìm kiếm:</strong> <input type="text" id="report-search-input" placeholder="Gõ từ khóa để lọc bài..." style="font-family: \'Times New Roman\', Times, serif; font-size: 15px; padding: 4px 8px; border: 1px solid #000000; width: 240px; color: #000000; background: #ffffff;"></div>';
echo '<div style="display: flex; align-items: center; gap: 6px;">';
echo '<a href="' . $excelexporturl . '" class="btn btn-sm" style="font-family: \'Times New Roman\', Times, serif; font-size: 14px; font-weight: bold; background-color: #217346; color: #ffffff; border: 1px solid #217346; padding: 5px 12px; border-radius: 4px; text-decoration: none; display: inline-block;" title="Xuất báo cáo sang file Excel (.xls)">Xuất Excel</a>';
echo '<a href="' . $wordexporturl . '" class="btn btn-sm" style="font-family: \'Times New Roman\', Times, serif; font-size: 14px; font-weight: bold; background-color: #2b579a; color: #ffffff; border: 1px solid #2b579a; padding: 5px 12px; border-radius: 4px; text-decoration: none; display: inline-block;" title="Xuất báo cáo sang file Word (.doc)">Xuất Word</a>';
echo '</div>';
echo '</div>';
echo '<div style="display: flex; align-items: center; gap: 8px;">';
echo '<label for="report-page-size" style="margin-bottom: 0; font-weight: bold;">Hiển thị:</label>';
echo '<select id="report-page-size" style="font-family: \'Times New Roman\', Times, serif; font-size: 15px; padding: 4px 8px; border: 1px solid #000000; color: #000000; background: #ffffff; cursor: pointer;">';
echo '<option value="5" selected>5 bản ghi / trang</option>';
echo '<option value="10">10 bản ghi / trang</option>';
echo '<option value="20">20 bản ghi / trang</option>';
echo '<option value="50">50 bản ghi / trang</option>';
echo '<option value="999999">Tất cả bản ghi</option>';
echo '</select>';
echo '</div>';
echo '</div>';

// Table with the exact requested columns: STT, Chương, Tên bài, Tên giáo viên, Trạng thái.
echo '<table id="report-table" style="width: 100%; border-collapse: collapse; font-family: \'Times New Roman\', Times, serif; font-size: 16px; color: #000000; margin-top: 5px;">';
echo '<thead>';
echo '<tr style="border: 1px solid #000000; background-color: #ffffff;">';
echo '<th data-col="0" style="border: 1px solid #000000; padding: 10px; text-align: center; font-weight: bold; width: 70px; cursor: pointer; user-select: none;" title="Bấm để sắp xếp theo STT">STT <span class="sort-icon">⇅</span></th>';
echo '<th data-col="1" style="border: 1px solid #000000; padding: 10px; text-align: left; font-weight: bold; width: 25%; cursor: pointer; user-select: none;" title="Bấm để sắp xếp theo Chương">Chương <span class="sort-icon">⇅</span></th>';
echo '<th data-col="2" style="border: 1px solid #000000; padding: 10px; text-align: left; font-weight: bold; width: 34%; cursor: pointer; user-select: none;" title="Bấm để sắp xếp theo Tên bài">Tên bài <span class="sort-icon">⇅</span></th>';
echo '<th data-col="3" style="border: 1px solid #000000; padding: 10px; text-align: left; font-weight: bold; width: 21%; cursor: pointer; user-select: none;" title="Bấm để sắp xếp theo Tên giáo viên">Tên giáo viên <span class="sort-icon">⇅</span></th>';
echo '<th data-col="4" style="border: 1px solid #000000; padding: 10px; text-align: center; font-weight: bold; width: 20%; cursor: pointer; user-select: none;" title="Bấm để sắp xếp theo Trạng thái">Trạng thái <span class="sort-icon">⇅</span></th>';
echo '</tr>';
echo '</thead>';
echo '<tbody id="report-tbody">';

if (empty($items)) {
    echo '<tr>';
    echo '<td colspan="5" style="border: 1px solid #000000; padding: 20px; text-align: center; font-style: italic;">Chưa có bài giảng nào trong kế hoạch này.</td>';
    echo '</tr>';
} else {
    $stt = 1;
    foreach ($items as $item) {
        $teachername = !empty($item->assigneeid) ? ($usermap[$item->assigneeid] ?? 'Chưa xác định') : 'Chưa phân công';

        // Determine status according to user specification:
        // - Hoàn thành (khi status = 3)
        // - Đang hoàn thành (khi status = 1, 2 hoặc đã có tương tác/file)
        // - Chưa làm (khi mà chưa đẩy lên hoặc tương tác)
        $hasfileorlink = (!empty($item->filename) || !empty($item->resourceurl));
        $hasinteraction = !empty($commentcounts[$item->id]) || $hasfileorlink;

        if ((int)$item->status === 3) {
            $statusdisplay = 'Hoàn thành';
        } else if ((int)$item->status === 1 || (int)$item->status === 2 || $hasinteraction) {
            $statusdisplay = 'Đang hoàn thành';
        } else {
            $statusdisplay = 'Chưa làm';
        }

        echo '<tr class="report-row">';
        echo '<td style="border: 1px solid #000000; padding: 8px 10px; text-align: center;">' . $stt++ . '</td>';
        echo '<td style="border: 1px solid #000000; padding: 8px 10px;">' . s($item->sectiontitle) . '</td>';
        echo '<td style="border: 1px solid #000000; padding: 8px 10px;">' . s($item->title) . '</td>';
        echo '<td style="border: 1px solid #000000; padding: 8px 10px;">' . s($teachername) . '</td>';
        echo '<td style="border: 1px solid #000000; padding: 8px 10px; text-align: center;">' . $statusdisplay . '</td>';
        echo '</tr>';
    }
}

echo '</tbody>';
echo '</table>';

// Footer: Pagination controls & Summary text.
echo '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 15px; font-family: \'Times New Roman\', Times, serif; font-size: 15px;">';
echo '<div id="report-info-text" style="font-style: italic; color: #000000;"></div>';
echo '<div id="report-pagination" style="display: flex; align-items: center; gap: 4px;"></div>';
echo '</div>';

echo '</div>'; // End report-wrapper.

// Interactive JavaScript: Click column headers to sort, instant search & client-side pagination.
echo <<<HTML
<script>
(function() {
    function initInteractiveTable() {
        var table = document.getElementById('report-table');
        if (!table || table.getAttribute('data-interactive-ready')) {
            return;
        }
        table.setAttribute('data-interactive-ready', 'true');

        var headers = table.querySelectorAll('thead th');
        var tbody = document.getElementById('report-tbody');
        var searchInput = document.getElementById('report-search-input');
        var pageSizeSelect = document.getElementById('report-page-size');
        var infoTextDiv = document.getElementById('report-info-text');
        var paginationDiv = document.getElementById('report-pagination');

        var allRows = Array.from(tbody.querySelectorAll('tr.report-row'));
        var filteredRows = allRows.slice();

        var currentSortCol = -1;
        var currentSortAsc = true;
        var currentPage = 1;
        var pageSize = parseInt(pageSizeSelect.value, 10) || 5;

        // Function: Render current page of rows and pagination buttons.
        function updatePagination() {
            var totalItems = filteredRows.length;
            var totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

            if (currentPage > totalPages) {
                currentPage = totalPages;
            }
            if (currentPage < 1) {
                currentPage = 1;
            }

            var startIndex = (currentPage - 1) * pageSize;
            var endIndex = Math.min(startIndex + pageSize, totalItems);

            // Hide all rows first, then show only the ones on the current page.
            allRows.forEach(function(row) {
                row.style.display = 'none';
            });

            for (var i = startIndex; i < endIndex; i++) {
                if (filteredRows[i]) {
                    filteredRows[i].style.display = '';
                }
            }

            // Update info summary text.
            if (totalItems === 0) {
                infoTextDiv.textContent = 'Không tìm thấy bài giảng nào phù hợp.';
            } else {
                infoTextDiv.textContent = 'Hiển thị: ' + (startIndex + 1) + ' - ' + endIndex + ' / ' + totalItems + ' bài giảng (Trang ' + currentPage + ' / ' + totalPages + ')';
            }

            // Render pagination buttons.
            paginationDiv.innerHTML = '';
            if (totalPages <= 1 && totalItems > 0) {
                return; // No pagination buttons needed if only 1 page.
            }

            // Previous Button.
            var prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.innerHTML = '&laquo; Trước';
            prevBtn.style.cssText = "font-family: 'Times New Roman', Times, serif; font-size: 14px; background: #ffffff; color: #000000; border: 1px solid #000000; padding: 3px 8px; cursor: pointer;";
            if (currentPage === 1) {
                prevBtn.disabled = true;
                prevBtn.style.opacity = '0.4';
                prevBtn.style.cursor = 'not-allowed';
            } else {
                prevBtn.addEventListener('click', function() {
                    currentPage--;
                    updatePagination();
                });
            }
            paginationDiv.appendChild(prevBtn);

            // Page Number Buttons.
            for (var p = 1; p <= totalPages; p++) {
                (function(pageNumber) {
                    var pageBtn = document.createElement('button');
                    pageBtn.type = 'button';
                    pageBtn.textContent = pageNumber;
                    if (pageNumber === currentPage) {
                        pageBtn.style.cssText = "font-family: 'Times New Roman', Times, serif; font-size: 14px; background: #000000; color: #ffffff; border: 1px solid #000000; padding: 3px 9px; font-weight: bold; cursor: default;";
                    } else {
                        pageBtn.style.cssText = "font-family: 'Times New Roman', Times, serif; font-size: 14px; background: #ffffff; color: #000000; border: 1px solid #000000; padding: 3px 9px; cursor: pointer;";
                        pageBtn.addEventListener('click', function() {
                            currentPage = pageNumber;
                            updatePagination();
                        });
                    }
                    paginationDiv.appendChild(pageBtn);
                })(p);
            }

            // Next Button.
            var nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.innerHTML = 'Sau &raquo;';
            nextBtn.style.cssText = "font-family: 'Times New Roman', Times, serif; font-size: 14px; background: #ffffff; color: #000000; border: 1px solid #000000; padding: 3px 8px; cursor: pointer;";
            if (currentPage === totalPages || totalItems === 0) {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.4';
                nextBtn.style.cursor = 'not-allowed';
            } else {
                nextBtn.addEventListener('click', function() {
                    currentPage++;
                    updatePagination();
                });
            }
            paginationDiv.appendChild(nextBtn);
        }

        // 1. Column Sorting when clicking on headers.
        headers.forEach(function(th) {
            th.addEventListener('click', function() {
                var colIndex = parseInt(th.getAttribute('data-col'), 10);
                if (allRows.length <= 1) {
                    return;
                }

                if (currentSortCol === colIndex) {
                    currentSortAsc = !currentSortAsc;
                } else {
                    currentSortCol = colIndex;
                    currentSortAsc = true;
                }

                // Update sort icons across all headers.
                headers.forEach(function(h) {
                    var idx = parseInt(h.getAttribute('data-col'), 10);
                    var iconSpan = h.querySelector('.sort-icon');
                    if (iconSpan) {
                        if (idx === colIndex) {
                            iconSpan.textContent = currentSortAsc ? ' ▲' : ' ▼';
                        } else {
                            iconSpan.textContent = ' ⇅';
                        }
                    }
                });

                // Status priority map for sorting status column logically.
                var statusOrder = {
                    'Hoàn thành': 1,
                    'Đang hoàn thành': 2,
                    'Chưa làm': 3
                };

                // Sort both allRows and filteredRows.
                function sortRowArray(arr) {
                    arr.sort(function(rowA, rowB) {
                        var cellA = rowA.children[colIndex] ? rowA.children[colIndex].innerText.trim() : '';
                        var cellB = rowB.children[colIndex] ? rowB.children[colIndex].innerText.trim() : '';

                        if (colIndex === 0) {
                            var numA = parseFloat(cellA) || 0;
                            var numB = parseFloat(cellB) || 0;
                            return currentSortAsc ? (numA - numB) : (numB - numA);
                        }

                        if (colIndex === 4) {
                            var rA = statusOrder[cellA] || 99;
                            var rB = statusOrder[cellB] || 99;
                            if (rA !== rB) {
                                return currentSortAsc ? (rA - rB) : (rB - rA);
                            }
                        }

                        var cmp = cellA.localeCompare(cellB, 'vi', { numeric: true, sensitivity: 'base' });
                        return currentSortAsc ? cmp : -cmp;
                    });
                }

                sortRowArray(allRows);
                sortRowArray(filteredRows);

                // Re-append to tbody.
                allRows.forEach(function(row) {
                    tbody.appendChild(row);
                });

                currentPage = 1;
                updatePagination();
            });

            // Hover effect.
            th.addEventListener('mouseenter', function() {
                th.style.textDecoration = 'underline';
            });
            th.addEventListener('mouseleave', function() {
                th.style.textDecoration = 'none';
            });
        });

        // 2. Real-time instant search filter.
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var query = searchInput.value.toLowerCase().trim();
                filteredRows = allRows.filter(function(row) {
                    var text = row.innerText.toLowerCase();
                    return (!query || text.indexOf(query) !== -1);
                });

                currentPage = 1;
                updatePagination();
            });
        }

        // 3. Page size selector change.
        if (pageSizeSelect) {
            pageSizeSelect.addEventListener('change', function() {
                pageSize = parseInt(pageSizeSelect.value, 10) || 5;
                currentPage = 1;
                updatePagination();
            });
        }

        // Initial render on page load.
        updatePagination();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initInteractiveTable);
    } else {
        initInteractiveTable();
    }
})();
</script>
HTML;

echo $OUTPUT->footer();
