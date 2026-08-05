-- ============================================================
--  เพิ่มสิทธิ์ "Admin ระดับอำเภอ"
--
--  สคริปต์ใน docker-entrypoint-initdb.d รันเฉพาะตอนสร้างฐานข้อมูลใหม่
--  ฐานข้อมูลที่มีข้อมูลอยู่แล้ว (เช่นบน production) ต้องรันไฟล์นี้เอง:
--
--    docker compose exec -T db mysql -u root -p"$DB_ROOT_PASS" phrcheck_db \
--      < docker/mysql/upgrades/2026-08-05-district-role.sql
--
--  รันซ้ำได้ ไม่ทำให้ข้อมูลเดิมเสีย
-- ============================================================

ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('admin','district','user') NOT NULL DEFAULT 'user'
  COMMENT 'admin=ทั้งจังหวัด, district=ทั้งอำเภอของตัวเอง, user=เฉพาะหน่วยบริการตัวเอง';

-- ตรวจผล: ต้องเห็น district อยู่ในรายการค่าที่เป็นไปได้
SELECT COLUMN_TYPE AS role_values
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME   = 'users'
  AND COLUMN_NAME  = 'role';
