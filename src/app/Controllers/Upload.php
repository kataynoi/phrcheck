<?php

namespace App\Controllers;

use App\Libraries\EncounterImporter;
use App\Models\UploadBatchModel;

class Upload extends BaseController
{
    /** จำนวนบรรทัดตัวอย่างข้อผิดพลาดที่แสดงกลับให้ผู้ใช้ */
    private const MAX_REPORTED_ERRORS = 20;

    public function index()
    {
        return view('upload/index', [
            'user'    => $this->currentUser(),
            'isAdmin' => $this->isAdmin(),
            'batches' => (new UploadBatchModel())->recent(
                $this->isAdmin() ? null : (int) $this->session->get('user_id')
            ),
        ]);
    }

    public function process()
    {
        $file = $this->request->getFile('import_file');

        if ($file === null || ! $file->isValid()) {
            return redirect()->back()->with('error', 'กรุณาเลือกไฟล์ (' . ($file?->getErrorString() ?? 'ไม่พบไฟล์') . ')');
        }

        $extension = strtolower($file->getClientExtension());

        if (! in_array($extension, EncounterImporter::ALLOWED_EXTENSIONS, true)) {
            return redirect()->back()->with(
                'error',
                'รองรับเฉพาะไฟล์ .xlsx, .xls และ .csv (ไฟล์ที่เลือกเป็น .' . $extension . ')'
            );
        }

        $result = (new EncounterImporter())->import(
            $file->getTempName(),
            $file->getClientName(),
            $this->scopeHoscode(),
            (int) $this->session->get('user_id')
        );

        if ($result['ok'] === false) {
            return redirect()->back()->with('error', $result['message']);
        }

        $result['errors'] = array_slice($result['errors'], 0, self::MAX_REPORTED_ERRORS);

        return redirect()->to('/upload')->with('import_result', $result);
    }
}
