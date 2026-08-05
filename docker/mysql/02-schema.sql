-- ============================================================
--  phrCheck : ตารางของแอปพลิเคชัน
--  รันหลัง 01-source-data.sql (dump ที่มี campur / chospital)
-- ============================================================
SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- 1. หน่วยบริการ — คัดจาก chospital เฉพาะ provcode = '44' (มหาสารคาม)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `hospitals`;
CREATE TABLE `hospitals` (
  `hoscode`   CHAR(5)      NOT NULL,
  `hosname`   VARCHAR(255) NULL DEFAULT NULL,
  `hostype`   CHAR(2)      NULL DEFAULT NULL,
  `distcode`  CHAR(2)      NULL DEFAULT NULL,
  `provcode`  CHAR(2)      NULL DEFAULT NULL,
  `ampurname` VARCHAR(255) NULL DEFAULT NULL,
  PRIMARY KEY (`hoscode`),
  INDEX `idx_hospitals_name` (`hosname`)
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO `hospitals` (`hoscode`, `hosname`, `hostype`, `distcode`, `provcode`, `ampurname`)
SELECT h.`hoscode`,
       h.`hosname`,
       h.`hostype`,
       h.`distcode`,
       h.`provcode`,
       a.`ampurname`
FROM `chospital` h
LEFT JOIN `campur` a
       ON a.`changwatcode` = h.`provcode`
      AND a.`ampurcode`    = h.`distcode`
WHERE h.`provcode` = '44';

-- ------------------------------------------------------------
-- 2. สถานะการตรวจสอบ (ตามคอลัมน์ "สาเหตุ" ในรายงาน)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `check_statuses`;
CREATE TABLE `check_statuses` (
  `id`         TINYINT UNSIGNED NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `color`      VARCHAR(20)  NOT NULL DEFAULT 'secondary',
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO `check_statuses` (`id`, `name`, `color`, `sort_order`) VALUES
  (1, 'อยู่ระหว่างการวิเคราะห์ข้อมูล', 'secondary', 1),
  (2, 'ข้อมูลปกติ ให้บริการจริง',      'success',   2),
  (3, 'Human Error',                    'danger',    3),
  (4, 'สัมพันธ์กับ KPI',                 'warning',   4),
  (5, 'สัมพันธ์กับเบิกจ่าย',              'info',      5),
  (6, 'อื่นๆ',                          'dark',      6);

-- ------------------------------------------------------------
-- 3. ผู้ใช้ระบบ (ล็อกอินด้วย LINE + ต้องผ่านการอนุมัติ)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `line_user_id`  VARCHAR(64)  NOT NULL,
  `display_name`  VARCHAR(255) NULL DEFAULT NULL,
  `picture_url`   VARCHAR(500) NULL DEFAULT NULL,
  `first_name`    VARCHAR(100) NULL DEFAULT NULL,
  `last_name`     VARCHAR(100) NULL DEFAULT NULL,
  `hoscode`       CHAR(5)      NULL DEFAULT NULL,
  -- admin    = เห็น/แก้ได้ทุกหน่วยบริการในจังหวัด
  -- district = เห็น/แก้ได้ทุกหน่วยบริการในอำเภอเดียวกับ hoscode ของตัวเอง
  -- user     = เฉพาะหน่วยบริการของตัวเอง
  `role`          ENUM('admin','district','user')     NOT NULL DEFAULT 'user',
  `status`        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_at`   DATETIME     NULL DEFAULT NULL,
  `approved_by`   INT UNSIGNED NULL DEFAULT NULL,
  `last_login_at` DATETIME     NULL DEFAULT NULL,
  `created_at`    DATETIME     NULL DEFAULT NULL,
  `updated_at`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `uq_users_line` (`line_user_id`),
  INDEX `idx_users_hoscode` (`hoscode`),
  INDEX `idx_users_status` (`status`)
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. รอบการนำเข้าไฟล์ (audit)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `upload_batches`;
CREATE TABLE `upload_batches` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`       INT UNSIGNED NOT NULL,
  `filename`      VARCHAR(255) NOT NULL,
  `total_rows`    INT UNSIGNED NOT NULL DEFAULT 0,
  `inserted_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_rows`  INT UNSIGNED NOT NULL DEFAULT 0,
  `error_rows`    INT UNSIGNED NOT NULL DEFAULT 0,
  `note`          TEXT         NULL DEFAULT NULL,
  `created_at`    DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_batches_user` (`user_id`)
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. ข้อมูล encounter mask ที่นำเข้า
--    code = 5 หลักแรกของ encounter_ref_code (รหัสสถานบริการ)
--    กันซ้ำด้วย (cid, encounter_ref_code)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `encounter_masks`;
CREATE TABLE `encounter_masks` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code`                  CHAR(5)      NOT NULL COMMENT 'รหัสสถานบริการ 5 หลักแรกของ encounter_ref_code',
  `phr_encounter_mask_id` VARCHAR(64)  NULL DEFAULT NULL,
  `cid`                   VARCHAR(13)  NOT NULL,
  `encounter_ref_code`    VARCHAR(64)  NOT NULL,
  `process_note`          TEXT         NULL DEFAULT NULL,
  `officer_name`          VARCHAR(255) NULL DEFAULT NULL,
  `process_datetime`      DATETIME     NULL DEFAULT NULL,
  `create_datetime`       DATETIME     NULL DEFAULT NULL,
  `update_datetime`       DATETIME     NULL DEFAULT NULL,

  `check_status_id`       TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `check_note`            TEXT         NULL DEFAULT NULL,
  `checked_at`            DATETIME     NULL DEFAULT NULL COMMENT 'วันที่ตรวจสอบ (stamp ตอน user กำหนดสถานะ)',
  `checked_by`            INT UNSIGNED NULL DEFAULT NULL,

  `batch_id`              INT UNSIGNED NULL DEFAULT NULL,
  `uploaded_by`           INT UNSIGNED NULL DEFAULT NULL,
  `created_at`            DATETIME     NULL DEFAULT NULL,
  `updated_at`            DATETIME     NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE INDEX `uq_masks_cid_ref` (`cid`, `encounter_ref_code`),
  INDEX `idx_masks_code` (`code`),
  INDEX `idx_masks_status` (`check_status_id`),
  INDEX `idx_masks_checked_at` (`checked_at`),
  INDEX `idx_masks_code_status` (`code`, `check_status_id`)
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
