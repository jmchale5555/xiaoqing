-- MariaDB dump 10.19  Distrib 10.5.22-MariaDB, for Linux (x86_64)
--
-- Host: localhost    Database: support
-- ------------------------------------------------------
-- Server version	10.5.22-MariaDB

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
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `desk_id` bigint(20) unsigned NOT NULL,
  `res_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bookings_user_id_foreign` (`user_id`),
  KEY `bookings_desk_id_foreign` (`desk_id`),
  CONSTRAINT `bookings_desk_id_foreign` FOREIGN KEY (`desk_id`) REFERENCES `desks` (`id`),
  CONSTRAINT `bookings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
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
INSERT INTO `cache` VALUES ('5d06cfd96f2f633d43c086357a4ffd35','i:1;',1727136617),('5d06cfd96f2f633d43c086357a4ffd35:timer','i:1727136617;',1727136617),('6760474be06f8cd74f3e4ab18535e99d','i:2;',1726911450),('6760474be06f8cd74f3e4ab18535e99d:timer','i:1726911450;',1726911450);
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
-- Table structure for table `desks`
--

DROP TABLE IF EXISTS `desks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `desks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint(20) unsigned NOT NULL,
  `desk_number` varchar(3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=223 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `desks`
--

LOCK TABLES `desks` WRITE;
/*!40000 ALTER TABLE `desks` DISABLE KEYS */;
INSERT INTO `desks` VALUES (1,1,'01','2024-09-14 15:31:42',NULL),(2,1,'02','2024-09-14 15:31:42',NULL),(3,1,'03','2024-09-14 15:31:42',NULL),(4,1,'04','2024-09-14 15:31:42',NULL),(5,1,'05','2024-09-14 15:31:42',NULL),(6,1,'06','2024-09-14 15:31:42',NULL),(7,1,'07','2024-09-14 15:31:42',NULL),(8,1,'08','2024-09-14 15:31:42',NULL),(9,1,'09','2024-09-14 15:31:42',NULL),(10,1,'10','2024-09-14 15:31:42',NULL),(11,1,'11','2024-09-14 15:31:42',NULL),(12,1,'12','2024-09-14 15:31:42',NULL),(13,1,'13','2024-09-14 15:31:42',NULL),(14,1,'14','2024-09-14 15:31:42',NULL),(15,1,'15','2024-09-14 15:31:42',NULL),(16,1,'16','2024-09-14 15:31:42',NULL),(17,1,'17','2024-09-14 15:31:42',NULL),(18,1,'18','2024-09-14 15:31:42',NULL),(19,1,'19','2024-09-14 15:31:42',NULL),(20,1,'20','2024-09-14 15:31:42',NULL),(21,1,'21','2024-09-14 15:31:42',NULL),(22,1,'22','2024-09-14 15:31:42',NULL),(23,1,'23','2024-09-14 15:31:42',NULL),(24,1,'24','2024-09-14 15:31:42',NULL),(25,1,'25','2024-09-14 15:31:42',NULL),(26,1,'26','2024-09-14 15:31:42',NULL),(27,1,'27','2024-09-14 15:31:42',NULL),(28,1,'28','2024-09-14 15:31:42',NULL),(29,1,'29','2024-09-14 15:31:42',NULL),(30,1,'30','2024-09-14 15:31:42',NULL),(31,1,'31','2024-09-14 15:31:42',NULL),(32,1,'32','2024-09-14 15:31:42',NULL),(33,1,'33','2024-09-14 15:31:42',NULL),(34,1,'34','2024-09-14 15:31:42',NULL),(35,1,'35','2024-09-14 15:31:42',NULL),(36,1,'36','2024-09-14 15:31:42',NULL),(37,1,'37','2024-09-14 15:31:42',NULL),(38,1,'38','2024-09-14 15:31:42',NULL),(39,1,'39','2024-09-14 15:31:42',NULL),(40,1,'40','2024-09-14 15:31:42',NULL),(41,1,'41','2024-09-14 15:31:42',NULL),(42,1,'42','2024-09-14 15:31:42',NULL),(43,1,'43','2024-09-14 15:31:42',NULL),(44,1,'44','2024-09-14 15:31:42',NULL),(45,1,'45','2024-09-14 15:31:42',NULL),(46,1,'46','2024-09-14 15:31:42',NULL),(47,1,'47','2024-09-14 15:31:42',NULL),(48,1,'48','2024-09-14 15:31:42',NULL),(49,1,'49','2024-09-14 15:31:42',NULL),(50,1,'50','2024-09-14 15:31:42',NULL),(51,1,'51','2024-09-14 15:31:42',NULL),(52,1,'52','2024-09-14 15:31:42',NULL),(53,1,'53','2024-09-14 15:31:42',NULL),(54,1,'54','2024-09-14 15:31:42',NULL),(55,1,'55','2024-09-14 15:31:42',NULL),(56,1,'56','2024-09-14 15:31:42',NULL),(57,1,'57','2024-09-14 15:31:42',NULL),(58,1,'58','2024-09-14 15:31:42',NULL),(59,1,'59','2024-09-14 15:31:42',NULL),(60,1,'60','2024-09-14 15:31:42',NULL),(61,1,'61','2024-09-14 15:31:42',NULL),(62,1,'62','2024-09-14 15:31:42',NULL),(63,1,'63','2024-09-14 15:31:42',NULL),(64,1,'64','2024-09-14 15:31:42',NULL),(65,2,'01','2024-09-14 15:31:42',NULL),(66,2,'02','2024-09-14 15:31:42',NULL),(67,2,'03','2024-09-14 15:31:42',NULL),(68,2,'04','2024-09-14 15:31:42',NULL),(69,2,'05','2024-09-14 15:31:42',NULL),(70,2,'06','2024-09-14 15:31:42',NULL),(71,2,'07','2024-09-14 15:31:42',NULL),(72,2,'08','2024-09-14 15:31:42',NULL),(73,2,'09','2024-09-14 15:31:42',NULL),(74,2,'10','2024-09-14 15:31:42',NULL),(75,2,'11','2024-09-14 15:31:42',NULL),(76,2,'12','2024-09-14 15:31:42',NULL),(77,2,'13','2024-09-14 15:31:42',NULL),(78,2,'14','2024-09-14 15:31:42',NULL),(79,2,'15','2024-09-14 15:31:42',NULL),(80,2,'16','2024-09-14 15:31:42',NULL),(81,2,'17','2024-09-14 15:31:42',NULL),(82,2,'18','2024-09-14 15:31:42',NULL),(83,3,'01','2024-09-14 15:31:42',NULL),(84,3,'02','2024-09-14 15:31:42',NULL),(85,3,'03','2024-09-14 15:31:42',NULL),(86,3,'04','2024-09-14 15:31:42',NULL),(87,3,'05','2024-09-14 15:31:42',NULL),(88,3,'06','2024-09-14 15:31:42',NULL),(89,3,'07','2024-09-14 15:31:42',NULL),(90,3,'08','2024-09-14 15:31:42',NULL),(91,3,'09','2024-09-14 15:31:42',NULL),(92,3,'10','2024-09-14 15:31:42',NULL),(93,3,'11','2024-09-14 15:31:42',NULL),(94,3,'12','2024-09-14 15:31:42',NULL),(95,4,'01','2024-09-14 15:31:42',NULL),(96,4,'02','2024-09-14 15:31:42',NULL),(97,4,'03','2024-09-14 15:31:42',NULL),(98,4,'04','2024-09-14 15:31:42',NULL),(99,4,'05','2024-09-14 15:31:42',NULL),(100,4,'06','2024-09-14 15:31:42',NULL),(101,4,'07','2024-09-14 15:31:42',NULL),(102,4,'08','2024-09-14 15:31:42',NULL),(103,4,'09','2024-09-14 15:31:42',NULL),(104,4,'10','2024-09-14 15:31:42',NULL),(105,4,'11','2024-09-14 15:31:42',NULL),(106,5,'01','2024-09-14 15:31:42',NULL),(107,5,'02','2024-09-14 15:31:42',NULL),(108,5,'03','2024-09-14 15:31:42',NULL),(109,6,'01','2024-09-14 15:31:42',NULL),(110,6,'02','2024-09-14 15:31:42',NULL),(111,6,'03','2024-09-14 15:31:42',NULL),(112,7,'01','2024-09-14 15:31:42',NULL),(113,7,'02','2024-09-14 15:31:42',NULL),(114,7,'03','2024-09-14 15:31:42',NULL),(115,7,'04','2024-09-14 15:31:42',NULL),(116,7,'05','2024-09-14 15:31:42',NULL),(117,7,'06','2024-09-14 15:31:42',NULL),(118,7,'07','2024-09-14 15:31:42',NULL),(119,7,'08','2024-09-14 15:31:42',NULL),(120,7,'09','2024-09-14 15:31:42',NULL),(121,7,'10','2024-09-14 15:31:42',NULL),(122,7,'11','2024-09-14 15:31:42',NULL),(123,7,'12','2024-09-14 15:31:42',NULL),(124,8,'01','2024-09-14 15:31:43',NULL),(125,8,'02','2024-09-14 15:31:43',NULL),(126,8,'03','2024-09-14 15:31:43',NULL),(127,9,'01','2024-09-14 15:31:43',NULL),(128,9,'02','2024-09-14 15:31:43',NULL),(129,9,'03','2024-09-14 15:31:43',NULL),(130,9,'04','2024-09-14 15:31:43',NULL),(131,10,'01','2024-09-14 15:31:43',NULL),(132,10,'02','2024-09-14 15:31:43',NULL),(133,10,'03','2024-09-14 15:31:43',NULL),(134,10,'04','2024-09-14 15:31:43',NULL),(135,10,'05','2024-09-14 15:31:43',NULL),(136,10,'06','2024-09-14 15:31:43',NULL),(137,10,'07','2024-09-14 15:31:43',NULL),(138,10,'08','2024-09-14 15:31:43',NULL),(139,10,'09','2024-09-14 15:31:43',NULL),(140,10,'10','2024-09-14 15:31:43',NULL),(141,10,'11','2024-09-14 15:31:43',NULL),(142,10,'12','2024-09-14 15:31:43',NULL),(143,10,'13','2024-09-14 15:31:43',NULL),(144,10,'14','2024-09-14 15:31:43',NULL),(145,10,'15','2024-09-14 15:31:43',NULL),(146,10,'16','2024-09-14 15:31:43',NULL),(147,10,'17','2024-09-14 15:31:43',NULL),(148,10,'18','2024-09-14 15:31:43',NULL),(149,10,'19','2024-09-14 15:31:43',NULL),(150,10,'20','2024-09-14 15:31:43',NULL),(151,10,'21','2024-09-14 15:31:43',NULL),(152,10,'22','2024-09-14 15:31:43',NULL),(153,10,'23','2024-09-14 15:31:43',NULL),(154,10,'24','2024-09-14 15:31:43',NULL),(155,10,'25','2024-09-14 15:31:43',NULL),(156,11,'01','2024-09-14 15:31:43',NULL),(157,11,'02','2024-09-14 15:31:43',NULL),(158,11,'03','2024-09-14 15:31:43',NULL),(159,11,'04','2024-09-14 15:31:43',NULL),(160,11,'05','2024-09-14 15:31:43',NULL),(161,11,'06','2024-09-14 15:31:43',NULL),(162,11,'07','2024-09-14 15:31:43',NULL),(163,11,'08','2024-09-14 15:31:43',NULL),(164,11,'09','2024-09-14 15:31:43',NULL),(165,11,'10','2024-09-14 15:31:43',NULL),(166,11,'11','2024-09-14 15:31:43',NULL),(167,11,'12','2024-09-14 15:31:43',NULL),(168,11,'13','2024-09-14 15:31:43',NULL),(169,11,'14','2024-09-14 15:31:43',NULL),(170,11,'15','2024-09-14 15:31:43',NULL),(171,11,'16','2024-09-14 15:31:43',NULL),(172,11,'17','2024-09-14 15:31:43',NULL),(173,11,'18','2024-09-14 15:31:43',NULL),(174,11,'19','2024-09-14 15:31:43',NULL),(175,11,'20','2024-09-14 15:31:43',NULL),(176,11,'21','2024-09-14 15:31:43',NULL),(177,11,'22','2024-09-14 15:31:43',NULL),(178,11,'23','2024-09-14 15:31:43',NULL),(179,11,'24','2024-09-14 15:31:43',NULL),(180,11,'25','2024-09-14 15:31:43',NULL),(181,11,'26','2024-09-14 15:31:43',NULL),(182,12,'01','2024-09-14 15:31:43',NULL),(183,12,'02','2024-09-14 15:31:43',NULL),(184,12,'03','2024-09-14 15:31:43',NULL),(185,12,'04','2024-09-14 15:31:43',NULL),(186,12,'05','2024-09-14 15:31:43',NULL),(187,12,'06','2024-09-14 15:31:43',NULL),(188,12,'07','2024-09-14 15:31:43',NULL),(189,12,'08','2024-09-14 15:31:43',NULL),(190,12,'09','2024-09-14 15:31:43',NULL),(191,12,'10','2024-09-14 15:31:43',NULL),(192,12,'11','2024-09-14 15:31:43',NULL),(193,12,'12','2024-09-14 15:31:43',NULL),(194,12,'13','2024-09-14 15:31:43',NULL),(195,12,'14','2024-09-14 15:31:43',NULL),(196,12,'15','2024-09-14 15:31:43',NULL),(197,12,'16','2024-09-14 15:31:43',NULL),(198,12,'17','2024-09-14 15:31:43',NULL),(199,12,'18','2024-09-14 15:31:43',NULL),(200,12,'19','2024-09-14 15:31:43',NULL),(201,12,'20','2024-09-14 15:31:43',NULL),(202,12,'21','2024-09-14 15:31:43',NULL),(203,12,'22','2024-09-14 15:31:43',NULL),(204,12,'23','2024-09-14 15:31:43',NULL),(205,12,'24','2024-09-14 15:31:43',NULL),(206,12,'25','2024-09-14 15:31:43',NULL),(207,13,'01','2024-09-14 15:31:43',NULL),(208,13,'02','2024-09-14 15:31:43',NULL),(209,13,'03','2024-09-14 15:31:43',NULL),(210,13,'04','2024-09-14 15:31:43',NULL),(211,13,'05','2024-09-14 15:31:43',NULL),(212,13,'06','2024-09-14 15:31:43',NULL),(213,13,'07','2024-09-14 15:31:43',NULL),(214,13,'08','2024-09-14 15:31:43',NULL),(215,13,'09','2024-09-14 15:31:43',NULL),(216,13,'10','2024-09-14 15:31:43',NULL),(217,13,'11','2024-09-14 15:31:43',NULL),(218,13,'12','2024-09-14 15:31:43',NULL),(219,14,'01','2024-09-14 15:31:43',NULL),(220,14,'02','2024-09-14 15:31:43',NULL),(221,14,'03','2024-09-14 15:31:43',NULL),(222,14,'04','2024-09-14 15:31:43',NULL);
/*!40000 ALTER TABLE `desks` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2024_06_12_151732_add_two_factor_columns_to_users_table',1),(5,'2024_06_12_151744_create_personal_access_tokens_table',1),(6,'2024_06_15_114413_add_ldap_columns_to_users_table',1),(7,'2024_06_15_122812_rename_email_column_in_users_table',1),(8,'2024_06_23_163931_create_desks_table',1),(9,'2024_06_29_091913_create_bookings_table',1),(10,'2024_09_04_223123_create_rooms_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
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
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
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
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
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
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (1,'MPEB.708','2024-09-14 15:31:42',NULL),(2,'R1006','2024-09-14 15:31:42',NULL),(3,'R1010','2024-09-14 15:31:42',NULL),(4,'R1012','2024-09-14 15:31:42',NULL),(5,'R1104','2024-09-14 15:31:42',NULL),(6,'R1105','2024-09-14 15:31:42',NULL),(7,'R1111','2024-09-14 15:31:42',NULL),(8,'R1116','2024-09-14 15:31:42',NULL),(9,'R1121','2024-09-14 15:31:42',NULL),(10,'R708','2024-09-14 15:31:42',NULL),(11,'R804','2024-09-14 15:31:42',NULL),(12,'R808','2024-09-14 15:31:42',NULL),(13,'R905','2024-09-14 15:31:42',NULL),(14,'R915','2024-09-14 15:31:42',NULL);
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
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
INSERT INTO `sessions` VALUES ('A20ler9zpHroyWg7DrRyLXHtA96Cw6SKybzAXvQq',1,'192.168.1.68','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 Edg/128.0.0.0','YTo2OntzOjY6Il90b2tlbiI7czo0MDoiS2hDdTE5dnJ3QTJhY3owUGd4T0VhQ1lqclBreTYwNGZXY0RyNGZxUyI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjE6e3M6MzoidXJsIjtzOjM2OiJodHRwOi8vZGVza3MubG9jYWxuZXQubGFuL2Jvb2tpbmdzLzUiO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjUwOiJsb2dpbl93ZWJfNTliYTM2YWRkYzJiMmY5NDAxNTgwZjAxNGM3ZjU4ZWE0ZTMwOTg5ZCI7aToxO3M6MjE6InBhc3N3b3JkX2hhc2hfc2FuY3R1bSI7czo2MDoiJDJ5JDEyJHJIMUd5LmNqYkFvemY5Wm5aaXg2RHV3dzJ4SGNJWk41WVU5aHgxNEtxMVF2MXcxMjlsNDhDIjt9',1727136702);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `username` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `two_factor_secret` text DEFAULT NULL,
  `two_factor_recovery_codes` text DEFAULT NULL,
  `two_factor_confirmed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `current_team_id` bigint(20) unsigned DEFAULT NULL,
  `profile_photo_path` varchar(2048) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `guid` varchar(255) DEFAULT NULL,
  `domain` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`username`),
  UNIQUE KEY `users_guid_unique` (`guid`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Jim Bowen','jim',NULL,'$2y$12$rH1Gy.cjbAozf9ZnZix6Duww2xHcIZN5YU9hx14Kq1Qv1w129l48C',NULL,NULL,NULL,NULL,NULL,NULL,'2024-09-21 08:45:24','2024-09-21 08:45:24','b7871bea-dfea-11ee-960b-525400484a8e','default');
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

-- Dump completed on 2024-11-07 23:50:02
