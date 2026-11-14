-- StemFlow AI Database Schema
-- Database Name: stemflow_db
-- Engine: InnoDB, Charset: utf8mb4

CREATE DATABASE IF NOT EXISTS `stemflow_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `stemflow_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Projects Table
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `original_file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT NOT NULL DEFAULT 0,
    `audio_duration` DECIMAL(10,2) DEFAULT 0.00,
    `sample_rate` INT DEFAULT 44100,
    `channels` INT DEFAULT 2,
    `status` ENUM('uploaded', 'queued', 'processing', 'completed', 'failed') DEFAULT 'uploaded',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_status` (`status`),
    CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Processing Jobs Table
CREATE TABLE IF NOT EXISTS `processing_jobs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `model_name` VARCHAR(100) DEFAULT 'htdemucs',
    `status` ENUM('queued', 'processing', 'completed', 'failed') DEFAULT 'queued',
    `error_message` TEXT NULL,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_job_status` (`status`),
    CONSTRAINT `fk_jobs_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_jobs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Stems Table
CREATE TABLE IF NOT EXISTS `stems` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `processing_job_id` INT NULL,
    `stem_type` ENUM('vocals', 'drums', 'bass', 'other') NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT NOT NULL DEFAULT 0,
    `duration` DECIMAL(10,2) DEFAULT 0.00,
    `sample_rate` INT DEFAULT 44100,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_project_stem` (`project_id`, `stem_type`),
    INDEX `idx_stems_project` (`project_id`),
    CONSTRAINT `fk_stems_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stems_job` FOREIGN KEY (`processing_job_id`) REFERENCES `processing_jobs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Audio Settings Table
CREATE TABLE IF NOT EXISTS `audio_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `vocals_volume` DECIMAL(5,2) DEFAULT 100.00,
    `drums_volume` DECIMAL(5,2) DEFAULT 100.00,
    `bass_volume` DECIMAL(5,2) DEFAULT 100.00,
    `other_volume` DECIMAL(5,2) DEFAULT 100.00,
    `vocals_pitch` INT DEFAULT 0,
    `drums_pitch` INT DEFAULT 0,
    `bass_pitch` INT DEFAULT 0,
    `other_pitch` INT DEFAULT 0,
    `master_volume` DECIMAL(5,2) DEFAULT 100.00,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_audio_settings_project` (`project_id`),
    CONSTRAINT `fk_audio_settings_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_audio_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Stem Settings Table (Mute & Solo)
CREATE TABLE IF NOT EXISTS `stem_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `stem_type` ENUM('vocals', 'drums', 'bass', 'other') NOT NULL,
    `is_muted` TINYINT(1) DEFAULT 0,
    `is_solo` TINYINT(1) DEFAULT 0,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_project_stem_setting` (`project_id`, `stem_type`),
    INDEX `idx_stem_settings_project` (`project_id`),
    CONSTRAINT `fk_stem_settings_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Exports Table
CREATE TABLE IF NOT EXISTS `exports` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `export_type` VARCHAR(50) DEFAULT 'adjusted_stems',
    `file_path` VARCHAR(500) NOT NULL,
    `status` ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_exports_project` (`project_id`),
    INDEX `idx_exports_user` (`user_id`),
    CONSTRAINT `fk_exports_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_exports_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
