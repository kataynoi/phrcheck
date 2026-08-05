<?php

namespace App\Libraries;

use App\Models\HospitalModel;

/**
 * ขอบเขตข้อมูลที่ผู้ใช้คนหนึ่งมีสิทธิ์เห็นและแก้ไข
 *
 * รวมตรรกะไว้ที่เดียว เพราะทุกทางที่แตะข้อมูล (รายการ, dashboard, นำเข้า,
 * แก้สถานะ) ต้องใช้เงื่อนไขเดียวกัน ถ้ากระจายไปเขียนซ้ำในแต่ละ controller
 * แล้วลืมที่ใดที่หนึ่ง ข้อมูลของหน่วยบริการอื่นจะรั่วทันที
 *
 *   admin     -> ทุกหน่วยบริการในจังหวัด
 *   district  -> ทุกหน่วยบริการในอำเภอเดียวกับตัวเอง (hospitals.distcode)
 *   user      -> เฉพาะหน่วยบริการของตัวเอง
 */
final class DataScope
{
    public const ROLE_ADMIN    = 'admin';
    public const ROLE_DISTRICT = 'district';
    public const ROLE_USER     = 'user';

    /** @var list<string>|null แคชรหัสหน่วยบริการที่อยู่ในขอบเขต */
    private ?array $codes = null;

    private function __construct(
        private readonly string $type,
        private readonly string $value,
    ) {
    }

    public static function all(): self
    {
        return new self(self::ROLE_ADMIN, '');
    }

    public static function district(string $distcode): self
    {
        return new self(self::ROLE_DISTRICT, $distcode);
    }

    public static function hospital(string $hoscode): self
    {
        return new self(self::ROLE_USER, $hoscode);
    }

    /**
     * สร้างจากข้อมูลใน session ของผู้ใช้ที่ล็อกอินอยู่
     */
    public static function fromSession(): self
    {
        $session = session();

        return match ((string) $session->get('role')) {
            self::ROLE_ADMIN    => self::all(),
            self::ROLE_DISTRICT => self::district((string) $session->get('distcode')),
            default             => self::hospital((string) $session->get('hoscode')),
        };
    }

    public function isAll(): bool
    {
        return $this->type === self::ROLE_ADMIN;
    }

    public function isDistrict(): bool
    {
        return $this->type === self::ROLE_DISTRICT;
    }

    /**
     * ข้อความอธิบายขอบเขต สำหรับแสดงใต้หัวข้อของแต่ละหน้า
     */
    public function label(): string
    {
        if ($this->isAll()) {
            return 'ทุกหน่วยบริการในจังหวัดมหาสารคาม';
        }

        if ($this->isDistrict()) {
            $name = (new HospitalModel())->districtName($this->value);

            return 'ทุกหน่วยบริการในอำเภอ' . $name . ' (' . count($this->allowedCodes() ?? []) . ' แห่ง)';
        }

        return (new HospitalModel())->nameOf($this->value);
    }

    /**
     * เงื่อนไข SQL สำหรับ WHERE — คืนค่าว่างเมื่อไม่ต้องจำกัดขอบเขต
     *
     * ระดับอำเภอใช้ subquery แทนการไล่ list รหัส เพื่อให้ผลตรงกับ
     * ตาราง hospitals เสมอแม้มีการเพิ่ม/ย้ายหน่วยบริการภายหลัง
     */
    public function sqlCondition(string $column = 'code'): string
    {
        if ($this->isAll()) {
            return '';
        }

        $db = db_connect();

        if ($this->isDistrict()) {
            return $column . ' IN (SELECT hoscode FROM hospitals WHERE distcode = ' . $db->escape($this->value) . ')';
        }

        return $column . ' = ' . $db->escape($this->value);
    }

    /**
     * รหัสหน่วยบริการทั้งหมดในขอบเขต — null = ไม่จำกัด (admin)
     *
     * @return list<string>|null
     */
    public function allowedCodes(): ?array
    {
        if ($this->isAll()) {
            return null;
        }

        if ($this->codes === null) {
            $this->codes = $this->isDistrict()
                ? (new HospitalModel())->codesInDistrict($this->value)
                : [$this->value];
        }

        return $this->codes;
    }

    /**
     * ผู้ใช้แตะข้อมูลของหน่วยบริการนี้ได้หรือไม่
     */
    public function allows(?string $hoscode): bool
    {
        if ($this->isAll()) {
            return true;
        }

        if ($hoscode === null || $hoscode === '') {
            return false;
        }

        return in_array($hoscode, $this->allowedCodes() ?? [], true);
    }
}
