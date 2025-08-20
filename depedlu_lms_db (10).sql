-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: depedlu_lms_db
-- ------------------------------------------------------
-- Server version	8.4.3

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `cto_earnings`
--

DROP TABLE IF EXISTS `cto_earnings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cto_earnings` (
  `cto_id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `days_earned` decimal(5,2) NOT NULL,
  `days_used` decimal(5,2) NOT NULL DEFAULT '0.00',
  `balance` decimal(5,2) NOT NULL DEFAULT '0.00',
  `earned_at` date NOT NULL,
  `expires_at` date NOT NULL,
  `source` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`cto_id`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `cto_earnings_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cto_earnings`
--

LOCK TABLES `cto_earnings` WRITE;
/*!40000 ALTER TABLE `cto_earnings` DISABLE KEYS */;
/*!40000 ALTER TABLE `cto_earnings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `employee_id` int NOT NULL AUTO_INCREMENT,
  `employee_number` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `middle_name` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `employment_type` enum('Teaching','Non-Teaching') COLLATE utf8mb4_general_ci NOT NULL,
  `position` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `office` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `date_hired` date NOT NULL,
  `status` enum('Active','Retired','Separated','Inactive') COLLATE utf8mb4_general_ci DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`employee_id`),
  UNIQUE KEY `employee_number` (`employee_number`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (37,'46541','josie','marcos','guzman','Non-Teaching','HR Officer','HR Division','josie.guzman@deped.gov.ph','2019-01-25','Active','2025-08-19 12:35:52','2025-08-19 12:35:52');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_application_details`
--

DROP TABLE IF EXISTS `leave_application_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_application_details` (
  `detail_id` int NOT NULL AUTO_INCREMENT,
  `application_id` int NOT NULL,
  `field_name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `field_value` text COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`detail_id`),
  KEY `application_id` (`application_id`),
  CONSTRAINT `leave_application_details_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `leave_applications` (`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_application_details`
--

LOCK TABLES `leave_application_details` WRITE;
/*!40000 ALTER TABLE `leave_application_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_application_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_applications`
--

DROP TABLE IF EXISTS `leave_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_applications` (
  `application_id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `leave_type_id` int NOT NULL,
  `number_of_days` decimal(5,2) NOT NULL,
  `status` enum('Pending','Approved','Rejected','Cancelled') COLLATE utf8mb4_general_ci DEFAULT 'Pending',
  `filed_by` int NOT NULL,
  `approved_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`application_id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `filed_by` (`filed_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `leave_applications_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  CONSTRAINT `leave_applications_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`),
  CONSTRAINT `leave_applications_ibfk_3` FOREIGN KEY (`filed_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `leave_applications_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_applications`
--

LOCK TABLES `leave_applications` WRITE;
/*!40000 ALTER TABLE `leave_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_credit_logs`
--

DROP TABLE IF EXISTS `leave_credit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_credit_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `leave_type_id` int NOT NULL,
  `added_credits` decimal(5,2) NOT NULL,
  `reason` text COLLATE utf8mb4_general_ci NOT NULL,
  `added_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `added_by` (`added_by`),
  CONSTRAINT `leave_credit_logs_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  CONSTRAINT `leave_credit_logs_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`),
  CONSTRAINT `leave_credit_logs_ibfk_3` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=88 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_credit_logs`
--

LOCK TABLES `leave_credit_logs` WRITE;
/*!40000 ALTER TABLE `leave_credit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_credit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_credits`
--

DROP TABLE IF EXISTS `leave_credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_credits` (
  `credit_id` int NOT NULL AUTO_INCREMENT,
  `employee_id` int NOT NULL,
  `leave_type_id` int NOT NULL,
  `total_credits` decimal(5,2) DEFAULT '0.00',
  `used_credits` decimal(5,2) DEFAULT '0.00',
  `balance_credits` decimal(5,2) GENERATED ALWAYS AS ((`total_credits` - `used_credits`)) STORED,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`credit_id`),
  KEY `employee_id` (`employee_id`),
  KEY `leave_type_id` (`leave_type_id`),
  CONSTRAINT `leave_credits_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  CONSTRAINT `leave_credits_ibfk_2` FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`leave_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=83 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_credits`
--

