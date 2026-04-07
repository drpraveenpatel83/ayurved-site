-- ============================================================
-- Subjects System Update
-- Hostinger phpMyAdmin mein import karo
-- ============================================================

-- 1. categories table mein 'ncism' type add karo
ALTER TABLE `categories`
  MODIFY COLUMN `type` ENUM('bams_year','samhita','aiapget','govt_exam','subject','ncism','bams_prof') NOT NULL;

-- 2. Subject PDFs table
CREATE TABLE IF NOT EXISTS `subject_pdfs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL COMMENT 'subject category id',
  `title` VARCHAR(255) NOT NULL,
  `file_url` VARCHAR(500) NOT NULL,
  `pdf_type` ENUM('syllabus','notes','pyq','other') DEFAULT 'syllabus',
  `display_order` SMALLINT DEFAULT 0,
  `is_published` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Test ↔ Subject (many-to-many junction)
CREATE TABLE IF NOT EXISTS `test_subject_map` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `test_id` INT UNSIGNED NOT NULL,
  `category_id` INT UNSIGNED NOT NULL COMMENT 'subject category id',
  UNIQUE KEY `uq_test_subject` (`test_id`,`category_id`),
  FOREIGN KEY (`test_id`) REFERENCES `mock_tests`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. mock_tests exam_type mein bams/ncism add karo
ALTER TABLE `mock_tests`
  MODIFY COLUMN `exam_type` ENUM('aiapget','govt_exam','bams','ncism') DEFAULT 'aiapget';

-- 5. Default BAMS Prof parent categories (agar nahi hain to insert karo)
INSERT IGNORE INTO `categories` (`name`,`slug`,`type`,`bams_year`,`icon`,`color`,`display_order`,`is_active`)
VALUES
  ('BAMS 1st Prof','bams-1st-prof','bams_prof',1,'📚','#E67E22',1,1),
  ('BAMS 2nd Prof','bams-2nd-prof','bams_prof',2,'📗','#27AE60',2,1),
  ('BAMS 3rd Prof','bams-3rd-prof','bams_prof',3,'📘','#2980B9',3,1),
  ('AIAPGET','aiapget','aiapget',NULL,'🎯','#C0392B',4,1),
  ('Govt Exam','govt-exam','govt_exam',NULL,'🏛','#16A085',5,1),
  ('NCISM','ncism','ncism',NULL,'📋','#1a6e3c',6,1);
