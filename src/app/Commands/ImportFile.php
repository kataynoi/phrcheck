<?php

namespace App\Commands;

use App\Libraries\EncounterImporter;
use App\Models\HospitalModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * นำเข้าไฟล์จากบรรทัดคำสั่ง — ใช้กับไฟล์ย้อนหลังจำนวนมากที่อัปโหลดผ่านเว็บไม่สะดวก
 *
 *   docker compose exec php php spark phr:import /path/file.xlsx
 *   docker compose exec php php spark phr:import /path/file.xlsx --user 1
 */
class ImportFile extends BaseCommand
{
    protected $group       = 'phrCheck';
    protected $name        = 'phr:import';
    protected $description = 'นำเข้าไฟล์ encounter mask (.xlsx / .xls / .csv) เข้าฐานข้อมูล';
    protected $usage       = 'phr:import <file> [--user <id>] [--hoscode <code>]';

    protected $arguments = [
        'file' => 'พาธไฟล์ที่จะนำเข้า (.xlsx, .xls หรือ .csv)',
    ];

    protected $options = [
        '--user'     => 'id ของผู้ใช้ที่จะบันทึกเป็นผู้นำเข้า (ไม่ระบุ = ไม่บันทึกประวัติ)',
        '--hoscode'  => 'จำกัดให้นำเข้าเฉพาะหน่วยบริการนี้',
        '--distcode' => 'จำกัดให้นำเข้าเฉพาะหน่วยบริการในอำเภอนี้',
    ];

    public function run(array $params)
    {
        $file = $params[0] ?? CLI::prompt('พาธไฟล์');

        if (! is_file($file)) {
            CLI::error('ไม่พบไฟล์: ' . $file);

            return EXIT_ERROR;
        }

        $userId    = CLI::getOption('user');
        $hoscode   = CLI::getOption('hoscode');
        $distcode  = CLI::getOption('distcode');

        // แปลงตัวเลือกเป็นรายการรหัสหน่วยบริการที่อนุญาต (null = ไม่จำกัด)
        $allowedCodes = null;

        if (is_string($distcode) && $distcode !== '') {
            $allowedCodes = (new HospitalModel())->codesInDistrict($distcode);

            if ($allowedCodes === []) {
                CLI::error('ไม่พบหน่วยบริการในอำเภอรหัส ' . $distcode);

                return EXIT_ERROR;
            }
        } elseif (is_string($hoscode) && $hoscode !== '') {
            $allowedCodes = [$hoscode];
        }

        $result = (new EncounterImporter())->import(
            $file,
            basename($file),
            $allowedCodes,
            is_numeric($userId) ? (int) $userId : null
        );

        if ($result['ok'] === false) {
            CLI::error($result['message']);

            return EXIT_ERROR;
        }

        CLI::write('ไฟล์             : ' . $result['filename']);
        CLI::write('แถวทั้งหมด        : ' . $result['total']);
        CLI::write('นำเข้าสำเร็จ       : ' . CLI::color((string) $result['inserted'], 'green'));
        CLI::write('ซ้ำในฐานข้อมูล    : ' . $result['duplicate_in_db']);
        CLI::write('ซ้ำภายในไฟล์      : ' . $result['duplicate_in_file']);
        CLI::write('ผิดพลาด          : ' . ($result['error_count'] > 0
            ? CLI::color((string) $result['error_count'], 'red')
            : '0'));

        foreach (array_slice($result['errors'], 0, 10) as $error) {
            CLI::write('  - ' . $error, 'yellow');
        }

        return EXIT_SUCCESS;
    }
}