LOCK TABLES `leave_credits` WRITE;
/*!40000 ALTER TABLE `leave_credits` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_credits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_types`
--

DROP TABLE IF EXISTS `leave_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `leave_types` (
  `leave_type_id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci,
  `applicable_to` enum('teaching','non_teaching','both') COLLATE utf8mb4_general_ci DEFAULT 'both',
  `required_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  PRIMARY KEY (`leave_type_id`),
  CONSTRAINT `leave_types_chk_1` CHECK (json_valid(`required_fields`))
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_types`
--

LOCK TABLES `leave_types` WRITE;
/*!40000 ALTER TABLE `leave_types` DISABLE KEYS */;
INSERT INTO `leave_types` VALUES (1,'Vacation Leave','Leave for personal reasons within or outside the Philippines.','both','[\"date_from\", \"date_to\", \"place_spent\"]','active'),(2,'Sick Leave','Leave of absence granted due to sickness or injury.','both','[\"date_from\", \"date_to\", \"illness_details\", \"medical_certificate\"]','active'),(3,'Maternity Leave','Leave granted to female employees in connection with pregnancy.','teaching','[\"date_from\", \"date_to\", \"expected_delivery_date\", \"actual_delivery_date\", \"medical_certificate\"]','active'),(4,'Paternity Leave','Leave granted to married male employees for childbirth of spouse.','teaching','[\"date_from\", \"date_to\", \"wife_name\", \"wife_delivery_date\", \"marriage_certificate\", \"birth_certificate\"]','active'),(5,'Study Leave','Leave for review and examination for bar or board exams or for completing a master\'s degree.','teaching','[\"date_from\", \"date_to\", \"purpose\", \"school_name\", \"admission_slip\"]','active'),(6,'Special Privilege Leave','Leave of absence for personal milestones or emergencies.','both','[\"date_from\", \"date_to\", \"reason\", \"proof_if_required\"]','active'),(7,'Special Leave for Women','Special leave for women due to gynecological disorders.','teaching','[\"date_from\", \"date_to\", \"gynecological_nature\", \"medical_certificate\"]','active'),(8,'Rehabilitation Leave','Leave granted for recovery from occupational injury.','both','[\"date_from\", \"date_to\", \"cause_of_injury\", \"medical_certificate\", \"injury_report\"]','active'),(9,'Adoption Leave','Leave granted for lawful adoption.','both','[\"date_from\", \"date_to\", \"adoption_decree\"]','active'),(10,'Terminal Leave','Leave granted upon resignation, retirement, or separation.','both','[\"effective_date\", \"number_of_days\", \"retirement_papers\", \"clearance\"]','active'),(12,'Compensatory Time‑Off','Leave earned in lieu of authorized overtime services; taken as days off.','both','[\"source\",\"earned_at\",\"expires_at\",\"date_from\",\"date_to\",\"number_of_days\"]','active');
/*!40000 ALTER TABLE `leave_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `notification_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('Unread','Read') COLLATE utf8mb4_general_ci DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`notification_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_logs`
--

DROP TABLE IF EXISTS `system_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `action` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `performed_by` int DEFAULT NULL,
  `details` text COLLATE utf8mb4_general_ci,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `performed_by` (`performed_by`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('super_admin','admin','hr','employee') COLLATE utf8mb4_general_ci NOT NULL,
  `employee_id` int DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_general_ci DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `employee_id` (`employee_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'superadmin','$2y$10$vmVsUvia6Dpy74a1CZly2.FgfuLO7E7o2ooVDIkGbLUQHvWnT02Gi','superadmin@depedlu.gov.ph','super_admin',NULL,'active','2025-07-17 12:07:04','2025-07-17 12:07:04'),(10,'admin','$2y$10$QXV/oz.7x392Uks.7lYQCeKOwP8S0tMlY.dTvHuHm1SQ8KcaSH46.','admin@deped.gov.ph','admin',NULL,'active','2025-07-17 17:11:05','2025-07-19 04:00:01'),(13,'admin1','$2y$10$xZ5yP.VEYDVNkcYdOhpdtubwLOQa.FYp.WQ5a.x8IXasDIBE4Qgc2','admin1@deped.gov.ph','admin',NULL,'active','2025-07-19 04:19:10','2025-07-19 04:19:10');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-08-19 23:02:04
