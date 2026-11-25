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

defined('MOODLE_INTERNAL') || die();

use stdClass;
use Exception;
use ZipArchive;
use DOMDocument;

/**
 * Excel / CSV Course Plan Importer with automatic teacher matching.
 *
 * @package    local_courseplan
 * @copyright  2026 Course Plan Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class excel_importer {

    /**
     * Remove Vietnamese accents and normalize string for fuzzy matching.
     *
     * @param string $str
     * @return string
     */
    public static function remove_accents(string $str): string {
        $str = preg_replace("/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u", 'a', $str);
        $str = preg_replace("/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u", 'e', $str);
        $str = preg_replace("/(ì|í|ị|ỉ|ĩ)/u", 'i', $str);
        $str = preg_replace("/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u", 'o', $str);
        $str = preg_replace("/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u", 'u', $str);
        $str = preg_replace("/(ỳ|ý|ỵ|ỷ|ỹ)/u", 'y', $str);
        $str = preg_replace("/(đ)/u", 'd', $str);
        $str = preg_replace("/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/u", 'a', $str);
        $str = preg_replace("/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/u", 'e', $str);
        $str = preg_replace("/(Ì|Í|Ị|Ỉ|Ĩ)/u", 'i', $str);
        $str = preg_replace("/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/u", 'o', $str);
        $str = preg_replace("/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/u", 'u', $str);
        $str = preg_replace("/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/u", 'y', $str);
        $str = preg_replace("/(Đ)/u", 'd', $str);
        return strtolower(trim(preg_replace('/\s+/', ' ', $str)));
    }

    /**
     * Match a raw teacher string against active Moodle users.
     * Matches by username, email, full name, accent-stripped name, and substrings.
     *
     * @param string $raw Raw string from spreadsheet
     * @param array $users List of Moodle user objects
     * @return stdClass|null Matched user object or null
     */
    public static function match_teacher(string $raw, array $users): ?stdClass {
        $raw = trim($raw);
        if ($raw === '' || $raw === '-' || strtolower($raw) === 'chưa phân công' || strtolower($raw) === 'chưa xác định') {
            return null;
        }

        $lower = mb_strtolower($raw, 'UTF-8');
        $unaccented = self::remove_accents($lower);

        // 1. Direct username match.
        foreach ($users as $u) {
            if (mb_strtolower($u->username, 'UTF-8') === $lower || self::remove_accents($u->username) === $unaccented) {
                return $u;
            }
        }

        // 2. Direct email match.
        foreach ($users as $u) {
            if (mb_strtolower($u->email, 'UTF-8') === $lower) {
                return $u;
            }
        }

        // 3. Exact full name match.
        foreach ($users as $u) {
            $n1 = mb_strtolower(fullname($u), 'UTF-8');
            $n2 = mb_strtolower($u->lastname . ' ' . $u->firstname, 'UTF-8');
            $n3 = mb_strtolower($u->firstname . ' ' . $u->lastname, 'UTF-8');
            if ($lower === $n1 || $lower === $n2 || $lower === $n3) {
                return $u;
            }
        }

        // 4. Accent-stripped full name match.
        foreach ($users as $u) {
            $u1 = self::remove_accents(fullname($u));
            $u2 = self::remove_accents($u->lastname . ' ' . $u->firstname);
            if ($unaccented === $u1 || $unaccented === $u2) {
                return $u;
            }
        }

        // 5. Token / substring match.
        foreach ($users as $u) {
            $u1 = self::remove_accents(fullname($u));
            if ($u1 !== '' && (strpos($u1, $unaccented) !== false || strpos($unaccented, $u1) !== false)) {
                return $u;
            }
        }

        return null;
    }

    /**
     * Parse any spreadsheet file (XLSX, HTML XLS, or CSV).
     *
     * @param string $filepath
     * @return array 2D array of rows
     */
    public static function parse_file(string $filepath): array {
        if (!file_exists($filepath) || !is_readable($filepath)) {
            throw new Exception("File không tồn tại hoặc không thể đọc.");
        }

        $content = file_get_contents($filepath);
        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));

        // 1. Check if HTML (HTML-based XLS export).
        if (stripos($content, '<html') !== false || stripos($content, '<table') !== false) {
            return self::parse_html_table($content);
        }

        // 2. If XLSX (ZIP format).
        if ($ext === 'xlsx' || substr($content, 0, 4) === "PK\x03\x04") {
            try {
                return self::parse_xlsx($filepath);
            } catch (Exception $e) {
                // If XLSX zip reading fails, try parsing as CSV.
            }
        }

        // 3. Delimited text / CSV.
        return self::parse_csv($content);
    }

    /**
     * Parse XLSX using native PHP ZipArchive and SimpleXML.
     *
     * @param string $filepath
     * @return array
     */
    public static function parse_xlsx(string $filepath): array {
        $zip = new ZipArchive();
        if ($zip->open($filepath) !== true) {
            throw new Exception("Không thể mở file nén XLSX.");
        }

        // Read shared strings.
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xml = @simplexml_load_string($sharedXml);
            if ($xml) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string)$si->t;
                    } else if (isset($si->r)) {
                        $txt = '';
                        foreach ($si->r as $r) {
                            $txt .= (string)$r->t;
                        }
                        $sharedStrings[] = $txt;
                    } else {
                        $sharedStrings[] = '';
                    }
                }
            }
        }

        // Read first worksheet.
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if (strpos($entry, 'xl/worksheets/sheet') === 0 && substr($entry, -4) === '.xml') {
                    $sheetXml = $zip->getFromName($entry);
                    break;
                }
            }
        }

        if ($sheetXml === false) {
            $zip->close();
            throw new Exception("Không tìm thấy Sheet dữ liệu trong file XLSX.");
        }

        $xml = @simplexml_load_string($sheetXml);
        $zip->close();

        if (!$xml || !isset($xml->sheetData)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            $maxCol = 0;
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $colLetters = preg_replace('/[0-9]/', '', $ref);
                $colIdx = self::col_letter_to_index($colLetters);
                $maxCol = max($maxCol, $colIdx);

                $val = '';
                $type = (string)$c['t'];
                if ($type === 's') {
                    $idx = (int)$c->v;
                    $val = $sharedStrings[$idx] ?? '';
                } else if ($type === 'inlineStr' && isset($c->is->t)) {
                    $val = (string)$c->is->t;
                } else if (isset($c->v)) {
                    $val = (string)$c->v;
                }

                $rowData[$colIdx] = trim($val);
            }

            $filledRow = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $filledRow[$i] = $rowData[$i] ?? '';
            }
            if (!empty(array_filter($filledRow, 'strlen'))) {
                $rows[] = $filledRow;
            }
        }

        return $rows;
    }

    /**
     * Parse HTML table into 2D array.
     *
     * @param string $html
     * @return array
     */
    public static function parse_html_table(string $html): array {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $tables = $dom->getElementsByTagName('table');
        if ($tables->length === 0) {
            return [];
        }

        $rows = [];
        $table = $tables->item(0);
        $trElements = $table->getElementsByTagName('tr');
        foreach ($trElements as $tr) {
            $rowData = [];
            $cellElements = $tr->childNodes;
            foreach ($cellElements as $cell) {
                if ($cell->nodeName === 'td' || $cell->nodeName === 'th') {
                    $rowData[] = trim($cell->textContent);
                }
            }
            if (!empty(array_filter($rowData, 'strlen'))) {
                $rows[] = $rowData;
            }
        }
        return $rows;
    }

    /**
     * Parse CSV or delimited text string into 2D array.
     *
     * @param string $content
     * @return array
     */
    public static function parse_csv(string $content): array {
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }

        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
        if (empty($lines)) {
            return [];
        }

        // Detect delimiter.
        $firstLine = reset($lines);
        $delims = [',', ';', "\t"];
        $bestDelim = ',';
        $maxCount = 0;
        foreach ($delims as $d) {
            $cnt = substr_count($firstLine, $d);
            if ($cnt > $maxCount) {
                $maxCount = $cnt;
                $bestDelim = $d;
            }
        }

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row = str_getcsv($line, $bestDelim);
            $trimmed = array_map('trim', $row);
            if (!empty(array_filter($trimmed, 'strlen'))) {
                $rows[] = $trimmed;
            }
        }
        return $rows;
    }

    /**
     * Convert Excel column letters (A, B, ..., Z, AA, AB) to 0-based integer index.
     *
     * @param string $letter
     * @return int
     */
    private static function col_letter_to_index(string $letter): int {
        $letter = strtoupper($letter);
        $len = strlen($letter);
        $index = 0;
        for ($i = 0; $i < $len; $i++) {
            $index = $index * 26 + (ord($letter[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    /**
     * Detect column mapping by matching header row strings.
     *
     * @param array $headerRow
     * @return array Column index map: ['section' => idx, 'title' => idx, 'teacher' => idx, 'course' => idx, 'order' => idx]
     */
    public static function detect_columns(array $headerRow): array {
        $map = [
            'order' => -1,
            'course' => -1,
            'section' => -1,
            'title' => -1,
            'teacher' => -1,
            'requirefile' => -1,
        ];

        foreach ($headerRow as $idx => $headerText) {
            $norm = self::remove_accents($headerText);

            if ($map['order'] === -1 && ($norm === 'stt' || $norm === 'no' || $norm === 'thu tu' || $norm === '#')) {
                $map['order'] = $idx;
            } else if ($map['course'] === -1 && (strpos($norm, 'mon') !== false || strpos($norm, 'hoc phan') !== false || strpos($norm, 'course') !== false)) {
                $map['course'] = $idx;
            } else if ($map['section'] === -1 && (strpos($norm, 'chuong') !== false || strpos($norm, 'section') !== false || strpos($norm, 'module') !== false || strpos($norm, 'phan') !== false)) {
                $map['section'] = $idx;
            } else if ($map['title'] === -1 && (strpos($norm, 'bai') !== false || strpos($norm, 'lesson') !== false || strpos($norm, 'topic') !== false || strpos($norm, 'tieu de') !== false)) {
                $map['title'] = $idx;
            } else if ($map['teacher'] === -1 && (strpos($norm, 'giao vien') !== false || strpos($norm, 'giang vien') !== false || strpos($norm, 'teacher') !== false || strpos($norm, 'assignee') !== false || strpos($norm, 'phu trach') !== false)) {
                $map['teacher'] = $idx;
            } else if ($map['requirefile'] === -1 && (strpos($norm, 'can up file') !== false || strpos($norm, 'up file') !== false || strpos($norm, 'nop file') !== false || strpos($norm, 'yeu cau file') !== false || strpos($norm, 'bat buoc file') !== false || strpos($norm, 'requirefile') !== false || strpos($norm, 'file required') !== false)) {
                $map['requirefile'] = $idx;
            }
        }

        // Positional fallbacks if some essential columns weren't matched.
        $totalCols = count($headerRow);
        if ($map['section'] === -1 || $map['title'] === -1) {
            if ($totalCols >= 6) {
                // STT | Tên môn | Chương | Tên bài | Tên giáo viên | Cần nộp file
                $map['order'] = 0;
                $map['course'] = 1;
                $map['section'] = 2;
                $map['title'] = 3;
                $map['teacher'] = 4;
                $map['requirefile'] = 5;
            } else if ($totalCols >= 5) {
                // STT | Tên môn | Chương | Tên bài | Tên giáo viên
                $map['order'] = 0;
                $map['course'] = 1;
                $map['section'] = 2;
                $map['title'] = 3;
                $map['teacher'] = 4;
            } else if ($totalCols === 4) {
                // STT | Chương | Tên bài | Tên giáo viên
                $map['order'] = 0;
                $map['section'] = 1;
                $map['title'] = 2;
                $map['teacher'] = 3;
            } else if ($totalCols === 3) {
                // Chương | Tên bài | Tên giáo viên
                $map['section'] = 0;
                $map['title'] = 1;
                $map['teacher'] = 2;
            }
        }

        return $map;
    }

    /**
     * Import spreadsheet rows directly into a course plan.
     * Automatically creates chapters (sections), lessons (items) with status = 0 (Chưa làm),
     * and maps teacher names to Moodle user IDs.
     *
     * @param string $filepath Path to uploaded spreadsheet file
     * @param int $planid Plan ID
     * @return array Results summary
     */
    public static function import_into_plan(string $filepath, int $planid): array {
        global $DB, $USER;

        $rows = self::parse_file($filepath);
        if (empty($rows)) {
            throw new Exception("File không chứa dữ liệu hợp lệ.");
        }

        // Find header row.
        $headerIdx = 0;
        foreach ($rows as $idx => $r) {
            $lineStr = self::remove_accents(implode(' ', $r));
            if (strpos($lineStr, 'chuong') !== false || strpos($lineStr, 'bai') !== false || strpos($lineStr, 'giao vien') !== false) {
                $headerIdx = $idx;
                break;
            }
        }

        $headerRow = $rows[$headerIdx];
        $colMap = self::detect_columns($headerRow);

        if ($colMap['section'] === -1 || $colMap['title'] === -1) {
            throw new Exception("Không thể nhận diện cột 'Chương' và 'Tên bài' trong file Excel. Vui lòng kiểm tra lại tiêu đề cột.");
        }

        // Preload active Moodle users for teacher matching.
        $users = $DB->get_records_select('user', 'deleted = 0 AND id > 1', null, 'id ASC', 'id, username, firstname, lastname, email');

        // Check if plan already has sections.
        $existingSections = $DB->get_records('local_courseplan_sections', ['planid' => $planid], 'sortorder ASC');
        $sectionMap = [];
        foreach ($existingSections as $sec) {
            $norm = self::remove_accents($sec->title);
            $sectionMap[$norm] = $sec;
        }

        // If plan has exactly 1 initial empty section with 0 items, remove it so we don't duplicate.
        if (count($existingSections) === 1) {
            $firstSec = reset($existingSections);
            $hasItems = $DB->record_exists('local_courseplan_items', ['sectionid' => $firstSec->id]);
            if (!$hasItems) {
                $DB->delete_records('local_courseplan_sections', ['id' => $firstSec->id]);
                $sectionMap = [];
                $existingSections = [];
            }
        }

        $now = time();
        $itemSortOrder = $DB->count_records('local_courseplan_items', ['planid' => $planid]) + 1;
        $sectionSortOrder = count($sectionMap) + 1;

        $createdSections = 0;
        $createdItems = 0;
        $matchedTeachers = [];
        $unmatchedTeachers = [];

        $transaction = $DB->start_delegated_transaction();

        // Process data rows.
        for ($i = $headerIdx + 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            $itemTitle = isset($colMap['title']) && isset($row[$colMap['title']]) ? trim($row[$colMap['title']]) : '';
            if ($itemTitle === '') {
                continue; // Skip blank lesson rows.
            }

            // Chapter / Section title.
            $sectionTitle = isset($colMap['section']) && isset($row[$colMap['section']]) ? trim($row[$colMap['section']]) : '';
            if ($sectionTitle === '') {
                $sectionTitle = 'Chương 1: Nội dung chung';
            }

            $normSection = self::remove_accents($sectionTitle);
            if (!isset($sectionMap[$normSection])) {
                $newSec = new stdClass();
                $newSec->planid = $planid;
                $newSec->title = $sectionTitle;
                $newSec->sortorder = $sectionSortOrder++;
                $newSec->timecreated = $now;
                $newSec->timemodified = $now;
                $newSec->id = $DB->insert_record('local_courseplan_sections', $newSec);

                $sectionMap[$normSection] = $newSec;
                $createdSections++;
            }

            $currentSection = $sectionMap[$normSection];

            // Teacher matching.
            $teacherRaw = ($colMap['teacher'] !== -1 && isset($row[$colMap['teacher']])) ? trim($row[$colMap['teacher']]) : '';
            $assigneeid = 0;

            if ($teacherRaw !== '') {
                $matchedUser = self::match_teacher($teacherRaw, $users);
                if ($matchedUser) {
                    $assigneeid = (int)$matchedUser->id;
                    $matchedTeachers[$teacherRaw] = fullname($matchedUser) . ' (@' . $matchedUser->username . ')';
                } else {
                    $unmatchedTeachers[$teacherRaw] = true;
                }
            }

            // Check if file upload is required for this lesson (Có / Không).
            $reqRaw = ($colMap['requirefile'] !== -1 && isset($row[$colMap['requirefile']])) ? trim($row[$colMap['requirefile']]) : '';
            $normReq = self::remove_accents($reqRaw);
            if ($normReq === 'co' || $normReq === 'true' || $normReq === '1' || $normReq === 'yes' || $normReq === 'y') {
                $requirefile = 1;
            } else if ($normReq === 'khong' || $normReq === 'false' || $normReq === '0' || $normReq === 'no' || $normReq === 'k') {
                $requirefile = 0;
            } else {
                // Default if not specified: 1 (required).
                $requirefile = 1;
            }

            // Insert lesson item.
            // Status is STRICTLY set to 0 ("Chưa làm" / To Do) as requested.
            $item = new stdClass();
            $item->planid = $planid;
            $item->sectionid = $currentSection->id;
            $item->title = $itemTitle;
            $item->assigneeid = $assigneeid;
            $item->status = 0; // Chưa làm
            $item->requirefile = $requirefile;
            $item->sortorder = $itemSortOrder++;
            $item->timecreated = $now;
            $item->timemodified = $now;
            $item->usermodified = $USER->id;

            $DB->insert_record('local_courseplan_items', $item);
            $createdItems++;
        }

        $transaction->allow_commit();

        return [
            'success' => true,
            'sections_count' => $createdSections,
            'items_count' => $createdItems,
            'matched_teachers' => $matchedTeachers,
            'unmatched_teachers' => array_keys($unmatchedTeachers),
        ];
    }
}
