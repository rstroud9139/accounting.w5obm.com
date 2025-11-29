-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: mysql.hamtestpro.com
-- Generation Time: Sep 01, 2025 at 10:55 AM
-- Server version: 8.0.37-0ubuntu0.22.04.3
-- PHP Version: 8.1.2-1ubuntu2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `w5obm`
--
CREATE DATABASE IF NOT EXISTS `w5obm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `w5obm`;

-- --------------------------------------------------------

--
-- Table structure for table `auth_2fa_attempts`
--

DROP TABLE IF EXISTS `auth_2fa_attempts`;
CREATE TABLE IF NOT EXISTS `auth_2fa_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempt_type` enum('totp','backup') COLLATE utf8mb4_unicode_ci NOT NULL,
  `success` tinyint(1) NOT NULL,
  `attempted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_attempts` (`user_id`,`attempted_at`),
  KEY `idx_cleanup` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_2fa_sessions`
--

DROP TABLE IF EXISTS `auth_2fa_sessions`;
CREATE TABLE IF NOT EXISTS `auth_2fa_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` timestamp NOT NULL,
  `verified` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_activity_log`
--

DROP TABLE IF EXISTS `auth_activity_log`;
CREATE TABLE IF NOT EXISTS `auth_activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `success` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB AUTO_INCREMENT=317 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_activity_log`
--

INSERT INTO `auth_activity_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `success`, `created_at`) VALUES
(1, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Viewer/98.9.8078.79', 0, '2025-06-15 20:29:03'),
(2, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-15 23:06:02'),
(3, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-15 23:11:43'),
(4, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-15 23:49:12'),
(5, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-16 00:28:59'),
(6, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-16 13:33:03'),
(7, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-16 14:19:27'),
(8, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-16 15:31:02'),
(9, 2, 'login_failed', 'Failed password verification (attempt 1)', '67.20.5.150', '', 0, '2025-06-16 15:57:50'),
(10, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-16 15:57:59'),
(11, 2, 'login_failed', 'Failed password verification (attempt 1)', '107.127.28.122', '', 0, '2025-06-16 17:03:00'),
(12, 2, 'login_success', 'User logged in successfully', '107.127.28.122', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 0, '2025-06-16 17:03:45'),
(13, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-16 21:21:13'),
(14, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-16 21:53:58'),
(15, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-17 08:17:08'),
(16, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-17 09:00:46'),
(17, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Config/98.2.7111.12', 0, '2025-06-17 09:55:39'),
(18, 2, 'login_success', 'User logged in successfully', '107.127.28.122', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1', 0, '2025-06-17 16:19:04'),
(19, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/96.5.2354.55', 1, '2025-07-13 13:11:12'),
(20, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 11:04:18'),
(21, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 12:37:09'),
(22, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 13:48:22'),
(23, 2, 'event_updated', 'Updated event: FCC License Testing', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-15 13:50:31'),
(24, 2, 'event_updated', 'Updated event: Monthly Club Meeting', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-15 13:50:56'),
(25, 2, 'event_updated', 'Updated event: FCC License Testing', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-15 14:04:49'),
(26, 2, 'event_updated', 'Updated event: FCC License Testing', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-15 14:39:51'),
(27, 2, 'event_updated', 'Updated event: FCC License Testing', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 14:44:51'),
(28, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:38:55'),
(29, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:39:11'),
(30, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:42:03'),
(31, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:44:57'),
(32, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:45:17'),
(33, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:45:29'),
(34, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:45:36'),
(35, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:45:41'),
(36, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:45:44'),
(37, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:45:52'),
(38, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:13'),
(39, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:19'),
(40, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:25'),
(41, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:30'),
(42, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:32'),
(43, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:36'),
(44, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:48'),
(45, 2, 'log_table_cleared', 'Cleared old entries from auth_activity_log (0 records)', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:48'),
(46, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:46:48'),
(47, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:47:13'),
(48, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:47:23'),
(49, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:47:29'),
(50, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:47:36'),
(51, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:47:44'),
(52, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:47:59'),
(53, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:48:03'),
(54, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:48:06'),
(55, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:48:11'),
(56, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/97.6.6175.76', 1, '2025-07-15 15:48:23'),
(57, 2, 'login_invalid_password', 'Failed login attempt #1', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 0, '2025-07-15 21:12:50'),
(58, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:13:15'),
(59, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:13:15'),
(60, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:38:42'),
(61, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:39:01'),
(62, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:39:18'),
(63, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:39:24'),
(64, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:40:20'),
(65, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:40:38'),
(66, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:40:41'),
(67, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:40:45'),
(68, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:40:57'),
(69, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:41:00'),
(70, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-15 21:41:03'),
(71, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-16 15:46:36'),
(72, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-16 15:46:36'),
(73, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:44:15'),
(74, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:44:15'),
(75, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:44:51'),
(76, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:45:19'),
(77, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:45:51'),
(78, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:50:41'),
(79, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:53:26'),
(80, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:53:29'),
(81, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:55:03'),
(82, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:55:09'),
(83, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:55:12'),
(84, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:55:24'),
(85, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:55:30'),
(86, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:55:38'),
(87, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 10:57:50'),
(88, 2, 'member_updated', 'Updated member information for ID: 86', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-17 11:00:36'),
(89, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 11:00:40'),
(90, 2, 'member_updated', 'Updated member information for ID: 87', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 11:01:09'),
(91, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 11:01:11'),
(92, 2, 'member_updated', 'Updated member information for ID: 83', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-17 11:01:24'),
(93, 2, 'member_updated', 'Updated member information for ID: 82', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-17 11:01:36'),
(94, 2, 'member_updated', 'Updated member information for ID: 81', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-17 11:01:47'),
(95, 2, 'member_updated', 'Updated member information for ID: 77', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-17 11:02:00'),
(96, 2, 'member_updated', 'Updated member information for ID: 73', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-17 11:02:26'),
(97, 2, 'accounting_access', 'Accessed Accounting System', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/90.5.8484.85', 1, '2025-07-17 11:27:44'),
(99, 2, 'login_success', 'User logged in successfully', '166.196.103.106', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148', 1, '2025-07-17 16:09:47'),
(100, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '166.196.103.106', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148', 1, '2025-07-17 16:09:47'),
(101, 2, 'accounting_access', 'Accessed Accounting System', '166.196.103.106', 'FileManager/3.6.3 CFNetwork/3826.600.41 Darwin/24.6.0', 1, '2025-07-17 16:10:16'),
(102, 2, 'accounting_access', 'Accessed Accounting System', '166.196.103.106', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148', 1, '2025-07-17 16:10:16'),
(103, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:30:01'),
(104, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:30:01'),
(105, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:30:23'),
(106, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:30:56'),
(107, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:31:11'),
(108, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:31:19'),
(109, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:31:23'),
(110, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:31:48'),
(111, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:31:53'),
(112, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:32:34'),
(113, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:32:39'),
(114, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:32:52'),
(115, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:32:59'),
(116, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:33:11'),
(117, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:33:18'),
(118, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:33:22'),
(119, 2, 'accounting_access', 'Accessed Accounting System', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:33:28'),
(120, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:33:32'),
(121, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-17 21:33:44'),
(122, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 08:19:18'),
(123, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 08:19:18'),
(124, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 09:58:58'),
(125, 2, 'member_updated', 'Updated member information for ID: 80', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-18 10:00:12'),
(126, 2, 'member_updated', 'Updated member information for ID: 79', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', 1, '2025-07-18 10:00:40'),
(127, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:00:46'),
(128, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:00:46'),
(129, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:01:04'),
(130, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:01:17'),
(131, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:03:19'),
(132, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:05:48'),
(133, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:07:01'),
(134, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:07:12'),
(135, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:08:42'),
(136, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:09:00'),
(137, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:09:50'),
(138, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:09:53'),
(139, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:10:00'),
(140, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:10:13'),
(141, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:10:29'),
(142, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:10:33'),
(143, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:10:41'),
(144, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:11:05'),
(145, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:11:10'),
(146, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:12:10'),
(147, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:13:27'),
(148, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:13:31'),
(149, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:13:54'),
(150, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:14:34'),
(151, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:15:57'),
(152, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:16:52'),
(153, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:16:54'),
(154, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:17:13'),
(155, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:17:52'),
(156, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:17:58'),
(157, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:18:07'),
(158, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:18:14'),
(159, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:18:28'),
(160, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:23:35'),
(161, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:24:50'),
(162, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:24:53'),
(163, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:28:35'),
(164, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:30:07'),
(165, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:30:14'),
(166, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:30:17'),
(167, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:30:42'),
(168, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 12:39:22'),
(169, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:09:38'),
(170, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:09:38'),
(171, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:10:42'),
(172, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:10:44'),
(173, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:10:59'),
(174, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:11:03'),
(175, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:11:08'),
(176, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:12:59'),
(177, 2, 'log_management_access', 'Accessed log management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:13:04'),
(178, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:13:13'),
(179, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:13:16'),
(180, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:13:59'),
(181, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:14:03'),
(182, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:14:24'),
(183, 2, 'session_settings_updated', 'Session timeout set to 86400 seconds', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:14:24'),
(184, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:14:24'),
(185, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:14:29'),
(186, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:15:06'),
(187, 2, 'session_settings_updated', 'Session timeout set to 86400 seconds', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:15:06'),
(188, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:15:06'),
(189, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:15:49'),
(190, 2, 'password_policy_updated', 'Password policy settings updated', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:15:49'),
(191, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:15:49'),
(192, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:16:21'),
(193, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:16:32'),
(194, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:17:39'),
(195, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:17:47'),
(196, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:17:50'),
(197, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:17:53'),
(198, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:17:54'),
(199, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:20:29'),
(200, 2, 'logout', 'Manual logout from dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 20:20:58'),
(201, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:32:32'),
(202, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:32:32'),
(203, 2, 'crm_access', 'Accessed CRM Marketplace', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:32:47'),
(204, 2, 'crm_access', 'Accessed CRM Marketplace', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:32:59'),
(205, 2, 'crm_access', 'Accessed CRM Marketplace', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:34:10'),
(206, 2, 'crm_access', 'Accessed CRM Marketplace', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:35:56'),
(207, 2, 'crm_access', 'Accessed CRM Marketplace', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:36:06'),
(208, 2, 'crm_access', 'Accessed CRM Marketplace', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:36:14');
INSERT INTO `auth_activity_log` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `success`, `created_at`) VALUES
(209, 2, 'crm_access', 'Accessed CRM Marketplace', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:40:11'),
(210, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:40:12'),
(211, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:47:41'),
(212, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:47:47'),
(213, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Viewer/95.9.1998.99', 1, '2025-07-18 22:47:59'),
(214, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 08:20:49'),
(215, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 08:20:49'),
(216, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 08:25:32'),
(217, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 10:52:32'),
(218, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 10:52:32'),
(219, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 10:52:46'),
(220, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 11:21:14'),
(221, 2, 'raffle_admin_access', 'Accessed raffle administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 12:48:43'),
(222, 2, 'raffle_admin_access', 'Accessed raffle administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 AtContent/94.5.3084.85', 1, '2025-07-20 12:49:52'),
(223, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:34:58'),
(224, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:34:59'),
(225, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:42:30'),
(226, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:44:08'),
(227, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:37'),
(228, 2, 'session_settings_updated', 'Session timeout set to 3600 seconds', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:37'),
(229, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:37'),
(230, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:38'),
(231, 2, 'session_settings_updated', 'Session timeout set to 3600 seconds', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:38'),
(232, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:38'),
(233, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:39'),
(234, 2, 'session_settings_updated', 'Session timeout set to 3600 seconds', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:39'),
(235, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:39'),
(236, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:40'),
(237, 2, 'session_settings_updated', 'Session timeout set to 3600 seconds', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:40'),
(238, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:45:40'),
(239, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:46:15'),
(240, 2, 'lockout_settings_updated', 'Account lockout settings updated', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:46:15'),
(241, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:46:15'),
(242, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:46:32'),
(243, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:46:48'),
(244, 2, 'security_configuration_access', 'Accessed security configuration', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:46:54'),
(245, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:47:13'),
(246, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:47:25'),
(247, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:48:21'),
(248, 2, 'member_deleted', 'Deleted member: ???? + 1.587599 BTC. la3034', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:48:21'),
(249, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:48:23'),
(250, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:48:28'),
(251, 2, 'member_deleted', 'Deleted member: ⛏ + 1.54179 BTC.GET  ztgy9j', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:48:28'),
(252, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:48:30'),
(253, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:48:46'),
(254, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:49:28'),
(255, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:49:52'),
(256, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:50:41'),
(257, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:51:33'),
(258, 2, 'cache_cleared', 'System cache cleared', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:53:30'),
(259, 2, 'logs_cleaned', 'Cleaned 0 old log files', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:53:51'),
(260, 2, 'sessions_cleaned', 'Cleaned 1 expired sessions', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:54:01'),
(261, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:54:18'),
(262, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:58:08'),
(263, 2, 'accounting_access', 'Accessed Accounting System', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 20:59:14'),
(264, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 21:00:14'),
(265, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 21:01:03'),
(266, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 21:01:04'),
(267, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 21:01:05'),
(268, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 21:01:07'),
(269, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 21:01:08'),
(270, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/93.6.8935.36', 1, '2025-07-20 21:01:10'),
(271, 2, 'login_invalid_password', 'Failed login attempt #1', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 0, '2025-07-21 06:07:24'),
(272, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:07:56'),
(273, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:07:56'),
(274, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:09:12'),
(275, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:11:40'),
(276, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:12:00'),
(277, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:12:06'),
(278, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:13:31'),
(279, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:14:08'),
(280, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:14:22'),
(281, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:15:15'),
(282, 2, 'directory_cleared', 'Cleared directory: /home/w5obmcom_admin/w5obm.com/administration/../logs (6 files)', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:15:15'),
(283, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:15:15'),
(284, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:15:36'),
(285, 2, 'directory_cleared', 'Cleared directory: /home/w5obmcom_admin/w5obm.com/administration/../uploads (1 files)', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:15:36'),
(286, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:15:36'),
(287, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:16:35'),
(288, 2, 'directory_cleared', 'Cleared directory: /home/w5obmcom_admin/w5obm.com/administration/../backups (0 files)', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:16:35'),
(289, 2, 'file_management_access', 'Accessed file management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:16:35'),
(290, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:16:50'),
(291, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:17:10'),
(292, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:17:15'),
(293, 2, 'membership_management_access', 'Accessed membership management system', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:17:18'),
(294, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:17:57'),
(295, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:18:21'),
(296, 2, 'accounting_access', 'Accessed Accounting System', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:18:31'),
(297, 2, 'income_statement_generated', 'Generated income statement for 7/2025', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:24:27'),
(298, 2, 'income_statement_generated', 'Generated income statement for 7/2025', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:28:54'),
(299, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:29:00'),
(300, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 06:29:49'),
(301, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 08:01:35'),
(302, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 08:01:35'),
(303, 2, 'accounting_access', 'Accessed Accounting System', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 08:01:59'),
(304, 2, 'income_statement_generated', 'Generated income statement for 7/2025', '67.20.5.150', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.6 Mobile/15E148 Safari/604.1', 1, '2025-07-21 08:20:40'),
(305, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/98.6.4315.16', 1, '2025-07-22 12:58:53'),
(306, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/98.6.4315.16', 1, '2025-07-22 12:58:53'),
(307, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/98.6.4315.16', 1, '2025-07-22 13:52:20'),
(308, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 LikeWise/98.6.4315.16', 1, '2025-07-22 13:52:20'),
(309, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Herring/96.1.3920.21', 1, '2025-07-28 13:00:11'),
(310, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Herring/96.1.3920.21', 1, '2025-07-28 13:00:11'),
(311, 2, 'crm_access', 'Accessed CRM Marketplace', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Herring/96.1.3920.21', 1, '2025-07-28 13:00:19'),
(312, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Herring/96.1.3920.21', 1, '2025-07-28 13:10:18'),
(313, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Herring/96.1.3920.21', 1, '2025-07-28 13:20:26'),
(314, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Herring/96.1.3920.21', 1, '2025-07-28 13:20:28'),
(315, 2, 'admin_dashboard_access', 'Accessed administration dashboard', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Herring/96.1.3920.21', 1, '2025-07-28 13:20:29'),
(316, 2, 'login_success', 'User logged in successfully', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 GLS/95.10.5079.80', 1, '2025-09-01 09:42:55');

-- --------------------------------------------------------

--
-- Table structure for table `auth_applications`
--

DROP TABLE IF EXISTS `auth_applications`;
CREATE TABLE IF NOT EXISTS `auth_applications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `app_name` varchar(100) COLLATE utf8mb3_unicode_ci NOT NULL,
  `app_url` varchar(255) COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `app_category` varchar(50) COLLATE utf8mb3_unicode_ci DEFAULT 'General',
  `app_icon` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT 'fas fa-cog',
  `app_status` enum('active','maintenance','beta','disabled') COLLATE utf8mb3_unicode_ci DEFAULT 'active',
  `launch_type` enum('direct','modal','iframe','new_tab') COLLATE utf8mb3_unicode_ci DEFAULT 'direct',
  `quick_actions` text COLLATE utf8mb3_unicode_ci,
  `help_url` varchar(255) COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `open_to_all_members` tinyint(1) DEFAULT '0',
  `permission_name` varchar(100) COLLATE utf8mb3_unicode_ci DEFAULT NULL COMMENT 'Permission required to access this application',
  PRIMARY KEY (`id`),
  UNIQUE KEY `app_name` (`app_name`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `auth_applications`
--

INSERT INTO `auth_applications` (`id`, `app_name`, `app_url`, `description`, `app_category`, `app_icon`, `app_status`, `launch_type`, `quick_actions`, `help_url`, `sort_order`, `created_at`, `updated_at`, `open_to_all_members`, `permission_name`) VALUES
(1, 'Authorization System', '/administration/users/index.php', 'User roles, permissions, and access control management', 'Administration', 'fas fa-users-cog', 'active', 'direct', NULL, NULL, 2, '2025-06-07 03:35:44', '2025-07-01 15:37:02', 0, 'app.authorization'),
(2, 'Administrative System', '../administration/index.php', 'Main administration interface and system overview', 'Administration', 'fas fa-tachometer-alt', 'active', 'direct', NULL, NULL, 3, '2025-06-07 03:35:44', '2025-07-13 19:24:58', 0, 'admin.system'),
(3, 'System Administration', '../administration/system/index.php', 'Advanced system administration and maintenance tools', 'Administration', 'fas fa-cog', 'active', 'direct', NULL, NULL, 100, '2025-06-25 04:45:43', '2025-07-13 19:24:58', 0, 'admin.system'),
(4, 'Accounting', '../accounting/index.php\r\n', 'Financial management and accounting system', 'Financial', 'fas fa-calculator', 'maintenance', 'direct', NULL, NULL, 5, '2025-06-08 18:25:12', '2025-07-07 18:16:22', 0, 'app.accounting'),
(6, 'Contests', '../contests/index.php', 'Amateur radio contest management and logging', 'Contests', 'fas fa-trophy', 'disabled', 'direct', NULL, NULL, 6, '2025-06-08 18:25:12', '2025-07-13 19:37:10', 0, 'app.contests'),
(8, 'Photos', '../photos/piwigo/index.php', 'Photo gallery system (Piwigo)', 'Media', 'fas fa-camera', 'active', 'direct', NULL, NULL, 8, '2025-06-08 18:25:12', '2025-07-13 19:24:58', 1, NULL),
(9, 'Raffle', '../raffle/index.php', 'Raffle and drawing management system', 'Raffle', 'fas fa-ticket-alt', 'maintenance', 'direct', NULL, NULL, 9, '2025-06-08 18:25:12', '2025-07-13 19:36:54', 0, 'app.raffle'),
(10, 'Survey', '../survey/dashboard.php', 'Survey and polling system for members', 'Survey', 'fas fa-poll', 'maintenance', 'direct', NULL, NULL, 10, '2025-06-08 18:25:12', '2025-07-13 19:36:08', 0, 'app.survey'),
(11, 'Events Management', '../events/index.php', 'Manage club events, calendar, and member registrations', 'Events', 'fas fa-calendar-alt', 'active', 'direct', NULL, NULL, 1, '2025-06-07 03:35:44', '2025-07-13 19:36:24', 0, 'app.events'),
(21, 'User Management', '../administration/users/index.php', 'Manage user accounts, roles, and permissions', 'Administration', 'fas fa-users', 'active', 'direct', NULL, NULL, 10, '2025-07-01 15:38:01', '2025-09-01 17:13:29', 0, 'user.management'),
(22, 'Admin Reports', '../administration/reports/index.php', 'System reports, analytics, and activity logs', 'Administration', 'fas fa-chart-line', 'active', 'direct', NULL, NULL, 20, '2025-07-01 15:38:01', '2025-07-13 19:24:58', 0, 'admin.reports'),
(23, 'Security Settings', '../administration/security/index.php', 'Security policies, 2FA management, and access controls', 'Administration', 'fas fa-shield-alt', 'active', 'direct', NULL, NULL, 30, '2025-07-01 15:38:01', '2025-07-13 19:24:58', 0, 'admin.security'),
(24, 'Member Directory', '../members/index.php', 'Club member directory and contact information', 'Members', 'fas fa-address-book', 'active', 'direct', NULL, NULL, 50, '2025-07-01 15:38:01', '2025-07-07 18:18:48', 1, NULL),
(28, 'Net Config', '../weekly_nets/net_config.php', 'The management of orne or more Nets that the Club conducts.  Requires authorization.', 'General', 'fas fa-cog', 'active', 'direct', NULL, NULL, 100, '2025-07-03 21:25:24', '2025-07-13 19:24:58', 0, 'app.nets'),
(30, 'CRM Marketplace', 'https://w5obm.com/crm/index.php', 'Club\'s Marketplace for Selling or Auctioning a variety of goods including Ham Radio Equipment.', 'General', 'fas fa-cog', 'active', 'direct', NULL, NULL, 100, '2025-07-28 20:13:55', '2025-07-28 20:13:55', 0, 'member '),
(31, 'CRM Marketplace Administration', 'https://w5obm.com/crm/admin/dashboard.php', 'Administrative portal for the Marketplace application.', 'CRM', 'fas fa-cog', 'active', 'direct', NULL, NULL, 100, '2025-07-28 20:19:07', '2025-07-28 20:19:07', 0, 'crm.admin'),
(40, 'Profile', '/administration/users/profile.php', 'Edit your profile information', 'Core', 'fas fa-user-edit', 'active', 'direct', NULL, NULL, 100, '2025-09-01 17:10:05', '2025-09-01 17:10:05', 1, 'profile_access'),
(41, 'Change Password', '/authentication/change_password.php', 'Change your password', 'Core', 'fas fa-key', 'active', 'direct', NULL, NULL, 110, '2025-09-01 17:10:05', '2025-09-01 17:10:05', 1, 'change_password'),
(42, 'Two-Factor Auth', '/administration/misc/two_factor_auth.php', 'Manage or enable 2FA security', 'Core', 'fas fa-shield-alt', 'active', 'direct', NULL, NULL, 120, '2025-09-01 17:10:05', '2025-09-01 17:10:05', 1, '2fa_manage'),
(43, 'Main Website', '/index.php', 'Return to club website', 'Core', 'fas fa-home', 'active', 'direct', NULL, NULL, 130, '2025-09-01 17:10:05', '2025-09-01 17:10:05', 1, 'main_site'),
(44, 'Admin Dashboard', '/administration/dashboard.php', 'Main administration center', 'Administration', 'fas fa-tachometer-alt', 'active', 'direct', NULL, NULL, 200, '2025-09-01 17:10:05', '2025-09-01 17:10:05', 0, 'admin_dashboard'),
(45, 'System Admin', '/administration/system/index.php', 'System configuration', 'Administration', 'fas fa-server', 'active', 'direct', NULL, NULL, 220, '2025-09-01 17:10:05', '2025-09-01 17:10:05', 0, 'system_admin'),
(46, 'Audit Logs', '/administration/misc/activity_audit_log.php', 'View security and activity logs', 'Administration', 'fas fa-clipboard-list', 'active', 'direct', NULL, NULL, 230, '2025-09-01 17:10:05', '2025-09-01 17:10:05', 0, 'audit_logs');

-- --------------------------------------------------------

--
-- Table structure for table `auth_audit_logs`
--

DROP TABLE IF EXISTS `auth_audit_logs`;
CREATE TABLE IF NOT EXISTS `auth_audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `record_id` int DEFAULT NULL,
  `old_values` text COLLATE utf8mb4_unicode_ci,
  `new_values` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_user_action` (`user_id`,`action`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_audit_logs`
--

INSERT INTO `auth_audit_logs` (`id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 2, 'preferences_update', 'Theme: default, Notifications: email=1, sms=0', 1, NULL, '', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Trailer/98.3.7502.3', '2025-06-24 22:06:21'),
(2, 2, 'preferences_update', 'Theme: default, Notifications: email=1, sms=0', 1, NULL, '', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Trailer/98.3.7502.3', '2025-06-24 22:06:44'),
(3, 2, 'preferences_update', 'Theme: light, Notifications: email=1, sms=0', 1, NULL, '', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Trailer/98.3.7502.3', '2025-06-24 22:07:10'),
(4, 2, 'login_success', 'auth_users', 2, NULL, 'Successful login from IP: 67.20.5.150', '67.20.5.150', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 Viewer/95.9.6378.79', '2025-06-26 16:03:05');

-- --------------------------------------------------------

--
-- Table structure for table `auth_dashboard_widgets`
--

DROP TABLE IF EXISTS `auth_dashboard_widgets`;
CREATE TABLE IF NOT EXISTS `auth_dashboard_widgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `widget_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `widget_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `widget_type` enum('chart','stats','table','custom') COLLATE utf8mb4_unicode_ci DEFAULT 'stats',
  `widget_size` enum('small','medium','large','full') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `widget_data_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `widget_config` json DEFAULT NULL,
  `default_position_x` int DEFAULT '0',
  `default_position_y` int DEFAULT '0',
  `is_system_widget` tinyint(1) DEFAULT '0',
  `required_permission` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_dashboard_widgets`
--

INSERT INTO `auth_dashboard_widgets` (`id`, `widget_name`, `widget_title`, `widget_type`, `widget_size`, `widget_data_source`, `widget_config`, `default_position_x`, `default_position_y`, `is_system_widget`, `required_permission`, `status`, `created_at`, `updated_at`) VALUES
(1, 'user_stats', 'User Statistics', 'stats', 'medium', 'SELECT COUNT(*) as total_users, COUNT(CASE WHEN status=\"active\" THEN 1 END) as active_users FROM auth_users', NULL, 0, 0, 1, 'users.view_all', 'active', '2025-06-07 03:41:07', '2025-06-07 03:41:07'),
(2, 'recent_activity', 'Recent Activity', 'table', 'large', 'SELECT action, description, created_at FROM auth_activity_log ORDER BY created_at DESC LIMIT 10', NULL, 0, 0, 1, 'system.view_logs', 'active', '2025-06-07 03:41:07', '2025-06-07 03:41:07'),
(3, 'login_attempts', 'Login Attempts Today', 'stats', 'small', 'SELECT COUNT(*) as attempts FROM auth_login_attempts WHERE DATE(created_at) = CURDATE()', NULL, 0, 0, 1, 'auth.view_audit_logs', 'active', '2025-06-07 03:41:07', '2025-06-07 03:41:07'),
(4, 'upcoming_events', 'Upcoming Events', 'table', 'large', 'SELECT title, event_date, location FROM events WHERE event_date >= CURDATE() ORDER BY event_date LIMIT 5', NULL, 0, 0, 1, 'events.view_all', 'active', '2025-06-07 03:41:07', '2025-06-07 03:41:07'),
(5, 'event_registrations', 'Event Registrations This Month', 'stats', 'medium', 'SELECT COUNT(*) as registrations FROM event_registrations WHERE MONTH(registration_date) = MONTH(CURDATE())', NULL, 0, 0, 1, 'events.manage_registrations', 'active', '2025-06-07 03:41:07', '2025-06-07 03:41:07'),
(6, 'app_status', 'Application Status', 'table', 'medium', 'SELECT app_name, app_status FROM auth_applications ORDER BY sort_order', NULL, 0, 0, 1, 'system.admin', 'active', '2025-06-07 03:41:07', '2025-06-07 03:41:07'),
(7, 'quick_actions', 'Quick Actions', 'custom', 'small', NULL, NULL, 0, 0, 1, 'system.admin', 'active', '2025-06-07 03:41:07', '2025-06-07 03:41:07');

-- --------------------------------------------------------

--
-- Table structure for table `auth_email_templates`
--

DROP TABLE IF EXISTS `auth_email_templates`;
CREATE TABLE IF NOT EXISTS `auth_email_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_type` enum('notification','welcome','reset','reminder','confirmation','newsletter','event','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'notification',
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `html_body` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_body` longtext COLLATE utf8mb4_unicode_ci,
  `variables` json DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_name` (`template_name`),
  KEY `idx_template_type` (`template_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_email_verification`
--

DROP TABLE IF EXISTS `auth_email_verification`;
CREATE TABLE IF NOT EXISTS `auth_email_verification` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `verification_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` timestamp NOT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `verification_token` (`verification_token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_login_attempts`
--

DROP TABLE IF EXISTS `auth_login_attempts`;
CREATE TABLE IF NOT EXISTS `auth_login_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `success` tinyint(1) DEFAULT '0',
  `failure_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`,`attempt_time`),
  KEY `idx_username` (`username`),
  KEY `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_password_resets`
--

DROP TABLE IF EXISTS `auth_password_resets`;
CREATE TABLE IF NOT EXISTS `auth_password_resets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_password_resets`
--

INSERT INTO `auth_password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
(1, 2, 'c1f09f0fbd2594b0dd5818f1d5e8ff49c42e30c9e6bd63153d34a35726251460', '2025-06-08 00:22:39', 0, '2025-06-08 06:22:39'),
(2, 2, 'ba812ff6a01ca2343a008e09830c12726122ffdef1df823fe1d94a13a6759d2c', '2025-06-08 00:47:56', 0, '2025-06-08 06:47:56');

-- --------------------------------------------------------

--
-- Table structure for table `auth_permissions`
--

DROP TABLE IF EXISTS `auth_permissions`;
CREATE TABLE IF NOT EXISTS `auth_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `permission_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this permission is active (1) or disabled (0)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_name` (`permission_name`)
) ENGINE=InnoDB AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_permissions`
--

INSERT INTO `auth_permissions` (`id`, `permission_name`, `description`, `category`, `created_at`, `is_active`) VALUES
(58, 'app.events', 'Access to Events Management application', 'Applications', '2025-06-16 07:18:54', 1),
(59, 'app.authorization', 'Access to Authorization System', 'Applications', '2025-06-16 07:18:54', 1),
(60, 'app.users', 'Access to User Management application', 'Applications', '2025-06-16 07:18:54', 1),
(61, 'app.admin_dashboard', 'Access to Admin Dashboard', 'Applications', '2025-06-16 07:18:54', 1),
(62, 'app.accounting', 'Access to Accounting application', 'Applications', '2025-06-16 07:18:54', 1),
(63, 'app.contests', 'Access to Contests application', 'Applications', '2025-06-16 07:18:54', 1),
(64, 'app.crm', 'Access to CRM application', 'Applications', '2025-06-16 07:18:54', 1),
(65, 'app.photos', 'Access to Photos application', 'Applications', '2025-06-16 07:18:54', 1),
(66, 'app.raffle', 'Access to Raffle application', 'Applications', '2025-06-16 07:18:54', 1),
(67, 'app.survey', 'Access to Survey application', 'Applications', '2025-06-16 07:18:54', 1),
(70, 'profile.manage_2fa', 'Manage two-factor authentication settings', 'Profile', '2025-06-16 21:30:59', 1),
(72, 'member', 'All logged in member access.', 'General', '2025-07-01 16:30:28', 1),
(74, 'everyone', 'Public access.  Not limited to members.', 'General', '2025-07-01 16:38:19', 1),
(78, 'net_config', 'Configure NET system parameters', 'NET System', '2025-07-04 16:24:37', 1),
(79, 'net_admin', 'Full NET system administration', 'NET System', '2025-07-04 16:24:37', 1),
(80, 'net_manage', 'Manage NET logging and assignments', 'NET System', '2025-07-04 16:24:37', 1),
(81, 'net_log', 'Log NET sessions and data', 'NET System', '2025-07-04 16:24:37', 1),
(82, 'net_view', 'View NET schedules and reports', 'NET System', '2025-07-04 16:24:37', 1),
(83, 'raffles.manager', 'Manages raffles and drawtings.', 'Raffle', '2025-07-13 17:45:58', 1),
(125, 'member.access', 'Standard member access to authenticated areas', 'Member Access', '2025-07-13 19:24:57', 1),
(126, 'member.profile', 'Manage own profile and account settings', 'Member Access', '2025-07-13 19:24:57', 1),
(127, 'member.2fa', 'Manage own two-factor authentication', 'Member Access', '2025-07-13 19:24:57', 1),
(128, 'app.nets', 'Full access to NET Management application', 'Applications', '2025-07-13 19:24:57', 1),
(129, 'admin.users', 'Full user account management', 'Administration', '2025-07-13 19:24:57', 1),
(130, 'admin.roles', 'Role and permission management', 'Administration', '2025-07-13 19:24:57', 1),
(131, 'admin.system', 'System configuration and maintenance', 'Administration', '2025-07-13 19:24:57', 1),
(132, 'admin.security', 'Security settings and audit logs', 'Administration', '2025-07-13 19:24:57', 1),
(133, 'admin.reports', 'Access to all system reports', 'Administration', '2025-07-13 19:24:57', 1),
(134, 'club.officer', 'Club officer privileges', 'Club Management', '2025-07-13 19:24:57', 1),
(135, 'club.secretary', 'Meeting minutes and communications', 'Club Management', '2025-07-13 19:24:57', 1),
(136, 'club.treasurer', 'Financial oversight and accounting', 'Club Management', '2025-07-13 19:24:57', 1),
(137, 'club.webmaster', 'Website content management', 'Club Management', '2025-07-13 19:24:57', 1),
(138, 'crm.admin', 'permission to administer changes in the crm system at the administrative level', 'crm admin', '2025-07-28 20:20:18', 1),
(139, 'system.admin', 'System configuration and administration', 'Administration', '2025-09-01 17:26:02', 1);

-- --------------------------------------------------------

--
-- Table structure for table `auth_permissions_backup_granular`
--

DROP TABLE IF EXISTS `auth_permissions_backup_granular`;
CREATE TABLE IF NOT EXISTS `auth_permissions_backup_granular` (
  `id` int NOT NULL DEFAULT '0',
  `permission_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this permission is active (1) or disabled (0)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_permissions_backup_granular`
--

INSERT INTO `auth_permissions_backup_granular` (`id`, `permission_name`, `description`, `category`, `created_at`, `is_active`) VALUES
(1, 'system.admin', 'Full system administration', 'System', '2025-06-07 03:34:27', 1),
(2, 'system.view_logs', 'View system logs and audit trails', 'System', '2025-06-07 03:34:27', 1),
(3, 'system.manage_settings', 'Manage system configuration', 'System', '2025-06-07 03:34:27', 1),
(4, 'users.create', 'Create new user accounts', 'Users', '2025-06-07 03:34:27', 1),
(5, 'users.edit', 'Edit existing user accounts', 'Users', '2025-06-07 03:34:27', 1),
(6, 'users.delete', 'Delete user accounts', 'Users', '2025-06-07 03:34:27', 1),
(7, 'users.view_all', 'View all user accounts and profiles', 'Users', '2025-06-07 03:34:27', 1),
(8, 'users.manage_roles', 'Assign and remove user roles', 'Users', '2025-06-07 03:34:27', 1),
(9, 'users.manage_permissions', 'Assign direct permissions to users', 'Users', '2025-06-07 03:34:27', 1),
(10, 'events.create', 'Create new events', 'Events', '2025-06-07 03:34:27', 1),
(11, 'events.edit_own', 'Edit own created events', 'Events', '2025-06-07 03:34:27', 1),
(12, 'events.edit_all', 'Edit any event in the system', 'Events', '2025-06-07 03:34:27', 1),
(13, 'events.delete_own', 'Delete own created events', 'Events', '2025-06-07 03:34:27', 1),
(14, 'events.delete_all', 'Delete any event in the system', 'Events', '2025-06-07 03:34:27', 1),
(15, 'events.view_all', 'View all events including private ones', 'Events', '2025-06-07 03:34:27', 1),
(16, 'events.manage.all', 'Manage all aspects of the event system.  Add, Edit and Delete.', 'Events', '2025-06-07 03:34:27', 1),
(17, 'events.export_data', 'Export event data and reports', 'Events', '2025-06-07 03:34:27', 1),
(18, 'auth.manage_roles', 'Create, edit, and delete roles', 'Authorization', '2025-06-07 03:34:27', 1),
(19, 'auth.manage_permissions', 'Create, edit, and delete permissions', 'Authorization', '2025-06-07 03:34:27', 1),
(20, 'auth.assign_roles', 'Assign roles to users', 'Authorization', '2025-06-07 03:34:27', 1),
(21, 'auth.assign_permissions', 'Assign permissions to users and roles', 'Authorization', '2025-06-07 03:34:27', 1),
(22, 'auth.view_audit_logs', 'View authorization audit logs', 'Authorization', '2025-06-07 03:34:27', 1),
(23, 'website.manage_content', 'Manage website content and pages', 'Website', '2025-06-07 03:34:27', 1),
(24, 'website.manage_navigation', 'Manage site navigation and menus', 'Website', '2025-06-07 03:34:27', 1),
(25, 'website.manage_settings', 'Manage website configuration', 'Website', '2025-06-07 03:34:27', 1),
(26, 'events.dashboard_access', 'Access to Events Dashboard', 'Events', '2025-06-07 04:15:11', 1),
(27, 'events.manage_all', 'Full event management capabilities (create, edit, delete all events)', 'Events', '2025-06-07 04:15:11', 1),
(28, 'events.view_management', 'View event management interface', 'Events', '2025-06-07 04:15:11', 1),
(48, 'events.delete', 'Delete events', 'Events', '2025-06-07 04:30:53', 1),
(49, 'user_management', 'Create, edit, and manage user accounts', 'User Management', '2025-06-16 06:52:26', 1),
(50, 'role_management', 'Create and manage user roles', 'Role Management', '2025-06-16 06:52:26', 1),
(51, 'permission_management', 'Define and manage system permissions', 'Permission Management', '2025-06-16 06:52:26', 1),
(52, 'system_admin', 'Advanced system configuration and maintenance', 'System Administration', '2025-06-16 06:52:26', 1),
(53, 'profile_management', 'Manage own profile and settings', 'Profile', '2025-06-16 06:52:26', 1),
(54, 'view_reports', 'View activity reports and analytics', 'Reports', '2025-06-16 06:52:26', 1),
(55, 'security_config', 'Configure security policies and settings', 'Security', '2025-06-16 06:52:26', 1),
(56, 'two_factor_admin', 'Manage 2FA settings for users', 'Security', '2025-06-16 06:52:26', 1);

-- --------------------------------------------------------

--
-- Table structure for table `auth_remember_tokens`
--

DROP TABLE IF EXISTS `auth_remember_tokens`;
CREATE TABLE IF NOT EXISTS `auth_remember_tokens` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_remember_tokens`
--

INSERT INTO `auth_remember_tokens` (`id`, `user_id`, `token_hash`, `ip_address`, `expires_at`, `created_at`) VALUES
(1, 2, '7422f0fc1c5e92f4cc4f60e5768ab852ff176284e74f58e4f4a36ae3d7419170', NULL, '2025-07-30 13:16:49', '2025-06-30 13:16:49'),
(2, 2, '7889040c704165dca39ed999b92275e0fa9d84d24d42fd7f662aea391be0280c', NULL, '2025-07-31 08:08:16', '2025-07-01 08:08:16'),
(3, 2, 'dd4607d6195db335433659893f30cab8207c3848c6c2f83419ece88a1e0135d9', NULL, '2025-08-01 23:10:46', '2025-07-02 23:10:46'),
(4, 2, 'd7b2c1d2d1bb748522a9e416b6c9b01497b0923e263f510272c1a95aed583e13', NULL, '2025-08-03 21:32:00', '2025-07-04 21:32:00'),
(5, 2, 'db343474e5ebb7c697f5b6a9d1c0ff49ece71005bdde3eaae5e2fb8bd8a1c2a3', NULL, '2025-08-04 15:07:35', '2025-07-05 15:07:35'),
(6, 2, 'd31f6e17394ec6f23e74aa7f3a8e8a148036e64b01d860b0dd0a976636b5cab2', NULL, '2025-08-04 18:12:59', '2025-07-05 18:12:59'),
(7, 2, '890324acab312e996ba93a1a76b1df245fe6eacb249bc4231925ac7292d8d3d9', NULL, '2025-08-05 20:09:54', '2025-07-06 20:09:54'),
(8, 2, '9e66b1dd93e12b2b5bfb76b12706d83f55823dcc025615ad6d833e24fbb9a631', NULL, '2025-08-05 21:31:59', '2025-07-06 21:31:59'),
(9, 2, '3408a98747279ae3964d797373fb8592a46e6ebf646081b05b26c01565885319', NULL, '2025-08-06 09:29:09', '2025-07-07 09:29:09'),
(10, 2, '31e17a9abcc7d532d713e0d2991dc727cd3dc1f3bd2e541b229dd74ec11e758f', NULL, '2025-08-06 19:59:03', '2025-07-07 19:59:03'),
(11, 2, 'a65457425a0da84425e182afdb0642accbc2c1508f32c6f383e5a20c9ca8b196', NULL, '2025-08-07 05:43:52', '2025-07-08 05:43:52'),
(12, 2, '9b1101457e6621e5e698d3fdfbd0497e1cd5789e7d7d99d76f248dfc924e14f2', NULL, '2025-08-07 05:57:31', '2025-07-08 05:57:31'),
(13, 2, 'b681a6b537535184b9549096988e75b8001cd42c8320c32f25d56c4327a82e79', NULL, '2025-08-08 02:02:36', '2025-07-09 02:02:36'),
(14, 2, 'b123b1b390878d27d00b8130389d0a8bedd9c8c2fb87064a29825f4f8dcd84c9', NULL, '2025-08-08 21:42:19', '2025-07-09 21:42:19'),
(15, 2, '9ada1dd3fd6f80b3c571ed6a277ab7b73e3fc68cbcebd62dc45dda996a8f39ee', NULL, '2025-08-09 12:54:40', '2025-07-10 12:54:40'),
(16, 2, '40451100a1ddad8580a03ba228c3ade5d8e8735469cebbd8d61ef9a5d7be7c32', NULL, '2025-08-09 20:28:08', '2025-07-10 20:28:08'),
(17, 2, 'b4f7a9066ea4a8a95ec4371708c2e9a03722f22f9c30c4c97f6a60e0cf85a410', NULL, '2025-08-10 08:49:20', '2025-07-11 08:49:20'),
(18, 2, '75460e5e50ae4ad49df4a3fcfdd4a5c83619098c5b5dbac012410bfd3cf85ef1', NULL, '2025-08-10 17:10:54', '2025-07-11 17:10:54'),
(19, 2, '46891a596804af97556740bb27c4bbbe255a671a742805b3cd2cb5d388997e2c', NULL, '2025-08-11 15:23:16', '2025-07-12 15:23:16');

-- --------------------------------------------------------

--
-- Table structure for table `auth_roles`
--

DROP TABLE IF EXISTS `auth_roles`;
CREATE TABLE IF NOT EXISTS `auth_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_system_role` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this role is active (1) or disabled (0)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=908 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_roles`
--

INSERT INTO `auth_roles` (`id`, `role_name`, `description`, `is_system_role`, `created_at`, `is_active`) VALUES
(1, 'Super Admin', 'Full system administrator with all permissions', 1, '2025-06-07 03:34:27', 1),
(4, 'Website Admin', 'Full access to Website management', 1, '2025-06-07 03:34:27', 1),
(5, 'User Manager', 'Can manage users and basic settings', 1, '2025-06-07 03:34:27', 1),
(26, 'President', 'Club President - event oversight and club leadership', 0, '2025-06-07 04:26:50', 1),
(27, 'Vice-President', 'Club Vice-President - assists with events and leadership', 0, '2025-06-07 04:26:50', 1),
(28, 'Secretary', 'Club Secretary - meeting notes and communications', 0, '2025-06-07 04:26:50', 1),
(29, 'Treasurer', 'Club Treasurer - financial oversight', 0, '2025-06-07 04:26:50', 1),
(30, 'Event Manager', 'Designated event coordinator', 0, '2025-06-07 04:26:50', 1),
(32, 'Admin', 'System administrator with most permissions', 1, '2025-06-16 03:56:03', 1),
(40, 'Authorization Admin', 'Full access to Authorization system', 1, '2025-06-07 03:34:27', 1),
(45, 'Events Admin', 'Full access to Events application', 1, '2025-06-07 03:34:27', 1),
(500, 'Member', 'Standard member access', 1, '2025-06-07 03:34:27', 1),
(900, 'Guest', 'Limited guest access', 1, '2025-06-07 03:34:27', 1);

-- --------------------------------------------------------

--
-- Table structure for table `auth_role_permissions`
--

DROP TABLE IF EXISTS `auth_role_permissions`;
CREATE TABLE IF NOT EXISTS `auth_role_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB AUTO_INCREMENT=428 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_role_permissions`
--

INSERT INTO `auth_role_permissions` (`id`, `role_id`, `permission_id`, `assigned_at`) VALUES
(151, 1, 62, '2025-06-16 07:19:17'),
(154, 1, 63, '2025-06-16 07:19:17'),
(155, 1, 64, '2025-06-16 07:19:17'),
(156, 1, 58, '2025-06-16 07:19:17'),
(157, 1, 65, '2025-06-16 07:19:17'),
(158, 1, 66, '2025-06-16 07:19:17'),
(159, 1, 67, '2025-06-16 07:19:17'),
(312, 27, 58, '2025-07-13 19:24:57'),
(313, 26, 58, '2025-07-13 19:24:57'),
(314, 30, 58, '2025-07-13 19:24:57'),
(315, 28, 58, '2025-07-13 19:24:57'),
(316, 32, 58, '2025-07-13 19:24:57'),
(319, 1, 129, '2025-07-13 19:24:57'),
(320, 32, 129, '2025-07-13 19:24:57'),
(322, 1, 131, '2025-07-13 19:24:57'),
(323, 32, 131, '2025-07-13 19:24:57'),
(325, 1, 130, '2025-07-13 19:24:57'),
(326, 32, 130, '2025-07-13 19:24:57'),
(328, 1, 133, '2025-07-13 19:24:58'),
(329, 1, 132, '2025-07-13 19:24:58'),
(330, 1, 128, '2025-07-13 19:24:58'),
(331, 1, 134, '2025-07-13 19:24:58'),
(332, 1, 135, '2025-07-13 19:24:58'),
(333, 1, 136, '2025-07-13 19:24:58'),
(334, 1, 137, '2025-07-13 19:24:58'),
(413, 500, 126, '2025-09-01 17:40:38'),
(414, 500, 127, '2025-09-01 17:40:38'),
(415, 500, 125, '2025-09-01 17:40:38'),
(416, 32, 61, '2025-09-01 17:40:38'),
(417, 32, 60, '2025-09-01 17:40:38'),
(418, 32, 59, '2025-09-01 17:40:38'),
(419, 32, 126, '2025-09-01 17:40:38'),
(420, 32, 127, '2025-09-01 17:40:38'),
(421, 32, 125, '2025-09-01 17:40:38'),
(422, 1, 61, '2025-09-01 17:40:38'),
(423, 1, 60, '2025-09-01 17:40:38'),
(424, 1, 59, '2025-09-01 17:40:38'),
(425, 1, 126, '2025-09-01 17:40:38'),
(426, 1, 127, '2025-09-01 17:40:38'),
(427, 1, 125, '2025-09-01 17:40:38');

-- --------------------------------------------------------

--
-- Table structure for table `auth_sessions`
--

DROP TABLE IF EXISTS `auth_sessions`;
CREATE TABLE IF NOT EXISTS `auth_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_settings`
--

DROP TABLE IF EXISTS `auth_settings`;
CREATE TABLE IF NOT EXISTS `auth_settings` (
  `setting_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text COLLATE utf8mb4_unicode_ci,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_name`),
  KEY `updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_settings`
--

INSERT INTO `auth_settings` (`setting_name`, `setting_value`, `updated_by`, `updated_at`) VALUES
('lockout_duration', '900', 2, '2025-07-21 03:46:15'),
('max_login_attempts', '20', 2, '2025-07-21 03:46:15'),
('min_password_length', '6', 2, '2025-06-17 23:24:01'),
('password_expiry_days', '365', 2, '2025-07-19 03:15:49'),
('password_history_count', '10', 2, '2025-07-19 03:15:49'),
('password_min_length', '8', 2, '2025-07-19 03:15:49'),
('password_require_digit', '1', 2, '2025-07-19 03:15:49'),
('password_require_lower', '1', 2, '2025-07-19 03:15:49'),
('password_require_special', '1', 2, '2025-07-19 03:15:49'),
('password_require_upper', '1', 2, '2025-07-19 03:15:49'),
('require_lowercase', '1', 2, '2025-06-17 23:24:01'),
('require_numbers', '1', 2, '2025-06-17 23:24:01'),
('require_special_chars', '0', 2, '2025-06-17 23:24:01'),
('require_uppercase', '1', 2, '2025-06-17 23:24:01'),
('session_timeout', '3600', 2, '2025-07-21 03:45:40');

-- --------------------------------------------------------

--
-- Table structure for table `auth_trusted_devices`
--

DROP TABLE IF EXISTS `auth_trusted_devices`;
CREATE TABLE IF NOT EXISTS `auth_trusted_devices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `device_token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_device_token` (`device_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_users`
--

DROP TABLE IF EXISTS `auth_users`;
CREATE TABLE IF NOT EXISTS `auth_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `callsign` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','suspended') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `is_Admin` tinyint(1) DEFAULT '0',
  `is_SuperAdmin` tinyint(1) DEFAULT '0',
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int DEFAULT '0',
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login_attempt` timestamp NULL DEFAULT NULL,
  `password_reset_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_reset_expires` timestamp NULL DEFAULT NULL,
  `dues_paid` tinyint(1) DEFAULT '0',
  `membership_expiry` date DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT '0',
  `two_factor_secret` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `two_factor_backup_codes` text COLLATE utf8mb4_unicode_ci,
  `two_factor_last_used` timestamp NULL DEFAULT NULL,
  `two_factor_setup_at` timestamp NULL DEFAULT NULL,
  `backup_codes` text COLLATE utf8mb4_unicode_ci COMMENT 'JSON array of backup codes for 2FA',
  `theme_preference` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'default',
  `email_notifications` tinyint(1) DEFAULT '1',
  `sms_notifications` tinyint(1) DEFAULT '0',
  `notification_frequency` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'immediate',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `idx_callsign` (`callsign`),
  KEY `idx_super_admin` (`is_SuperAdmin`),
  KEY `idx_admin_super` (`is_Admin`,`is_SuperAdmin`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TOTP secret for 2FA';

--
-- Dumping data for table `auth_users`
--

INSERT INTO `auth_users` (`id`, `username`, `callsign`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `status`, `is_Admin`, `is_SuperAdmin`, `last_login`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `last_login_attempt`, `password_reset_token`, `password_reset_expires`, `dues_paid`, `membership_expiry`, `two_factor_enabled`, `two_factor_secret`, `two_factor_backup_codes`, `two_factor_last_used`, `two_factor_setup_at`, `backup_codes`, `theme_preference`, `email_notifications`, `sms_notifications`, `notification_frequency`) VALUES
(1, 'admin', 'ADM1N', 'admin@w5obm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', '', 'active', 1, 0, NULL, 0, NULL, '2025-06-07 03:34:27', '2025-06-08 05:54:30', NULL, NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'default', 1, 0, 'immediate'),
(2, 'KD5BS', 'KD5BS', 'kd5bs@arrl.net', '$2y$10$45Qg28.pe9glRjsgPGI0Y.L3zRVguT5JC6p3ySK31t81h68CE7arG', 'Robert', 'Stroud', '9014880460', 'active', 1, 1, '2025-07-12 22:23:16', 0, NULL, '2025-06-07 03:34:27', '2025-07-21 13:07:56', '2025-07-21 13:07:24', NULL, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'light', 1, 0, 'immediate');

-- --------------------------------------------------------

--
-- Table structure for table `auth_user_dashboard_widgets`
--

DROP TABLE IF EXISTS `auth_user_dashboard_widgets`;
CREATE TABLE IF NOT EXISTS `auth_user_dashboard_widgets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `widget_id` int NOT NULL,
  `position_x` int DEFAULT '0',
  `position_y` int DEFAULT '0',
  `is_visible` tinyint(1) DEFAULT '1',
  `custom_config` json DEFAULT NULL,
  `added_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_widget` (`user_id`,`widget_id`),
  KEY `widget_id` (`widget_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_user_dashboard_widgets`
--

INSERT INTO `auth_user_dashboard_widgets` (`id`, `user_id`, `widget_id`, `position_x`, `position_y`, `is_visible`, `custom_config`, `added_at`) VALUES
(1, 1, 1, 4, 0, 1, NULL, '2025-06-07 03:42:08'),
(2, 1, 2, 8, 0, 1, NULL, '2025-06-07 03:42:08'),
(3, 1, 3, 0, 0, 1, NULL, '2025-06-07 03:42:08'),
(4, 1, 4, 4, 3, 1, NULL, '2025-06-07 03:42:08'),
(5, 1, 5, 8, 3, 1, NULL, '2025-06-07 03:42:08'),
(6, 1, 6, 0, 3, 1, NULL, '2025-06-07 03:42:08'),
(7, 1, 7, 4, 6, 1, NULL, '2025-06-07 03:42:08'),
(8, 2, 1, 4, 0, 1, NULL, '2025-06-07 03:42:08'),
(9, 2, 2, 8, 0, 1, NULL, '2025-06-07 03:42:08'),
(10, 2, 3, 0, 0, 1, NULL, '2025-06-07 03:42:08'),
(11, 2, 4, 4, 3, 1, NULL, '2025-06-07 03:42:08'),
(12, 2, 5, 8, 3, 1, NULL, '2025-06-07 03:42:08'),
(13, 2, 6, 0, 3, 1, NULL, '2025-06-07 03:42:08'),
(14, 2, 7, 4, 6, 1, NULL, '2025-06-07 03:42:08');

-- --------------------------------------------------------

--
-- Table structure for table `auth_user_levels`
--

DROP TABLE IF EXISTS `auth_user_levels`;
CREATE TABLE IF NOT EXISTS `auth_user_levels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `level_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level_value` int NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `level_name` (`level_name`),
  KEY `idx_level_value` (`level_value`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_user_levels`
--

INSERT INTO `auth_user_levels` (`id`, `level_name`, `level_value`, `description`, `created_at`) VALUES
(1, 'user', 1, 'Regular user - basic access', '2025-07-13 19:40:53'),
(2, 'member', 2, 'Club member - extended access', '2025-07-13 19:40:53'),
(3, 'officer', 3, 'Club officer - management access', '2025-07-13 19:40:53'),
(4, 'admin', 4, 'Administrator - system access', '2025-07-13 19:40:53'),
(5, 'super_admin', 5, 'Super Administrator - unlimited access', '2025-07-13 19:40:53');

-- --------------------------------------------------------

--
-- Table structure for table `auth_user_permissions`
--

DROP TABLE IF EXISTS `auth_user_permissions`;
CREATE TABLE IF NOT EXISTS `auth_user_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  KEY `assigned_by` (`assigned_by`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_user_permissions`
--

INSERT INTO `auth_user_permissions` (`id`, `user_id`, `permission_id`, `assigned_at`, `assigned_by`) VALUES
(81, 1, 58, '2025-07-13 19:24:57', 1),
(82, 2, 58, '2025-07-13 19:24:57', 1),
(84, 1, 129, '2025-07-13 19:24:57', 1),
(85, 2, 129, '2025-07-13 19:24:57', 1),
(87, 1, 125, '2025-07-13 19:24:57', 1),
(88, 2, 125, '2025-07-13 19:24:57', 1);

-- --------------------------------------------------------

--
-- Table structure for table `auth_user_preferences`
--

DROP TABLE IF EXISTS `auth_user_preferences`;
CREATE TABLE IF NOT EXISTS `auth_user_preferences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `preference_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preference_value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_preference` (`user_id`,`preference_key`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auth_user_roles`
--

DROP TABLE IF EXISTS `auth_user_roles`;
CREATE TABLE IF NOT EXISTS `auth_user_roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `role_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_role` (`user_id`,`role_id`),
  KEY `role_id` (`role_id`),
  KEY `assigned_by` (`assigned_by`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auth_user_roles`
--

INSERT INTO `auth_user_roles` (`id`, `user_id`, `role_id`, `assigned_at`, `assigned_by`, `is_active`) VALUES
(1, 1, 1, '2025-06-07 03:34:55', 1, 1),
(2, 2, 1, '2025-06-07 03:34:55', 1, 1);

--
-- Constraints for dumped tables
--

--
-- Constraints for table `auth_2fa_attempts`
--
ALTER TABLE `auth_2fa_attempts`
  ADD CONSTRAINT `auth_2fa_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_2fa_sessions`
--
ALTER TABLE `auth_2fa_sessions`
  ADD CONSTRAINT `auth_2fa_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_activity_log`
--
ALTER TABLE `auth_activity_log`
  ADD CONSTRAINT `auth_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `auth_audit_logs`
--
ALTER TABLE `auth_audit_logs`
  ADD CONSTRAINT `auth_audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `auth_email_templates`
--
ALTER TABLE `auth_email_templates`
  ADD CONSTRAINT `fk_email_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_email_verification`
--
ALTER TABLE `auth_email_verification`
  ADD CONSTRAINT `auth_email_verification_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_password_resets`
--
ALTER TABLE `auth_password_resets`
  ADD CONSTRAINT `auth_password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_remember_tokens`
--
ALTER TABLE `auth_remember_tokens`
  ADD CONSTRAINT `auth_remember_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_role_permissions`
--
ALTER TABLE `auth_role_permissions`
  ADD CONSTRAINT `auth_role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `auth_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auth_role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `auth_permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_sessions`
--
ALTER TABLE `auth_sessions`
  ADD CONSTRAINT `auth_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_settings`
--
ALTER TABLE `auth_settings`
  ADD CONSTRAINT `auth_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `auth_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `auth_trusted_devices`
--
ALTER TABLE `auth_trusted_devices`
  ADD CONSTRAINT `auth_trusted_devices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_user_dashboard_widgets`
--
ALTER TABLE `auth_user_dashboard_widgets`
  ADD CONSTRAINT `auth_user_dashboard_widgets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auth_user_dashboard_widgets_ibfk_2` FOREIGN KEY (`widget_id`) REFERENCES `auth_dashboard_widgets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `auth_user_permissions`
--
ALTER TABLE `auth_user_permissions`
  ADD CONSTRAINT `auth_user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auth_user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `auth_permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auth_user_permissions_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `auth_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `auth_user_roles`
--
ALTER TABLE `auth_user_roles`
  ADD CONSTRAINT `auth_user_roles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `auth_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auth_user_roles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `auth_roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `auth_user_roles_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `auth_users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
