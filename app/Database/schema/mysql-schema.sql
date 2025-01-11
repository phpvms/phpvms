/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `acars`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acars` (
  `id` varchar(36) NOT NULL,
  `pirep_id` varchar(36) NOT NULL,
  `type` tinyint(3) unsigned NOT NULL,
  `nav_type` int(10) unsigned DEFAULT NULL,
  `order` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(191) DEFAULT NULL,
  `status` char(3) NOT NULL DEFAULT 'SCH',
  `log` varchar(191) DEFAULT NULL,
  `lat` decimal(10,5) DEFAULT 0.00000,
  `lon` decimal(11,5) DEFAULT 0.00000,
  `distance` int(10) unsigned DEFAULT NULL,
  `heading` int(10) unsigned DEFAULT NULL,
  `altitude_agl` decimal(8,2) DEFAULT 0.00,
  `altitude_msl` decimal(8,2) DEFAULT 0.00,
  `vs` double DEFAULT 0,
  `gs` int(11) DEFAULT NULL,
  `ias` int(11) DEFAULT NULL,
  `transponder` int(10) unsigned DEFAULT NULL,
  `autopilot` varchar(191) DEFAULT NULL,
  `fuel` decimal(8,2) DEFAULT NULL,
  `fuel_flow` decimal(8,2) DEFAULT NULL,
  `sim_time` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `source` varchar(5) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `acars_pirep_id_index` (`pirep_id`),
  KEY `acars_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(191) DEFAULT NULL,
  `description` text NOT NULL,
  `subject_type` varchar(191) DEFAULT NULL,
  `event` varchar(191) DEFAULT NULL,
  `subject_id` char(36) DEFAULT NULL,
  `causer_type` varchar(191) DEFAULT NULL,
  `causer_id` bigint(20) unsigned DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`properties`)),
  `batch_uuid` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`,`subject_id`),
  KEY `causer` (`causer_type`,`causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `aircraft`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aircraft` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `subfleet_id` int(10) unsigned NOT NULL,
  `icao` varchar(4) DEFAULT NULL,
  `iata` varchar(4) DEFAULT NULL,
  `airport_id` varchar(5) DEFAULT NULL,
  `hub_id` varchar(5) DEFAULT NULL,
  `landing_time` timestamp NULL DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `registration` varchar(10) DEFAULT NULL,
  `fin` varchar(5) DEFAULT NULL,
  `hex_code` varchar(10) DEFAULT NULL,
  `selcal` varchar(5) DEFAULT NULL,
  `dow` decimal(10,2) unsigned DEFAULT NULL,
  `mtow` decimal(10,2) unsigned DEFAULT 0.00,
  `mlw` decimal(10,2) unsigned DEFAULT NULL,
  `zfw` decimal(10,2) unsigned DEFAULT 0.00,
  `simbrief_type` varchar(25) DEFAULT NULL,
  `fuel_onboard` decimal(8,2) unsigned DEFAULT 0.00,
  `flight_time` bigint(20) unsigned DEFAULT 0,
  `status` char(1) NOT NULL DEFAULT 'A',
  `state` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `aircraft_registration_unique` (`registration`),
  UNIQUE KEY `aircraft_fin_unique` (`fin`),
  KEY `aircraft_airport_id_index` (`airport_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `airlines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `airlines` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `icao` varchar(5) NOT NULL,
  `iata` varchar(5) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `callsign` varchar(191) DEFAULT NULL,
  `country` varchar(2) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `total_flights` bigint(20) unsigned DEFAULT 0,
  `total_time` bigint(20) unsigned DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `airlines_icao_unique` (`icao`),
  KEY `airlines_icao_index` (`icao`),
  KEY `airlines_iata_index` (`iata`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `airports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `airports` (
  `id` varchar(4) NOT NULL,
  `iata` varchar(5) DEFAULT NULL,
  `icao` varchar(5) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `region` varchar(150) DEFAULT NULL,
  `country` varchar(64) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `hub` tinyint(1) NOT NULL DEFAULT 0,
  `notes` mediumtext DEFAULT NULL,
  `lat` decimal(10,5) DEFAULT 0.00000,
  `lon` decimal(11,5) DEFAULT 0.00000,
  `elevation` int(11) DEFAULT NULL,
  `ground_handling_cost` decimal(8,2) unsigned DEFAULT 0.00,
  `fuel_100ll_cost` decimal(8,2) unsigned DEFAULT 0.00,
  `fuel_jeta_cost` decimal(8,2) unsigned DEFAULT 0.00,
  `fuel_mogas_cost` decimal(8,2) unsigned DEFAULT 0.00,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `airports_icao_index` (`icao`),
  KEY `airports_iata_index` (`iata`),
  KEY `airports_hub_index` (`hub`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `awards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` text DEFAULT NULL,
  `ref_model` varchar(191) DEFAULT NULL,
  `ref_model_params` text DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `awards_ref_model_index` (`ref_model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bids`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bids` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `flight_id` varchar(36) NOT NULL,
  `aircraft_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bids_user_id_index` (`user_id`),
  KEY `bids_user_id_flight_id_index` (`user_id`,`flight_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `type` int(10) unsigned NOT NULL DEFAULT 0,
  `name` varchar(250) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `airline_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(191) NOT NULL,
  `amount` int(10) unsigned NOT NULL,
  `type` char(191) NOT NULL,
  `flight_type` varchar(50) DEFAULT NULL,
  `charge_to_user` tinyint(1) DEFAULT 0,
  `multiplier` tinyint(1) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `ref_model` varchar(191) DEFAULT NULL,
  `ref_model_id` varchar(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `expenses_ref_model_ref_model_id_index` (`ref_model`,`ref_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `fares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fares` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL,
  `name` varchar(50) NOT NULL,
  `price` decimal(8,2) unsigned DEFAULT 0.00,
  `cost` decimal(8,2) unsigned DEFAULT 0.00,
  `capacity` int(10) unsigned DEFAULT 0,
  `type` tinyint(3) unsigned DEFAULT 0,
  `notes` varchar(191) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fares_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `files` (
  `id` varchar(16) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `disk` mediumtext DEFAULT NULL,
  `path` mediumtext DEFAULT NULL,
  `public` tinyint(1) NOT NULL DEFAULT 1,
  `download_count` int(10) unsigned NOT NULL DEFAULT 0,
  `ref_model` varchar(50) DEFAULT NULL,
  `ref_model_id` varchar(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `files_ref_model_ref_model_id_index` (`ref_model`,`ref_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flight_fare`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flight_fare` (
  `flight_id` varchar(36) NOT NULL,
  `fare_id` int(10) unsigned NOT NULL,
  `price` varchar(10) DEFAULT NULL,
  `cost` varchar(10) DEFAULT NULL,
  `capacity` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`flight_id`,`fare_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flight_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flight_field_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `flight_id` varchar(36) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) DEFAULT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flight_field_values_flight_id_index` (`flight_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flight_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flight_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flight_subfleet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flight_subfleet` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `subfleet_id` int(10) unsigned NOT NULL,
  `flight_id` varchar(36) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `flight_subfleet_subfleet_id_flight_id_index` (`subfleet_id`,`flight_id`),
  KEY `flight_subfleet_flight_id_subfleet_id_index` (`flight_id`,`subfleet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `flights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `flights` (
  `id` varchar(36) NOT NULL,
  `airline_id` int(10) unsigned NOT NULL,
  `flight_number` int(10) unsigned NOT NULL,
  `callsign` varchar(4) DEFAULT NULL,
  `route_code` varchar(5) DEFAULT NULL,
  `route_leg` int(10) unsigned DEFAULT NULL,
  `dpt_airport_id` varchar(4) NOT NULL,
  `arr_airport_id` varchar(4) NOT NULL,
  `alt_airport_id` varchar(4) DEFAULT NULL,
  `dpt_time` varchar(10) DEFAULT NULL,
  `arr_time` varchar(10) DEFAULT NULL,
  `level` int(10) unsigned DEFAULT 0,
  `distance` decimal(8,2) unsigned DEFAULT 0.00,
  `flight_time` int(10) unsigned DEFAULT NULL,
  `flight_type` char(1) NOT NULL DEFAULT 'J',
  `load_factor` decimal(5,2) DEFAULT NULL,
  `load_factor_variance` decimal(5,2) DEFAULT NULL,
  `route` text DEFAULT NULL,
  `pilot_pay` decimal(8,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `scheduled` tinyint(1) DEFAULT 0,
  `days` tinyint(3) unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `has_bid` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `event_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `owner_type` varchar(191) DEFAULT NULL,
  `owner_id` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flights_flight_number_index` (`flight_number`),
  KEY `flights_dpt_airport_id_index` (`dpt_airport_id`),
  KEY `flights_arr_airport_id_index` (`arr_airport_id`),
  KEY `flights_owner_type_owner_id_index` (`owner_type`,`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) DEFAULT NULL,
  `token` varchar(191) NOT NULL,
  `usage_count` int(11) NOT NULL DEFAULT 0,
  `usage_limit` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journal_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_transactions` (
  `id` char(36) NOT NULL,
  `transaction_group` varchar(191) DEFAULT NULL,
  `journal_id` int(11) NOT NULL,
  `credit` bigint(20) unsigned DEFAULT NULL,
  `debit` bigint(20) unsigned DEFAULT NULL,
  `currency` char(5) NOT NULL,
  `memo` text DEFAULT NULL,
  `tags` varchar(191) DEFAULT NULL,
  `ref_model` varchar(50) DEFAULT NULL,
  `ref_model_id` varchar(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `post_date` date NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `journal_transactions_id_unique` (`id`),
  KEY `journal_transactions_journal_id_index` (`journal_id`),
  KEY `journal_transactions_transaction_group_index` (`transaction_group`),
  KEY `journal_transactions_ref_model_ref_model_id_index` (`ref_model`,`ref_model_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `journals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `journals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ledger_id` int(10) unsigned DEFAULT NULL,
  `type` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `balance` bigint(20) NOT NULL DEFAULT 0,
  `currency` varchar(5) NOT NULL,
  `morphed_type` varchar(191) DEFAULT NULL,
  `morphed_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journals_morphed_type_morphed_id_index` (`morphed_type`,`morphed_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `kvp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kvp` (
  `key` varchar(191) NOT NULL,
  `value` varchar(191) NOT NULL,
  KEY `kvp_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ledgers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ledgers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` enum('asset','liability','equity','income','expense') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations_data`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations_data` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `navdata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `navdata` (
  `id` varchar(5) NOT NULL,
  `name` varchar(24) NOT NULL,
  `type` int(10) unsigned NOT NULL,
  `lat` double(7,4) DEFAULT 0.0000,
  `lon` double(7,4) DEFAULT 0.0000,
  `freq` varchar(7) DEFAULT NULL,
  PRIMARY KEY (`id`,`name`),
  KEY `navdata_id_index` (`id`),
  KEY `navdata_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `subject` varchar(191) NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(191) NOT NULL,
  `notifiable_type` varchar(191) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `slug` varchar(191) NOT NULL,
  `icon` varchar(191) DEFAULT NULL,
  `type` smallint(5) unsigned NOT NULL DEFAULT 0,
  `public` tinyint(1) NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `body` mediumtext DEFAULT NULL,
  `link` varchar(191) DEFAULT '',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `new_window` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `pages_slug_index` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`),
  KEY `password_resets_token_index` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `permission_id` int(10) unsigned NOT NULL,
  `role_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_id_foreign` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_user` (
  `permission_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `user_type` varchar(191) NOT NULL,
  PRIMARY KEY (`user_id`,`permission_id`,`user_type`),
  KEY `permission_user_permission_id_foreign` (`permission_id`),
  CONSTRAINT `permission_user_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `display_name` varchar(191) DEFAULT NULL,
  `description` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pirep_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pirep_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pirep_id` varchar(36) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pirep_fares`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pirep_fares` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pirep_id` varchar(36) NOT NULL,
  `fare_id` bigint(20) unsigned DEFAULT NULL,
  `count` int(10) unsigned DEFAULT 0,
  `code` varchar(191) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `price` decimal(8,2) unsigned DEFAULT 0.00,
  `cost` decimal(8,2) unsigned DEFAULT 0.00,
  `capacity` int(10) unsigned DEFAULT 0,
  `type` tinyint(3) unsigned DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pirep_fares_pirep_id_index` (`pirep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pirep_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pirep_field_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pirep_id` varchar(36) NOT NULL,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) DEFAULT NULL,
  `value` varchar(191) DEFAULT NULL,
  `source` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pirep_field_values_pirep_id_index` (`pirep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pirep_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pirep_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `slug` varchar(50) DEFAULT NULL,
  `description` varchar(191) DEFAULT NULL,
  `required` tinyint(1) DEFAULT 0,
  `pirep_source` tinyint(4) DEFAULT 3,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pireps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pireps` (
  `id` varchar(36) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `airline_id` int(10) unsigned NOT NULL,
  `aircraft_id` int(10) unsigned DEFAULT NULL,
  `event_id` int(10) unsigned DEFAULT NULL,
  `flight_id` varchar(36) DEFAULT NULL,
  `flight_number` varchar(10) DEFAULT NULL,
  `route_code` varchar(5) DEFAULT NULL,
  `route_leg` varchar(5) DEFAULT NULL,
  `flight_type` char(1) NOT NULL DEFAULT 'J',
  `dpt_airport_id` varchar(5) NOT NULL,
  `arr_airport_id` varchar(5) NOT NULL,
  `alt_airport_id` varchar(5) DEFAULT NULL,
  `level` int(10) unsigned DEFAULT NULL,
  `distance` decimal(8,2) unsigned DEFAULT NULL,
  `planned_distance` decimal(8,2) unsigned DEFAULT NULL,
  `flight_time` int(10) unsigned DEFAULT NULL,
  `planned_flight_time` int(10) unsigned DEFAULT NULL,
  `zfw` decimal(8,2) unsigned DEFAULT NULL,
  `block_fuel` decimal(8,2) unsigned DEFAULT NULL,
  `fuel_used` decimal(8,2) unsigned DEFAULT NULL,
  `landing_rate` decimal(8,2) DEFAULT NULL,
  `score` smallint(6) DEFAULT NULL,
  `route` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `source` tinyint(3) unsigned DEFAULT 0,
  `source_name` varchar(50) DEFAULT NULL,
  `state` smallint(5) unsigned NOT NULL DEFAULT 1,
  `status` char(3) NOT NULL DEFAULT 'SCH',
  `submitted_at` datetime DEFAULT NULL,
  `block_off_time` datetime DEFAULT NULL,
  `block_on_time` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pireps_user_id_index` (`user_id`),
  KEY `pireps_flight_number_index` (`flight_number`),
  KEY `pireps_dpt_airport_id_index` (`dpt_airport_id`),
  KEY `pireps_arr_airport_id_index` (`arr_airport_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ranks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ranks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `image_url` varchar(191) DEFAULT NULL,
  `hours` int(10) unsigned NOT NULL DEFAULT 0,
  `acars_base_pay_rate` decimal(8,2) unsigned DEFAULT 0.00,
  `manual_base_pay_rate` decimal(8,2) unsigned DEFAULT 0.00,
  `auto_approve_acars` tinyint(1) DEFAULT 0,
  `auto_approve_manual` tinyint(1) DEFAULT 0,
  `auto_promote` tinyint(1) DEFAULT 1,
  `auto_approve_above_score` tinyint(1) DEFAULT 0,
  `auto_approve_score` smallint(6) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ranks_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_user` (
  `role_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `user_type` varchar(191) NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`,`user_type`),
  KEY `role_user_role_id_foreign` (`role_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `display_name` varchar(191) DEFAULT NULL,
  `description` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `read_only` tinyint(1) NOT NULL DEFAULT 0,
  `disable_activity_checks` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` text NOT NULL,
  `last_activity` int(11) NOT NULL,
  UNIQUE KEY `sessions_id_unique` (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` varchar(191) NOT NULL,
  `offset` int(10) unsigned NOT NULL DEFAULT 0,
  `order` int(10) unsigned NOT NULL DEFAULT 99,
  `key` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `value` varchar(191) NOT NULL,
  `default` varchar(191) DEFAULT NULL,
  `group` varchar(191) DEFAULT NULL,
  `type` varchar(191) DEFAULT NULL,
  `options` text DEFAULT NULL,
  `description` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `settings_key_index` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `simbrief`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simbrief` (
  `id` varchar(36) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `flight_id` varchar(36) DEFAULT NULL,
  `pirep_id` varchar(36) DEFAULT NULL,
  `aircraft_id` int(10) unsigned DEFAULT NULL,
  `acars_xml` mediumtext NOT NULL,
  `ofp_xml` mediumtext NOT NULL,
  `fare_data` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `simbrief_pirep_id_unique` (`pirep_id`),
  KEY `simbrief_user_id_flight_id_index` (`user_id`,`flight_id`),
  KEY `simbrief_pirep_id_index` (`pirep_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `simbrief_aircraft`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simbrief_aircraft` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `icao` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `details` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `simbrief_airframes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simbrief_airframes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `icao` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `airframe_id` varchar(191) DEFAULT NULL,
  `source` tinyint(3) unsigned DEFAULT NULL,
  `details` mediumtext DEFAULT NULL,
  `options` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `simbrief_layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `simbrief_layouts` (
  `id` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `name_long` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stats` (
  `id` varchar(191) NOT NULL,
  `value` varchar(191) NOT NULL,
  `order` int(10) unsigned NOT NULL,
  `type` varchar(191) DEFAULT NULL,
  `description` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subfleet_fare`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subfleet_fare` (
  `subfleet_id` int(10) unsigned NOT NULL,
  `fare_id` int(10) unsigned NOT NULL,
  `price` varchar(191) DEFAULT NULL,
  `cost` varchar(191) DEFAULT NULL,
  `capacity` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`subfleet_id`,`fare_id`),
  KEY `subfleet_fare_fare_id_subfleet_id_index` (`fare_id`,`subfleet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subfleet_rank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subfleet_rank` (
  `rank_id` int(10) unsigned NOT NULL,
  `subfleet_id` int(10) unsigned NOT NULL,
  `acars_pay` varchar(191) DEFAULT NULL,
  `manual_pay` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`rank_id`,`subfleet_id`),
  KEY `subfleet_rank_subfleet_id_rank_id_index` (`subfleet_id`,`rank_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `subfleets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `subfleets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `airline_id` int(10) unsigned DEFAULT NULL,
  `hub_id` varchar(4) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `simbrief_type` varchar(20) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `cost_block_hour` decimal(8,2) unsigned DEFAULT 0.00,
  `cost_delay_minute` decimal(8,2) unsigned DEFAULT 0.00,
  `fuel_type` tinyint(3) unsigned DEFAULT NULL,
  `ground_handling_multiplier` decimal(8,2) unsigned DEFAULT 100.00,
  `cargo_capacity` decimal(8,2) unsigned DEFAULT NULL,
  `fuel_capacity` decimal(8,2) unsigned DEFAULT NULL,
  `gross_weight` decimal(8,2) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `typerating_subfleet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `typerating_subfleet` (
  `typerating_id` int(10) unsigned NOT NULL,
  `subfleet_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`typerating_id`,`subfleet_id`),
  KEY `typerating_subfleet_typerating_id_subfleet_id_index` (`typerating_id`,`subfleet_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `typerating_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `typerating_user` (
  `typerating_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`typerating_id`,`user_id`),
  KEY `typerating_user_typerating_id_user_id_index` (`typerating_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `typeratings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `typeratings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `type` varchar(191) NOT NULL,
  `description` varchar(191) DEFAULT NULL,
  `image_url` varchar(191) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `typeratings_id_unique` (`id`),
  UNIQUE KEY `typeratings_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_awards` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `award_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_awards_user_id_award_id_index` (`user_id`,`award_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_field_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_field_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_field_id` bigint(20) unsigned NOT NULL,
  `user_id` varchar(16) NOT NULL,
  `value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_field_values_user_field_id_user_id_index` (`user_field_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `show_on_registration` tinyint(1) DEFAULT 0,
  `required` tinyint(1) DEFAULT 0,
  `private` tinyint(1) DEFAULT 0,
  `internal` tinyint(1) NOT NULL DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_oauth_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_oauth_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `provider` varchar(191) NOT NULL,
  `token` text NOT NULL,
  `refresh_token` text NOT NULL,
  `last_refreshed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `pilot_id` bigint(20) unsigned DEFAULT NULL,
  `callsign` varchar(191) DEFAULT NULL,
  `name` varchar(191) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `password` varchar(191) NOT NULL,
  `api_key` varchar(40) DEFAULT NULL,
  `airline_id` int(10) unsigned NOT NULL,
  `rank_id` int(10) unsigned DEFAULT NULL,
  `discord_id` varchar(191) NOT NULL DEFAULT '',
  `discord_private_channel_id` varchar(191) NOT NULL DEFAULT '',
  `vatsim_id` varchar(191) NOT NULL DEFAULT '',
  `ivao_id` varchar(191) NOT NULL DEFAULT '',
  `country` varchar(2) DEFAULT NULL,
  `home_airport_id` varchar(5) DEFAULT NULL,
  `curr_airport_id` varchar(5) DEFAULT NULL,
  `last_pirep_id` varchar(36) DEFAULT NULL,
  `flights` bigint(20) unsigned NOT NULL DEFAULT 0,
  `flight_time` bigint(20) unsigned DEFAULT 0,
  `transfer_time` bigint(20) unsigned DEFAULT 0,
  `avatar` varchar(191) DEFAULT NULL,
  `timezone` varchar(64) DEFAULT NULL,
  `status` tinyint(3) unsigned DEFAULT 0,
  `state` tinyint(3) unsigned DEFAULT 0,
  `toc_accepted` tinyint(1) DEFAULT NULL,
  `opt_in` tinyint(1) DEFAULT NULL,
  `active` tinyint(1) DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `lastlogin_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_pilot_id_unique` (`pilot_id`),
  KEY `users_email_index` (`email`),
  KEY `users_api_key_index` (`api_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'2017_06_07_014930_create_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2017_06_08_0000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'2017_06_08_0001_roles_permissions_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2017_06_08_0005_create_password_resets_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2017_06_08_0006_create_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2017_06_08_191703_create_airlines_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2017_06_09_010621_create_aircrafts_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2017_06_10_040335_create_fares_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2017_06_11_135707_create_airports_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2017_06_17_214650_create_flight_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2017_06_21_165410_create_ranks_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2017_06_23_011011_create_subfleet_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2017_06_28_195426_create_pirep_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2017_12_12_174519_create_bids_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2017_12_14_225241_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2017_12_14_225337_create_failed_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2017_12_20_004147_create_navdata_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2017_12_20_005147_create_acars_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2018_01_03_014930_create_stats_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2018_01_08_142204_create_news_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2018_01_28_180522_create_awards_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2018_02_26_185121_create_expenses_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2018_02_28_231807_create_journal_transactions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2018_02_28_231813_create_journals_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2018_02_28_232438_create_ledgers_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2018_04_01_193443_create_files_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2019_06_19_220910_add_readonly_to_roles',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2019_07_16_141152_users_add_pilot_id',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2019_08_30_132224_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2019_09_16_141152_pireps_change_state_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2019_10_30_141152_pireps_add_flight_id',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2020_02_12_141152_expenses_add_flight_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2020_02_26_044305_modify_airports_coordinates',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2020_03_04_044305_flight_field_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2020_03_05_141152_flights_add_load_factor',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2020_03_06_141152_flights_add_pilot_pay',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2020_03_06_141153_fares_add_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2020_03_09_141152_increase_id_lengths',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2020_03_09_141153_remove_subfleet_type_index',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2020_03_11_141153_add_simbrief_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2020_03_27_174238_create_pages',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (42,'2020_03_28_174238_airline_remove_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (43,'2020_03_28_174238_page_icon_nullable',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (44,'2020_06_09_141153_pages_add_link',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (45,'2020_07_21_141153_create_user_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (46,'2020_09_03_141152_aircraft_add_mtow',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (47,'2020_09_30_081536_create_modules_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (48,'2020_11_20_044305_modify_pages_size',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (49,'2020_11_26_044305_modify_download_link_size',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (50,'2021_01_17_044305_add_hub_to_subfleets',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (51,'2021_02_10_044305_change_acars_vs_type',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (52,'2021_02_11_044305_update_sessions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (53,'2021_02_23_150601_aircraft_add_fuelonboard',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (54,'2021_02_23_205630_add_sbtype_to_subfleets',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (55,'2021_03_01_044305_add_aircraft_to_simbrief',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (56,'2021_03_05_044305_add_kvp_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (57,'2021_03_18_161419_add_disableactivitychecks_to_roles',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (58,'2021_03_25_213017_remove_setting_removebidonaccept',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (59,'2021_04_05_055245_flights_add_alphanumeric_callsign',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (60,'2021_04_10_055245_migrate_configs',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (61,'2021_05_21_141152_increase_icao_sizes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (62,'2021_05_28_165608_remove_setting_simbriefexpiredays',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (63,'2021_06_01_141152_discord_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (64,'2021_06_04_141152_discord_private_channel_id',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (65,'2021_11_23_184532_add_type_rating_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (66,'2021_11_27_132418_add_hub_to_aircraft',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (67,'2022_01_10_131604_update_awards_add_active',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (68,'2022_02_11_124926_update_users_add_notes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (69,'2022_03_09_152342_ignore_admin_activity_checks',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (70,'2022_07_12_142108_add_notes_to_airports',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (71,'2022_08_20_213507_add_callsign_to_airlines',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (72,'2022_12_17_211036_add_callsign_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (73,'2022_12_27_192218_create_events',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (74,'2023_01_28_174436_add_lastlogin_at_to_users',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (75,'2023_05_08_174436_add_fare_details_to_pirep',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (76,'2023_06_24_072812_add_softdelete_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (77,'2023_06_24_204216_add_description_to_pirepfields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (78,'2023_06_24_211137_add_fin_to_aircraft',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (79,'2023_08_01_211137_set_onb_to_arr',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (80,'2023_08_11_225426_add_email_verified_to_user',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (81,'2023_08_14_074828_add_aircraft_id_to_bids',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (82,'2023_08_22_192218_create_data_migrations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (83,'2023_08_29_192218_add_ias_column_acars',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (84,'2023_09_26_190028_add_new_fields_to_airports',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (85,'2023_11_28_190028_flights_add_ref_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (86,'2023_12_08_185109_add_pirep_source_column_to_pirepfields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (87,'2023_12_15_154815_create_user_oauth_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (88,'2023_12_17_181350_add_fields_to_aircraft',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (89,'2023_12_24_091030_drop_user_oauth_tokens_foreign_keys',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (90,'2023_12_27_112456_create_invites_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (91,'2024_01_20_183702_create_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (92,'2024_01_20_183703_add_event_column_to_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (93,'2024_01_20_183704_add_batch_uuid_column_to_activity_log_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (94,'2024_05_15_144813_add_internal_to_user_fields',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (95,'2024_06_03_124335_add_ivao_id_to_users',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (96,'2024_01_26_142501_add_vatsim_id_to_users',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (97,'2024_07_02_164640_update_tokens_in_user_oauth_tokens',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (98,'2024_07_09_210356_update_db_charset',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (99,'2024_15_07_164640_add_detailed_altitude',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (100,'2024_11_16_105923_create_simbrief_support_tables',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (101,'2024_11_22_182419_update_aircraft_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (102,'2024_12_03_093851_add_sources_to_acars_table',5);
