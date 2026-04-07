-- Ayurveda Quiz Database — Hostinger phpMyAdmin mein import karo
SET FOREIGN_KEY_CHECKS=0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(15) DEFAULT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('student','admin') DEFAULT 'student',
  `is_active` TINYINT(1) DEFAULT 1,
  `membership_type` ENUM('free','monthly','yearly') DEFAULT 'free',
  `membership_expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `session_token` VARCHAR(128) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_token` (`session_token`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(170) NOT NULL,
  `type` ENUM('bams_year','samhita','aiapget','govt_exam','subject') NOT NULL,
  `bams_year` TINYINT(1) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `icon` VARCHAR(10) DEFAULT NULL,
  `color` VARCHAR(20) DEFAULT '#E67E22',
  `display_order` SMALLINT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_slug` (`slug`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `question_text` TEXT NOT NULL,
  `option_a` VARCHAR(600) NOT NULL,
  `option_b` VARCHAR(600) NOT NULL,
  `option_c` VARCHAR(600) NOT NULL,
  `option_d` VARCHAR(600) NOT NULL,
  `correct_option` ENUM('a','b','c','d') NOT NULL,
  `explanation` TEXT DEFAULT NULL,
  `difficulty` ENUM('easy','medium','hard') DEFAULT 'medium',
  `source` VARCHAR(300) DEFAULT NULL,
  `year` SMALLINT DEFAULT NULL,
  `image_url` VARCHAR(500) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `type` ENUM('random','weekly','monthly','practice','previous_year','mock') NOT NULL,
  `question_count` TINYINT UNSIGNED NOT NULL DEFAULT 10,
  `duration_mins` SMALLINT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `display_order` SMALLINT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_quizzes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `quiz_date` DATE NOT NULL,
  `title` VARCHAR(200) DEFAULT NULL,
  `is_published` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_date` (`quiz_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `daily_quiz_questions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `daily_quiz_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `display_order` TINYINT DEFAULT 0,
  FOREIGN KEY (`daily_quiz_id`) REFERENCES `daily_quizzes`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `quiz_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `quiz_type` ENUM('random','daily','weekly','monthly','practice','previous_year','mock') NOT NULL,
  `category_id` INT UNSIGNED DEFAULT NULL,
  `daily_quiz_id` INT UNSIGNED DEFAULT NULL,
  `score` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `total_questions` TINYINT UNSIGNED NOT NULL,
  `time_taken_secs` SMALLINT UNSIGNED DEFAULT NULL,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  `share_token` VARCHAR(64) DEFAULT NULL,
  UNIQUE KEY `uq_share` (`share_token`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attempt_answers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `attempt_id` INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `selected_option` ENUM('a','b','c','d') DEFAULT NULL,
  `is_correct` TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(250) NOT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `file_url` VARCHAR(500) DEFAULT NULL,
  `type` ENUM('syllabus','short_notes','full_notes') NOT NULL,
  `display_order` SMALLINT DEFAULT 0,
  `is_published` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED DEFAULT NULL,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `banners` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `image_url` VARCHAR(500) NOT NULL,
  `title` VARCHAR(200) DEFAULT NULL,
  `subtitle` VARCHAR(300) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `color` VARCHAR(20) DEFAULT '#E67E22',
  `display_order` SMALLINT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `posts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `slug` VARCHAR(520) NOT NULL,
  `excerpt` TEXT DEFAULT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `thumbnail` VARCHAR(500) DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `category_slug` VARCHAR(120) DEFAULT NULL,
  `author` VARCHAR(150) DEFAULT 'Admin',
  `is_published` TINYINT(1) DEFAULT 1,
  `is_featured` TINYINT(1) DEFAULT 0,
  `view_count` INT UNSIGNED DEFAULT 0,
  `published_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_category` (`category_slug`),
  KEY `idx_published` (`is_published`,`published_at`),
  KEY `idx_featured` (`is_featured`,`is_published`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;

-- ── Default Data ──────────────────────────────────────────────
-- Admin user (password: Admin@1234)
INSERT INTO `users` (`name`,`email`,`password_hash`,`role`) VALUES
('Admin','admin@ayurvedaquiz.com','$2y$10$TKh8H1.PfzsOf8XgFoQJCu9PJkR6y4K4fRxvFBNTuuW1LhZknqFyW','admin');

-- Main categories
INSERT INTO `categories` (`name`,`slug`,`type`,`bams_year`,`icon`,`color`,`display_order`) VALUES
('BAMS 1st Year','bams-1st-year','bams_year',1,'📚','#E67E22',1),
('BAMS 2nd Year','bams-2nd-year','bams_year',2,'📗','#27AE60',2),
('BAMS 3rd Year','bams-3rd-year','bams_year',3,'📘','#2980B9',3),
('Samhita','samhita','samhita',NULL,'📜','#8E44AD',4),
('AIAPGET','aiapget','aiapget',NULL,'🎯','#C0392B',5),
('Govt Exams','govt-exams','govt_exam',NULL,'🏛','#16A085',6);

-- BAMS 1st Year subjects
INSERT INTO `categories` (`parent_id`,`name`,`slug`,`type`,`display_order`) VALUES
(1,'Padartha Vigyan','padartha-vigyan','subject',1),
(1,'Rachana Sharira','rachana-sharira','subject',2),
(1,'Kriya Sharira','kriya-sharira','subject',3),
(1,'Maulika Siddhanta','maulika-siddhanta','subject',4),
(1,'Sanskrit','sanskrit','subject',5);

-- BAMS 2nd Year subjects
INSERT INTO `categories` (`parent_id`,`name`,`slug`,`type`,`display_order`) VALUES
(2,'Dravyaguna Vigyan','dravyaguna-vigyan','subject',1),
(2,'Roga Nidana','roga-nidana','subject',2),
(2,'Rasashastra','rasashastra','subject',3),
(2,'Charaka Samhita (2nd Yr)','charaka-samhita-2yr','subject',4);

-- BAMS 3rd Year subjects
INSERT INTO `categories` (`parent_id`,`name`,`slug`,`type`,`display_order`) VALUES
(3,'Kayachikitsa','kayachikitsa','subject',1),
(3,'Panchakarma','panchakarma','subject',2),
(3,'Prasuti Tantra','prasuti-tantra','subject',3),
(3,'Kaumarabhritya','kaumarabhritya','subject',4);

-- Samhita
INSERT INTO `categories` (`parent_id`,`name`,`slug`,`type`,`display_order`) VALUES
(4,'Charaka Samhita','charaka-samhita','subject',1),
(4,'Sushruta Samhita','sushruta-samhita','subject',2),
(4,'Ashtanga Hridayam','ashtanga-hridayam','subject',3),
(4,'Ashtanga Sangraha','ashtanga-sangraha','subject',4);

-- AIAPGET
INSERT INTO `categories` (`parent_id`,`name`,`slug`,`type`,`display_order`) VALUES
(5,'PYQ 2023','aiapget-pyq-2023','subject',1),
(5,'PYQ 2022','aiapget-pyq-2022','subject',2),
(5,'PYQ 2021','aiapget-pyq-2021','subject',3),
(5,'Mock Test 1','aiapget-mock-1','subject',4);

-- Default banner
INSERT INTO `banners` (`image_url`,`title`,`subtitle`,`display_order`,`is_active`) VALUES
('/assets/img/banner-default.jpg','🌿 Ayurveda Quiz & Notes','BAMS, AIAPGET, Govt Exams ki taiyari karein',1,1);
