

CREATE DATABASE `register` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `register`;

--
-- Table structure for table `appointment_day_limits`
--

DROP TABLE IF EXISTS `appointment_day_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_day_limits` (
  `office` varchar(50) NOT NULL,
  `slot_date` date NOT NULL,
  `max_capacity` int(10) unsigned NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`office`,`slot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_day_limits`
--

LOCK TABLES `appointment_day_limits` WRITE;

UNLOCK TABLES;

--
-- Table structure for table `appointment_slots`
--

DROP TABLE IF EXISTS `appointment_slots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_slots` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `office` varchar(50) NOT NULL,
  `slot_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_capacity` int(11) NOT NULL DEFAULT 3,
  `status` varchar(20) NOT NULL DEFAULT 'open',
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_office_slot` (`office`,`slot_date`,`start_time`),
  KEY `office` (`office`),
  KEY `slot_date` (`slot_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=366 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_slots`
--

LOCK TABLES `appointment_slots` WRITE;
/*!40000 ALTER TABLE `appointment_slots` DISABLE KEYS */;
INSERT INTO `appointment_slots` VALUES (344,'registrar','2026-09-28','09:00:00','09:30:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(345,'registrar','2026-09-28','09:30:00','10:00:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(346,'registrar','2026-09-28','10:00:00','10:30:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(347,'registrar','2026-09-28','10:30:00','11:00:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(348,'registrar','2026-09-28','11:00:00','11:30:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(349,'registrar','2026-09-28','13:30:00','14:00:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(350,'registrar','2026-09-28','14:00:00','14:30:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(351,'registrar','2026-09-28','14:30:00','15:00:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(352,'registrar','2026-09-28','15:00:00','15:30:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(353,'registrar','2026-09-28','15:30:00','16:00:00',3,'open','ADMIN-001','2026-09-22 10:45:14'),(362,'registrar','2026-09-24','14:00:00','14:30:00',3,'open','ADMIN-001','2026-09-24 09:46:01'),(365,'registrar','2026-09-24','15:30:00','16:00:00',3,'open','ADMIN-001','2026-09-24 09:46:01');
/*!40000 ALTER TABLE `appointment_slots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `slot_id` int(11) DEFAULT NULL,
  `student_id` varchar(50) NOT NULL,
  `office` varchar(50) NOT NULL,
  `service` varchar(100) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `registrar_notes` text DEFAULT NULL,
  `queue_number` varchar(20) NOT NULL DEFAULT '',
  `queue_status` varchar(30) NOT NULL DEFAULT 'Waiting',
  `called_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `slot_id` (`slot_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Daily queue sequence counters, locked transactionally per office and date
--

CREATE TABLE `queue_daily_counters` (
  `office` varchar(100) NOT NULL,
  `queue_date` date NOT NULL,
  `last_number` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`office`, `queue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (38,304,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Student Records Update, Certificate of Enrollment','2026-09-22','09:00:00','Rejected','','','R-001','Waiting',NULL,'2026-09-22 10:55:50','2026-09-22 12:14:14','2026-09-22 05:14:14'),(39,309,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Student Records Update, Certificate of Enrollment','2026-09-22','13:30:00','Rejected','','','R-002','Waiting',NULL,'2026-09-22 10:56:23','2026-09-22 12:15:25','2026-09-22 05:15:25'),(40,NULL,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Student Records Update, Certificate of Enrollment','2026-09-22','09:30:00','Rejected','','','R-003','Waiting',NULL,'2026-09-22 10:57:31','2026-09-22 12:16:21','2026-09-22 05:14:22'),(41,304,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of G','2026-09-22','09:00:00','Rejected','','','R-004','Waiting',NULL,'2026-09-22 11:02:16','2026-09-22 12:14:17','2026-09-22 05:14:17'),(42,NULL,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of G','2026-09-22','09:30:00','Rejected','fuck that','','R-005','Waiting',NULL,'2026-09-22 11:03:37','2026-09-22 12:16:21','2026-09-22 05:14:36'),(43,309,'24001321100','registrar','Enrollment Verification, Certificate of Enrollment (COE)','2026-09-22','13:30:00','Rejected','','','R-006','Waiting',NULL,'2026-09-22 11:04:15','2026-09-22 12:15:08','2026-09-22 05:15:08'),(44,NULL,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of G','2026-09-22','09:30:00','Rejected','','','R-007','Waiting',NULL,'2026-09-22 11:08:57','2026-09-22 12:16:21','2026-09-22 05:14:39'),(45,NULL,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Student Records Update','2026-09-22','09:30:00','Rejected','','','R-008','Waiting',NULL,'2026-09-22 11:13:30','2026-09-22 12:16:21','2026-09-22 05:14:40'),(46,NULL,'24001321100','registrar','Transcript Request (TOR), Certificate of Good Moral Character','2026-09-22','09:30:00','Rejected','fuck y\'all','','R-009','Waiting',NULL,'2026-09-22 11:16:09','2026-09-22 12:16:21','2026-09-22 05:14:49'),(47,NULL,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of G','2026-09-22','09:30:00','Approved','','','R-010','Completed','2026-09-24 02:38:09','2026-09-22 11:23:27','2026-09-24 09:38:22','2026-09-22 05:15:39'),(48,304,'24001321100','registrar','Certificate of Enrollment (COE), Certificate of Good Moral Character, Honorable Dismissal','2026-09-22','09:00:00','Rejected','','','R-011','Waiting',NULL,'2026-09-22 11:27:10','2026-09-22 12:14:19','2026-09-22 05:14:19'),(49,NULL,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of G','2026-09-22','09:30:00','Rejected','','','R-012','Waiting',NULL,'2026-09-22 11:36:17','2026-09-22 12:16:21','2026-09-22 05:15:34'),(50,309,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of G','2026-09-22','13:30:00','Rejected','','','R-013','Waiting',NULL,'2026-09-22 11:48:16','2026-09-22 12:15:06','2026-09-22 05:15:06'),(51,309,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Certificate of Good Moral Character','2026-09-22','13:30:00','Rejected','','','R-014','Waiting',NULL,'2026-09-22 11:53:34','2026-09-22 12:15:05','2026-09-22 05:15:05'),(52,NULL,'24001321100','registrar','Transcript Request (TOR), Certificate of Good Moral Character','2026-09-22','09:30:00','Rejected','','','R-015','Waiting',NULL,'2026-09-22 11:56:52','2026-09-22 12:16:21','2026-09-22 05:15:33'),(53,309,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of G','2026-09-22','13:30:00','Rejected','','','R-016','Waiting',NULL,'2026-09-22 12:00:16','2026-09-22 12:15:03','2026-09-22 05:15:03'),(54,NULL,'24001321100','registrar','Enrollment Verification, Certificate of Enrollment (COE)','2026-09-22','09:30:00','Rejected','','','R-017','Waiting',NULL,'2026-09-22 12:09:27','2026-09-22 12:16:21','2026-09-22 05:15:30'),(55,309,'24001321100','registrar','Enrollment Verification, Certificate of Enrollment (COE)','2026-09-22','13:30:00','Rejected','','','R-018','Waiting',NULL,'2026-09-22 12:10:05','2026-09-22 12:15:01','2026-09-22 05:15:01'),(56,304,'24001321100','registrar','Certificate of Good Moral Character','2026-09-22','09:00:00','Rejected','','','R-019','Waiting',NULL,'2026-09-22 12:20:05','2026-09-22 12:20:24','2026-09-22 05:20:24'),(57,307,'24001321100','registrar','Enrollment Verification, Transcript Request (TOR)','2026-09-22','10:30:00','Rejected','','','R-020','Waiting',NULL,'2026-09-22 12:20:48','2026-09-22 12:20:54','2026-09-22 05:20:54'),(58,306,'24001321100','registrar','Transcript Request (TOR), Honorable Dismissal','2026-09-22','10:00:00','Rejected','','','R-021','Waiting',NULL,'2026-09-22 12:21:09','2026-09-22 12:29:30','2026-09-22 05:29:30'),(59,308,'24001321100','registrar','Transcript Request (TOR), Honorable Dismissal','2026-09-22','11:00:00','Rejected','','','R-022','Waiting',NULL,'2026-09-22 12:22:19','2026-09-22 12:29:40','2026-09-22 05:29:40'),(60,362,'24001321100','registrar','Transcript Request (TOR), Honorable Dismissal','2026-09-24','14:00:00','Approved','','','R-001','Completed','2026-09-24 02:47:10','2026-09-24 09:46:31','2026-09-24 09:47:23','2026-09-24 02:46:37'),(61,365,'24001321100','registrar','Transcript Request (TOR), Student Records Update','2026-09-24','15:30:00','Approved','','','R-002','Completed','2026-09-24 02:47:23','2026-09-24 09:46:45','2026-09-25 12:54:53','2026-09-24 02:46:48'),(62,362,'24001321100','registrar','Transcript Request (TOR)','2026-09-24','14:00:00','Rejected','','','R-003','Waiting',NULL,'2026-09-24 10:11:11','2026-09-24 10:11:34','2026-09-24 03:11:34'),(63,347,'24001321100','registrar','Enrollment Verification, Honorable Dismissal','2026-09-28','10:30:00','Approved','','','R-001','Completed','2026-09-25 05:54:53','2026-09-25 12:54:30','2026-09-25 12:55:49','2026-09-25 05:54:45'),(64,348,'24001321100','registrar','Enrollment Verification, Student Records Update, Honorable Dismissal, Subject Add / Drop','2026-09-28','11:00:00','Approved','','','R-002','Serving','2026-09-25 05:55:49','2026-09-25 12:55:22','2026-09-25 12:55:49','2026-09-25 05:55:42');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

ALTER TABLE `appointments`
  ADD COLUMN `education_level` varchar(20) DEFAULT NULL AFTER `service`,
  ADD COLUMN `strand` varchar(50) DEFAULT NULL AFTER `education_level`,
  ADD COLUMN `grade_level` varchar(2) DEFAULT NULL AFTER `strand`,
  ADD COLUMN `program` varchar(100) DEFAULT NULL AFTER `grade_level`,
  ADD COLUMN `college_year_level` varchar(3) DEFAULT NULL AFTER `program`,
  ADD COLUMN `section` varchar(50) DEFAULT NULL AFTER `college_year_level`;

--
-- Table structure for table `offices`
--

DROP TABLE IF EXISTS `offices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `offices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `offices`
--

LOCK TABLES `offices` WRITE;
/*!40000 ALTER TABLE `offices` DISABLE KEYS */;
INSERT INTO `offices` VALUES (1,'registrar','Registrar Office','Enrollment, Academic Records, Transcripts, and Certifications','2026-09-20 03:38:14');
/*!40000 ALTER TABLE `offices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `queue_control`
--

DROP TABLE IF EXISTS `queue_control`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `queue_control` (
  `office` varchar(50) NOT NULL,
  `is_paused` tinyint(1) NOT NULL DEFAULT 0,
  `counter_name` varchar(100) NOT NULL DEFAULT 'Registrar Window 1',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`office`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `queue_control`
--

LOCK TABLES `queue_control` WRITE;
/*!40000 ALTER TABLE `queue_control` DISABLE KEYS */;
INSERT INTO `queue_control` VALUES ('registrar',1,'Registrar Window 1','2026-09-25 13:19:51');
/*!40000 ALTER TABLE `queue_control` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `realtime_events`
--

DROP TABLE IF EXISTS `realtime_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `realtime_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(80) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `payload` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `realtime_events`
--

LOCK TABLES `realtime_events` WRITE;
/*!40000 ALTER TABLE `realtime_events` DISABLE KEYS */;
INSERT INTO `realtime_events` VALUES (1,'appointment_created',38,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Student Records Update, Certificate of Enrollment (COE), Certificate of Good Moral Character, Honorable Dismissal\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:00\",\"queue_number\":\"R-001\",\"status\":\"Pending\"}','2026-09-22 10:55:50'),(2,'appointment_created',39,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Student Records Update, Certificate of Enrollment (COE), Certificate of Good Moral Character, Honorable Dismissal\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30\",\"queue_number\":\"R-002\",\"status\":\"Pending\"}','2026-09-22 10:56:23'),(3,'appointment_created',40,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Student Records Update, Certificate of Enrollment (COE), Certificate of Good Moral Character, Honorable Dismissal\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-003\",\"status\":\"Pending\"}','2026-09-22 10:57:31'),(4,'appointment_created',41,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:00\",\"queue_number\":\"R-004\",\"status\":\"Pending\"}','2026-09-22 11:02:16'),(5,'appointment_created',42,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-005\",\"status\":\"Pending\"}','2026-09-22 11:03:37'),(6,'appointment_created',43,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Certificate of Enrollment (COE)\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30\",\"queue_number\":\"R-006\",\"status\":\"Pending\"}','2026-09-22 11:04:15'),(7,'appointment_created',44,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-007\",\"status\":\"Pending\"}','2026-09-22 11:08:57'),(8,'appointment_created',45,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Student Records Update\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-008\",\"status\":\"Pending\"}','2026-09-22 11:13:30'),(9,'appointment_created',46,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Transcript Request (TOR), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-009\",\"status\":\"Pending\"}','2026-09-22 11:16:09'),(10,'appointment_created',47,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-010\",\"status\":\"Pending\"}','2026-09-22 11:23:27'),(11,'appointment_created',48,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Certificate of Enrollment (COE), Certificate of Good Moral Character, Honorable Dismissal\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:00\",\"queue_number\":\"R-011\",\"status\":\"Pending\"}','2026-09-22 11:27:10'),(12,'appointment_created',49,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-012\",\"status\":\"Pending\"}','2026-09-22 11:36:17'),(13,'appointment_created',50,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30\",\"queue_number\":\"R-013\",\"status\":\"Pending\"}','2026-09-22 11:48:16'),(14,'appointment_created',51,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30\",\"queue_number\":\"R-014\",\"status\":\"Pending\"}','2026-09-22 11:53:34'),(15,'appointment_created',52,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Transcript Request (TOR), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-015\",\"status\":\"Pending\"}','2026-09-22 11:56:52'),(16,'appointment_created',53,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR), Certificate of Enrollment (COE), Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30\",\"queue_number\":\"R-016\",\"status\":\"Pending\"}','2026-09-22 12:00:16'),(17,'appointment_created',54,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Certificate of Enrollment (COE)\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30\",\"queue_number\":\"R-017\",\"status\":\"Pending\"}','2026-09-22 12:09:27'),(18,'appointment_created',55,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Certificate of Enrollment (COE)\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30\",\"queue_number\":\"R-018\",\"status\":\"Pending\"}','2026-09-22 12:10:05'),(19,'appointment_updated',38,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:00:00\",\"queue_number\":\"R-001\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:14:14'),(20,'appointment_updated',41,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:00:00\",\"queue_number\":\"R-004\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:14:17'),(21,'appointment_updated',48,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:00:00\",\"queue_number\":\"R-011\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:14:19'),(22,'appointment_updated',40,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-003\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:14:22'),(23,'appointment_updated',42,'24001321100','{\"status\":\"Rejected\",\"reason\":\"fuck that\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-005\",\"message\":\"Appointment Rejected. Reason: fuck that\"}','2026-09-22 12:14:36'),(24,'appointment_updated',44,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-007\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:14:39'),(25,'appointment_updated',45,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-008\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:14:40'),(26,'appointment_updated',46,'24001321100','{\"status\":\"Rejected\",\"reason\":\"fuck y\'all\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-009\",\"message\":\"Appointment Rejected. Reason: fuck y\'all\"}','2026-09-22 12:14:49'),(27,'appointment_updated',55,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30:00\",\"queue_number\":\"R-018\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:01'),(28,'appointment_updated',53,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30:00\",\"queue_number\":\"R-016\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:03'),(29,'appointment_updated',51,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30:00\",\"queue_number\":\"R-014\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:05'),(30,'appointment_updated',50,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30:00\",\"queue_number\":\"R-013\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:06'),(31,'appointment_updated',43,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30:00\",\"queue_number\":\"R-006\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:08'),(32,'appointment_updated',39,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"13:30:00\",\"queue_number\":\"R-002\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:25'),(33,'appointment_updated',54,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-017\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:30'),(34,'appointment_updated',52,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-015\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:33'),(35,'appointment_updated',49,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-012\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:15:34'),(36,'appointment_updated',47,'24001321100','{\"status\":\"Approved\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:30:00\",\"queue_number\":\"R-010\",\"message\":\"Appointment Approved.\"}','2026-09-22 12:15:39'),(37,'appointment_created',56,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Certificate of Good Moral Character\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:00\",\"queue_number\":\"R-019\",\"status\":\"Pending\"}','2026-09-22 12:20:05'),(38,'appointment_updated',56,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"09:00:00\",\"queue_number\":\"R-019\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:20:24'),(39,'appointment_created',57,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Transcript Request (TOR)\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"10:30\",\"queue_number\":\"R-020\",\"status\":\"Pending\"}','2026-09-22 12:20:48'),(40,'appointment_updated',57,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"10:30:00\",\"queue_number\":\"R-020\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:20:54'),(41,'appointment_created',58,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Transcript Request (TOR), Honorable Dismissal\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"10:00\",\"queue_number\":\"R-021\",\"status\":\"Pending\"}','2026-09-22 12:21:09'),(42,'appointment_created',59,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Transcript Request (TOR), Honorable Dismissal\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"11:00\",\"queue_number\":\"R-022\",\"status\":\"Pending\"}','2026-09-22 12:22:19'),(43,'appointment_updated',58,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"10:00:00\",\"queue_number\":\"R-021\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:29:30'),(44,'appointment_updated',59,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-22\",\"appointment_time\":\"11:00:00\",\"queue_number\":\"R-022\",\"message\":\"Appointment Rejected.\"}','2026-09-22 12:29:40'),(45,'appointment_created',60,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Transcript Request (TOR), Honorable Dismissal\",\"appointment_date\":\"2026-09-24\",\"appointment_time\":\"14:00\",\"queue_number\":\"R-001\",\"status\":\"Pending\"}','2026-09-24 09:46:31'),(46,'appointment_updated',60,'24001321100','{\"status\":\"Approved\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-24\",\"appointment_time\":\"14:00:00\",\"queue_number\":\"R-001\",\"message\":\"Appointment Approved.\"}','2026-09-24 09:46:37'),(47,'appointment_created',61,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Transcript Request (TOR), Student Records Update\",\"appointment_date\":\"2026-09-24\",\"appointment_time\":\"15:30\",\"queue_number\":\"R-002\",\"status\":\"Pending\"}','2026-09-24 09:46:45'),(48,'appointment_updated',61,'24001321100','{\"status\":\"Approved\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-24\",\"appointment_time\":\"15:30:00\",\"queue_number\":\"R-002\",\"message\":\"Appointment Approved.\"}','2026-09-24 09:46:48'),(49,'appointment_created',62,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Transcript Request (TOR)\",\"appointment_date\":\"2026-09-24\",\"appointment_time\":\"14:00\",\"queue_number\":\"R-003\",\"status\":\"Pending\"}','2026-09-24 10:11:11'),(50,'appointment_updated',62,'24001321100','{\"status\":\"Rejected\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-24\",\"appointment_time\":\"14:00:00\",\"queue_number\":\"R-003\",\"message\":\"Appointment Rejected.\"}','2026-09-24 10:11:34'),(51,'appointment_created',63,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Honorable Dismissal\",\"appointment_date\":\"2026-09-28\",\"appointment_time\":\"10:30\",\"queue_number\":\"R-001\",\"status\":\"Pending\"}','2026-09-25 12:54:30'),(52,'appointment_updated',63,'24001321100','{\"status\":\"Approved\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-28\",\"appointment_time\":\"10:30:00\",\"queue_number\":\"R-001\",\"message\":\"Appointment Approved.\"}','2026-09-25 12:54:45'),(53,'appointment_created',64,'24001321100','{\"student_name\":\"Unice Ann Tubal\",\"service\":\"Enrollment Verification, Student Records Update, Honorable Dismissal, Subject Add \\/ Drop\",\"appointment_date\":\"2026-09-28\",\"appointment_time\":\"11:00\",\"queue_number\":\"R-002\",\"status\":\"Pending\"}','2026-09-25 12:55:22'),(54,'appointment_updated',64,'24001321100','{\"status\":\"Approved\",\"reason\":\"\",\"registrar_notes\":\"\",\"appointment_date\":\"2026-09-28\",\"appointment_time\":\"11:00:00\",\"queue_number\":\"R-002\",\"message\":\"Appointment Approved.\"}','2026-09-25 12:55:42');
/*!40000 ALTER TABLE `realtime_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `office_code` varchar(50) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `estimated_duration_min` int(11) DEFAULT 15,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `available_from` datetime DEFAULT NULL,
  `available_until` datetime DEFAULT NULL,
  `education_level` varchar(20) NOT NULL DEFAULT 'ALL',
  `target_level` varchar(20) NOT NULL DEFAULT 'Both',
  PRIMARY KEY (`id`),
  KEY `office_code` (`office_code`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'registrar','Enrollment Verification',15,'2026-09-20 03:38:14',1,NULL,NULL,'ALL','Both'),(2,'registrar','Transcript Request (TOR)',20,'2026-09-20 03:38:14',0,NULL,NULL,'ALL','College'),(3,'registrar','Student Records Update',15,'2026-09-20 03:38:14',1,NULL,NULL,'ALL','Both'),(4,'registrar','Certificate of Enrollment (COE)',10,'2026-09-20 03:38:14',1,NULL,NULL,'ALL','Both'),(5,'registrar','Certificate of Good Moral Character',10,'2026-09-20 03:38:14',1,NULL,NULL,'ALL','Both'),(6,'registrar','Honorable Dismissal',20,'2026-09-20 03:38:14',1,NULL,NULL,'ALL','College'),(14,'registrar','Form 137 / SF10 Request',20,'2026-09-25 12:14:33',1,NULL,NULL,'SHS','SHS'),(15,'registrar','Strand Transfer Request',15,'2026-09-25 12:14:33',1,NULL,NULL,'SHS','SHS'),(16,'registrar','SHS Diploma Request',15,'2026-09-25 12:14:33',1,NULL,NULL,'SHS','SHS'),(17,'registrar','Subject Add / Drop',15,'2026-09-25 12:14:33',1,NULL,NULL,'College','College'),(18,'registrar','Graduation Clearance',20,'2026-09-25 12:14:33',1,NULL,NULL,'College','College'),(19,'registrar','Evaluation Request',20,'2026-09-25 12:14:33',1,NULL,NULL,'College','College');
DELETE FROM `services` WHERE `office_code` = 'registrar' AND `service_name` = 'Form 137 / SF10 Request';
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `statuses`
--

DROP TABLE IF EXISTS `statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `statuses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(30) NOT NULL,
  `label` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=22067 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `statuses`
--

LOCK TABLES `statuses` WRITE;
/*!40000 ALTER TABLE `statuses` DISABLE KEYS */;
INSERT INTO `statuses` VALUES (1,'pending','Pending'),(2,'approved','Approved'),(3,'rejected','Rejected'),(4,'cancelled','Cancelled');
/*!40000 ALTER TABLE `statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `student_id` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(25) NOT NULL DEFAULT '',
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `role` varchar(50) NOT NULL DEFAULT 'student',
  `education_level` varchar(20) DEFAULT NULL,
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`student_id`, `first_name`, `last_name`, `email`, `phone`, `password`, `created_at`, `role`, `education_level`) VALUES ('ADMIN-001','Registrar','Admin','admin@aclc.com','','$2y$12$zp/PkCzjRqOWTkj2NeaxO.rgRWLe3rKzcsLy9Q1/YrvwifYPxGKYu','2026-08-29 02:06:20','registrar',NULL),('24001321100','Unice Ann','Tubal','unixiann@gmail.com','09202527586','$2y$10$bBQWup5BTa0TUF/w1fWT.O/ATibnmCLhNTMdaBr1yTn4eEawNvLiS','2026-09-20 05:36:31','student','College');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'register'
--

--
-- Dumping routines for database 'register'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-25 23:04:05
