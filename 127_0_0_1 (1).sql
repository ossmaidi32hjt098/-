-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 30 مايو 2026 الساعة 03:02
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `phpmyadmin`
--
CREATE DATABASE IF NOT EXISTS `phpmyadmin` DEFAULT CHARACTER SET utf8 COLLATE utf8_bin;
USE `phpmyadmin`;

-- --------------------------------------------------------

--
-- بنية الجدول `pma__bookmark`
--

CREATE TABLE `pma__bookmark` (
  `id` int(10) UNSIGNED NOT NULL,
  `dbase` varchar(255) NOT NULL DEFAULT '',
  `user` varchar(255) NOT NULL DEFAULT '',
  `label` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `query` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Bookmarks';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__central_columns`
--

CREATE TABLE `pma__central_columns` (
  `db_name` varchar(64) NOT NULL,
  `col_name` varchar(64) NOT NULL,
  `col_type` varchar(64) NOT NULL,
  `col_length` text DEFAULT NULL,
  `col_collation` varchar(64) NOT NULL,
  `col_isNull` tinyint(1) NOT NULL,
  `col_extra` varchar(255) DEFAULT '',
  `col_default` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Central list of columns';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__column_info`
--

CREATE TABLE `pma__column_info` (
  `id` int(5) UNSIGNED NOT NULL,
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `column_name` varchar(64) NOT NULL DEFAULT '',
  `comment` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `mimetype` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT '',
  `transformation` varchar(255) NOT NULL DEFAULT '',
  `transformation_options` varchar(255) NOT NULL DEFAULT '',
  `input_transformation` varchar(255) NOT NULL DEFAULT '',
  `input_transformation_options` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Column information for phpMyAdmin';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__designer_settings`
--

CREATE TABLE `pma__designer_settings` (
  `username` varchar(64) NOT NULL,
  `settings_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Settings related to Designer';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__export_templates`
--

CREATE TABLE `pma__export_templates` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL,
  `export_type` varchar(10) NOT NULL,
  `template_name` varchar(64) NOT NULL,
  `template_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved export templates';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__favorite`
--

CREATE TABLE `pma__favorite` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Favorite tables';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__history`
--

CREATE TABLE `pma__history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db` varchar(64) NOT NULL DEFAULT '',
  `table` varchar(64) NOT NULL DEFAULT '',
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp(),
  `sqlquery` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='SQL history for phpMyAdmin';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__navigationhiding`
--

CREATE TABLE `pma__navigationhiding` (
  `username` varchar(64) NOT NULL,
  `item_name` varchar(64) NOT NULL,
  `item_type` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Hidden items of navigation tree';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__pdf_pages`
--

CREATE TABLE `pma__pdf_pages` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `page_nr` int(10) UNSIGNED NOT NULL,
  `page_descr` varchar(50) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='PDF relation pages for phpMyAdmin';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__recent`
--

CREATE TABLE `pma__recent` (
  `username` varchar(64) NOT NULL,
  `tables` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Recently accessed tables';

--
-- إرجاع أو استيراد بيانات الجدول `pma__recent`
--

INSERT INTO `pma__recent` (`username`, `tables`) VALUES
('root', '[{\"db\":\"zakat_central_db\",\"table\":\"committees_registry\"},{\"db\":\"zakat_aleppo_db\",\"table\":\"donation_types\"},{\"db\":\"zakat_daraa_db\",\"table\":\"donation_types\"},{\"db\":\"zakat_aleppo_db\",\"table\":\"committees\"},{\"db\":\"zakat_aleppo_db\",\"table\":\"users\"},{\"db\":\"shefa_db\",\"table\":\"committees\"},{\"db\":\"shefa_db\",\"table\":\"user_settings\"},{\"db\":\"shefa_db\",\"table\":\"users\"},{\"db\":\"shefa_db\",\"table\":\"donation_types\"},{\"db\":\"shefa_db\",\"table\":\"beneficiaries\"}]');

-- --------------------------------------------------------

--
-- بنية الجدول `pma__relation`
--

CREATE TABLE `pma__relation` (
  `master_db` varchar(64) NOT NULL DEFAULT '',
  `master_table` varchar(64) NOT NULL DEFAULT '',
  `master_field` varchar(64) NOT NULL DEFAULT '',
  `foreign_db` varchar(64) NOT NULL DEFAULT '',
  `foreign_table` varchar(64) NOT NULL DEFAULT '',
  `foreign_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Relation table';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__savedsearches`
--

CREATE TABLE `pma__savedsearches` (
  `id` int(5) UNSIGNED NOT NULL,
  `username` varchar(64) NOT NULL DEFAULT '',
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `search_name` varchar(64) NOT NULL DEFAULT '',
  `search_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Saved searches';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__table_coords`
--

CREATE TABLE `pma__table_coords` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `pdf_page_number` int(11) NOT NULL DEFAULT 0,
  `x` float UNSIGNED NOT NULL DEFAULT 0,
  `y` float UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table coordinates for phpMyAdmin PDF output';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__table_info`
--

CREATE TABLE `pma__table_info` (
  `db_name` varchar(64) NOT NULL DEFAULT '',
  `table_name` varchar(64) NOT NULL DEFAULT '',
  `display_field` varchar(64) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Table information for phpMyAdmin';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__table_uiprefs`
--

CREATE TABLE `pma__table_uiprefs` (
  `username` varchar(64) NOT NULL,
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `prefs` text NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Tables'' UI preferences';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__tracking`
--

CREATE TABLE `pma__tracking` (
  `db_name` varchar(64) NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `version` int(10) UNSIGNED NOT NULL,
  `date_created` datetime NOT NULL,
  `date_updated` datetime NOT NULL,
  `schema_snapshot` text NOT NULL,
  `schema_sql` text DEFAULT NULL,
  `data_sql` longtext DEFAULT NULL,
  `tracking` set('UPDATE','REPLACE','INSERT','DELETE','TRUNCATE','CREATE DATABASE','ALTER DATABASE','DROP DATABASE','CREATE TABLE','ALTER TABLE','RENAME TABLE','DROP TABLE','CREATE INDEX','DROP INDEX','CREATE VIEW','ALTER VIEW','DROP VIEW') DEFAULT NULL,
  `tracking_active` int(1) UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Database changes tracking for phpMyAdmin';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__userconfig`
--

CREATE TABLE `pma__userconfig` (
  `username` varchar(64) NOT NULL,
  `timevalue` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `config_data` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User preferences storage for phpMyAdmin';

--
-- إرجاع أو استيراد بيانات الجدول `pma__userconfig`
--

INSERT INTO `pma__userconfig` (`username`, `timevalue`, `config_data`) VALUES
('root', '2026-05-30 00:54:44', '{\"Console\\/Mode\":\"collapse\",\"lang\":\"ar\",\"NavigationWidth\":216}');

-- --------------------------------------------------------

--
-- بنية الجدول `pma__usergroups`
--

CREATE TABLE `pma__usergroups` (
  `usergroup` varchar(64) NOT NULL,
  `tab` varchar(64) NOT NULL,
  `allowed` enum('Y','N') NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='User groups with configured menu items';

-- --------------------------------------------------------

--
-- بنية الجدول `pma__users`
--

CREATE TABLE `pma__users` (
  `username` varchar(64) NOT NULL,
  `usergroup` varchar(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin COMMENT='Users and their assignments to user groups';

--
-- Indexes for dumped tables
--

--
-- Indexes for table `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pma__central_columns`
--
ALTER TABLE `pma__central_columns`
  ADD PRIMARY KEY (`db_name`,`col_name`);

--
-- Indexes for table `pma__column_info`
--
ALTER TABLE `pma__column_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `db_name` (`db_name`,`table_name`,`column_name`);

--
-- Indexes for table `pma__designer_settings`
--
ALTER TABLE `pma__designer_settings`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_user_type_template` (`username`,`export_type`,`template_name`);

--
-- Indexes for table `pma__favorite`
--
ALTER TABLE `pma__favorite`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__history`
--
ALTER TABLE `pma__history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`,`db`,`table`,`timevalue`);

--
-- Indexes for table `pma__navigationhiding`
--
ALTER TABLE `pma__navigationhiding`
  ADD PRIMARY KEY (`username`,`item_name`,`item_type`,`db_name`,`table_name`);

--
-- Indexes for table `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  ADD PRIMARY KEY (`page_nr`),
  ADD KEY `db_name` (`db_name`);

--
-- Indexes for table `pma__recent`
--
ALTER TABLE `pma__recent`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__relation`
--
ALTER TABLE `pma__relation`
  ADD PRIMARY KEY (`master_db`,`master_table`,`master_field`),
  ADD KEY `foreign_field` (`foreign_db`,`foreign_table`);

--
-- Indexes for table `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `u_savedsearches_username_dbname` (`username`,`db_name`,`search_name`);

--
-- Indexes for table `pma__table_coords`
--
ALTER TABLE `pma__table_coords`
  ADD PRIMARY KEY (`db_name`,`table_name`,`pdf_page_number`);

--
-- Indexes for table `pma__table_info`
--
ALTER TABLE `pma__table_info`
  ADD PRIMARY KEY (`db_name`,`table_name`);

--
-- Indexes for table `pma__table_uiprefs`
--
ALTER TABLE `pma__table_uiprefs`
  ADD PRIMARY KEY (`username`,`db_name`,`table_name`);

--
-- Indexes for table `pma__tracking`
--
ALTER TABLE `pma__tracking`
  ADD PRIMARY KEY (`db_name`,`table_name`,`version`);

--
-- Indexes for table `pma__userconfig`
--
ALTER TABLE `pma__userconfig`
  ADD PRIMARY KEY (`username`);

--
-- Indexes for table `pma__usergroups`
--
ALTER TABLE `pma__usergroups`
  ADD PRIMARY KEY (`usergroup`,`tab`,`allowed`);

--
-- Indexes for table `pma__users`
--
ALTER TABLE `pma__users`
  ADD PRIMARY KEY (`username`,`usergroup`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `pma__bookmark`
--
ALTER TABLE `pma__bookmark`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__column_info`
--
ALTER TABLE `pma__column_info`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__export_templates`
--
ALTER TABLE `pma__export_templates`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__history`
--
ALTER TABLE `pma__history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__pdf_pages`
--
ALTER TABLE `pma__pdf_pages`
  MODIFY `page_nr` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pma__savedsearches`
--
ALTER TABLE `pma__savedsearches`
  MODIFY `id` int(5) UNSIGNED NOT NULL AUTO_INCREMENT;
--
-- Database: `test`
--
CREATE DATABASE IF NOT EXISTS `test` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `test`;
--
-- Database: `zakat_aleppo_db`
--
CREATE DATABASE IF NOT EXISTS `zakat_aleppo_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `zakat_aleppo_db`;

-- --------------------------------------------------------

--
-- بنية الجدول `beneficiaries`
--

CREATE TABLE `beneficiaries` (
  `id` int(11) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `gender` enum('ذكر','أنثى') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `income_level` decimal(10,2) DEFAULT 0.00,
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `family_members_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `family_file_number` varchar(50) DEFAULT NULL,
  `family_size` int(11) DEFAULT 0,
  `children_count` int(11) DEFAULT 0,
  `elderly_count` int(11) DEFAULT 0,
  `has_breadwinner` tinyint(1) DEFAULT 0,
  `relationship_to_head` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT 0.00,
  `income_source` varchar(255) DEFAULT NULL,
  `employment_status` varchar(100) DEFAULT NULL,
  `work_type` varchar(100) DEFAULT NULL,
  `living_standard` varchar(100) DEFAULT NULL,
  `has_debt` tinyint(1) DEFAULT 0,
  `health_general` text DEFAULT NULL,
  `has_chronic_disease` tinyint(1) DEFAULT 0,
  `has_disability` tinyint(1) DEFAULT 0,
  `disability_type` varchar(255) DEFAULT NULL,
  `needs_continuous_treatment` tinyint(1) DEFAULT 0,
  `education_level` varchar(100) DEFAULT NULL,
  `current_study_status` varchar(100) DEFAULT NULL,
  `student_count` int(11) DEFAULT 0,
  `has_learning_difficulties` tinyint(1) DEFAULT 0,
  `housing_type` varchar(100) DEFAULT NULL,
  `housing_condition` varchar(100) DEFAULT NULL,
  `room_count` int(11) DEFAULT 0,
  `has_basic_services` tinyint(1) DEFAULT 0,
  `eviction_risk` tinyint(1) DEFAULT 0,
  `gps_coordinates` varchar(255) DEFAULT NULL,
  `map_link` varchar(500) DEFAULT NULL,
  `nearest_landmark` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'جديد',
  `priority_level` varchar(50) DEFAULT 'متوسطة',
  `committee_id` int(11) DEFAULT NULL,
  `id_photo_attachment` varchar(255) DEFAULT NULL,
  `official_docs_attachment` varchar(255) DEFAULT NULL,
  `medical_report_attachment` varchar(255) DEFAULT NULL,
  `income_proof_attachment` varchar(255) DEFAULT NULL,
  `other_attachments` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `beneficiaries`
--

INSERT INTO `beneficiaries` (`id`, `national_id`, `phone_number`, `gender`, `date_of_birth`, `full_name`, `nationality`, `birth_date`, `income_level`, `marital_status`, `address`, `family_members_count`, `created_at`, `family_file_number`, `family_size`, `children_count`, `elderly_count`, `has_breadwinner`, `relationship_to_head`, `monthly_income`, `income_source`, `employment_status`, `work_type`, `living_standard`, `has_debt`, `health_general`, `has_chronic_disease`, `has_disability`, `disability_type`, `needs_continuous_treatment`, `education_level`, `current_study_status`, `student_count`, `has_learning_difficulties`, `housing_type`, `housing_condition`, `room_count`, `has_basic_services`, `eviction_risk`, `gps_coordinates`, `map_link`, `nearest_landmark`, `status`, `priority_level`, `committee_id`, `id_photo_attachment`, `official_docs_attachment`, `medical_report_attachment`, `income_proof_attachment`, `other_attachments`) VALUES
(1, '3', NULL, NULL, NULL, 'ب', NULL, '0000-00-00', 0.00, '', NULL, 1, '2026-04-17 00:10:39', NULL, 0, 0, 0, 0, NULL, 0.00, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 'جديد', 'متوسطة', 1, NULL, NULL, NULL, NULL, NULL),
(2, '9', NULL, NULL, NULL, 'غ', NULL, '0000-00-00', 0.00, '', NULL, 1, '2026-04-17 00:14:44', NULL, 0, 0, 0, 0, NULL, 0.00, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 'جديد', 'متوسطة', 1, NULL, NULL, NULL, NULL, NULL),
(4, '11', NULL, NULL, '2002-04-29', '1', NULL, '0000-00-00', 0.00, '', NULL, 1, '2026-05-01 08:07:47', NULL, 0, 0, 0, 0, NULL, 0.00, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 'جديد', 'متوسطة', 1, NULL, NULL, NULL, NULL, NULL),
(5, '222', NULL, NULL, '2000-05-01', 'محمد', NULL, '0000-00-00', 0.00, '', NULL, 1, '2026-05-01 08:10:11', NULL, 0, 0, 0, 0, NULL, 0.00, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 'جديد', 'متوسطة', 1, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `committees`
--

CREATE TABLE `committees` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `committees`
--

INSERT INTO `committees` (`id`, `name`) VALUES
(1, 'لجنة حلب'),
(2, 'لجنة درعا'),
(3, 'لجنة إدلب');

-- --------------------------------------------------------

--
-- بنية الجدول `committee_finances`
--

CREATE TABLE `committee_finances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `committee_finances`
--

INSERT INTO `committee_finances` (`id`, `committee_id`, `balance`) VALUES
(1, 0, 80.00);

-- --------------------------------------------------------

--
-- بنية الجدول `donations_history`
--

CREATE TABLE `donations_history` (
  `id` bigint(20) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `donation_type_id` int(11) NOT NULL,
  `amount_or_value` decimal(10,2) DEFAULT NULL COMMENT 'القيمة النقدية أو التقديرية',
  `notes` text DEFAULT NULL,
  `donation_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(10,2) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `donation_source` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_status` varchar(50) DEFAULT 'تم الصرف',
  `delivery_method` varchar(100) DEFAULT NULL,
  `receipt_doc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `donations_history`
--

INSERT INTO `donations_history` (`id`, `national_id`, `committee_id`, `donation_type_id`, `amount_or_value`, `notes`, `donation_date`, `created_at`, `amount`, `user_id`, `donation_source`, `campaign_name`, `donation_status`, `delivery_method`, `receipt_doc`) VALUES
(1, '3', 1, 7, NULL, NULL, '2026-04-18', '2026-04-18 01:23:10', NULL, 4, NULL, NULL, 'تم الصرف', NULL, NULL),
(2, '3', 1, 1, NULL, NULL, '2026-04-18', '2026-04-18 01:51:42', NULL, 4, '', NULL, 'تم الصرف', '', NULL),
(3, '3', 1, 1, NULL, NULL, '2026-04-18', '2026-04-18 02:10:38', NULL, 4, '', NULL, 'تم الصرف', '', NULL),
(4, '3', 1, 1, NULL, NULL, '2026-04-18', '2026-04-18 02:12:33', NULL, 4, '', NULL, 'تم الصرف', '', NULL),
(5, '3', 1, 3, NULL, NULL, '2026-04-18', '2026-04-18 02:12:44', NULL, 4, '', NULL, 'تم الصرف', '', NULL),
(6, '3', 1, 4, NULL, NULL, '2026-04-18', '2026-04-18 11:52:30', NULL, 4, '', NULL, 'تم الصرف', '', NULL),
(7, '222', 1, 13, NULL, NULL, '2026-05-05', '2026-05-05 11:39:31', 44.00, 4, '', NULL, 'تم الصرف', '', NULL),
(8, '222', 1, 13, NULL, NULL, '2026-05-15', '2026-05-15 12:49:45', 3.00, 4, '', NULL, 'تم الصرف', '', NULL),
(9, '222', 0, 13, NULL, 'ا', '2026-05-16', '2026-05-16 11:56:06', 6.00, 4, '', NULL, 'تم الصرف', '', NULL),
(10, '3', 0, 4, NULL, NULL, '2026-05-16', '2026-05-16 13:15:17', 2.00, 6, '', NULL, 'تم الصرف', '', NULL),
(11, '222', 0, 4, NULL, NULL, '2026-05-16', '2026-05-16 13:25:04', 6.00, 6, '', NULL, 'تم الصرف', '', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `donation_types`
--

CREATE TABLE `donation_types` (
  `id` int(11) NOT NULL,
  `category` enum('Cash','In-Kind') NOT NULL COMMENT 'نقدي أو عيني',
  `sub_category` varchar(100) NOT NULL COMMENT 'مثل: مؤقتة، سلة غذائية، الخ',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `donation_types`
--

INSERT INTO `donation_types` (`id`, `category`, `sub_category`, `description`) VALUES
(1, '', 'زكاة مال', NULL),
(2, '', 'صدقة', NULL),
(3, '', 'كفالة شهرية', NULL),
(4, '', 'دعم طارئ', NULL),
(5, '', 'سلة غذائية', NULL),
(6, '', 'كسوة (ملابس)', NULL),
(7, '', 'مستلزمات طبية', NULL),
(8, '', 'مستلزمات تعليمية (قرطاسية)', NULL),
(9, '', 'لحوم أضاحي (موسمي)', NULL),
(10, '', 'استشارة طبية مجانية', NULL),
(11, '', 'جلسة علاج فيزيائي', NULL),
(12, '', 'دعم نفسي وتعليمي', NULL),
(13, '', 'رصيد نقدي عام', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `incoming_donations`
--

CREATE TABLE `incoming_donations` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `deposit_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `donor_name` varchar(255) DEFAULT 'فاعل خير',
  `currency` varchar(10) DEFAULT 'JOD',
  `payment_method` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `incoming_donations`
--

INSERT INTO `incoming_donations` (`id`, `committee_id`, `donation_type_id`, `quantity`, `deposit_date`, `donor_name`, `currency`, `payment_method`, `campaign_name`, `donation_date`, `notes`) VALUES
(24, 1, 5, 4.00, '2026-04-19 12:37:41', 'فاعل خير', 'JOD', NULL, NULL, NULL, NULL),
(26, 1, 13, 1000.00, '2026-04-19 12:38:42', 'فاعل خير', 'JOD', NULL, NULL, NULL, NULL),
(27, 0, 4, 88.00, '2026-05-16 13:09:18', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-16', '');

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_balances`
--

CREATE TABLE `inventory_balances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `inventory_balances`
--

INSERT INTO `inventory_balances` (`id`, `committee_id`, `donation_type_id`, `quantity`) VALUES
(24, 1, 5, 4.00),
(26, 1, 13, 947.00),
(27, 0, 4, 80.00);

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `committee_id`, `username`, `password_hash`, `full_name`, `created_at`, `email`, `phone`, `profile_pic`, `last_login`, `is_admin`) VALUES
(6, 1, 'halab_user', '$2y$10$.GwVRmLurHXCqBX1S/YmJOuIjSOtsf/S07nd4aBr3nuaVrIjOI.rO', 'الموظف اسامة .', '2026-05-16 12:32:46', '', '', NULL, '2026-05-18 16:37:13', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_national_committee` (`national_id`,`committee_id`),
  ADD KEY `idx_full_name` (`full_name`);

--
-- Indexes for table `committees`
--
ALTER TABLE `committees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `committee_finances`
--
ALTER TABLE `committee_finances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `committee_id` (`committee_id`);

--
-- Indexes for table `donations_history`
--
ALTER TABLE `donations_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_committee` (`committee_id`),
  ADD KEY `fk_donation_type` (`donation_type_id`),
  ADD KEY `idx_donation_date` (`donation_date`),
  ADD KEY `fk_beneficiary` (`national_id`);

--
-- Indexes for table `donation_types`
--
ALTER TABLE `donation_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inv` (`committee_id`,`donation_type_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_user_committee` (`committee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `committees`
--
ALTER TABLE `committees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `committee_finances`
--
ALTER TABLE `committee_finances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `donations_history`
--
ALTER TABLE `donations_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `donation_types`
--
ALTER TABLE `donation_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
--
-- Database: `zakat_central_db`
--
CREATE DATABASE IF NOT EXISTS `zakat_central_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `zakat_central_db`;

-- --------------------------------------------------------

--
-- بنية الجدول `committees_registry`
--

CREATE TABLE `committees_registry` (
  `id` int(11) NOT NULL,
  `committee_name` varchar(255) NOT NULL,
  `api_base_url` varchar(255) DEFAULT NULL,
  `api_auth_token` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `committees_registry`
--

INSERT INTO `committees_registry` (`id`, `committee_name`, `api_base_url`, `api_auth_token`, `status`) VALUES
(1, 'لجنة حلب', NULL, NULL, 'Active'),
(2, 'لجنة درعا', NULL, NULL, 'Active'),
(3, 'لجنة إدلب', NULL, NULL, 'Active');

-- --------------------------------------------------------

--
-- بنية الجدول `committee_finances`
--

CREATE TABLE `committee_finances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `committee_finances`
--

INSERT INTO `committee_finances` (`id`, `committee_id`, `balance`) VALUES
(1, 1, 106.98);

-- --------------------------------------------------------

--
-- بنية الجدول `donation_types`
--

CREATE TABLE `donation_types` (
  `id` int(11) NOT NULL,
  `category` varchar(50) NOT NULL,
  `sub_category` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `donation_types`
--

INSERT INTO `donation_types` (`id`, `category`, `sub_category`) VALUES
(10, 'خدمات', 'استشارة طبية مجانية'),
(11, 'خدمات', 'جلسة علاج فيزيائي'),
(12, 'خدمات', 'دعم نفسي وتعليمي'),
(5, 'عيني', 'سلة غذائية'),
(6, 'عيني', 'كسوة (ملابس)'),
(9, 'عيني', 'لحوم أضاحي (موسمي)'),
(8, 'عيني', 'مستلزمات تعليمية (قرطاسية)'),
(7, 'عيني', 'مستلزمات طبية'),
(4, 'نقدي', 'دعم طارئ'),
(1, 'نقدي', 'زكاة مال'),
(2, 'نقدي', 'صدقة'),
(3, 'نقدي', 'كفالة شهرية');

-- --------------------------------------------------------

--
-- بنية الجدول `gateway_audit_logs`
--

CREATE TABLE `gateway_audit_logs` (
  `id` int(11) NOT NULL,
  `requesting_committee_id` int(11) NOT NULL,
  `hashed_national_id` varchar(255) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `duplicate_found` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `global_messages`
--

CREATE TABLE `global_messages` (
  `id` int(11) NOT NULL,
  `sender_global_id` varchar(50) NOT NULL,
  `receiver_global_id` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message_body` text NOT NULL,
  `subject_type` varchar(100) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `global_messages`
--

INSERT INTO `global_messages` (`id`, `sender_global_id`, `receiver_global_id`, `subject`, `message_body`, `subject_type`, `is_read`, `created_at`) VALUES
(1, '2_5', '3_3', 'ن', 'ن', 'إشعار إداري', 0, '2026-05-16 15:13:54'),
(2, '2_5', '0_1', 'ن', 'ن', 'استفسار', 1, '2026-05-16 15:17:04');

-- --------------------------------------------------------

--
-- بنية الجدول `incoming_donations`
--

CREATE TABLE `incoming_donations` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `deposit_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `donor_name` varchar(255) DEFAULT 'فاعل خير',
  `currency` varchar(10) DEFAULT 'JOD',
  `payment_method` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `incoming_donations`
--

INSERT INTO `incoming_donations` (`id`, `committee_id`, `donation_type_id`, `quantity`, `deposit_date`, `donor_name`, `currency`, `payment_method`, `campaign_name`, `donation_date`, `notes`) VALUES
(1, 0, 1, 6.00, '2026-05-16 12:53:41', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-16', ''),
(3, 1, 1, 87.99, '2026-05-16 12:55:56', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-16', ''),
(4, 1, 1, 3.99, '2026-05-16 12:57:48', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-16', ''),
(5, 1, 1, 7.00, '2026-05-16 13:03:21', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-16', ''),
(6, 1, 4, 8.00, '2026-05-16 13:04:18', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-16', ''),
(7, 2, 1, 3.00, '2026-05-25 22:03:21', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-26', '');

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_balances`
--

CREATE TABLE `inventory_balances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `inventory_balances`
--

INSERT INTO `inventory_balances` (`id`, `committee_id`, `donation_type_id`, `quantity`) VALUES
(1, 0, 1, 6.00),
(3, 1, 1, 98.98),
(6, 1, 4, 8.00);

-- --------------------------------------------------------

--
-- بنية الجدول `manager_finance`
--

CREATE TABLE `manager_finance` (
  `id` int(11) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `manager_finance`
--

INSERT INTO `manager_finance` (`id`, `balance`) VALUES
(1, 6.00);

-- --------------------------------------------------------

--
-- بنية الجدول `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message_body` text NOT NULL,
  `subject_type` varchar(100) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `search_audit_logs`
--

CREATE TABLE `search_audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `committee_id` int(11) DEFAULT NULL,
  `searched_national_id` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `was_successful` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `search_audit_logs`
--

INSERT INTO `search_audit_logs` (`id`, `user_id`, `committee_id`, `searched_national_id`, `ip_address`, `was_successful`, `created_at`) VALUES
(1, 1, 0, '222', '::1', 1, '2026-05-15 15:31:19'),
(2, 1, 0, '222', '::1', 1, '2026-05-15 15:31:25'),
(3, 5, 2, '222', '::1', 1, '2026-05-15 15:53:59'),
(4, 5, 2, '222', '::1', 1, '2026-05-15 16:04:34'),
(5, 1, 0, '222', '::1', 1, '2026-05-15 16:07:22'),
(6, 4, 1, '222', '::1', 1, '2026-05-15 16:24:46'),
(7, 1, 0, '222', '::1', 0, '2026-05-15 16:27:22'),
(8, 1, 0, '222', '::1', 1, '2026-05-15 16:28:39'),
(9, 5, 2, '222', '::1', 1, '2026-05-15 16:53:52'),
(10, 4, 1, '222', '::1', 1, '2026-05-16 14:18:13'),
(11, 4, 1, '222', '::1', 1, '2026-05-16 14:25:37'),
(12, 4, 1, '222', '::1', 1, '2026-05-16 14:27:20'),
(13, 4, 1, '222', '::1', 1, '2026-05-16 14:51:11'),
(14, 4, 1, '222', '::1', 1, '2026-05-16 14:54:36'),
(15, 5, 2, '222', '::1', 1, '2026-05-16 14:56:24'),
(16, 5, 2, '222', '::1', 1, '2026-05-16 14:58:13'),
(17, 4, 1, '222', '::1', 1, '2026-05-16 15:24:21'),
(18, 1, 0, '222', '::1', 0, '2026-05-16 15:25:41'),
(19, 6, 1, '222', '::1', 1, '2026-05-16 16:24:31'),
(20, 6, 1, '222', '::1', 1, '2026-05-16 16:25:09'),
(21, 5, 2, '222', '::1', 1, '2026-05-16 16:34:40'),
(22, 5, 2, '222', '::1', 1, '2026-05-18 16:36:55'),
(23, 1, 0, '222', '::1', 1, '2026-05-26 00:49:05');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `is_admin` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `committee_id`, `is_admin`, `last_login`, `email`, `phone`, `profile_pic`) VALUES
(1, 'مدير النظام', 'admin', 'admin123', 0, 1, '2026-05-26 01:03:31', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `user_settings`
--

CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `email_notif` tinyint(1) DEFAULT 1,
  `system_notif` tinyint(1) DEFAULT 1,
  `language` varchar(10) DEFAULT 'ar',
  `date_format` varchar(20) DEFAULT 'gregorian',
  `two_factor_auth` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `committees_registry`
--
ALTER TABLE `committees_registry`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `committee_finances`
--
ALTER TABLE `committee_finances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `committee_id` (`committee_id`);

--
-- Indexes for table `donation_types`
--
ALTER TABLE `donation_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cat_sub` (`category`,`sub_category`);

--
-- Indexes for table `gateway_audit_logs`
--
ALTER TABLE `gateway_audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `global_messages`
--
ALTER TABLE `global_messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inv` (`committee_id`,`donation_type_id`);

--
-- Indexes for table `manager_finance`
--
ALTER TABLE `manager_finance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `search_audit_logs`
--
ALTER TABLE `search_audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `committees_registry`
--
ALTER TABLE `committees_registry`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `committee_finances`
--
ALTER TABLE `committee_finances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `donation_types`
--
ALTER TABLE `donation_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `gateway_audit_logs`
--
ALTER TABLE `gateway_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `global_messages`
--
ALTER TABLE `global_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `manager_finance`
--
ALTER TABLE `manager_finance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_audit_logs`
--
ALTER TABLE `search_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
--
-- Database: `zakat_daraa_db`
--
CREATE DATABASE IF NOT EXISTS `zakat_daraa_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `zakat_daraa_db`;

-- --------------------------------------------------------

--
-- بنية الجدول `beneficiaries`
--

CREATE TABLE `beneficiaries` (
  `id` int(11) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `gender` enum('ذكر','أنثى') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `income_level` decimal(10,2) DEFAULT 0.00,
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `family_members_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `family_file_number` varchar(50) DEFAULT NULL,
  `family_size` int(11) DEFAULT 0,
  `children_count` int(11) DEFAULT 0,
  `elderly_count` int(11) DEFAULT 0,
  `has_breadwinner` tinyint(1) DEFAULT 0,
  `relationship_to_head` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT 0.00,
  `income_source` varchar(255) DEFAULT NULL,
  `employment_status` varchar(100) DEFAULT NULL,
  `work_type` varchar(100) DEFAULT NULL,
  `living_standard` varchar(100) DEFAULT NULL,
  `has_debt` tinyint(1) DEFAULT 0,
  `health_general` text DEFAULT NULL,
  `has_chronic_disease` tinyint(1) DEFAULT 0,
  `has_disability` tinyint(1) DEFAULT 0,
  `disability_type` varchar(255) DEFAULT NULL,
  `needs_continuous_treatment` tinyint(1) DEFAULT 0,
  `education_level` varchar(100) DEFAULT NULL,
  `current_study_status` varchar(100) DEFAULT NULL,
  `student_count` int(11) DEFAULT 0,
  `has_learning_difficulties` tinyint(1) DEFAULT 0,
  `housing_type` varchar(100) DEFAULT NULL,
  `housing_condition` varchar(100) DEFAULT NULL,
  `room_count` int(11) DEFAULT 0,
  `has_basic_services` tinyint(1) DEFAULT 0,
  `eviction_risk` tinyint(1) DEFAULT 0,
  `gps_coordinates` varchar(255) DEFAULT NULL,
  `map_link` varchar(500) DEFAULT NULL,
  `nearest_landmark` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'جديد',
  `priority_level` varchar(50) DEFAULT 'متوسطة',
  `committee_id` int(11) DEFAULT NULL,
  `id_photo_attachment` varchar(255) DEFAULT NULL,
  `official_docs_attachment` varchar(255) DEFAULT NULL,
  `medical_report_attachment` varchar(255) DEFAULT NULL,
  `income_proof_attachment` varchar(255) DEFAULT NULL,
  `other_attachments` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `beneficiaries`
--

INSERT INTO `beneficiaries` (`id`, `national_id`, `phone_number`, `gender`, `date_of_birth`, `full_name`, `nationality`, `birth_date`, `income_level`, `marital_status`, `address`, `family_members_count`, `created_at`, `family_file_number`, `family_size`, `children_count`, `elderly_count`, `has_breadwinner`, `relationship_to_head`, `monthly_income`, `income_source`, `employment_status`, `work_type`, `living_standard`, `has_debt`, `health_general`, `has_chronic_disease`, `has_disability`, `disability_type`, `needs_continuous_treatment`, `education_level`, `current_study_status`, `student_count`, `has_learning_difficulties`, `housing_type`, `housing_condition`, `room_count`, `has_basic_services`, `eviction_risk`, `gps_coordinates`, `map_link`, `nearest_landmark`, `status`, `priority_level`, `committee_id`, `id_photo_attachment`, `official_docs_attachment`, `medical_report_attachment`, `income_proof_attachment`, `other_attachments`) VALUES
(3, '0', NULL, 'ذكر', NULL, 'ن', NULL, '0000-00-00', 0.00, '', NULL, 1, '2026-04-17 00:40:42', NULL, 0, 0, 0, 0, NULL, 0.00, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 'جديد', 'متوسطة', 2, NULL, NULL, NULL, NULL, NULL),
(10, '222', NULL, 'ذكر', '2000-05-01', 'محمد', NULL, '0000-00-00', 0.00, '', NULL, 1, '2026-05-07 10:52:13', NULL, 0, 0, 0, 0, NULL, 0.00, NULL, NULL, NULL, NULL, 0, NULL, 0, 0, NULL, 0, NULL, NULL, 0, 0, NULL, NULL, 0, 0, 0, NULL, NULL, NULL, 'جديد', 'متوسطة', 2, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `committees`
--

CREATE TABLE `committees` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `committees`
--

INSERT INTO `committees` (`id`, `name`) VALUES
(1, 'لجنة حلب'),
(2, 'لجنة درعا'),
(3, 'لجنة إدلب');

-- --------------------------------------------------------

--
-- بنية الجدول `committee_finances`
--

CREATE TABLE `committee_finances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `committee_finances`
--

INSERT INTO `committee_finances` (`id`, `committee_id`, `balance`) VALUES
(1, 0, 4.00);

-- --------------------------------------------------------

--
-- بنية الجدول `donations_history`
--

CREATE TABLE `donations_history` (
  `id` bigint(20) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `donation_type_id` int(11) NOT NULL,
  `amount_or_value` decimal(10,2) DEFAULT NULL COMMENT 'القيمة النقدية أو التقديرية',
  `notes` text DEFAULT NULL,
  `donation_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(10,2) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `donation_source` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_status` varchar(50) DEFAULT 'تم الصرف',
  `delivery_method` varchar(100) DEFAULT NULL,
  `receipt_doc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `donation_types`
--

CREATE TABLE `donation_types` (
  `id` int(11) NOT NULL,
  `category` enum('Cash','In-Kind') NOT NULL COMMENT 'نقدي أو عيني',
  `sub_category` varchar(100) NOT NULL COMMENT 'مثل: مؤقتة، سلة غذائية، الخ',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `donation_types`
--

INSERT INTO `donation_types` (`id`, `category`, `sub_category`, `description`) VALUES
(1, '', 'زكاة مال', NULL),
(2, '', 'صدقة', NULL),
(3, '', 'كفالة شهرية', NULL),
(4, '', 'دعم طارئ', NULL),
(5, '', 'سلة غذائية', NULL),
(6, '', 'كسوة (ملابس)', NULL),
(7, '', 'مستلزمات طبية', NULL),
(8, '', 'مستلزمات تعليمية (قرطاسية)', NULL),
(9, '', 'لحوم أضاحي (موسمي)', NULL),
(10, '', 'استشارة طبية مجانية', NULL),
(11, '', 'جلسة علاج فيزيائي', NULL),
(12, '', 'دعم نفسي وتعليمي', NULL),
(13, '', 'رصيد نقدي عام', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `incoming_donations`
--

CREATE TABLE `incoming_donations` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `deposit_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `donor_name` varchar(255) DEFAULT 'فاعل خير',
  `currency` varchar(10) DEFAULT 'JOD',
  `payment_method` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `incoming_donations`
--

INSERT INTO `incoming_donations` (`id`, `committee_id`, `donation_type_id`, `quantity`, `deposit_date`, `donor_name`, `currency`, `payment_method`, `campaign_name`, `donation_date`, `notes`) VALUES
(28, 2, 13, 999.99, '2026-05-01 08:15:16', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-01', ''),
(29, 0, 1, 1.00, '2026-05-25 21:56:20', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-25', ''),
(30, 0, 1, 3.00, '2026-05-25 22:03:21', 'فاعل خير', 'JOD', 'نقدي (كاش)', '', '2026-05-26', '');

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_balances`
--

CREATE TABLE `inventory_balances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `inventory_balances`
--

INSERT INTO `inventory_balances` (`id`, `committee_id`, `donation_type_id`, `quantity`) VALUES
(28, 2, 13, 999.99),
(29, 0, 1, 4.00);

-- --------------------------------------------------------

--
-- بنية الجدول `manager_finance`
--

CREATE TABLE `manager_finance` (
  `id` int(11) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `manager_finance`
--

INSERT INTO `manager_finance` (`id`, `balance`) VALUES
(1, 0.00);

-- --------------------------------------------------------

--
-- بنية الجدول `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message_body` text NOT NULL,
  `subject_type` varchar(100) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `subject`, `message_body`, `subject_type`, `is_read`, `created_at`) VALUES
(1, 5, 6, 'okjk', 'ب', 'أخرى', 0, '2026-05-16 15:09:29');

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `committee_id`, `username`, `password_hash`, `full_name`, `created_at`, `email`, `phone`, `profile_pic`, `last_login`, `is_admin`) VALUES
(5, 2, 'daraa_user', '$2y$10$WlmF/xjtkoIk0tp.h0nzJ.e4NEFFc3zPTk./aiCc7kNd6bgnfF.GG', 'موظف لجنة درعا', '2026-04-17 00:34:05', NULL, NULL, NULL, '2026-05-26 01:21:24', 0),
(8, 0, 'admin', 'admin123', 'مدير النظام', '2026-05-25 21:56:24', NULL, NULL, NULL, '2026-05-26 01:40:53', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_national_committee` (`national_id`,`committee_id`),
  ADD KEY `idx_full_name` (`full_name`);

--
-- Indexes for table `committees`
--
ALTER TABLE `committees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `committee_finances`
--
ALTER TABLE `committee_finances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `committee_id` (`committee_id`);

--
-- Indexes for table `donations_history`
--
ALTER TABLE `donations_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_committee` (`committee_id`),
  ADD KEY `fk_donation_type` (`donation_type_id`),
  ADD KEY `idx_donation_date` (`donation_date`),
  ADD KEY `fk_beneficiary` (`national_id`);

--
-- Indexes for table `donation_types`
--
ALTER TABLE `donation_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inv` (`committee_id`,`donation_type_id`);

--
-- Indexes for table `manager_finance`
--
ALTER TABLE `manager_finance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_user_committee` (`committee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `committees`
--
ALTER TABLE `committees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `committee_finances`
--
ALTER TABLE `committee_finances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `donations_history`
--
ALTER TABLE `donations_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donation_types`
--
ALTER TABLE `donation_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `manager_finance`
--
ALTER TABLE `manager_finance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
--
-- Database: `zakat_idlib_db`
--
CREATE DATABASE IF NOT EXISTS `zakat_idlib_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `zakat_idlib_db`;

-- --------------------------------------------------------

--
-- بنية الجدول `beneficiaries`
--

CREATE TABLE `beneficiaries` (
  `id` int(11) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `gender` enum('ذكر','أنثى') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `income_level` decimal(10,2) DEFAULT 0.00,
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `family_members_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `family_file_number` varchar(50) DEFAULT NULL,
  `family_size` int(11) DEFAULT 0,
  `children_count` int(11) DEFAULT 0,
  `elderly_count` int(11) DEFAULT 0,
  `has_breadwinner` tinyint(1) DEFAULT 0,
  `relationship_to_head` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT 0.00,
  `income_source` varchar(255) DEFAULT NULL,
  `employment_status` varchar(100) DEFAULT NULL,
  `work_type` varchar(100) DEFAULT NULL,
  `living_standard` varchar(100) DEFAULT NULL,
  `has_debt` tinyint(1) DEFAULT 0,
  `health_general` text DEFAULT NULL,
  `has_chronic_disease` tinyint(1) DEFAULT 0,
  `has_disability` tinyint(1) DEFAULT 0,
  `disability_type` varchar(255) DEFAULT NULL,
  `needs_continuous_treatment` tinyint(1) DEFAULT 0,
  `education_level` varchar(100) DEFAULT NULL,
  `current_study_status` varchar(100) DEFAULT NULL,
  `student_count` int(11) DEFAULT 0,
  `has_learning_difficulties` tinyint(1) DEFAULT 0,
  `housing_type` varchar(100) DEFAULT NULL,
  `housing_condition` varchar(100) DEFAULT NULL,
  `room_count` int(11) DEFAULT 0,
  `has_basic_services` tinyint(1) DEFAULT 0,
  `eviction_risk` tinyint(1) DEFAULT 0,
  `gps_coordinates` varchar(255) DEFAULT NULL,
  `map_link` varchar(500) DEFAULT NULL,
  `nearest_landmark` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'جديد',
  `priority_level` varchar(50) DEFAULT 'متوسطة',
  `committee_id` int(11) DEFAULT NULL,
  `id_photo_attachment` varchar(255) DEFAULT NULL,
  `official_docs_attachment` varchar(255) DEFAULT NULL,
  `medical_report_attachment` varchar(255) DEFAULT NULL,
  `income_proof_attachment` varchar(255) DEFAULT NULL,
  `other_attachments` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `committees`
--

CREATE TABLE `committees` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `committees`
--

INSERT INTO `committees` (`id`, `name`) VALUES
(1, 'لجنة حلب'),
(2, 'لجنة درعا'),
(3, 'لجنة إدلب');

-- --------------------------------------------------------

--
-- بنية الجدول `committee_finances`
--

CREATE TABLE `committee_finances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `committee_finances`
--

INSERT INTO `committee_finances` (`id`, `committee_id`, `balance`) VALUES
(1, 0, 0.00);

-- --------------------------------------------------------

--
-- بنية الجدول `donations_history`
--

CREATE TABLE `donations_history` (
  `id` bigint(20) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `donation_type_id` int(11) NOT NULL,
  `amount_or_value` decimal(10,2) DEFAULT NULL COMMENT 'القيمة النقدية أو التقديرية',
  `notes` text DEFAULT NULL,
  `donation_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(10,2) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `donation_source` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_status` varchar(50) DEFAULT 'تم الصرف',
  `delivery_method` varchar(100) DEFAULT NULL,
  `receipt_doc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `donation_types`
--

CREATE TABLE `donation_types` (
  `id` int(11) NOT NULL,
  `category` enum('Cash','In-Kind') NOT NULL COMMENT 'نقدي أو عيني',
  `sub_category` varchar(100) NOT NULL COMMENT 'مثل: مؤقتة، سلة غذائية، الخ',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `donation_types`
--

INSERT INTO `donation_types` (`id`, `category`, `sub_category`, `description`) VALUES
(1, '', 'زكاة مال', NULL),
(2, '', 'صدقة', NULL),
(3, '', 'كفالة شهرية', NULL),
(4, '', 'دعم طارئ', NULL),
(5, '', 'سلة غذائية', NULL),
(6, '', 'كسوة (ملابس)', NULL),
(7, '', 'مستلزمات طبية', NULL),
(8, '', 'مستلزمات تعليمية (قرطاسية)', NULL),
(9, '', 'لحوم أضاحي (موسمي)', NULL),
(10, '', 'استشارة طبية مجانية', NULL),
(11, '', 'جلسة علاج فيزيائي', NULL),
(12, '', 'دعم نفسي وتعليمي', NULL),
(13, '', 'رصيد نقدي عام', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `incoming_donations`
--

CREATE TABLE `incoming_donations` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `deposit_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `donor_name` varchar(255) DEFAULT 'فاعل خير',
  `currency` varchar(10) DEFAULT 'JOD',
  `payment_method` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_balances`
--

CREATE TABLE `inventory_balances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT current_timestamp(),
  `is_admin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`id`, `committee_id`, `username`, `password_hash`, `full_name`, `created_at`, `email`, `phone`, `profile_pic`, `last_login`, `is_admin`) VALUES
(6, 3, 'idleb_user', '$2y$10$NPuZbnhiazdJKwOe1LbPCOEoBn/wHPxUMaadfCJ.dJhrzXvMG9eYu', 'موظف لجنة إدلب', '2026-04-17 00:34:05', NULL, NULL, NULL, '2026-05-26 00:37:22', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_national_committee` (`national_id`,`committee_id`),
  ADD KEY `idx_full_name` (`full_name`);

--
-- Indexes for table `committees`
--
ALTER TABLE `committees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `committee_finances`
--
ALTER TABLE `committee_finances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `committee_id` (`committee_id`);

--
-- Indexes for table `donations_history`
--
ALTER TABLE `donations_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_committee` (`committee_id`),
  ADD KEY `fk_donation_type` (`donation_type_id`),
  ADD KEY `idx_donation_date` (`donation_date`),
  ADD KEY `fk_beneficiary` (`national_id`);

--
-- Indexes for table `donation_types`
--
ALTER TABLE `donation_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inv` (`committee_id`,`donation_type_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_user_committee` (`committee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `committees`
--
ALTER TABLE `committees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `committee_finances`
--
ALTER TABLE `committee_finances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `donations_history`
--
ALTER TABLE `donations_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donation_types`
--
ALTER TABLE `donation_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
--
-- Database: `zakat_node_4_db`
--
CREATE DATABASE IF NOT EXISTS `zakat_node_4_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `zakat_node_4_db`;

-- --------------------------------------------------------

--
-- بنية الجدول `beneficiaries`
--

CREATE TABLE `beneficiaries` (
  `id` int(11) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `gender` enum('ذكر','أنثى') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `birth_date` date NOT NULL,
  `income_level` decimal(10,2) DEFAULT 0.00,
  `marital_status` enum('Single','Married','Divorced','Widowed') NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `family_members_count` int(11) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `family_file_number` varchar(50) DEFAULT NULL,
  `family_size` int(11) DEFAULT 0,
  `children_count` int(11) DEFAULT 0,
  `elderly_count` int(11) DEFAULT 0,
  `has_breadwinner` tinyint(1) DEFAULT 0,
  `relationship_to_head` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT 0.00,
  `income_source` varchar(255) DEFAULT NULL,
  `employment_status` varchar(100) DEFAULT NULL,
  `work_type` varchar(100) DEFAULT NULL,
  `living_standard` varchar(100) DEFAULT NULL,
  `has_debt` tinyint(1) DEFAULT 0,
  `health_general` text DEFAULT NULL,
  `has_chronic_disease` tinyint(1) DEFAULT 0,
  `has_disability` tinyint(1) DEFAULT 0,
  `disability_type` varchar(255) DEFAULT NULL,
  `needs_continuous_treatment` tinyint(1) DEFAULT 0,
  `education_level` varchar(100) DEFAULT NULL,
  `current_study_status` varchar(100) DEFAULT NULL,
  `student_count` int(11) DEFAULT 0,
  `has_learning_difficulties` tinyint(1) DEFAULT 0,
  `housing_type` varchar(100) DEFAULT NULL,
  `housing_condition` varchar(100) DEFAULT NULL,
  `room_count` int(11) DEFAULT 0,
  `has_basic_services` tinyint(1) DEFAULT 0,
  `eviction_risk` tinyint(1) DEFAULT 0,
  `gps_coordinates` varchar(255) DEFAULT NULL,
  `map_link` varchar(500) DEFAULT NULL,
  `nearest_landmark` varchar(255) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'جديد',
  `priority_level` varchar(50) DEFAULT 'متوسطة',
  `committee_id` int(11) DEFAULT NULL,
  `id_photo_attachment` varchar(255) DEFAULT NULL,
  `official_docs_attachment` varchar(255) DEFAULT NULL,
  `medical_report_attachment` varchar(255) DEFAULT NULL,
  `income_proof_attachment` varchar(255) DEFAULT NULL,
  `other_attachments` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `committee_finances`
--

CREATE TABLE `committee_finances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `donations_history`
--

CREATE TABLE `donations_history` (
  `id` bigint(20) NOT NULL,
  `national_id` varchar(20) NOT NULL,
  `committee_id` int(11) NOT NULL,
  `donation_type_id` int(11) NOT NULL,
  `amount_or_value` decimal(10,2) DEFAULT NULL COMMENT 'القيمة النقدية أو التقديرية',
  `notes` text DEFAULT NULL,
  `donation_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `amount` decimal(10,2) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `donation_source` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_status` varchar(50) DEFAULT 'تم الصرف',
  `delivery_method` varchar(100) DEFAULT NULL,
  `receipt_doc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `incoming_donations`
--

CREATE TABLE `incoming_donations` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `deposit_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `donor_name` varchar(255) DEFAULT 'فاعل خير',
  `currency` varchar(10) DEFAULT 'JOD',
  `payment_method` varchar(100) DEFAULT NULL,
  `campaign_name` varchar(255) DEFAULT NULL,
  `donation_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_balances`
--

CREATE TABLE `inventory_balances` (
  `id` int(11) NOT NULL,
  `committee_id` int(11) NOT NULL DEFAULT 0,
  `donation_type_id` int(11) NOT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_national_committee` (`national_id`,`committee_id`),
  ADD KEY `idx_full_name` (`full_name`);

--
-- Indexes for table `committee_finances`
--
ALTER TABLE `committee_finances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `committee_id` (`committee_id`);

--
-- Indexes for table `donations_history`
--
ALTER TABLE `donations_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_committee` (`committee_id`),
  ADD KEY `fk_donation_type` (`donation_type_id`),
  ADD KEY `idx_donation_date` (`donation_date`),
  ADD KEY `fk_beneficiary` (`national_id`);

--
-- Indexes for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_inv` (`committee_id`,`donation_type_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `committee_finances`
--
ALTER TABLE `committee_finances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donations_history`
--
ALTER TABLE `donations_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incoming_donations`
--
ALTER TABLE `incoming_donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_balances`
--
ALTER TABLE `inventory_balances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
