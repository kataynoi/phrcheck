<?php

namespace App\Libraries;

use App\Models\EncounterMaskModel;
use App\Models\UploadBatchModel;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * นำเข้าไฟล์ encounter mask ที่ export มาจากระบบ PHR
 *
 * รับไฟล์ .xlsx / .xls ได้โดยตรง (ไม่ต้องแปลงเป็น CSV ก่อน) และยังรับ .csv ได้อยู่
 *
 * แยกออกมาจาก controller เพื่อให้เรียกใช้ได้ทั้งจากหน้าเว็บและจาก CLI
 * (php spark phr:import)
 */
class EncounterImporter
{
    /** คอลัมน์ที่ต้องมีในไฟล์ */
    public const REQUIRED_HEADERS = ['cid', 'encounter_ref_code'];

    /** นามสกุลไฟล์ที่รับ */
    public const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv', 'txt'];

    /** จำนวนข้อความ error สูงสุดที่เก็บไว้รายงาน */
    private const MAX_ERRORS = 200;

    /**
     * @param string            $path          พาธไฟล์
     * @param string            $originalName  ชื่อไฟล์ที่ผู้ใช้อัปโหลด (ใช้ดูนามสกุล + เก็บลงประวัติ)
     * @param list<string>|null $allowedCodes  รหัสหน่วยบริการที่นำเข้าได้ (null = ทุกหน่วย)
     * @param int|null          $userId        ผู้นำเข้า (null = ไม่บันทึกประวัติ)
     *
     * @return array<string, mixed>
     */
    public function import(string $path, string $originalName, ?array $allowedCodes, ?int $userId): array
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            return ['ok' => false, 'message' => 'รองรับเฉพาะไฟล์ .xlsx, .xls และ .csv'];
        }

        try {
            $table = in_array($extension, ['csv', 'txt'], true)
                ? $this->readCsv($path)
                : $this->readSpreadsheet($path);
        } catch (Throwable $e) {
            log_message('error', 'อ่านไฟล์นำเข้าไม่สำเร็จ: {msg}', ['msg' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'อ่านไฟล์ไม่สำเร็จ: ' . $e->getMessage()];
        }

        if (isset($table['fatal'])) {
            return ['ok' => false, 'message' => $table['fatal']];
        }

        $parsed = $this->buildRows($table['header'], $table['rows'], $allowedCodes);

        if (isset($parsed['fatal'])) {
            return ['ok' => false, 'message' => $parsed['fatal']];
        }

        $batchId = null;
        $batches = new UploadBatchModel();

        if ($userId !== null) {
            $batchId = $batches->insert([
                'user_id'       => $userId,
                'filename'      => $originalName,
                'total_rows'    => $parsed['total'],
                'inserted_rows' => 0,
                'skipped_rows'  => 0,
                'error_rows'    => count($parsed['errors']),
            ], true);

            foreach ($parsed['rows'] as &$row) {
                $row['batch_id']    = $batchId;
                $row['uploaded_by'] = $userId;
            }
            unset($row);
        }

        $inserted = (new EncounterMaskModel())->insertIgnoreBatch($parsed['rows']);

        // แถวที่ผ่านการตรวจแล้วแต่ INSERT IGNORE ไม่รับ = ซ้ำกับที่มีอยู่เดิม
        $duplicateInDb = count($parsed['rows']) - $inserted;
        $skipped       = $duplicateInDb + $parsed['duplicate_in_file'];

        if ($batchId !== null) {
            $batches->update($batchId, [
                'inserted_rows' => $inserted,
                'skipped_rows'  => $skipped,
                'error_rows'    => count($parsed['errors']),
                'note'          => $parsed['errors'] === [] ? null : implode("\n", array_slice($parsed['errors'], 0, 20)),
            ]);
        }

        return [
            'ok'                => true,
            'filename'          => $originalName,
            'total'             => $parsed['total'],
            'inserted'          => $inserted,
            'duplicate_in_db'   => $duplicateInDb,
            'duplicate_in_file' => $parsed['duplicate_in_file'],
            'errors'            => $parsed['errors'],
            'error_count'       => count($parsed['errors']),
        ];
    }

    // ------------------------------------------------------------------
    // อ่านไฟล์ให้ออกมาเป็น header + แถวข้อมูล (ยังไม่ตรวจความถูกต้อง)
    // ------------------------------------------------------------------

    /**
     * @return array{fatal?: string, header?: list<string>, rows?: list<list<string>>}
     */
    private function readSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        // ไม่อ่าน style/สูตร ประหยัดหน่วยความจำมากสำหรับไฟล์หลายหมื่นแถว
        // (number format ยังถูกอ่านอยู่ จึงยังแยกได้ว่าเซลล์ไหนเป็นวันที่)
        $reader->setReadDataOnly(true);

        $sheet = $reader->load($path)->getSheet(0);
        $rows  = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells    = $row->getCellIterator();
            $cells->setIterateOnlyExistingCells(false);
            $line     = [];
            $hasValue = false;

            foreach ($cells as $cell) {
                $value = $this->cellToString($cell);
                $line[] = $value;

                if ($value !== '') {
                    $hasValue = true;
                }
            }

            // ข้ามแถวว่างที่ Excel มักทิ้งไว้ท้ายชีต
            if ($hasValue) {
                $rows[] = $line;
            }
        }

        if ($rows === []) {
            return ['fatal' => 'ไฟล์ว่าง'];
        }

        $header = array_map(
            static fn ($h): string => strtolower(trim((string) $h, " \t\n\r\0\x0B\"'")),
            array_shift($rows)
        );

        return ['header' => $header, 'rows' => $rows];
    }

    /**
     * แปลงค่าในเซลล์เป็นข้อความ โดยคืนวันที่เป็น 'Y-m-d H:i:s'
     *
     * ระบบ PHR เก็บวันที่มาเป็น Excel serial ถ้าปล่อยเป็นตัวเลขดิบจะอ่านไม่ออก
     */
    private function cellToString(Cell $cell): string
    {
        $value = $cell->getValue();

        if ($value === null) {
            return '';
        }

        if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
            return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }

    /**
     * @return array{fatal?: string, header?: list<string>, rows?: list<list<string>>}
     */
    private function readCsv(string $path): array
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return ['fatal' => 'เปิดไฟล์ไม่สำเร็จ'];
        }

        $firstLine = fgets($handle);

        if ($firstLine === false) {
            fclose($handle);

            return ['fatal' => 'ไฟล์ว่าง'];
        }

        // Excel "CSV UTF-8" ใส่ BOM ไว้หน้าไฟล์ ทำให้ชื่อคอลัมน์แรกเพี้ยน
        $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine) ?? $firstLine;
        $delimiter = $this->sniffDelimiter($firstLine);

        $header = array_map(
            static fn ($h): string => strtolower(trim((string) $h, " \t\n\r\0\x0B\"'")),
            str_getcsv($this->toUtf8(rtrim($firstLine, "\r\n")), $delimiter, '"', '\\')
        );

        $rows = [];

        while (($line = fgets($handle)) !== false) {
            $line = rtrim($line, "\r\n");

            if (trim($line) === '') {
                continue;
            }

            $rows[] = array_map(
                static fn ($v): string => trim((string) $v),
                str_getcsv($this->toUtf8($line), $delimiter, '"', '\\')
            );
        }

        fclose($handle);

        return ['header' => $header, 'rows' => $rows];
    }

    // ------------------------------------------------------------------
    // ตรวจความถูกต้องและเตรียมแถวสำหรับบันทึก
    // ------------------------------------------------------------------

    /**
     * @param list<string>       $header
     * @param list<list<string>> $rows
     * @param list<string>|null  $allowedCodes
     *
     * @return array{fatal?: string, rows?: list<array<string, mixed>>, total?: int, duplicate_in_file?: int, errors?: list<string>}
     */
    private function buildRows(array $header, array $rows, ?array $allowedCodes): array
    {
        helper('phr');

        $missing = array_diff(self::REQUIRED_HEADERS, $header);

        if ($missing !== []) {
            return ['fatal' => 'ไฟล์ขาดคอลัมน์ที่จำเป็น: ' . implode(', ', $missing)
                . ' (พบคอลัมน์: ' . implode(', ', array_filter($header)) . ')'];
        }

        $index = array_flip($header);

        $prepared  = [];
        $seen      = [];
        $errors    = [];
        $total     = 0;
        $dupInFile = 0;
        $now       = phr_now();

        foreach ($rows as $offset => $data) {
            // +2 = ข้ามแถวหัวตาราง และให้เลขตรงกับที่เห็นใน Excel
            $lineNo = $offset + 2;
            $total++;

            $get = static function (string $key) use ($data, $index): ?string {
                if (! isset($index[$key])) {
                    return null;
                }

                $value = $data[$index[$key]] ?? null;

                return $value === null ? null : trim((string) $value);
            };

            $refCode = (string) $get('encounter_ref_code');
            $cid     = preg_replace('/\D/', '', (string) $get('cid')) ?? '';

            if ($refCode === '') {
                $this->addError($errors, "แถว {$lineNo}: ไม่มี encounter_ref_code");
                continue;
            }

            // code = รหัสสถานบริการ 5 หลักแรกของ encounter_ref_code
            $code = phr_hoscode_from_ref($refCode);

            if ($code === null) {
                $this->addError($errors, "แถว {$lineNo}: encounter_ref_code '{$refCode}' ไม่ขึ้นต้นด้วยรหัสหน่วยบริการ 5 หลัก");
                continue;
            }

            if ($cid === '') {
                $this->addError($errors, "แถว {$lineNo}: ไม่มี cid");
                continue;
            }

            // นำเข้าได้เฉพาะหน่วยบริการที่อยู่ในขอบเขตของผู้ใช้
            if ($allowedCodes !== null && ! in_array($code, $allowedCodes, true)) {
                $this->addError($errors, "แถว {$lineNo}: รหัสหน่วยบริการ {$code} อยู่นอกขอบเขตที่คุณนำเข้าได้");
                continue;
            }

            $key = $cid . '|' . $refCode;

            if (isset($seen[$key])) {
                $dupInFile++;
                continue;
            }

            $seen[$key] = true;

            $prepared[] = [
                'code'                  => $code,
                'phr_encounter_mask_id' => $get('phr_encounter_mask_id'),
                'cid'                   => $cid,
                'encounter_ref_code'    => $refCode,
                'process_note'          => $get('process_note'),
                'officer_name'          => $get('officer_name'),
                'process_datetime'      => phr_parse_datetime($get('process_datetime')),
                'create_datetime'       => phr_parse_datetime($get('create_datetime')),
                'update_datetime'       => phr_parse_datetime($get('update_datetime')),
                'check_status_id'       => 1,
                'batch_id'              => null,
                'uploaded_by'           => null,
                'created_at'            => $now,
                'updated_at'            => $now,
            ];
        }

        return [
            'rows'              => $prepared,
            'total'             => $total,
            'duplicate_in_file' => $dupInFile,
            'errors'            => $errors,
        ];
    }

    /**
     * @param list<string> $errors
     */
    private function addError(array &$errors, string $message): void
    {
        if (count($errors) < self::MAX_ERRORS) {
            $errors[] = $message;
        }
    }

    /**
     * Excel บน Windows ภาษาไทยบันทึก CSV เป็น TIS-620 ไม่ใช่ UTF-8
     */
    private function toUtf8(string $line): string
    {
        if (mb_check_encoding($line, 'UTF-8')) {
            return $line;
        }

        return (string) mb_convert_encoding($line, 'UTF-8', 'TIS-620');
    }

    /**
     * บาง locale ของ Excel ใช้ ; หรือ tab แทน ,
     */
    private function sniffDelimiter(string $headerLine): string
    {
        $counts = [
            ','  => substr_count($headerLine, ','),
            ';'  => substr_count($headerLine, ';'),
            "\t" => substr_count($headerLine, "\t"),
            '|'  => substr_count($headerLine, '|'),
        ];

        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? (string) $best : ',';
    }
}
