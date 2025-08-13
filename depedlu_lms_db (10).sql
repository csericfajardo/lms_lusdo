-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 12, 2025 at 10:13 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `depedlu_lms_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `cto_earnings`
--

CREATE TABLE `cto_earnings` (
  `cto_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `days_earned` decimal(5,2) NOT NULL,
  `days_used` decimal(5,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(5,2) NOT NULL DEFAULT 0.00,
  `earned_at` date NOT NULL,
  `expires_at` date NOT NULL,
  `source` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `cto_earnings`
--
DELIMITER $$
CREATE TRIGGER `trg_cto_before_insert` BEFORE INSERT ON `cto_earnings` FOR EACH ROW BEGIN
  SET NEW.balance = NEW.days_earned - NEW.days_used;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_cto_before_update` BEFORE UPDATE ON `cto_earnings` FOR EACH ROW BEGIN
  SET NEW.balance = NEW.days_earned - NEW.days_used;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `employee_number` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `employment_type` enum('Teaching','Non-Teaching') NOT NULL,
  `position` varchar(100) NOT NULL,
  `office` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `date_hired` date NOT NULL,
  `status` enum('Active','Retired','Separated','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `employee_number`, `first_name`, `middle_name`, `last_name`, `employment_type`, `position`, `office`, `email`, `date_hired`, `status`, `created_at`, `updated_at`) VALUES
(33, '454214', 'ERIC', 'ARCIAGA', 'FAJARDO', 'Non-Teaching', 'ADAS III', 'SUDIPEN DISTRICT', 'csericfajardo@gmail.com', '2025-08-12', 'Active', '2025-08-12 05:55:56', '2025-08-12 05:55:56');

-- --------------------------------------------------------

--
-- Table structure for table `leave_applications`
--

CREATE TABLE `leave_applications` (
  `application_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `number_of_days` decimal(5,2) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') DEFAULT 'Pending',
  `filed_by` int(11) NOT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_applications`
--

INSERT INTO `leave_applications` (`application_id`, `employee_id`, `leave_type_id`, `number_of_days`, `status`, `filed_by`, `approved_by`, `created_at`, `updated_at`) VALUES
(36, 33, 1, 5.00, 'Approved', 12, NULL, '2025-08-12 06:35:18', '2025-08-12 06:44:24'),
(37, 33, 1, 5.00, 'Approved', 46, NULL, '2025-08-12 07:29:05', '2025-08-12 07:30:04');

-- --------------------------------------------------------

--
-- Table structure for table `leave_application_details`
--

CREATE TABLE `leave_application_details` (
  `detail_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_application_details`
--

INSERT INTO `leave_application_details` (`detail_id`, `application_id`, `field_name`, `field_value`) VALUES
(123, 36, 'date_from', '2025-08-12'),
(124, 36, 'date_to', '2025-08-23'),
(125, 36, 'place_spent', 'local'),
(129, 37, 'date_from', '2025-08-12'),
(130, 37, 'date_to', '2025-08-13'),
(131, 37, 'place_spent', 'local');

-- --------------------------------------------------------

--
-- Table structure for table `leave_credits`
--

CREATE TABLE `leave_credits` (
  `credit_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `total_credits` decimal(5,2) DEFAULT 0.00,
  `used_credits` decimal(5,2) DEFAULT 0.00,
  `balance_credits` decimal(5,2) GENERATED ALWAYS AS (`total_credits` - `used_credits`) STORED,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_credits`
--

INSERT INTO `leave_credits` (`credit_id`, `employee_id`, `leave_type_id`, `total_credits`, `used_credits`, `updated_at`) VALUES
(72, 33, 1, 20.00, 10.00, '2025-08-12 07:30:04'),
(73, 33, 2, 0.00, 0.00, '2025-08-12 05:55:56'),
(74, 33, 4, 10.00, 0.00, '2025-08-12 06:07:15');

-- --------------------------------------------------------

--
-- Table structure for table `leave_credit_logs`
--

CREATE TABLE `leave_credit_logs` (
  `log_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `leave_type_id` int(11) NOT NULL,
  `added_credits` decimal(5,2) NOT NULL,
  `reason` text NOT NULL,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_credit_logs`
--

INSERT INTO `leave_credit_logs` (`log_id`, `employee_id`, `leave_type_id`, `added_credits`, `reason`, `added_by`, `created_at`) VALUES
(81, 33, 4, 10.00, 'Initial credit setup', 12, '2025-08-12 06:07:15'),
(82, 33, 1, 20.00, 'initial', 12, '2025-08-12 06:30:31');

-- --------------------------------------------------------

--
-- Table structure for table `leave_types`
--

CREATE TABLE `leave_types` (
  `leave_type_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `applicable_to` enum('teaching','non_teaching','both') DEFAULT 'both',
  `required_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_fields`)),
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_types`
--

INSERT INTO `leave_types` (`leave_type_id`, `name`, `description`, `applicable_to`, `required_fields`, `status`) VALUES
(1, 'Vacation Leave', 'Leave for personal reasons within or outside the Philippines.', 'both', '[\"date_from\", \"date_to\", \"place_spent\"]', 'active'),
(2, 'Sick Leave', 'Leave of absence granted due to sickness or injury.', 'both', '[\"date_from\", \"date_to\", \"illness_details\", \"medical_certificate\"]', 'active'),
(3, 'Maternity Leave', 'Leave granted to female employees in connection with pregnancy.', 'teaching', '[\"date_from\", \"date_to\", \"expected_delivery_date\", \"actual_delivery_date\", \"medical_certificate\"]', 'active'),
(4, 'Paternity Leave', 'Leave granted to married male employees for childbirth of spouse.', 'teaching', '[\"date_from\", \"date_to\", \"wife_name\", \"wife_delivery_date\", \"marriage_certificate\", \"birth_certificate\"]', 'active'),
(5, 'Study Leave', 'Leave for review and examination for bar or board exams or for completing a master\'s degree.', 'teaching', '[\"date_from\", \"date_to\", \"purpose\", \"school_name\", \"admission_slip\"]', 'active'),
(6, 'Special Privilege Leave', 'Leave of absence for personal milestones or emergencies.', 'both', '[\"date_from\", \"date_to\", \"reason\", \"proof_if_required\"]', 'active'),
(7, 'Special Leave for Women', 'Special leave for women due to gynecological disorders.', 'teaching', '[\"date_from\", \"date_to\", \"gynecological_nature\", \"medical_certificate\"]', 'active'),
(8, 'Rehabilitation Leave', 'Leave granted for recovery from occupational injury.', 'both', '[\"date_from\", \"date_to\", \"cause_of_injury\", \"medical_certificate\", \"injury_report\"]', 'active'),
(9, 'Adoption Leave', 'Leave granted for lawful adoption.', 'both', '[\"date_from\", \"date_to\", \"adoption_decree\"]', 'active'),
(10, 'Terminal Leave', 'Leave granted upon resignation, retirement, or separation.', 'both', '[\"effective_date\", \"number_of_days\", \"retirement_papers\", \"clearance\"]', 'active'),
(12, 'Compensatory Time‑Off', 'Leave earned in lieu of authorized overtime services; taken as days off.', 'both', '[\"source\",\"earned_at\",\"expires_at\",\"date_from\",\"date_to\",\"number_of_days\"]', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `status` enum('Unread','Read') DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `message`, `status`, `created_at`) VALUES
(10, 44, 'Your leave application #36 for Vacation Leave (5.00 day/s) has been submitted. Status: Approved.', 'Unread', '2025-08-12 06:35:18'),
(11, 12, 'You changed leave application #36 for 454214 – ERIC FAJARDO — Vacation Leave (5.00 day/s): Approved → Pending.', 'Unread', '2025-08-12 06:42:07'),
(12, 44, 'Your leave application #36 — Vacation Leave (5.00 day/s) changed status: Approved → Pending.', 'Unread', '2025-08-12 06:42:07'),
(13, 12, 'You changed leave application #36 for 454214 – ERIC FAJARDO — Vacation Leave (5.00 day/s): Pending → Approved.', 'Unread', '2025-08-12 06:44:24'),
(14, 44, 'Your leave application #36 — Vacation Leave (5.00 day/s) changed status: Pending → Approved.', 'Unread', '2025-08-12 06:44:24'),
(15, 44, 'Your leave application #37 for Vacation Leave (5.00 day/s) has been submitted. Status: Pending.', 'Unread', '2025-08-12 07:29:05'),
(16, 12, 'New/updated leave application #37 filed for 454214 – ERIC FAJARDO — Vacation Leave (5.00 day/s). Status: Pending.', 'Unread', '2025-08-12 07:29:05'),
(17, 46, 'You changed leave application #37 for 454214 – ERIC FAJARDO — Vacation Leave (5.00 day/s): Pending → Approved.', 'Unread', '2025-08-12 07:30:04'),
(18, 44, 'Your leave application #37 — Vacation Leave (5.00 day/s) changed status: Pending → Approved.', 'Unread', '2025-08-12 07:30:04');

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL,
  `action` varchar(150) NOT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` enum('super_admin','admin','hr','employee') NOT NULL,
  `employee_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `email`, `role`, `employee_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', '$2y$10$vmVsUvia6Dpy74a1CZly2.FgfuLO7E7o2ooVDIkGbLUQHvWnT02Gi', 'superadmin@depedlu.gov.ph', 'super_admin', NULL, 'active', '2025-07-17 12:07:04', '2025-07-17 12:07:04'),
