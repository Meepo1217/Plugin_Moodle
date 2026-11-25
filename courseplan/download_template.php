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
 * Download sample Excel template for importing Course Plans.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

$format = optional_param('format', 'xlsx', PARAM_ALPHA);

if ($format === 'csv') {
    $filename = 'Mau_Nhap_Giao_Trinh.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    echo "\xEF\xBB\xBF"; // UTF-8 BOM.
    echo "STT,Tên môn,Chương,Tên bài,Tên giáo viên,Cần nộp file (Có/Không)\n";
    echo "1,Lập trình Web Hiện đại,Chương 1: Tổng quan Web Hiện đại,Bài 1.1: Giới thiệu Kiến trúc Microservices & RESTful API,Admin User,Có\n";
    echo "2,Lập trình Web Hiện đại,Chương 1: Tổng quan Web Hiện đại,Bài 1.2: Xác thực & Phân quyền Bảo mật với JWT,The Anh Nguyen,Có\n";
    echo "3,Lập trình Web Hiện đại,Chương 2: Phát triển Backend API,Bài 2.1: Thiết kế API chuẩn OpenAPI (Swagger),cousercreator,Không\n";
    echo "4,Lập trình Web Hiện đại,Chương 2: Phát triển Backend API,Bài 2.2: Tối ưu hóa Database & Redis Caching,gv1,Có\n";
    exit;
}

// Generate XLSX file.
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Kế hoạch giáo trình');

// Header row.
$headers = ['STT', 'Tên môn', 'Chương', 'Tên bài', 'Tên giáo viên', 'Cần nộp file (Có/Không)'];
foreach ($headers as $colIdx => $header) {
    $colLetter = chr(65 + $colIdx);
    $sheet->setCellValue($colLetter . '1', $header);
}

// Style header row.
$headerStyle = [
    'font' => [
        'name' => 'Times New Roman',
        'bold' => true,
        'size' => 12,
        'color' => ['rgb' => 'FFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '217346'], // Excel green.
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
];
$sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
$sheet->getRowDimension('1')->setRowHeight(28);

// Sample data.
$sampleRows = [
    [1, 'Lập trình Web Hiện đại', 'Chương 1: Tổng quan Web Hiện đại', 'Bài 1.1: Giới thiệu Kiến trúc Microservices & RESTful API', 'Admin User', 'Có'],
    [2, 'Lập trình Web Hiện đại', 'Chương 1: Tổng quan Web Hiện đại', 'Bài 1.2: Xác thực & Phân quyền Bảo mật với JWT', 'The Anh Nguyen', 'Có'],
    [3, 'Lập trình Web Hiện đại', 'Chương 2: Phát triển Backend API', 'Bài 2.1: Thiết kế API chuẩn OpenAPI (Swagger)', 'cousercreator', 'Không'],
    [4, 'Lập trình Web Hiện đại', 'Chương 2: Phát triển Backend API', 'Bài 2.2: Tối ưu hóa Database & Redis Caching', 'gv1', 'Có'],
];

$rowNum = 2;
foreach ($sampleRows as $row) {
    foreach ($row as $colIdx => $val) {
        $colLetter = chr(65 + $colIdx);
        $sheet->setCellValue($colLetter . $rowNum, $val);
    }
    $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('F' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getRowDimension($rowNum)->setRowHeight(22);
    $rowNum++;
}

// Font for data rows.
$dataStyle = [
    'font' => [
        'name' => 'Times New Roman',
        'size' => 11,
    ],
];
$sheet->getStyle('A2:F' . ($rowNum - 1))->applyFromArray($dataStyle);

// Add Select Box (DataValidation dropdown list "Có,Không") for Column F (rows 2 to 200).
for ($r = 2; $r <= 200; $r++) {
    $validation = $sheet->getCell('F' . $r)->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_STOP);
    $validation->setAllowBlank(false);
    $validation->setShowInputMessage(true);
    $validation->setShowErrorMessage(true);
    $validation->setShowDropDown(true);
    $validation->setErrorTitle('Giá trị không hợp lệ');
    $validation->setError('Vui lòng chọn "Có" hoặc "Không" từ danh sách.');
    $validation->setPromptTitle('Bắt buộc nộp file?');
    $validation->setPrompt('Chọn "Có" nếu bắt buộc nộp file tài liệu, "Không" nếu không yêu cầu.');
    $validation->setFormula1('"Có,Không"');
}

// Set column widths.
$sheet->getColumnDimension('A')->setWidth(8);
$sheet->getColumnDimension('B')->setWidth(26);
$sheet->getColumnDimension('C')->setWidth(35);
$sheet->getColumnDimension('D')->setWidth(50);
$sheet->getColumnDimension('E')->setWidth(22);
$sheet->getColumnDimension('F')->setWidth(26);

$filename = 'Mau_Nhap_Giao_Trinh.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
