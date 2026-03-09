-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql211.infinityfree.com
-- Generation Time: 09 مارس 2026 الساعة 14:14
-- إصدار الخادم: 11.4.10-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_41288472_thelegendedu_0`
--

-- --------------------------------------------------------

--
-- بنية الجدول `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('مدير','مشرف') NOT NULL DEFAULT 'مشرف',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `admin_dashboard_widgets`
--

CREATE TABLE `admin_dashboard_widgets` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `allowed_widgets` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `admin_permissions`
--

CREATE TABLE `admin_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED NOT NULL,
  `allowed_menu` longtext NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `assignments`
--

CREATE TABLE `assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `bank_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `duration_minutes` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `questions_total` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `questions_per_student` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `created_by_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `assignment_attempts`
--

CREATE TABLE `assignment_attempts` (
  `id` int(10) UNSIGNED NOT NULL,
  `assignment_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `duration_minutes` int(10) UNSIGNED NOT NULL,
  `score` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_score` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('in_progress','submitted','expired') NOT NULL DEFAULT 'in_progress'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `assignment_attempt_answers`
--

CREATE TABLE `assignment_attempt_answers` (
  `id` int(10) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `choice_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `assignment_attempt_questions`
--

CREATE TABLE `assignment_attempt_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `attempt_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `assignment_questions`
--

CREATE TABLE `assignment_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `bank_id` int(10) UNSIGNED NOT NULL,
  `degree` decimal(12,2) NOT NULL DEFAULT 1.00,
  `correction_type` enum('single','double') NOT NULL DEFAULT 'single',
  `question_kind` enum('text','image','text_image') NOT NULL DEFAULT 'text',
  `question_text` longtext DEFAULT NULL,
  `question_image_path` varchar(255) DEFAULT NULL,
  `choices_count` int(10) UNSIGNED NOT NULL DEFAULT 4,
  `choices_kind` enum('text','image','text_image') NOT NULL DEFAULT 'text',
  `correct_choices_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `assignment_question_banks`
--

CREATE TABLE `assignment_question_banks` (
  `id` int(10) UNSIGNED NOT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `created_by_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `assignment_question_choices`
--

CREATE TABLE `assignment_question_choices` (
  `id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `choice_index` int(10) UNSIGNED NOT NULL,
  `choice_text` longtext DEFAULT NULL,
  `choice_image_path` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `centers`
--

CREATE TABLE `centers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `courses`
--

CREATE TABLE `courses` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `access_type` enum('attendance','buy','free') NOT NULL DEFAULT 'attendance',
  `buy_type` enum('none','discount') DEFAULT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `price_base` decimal(12,2) DEFAULT NULL,
  `price_discount` decimal(12,2) DEFAULT NULL,
  `discount_end` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `course_codes`
--

CREATE TABLE `course_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `is_global` tinyint(1) NOT NULL DEFAULT 0,
  `course_id` int(10) UNSIGNED DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_by_student_id` int(10) UNSIGNED DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_by_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `exams`
--

CREATE TABLE `exams` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `bank_id` int(10) UNSIGNED NOT NULL,
  `duration_minutes` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `questions_total` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `questions_per_student` int(10) UNSIGNED NOT NULL DEFAULT 10,
  `created_by_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `exam_questions`
--

CREATE TABLE `exam_questions` (
  `id` int(10) UNSIGNED NOT NULL,
  `bank_id` int(10) UNSIGNED NOT NULL,
  `degree` decimal(12,2) NOT NULL DEFAULT 1.00,
  `correction_type` enum('single','double') NOT NULL DEFAULT 'single',
  `question_kind` enum('text','image','text_image') NOT NULL DEFAULT 'text',
  `question_text` longtext DEFAULT NULL,
  `question_image_path` varchar(255) DEFAULT NULL,
  `choices_count` int(10) UNSIGNED NOT NULL DEFAULT 4,
  `choices_kind` enum('text','image','text_image') NOT NULL DEFAULT 'text',
  `correct_choices_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `exam_question_banks`
--

CREATE TABLE `exam_question_banks` (
  `id` int(10) UNSIGNED NOT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `created_by_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `exam_question_choices`
--

CREATE TABLE `exam_question_choices` (
  `id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `choice_index` int(10) UNSIGNED NOT NULL,
  `choice_text` longtext DEFAULT NULL,
  `choice_image_path` varchar(255) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `grades`
--

CREATE TABLE `grades` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `groups`
--

CREATE TABLE `groups` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `center_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `lectures`
--

CREATE TABLE `lectures` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `details` longtext DEFAULT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `lecture_codes`
--

CREATE TABLE `lecture_codes` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(40) NOT NULL,
  `is_global` tinyint(1) NOT NULL DEFAULT 0,
  `lecture_id` int(10) UNSIGNED DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_by_student_id` int(10) UNSIGNED DEFAULT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_by_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `pdfs`
--

CREATE TABLE `pdfs` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `lecture_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size_bytes` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `platform_feature_cards`
--

CREATE TABLE `platform_feature_cards` (
  `id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) NOT NULL DEFAULT 0,
  `theme` enum('light','dark') NOT NULL DEFAULT 'light',
  `icon_path` varchar(255) DEFAULT NULL,
  `title` varchar(190) NOT NULL,
  `body` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `platform_footer_social_links`
--

CREATE TABLE `platform_footer_social_links` (
  `id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) NOT NULL DEFAULT 0,
  `label` varchar(60) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icon_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `platform_settings`
--

CREATE TABLE `platform_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `platform_name` varchar(190) NOT NULL DEFAULT 'منصتي التعليمية',
  `platform_logo` varchar(255) DEFAULT NULL,
  `hero_small_title` varchar(190) DEFAULT NULL,
  `hero_title` varchar(255) DEFAULT NULL,
  `hero_description` longtext DEFAULT NULL,
  `hero_button_text` varchar(80) DEFAULT NULL,
  `hero_button_url` varchar(255) DEFAULT NULL,
  `hero_teacher_image` varchar(255) DEFAULT NULL,
  `stats_bg_text` varchar(40) DEFAULT NULL,
  `stat1_value` varchar(30) DEFAULT NULL,
  `stat1_label` varchar(190) DEFAULT NULL,
  `stat2_value` varchar(30) DEFAULT NULL,
  `stat2_label` varchar(190) DEFAULT NULL,
  `stat3_value` varchar(30) DEFAULT NULL,
  `stat3_label` varchar(190) DEFAULT NULL,
  `hero_stats_bg_text` varchar(60) DEFAULT NULL,
  `hero_stat_1_value` varchar(40) DEFAULT NULL,
  `hero_stat_1_label` varchar(190) DEFAULT NULL,
  `hero_stat_2_value` varchar(40) DEFAULT NULL,
  `hero_stat_2_label` varchar(190) DEFAULT NULL,
  `hero_stat_3_value` varchar(40) DEFAULT NULL,
  `hero_stat_3_label` varchar(190) DEFAULT NULL,
  `feature_cards_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `feature_cards_title` varchar(255) DEFAULT NULL,
  `register_image_path` varchar(255) DEFAULT NULL,
  `login_image_path` varchar(255) DEFAULT NULL,
  `register_page_image` varchar(255) DEFAULT NULL,
  `cta_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `cta_title` varchar(255) DEFAULT NULL,
  `cta_subtitle` varchar(255) DEFAULT NULL,
  `cta_button_text` varchar(80) DEFAULT NULL,
  `cta_button_url` varchar(255) DEFAULT NULL,
  `footer_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `footer_logo_path` varchar(255) DEFAULT NULL,
  `footer_social_title` varchar(190) DEFAULT NULL,
  `footer_contact_title` varchar(190) DEFAULT NULL,
  `footer_phone_1` varchar(60) DEFAULT NULL,
  `footer_phone_2` varchar(60) DEFAULT NULL,
  `footer_rights_line` varchar(255) DEFAULT NULL,
  `footer_developed_by_line` varchar(255) DEFAULT NULL,
  `register_image` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `settings`
--

CREATE TABLE `settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `platform_name` varchar(190) NOT NULL DEFAULT 'منصتي التعليمية',
  `platform_logo` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `students`
--

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(190) NOT NULL,
  `student_phone` varchar(30) NOT NULL,
  `parent_phone` varchar(30) DEFAULT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `governorate` varchar(60) NOT NULL,
  `status` enum('اونلاين','سنتر') NOT NULL DEFAULT 'اونلاين',
  `center_id` int(10) UNSIGNED DEFAULT NULL,
  `group_id` int(10) UNSIGNED DEFAULT NULL,
  `barcode` varchar(60) DEFAULT NULL,
  `wallet_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `password_hash` varchar(255) NOT NULL,
  `password_plain` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `student_course_enrollments`
--

CREATE TABLE `student_course_enrollments` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `access_type` enum('free','buy','attendance','code') NOT NULL DEFAULT 'buy',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `student_devices`
--

CREATE TABLE `student_devices` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `device_hash` varchar(64) NOT NULL,
  `device_label` varchar(190) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `ip_first` varchar(64) DEFAULT NULL,
  `first_login_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `student_notifications`
--

CREATE TABLE `student_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `grade_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `body` longtext NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by_admin_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `student_notification_reads`
--

CREATE TABLE `student_notification_reads` (
  `id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `notification_id` int(10) UNSIGNED NOT NULL,
  `read_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `videos`
--

CREATE TABLE `videos` (
  `id` int(10) UNSIGNED NOT NULL,
  `course_id` int(10) UNSIGNED NOT NULL,
  `lecture_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(190) NOT NULL,
  `duration_minutes` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `allowed_views_per_student` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `video_type` enum('youtube','bunny','inkrypt','vimeo','vdocipher') NOT NULL DEFAULT 'youtube',
  `embed_iframe` longtext DEFAULT NULL,
  `embed_iframe_enc` longtext DEFAULT NULL,
  `embed_iframe_iv` varchar(64) DEFAULT NULL,
  `exam_id` int(10) UNSIGNED DEFAULT NULL,
  `assignment_id` int(10) UNSIGNED DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `video_student_views`
--

CREATE TABLE `video_student_views` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `video_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `views_used` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `last_view_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `video_views`
--

CREATE TABLE `video_views` (
  `id` bigint(20) NOT NULL,
  `video_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `view_count` int(11) NOT NULL DEFAULT 0,
  `last_view_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_dashboard_widgets`
--
ALTER TABLE `admin_dashboard_widgets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_admin` (`admin_id`);

--
-- Indexes for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_admin` (`admin_id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assign_grade` (`grade_id`),
  ADD KEY `idx_assign_bank` (`bank_id`);

--
-- Indexes for table `assignment_attempts`
--
ALTER TABLE `assignment_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_attempt` (`assignment_id`,`student_id`),
  ADD KEY `idx_attempt_assignment` (`assignment_id`),
  ADD KEY `idx_attempt_student` (`student_id`);

--
-- Indexes for table `assignment_attempt_answers`
--
ALTER TABLE `assignment_attempt_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_attempt_question_choice` (`attempt_id`,`question_id`,`choice_id`),
  ADD KEY `idx_ans_attempt` (`attempt_id`),
  ADD KEY `fk_ans_question` (`question_id`),
  ADD KEY `fk_ans_choice` (`choice_id`);

--
-- Indexes for table `assignment_attempt_questions`
--
ALTER TABLE `assignment_attempt_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_attempt_question` (`attempt_id`,`question_id`),
  ADD KEY `idx_attempt_q` (`attempt_id`),
  ADD KEY `fk_attemptq_question` (`question_id`);

--
-- Indexes for table `assignment_questions`
--
ALTER TABLE `assignment_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_q_bank` (`bank_id`);

--
-- Indexes for table `assignment_question_banks`
--
ALTER TABLE `assignment_question_banks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_grade_bank` (`grade_id`,`name`),
  ADD KEY `idx_bank_grade` (`grade_id`);

--
-- Indexes for table `assignment_question_choices`
--
ALTER TABLE `assignment_question_choices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_question_choice_index` (`question_id`,`choice_index`),
  ADD KEY `idx_choice_question` (`question_id`),
  ADD KEY `idx_choice_correct` (`question_id`,`is_correct`);

--
-- Indexes for table `centers`
--
ALTER TABLE `centers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_course_name` (`name`),
  ADD KEY `idx_course_grade` (`grade_id`);

--
-- Indexes for table `course_codes`
--
ALTER TABLE `course_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_code` (`code`),
  ADD KEY `idx_course` (`course_id`),
  ADD KEY `idx_used` (`is_used`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_exam_grade` (`grade_id`),
  ADD KEY `idx_exam_bank` (`bank_id`);

--
-- Indexes for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_q_bank` (`bank_id`);

--
-- Indexes for table `exam_question_banks`
--
ALTER TABLE `exam_question_banks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_grade_bank` (`grade_id`,`name`),
  ADD KEY `idx_bank_grade` (`grade_id`);

--
-- Indexes for table `exam_question_choices`
--
ALTER TABLE `exam_question_choices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_question_choice_index` (`question_id`,`choice_index`),
  ADD KEY `idx_choice_question` (`question_id`),
  ADD KEY `idx_choice_correct` (`question_id`,`is_correct`);

--
-- Indexes for table `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_grade_name` (`name`),
  ADD KEY `idx_grades_sort` (`sort_order`,`id`);

--
-- Indexes for table `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_group_name` (`name`),
  ADD KEY `idx_groups_center` (`center_id`),
  ADD KEY `idx_groups_grade` (`grade_id`);

--
-- Indexes for table `lectures`
--
ALTER TABLE `lectures`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_lecture_course_name` (`course_id`,`name`),
  ADD KEY `idx_lectures_course` (`course_id`);

--
-- Indexes for table `lecture_codes`
--
ALTER TABLE `lecture_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_code` (`code`),
  ADD KEY `idx_lecture` (`lecture_id`),
  ADD KEY `idx_used` (`is_used`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `pdfs`
--
ALTER TABLE `pdfs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_pdf_lecture_title` (`lecture_id`,`title`),
  ADD KEY `idx_pdfs_course` (`course_id`),
  ADD KEY `idx_pdfs_lecture` (`lecture_id`);

--
-- Indexes for table `platform_feature_cards`
--
ALTER TABLE `platform_feature_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_sort` (`is_active`,`sort_order`,`id`);

--
-- Indexes for table `platform_footer_social_links`
--
ALTER TABLE `platform_footer_social_links`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_active_sort` (`is_active`,`sort_order`,`id`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_phone` (`student_phone`),
  ADD UNIQUE KEY `uniq_student_barcode` (`barcode`),
  ADD KEY `idx_students_grade` (`grade_id`),
  ADD KEY `idx_students_center` (`center_id`),
  ADD KEY `idx_students_group` (`group_id`),
  ADD KEY `idx_students_name` (`full_name`);

--
-- Indexes for table `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_course` (`student_id`,`course_id`),
  ADD KEY `idx_student` (`student_id`),
  ADD KEY `idx_course` (`course_id`);

--
-- Indexes for table `student_devices`
--
ALTER TABLE `student_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_device` (`student_id`,`device_hash`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_grade` (`grade_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `student_notification_reads`
--
ALTER TABLE `student_notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_notification` (`student_id`,`notification_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_notification_id` (`notification_id`);

--
-- Indexes for table `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_videos_course` (`course_id`),
  ADD KEY `idx_videos_lecture` (`lecture_id`);

--
-- Indexes for table `video_student_views`
--
ALTER TABLE `video_student_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_video_student` (`video_id`,`student_id`),
  ADD KEY `idx_video` (`video_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `video_views`
--
ALTER TABLE `video_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_video_student` (`video_id`,`student_id`),
  ADD KEY `video_id` (`video_id`),
  ADD KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_dashboard_widgets`
--
ALTER TABLE `admin_dashboard_widgets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_permissions`
--
ALTER TABLE `admin_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_attempts`
--
ALTER TABLE `assignment_attempts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_attempt_answers`
--
ALTER TABLE `assignment_attempt_answers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_attempt_questions`
--
ALTER TABLE `assignment_attempt_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_questions`
--
ALTER TABLE `assignment_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_question_banks`
--
ALTER TABLE `assignment_question_banks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_question_choices`
--
ALTER TABLE `assignment_question_choices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `centers`
--
ALTER TABLE `centers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_codes`
--
ALTER TABLE `course_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_question_banks`
--
ALTER TABLE `exam_question_banks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exam_question_choices`
--
ALTER TABLE `exam_question_choices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lectures`
--
ALTER TABLE `lectures`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lecture_codes`
--
ALTER TABLE `lecture_codes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pdfs`
--
ALTER TABLE `pdfs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_feature_cards`
--
ALTER TABLE `platform_feature_cards`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_footer_social_links`
--
ALTER TABLE `platform_footer_social_links`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_settings`
--
ALTER TABLE `platform_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_devices`
--
ALTER TABLE `student_devices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_notifications`
--
ALTER TABLE `student_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_notification_reads`
--
ALTER TABLE `student_notification_reads`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_student_views`
--
ALTER TABLE `video_student_views`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `video_views`
--
ALTER TABLE `video_views`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- قيود الجداول المحفوظة
--

--
-- القيود للجدول `admin_dashboard_widgets`
--
ALTER TABLE `admin_dashboard_widgets`
  ADD CONSTRAINT `fk_admin_widgets_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `admin_permissions`
--
ALTER TABLE `admin_permissions`
  ADD CONSTRAINT `fk_admin_permissions_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assign_bank` FOREIGN KEY (`bank_id`) REFERENCES `assignment_question_banks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assign_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `assignment_attempts`
--
ALTER TABLE `assignment_attempts`
  ADD CONSTRAINT `fk_attempt_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attempt_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `assignment_attempt_answers`
--
ALTER TABLE `assignment_attempt_answers`
  ADD CONSTRAINT `fk_ans_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `assignment_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ans_choice` FOREIGN KEY (`choice_id`) REFERENCES `assignment_question_choices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ans_question` FOREIGN KEY (`question_id`) REFERENCES `assignment_questions` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `assignment_attempt_questions`
--
ALTER TABLE `assignment_attempt_questions`
  ADD CONSTRAINT `fk_attemptq_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `assignment_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attemptq_question` FOREIGN KEY (`question_id`) REFERENCES `assignment_questions` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `assignment_questions`
--
ALTER TABLE `assignment_questions`
  ADD CONSTRAINT `fk_aq_bank` FOREIGN KEY (`bank_id`) REFERENCES `assignment_question_banks` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `assignment_question_banks`
--
ALTER TABLE `assignment_question_banks`
  ADD CONSTRAINT `fk_aqb_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `assignment_question_choices`
--
ALTER TABLE `assignment_question_choices`
  ADD CONSTRAINT `fk_aqc_question` FOREIGN KEY (`question_id`) REFERENCES `assignment_questions` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `courses`
--
ALTER TABLE `courses`
  ADD CONSTRAINT `fk_courses_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `course_codes`
--
ALTER TABLE `course_codes`
  ADD CONSTRAINT `fk_course_codes_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE SET NULL;

--
-- القيود للجدول `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `fk_exam_bank` FOREIGN KEY (`bank_id`) REFERENCES `exam_question_banks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_exam_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD CONSTRAINT `fk_eq_bank` FOREIGN KEY (`bank_id`) REFERENCES `exam_question_banks` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `exam_question_banks`
--
ALTER TABLE `exam_question_banks`
  ADD CONSTRAINT `fk_eqb_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `exam_question_choices`
--
ALTER TABLE `exam_question_choices`
  ADD CONSTRAINT `fk_eqc_question` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `groups`
--
ALTER TABLE `groups`
  ADD CONSTRAINT `fk_groups_center` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_groups_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `lectures`
--
ALTER TABLE `lectures`
  ADD CONSTRAINT `fk_lectures_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `lecture_codes`
--
ALTER TABLE `lecture_codes`
  ADD CONSTRAINT `fk_lecture_codes_lecture` FOREIGN KEY (`lecture_id`) REFERENCES `lectures` (`id`) ON DELETE SET NULL;

--
-- القيود للجدول `pdfs`
--
ALTER TABLE `pdfs`
  ADD CONSTRAINT `fk_pdfs_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pdfs_lecture` FOREIGN KEY (`lecture_id`) REFERENCES `lectures` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_center` FOREIGN KEY (`center_id`) REFERENCES `centers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_students_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`),
  ADD CONSTRAINT `fk_students_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL;

--
-- القيود للجدول `student_course_enrollments`
--
ALTER TABLE `student_course_enrollments`
  ADD CONSTRAINT `fk_sce_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sce_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `student_devices`
--
ALTER TABLE `student_devices`
  ADD CONSTRAINT `fk_student_devices_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `student_notifications`
--
ALTER TABLE `student_notifications`
  ADD CONSTRAINT `fk_student_notifications_grade` FOREIGN KEY (`grade_id`) REFERENCES `grades` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `videos`
--
ALTER TABLE `videos`
  ADD CONSTRAINT `fk_videos_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_videos_lecture` FOREIGN KEY (`lecture_id`) REFERENCES `lectures` (`id`) ON DELETE CASCADE;

--
-- القيود للجدول `video_student_views`
--
ALTER TABLE `video_student_views`
  ADD CONSTRAINT `fk_vsv_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_vsv_video` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