(10, 'admin', '$2y$10$QXV/oz.7x392Uks.7lYQCeKOwP8S0tMlY.dTvHuHm1SQ8KcaSH46.', 'admin@deped.gov.ph', 'admin', NULL, 'active', '2025-07-17 17:11:05', '2025-07-19 04:00:01'),
(12, 'hr', '$2y$10$YEfgG10W7aCGL95Y7O8o1eygSQTPwYrjUQMmWaI4lxfAEIG./mTWi', 'hr@deped.gov.ph', 'hr', NULL, 'active', '2025-07-17 17:30:32', '2025-07-17 17:30:32'),
(13, 'admin1', '$2y$10$xZ5yP.VEYDVNkcYdOhpdtubwLOQa.FYp.WQ5a.x8IXasDIBE4Qgc2', 'admin1@deped.gov.ph', 'admin', NULL, 'active', '2025-07-19 04:19:10', '2025-07-19 04:19:10'),
(44, '454214', '$2y$10$wTMWOhSEL7j35vvlZex5KeINPOpFaQ0krlvmJHf19Sw2iPZ175Zd.', 'csericfajardo@gmail.com', '', 33, 'active', '2025-08-12 05:55:56', '2025-08-12 05:55:56'),
(46, 'hr2', '$2y$10$/5I5Gtryv9pcFj6dlkt//eKXBJIr.qBeAMdJ/HDCgsSMbSObwvEg.', 'hr2@deped.gov.ph', 'hr', NULL, 'active', '2025-08-12 07:27:51', '2025-08-12 07:27:51');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cto_earnings`
--
ALTER TABLE `cto_earnings`
  ADD PRIMARY KEY (`cto_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_number` (`employee_number`);

--
-- Indexes for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD PRIMARY KEY (`application_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `filed_by` (`filed_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `leave_application_details`
--
ALTER TABLE `leave_application_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `application_id` (`application_id`);

--
-- Indexes for table `leave_credits`
--
ALTER TABLE `leave_credits`
  ADD PRIMARY KEY (`credit_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`);

--
-- Indexes for table `leave_credit_logs`
--
ALTER TABLE `leave_credit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `leave_type_id` (`leave_type_id`),
  ADD KEY `added_by` (`added_by`);

--
-- Indexes for table `leave_types`
--
ALTER TABLE `leave_types`
  ADD PRIMARY KEY (`leave_type_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cto_earnings`
--
ALTER TABLE `cto_earnings`
  MODIFY `cto_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `leave_applications`
--
ALTER TABLE `leave_applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `leave_application_details`
--
ALTER TABLE `leave_application_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=132;

--
-- AUTO_INCREMENT for table `leave_credits`
--
ALTER TABLE `leave_credits`
  MODIFY `credit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `leave_credit_logs`
--
ALTER TABLE `leave_credit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=83;

--
-- AUTO_INCREMENT for table `leave_types`
--
ALTER TABLE `leave_types`
  MODIFY `leave_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cto_earnings`
--
ALTER TABLE `cto_earnings`
  ADD CONSTRAINT `cto_earnings_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_applications`
--
ALTER TABLE `leave_applications`
  ADD CONSTRAINT `leave_applications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `leave_applications_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`),
  ADD CONSTRAINT `leave_applications_ibfk_3` FOREIGN KEY (`filed_by`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `leave_applications_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `leave_application_details`
--
ALTER TABLE `leave_application_details`
  ADD CONSTRAINT `leave_application_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `leave_applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `leave_credits`
--
ALTER TABLE `leave_credits`
  ADD CONSTRAINT `leave_credits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `leave_credits_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`);

--
-- Constraints for table `leave_credit_logs`
--
ALTER TABLE `leave_credit_logs`
  ADD CONSTRAINT `leave_credit_logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `leave_credit_logs_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`),
  ADD CONSTRAINT `leave_credit_logs_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
