<?php

use CodeIgniter\I18n\Time;

if (! function_exists('phr_parse_datetime')) {
    /**
     * แปลงวันที่จาก CSV ให้เป็น 'Y-m-d H:i:s'
     *
     * ไฟล์ต้นทางเก็บวันที่เป็น Excel serial พอ Save As CSV แล้วรูปแบบจะขึ้นกับ
     * locale ของเครื่องผู้ใช้ — เครื่องที่ตั้งเป็นไทยมักได้ปี พ.ศ. ออกมา
     * ฟังก์ชันนี้จึงรับหลายรูปแบบและแปลง พ.ศ. -> ค.ศ. ให้อัตโนมัติ
     *
     * ค่าที่ถือว่า "ไม่มีข้อมูล" จะคืน null:
     *   ว่าง, 1970-01-01 (epoch), 1899-12-30 (Excel serial 0)
     */
    function phr_parse_datetime(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '-' || strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        // ตัวเลขล้วน = Excel serial (วันนับจาก 1899-12-30)
        if (preg_match('/^\d+(\.\d+)?$/', $value) === 1) {
            $serial = (float) $value;

            if ($serial <= 0) {
                return null;
            }

            $timestamp = (int) round(($serial - 25569) * 86400);

            return $timestamp <= 0 ? null : gmdate('Y-m-d H:i:s', $timestamp);
        }

        $value = str_replace(['T', '/'], [' ', '-'], $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd-m-Y',
            'm-d-Y H:i:s',
            'm-d-Y H:i',
        ];

        foreach ($formats as $format) {
            $parsed = date_create_from_format($format, $value);

            if ($parsed === false) {
                continue;
            }

            $errors = date_get_last_errors();

            if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
                continue;
            }

            $year = (int) $parsed->format('Y');

            // ปี พ.ศ. (2400-2600) -> ค.ศ.
            if ($year > 2400) {
                $parsed = $parsed->modify('-543 year');
            }

            $result = $parsed->format('Y-m-d H:i:s');

            // epoch / Excel serial 0 = ช่องว่างที่ระบบต้นทางใส่มา ไม่ใช่วันที่จริง
            if (str_starts_with($result, '1970-01-01') || str_starts_with($result, '1899-12-30')) {
                return null;
            }

            return $result;
        }

        return null;
    }
}

if (! function_exists('phr_hoscode_from_ref')) {
    /**
     * ดึงรหัสสถานบริการ 5 หลักแรกจาก encounter_ref_code
     * เช่น '11055:690615062028' -> '11055'
     */
    function phr_hoscode_from_ref(?string $refCode): ?string
    {
        $refCode = trim((string) $refCode);

        if ($refCode === '') {
            return null;
        }

        $code = substr($refCode, 0, 5);

        return preg_match('/^\d{5}$/', $code) === 1 ? $code : null;
    }
}

if (! function_exists('phr_thai_date')) {
    /**
     * แสดงวันที่แบบไทย (พ.ศ.) เช่น '2 ส.ค. 2569 18:30'
     */
    function phr_thai_date(?string $datetime, bool $withTime = true): string
    {
        if ($datetime === null || $datetime === '' || str_starts_with($datetime, '0000')) {
            return '-';
        }

        $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
            'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

        $time = strtotime($datetime);

        if ($time === false) {
            return '-';
        }

        $text = date('j', $time) . ' ' . $months[(int) date('n', $time)] . ' ' . (date('Y', $time) + 543);

        return $withTime ? $text . ' ' . date('H:i', $time) : $text;
    }
}

if (! function_exists('phr_now')) {
    /**
     * เวลาปัจจุบันตามโซนเวลาแอป สำหรับ stamp ลงฐานข้อมูล
     */
    function phr_now(): string
    {
        return Time::now()->format('Y-m-d H:i:s');
    }
}
