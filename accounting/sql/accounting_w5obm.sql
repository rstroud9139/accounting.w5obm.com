-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: mysql.w5obm.com
-- Generation Time: Nov 16, 2025 at 01:51 PM
-- Server version: 8.0.42-0ubuntu0.24.04.2
-- PHP Version: 8.1.2-1ubuntu2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `accounting_w5obm`
--

-- --------------------------------------------------------

--
-- Table structure for table `acc_assets`
--

CREATE TABLE `acc_assets` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `value` decimal(10,2) NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `acquisition_date` date DEFAULT NULL,
  `depreciation_rate` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'Active',
  `category` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `location` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `serial_number` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `vendor` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `warranty_expiration` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_audit_logs`
--

CREATE TABLE `acc_audit_logs` (
  `id` int NOT NULL,
  `action` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `performed_by` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `details` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `performed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_categories`
--

CREATE TABLE `acc_categories` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `acc_categories`
--

INSERT INTO `acc_categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Operating', 'Operating activities cash flow classification', '2025-10-07 03:53:42', '2025-10-07 03:53:42'),
(2, 'Investing', 'Investing activities cash flow classification', '2025-10-07 03:53:42', '2025-10-07 03:53:42'),
(3, 'Financing', 'Financing activities cash flow classification', '2025-10-07 03:53:42', '2025-10-07 03:53:42');

-- --------------------------------------------------------

--
-- Table structure for table `acc_donations`
--

