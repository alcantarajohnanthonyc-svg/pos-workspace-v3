-- MySQL dump 10.16  Distrib 10.1.19-MariaDB, for Win32 (AMD64)
--
-- Host: localhost    Database: localhost
-- ------------------------------------------------------
-- Server version	10.1.19-MariaDB

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
-- Current Database: `pos_workspace_v3`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `pos_workspace_v3` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `pos_workspace_v3`;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usr_accounts`
--

DROP TABLE IF EXISTS `usr_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usr_accounts` (
  `user_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `employee_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(2048) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `position_id` bigint(20) unsigned DEFAULT NULL,
  `default_module_id` bigint(20) unsigned DEFAULT NULL,
  `account_status` enum('PENDING','ACTIVE','SUSPENDED','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PENDING',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_usr_accounts_email` (`email`),
  UNIQUE KEY `uq_usr_accounts_username` (`username`),
  UNIQUE KEY `uq_usr_accounts_employee_code` (`employee_code`),
  KEY `idx_usr_accounts_role` (`role_id`),
  KEY `idx_usr_accounts_status` (`account_status`),
  CONSTRAINT `fk_usr_accounts_role` FOREIGN KEY (`role_id`) REFERENCES `usr_roles` (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usr_accounts`
--

LOCK TABLES `usr_accounts` WRITE;
/*!40000 ALTER TABLE `usr_accounts` DISABLE KEYS */;
INSERT INTO `usr_accounts` VALUES (1,NULL,NULL,'John Anthony','Alcantara','https://lh3.googleusercontent.com/a/ACg8ocLwDoqRn5v_v6zAhOvaEr8OIQrmrsQ4MHFe6vt6p4Y3HVq_cCk=s96-c',NULL,'jcalcantara@bounty.com.ph',NULL,4,NULL,NULL,'ACTIVE','2026-10-23 13:11:46','2026-10-23 13:17:03');
INSERT INTO `usr_accounts` VALUES (2,'superadmin',NULL,'bounty','test',NULL,NULL,'Superadmin@gmail.com','$2y$10$ZkgCCida35QPq9DNhEX0weUtB3GtFUBWEy/bsbe1D9uG.fOQO1Jkm',4,NULL,NULL,'ACTIVE','2026-10-23 13:15:50',NULL);
/*!40000 ALTER TABLE `usr_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usr_roles`
--

DROP TABLE IF EXISTS `usr_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usr_roles` (
  `role_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system_role` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('ACTIVE','INACTIVE') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_usr_roles_code` (`role_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usr_roles`
--

LOCK TABLES `usr_roles` WRITE;
/*!40000 ALTER TABLE `usr_roles` DISABLE KEYS */;
INSERT INTO `usr_roles` VALUES (4,'SUPERADMIN','Super Administrator','System administrator',1,'ACTIVE','2026-10-23 13:15:50',NULL);
/*!40000 ALTER TABLE `usr_roles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-23 14:59:33
