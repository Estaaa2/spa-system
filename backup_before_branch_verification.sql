-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: levictas_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `applicants`
--

DROP TABLE IF EXISTS `applicants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `applicants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_posting_id` bigint(20) unsigned DEFAULT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `position_applied` varchar(255) DEFAULT NULL,
  `availability` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `gender` varchar(255) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `civil_status` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `education` varchar(255) DEFAULT NULL,
  `work_experience` text DEFAULT NULL,
  `resume_path` varchar(255) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_relation` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(255) DEFAULT NULL,
  `expected_start_date` date DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','interview','approved','rejected','hired') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `applicants_spa_id_foreign` (`spa_id`),
  KEY `applicants_branch_id_foreign` (`branch_id`),
  KEY `applicants_job_posting_id_foreign` (`job_posting_id`),
  CONSTRAINT `applicants_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `applicants_job_posting_id_foreign` FOREIGN KEY (`job_posting_id`) REFERENCES `job_postings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `applicants_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `applicants`
--

LOCK TABLES `applicants` WRITE;
/*!40000 ALTER TABLE `applicants` DISABLE KEYS */;
INSERT INTO `applicants` VALUES (3,NULL,2,2,'manager','full_time','website','male','2004-11-07','single','Bancal Elementary School, Governor\'s Drive, Bancal, Carmona, Cavite, Calabarzon, 4116, Philippines','college',NULL,'resumes/oZfG6A1CUcB8MTM1iBIsQ3q0OZlMnvspxrXIkaAt.pdf',NULL,'Evan Lingo','Father','09753816489','2026-08-24','Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,'pending','2026-08-17 14:13:24','2026-08-17 14:13:24',NULL),(4,NULL,2,2,'therapist','full_time','website','male','2004-11-18','single','Bancal Elementary School, Governor\'s Drive, Bancal, Carmona, Cavite, Calabarzon, 4116, Philippines','college',NULL,'resumes/phTNOKHkwy6i1GzpOaeG5KPYg3ripvzKXaFV6e7e.pdf',NULL,'Mark Lingo','Father','09357816489','2026-08-31','Esta Lingo','lingopiolo@gmail.com','09753305469',NULL,'interview','2026-08-24 07:38:03','2026-10-04 14:56:29',NULL),(6,NULL,2,2,'therapist','full_time','manual','male','2004-11-07','single','Blk15 Lot14 Monte Carlo Townhomes Bancal,Carmona,Cavite','college',NULL,'resumes/fNa0nTlOPLwytgDPPt54JmxzSmEg5TvRf6XkyWvc.pdf',NULL,'Mark Lingo','Father','09357816489','2026-10-12','Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,'rejected','2026-10-04 13:23:43','2026-10-04 14:52:08',NULL),(7,NULL,2,2,'therapist','part_time','manual','male','2004-11-07','single','Blk15 Lot14 Monte Carlo Townhomes Bancal,Carmona,Cavite','undergraduate',NULL,NULL,NULL,'Mark Lingo','Father','09357816489','2026-10-19','Evan Lingo','lingopiolo@gmail.com','09357816489',NULL,'rejected','2026-10-04 13:30:09','2026-10-04 13:48:17',NULL),(8,NULL,2,2,'receptionist','part_time','manual','female','2002-03-20','single','Blk15 Lot14 Monte Carlo Townhomes Bancal,Carmona,Cavite','undergraduate',NULL,'resumes/RTqQag5ZAIwjDPduAp77OKEaBfvpmdxJalzpq9QZ.pdf',NULL,'Marlyn Lingo','Mother','09192255624','2026-10-19','Pamela Lingo','pamelalingo6@gmail.com','09357816489',NULL,'approved','2026-10-04 14:55:05','2026-10-04 14:56:44',NULL);
/*!40000 ALTER TABLE `applicants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_consumption_items`
--

DROP TABLE IF EXISTS `booking_consumption_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_consumption_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_consumption_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `product_batch_id` bigint(20) unsigned DEFAULT NULL,
  `treatment_id` bigint(20) unsigned DEFAULT NULL,
  `treatment_name` varchar(255) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `batch_number` varchar(255) DEFAULT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `unit` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `booking_consumption_items_booking_consumption_id_foreign` (`booking_consumption_id`),
  KEY `booking_consumption_items_product_id_foreign` (`product_id`),
  KEY `booking_consumption_items_product_batch_id_foreign` (`product_batch_id`),
  KEY `booking_consumption_items_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `booking_consumption_items_booking_consumption_id_foreign` FOREIGN KEY (`booking_consumption_id`) REFERENCES `booking_consumptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `booking_consumption_items_product_batch_id_foreign` FOREIGN KEY (`product_batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `booking_consumption_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `booking_consumption_items_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_consumption_items`
--

LOCK TABLES `booking_consumption_items` WRITE;
/*!40000 ALTER TABLE `booking_consumption_items` DISABLE KEYS */;
INSERT INTO `booking_consumption_items` VALUES (1,1,4,NULL,NULL,NULL,'Lavander Massage Oil','BATCH 2',30.000,'ml','2026-10-01 16:11:28','2026-10-01 16:11:28'),(2,2,4,NULL,NULL,NULL,'Lavander Massage Oil','BATCH 2',30.000,'ml','2026-10-01 16:19:07','2026-10-01 16:19:07'),(3,3,4,NULL,NULL,NULL,'Lavander Massage Oil','BATCH 2',30.000,'ml','2026-10-01 16:22:24','2026-10-01 16:22:24'),(4,11,4,NULL,NULL,NULL,'Lavander Massage Oil','BATCH 2',470.000,'ml','2026-10-01 16:35:16','2026-10-01 16:35:16'),(5,11,4,NULL,NULL,NULL,'Lavander Massage Oil','FEFO-C',30.000,'ml','2026-10-01 16:35:16','2026-10-01 16:35:16'),(12,18,4,NULL,3,'Swedish Massage','Lavander Massage Oil','BATCH 2',30.000,'ml','2026-10-01 16:59:52','2026-10-01 16:59:52'),(13,19,4,NULL,3,'Swedish Massage','Lavander Massage Oil','BATCH 2',60.000,'ml','2026-10-01 17:03:03','2026-10-01 17:03:03'),(14,20,4,NULL,3,'Swedish Massage','Lavander Massage Oil','BATCH 2',30.000,'ml','2026-10-01 17:07:43','2026-10-01 17:07:43'),(15,20,2,NULL,4,'Foot Spa','Peppermint Foot Soak','LEGACY-2-2',5.000,'ml','2026-10-01 17:07:43','2026-10-01 17:07:43'),(18,23,1,NULL,12,'Marjo Catibod','Hand Wash','FEFO-A',100.000,'ml','2026-10-02 08:06:25','2026-10-02 08:06:25'),(19,24,4,NULL,3,'Swedish Massage','Lavander Massage Oil','FEFO-C',30.000,'ml','2026-10-02 14:04:21','2026-10-02 14:04:21');
/*!40000 ALTER TABLE `booking_consumption_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_consumptions`
--

DROP TABLE IF EXISTS `booking_consumptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_consumptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `treatment_id` bigint(20) unsigned DEFAULT NULL,
  `package_id` bigint(20) unsigned DEFAULT NULL,
  `processed_by` bigint(20) unsigned DEFAULT NULL,
  `service_reference` varchar(255) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `consumed_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_consumptions_booking_id_unique` (`booking_id`),
  KEY `booking_consumptions_spa_id_foreign` (`spa_id`),
  KEY `booking_consumptions_branch_id_foreign` (`branch_id`),
  KEY `booking_consumptions_treatment_id_foreign` (`treatment_id`),
  KEY `booking_consumptions_processed_by_foreign` (`processed_by`),
  KEY `booking_consumptions_package_id_foreign` (`package_id`),
  CONSTRAINT `booking_consumptions_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `booking_consumptions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `booking_consumptions_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `booking_consumptions_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `booking_consumptions_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`),
  CONSTRAINT `booking_consumptions_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_consumptions`
--

LOCK TABLES `booking_consumptions` WRITE;
/*!40000 ALTER TABLE `booking_consumptions` DISABLE KEYS */;
INSERT INTO `booking_consumptions` VALUES (1,12,2,2,3,NULL,7,'treatment_3','Swedish Massage','2026-10-01 16:11:28','2026-10-01 16:11:28','2026-10-01 16:11:28'),(2,14,2,2,3,NULL,NULL,'treatment_3','Swedish Massage','2026-10-01 16:19:07','2026-10-01 16:19:07','2026-10-01 16:19:07'),(3,15,2,2,3,NULL,NULL,'treatment_3','Swedish Massage','2026-10-01 16:22:24','2026-10-01 16:22:24','2026-10-01 16:22:24'),(11,17,2,2,NULL,NULL,NULL,'treatment_9','Phase 2C FEFO Split Test','2026-10-01 16:35:16','2026-10-01 16:35:16','2026-10-01 16:35:16'),(18,22,2,2,NULL,1,7,'package_1','Duo Goals','2026-10-01 16:59:52','2026-10-01 16:59:52','2026-10-01 16:59:52'),(19,23,2,2,NULL,1,7,'package_1','Duo Goals','2026-10-01 17:03:03','2026-10-01 17:03:03','2026-10-01 17:03:03'),(20,24,2,2,NULL,1,7,'package_1','Duo Goals','2026-10-01 17:07:43','2026-10-01 17:07:43','2026-10-01 17:07:43'),(23,29,2,2,12,NULL,NULL,'treatment_12','Marjo Catibod','2026-10-02 08:06:25','2026-10-02 08:06:25','2026-10-02 08:06:25'),(24,28,2,2,3,NULL,NULL,'treatment_3','Swedish Massage','2026-10-02 14:04:21','2026-10-02 14:04:21','2026-10-02 14:04:21'),(25,31,4,6,14,NULL,NULL,'treatment_14','Swedish Massage','2026-10-05 09:18:08','2026-10-05 09:18:08','2026-10-05 09:18:08');
/*!40000 ALTER TABLE `booking_consumptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `customer_user_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `booking_source` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'reserved',
  `payment_status` varchar(255) NOT NULL DEFAULT 'unpaid',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `service_type` varchar(255) NOT NULL,
  `treatment` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `customer_address` varchar(255) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `therapist_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bookings_spa_id_foreign` (`spa_id`),
  KEY `bookings_branch_id_foreign` (`branch_id`),
  KEY `bookings_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `bookings_therapist_id_foreign` (`therapist_id`),
  KEY `bookings_customer_user_id_foreign` (`customer_user_id`),
  CONSTRAINT `bookings_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_customer_user_id_foreign` FOREIGN KEY (`customer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `bookings_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bookings_therapist_id_foreign` FOREIGN KEY (`therapist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (1,2,2,6,NULL,'online','completed','partially_paid',140.00,700.00,560.00,'in_branch','treatment_3','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-06-29','11:00:00','11:30:00',9,'2026-06-28 15:45:47','2026-09-27 08:39:36',NULL),(2,2,2,15,NULL,'online','completed','partially_paid',140.00,700.00,560.00,'in_branch','treatment_3','09357816489','Marjo Catibod',NULL,'catibod.marjo@ncst.edu.ph','2026-07-13','10:00:00','10:30:00',9,'2026-07-12 12:35:21','2026-09-27 08:39:36',NULL),(3,2,2,15,NULL,'online','completed','partially_paid',140.00,700.00,560.00,'in_branch','treatment_4','09123456789','Marjo Catibod',NULL,'catibod.marjo@ncst.edu.ph','2026-07-14','10:00:00','10:30:00',9,'2026-07-12 13:00:47','2026-09-27 08:39:36',NULL),(4,2,2,6,NULL,'online','completed','partially_paid',140.00,700.00,560.00,'in_branch','treatment_3','09123456789','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-07-24','09:43:00','10:13:00',9,'2026-07-13 01:45:56','2026-09-27 08:39:36',NULL),(5,2,2,6,NULL,'online','completed','partially_paid',140.00,700.00,560.00,'in_branch','treatment_4','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-07-30','10:00:00','10:30:00',9,'2026-07-23 14:25:54','2026-09-27 08:39:36',NULL),(6,2,2,6,NULL,'online','cancelled','partially_paid',140.00,700.00,560.00,'in_branch','treatment_3','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-12-23','12:30:00','13:00:00',9,'2026-07-23 14:27:04','2026-09-27 08:39:36',NULL),(7,2,2,6,NULL,'online','completed','partially_paid',140.00,700.00,560.00,'in_branch','treatment_4','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-08-06','09:00:00','09:30:00',9,'2026-08-05 13:38:27','2026-09-27 08:39:36',NULL),(8,2,2,6,NULL,'online','completed','partially_paid',140.00,700.00,560.00,'in_branch','treatment_4','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-08-06','10:00:00','10:30:00',9,'2026-08-05 13:38:58','2026-09-27 08:39:36',NULL),(9,2,2,6,NULL,'online','completed','partially_paid',140.00,700.00,560.00,'in_branch','treatment_3','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-08-20','09:00:00','09:30:00',17,'2026-08-17 14:50:58','2026-09-27 08:39:36',NULL),(11,2,2,6,NULL,'online','completed','partially_paid',112.00,560.00,448.00,'in_branch','treatment_3','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-08-24','09:00:00','09:30:00',9,'2026-08-23 14:20:42','2026-09-27 08:39:36',NULL),(12,2,2,6,NULL,'online','completed','partially_paid',112.00,560.00,448.00,'in_branch','treatment_3','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-08-25','09:00:00','09:30:00',17,'2026-08-24 03:06:55','2026-09-27 08:39:36',NULL),(13,2,2,15,NULL,'online','reserved','partially_paid',280.00,1400.00,1120.00,'in_branch','package_1','09357816489','Marjo Catibod',NULL,'catibod.marjo@ncst.edu.ph','2026-10-26','09:00:00','10:00:00',17,'2026-09-27 09:17:48','2026-10-01 15:52:38',NULL),(14,2,2,6,7,'staff','completed','paid',700.00,700.00,0.00,'in_branch','treatment_3','09123456789','Phase 2B Test',NULL,'phase2b@test.local','2026-10-01','09:00:00','09:30:00',17,'2026-10-01 16:18:37','2026-10-01 16:26:51','2026-10-01 16:26:51'),(15,2,2,6,7,'staff','completed','paid',700.00,700.00,0.00,'in_branch','treatment_3','09123456789','Phase 2B Test',NULL,'phase2b@test.local','2026-10-01','09:00:00','09:30:00',17,'2026-10-01 16:22:10','2026-10-01 16:24:23','2026-10-01 16:24:23'),(16,2,2,6,7,'staff','ongoing','paid',100.00,100.00,0.00,'in_branch','treatment_8','09123456789','Phase 2C Insufficient Test',NULL,'phase2c@test.local','2026-10-01','09:00:00','09:30:00',17,'2026-10-01 16:31:03','2026-10-01 16:34:12','2026-10-01 16:34:12'),(17,2,2,6,7,'staff','completed','paid',100.00,100.00,0.00,'in_branch','treatment_9','09123456789','Phase 2C FEFO Split Test',NULL,'phase2c-fefo@test.local','2026-10-01','09:00:00','09:30:00',17,'2026-10-01 16:35:08','2026-10-01 16:37:34','2026-10-01 16:37:34'),(18,2,2,6,7,'staff','ongoing','paid',100.00,100.00,0.00,'in_branch','treatment_10','09123456789','Phase 2C Multi Product Test',NULL,'phase2c-multi@test.local','2026-10-01','09:00:00','09:30:00',17,'2026-10-01 16:41:17','2026-10-01 16:43:36','2026-10-01 16:43:36'),(19,2,2,6,7,'staff','cancelled','paid',100.00,100.00,0.00,'in_branch','treatment_3','09123456789','Phase 2C Cancelled Test',NULL,'phase2c-cancelled@test.local','2026-10-01','09:00:00','09:30:00',17,'2026-10-01 16:43:52','2026-10-01 16:44:17','2026-10-01 16:44:17'),(20,2,2,6,7,'staff','completed','paid',100.00,100.00,0.00,'in_branch','package_1','09123456789','Phase 2C Package Test',NULL,'phase2c-package@test.local','2026-10-01','09:00:00','09:30:00',17,'2026-10-01 16:45:18','2026-10-01 16:45:38','2026-10-01 16:45:38'),(21,2,2,6,7,'staff','completed','paid',1400.00,1400.00,0.00,'in_branch','package_1','09123456789','Phase 3A Package Test',NULL,'phase3a-package@test.local','2026-10-01','09:00:00','10:00:00',17,'2026-10-01 16:57:00','2026-10-01 16:58:53','2026-10-01 16:58:53'),(22,2,2,6,7,'staff','completed','paid',1400.00,1400.00,0.00,'in_branch','package_1','09123456789','Phase 3A Package Test',NULL,'phase3a-package@test.local','2026-10-01','09:00:00','10:00:00',17,'2026-10-01 16:59:24','2026-10-01 17:02:23','2026-10-01 17:02:23'),(23,2,2,6,7,'staff','completed','paid',1400.00,1400.00,0.00,'in_branch','package_1','09123456789','Phase 3A Multiplier Test',NULL,'phase3a-multiplier@test.local','2026-10-01','09:00:00','10:00:00',17,'2026-10-01 17:02:54','2026-10-01 17:05:05','2026-10-01 17:05:05'),(24,2,2,6,7,'staff','completed','paid',1400.00,1400.00,0.00,'in_branch','package_1','09123456789','Phase 3B Multi Treatment Test',NULL,'phase3b@test.local','2026-10-01','09:00:00','10:00:00',17,'2026-10-01 17:07:22','2026-10-01 17:12:50','2026-10-01 17:12:50'),(25,2,2,6,7,'staff','ongoing','paid',1400.00,1400.00,0.00,'in_branch','package_1','09123456789','Phase 3B Rollback Test',NULL,'phase3b-rollback@test.local','2026-10-01','09:00:00','10:00:00',17,'2026-10-01 17:13:22','2026-10-01 17:14:06','2026-10-01 17:14:06'),(26,2,2,6,7,'staff','completed','paid',500.00,500.00,0.00,'in_branch','package_3','09123456789','Phase 3C Cross Branch Test',NULL,'phase3c@test.local','2026-10-01','09:00:00','10:00:00',17,'2026-10-01 17:15:51','2026-10-01 17:16:16','2026-10-01 17:16:16'),(27,2,2,NULL,7,NULL,'cancelled','unpaid',0.00,700.00,700.00,'in_branch','treatment_3','09357816489','Piolo Lingo',NULL,NULL,'2026-10-02','15:48:00','16:18:00',17,'2026-10-02 07:45:33','2026-10-02 07:55:00',NULL),(28,2,2,NULL,7,NULL,'completed','partially_paid',400.00,700.00,300.00,'in_branch','treatment_3','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-10-02','15:59:00','16:29:00',17,'2026-10-02 07:58:11','2026-10-02 14:04:21',NULL),(29,2,2,NULL,7,NULL,'completed','partially_paid',150.00,700.00,550.00,'in_branch','treatment_12','09357816489','Piolo Lingo',NULL,'lingopiolo@gmail.com','2026-10-02','16:05:00','16:06:00',9,'2026-10-02 08:04:38','2026-10-02 08:06:25',NULL),(30,4,6,NULL,NULL,'online','reserved','partially_paid',160.00,800.00,640.00,'in_branch','treatment_13','09357816489','Ericka Cedes',NULL,'ericha122c81@fzi.inovel26.com','2026-10-06','09:00:00','09:30:00',NULL,'2026-10-05 09:11:31','2026-10-05 09:11:31',NULL),(31,4,6,NULL,NULL,'online','completed','paid',1000.00,1000.00,0.00,'in_branch','treatment_14','09357816489','Ericka Cedes',NULL,'ericha122c81@fzi.inovel26.com','2026-10-05','17:17:00','17:18:00',NULL,'2026-10-05 09:15:48','2026-10-05 09:18:08',NULL);
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branch_product_stocks`
--

DROP TABLE IF EXISTS `branch_product_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branch_product_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `on_hand_quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `reorder_level` decimal(14,3) NOT NULL DEFAULT 0.000,
  `minimum_stock` decimal(14,3) DEFAULT NULL,
  `maximum_stock` decimal(14,3) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_product_stocks_branch_product_unique` (`branch_id`,`product_id`),
  KEY `branch_product_stocks_product_id_foreign` (`product_id`),
  KEY `branch_product_stocks_spa_id_branch_id_index` (`spa_id`,`branch_id`),
  CONSTRAINT `branch_product_stocks_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `branch_product_stocks_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `branch_product_stocks_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branch_product_stocks`
--

LOCK TABLES `branch_product_stocks` WRITE;
/*!40000 ALTER TABLE `branch_product_stocks` DISABLE KEYS */;
INSERT INTO `branch_product_stocks` VALUES (6,2,2,5,4000.000,1500.000,1000.000,6000.000,'2026-10-05 02:40:41','2026-10-05 04:06:56'),(7,2,2,6,500.000,2000.000,1000.000,5000.000,'2026-10-05 02:40:41','2026-10-05 04:06:56'),(8,2,2,7,10.000,30.000,20.000,80.000,'2026-10-05 02:40:41','2026-10-05 04:06:56'),(9,2,2,8,0.000,200.000,100.000,600.000,'2026-10-05 02:40:41','2026-10-05 04:06:56'),(10,2,3,5,1000.000,1000.000,500.000,5000.000,'2026-10-05 02:40:41','2026-10-05 04:06:56'),(11,2,3,6,0.000,1500.000,500.000,4000.000,'2026-10-05 02:40:41','2026-10-05 04:06:56'),(12,2,3,7,30.000,20.000,10.000,80.000,'2026-10-05 02:40:41','2026-10-05 04:06:56'),(13,2,3,8,0.000,200.000,100.000,600.000,'2026-10-05 02:40:41','2026-10-05 04:06:56');
/*!40000 ALTER TABLE `branch_product_stocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branch_profiles`
--

DROP TABLE IF EXISTS `branch_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branch_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned NOT NULL,
  `is_listed` tinyint(1) NOT NULL DEFAULT 0,
  `is_hiring` tinyint(1) NOT NULL DEFAULT 0,
  `hiring_note` varchar(150) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `gallery_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery_images`)),
  `gallery_captions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery_captions`)),
  `description` text DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `amenities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`amenities`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `branch_profiles_branch_id_foreign` (`branch_id`),
  CONSTRAINT `branch_profiles_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branch_profiles`
--

LOCK TABLES `branch_profiles` WRITE;
/*!40000 ALTER TABLE `branch_profiles` DISABLE KEYS */;
INSERT INTO `branch_profiles` VALUES (2,2,1,1,NULL,'branch_profiles/y6gYfg1U0AZRYzoquFwmm9DSxjpjUqrpSGQkMBuf.png','[\"branch_profiles\\/cXwU5moPgYEFLb77MsmWdyGGGwg9w79CRA8ECUBq.png\",\"branch_profiles\\/QYDE9KfePlbOBgFD3XSHFGki9XKretd03VJqwfQB.png\",\"branch_profiles\\/58BJlPJ89vhsIrHSBTEzVrgYTv2TcgnYNMqpc17i.png\",\"branch_profiles\\/NWCJHPPuoas2I26uQUja0oMsCbhIyyJPmPBumoLU.png\"]','[]','Indulge in a luxurious wellness experience designed to nourish your body and mind. From therapeutic massages to revitalizing foot spa treatments, we are dedicated to promoting comfort, relaxation, and overall well-being through exceptional service and a calming atmosphere.','09753305469','Colonel Jose S. Tagle Street, Palico I, Imus, Cavite, Calabarzon, 4103, Philippines',NULL,14.4305657,120.9402895,'[\"aircon\",\"private_rooms\",\"shower\",\"parking\"]','2026-06-28 13:45:53','2026-10-01 13:39:25',NULL),(3,3,0,0,NULL,'branch_profiles/njrrwsgfv6hYWeK99vhtqgazrem4qjHKmU4Sf2bx.png','[\"branch_profiles\\/GkHR3q1uPDwikb1xA4KTslRThhwXdGSdwduHlcgf.png\",\"branch_profiles\\/RCJHOnuodXNyh0hLcuzaovOurgEBPQQR6X50Z9rC.png\",\"branch_profiles\\/qbF7iR3zcfKR0C8rtIsUoyNQsKyyzaJqvPdrRlwD.png\",\"branch_profiles\\/WPfU4VYK7UzVIMKBDN2vXqIqMwFJTap6tzDZjXDE.png\"]',NULL,NULL,'09753305469','Cabezas Street, Transville Homes, San Agustin, Trece Martires, Cavite, Calabarzon, 4109, Philippines',NULL,14.2867420,120.8674125,'[\"aircon\",\"private_rooms\",\"shower\",\"parking\"]','2026-06-28 13:46:06','2026-06-28 15:02:29',NULL),(4,4,1,0,NULL,'branch_profiles/AhMdpWBUgvvfKOLGpL0fTgSLHdIVqB0HJh5aIr88.png','[\"branch_profiles\\/Ny4TDiiXivjgC2UH65ZUwEZJh1hgi0lGogxc3Fv0.png\",\"branch_profiles\\/wiUTG6G4y55aBS2oaSz1N35wQ9VNupoQmExR4zgN.png\",\"branch_profiles\\/qINNqifrIioerlGgXDSpDV56NU3uLUD2ScFTSGG4.png\",\"branch_profiles\\/PfpuADN5sdHv17YL0LfXUcNRopKL8wleWO4UIsB6.png\"]',NULL,'Escape the stress of everyday life and experience complete relaxation with our soothing spa treatments. Our skilled therapists provide personalized care in a peaceful environment, helping you restore balance, reduce tension, and leave feeling refreshed and rejuvenated.','09753305469','Crisanto Mendoza de los Reyes Avenue, Kaybagal South, Tagaytay, Cavite, Calabarzon, 4120, Philippines','Tagaytay',14.1019487,120.9410405,'[\"aircon\",\"private_rooms\",\"shower\",\"parking\"]','2026-06-28 14:25:24','2026-08-21 06:31:00',NULL),(5,5,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-28 14:25:41','2026-06-28 14:25:41',NULL),(6,6,1,0,NULL,'branch_profiles/zPKbWTPwZcHyYHmuh3yhzjCG7PrLkhvi4Eu1D8t8.png','[]','[]',NULL,NULL,'Isidro Mangubat Street, Zone 4, Poblacion, Dasmariñas, Cavite, Calabarzon, 4114, Philippines','Dasmariñas',14.3261474,120.9387505,'[\"aircon\",\"private_rooms\",\"shower\",\"parking\",\"wifi\",\"pet_friendly\"]','2026-10-05 08:15:12','2026-10-08 13:17:05',NULL),(7,7,0,0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-05 08:15:35','2026-10-05 08:15:35',NULL),(9,9,1,0,NULL,'branch_profiles/oslpVdx1kNMjlMVAjA2CS6OlfgZDmeDxyr7wzgVG.png','[]','[]','A relaxing sanctuary offering soothing spa treatments designed to refresh your body, calm your mind, and leave you feeling beautifully renewed.',NULL,'Gateway Business Park, Javalera, General Trias, Cavite, Calabarzon, 4107, Philippines','General Trias',14.2613884,120.9157848,'[\"aircon\",\"private_rooms\",\"shower\",\"parking\"]','2026-10-05 17:34:09','2026-10-09 17:13:31',NULL),(10,10,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'Paliparan Elementary School, Paliparan Road, Paliparan 2, Paliparan, Dasmariñas, Cavite, Calabarzon, 4114, Philippines','Dasmariñas',14.3009605,120.9926140,NULL,'2026-10-08 07:39:22','2026-10-08 07:39:22',NULL);
/*!40000 ALTER TABLE `branch_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branch_role_permissions`
--

DROP TABLE IF EXISTS `branch_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branch_role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned NOT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `role_name` varchar(255) NOT NULL,
  `permission_name` varchar(255) NOT NULL,
  `granted` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_role_perm_unique` (`branch_id`,`role_name`,`permission_name`),
  KEY `branch_role_permissions_spa_id_foreign` (`spa_id`),
  CONSTRAINT `branch_role_permissions_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `branch_role_permissions_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branch_role_permissions`
--

LOCK TABLES `branch_role_permissions` WRITE;
/*!40000 ALTER TABLE `branch_role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `branch_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `branches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `is_main` tinyint(1) NOT NULL DEFAULT 0,
  `has_workforce_finance_suite` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `min_daily_wage` decimal(10,2) DEFAULT NULL,
  `wage_order_ref` varchar(255) DEFAULT NULL,
  `min_wage_effective_from` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branches_spa_id_name_unique` (`spa_id`,`name`),
  CONSTRAINT `branches_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `branches`
--

LOCK TABLES `branches` WRITE;
/*!40000 ALTER TABLE `branches` DISABLE KEYS */;
INSERT INTO `branches` VALUES (2,2,'Marjo\'s Spa Imus','Imus',1,1,'2026-06-28 13:45:53','2026-10-03 02:33:26',NULL,600.00,'WO IVA-22','2026-09-01'),(3,2,'Marjo\'s Spa Carmona','Carmona',0,0,'2026-06-28 13:46:06','2026-10-01 15:50:09',NULL,NULL,NULL,NULL),(4,3,'Evan\'s Spa Tagaytay','Tagaytay',1,0,'2026-06-28 14:25:24','2026-06-28 14:25:24',NULL,NULL,NULL,NULL),(5,3,'Evan\'s Spa Bacoor','Bacoor',0,0,'2026-06-28 14:25:41','2026-06-28 14:25:41',NULL,NULL,NULL,NULL),(6,4,'Princess\'s Spa Dasma','Isidro Mangubat Street, Zone 4, Poblacion, Dasmariñas, Cavite, Calabarzon, 4114, Philippines',1,1,'2026-10-05 08:15:12','2026-10-08 13:17:05',NULL,NULL,NULL,NULL),(7,4,'Princess Spa Imus','Imus',0,0,'2026-10-05 08:15:35','2026-10-05 08:15:35',NULL,NULL,NULL,NULL),(9,5,'Jay\'s Spa Gentri','Gateway Business Park, Javalera, General Trias, Cavite, Calabarzon, 4107, Philippines',1,0,'2026-10-05 17:34:09','2026-10-09 17:00:48',NULL,NULL,NULL,NULL),(10,6,'Jay\'s Spa Gentri','Paliparan Elementary School, Paliparan Road, Paliparan 2, Paliparan, Dasmariñas, Cavite, Calabarzon, 4114, Philippines',1,0,'2026-10-08 07:39:22','2026-10-08 07:39:22',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `branches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('levictas-spa-wellness-cache-spatie.permission.cache','a:3:{s:5:\"alias\";a:4:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:10:\"guard_name\";s:1:\"r\";s:5:\"roles\";}s:11:\"permissions\";a:116:{i:0;a:3:{s:1:\"a\";i:1;s:1:\"b\";s:20:\"view owner dashboard\";s:1:\"c\";s:3:\"web\";}i:1;a:4:{s:1:\"a\";i:2;s:1:\"b\";s:20:\"view admin dashboard\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:2;a:4:{s:1:\"a\";i:3;s:1:\"b\";s:17:\"book appointments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:5;}}i:3;a:4:{s:1:\"a\";i:4;s:1:\"b\";s:17:\"view appointments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:2;i:1;i:3;i:2;i:4;i:3;i:5;}}i:4;a:4:{s:1:\"a\";i:5;s:1:\"b\";s:17:\"edit appointments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:5;}}i:5;a:4:{s:1:\"a\";i:6;s:1:\"b\";s:19:\"delete appointments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:6;a:4:{s:1:\"a\";i:7;s:1:\"b\";s:13:\"view schedule\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:2;i:1;i:3;i:2;i:4;i:3;i:5;i:4;i:7;}}i:7;a:4:{s:1:\"a\";i:8;s:1:\"b\";s:15:\"view attendance\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:2;i:1;i:3;i:2;i:5;i:3;i:7;}}i:8;a:4:{s:1:\"a\";i:9;s:1:\"b\";s:15:\"edit attendance\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:2;i:1;i:3;i:2;i:5;i:3;i:7;}}i:9;a:4:{s:1:\"a\";i:10;s:1:\"b\";s:19:\"view leave requests\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:2;i:1;i:3;i:2;i:4;i:3;i:5;i:4;i:7;}}i:10;a:4:{s:1:\"a\";i:11;s:1:\"b\";s:21:\"create leave requests\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:2;i:1;i:3;i:2;i:4;i:3;i:5;i:4;i:7;}}i:11;a:4:{s:1:\"a\";i:12;s:1:\"b\";s:19:\"edit leave requests\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:7;}}i:12;a:4:{s:1:\"a\";i:13;s:1:\"b\";s:21:\"delete leave requests\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:13;a:4:{s:1:\"a\";i:14;s:1:\"b\";s:13:\"view branches\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:2;i:1;i:3;i:2;i:5;i:3;i:7;}}i:14;a:4:{s:1:\"a\";i:15;s:1:\"b\";s:15:\"create branches\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:15;a:3:{s:1:\"a\";i:16;s:1:\"b\";s:13:\"edit branches\";s:1:\"c\";s:3:\"web\";}i:16;a:4:{s:1:\"a\";i:17;s:1:\"b\";s:15:\"delete branches\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:17;a:4:{s:1:\"a\";i:18;s:1:\"b\";s:10:\"view staff\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:2;i:1;i:3;i:2;i:5;i:3;i:7;}}i:18;a:4:{s:1:\"a\";i:19;s:1:\"b\";s:12:\"create staff\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:7;}}i:19;a:4:{s:1:\"a\";i:20;s:1:\"b\";s:10:\"edit staff\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:7;}}i:20;a:4:{s:1:\"a\";i:21;s:1:\"b\";s:12:\"delete staff\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:21;a:4:{s:1:\"a\";i:22;s:1:\"b\";s:13:\"view services\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:5;}}i:22;a:4:{s:1:\"a\";i:23;s:1:\"b\";s:17:\"create treatments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:23;a:4:{s:1:\"a\";i:24;s:1:\"b\";s:15:\"edit treatments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:24;a:4:{s:1:\"a\";i:25;s:1:\"b\";s:17:\"delete treatments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:25;a:4:{s:1:\"a\";i:26;s:1:\"b\";s:15:\"create packages\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:26;a:4:{s:1:\"a\";i:27;s:1:\"b\";s:13:\"edit packages\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:27;a:4:{s:1:\"a\";i:28;s:1:\"b\";s:15:\"delete packages\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:28;a:4:{s:1:\"a\";i:29;s:1:\"b\";s:12:\"view reports\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:8;}}i:29;a:4:{s:1:\"a\";i:30;s:1:\"b\";s:14:\"export reports\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:30;a:4:{s:1:\"a\";i:31;s:1:\"b\";s:21:\"view decision support\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:8;}}i:31;a:4:{s:1:\"a\";i:32;s:1:\"b\";s:14:\"view inventory\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:32;a:4:{s:1:\"a\";i:33;s:1:\"b\";s:19:\"view inventory logs\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:33;a:4:{s:1:\"a\";i:34;s:1:\"b\";s:22:\"create inventory items\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:34;a:4:{s:1:\"a\";i:35;s:1:\"b\";s:20:\"edit inventory items\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:35;a:4:{s:1:\"a\";i:36;s:1:\"b\";s:22:\"delete inventory items\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:36;a:4:{s:1:\"a\";i:37;s:1:\"b\";s:22:\"view product inventory\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:37;a:4:{s:1:\"a\";i:38;s:1:\"b\";s:24:\"create product inventory\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:38;a:4:{s:1:\"a\";i:39;s:1:\"b\";s:22:\"edit product inventory\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:39;a:4:{s:1:\"a\";i:40;s:1:\"b\";s:24:\"delete product inventory\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:40;a:4:{s:1:\"a\";i:41;s:1:\"b\";s:17:\"view product logs\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:41;a:4:{s:1:\"a\";i:42;s:1:\"b\";s:16:\"edit own profile\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:6:{i:0;i:2;i:1;i:3;i:2;i:4;i:3;i:5;i:4;i:7;i:5;i:8;}}i:42;a:4:{s:1:\"a\";i:43;s:1:\"b\";s:16:\"view spa profile\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:43;a:4:{s:1:\"a\";i:44;s:1:\"b\";s:16:\"edit spa profile\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:44;a:4:{s:1:\"a\";i:45;s:1:\"b\";s:21:\"view registered users\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:45;a:4:{s:1:\"a\";i:46;s:1:\"b\";s:21:\"edit registered users\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:46;a:4:{s:1:\"a\";i:47;s:1:\"b\";s:23:\"delete registered users\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:47;a:4:{s:1:\"a\";i:48;s:1:\"b\";s:20:\"view registered spas\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:48;a:4:{s:1:\"a\";i:49;s:1:\"b\";s:20:\"edit registered spas\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:49;a:4:{s:1:\"a\";i:50;s:1:\"b\";s:22:\"verify registered spas\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:50;a:4:{s:1:\"a\";i:51;s:1:\"b\";s:24:\"change spa subscriptions\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:51;a:4:{s:1:\"a\";i:52;s:1:\"b\";s:17:\"view system roles\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:52;a:4:{s:1:\"a\";i:53;s:1:\"b\";s:17:\"edit system roles\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:53;a:4:{s:1:\"a\";i:54;s:1:\"b\";s:18:\"edit admin profile\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:54;a:4:{s:1:\"a\";i:55;s:1:\"b\";s:22:\"manage system settings\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:55;a:4:{s:1:\"a\";i:57;s:1:\"b\";s:11:\"view hiring\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:56;a:4:{s:1:\"a\";i:58;s:1:\"b\";s:13:\"create hiring\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:57;a:4:{s:1:\"a\";i:59;s:1:\"b\";s:11:\"edit hiring\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:58;a:4:{s:1:\"a\";i:60;s:1:\"b\";s:13:\"delete hiring\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:59;a:4:{s:1:\"a\";i:61;s:1:\"b\";s:17:\"view applications\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:60;a:4:{s:1:\"a\";i:62;s:1:\"b\";s:17:\"edit applications\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:61;a:4:{s:1:\"a\";i:63;s:1:\"b\";s:19:\"delete applications\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:62;a:4:{s:1:\"a\";i:64;s:1:\"b\";s:15:\"view interviews\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:63;a:4:{s:1:\"a\";i:65;s:1:\"b\";s:17:\"create interviews\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:64;a:4:{s:1:\"a\";i:66;s:1:\"b\";s:15:\"edit interviews\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:65;a:4:{s:1:\"a\";i:67;s:1:\"b\";s:17:\"delete interviews\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:66;a:4:{s:1:\"a\";i:68;s:1:\"b\";s:12:\"view payroll\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:67;a:4:{s:1:\"a\";i:69;s:1:\"b\";s:12:\"edit payroll\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:68;a:4:{s:1:\"a\";i:70;s:1:\"b\";s:16:\"view deployments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:7;}}i:69;a:4:{s:1:\"a\";i:71;s:1:\"b\";s:18:\"create deployments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:70;a:4:{s:1:\"a\";i:72;s:1:\"b\";s:19:\"approve deployments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:71;a:4:{s:1:\"a\";i:73;s:1:\"b\";s:18:\"delete deployments\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:7;}}i:72;a:4:{s:1:\"a\";i:75;s:1:\"b\";s:12:\"view revenue\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:73;a:4:{s:1:\"a\";i:76;s:1:\"b\";s:12:\"view billing\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:74;a:4:{s:1:\"a\";i:77;s:1:\"b\";s:14:\"create billing\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:75;a:4:{s:1:\"a\";i:78;s:1:\"b\";s:12:\"edit billing\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:76;a:4:{s:1:\"a\";i:79;s:1:\"b\";s:14:\"delete billing\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:77;a:4:{s:1:\"a\";i:80;s:1:\"b\";s:22:\"view finance inventory\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:78;a:4:{s:1:\"a\";i:81;s:1:\"b\";s:22:\"edit finance inventory\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:79;a:4:{s:1:\"a\";i:82;s:1:\"b\";s:23:\"view business dashboard\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:6:{i:0;i:2;i:1;i:3;i:2;i:4;i:3;i:5;i:4;i:7;i:5;i:8;}}i:80;a:4:{s:1:\"a\";i:83;s:1:\"b\";s:19:\"view dashboard kpis\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:2;i:1;i:3;i:2;i:5;i:3;i:7;i:4;i:8;}}i:81;a:4:{s:1:\"a\";i:84;s:1:\"b\";s:22:\"view dashboard revenue\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:8;}}i:82;a:4:{s:1:\"a\";i:85;s:1:\"b\";s:23:\"view dashboard timeline\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:5;}}i:83;a:4:{s:1:\"a\";i:86;s:1:\"b\";s:31:\"view dashboard therapist status\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:84;a:4:{s:1:\"a\";i:87;s:1:\"b\";s:21:\"view dashboard alerts\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:5;}}i:85;a:4:{s:1:\"a\";i:88;s:1:\"b\";s:29:\"view dashboard booking button\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:5;}}i:86;a:4:{s:1:\"a\";i:89;s:1:\"b\";s:23:\"view dashboard my today\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:4;}}i:87;a:4:{s:1:\"a\";i:90;s:1:\"b\";s:32:\"request appointment reassignment\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:4;}}i:88;a:4:{s:1:\"a\";i:91;s:1:\"b\";s:11:\"view promos\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:5;}}i:89;a:4:{s:1:\"a\";i:92;s:1:\"b\";s:13:\"create promos\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:90;a:4:{s:1:\"a\";i:93;s:1:\"b\";s:11:\"edit promos\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:91;a:4:{s:1:\"a\";i:94;s:1:\"b\";s:13:\"delete promos\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:92;a:4:{s:1:\"a\";i:95;s:1:\"b\";s:19:\"edit branch general\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:2;}}i:93;a:4:{s:1:\"a\";i:96;s:1:\"b\";s:17:\"edit branch hours\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:94;a:4:{s:1:\"a\";i:97;s:1:\"b\";s:19:\"edit branch profile\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:2;i:1;i:3;i:2;i:5;}}i:95;a:4:{s:1:\"a\";i:98;s:1:\"b\";s:20:\"view stock transfers\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:96;a:4:{s:1:\"a\";i:99;s:1:\"b\";s:22:\"create stock transfers\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:97;a:4:{s:1:\"a\";i:100;s:1:\"b\";s:23:\"process stock transfers\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:98;a:4:{s:1:\"a\";i:101;s:1:\"b\";s:22:\"cancel stock transfers\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:99;a:4:{s:1:\"a\";i:102;s:1:\"b\";s:14:\"view suppliers\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:100;a:4:{s:1:\"a\";i:103;s:1:\"b\";s:16:\"create suppliers\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:101;a:4:{s:1:\"a\";i:104;s:1:\"b\";s:14:\"edit suppliers\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:102;a:4:{s:1:\"a\";i:105;s:1:\"b\";s:24:\"manage supplier products\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:103;a:4:{s:1:\"a\";i:106;s:1:\"b\";s:22:\"view purchase requests\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:104;a:4:{s:1:\"a\";i:107;s:1:\"b\";s:24:\"create purchase requests\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:105;a:4:{s:1:\"a\";i:108;s:1:\"b\";s:24:\"review purchase requests\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:106;a:4:{s:1:\"a\";i:109;s:1:\"b\";s:20:\"view purchase orders\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:107;a:4:{s:1:\"a\";i:110;s:1:\"b\";s:22:\"create purchase orders\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:108;a:4:{s:1:\"a\";i:111;s:1:\"b\";s:22:\"manage purchase orders\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:109;a:4:{s:1:\"a\";i:112;s:1:\"b\";s:19:\"view goods receipts\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:110;a:4:{s:1:\"a\";i:113;s:1:\"b\";s:13:\"receive goods\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:111;a:4:{s:1:\"a\";i:114;s:1:\"b\";s:18:\"view replenishment\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:3;}}i:112;a:4:{s:1:\"a\";i:115;s:1:\"b\";s:17:\"view vendor bills\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:113;a:4:{s:1:\"a\";i:116;s:1:\"b\";s:19:\"create vendor bills\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:114;a:4:{s:1:\"a\";i:117;s:1:\"b\";s:18:\"match vendor bills\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}i:115;a:4:{s:1:\"a\";i:118;s:1:\"b\";s:17:\"edit vendor bills\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:2;i:1;i:8;}}}s:5:\"roles\";a:7:{i:0;a:3:{s:1:\"a\";i:1;s:1:\"b\";s:5:\"admin\";s:1:\"c\";s:3:\"web\";}i:1;a:3:{s:1:\"a\";i:2;s:1:\"b\";s:5:\"owner\";s:1:\"c\";s:3:\"web\";}i:2;a:3:{s:1:\"a\";i:3;s:1:\"b\";s:7:\"manager\";s:1:\"c\";s:3:\"web\";}i:3;a:3:{s:1:\"a\";i:5;s:1:\"b\";s:12:\"receptionist\";s:1:\"c\";s:3:\"web\";}i:4;a:3:{s:1:\"a\";i:4;s:1:\"b\";s:9:\"therapist\";s:1:\"c\";s:3:\"web\";}i:5;a:3:{s:1:\"a\";i:7;s:1:\"b\";s:2:\"hr\";s:1:\"c\";s:3:\"web\";}i:6;a:3:{s:1:\"a\";i:8;s:1:\"b\";s:7:\"finance\";s:1:\"c\";s:3:\"web\";}}}',1791651587);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `commission_rules`
--

DROP TABLE IF EXISTS `commission_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commission_rules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `target_type` enum('treatment','package','default') NOT NULL,
  `target_id` bigint(20) unsigned DEFAULT NULL,
  `method` enum('percent','flat') NOT NULL,
  `value` decimal(10,4) NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `commission_rules_spa_id_foreign` (`spa_id`),
  KEY `commission_rules_branch_id_foreign` (`branch_id`),
  KEY `commission_rules_created_by_foreign` (`created_by`),
  CONSTRAINT `commission_rules_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `commission_rules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `commission_rules_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `commission_rules`
--

LOCK TABLES `commission_rules` WRITE;
/*!40000 ALTER TABLE `commission_rules` DISABLE KEYS */;
INSERT INTO `commission_rules` VALUES (1,2,NULL,'default',NULL,'percent',10.0000,'2026-09-01',NULL,7,'2026-10-03 02:34:47','2026-10-03 02:34:47');
/*!40000 ALTER TABLE `commission_rules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` varchar(255) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `position` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `hire_date` date NOT NULL,
  `salary` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive','terminated','on_leave') NOT NULL DEFAULT 'active',
  `address` text DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `termination_reason` text DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_employee_id_unique` (`employee_id`),
  UNIQUE KEY `employees_email_unique` (`email`),
  KEY `employees_user_id_foreign` (`user_id`),
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','on_review','accepted','rejected') NOT NULL DEFAULT 'pending',
  `review_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_spa_id_foreign` (`spa_id`),
  KEY `expenses_branch_id_foreign` (`branch_id`),
  KEY `expenses_requested_by_foreign` (`requested_by`),
  KEY `expenses_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `expenses_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `expenses_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_receipt_items`
--

DROP TABLE IF EXISTS `goods_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipt_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `goods_receipt_id` bigint(20) unsigned NOT NULL,
  `purchase_order_item_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `product_batch_id` bigint(20) unsigned DEFAULT NULL,
  `received_quantity` decimal(14,3) NOT NULL,
  `accepted_quantity` decimal(14,3) NOT NULL,
  `rejected_quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `unit` varchar(255) DEFAULT NULL,
  `conversion_factor` decimal(14,3) NOT NULL DEFAULT 1.000,
  `inventory_quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `batch_number` varchar(100) DEFAULT NULL,
  `manufactured_at` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_receipt_items_goods_receipt_id_foreign` (`goods_receipt_id`),
  KEY `goods_receipt_items_product_batch_id_foreign` (`product_batch_id`),
  KEY `grn_items_po_item_receipt_idx` (`purchase_order_item_id`,`goods_receipt_id`),
  KEY `grn_items_product_batch_idx` (`product_id`,`batch_number`),
  CONSTRAINT `goods_receipt_items_goods_receipt_id_foreign` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goods_receipt_items_product_batch_id_foreign` FOREIGN KEY (`product_batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goods_receipt_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `goods_receipt_items_purchase_order_item_id_foreign` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_receipt_items`
--

LOCK TABLES `goods_receipt_items` WRITE;
/*!40000 ALTER TABLE `goods_receipt_items` DISABLE KEYS */;
INSERT INTO `goods_receipt_items` VALUES (11,13,8,5,37,6.000,6.000,0.000,'bottle',500.000,3000.000,'LOT-LAV-IMUS-01','2026-08-05','2027-04-05',NULL,'All delivered units passed inspection.','2026-09-22 02:40:41','2026-10-05 04:06:56'),(12,14,10,7,41,1.000,1.000,0.000,'pack',10.000,10.000,'LOT-SHEET-IMUS-01','2026-09-05','2027-10-05',NULL,'One pack accepted. Remaining pack is still pending delivery.','2026-09-28 02:40:41','2026-10-05 04:06:56');
/*!40000 ALTER TABLE `goods_receipt_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `goods_receipts`
--

DROP TABLE IF EXISTS `goods_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goods_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `received_by` bigint(20) unsigned NOT NULL,
  `delivery_reference` varchar(255) DEFAULT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goods_receipts_branch_id_foreign` (`branch_id`),
  KEY `goods_receipts_received_by_foreign` (`received_by`),
  KEY `grn_spa_branch_received_idx` (`spa_id`,`branch_id`,`received_at`),
  KEY `grn_po_received_idx` (`purchase_order_id`,`received_at`),
  CONSTRAINT `goods_receipts_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goods_receipts_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `goods_receipts_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`),
  CONSTRAINT `goods_receipts_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `goods_receipts`
--

LOCK TABLES `goods_receipts` WRITE;
/*!40000 ALTER TABLE `goods_receipts` DISABLE KEYS */;
INSERT INTO `goods_receipts` VALUES (13,2,2,8,7,'DR-LAV-001','2026-09-22 02:40:41','Complete delivery. All six bottles were accepted.','2026-09-22 02:40:41','2026-09-22 02:40:41'),(14,2,2,10,7,'DR-SHEET-001','2026-09-28 02:40:41','Partial delivery. One of two packs was delivered.','2026-09-28 02:40:41','2026-09-28 02:40:41');
/*!40000 ALTER TABLE `goods_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `interviews`
--

DROP TABLE IF EXISTS `interviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `interviews` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `applicant_id` bigint(20) unsigned NOT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `interviewed_by` bigint(20) unsigned NOT NULL,
  `interview_date` date NOT NULL,
  `interview_time` time NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `staff_account_created` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `interviews_applicant_id_unique` (`applicant_id`),
  KEY `interviews_spa_id_foreign` (`spa_id`),
  KEY `interviews_branch_id_foreign` (`branch_id`),
  KEY `interviews_interviewed_by_foreign` (`interviewed_by`),
  CONSTRAINT `interviews_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `interviews_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `interviews_interviewed_by_foreign` FOREIGN KEY (`interviewed_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `interviews_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `interviews`
--

LOCK TABLES `interviews` WRITE;
/*!40000 ALTER TABLE `interviews` DISABLE KEYS */;
INSERT INTO `interviews` VALUES (2,7,2,2,7,'2026-10-05','09:00:00','rejected',NULL,'Insufficient experience for the therapist position.',0,'2026-10-04 13:47:59','2026-10-04 14:44:30',NULL),(3,6,2,2,7,'2026-10-05','09:00:00','rejected',NULL,'Insufficient experience for the therapist position.',0,'2026-10-04 13:50:50','2026-10-04 14:52:08',NULL),(4,8,2,2,7,'2026-10-12','10:00:00','approved',NULL,NULL,0,'2026-10-04 14:55:49','2026-10-04 14:56:44',NULL),(5,4,2,2,7,'2026-10-12','10:00:00','pending',NULL,NULL,0,'2026-10-04 14:56:29','2026-10-04 14:56:29',NULL);
/*!40000 ALTER TABLE `interviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_postings`
--

DROP TABLE IF EXISTS `job_postings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_postings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `status` enum('open','closed','draft') NOT NULL DEFAULT 'open',
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `job_postings_spa_id_foreign` (`spa_id`),
  KEY `job_postings_branch_id_foreign` (`branch_id`),
  KEY `job_postings_created_by_foreign` (`created_by`),
  CONSTRAINT `job_postings_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_postings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_postings_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_postings`
--

LOCK TABLES `job_postings` WRITE;
/*!40000 ALTER TABLE `job_postings` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_postings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `leave_type` enum('sick','emergency','vacation','personal','other') NOT NULL DEFAULT 'sick',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `leave_requests_branch_id_foreign` (`branch_id`),
  KEY `leave_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `leave_requests_spa_id_branch_id_status_index` (`spa_id`,`branch_id`,`status`),
  KEY `leave_requests_user_id_status_index` (`user_id`,`status`),
  CONSTRAINT `leave_requests_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leave_requests_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leave_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
INSERT INTO `leave_requests` VALUES (1,9,2,2,'sick','2026-08-08','2026-08-09','i got a flu','rejected','SEND ME A PROOF MESSENGER THEN REQ AGAIN',7,'2026-08-08 11:07:00','2026-08-08 11:04:41','2026-08-08 11:07:00');
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_01_13_110520_create_permission_tables',1),(5,'2026_01_13_141236_add_phone_and_status_to_users_table',1),(6,'2026_01_14_140149_create_posts_table',1),(7,'2026_01_14_150257_create_employees_table',1),(8,'2026_01_18_000000_create_spas_table',1),(9,'2026_01_18_000001_create_branches_table',1),(10,'2026_01_18_000002_create_operating_hours_table',1),(11,'2026_01_18_141020_create_staff_table',1),(12,'2026_01_18_151521_create_bookings_table',1),(13,'2026_01_22_032556_create_treatments_table',1),(14,'2026_01_22_032602_create_packages_table',1),(15,'2026_01_26_143832_create_staff_availabilities_table',1),(16,'2026_01_27_034135_create_schedules_table',1),(17,'2026_01_27_071559_create_package_treatment_table',1),(18,'2026_02_25_152421_create_products_table',1),(19,'2026_02_25_153101_product_logs_table',1),(20,'2026_02_25_161944_add_unit_to_products_table',1),(21,'2026_02_26_181716_create_branch_profiles_table',1),(22,'2026_03_03_190436_add_customer_user_id_to_bookings_table',1),(23,'2026_03_03_211659_add_has_home_service_to_branches_table',1),(24,'2026_03_15_120813_create_subscriptions_table',1),(25,'2026_03_17_202914_create_job_postings_table',1),(26,'2026_03_17_202941_create_applicants_table',1),(27,'2026_03_17_202953_create_interviews_table',1),(28,'2026_03_17_203000_create_staff_attendance_table',1),(29,'2026_03_17_203007_create_payroll_table',1),(30,'2026_03_17_203012_add_salary_fields_to_staff_table',1),(31,'2026_03_17_210322_add_applicant_fields_to_applicants_table',1),(32,'2026_03_21_005124_create_spa_verification_documents_table',1),(33,'2026_03_21_012625_update_spas_table',1),(34,'2026_03_22_024614_drop_has_home_service_from_branches_table',1),(35,'2026_03_22_120814_create_branch_role_permissions_table',1),(36,'2026_03_22_122916_add_payment_fields_to_bookings_table',1),(37,'2026_03_22_123315_create_online_reservation_payments_table',1),(38,'2026_03_24_032442_add_soft_deletes_to_admin_tables',1),(39,'2026_03_24_041025_add_soft_deletes_to_business_tables',1),(40,'2026_03_24_041401_add_soft_deletes_to_hr_and_finance_tables',1),(41,'2026_03_25_025942_add_workforce_finance_suite_to_branches_table',1),(42,'2026_03_26_020352_make_job_posting_id_nullable',1),(43,'2026_03_26_023812_update_applicants_table_for_new_application_fields',1),(44,'2026_03_30_120706_replace_name_with_name_parts_on_users_table',1),(45,'2026_04_01_102259_create_reschedule_requests_table',1),(46,'2026_04_02_135954_create_personal_access_tokens_table',1),(47,'2026_04_03_000545_add_address_to_users_table',1),(48,'2026_04_04_155610_create_staff_branch_deployments_table',1),(49,'2026_04_05_004015_create_ratings_table',1),(50,'2026_04_05_053014_create_expenses_table',1),(51,'2026_07_01_150700_add_image_path_to_treatments_and_packages_tables',2),(52,'2026_07_01_201120_add_is_hiring_to_branch_profiles_table',3),(53,'2026_08_02_001350_create_reassignment_requests_table',4),(54,'2026_08_03_000000_add_expiry_notice_sent_at_to_subscriptions_table',5),(55,'2026_08_03_000001_add_staff_response_to_staff_branch_deployments_table',6),(56,'2026_08_05_191321_add_time_tracking_to_staff_attendance_table',7),(57,'2026_08_05_191633_create_leave_requests_table',7),(58,'2026_08_05_191730_add_leave_request_id_to_reassignment_requests_table',7),(59,'2026_08_15_135429_add_city_to_branch_profiles_table',8),(60,'2026_08_17_215853_add_resume_path_to_applicants_table',9),(61,'2026_08_21_151445_create_promos_table',10),(62,'2026_08_21_152904_add_soft_deletes_to_promos_table',11),(63,'2026_08_21_163304_add_unit_value_to_products_table',12),(64,'2026_08_30_195008_add_spa_rating_columns_to_ratings_table',13),(65,'2026_08_31_230236_add_gallery_captions_to_branch_profiles_table',14),(66,'2026_09_06_215656_add_payment_details_to_subscriptions_table',15),(67,'2026_09_07_210200_create_rating_photos_table',16),(70,'2026_09_30_160245_add_erp_master_fields_to_products_table',17),(71,'2026_09_30_160257_create_branch_product_stocks_table',17),(72,'2026_09_30_160304_create_product_batches_table',17),(73,'2026_09_30_160309_create_stock_movements_table',17),(74,'2026_09_30_160318_migrate_legacy_product_stock_to_branch_product_stocks',17),(76,'2026_09_30_203134_backfill_unbatched_inventory_into_product_batches',18),(77,'2026_10_01_170333_create_stock_transfers_table',18),(78,'2026_10_01_170340_create_stock_transfer_items_table',18),(79,'2026_10_01_190333_update_stock_transfer_foreign_key_delete_rules',19),(80,'2026_10_01_225909_create_treatment_recipe_items_table',20),(81,'2026_10_02_000157_create_booking_consumptions_table',21),(82,'2026_10_02_000203_create_booking_consumption_items_table',21),(83,'2026_10_02_005015_add_package_lineage_to_booking_consumptions',22),(84,'2026_10_02_015031_create_suppliers_table',23),(85,'2026_10_02_015043_create_supplier_products_table',23),(86,'2026_10_02_025340_create_purchase_requests_table',24),(87,'2026_10_02_025344_create_purchase_request_items_table',24),(88,'2026_10_02_025848_create_purchase_request_status_histories_table',25),(89,'2026_10_02_112438_create_purchase_orders_table',26),(90,'2026_10_02_112441_create_purchase_order_items_table',26),(91,'2026_10_02_112446_create_purchase_order_status_histories_table',26),(92,'2026_10_02_121630_create_goods_receipts_table',27),(93,'2026_10_02_121634_create_goods_receipt_items_table',27),(94,'2026_10_02_233029_create_vendor_bills_table',28),(95,'2026_10_02_233053_create_vendor_bill_items_table',28),(96,'2026_10_02_233128_add_unit_cost_to_purchase_order_items_table',28),(97,'2026_09_24_234350_add_payroll_columns_to_spas_branches_staff',29),(98,'2026_09_24_234511_create_staff_pay_profiles_table',29),(99,'2026_09_24_234630_create_staff_recurring_items_table',29),(100,'2026_09_24_234713_create_commission_rules_table',29),(101,'2026_09_24_234806_create_payroll_runs_table',29),(102,'2026_09_24_234903_create_payslips_table',29),(103,'2026_09_25_010159_create_payslip_lines_table',29),(104,'2026_09_29_155443_add_review_to_payroll_runs_table',29),(105,'2026_09_29_235710_drop_legacy_payroll_table',29),(106,'2026_10_01_165135_drop_monthly_rate_divisor_from_spas',29),(107,'2026_10_04_210458_add_applicant_id_to_staff_table',30),(108,'2026_10_04_210641_make_job_posting_id_nullable_on_applicants_table',30),(109,'2026_10_04_212122_add_unique_applicant_id_to_interviews_table',30),(110,'2026_10_04_223012_add_rejection_reason_to_interviews_table',31),(111,'2026_10_05_223554_add_suffix_to_users_table',32),(112,'2026_10_05_225534_add_email_verification_otp_fields_to_users_table',33),(113,'2026_10_06_000646_create_pending_registrations_table',34),(114,'2026_10_06_003127_add_unique_constraint_to_name_on_spas_table',35),(115,'2026_10_06_012829_add_unique_spa_branch_name_constraint_to_branches_table',36),(116,'2026_10_06_015143_add_business_permit_to_spa_verification_document_type',37),(117,'2026_10_06_140057_add_expiry_fields_to_spa_verification_documents_table',38),(118,'2026_10_06_140123_create_spa_verification_document_histories_table',39),(119,'2026_10_06_183729_add_declared_and_ocr_expiry_dates_to_verification_documents',40),(120,'2026_10_08_010152_add_trial_fields_to_spas_table',41),(121,'2026_10_08_010206_add_plan_fields_to_subscriptions_table',41),(122,'2026_10_08_010234_convert_professional_plans_to_premium',42);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint(20) unsigned NOT NULL,
  `model_type` varchar(255) NOT NULL,
  `model_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(1,'App\\Models\\User',16),(2,'App\\Models\\User',2),(2,'App\\Models\\User',7),(2,'App\\Models\\User',12),(2,'App\\Models\\User',22),(2,'App\\Models\\User',28),(2,'App\\Models\\User',29),(2,'App\\Models\\User',30),(2,'App\\Models\\User',34),(3,'App\\Models\\User',8),(3,'App\\Models\\User',10),(3,'App\\Models\\User',24),(4,'App\\Models\\User',3),(4,'App\\Models\\User',9),(4,'App\\Models\\User',17),(4,'App\\Models\\User',23),(6,'App\\Models\\User',4),(6,'App\\Models\\User',5),(6,'App\\Models\\User',6),(6,'App\\Models\\User',11),(6,'App\\Models\\User',13),(6,'App\\Models\\User',14),(6,'App\\Models\\User',15),(6,'App\\Models\\User',20),(6,'App\\Models\\User',21),(6,'App\\Models\\User',27),(6,'App\\Models\\User',31),(6,'App\\Models\\User',32),(6,'App\\Models\\User',33),(6,'App\\Models\\User',35),(7,'App\\Models\\User',18),(7,'App\\Models\\User',25),(8,'App\\Models\\User',19),(8,'App\\Models\\User',26);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `online_reservation_payments`
--

DROP TABLE IF EXISTS `online_reservation_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `online_reservation_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `customer_phone` varchar(255) NOT NULL,
  `customer_address` text DEFAULT NULL,
  `bookable_id` bigint(20) unsigned NOT NULL,
  `bookable_type` varchar(255) NOT NULL,
  `bookable_name` varchar(255) NOT NULL,
  `full_amount` decimal(10,2) NOT NULL,
  `downpayment_amount` decimal(10,2) NOT NULL,
  `service_type` enum('in_branch','in_home') NOT NULL,
  `appointment_date` date NOT NULL,
  `start_time` time NOT NULL,
  `paymongo_checkout_session_id` varchar(255) DEFAULT NULL,
  `paymongo_payment_intent_id` varchar(255) DEFAULT NULL,
  `paymongo_payment_id` varchar(255) DEFAULT NULL,
  `payment_reference` varchar(255) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed','expired','cancelled') NOT NULL DEFAULT 'pending',
  `reservation_status` enum('awaiting_payment','reserved','failed','cancelled') NOT NULL DEFAULT 'awaiting_payment',
  `paymongo_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`paymongo_payload`)),
  `paid_at` timestamp NULL DEFAULT NULL,
  `booking_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `online_reservation_payments_paymongo_checkout_session_id_unique` (`paymongo_checkout_session_id`),
  KEY `online_reservation_payments_user_id_foreign` (`user_id`),
  KEY `online_reservation_payments_spa_id_foreign` (`spa_id`),
  KEY `online_reservation_payments_branch_id_foreign` (`branch_id`),
  KEY `online_reservation_payments_booking_id_foreign` (`booking_id`),
  CONSTRAINT `online_reservation_payments_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `online_reservation_payments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `online_reservation_payments_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `online_reservation_payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `online_reservation_payments`
--

LOCK TABLES `online_reservation_payments` WRITE;
/*!40000 ALTER TABLE `online_reservation_payments` DISABLE KEYS */;
INSERT INTO `online_reservation_payments` VALUES (1,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09123456789',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-06-29','10:00:00','cs_3629d69e3c056b97b4660a9b',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_3629d69e3c056b97b4660a9b\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=1\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/3629d69e3c056b97b4660a9b\",\"client_key\":\"cs_3629d69e3c056b97b4660a9b_client_03e6f35e075f7d8448c3e750\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"1\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_uAJSmeJCKFoNPpx6RJnoz9xX\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_uAJSmeJCKFoNPpx6RJnoz9xX_client_UBsbLWatt9aPmJYkdzpfLnSv\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"1\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"gcash\",\"paymaya\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1782659742,\"updated_at\":1782659742}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=1\",\"created_at\":1782659742,\"updated_at\":1782659742}}}',NULL,NULL,'2026-06-28 15:15:52','2026-06-28 15:15:53'),(2,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-06-29','10:00:00','cs_377347a083f07f4ffbf3a9fa',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_377347a083f07f4ffbf3a9fa\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=2\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/377347a083f07f4ffbf3a9fa\",\"client_key\":\"cs_377347a083f07f4ffbf3a9fa_client_51ab09a4fa0a72effc814b63\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"2\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_u93a2vq55tk3LGBD4w5eJRpc\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_u93a2vq55tk3LGBD4w5eJRpc_client_USaREF78HH5rnZCeCHCsaX4x\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"2\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1782659867,\"updated_at\":1782659867}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=2\",\"created_at\":1782659867,\"updated_at\":1782659867}}}',NULL,NULL,'2026-06-28 15:17:57','2026-06-28 15:17:57'),(3,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-06-29','11:00:00','cs_5f6ceed01fdc87f7236a8c91',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_5f6ceed01fdc87f7236a8c91\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=3\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/5f6ceed01fdc87f7236a8c91\",\"client_key\":\"cs_5f6ceed01fdc87f7236a8c91_client_2319a4a3de4dd02977b3d5cd\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"3\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_Mh7FrfQPSY3Ar9nMhq2acyy5\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_Mh7FrfQPSY3Ar9nMhq2acyy5_client_n6ciZRr6wsVEd6q6LprkroPH\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"3\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1782659934,\"updated_at\":1782659934}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=3\",\"created_at\":1782659934,\"updated_at\":1782659934}}}',NULL,NULL,'2026-06-28 15:19:04','2026-06-28 15:19:05'),(4,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-06-29','10:00:00','cs_629afbe9d96a07a7b6e96e5b',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_629afbe9d96a07a7b6e96e5b\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=4\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/629afbe9d96a07a7b6e96e5b\",\"client_key\":\"cs_629afbe9d96a07a7b6e96e5b_client_da87d625cbc77bb5123edb84\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"4\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_mwW7Qa4di7Dj1hVnH3zPuiUX\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_mwW7Qa4di7Dj1hVnH3zPuiUX_client_mUVjKX6eATaMnY3Sr7u4SVtD\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"4\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1782660471,\"updated_at\":1782660471}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=4\",\"created_at\":1782660471,\"updated_at\":1782660471}}}',NULL,NULL,'2026-06-28 15:28:01','2026-06-28 15:28:02'),(5,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-06-29','11:00:00','cs_a45a275045038220772d241d',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_a45a275045038220772d241d\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=5\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/a45a275045038220772d241d\",\"client_key\":\"cs_a45a275045038220772d241d_client_e3672ff2f0e582021f32826f\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"5\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_vVRedvkXKMX3knApVZokyPWN\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_vVRedvkXKMX3knApVZokyPWN_client_NAqhD5QthmcPqW5jaLt56JRy\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"5\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1782660556,\"updated_at\":1782660556}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=5\",\"created_at\":1782660556,\"updated_at\":1782660556}}}',NULL,NULL,'2026-06-28 15:29:27','2026-06-28 15:29:27'),(6,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-06-29','11:00:00','cs_c79101404d22a553778b41ea',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_c79101404d22a553778b41ea\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=6\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/c79101404d22a553778b41ea\",\"client_key\":\"cs_c79101404d22a553778b41ea_client_e3d5762d11e95297ed5fe2e0\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"6\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_Bk4W9noA6EUNEh8Q3TKGZ1pf\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_Bk4W9noA6EUNEh8Q3TKGZ1pf_client_nL1wTQkwrK2xgZLqFrXzwBYT\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"6\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1782661322,\"updated_at\":1782661322}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=6\",\"created_at\":1782661322,\"updated_at\":1782661322}}}',NULL,NULL,'2026-06-28 15:42:13','2026-06-28 15:42:13'),(7,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-06-29','11:00:00','cs_0a0e9b0dedbd737e93a7b2e1','pi_evXQtD1z3TH4mUpg2e3KRSRq','pay_UgjLCvZojWWtuC7RuXkzAkwB',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_MQKLW2WYEi74RdWDLm1wa8bA\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_0a0e9b0dedbd737e93a7b2e1\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=7\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/0a0e9b0dedbd737e93a7b2e1\",\"client_key\":\"cs_0a0e9b0dedbd737e93a7b2e1_client_a94f1d37e41bda3e04ca7301\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"7\",\"spa_id\":\"2\"},\"paid_at\":1782661536,\"payments\":[{\"id\":\"pay_UgjLCvZojWWtuC7RuXkzAkwB\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_yrPizygmD5Kqt8DD5zCrqc1K\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":null},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"7\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_evXQtD1z3TH4mUpg2e3KRSRq\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_jsN1WQ7EFnTW6AFsTJtiw4Mg\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1782896400,\"created_at\":1782661536,\"credited_at\":1783299600,\"paid_at\":1782661536,\"updated_at\":1782661536}}],\"payment_intent\":{\"id\":\"pi_evXQtD1z3TH4mUpg2e3KRSRq\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_evXQtD1z3TH4mUpg2e3KRSRq_client_EjNsvtGqyP5bqfRSDmi4KXVJ\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"7\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_UgjLCvZojWWtuC7RuXkzAkwB\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_yrPizygmD5Kqt8DD5zCrqc1K\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":null},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"7\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_evXQtD1z3TH4mUpg2e3KRSRq\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_jsN1WQ7EFnTW6AFsTJtiw4Mg\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1782896400,\"created_at\":1782661536,\"credited_at\":1783299600,\"paid_at\":1782661536,\"updated_at\":1782661536}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1782661529,\"updated_at\":1782661536}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=7\",\"created_at\":1782661529,\"updated_at\":1782661534}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1782661536,\"updated_at\":1782661536}}}','2026-06-28 15:45:47',1,'2026-06-28 15:45:39','2026-06-28 15:45:47'),(8,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09123456789',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-06-29','10:00:00',NULL,NULL,NULL,NULL,'failed','failed',NULL,NULL,NULL,'2026-06-29 01:49:30','2026-06-29 01:49:42'),(9,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09123456789',NULL,1,'package','Duo Goals',1400.00,280.00,'in_branch','2026-06-30','11:53:00',NULL,NULL,NULL,NULL,'failed','failed',NULL,NULL,NULL,'2026-06-29 01:53:31','2026-06-29 01:53:41'),(10,15,2,2,'Marjo Catibod','catibod.marjo@ncst.edu.ph','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-07-13','10:00:00','cs_6dc2825ef7927dd85e007433','pi_RQvWB3mhp8oKcVcJBaTsLu3F','pay_XDEo3xdhPQxhRyb7zn9UYRsM',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_NoggEnq4KVqqZnUg3Fj5K3xZ\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_6dc2825ef7927dd85e007433\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=10\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/6dc2825ef7927dd85e007433\",\"client_key\":\"cs_6dc2825ef7927dd85e007433_client_1d868eecb8e13ad266f11f1e\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"10\",\"spa_id\":\"2\"},\"paid_at\":1783859719,\"payments\":[{\"id\":\"pay_XDEo3xdhPQxhRyb7zn9UYRsM\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_SfLT1uYzHKFDjZ2CbKMU9TKp\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":null},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"10\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_RQvWB3mhp8oKcVcJBaTsLu3F\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_fbn5WB7WhpJSxMLuGJ4uiUCm\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1784106000,\"created_at\":1783859720,\"credited_at\":1784509200,\"paid_at\":1783859719,\"updated_at\":1783859720}}],\"payment_intent\":{\"id\":\"pi_RQvWB3mhp8oKcVcJBaTsLu3F\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_RQvWB3mhp8oKcVcJBaTsLu3F_client_sZTmp3kA9WkcKckR8kRDMyP4\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"10\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_XDEo3xdhPQxhRyb7zn9UYRsM\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_SfLT1uYzHKFDjZ2CbKMU9TKp\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":null},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"10\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_RQvWB3mhp8oKcVcJBaTsLu3F\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_fbn5WB7WhpJSxMLuGJ4uiUCm\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1784106000,\"created_at\":1783859720,\"credited_at\":1784509200,\"paid_at\":1783859719,\"updated_at\":1783859720}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1783859692,\"updated_at\":1783859720}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=10\",\"created_at\":1783859692,\"updated_at\":1783859717}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1783859720,\"updated_at\":1783859720}}}','2026-07-12 12:35:21',2,'2026-07-12 12:34:50','2026-07-12 12:35:21'),(11,15,2,2,'Marjo Catibod','catibod.marjo@ncst.edu.ph','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-07-13','11:00:00','cs_57b2ddeef75abcb6611e0ae8',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_57b2ddeef75abcb6611e0ae8\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":null,\"name\":null,\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=11\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/57b2ddeef75abcb6611e0ae8\",\"client_key\":\"cs_57b2ddeef75abcb6611e0ae8_client_1106c4be2ce381b5921b84ce\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"11\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_x3yW4t1zq293k645iRi3tnPR\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_x3yW4t1zq293k645iRi3tnPR_client_BomSsKpZ8z562AMVxU8bFwiD\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"11\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"gcash\",\"paymaya\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1783860870,\"updated_at\":1783860870}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=11\",\"created_at\":1783860870,\"updated_at\":1783860870}}}',NULL,NULL,'2026-07-12 12:54:30','2026-07-12 12:54:31'),(12,15,2,2,'Marjo Catibod','catibod.marjo@ncst.edu.ph','09123456789',NULL,4,'treatment','Foot Spa',700.00,140.00,'in_branch','2026-07-14','10:00:00','cs_0e4812e08a21f206ad452b58','pi_m82hVHHiFwZcGPPARb7FEV6H','pay_735TZfj1ppw81NEncEZsFtsE',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_zXdNdQd7yCpWGMyoXrfiZ7zN\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_0e4812e08a21f206ad452b58\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":null},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=12\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/0e4812e08a21f206ad452b58\",\"client_key\":\"cs_0e4812e08a21f206ad452b58_client_86b239e349c762820451b8ab\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Foot Spa Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"12\",\"spa_id\":\"2\"},\"paid_at\":1783861245,\"payments\":[{\"id\":\"pay_735TZfj1ppw81NEncEZsFtsE\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_N7sQR7mCFViPCucx9XXoP2mL\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":null},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"12\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_m82hVHHiFwZcGPPARb7FEV6H\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_xDZ1nfVBrz2ULMARwDx9bswd\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1784106000,\"created_at\":1783861246,\"credited_at\":1784509200,\"paid_at\":1783861245,\"updated_at\":1783861246}}],\"payment_intent\":{\"id\":\"pi_m82hVHHiFwZcGPPARb7FEV6H\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_m82hVHHiFwZcGPPARb7FEV6H_client_j7VwjpV2oYD8C72riFafBtMj\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"12\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"gcash\",\"paymaya\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_735TZfj1ppw81NEncEZsFtsE\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_N7sQR7mCFViPCucx9XXoP2mL\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":null},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"12\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_m82hVHHiFwZcGPPARb7FEV6H\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_xDZ1nfVBrz2ULMARwDx9bswd\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1784106000,\"created_at\":1783861246,\"credited_at\":1784509200,\"paid_at\":1783861245,\"updated_at\":1783861246}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1783861231,\"updated_at\":1783861246}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=12\",\"created_at\":1783861232,\"updated_at\":1783861243}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1783861246,\"updated_at\":1783861246}}}','2026-07-12 13:00:47',3,'2026-07-12 13:00:31','2026-07-12 13:00:47'),(13,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09123456789',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-07-23','09:43:00','cs_81c6213df3258e708abf1d07','pi_hVrCQShVRKqNoLS2NKST6iLG','pay_X1yKKVXjbVB6PUYpctojyujX',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_kpsKFUbDUfosmsLsR9NH4b8A\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_81c6213df3258e708abf1d07\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=13\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/81c6213df3258e708abf1d07\",\"client_key\":\"cs_81c6213df3258e708abf1d07_client_087c4ac273a7aebe82c55586\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"13\",\"spa_id\":\"2\"},\"paid_at\":1783907154,\"payments\":[{\"id\":\"pay_X1yKKVXjbVB6PUYpctojyujX\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_qDy3SZXZFq9Ct9gdMfHSpsSR\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"13\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_hVrCQShVRKqNoLS2NKST6iLG\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_19fDGJaFoSoEdo466aYf8uzo\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1784106000,\"created_at\":1783907154,\"credited_at\":1784509200,\"paid_at\":1783907154,\"updated_at\":1783907154}}],\"payment_intent\":{\"id\":\"pi_hVrCQShVRKqNoLS2NKST6iLG\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_hVrCQShVRKqNoLS2NKST6iLG_client_DkZMuV9ALAi8xb4DpYGx1zXU\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"13\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"gcash\",\"paymaya\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_X1yKKVXjbVB6PUYpctojyujX\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_qDy3SZXZFq9Ct9gdMfHSpsSR\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"13\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_hVrCQShVRKqNoLS2NKST6iLG\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_19fDGJaFoSoEdo466aYf8uzo\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1784106000,\"created_at\":1783907154,\"credited_at\":1784509200,\"paid_at\":1783907154,\"updated_at\":1783907154}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1783907091,\"updated_at\":1783907154}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=13\",\"created_at\":1783907091,\"updated_at\":1783907146}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1783907154,\"updated_at\":1783907154}}}','2026-07-13 01:45:56',4,'2026-07-13 01:44:49','2026-07-13 01:45:56'),(14,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,4,'treatment','Foot Spa',700.00,140.00,'in_branch','2026-07-20','09:00:00','cs_2057770bf65a5c2552dfaf49',NULL,NULL,NULL,'cancelled','cancelled','{\"data\":{\"id\":\"cs_2057770bf65a5c2552dfaf49\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=14\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/2057770bf65a5c2552dfaf49\",\"client_key\":\"cs_2057770bf65a5c2552dfaf49_client_8f33af1220d5a972e40c4524\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Foot Spa Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"14\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_DTZyLedggQ1DJAmXBEK7PUz6\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_DTZyLedggQ1DJAmXBEK7PUz6_client_JAScSrA9y5bypUZiZnCjzaqb\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"14\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1784383221,\"updated_at\":1784383221}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=14\",\"created_at\":1784383221,\"updated_at\":1784383221}}}',NULL,NULL,'2026-07-18 14:00:19','2026-07-18 14:00:24'),(15,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,4,'treatment','Foot Spa',700.00,140.00,'in_branch','2026-07-30','10:00:00','cs_965e0c36d3cf8706ad2d95d2','pi_kLNiXL6F9qhR9Q4JKMm3iYVX','pay_BQRUpPJAYagjmgv2H3u6jmao',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_Vr174JNgrDCDNVsLR1WxLpUB\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_965e0c36d3cf8706ad2d95d2\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=15\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/965e0c36d3cf8706ad2d95d2\",\"client_key\":\"cs_965e0c36d3cf8706ad2d95d2_client_994376f1f6d05fe6be7eed66\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Foot Spa Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"15\",\"spa_id\":\"2\"},\"paid_at\":1784816753,\"payments\":[{\"id\":\"pay_BQRUpPJAYagjmgv2H3u6jmao\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_MziwzNFtLn22uNnCiJZBr8yG\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"15\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_kLNiXL6F9qhR9Q4JKMm3iYVX\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_hVuxUpUJCubceeDvLsFwp8Kn\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":0,\"taxes\":[],\"available_at\":1785229200,\"created_at\":1784816753,\"credited_at\":1785286800,\"paid_at\":1784816753,\"updated_at\":1784816754}}],\"payment_intent\":{\"id\":\"pi_kLNiXL6F9qhR9Q4JKMm3iYVX\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_kLNiXL6F9qhR9Q4JKMm3iYVX_client_uT6Zh2vZUXvFvkALVozXFK3h\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"15\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_BQRUpPJAYagjmgv2H3u6jmao\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_MziwzNFtLn22uNnCiJZBr8yG\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"15\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_kLNiXL6F9qhR9Q4JKMm3iYVX\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_hVuxUpUJCubceeDvLsFwp8Kn\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":0,\"taxes\":[],\"available_at\":1785229200,\"created_at\":1784816753,\"credited_at\":1785286800,\"paid_at\":1784816753,\"updated_at\":1784816754}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1784816745,\"updated_at\":1784816754}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=15\",\"created_at\":1784816745,\"updated_at\":1784816751}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1784816753,\"updated_at\":1784816754}}}','2026-07-23 14:25:54',5,'2026-07-23 14:25:43','2026-07-23 14:25:54'),(16,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-12-23','12:30:00','cs_f0839e3eb57bd92d0d56d4de','pi_RPDZHjrAz8t6KzBNgZ3dGNDN','pay_CpnTobkZ1pdYbQDjVik3dTjG',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_i39pqmehhcoeLnwrWxNxtdBx\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_f0839e3eb57bd92d0d56d4de\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=16\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/f0839e3eb57bd92d0d56d4de\",\"client_key\":\"cs_f0839e3eb57bd92d0d56d4de_client_832b9ea9edf81a1a4e734dce\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"16\",\"spa_id\":\"2\"},\"paid_at\":1784816824,\"payments\":[{\"id\":\"pay_CpnTobkZ1pdYbQDjVik3dTjG\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_pt1Wr7GNBaKNPAyseUBnmH7Q\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"16\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_RPDZHjrAz8t6KzBNgZ3dGNDN\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_LR5mKDgZ5At5HrtmrdWbmdB8\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":0,\"taxes\":[],\"available_at\":1785229200,\"created_at\":1784816824,\"credited_at\":1785286800,\"paid_at\":1784816824,\"updated_at\":1784816824}}],\"payment_intent\":{\"id\":\"pi_RPDZHjrAz8t6KzBNgZ3dGNDN\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_RPDZHjrAz8t6KzBNgZ3dGNDN_client_Vakkhwy8VGs8dhQ5tBQrATNT\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"16\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"gcash\",\"paymaya\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_CpnTobkZ1pdYbQDjVik3dTjG\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_pt1Wr7GNBaKNPAyseUBnmH7Q\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"16\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_RPDZHjrAz8t6KzBNgZ3dGNDN\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_LR5mKDgZ5At5HrtmrdWbmdB8\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":0,\"taxes\":[],\"available_at\":1785229200,\"created_at\":1784816824,\"credited_at\":1785286800,\"paid_at\":1784816824,\"updated_at\":1784816824}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1784816818,\"updated_at\":1784816824}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=16\",\"created_at\":1784816818,\"updated_at\":1784816822}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1784816824,\"updated_at\":1784816824}}}','2026-07-23 14:27:04',6,'2026-07-23 14:26:56','2026-07-23 14:27:04'),(17,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,1,'package','Duo Goals',1400.00,280.00,'in_branch','2026-07-27','10:00:00',NULL,NULL,NULL,NULL,'failed','failed',NULL,NULL,NULL,'2026-07-27 01:38:13','2026-07-27 01:38:27'),(18,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,1,'package','Duo Goals',1400.00,280.00,'in_branch','2026-07-27','10:00:00',NULL,NULL,NULL,NULL,'failed','failed',NULL,NULL,NULL,'2026-07-27 01:38:43','2026-07-27 01:38:53'),(19,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,4,'treatment','Foot Spa',700.00,140.00,'in_branch','2026-08-06','09:00:00','cs_67205cb82078e7e09dfc1297','pi_LhaazJdoTcBG6Wur5r6yyQUa','pay_ALvbAJCj3LKFr5pFnm9GdJCb',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_dyg28sWQEhxe3zh2A9p1AAht\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_67205cb82078e7e09dfc1297\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=19\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/67205cb82078e7e09dfc1297\",\"client_key\":\"cs_67205cb82078e7e09dfc1297_client_5e188ba77b1d26a36710ccb9\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Foot Spa Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"19\",\"spa_id\":\"2\"},\"paid_at\":1785937062,\"payments\":[{\"id\":\"pay_ALvbAJCj3LKFr5pFnm9GdJCb\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_UE34gs3FLF8y7Mv1E6Gtsqgh\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"19\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_LhaazJdoTcBG6Wur5r6yyQUa\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_hFgUkMa15tbseGbhMsYcwkrF\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1786352400,\"created_at\":1785937062,\"credited_at\":1786496400,\"paid_at\":1785937062,\"updated_at\":1785937062}}],\"payment_intent\":{\"id\":\"pi_LhaazJdoTcBG6Wur5r6yyQUa\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_LhaazJdoTcBG6Wur5r6yyQUa_client_Jr1SyhHubpRDMoWuaC67D2eK\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"19\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"gcash\",\"paymaya\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_ALvbAJCj3LKFr5pFnm9GdJCb\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_UE34gs3FLF8y7Mv1E6Gtsqgh\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"19\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_LhaazJdoTcBG6Wur5r6yyQUa\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_hFgUkMa15tbseGbhMsYcwkrF\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1786352400,\"created_at\":1785937062,\"credited_at\":1786496400,\"paid_at\":1785937062,\"updated_at\":1785937062}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1785937045,\"updated_at\":1785937062}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=19\",\"created_at\":1785937045,\"updated_at\":1785937059}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1785937062,\"updated_at\":1785937062}}}','2026-08-05 13:38:27',7,'2026-08-05 13:37:27','2026-08-05 13:38:27'),(20,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,4,'treatment','Foot Spa',700.00,140.00,'in_branch','2026-08-06','10:00:00','cs_17b2bb953986f8f97e5fb6e9','pi_sRyNsZ2jZm9NK99uCQrmiqFU','pay_pkxhVEYaszx447RYv9hnHN6r',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_CVntTjnRedH1ADX4z2Sz1M2S\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_17b2bb953986f8f97e5fb6e9\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=20\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/17b2bb953986f8f97e5fb6e9\",\"client_key\":\"cs_17b2bb953986f8f97e5fb6e9_client_a34cb8a65b1f276a07363f90\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Foot Spa Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"20\",\"spa_id\":\"2\"},\"paid_at\":1785937133,\"payments\":[{\"id\":\"pay_pkxhVEYaszx447RYv9hnHN6r\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_DXut211TpaLRYFkcnLpYhgTk\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"20\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_sRyNsZ2jZm9NK99uCQrmiqFU\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_p6LPnX77tB7uHXZ6W5wStVj2\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1786352400,\"created_at\":1785937134,\"credited_at\":1786496400,\"paid_at\":1785937133,\"updated_at\":1785937134}}],\"payment_intent\":{\"id\":\"pi_sRyNsZ2jZm9NK99uCQrmiqFU\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_sRyNsZ2jZm9NK99uCQrmiqFU_client_QyrMNRMDPwR5xbD8R84Ra7uZ\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"20\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_pkxhVEYaszx447RYv9hnHN6r\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_DXut211TpaLRYFkcnLpYhgTk\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"4\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"20\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_sRyNsZ2jZm9NK99uCQrmiqFU\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_p6LPnX77tB7uHXZ6W5wStVj2\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1786352400,\"created_at\":1785937134,\"credited_at\":1786496400,\"paid_at\":1785937133,\"updated_at\":1785937134}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1785937128,\"updated_at\":1785937134}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=20\",\"created_at\":1785937128,\"updated_at\":1785937131}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1785937134,\"updated_at\":1785937134}}}','2026-08-05 13:38:58',8,'2026-08-05 13:38:50','2026-08-05 13:38:58'),(21,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',700.00,140.00,'in_branch','2026-08-20','09:00:00','cs_58e6e43ccbebd7200e68c051','pi_K1So1Uu2uArnT7RvGRhqfdGY','pay_mvEC7QwEsiUA9mp8DmdpvUUm',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_TVK4cfgdEacWXtY7yWxnS2Hj\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_58e6e43ccbebd7200e68c051\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=21\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/58e6e43ccbebd7200e68c051\",\"client_key\":\"cs_58e6e43ccbebd7200e68c051_client_8cbe20b2272a07bf2ce59360\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":14000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"21\",\"spa_id\":\"2\"},\"paid_at\":1786978256,\"payments\":[{\"id\":\"pay_mvEC7QwEsiUA9mp8DmdpvUUm\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_Y7drox8QrKU2uxCRKP9hPzRH\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"21\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_K1So1Uu2uArnT7RvGRhqfdGY\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_af6XV4ZZ6xZok2dbdxTRqPRa\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1787216400,\"created_at\":1786978256,\"credited_at\":1787533200,\"paid_at\":1786978256,\"updated_at\":1786978257}}],\"payment_intent\":{\"id\":\"pi_K1So1Uu2uArnT7RvGRhqfdGY\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":14000,\"capture_type\":\"automatic\",\"client_key\":\"pi_K1So1Uu2uArnT7RvGRhqfdGY_client_ek55Yi9JADmAnUkBdYBp3oHf\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"21\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":14000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_mvEC7QwEsiUA9mp8DmdpvUUm\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":14000,\"balance_transaction_id\":\"bal_txn_Y7drox8QrKU2uxCRKP9hPzRH\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":350,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"21\",\"spa_id\":\"2\"},\"net_amount\":13650,\"payment_intent_id\":\"pi_K1So1Uu2uArnT7RvGRhqfdGY\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_af6XV4ZZ6xZok2dbdxTRqPRa\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1787216400,\"created_at\":1786978256,\"credited_at\":1787533200,\"paid_at\":1786978256,\"updated_at\":1786978257}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1786978236,\"updated_at\":1786978257}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=21\",\"created_at\":1786978236,\"updated_at\":1786978254}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1786978256,\"updated_at\":1786978257}}}','2026-08-17 14:50:58',9,'2026-08-17 14:50:35','2026-08-17 14:50:58'),(22,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',560.00,112.00,'in_branch','2026-08-24','09:00:00','cs_25f228437177d814e34c9df0','pi_6etW7pX5BWQLqigU8JPoNnCE','pay_ri2iPe5fkbwMYCSpgFA6ZgBG',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_G8zunr87bz377gh2mthJRTFq\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_25f228437177d814e34c9df0\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=22\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/25f228437177d814e34c9df0\",\"client_key\":\"cs_25f228437177d814e34c9df0_client_e3d8f957fa26f9bd0a2ad022\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":11200,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"22\",\"spa_id\":\"2\"},\"paid_at\":1787299543,\"payments\":[{\"id\":\"pay_ri2iPe5fkbwMYCSpgFA6ZgBG\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":11200,\"balance_transaction_id\":\"bal_txn_PSS96uvwenKFbPD6q5xrbi57\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":280,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"22\",\"spa_id\":\"2\"},\"net_amount\":10920,\"payment_intent_id\":\"pi_6etW7pX5BWQLqigU8JPoNnCE\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_QQB9ZZ2Y4fSFgQj2Yu8zoz4c\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1787648400,\"created_at\":1787299544,\"credited_at\":1787706000,\"paid_at\":1787299543,\"updated_at\":1787299544}}],\"payment_intent\":{\"id\":\"pi_6etW7pX5BWQLqigU8JPoNnCE\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":11200,\"capture_type\":\"automatic\",\"client_key\":\"pi_6etW7pX5BWQLqigU8JPoNnCE_client_7LZ3GKHQez9qTcsLpzr5KQ1i\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"22\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":11200,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_ri2iPe5fkbwMYCSpgFA6ZgBG\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":11200,\"balance_transaction_id\":\"bal_txn_PSS96uvwenKFbPD6q5xrbi57\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":280,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"22\",\"spa_id\":\"2\"},\"net_amount\":10920,\"payment_intent_id\":\"pi_6etW7pX5BWQLqigU8JPoNnCE\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_QQB9ZZ2Y4fSFgQj2Yu8zoz4c\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1787648400,\"created_at\":1787299544,\"credited_at\":1787706000,\"paid_at\":1787299543,\"updated_at\":1787299544}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1787299536,\"updated_at\":1787299544}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=22\",\"created_at\":1787299536,\"updated_at\":1787299541}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1787299544,\"updated_at\":1787299544}}}','2026-08-21 08:05:45',NULL,'2026-08-21 08:05:35','2026-08-21 08:05:45'),(23,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',560.00,112.00,'in_branch','2026-08-24','09:00:00','cs_2f7650b89d0aad71003f7beb','pi_RsrJTuQkj7qAZLZbWoayxHTA','pay_UeGog1dapLdTU5DcrpJ7QUg9',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_ALjpGoBQWmRrr6aLESmNgoPk\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_2f7650b89d0aad71003f7beb\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=23\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/2f7650b89d0aad71003f7beb\",\"client_key\":\"cs_2f7650b89d0aad71003f7beb_client_d4d845d3b15e834c62e4e8ac\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":11200,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"23\",\"spa_id\":\"2\"},\"paid_at\":1787494839,\"payments\":[{\"id\":\"pay_UeGog1dapLdTU5DcrpJ7QUg9\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":11200,\"balance_transaction_id\":\"bal_txn_Y7xCamhDckHHpFFTeNpV2LNJ\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":280,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"23\",\"spa_id\":\"2\"},\"net_amount\":10920,\"payment_intent_id\":\"pi_RsrJTuQkj7qAZLZbWoayxHTA\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_okNH7RNZNRygsAYLJHydffBj\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1787734800,\"created_at\":1787494839,\"credited_at\":1788138000,\"paid_at\":1787494839,\"updated_at\":1787494840}}],\"payment_intent\":{\"id\":\"pi_RsrJTuQkj7qAZLZbWoayxHTA\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":11200,\"capture_type\":\"automatic\",\"client_key\":\"pi_RsrJTuQkj7qAZLZbWoayxHTA_client_KJfb5Kc7LzMxUmQzxCpGDPkc\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"23\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":11200,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_UeGog1dapLdTU5DcrpJ7QUg9\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":11200,\"balance_transaction_id\":\"bal_txn_Y7xCamhDckHHpFFTeNpV2LNJ\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":280,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"23\",\"spa_id\":\"2\"},\"net_amount\":10920,\"payment_intent_id\":\"pi_RsrJTuQkj7qAZLZbWoayxHTA\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_okNH7RNZNRygsAYLJHydffBj\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1787734800,\"created_at\":1787494839,\"credited_at\":1788138000,\"paid_at\":1787494839,\"updated_at\":1787494840}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1787494801,\"updated_at\":1787494840}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=23\",\"created_at\":1787494801,\"updated_at\":1787494837}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1787494839,\"updated_at\":1787494840}}}','2026-08-23 14:20:42',11,'2026-08-23 14:19:59','2026-08-23 14:20:42'),(24,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09123456789',NULL,3,'treatment','Swedish Massage',560.00,112.00,'in_branch','2026-08-25','11:00:00','cs_ba42149549b3c601da7c03b5',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_ba42149549b3c601da7c03b5\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09123456789\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=24\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/ba42149549b3c601da7c03b5\",\"client_key\":\"cs_ba42149549b3c601da7c03b5_client_278f9101b36abd326f16a087\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":11200,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"24\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_ut9RmCxydNbu4kRF5WQ41PxA\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":11200,\"capture_type\":\"automatic\",\"client_key\":\"pi_ut9RmCxydNbu4kRF5WQ41PxA_client_n1g6KMAyEf6GtoWT2QGzHpor\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"24\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":11200,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1787540031,\"updated_at\":1787540031}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=24\",\"created_at\":1787540031,\"updated_at\":1787540031}}}',NULL,NULL,'2026-08-24 02:53:50','2026-08-24 02:53:52'),(25,6,2,2,'Piolo Lingo','lingopiolo@gmail.com','09357816489',NULL,3,'treatment','Swedish Massage',560.00,112.00,'in_branch','2026-08-25','09:00:00','cs_78d0ba3df3bfbb87a4fc4498','pi_sCFEbtMqnt8PmzqRJW5KA8YJ','pay_QR9dtpSN6TBN5cibwE3Mtbcd',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_TKgL5ZBeQbhhZQYUGBNFyHbf\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_78d0ba3df3bfbb87a4fc4498\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=25\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/78d0ba3df3bfbb87a4fc4498\",\"client_key\":\"cs_78d0ba3df3bfbb87a4fc4498_client_98c3d28cfc9cb18b54d443b3\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":11200,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"25\",\"spa_id\":\"2\"},\"paid_at\":1787540812,\"payments\":[{\"id\":\"pay_QR9dtpSN6TBN5cibwE3Mtbcd\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":11200,\"balance_transaction_id\":\"bal_txn_jELytcJYrj16T6bThUAPgK5F\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":280,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"25\",\"spa_id\":\"2\"},\"net_amount\":10920,\"payment_intent_id\":\"pi_sCFEbtMqnt8PmzqRJW5KA8YJ\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_h2dKPRSfqeMHiGukszJrieoW\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1787734800,\"created_at\":1787540813,\"credited_at\":1788138000,\"paid_at\":1787540812,\"updated_at\":1787540813}}],\"payment_intent\":{\"id\":\"pi_sCFEbtMqnt8PmzqRJW5KA8YJ\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":11200,\"capture_type\":\"automatic\",\"client_key\":\"pi_sCFEbtMqnt8PmzqRJW5KA8YJ_client_cVrCo6iqcyquk4KfsZeA1fY2\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"25\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":11200,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_QR9dtpSN6TBN5cibwE3Mtbcd\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":11200,\"balance_transaction_id\":\"bal_txn_jELytcJYrj16T6bThUAPgK5F\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"lingopiolo@gmail.com\",\"name\":\"Piolo Lingo\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":280,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"3\",\"bookable_type\":\"treatment\",\"branch_id\":\"2\",\"reservation_id\":\"25\",\"spa_id\":\"2\"},\"net_amount\":10920,\"payment_intent_id\":\"pi_sCFEbtMqnt8PmzqRJW5KA8YJ\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_h2dKPRSfqeMHiGukszJrieoW\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1787734800,\"created_at\":1787540813,\"credited_at\":1788138000,\"paid_at\":1787540812,\"updated_at\":1787540813}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1787540796,\"updated_at\":1787540813}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=25\",\"created_at\":1787540796,\"updated_at\":1787540806}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1787540813,\"updated_at\":1787540813}}}','2026-08-24 03:06:55',12,'2026-08-24 03:06:28','2026-08-24 03:06:55'),(26,15,2,2,'Marjo Catibod','catibod.marjo@ncst.edu.ph','09357816489',NULL,1,'package','Duo Goals',1400.00,280.00,'in_branch','2026-10-26','09:00:00','cs_ea1a76187b56126e5ef77585','pi_RyW1BTp8GqFekrzVbp46DtkC','pay_2vBciS8jHvm3Jp5JsDYqtWx5',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_sBUnSiDqmjFh2UicoLzNSst2\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_ea1a76187b56126e5ef77585\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=26\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/ea1a76187b56126e5ef77585\",\"client_key\":\"cs_ea1a76187b56126e5ef77585_client_c646a0e4907f29e6257b3981\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":28000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Duo Goals Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"1\",\"bookable_type\":\"package\",\"branch_id\":\"2\",\"reservation_id\":\"26\",\"spa_id\":\"2\"},\"paid_at\":1790500082,\"payments\":[{\"id\":\"pay_2vBciS8jHvm3Jp5JsDYqtWx5\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":28000,\"balance_transaction_id\":\"bal_txn_RGTB2oAcVPzE2F97yMgLj6L1\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":700,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"1\",\"bookable_type\":\"package\",\"branch_id\":\"2\",\"reservation_id\":\"26\",\"spa_id\":\"2\"},\"net_amount\":27300,\"payment_intent_id\":\"pi_RyW1BTp8GqFekrzVbp46DtkC\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_sfZGjFTvVXLjqNGWS8icjBQy\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1790758800,\"created_at\":1790500082,\"credited_at\":1791162000,\"paid_at\":1790500082,\"updated_at\":1790500082}}],\"payment_intent\":{\"id\":\"pi_RyW1BTp8GqFekrzVbp46DtkC\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":28000,\"capture_type\":\"automatic\",\"client_key\":\"pi_RyW1BTp8GqFekrzVbp46DtkC_client_chEec6JJvk6GeLgsVdceuhRF\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"1\",\"bookable_type\":\"package\",\"branch_id\":\"2\",\"reservation_id\":\"26\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":28000,\"payment_method_allowed\":[\"gcash\",\"paymaya\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_2vBciS8jHvm3Jp5JsDYqtWx5\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":28000,\"balance_transaction_id\":\"bal_txn_RGTB2oAcVPzE2F97yMgLj6L1\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":700,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"1\",\"bookable_type\":\"package\",\"branch_id\":\"2\",\"reservation_id\":\"26\",\"spa_id\":\"2\"},\"net_amount\":27300,\"payment_intent_id\":\"pi_RyW1BTp8GqFekrzVbp46DtkC\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_sfZGjFTvVXLjqNGWS8icjBQy\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1790758800,\"created_at\":1790500082,\"credited_at\":1791162000,\"paid_at\":1790500082,\"updated_at\":1790500082}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1790500068,\"updated_at\":1790500082}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=26\",\"created_at\":1790500068,\"updated_at\":1790500079}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1790500082,\"updated_at\":1790500082}}}','2026-09-27 09:17:48',13,'2026-09-27 09:07:47','2026-09-27 09:17:48'),(27,15,2,2,'Marjo Catibod','catibod.marjo@ncst.edu.ph','09357816489',NULL,1,'package','Duo Goals',1400.00,280.00,'in_branch','2026-10-26','09:00:00','cs_6037cf3c71991e0c338dc08a',NULL,NULL,NULL,'pending','awaiting_payment','{\"data\":{\"id\":\"cs_6037cf3c71991e0c338dc08a\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"catibod.marjo@ncst.edu.ph\",\"name\":\"Marjo Catibod\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=27\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/6037cf3c71991e0c338dc08a\",\"client_key\":\"cs_6037cf3c71991e0c338dc08a_client_fee52c13762ef7ca3e8ec055\",\"collection\":{\"customer_info\":{\"email\":{\"state\":\"auto\"},\"name\":{\"state\":\"auto\"},\"mobile_phone\":{\"state\":\"auto\"},\"address\":{\"state\":\"auto\"}}},\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":28000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Duo Goals Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"1\",\"bookable_type\":\"package\",\"branch_id\":\"2\",\"reservation_id\":\"27\",\"spa_id\":\"2\"},\"organization_id\":\"org_rAZKoHZDjCzsY72qriQs1VBR\",\"pass_on_fees\":false,\"payment_intent\":{\"id\":\"pi_1bk3zCwWy613TmZYrq1HGRTQ\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":28000,\"capture_type\":\"automatic\",\"client_key\":\"pi_1bk3zCwWy613TmZYrq1HGRTQ_client_iMcQi1WAYNjtSuiyJiDTQiWs\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"1\",\"bookable_type\":\"package\",\"branch_id\":\"2\",\"reservation_id\":\"27\",\"spa_id\":\"2\"},\"next_action\":null,\"original_amount\":28000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"awaiting_payment_method\",\"created_at\":1790502319,\"updated_at\":1790502319}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payments\":[],\"public_key\":\"pk_test_rf3LXzYJ36uKtxhGW9E1MsFW\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=27\",\"created_at\":1790502319,\"updated_at\":1790502319}}}',NULL,NULL,'2026-09-27 09:45:19','2026-09-27 09:45:19'),(28,NULL,4,6,'Ericka Cedes','ericha122c81@fzi.inovel26.com','09357816489',NULL,13,'treatment','Facial Treatment',800.00,160.00,'in_branch','2026-10-06','09:00:00','cs_2e7342ba835e272bab4dab5a','pi_HGXPztM5t11mKZ8SSoZpf6FV','pay_GpQcB1ARa3BGUaAj9RKdX4hk',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_Niqr5du4MbVNxbYqFqSkybz5\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_2e7342ba835e272bab4dab5a\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"ericha122c81@fzi.inovel26.com\",\"name\":\"Ericka Cedes\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=28\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/2e7342ba835e272bab4dab5a\",\"client_key\":\"cs_2e7342ba835e272bab4dab5a_client_a395b309613fd8a92d3764da\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":16000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Facial Treatment Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"13\",\"bookable_type\":\"treatment\",\"branch_id\":\"6\",\"reservation_id\":\"28\",\"spa_id\":\"4\"},\"paid_at\":1791191488,\"payments\":[{\"id\":\"pay_GpQcB1ARa3BGUaAj9RKdX4hk\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":16000,\"balance_transaction_id\":\"bal_txn_MjEa8qptup8GQWy9ct6FwBTo\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"ericha122c81@fzi.inovel26.com\",\"name\":\"Ericka Cedes\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":400,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"13\",\"bookable_type\":\"treatment\",\"branch_id\":\"6\",\"reservation_id\":\"28\",\"spa_id\":\"4\"},\"net_amount\":15600,\"payment_intent_id\":\"pi_HGXPztM5t11mKZ8SSoZpf6FV\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_Y6MUY2ZyTp5oJMGcA91tEQp7\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1791450000,\"created_at\":1791191489,\"credited_at\":1791766800,\"paid_at\":1791191488,\"updated_at\":1791191489}}],\"payment_intent\":{\"id\":\"pi_HGXPztM5t11mKZ8SSoZpf6FV\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":16000,\"capture_type\":\"automatic\",\"client_key\":\"pi_HGXPztM5t11mKZ8SSoZpf6FV_client_D6T9noAfqsuUwc52q3eHv3T8\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"13\",\"bookable_type\":\"treatment\",\"branch_id\":\"6\",\"reservation_id\":\"28\",\"spa_id\":\"4\"},\"next_action\":null,\"original_amount\":16000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_GpQcB1ARa3BGUaAj9RKdX4hk\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":16000,\"balance_transaction_id\":\"bal_txn_MjEa8qptup8GQWy9ct6FwBTo\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"ericha122c81@fzi.inovel26.com\",\"name\":\"Ericka Cedes\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":400,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"13\",\"bookable_type\":\"treatment\",\"branch_id\":\"6\",\"reservation_id\":\"28\",\"spa_id\":\"4\"},\"net_amount\":15600,\"payment_intent_id\":\"pi_HGXPztM5t11mKZ8SSoZpf6FV\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_Y6MUY2ZyTp5oJMGcA91tEQp7\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1791450000,\"created_at\":1791191489,\"credited_at\":1791766800,\"paid_at\":1791191488,\"updated_at\":1791191489}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1791191465,\"updated_at\":1791191489}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=28\",\"created_at\":1791191465,\"updated_at\":1791191484}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1791191489,\"updated_at\":1791191489}}}','2026-10-05 09:11:31',30,'2026-10-05 09:11:05','2026-10-05 09:11:31'),(29,NULL,4,6,'Ericka Cedes','ericha122c81@fzi.inovel26.com','09357816489',NULL,14,'treatment','Swedish Massage',1000.00,200.00,'in_branch','2026-10-05','17:30:00','cs_398ace2efe1b38d96e57e323','pi_RYDRQMH7V8RQKRRDv2zXPCgq','pay_5kBnL2b115PuJdpNX9BTmaSa',NULL,'paid','reserved','{\"data\":{\"id\":\"evt_drCvFKcJhwt7qSC8qmXVAvjz\",\"type\":\"event\",\"attributes\":{\"type\":\"checkout_session.payment.paid\",\"livemode\":false,\"data\":{\"id\":\"cs_398ace2efe1b38d96e57e323\",\"type\":\"checkout_session\",\"attributes\":{\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"ericha122c81@fzi.inovel26.com\",\"name\":\"Ericka Cedes\",\"phone\":\"09357816489\"},\"billing_information_fields_editable\":\"enabled\",\"cancel_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/cancel?reservation=29\",\"checkout_url\":\"https:\\/\\/checkout.paymongo.com\\/398ace2efe1b38d96e57e323\",\"client_key\":\"cs_398ace2efe1b38d96e57e323_client_29864420d43df7fd9cb01bf0\",\"customer_email\":null,\"customer_id\":null,\"description\":\"20% reservation fee for spa appointment\",\"line_items\":[{\"amount\":20000,\"currency\":\"PHP\",\"description\":\"20% downpayment for appointment reservation\",\"images\":[],\"name\":\"Swedish Massage Reservation Fee\",\"quantity\":1}],\"livemode\":false,\"merchant\":\"PIOLO BONGGO LINGO\",\"metadata\":{\"bookable_id\":\"14\",\"bookable_type\":\"treatment\",\"branch_id\":\"6\",\"reservation_id\":\"29\",\"spa_id\":\"4\"},\"paid_at\":1791191746,\"payments\":[{\"id\":\"pay_5kBnL2b115PuJdpNX9BTmaSa\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":20000,\"balance_transaction_id\":\"bal_txn_UYdark9aWzod91MUAVerDXpV\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"ericha122c81@fzi.inovel26.com\",\"name\":\"Ericka Cedes\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":500,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"14\",\"bookable_type\":\"treatment\",\"branch_id\":\"6\",\"reservation_id\":\"29\",\"spa_id\":\"4\"},\"net_amount\":19500,\"payment_intent_id\":\"pi_RYDRQMH7V8RQKRRDv2zXPCgq\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_1XLfVhdg3TmhyfYsKrbNtB2q\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1791450000,\"created_at\":1791191747,\"credited_at\":1791766800,\"paid_at\":1791191746,\"updated_at\":1791191747}}],\"payment_intent\":{\"id\":\"pi_RYDRQMH7V8RQKRRDv2zXPCgq\",\"type\":\"payment_intent\",\"attributes\":{\"amount\":20000,\"capture_type\":\"automatic\",\"client_key\":\"pi_RYDRQMH7V8RQKRRDv2zXPCgq_client_q4Zeb2VVUc9ADJP3ZBki3dT7\",\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"last_payment_error\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"14\",\"bookable_type\":\"treatment\",\"branch_id\":\"6\",\"reservation_id\":\"29\",\"spa_id\":\"4\"},\"next_action\":null,\"original_amount\":20000,\"payment_method_allowed\":[\"paymaya\",\"gcash\"],\"payment_method_options\":null,\"payments\":[{\"id\":\"pay_5kBnL2b115PuJdpNX9BTmaSa\",\"type\":\"payment\",\"attributes\":{\"access_url\":null,\"amount\":20000,\"balance_transaction_id\":\"bal_txn_UYdark9aWzod91MUAVerDXpV\",\"billing\":{\"address\":{\"city\":null,\"country\":null,\"line1\":null,\"line2\":null,\"postal_code\":null,\"state\":null},\"email\":\"ericha122c81@fzi.inovel26.com\",\"name\":\"Ericka Cedes\",\"phone\":\"09357816489\"},\"currency\":\"PHP\",\"description\":\"20% reservation fee for spa appointment\",\"digital_withholding_vat_amount\":0,\"disputed\":false,\"external_reference_number\":null,\"fee\":500,\"foreign_fee\":null,\"instant_settlement\":null,\"livemode\":false,\"metadata\":{\"bookable_id\":\"14\",\"bookable_type\":\"treatment\",\"branch_id\":\"6\",\"reservation_id\":\"29\",\"spa_id\":\"4\"},\"net_amount\":19500,\"payment_intent_id\":\"pi_RYDRQMH7V8RQKRRDv2zXPCgq\",\"payout\":null,\"promotion\":null,\"refunds\":[],\"source\":{\"id\":\"src_1XLfVhdg3TmhyfYsKrbNtB2q\",\"provider\":{\"id\":null},\"provider_id\":null,\"type\":\"gcash\"},\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"paid\",\"tax_amount\":null,\"taxes\":[],\"available_at\":1791450000,\"created_at\":1791191747,\"credited_at\":1791766800,\"paid_at\":1791191746,\"updated_at\":1791191747}}],\"setup_future_usage\":null,\"statement_descriptor\":\"PIOLO BONGGO LINGO\",\"status\":\"succeeded\",\"created_at\":1791191740,\"updated_at\":1791191747}},\"payment_method_types\":[\"gcash\",\"paymaya\"],\"payment_method_used\":\"gcash\",\"reference_number\":null,\"send_email_receipt\":true,\"show_description\":true,\"show_line_items\":true,\"status\":\"active\",\"success_url\":\"http:\\/\\/127.0.0.1:8000\\/bookings\\/online\\/payment\\/success?reservation=29\",\"created_at\":1791191740,\"updated_at\":1791191744}},\"previous_data\":[],\"pending_webhooks\":1,\"created_at\":1791191747,\"updated_at\":1791191747}}}','2026-10-05 09:15:48',31,'2026-10-05 09:15:39','2026-10-05 09:15:48');
/*!40000 ALTER TABLE `online_reservation_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `operating_hours`
--

DROP TABLE IF EXISTS `operating_hours`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `operating_hours` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` bigint(20) unsigned NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `opening_time` time NOT NULL,
  `closing_time` time NOT NULL,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `operating_hours_branch_id_day_of_week_unique` (`branch_id`,`day_of_week`),
  CONSTRAINT `operating_hours_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `operating_hours`
--

LOCK TABLES `operating_hours` WRITE;
/*!40000 ALTER TABLE `operating_hours` DISABLE KEYS */;
INSERT INTO `operating_hours` VALUES (8,2,'Monday','09:00:00','18:00:00',0,'2026-06-28 13:45:53','2026-10-01 15:50:05'),(9,2,'Tuesday','09:00:00','18:00:00',0,'2026-06-28 13:45:53','2026-10-01 15:50:05'),(10,2,'Wednesday','09:00:00','18:00:00',0,'2026-06-28 13:45:53','2026-10-01 15:50:05'),(11,2,'Thursday','09:00:00','18:00:00',0,'2026-06-28 13:45:53','2026-10-01 15:50:05'),(12,2,'Friday','09:00:00','18:00:00',0,'2026-06-28 13:45:53','2026-10-01 15:50:05'),(13,2,'Saturday','09:00:00','18:00:00',1,'2026-06-28 13:45:53','2026-10-01 15:50:05'),(14,2,'Sunday','09:00:00','18:00:00',1,'2026-06-28 13:45:53','2026-10-01 15:50:05'),(15,3,'Monday','09:00:00','18:00:00',1,'2026-06-28 13:46:06','2026-06-28 13:47:14'),(16,3,'Tuesday','09:00:00','18:00:00',1,'2026-06-28 13:46:06','2026-06-28 13:47:14'),(17,3,'Wednesday','09:00:00','18:00:00',0,'2026-06-28 13:46:06','2026-06-28 13:47:14'),(18,3,'Thursday','09:00:00','18:00:00',0,'2026-06-28 13:46:06','2026-06-28 13:47:14'),(19,3,'Friday','09:00:00','18:00:00',0,'2026-06-28 13:46:06','2026-06-28 13:47:14'),(20,3,'Saturday','09:00:00','18:00:00',0,'2026-06-28 13:46:06','2026-06-28 13:47:14'),(21,3,'Sunday','09:00:00','18:00:00',0,'2026-06-28 13:46:06','2026-06-28 13:47:14'),(22,4,'Monday','09:00:00','18:00:00',0,'2026-06-28 14:25:24','2026-06-28 14:25:50'),(23,4,'Tuesday','09:00:00','18:00:00',0,'2026-06-28 14:25:24','2026-06-28 14:25:50'),(24,4,'Wednesday','09:00:00','18:00:00',0,'2026-06-28 14:25:24','2026-06-28 14:25:50'),(25,4,'Thursday','09:00:00','18:00:00',0,'2026-06-28 14:25:24','2026-06-28 14:25:50'),(26,4,'Friday','09:00:00','18:00:00',0,'2026-06-28 14:25:24','2026-06-28 14:25:50'),(27,4,'Saturday','09:00:00','18:00:00',0,'2026-06-28 14:25:24','2026-06-28 14:25:50'),(28,4,'Sunday','09:00:00','18:00:00',1,'2026-06-28 14:25:24','2026-06-28 14:25:50'),(29,5,'Monday','09:00:00','18:00:00',0,'2026-06-28 14:25:41','2026-06-28 14:25:58'),(30,5,'Tuesday','09:00:00','18:00:00',0,'2026-06-28 14:25:41','2026-06-28 14:25:58'),(31,5,'Wednesday','09:00:00','18:00:00',0,'2026-06-28 14:25:41','2026-06-28 14:25:58'),(32,5,'Thursday','09:00:00','18:00:00',0,'2026-06-28 14:25:41','2026-06-28 14:25:58'),(33,5,'Friday','09:00:00','18:00:00',0,'2026-06-28 14:25:41','2026-06-28 14:25:58'),(34,5,'Saturday','09:00:00','18:00:00',1,'2026-06-28 14:25:41','2026-06-28 14:25:58'),(35,5,'Sunday','09:00:00','18:00:00',1,'2026-06-28 14:25:41','2026-06-28 14:25:58'),(36,6,'Monday','09:00:00','18:00:00',0,'2026-10-05 08:15:12','2026-10-05 09:07:12'),(37,6,'Tuesday','09:00:00','18:00:00',0,'2026-10-05 08:15:12','2026-10-05 09:07:12'),(38,6,'Wednesday','09:00:00','18:00:00',0,'2026-10-05 08:15:12','2026-10-05 09:07:12'),(39,6,'Thursday','09:00:00','18:00:00',0,'2026-10-05 08:15:12','2026-10-05 09:07:12'),(40,6,'Friday','09:00:00','18:00:00',0,'2026-10-05 08:15:12','2026-10-05 09:07:12'),(41,6,'Saturday','09:00:00','18:00:00',1,'2026-10-05 08:15:12','2026-10-05 09:07:12'),(42,6,'Sunday','09:00:00','18:00:00',1,'2026-10-05 08:15:12','2026-10-05 09:07:12'),(43,7,'Monday','09:00:00','18:00:00',0,'2026-10-05 08:15:35','2026-10-05 08:15:35'),(44,7,'Tuesday','09:00:00','18:00:00',0,'2026-10-05 08:15:35','2026-10-05 08:15:35'),(45,7,'Wednesday','09:00:00','18:00:00',0,'2026-10-05 08:15:35','2026-10-05 08:15:35'),(46,7,'Thursday','09:00:00','18:00:00',0,'2026-10-05 08:15:35','2026-10-05 08:15:35'),(47,7,'Friday','09:00:00','18:00:00',0,'2026-10-05 08:15:35','2026-10-05 08:15:35'),(48,7,'Saturday','09:00:00','18:00:00',0,'2026-10-05 08:15:35','2026-10-05 08:15:35'),(49,7,'Sunday','09:00:00','18:00:00',0,'2026-10-05 08:15:35','2026-10-05 08:15:35'),(57,9,'Monday','09:00:00','18:00:00',0,'2026-10-05 17:34:09','2026-10-05 17:43:39'),(58,9,'Tuesday','09:00:00','18:00:00',0,'2026-10-05 17:34:09','2026-10-05 17:43:39'),(59,9,'Wednesday','09:00:00','18:00:00',0,'2026-10-05 17:34:09','2026-10-05 17:43:39'),(60,9,'Thursday','09:00:00','18:00:00',0,'2026-10-05 17:34:09','2026-10-05 17:43:39'),(61,9,'Friday','09:00:00','18:00:00',0,'2026-10-05 17:34:09','2026-10-05 17:43:39'),(62,9,'Saturday','09:00:00','18:00:00',1,'2026-10-05 17:34:09','2026-10-05 17:43:39'),(63,9,'Sunday','09:00:00','18:00:00',1,'2026-10-05 17:34:09','2026-10-05 17:43:39'),(64,10,'Monday','09:00:00','18:00:00',0,'2026-10-08 07:39:22','2026-10-08 07:39:22'),(65,10,'Tuesday','09:00:00','18:00:00',0,'2026-10-08 07:39:22','2026-10-08 07:39:22'),(66,10,'Wednesday','09:00:00','18:00:00',0,'2026-10-08 07:39:22','2026-10-08 07:39:22'),(67,10,'Thursday','09:00:00','18:00:00',0,'2026-10-08 07:39:22','2026-10-08 07:39:22'),(68,10,'Friday','09:00:00','18:00:00',0,'2026-10-08 07:39:22','2026-10-08 07:39:22'),(69,10,'Saturday','09:00:00','18:00:00',0,'2026-10-08 07:39:22','2026-10-08 07:39:22'),(70,10,'Sunday','09:00:00','18:00:00',0,'2026-10-08 07:39:22','2026-10-08 07:39:22');
/*!40000 ALTER TABLE `operating_hours` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `package_treatment`
--

DROP TABLE IF EXISTS `package_treatment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_treatment` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint(20) unsigned NOT NULL,
  `treatment_id` bigint(20) unsigned NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `package_treatment_package_id_treatment_id_unique` (`package_id`,`treatment_id`),
  KEY `package_treatment_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `package_treatment_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `package_treatment_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_treatment`
--

LOCK TABLES `package_treatment` WRITE;
/*!40000 ALTER TABLE `package_treatment` DISABLE KEYS */;
INSERT INTO `package_treatment` VALUES (1,1,3,1,'2026-06-28 13:58:27','2026-10-01 17:04:50'),(2,1,4,1,'2026-06-28 13:58:27','2026-06-28 13:58:27'),(3,2,5,1,'2026-06-28 14:27:20','2026-06-28 14:27:20'),(4,2,6,1,'2026-06-28 14:27:20','2026-06-28 14:27:20'),(7,4,13,1,'2026-10-05 09:02:43','2026-10-05 09:02:43'),(8,4,14,1,'2026-10-05 09:02:43','2026-10-05 09:02:43');
/*!40000 ALTER TABLE `package_treatment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `packages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_duration` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `packages_spa_id_foreign` (`spa_id`),
  KEY `packages_branch_id_foreign` (`branch_id`),
  CONSTRAINT `packages_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `packages_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
INSERT INTO `packages` VALUES (1,2,2,'Duo Goals',60,1400.00,'Enjoy the perfect combination of a relaxing Swedish massage and a refreshing foot spa treatment for complete body relaxation and renewed energy.','packages/hdVW1j3rxIG11Jl5rxgmdrDAkmrIonEXsbPAl3Nt.png','2026-06-28 13:58:27','2026-07-15 12:09:33',NULL),(2,3,4,'Duo Pack',60,1400.00,NULL,NULL,'2026-06-28 14:27:20','2026-06-28 14:27:20',NULL),(4,4,6,'Package 1',60,1800.00,NULL,NULL,'2026-10-05 09:02:43','2026-10-05 09:02:43',NULL);
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
INSERT INTO `password_reset_tokens` VALUES ('lingopiolo@gmail.com','$2y$12$hj9To0lFWWxQbIkmh278kOIwlQgGsJUQ/E6zZDmVKHxBBclCL7kCu','2026-07-12 12:16:17');
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_runs`
--

DROP TABLE IF EXISTS `payroll_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payroll_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `run_type` enum('regular','thirteenth_month') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `cutoff_no` tinyint(3) unsigned DEFAULT NULL,
  `pay_date` date NOT NULL,
  `status` enum('draft','approved','finalized','released') NOT NULL DEFAULT 'draft',
  `config_version` varchar(255) NOT NULL,
  `review` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`review`)),
  `generated_by` bigint(20) unsigned NOT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `finalized_by` bigint(20) unsigned DEFAULT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `released_by` bigint(20) unsigned DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payroll_runs_spa_type_period_unique` (`spa_id`,`run_type`,`period_start`),
  KEY `payroll_runs_generated_by_foreign` (`generated_by`),
  KEY `payroll_runs_approved_by_foreign` (`approved_by`),
  KEY `payroll_runs_finalized_by_foreign` (`finalized_by`),
  KEY `payroll_runs_released_by_foreign` (`released_by`),
  CONSTRAINT `payroll_runs_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_runs_finalized_by_foreign` FOREIGN KEY (`finalized_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_runs_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `payroll_runs_released_by_foreign` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_runs_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_runs`
--

LOCK TABLES `payroll_runs` WRITE;
/*!40000 ALTER TABLE `payroll_runs` DISABLE KEYS */;
INSERT INTO `payroll_runs` VALUES (1,2,'regular','2026-09-01','2026-09-15',1,'2026-09-20','draft','2026.4','{\"generated_at\":\"2026-10-03T10:41:27+08:00\",\"generated_by\":7,\"regenerated\":false,\"adjusted_at\":null,\"warnings\":[{\"code\":\"missing_statutory_id\",\"message\":\"Lingo Piolo (staff #3): missing SSS, PhilHealth, Pag-IBIG, TIN number \\u2014 contributions and tax are still computed, but remittance needs them.\",\"staff_id\":3},{\"code\":\"missing_profile\",\"message\":\"Marjo Bodcati (staff #4): no pay profile in effect between 2026-09-01 and 2026-09-15 \\u2014 not paid.\",\"staff_id\":4},{\"code\":\"missing_profile\",\"message\":\"Karlo Daban (staff #5): no pay profile in effect between 2026-09-01 and 2026-09-15 \\u2014 not paid.\",\"staff_id\":5},{\"code\":\"missing_profile\",\"message\":\"Pamela Lingo (staff #6): no pay profile in effect between 2026-09-01 and 2026-09-15 \\u2014 not paid.\",\"staff_id\":6},{\"code\":\"unverified_rule\",\"message\":\"1 payslip(s) are treated as minimum wage earners: daily rate \\u2264 home-branch minimum wage (rates sheet \\u00a75 \\u2014 UNVERIFIED test; exempt-component mapping UNVERIFIED).\",\"staff_id\":null}]}',7,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 02:41:27','2026-10-03 02:41:27');
/*!40000 ALTER TABLE `payroll_runs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payslip_lines`
--

DROP TABLE IF EXISTS `payslip_lines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payslip_lines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payslip_id` bigint(20) unsigned NOT NULL,
  `component_code` varchar(32) NOT NULL,
  `label` varchar(100) NOT NULL,
  `kind` enum('earning','deduction','employer_share') NOT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `rate` decimal(12,4) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `source_type` enum('booking','attendance','recurring_item') DEFAULT NULL,
  `source_id` bigint(20) unsigned DEFAULT NULL,
  `unique_source_id` bigint(20) unsigned GENERATED ALWAYS AS (case when `source_type` in ('booking','attendance') then `source_id` else NULL end) STORED,
  `is_manual` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payslip_lines_paid_once_unique` (`component_code`,`source_type`,`unique_source_id`),
  KEY `payslip_lines_payslip_id_foreign` (`payslip_id`),
  KEY `payslip_lines_branch_id_foreign` (`branch_id`),
  KEY `payslip_lines_created_by_foreign` (`created_by`),
  CONSTRAINT `payslip_lines_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `payslip_lines_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payslip_lines_payslip_id_foreign` FOREIGN KEY (`payslip_id`) REFERENCES `payslips` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payslip_lines`
--

LOCK TABLES `payslip_lines` WRITE;
/*!40000 ALTER TABLE `payslip_lines` DISABLE KEYS */;
INSERT INTO `payslip_lines` VALUES (1,1,'ALLOWANCE','Allowance — TRANSPORTATION','earning',2,2.00,50.0000,100.00,'recurring_item',1,NULL,0,NULL,'₱50.00 × 2 day(s) worked','2026-10-03 02:41:27','2026-10-03 02:41:27'),(2,1,'BASIC','Basic Pay — Sep 2','earning',2,1.00,600.0000,600.00,'attendance',3,3,0,NULL,'Daily rate ₱600.00','2026-10-03 02:41:27','2026-10-03 02:41:27'),(3,1,'BASIC','Basic Pay — Sep 3','earning',2,1.00,600.0000,600.00,'attendance',5,5,0,NULL,'Daily rate ₱600.00','2026-10-03 02:41:27','2026-10-03 02:41:27');
/*!40000 ALTER TABLE `payslip_lines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payslips`
--

DROP TABLE IF EXISTS `payslips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payslips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payroll_run_id` bigint(20) unsigned NOT NULL,
  `staff_id` bigint(20) unsigned NOT NULL,
  `home_branch_id` bigint(20) unsigned NOT NULL,
  `gross_pay` decimal(12,2) NOT NULL,
  `total_deductions` decimal(12,2) NOT NULL,
  `net_pay` decimal(12,2) NOT NULL,
  `taxable_compensation` decimal(12,2) NOT NULL,
  `days_worked` decimal(5,2) NOT NULL,
  `is_mwe` tinyint(1) NOT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payslips_run_staff_unique` (`payroll_run_id`,`staff_id`),
  KEY `payslips_staff_id_foreign` (`staff_id`),
  KEY `payslips_home_branch_id_foreign` (`home_branch_id`),
  CONSTRAINT `payslips_home_branch_id_foreign` FOREIGN KEY (`home_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `payslips_payroll_run_id_foreign` FOREIGN KEY (`payroll_run_id`) REFERENCES `payroll_runs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payslips_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payslips`
--

LOCK TABLES `payslips` WRITE;
/*!40000 ALTER TABLE `payslips` DISABLE KEYS */;
INSERT INTO `payslips` VALUES (1,1,3,2,1300.00,0.00,1300.00,100.00,2.00,1,'{\"eligibility\":{\"eligible\":true,\"reason\":null},\"pay_profiles\":[{\"id\":1,\"effective_from\":\"2026-09-01\",\"effective_to\":null,\"pay_basis\":\"daily\",\"base_rate\":\"600.00\",\"commission_enabled\":true,\"rest_days\":[\"Saturday\",\"Sunday\"]}],\"home_branch\":{\"id\":2,\"name\":\"Marjo\'s Spa Imus\",\"min_daily_wage\":\"600.00\",\"wage_order_ref\":\"WO IVA-22\",\"min_wage_effective_from\":\"2026-09-01\",\"has_workforce_finance_suite\":true},\"spa_settings\":{\"payroll_first_cutoff_day\":15,\"payroll_pay_day_offset\":5},\"holidays\":[],\"rule_ids\":{\"commission_rules\":[],\"recurring_items\":[1]}}','2026-10-03 02:41:27','2026-10-03 02:41:27');
/*!40000 ALTER TABLE `payslips` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pending_registrations`
--

DROP TABLE IF EXISTS `pending_registrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pending_registrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `registration_type` varchar(20) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expires_at` datetime DEFAULT NULL,
  `otp_sent_at` datetime DEFAULT NULL,
  `otp_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pending_registrations_uuid_unique` (`uuid`),
  UNIQUE KEY `pending_registrations_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pending_registrations`
--

LOCK TABLES `pending_registrations` WRITE;
/*!40000 ALTER TABLE `pending_registrations` DISABLE KEYS */;
INSERT INTO `pending_registrations` VALUES (4,'67627a92-85a2-4fea-8a31-f3f33a34d455','customer','Yokna',NULL,'Jirda',NULL,'yoknajirda@tozya.com','$2y$12$so0NyThAeUfkBYerXSY3LeMI4ilH0iYYN6yUYhQjH86qIpv3SeeWy','$2y$12$o9H9VcZBsJ5doV63.n9GAeOhms0a878SHJ8o0yF5hHV7hm.gAYoAu','2026-10-06 03:20:58','2026-10-06 03:10:58',0,'2026-10-07 03:10:58','2026-10-05 19:10:58','2026-10-05 19:10:58');
/*!40000 ALTER TABLE `pending_registrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'view owner dashboard','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(2,'view admin dashboard','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(3,'book appointments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(4,'view appointments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(5,'edit appointments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(6,'delete appointments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(7,'view schedule','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(8,'view attendance','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(9,'edit attendance','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(10,'view leave requests','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(11,'create leave requests','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(12,'edit leave requests','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(13,'delete leave requests','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(14,'view branches','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(15,'create branches','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(16,'edit branches','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(17,'delete branches','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(18,'view staff','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(19,'create staff','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(20,'edit staff','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(21,'delete staff','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(22,'view services','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(23,'create treatments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(24,'edit treatments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(25,'delete treatments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(26,'create packages','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(27,'edit packages','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(28,'delete packages','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(29,'view reports','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(30,'export reports','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(31,'view decision support','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(32,'view inventory','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(33,'view inventory logs','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(34,'create inventory items','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(35,'edit inventory items','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(36,'delete inventory items','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(37,'view product inventory','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(38,'create product inventory','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(39,'edit product inventory','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(40,'delete product inventory','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(41,'view product logs','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(42,'edit own profile','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(43,'view spa profile','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(44,'edit spa profile','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(45,'view registered users','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(46,'edit registered users','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(47,'delete registered users','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(48,'view registered spas','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(49,'edit registered spas','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(50,'verify registered spas','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(51,'change spa subscriptions','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(52,'view system roles','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(53,'edit system roles','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(54,'edit admin profile','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(55,'manage system settings','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(57,'view hiring','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(58,'create hiring','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(59,'edit hiring','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(60,'delete hiring','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(61,'view applications','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(62,'edit applications','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(63,'delete applications','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(64,'view interviews','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(65,'create interviews','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(66,'edit interviews','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(67,'delete interviews','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(68,'view payroll','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(69,'edit payroll','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(70,'view deployments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(71,'create deployments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(72,'approve deployments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(73,'delete deployments','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(75,'view revenue','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(76,'view billing','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(77,'create billing','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(78,'edit billing','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(79,'delete billing','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(80,'view finance inventory','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(81,'edit finance inventory','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(82,'view business dashboard','web','2026-06-28 13:55:28','2026-06-28 13:55:28'),(83,'view dashboard kpis','web','2026-06-28 13:55:28','2026-06-28 13:55:28'),(84,'view dashboard revenue','web','2026-06-28 13:55:28','2026-06-28 13:55:28'),(85,'view dashboard timeline','web','2026-06-28 13:55:28','2026-06-28 13:55:28'),(86,'view dashboard therapist status','web','2026-06-28 13:55:28','2026-06-28 13:55:28'),(87,'view dashboard alerts','web','2026-06-28 13:55:28','2026-06-28 13:55:28'),(88,'view dashboard booking button','web','2026-06-28 13:55:28','2026-06-28 13:55:28'),(89,'view dashboard my today','web','2026-06-28 13:55:28','2026-06-28 13:55:28'),(90,'request appointment reassignment','web','2026-08-03 05:17:41','2026-08-03 05:17:41'),(91,'view promos','web','2026-08-21 07:19:17','2026-08-21 07:19:17'),(92,'create promos','web','2026-08-21 07:19:17','2026-08-21 07:19:17'),(93,'edit promos','web','2026-08-21 07:19:17','2026-08-21 07:19:17'),(94,'delete promos','web','2026-08-21 07:19:17','2026-08-21 07:19:17'),(95,'edit branch general','web','2026-09-06 12:39:29','2026-09-06 12:39:29'),(96,'edit branch hours','web','2026-09-06 12:39:29','2026-09-06 12:39:29'),(97,'edit branch profile','web','2026-09-06 12:39:29','2026-09-06 12:39:29'),(98,'view stock transfers','web','2026-10-01 10:14:29','2026-10-01 10:14:29'),(99,'create stock transfers','web','2026-10-01 10:14:29','2026-10-01 10:14:29'),(100,'process stock transfers','web','2026-10-01 10:14:29','2026-10-01 10:14:29'),(101,'cancel stock transfers','web','2026-10-01 10:14:29','2026-10-01 10:14:29'),(102,'view suppliers','web','2026-10-01 18:10:14','2026-10-01 18:10:14'),(103,'create suppliers','web','2026-10-01 18:10:14','2026-10-01 18:10:14'),(104,'edit suppliers','web','2026-10-01 18:10:14','2026-10-01 18:10:14'),(105,'manage supplier products','web','2026-10-01 18:10:14','2026-10-01 18:10:14'),(106,'view purchase requests','web','2026-10-01 19:27:54','2026-10-01 19:27:54'),(107,'create purchase requests','web','2026-10-01 19:27:54','2026-10-01 19:27:54'),(108,'review purchase requests','web','2026-10-01 19:27:54','2026-10-01 19:27:54'),(109,'view purchase orders','web','2026-10-02 03:48:32','2026-10-02 03:48:32'),(110,'create purchase orders','web','2026-10-02 03:48:32','2026-10-02 03:48:32'),(111,'manage purchase orders','web','2026-10-02 03:48:32','2026-10-02 03:48:32'),(112,'view goods receipts','web','2026-10-02 05:08:38','2026-10-02 05:08:38'),(113,'receive goods','web','2026-10-02 05:08:38','2026-10-02 05:08:38'),(114,'view replenishment','web','2026-10-02 05:57:03','2026-10-02 05:57:03'),(115,'view vendor bills','web','2026-10-02 16:00:00','2026-10-02 16:00:00'),(116,'create vendor bills','web','2026-10-02 16:00:01','2026-10-02 16:00:01'),(117,'match vendor bills','web','2026-10-02 16:00:01','2026-10-02 16:00:01'),(118,'edit vendor bills','web','2026-10-02 17:13:35','2026-10-02 17:13:35');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `posts`
--

DROP TABLE IF EXISTS `posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `posts`
--

LOCK TABLES `posts` WRITE;
/*!40000 ALTER TABLE `posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_batches`
--

DROP TABLE IF EXISTS `product_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `batch_number` varchar(255) DEFAULT NULL,
  `received_quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `remaining_quantity` decimal(14,3) NOT NULL DEFAULT 0.000,
  `unit_cost` decimal(12,4) DEFAULT NULL,
  `manufactured_at` date DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_batches_product_id_foreign` (`product_id`),
  KEY `product_batches_fefo_index` (`branch_id`,`product_id`,`expiration_date`),
  KEY `product_batches_spa_id_branch_id_index` (`spa_id`,`branch_id`),
  CONSTRAINT `product_batches_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_batches_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_batches_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_batches`
--

LOCK TABLES `product_batches` WRITE;
/*!40000 ALTER TABLE `product_batches` DISABLE KEYS */;
INSERT INTO `product_batches` VALUES (37,2,2,5,'LOT-LAV-IMUS-01',3000.000,3000.000,500.0000,'2026-08-05','2027-04-05','2026-09-22 02:40:41','2026-09-22 02:40:41','2026-10-05 04:06:56'),(38,2,3,5,'LOT-LAV-CAR-01',2000.000,1000.000,500.0000,'2026-08-05','2027-03-05','2026-09-19 02:40:41','2026-09-19 02:40:41','2026-10-05 04:06:56'),(39,2,2,5,'LOT-LAV-CAR-01-T',1000.000,1000.000,500.0000,'2026-08-05','2027-03-05','2026-09-30 02:40:41','2026-09-30 02:40:41','2026-10-05 04:06:56'),(40,2,2,6,'LOT-PEP-IMUS-01',500.000,500.000,250.0000,'2026-05-05','2026-10-30','2026-09-27 02:40:41','2026-09-27 02:40:41','2026-10-05 04:06:56'),(41,2,2,7,'LOT-SHEET-IMUS-01',10.000,10.000,350.0000,'2026-09-05','2027-10-05','2026-09-28 02:40:41','2026-09-28 02:40:41','2026-10-05 04:06:56'),(42,2,3,7,'LOT-SHEET-CAR-01',30.000,30.000,350.0000,'2026-09-05','2027-10-05','2026-09-26 02:40:41','2026-09-26 02:40:41','2026-10-05 04:06:56');
/*!40000 ALTER TABLE `product_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_logs`
--

DROP TABLE IF EXISTS `product_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_logs_product_id_foreign` (`product_id`),
  KEY `product_logs_user_id_foreign` (`user_id`),
  KEY `product_logs_spa_id_logged_at_index` (`spa_id`,`logged_at`),
  CONSTRAINT `product_logs_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `product_logs_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_logs`
--

LOCK TABLES `product_logs` WRITE;
/*!40000 ALTER TABLE `product_logs` DISABLE KEYS */;
INSERT INTO `product_logs` VALUES (51,2,5,7,'Stock Adjustment — 2000 ml of Lavender Massage Oil recorded at Marjo\'s Spa Carmona.','2026-09-19 02:40:41','2026-09-19 02:40:41','2026-09-19 02:40:41'),(52,2,5,7,'Purchase Receipt — 3000 ml of Lavender Massage Oil received at Marjo\'s Spa Imus.','2026-09-22 02:40:41','2026-09-22 02:40:41','2026-09-22 02:40:41'),(53,2,7,7,'Stock Adjustment — 30 pcs of Disposable Bed Sheets recorded at Marjo\'s Spa Carmona.','2026-09-26 02:40:41','2026-09-26 02:40:41','2026-09-26 02:40:41'),(54,2,6,7,'Stock Adjustment — 500 g of Peppermint Foot Soak recorded at Marjo\'s Spa Imus.','2026-09-27 02:40:41','2026-09-27 02:40:41','2026-09-27 02:40:41'),(55,2,7,7,'Purchase Receipt — 10 pcs of Disposable Bed Sheets received at Marjo\'s Spa Imus.','2026-09-28 02:40:41','2026-09-28 02:40:41','2026-09-28 02:40:41'),(56,2,5,7,'Transfer Out — 1000 ml of Lavender Massage Oil moved from Marjo\'s Spa Carmona.','2026-09-30 02:40:41','2026-09-30 02:40:41','2026-09-30 02:40:41'),(57,2,5,7,'Transfer In — 1000 ml of Lavender Massage Oil received at Marjo\'s Spa Imus.','2026-09-30 02:41:41','2026-09-30 02:41:41','2026-09-30 02:41:41');
/*!40000 ALTER TABLE `product_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `barcode` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(255) DEFAULT NULL,
  `inventory_type` varchar(255) NOT NULL DEFAULT 'backbar',
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `unit_value` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(20) NOT NULL DEFAULT 'ml',
  `purchase_unit` varchar(30) DEFAULT NULL,
  `usage_unit` varchar(30) DEFAULT NULL,
  `conversion_factor` decimal(14,3) NOT NULL DEFAULT 1.000,
  `retail_price` decimal(12,2) DEFAULT NULL,
  `acquisition_cost` decimal(12,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `expiration_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_spa_sku_unique` (`spa_id`,`sku`),
  KEY `products_spa_id_name_index` (`spa_id`,`name`),
  KEY `products_spa_id_category_index` (`spa_id`,`category`),
  KEY `products_spa_id_is_active_index` (`spa_id`,`is_active`),
  CONSTRAINT `products_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,2,NULL,NULL,'Hand Wash','Yuzu',NULL,NULL,'backbar',30,30,'ml',NULL,'ml',1.000,NULL,NULL,0,'2027-08-06','2026-08-06 09:17:53','2026-10-05 02:40:41','2026-10-05 02:40:41'),(2,2,NULL,NULL,'Peppermint Foot Soak','Natura Bliss',NULL,NULL,'backbar',10,150,'ml',NULL,'ml',1.000,NULL,NULL,0,'2028-08-21','2026-08-21 09:13:35','2026-10-05 02:40:41','2026-10-05 02:40:41'),(3,2,NULL,NULL,'Herbal Massage Balm','Calmora Spa Essentials',NULL,NULL,'backbar',10,500,'ml',NULL,'ml',1.000,NULL,NULL,0,'2028-08-21','2026-08-21 09:17:34','2026-10-05 02:40:41','2026-10-05 02:40:41'),(4,2,'OIL-LAV-001',NULL,'Lavander Massage Oil','ZenCare','Lavender oil used for massage treatments','Massage Oils','backbar',0,0,'ml','bottle','ml',1000.000,NULL,NULL,0,NULL,'2026-09-30 09:36:14','2026-10-05 02:40:41','2026-10-05 02:40:41'),(5,2,'LAV-261005104041',NULL,'Lavender Massage Oil','Zen Essentials','Relaxing lavender massage oil for spa treatments.','Massage Essentials','consumable',5000,1,'ml','bottle','ml',500.000,650.00,500.00,1,NULL,'2026-10-05 02:40:41','2026-10-05 04:06:56',NULL),(6,2,'PEP-261005104041',NULL,'Peppermint Foot Soak','Zen Essentials','Cooling peppermint foot soak for foot spa services.','Foot Care','backbar',500,1,'g','bag','g',500.000,350.00,250.00,1,NULL,'2026-10-05 02:40:41','2026-10-05 04:06:56',NULL),(7,2,'SHEET-261005104041',NULL,'Disposable Bed Sheets','SpaCare','Disposable hygienic bed sheets for treatment rooms.','Hygiene Supplies','consumable',40,1,'pcs','pack','pcs',10.000,450.00,350.00,1,NULL,'2026-10-05 02:40:41','2026-10-05 04:06:56',NULL),(8,2,'BALM-261005104041',NULL,'Herbal Massage Balm','Zen Essentials','Herbal massage balm used for targeted massage treatments.','Massage Essentials','consumable',0,1,'g','jar','g',100.000,420.00,300.00,1,NULL,'2026-10-05 02:40:41','2026-10-05 04:06:56',NULL);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_package`
--

DROP TABLE IF EXISTS `promo_package`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo_package` (
  `promo_id` bigint(20) unsigned NOT NULL,
  `package_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `promo_package_promo_id_foreign` (`promo_id`),
  KEY `promo_package_package_id_foreign` (`package_id`),
  CONSTRAINT `promo_package_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promo_package_promo_id_foreign` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_package`
--

LOCK TABLES `promo_package` WRITE;
/*!40000 ALTER TABLE `promo_package` DISABLE KEYS */;
INSERT INTO `promo_package` VALUES (3,1,'2026-10-01 14:02:21','2026-10-01 14:02:21');
/*!40000 ALTER TABLE `promo_package` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promo_treatment`
--

DROP TABLE IF EXISTS `promo_treatment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promo_treatment` (
  `promo_id` bigint(20) unsigned NOT NULL,
  `treatment_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  KEY `promo_treatment_promo_id_foreign` (`promo_id`),
  KEY `promo_treatment_treatment_id_foreign` (`treatment_id`),
  CONSTRAINT `promo_treatment_promo_id_foreign` FOREIGN KEY (`promo_id`) REFERENCES `promos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promo_treatment_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promo_treatment`
--

LOCK TABLES `promo_treatment` WRITE;
/*!40000 ALTER TABLE `promo_treatment` DISABLE KEYS */;
INSERT INTO `promo_treatment` VALUES (1,3,'2026-08-21 07:48:11','2026-08-21 07:48:11'),(2,4,'2026-08-24 03:03:25','2026-08-24 03:03:25');
/*!40000 ALTER TABLE `promo_treatment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promos`
--

DROP TABLE IF EXISTS `promos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `discount_type` enum('percent','fixed') NOT NULL,
  `discount_value` decimal(8,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `promos_spa_id_foreign` (`spa_id`),
  KEY `promos_branch_id_foreign` (`branch_id`),
  CONSTRAINT `promos_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `promos_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promos`
--

LOCK TABLES `promos` WRITE;
/*!40000 ALTER TABLE `promos` DISABLE KEYS */;
INSERT INTO `promos` VALUES (1,2,2,'Summer Sale','percent',20.00,'2026-08-23','2026-08-28',1,'2026-08-21 07:48:11','2026-08-23 14:18:53',NULL),(2,2,2,'AUG PROMO','percent',20.00,'2026-08-24','2026-08-26',1,'2026-08-24 03:03:25','2026-08-24 03:03:25',NULL),(3,2,2,'OCT SALE','percent',15.00,'2026-10-01','2026-10-10',1,'2026-10-01 14:02:21','2026-10-03 03:38:47',NULL);
/*!40000 ALTER TABLE `promos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `unit_cost` decimal(14,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_items_order_product_unique` (`purchase_order_id`,`product_id`),
  KEY `purchase_order_items_product_id_foreign` (`product_id`),
  CONSTRAINT `purchase_order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `purchase_order_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
INSERT INTO `purchase_order_items` VALUES (8,8,5,6.000,'bottle',500.00,'2026-09-17 02:40:41','2026-09-17 02:40:41'),(9,9,6,5.000,'bag',250.00,'2026-10-01 02:40:41','2026-10-01 02:40:41'),(10,10,7,2.000,'pack',350.00,'2026-09-25 02:40:41','2026-09-25 02:40:41');
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_status_histories`
--

DROP TABLE IF EXISTS `purchase_order_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_status_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(255) DEFAULT NULL,
  `to_status` varchar(255) NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_order_status_histories_changed_by_foreign` (`changed_by`),
  KEY `po_status_history_order_status_idx` (`purchase_order_id`,`to_status`),
  CONSTRAINT `purchase_order_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_order_status_histories_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_status_histories`
--

LOCK TABLES `purchase_order_status_histories` WRITE;
/*!40000 ALTER TABLE `purchase_order_status_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `purchase_request_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_orders_purchase_request_id_unique` (`purchase_request_id`),
  KEY `purchase_orders_branch_id_foreign` (`branch_id`),
  KEY `purchase_orders_created_by_foreign` (`created_by`),
  KEY `po_spa_branch_status_idx` (`spa_id`,`branch_id`,`status`),
  KEY `po_supplier_status_idx` (`supplier_id`,`status`),
  CONSTRAINT `purchase_orders_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `purchase_orders_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`),
  CONSTRAINT `purchase_orders_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
INSERT INTO `purchase_orders` VALUES (8,2,2,16,6,7,'received','Fully received order for Lavender Massage Oil.','2026-09-17 02:40:41','2026-09-21',NULL,'2026-09-17 02:40:41','2026-09-22 02:40:41'),(9,2,2,17,6,7,'issued','Incoming stock. Goods have not yet been received.','2026-10-01 02:40:41','2026-10-07',NULL,'2026-10-01 02:40:41','2026-10-01 02:40:41'),(10,2,2,18,6,7,'partially_received','One of two ordered packs has been received.','2026-09-25 02:40:41','2026-09-28',NULL,'2026-09-25 02:40:41','2026-09-28 02:40:41');
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_request_items`
--

DROP TABLE IF EXISTS `purchase_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_request_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `purchase_request_items_purchase_request_id_product_id_unique` (`purchase_request_id`,`product_id`),
  KEY `purchase_request_items_product_id_foreign` (`product_id`),
  CONSTRAINT `purchase_request_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `purchase_request_items_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_request_items`
--

LOCK TABLES `purchase_request_items` WRITE;
/*!40000 ALTER TABLE `purchase_request_items` DISABLE KEYS */;
INSERT INTO `purchase_request_items` VALUES (16,16,5,6.000,'bottle','2026-09-15 02:40:41','2026-09-15 02:40:41'),(17,17,6,5.000,'bag','2026-09-29 02:40:41','2026-09-29 02:40:41'),(18,18,7,2.000,'pack','2026-09-23 02:40:41','2026-09-23 02:40:41'),(19,19,8,4.000,'jar','2026-10-04 20:40:41','2026-10-04 20:40:41');
/*!40000 ALTER TABLE `purchase_request_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_request_status_histories`
--

DROP TABLE IF EXISTS `purchase_request_status_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_request_status_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(255) DEFAULT NULL,
  `to_status` varchar(255) NOT NULL,
  `changed_by` bigint(20) unsigned DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_request_status_histories_changed_by_foreign` (`changed_by`),
  KEY `pr_status_history_request_status_idx` (`purchase_request_id`,`to_status`),
  CONSTRAINT `purchase_request_status_histories_changed_by_foreign` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_request_status_histories_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_request_status_histories`
--

LOCK TABLES `purchase_request_status_histories` WRITE;
/*!40000 ALTER TABLE `purchase_request_status_histories` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_request_status_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_requests`
--

DROP TABLE IF EXISTS `purchase_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `review_reason` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `purchase_requests_branch_id_foreign` (`branch_id`),
  KEY `purchase_requests_requested_by_foreign` (`requested_by`),
  KEY `purchase_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `purchase_requests_spa_id_branch_id_status_index` (`spa_id`,`branch_id`,`status`),
  CONSTRAINT `purchase_requests_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_requests_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_requests`
--

LOCK TABLES `purchase_requests` WRITE;
/*!40000 ALTER TABLE `purchase_requests` DISABLE KEYS */;
INSERT INTO `purchase_requests` VALUES (16,2,2,7,7,'Restock massage oil for regular treatment operations.','converted','Approved and converted to purchase order.','2026-09-16 02:40:41','2026-09-17 02:40:41','2026-09-15 02:40:41','2026-09-17 02:40:41'),(17,2,2,7,7,'Low stock detected. Additional foot soak is required.','converted','Approved due to low stock level.','2026-09-30 02:40:41','2026-10-01 02:40:41','2026-09-29 02:40:41','2026-10-01 02:40:41'),(18,2,2,7,7,'Critical hygiene supply stock requires replenishment.','converted','Approved for immediate hygiene supply restocking.','2026-09-24 02:40:41','2026-09-25 02:40:41','2026-09-23 02:40:41','2026-09-25 02:40:41'),(19,2,2,7,NULL,'Product is out of stock and requires replenishment.','pending',NULL,NULL,NULL,'2026-10-04 20:40:41','2026-10-04 20:40:41');
/*!40000 ALTER TABLE `purchase_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rating_photos`
--

DROP TABLE IF EXISTS `rating_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rating_photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rating_id` bigint(20) unsigned NOT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rating_photos_rating_id_foreign` (`rating_id`),
  CONSTRAINT `rating_photos_rating_id_foreign` FOREIGN KEY (`rating_id`) REFERENCES `ratings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rating_photos`
--

LOCK TABLES `rating_photos` WRITE;
/*!40000 ALTER TABLE `rating_photos` DISABLE KEYS */;
INSERT INTO `rating_photos` VALUES (1,3,'rating_photos/DGwbwfzI9IRYwVRyuBdbabS3IU7HbII2FrT1007T.png','2026-09-07 13:15:30','2026-09-07 13:15:30'),(2,4,'rating_photos/6HhMBdoLqlNpym9by1u7PY4luC8373QEjoLOYSVl.webp','2026-09-29 12:42:25','2026-09-29 12:42:25');
/*!40000 ALTER TABLE `rating_photos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ratings`
--

DROP TABLE IF EXISTS `ratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ratings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `therapist_id` bigint(20) unsigned NOT NULL,
  `customer_id` bigint(20) unsigned NOT NULL,
  `rating` int(10) unsigned NOT NULL,
  `spa_rating` tinyint(3) unsigned DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `spa_comment` varchar(500) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ratings_booking_id_unique` (`booking_id`),
  KEY `ratings_therapist_id_foreign` (`therapist_id`),
  KEY `ratings_customer_id_foreign` (`customer_id`),
  CONSTRAINT `ratings_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ratings_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ratings_therapist_id_foreign` FOREIGN KEY (`therapist_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ratings`
--

LOCK TABLES `ratings` WRITE;
/*!40000 ALTER TABLE `ratings` DISABLE KEYS */;
INSERT INTO `ratings` VALUES (1,5,9,6,4,NULL,'very good, nalibugan ako',NULL,'wala naman masarap e','2026-08-05 13:31:09','2026-08-05 13:31:09'),(2,12,17,6,4,4,'Karlo is a good and kind therapist i like him','Good Environment and good accomodation','There\'s always improvement in many ways you just have to explore it so people will love u','2026-08-30 12:18:22','2026-08-30 12:18:22'),(3,11,9,6,4,5,'THIS THERAPIST IS GOOD AND GENTLE IT MAKES MY BODY FEEL LIGHT','ITS GOOD AND THE ENVIRONMENT IS CLEAN,I\'D HIGHLY RECOMMEND THIS SPA','NOTHING TO IMPROVE HE\'S GOOD','2026-09-07 13:15:30','2026-09-07 13:15:30'),(4,3,9,15,4,5,'The way he massage is good and gentle i like it!','The room was super clean and has a aromatic smell, will come back soon!!',NULL,'2026-09-29 12:42:24','2026-09-29 12:42:24');
/*!40000 ALTER TABLE `ratings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reassignment_requests`
--

DROP TABLE IF EXISTS `reassignment_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reassignment_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `leave_request_id` bigint(20) unsigned DEFAULT NULL,
  `booking_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `old_therapist_id` bigint(20) unsigned NOT NULL,
  `new_therapist_id` bigint(20) unsigned DEFAULT NULL,
  `reason` text NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reassignment_requests_requested_by_foreign` (`requested_by`),
  KEY `reassignment_requests_old_therapist_id_foreign` (`old_therapist_id`),
  KEY `reassignment_requests_new_therapist_id_foreign` (`new_therapist_id`),
  KEY `reassignment_requests_reviewed_by_foreign` (`reviewed_by`),
  KEY `reassignment_requests_booking_id_status_index` (`booking_id`,`status`),
  KEY `reassignment_requests_leave_request_id_foreign` (`leave_request_id`),
  CONSTRAINT `reassignment_requests_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reassignment_requests_leave_request_id_foreign` FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `reassignment_requests_new_therapist_id_foreign` FOREIGN KEY (`new_therapist_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reassignment_requests_old_therapist_id_foreign` FOREIGN KEY (`old_therapist_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reassignment_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `reassignment_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reassignment_requests`
--

LOCK TABLES `reassignment_requests` WRITE;
/*!40000 ALTER TABLE `reassignment_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `reassignment_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reschedule_requests`
--

DROP TABLE IF EXISTS `reschedule_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reschedule_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `requested_date` date NOT NULL,
  `requested_time` time NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reschedule_requests_booking_id_foreign` (`booking_id`),
  KEY `reschedule_requests_requested_by_foreign` (`requested_by`),
  KEY `reschedule_requests_reviewed_by_foreign` (`reviewed_by`),
  CONSTRAINT `reschedule_requests_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reschedule_requests_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reschedule_requests_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reschedule_requests`
--

LOCK TABLES `reschedule_requests` WRITE;
/*!40000 ALTER TABLE `reschedule_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `reschedule_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (2,1),(3,2),(3,3),(3,5),(4,2),(4,3),(4,4),(4,5),(5,2),(5,3),(5,5),(6,2),(7,2),(7,3),(7,4),(7,5),(7,7),(8,2),(8,3),(8,5),(8,7),(9,2),(9,3),(9,5),(9,7),(10,2),(10,3),(10,4),(10,5),(10,7),(11,2),(11,3),(11,4),(11,5),(11,7),(12,2),(12,3),(12,7),(13,2),(13,7),(14,2),(14,3),(14,5),(14,7),(15,2),(17,2),(18,2),(18,3),(18,5),(18,7),(19,2),(19,3),(19,7),(20,2),(20,3),(20,7),(21,2),(21,7),(22,2),(22,3),(22,5),(23,2),(23,3),(24,2),(24,3),(25,2),(26,2),(26,3),(27,2),(27,3),(28,2),(29,2),(29,3),(29,8),(30,2),(31,2),(31,3),(31,8),(32,2),(32,3),(33,2),(33,3),(34,2),(34,3),(35,2),(35,3),(36,2),(37,2),(37,3),(38,2),(39,2),(39,3),(40,2),(41,2),(41,3),(42,2),(42,3),(42,4),(42,5),(42,7),(42,8),(43,2),(43,3),(44,2),(45,1),(46,1),(47,1),(48,1),(49,1),(50,1),(51,1),(52,1),(53,1),(54,1),(55,1),(57,2),(57,7),(58,2),(58,7),(59,2),(59,7),(60,2),(60,7),(61,2),(61,7),(62,2),(62,7),(63,2),(63,7),(64,2),(64,7),(65,2),(65,7),(66,2),(66,7),(67,2),(67,7),(68,2),(68,7),(69,2),(69,7),(70,2),(70,3),(70,7),(71,2),(71,7),(72,2),(73,2),(73,7),(75,2),(75,8),(76,2),(76,8),(77,2),(77,8),(78,2),(78,8),(79,2),(79,8),(80,2),(80,8),(81,2),(81,8),(82,2),(82,3),(82,4),(82,5),(82,7),(82,8),(83,2),(83,3),(83,5),(83,7),(83,8),(84,2),(84,3),(84,8),(85,2),(85,3),(85,5),(86,2),(86,3),(87,2),(87,3),(87,5),(88,2),(88,3),(88,5),(89,4),(90,4),(91,2),(91,3),(91,5),(92,2),(92,3),(93,2),(93,3),(94,2),(95,2),(96,2),(96,3),(97,2),(97,3),(97,5),(98,2),(98,3),(99,2),(99,3),(100,2),(100,3),(101,2),(101,3),(102,2),(102,3),(103,2),(103,3),(104,2),(104,3),(105,2),(105,3),(106,2),(106,3),(107,2),(107,3),(108,2),(108,3),(109,2),(109,3),(110,2),(110,3),(111,2),(111,3),(112,2),(112,3),(113,2),(113,3),(114,2),(114,3),(115,2),(115,8),(116,2),(116,8),(117,2),(117,8),(118,2),(118,8);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `guard_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(2,'owner','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(3,'manager','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(4,'therapist','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(5,'receptionist','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(6,'customer','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(7,'hr','web','2026-04-07 13:48:33','2026-04-07 13:48:33'),(8,'finance','web','2026-04-07 13:48:33','2026-04-07 13:48:33');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('available','booked','break','time_off') NOT NULL DEFAULT 'available',
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `schedules_staff_id_date_start_time_unique` (`staff_id`,`date`,`start_time`),
  CONSTRAINT `schedules_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('DeQeNjsuiggSCLAKOiiu95cqxIN2ieBJn0rogI2q',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiVVlwaWR0Qk1xUjBWZHkzY09IeFAweGJLM2ZDVFdUcWFRYXRGbzMwdCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czoxMjoibGFuZGluZy5wYWdlIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==',1791566025),('GnRefv0b77GwW4EEi8lp7WVukpRrjJQd3MsyTKdP',30,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiR0p1U0JZV041dk5BckJOREMwSzB2czhiMjhHblA2aTYwc2g5TmRQdCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDk6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9icmFuY2hlcy85L2VkaXQ/dGFiPXByb2ZpbGUiO3M6NToicm91dGUiO3M6MTM6ImJyYW5jaGVzLmVkaXQiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aTozMDtzOjE3OiJjdXJyZW50X2JyYW5jaF9pZCI7aTo5O30=',1791566011),('zWM1QfR8ag2D2vei3rboKoqdRNW0N1uCM3T83VS5',12,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiRW5OemZWQ0wyS1ZWZk91eWp0U1JLbzJnUXg5eURNQVowaFVCcU9STiI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mzk6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9vd25lci9zcGEtcHJvZmlsZSI7czo1OiJyb3V0ZSI7czoyMjoib3duZXIuc3BhLXByb2ZpbGUuZWRpdCI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjEyO3M6MTc6ImN1cnJlbnRfYnJhbmNoX2lkIjtpOjQ7fQ==',1791632841);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spa_verification_document_histories`
--

DROP TABLE IF EXISTS `spa_verification_document_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spa_verification_document_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `document_type` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `owner_expiry_date` date DEFAULT NULL,
  `ocr_expiry_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `expiry_date_raw` varchar(255) DEFAULT NULL,
  `expiry_detection_status` varchar(255) DEFAULT NULL,
  `expiry_detection_source` varchar(255) DEFAULT NULL,
  `expiry_scanned_at` timestamp NULL DEFAULT NULL,
  `expiry_verified_at` timestamp NULL DEFAULT NULL,
  `expiry_verified_by` bigint(20) unsigned DEFAULT NULL,
  `replaced_at` datetime NOT NULL,
  `replaced_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spa_verification_document_histories_expiry_verified_by_foreign` (`expiry_verified_by`),
  KEY `spa_verification_document_histories_replaced_by_foreign` (`replaced_by`),
  KEY `spa_verification_document_histories_spa_id_document_type_index` (`spa_id`,`document_type`),
  CONSTRAINT `spa_verification_document_histories_expiry_verified_by_foreign` FOREIGN KEY (`expiry_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spa_verification_document_histories_replaced_by_foreign` FOREIGN KEY (`replaced_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spa_verification_document_histories_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spa_verification_document_histories`
--

LOCK TABLES `spa_verification_document_histories` WRITE;
/*!40000 ALTER TABLE `spa_verification_document_histories` DISABLE KEYS */;
INSERT INTO `spa_verification_document_histories` VALUES (1,4,'government_id','spa-verification-documents/v15zxlLwKKHvg6y8RjChJp2u8QzDggvHUtix8GWl.jpg','OIP (3).jpg','image/jpeg',13721,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-10-06 15:10:08',22,'2026-10-06 07:10:08','2026-10-06 07:10:08'),(2,4,'dti_sec','spa-verification-documents/NX56WdKUOoHAK1UkwVo6RTJdbfZMRhobIOab27Wm.jpg','DTI.jpg','image/jpeg',60200,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-10-06 15:10:09',22,'2026-10-06 07:10:09','2026-10-06 07:10:09'),(3,4,'business_permit','spa-verification-documents/IliTRRtL7weixjd7alCEOVXXJaSGsWM8XynVPd4l.png','BusinessPermit.png','image/png',455157,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-10-06 15:10:10',22,'2026-10-06 07:10:10','2026-10-06 07:10:10'),(4,4,'business_permit','spa-verification-documents/HgyeIKGuiSF9cC3XaRnR3M0MYx6GFRiMpNskEFk9.png','BusinessPermit.png','image/png',455157,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-10-06 15:57:57',22,'2026-10-06 07:57:57','2026-10-06 07:57:57'),(5,4,'government_id','spa-verification-documents/0H9wxYPuEYvczczQdKHRL6Yc2aHvtVKncD5WtSgW.png','governmentId.png','image/png',900760,'2026-12-01',NULL,NULL,NULL,'not_found','ocr_image','2026-10-06 12:02:29',NULL,NULL,'2026-10-06 20:20:50',22,'2026-10-06 12:20:50','2026-10-06 12:20:50'),(6,4,'dti_sec','spa-verification-documents/Tye48ZfwluvA7i5sDM8o5vnoAUr7mS1DxbnEAAnn.jpg','dticert.jpg','image/jpeg',134344,NULL,'2031-02-22',NULL,'valid from February 22, 2026 to February 22, 2031','detected','ocr_image','2026-10-06 12:02:30',NULL,NULL,'2026-10-06 20:20:50',22,'2026-10-06 12:20:50','2026-10-06 12:20:50'),(7,4,'government_id','spa-verification-documents/Y2hofdUt9AOQsvP84rlcjxlb851W12i15raNZvNR.png','GOVID.png','image/png',60471,'2028-09-15',NULL,NULL,NULL,'unreadable','ocr_image','2026-10-06 12:20:50',NULL,NULL,'2026-10-08 18:03:10',22,'2026-10-08 10:03:10','2026-10-08 10:03:10'),(8,4,'business_permit','spa-verification-documents/MBUTsseW3aytt97FCuifA1o6yK6qoF7jdG9aMdx3.png','business-permit1.png','image/png',1544092,'2026-12-31','2021-12-31',NULL,'DECEMBER 31, 2021','detected','ocr_image','2026-10-06 12:02:31',NULL,NULL,'2026-10-08 18:03:10',22,'2026-10-08 10:03:10','2026-10-08 10:03:10');
/*!40000 ALTER TABLE `spa_verification_document_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spa_verification_documents`
--

DROP TABLE IF EXISTS `spa_verification_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spa_verification_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `document_type` enum('government_id','dti_sec','bir_certificate','business_permit') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) unsigned DEFAULT NULL,
  `owner_expiry_date` date DEFAULT NULL,
  `ocr_expiry_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `expiry_date_raw` varchar(255) DEFAULT NULL,
  `expiry_detection_status` varchar(255) NOT NULL DEFAULT 'not_scanned',
  `expiry_detection_source` varchar(255) DEFAULT NULL,
  `expiry_scanned_at` timestamp NULL DEFAULT NULL,
  `expiry_verified_at` timestamp NULL DEFAULT NULL,
  `expiry_verified_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spa_verification_documents_spa_id_document_type_unique` (`spa_id`,`document_type`),
  KEY `spa_verification_documents_expiry_verified_by_foreign` (`expiry_verified_by`),
  CONSTRAINT `spa_verification_documents_expiry_verified_by_foreign` FOREIGN KEY (`expiry_verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spa_verification_documents_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spa_verification_documents`
--

LOCK TABLES `spa_verification_documents` WRITE;
/*!40000 ALTER TABLE `spa_verification_documents` DISABLE KEYS */;
INSERT INTO `spa_verification_documents` VALUES (1,2,'government_id','spa-verification-documents/EE37fx7W5csY3NSFLoKGMlS8vRQkQTbGxgHzrGs1.jpg','OIP (3).jpg','image/jpeg',13721,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-06-28 14:17:25','2026-06-28 14:17:25'),(2,2,'dti_sec','spa-verification-documents/z3t9yVfltlmpIvT9cHk3OvpnpwbSJ5eTB8ZYQr5p.jpg','DTI.jpg','image/jpeg',60200,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-06-28 14:17:25','2026-06-28 14:17:25'),(3,2,'bir_certificate','spa-verification-documents/Xjhssz0BZOPR9T4nKCHoR9dP75qabv5AuhVEGFsW.png','Sample-BIR-Certificate-of-Registration-2.png','image/png',1007712,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-06-28 14:17:25','2026-06-28 14:17:25'),(4,3,'government_id','spa-verification-documents/oqoXrZLFQVNtDN8MWaVg7m6zulfBS5nAvbII7vdn.jpg','OIP (3).jpg','image/jpeg',13721,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-06-28 14:29:32','2026-07-18 14:01:30'),(5,3,'dti_sec','spa-verification-documents/n4M8YzMyVOFU5Qpjwwiy0hmXI46E0jLX0Ix2R0x2.jpg','DTI.jpg','image/jpeg',60200,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-06-28 14:29:32','2026-07-18 14:01:30'),(6,3,'bir_certificate','spa-verification-documents/WotWD5nk71oe4Gxw7sGpSpVY9Icp7Rgc8k3lu3SZ.png','Sample-BIR-Certificate-of-Registration-2.png','image/png',1007712,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-06-28 14:29:32','2026-07-18 14:01:30'),(10,5,'government_id','spa-verification-documents/E3mrlYrB2l0NCaFPq2RGpLQC8v6W4npO3OVrVHub.jpg','OIP (3).jpg','image/jpeg',13721,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-10-05 18:14:23','2026-10-05 18:14:23'),(11,5,'dti_sec','spa-verification-documents/VItjo6kU15dNQNsQuPrE25BnCDVcVwVKfABJFkdk.jpg','DTI.jpg','image/jpeg',60200,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-10-05 18:14:23','2026-10-05 18:14:23'),(12,5,'bir_certificate','spa-verification-documents/1CIIYKOJUbxvTrO3gWfMEjDWGflL1Ky3rncb0mSa.png','Sample-BIR-Certificate-of-Registration-2.png','image/png',1007712,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-10-05 18:14:23','2026-10-05 18:14:23'),(13,5,'business_permit','spa-verification-documents/vDjDo0XJ59FfRU2tU8pbV0Eu1NyoT0EMhCGpOf5m.png','BusinessPermit.png','image/png',455157,NULL,NULL,NULL,NULL,'not_scanned',NULL,NULL,NULL,NULL,'2026-10-05 18:14:23','2026-10-05 18:14:23'),(19,4,'government_id','spa-verification-documents/63POjcCENopuIIIGqgxVkvA4FUo5a8911Lf09TxF.jpg','citizencard-uk-id-card-2025.jpg','image/jpeg',93750,'2028-04-30','2028-04-30','2028-04-30','30 Apr 2028','verified','ocr_image','2026-10-08 10:03:10','2026-10-08 10:05:37',16,'2026-10-06 12:02:29','2026-10-08 10:05:37'),(20,4,'dti_sec','spa-verification-documents/7D68rKTqB13zGBiGW6dOjfPKbwu9sOShRTlhNPcX.png','DTIDOC.png','image/png',75954,NULL,'2026-10-22','2026-10-22','OCTOBER 22, 2026','verified','ocr_image','2026-10-06 12:20:50','2026-10-08 10:05:37',16,'2026-10-06 12:02:30','2026-10-08 10:05:37'),(21,4,'bir_certificate','spa-verification-documents/J4ldqRe1t0II20dxXSiPj4nDKVtU2tsPZwLgmT7n.png','Sample-BIR-Certificate-of-Registration-2.png','image/png',1007712,NULL,NULL,NULL,NULL,'not_required',NULL,NULL,'2026-10-08 10:05:37',16,'2026-10-06 12:02:30','2026-10-08 10:05:37'),(22,4,'business_permit','spa-verification-documents/zvZQTzU5SjX65XsBuKvx6mulcPrw1XiF1yhFZi78.png','BPDOC.png','image/png',89521,'2026-12-31','2026-12-31','2026-12-31','DECEMBER 31, 2026','verified','ocr_image','2026-10-08 10:03:10','2026-10-08 10:05:37',16,'2026-10-06 12:02:31','2026-10-08 10:05:37'),(23,6,'dti_sec','spa-verification-documents/BQrBJvoIP3bvoGDPQ9g1nxQiZDqBXAc9g08orAca.png','DTIDOC.png','image/png',75954,NULL,'2026-10-22','2026-10-22','OCTOBER 22, 2026','verified','ocr_image','2026-10-08 07:59:34','2026-10-08 08:19:25',16,'2026-10-08 07:59:34','2026-10-08 08:19:25'),(24,6,'bir_certificate','spa-verification-documents/NdBQcJvdF5ChzOuXhh3xTEUm3zt6G1etFaVdlkpk.png','Sample-BIR-Certificate-of-Registration-2.png','image/png',1007712,NULL,NULL,NULL,NULL,'not_required',NULL,NULL,'2026-10-08 08:19:25',16,'2026-10-08 07:59:34','2026-10-08 08:19:25'),(25,6,'business_permit','spa-verification-documents/RE2xRujL60etHCmode8zMibXPTsYELDOsq3EM0uK.png','BPDOC.png','image/png',89521,NULL,'2026-12-31','2026-12-31','DECEMBER 31, 2026','verified','ocr_image','2026-10-08 08:08:40','2026-10-08 08:19:25',16,'2026-10-08 08:08:40','2026-10-08 08:19:25'),(26,6,'government_id','spa-verification-documents/VhekBUcu7tVXM8ZjDWIeffiOzGaGUbndZrmzyxkb.jpg','citizencard-uk-id-card-2025.jpg','image/jpeg',93750,NULL,'2028-04-30','2028-04-30','30 Apr 2028','verified','ocr_image','2026-10-08 08:15:29','2026-10-08 08:19:25',16,'2026-10-08 08:15:29','2026-10-08 08:19:25');
/*!40000 ALTER TABLE `spa_verification_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `spas`
--

DROP TABLE IF EXISTS `spas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `owner_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `business_tier` varchar(255) NOT NULL DEFAULT 'basic',
  `trial_plan` varchar(255) DEFAULT NULL,
  `trial_started_at` timestamp NULL DEFAULT NULL,
  `trial_ends_at` timestamp NULL DEFAULT NULL,
  `trial_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `verification_status` enum('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  `verification_remarks` text DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `payroll_first_cutoff_day` tinyint(3) unsigned NOT NULL DEFAULT 15,
  `payroll_pay_day_offset` tinyint(3) unsigned NOT NULL DEFAULT 5 COMMENT 'Days after cutoff end',
  PRIMARY KEY (`id`),
  UNIQUE KEY `spas_name_unique` (`name`),
  KEY `spas_owner_id_foreign` (`owner_id`),
  KEY `spas_verified_by_foreign` (`verified_by`),
  CONSTRAINT `spas_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spas_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `spas`
--

LOCK TABLES `spas` WRITE;
/*!40000 ALTER TABLE `spas` DISABLE KEYS */;
INSERT INTO `spas` VALUES (2,7,'Marjo\'s Spa','business',NULL,NULL,NULL,0,'2026-06-28 13:45:20','2026-10-08 09:05:29','verified',NULL,'2026-06-28 14:17:46',1,NULL,15,5),(3,12,'Evan\'s Spa','premium',NULL,NULL,NULL,0,'2026-06-28 14:25:05','2026-10-08 06:13:46','verified',NULL,'2026-07-18 14:02:07',1,NULL,15,5),(4,22,'Princess\'s Spa','premium','premium','2026-10-08 10:06:26','2026-11-08 10:06:26',1,'2026-10-05 08:14:16','2026-10-08 10:06:26','verified',NULL,'2026-10-08 10:05:37',16,NULL,15,5),(5,30,'Jay\'s Spa','basic',NULL,NULL,NULL,0,'2026-10-05 16:41:23','2026-10-08 04:15:50','verified',NULL,'2026-10-07 15:46:02',16,NULL,15,5),(6,34,'Kive\'s Spa','basic','basic','2026-10-08 08:27:53','2026-11-08 08:39:22',1,'2026-10-08 07:37:45','2026-10-08 08:39:22','verified',NULL,'2026-10-08 08:19:25',16,NULL,15,5);
/*!40000 ALTER TABLE `spas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff`
--

DROP TABLE IF EXISTS `staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `applicant_id` bigint(20) unsigned DEFAULT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `employment_status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `hire_date` date DEFAULT NULL,
  `basic_salary` decimal(10,2) NOT NULL DEFAULT 0.00,
  `daily_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `tin` text DEFAULT NULL,
  `sss_no` text DEFAULT NULL,
  `philhealth_no` text DEFAULT NULL,
  `pagibig_no` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_applicant_id_unique` (`applicant_id`),
  KEY `staff_user_id_foreign` (`user_id`),
  KEY `staff_spa_id_foreign` (`spa_id`),
  KEY `staff_branch_id_foreign` (`branch_id`),
  CONSTRAINT `staff_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff`
--

LOCK TABLES `staff` WRITE;
/*!40000 ALTER TABLE `staff` DISABLE KEYS */;
INSERT INTO `staff` VALUES (3,9,NULL,2,2,'active','2026-06-28',0.00,0.00,'2026-06-28 14:06:00','2026-06-28 14:06:00',NULL,NULL,NULL,NULL,NULL),(4,10,NULL,2,2,'active','2026-06-28',0.00,0.00,'2026-06-28 14:07:42','2026-06-28 14:07:42',NULL,NULL,NULL,NULL,NULL),(5,17,NULL,2,2,'active','2026-08-17',0.00,0.00,'2026-08-17 14:44:17','2026-08-17 14:44:17',NULL,NULL,NULL,NULL,NULL),(6,18,NULL,2,2,'active','2026-08-21',0.00,0.00,'2026-08-21 06:38:01','2026-08-21 06:38:01',NULL,NULL,NULL,NULL,NULL),(7,19,NULL,2,2,'active','2026-10-03',0.00,0.00,'2026-10-03 06:43:31','2026-10-03 06:43:31',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_attendance`
--

DROP TABLE IF EXISTS `staff_attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned NOT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `status` enum('present','late','absent','on_leave') NOT NULL DEFAULT 'present',
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `marked_by` bigint(20) unsigned DEFAULT NULL,
  `source` enum('self','manual','system') NOT NULL DEFAULT 'manual',
  `auto_closed` tinyint(1) NOT NULL DEFAULT 0,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_attendance_staff_id_date_unique` (`staff_id`,`date`),
  KEY `staff_attendance_spa_id_foreign` (`spa_id`),
  KEY `staff_attendance_branch_id_foreign` (`branch_id`),
  KEY `staff_attendance_marked_by_foreign` (`marked_by`),
  CONSTRAINT `staff_attendance_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_attendance_marked_by_foreign` FOREIGN KEY (`marked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_attendance_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_attendance_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_attendance`
--

LOCK TABLES `staff_attendance` WRITE;
/*!40000 ALTER TABLE `staff_attendance` DISABLE KEYS */;
INSERT INTO `staff_attendance` VALUES (1,3,2,2,'2026-08-08','present','19:03:37',NULL,NULL,'self',0,NULL,'2026-08-08 11:03:37','2026-08-08 11:03:37',NULL),(2,5,2,2,'2026-08-17','late','23:22:41',NULL,NULL,'self',0,NULL,'2026-08-17 15:22:41','2026-08-17 15:22:41',NULL),(3,3,2,2,'2026-09-02','present','09:00:00','18:00:00',7,'manual',0,NULL,'2026-10-03 02:40:26','2026-10-03 02:40:26',NULL),(4,4,2,2,'2026-09-02','present','09:00:00','18:00:00',7,'manual',0,NULL,'2026-10-03 02:40:48','2026-10-03 02:40:48',NULL),(5,3,2,2,'2026-09-03','present','09:00:00','18:00:00',7,'manual',0,NULL,'2026-10-03 02:41:09','2026-10-03 02:41:09',NULL);
/*!40000 ALTER TABLE `staff_attendance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_availabilities`
--

DROP TABLE IF EXISTS `staff_availabilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_availabilities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `status` enum('available','partial','unavailable') NOT NULL DEFAULT 'available',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_avail_unique` (`user_id`,`date`),
  KEY `staff_availabilities_branch_id_foreign` (`branch_id`),
  CONSTRAINT `staff_availabilities_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_availabilities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_availabilities`
--

LOCK TABLES `staff_availabilities` WRITE;
/*!40000 ALTER TABLE `staff_availabilities` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_availabilities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_branch_deployments`
--

DROP TABLE IF EXISTS `staff_branch_deployments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_branch_deployments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned NOT NULL,
  `spa_id` bigint(20) unsigned NOT NULL,
  `requested_by` bigint(20) unsigned NOT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `from_branch_id` bigint(20) unsigned NOT NULL,
  `to_branch_id` bigint(20) unsigned NOT NULL,
  `start_date` date NOT NULL COMMENT 'When the deployment activates',
  `end_date` date DEFAULT NULL COMMENT 'When the deployment ends; null = use is_permanent flag',
  `is_permanent` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'If true, end_date is ignored and staff stays permanently',
  `status` enum('pending','approved','rejected','active','completed','cancelled') NOT NULL DEFAULT 'pending',
  `staff_response` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending' COMMENT 'Independent staff consent track — separate from Owner/HR status',
  `staff_responded_at` timestamp NULL DEFAULT NULL,
  `staff_decline_reason` text DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'HR notes when submitting',
  `deployed_at` timestamp NULL DEFAULT NULL,
  `reverted_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_branch_deployments_requested_by_foreign` (`requested_by`),
  KEY `staff_branch_deployments_reviewed_by_foreign` (`reviewed_by`),
  KEY `staff_branch_deployments_from_branch_id_foreign` (`from_branch_id`),
  KEY `staff_branch_deployments_to_branch_id_foreign` (`to_branch_id`),
  KEY `staff_branch_deployments_staff_id_status_index` (`staff_id`,`status`),
  KEY `staff_branch_deployments_spa_id_status_index` (`spa_id`,`status`),
  CONSTRAINT `staff_branch_deployments_from_branch_id_foreign` FOREIGN KEY (`from_branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_branch_deployments_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_branch_deployments_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `staff_branch_deployments_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_branch_deployments_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_branch_deployments_to_branch_id_foreign` FOREIGN KEY (`to_branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_branch_deployments`
--

LOCK TABLES `staff_branch_deployments` WRITE;
/*!40000 ALTER TABLE `staff_branch_deployments` DISABLE KEYS */;
INSERT INTO `staff_branch_deployments` VALUES (1,3,2,7,7,2,3,'2026-07-02','2027-07-02',0,'rejected','accepted','2026-08-03 11:49:21',NULL,'I CHANGE MY MIND','THANKS FOR YOUR SERVICE!',NULL,NULL,NULL,'2026-07-01 11:51:26','2026-08-03 11:58:51'),(2,3,2,7,NULL,2,3,'2026-08-03','2026-09-03',0,'cancelled','declined','2026-08-03 12:02:14','I CANT SINCE IM FAR FROM MY PLACE',NULL,'THEY NEED U',NULL,NULL,NULL,'2026-08-03 12:01:00','2026-08-03 12:15:25'),(3,3,2,7,NULL,2,3,'2026-08-03','2026-09-03',0,'cancelled','declined','2026-08-03 12:16:21','IM FAR FROM HOME',NULL,'THEY NEED U THERE',NULL,NULL,NULL,'2026-08-03 12:15:49','2026-08-03 12:16:21'),(4,3,2,7,NULL,2,3,'2026-08-03','2026-09-03',0,'cancelled','declined','2026-08-03 12:33:37','malayo po ako kulang po pamasahe ko',NULL,'they need u',NULL,NULL,NULL,'2026-08-03 12:32:46','2026-08-03 12:33:37'),(5,3,2,7,NULL,2,3,'2026-08-31','2026-10-26',0,'cancelled','accepted','2026-08-25 11:55:50',NULL,NULL,'ARE U WILLING TO DO THE WORK HERE? IF SO TELL ME',NULL,NULL,NULL,'2026-08-25 11:54:58','2026-08-25 11:57:49'),(6,3,2,7,NULL,2,3,'2026-08-31','2026-10-26',0,'cancelled','declined','2026-08-25 12:05:22','IM SORRY IM FAR AWAY FROM MY HOME',NULL,NULL,NULL,NULL,NULL,'2026-08-25 11:58:10','2026-08-25 12:05:22'),(7,4,2,7,NULL,2,3,'2026-08-31','2026-10-26',0,'cancelled','declined','2026-08-25 12:13:21','IM SORRY I CANT SINCE I HAVE A FAMILY TO BE TAKEN CARE OF',NULL,NULL,NULL,NULL,NULL,'2026-08-25 12:12:33','2026-08-25 12:13:21'),(8,4,2,10,7,2,3,'2026-08-31','2026-10-26',0,'rejected','accepted','2026-08-25 12:44:47',NULL,'MAYBE NEXT MONTH SINCE U HAVE A LOT OF THINGS TO DO','IM PLANNING TO IMPROVE WHAT\'S IN THAT BRANCH',NULL,NULL,NULL,'2026-08-25 12:44:47','2026-08-25 12:52:45'),(9,4,2,10,NULL,2,3,'2026-09-06','2026-09-13',0,'cancelled','accepted','2026-09-06 14:41:35',NULL,NULL,'fawfawfa',NULL,NULL,NULL,'2026-09-06 14:41:35','2026-09-06 14:41:41');
/*!40000 ALTER TABLE `staff_branch_deployments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_pay_profiles`
--

DROP TABLE IF EXISTS `staff_pay_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_pay_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned NOT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `pay_basis` enum('monthly','daily') NOT NULL,
  `base_rate` decimal(12,2) NOT NULL,
  `commission_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `rest_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`rest_days`)),
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_pay_profiles_staff_id_foreign` (`staff_id`),
  KEY `staff_pay_profiles_created_by_foreign` (`created_by`),
  CONSTRAINT `staff_pay_profiles_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `staff_pay_profiles_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_pay_profiles`
--

LOCK TABLES `staff_pay_profiles` WRITE;
/*!40000 ALTER TABLE `staff_pay_profiles` DISABLE KEYS */;
INSERT INTO `staff_pay_profiles` VALUES (1,3,'2026-09-01',NULL,'daily',600.00,1,'[\"Saturday\",\"Sunday\"]',7,'2026-10-03 02:36:04','2026-10-03 02:36:04');
/*!40000 ALTER TABLE `staff_pay_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_recurring_items`
--

DROP TABLE IF EXISTS `staff_recurring_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_recurring_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint(20) unsigned NOT NULL,
  `kind` enum('earning','deduction') NOT NULL,
  `component_code` varchar(32) NOT NULL,
  `label` varchar(100) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `frequency` enum('per_cutoff','per_day_worked','second_cutoff_only') NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `authorization_ref` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_recurring_items_staff_id_foreign` (`staff_id`),
  KEY `staff_recurring_items_created_by_foreign` (`created_by`),
  CONSTRAINT `staff_recurring_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `staff_recurring_items_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_recurring_items`
--

LOCK TABLES `staff_recurring_items` WRITE;
/*!40000 ALTER TABLE `staff_recurring_items` DISABLE KEYS */;
INSERT INTO `staff_recurring_items` VALUES (1,3,'earning','ALLOWANCE','TRANSPORTATION',50.00,'per_day_worked','2026-09-01',NULL,NULL,1,7,'2026-10-03 02:37:36','2026-10-03 02:37:36');
/*!40000 ALTER TABLE `staff_recurring_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_movements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `product_batch_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `booking_id` bigint(20) unsigned DEFAULT NULL,
  `movement_type` varchar(50) NOT NULL,
  `direction` varchar(10) NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `balance_before` decimal(14,3) NOT NULL,
  `balance_after` decimal(14,3) NOT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `occurred_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_movements_branch_id_foreign` (`branch_id`),
  KEY `stock_movements_product_batch_id_foreign` (`product_batch_id`),
  KEY `stock_movements_user_id_foreign` (`user_id`),
  KEY `stock_movements_booking_id_foreign` (`booking_id`),
  KEY `stock_movements_spa_id_branch_id_occurred_at_index` (`spa_id`,`branch_id`,`occurred_at`),
  KEY `stock_movements_product_id_occurred_at_index` (`product_id`,`occurred_at`),
  KEY `stock_movements_movement_type_occurred_at_index` (`movement_type`,`occurred_at`),
  KEY `stock_movements_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  CONSTRAINT `stock_movements_booking_id_foreign` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_product_batch_id_foreign` FOREIGN KEY (`product_batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `stock_movements_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=85 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_movements`
--

LOCK TABLES `stock_movements` WRITE;
/*!40000 ALTER TABLE `stock_movements` DISABLE KEYS */;
INSERT INTO `stock_movements` VALUES (78,2,3,5,38,7,NULL,'adjustment','in',2000.000,'ml',0.000,2000.000,'opening_balance',NULL,'Opening demo balance for Carmona branch.','2026-09-19 02:40:41','2026-09-19 02:40:41','2026-10-05 04:06:56'),(79,2,3,7,42,7,NULL,'adjustment','in',30.000,'pcs',0.000,30.000,'opening_balance',NULL,'Opening demo balance for Carmona branch.','2026-09-26 02:40:41','2026-09-26 02:40:41','2026-10-05 04:06:56'),(80,2,2,6,40,7,NULL,'adjustment','in',500.000,'g',0.000,500.000,'opening_balance',NULL,'Opening demo balance showing low stock.','2026-09-27 02:40:41','2026-09-27 02:40:41','2026-10-05 04:06:56'),(81,2,2,5,37,7,NULL,'purchase_receipt','in',3000.000,'ml',0.000,3000.000,'goods_receipt',13,'Six bottles received through GRN.','2026-09-22 02:40:41','2026-09-22 02:40:41','2026-10-05 04:06:56'),(82,2,2,7,41,7,NULL,'purchase_receipt','in',10.000,'pcs',0.000,10.000,'goods_receipt',14,'One pack received from partial PO delivery.','2026-09-28 02:40:41','2026-09-28 02:40:41','2026-10-05 04:06:56'),(83,2,3,5,38,7,NULL,'transfer_out','out',1000.000,'ml',2000.000,1000.000,'stock_transfer',8,'Transferred two bottles from Carmona to Imus.','2026-09-30 02:40:41','2026-09-30 02:40:41','2026-10-05 04:06:56'),(84,2,2,5,39,7,NULL,'transfer_in','in',1000.000,'ml',3000.000,4000.000,'stock_transfer',8,'Received two bottles transferred from Carmona.','2026-09-30 02:41:41','2026-09-30 02:41:41','2026-10-05 04:06:56');
/*!40000 ALTER TABLE `stock_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_transfer_items`
--

DROP TABLE IF EXISTS `stock_transfer_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_transfer_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` bigint(20) unsigned NOT NULL,
  `source_product_batch_id` bigint(20) unsigned DEFAULT NULL,
  `destination_product_batch_id` bigint(20) unsigned DEFAULT NULL,
  `source_batch_number` varchar(255) NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `expiration_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_transfer_items_source_product_batch_id_foreign` (`source_product_batch_id`),
  KEY `stock_transfer_items_destination_product_batch_id_foreign` (`destination_product_batch_id`),
  KEY `sti_transfer_batch_idx` (`stock_transfer_id`,`source_product_batch_id`),
  CONSTRAINT `stock_transfer_items_destination_product_batch_id_foreign` FOREIGN KEY (`destination_product_batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_transfer_items_source_product_batch_id_foreign` FOREIGN KEY (`source_product_batch_id`) REFERENCES `product_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_transfer_items_stock_transfer_id_foreign` FOREIGN KEY (`stock_transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_transfer_items`
--

LOCK TABLES `stock_transfer_items` WRITE;
/*!40000 ALTER TABLE `stock_transfer_items` DISABLE KEYS */;
INSERT INTO `stock_transfer_items` VALUES (11,8,38,39,'LOT-LAV-CAR-01',1000.000,'2027-03-05','2026-09-30 02:40:41','2026-10-05 04:06:56'),(12,9,42,NULL,'LOT-SHEET-CAR-01',10.000,'2027-10-05','2026-10-04 02:40:41','2026-10-05 04:06:56');
/*!40000 ALTER TABLE `stock_transfer_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_transfers`
--

DROP TABLE IF EXISTS `stock_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_transfers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `source_branch_id` bigint(20) unsigned NOT NULL,
  `destination_branch_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `requested_by` bigint(20) unsigned DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `transferred_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_transfers_product_id_foreign` (`product_id`),
  KEY `stock_transfers_requested_by_foreign` (`requested_by`),
  KEY `stock_transfers_completed_by_foreign` (`completed_by`),
  KEY `st_branch_route_idx` (`spa_id`,`source_branch_id`,`destination_branch_id`),
  KEY `st_product_status_idx` (`spa_id`,`product_id`,`status`),
  KEY `stock_transfers_source_branch_id_foreign` (`source_branch_id`),
  KEY `stock_transfers_destination_branch_id_foreign` (`destination_branch_id`),
  CONSTRAINT `stock_transfers_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_transfers_destination_branch_id_foreign` FOREIGN KEY (`destination_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `stock_transfers_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `stock_transfers_requested_by_foreign` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_transfers_source_branch_id_foreign` FOREIGN KEY (`source_branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `stock_transfers_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_transfers`
--

LOCK TABLES `stock_transfers` WRITE;
/*!40000 ALTER TABLE `stock_transfers` DISABLE KEYS */;
INSERT INTO `stock_transfers` VALUES (8,2,3,2,5,1000.000,'ml','received',7,7,'Completed transfer to support Imus treatment demand.','2026-09-30 02:40:41','2026-09-29 02:40:41','2026-10-05 04:06:56'),(9,2,3,2,7,10.000,'pcs','requested',7,NULL,'Requested transfer. Inventory has not changed yet.',NULL,'2026-10-04 02:40:41','2026-10-05 04:06:56');
/*!40000 ALTER TABLE `stock_transfers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `business_tier` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `billing_cycle` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `paymongo_checkout_id` varchar(255) DEFAULT NULL,
  `paymongo_payment_id` varchar(255) DEFAULT NULL,
  `payment_method` varchar(255) DEFAULT NULL,
  `payment_status` varchar(255) NOT NULL DEFAULT 'pending',
  `status` enum('pending','trialing','active','past_due','expired','cancelled') NOT NULL DEFAULT 'pending',
  `starts_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `expiry_notice_sent_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_spa_id_foreign` (`spa_id`),
  CONSTRAINT `subscriptions_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
INSERT INTO `subscriptions` VALUES (1,2,'premium',200.00,'monthly','cs_3f994d4f2dcbcb3d029a49e9',NULL,NULL,'paid','expired','2026-06-29 01:19:09','2026-06-29 13:06:46',NULL,NULL,'2026-06-29 01:18:19','2026-10-08 09:05:29'),(2,2,'premium',200.00,'monthly','cs_599da6f8a10a552a41160e27',NULL,NULL,'paid','expired','2026-07-01 11:34:17','2026-08-01 11:34:17',NULL,NULL,'2026-07-01 11:34:04','2026-10-08 09:05:29'),(3,3,'premium',200.00,'monthly','cs_40f946ba9742512654893c98',NULL,NULL,'pending','pending',NULL,NULL,NULL,NULL,'2026-07-18 13:54:49','2026-07-18 13:54:51'),(4,3,'premium',200.00,'monthly','cs_03dbddf750ae6bb6bf93d490',NULL,NULL,'paid','expired',NULL,'2026-08-06 07:28:42',NULL,NULL,'2026-07-18 13:59:29','2026-10-08 06:16:11'),(5,2,'premium',200.00,'monthly','cs_2b4eb507e7ea024731338cdd',NULL,NULL,'paid','expired','2026-08-03 11:56:50','2026-09-03 11:56:50',NULL,NULL,'2026-08-03 11:56:20','2026-10-08 09:05:29'),(6,2,'business',200.00,'monthly','cs_7927a2ef84c433c214e91dd3',NULL,NULL,'paid','active','2026-10-08 06:08:29','2026-11-08 06:08:29',NULL,NULL,'2026-09-06 13:45:25','2026-10-08 09:05:29'),(7,4,'premium',200.00,'monthly',NULL,NULL,NULL,'pending','pending',NULL,NULL,NULL,NULL,'2026-10-05 08:45:21','2026-10-05 08:45:21'),(8,5,'basic',799.00,'monthly','cs_11371c8164f5d7fca6c94176','pay_dqsYBD1RSrzUftpetyZ5vbEc','gcash','paid','active','2026-10-07 19:00:39','2026-11-07 19:00:39',NULL,NULL,'2026-10-07 19:00:26','2026-10-07 19:00:39'),(9,3,'premium',1499.00,'monthly','cs_98d3a67c2d756d3c740a48e7','pay_KDSQxZDp5TzYiuBuJdyigfau','gcash','paid','active','2026-10-08 06:13:46','2026-11-08 06:13:46',NULL,NULL,'2026-10-08 06:13:36','2026-10-08 06:13:46');
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `supplier_products`
--

DROP TABLE IF EXISTS `supplier_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `unit_cost` decimal(14,2) DEFAULT NULL,
  `lead_time_days` int(10) unsigned DEFAULT NULL,
  `minimum_order_quantity` decimal(14,3) DEFAULT NULL,
  `is_preferred` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_products_supplier_id_product_id_unique` (`supplier_id`,`product_id`),
  KEY `supplier_products_product_id_foreign` (`product_id`),
  CONSTRAINT `supplier_products_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_products_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `supplier_products`
--

LOCK TABLES `supplier_products` WRITE;
/*!40000 ALTER TABLE `supplier_products` DISABLE KEYS */;
INSERT INTO `supplier_products` VALUES (6,6,5,500.00,3,2.000,1,1,'2026-10-05 02:40:41','2026-10-05 02:40:41'),(7,6,6,250.00,4,3.000,1,1,'2026-10-05 02:40:41','2026-10-05 02:40:41'),(8,6,7,350.00,2,1.000,1,1,'2026-10-05 02:40:41','2026-10-05 02:40:41'),(9,6,8,300.00,5,2.000,1,1,'2026-10-05 02:40:41','2026-10-05 02:40:41');
/*!40000 ALTER TABLE `supplier_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `suppliers_spa_id_name_unique` (`spa_id`,`name`),
  CONSTRAINT `suppliers_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (6,2,'Serenity Wellness Supplies','Angela Reyes','orders@serenity-demo.local','0917-555-0101','Cavite, Philippines','active','2026-10-05 02:40:41','2026-10-05 02:40:41');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `treatment_recipe_items`
--

DROP TABLE IF EXISTS `treatment_recipe_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `treatment_recipe_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `treatment_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(14,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `treatment_recipe_items_treatment_id_product_id_unique` (`treatment_id`,`product_id`),
  KEY `treatment_recipe_items_product_id_foreign` (`product_id`),
  CONSTRAINT `treatment_recipe_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `treatment_recipe_items_treatment_id_foreign` FOREIGN KEY (`treatment_id`) REFERENCES `treatments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `treatment_recipe_items`
--

LOCK TABLES `treatment_recipe_items` WRITE;
/*!40000 ALTER TABLE `treatment_recipe_items` DISABLE KEYS */;
INSERT INTO `treatment_recipe_items` VALUES (12,12,1,100.000,'2026-10-02 08:04:00','2026-10-02 08:04:00'),(13,3,5,30.000,'2026-10-05 03:55:43','2026-10-05 03:55:43');
/*!40000 ALTER TABLE `treatment_recipe_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `treatments`
--

DROP TABLE IF EXISTS `treatments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `treatments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `duration` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `service_type` enum('in_branch_only','in_branch_and_home') NOT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `treatments_spa_id_foreign` (`spa_id`),
  KEY `treatments_branch_id_foreign` (`branch_id`),
  CONSTRAINT `treatments_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `treatments_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `treatments`
--

LOCK TABLES `treatments` WRITE;
/*!40000 ALTER TABLE `treatments` DISABLE KEYS */;
INSERT INTO `treatments` VALUES (3,2,2,'Swedish Massage',30,700.00,'in_branch_only','A gentle, relaxing full-body massage that helps relieve muscle tension, improve circulation, and promote overall relaxation and well-being.','treatments/Xj6kYpWwQUngi8HnI8B1nfZd3G0vV8GN6prAk4yc.png','2026-06-28 13:57:56','2026-07-15 12:09:11',NULL),(4,2,2,'Foot Spa',30,700.00,'in_branch_only','A soothing foot treatment that cleanses, exfoliates, and relaxes tired feet, leaving them soft, refreshed, and rejuvenated.','treatments/IJ0NFPzVnYATkAAjxZjxaYAkFRiyTjxUasPGXds1.jpg','2026-06-28 13:58:12','2026-07-15 12:09:21',NULL),(5,3,4,'Swedish Massage',30,800.00,'in_branch_only',NULL,NULL,'2026-06-28 14:26:54','2026-06-28 14:26:54',NULL),(6,3,4,'Facial Treatment',30,600.00,'in_branch_only',NULL,NULL,'2026-06-28 14:27:05','2026-06-28 14:27:05',NULL),(7,2,2,'Body Stone',60,800.00,'in_branch_only',NULL,NULL,'2026-07-01 07:40:34','2026-07-01 07:40:39','2026-07-01 07:40:39'),(12,2,2,'Marjo Catibod',1,700.00,'in_branch_only',NULL,'treatments/XZ42IMT2rCyhuS3HRDqYz7XLWkg0H02eihX38nif.jpg','2026-10-02 08:03:15','2026-10-03 02:24:27','2026-10-03 02:24:27'),(13,4,6,'Facial Treatment',30,800.00,'in_branch_only',NULL,NULL,'2026-10-05 09:00:58','2026-10-05 09:00:58',NULL),(14,4,6,'Swedish Massage',1,1000.00,'in_branch_only',NULL,NULL,'2026-10-05 09:02:25','2026-10-05 09:02:25',NULL);
/*!40000 ALTER TABLE `treatments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) NOT NULL,
  `middle_name` varchar(255) DEFAULT NULL,
  `last_name` varchar(255) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `email_verification_otp_hash` varchar(255) DEFAULT NULL,
  `email_verification_otp_expires_at` timestamp NULL DEFAULT NULL,
  `email_verification_otp_sent_at` timestamp NULL DEFAULT NULL,
  `email_verification_otp_attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `password` varchar(255) NOT NULL,
  `is_owner` tinyint(1) NOT NULL DEFAULT 0,
  `temp_password` varchar(255) DEFAULT NULL,
  `password_reset_required` tinyint(1) NOT NULL DEFAULT 0,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `spa_id` bigint(20) unsigned DEFAULT NULL,
  `branch_id` bigint(20) unsigned DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_spa_id_foreign` (`spa_id`),
  KEY `users_branch_id_foreign` (`branch_id`),
  CONSTRAINT `users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `users_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'System','Admin','User',NULL,'levictas.dev@gmail.com',NULL,'active','2026-06-28 14:16:13',NULL,NULL,NULL,0,'$2y$12$DFVe11Ve.7ClaAIJIRI4Au9nCBumraDzGBp24JNSoQCcXkpB.5eze',0,NULL,0,NULL,'2026-04-07 13:48:34','2026-06-28 14:16:18',NULL,NULL,NULL,NULL,NULL,NULL),(6,'Piolo',NULL,'Lingo',NULL,'lingopiolo@gmail.com','09357816489','active','2026-06-28 13:41:35',NULL,NULL,NULL,0,'$2y$12$MDRhZw/zIwQ6Qmdy13h7Qet75qDLbfzGdvHL6TmLSqEWLx8AtpHFW',0,NULL,0,NULL,'2026-06-28 13:40:45','2026-10-04 16:33:29',NULL,NULL,NULL,'Southern City 1, Tanzang Luma IV, Imus, Cavite, Calabarzon, 4103, Philippines',14.4153745,120.9420919),(7,'Esta',NULL,'Balong',NULL,'catibodmarjo29@gmail.com',NULL,'active','2026-06-28 13:45:05',NULL,NULL,NULL,0,'$2y$12$PsyHvhwY3iRDvffomNLLQ.QJXUZRDm1kzAqzIar3.PDp0VfpehO86',1,NULL,0,NULL,'2026-06-28 13:43:33','2026-06-28 13:45:20',NULL,2,NULL,NULL,NULL,NULL),(9,'Lingo',NULL,'Piolo',NULL,'lingo.piolo@ncst.edu.ph',NULL,'active','2026-06-28 14:06:00',NULL,NULL,NULL,0,'$2y$12$b7IB02nhC2F1T1wnoL/HhOlmJY41lg9JkNSSjP6j1WCXHRSl1S2cm',0,NULL,0,'3a7MWwd6K0Vq40fjuZYbVYDNTOLdXD8FWMHc5ksQudq4UB9FRqvzKSx6mG7t','2026-06-28 14:06:00','2026-08-03 07:43:25',NULL,2,2,NULL,NULL,NULL),(10,'Marjo',NULL,'Bodcati',NULL,'marjoguiba09@gmail.com',NULL,'active','2026-06-28 14:07:42',NULL,NULL,NULL,0,'$2y$12$RhVW2l//3AsGc7SO8PGkTeptva4LpV8XENC88fwQ5v5tyvzMwlayi',0,NULL,0,NULL,'2026-06-28 14:07:42','2026-08-06 09:12:07',NULL,2,2,NULL,NULL,NULL),(12,'Evan',NULL,'Lingo',NULL,'piololingo30@gmail.com',NULL,'active','2026-06-28 14:24:50',NULL,NULL,NULL,0,'$2y$12$sgmacNkK/ke80uT1lHfccuBsZD4ILsJRoWfj35HAr7BQgeAd1WrCe',1,NULL,0,NULL,'2026-06-28 14:24:34','2026-06-28 14:25:05',NULL,3,NULL,NULL,NULL,NULL),(13,'josh',NULL,'melia',NULL,'melia.joshwayne@ncst.edu.ph',NULL,'active','2026-10-05 18:03:57',NULL,NULL,NULL,0,'$2y$12$LMQNECxXguZOKMhA1PqUJ.EO8xaN5.BqIMAer8rYEnWMc5bmGpS7i',0,NULL,0,NULL,'2026-06-29 01:32:00','2026-10-05 18:03:57',NULL,NULL,NULL,NULL,NULL,NULL),(14,'marjo',NULL,'catibod',NULL,'marjoguiba029@gmail.com',NULL,'active','2026-10-05 18:03:57',NULL,NULL,NULL,0,'$2y$12$FFMLfPoVEWNtuX958Vnf5ud2ILI1v2ufK0sdKgZplVjTKYd9Zeezu',0,NULL,0,NULL,'2026-06-29 01:40:37','2026-10-05 18:03:57',NULL,NULL,NULL,NULL,NULL,NULL),(15,'Marjo',NULL,'Catibod',NULL,'catibod.marjo@ncst.edu.ph',NULL,'active','2026-07-12 12:32:29',NULL,NULL,NULL,0,'$2y$12$o.HrZYcOdm0vD1GHBY3wuuOMBufwGthZRig9zZ3mQ3xFpxOk.TkDq',0,NULL,0,NULL,'2026-07-12 12:32:00','2026-07-12 12:32:29',NULL,NULL,NULL,NULL,NULL,NULL),(16,'System','Admin','User',NULL,'levictas.business@gmail.com',NULL,'active','2026-10-05 18:03:57',NULL,NULL,NULL,0,'$2y$12$Jgb//WWF0tqKL1tTIoj0xOqukVbDEVCS2Xuuo4MooysLqLa/7iNMO',0,NULL,0,NULL,'2026-07-21 10:51:33','2026-10-05 18:03:57',NULL,NULL,NULL,NULL,NULL,NULL),(17,'Karlo',NULL,'Daban',NULL,'dabancarlo16@gmail.com',NULL,'active','2026-08-17 14:44:17',NULL,NULL,NULL,0,'$2y$12$BdeUYab1UbYwLdRgUlj/h.v/y3c2kbnohrfByvieXag8vDFC4Celu',0,NULL,0,NULL,'2026-08-17 14:44:17','2026-08-17 14:49:54',NULL,2,2,NULL,NULL,NULL),(18,'Pamela',NULL,'Lingo',NULL,'pamelalingo20@gmail.com',NULL,'active','2026-08-21 06:38:01',NULL,NULL,NULL,0,'$2y$12$/HVau5dbMsrYws9ilRNzNu6fb6mQ48f5CcjwPk1SDRLSnKXWSO5xu',0,NULL,0,NULL,'2026-08-21 06:38:01','2026-08-21 06:39:35',NULL,2,2,NULL,NULL,NULL),(19,'Wayne',NULL,'Melia',NULL,'melia.joshwayne052405@gmail.com',NULL,'active','2026-10-03 06:43:31',NULL,NULL,NULL,0,'$2y$12$EYwtzcbXvTt.oSc8tB9c/eBs3CJ.fI2tIvA8ogxSPQqpuPwPLe7.m',0,NULL,0,NULL,'2026-10-03 06:43:31','2026-10-03 06:45:25',NULL,2,2,NULL,NULL,NULL),(22,'Princess',NULL,'Chan',NULL,'4g3lyx5dxu@ooynib.com',NULL,'active','2026-10-05 08:12:54',NULL,NULL,NULL,0,'$2y$12$qReJ3.9ADCNLYxdw0p1io.SmsMpmjaTegOhLCVYtJbe/SicsH..7a',1,NULL,0,NULL,'2026-10-05 08:12:34','2026-10-06 06:33:55',NULL,4,NULL,NULL,NULL,NULL),(30,'Jay',NULL,'Who','Jr.','johnedwarddolor21@gmail.com',NULL,'active','2026-10-05 16:40:08',NULL,NULL,NULL,0,'$2y$12$1vU6LVQY77BTX9VrP.rs5OC4v1t077DNlHJwYL4qipPDzuoo8rgsS',1,NULL,0,NULL,'2026-10-05 16:40:08','2026-10-07 15:00:26',NULL,5,NULL,NULL,NULL,NULL),(32,'Yoro',NULL,'Yef',NULL,'yoroyef135@deertees.com',NULL,'active','2026-10-05 19:13:46',NULL,NULL,NULL,0,'$2y$12$ovfgfcPT69tYmIjKRE8RSuSj03HCrhs/m.uICZsm.cNqHDt5HtXuG',0,NULL,0,NULL,'2026-10-05 19:13:46','2026-10-05 19:13:46',NULL,NULL,NULL,NULL,NULL,NULL),(34,'Kive',NULL,'Geve','Jr.','kivege5308@herclan.com',NULL,'active','2026-10-08 07:35:52',NULL,NULL,NULL,0,'$2y$12$.JwcldJKO5yQeAXcSmJHUOZ2sYhZmHXaqBK26DQht532BopP2qr96',1,NULL,0,NULL,'2026-10-08 07:35:52','2026-10-08 07:37:45',NULL,6,NULL,NULL,NULL,NULL),(35,'Nana',NULL,'Misalucha',NULL,'nqncx98y2l@lnovic.com',NULL,'active','2026-10-08 12:40:07',NULL,NULL,NULL,0,'$2y$12$2kK8H.zCWyXeJDmrWY/de.2ioRO5B.IzFpdBjAWL/3ST/gqGvjSsW',0,NULL,0,NULL,'2026-10-08 12:40:07','2026-10-08 12:40:07',NULL,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendor_bill_items`
--

DROP TABLE IF EXISTS `vendor_bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bill_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vendor_bill_id` bigint(20) unsigned NOT NULL,
  `purchase_order_item_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `billed_quantity` decimal(12,3) NOT NULL,
  `unit` varchar(50) NOT NULL,
  `unit_cost` decimal(14,2) NOT NULL,
  `line_total` decimal(14,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_bill_po_item_unique` (`vendor_bill_id`,`purchase_order_item_id`),
  KEY `vendor_bill_items_purchase_order_item_id_foreign` (`purchase_order_item_id`),
  KEY `vendor_bill_items_product_id_foreign` (`product_id`),
  CONSTRAINT `vendor_bill_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `vendor_bill_items_purchase_order_item_id_foreign` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items` (`id`),
  CONSTRAINT `vendor_bill_items_vendor_bill_id_foreign` FOREIGN KEY (`vendor_bill_id`) REFERENCES `vendor_bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendor_bill_items`
--

LOCK TABLES `vendor_bill_items` WRITE;
/*!40000 ALTER TABLE `vendor_bill_items` DISABLE KEYS */;
INSERT INTO `vendor_bill_items` VALUES (4,4,8,5,6.000,'bottle',500.00,3000.00,'2026-09-22 02:40:41','2026-09-22 02:40:41'),(5,5,10,7,2.000,'pack',350.00,700.00,'2026-09-28 02:40:41','2026-09-28 02:40:41');
/*!40000 ALTER TABLE `vendor_bill_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendor_bills`
--

DROP TABLE IF EXISTS `vendor_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_bills` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `spa_id` bigint(20) unsigned NOT NULL,
  `branch_id` bigint(20) unsigned NOT NULL,
  `supplier_id` bigint(20) unsigned NOT NULL,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `match_details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`match_details`)),
  `matched_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_bills_supplier_invoice_unique` (`spa_id`,`supplier_id`,`invoice_number`),
  KEY `vendor_bills_branch_id_foreign` (`branch_id`),
  KEY `vendor_bills_supplier_id_foreign` (`supplier_id`),
  KEY `vendor_bills_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `vendor_bills_created_by_foreign` (`created_by`),
  KEY `vendor_bills_approved_by_foreign` (`approved_by`),
  KEY `vendor_bills_scope_status_index` (`spa_id`,`branch_id`,`status`),
  CONSTRAINT `vendor_bills_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `vendor_bills_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_bills_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `vendor_bills_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `vendor_bills_spa_id_foreign` FOREIGN KEY (`spa_id`) REFERENCES `spas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_bills_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendor_bills`
--

LOCK TABLES `vendor_bills` WRITE;
/*!40000 ALTER TABLE `vendor_bills` DISABLE KEYS */;
INSERT INTO `vendor_bills` VALUES (4,2,2,6,8,7,NULL,'DEMO-LAV-261005104041','2026-09-22','2026-10-22',3000.00,3000.00,'matched','{\"matched\":true,\"summary\":{\"matched_lines\":1,\"discrepancy_lines\":0},\"lines\":[{\"product_id\":5,\"ordered_quantity\":6,\"received_quantity\":6,\"billed_quantity\":6,\"matched\":true}]}','2026-09-23 02:40:41',NULL,NULL,'Clean 3-way match: PO 6, GRN 6, invoice 6.','2026-09-22 02:40:41','2026-09-23 02:40:41'),(5,2,2,6,10,7,NULL,'DEMO-SHEET-261005104041','2026-09-28','2026-10-28',700.00,700.00,'discrepancy','{\"matched\":false,\"summary\":{\"matched_lines\":0,\"discrepancy_lines\":1},\"lines\":[{\"product_id\":7,\"ordered_quantity\":2,\"received_quantity\":1,\"billed_quantity\":2,\"matched\":false,\"reason\":\"Supplier billed 2 packs but only 1 pack has been accepted.\"}]}',NULL,NULL,NULL,'Discrepancy demo: PO 2, GRN accepted 1, invoice billed 2.','2026-09-28 02:40:41','2026-09-28 02:40:41');
/*!40000 ALTER TABLE `vendor_bills` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-10 20:55:10