CREATE TABLE `acc_donations` (
  `id` int NOT NULL,
  `donor_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `donor_email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `donation_date` date NOT NULL,
  `purpose` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `account_id` int DEFAULT NULL,
  `member_id` int DEFAULT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_items`
--

CREATE TABLE `acc_items` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int DEFAULT NULL,
  `account_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_journals`
--

CREATE TABLE `acc_journals` (
  `id` int NOT NULL,
  `journal_date` date NOT NULL,
  `memo` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `source` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `ref_no` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `posted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_journal_lines`
--

CREATE TABLE `acc_journal_lines` (
  `id` int NOT NULL,
  `journal_id` int NOT NULL,
  `account_id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `debit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `credit` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_ledger_accounts`
--

CREATE TABLE `acc_ledger_accounts` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `account_number` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `account_type` varchar(20) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `parent_account_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `acc_ledger_accounts`
--

INSERT INTO `acc_ledger_accounts` (`id`, `category_id`, `name`, `description`, `created_at`, `updated_at`, `account_number`, `account_type`, `parent_account_id`, `active`, `created_by`, `updated_by`) VALUES
(1, 1, 'Membership Revenue', 'Revenue from membership fees', '2024-11-26 06:36:53', '2024-11-26 06:36:53', NULL, NULL, NULL, 1, NULL, NULL),
(2, 2, 'Office Expenses', 'Expenses for office supplies', '2024-11-26 06:36:53', '2024-11-26 06:36:53', NULL, NULL, NULL, 1, NULL, NULL),
(4, 53, 'Assets', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1000', 'Asset', NULL, 1, NULL, NULL),
(5, 54, 'Liabilities', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '2000', 'Liability', NULL, 1, NULL, NULL),
(6, 55, 'Equity', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '3000', 'Equity', NULL, 1, NULL, NULL),
(7, 3, 'Revenue', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '4000', 'Revenue', NULL, 1, NULL, NULL),
(8, 48, 'COGS', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '5000', 'COGS', NULL, 1, NULL, NULL),
(9, 56, 'Expenses', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '6000', 'Expense', NULL, 1, NULL, NULL),
(10, 53, 'Current Assets', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1100', 'Asset', 4, 1, NULL, NULL),
(11, 57, 'Cash & Cash Equivalents', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1110', 'Asset', 10, 1, NULL, NULL),
(12, 57, 'Checking Account', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1111', 'Asset', 11, 1, NULL, NULL),
(13, 57, 'Savings Account', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1112', 'Asset', 11, 1, NULL, NULL),
(14, 58, 'Accounts Receivable', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1200', 'Asset', 10, 1, NULL, NULL),
(15, 59, 'Inventory', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1300', 'Asset', 10, 1, NULL, NULL),
(16, 60, 'Prepaid Expenses', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1400', 'Asset', 10, 1, NULL, NULL),
(17, 61, 'Fixed Assets', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1500', 'Asset', 4, 1, NULL, NULL),
(18, 62, 'Furniture & Equipment', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1510', 'Asset', 17, 1, NULL, NULL),
(19, 63, 'Vehicles', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1520', 'Asset', 17, 1, NULL, NULL),
(20, 64, 'Accumulated Depreciation', NULL, '2025-10-07 03:38:06', '2025-10-07 03:38:06', '1590', 'Asset', 17, 1, NULL, NULL),
(22, 0, 'Current Liabilities', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '2100', 'Liability', 5, 1, NULL, NULL),
(23, 0, 'Accounts Payable', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '2110', 'Liability', 22, 1, NULL, NULL),
(24, 0, 'Credit Cards', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '2120', 'Liability', 22, 1, NULL, NULL),
(25, 0, 'Payroll Liabilities', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '2130', 'Liability', 22, 1, NULL, NULL),
(26, 0, 'Sales Tax Payable', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '2140', 'Liability', 22, 1, NULL, NULL),
(27, 0, 'Unearned Revenue', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '2150', 'Liability', 22, 1, NULL, NULL),
(28, 0, 'Long-term Liabilities', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '2200', 'Liability', 5, 1, NULL, NULL),
(29, 0, 'Loans Payable', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '2210', 'Liability', 28, 1, NULL, NULL),
(30, 55, 'Owner\'s Equity', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '3100', 'Equity', 6, 1, NULL, NULL),
(31, 55, 'Retained Earnings', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '3200', 'Equity', 6, 1, NULL, NULL),
(32, 55, 'Opening Balance Equity', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '3300', 'Equity', 6, 1, NULL, NULL),
(33, 4, 'Sales Revenue', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '4100', 'Revenue', 7, 1, NULL, NULL),
(34, 5, 'Service Revenue', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '4200', 'Revenue', 7, 1, NULL, NULL),
(35, 7, 'Interest Income', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '4300', 'Revenue', 7, 1, NULL, NULL),
(36, 8, 'Other Income', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '4400', 'Revenue', 7, 1, NULL, NULL),
(37, 48, 'Cost of Goods Sold', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '5100', 'COGS', 8, 1, NULL, NULL),
(38, 49, 'Materials', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '5110', 'COGS', 8, 1, NULL, NULL),
(39, 50, 'Subcontractors', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '5120', 'COGS', 8, 1, NULL, NULL),
(40, 51, 'Freight & Shipping', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '5130', 'COGS', 8, 1, NULL, NULL),
(41, 52, 'Packaging', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '5140', 'COGS', 8, 1, NULL, NULL),
(42, 21, 'Payroll Expenses', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6100', 'Expense', 9, 1, NULL, NULL),
(43, 41, 'Rent Expense', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6200', 'Expense', 9, 1, NULL, NULL),
(44, 10, 'Utilities Expense', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6300', 'Expense', 9, 1, NULL, NULL),
(45, 30, 'Office Expenses', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6400', 'Expense', 9, 1, NULL, NULL),
(46, 26, 'Advertising & Marketing', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6500', 'Expense', 9, 1, NULL, NULL),
(47, 16, 'Travel Expense', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6600', 'Expense', 9, 1, NULL, NULL),
(48, 33, 'Insurance Expense', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6700', 'Expense', 9, 1, NULL, NULL),
(49, 37, 'Professional Fees', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6800', 'Expense', 9, 1, NULL, NULL),
(50, 43, 'Software & Subscriptions', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6900', 'Expense', 9, 1, NULL, NULL),
(51, 42, 'Repairs & Maintenance', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6950', 'Expense', 9, 1, NULL, NULL),
(52, 44, 'Bank & Merchant Fees', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6960', 'Expense', 9, 1, NULL, NULL),
(53, 45, 'Taxes & Licenses', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6970', 'Expense', 9, 1, NULL, NULL),
(54, 46, 'Depreciation Expense', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6980', 'Expense', 9, 1, NULL, NULL),
(55, 47, 'Bad Debt Expense', NULL, '2025-10-07 03:38:23', '2025-10-07 03:38:23', '6990', 'Expense', 9, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `acc_membership_dues`
--

CREATE TABLE `acc_membership_dues` (
  `id` int NOT NULL,
  `member_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `member_email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `amount_due` decimal(10,2) NOT NULL,
  `due_date` date NOT NULL,
  `payment_status` enum('Paid','Unpaid') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT 'Unpaid',
  `payment_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_recurring_transactions`
--

CREATE TABLE `acc_recurring_transactions` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `account_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `type` enum('Income','Expense') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `frequency` enum('Daily','Weekly','Monthly','Yearly') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `next_occurrence` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_reports`
--

CREATE TABLE `acc_reports` (
  `id` int NOT NULL,
  `report_type` enum('Monthly','Yearly','Category') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `parameters` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `file_path` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `acc_reports`
--

INSERT INTO `acc_reports` (`id`, `report_type`, `parameters`, `generated_at`, `file_path`) VALUES
(1, 'Monthly', '{\"month\":\"2024-11\"}', '2024-11-26 06:53:15', '/path/to/report1.pdf'),
(2, 'Yearly', '{\"year\":\"2024\"}', '2024-11-26 06:53:15', '/path/to/report2.pdf'),
(3, 'Monthly', '{\"month\":\"2024-11\"}', '2024-11-26 06:54:33', '/path/to/report1.pdf'),
(4, 'Yearly', '{\"year\":\"2024\"}', '2024-11-26 06:54:33', '/path/to/report2.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `acc_transactions`
--

CREATE TABLE `acc_transactions` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `account_id` int DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `type` enum('Income','Expense') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `customer_id` int DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `transaction_type` enum('Invoice','Payment','Bill','Deposit') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reference_number` varchar(100) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `acc_transactions`
--

INSERT INTO `acc_transactions` (`id`, `category_id`, `account_id`, `amount`, `transaction_date`, `description`, `type`, `customer_id`, `vendor_id`, `transaction_type`, `created_at`, `updated_at`, `reference_number`, `notes`, `created_by`, `updated_by`) VALUES
(5, NULL, NULL, 25.00, '2025-11-07', 'Individual Dues', 'Income', NULL, NULL, 'Payment', '2025-11-07 20:11:45', '2025-11-07 20:11:45', '5N028095PJ217204N', 'Online dues payment for KD5BS', NULL, NULL),
(6, 1, 11, 25.00, '2025-11-07', 'Individual Dues', 'Income', NULL, NULL, 'Deposit', '2025-11-07 20:30:38', '2025-11-12 07:00:17', '', '', NULL, 10),
(8, NULL, NULL, 25.00, '2025-11-13', 'Individual Dues', 'Income', 2124, NULL, 'Payment', '2025-11-13 22:06:50', '2025-11-13 22:06:50', '07H01804UA431974Y', 'Online dues payment for KD5BS', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `acc_transaction_categories`
--

CREATE TABLE `acc_transaction_categories` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `type` enum('Income','Expense','Asset','Liability','Equity') CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `parent_category_id` int DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `acc_transaction_categories`
--

INSERT INTO `acc_transaction_categories` (`id`, `category_id`, `name`, `type`, `description`, `created_at`, `updated_at`, `parent_category_id`, `active`, `created_by`, `updated_by`) VALUES
(1, NULL, 'Membership Fees', 'Income', 'Income from membership fees', '2024-11-26 06:36:35', '2024-11-26 06:36:35', NULL, 1, NULL, NULL),
(2, 1, 'Office Supplies', 'Expense', 'Expenses for office supplies', '2024-11-26 06:36:35', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(3, NULL, 'Revenue', '', NULL, '2025-10-07 01:41:19', '2025-10-07 01:41:19', NULL, 1, NULL, NULL),
(4, 1, 'Product Sales', '', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 3, 1, NULL, NULL),
(5, 1, 'Service Revenue', '', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 3, 1, NULL, NULL),
(6, 1, 'Subscription Revenue', '', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 3, 1, NULL, NULL),
(7, 1, 'Interest Income', '', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 3, 1, NULL, NULL),
(8, 1, 'Other Income', '', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 3, 1, NULL, NULL),
(9, NULL, 'Refunds & Discounts', '', NULL, '2025-10-07 01:41:19', '2025-10-07 01:41:19', 3, 1, NULL, NULL),
(10, 1, 'Utilities', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(11, 1, 'Electricity', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 10, 1, NULL, NULL),
(12, 1, 'Water', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 10, 1, NULL, NULL),
(13, 1, 'Gas', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 10, 1, NULL, NULL),
(14, 1, 'Internet', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 10, 1, NULL, NULL),
(15, 1, 'Telephone', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 10, 1, NULL, NULL),
(16, 1, 'Travel', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(17, 1, 'Airfare', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 16, 1, NULL, NULL),
(18, 1, 'Lodging', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 16, 1, NULL, NULL),
(19, 1, 'Meals', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 16, 1, NULL, NULL),
(20, 1, 'Transportation', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 16, 1, NULL, NULL),
(21, 1, 'Payroll', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(22, 1, 'Wages', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 21, 1, NULL, NULL),
(23, 1, 'Payroll Taxes', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 21, 1, NULL, NULL),
(24, 1, 'Benefits', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 21, 1, NULL, NULL),
(25, 1, 'Contract Labor', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 21, 1, NULL, NULL),
(26, 1, 'Marketing & Advertising', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(27, 1, 'Online Ads', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 26, 1, NULL, NULL),
(28, 1, 'Events', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 26, 1, NULL, NULL),
(29, 1, 'Sponsorships', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 26, 1, NULL, NULL),
(30, 1, 'Office Expenses', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(31, 1, 'Postage & Delivery', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 30, 1, NULL, NULL),
(32, 1, 'Printing & Stationery', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 30, 1, NULL, NULL),
(33, 1, 'Insurance', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(34, 1, 'General Liability', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 33, 1, NULL, NULL),
(35, 1, 'Health Insurance', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 33, 1, NULL, NULL),
(36, 1, 'Workers Compensation', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 33, 1, NULL, NULL),
(37, 1, 'Professional Services', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(38, 1, 'Accounting', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 37, 1, NULL, NULL),
(39, 1, 'Legal', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 37, 1, NULL, NULL),
(40, 1, 'Consulting', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', 37, 1, NULL, NULL),
(41, 1, 'Rent & Lease', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(42, 1, 'Repairs & Maintenance', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(43, 1, 'Software & Subscriptions', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(44, 1, 'Bank & Merchant Fees', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(45, 1, 'Taxes & Licenses', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(46, 1, 'Depreciation', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(47, 1, 'Bad Debt', 'Expense', NULL, '2025-10-07 01:41:19', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(48, 1, 'Cost of Goods Sold', 'Expense', NULL, '2025-10-07 01:41:20', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(49, 1, 'Materials', 'Expense', NULL, '2025-10-07 01:41:20', '2025-10-07 03:53:42', 48, 1, NULL, NULL),
(50, 1, 'Subcontractors', 'Expense', NULL, '2025-10-07 01:41:20', '2025-10-07 03:53:42', 48, 1, NULL, NULL),
(51, 1, 'Freight & Shipping', 'Expense', NULL, '2025-10-07 01:41:20', '2025-10-07 03:53:42', 48, 1, NULL, NULL),
(52, 1, 'Packaging', 'Expense', NULL, '2025-10-07 01:41:20', '2025-10-07 03:53:42', 48, 1, NULL, NULL),
(53, NULL, 'Assets', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:38:05', NULL, 1, NULL, NULL),
(54, NULL, 'Liabilities', 'Liability', NULL, '2025-10-07 03:38:05', '2025-10-07 03:38:05', NULL, 1, NULL, NULL),
(55, NULL, 'Equity', 'Equity', NULL, '2025-10-07 03:38:05', '2025-10-07 03:38:05', NULL, 1, NULL, NULL),
(56, NULL, 'Expenses', 'Expense', NULL, '2025-10-07 03:38:05', '2025-10-07 03:38:05', NULL, 1, NULL, NULL),
(57, NULL, 'Cash & Cash Equivalents', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:38:05', 53, 1, NULL, NULL),
(58, NULL, 'Accounts Receivable', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:38:05', 53, 1, NULL, NULL),
(59, 2, 'Inventory', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:53:42', 53, 1, NULL, NULL),
(60, 2, 'Prepaid Expenses', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:53:42', 53, 1, NULL, NULL),
(61, 2, 'Fixed Assets', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:53:42', 53, 1, NULL, NULL),
(62, 2, 'Furniture & Equipment', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:53:42', 53, 1, NULL, NULL),
(63, 2, 'Vehicles', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:53:42', 53, 1, NULL, NULL),
(64, 2, 'Accumulated Depreciation', 'Asset', NULL, '2025-10-07 03:38:05', '2025-10-07 03:53:42', 53, 1, NULL, NULL),
(65, NULL, 'Revenue', 'Income', NULL, '2025-10-07 03:52:57', '2025-10-07 03:52:57', NULL, 1, NULL, NULL),
(66, 3, 'Owner Contribution', 'Equity', 'Owner cash/equity contribution', '2025-10-07 03:53:42', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(67, 3, 'Owner Distribution', 'Equity', 'Owner cash draw/distribution', '2025-10-07 03:53:42', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(68, 3, 'Loan Proceeds', 'Liability', 'Proceeds from loan', '2025-10-07 03:53:42', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(69, 3, 'Loan Principal Payment', 'Liability', 'Principal repayment on loan', '2025-10-07 03:53:42', '2025-10-07 03:53:42', NULL, 1, NULL, NULL),
(70, NULL, 'Bank Account - Cash', 'Asset', 'Club&amp;#039;s Primary Bank Account', '2025-11-08 05:38:00', '2025-11-08 05:38:00', 57, 1, 6, NULL),
(71, NULL, 'PayPal Account - Cash Holding', 'Asset', 'PayPal Holding Cash Account.  In order to minimize amount paid to PayPal, funds are left on account with PayPal until needed, then requested to be transferred into the primary bank account.', '2025-11-08 05:40:07', '2025-11-08 05:40:07', 57, 1, 6, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `acc_vendors`
--

CREATE TABLE `acc_vendors` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `phone` varchar(50) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `description` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `contact_name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci,
  `active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

--
-- Dumping data for table `acc_vendors`
--

INSERT INTO `acc_vendors` (`id`, `name`, `email`, `phone`, `address`, `description`, `created_at`, `updated_at`, `contact_name`, `notes`, `active`) VALUES
(1, 'Office Depot', 'contact@officedepot.com', '555-0000', '789 Oak St', 'Office supplies vendor', '2024-11-26 06:37:15', '2024-11-26 06:37:15', NULL, NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `acc_assets`
--
ALTER TABLE `acc_assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_acc_assets_acq_date` (`acquisition_date`);

--
-- Indexes for table `acc_audit_logs`
--
ALTER TABLE `acc_audit_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acc_categories`
--
ALTER TABLE `acc_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acc_donations`
--
ALTER TABLE `acc_donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `acc_items`
--
ALTER TABLE `acc_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `acc_journals`
--
ALTER TABLE `acc_journals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acc_journal_lines`
--
ALTER TABLE `acc_journal_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_journal_id` (`journal_id`),
  ADD KEY `idx_account_id` (`account_id`),
  ADD KEY `idx_category_id` (`category_id`);

--
-- Indexes for table `acc_ledger_accounts`
--
ALTER TABLE `acc_ledger_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_acc_ledger_account_number` (`account_number`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_ledger_type` (`account_type`),
  ADD KEY `idx_acc_ledger_parent` (`parent_account_id`),
  ADD KEY `idx_acc_ledger_active` (`active`);

--
-- Indexes for table `acc_membership_dues`
--
ALTER TABLE `acc_membership_dues`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `acc_recurring_transactions`
--
ALTER TABLE `acc_recurring_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `acc_reports`
--
ALTER TABLE `acc_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_acc_reports_type` (`report_type`),
  ADD KEY `idx_acc_reports_generated` (`generated_at`);

--
-- Indexes for table `acc_transactions`
--
ALTER TABLE `acc_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_acc_txn_category` (`category_id`),
  ADD KEY `idx_acc_transactions_vendor` (`vendor_id`),
  ADD KEY `idx_acc_transactions_date_type` (`transaction_date`,`type`),
  ADD KEY `idx_acc_transactions_account_date` (`account_id`,`transaction_date`),
  ADD KEY `idx_acc_transactions_type_date` (`type`,`transaction_date`),
  ADD KEY `idx_acc_transactions_vendor_date` (`vendor_id`,`transaction_date`);

--
-- Indexes for table `acc_transaction_categories`
--
ALTER TABLE `acc_transaction_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `fk_acc_cat_parent` (`parent_category_id`),
  ADD KEY `idx_acc_cat_type` (`type`);

--
-- Indexes for table `acc_vendors`
--
ALTER TABLE `acc_vendors`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `acc_assets`
--
ALTER TABLE `acc_assets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_audit_logs`
--
ALTER TABLE `acc_audit_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_categories`
--
ALTER TABLE `acc_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `acc_donations`
--
ALTER TABLE `acc_donations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `acc_items`
--
ALTER TABLE `acc_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_journals`
--
ALTER TABLE `acc_journals`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_journal_lines`
--
ALTER TABLE `acc_journal_lines`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_ledger_accounts`
--
ALTER TABLE `acc_ledger_accounts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `acc_membership_dues`
--
ALTER TABLE `acc_membership_dues`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_recurring_transactions`
--
ALTER TABLE `acc_recurring_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_reports`
--
ALTER TABLE `acc_reports`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `acc_transactions`
--
ALTER TABLE `acc_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `acc_transaction_categories`
--
ALTER TABLE `acc_transaction_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `acc_vendors`
--
ALTER TABLE `acc_vendors`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
