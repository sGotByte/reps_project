-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 14, 2026 at 10:32 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `reps_v2_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_terms`
--

CREATE TABLE `academic_terms` (
  `id` int UNSIGNED NOT NULL,
  `year_be` smallint UNSIGNED NOT NULL COMMENT 'ปีการศึกษา พ.ศ. เช่น 2569',
  `term_no` tinyint UNSIGNED NOT NULL COMMENT '1, 2 หรือ 3 (ภาคฤดูร้อน)',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `max_staff` tinyint UNSIGNED NOT NULL DEFAULT '4',
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `academic_terms`
--

INSERT INTO `academic_terms` (`id`, `year_be`, `term_no`, `start_date`, `end_date`, `max_staff`, `note`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2569, 1, '2026-05-01', '2026-10-31', 4, 'ทีม Lab boy 2 คน', 1214, '2026-11-14 11:39:41', '2026-11-14 11:39:41'),
(2, 2569, 2, '2026-11-01', '2027-04-30', 4, 'ทีม Lab boy 3 คน', 1214, '2026-11-14 11:39:41', '2026-11-14 11:39:41');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(1, 'คอมพิวเตอร์และฮาร์ดแวร์'),
(5, 'งานแอดมิน / ระบบ'),
(2, 'ระบบปฏิบัติการและซอฟต์แวร์'),
(4, 'อินเทอร์เน็ต / เครือข่าย'),
(6, 'อื่นๆ'),
(3, 'อุปกรณ์ต่อพ่วงและเครื่องพิมพ์');

-- --------------------------------------------------------

--
-- Table structure for table `checklist_presets`
--

CREATE TABLE `checklist_presets` (
  `id` int NOT NULL,
  `category_id` int DEFAULT NULL COMMENT 'หมวดหมู่งาน (NULL = ใช้กับทุกหมวด)',
  `item_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_type` enum('main','accessory') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'accessory',
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='รายการเช็คลิสต์ตั้งต้นตามหมวดหมู่ (admin แก้ได้)';

--
-- Dumping data for table `checklist_presets`
--

INSERT INTO `checklist_presets` (`id`, `category_id`, `item_name`, `item_type`, `sort_order`, `is_active`) VALUES
(1, 1, 'ตัวเครื่อง / เคส', 'main', 1, 1),
(2, 1, 'สายไฟ / อะแดปเตอร์', 'accessory', 2, 1),
(3, 1, 'จอภาพ', 'accessory', 3, 1),
(4, 1, 'เมาส์', 'accessory', 4, 1),
(5, 1, 'คีย์บอร์ด', 'accessory', 5, 1),
(6, 1, 'สายสัญญาณภาพ (HDMI/VGA)', 'accessory', 6, 1),
(7, 3, 'ตัวเครื่อง', 'main', 1, 1),
(8, 3, 'สายไฟ / อะแดปเตอร์', 'accessory', 2, 1),
(9, 3, 'สาย USB / สายสัญญาณ', 'accessory', 3, 1),
(10, 3, 'ตลับหมึก / หมึก', 'accessory', 4, 1),
(11, 3, 'รีโมต', 'accessory', 5, 1),
(12, 4, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', 1, 1),
(13, 4, 'อะแดปเตอร์ไฟ', 'accessory', 2, 1),
(14, 4, 'สาย LAN', 'accessory', 3, 1),
(15, 4, 'เสาอากาศ', 'accessory', 4, 1),
(16, 2, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', 1, 1),
(17, 2, 'สายชาร์จ / อะแดปเตอร์', 'accessory', 2, 1),
(18, 2, 'กระเป๋า', 'accessory', 3, 1),
(19, 5, 'ตัวเครื่อง', 'main', 1, 1),
(20, 5, 'สายไฟ / อะแดปเตอร์', 'accessory', 2, 1),
(21, 6, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', 1, 1),
(22, 6, 'สายไฟ / อะแดปเตอร์', 'accessory', 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `d_id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`d_id`, `name`) VALUES
(3, 'การบัญชี'),
(8, 'ช่างกลโรงงาน'),
(10, 'ช่างก่อสร้าง'),
(7, 'ช่างยนต์'),
(9, 'ช่างเชื่อมโลหะ'),
(13, 'ช่างเทคนิคคอมพิวเตอร์'),
(12, 'ช่างเทคนิคอุตสาหกรรม'),
(6, 'ช่างไฟฟ้ากำลัง'),
(5, 'บุคลากร'),
(11, 'อิเล็กทรอนิกส์และหุ่นยนต์'),
(1, 'เทคโนโลยีธุรกิจดิจิทัล'),
(2, 'เทคโนโลยีสารสนเทศ');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int NOT NULL,
  `ticket_id` int DEFAULT NULL COMMENT 'ใบงานที่เกี่ยวข้อง (NULL ได้ถ้าลบใบงานแล้ว)',
  `to_email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'อีเมลผู้รับ',
  `template_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ชนิดอีเมล เช่น intake_recorded, requester_turn',
  `status` enum('pending','sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'ผลการส่ง',
  `error` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'ข้อความ error กรณีส่งไม่สำเร็จ',
  `sent_at` datetime DEFAULT NULL COMMENT 'เวลาที่ส่งสำเร็จ',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `ticket_id`, `to_email`, `template_key`, `status`, `error`, `sent_at`, `created_at`) VALUES
(8432, 9777, 'demo_u@demo.reps.invalid', 'completed', 'failed', 'SMTP connection timed out', NULL, '2026-05-21 11:44:59'),
(8433, 9778, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-07 14:46:58', '2026-05-07 14:46:53'),
(8434, 9779, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-14 08:53:07', '2026-05-14 08:53:02'),
(8435, 9780, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-21 12:51:04', '2026-05-21 12:50:59'),
(8436, 9781, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-28 09:30:28', '2026-05-28 09:30:23'),
(8437, 9782, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-28 08:43:42', '2026-05-28 08:43:37'),
(8438, 9783, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-11 08:40:05', '2026-05-11 08:40:00'),
(8439, 9784, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-05 13:35:58', '2026-05-05 13:35:53'),
(8440, 9786, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-18 08:40:05', '2026-05-18 08:40:00'),
(8441, 9787, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-28 08:42:21', '2026-05-28 08:42:16'),
(8442, 9788, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-01 08:40:05', '2026-06-01 08:40:00'),
(8443, 9789, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-11 13:17:38', '2026-05-11 13:17:33'),
(8444, 9790, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-22 09:22:05', '2026-05-22 09:22:00'),
(8445, 9791, 'demo_u@demo.reps.invalid', 'completed', 'failed', 'SMTP connection timed out', NULL, '2026-05-11 08:40:00'),
(8446, 9792, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-15 09:10:59', '2026-05-15 09:10:54'),
(8447, 9793, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-11 08:40:05', '2026-05-11 08:40:00'),
(8448, 9794, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-18 08:40:05', '2026-05-18 08:40:00'),
(8449, 9795, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-18 08:40:05', '2026-05-18 08:40:00'),
(8450, 9796, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-19 14:17:06', '2026-05-19 14:17:01'),
(8451, 9797, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-26 09:30:46', '2026-05-26 09:30:41'),
(8452, 9798, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-25 08:40:05', '2026-05-25 08:40:00'),
(8453, 9800, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-18 08:40:05', '2026-05-18 08:40:00'),
(8454, 9801, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-19 08:56:58', '2026-05-19 08:56:53'),
(8455, 9802, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-15 09:12:24', '2026-05-15 09:12:19'),
(8456, 9803, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-05-22 08:47:28', '2026-05-22 08:47:23'),
(8457, 9804, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-18 15:44:53', '2026-06-18 15:44:48'),
(8458, 9806, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-18 08:45:50', '2026-06-18 08:45:45'),
(8459, 9807, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-16 08:43:48', '2026-06-16 08:43:43'),
(8460, 9808, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-25 12:37:50', '2026-06-25 12:37:45'),
(8461, 9809, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-19 08:41:51', '2026-06-19 08:41:46'),
(8462, 9810, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-17 12:33:38', '2026-06-17 12:33:33'),
(8463, 9811, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-01 09:17:17', '2026-07-01 09:17:12'),
(8464, 9812, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-01 12:15:52', '2026-07-01 12:15:47'),
(8465, 9813, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-25 09:30:11', '2026-06-25 09:30:06'),
(8466, 9814, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-18 08:58:21', '2026-06-18 08:58:16'),
(8467, 9815, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-26 09:08:23', '2026-06-26 09:08:18'),
(8468, 9816, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-30 09:25:19', '2026-06-30 09:25:14'),
(8469, 9817, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-06 08:40:05', '2026-07-06 08:40:00'),
(8470, 9818, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-17 08:55:26', '2026-06-17 08:55:21'),
(8471, 9820, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-24 08:52:15', '2026-06-24 08:52:10'),
(8472, 9821, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-22 08:40:05', '2026-06-22 08:40:00'),
(8473, 9822, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-11 09:13:51', '2026-06-11 09:13:46'),
(8474, 9823, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-15 08:40:05', '2026-06-15 08:40:00'),
(8475, 9824, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-23 08:52:51', '2026-06-23 08:52:46'),
(8476, 9825, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-22 08:40:05', '2026-06-22 08:40:00'),
(8477, 9826, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-08 08:40:05', '2026-06-08 08:40:00'),
(8478, 9827, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-22 08:40:05', '2026-06-22 08:40:00'),
(8479, 9828, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-17 09:25:08', '2026-06-17 09:25:03'),
(8480, 9829, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-11 09:17:26', '2026-06-11 09:17:21'),
(8481, 9830, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-11 08:35:40', '2026-06-11 08:35:35'),
(8482, 9831, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-06-18 10:57:53', '2026-06-18 10:57:48'),
(8483, 9832, 'demo_u@demo.reps.invalid', 'completed', 'failed', 'SMTP connection timed out', NULL, '2026-07-09 08:43:23'),
(8484, 9833, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-23 09:12:22', '2026-07-23 09:12:17'),
(8485, 9834, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-13 08:40:05', '2026-07-13 08:40:00'),
(8486, 9835, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-15 09:21:34', '2026-07-15 09:21:29'),
(8487, 9836, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-08 09:32:36', '2026-07-08 09:32:31'),
(8488, 9837, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-09 08:52:59', '2026-07-09 08:52:54'),
(8489, 9838, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-10 09:12:11', '2026-07-10 09:12:06'),
(8490, 9839, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-03 08:40:05', '2026-08-03 08:40:00'),
(8491, 9840, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-09 09:05:47', '2026-07-09 09:05:42'),
(8492, 9842, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-29 08:40:52', '2026-07-29 08:40:47'),
(8493, 9843, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-08 14:39:03', '2026-07-08 14:38:58'),
(8494, 9844, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-06 09:34:29', '2026-08-06 09:34:24'),
(8495, 9845, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-29 08:38:47', '2026-07-29 08:38:42'),
(8496, 9846, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-31 11:47:49', '2026-07-31 11:47:44'),
(8497, 9847, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-03 08:40:05', '2026-08-03 08:40:00'),
(8498, 9848, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-06 11:01:55', '2026-08-06 11:01:50'),
(8499, 9849, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-29 14:18:32', '2026-07-29 14:18:27'),
(8500, 9850, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-10 14:02:10', '2026-07-10 14:02:05'),
(8501, 9851, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-07-30 08:55:25', '2026-07-30 08:55:20'),
(8502, 9853, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-31 08:40:05', '2026-08-31 08:40:00'),
(8503, 9854, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-02 09:05:23', '2026-09-02 09:05:18'),
(8504, 9855, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-19 13:37:56', '2026-08-19 13:37:51'),
(8505, 9856, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-25 13:44:53', '2026-08-25 13:44:48'),
(8506, 9857, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-04 12:11:57', '2026-09-04 12:11:52'),
(8507, 9858, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-28 10:51:25', '2026-08-28 10:51:20'),
(8508, 9859, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-03 09:27:01', '2026-09-03 09:26:56'),
(8509, 9860, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-18 11:14:04', '2026-08-18 11:13:59'),
(8510, 9862, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-06 09:03:35', '2026-08-06 09:03:30'),
(8511, 9863, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-17 08:40:05', '2026-08-17 08:40:00'),
(8512, 9864, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-14 08:54:12', '2026-08-14 08:54:07'),
(8513, 9865, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-10 08:40:05', '2026-08-10 08:40:00'),
(8514, 9866, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-17 08:40:05', '2026-08-17 08:40:00'),
(8515, 9867, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-12 09:06:32', '2026-08-12 09:06:27'),
(8516, 9868, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-07 08:58:08', '2026-08-07 08:58:03'),
(8517, 9869, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-25 14:00:04', '2026-08-25 13:59:59'),
(8518, 9871, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-03 09:09:41', '2026-09-03 09:09:36'),
(8519, 9872, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-03 09:17:32', '2026-09-03 09:17:27'),
(8520, 9873, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-08-11 14:31:35', '2026-08-11 14:31:30'),
(8521, 9874, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-25 12:48:36', '2026-09-25 12:48:31'),
(8522, 9875, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-22 09:02:23', '2026-09-22 09:02:18'),
(8523, 9876, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-30 09:16:41', '2026-09-30 09:16:36'),
(8524, 9877, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-08 11:21:26', '2026-09-08 11:21:21'),
(8525, 9878, 'demo_u@demo.reps.invalid', 'completed', 'failed', 'SMTP connection timed out', NULL, '2026-10-01 09:05:39'),
(8526, 9879, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-09 08:59:38', '2026-09-09 08:59:33'),
(8527, 9880, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-30 08:51:49', '2026-09-30 08:51:44'),
(8528, 9881, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-09 08:59:30', '2026-09-09 08:59:25'),
(8529, 9882, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-28 08:40:05', '2026-09-28 08:40:00'),
(8530, 9883, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-02 11:18:27', '2026-10-02 11:18:22'),
(8531, 9884, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-18 14:32:34', '2026-09-18 14:32:29'),
(8532, 9885, 'demo_u@demo.reps.invalid', 'completed', 'failed', 'SMTP connection timed out', NULL, '2026-09-16 12:01:03'),
(8533, 9886, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-05 08:40:05', '2026-10-05 08:40:00'),
(8534, 9887, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-21 08:40:05', '2026-09-21 08:40:00'),
(8535, 9888, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-25 08:36:09', '2026-09-25 08:36:04'),
(8536, 9889, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-10 08:43:24', '2026-09-10 08:43:19'),
(8537, 9890, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-24 11:01:23', '2026-09-24 11:01:18'),
(8538, 9891, 'demo_u@demo.reps.invalid', 'completed', 'failed', 'SMTP connection timed out', NULL, '2026-10-06 09:04:41'),
(8539, 9892, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-05 08:40:05', '2026-10-05 08:40:00'),
(8540, 9893, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-29 09:16:20', '2026-09-29 09:16:15'),
(8541, 9894, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-18 08:59:26', '2026-09-18 08:59:21'),
(8542, 9895, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-29 15:34:06', '2026-09-29 15:34:01'),
(8543, 9896, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-11 15:15:58', '2026-09-11 15:15:53'),
(8544, 9897, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-09-10 10:06:03', '2026-09-10 10:05:58'),
(8545, 9898, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-19 08:40:05', '2026-10-19 08:40:00'),
(8546, 9899, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-13 13:37:52', '2026-10-13 13:37:47'),
(8547, 9900, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-12 08:40:05', '2026-10-12 08:40:00'),
(8548, 9901, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-09 09:11:43', '2026-10-09 09:11:38'),
(8549, 9902, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-11-02 08:40:05', '2026-11-02 08:40:00'),
(8550, 9903, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-12 08:40:05', '2026-10-12 08:40:00'),
(8551, 9904, 'demo_u@demo.reps.invalid', 'completed', 'failed', 'SMTP connection timed out', NULL, '2026-10-27 11:54:37'),
(8552, 9905, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-11-03 10:22:53', '2026-11-03 10:22:48'),
(8553, 9906, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-16 10:04:27', '2026-10-16 10:04:22'),
(8554, 9908, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-11-02 08:40:05', '2026-11-02 08:40:00'),
(8555, 9909, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-10-23 09:30:44', '2026-10-23 09:30:39'),
(8556, 9910, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-11-02 08:40:05', '2026-11-02 08:40:00'),
(8557, 9919, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-11-04 08:58:28', '2026-11-04 08:58:23'),
(8558, 9929, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-11-09 08:40:05', '2026-11-09 08:40:00'),
(8559, 9931, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-11-10 09:32:12', '2026-11-10 09:32:07'),
(8560, 9933, 'demo_u@demo.reps.invalid', 'completed', 'sent', NULL, '2026-11-13 09:07:05', '2026-11-13 09:07:00'),
(8561, 9907, 'demo_u005@demo.reps.invalid', 'sign_reminder', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:02:18'),
(8562, 9913, 'demo_u037@demo.reps.invalid', 'sign_reminder', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:02:18'),
(8563, 9918, 'demo_u024@demo.reps.invalid', 'sign_reminder', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:02:18'),
(8564, 9915, 'demo_u023@demo.reps.invalid', 'intake_recorded', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:13:17'),
(8565, 9915, 'demo_u023@demo.reps.invalid', 'recheck_done', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:16:24'),
(8566, 9915, 'demo_u023@demo.reps.invalid', 'requester_turn', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:16:34'),
(8567, 9915, 'pongsathorn.cat@gmail.com', 'requester_signed', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:17:05'),
(8568, 9915, 'nalan@gmail.com', 'requester_signed', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:17:05'),
(8569, 9915, 't_admin@example.invalid', 'requester_signed', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:17:05'),
(8570, 9915, 'demo_admin1@demo.reps.invalid', 'requester_signed', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:17:05'),
(8571, 9915, 'demo_admin2@demo.reps.invalid', 'requester_signed', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:17:05'),
(8572, 9915, 'demo_u023@demo.reps.invalid', 'completed', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:22:42'),
(8573, 9915, 'lb2569-2-01@demo.reps.invalid', 'completed', 'failed', 'Mail transport (smtp) is not configured', NULL, '2026-11-14 16:22:42');

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int UNSIGNED NOT NULL,
  `template_key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `body` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int NOT NULL,
  `item_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'รหัสวัสดุ/อะไหล่',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ชิ้น',
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock_qty` int NOT NULL DEFAULT '0',
  `min_stock` int NOT NULL DEFAULT '5' COMMENT 'จุดแจ้งเตือนของใกล้หมด',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_code`, `name`, `unit`, `image_path`, `stock_qty`, `min_stock`, `updated_at`) VALUES
(1, 'MAT-001', 'หัวแลน RJ45 Cat6', 'หัว', NULL, 100, 20, '2026-11-13 07:35:53'),
(2, 'MAT-002', 'สายแลน Cat6', 'เมตร', NULL, 300, 50, '2026-11-12 14:02:49'),
(3, 'MAT-003', 'เมาส์ USB มาตรฐาน', 'ตัว', NULL, 15, 5, '2026-11-13 00:07:41'),
(4, 'MAT-004', 'คีย์บอร์ด USB มาตรฐาน', 'ตัว', NULL, 15, 5, '2026-11-13 06:50:36'),
(5, 'MAT-005', 'ถ่านไบออส CR2032', 'ก้อน', NULL, 20, 5, '2026-11-12 14:02:49'),
(142, 'DM-RJ45', 'หัวแลน RJ45 Cat6 (สาธิต)', 'หัว', NULL, 153, 20, '2026-11-14 15:49:25'),
(143, 'DM-LAN', 'สายแลน Cat6 (สาธิต)', 'เมตร', NULL, 278, 50, '2026-11-14 15:49:25'),
(144, 'DM-MOUSE', 'เมาส์ USB (สาธิต)', 'ตัว', NULL, 28, 5, '2026-11-14 15:49:25'),
(145, 'DM-KB', 'คีย์บอร์ด USB (สาธิต)', 'ตัว', NULL, 4, 5, '2026-11-14 16:56:40'),
(146, 'DM-BIOS', 'ถ่านไบออส CR2032 (สาธิต)', 'ก้อน', NULL, 27, 5, '2026-11-14 15:49:25'),
(147, 'DM-PASTE', 'ซิลิโคนระบายความร้อน (สาธิต)', 'หลอด', 'ticket-private/inv_64ab1758a4eec61b0f02855a9a775c7f.png', 12, 3, '2026-11-14 16:56:08'),
(148, 'DM-PSU', 'พาวเวอร์ซัพพลาย 500W (สาธิต)', 'ตัว', NULL, 1, 2, '2026-11-14 15:49:25'),
(149, 'DM-HDMI', 'สาย HDMI 3 เมตร (สาธิต)', 'เส้น', NULL, 18, 3, '2026-11-14 15:49:25');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `movement_type` enum('receive','withdraw','adjust') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'receive=รับเข้า, withdraw=เบิกออก, adjust=ปรับยอด',
  `qty_change` int NOT NULL COMMENT 'จำนวนที่เปลี่ยน (บวก=เพิ่ม, ลบ=ลด)',
  `stock_before` int NOT NULL COMMENT 'ยอดคงเหลือก่อนทำรายการ',
  `stock_after` int NOT NULL COMMENT 'ยอดคงเหลือหลังทำรายการ',
  `ticket_id` int DEFAULT NULL COMMENT 'ใบงานที่เบิกวัสดุ (ถ้ามี)',
  `action_by` int DEFAULT NULL COMMENT 'ผู้ทำรายการ (NULL = ระบบ)',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_movements`
--

INSERT INTO `inventory_movements` (`id`, `item_id`, `movement_type`, `qty_change`, `stock_before`, `stock_after`, `ticket_id`, `action_by`, `note`, `created_at`) VALUES
(3827, 142, 'receive', 160, 0, 160, NULL, 1366, 'ยอดยกมาต้นปีการศึกษา 2569', '2026-05-01 09:00:00'),
(3828, 143, 'receive', 300, 0, 300, NULL, 1366, 'ยอดยกมาต้นปีการศึกษา 2569', '2026-05-01 09:00:00'),
(3829, 144, 'receive', 40, 0, 40, NULL, 1366, 'ยอดยกมาต้นปีการศึกษา 2569', '2026-05-01 09:00:00'),
(3830, 145, 'receive', 40, 0, 40, NULL, 1366, 'ยอดยกมาต้นปีการศึกษา 2569', '2026-05-01 09:00:00'),
(3831, 146, 'receive', 40, 0, 40, NULL, 1366, 'ยอดยกมาต้นปีการศึกษา 2569', '2026-05-01 09:00:00'),
(3832, 147, 'receive', 24, 0, 24, NULL, 1366, 'ยอดยกมาต้นปีการศึกษา 2569', '2026-05-01 09:00:00'),
(3833, 148, 'receive', 20, 0, 20, NULL, 1366, 'ยอดยกมาต้นปีการศึกษา 2569', '2026-05-01 09:00:00'),
(3834, 149, 'receive', 24, 0, 24, NULL, 1366, 'ยอดยกมาต้นปีการศึกษา 2569', '2026-05-01 09:00:00'),
(3835, 149, 'withdraw', -1, 24, 23, 9778, 1369, 'เบิกใช้กับใบงาน #DM-2026-0002', '2026-05-06 11:42:58'),
(3836, 147, 'withdraw', -1, 24, 23, 9780, 1368, 'เบิกใช้กับใบงาน #DM-2026-0004', '2026-05-20 10:35:52'),
(3837, 147, 'withdraw', -2, 23, 21, 9781, 1369, 'เบิกใช้กับใบงาน #DM-2026-0005', '2026-05-27 09:24:04'),
(3838, 144, 'withdraw', -1, 40, 39, 9782, 1369, 'เบิกใช้กับใบงาน #DM-2026-0006', '2026-05-26 10:34:19'),
(3839, 146, 'withdraw', -2, 40, 38, 9783, 1368, 'เบิกใช้กับใบงาน #DM-2026-0007', '2026-05-08 15:22:09'),
(3840, 146, 'withdraw', -2, 38, 36, 9789, 1369, 'เบิกใช้กับใบงาน #DM-2026-0013', '2026-05-11 12:05:00'),
(3841, 144, 'withdraw', -1, 39, 38, 9793, 1369, 'เบิกใช้กับใบงาน #DM-2026-0017', '2026-05-08 15:25:30'),
(3842, 149, 'withdraw', -2, 23, 21, 9800, 1368, 'เบิกใช้กับใบงาน #DM-2026-0024', '2026-05-15 11:48:16'),
(3843, 143, 'withdraw', -6, 300, 294, 9804, 1368, 'เบิกใช้กับใบงาน #DM-2026-0028', '2026-06-16 15:53:29'),
(3844, 142, 'withdraw', -1, 160, 159, 9807, 1368, 'เบิกใช้กับใบงาน #DM-2026-0031', '2026-06-15 09:34:22'),
(3845, 142, 'withdraw', -1, 159, 158, 9809, 1369, 'เบิกใช้กับใบงาน #DM-2026-0033', '2026-06-17 09:30:16'),
(3846, 144, 'withdraw', -2, 38, 36, 9817, 1369, 'เบิกใช้กับใบงาน #DM-2026-0041', '2026-07-03 09:04:46'),
(3847, 146, 'withdraw', -1, 36, 35, 9823, 1369, 'เบิกใช้กับใบงาน #DM-2026-0047', '2026-06-12 09:34:25'),
(3848, 144, 'withdraw', -1, 36, 35, 9827, 1368, 'เบิกใช้กับใบงาน #DM-2026-0051', '2026-06-19 09:05:11'),
(3849, 146, 'withdraw', -2, 35, 33, 9830, 1369, 'เบิกใช้กับใบงาน #DM-2026-0054', '2026-06-10 12:03:31'),
(3850, 144, 'withdraw', -2, 35, 33, 9832, 1369, 'เบิกใช้กับใบงาน #DM-2026-0056', '2026-07-08 16:10:16'),
(3851, 145, 'withdraw', -2, 40, 38, 9833, 1369, 'เบิกใช้กับใบงาน #DM-2026-0057', '2026-07-21 14:43:23'),
(3852, 145, 'withdraw', -1, 38, 37, 9834, 1369, 'เบิกใช้กับใบงาน #DM-2026-0058', '2026-07-10 09:22:29'),
(3853, 147, 'withdraw', -1, 21, 20, 9836, 1368, 'เบิกใช้กับใบงาน #DM-2026-0060', '2026-07-07 09:08:24'),
(3854, 146, 'withdraw', -1, 33, 32, 9837, 1368, 'เบิกใช้กับใบงาน #DM-2026-0061', '2026-07-08 15:59:23'),
(3855, 145, 'withdraw', -1, 37, 36, 9838, 1368, 'เบิกใช้กับใบงาน #DM-2026-0062', '2026-07-09 08:49:07'),
(3856, 149, 'withdraw', -1, 21, 20, 9839, 1368, 'เบิกใช้กับใบงาน #DM-2026-0063', '2026-07-30 09:00:02'),
(3857, 142, 'withdraw', -2, 158, 156, 9840, 1369, 'เบิกใช้กับใบงาน #DM-2026-0064', '2026-07-08 08:57:32'),
(3858, 148, 'withdraw', -2, 20, 18, 9842, 1368, 'เบิกใช้กับใบงาน #DM-2026-0066', '2026-07-28 08:58:10'),
(3859, 144, 'withdraw', -2, 33, 31, 9843, 1369, 'เบิกใช้กับใบงาน #DM-2026-0067', '2026-07-07 09:15:46'),
(3860, 148, 'withdraw', -2, 18, 16, 9845, 1369, 'เบิกใช้กับใบงาน #DM-2026-0069', '2026-07-27 08:40:00'),
(3861, 147, 'withdraw', -1, 20, 19, 9846, 1369, 'เบิกใช้กับใบงาน #DM-2026-0070', '2026-07-30 14:49:21'),
(3862, 142, 'withdraw', -1, 156, 155, 9847, 1368, 'เบิกใช้กับใบงาน #DM-2026-0071', '2026-07-30 08:41:22'),
(3863, 146, 'withdraw', -1, 32, 31, 9848, 1368, 'เบิกใช้กับใบงาน #DM-2026-0072', '2026-08-05 08:38:26'),
(3864, 145, 'withdraw', -2, 36, 34, 9850, 1369, 'เบิกใช้กับใบงาน #DM-2026-0074', '2026-07-09 13:15:05'),
(3865, 143, 'withdraw', -8, 294, 286, 9851, 1368, 'เบิกใช้กับใบงาน #DM-2026-0075', '2026-07-29 11:18:20'),
(3866, 147, 'withdraw', -1, 19, 18, 9855, 1368, 'เบิกใช้กับใบงาน #DM-2026-0079', '2026-08-17 14:08:48'),
(3867, 146, 'withdraw', -2, 31, 29, 9856, 1368, 'เบิกใช้กับใบงาน #DM-2026-0080', '2026-08-24 16:26:59'),
(3868, 143, 'withdraw', -8, 286, 278, 9864, 1369, 'เบิกใช้กับใบงาน #DM-2026-0088', '2026-08-13 08:37:37'),
(3869, 148, 'withdraw', -1, 16, 15, 9873, 1368, 'เบิกใช้กับใบงาน #DM-2026-0097', '2026-08-10 08:40:00'),
(3870, 147, 'withdraw', -2, 18, 16, 9874, 1368, 'เบิกใช้กับใบงาน #DM-2026-0098', '2026-09-24 09:11:27'),
(3871, 147, 'withdraw', -1, 16, 15, 9875, 1368, 'เบิกใช้กับใบงาน #DM-2026-0099', '2026-09-21 11:53:05'),
(3872, 146, 'withdraw', -2, 29, 27, 9877, 1369, 'เบิกใช้กับใบงาน #DM-2026-0101', '2026-09-07 08:40:00'),
(3873, 142, 'withdraw', -2, 155, 153, 9878, 1369, 'เบิกใช้กับใบงาน #DM-2026-0102', '2026-09-30 09:12:34'),
(3874, 148, 'withdraw', -1, 15, 14, 9886, 1368, 'เบิกใช้กับใบงาน #DM-2026-0110', '2026-10-02 09:27:24'),
(3875, 147, 'withdraw', -2, 15, 13, 9897, 1368, 'เบิกใช้กับใบงาน #DM-2026-0121', '2026-09-09 10:02:30'),
(3876, 149, 'withdraw', -2, 20, 18, 9903, 1369, 'เบิกใช้กับใบงาน #DM-2026-0127', '2026-10-08 11:48:32'),
(3877, 144, 'withdraw', -2, 31, 29, 9905, 1369, 'เบิกใช้กับใบงาน #DM-2026-0129', '2026-11-02 08:40:00'),
(3878, 144, 'withdraw', -1, 29, 28, 9910, 1369, 'เบิกใช้กับใบงาน #DM-2026-0134', '2026-10-30 09:17:50'),
(3879, 147, 'withdraw', -2, 13, 11, 9919, 1370, 'เบิกใช้กับใบงาน #DM-2026-0143', '2026-11-02 14:48:11'),
(3880, 147, 'adjust', -9, 11, 2, NULL, 1366, 'ตรวจนับสต็อกประจำเดือน', '2026-11-12 15:49:23'),
(3881, 148, 'adjust', -13, 14, 1, NULL, 1366, 'ตรวจนับสต็อกประจำเดือน', '2026-11-12 15:49:23'),
(3883, 147, 'receive', 10, 2, 12, NULL, 1366, NULL, '2026-11-14 16:56:08'),
(3884, 145, 'adjust', -30, 34, 4, NULL, 1366, 'ยอดผิด', '2026-11-14 16:56:40');

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `user_id` int NOT NULL,
  `notif_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_reads`
--

INSERT INTO `notification_reads` (`user_id`, `notif_key`, `read_at`) VALUES
(1366, 'urgent:9922', '2026-11-14 16:55:00');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `score_logs`
--

CREATE TABLE `score_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL COMMENT 'สตาฟที่ถูกหัก/เพิ่มคะแนน',
  `ticket_id` int DEFAULT NULL,
  `points_change` int NOT NULL COMMENT 'จำนวนคะแนนที่เปลี่ยน เช่น -5, +2',
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_by` int DEFAULT NULL COMMENT 'NULL = ระบบหักอัตโนมัติ',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `score_logs`
--

INSERT INTO `score_logs` (`id`, `user_id`, `ticket_id`, `points_change`, `reason`, `action_by`, `created_at`) VALUES
(10364, 1369, 9788, -5, 'คิว: ไม่กดรับงาน #DM-2026-0012 ภายใน 30 นาที', NULL, '2026-05-25 09:10:00'),
(10365, 1369, 9798, -5, 'คิว: ไม่กดรับงาน #DM-2026-0022 ภายใน 30 นาที', NULL, '2026-05-18 09:10:00'),
(10366, 1369, 9856, -5, 'คิว: ไม่กดรับงาน #DM-2026-0080 ภายใน 30 นาที', NULL, '2026-08-21 09:16:52'),
(10367, 1368, 9883, -5, 'คิว: ไม่กดรับงาน #DM-2026-0107 ภายใน 30 นาที', NULL, '2026-09-28 09:10:00'),
(10368, 1368, NULL, -5, 'ครูหักคะแนน: ความประพฤติหรือการแต่งกายไม่เหมาะสม', 1366, '2026-05-26 15:31:22'),
(10369, 1368, NULL, -20, 'ครูหักคะแนน: ทำอุปกรณ์เสียหายจากความประมาท', 1367, '2026-08-10 08:40:00'),
(10370, 1368, NULL, -5, 'ครูหักคะแนน: ความประพฤติหรือการแต่งกายไม่เหมาะสม', 1366, '2026-06-12 08:54:39'),
(10371, 1368, NULL, 5, 'ครูให้คะแนน: ได้รับคำชมจากผู้แจ้งหรือครู', 1366, '2026-08-21 09:29:19'),
(10372, 1369, NULL, -10, 'ครูหักคะแนน: มาสาย / ไม่มาเข้าเวรตามตาราง', 1366, '2026-06-11 12:07:13'),
(10373, 1369, NULL, -10, 'ครูหักคะแนน: มาสาย / ไม่มาเข้าเวรตามตาราง', 1366, '2026-06-26 08:51:35'),
(10374, 1369, NULL, -20, 'ครูหักคะแนน: ทำอุปกรณ์เสียหายจากความประมาท', 1366, '2026-07-13 08:40:00'),
(10375, 1370, NULL, -5, 'ครูหักคะแนน: ความประพฤติหรือการแต่งกายไม่เหมาะสม', 1366, '2026-11-06 14:16:45'),
(10376, 1370, NULL, -15, 'ครูหักคะแนน: ทิ้งงาน / ไม่แจ้งความคืบหน้าให้ผู้แจ้ง', 1366, '2026-11-02 08:55:15'),
(10377, 1370, NULL, 5, 'ครูให้คะแนน: ช่วยงานนอกเวลา / งานเร่งด่วน', 1367, '2026-11-13 09:20:28'),
(10378, 1371, NULL, -10, 'ครูหักคะแนน: มาสาย / ไม่มาเข้าเวรตามตาราง', 1366, '2026-11-12 14:00:24'),
(10379, 1371, NULL, -15, 'ครูหักคะแนน: ทิ้งงาน / ไม่แจ้งความคืบหน้าให้ผู้แจ้ง', 1366, '2026-11-04 09:33:11'),
(10380, 1371, NULL, 5, 'ครูให้คะแนน: ได้รับคำชมจากผู้แจ้งหรือครู', 1366, '2026-11-09 09:04:29'),
(10381, 1372, NULL, -10, 'ครูหักคะแนน: มาสาย / ไม่มาเข้าเวรตามตาราง', 1367, '2026-11-12 08:44:01'),
(10382, 1372, NULL, -20, 'ครูหักคะแนน: ทำอุปกรณ์เสียหายจากความประมาท', 1366, '2026-11-10 11:19:21'),
(10383, 1372, NULL, 5, 'ครูให้คะแนน: ช่วยงานนอกเวลา / งานเร่งด่วน', 1366, '2026-11-13 09:16:27'),
(10384, 1370, 9924, -5, 'คิว: ไม่กดรับงาน #DM-2026-0148 ภายใน 30 นาที', NULL, '2026-11-14 16:09:27'),
(10385, 1371, 9915, -5, 'คิว: ไม่กดรับงาน #DM-2026-0139 ภายใน 30 นาที', NULL, '2026-11-14 16:09:27'),
(10386, 1370, 9943, -5, 'คิว: ไม่กดรับงาน #DM-2026-0167 ภายใน 30 นาที', NULL, '2026-11-14 16:20:09'),
(10387, 1371, 9924, -5, 'คิว: ไม่กดรับงาน #DM-2026-0148 ภายใน 30 นาที', NULL, '2026-11-14 16:44:36'),
(10388, 1372, 9944, -5, 'คิว: ไม่กดรับงาน #LB-2026-DE0C598C ภายใน 30 นาที', NULL, '2026-11-14 16:44:36'),
(10389, 1371, 9943, -5, 'คิว: ไม่กดรับงาน #DM-2026-0167 ภายใน 30 นาที', NULL, '2026-11-14 16:50:16'),
(10390, 1370, 9944, -5, 'คิว: ไม่กดรับงาน #LB-2026-DE0C598C ภายใน 30 นาที', NULL, '2026-11-14 17:21:46'),
(10391, 1372, 9924, -5, 'คิว: ไม่กดรับงาน #DM-2026-0148 ภายใน 30 นาที', NULL, '2026-11-14 17:21:46'),
(10392, 1372, 9943, -5, 'คิว: ไม่กดรับงาน #DM-2026-0167 ภายใน 30 นาที', NULL, '2026-11-14 17:21:46'),
(10393, 1372, 9922, -5, 'คิว: ไม่กดรับงาน #DM-2026-0146 ภายใน 30 นาที', NULL, '2026-11-14 17:31:27');

-- --------------------------------------------------------

--
-- Table structure for table `score_rules`
--

CREATE TABLE `score_rules` (
  `id` int UNSIGNED NOT NULL,
  `label` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` smallint NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `score_rules`
--

INSERT INTO `score_rules` (`id`, `label`, `points`, `sort_order`, `is_active`, `updated_at`) VALUES
(1, 'มาสาย / ไม่มาเข้าเวรตามตาราง', -10, 10, 1, '2026-11-14 13:26:06'),
(2, 'ไม่บันทึกเช็คลิสต์ หรือบันทึกไม่ครบ', -10, 20, 1, '2026-11-14 13:26:06'),
(3, 'ทิ้งงาน / ไม่แจ้งความคืบหน้าให้ผู้แจ้ง', -15, 30, 1, '2026-11-14 13:26:06'),
(4, 'ทำอุปกรณ์เสียหายจากความประมาท', -20, 40, 1, '2026-11-14 13:26:06'),
(5, 'ความประพฤติหรือการแต่งกายไม่เหมาะสม', -5, 50, 1, '2026-11-14 13:26:06'),
(6, 'ช่วยงานนอกเวลา / งานเร่งด่วน', 5, 60, 1, '2026-11-14 13:26:06'),
(7, 'ได้รับคำชมจากผู้แจ้งหรือครู', 5, 70, 1, '2026-11-14 13:26:06');

-- --------------------------------------------------------

--
-- Table structure for table `site_blocks`
--

CREATE TABLE `site_blocks` (
  `id` int UNSIGNED NOT NULL,
  `page_key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `config_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `status` enum('draft','published') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_media`
--

CREATE TABLE `site_media` (
  `id` int UNSIGNED NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mime` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` int UNSIGNED NOT NULL DEFAULT '0',
  `width` int UNSIGNED NOT NULL DEFAULT '0',
  `height` int UNSIGNED NOT NULL DEFAULT '0',
  `uploaded_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_menus`
--

CREATE TABLE `site_menus` (
  `id` int UNSIGNED NOT NULL,
  `area` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_key` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `label_en` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `target` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `icon` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `group_key` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `sort_order` int NOT NULL DEFAULT '0',
  `visible_roles` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin,staff,user',
  `is_visible` tinyint(1) NOT NULL DEFAULT '1',
  `new_tab` tinyint(1) NOT NULL DEFAULT '0',
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_pages`
--

CREATE TABLE `site_pages` (
  `id` int UNSIGNED NOT NULL,
  `slug` varchar(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('draft','published') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_revisions`
--

CREATE TABLE `site_revisions` (
  `id` int UNSIGNED NOT NULL,
  `entity_type` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `data_json` mediumtext CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('academic_year_start_month', '5', 'เดือนที่เริ่มต้นปีการศึกษา (5 = พฤษภาคม)', '2026-11-12 14:02:49'),
('completion_bonus_points', '0', 'คะแนนที่สตาฟได้เมื่อครูรับรองงานเสร็จ', '2026-11-14 13:26:06'),
('donate_enabled', '0', 'เปิดปุ่มสนับสนุนค่ากาแฟ (1=เปิด, 0=ปิด)', '2026-11-13 07:35:53'),
('donate_message', '', 'ข้อความขอบคุณ/รายละเอียดหน้าสนับสนุน (ข้อความล้วน)', '2026-11-13 07:35:53'),
('donate_qr_path', 'ticket-private/qr_94a022a4911215eb27f2a50d10097875.png', 'path รูป QR พร้อมเพย์ (โฟลเดอร์อัปโหลดที่ปลอดภัย)', '2026-11-14 11:21:59'),
('footer_links', '', 'ลิงก์ใน footer (JSON: [{label, url}])', '2026-11-14 06:05:28'),
('footer_text', '', 'ข้อความ footer (ข้อความล้วน)', '2026-11-12 20:20:48'),
('homepage_announcement', '', 'ประกาศบนหน้าแรก (ข้อความล้วน)', '2026-11-13 07:35:53'),
('homepage_intro', '', 'ข้อความต้อนรับ/แนะนำบนหน้าแรก (ข้อความล้วน)', '2026-11-13 07:35:53'),
('homepage_show_announcement', '0', 'แสดงประกาศหน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-13 07:35:53'),
('homepage_show_faq', '1', 'แสดง FAQ หน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-12 20:20:48'),
('homepage_show_steps', '1', 'แสดงขั้นตอนการแจ้งซ่อมหน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-12 20:20:48'),
('intake_sign_skip_hours', '24', 'ผู้แจ้งไม่เซ็นรับทราบเช็คลิสต์ตอนรับเกินกี่ชั่วโมง admin จึงข้ามขั้นได้ (0 = ไม่อนุญาต)', '2026-11-13 19:47:14'),
('office_hours', 'จันทร์–ศุกร์ 08:30–16:30 น.', 'เวลาทำการของฝ่ายซ่อม', '2026-11-13 07:35:53'),
('org_contact', '', 'ช่องทางติดต่อ เช่น เบอร์โทร อีเมล หรือห้อง', '2026-11-13 07:28:58'),
('org_name', '', 'ชื่อสถานศึกษา/หน่วยงาน (แสดงใน footer และลิขสิทธิ์)', '2026-11-13 07:28:58'),
('org_unit', 'แผนกเทคโนโลยีธุรกิจดิจิทัล · ห้องปฏิบัติการกลางคอมพิวเตอร์', 'หน่วยงานที่ดูแลระบบ', '2026-11-13 07:35:53'),
('primary_color', '', 'สีหลักแบบกำหนดเอง (hex) ถ้าว่างให้ใช้ theme_color', '2026-11-12 20:20:48'),
('queue_cron_token', '', 'token สำหรับ cron ภายนอกเรียก cron_queue.php (ว่าง = ปิด)', '2026-11-12 22:58:06'),
('queue_dispatch_mode', 'auto', 'โหมดจ่ายงาน: auto=ตามคิวอัตโนมัติ, manual=admin เลือกสตาฟเอง', '2026-11-13 07:35:50'),
('queue_penalty_points', '5', 'จำนวนคะแนนที่หักเมื่อไม่รับงานตามคิว', '2026-11-12 14:02:49'),
('queue_timeout_minutes', '30', 'เวลาที่สตาฟต้องกดรับงานตามคิว (นาที)', '2026-11-12 14:02:49'),
('queue_work_end', '', 'เวลาสิ้นสุดนับคิว/หักคะแนน รูปแบบ HH:MM (ว่าง = ตลอด 24 ชม.)', '2026-11-12 22:58:06'),
('queue_work_start', '', 'เวลาเริ่มนับคิว/หักคะแนน รูปแบบ HH:MM (ว่าง = ตลอด 24 ชม.)', '2026-11-12 22:58:06'),
('requester_sign_skip_days', '3', 'ผู้แจ้งไม่เซ็นรับคืนเกินกี่วัน admin จึงเซ็นข้ามขั้นได้ (0 = ไม่อนุญาตให้ข้าม)', '2026-11-13 19:47:14'),
('sign_reminder_days', '1', 'ส่งอีเมลเตือนผู้แจ้งที่ยังไม่เซ็นซ้ำทุกกี่วัน (0 = ไม่เตือน)', '2026-11-13 19:47:14'),
('site_favicon_path', '', 'ไอคอนแท็บเบราว์เซอร์ (ว่าง = ไอคอนตั้งต้น)', '2026-11-14 06:05:28'),
('site_logo_path', '', 'path ไฟล์โลโก้เว็บ (โฟลเดอร์อัปโหลดที่ปลอดภัย)', '2026-11-12 20:20:48'),
('site_name', 'REPS - ระบบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์', 'ชื่อเว็บไซต์ที่แสดงบน Header และ Title', '2026-11-12 14:02:49'),
('site_tagline', '', 'คำโปรยของเว็บ (แสดงใต้ชื่อเว็บในหน้าแรกและคำอธิบายหน้าเว็บ)', '2026-11-14 06:05:28'),
('site_text_overrides', '', 'ข้อความ UI ที่ admin แก้เอง (JSON: ข้อความเดิม -> {th, en})', '2026-11-14 06:17:05'),
('stale_critical_hours', '72', 'งานค้างเกินกี่ชั่วโมงเป็นระดับวิกฤต (สีแดง)', '2026-11-13 19:47:14'),
('stale_warn_hours', '24', 'งานที่รับแล้วแต่ยังไม่เสร็จเกินกี่ชั่วโมงนับว่าค้าง (สีเหลือง)', '2026-11-13 19:47:14'),
('term_staff_max', '4', 'จำนวนสตาฟสูงสุดต่อภาคเรียน (ค่าเริ่มต้นของภาคเรียนใหม่)', '2026-11-14 11:35:53'),
('thanks_days', '30', 'ขอบคุณได้ภายในกี่วันหลังผู้แจ้งเซ็นรับคืน', '2026-11-14 13:26:06'),
('thanks_enabled', '1', 'เปิดให้ผู้แจ้งขอบคุณ Lab boy (QR ของแต่ละคน + บันทึกเงิน/ของที่ให้)', '2026-11-14 15:56:35'),
('thanks_max_amount', '5000', 'ยอดเงินสูงสุดต่อครั้งที่บันทึกได้ (บาท)', '2026-11-14 13:26:06'),
('theme_accent_color', '', 'สีรอง #RRGGBB (ว่าง = ตามธีม)', '2026-11-14 06:05:28'),
('theme_bg_color', '', 'สีพื้นหลังหน้าทำงาน #RRGGBB (ว่าง = ค่าเดิม)', '2026-11-14 06:05:28'),
('theme_color', 'default', 'ธีมสีหลักของเว็บไซต์ (default, blue, emerald, rose, amber)', '2026-11-13 08:16:18'),
('theme_font', 'kanit', 'ฟอนต์หลักของเว็บ (เลือกจากรายการที่กำหนด)', '2026-11-14 06:05:28'),
('theme_radius', 'default', 'ความโค้งมุมปุ่ม/ช่องกรอก: square | default | round', '2026-11-14 06:05:28'),
('wallboard_enabled', '1', 'เปิดใช้จอสรุปงานสาธารณะ wallboard.php (1 = เปิด)', '2026-11-13 17:37:06'),
('wallboard_key', '', 'รหัสเปิดจอสรุปงาน (ว่าง = ไม่ต้องใช้รหัส) ใส่ต่อท้ายลิงก์เป็น ?k=รหัส', '2026-11-13 17:37:06'),
('warroom_refresh_seconds', '30', 'ช่วงรีเฟรชอัตโนมัติของหน้าภาพรวมงาน (วินาที)', '2026-11-13 19:47:14');

-- --------------------------------------------------------

--
-- Table structure for table `term_staff`
--

CREATE TABLE `term_staff` (
  `term_id` int UNSIGNED NOT NULL,
  `user_id` int NOT NULL,
  `queue_order` tinyint UNSIGNED NOT NULL DEFAULT '1',
  `added_by` int DEFAULT NULL,
  `added_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `term_staff`
--

INSERT INTO `term_staff` (`term_id`, `user_id`, `queue_order`, `added_by`, `added_at`) VALUES
(1, 1368, 1, 1366, '2026-04-24 09:00:00'),
(1, 1369, 2, 1366, '2026-04-25 09:00:00'),
(2, 1370, 1, 1366, '2026-10-25 09:00:00'),
(2, 1371, 2, 1366, '2026-10-26 09:00:00'),
(2, 1372, 3, 1366, '2026-10-27 09:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int NOT NULL,
  `ticket_no` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'เลขที่ใบงาน เช่น LB-2026-XXXX',
  `requester_id` int NOT NULL COMMENT 'ผู้แจ้งซ่อม',
  `category_id` int DEFAULT NULL,
  `custom_category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ระบุหมวดหมู่เองเมื่อเลือก อื่นๆ',
  `device_detail` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'รายละเอียดอุปกรณ์: ชนิด ยี่ห้อ รุ่น รหัสครุภัณฑ์',
  `quantity` int NOT NULL DEFAULT '1' COMMENT 'จำนวนอุปกรณ์ที่ชำรุด',
  `location_note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'สถานที่ / ห้อง / เลขเครื่อง',
  `contact_person` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ผู้ที่ติดต่อได้หน้างาน',
  `coordinator_teacher` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ครูผู้ประสานงาน',
  `coordinator_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เบอร์โทรครูผู้ประสานงาน',
  `room_caretaker` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ครูผู้ดูแลห้อง',
  `room_caretaker_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เบอร์โทรครูผู้ดูแลห้อง',
  `symptom` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'อาการเสียที่พบ',
  `urgency` enum('urgent','normal') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'ความเร่งด่วน: urgent=ด่วน (ต้องแก้ภายในวันนี้), normal=ปกติ',
  `urgency_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เหตุผลที่ต้องดำเนินการด่วน',
  `status` enum('pending','accepted','awaiting_intake_sign','in_progress','inspected_ready','awaiting_requester_sign','awaiting_admin_sign','disputed','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending=รอรับ, accepted=รับแล้วรอบันทึกเช็คลิสต์, awaiting_intake_sign=รอผู้แจ้งเซ็นรับทราบเช็คลิสต์, in_progress=กำลังซ่อม, inspected_ready=ตรวจซ้ำแล้วรอสตาฟเซ็น, awaiting_requester_sign=รอผู้แจ้งเซ็นรับคืน, awaiting_admin_sign=รอครูรับรอง, disputed=ผู้แจ้งไม่ยอมรับ, completed=เสร็จสมบูรณ์, cancelled=ยกเลิก',
  `assigned_to` int DEFAULT NULL COMMENT 'สตาฟ/ช่างผู้รับผิดชอบงาน',
  `queue_assigned_to` int DEFAULT NULL COMMENT 'ID สตาฟที่ถึงคิวรับงานนี้',
  `queue_expires_at` datetime DEFAULT NULL COMMENT 'เวลาหมดเขตรับงานตามคิว',
  `resolution_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'สรุปผลการซ่อม / หมายเหตุปิดงาน',
  `repair_method` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'วิธีดำเนินการ / สิ่งที่ซ่อมไปบ้าง',
  `completion_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Path รูปหลักฐานตอนปิดงาน',
  `cancel_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เหตุผลการยกเลิก',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accepted_at` datetime DEFAULT NULL COMMENT 'วันที่/เวลาที่กดรับงาน',
  `completed_at` datetime DEFAULT NULL COMMENT 'วันที่/เวลาที่ปิดงานเสร็จสิ้น',
  `has_discrepancy` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = ตรวจซ้ำแล้วพบข้อแตกต่างจากตอนรับ',
  `discrepancy_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'คำอธิบายข้อแตกต่างตอนตรวจซ้ำ',
  `sign_round` int NOT NULL DEFAULT '1' COMMENT 'รอบการเซ็นส่งคืน (+1 เมื่อ admin ส่งกลับให้แก้)',
  `stage_since` datetime DEFAULT NULL COMMENT 'เวลาที่เข้าสถานะปัจจุบัน (ใช้คำนวณกำหนดเซ็น/เตือนซ้ำ)',
  `intake_photo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'รูปรวมอุปกรณ์ตอนรับ (private storage)',
  `intake_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'หมายเหตุรวมตอนรับอุปกรณ์',
  `return_note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'หมายเหตุรวมตอนตรวจซ้ำก่อนส่งคืน',
  `return_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เหตุผลล่าสุดที่ admin ส่งกลับ/ผู้แจ้งโต้แย้ง'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_no`, `requester_id`, `category_id`, `custom_category`, `device_detail`, `quantity`, `location_note`, `contact_person`, `coordinator_teacher`, `coordinator_phone`, `room_caretaker`, `room_caretaker_phone`, `symptom`, `urgency`, `urgency_reason`, `status`, `assigned_to`, `queue_assigned_to`, `queue_expires_at`, `resolution_summary`, `repair_method`, `completion_image`, `cancel_reason`, `created_at`, `accepted_at`, `completed_at`, `has_discrepancy`, `discrepancy_note`, `sign_round`, `stage_since`, `intake_photo`, `intake_note`, `return_note`, `return_reason`) VALUES
(6997, 'LB-2026-B1F77221', 9, 1, NULL, '1', 1, '221', 'สุทธิรรักษ์ ภูธร (0812761358)', 'gg', '0123456789', 'ff', '0123456789', 'test', 'urgent', 'gg', 'accepted', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-13 16:52:36', '2026-11-14 05:52:42', NULL, 0, NULL, 1, '2026-11-14 05:52:42', NULL, NULL, NULL, NULL),
(9777, 'DM-2026-0001', 1430, 6, 'กล้องวงจรปิด', 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-217-8245', 3, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-31', 'ผู้แจ้ง (0874029461)', 'ครูสมชาย วงศ์ใหญ่', '0871634793', NULL, NULL, 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9777_done.jpg', NULL, '2026-05-15 10:44:18', '2026-05-18 08:40:00', '2026-05-21 11:44:59', 0, NULL, 1, '2026-05-21 11:44:59', NULL, NULL, NULL, NULL),
(9778, 'DM-2026-0002', 1431, 1, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-788-5390', 1, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-30', 'ผู้แจ้ง (0842228513)', 'ครูณรงค์ศักดิ์ บุญมา', '0864502545', 'ครูสมชาย วงศ์ใหญ่', '0867129252', 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9778_done.jpg', NULL, '2026-05-06 08:35:40', '2026-05-06 09:46:08', '2026-05-07 14:46:53', 0, NULL, 1, '2026-05-07 14:46:53', NULL, NULL, NULL, NULL),
(9779, 'DM-2026-0003', 1429, 4, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-915-0903', 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-30', 'ผู้แจ้ง (0853268680)', 'ครูวิไลวรรณ ศรีสุข', '0824081621', NULL, NULL, 'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9779_done.jpg', NULL, '2026-05-11 08:40:00', '2026-05-12 09:25:59', '2026-05-14 08:53:02', 0, NULL, 1, '2026-05-14 08:53:02', NULL, NULL, NULL, NULL),
(9780, 'DM-2026-0004', 1409, 1, NULL, 'Acer Veriton X รหัสครุภัณฑ์ 7440-172-5373', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-03', 'ผู้แจ้ง (0874452398)', 'ครูวิไลวรรณ ศรีสุข', '0877039934', NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9780_done.jpg', NULL, '2026-05-18 13:49:36', '2026-05-19 10:53:18', '2026-05-21 12:50:59', 0, NULL, 1, '2026-05-21 12:50:59', NULL, NULL, NULL, NULL),
(9781, 'DM-2026-0005', 1388, 1, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-179-0993', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-04', 'ผู้แจ้ง (0858586882)', 'ครูวิไลวรรณ ศรีสุข', '0853778920', 'ครูสมชาย วงศ์ใหญ่', '0895027193', 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9781_done.jpg', NULL, '2026-05-26 08:39:43', '2026-05-26 11:41:04', '2026-05-28 09:30:23', 0, NULL, 1, '2026-05-28 09:30:23', NULL, NULL, NULL, NULL),
(9782, 'DM-2026-0006', 1408, 1, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-854-3367', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-24', 'ผู้แจ้ง (0884266660)', 'ครูวิไลวรรณ ศรีสุข', '0839073502', NULL, NULL, 'พัดลมเครื่องเสียงดังผิดปกติ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9782_done.jpg', NULL, '2026-05-25 09:23:24', '2026-05-25 16:11:15', '2026-05-28 08:43:37', 0, NULL, 1, '2026-05-28 08:43:37', NULL, NULL, NULL, NULL),
(9783, 'DM-2026-0007', 1417, 2, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-357-7717', 5, 'อาคาร 4 ห้อง 412 เครื่อง PC-03', 'ผู้แจ้ง (0877808584)', 'ครูประภาพร ทองดี', '0880726506', 'ครูสมชาย วงศ์ใหญ่', '0878317309', 'พัดลมเครื่องเสียงดังผิดปกติ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9783_done.jpg', NULL, '2026-05-05 09:32:33', '2026-05-06 09:03:25', '2026-05-11 08:40:00', 0, NULL, 1, '2026-05-11 08:40:00', NULL, NULL, NULL, NULL),
(9784, 'DM-2026-0008', 1411, 6, 'ระบบเสียงห้องประชุม', 'Acer Veriton X รหัสครุภัณฑ์ 7440-900-3479', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-24', 'ผู้แจ้ง (0819780906)', NULL, NULL, 'ครูณรงค์ศักดิ์ บุญมา', '0876698489', 'เครื่องพิมพ์กระดาษติดและพิมพ์ไม่ออก', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9784_done.jpg', NULL, '2026-05-04 08:40:00', '2026-05-04 10:06:35', '2026-05-05 13:35:53', 1, 'สายไฟ / อะแดปเตอร์ ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-05-05 13:35:53', NULL, NULL, NULL, NULL),
(9785, 'DM-2026-0009', 1418, 4, NULL, NULL, 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-02', 'ผู้แจ้ง (0843882846)', NULL, NULL, NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'แจ้งผิดรายการ', '2026-05-20 15:08:33', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9786, 'DM-2026-0010', 1399, 4, NULL, NULL, 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-01', 'ผู้แจ้ง (0876458661)', 'ครูวิไลวรรณ ศรีสุข', '0832967898', NULL, NULL, 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9786_done.jpg', NULL, '2026-05-12 15:54:59', '2026-05-13 09:15:57', '2026-05-18 08:40:00', 0, NULL, 1, '2026-05-18 08:40:00', NULL, NULL, NULL, NULL),
(9787, 'DM-2026-0011', 1408, 4, NULL, 'Acer Veriton X รหัสครุภัณฑ์ 7440-928-2861', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-06', 'ผู้แจ้ง (0847973856)', 'ครูประภาพร ทองดี', '0821957781', 'ครูวิไลวรรณ ศรีสุข', '0887861632', 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9787_done.jpg', NULL, '2026-05-22 09:29:43', '2026-05-25 08:40:00', '2026-05-28 08:42:16', 0, NULL, 1, '2026-05-28 08:42:16', NULL, NULL, NULL, NULL),
(9788, 'DM-2026-0012', 1399, 1, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-302-3219', 3, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-08', 'ผู้แจ้ง (0851676998)', 'ครูเบญจมาศ แก้วมณี', '0838782789', NULL, NULL, 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9788_done.jpg', NULL, '2026-05-25 08:40:00', '2026-05-26 09:09:37', '2026-06-01 08:40:00', 0, NULL, 1, '2026-06-01 08:40:00', NULL, NULL, NULL, NULL),
(9789, 'DM-2026-0013', 1411, 4, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-781-1318', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-03', 'ผู้แจ้ง (0894484209)', 'ครูเบญจมาศ แก้วมณี', '0849174048', NULL, NULL, 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9789_done.jpg', NULL, '2026-05-11 08:40:00', '2026-05-11 09:34:37', '2026-05-11 13:17:33', 0, NULL, 1, '2026-05-11 13:17:33', NULL, NULL, NULL, NULL),
(9790, 'DM-2026-0014', 1415, 5, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-703-5860', 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-22', 'ผู้แจ้ง (0890332939)', 'ครูณรงค์ศักดิ์ บุญมา', '0881685274', 'ครูสมชาย วงศ์ใหญ่', '0869450850', 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'completed', 1368, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9790_done.jpg', NULL, '2026-05-21 09:23:36', '2026-05-21 10:16:08', '2026-05-22 09:22:00', 0, NULL, 1, '2026-05-22 09:22:00', NULL, NULL, NULL, NULL),
(9791, 'DM-2026-0015', 1378, 1, NULL, NULL, 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-18', 'ผู้แจ้ง (0868818544)', 'ครูวิไลวรรณ ศรีสุข', '0824459897', 'ครูประภาพร ทองดี', '0864143791', 'เครื่องพิมพ์กระดาษติดและพิมพ์ไม่ออก', 'urgent', 'มีการประชุมผู้บริหารช่วงบ่าย', 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9791_done.jpg', NULL, '2026-05-08 12:31:41', '2026-05-08 13:31:16', '2026-05-11 08:40:00', 0, NULL, 1, '2026-05-11 08:40:00', NULL, NULL, NULL, NULL),
(9792, 'DM-2026-0016', 1426, 2, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-614-1810', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-23', 'ผู้แจ้ง (0897670715)', 'ครูเบญจมาศ แก้วมณี', '0895080110', NULL, NULL, 'พัดลมเครื่องเสียงดังผิดปกติ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9792_done.jpg', NULL, '2026-05-08 14:53:08', '2026-05-11 08:40:00', '2026-05-15 09:10:54', 0, NULL, 1, '2026-05-15 09:10:54', NULL, NULL, NULL, NULL),
(9793, 'DM-2026-0017', 1376, 5, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-528-2119', 1, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-15', 'ผู้แจ้ง (0890893305)', 'ครูสมชาย วงศ์ใหญ่', '0877587446', NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9793_done.jpg', NULL, '2026-05-06 08:36:27', '2026-05-07 08:43:08', '2026-05-11 08:40:00', 0, NULL, 1, '2026-05-11 08:40:00', NULL, NULL, NULL, NULL),
(9794, 'DM-2026-0018', 1376, 3, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-322-1025', 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-13', 'ผู้แจ้ง (0866556423)', NULL, NULL, NULL, NULL, 'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9794_done.jpg', NULL, '2026-05-08 09:16:36', '2026-05-11 08:40:00', '2026-05-18 08:40:00', 0, NULL, 1, '2026-05-18 08:40:00', NULL, NULL, NULL, NULL),
(9795, 'DM-2026-0019', 1407, 6, 'กล้องวงจรปิด', NULL, 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-02', 'ผู้แจ้ง (0810528817)', 'ครูเบญจมาศ แก้วมณี', '0889283988', NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9795_done.jpg', NULL, '2026-05-11 08:40:00', '2026-05-12 09:21:28', '2026-05-18 08:40:00', 0, NULL, 1, '2026-05-18 08:40:00', NULL, NULL, NULL, NULL),
(9796, 'DM-2026-0020', 1392, 6, 'กล้องวงจรปิด', 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-985-7568', 6, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-03', 'ผู้แจ้ง (0826327057)', 'ครูสมชาย วงศ์ใหญ่', '0871827595', NULL, NULL, 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9796_done.jpg', NULL, '2026-05-14 09:28:36', '2026-05-15 09:22:06', '2026-05-19 14:17:01', 0, NULL, 1, '2026-05-19 14:17:01', NULL, NULL, NULL, NULL),
(9797, 'DM-2026-0021', 1390, 3, NULL, NULL, 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-16', 'ผู้แจ้ง (0811385447)', 'ครูณรงค์ศักดิ์ บุญมา', '0815929941', 'ครูสมชาย วงศ์ใหญ่', '0844701400', 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9797_done.jpg', NULL, '2026-05-22 08:58:14', '2026-05-22 11:38:29', '2026-05-26 09:30:41', 0, NULL, 1, '2026-05-26 09:30:41', NULL, NULL, NULL, NULL),
(9798, 'DM-2026-0022', 1430, 4, NULL, 'Acer Veriton X รหัสครุภัณฑ์ 7440-420-0725', 1, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-33', 'ผู้แจ้ง (0867337615)', 'ครูเบญจมาศ แก้วมณี', '0891309857', NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9798_done.jpg', NULL, '2026-05-18 08:40:00', '2026-05-18 15:11:50', '2026-05-25 08:40:00', 0, NULL, 1, '2026-05-25 08:40:00', NULL, NULL, NULL, NULL),
(9799, 'DM-2026-0023', 1385, 1, NULL, NULL, 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-07', 'ผู้แจ้ง (0829450418)', 'ครูเบญจมาศ แก้วมณี', '0894146693', NULL, NULL, 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'แจ้งผิดรายการ', '2026-05-12 09:23:27', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9800, 'DM-2026-0024', 1416, 6, 'กล้องวงจรปิด', 'Canon G3010 รหัสครุภัณฑ์ 7440-406-0856', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-01', 'ผู้แจ้ง (0843038579)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0866712993', 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9800_done.jpg', NULL, '2026-05-15 09:34:29', '2026-05-15 09:47:15', '2026-05-18 08:40:00', 0, NULL, 1, '2026-05-18 08:40:00', NULL, NULL, NULL, NULL),
(9801, 'DM-2026-0025', 1390, 1, NULL, NULL, 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-29', 'ผู้แจ้ง (0889039077)', NULL, NULL, NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9801_done.jpg', NULL, '2026-05-14 11:18:19', '2026-05-15 08:48:06', '2026-05-19 08:56:53', 0, NULL, 1, '2026-05-19 08:56:53', NULL, NULL, NULL, NULL),
(9802, 'DM-2026-0026', 1417, 1, NULL, NULL, 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-20', 'ผู้แจ้ง (0837617841)', 'ครูเบญจมาศ แก้วมณี', '0811778402', NULL, NULL, 'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9802_done.jpg', NULL, '2026-05-11 09:09:11', '2026-05-11 10:28:39', '2026-05-15 09:12:19', 0, NULL, 1, '2026-05-15 09:12:19', NULL, NULL, NULL, NULL),
(9803, 'DM-2026-0027', 1409, 4, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-460-2191', 1, 'อาคาร 2 ห้อง 204 เครื่อง PC-15', 'ผู้แจ้ง (0866693727)', 'ครูณรงค์ศักดิ์ บุญมา', '0834001040', 'ครูสมชาย วงศ์ใหญ่', '0842363155', 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ล้างหัวพิมพ์และนำกระดาษที่ติดออก', 'ticket-private/dm_9803_done.jpg', NULL, '2026-05-19 09:13:44', '2026-05-19 16:08:42', '2026-05-22 08:47:23', 0, NULL, 1, '2026-05-22 08:47:23', NULL, NULL, NULL, NULL),
(9804, 'DM-2026-0028', 1404, 4, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-863-3084', 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-04', 'ผู้แจ้ง (0836497892)', 'ครูณรงค์ศักดิ์ บุญมา', '0845283018', 'ครูสมชาย วงศ์ใหญ่', '0893714295', 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'เปลี่ยนเมาส์ใหม่จากสต็อก', 'ticket-private/dm_9804_done.jpg', NULL, '2026-06-15 08:40:00', '2026-06-16 09:07:30', '2026-06-18 15:44:48', 0, NULL, 1, '2026-06-18 15:44:48', NULL, NULL, NULL, NULL),
(9805, 'DM-2026-0029', 1417, 1, NULL, NULL, 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-31', 'ผู้แจ้ง (0870477255)', NULL, NULL, NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'แจ้งซ้ำกับใบงานอื่น', '2026-06-18 09:07:14', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9806, 'DM-2026-0030', 1382, 4, NULL, NULL, 1, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-05', 'ผู้แจ้ง (0818588597)', 'ครูวิไลวรรณ ศรีสุข', '0898631495', 'ครูประภาพร ทองดี', '0897525033', 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9806_done.jpg', NULL, '2026-06-15 08:40:00', '2026-06-16 08:41:27', '2026-06-18 08:45:45', 1, 'อะแดปเตอร์ไฟ ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-06-18 08:45:45', NULL, NULL, NULL, NULL),
(9807, 'DM-2026-0031', 1431, 3, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-606-5780', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-08', 'ผู้แจ้ง (0887186865)', 'ครูวิไลวรรณ ศรีสุข', '0819708135', NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9807_done.jpg', NULL, '2026-06-11 08:56:33', '2026-06-12 09:29:54', '2026-06-16 08:43:43', 0, NULL, 1, '2026-06-16 08:43:43', NULL, NULL, NULL, NULL),
(9808, 'DM-2026-0032', 1391, 3, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-713-7325', 5, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-18', 'ผู้แจ้ง (0861519976)', NULL, NULL, NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนเมาส์ใหม่จากสต็อก', 'ticket-private/dm_9808_done.jpg', NULL, '2026-06-22 08:40:00', '2026-06-23 08:54:12', '2026-06-25 12:37:45', 0, NULL, 1, '2026-06-25 12:37:45', NULL, NULL, NULL, NULL),
(9809, 'DM-2026-0033', 1375, 6, 'ระบบเสียงห้องประชุม', 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-571-8060', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-14', 'ผู้แจ้ง (0896443785)', 'ครูเบญจมาศ แก้วมณี', '0887614559', 'ครูเบญจมาศ แก้วมณี', '0888574969', 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'เปลี่ยนเมาส์ใหม่จากสต็อก', 'ticket-private/dm_9809_done.jpg', NULL, '2026-06-15 08:40:00', '2026-06-16 08:57:59', '2026-06-19 08:41:46', 0, NULL, 1, '2026-06-19 08:41:46', NULL, NULL, NULL, NULL),
(9810, 'DM-2026-0034', 1399, 1, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-769-9721', 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-34', 'ผู้แจ้ง (0844699752)', 'ครูสมชาย วงศ์ใหญ่', '0848863017', NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9810_done.jpg', NULL, '2026-06-15 08:54:50', '2026-06-15 09:56:36', '2026-06-17 12:33:33', 0, NULL, 1, '2026-06-17 12:33:33', NULL, NULL, NULL, NULL),
(9811, 'DM-2026-0035', 1383, 5, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-368-0311', 1, 'อาคาร 2 ห้อง 204 เครื่อง PC-02', 'ผู้แจ้ง (0829994493)', 'ครูเบญจมาศ แก้วมณี', '0889847969', 'ครูประภาพร ทองดี', '0893316782', 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9811_done.jpg', NULL, '2026-06-23 14:37:58', '2026-06-24 09:03:09', '2026-07-01 09:17:12', 0, NULL, 1, '2026-07-01 09:17:12', NULL, NULL, NULL, NULL),
(9812, 'DM-2026-0036', 1387, 1, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-696-6466', 1, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-06', 'ผู้แจ้ง (0892119235)', 'ครูประภาพร ทองดี', '0824392866', 'ครูเบญจมาศ แก้วมณี', '0879853816', 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9812_done.jpg', NULL, '2026-06-29 08:40:00', '2026-06-30 09:02:45', '2026-07-01 12:15:47', 0, NULL, 1, '2026-07-01 12:15:47', NULL, NULL, NULL, NULL),
(9813, 'DM-2026-0037', 1381, 4, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-560-1519', 1, 'อาคาร 2 ห้อง 204 เครื่อง PC-27', 'ผู้แจ้ง (0889110733)', 'ครูเบญจมาศ แก้วมณี', '0849858522', 'ครูเบญจมาศ แก้วมณี', '0863598287', 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9813_done.jpg', NULL, '2026-06-22 08:40:00', '2026-06-22 08:51:21', '2026-06-25 09:30:06', 0, NULL, 1, '2026-06-25 09:30:06', NULL, NULL, NULL, NULL),
(9814, 'DM-2026-0038', 1391, 3, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-689-7586', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-18', 'ผู้แจ้ง (0815850705)', 'ครูสมชาย วงศ์ใหญ่', '0817655703', 'ครูประภาพร ทองดี', '0829805188', 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9814_done.jpg', NULL, '2026-06-15 08:40:00', '2026-06-16 09:17:29', '2026-06-18 08:58:16', 0, NULL, 1, '2026-06-18 08:58:16', NULL, NULL, NULL, NULL),
(9815, 'DM-2026-0039', 1418, 2, NULL, NULL, 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-06', 'ผู้แจ้ง (0815249695)', NULL, NULL, 'ครูประภาพร ทองดี', '0878876100', 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'urgent', 'ครูผู้สอนใช้สอนวันนี้', 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9815_done.jpg', NULL, '2026-06-24 14:02:44', '2026-06-24 14:19:28', '2026-06-26 09:08:18', 0, NULL, 1, '2026-06-26 09:08:18', NULL, NULL, NULL, NULL),
(9816, 'DM-2026-0040', 1426, 2, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-738-5826', 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-06', 'ผู้แจ้ง (0865558364)', NULL, NULL, NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9816_done.jpg', NULL, '2026-06-24 08:40:40', '2026-06-25 09:33:03', '2026-06-30 09:25:14', 0, NULL, 1, '2026-06-30 09:25:14', NULL, NULL, NULL, NULL),
(9817, 'DM-2026-0041', 1430, 3, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-348-8063', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-17', 'ผู้แจ้ง (0829960261)', 'ครูวิไลวรรณ ศรีสุข', '0898990053', NULL, NULL, 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9817_done.jpg', NULL, '2026-06-29 08:40:00', '2026-06-30 09:07:50', '2026-07-06 08:40:00', 0, NULL, 1, '2026-07-06 08:40:00', NULL, NULL, NULL, NULL),
(9818, 'DM-2026-0042', 1429, 2, NULL, NULL, 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-18', 'ผู้แจ้ง (0857533855)', 'ครูประภาพร ทองดี', '0898113519', 'ครูวิไลวรรณ ศรีสุข', '0859401917', 'พัดลมเครื่องเสียงดังผิดปกติ', 'urgent', 'มีการประชุมผู้บริหารช่วงบ่าย', 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนเมาส์ใหม่จากสต็อก', 'ticket-private/dm_9818_done.jpg', NULL, '2026-06-16 09:08:37', '2026-06-16 09:45:44', '2026-06-17 08:55:21', 0, NULL, 1, '2026-06-17 08:55:21', NULL, NULL, NULL, NULL),
(9819, 'DM-2026-0043', 1387, 2, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-206-5205', 5, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-16', 'ผู้แจ้ง (0871990483)', 'ครูสมชาย วงศ์ใหญ่', '0868926983', NULL, NULL, 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'แจ้งผิดรายการ', '2026-06-24 10:30:57', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9820, 'DM-2026-0044', 1418, 1, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-589-8647', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-35', 'ผู้แจ้ง (0830276569)', 'ครูสมชาย วงศ์ใหญ่', '0889667441', NULL, NULL, 'พัดลมเครื่องเสียงดังผิดปกติ', 'urgent', 'มีการประชุมผู้บริหารช่วงบ่าย', 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9820_done.jpg', NULL, '2026-06-22 08:40:00', '2026-06-22 08:56:06', '2026-06-24 08:52:10', 0, NULL, 1, '2026-06-24 08:52:10', NULL, NULL, NULL, NULL),
(9821, 'DM-2026-0045', 1407, 5, NULL, NULL, 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-01', 'ผู้แจ้ง (0843042094)', NULL, NULL, NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9821_done.jpg', NULL, '2026-06-17 13:59:19', '2026-06-18 09:00:06', '2026-06-22 08:40:00', 0, NULL, 1, '2026-06-22 08:40:00', NULL, NULL, NULL, NULL),
(9822, 'DM-2026-0046', 1407, 6, 'แท็บเล็ตห้องเรียน', NULL, 5, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-35', 'ผู้แจ้ง (0825269542)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0874960068', 'โปรแกรม Microsoft Office ใช้งานไม่ได้ แจ้งลิขสิทธิ์หมดอายุ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9822_done.jpg', NULL, '2026-06-09 13:29:32', '2026-06-10 08:59:54', '2026-06-11 09:13:46', 0, NULL, 1, '2026-06-11 09:13:46', NULL, NULL, NULL, NULL),
(9823, 'DM-2026-0047', 1414, 3, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-505-3462', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-03', 'ผู้แจ้ง (0846597121)', 'ครูสมชาย วงศ์ใหญ่', '0812101794', 'ครูวิไลวรรณ ศรีสุข', '0845293887', 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9823_done.jpg', NULL, '2026-06-09 09:03:43', '2026-06-10 08:44:08', '2026-06-15 08:40:00', 0, NULL, 1, '2026-06-15 08:40:00', NULL, NULL, NULL, NULL),
(9824, 'DM-2026-0048', 1390, 2, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-911-6817', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-14', 'ผู้แจ้ง (0860149625)', 'ครูวิไลวรรณ ศรีสุข', '0873907733', NULL, NULL, 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9824_done.jpg', NULL, '2026-06-18 16:23:37', '2026-06-19 08:55:28', '2026-06-23 08:52:46', 0, NULL, 1, '2026-06-23 08:52:46', NULL, NULL, NULL, NULL),
(9825, 'DM-2026-0049', 1390, 4, NULL, NULL, 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-05', 'ผู้แจ้ง (0888004723)', NULL, NULL, NULL, NULL, 'โปรแกรม Microsoft Office ใช้งานไม่ได้ แจ้งลิขสิทธิ์หมดอายุ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9825_done.jpg', NULL, '2026-06-15 09:19:45', '2026-06-16 08:43:11', '2026-06-22 08:40:00', 0, NULL, 1, '2026-06-22 08:40:00', NULL, NULL, NULL, NULL),
(9826, 'DM-2026-0050', 1408, 3, NULL, NULL, 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-09', 'ผู้แจ้ง (0863224143)', NULL, NULL, NULL, NULL, 'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9826_done.jpg', NULL, '2026-06-04 08:57:25', '2026-06-05 08:50:19', '2026-06-08 08:40:00', 0, NULL, 1, '2026-06-08 08:40:00', NULL, NULL, NULL, NULL),
(9827, 'DM-2026-0051', 1399, 6, 'ปลั๊กไฟและรางไฟ', 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-106-6230', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-18', 'ผู้แจ้ง (0892619160)', NULL, NULL, 'ครูประภาพร ทองดี', '0813271338', 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9827_done.jpg', NULL, '2026-06-16 09:14:38', '2026-06-17 08:57:59', '2026-06-22 08:40:00', 0, NULL, 1, '2026-06-22 08:40:00', NULL, NULL, NULL, NULL),
(9828, 'DM-2026-0052', 1391, 3, NULL, NULL, 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-22', 'ผู้แจ้ง (0855557739)', 'ครูณรงค์ศักดิ์ บุญมา', '0810729277', NULL, NULL, 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ล้างหัวพิมพ์และนำกระดาษที่ติดออก', 'ticket-private/dm_9828_done.jpg', NULL, '2026-06-15 08:40:00', '2026-06-15 09:07:17', '2026-06-17 09:25:03', 0, NULL, 1, '2026-06-17 09:25:03', NULL, NULL, NULL, NULL),
(9829, 'DM-2026-0053', 1383, 2, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-490-6446', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-38', 'ผู้แจ้ง (0829091514)', 'ครูสมชาย วงศ์ใหญ่', '0886676867', NULL, NULL, 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9829_done.jpg', NULL, '2026-06-08 08:40:00', '2026-06-08 14:22:31', '2026-06-11 09:17:21', 0, NULL, 1, '2026-06-11 09:17:21', NULL, NULL, NULL, NULL),
(9830, 'DM-2026-0054', 1382, 5, NULL, NULL, 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-26', 'ผู้แจ้ง (0857799018)', NULL, NULL, NULL, NULL, 'พัดลมเครื่องเสียงดังผิดปกติ', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9830_done.jpg', NULL, '2026-06-10 09:06:43', '2026-06-10 09:39:01', '2026-06-11 08:35:35', 0, NULL, 1, '2026-06-11 08:35:35', NULL, NULL, NULL, NULL),
(9831, 'DM-2026-0055', 1379, 5, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-143-0527', 5, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-33', 'ผู้แจ้ง (0864274139)', NULL, NULL, NULL, NULL, 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9831_done.jpg', NULL, '2026-06-15 08:40:00', '2026-06-16 09:10:58', '2026-06-18 10:57:48', 1, 'สายไฟ / อะแดปเตอร์ ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-06-18 10:57:48', NULL, NULL, NULL, NULL),
(9832, 'DM-2026-0056', 1429, 3, NULL, NULL, 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-20', 'ผู้แจ้ง (0847018959)', NULL, NULL, 'ครูสมชาย วงศ์ใหญ่', '0830618190', 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9832_done.jpg', NULL, '2026-07-06 08:40:00', '2026-07-06 15:24:14', '2026-07-09 08:43:23', 0, NULL, 1, '2026-07-09 08:43:23', NULL, NULL, NULL, NULL),
(9833, 'DM-2026-0057', 1394, 4, NULL, NULL, 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-20', 'ผู้แจ้ง (0812426083)', NULL, NULL, NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9833_done.jpg', NULL, '2026-07-21 09:12:16', '2026-07-21 11:49:48', '2026-07-23 09:12:17', 0, NULL, 1, '2026-07-23 09:12:17', NULL, NULL, NULL, NULL),
(9834, 'DM-2026-0058', 1389, 2, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-922-7545', 6, 'อาคาร 4 ห้อง 412 เครื่อง PC-32', 'ผู้แจ้ง (0897956906)', NULL, NULL, 'ครูสมชาย วงศ์ใหญ่', '0889791021', 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9834_done.jpg', NULL, '2026-07-08 08:44:24', '2026-07-09 08:48:47', '2026-07-13 08:40:00', 0, NULL, 1, '2026-07-13 08:40:00', NULL, NULL, NULL, NULL),
(9835, 'DM-2026-0059', 1375, 5, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-290-2951', 3, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-26', 'ผู้แจ้ง (0833924093)', 'ครูวิไลวรรณ ศรีสุข', '0850022730', 'ครูเบญจมาศ แก้วมณี', '0890454953', 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9835_done.jpg', NULL, '2026-07-10 09:08:00', '2026-07-13 08:40:00', '2026-07-15 09:21:29', 0, NULL, 1, '2026-07-15 09:21:29', NULL, NULL, NULL, NULL),
(9836, 'DM-2026-0060', 1418, 3, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-622-2242', 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-09', 'ผู้แจ้ง (0836501776)', NULL, NULL, NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9836_done.jpg', NULL, '2026-07-06 08:40:00', '2026-07-06 12:26:40', '2026-07-08 09:32:31', 0, NULL, 1, '2026-07-08 09:32:31', NULL, NULL, NULL, NULL),
(9837, 'DM-2026-0061', 1403, 4, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-913-6366', 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-33', 'ผู้แจ้ง (0814247355)', NULL, NULL, NULL, NULL, 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9837_done.jpg', NULL, '2026-07-06 08:40:00', '2026-07-06 14:57:52', '2026-07-09 08:52:54', 0, NULL, 1, '2026-07-09 08:52:54', NULL, NULL, NULL, NULL),
(9838, 'DM-2026-0062', 1387, 6, 'ระบบเสียงห้องประชุม', 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-854-4660', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-19', 'ผู้แจ้ง (0874230272)', NULL, NULL, NULL, NULL, 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนเมาส์ใหม่จากสต็อก', 'ticket-private/dm_9838_done.jpg', NULL, '2026-07-06 08:40:00', '2026-07-07 08:59:22', '2026-07-10 09:12:06', 0, NULL, 1, '2026-07-10 09:12:06', NULL, NULL, NULL, NULL),
(9839, 'DM-2026-0063', 1375, 4, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-892-0758', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-39', 'ผู้แจ้ง (0818417565)', NULL, NULL, NULL, NULL, 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9839_done.jpg', NULL, '2026-07-27 08:40:00', '2026-07-28 08:46:24', '2026-08-03 08:40:00', 0, NULL, 1, '2026-08-03 08:40:00', NULL, NULL, NULL, NULL),
(9840, 'DM-2026-0064', 1431, 4, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-143-6213', 5, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-24', 'ผู้แจ้ง (0898274520)', 'ครูวิไลวรรณ ศรีสุข', '0854765694', NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ล้างหัวพิมพ์และนำกระดาษที่ติดออก', 'ticket-private/dm_9840_done.jpg', NULL, '2026-07-06 08:40:00', '2026-07-06 13:03:26', '2026-07-09 09:05:42', 0, NULL, 1, '2026-07-09 09:05:42', NULL, NULL, NULL, NULL),
(9841, 'DM-2026-0065', 1409, 6, 'ปลั๊กไฟและรางไฟ', 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-596-6853', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-25', 'ผู้แจ้ง (0849800951)', NULL, NULL, NULL, NULL, 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'ปัญหาหายแล้ว ไม่ต้องซ่อม', '2026-07-17 08:46:57', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9842, 'DM-2026-0066', 1427, 3, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-630-3742', 1, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-24', 'ผู้แจ้ง (0836890403)', 'ครูณรงค์ศักดิ์ บุญมา', '0892077110', NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9842_done.jpg', NULL, '2026-07-27 10:53:00', '2026-07-27 16:19:37', '2026-07-29 08:40:47', 0, NULL, 1, '2026-07-29 08:40:47', NULL, NULL, NULL, NULL),
(9843, 'DM-2026-0067', 1422, 4, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-291-1510', 5, 'อาคาร 2 ห้อง 204 เครื่อง PC-16', 'ผู้แจ้ง (0816781605)', 'ครูเบญจมาศ แก้วมณี', '0831424210', NULL, NULL, 'โปรแกรม Microsoft Office ใช้งานไม่ได้ แจ้งลิขสิทธิ์หมดอายุ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9843_done.jpg', NULL, '2026-07-06 08:40:00', '2026-07-06 16:12:46', '2026-07-08 14:38:58', 0, NULL, 1, '2026-07-08 14:38:58', NULL, NULL, NULL, NULL),
(9844, 'DM-2026-0068', 1376, 1, NULL, 'Acer Veriton X รหัสครุภัณฑ์ 7440-151-1211', 6, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-32', 'ผู้แจ้ง (0849985650)', NULL, NULL, 'ครูประภาพร ทองดี', '0893625915', 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9844_done.jpg', NULL, '2026-07-31 09:06:00', '2026-08-03 08:40:00', '2026-08-06 09:34:24', 0, NULL, 1, '2026-08-06 09:34:24', NULL, NULL, NULL, NULL),
(9845, 'DM-2026-0069', 1382, 1, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-668-7839', 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-02', 'ผู้แจ้ง (0884891850)', 'ครูเบญจมาศ แก้วมณี', '0826989362', 'ครูเบญจมาศ แก้วมณี', '0869243660', 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9845_done.jpg', NULL, '2026-07-23 09:33:11', '2026-07-24 08:35:17', '2026-07-29 08:38:42', 0, NULL, 1, '2026-07-29 08:38:42', NULL, NULL, NULL, NULL),
(9846, 'DM-2026-0070', 1391, 5, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-465-9115', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-18', 'ผู้แจ้ง (0867878773)', 'ครูเบญจมาศ แก้วมณี', '0871799007', 'ครูณรงค์ศักดิ์ บุญมา', '0894996812', 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9846_done.jpg', NULL, '2026-07-29 08:57:01', '2026-07-30 08:53:02', '2026-07-31 11:47:44', 0, NULL, 1, '2026-07-31 11:47:44', NULL, NULL, NULL, NULL),
(9847, 'DM-2026-0071', 1425, 4, NULL, NULL, 5, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-06', 'ผู้แจ้ง (0820651920)', 'ครูเบญจมาศ แก้วมณี', '0888207711', NULL, NULL, 'โปรแกรม Microsoft Office ใช้งานไม่ได้ แจ้งลิขสิทธิ์หมดอายุ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9847_done.jpg', NULL, '2026-07-27 08:40:00', '2026-07-28 09:07:23', '2026-08-03 08:40:00', 0, NULL, 1, '2026-08-03 08:40:00', NULL, NULL, NULL, NULL),
(9848, 'DM-2026-0072', 1422, 2, NULL, NULL, 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-39', 'ผู้แจ้ง (0862877695)', 'ครูเบญจมาศ แก้วมณี', '0895539008', 'ครูเบญจมาศ แก้วมณี', '0825888388', 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9848_done.jpg', NULL, '2026-07-31 09:05:55', '2026-08-03 08:40:00', '2026-08-06 11:01:50', 0, NULL, 1, '2026-08-06 11:01:50', NULL, NULL, NULL, NULL),
(9849, 'DM-2026-0073', 1399, 2, NULL, NULL, 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-04', 'ผู้แจ้ง (0834783448)', 'ครูประภาพร ทองดี', '0812328213', NULL, NULL, 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9849_done.jpg', NULL, '2026-07-27 08:40:00', '2026-07-28 08:47:23', '2026-07-29 14:18:27', 0, NULL, 1, '2026-07-29 14:18:27', NULL, NULL, NULL, NULL),
(9850, 'DM-2026-0074', 1429, 3, NULL, NULL, 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-17', 'ผู้แจ้ง (0814176388)', NULL, NULL, NULL, NULL, 'พัดลมเครื่องเสียงดังผิดปกติ', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9850_done.jpg', NULL, '2026-07-09 09:23:05', '2026-07-09 09:58:04', '2026-07-10 14:02:05', 0, NULL, 1, '2026-07-10 14:02:05', NULL, NULL, NULL, NULL),
(9851, 'DM-2026-0075', 1383, 2, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-572-5921', 1, 'อาคาร 2 ห้อง 204 เครื่อง PC-37', 'ผู้แจ้ง (0890563229)', 'ครูเบญจมาศ แก้วมณี', '0894645894', 'ครูเบญจมาศ แก้วมณี', '0815238363', 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9851_done.jpg', NULL, '2026-07-29 08:54:57', '2026-07-29 09:53:07', '2026-07-30 08:55:20', 0, NULL, 1, '2026-07-30 08:55:20', NULL, NULL, NULL, NULL),
(9852, 'DM-2026-0076', 1416, 3, NULL, NULL, 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-19', 'ผู้แจ้ง (0848557022)', NULL, NULL, 'ครูสมชาย วงศ์ใหญ่', '0878818190', 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'ปัญหาหายแล้ว ไม่ต้องซ่อม', '2026-07-22 10:19:55', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9853, 'DM-2026-0077', 1413, 4, NULL, NULL, 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-16', 'ผู้แจ้ง (0852635079)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0847925854', 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9853_done.jpg', NULL, '2026-08-25 12:57:43', '2026-08-26 09:09:50', '2026-08-31 08:40:00', 0, NULL, 1, '2026-08-31 08:40:00', NULL, NULL, NULL, NULL),
(9854, 'DM-2026-0078', 1404, 4, NULL, NULL, 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-03', 'ผู้แจ้ง (0892219981)', NULL, NULL, NULL, NULL, 'เครื่องพิมพ์กระดาษติดและพิมพ์ไม่ออก', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'เปลี่ยนเมาส์ใหม่จากสต็อก', 'ticket-private/dm_9854_done.jpg', NULL, '2026-08-27 13:39:50', '2026-08-28 08:52:33', '2026-09-02 09:05:18', 0, NULL, 1, '2026-09-02 09:05:18', NULL, NULL, NULL, NULL),
(9855, 'DM-2026-0079', 1396, 4, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-978-1538', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-20', 'ผู้แจ้ง (0831460475)', 'ครูณรงค์ศักดิ์ บุญมา', '0816403964', NULL, NULL, 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9855_done.jpg', NULL, '2026-08-17 10:46:06', '2026-08-17 12:14:06', '2026-08-19 13:37:51', 0, NULL, 1, '2026-08-19 13:37:51', NULL, NULL, NULL, NULL),
(9856, 'DM-2026-0080', 1382, 2, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-150-9268', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-15', 'ผู้แจ้ง (0821028485)', NULL, NULL, NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9856_done.jpg', NULL, '2026-08-21 08:46:52', '2026-08-24 08:40:00', '2026-08-25 13:44:48', 0, NULL, 1, '2026-08-25 13:44:48', NULL, NULL, NULL, NULL),
(9857, 'DM-2026-0081', 1426, 5, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-690-1171', 1, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-01', 'ผู้แจ้ง (0834241790)', NULL, NULL, NULL, NULL, 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9857_done.jpg', NULL, '2026-08-31 08:40:00', '2026-08-31 13:38:28', '2026-09-04 12:11:52', 0, NULL, 1, '2026-09-04 12:11:52', NULL, NULL, NULL, NULL),
(9858, 'DM-2026-0082', 1419, 5, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-266-3041', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-19', 'ผู้แจ้ง (0824281117)', 'ครูวิไลวรรณ ศรีสุข', '0858615546', NULL, NULL, 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'urgent', 'มีการประชุมผู้บริหารช่วงบ่าย', 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9858_done.jpg', NULL, '2026-08-27 09:21:01', '2026-08-27 10:01:03', '2026-08-28 10:51:20', 0, NULL, 1, '2026-08-28 10:51:20', NULL, NULL, NULL, NULL),
(9859, 'DM-2026-0083', 1391, 2, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-484-0168', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-22', 'ผู้แจ้ง (0881067953)', NULL, NULL, 'ครูณรงค์ศักดิ์ บุญมา', '0886108974', 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9859_done.jpg', NULL, '2026-08-28 09:25:37', '2026-08-31 08:40:00', '2026-09-03 09:26:56', 0, NULL, 1, '2026-09-03 09:26:56', NULL, NULL, NULL, NULL),
(9860, 'DM-2026-0084', 1421, 1, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-473-1406', 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-33', 'ผู้แจ้ง (0838913344)', NULL, NULL, 'ครูสมชาย วงศ์ใหญ่', '0888105153', 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ล้างหัวพิมพ์และนำกระดาษที่ติดออก', 'ticket-private/dm_9860_done.jpg', NULL, '2026-08-17 08:40:00', '2026-08-17 12:32:49', '2026-08-18 11:13:59', 0, NULL, 1, '2026-08-18 11:13:59', NULL, NULL, NULL, NULL),
(9861, 'DM-2026-0085', 1421, 1, NULL, NULL, 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-37', 'ผู้แจ้ง (0857925764)', 'ครูวิไลวรรณ ศรีสุข', '0877124459', 'ครูเบญจมาศ แก้วมณี', '0836032575', 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'แจ้งผิดรายการ', '2026-08-31 08:40:00', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9862, 'DM-2026-0086', 1385, 6, 'ปลั๊กไฟและรางไฟ', 'Canon G3010 รหัสครุภัณฑ์ 7440-307-6011', 6, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-31', 'ผู้แจ้ง (0843961296)', 'ครูเบญจมาศ แก้วมณี', '0863795308', 'ครูเบญจมาศ แก้วมณี', '0890138516', 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'urgent', 'มีการประชุมผู้บริหารช่วงบ่าย', 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9862_done.jpg', NULL, '2026-08-04 08:47:23', '2026-08-04 09:16:27', '2026-08-06 09:03:30', 1, 'สายไฟ / อะแดปเตอร์ ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-08-06 09:03:30', NULL, NULL, NULL, NULL),
(9863, 'DM-2026-0087', 1415, 5, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-880-5374', 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-38', 'ผู้แจ้ง (0819032300)', NULL, NULL, NULL, NULL, 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9863_done.jpg', NULL, '2026-08-12 11:35:36', '2026-08-13 08:57:18', '2026-08-17 08:40:00', 0, NULL, 1, '2026-08-17 08:40:00', NULL, NULL, NULL, NULL),
(9864, 'DM-2026-0088', 1396, 2, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-805-8727', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-34', 'ผู้แจ้ง (0842573478)', 'ครูเบญจมาศ แก้วมณี', '0865859581', 'ครูวิไลวรรณ ศรีสุข', '0846871459', 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9864_done.jpg', NULL, '2026-08-10 08:40:00', '2026-08-11 09:21:20', '2026-08-14 08:54:07', 0, NULL, 1, '2026-08-14 08:54:07', NULL, NULL, NULL, NULL),
(9865, 'DM-2026-0089', 1382, 4, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-229-7704', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-38', 'ผู้แจ้ง (0848785361)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0855623925', 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9865_done.jpg', NULL, '2026-08-05 08:57:55', '2026-08-06 08:35:49', '2026-08-10 08:40:00', 0, NULL, 1, '2026-08-10 08:40:00', NULL, NULL, NULL, NULL),
(9866, 'DM-2026-0090', 1381, 6, 'แท็บเล็ตห้องเรียน', 'Canon G3010 รหัสครุภัณฑ์ 7440-594-9700', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-10', 'ผู้แจ้ง (0886305811)', NULL, NULL, 'ครูวิไลวรรณ ศรีสุข', '0848267377', 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9866_done.jpg', NULL, '2026-08-10 08:40:00', '2026-08-10 12:26:30', '2026-08-17 08:40:00', 0, NULL, 1, '2026-08-17 08:40:00', NULL, NULL, NULL, NULL),
(9867, 'DM-2026-0091', 1409, 2, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-453-5711', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-01', 'ผู้แจ้ง (0872465033)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0844103410', 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9867_done.jpg', NULL, '2026-08-07 09:15:37', '2026-08-10 08:40:00', '2026-08-12 09:06:27', 0, NULL, 1, '2026-08-12 09:06:27', NULL, NULL, NULL, NULL),
(9868, 'DM-2026-0092', 1379, 6, 'ระบบเสียงห้องประชุม', NULL, 5, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-40', 'ผู้แจ้ง (0835247271)', NULL, NULL, NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'urgent', 'มีการประชุมผู้บริหารช่วงบ่าย', 'completed', 1369, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9868_done.jpg', NULL, '2026-08-04 15:13:59', '2026-08-04 16:11:36', '2026-08-07 08:58:03', 0, NULL, 1, '2026-08-07 08:58:03', NULL, NULL, NULL, NULL),
(9869, 'DM-2026-0093', 1431, 4, NULL, NULL, 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-34', 'ผู้แจ้ง (0897368780)', NULL, NULL, NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9869_done.jpg', NULL, '2026-08-24 08:40:00', '2026-08-24 10:44:32', '2026-08-25 13:59:59', 0, NULL, 1, '2026-08-25 13:59:59', NULL, NULL, NULL, NULL),
(9870, 'DM-2026-0094', 1396, 1, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-374-0129', 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-23', 'ผู้แจ้ง (0825131224)', 'ครูณรงค์ศักดิ์ บุญมา', '0856034372', 'ครูประภาพร ทองดี', '0890074433', 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'แจ้งซ้ำกับใบงานอื่น', '2026-08-17 08:40:00', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9871, 'DM-2026-0095', 1428, 3, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-596-0528', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-18', 'ผู้แจ้ง (0836865613)', 'ครูประภาพร ทองดี', '0828516901', NULL, NULL, 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9871_done.jpg', NULL, '2026-08-31 11:50:56', '2026-09-01 08:46:53', '2026-09-03 09:09:36', 0, NULL, 1, '2026-09-03 09:09:36', NULL, NULL, NULL, NULL);
INSERT INTO `tickets` (`id`, `ticket_no`, `requester_id`, `category_id`, `custom_category`, `device_detail`, `quantity`, `location_note`, `contact_person`, `coordinator_teacher`, `coordinator_phone`, `room_caretaker`, `room_caretaker_phone`, `symptom`, `urgency`, `urgency_reason`, `status`, `assigned_to`, `queue_assigned_to`, `queue_expires_at`, `resolution_summary`, `repair_method`, `completion_image`, `cancel_reason`, `created_at`, `accepted_at`, `completed_at`, `has_discrepancy`, `discrepancy_note`, `sign_round`, `stage_since`, `intake_photo`, `intake_note`, `return_note`, `return_reason`) VALUES
(9872, 'DM-2026-0096', 1388, 6, 'ระบบเสียงห้องประชุม', 'Canon G3010 รหัสครุภัณฑ์ 7440-387-5089', 3, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-38', 'ผู้แจ้ง (0848261901)', NULL, NULL, NULL, NULL, 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ล้างหัวพิมพ์และนำกระดาษที่ติดออก', 'ticket-private/dm_9872_done.jpg', NULL, '2026-08-31 08:40:00', '2026-09-01 08:45:30', '2026-09-03 09:17:27', 1, 'สายไฟ / อะแดปเตอร์ ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-09-03 09:17:27', NULL, NULL, NULL, NULL),
(9873, 'DM-2026-0097', 1417, 5, NULL, NULL, 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-10', 'ผู้แจ้ง (0899238268)', NULL, NULL, NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9873_done.jpg', NULL, '2026-08-06 09:18:42', '2026-08-07 09:18:55', '2026-08-11 14:31:30', 0, NULL, 1, '2026-08-11 14:31:30', NULL, NULL, NULL, NULL),
(9874, 'DM-2026-0098', 1426, 2, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-648-8285', 3, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-04', 'ผู้แจ้ง (0838273457)', 'ครูประภาพร ทองดี', '0816345776', NULL, NULL, 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9874_done.jpg', NULL, '2026-09-21 08:40:00', '2026-09-21 08:55:51', '2026-09-25 12:48:31', 0, NULL, 1, '2026-09-25 12:48:31', NULL, NULL, NULL, NULL),
(9875, 'DM-2026-0099', 1386, 4, NULL, NULL, 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-07', 'ผู้แจ้ง (0860149319)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0812798258', 'สาย HDMI ต่อแล้วภาพกระพริบ', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9875_done.jpg', NULL, '2026-09-21 08:40:00', '2026-09-21 09:40:35', '2026-09-22 09:02:18', 0, NULL, 1, '2026-09-22 09:02:18', NULL, NULL, NULL, NULL),
(9876, 'DM-2026-0100', 1374, 6, 'แท็บเล็ตห้องเรียน', 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-247-0011', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-08', 'ผู้แจ้ง (0865078155)', 'ครูประภาพร ทองดี', '0882265395', NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9876_done.jpg', NULL, '2026-09-25 08:56:30', '2026-09-25 09:45:32', '2026-09-30 09:16:36', 0, NULL, 1, '2026-09-30 09:16:36', NULL, NULL, NULL, NULL),
(9877, 'DM-2026-0101', 1392, 1, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-214-5509', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-22', 'ผู้แจ้ง (0844113346)', NULL, NULL, NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9877_done.jpg', NULL, '2026-09-04 08:57:16', '2026-09-04 15:32:33', '2026-09-08 11:21:21', 0, NULL, 1, '2026-09-08 11:21:21', NULL, NULL, NULL, NULL),
(9878, 'DM-2026-0102', 1420, 5, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-738-8148', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-34', 'ผู้แจ้ง (0856141235)', 'ครูวิไลวรรณ ศรีสุข', '0862787862', 'ครูประภาพร ทองดี', '0892992904', 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9878_done.jpg', NULL, '2026-09-28 08:40:00', '2026-09-29 08:57:24', '2026-10-01 09:05:39', 0, NULL, 1, '2026-10-01 09:05:39', NULL, NULL, NULL, NULL),
(9879, 'DM-2026-0103', 1419, 5, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-968-9089', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-05', 'ผู้แจ้ง (0811427064)', 'ครูประภาพร ทองดี', '0841800489', NULL, NULL, 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9879_done.jpg', NULL, '2026-09-07 08:40:00', '2026-09-07 15:20:43', '2026-09-09 08:59:33', 0, NULL, 1, '2026-09-09 08:59:33', NULL, NULL, NULL, NULL),
(9880, 'DM-2026-0104', 1406, 6, 'กล้องวงจรปิด', 'Canon G3010 รหัสครุภัณฑ์ 7440-617-2819', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-11', 'ผู้แจ้ง (0833898316)', NULL, NULL, NULL, NULL, 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9880_done.jpg', NULL, '2026-09-25 08:55:14', '2026-09-28 08:40:00', '2026-09-30 08:51:44', 0, NULL, 1, '2026-09-30 08:51:44', NULL, NULL, NULL, NULL),
(9881, 'DM-2026-0105', 1392, 6, 'กล้องวงจรปิด', 'Acer Veriton X รหัสครุภัณฑ์ 7440-203-5475', 1, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-40', 'ผู้แจ้ง (0835457152)', NULL, NULL, NULL, NULL, 'พัดลมเครื่องเสียงดังผิดปกติ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9881_done.jpg', NULL, '2026-09-04 08:49:36', '2026-09-04 13:54:17', '2026-09-09 08:59:25', 0, NULL, 1, '2026-09-09 08:59:25', NULL, NULL, NULL, NULL),
(9882, 'DM-2026-0106', 1391, 3, NULL, NULL, 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-27', 'ผู้แจ้ง (0819078610)', 'ครูณรงค์ศักดิ์ บุญมา', '0827837369', 'ครูสมชาย วงศ์ใหญ่', '0857064431', 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9882_done.jpg', NULL, '2026-09-23 10:52:50', '2026-09-23 13:44:14', '2026-09-28 08:40:00', 0, NULL, 1, '2026-09-28 08:40:00', NULL, NULL, NULL, NULL),
(9883, 'DM-2026-0107', 1387, 2, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-134-6314', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-12', 'ผู้แจ้ง (0826420073)', 'ครูเบญจมาศ แก้วมณี', '0856479361', NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9883_done.jpg', NULL, '2026-09-28 08:40:00', '2026-09-29 09:05:25', '2026-10-02 11:18:22', 0, NULL, 1, '2026-10-02 11:18:22', NULL, NULL, NULL, NULL),
(9884, 'DM-2026-0108', 1428, 2, NULL, 'Acer Veriton X รหัสครุภัณฑ์ 7440-883-9501', 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-39', 'ผู้แจ้ง (0874758906)', 'ครูสมชาย วงศ์ใหญ่', '0858937661', NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9884_done.jpg', NULL, '2026-09-14 08:40:00', '2026-09-15 09:21:46', '2026-09-18 14:32:29', 1, 'สายชาร์จ / อะแดปเตอร์ ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-09-18 14:32:29', NULL, NULL, NULL, NULL),
(9885, 'DM-2026-0109', 1409, 3, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-813-3872', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-14', 'ผู้แจ้ง (0894641581)', 'ครูวิไลวรรณ ศรีสุข', '0892107164', NULL, NULL, 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9885_done.jpg', NULL, '2026-09-11 08:58:36', '2026-09-14 08:40:00', '2026-09-16 12:01:03', 0, NULL, 1, '2026-09-16 12:01:03', NULL, NULL, NULL, NULL),
(9886, 'DM-2026-0110', 1396, 5, NULL, NULL, 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-38', 'ผู้แจ้ง (0837107244)', 'ครูวิไลวรรณ ศรีสุข', '0810648569', 'ครูประภาพร ทองดี', '0847415981', 'โปรแกรม Microsoft Office ใช้งานไม่ได้ แจ้งลิขสิทธิ์หมดอายุ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9886_done.jpg', NULL, '2026-09-30 08:50:12', '2026-10-01 08:42:41', '2026-10-05 08:40:00', 0, NULL, 1, '2026-10-05 08:40:00', NULL, NULL, NULL, NULL),
(9887, 'DM-2026-0111', 1405, 3, NULL, NULL, 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-25', 'ผู้แจ้ง (0811667880)', 'ครูวิไลวรรณ ศรีสุข', '0840007145', NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9887_done.jpg', NULL, '2026-09-14 08:40:00', '2026-09-15 09:12:24', '2026-09-21 08:40:00', 0, NULL, 1, '2026-09-21 08:40:00', NULL, NULL, NULL, NULL),
(9888, 'DM-2026-0112', 1373, 1, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-605-4204', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-18', 'ผู้แจ้ง (0845218822)', 'ครูสมชาย วงศ์ใหญ่', '0818019111', 'ครูสมชาย วงศ์ใหญ่', '0876558006', 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ล้างหัวพิมพ์และนำกระดาษที่ติดออก', 'ticket-private/dm_9888_done.jpg', NULL, '2026-09-22 14:23:41', '2026-09-22 15:33:23', '2026-09-25 08:36:04', 0, NULL, 1, '2026-09-25 08:36:04', NULL, NULL, NULL, NULL),
(9889, 'DM-2026-0113', 1418, 2, NULL, NULL, 1, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-11', 'ผู้แจ้ง (0836348721)', 'ครูเบญจมาศ แก้วมณี', '0843517084', NULL, NULL, 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9889_done.jpg', NULL, '2026-09-04 09:05:06', '2026-09-07 08:40:00', '2026-09-10 08:43:19', 0, NULL, 1, '2026-09-10 08:43:19', NULL, NULL, NULL, NULL),
(9890, 'DM-2026-0114', 1382, 5, NULL, NULL, 1, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-18', 'ผู้แจ้ง (0857864319)', NULL, NULL, NULL, NULL, 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9890_done.jpg', NULL, '2026-09-22 08:52:54', '2026-09-22 09:12:17', '2026-09-24 11:01:18', 0, NULL, 1, '2026-09-24 11:01:18', NULL, NULL, NULL, NULL),
(9891, 'DM-2026-0115', 1402, 4, NULL, NULL, 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-23', 'ผู้แจ้ง (0867951650)', 'ครูวิไลวรรณ ศรีสุข', '0835031260', NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9891_done.jpg', NULL, '2026-09-30 15:50:06', '2026-10-01 09:01:31', '2026-10-06 09:04:41', 0, NULL, 1, '2026-10-06 09:04:41', NULL, NULL, NULL, NULL),
(9892, 'DM-2026-0116', 1426, 4, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-630-3549', 1, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-38', 'ผู้แจ้ง (0895751810)', 'ครูณรงค์ศักดิ์ บุญมา', '0896372309', NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9892_done.jpg', NULL, '2026-09-28 14:37:28', '2026-09-29 09:12:35', '2026-10-05 08:40:00', 0, NULL, 1, '2026-10-05 08:40:00', NULL, NULL, NULL, NULL),
(9893, 'DM-2026-0117', 1385, 1, NULL, NULL, 3, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-37', 'ผู้แจ้ง (0876169525)', 'ครูเบญจมาศ แก้วมณี', '0829541422', 'ครูสมชาย วงศ์ใหญ่', '0881756180', 'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9893_done.jpg', NULL, '2026-09-23 09:01:36', '2026-09-24 09:08:06', '2026-09-29 09:16:15', 0, NULL, 1, '2026-09-29 09:16:15', NULL, NULL, NULL, NULL),
(9894, 'DM-2026-0118', 1428, 2, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-399-2863', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-21', 'ผู้แจ้ง (0846435311)', 'ครูสมชาย วงศ์ใหญ่', '0845628833', NULL, NULL, 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ล้างหัวพิมพ์และนำกระดาษที่ติดออก', 'ticket-private/dm_9894_done.jpg', NULL, '2026-09-14 08:40:00', '2026-09-15 09:34:01', '2026-09-18 08:59:21', 0, NULL, 1, '2026-09-18 08:59:21', NULL, NULL, NULL, NULL),
(9895, 'DM-2026-0119', 1385, 4, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-138-9268', 1, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-35', 'ผู้แจ้ง (0899521551)', 'ครูประภาพร ทองดี', '0816642516', NULL, NULL, 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'urgent', 'มีการประชุมผู้บริหารช่วงบ่าย', 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9895_done.jpg', NULL, '2026-09-28 08:40:00', '2026-09-28 09:43:49', '2026-09-29 15:34:01', 0, NULL, 1, '2026-09-29 15:34:01', NULL, NULL, NULL, NULL),
(9896, 'DM-2026-0120', 1420, 3, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-190-7965', 4, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-16', 'ผู้แจ้ง (0833279856)', 'ครูณรงค์ศักดิ์ บุญมา', '0872419017', NULL, NULL, 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9896_done.jpg', NULL, '2026-09-07 08:40:00', '2026-09-08 09:29:45', '2026-09-11 15:15:53', 0, NULL, 1, '2026-09-11 15:15:53', NULL, NULL, NULL, NULL),
(9897, 'DM-2026-0121', 1399, 1, NULL, 'Acer Veriton X รหัสครุภัณฑ์ 7440-675-8287', 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-32', 'ผู้แจ้ง (0872625339)', NULL, NULL, NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9897_done.jpg', NULL, '2026-09-07 12:51:49', '2026-09-08 09:23:14', '2026-09-10 10:05:58', 0, NULL, 1, '2026-09-10 10:05:58', NULL, NULL, NULL, NULL),
(9898, 'DM-2026-0122', 1389, 5, NULL, NULL, 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-10', 'ผู้แจ้ง (0848574527)', NULL, NULL, NULL, NULL, 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9898_done.jpg', NULL, '2026-10-13 09:33:08', '2026-10-14 09:14:35', '2026-10-19 08:40:00', 0, NULL, 1, '2026-10-19 08:40:00', NULL, NULL, NULL, NULL),
(9899, 'DM-2026-0123', 1432, 6, 'ปลั๊กไฟและรางไฟ', 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-530-6053', 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-25', 'ผู้แจ้ง (0821805037)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0865100859', 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'completed', 1369, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9899_done.jpg', NULL, '2026-10-08 09:02:07', '2026-10-09 09:34:06', '2026-10-13 13:37:47', 0, NULL, 1, '2026-10-13 13:37:47', NULL, NULL, NULL, NULL),
(9900, 'DM-2026-0124', 1405, 3, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-600-7076', 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-25', 'ผู้แจ้ง (0866921729)', NULL, NULL, 'ครูวิไลวรรณ ศรีสุข', '0834332556', 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'รีเซ็ตการตั้งค่าเครือข่ายและรีสตาร์ท Access Point', 'ticket-private/dm_9900_done.jpg', NULL, '2026-10-06 09:08:24', '2026-10-07 08:41:24', '2026-10-12 08:40:00', 0, NULL, 1, '2026-10-12 08:40:00', NULL, NULL, NULL, NULL),
(9901, 'DM-2026-0125', 1423, 1, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-584-6227', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-38', 'ผู้แจ้ง (0882293761)', NULL, NULL, 'ครูณรงค์ศักดิ์ บุญมา', '0829915855', 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9901_done.jpg', NULL, '2026-10-05 08:40:00', '2026-10-05 15:19:58', '2026-10-09 09:11:38', 0, NULL, 1, '2026-10-09 09:11:38', NULL, NULL, NULL, NULL),
(9902, 'DM-2026-0126', 1406, 6, 'ระบบเสียงห้องประชุม', 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-148-9101', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-35', 'ผู้แจ้ง (0895527717)', NULL, NULL, NULL, NULL, 'สาย HDMI ต่อแล้วภาพกระพริบ', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ติดตั้งและเปิดใช้งานโปรแกรมใหม่', 'ticket-private/dm_9902_done.jpg', NULL, '2026-10-27 08:46:21', '2026-10-28 09:23:40', '2026-11-02 08:40:00', 1, 'สายไฟ / อะแดปเตอร์ ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-11-02 08:40:00', NULL, NULL, NULL, NULL),
(9903, 'DM-2026-0127', 1388, 2, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-649-4379', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-08', 'ผู้แจ้ง (0874828197)', 'ครูสมชาย วงศ์ใหญ่', '0836537221', NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9903_done.jpg', NULL, '2026-10-08 09:29:59', '2026-10-08 10:08:01', '2026-10-12 08:40:00', 0, NULL, 1, '2026-10-12 08:40:00', NULL, NULL, NULL, NULL),
(9904, 'DM-2026-0128', 1373, 2, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-783-6542', 4, 'อาคาร 4 ห้อง 412 เครื่อง PC-10', 'ผู้แจ้ง (0864241316)', NULL, NULL, NULL, NULL, 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9904_done.jpg', NULL, '2026-10-22 08:55:32', '2026-10-23 09:19:35', '2026-10-27 11:54:37', 0, NULL, 1, '2026-10-27 11:54:37', NULL, NULL, NULL, NULL),
(9905, 'DM-2026-0129', 1422, 6, 'ปลั๊กไฟและรางไฟ', 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-211-3186', 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-17', 'ผู้แจ้ง (0832898738)', 'ครูสมชาย วงศ์ใหญ่', '0864210315', NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'completed', 1369, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9905_done.jpg', NULL, '2026-10-29 14:36:16', '2026-10-29 15:43:44', '2026-11-03 10:22:48', 0, NULL, 1, '2026-11-03 10:22:48', NULL, NULL, NULL, NULL),
(9906, 'DM-2026-0130', 1386, 2, NULL, NULL, 1, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-38', 'ผู้แจ้ง (0887598134)', 'ครูประภาพร ทองดี', '0877959073', NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9906_done.jpg', NULL, '2026-10-09 09:54:49', '2026-10-12 08:40:00', '2026-10-16 10:04:22', 0, NULL, 1, '2026-10-16 10:04:22', NULL, NULL, NULL, NULL),
(9907, 'DM-2026-0131', 1377, 4, NULL, NULL, 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-39', 'ผู้แจ้ง (0862683135)', 'ครูณรงค์ศักดิ์ บุญมา', '0839228983', NULL, NULL, 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'urgent', 'ครูผู้สอนใช้สอนวันนี้', 'awaiting_intake_sign', 1370, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-02 08:40:00', '2026-11-02 09:48:40', NULL, 0, NULL, 1, '2026-11-02 09:57:17', NULL, NULL, NULL, NULL),
(9908, 'DM-2026-0132', 1423, 6, 'แท็บเล็ตห้องเรียน', 'Canon G3010 รหัสครุภัณฑ์ 7440-712-4739', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 2 เครื่อง PC-24', 'ผู้แจ้ง (0872087250)', 'ครูเบญจมาศ แก้วมณี', '0850214668', 'ครูวิไลวรรณ ศรีสุข', '0870591330', 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'completed', 1368, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9908_done.jpg', NULL, '2026-10-26 08:40:00', '2026-10-27 09:33:38', '2026-11-02 08:40:00', 1, 'สายไฟ / อะแดปเตอร์ ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-11-02 08:40:00', NULL, NULL, NULL, NULL),
(9909, 'DM-2026-0133', 1396, 6, 'กล้องวงจรปิด', 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-569-7059', 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-03', 'ผู้แจ้ง (0814897496)', 'ครูประภาพร ทองดี', '0836862624', 'ครูวิไลวรรณ ศรีสุข', '0822635512', 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'completed', 1368, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9909_done.jpg', NULL, '2026-10-19 08:40:00', '2026-10-20 09:33:13', '2026-10-23 09:30:39', 0, NULL, 1, '2026-10-23 09:30:39', NULL, NULL, NULL, NULL),
(9910, 'DM-2026-0134', 1420, 6, 'ระบบเสียงห้องประชุม', 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-433-9760', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-09', 'ผู้แจ้ง (0856683751)', NULL, NULL, 'ครูณรงค์ศักดิ์ บุญมา', '0868962570', 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'completed', 1369, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'เปลี่ยนสาย HDMI และตั้งค่าความละเอียดจอ', 'ticket-private/dm_9910_done.jpg', NULL, '2026-10-26 08:40:00', '2026-10-27 09:30:09', '2026-11-02 08:40:00', 0, NULL, 1, '2026-11-02 08:40:00', NULL, NULL, NULL, NULL),
(9911, 'DM-2026-0135', 1388, 3, NULL, NULL, 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-36', 'ผู้แจ้ง (0888253627)', 'ครูประภาพร ทองดี', '0884862973', NULL, NULL, 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'normal', NULL, 'in_progress', 1371, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-11 15:32:45', '2026-11-12 09:15:09', NULL, 0, NULL, 1, '2026-11-12 10:03:48', NULL, NULL, NULL, NULL),
(9912, 'DM-2026-0136', 1416, 5, NULL, 'Canon G3010 รหัสครุภัณฑ์ 7440-261-0041', 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-05', 'ผู้แจ้ง (0843267893)', NULL, NULL, NULL, NULL, 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-02 14:00:08', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9913, 'DM-2026-0137', 1409, 2, NULL, NULL, 2, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-21', 'ผู้แจ้ง (0815040594)', 'ครูวิไลวรรณ ศรีสุข', '0820540280', NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'normal', NULL, 'awaiting_intake_sign', 1372, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-05 08:40:00', '2026-11-06 08:48:05', NULL, 0, NULL, 1, '2026-11-06 09:05:50', NULL, NULL, NULL, NULL),
(9914, 'DM-2026-0138', 1408, 5, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-325-4745', 2, 'อาคาร 4 ห้องพักครูแผนกไอที เครื่อง PC-40', 'ผู้แจ้ง (0883953198)', NULL, NULL, NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'awaiting_admin_sign', 1371, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9914_done.jpg', NULL, '2026-11-03 09:10:54', '2026-11-03 11:56:52', NULL, 0, NULL, 1, '2026-11-04 11:52:33', NULL, NULL, NULL, NULL),
(9915, 'DM-2026-0139', 1395, 6, 'ระบบเสียงห้องประชุม', NULL, 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-34', 'ผู้แจ้ง (0826944194)', 'ครูวิไลวรรณ ศรีสุข', '0879482085', NULL, NULL, 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'completed', 1370, NULL, NULL, 'เรียบร้อย', 'ขยับสาย', 'ticket-private/done_2544e00de0f55811d079ddc54de3d459.jpg', NULL, '2026-11-02 13:32:52', '2026-11-14 16:13:09', '2026-11-14 16:22:42', 0, NULL, 1, '2026-11-14 16:22:42', NULL, NULL, NULL, NULL),
(9916, 'DM-2026-0140', 1380, 5, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-725-9192', 1, 'อาคาร 2 ห้อง 204 เครื่อง PC-18', 'ผู้แจ้ง (0884104881)', NULL, NULL, 'ครูสมชาย วงศ์ใหญ่', '0872171100', 'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'แจ้งผิดรายการ', '2026-11-09 08:40:00', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9917, 'DM-2026-0141', 1375, 3, NULL, NULL, 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-26', 'ผู้แจ้ง (0890129224)', 'ครูวิไลวรรณ ศรีสุข', '0872253596', 'ครูเบญจมาศ แก้วมณี', '0823281173', 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'normal', NULL, 'in_progress', 1372, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-12 09:41:25', '2026-11-13 08:36:51', NULL, 0, NULL, 1, '2026-11-13 09:02:49', NULL, NULL, NULL, NULL),
(9918, 'DM-2026-0142', 1396, 3, NULL, 'HP ProDesk 400 รหัสครุภัณฑ์ 7440-817-8660', 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-30', 'ผู้แจ้ง (0897604379)', 'ครูณรงค์ศักดิ์ บุญมา', '0816688667', NULL, NULL, 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'normal', NULL, 'awaiting_intake_sign', 1371, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-10 09:09:33', '2026-11-11 09:21:04', NULL, 0, NULL, 1, '2026-11-11 09:33:09', NULL, NULL, NULL, NULL),
(9919, 'DM-2026-0143', 1378, 6, 'ระบบเสียงห้องประชุม', 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-894-7839', 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-16', 'ผู้แจ้ง (0854719832)', NULL, NULL, 'ครูณรงค์ศักดิ์ บุญมา', '0827799016', 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'completed', 1370, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9919_done.jpg', NULL, '2026-11-02 08:40:00', '2026-11-02 09:37:03', '2026-11-04 08:58:23', 0, NULL, 1, '2026-11-04 08:58:23', NULL, NULL, NULL, NULL),
(9920, 'DM-2026-0144', 1392, 6, 'ระบบเสียงห้องประชุม', 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-550-2064', 5, 'อาคาร 3 ห้องสมุด เครื่อง PC-07', 'ผู้แจ้ง (0875336053)', 'ครูณรงค์ศักดิ์ บุญมา', '0893574974', NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'แจ้งผิดรายการ', '2026-11-09 08:40:00', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9921, 'DM-2026-0145', 1412, 1, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-768-8143', 1, 'อาคาร 2 ห้อง 204 เครื่อง PC-21', 'ผู้แจ้ง (0847482021)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0876125024', 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'accepted', 1366, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-13 13:14:41', '2026-11-14 16:50:19', NULL, 0, NULL, 1, '2026-11-14 16:50:19', NULL, NULL, NULL, NULL),
(9922, 'DM-2026-0146', 1388, 4, NULL, NULL, 1, 'อาคาร 2 ห้อง 204 เครื่อง PC-12', 'ผู้แจ้ง (0825301671)', NULL, NULL, NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'urgent', 'ครูผู้สอนใช้สอนวันนี้', 'pending', NULL, 1370, '2026-11-14 18:01:27', NULL, NULL, NULL, NULL, '2026-11-03 13:50:40', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9923, 'DM-2026-0147', 1410, 3, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-908-2412', 1, 'อาคาร 2 ห้อง 204 เครื่อง PC-37', 'ผู้แจ้ง (0889137312)', 'ครูสมชาย วงศ์ใหญ่', '0897586130', 'ครูวิไลวรรณ ศรีสุข', '0811532540', 'โปรเจกเตอร์ภาพไม่ขึ้น ขึ้น No Signal', 'normal', NULL, 'awaiting_admin_sign', 1370, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ตรวจสอบสายไฟและพาวเวอร์ซัพพลาย เปลี่ยนพาวเวอร์ซัพพลายใหม่', 'ticket-private/dm_9923_done.jpg', NULL, '2026-11-04 09:01:27', '2026-11-05 09:11:39', NULL, 0, NULL, 1, '2026-11-06 14:06:54', NULL, NULL, NULL, NULL),
(9924, 'DM-2026-0148', 1431, 1, NULL, NULL, 1, 'อาคารอำนวยการ ห้องทะเบียน เครื่อง PC-36', 'ผู้แจ้ง (0823721269)', 'ครูสมชาย วงศ์ใหญ่', '0879786884', 'ครูประภาพร ทองดี', '0838977130', 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-10 08:43:57', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9925, 'DM-2026-0149', 1385, 6, 'แท็บเล็ตห้องเรียน', NULL, 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-37', 'ผู้แจ้ง (0879644896)', 'ครูวิไลวรรณ ศรีสุข', '0817056621', NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'in_progress', 1370, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-04 09:29:57', '2026-11-04 14:14:03', NULL, 0, NULL, 1, '2026-11-04 15:36:57', NULL, NULL, NULL, NULL),
(9926, 'DM-2026-0150', 1392, 5, NULL, 'Epson EB-X51 รหัสครุภัณฑ์ 7440-345-7839', 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-01', 'ผู้แจ้ง (0839008279)', NULL, NULL, 'ครูเบญจมาศ แก้วมณี', '0891468588', 'ลำโพงห้องเรียนไม่มีเสียง', 'normal', NULL, 'cancelled', NULL, NULL, NULL, NULL, NULL, NULL, 'ปัญหาหายแล้ว ไม่ต้องซ่อม', '2026-11-04 09:08:30', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9927, 'DM-2026-0151', 1388, 5, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-281-2970', 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-37', 'ผู้แจ้ง (0880081782)', NULL, NULL, NULL, NULL, 'คีย์บอร์ดพิมพ์บางปุ่มไม่ติด', 'normal', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-02 08:40:00', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9928, 'DM-2026-0152', 1386, 1, NULL, 'TP-Link Archer C6 รหัสครุภัณฑ์ 7440-712-7338', 1, 'อาคารอำนวยการ ห้องธุรการ เครื่อง PC-38', 'ผู้แจ้ง (0826978640)', 'ครูวิไลวรรณ ศรีสุข', '0897325722', 'ครูวิไลวรรณ ศรีสุข', '0867856079', 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'in_progress', 1370, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-12 14:22:56', '2026-11-13 09:33:37', NULL, 0, NULL, 1, '2026-11-13 10:47:07', NULL, NULL, NULL, NULL),
(9929, 'DM-2026-0153', 1408, 2, NULL, NULL, 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-13', 'ผู้แจ้ง (0845031289)', NULL, NULL, NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1370, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เปลี่ยนเมาส์ใหม่จากสต็อก', 'ticket-private/dm_9929_done.jpg', NULL, '2026-11-06 09:10:51', '2026-11-06 10:29:26', '2026-11-09 08:40:00', 1, 'กระเป๋า ไม่พบตอนส่งคืน ผู้แจ้งแจ้งว่าอาจลืมไว้ที่ห้อง', 1, '2026-11-09 08:40:00', NULL, NULL, NULL, NULL),
(9930, 'DM-2026-0154', 1391, 5, NULL, 'Dell OptiPlex 3080 รหัสครุภัณฑ์ 7440-437-9620', 1, 'อาคาร 3 ห้องสมุด เครื่อง PC-28', 'ผู้แจ้ง (0873554532)', 'ครูณรงค์ศักดิ์ บุญมา', '0837959185', 'ครูณรงค์ศักดิ์ บุญมา', '0857067262', 'นาฬิกาเครื่องรีเซ็ตทุกครั้งที่เปิด', 'normal', NULL, 'in_progress', 1370, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-05 09:11:51', '2026-11-06 08:52:22', NULL, 0, NULL, 1, '2026-11-06 09:51:56', NULL, NULL, NULL, NULL),
(9931, 'DM-2026-0155', 1403, 3, NULL, NULL, 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-25', 'ผู้แจ้ง (0855188289)', 'ครูณรงค์ศักดิ์ บุญมา', '0811994397', NULL, NULL, 'โปรแกรม Microsoft Office ใช้งานไม่ได้ แจ้งลิขสิทธิ์หมดอายุ', 'normal', NULL, 'completed', 1371, NULL, NULL, 'ใช้งานได้ปกติแล้ว', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9931_done.jpg', NULL, '2026-11-05 09:18:38', '2026-11-06 08:43:56', '2026-11-10 09:32:07', 0, NULL, 1, '2026-11-10 09:32:07', NULL, NULL, NULL, NULL),
(9932, 'DM-2026-0156', 1430, 5, NULL, 'Lenovo ThinkCentre M70 รหัสครุภัณฑ์ 7440-429-4258', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-22', 'ผู้แจ้ง (0896450790)', 'ครูเบญจมาศ แก้วมณี', '0826175386', NULL, NULL, 'โปรแกรม Microsoft Office ใช้งานไม่ได้ แจ้งลิขสิทธิ์หมดอายุ', 'normal', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-11 09:59:54', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9933, 'DM-2026-0157', 1402, 5, NULL, 'Acer Veriton X รหัสครุภัณฑ์ 7440-302-5631', 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-31', 'ผู้แจ้ง (0827616961)', NULL, NULL, NULL, NULL, 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'urgent', 'งานทะเบียนต้องพิมพ์เอกสารวันนี้', 'completed', 1370, NULL, NULL, 'เปลี่ยนอุปกรณ์ใหม่ ใช้งานได้ปกติ', 'เข้าหัวสาย RJ45 ใหม่ และทดสอบสัญญาณ', 'ticket-private/dm_9933_done.jpg', NULL, '2026-11-12 09:05:15', '2026-11-12 10:34:06', '2026-11-13 09:07:00', 0, NULL, 1, '2026-11-13 09:07:00', NULL, NULL, NULL, NULL),
(9934, 'DM-2026-0158', 1417, 4, NULL, NULL, 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-07', 'ผู้แจ้ง (0837041888)', 'ครูเบญจมาศ แก้วมณี', '0842374513', NULL, NULL, 'อินเทอร์เน็ตหลุดบ่อย เชื่อมต่อ LAN ไม่ได้', 'normal', NULL, 'completed', 1370, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'เปลี่ยนเมาส์ใหม่จากสต็อก', 'ticket-private/dm_9934_done.jpg', NULL, '2026-11-14 10:49:23', '2026-11-14 11:09:23', '2026-11-14 13:19:23', 0, NULL, 1, '2026-11-14 13:19:23', NULL, NULL, NULL, NULL),
(9935, 'DM-2026-0159', 1388, 5, NULL, NULL, 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-08', 'ผู้แจ้ง (0865868485)', 'ครูวิไลวรรณ ศรีสุข', '0813939795', NULL, NULL, 'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'completed', 1371, NULL, NULL, 'แก้ไขเรียบร้อย แนะนำให้ปิดเครื่องหลังใช้งาน', 'ทำความสะอาดฝุ่น ทาซิลิโคนใหม่ อัปเดตไดรเวอร์', 'ticket-private/dm_9935_done.jpg', NULL, '2026-11-14 11:29:23', '2026-11-14 11:39:23', '2026-11-14 13:49:23', 0, NULL, 1, '2026-11-14 13:49:23', NULL, NULL, NULL, NULL),
(9936, 'DM-2026-0160', 1427, 2, NULL, NULL, 1, 'อาคาร 2 ห้อง 305 เครื่อง PC-30', 'ผู้แจ้ง (0835521265)', 'ครูประภาพร ทองดี', '0887568542', NULL, NULL, 'คอมพิวเตอร์เปิดไม่ติด ไฟเข้าแต่จอดำ', 'normal', NULL, 'awaiting_admin_sign', 1372, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนถ่านไบออสและตั้งเวลาใหม่', 'ticket-private/dm_9936_done.jpg', NULL, '2026-11-14 11:49:23', '2026-11-14 12:04:23', NULL, 0, NULL, 1, '2026-11-14 15:19:48', NULL, NULL, NULL, NULL),
(9937, 'DM-2026-0161', 1391, 4, NULL, NULL, 1, 'อาคาร 4 ห้อง 412 เครื่อง PC-22', 'ผู้แจ้ง (0826572934)', 'ครูประภาพร ทองดี', '0879667746', NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'awaiting_requester_sign', 1370, NULL, NULL, 'ผู้แจ้งตรวจสอบแล้วใช้งานได้', 'ตรวจสอบไดรเวอร์เสียงและสายลำโพง', 'ticket-private/dm_9937_done.jpg', NULL, '2026-11-14 11:59:23', '2026-11-14 12:14:23', NULL, 0, NULL, 1, '2026-11-14 15:18:03', NULL, NULL, NULL, NULL),
(9938, 'DM-2026-0162', 1379, 3, NULL, NULL, 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-03', 'ผู้แจ้ง (0823847008)', 'ครูณรงค์ศักดิ์ บุญมา', '0829972110', NULL, NULL, 'Wi-Fi ห้องเรียนสัญญาณอ่อนมาก', 'normal', NULL, 'disputed', 1371, NULL, NULL, 'ทดสอบเชื่อมต่อเรียบร้อย', 'เปลี่ยนคีย์บอร์ดใหม่จากสต็อก', 'ticket-private/dm_9938_done.jpg', NULL, '2026-11-14 09:09:23', '2026-11-14 09:29:23', NULL, 0, NULL, 1, '2026-11-14 15:38:35', NULL, NULL, NULL, 'อุปกรณ์ยังมีอาการเดิมบางครั้ง ขอให้ตรวจอีกครั้ง'),
(9939, 'DM-2026-0163', 1432, 3, NULL, NULL, 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-20', 'ผู้แจ้ง (0819809935)', 'ครูประภาพร ทองดี', '0886208549', NULL, NULL, 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'urgent', 'ครูผู้สอนใช้สอนวันนี้', 'in_progress', 1372, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-14 12:49:23', '2026-11-14 13:09:23', NULL, 0, NULL, 1, '2026-11-14 14:25:35', NULL, NULL, NULL, NULL),
(9940, 'DM-2026-0164', 1377, 5, NULL, NULL, 1, 'อาคาร 3 ห้องประชุมใหญ่ เครื่อง PC-21', 'ผู้แจ้ง (0830065124)', 'ครูณรงค์ศักดิ์ บุญมา', '0844259225', NULL, NULL, 'เครื่องค้างบ่อยและรีสตาร์ทเอง', 'normal', NULL, 'awaiting_intake_sign', 1370, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-14 13:49:23', '2026-11-14 14:09:23', NULL, 0, NULL, 1, '2026-11-14 14:18:40', NULL, NULL, NULL, NULL),
(9941, 'DM-2026-0165', 1395, 2, NULL, NULL, 1, 'อาคาร 5 ห้องปฏิบัติการไฟฟ้า เครื่อง PC-35', 'ผู้แจ้ง (0893209468)', 'ครูสมชาย วงศ์ใหญ่', '0817516471', NULL, NULL, 'เมาส์ขยับไม่ได้ เปลี่ยนพอร์ตแล้วยังเหมือนเดิม', 'normal', NULL, 'accepted', 1371, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-14 15:09:23', '2026-11-14 15:19:23', NULL, 0, NULL, 1, '2026-11-14 15:19:23', NULL, NULL, NULL, NULL),
(9942, 'DM-2026-0166', 1391, 3, NULL, NULL, 1, 'อาคาร 1 ห้องปฏิบัติการคอมพิวเตอร์ 1 เครื่อง PC-38', 'ผู้แจ้ง (0880199994)', 'ครูประภาพร ทองดี', '0840578593', NULL, NULL, 'เครื่องช้ามาก เปิดโปรแกรมนาน', 'urgent', 'ใช้สอบปฏิบัติคาบถัดไป', 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-14 15:04:23', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9943, 'DM-2026-0167', 1393, 1, NULL, NULL, 1, 'อาคาร 6 ห้องปฏิบัติการช่างยนต์ เครื่อง PC-07', 'ผู้แจ้ง (0885350269)', 'ครูสมชาย วงศ์ใหญ่', '0856716155', NULL, NULL, 'จอฟ้าขึ้น error ตอนเปิดเครื่อง', 'normal', NULL, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-14 15:34:23', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL),
(9944, 'LB-2026-DE0C598C', 1419, 1, NULL, 'dell x526', 1, 'BC2', 'สุภาวดี ศรีวงศ์ (0840775408)', 'กิต', '0626642778', NULL, NULL, 'มีเสียงดัง', 'urgent', 'ใช้ในคาบถัดไป', 'pending', NULL, 1371, '2026-11-14 17:51:46', NULL, NULL, NULL, NULL, '2026-11-14 16:01:30', NULL, NULL, 0, NULL, 1, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_assignees`
--

CREATE TABLE `ticket_assignees` (
  `ticket_id` int NOT NULL COMMENT 'ใบงาน',
  `user_id` int NOT NULL COMMENT 'สตาฟผู้ช่วย',
  `added_by` int DEFAULT NULL COMMENT 'admin ผู้มอบหมาย',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ผู้ช่วยในใบงาน (ทำงานร่วมกับช่างหลัก)';

--
-- Dumping data for table `ticket_assignees`
--

INSERT INTO `ticket_assignees` (`ticket_id`, `user_id`, `added_by`, `created_at`) VALUES
(9790, 1369, 1366, '2026-05-21 10:26:08'),
(9791, 1368, 1366, '2026-05-08 13:41:16'),
(9796, 1369, 1367, '2026-05-15 09:32:06'),
(9809, 1368, 1367, '2026-06-16 09:07:59'),
(9810, 1368, 1367, '2026-06-15 10:06:36'),
(9816, 1368, 1366, '2026-06-25 09:43:03'),
(9821, 1368, 1367, '2026-06-18 09:10:06'),
(9836, 1369, 1367, '2026-07-06 12:36:40'),
(9842, 1369, 1366, '2026-07-27 16:29:37'),
(9843, 1368, 1367, '2026-07-06 16:22:46'),
(9844, 1368, 1367, '2026-08-03 08:50:00'),
(9849, 1369, 1367, '2026-07-28 08:57:23'),
(9854, 1368, 1366, '2026-08-28 09:02:33'),
(9868, 1368, 1367, '2026-08-04 16:21:36'),
(9885, 1369, 1367, '2026-09-14 08:50:00'),
(9890, 1369, 1366, '2026-09-22 09:22:17'),
(9896, 1369, 1366, '2026-09-08 09:39:45'),
(9898, 1368, 1366, '2026-10-14 09:24:35'),
(9917, 1370, 1366, '2026-11-13 08:46:51'),
(9917, 1371, 1366, '2026-11-13 08:46:51'),
(9922, 1371, 1366, '2026-11-14 16:55:21'),
(9935, 1370, 1366, '2026-11-14 11:40:23'),
(9935, 1372, 1366, '2026-11-14 11:40:23'),
(9937, 1372, 1367, '2026-11-14 12:15:23');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_attachments`
--

CREATE TABLE `ticket_attachments` (
  `id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_attachments`
--

INSERT INTO `ticket_attachments` (`id`, `ticket_id`, `file_path`, `uploaded_at`) VALUES
(5677, 9777, 'ticket-private/dm_9777_a1.jpg', '2026-05-15 10:44:18'),
(5678, 9778, 'ticket-private/dm_9778_a1.jpg', '2026-05-06 08:35:40'),
(5679, 9778, 'ticket-private/dm_9778_a2.jpg', '2026-05-06 08:35:40'),
(5680, 9779, 'ticket-private/dm_9779_a1.jpg', '2026-05-11 08:40:00'),
(5681, 9780, 'ticket-private/dm_9780_a1.jpg', '2026-05-18 13:49:36'),
(5682, 9781, 'ticket-private/dm_9781_a1.jpg', '2026-05-26 08:39:43'),
(5683, 9781, 'ticket-private/dm_9781_a2.jpg', '2026-05-26 08:39:43'),
(5684, 9782, 'ticket-private/dm_9782_a1.jpg', '2026-05-25 09:23:24'),
(5685, 9782, 'ticket-private/dm_9782_a2.jpg', '2026-05-25 09:23:24'),
(5686, 9783, 'ticket-private/dm_9783_a1.jpg', '2026-05-05 09:32:33'),
(5687, 9783, 'ticket-private/dm_9783_a2.jpg', '2026-05-05 09:32:33'),
(5688, 9784, 'ticket-private/dm_9784_a1.jpg', '2026-05-04 08:40:00'),
(5689, 9785, 'ticket-private/dm_9785_a1.jpg', '2026-05-20 15:08:33'),
(5690, 9785, 'ticket-private/dm_9785_a2.jpg', '2026-05-20 15:08:33'),
(5691, 9786, 'ticket-private/dm_9786_a1.jpg', '2026-05-12 15:54:59'),
(5692, 9787, 'ticket-private/dm_9787_a1.jpg', '2026-05-22 09:29:43'),
(5693, 9788, 'ticket-private/dm_9788_a1.jpg', '2026-05-25 08:40:00'),
(5694, 9789, 'ticket-private/dm_9789_a1.jpg', '2026-05-11 08:40:00'),
(5695, 9789, 'ticket-private/dm_9789_a2.jpg', '2026-05-11 08:40:00'),
(5696, 9790, 'ticket-private/dm_9790_a1.jpg', '2026-05-21 09:23:36'),
(5697, 9791, 'ticket-private/dm_9791_a1.jpg', '2026-05-08 12:31:41'),
(5698, 9792, 'ticket-private/dm_9792_a1.jpg', '2026-05-08 14:53:08'),
(5699, 9792, 'ticket-private/dm_9792_a2.jpg', '2026-05-08 14:53:08'),
(5700, 9793, 'ticket-private/dm_9793_a1.jpg', '2026-05-06 08:36:27'),
(5701, 9793, 'ticket-private/dm_9793_a2.jpg', '2026-05-06 08:36:27'),
(5702, 9794, 'ticket-private/dm_9794_a1.jpg', '2026-05-08 09:16:36'),
(5703, 9795, 'ticket-private/dm_9795_a1.jpg', '2026-05-11 08:40:00'),
(5704, 9796, 'ticket-private/dm_9796_a1.jpg', '2026-05-14 09:28:36'),
(5705, 9797, 'ticket-private/dm_9797_a1.jpg', '2026-05-22 08:58:14'),
(5706, 9798, 'ticket-private/dm_9798_a1.jpg', '2026-05-18 08:40:00'),
(5707, 9798, 'ticket-private/dm_9798_a2.jpg', '2026-05-18 08:40:00'),
(5708, 9799, 'ticket-private/dm_9799_a1.jpg', '2026-05-12 09:23:27'),
(5709, 9799, 'ticket-private/dm_9799_a2.jpg', '2026-05-12 09:23:27'),
(5710, 9800, 'ticket-private/dm_9800_a1.jpg', '2026-05-15 09:34:29'),
(5711, 9801, 'ticket-private/dm_9801_a1.jpg', '2026-05-14 11:18:19'),
(5712, 9801, 'ticket-private/dm_9801_a2.jpg', '2026-05-14 11:18:19'),
(5713, 9802, 'ticket-private/dm_9802_a1.jpg', '2026-05-11 09:09:11'),
(5714, 9803, 'ticket-private/dm_9803_a1.jpg', '2026-05-19 09:13:44'),
(5715, 9803, 'ticket-private/dm_9803_a2.jpg', '2026-05-19 09:13:44'),
(5716, 9804, 'ticket-private/dm_9804_a1.jpg', '2026-06-15 08:40:00'),
(5717, 9805, 'ticket-private/dm_9805_a1.jpg', '2026-06-18 09:07:14'),
(5718, 9806, 'ticket-private/dm_9806_a1.jpg', '2026-06-15 08:40:00'),
(5719, 9806, 'ticket-private/dm_9806_a2.jpg', '2026-06-15 08:40:00'),
(5720, 9807, 'ticket-private/dm_9807_a1.jpg', '2026-06-11 08:56:33'),
(5721, 9808, 'ticket-private/dm_9808_a1.jpg', '2026-06-22 08:40:00'),
(5722, 9808, 'ticket-private/dm_9808_a2.jpg', '2026-06-22 08:40:00'),
(5723, 9809, 'ticket-private/dm_9809_a1.jpg', '2026-06-15 08:40:00'),
(5724, 9809, 'ticket-private/dm_9809_a2.jpg', '2026-06-15 08:40:00'),
(5725, 9810, 'ticket-private/dm_9810_a1.jpg', '2026-06-15 08:54:50'),
(5726, 9811, 'ticket-private/dm_9811_a1.jpg', '2026-06-23 14:37:58'),
(5727, 9811, 'ticket-private/dm_9811_a2.jpg', '2026-06-23 14:37:58'),
(5728, 9812, 'ticket-private/dm_9812_a1.jpg', '2026-06-29 08:40:00'),
(5729, 9813, 'ticket-private/dm_9813_a1.jpg', '2026-06-22 08:40:00'),
(5730, 9814, 'ticket-private/dm_9814_a1.jpg', '2026-06-15 08:40:00'),
(5731, 9815, 'ticket-private/dm_9815_a1.jpg', '2026-06-24 14:02:44'),
(5732, 9816, 'ticket-private/dm_9816_a1.jpg', '2026-06-24 08:40:40'),
(5733, 9817, 'ticket-private/dm_9817_a1.jpg', '2026-06-29 08:40:00'),
(5734, 9817, 'ticket-private/dm_9817_a2.jpg', '2026-06-29 08:40:00'),
(5735, 9818, 'ticket-private/dm_9818_a1.jpg', '2026-06-16 09:08:37'),
(5736, 9818, 'ticket-private/dm_9818_a2.jpg', '2026-06-16 09:08:37'),
(5737, 9819, 'ticket-private/dm_9819_a1.jpg', '2026-06-24 10:30:57'),
(5738, 9819, 'ticket-private/dm_9819_a2.jpg', '2026-06-24 10:30:57'),
(5739, 9820, 'ticket-private/dm_9820_a1.jpg', '2026-06-22 08:40:00'),
(5740, 9821, 'ticket-private/dm_9821_a1.jpg', '2026-06-17 13:59:19'),
(5741, 9821, 'ticket-private/dm_9821_a2.jpg', '2026-06-17 13:59:19'),
(5742, 9822, 'ticket-private/dm_9822_a1.jpg', '2026-06-09 13:29:32'),
(5743, 9822, 'ticket-private/dm_9822_a2.jpg', '2026-06-09 13:29:32'),
(5744, 9823, 'ticket-private/dm_9823_a1.jpg', '2026-06-09 09:03:43'),
(5745, 9823, 'ticket-private/dm_9823_a2.jpg', '2026-06-09 09:03:43'),
(5746, 9824, 'ticket-private/dm_9824_a1.jpg', '2026-06-18 16:23:37'),
(5747, 9825, 'ticket-private/dm_9825_a1.jpg', '2026-06-15 09:19:45'),
(5748, 9826, 'ticket-private/dm_9826_a1.jpg', '2026-06-04 08:57:25'),
(5749, 9827, 'ticket-private/dm_9827_a1.jpg', '2026-06-16 09:14:38'),
(5750, 9828, 'ticket-private/dm_9828_a1.jpg', '2026-06-15 08:40:00'),
(5751, 9829, 'ticket-private/dm_9829_a1.jpg', '2026-06-08 08:40:00'),
(5752, 9830, 'ticket-private/dm_9830_a1.jpg', '2026-06-10 09:06:43'),
(5753, 9830, 'ticket-private/dm_9830_a2.jpg', '2026-06-10 09:06:43'),
(5754, 9831, 'ticket-private/dm_9831_a1.jpg', '2026-06-15 08:40:00'),
(5755, 9832, 'ticket-private/dm_9832_a1.jpg', '2026-07-06 08:40:00'),
(5756, 9832, 'ticket-private/dm_9832_a2.jpg', '2026-07-06 08:40:00'),
(5757, 9833, 'ticket-private/dm_9833_a1.jpg', '2026-07-21 09:12:16'),
(5758, 9834, 'ticket-private/dm_9834_a1.jpg', '2026-07-08 08:44:24'),
(5759, 9834, 'ticket-private/dm_9834_a2.jpg', '2026-07-08 08:44:24'),
(5760, 9835, 'ticket-private/dm_9835_a1.jpg', '2026-07-10 09:08:00'),
(5761, 9836, 'ticket-private/dm_9836_a1.jpg', '2026-07-06 08:40:00'),
(5762, 9837, 'ticket-private/dm_9837_a1.jpg', '2026-07-06 08:40:00'),
(5763, 9837, 'ticket-private/dm_9837_a2.jpg', '2026-07-06 08:40:00'),
(5764, 9838, 'ticket-private/dm_9838_a1.jpg', '2026-07-06 08:40:00'),
(5765, 9839, 'ticket-private/dm_9839_a1.jpg', '2026-07-27 08:40:00'),
(5766, 9840, 'ticket-private/dm_9840_a1.jpg', '2026-07-06 08:40:00'),
(5767, 9841, 'ticket-private/dm_9841_a1.jpg', '2026-07-17 08:46:57'),
(5768, 9842, 'ticket-private/dm_9842_a1.jpg', '2026-07-27 10:53:00'),
(5769, 9843, 'ticket-private/dm_9843_a1.jpg', '2026-07-06 08:40:00'),
(5770, 9843, 'ticket-private/dm_9843_a2.jpg', '2026-07-06 08:40:00'),
(5771, 9844, 'ticket-private/dm_9844_a1.jpg', '2026-07-31 09:06:00'),
(5772, 9845, 'ticket-private/dm_9845_a1.jpg', '2026-07-23 09:33:11'),
(5773, 9846, 'ticket-private/dm_9846_a1.jpg', '2026-07-29 08:57:01'),
(5774, 9847, 'ticket-private/dm_9847_a1.jpg', '2026-07-27 08:40:00'),
(5775, 9847, 'ticket-private/dm_9847_a2.jpg', '2026-07-27 08:40:00'),
(5776, 9848, 'ticket-private/dm_9848_a1.jpg', '2026-07-31 09:05:55'),
(5777, 9849, 'ticket-private/dm_9849_a1.jpg', '2026-07-27 08:40:00'),
(5778, 9849, 'ticket-private/dm_9849_a2.jpg', '2026-07-27 08:40:00'),
(5779, 9850, 'ticket-private/dm_9850_a1.jpg', '2026-07-09 09:23:05'),
(5780, 9850, 'ticket-private/dm_9850_a2.jpg', '2026-07-09 09:23:05'),
(5781, 9851, 'ticket-private/dm_9851_a1.jpg', '2026-07-29 08:54:57'),
(5782, 9851, 'ticket-private/dm_9851_a2.jpg', '2026-07-29 08:54:57'),
(5783, 9852, 'ticket-private/dm_9852_a1.jpg', '2026-07-22 10:19:55'),
(5784, 9852, 'ticket-private/dm_9852_a2.jpg', '2026-07-22 10:19:55'),
(5785, 9853, 'ticket-private/dm_9853_a1.jpg', '2026-08-25 12:57:43'),
(5786, 9853, 'ticket-private/dm_9853_a2.jpg', '2026-08-25 12:57:43'),
(5787, 9854, 'ticket-private/dm_9854_a1.jpg', '2026-08-27 13:39:50'),
(5788, 9854, 'ticket-private/dm_9854_a2.jpg', '2026-08-27 13:39:50'),
(5789, 9855, 'ticket-private/dm_9855_a1.jpg', '2026-08-17 10:46:06'),
(5790, 9855, 'ticket-private/dm_9855_a2.jpg', '2026-08-17 10:46:06'),
(5791, 9856, 'ticket-private/dm_9856_a1.jpg', '2026-08-21 08:46:52'),
(5792, 9857, 'ticket-private/dm_9857_a1.jpg', '2026-08-31 08:40:00'),
(5793, 9857, 'ticket-private/dm_9857_a2.jpg', '2026-08-31 08:40:00'),
(5794, 9858, 'ticket-private/dm_9858_a1.jpg', '2026-08-27 09:21:01'),
(5795, 9858, 'ticket-private/dm_9858_a2.jpg', '2026-08-27 09:21:01'),
(5796, 9859, 'ticket-private/dm_9859_a1.jpg', '2026-08-28 09:25:37'),
(5797, 9859, 'ticket-private/dm_9859_a2.jpg', '2026-08-28 09:25:37'),
(5798, 9860, 'ticket-private/dm_9860_a1.jpg', '2026-08-17 08:40:00'),
(5799, 9861, 'ticket-private/dm_9861_a1.jpg', '2026-08-31 08:40:00'),
(5800, 9862, 'ticket-private/dm_9862_a1.jpg', '2026-08-04 08:47:23'),
(5801, 9863, 'ticket-private/dm_9863_a1.jpg', '2026-08-12 11:35:36'),
(5802, 9864, 'ticket-private/dm_9864_a1.jpg', '2026-08-10 08:40:00'),
(5803, 9865, 'ticket-private/dm_9865_a1.jpg', '2026-08-05 08:57:55'),
(5804, 9866, 'ticket-private/dm_9866_a1.jpg', '2026-08-10 08:40:00'),
(5805, 9867, 'ticket-private/dm_9867_a1.jpg', '2026-08-07 09:15:37'),
(5806, 9868, 'ticket-private/dm_9868_a1.jpg', '2026-08-04 15:13:59'),
(5807, 9868, 'ticket-private/dm_9868_a2.jpg', '2026-08-04 15:13:59'),
(5808, 9869, 'ticket-private/dm_9869_a1.jpg', '2026-08-24 08:40:00'),
(5809, 9869, 'ticket-private/dm_9869_a2.jpg', '2026-08-24 08:40:00'),
(5810, 9870, 'ticket-private/dm_9870_a1.jpg', '2026-08-17 08:40:00'),
(5811, 9871, 'ticket-private/dm_9871_a1.jpg', '2026-08-31 11:50:56'),
(5812, 9872, 'ticket-private/dm_9872_a1.jpg', '2026-08-31 08:40:00'),
(5813, 9873, 'ticket-private/dm_9873_a1.jpg', '2026-08-06 09:18:42'),
(5814, 9874, 'ticket-private/dm_9874_a1.jpg', '2026-09-21 08:40:00'),
(5815, 9874, 'ticket-private/dm_9874_a2.jpg', '2026-09-21 08:40:00'),
(5816, 9875, 'ticket-private/dm_9875_a1.jpg', '2026-09-21 08:40:00'),
(5817, 9876, 'ticket-private/dm_9876_a1.jpg', '2026-09-25 08:56:30'),
(5818, 9877, 'ticket-private/dm_9877_a1.jpg', '2026-09-04 08:57:16'),
(5819, 9878, 'ticket-private/dm_9878_a1.jpg', '2026-09-28 08:40:00'),
(5820, 9879, 'ticket-private/dm_9879_a1.jpg', '2026-09-07 08:40:00'),
(5821, 9880, 'ticket-private/dm_9880_a1.jpg', '2026-09-25 08:55:14'),
(5822, 9880, 'ticket-private/dm_9880_a2.jpg', '2026-09-25 08:55:14'),
(5823, 9881, 'ticket-private/dm_9881_a1.jpg', '2026-09-04 08:49:36'),
(5824, 9881, 'ticket-private/dm_9881_a2.jpg', '2026-09-04 08:49:36'),
(5825, 9882, 'ticket-private/dm_9882_a1.jpg', '2026-09-23 10:52:50'),
(5826, 9883, 'ticket-private/dm_9883_a1.jpg', '2026-09-28 08:40:00'),
(5827, 9884, 'ticket-private/dm_9884_a1.jpg', '2026-09-14 08:40:00'),
(5828, 9885, 'ticket-private/dm_9885_a1.jpg', '2026-09-11 08:58:36'),
(5829, 9886, 'ticket-private/dm_9886_a1.jpg', '2026-09-30 08:50:12'),
(5830, 9887, 'ticket-private/dm_9887_a1.jpg', '2026-09-14 08:40:00'),
(5831, 9888, 'ticket-private/dm_9888_a1.jpg', '2026-09-22 14:23:41'),
(5832, 9889, 'ticket-private/dm_9889_a1.jpg', '2026-09-04 09:05:06'),
(5833, 9889, 'ticket-private/dm_9889_a2.jpg', '2026-09-04 09:05:06'),
(5834, 9890, 'ticket-private/dm_9890_a1.jpg', '2026-09-22 08:52:54'),
(5835, 9890, 'ticket-private/dm_9890_a2.jpg', '2026-09-22 08:52:54'),
(5836, 9891, 'ticket-private/dm_9891_a1.jpg', '2026-09-30 15:50:06'),
(5837, 9892, 'ticket-private/dm_9892_a1.jpg', '2026-09-28 14:37:28'),
(5838, 9893, 'ticket-private/dm_9893_a1.jpg', '2026-09-23 09:01:36'),
(5839, 9894, 'ticket-private/dm_9894_a1.jpg', '2026-09-14 08:40:00'),
(5840, 9895, 'ticket-private/dm_9895_a1.jpg', '2026-09-28 08:40:00'),
(5841, 9895, 'ticket-private/dm_9895_a2.jpg', '2026-09-28 08:40:00'),
(5842, 9896, 'ticket-private/dm_9896_a1.jpg', '2026-09-07 08:40:00'),
(5843, 9897, 'ticket-private/dm_9897_a1.jpg', '2026-09-07 12:51:49'),
(5844, 9898, 'ticket-private/dm_9898_a1.jpg', '2026-10-13 09:33:08'),
(5845, 9899, 'ticket-private/dm_9899_a1.jpg', '2026-10-08 09:02:07'),
(5846, 9900, 'ticket-private/dm_9900_a1.jpg', '2026-10-06 09:08:24'),
(5847, 9901, 'ticket-private/dm_9901_a1.jpg', '2026-10-05 08:40:00'),
(5848, 9901, 'ticket-private/dm_9901_a2.jpg', '2026-10-05 08:40:00'),
(5849, 9902, 'ticket-private/dm_9902_a1.jpg', '2026-10-27 08:46:21'),
(5850, 9903, 'ticket-private/dm_9903_a1.jpg', '2026-10-08 09:29:59'),
(5851, 9904, 'ticket-private/dm_9904_a1.jpg', '2026-10-22 08:55:32'),
(5852, 9904, 'ticket-private/dm_9904_a2.jpg', '2026-10-22 08:55:32'),
(5853, 9905, 'ticket-private/dm_9905_a1.jpg', '2026-10-29 14:36:16'),
(5854, 9905, 'ticket-private/dm_9905_a2.jpg', '2026-10-29 14:36:16'),
(5855, 9906, 'ticket-private/dm_9906_a1.jpg', '2026-10-09 09:54:49'),
(5856, 9907, 'ticket-private/dm_9907_a1.jpg', '2026-11-02 08:40:00'),
(5857, 9908, 'ticket-private/dm_9908_a1.jpg', '2026-10-26 08:40:00'),
(5858, 9908, 'ticket-private/dm_9908_a2.jpg', '2026-10-26 08:40:00'),
(5859, 9909, 'ticket-private/dm_9909_a1.jpg', '2026-10-19 08:40:00'),
(5860, 9909, 'ticket-private/dm_9909_a2.jpg', '2026-10-19 08:40:00'),
(5861, 9910, 'ticket-private/dm_9910_a1.jpg', '2026-10-26 08:40:00'),
(5862, 9911, 'ticket-private/dm_9911_a1.jpg', '2026-11-11 15:32:45'),
(5863, 9912, 'ticket-private/dm_9912_a1.jpg', '2026-11-02 14:00:08'),
(5864, 9913, 'ticket-private/dm_9913_a1.jpg', '2026-11-05 08:40:00'),
(5865, 9914, 'ticket-private/dm_9914_a1.jpg', '2026-11-03 09:10:54'),
(5866, 9915, 'ticket-private/dm_9915_a1.jpg', '2026-11-02 13:32:52'),
(5867, 9916, 'ticket-private/dm_9916_a1.jpg', '2026-11-09 08:40:00'),
(5868, 9916, 'ticket-private/dm_9916_a2.jpg', '2026-11-09 08:40:00'),
(5869, 9917, 'ticket-private/dm_9917_a1.jpg', '2026-11-12 09:41:25'),
(5870, 9918, 'ticket-private/dm_9918_a1.jpg', '2026-11-10 09:09:33'),
(5871, 9919, 'ticket-private/dm_9919_a1.jpg', '2026-11-02 08:40:00'),
(5872, 9919, 'ticket-private/dm_9919_a2.jpg', '2026-11-02 08:40:00'),
(5873, 9920, 'ticket-private/dm_9920_a1.jpg', '2026-11-09 08:40:00'),
(5874, 9921, 'ticket-private/dm_9921_a1.jpg', '2026-11-13 13:14:41'),
(5875, 9922, 'ticket-private/dm_9922_a1.jpg', '2026-11-03 13:50:40'),
(5876, 9922, 'ticket-private/dm_9922_a2.jpg', '2026-11-03 13:50:40'),
(5877, 9923, 'ticket-private/dm_9923_a1.jpg', '2026-11-04 09:01:27'),
(5878, 9924, 'ticket-private/dm_9924_a1.jpg', '2026-11-10 08:43:57'),
(5879, 9924, 'ticket-private/dm_9924_a2.jpg', '2026-11-10 08:43:57'),
(5880, 9925, 'ticket-private/dm_9925_a1.jpg', '2026-11-04 09:29:57'),
(5881, 9926, 'ticket-private/dm_9926_a1.jpg', '2026-11-04 09:08:30'),
(5882, 9926, 'ticket-private/dm_9926_a2.jpg', '2026-11-04 09:08:30'),
(5883, 9927, 'ticket-private/dm_9927_a1.jpg', '2026-11-02 08:40:00'),
(5884, 9928, 'ticket-private/dm_9928_a1.jpg', '2026-11-12 14:22:56'),
(5885, 9929, 'ticket-private/dm_9929_a1.jpg', '2026-11-06 09:10:51'),
(5886, 9930, 'ticket-private/dm_9930_a1.jpg', '2026-11-05 09:11:51'),
(5887, 9931, 'ticket-private/dm_9931_a1.jpg', '2026-11-05 09:18:38'),
(5888, 9932, 'ticket-private/dm_9932_a1.jpg', '2026-11-11 09:59:54'),
(5889, 9933, 'ticket-private/dm_9933_a1.jpg', '2026-11-12 09:05:15'),
(5890, 9934, 'ticket-private/dm_9934_a1.jpg', '2026-11-14 10:49:23'),
(5891, 9935, 'ticket-private/dm_9935_a1.jpg', '2026-11-14 11:29:23'),
(5892, 9936, 'ticket-private/dm_9936_a1.jpg', '2026-11-14 11:49:23'),
(5893, 9937, 'ticket-private/dm_9937_a1.jpg', '2026-11-14 11:59:23'),
(5894, 9938, 'ticket-private/dm_9938_a1.jpg', '2026-11-14 09:09:23'),
(5895, 9939, 'ticket-private/dm_9939_a1.jpg', '2026-11-14 12:49:23'),
(5896, 9940, 'ticket-private/dm_9940_a1.jpg', '2026-11-14 13:49:23'),
(5897, 9941, 'ticket-private/dm_9941_a1.jpg', '2026-11-14 15:09:23'),
(5898, 9942, 'ticket-private/dm_9942_a1.jpg', '2026-11-14 15:04:23'),
(5899, 9943, 'ticket-private/dm_9943_a1.jpg', '2026-11-14 15:34:23'),
(5900, 9944, 'ticket-private/tk_7fd161c5f75ef54b83183d6fc38e2e14.jpg', '2026-11-14 16:01:30'),
(5901, 9944, 'ticket-private/tk_c16d5c020407bcf15b49a2a0bf741eba.jpg', '2026-11-14 16:01:30');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_completion_photos`
--

CREATE TABLE `ticket_completion_photos` (
  `id` int UNSIGNED NOT NULL,
  `ticket_id` int NOT NULL,
  `round_no` int NOT NULL DEFAULT '1',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` tinyint UNSIGNED NOT NULL DEFAULT '2',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_completion_photos`
--

INSERT INTO `ticket_completion_photos` (`id`, `ticket_id`, `round_no`, `file_path`, `sort_order`, `created_by`, `created_at`) VALUES
(1, 9780, 1, 'ticket-private/dm_9780_done2.jpg', 2, 1368, '2026-05-20 10:35:52'),
(2, 9780, 1, 'ticket-private/dm_9780_done3.jpg', 3, 1368, '2026-05-20 10:35:52'),
(3, 9781, 1, 'ticket-private/dm_9781_done2.jpg', 2, 1369, '2026-05-27 09:24:04'),
(4, 9786, 1, 'ticket-private/dm_9786_done2.jpg', 2, 1369, '2026-05-15 08:48:31'),
(5, 9790, 1, 'ticket-private/dm_9790_done2.jpg', 2, 1368, '2026-05-21 13:50:59'),
(6, 9790, 1, 'ticket-private/dm_9790_done3.jpg', 3, 1368, '2026-05-21 13:50:59'),
(7, 9791, 1, 'ticket-private/dm_9791_done2.jpg', 2, 1369, '2026-05-08 14:31:34'),
(8, 9795, 1, 'ticket-private/dm_9795_done2.jpg', 2, 1369, '2026-05-14 15:04:40'),
(9, 9795, 1, 'ticket-private/dm_9795_done3.jpg', 3, 1369, '2026-05-14 15:04:40'),
(10, 9796, 1, 'ticket-private/dm_9796_done2.jpg', 2, 1368, '2026-05-18 08:40:00'),
(11, 9800, 1, 'ticket-private/dm_9800_done2.jpg', 2, 1368, '2026-05-15 11:48:16'),
(12, 9800, 1, 'ticket-private/dm_9800_done3.jpg', 3, 1368, '2026-05-15 11:48:16'),
(13, 9801, 1, 'ticket-private/dm_9801_done2.jpg', 2, 1368, '2026-05-18 08:40:00'),
(14, 9806, 1, 'ticket-private/dm_9806_done2.jpg', 2, 1369, '2026-06-17 09:27:26'),
(15, 9810, 1, 'ticket-private/dm_9810_done2.jpg', 2, 1369, '2026-06-15 13:52:44'),
(16, 9810, 1, 'ticket-private/dm_9810_done3.jpg', 3, 1369, '2026-06-15 13:52:44'),
(17, 9811, 1, 'ticket-private/dm_9811_done2.jpg', 2, 1368, '2026-06-29 08:40:00'),
(18, 9815, 1, 'ticket-private/dm_9815_done2.jpg', 2, 1368, '2026-06-25 08:39:26'),
(19, 9815, 1, 'ticket-private/dm_9815_done3.jpg', 3, 1368, '2026-06-25 08:39:26'),
(20, 9816, 1, 'ticket-private/dm_9816_done2.jpg', 2, 1369, '2026-06-29 08:40:00'),
(21, 9820, 1, 'ticket-private/dm_9820_done2.jpg', 2, 1368, '2026-06-22 12:24:27'),
(22, 9820, 1, 'ticket-private/dm_9820_done3.jpg', 3, 1368, '2026-06-22 12:24:27'),
(23, 9821, 1, 'ticket-private/dm_9821_done2.jpg', 2, 1369, '2026-06-19 09:19:11'),
(24, 9825, 1, 'ticket-private/dm_9825_done2.jpg', 2, 1369, '2026-06-19 08:42:00'),
(25, 9825, 1, 'ticket-private/dm_9825_done3.jpg', 3, 1369, '2026-06-19 08:42:00'),
(26, 9826, 1, 'ticket-private/dm_9826_done2.jpg', 2, 1369, '2026-06-05 15:04:14'),
(27, 9830, 1, 'ticket-private/dm_9830_done2.jpg', 2, 1369, '2026-06-10 12:03:31'),
(28, 9830, 1, 'ticket-private/dm_9830_done3.jpg', 3, 1369, '2026-06-10 12:03:31'),
(29, 9831, 1, 'ticket-private/dm_9831_done2.jpg', 2, 1368, '2026-06-17 08:46:31'),
(30, 9835, 1, 'ticket-private/dm_9835_done2.jpg', 2, 1368, '2026-07-13 12:19:04'),
(31, 9835, 1, 'ticket-private/dm_9835_done3.jpg', 3, 1368, '2026-07-13 12:19:04'),
(32, 9836, 1, 'ticket-private/dm_9836_done2.jpg', 2, 1368, '2026-07-07 09:08:24'),
(33, 9840, 1, 'ticket-private/dm_9840_done2.jpg', 2, 1369, '2026-07-08 08:57:32'),
(34, 9840, 1, 'ticket-private/dm_9840_done3.jpg', 3, 1369, '2026-07-08 08:57:32'),
(35, 9845, 1, 'ticket-private/dm_9845_done2.jpg', 2, 1369, '2026-07-27 08:40:00'),
(36, 9845, 1, 'ticket-private/dm_9845_done3.jpg', 3, 1369, '2026-07-27 08:40:00'),
(37, 9846, 1, 'ticket-private/dm_9846_done2.jpg', 2, 1369, '2026-07-30 14:49:21'),
(38, 9850, 1, 'ticket-private/dm_9850_done2.jpg', 2, 1369, '2026-07-09 13:15:05'),
(39, 9850, 1, 'ticket-private/dm_9850_done3.jpg', 3, 1369, '2026-07-09 13:15:05'),
(40, 9851, 1, 'ticket-private/dm_9851_done2.jpg', 2, 1368, '2026-07-29 11:18:20'),
(41, 9855, 1, 'ticket-private/dm_9855_done2.jpg', 2, 1368, '2026-08-17 14:08:48'),
(42, 9855, 1, 'ticket-private/dm_9855_done3.jpg', 3, 1368, '2026-08-17 14:08:48'),
(43, 9856, 1, 'ticket-private/dm_9856_done2.jpg', 2, 1368, '2026-08-24 16:26:59'),
(44, 9860, 1, 'ticket-private/dm_9860_done2.jpg', 2, 1368, '2026-08-18 09:32:19'),
(45, 9860, 1, 'ticket-private/dm_9860_done3.jpg', 3, 1368, '2026-08-18 09:32:19'),
(46, 9865, 1, 'ticket-private/dm_9865_done2.jpg', 2, 1368, '2026-08-07 08:37:43'),
(47, 9865, 1, 'ticket-private/dm_9865_done3.jpg', 3, 1368, '2026-08-07 08:37:43'),
(48, 9866, 1, 'ticket-private/dm_9866_done2.jpg', 2, 1369, '2026-08-13 10:43:53'),
(49, 9871, 1, 'ticket-private/dm_9871_done2.jpg', 2, 1369, '2026-09-01 10:52:12'),
(50, 9875, 1, 'ticket-private/dm_9875_done2.jpg', 2, 1368, '2026-09-21 11:53:05'),
(51, 9875, 1, 'ticket-private/dm_9875_done3.jpg', 3, 1368, '2026-09-21 11:53:05'),
(52, 9876, 1, 'ticket-private/dm_9876_done2.jpg', 2, 1368, '2026-09-28 08:40:00'),
(53, 9880, 1, 'ticket-private/dm_9880_done2.jpg', 2, 1368, '2026-09-29 08:41:30'),
(54, 9880, 1, 'ticket-private/dm_9880_done3.jpg', 3, 1368, '2026-09-29 08:41:30'),
(55, 9881, 1, 'ticket-private/dm_9881_done2.jpg', 2, 1369, '2026-09-07 08:40:00'),
(56, 9885, 1, 'ticket-private/dm_9885_done2.jpg', 2, 1368, '2026-09-16 08:49:44'),
(57, 9885, 1, 'ticket-private/dm_9885_done3.jpg', 3, 1368, '2026-09-16 08:49:44'),
(58, 9886, 1, 'ticket-private/dm_9886_done2.jpg', 2, 1368, '2026-10-02 09:27:24'),
(59, 9890, 1, 'ticket-private/dm_9890_done2.jpg', 2, 1368, '2026-09-22 12:48:24'),
(60, 9890, 1, 'ticket-private/dm_9890_done3.jpg', 3, 1368, '2026-09-22 12:48:24'),
(61, 9891, 1, 'ticket-private/dm_9891_done2.jpg', 2, 1369, '2026-10-05 08:40:00'),
(62, 9895, 1, 'ticket-private/dm_9895_done2.jpg', 2, 1369, '2026-09-28 12:58:22'),
(63, 9895, 1, 'ticket-private/dm_9895_done3.jpg', 3, 1369, '2026-09-28 12:58:22'),
(64, 9896, 1, 'ticket-private/dm_9896_done2.jpg', 2, 1368, '2026-09-10 09:21:06'),
(65, 9900, 1, 'ticket-private/dm_9900_done2.jpg', 2, 1368, '2026-10-09 12:56:04'),
(66, 9900, 1, 'ticket-private/dm_9900_done3.jpg', 3, 1368, '2026-10-09 12:56:04'),
(67, 9901, 1, 'ticket-private/dm_9901_done2.jpg', 2, 1368, '2026-10-07 09:06:56'),
(68, 9905, 1, 'ticket-private/dm_9905_done2.jpg', 2, 1369, '2026-11-02 08:40:00'),
(69, 9905, 1, 'ticket-private/dm_9905_done3.jpg', 3, 1369, '2026-11-02 08:40:00'),
(70, 9906, 1, 'ticket-private/dm_9906_done2.jpg', 2, 1369, '2026-10-15 08:50:17'),
(71, 9910, 1, 'ticket-private/dm_9910_done2.jpg', 2, 1369, '2026-10-30 09:17:50'),
(72, 9910, 1, 'ticket-private/dm_9910_done3.jpg', 3, 1369, '2026-10-30 09:17:50'),
(73, 9931, 1, 'ticket-private/dm_9931_done2.jpg', 2, 1371, '2026-11-09 08:50:37'),
(74, 9915, 1, 'ticket-private/done_a6bccd22aff810d8de2fa2e5c0f87528.jpg', 2, 1370, '2026-11-14 16:16:24');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_item_checks`
--

CREATE TABLE `ticket_item_checks` (
  `id` int NOT NULL,
  `ticket_id` int NOT NULL COMMENT 'ใบงาน',
  `item_name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ชื่ออุปกรณ์',
  `item_type` enum('main','accessory') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'accessory' COMMENT 'อุปกรณ์หลัก / อุปกรณ์พ่วง',
  `serial_no` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เลขเครื่อง/รหัสครุภัณฑ์',
  `sort_order` int NOT NULL DEFAULT '0',
  `qty_in` int NOT NULL DEFAULT '1' COMMENT 'จำนวนตอนรับ (0 = ไม่มีมา)',
  `condition_in` enum('normal','scratched','damaged') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT 'สภาพตอนรับ: ปกติ / มีรอย / ชำรุดอยู่เดิม',
  `note_in` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_in` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `received_by` int DEFAULT NULL COMMENT 'สตาฟผู้บันทึกตอนรับ',
  `received_at` datetime DEFAULT NULL,
  `qty_out` int DEFAULT NULL COMMENT 'จำนวนตอนส่งคืน',
  `result_out` enum('returned','missing','changed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'คืนครบ / ไม่พบ / สภาพเปลี่ยน',
  `note_out` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `photo_out` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `checked_out_by` int DEFAULT NULL COMMENT 'สตาฟผู้ตรวจซ้ำ',
  `checked_out_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='เช็คลิสต์อุปกรณ์ต่อใบงาน (ตอนรับและตอนส่งคืนในแถวเดียว)';

--
-- Dumping data for table `ticket_item_checks`
--

INSERT INTO `ticket_item_checks` (`id`, `ticket_id`, `item_name`, `item_type`, `serial_no`, `sort_order`, `qty_in`, `condition_in`, `note_in`, `photo_in`, `received_by`, `received_at`, `qty_out`, `result_out`, `note_out`, `photo_out`, `checked_out_by`, `checked_out_at`) VALUES
(6371, 9777, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-530-9405', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-18 08:55:24', 1, 'returned', NULL, NULL, 1368, '2026-05-21 08:45:13'),
(6372, 9777, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-18 08:55:24', 1, 'returned', NULL, NULL, 1368, '2026-05-21 08:45:13'),
(6373, 9778, 'ตัวเครื่อง / เคส', 'main', '7440-890-2811', 0, 1, 'normal', NULL, NULL, 1369, '2026-05-06 10:01:32', 1, 'returned', NULL, NULL, 1369, '2026-05-06 11:42:58'),
(6374, 9778, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-06 10:01:32', 1, 'returned', NULL, NULL, 1369, '2026-05-06 11:42:58'),
(6375, 9778, 'จอภาพ', 'accessory', NULL, 2, 0, 'normal', NULL, NULL, 1369, '2026-05-06 10:01:32', 0, 'returned', NULL, NULL, 1369, '2026-05-06 11:42:58'),
(6376, 9778, 'เมาส์', 'accessory', NULL, 3, 0, 'normal', NULL, NULL, 1369, '2026-05-06 10:01:32', 0, 'returned', NULL, NULL, 1369, '2026-05-06 11:42:58'),
(6377, 9779, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-609-5856', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-12 09:40:38', 1, 'returned', NULL, NULL, 1368, '2026-05-12 12:06:23'),
(6378, 9779, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-12 09:40:38', 1, 'returned', NULL, NULL, 1368, '2026-05-12 12:06:23'),
(6379, 9780, 'ตัวเครื่อง / เคส', 'main', '7440-384-6723', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-05-19 11:04:22', 1, 'returned', NULL, NULL, 1368, '2026-05-20 10:35:52'),
(6380, 9780, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-19 11:04:22', 1, 'returned', NULL, NULL, 1368, '2026-05-20 10:35:52'),
(6381, 9780, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-05-19 11:04:22', 1, 'returned', NULL, NULL, 1368, '2026-05-20 10:35:52'),
(6382, 9781, 'ตัวเครื่อง / เคส', 'main', '7440-467-6847', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-05-26 11:52:12', 1, 'returned', NULL, NULL, 1369, '2026-05-27 09:24:04'),
(6383, 9781, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-26 11:52:12', 1, 'returned', NULL, NULL, 1369, '2026-05-27 09:24:04'),
(6384, 9781, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-05-26 11:52:12', 1, 'returned', NULL, NULL, 1369, '2026-05-27 09:24:04'),
(6385, 9782, 'ตัวเครื่อง / เคส', 'main', '7440-746-6243', 0, 1, 'normal', NULL, NULL, 1369, '2026-05-25 16:20:43', 1, 'returned', NULL, NULL, 1369, '2026-05-26 10:34:19'),
(6386, 9782, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-25 16:20:43', 1, 'returned', NULL, NULL, 1369, '2026-05-26 10:34:19'),
(6387, 9782, 'จอภาพ', 'accessory', NULL, 2, 0, 'normal', NULL, NULL, 1369, '2026-05-25 16:20:43', 0, 'returned', NULL, NULL, 1369, '2026-05-26 10:34:19'),
(6388, 9783, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-388-8103', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-06 09:16:15', 1, 'returned', NULL, NULL, 1368, '2026-05-08 15:22:09'),
(6389, 9783, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-06 09:16:15', 1, 'returned', NULL, NULL, 1368, '2026-05-08 15:22:09'),
(6390, 9784, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-646-3808', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-04 10:22:28', 1, 'returned', NULL, NULL, 1368, '2026-05-04 13:06:29'),
(6391, 9784, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-04 10:22:28', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1368, '2026-05-04 13:06:29'),
(6392, 9786, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-795-9704', 0, 1, 'normal', NULL, NULL, 1369, '2026-05-13 09:34:41', 1, 'returned', NULL, NULL, 1369, '2026-05-15 08:48:31'),
(6393, 9786, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-05-13 09:34:41', 0, 'returned', NULL, NULL, 1369, '2026-05-15 08:48:31'),
(6394, 9787, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-725-4533', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-05-25 08:59:49', 1, 'returned', NULL, NULL, 1369, '2026-05-26 09:20:54'),
(6395, 9787, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-25 08:59:49', 1, 'returned', NULL, NULL, 1369, '2026-05-26 09:20:54'),
(6396, 9787, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-05-25 08:59:49', 1, 'returned', NULL, NULL, 1369, '2026-05-26 09:20:54'),
(6397, 9788, 'ตัวเครื่อง / เคส', 'main', '7440-825-7928', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-26 09:22:31', 1, 'returned', NULL, NULL, 1368, '2026-05-29 09:07:42'),
(6398, 9788, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-26 09:22:31', 1, 'returned', NULL, NULL, 1368, '2026-05-29 09:07:42'),
(6399, 9788, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-05-26 09:22:31', 1, 'returned', NULL, NULL, 1368, '2026-05-29 09:07:42'),
(6400, 9789, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-225-7840', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-05-11 09:53:39', 1, 'returned', NULL, NULL, 1369, '2026-05-11 12:05:00'),
(6401, 9789, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-11 09:53:39', 1, 'returned', NULL, NULL, 1369, '2026-05-11 12:05:00'),
(6402, 9790, 'ตัวเครื่อง', 'main', '7440-755-6986', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-21 10:29:25', 1, 'returned', NULL, NULL, 1368, '2026-05-21 13:50:59'),
(6403, 9790, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-05-21 10:29:25', 0, 'returned', NULL, NULL, 1368, '2026-05-21 13:50:59'),
(6404, 9791, 'ตัวเครื่อง / เคส', 'main', '7440-196-4379', 0, 1, 'normal', NULL, NULL, 1369, '2026-05-08 13:37:40', 1, 'returned', NULL, NULL, 1369, '2026-05-08 14:31:34'),
(6405, 9791, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-05-08 13:37:40', 0, 'returned', NULL, NULL, 1369, '2026-05-08 14:31:34'),
(6406, 9791, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-05-08 13:37:40', 1, 'returned', NULL, NULL, 1369, '2026-05-08 14:31:34'),
(6407, 9792, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-168-4333', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-11 08:53:15', 1, 'returned', NULL, NULL, 1368, '2026-05-13 09:33:47'),
(6408, 9792, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-11 08:53:15', 1, 'returned', NULL, NULL, 1368, '2026-05-13 09:33:47'),
(6409, 9792, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-05-11 08:53:15', 1, 'returned', NULL, NULL, 1368, '2026-05-13 09:33:47'),
(6410, 9793, 'ตัวเครื่อง', 'main', '7440-854-6009', 0, 1, 'normal', NULL, NULL, 1369, '2026-05-07 08:51:29', 1, 'returned', NULL, NULL, 1369, '2026-05-08 15:25:30'),
(6411, 9793, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-07 08:51:29', 1, 'returned', NULL, NULL, 1369, '2026-05-08 15:25:30'),
(6412, 9794, 'ตัวเครื่อง', 'main', '7440-618-3548', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-11 08:57:44', 1, 'returned', NULL, NULL, 1368, '2026-05-14 09:01:58'),
(6413, 9794, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-11 08:57:44', 1, 'returned', NULL, NULL, 1368, '2026-05-14 09:01:58'),
(6414, 9795, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-763-6257', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-05-12 09:29:20', 1, 'returned', NULL, NULL, 1369, '2026-05-14 15:04:40'),
(6415, 9795, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-12 09:29:20', 1, 'returned', NULL, NULL, 1369, '2026-05-14 15:04:40'),
(6416, 9796, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-850-4967', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-15 09:36:12', 1, 'returned', NULL, NULL, 1368, '2026-05-18 08:40:00'),
(6417, 9796, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-15 09:36:12', 1, 'returned', NULL, NULL, 1368, '2026-05-18 08:40:00'),
(6418, 9797, 'ตัวเครื่อง', 'main', '7440-555-4429', 0, 1, 'normal', NULL, NULL, 1369, '2026-05-22 11:57:26', 1, 'returned', NULL, NULL, 1369, '2026-05-25 09:26:57'),
(6419, 9797, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-22 11:57:26', 1, 'returned', NULL, NULL, 1369, '2026-05-25 09:26:57'),
(6420, 9797, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-05-22 11:57:26', 1, 'returned', NULL, NULL, 1369, '2026-05-25 09:26:57'),
(6421, 9797, 'ตลับหมึก / หมึก', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1369, '2026-05-22 11:57:26', 1, 'returned', NULL, NULL, 1369, '2026-05-25 09:26:57'),
(6422, 9798, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-186-8531', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-18 15:23:48', 1, 'returned', NULL, NULL, 1368, '2026-05-21 09:34:03'),
(6423, 9798, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-18 15:23:48', 1, 'returned', NULL, NULL, 1368, '2026-05-21 09:34:03'),
(6424, 9800, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-514-4461', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-15 10:04:48', 1, 'returned', NULL, NULL, 1368, '2026-05-15 11:48:16'),
(6425, 9800, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-15 10:04:48', 1, 'returned', NULL, NULL, 1368, '2026-05-15 11:48:16'),
(6426, 9801, 'ตัวเครื่อง / เคส', 'main', '7440-848-5241', 0, 1, 'normal', NULL, NULL, 1368, '2026-05-15 08:57:46', 1, 'returned', NULL, NULL, 1368, '2026-05-18 08:40:00'),
(6427, 9801, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-05-15 08:57:46', 1, 'returned', NULL, NULL, 1368, '2026-05-18 08:40:00'),
(6428, 9801, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-05-15 08:57:46', 1, 'returned', NULL, NULL, 1368, '2026-05-18 08:40:00'),
(6429, 9802, 'ตัวเครื่อง / เคส', 'main', '7440-740-9613', 0, 1, 'normal', NULL, NULL, 1369, '2026-05-11 10:41:17', 1, 'returned', NULL, NULL, 1369, '2026-05-13 09:31:35'),
(6430, 9802, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-05-11 10:41:17', 1, 'returned', NULL, NULL, 1369, '2026-05-13 09:31:35'),
(6431, 9803, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-374-4716', 0, 1, 'normal', NULL, NULL, 1369, '2026-05-19 16:13:51', 1, 'returned', NULL, NULL, 1369, '2026-05-21 09:02:40'),
(6432, 9803, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-05-19 16:13:51', 0, 'returned', NULL, NULL, 1369, '2026-05-21 09:02:40'),
(6433, 9803, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-05-19 16:13:51', 1, 'returned', NULL, NULL, 1369, '2026-05-21 09:02:40'),
(6434, 9804, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-534-7247', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-16 09:16:12', 1, 'returned', NULL, NULL, 1368, '2026-06-16 15:53:29'),
(6435, 9804, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-06-16 09:16:12', 1, 'returned', NULL, NULL, 1368, '2026-06-16 15:53:29'),
(6436, 9806, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-386-5258', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-06-16 08:51:06', 1, 'returned', NULL, NULL, 1369, '2026-06-17 09:27:26'),
(6437, 9806, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-16 08:51:06', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1369, '2026-06-17 09:27:26'),
(6438, 9807, 'ตัวเครื่อง', 'main', '7440-853-2561', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-12 09:37:50', 1, 'returned', NULL, NULL, 1368, '2026-06-15 09:34:22'),
(6439, 9807, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-06-12 09:37:50', 1, 'returned', NULL, NULL, 1368, '2026-06-15 09:34:22'),
(6440, 9807, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-06-12 09:37:50', 1, 'returned', NULL, NULL, 1368, '2026-06-15 09:34:22'),
(6441, 9807, 'ตลับหมึก / หมึก', 'accessory', NULL, 3, 0, 'normal', NULL, NULL, 1368, '2026-06-12 09:37:50', 0, 'returned', NULL, NULL, 1368, '2026-06-15 09:34:22'),
(6442, 9808, 'ตัวเครื่อง', 'main', '7440-448-0226', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-23 09:05:46', 1, 'returned', NULL, NULL, 1369, '2026-06-25 09:32:25'),
(6443, 9808, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-23 09:05:46', 1, 'returned', NULL, NULL, 1369, '2026-06-25 09:32:25'),
(6444, 9808, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-23 09:05:46', 1, 'returned', NULL, NULL, 1369, '2026-06-25 09:32:25'),
(6445, 9808, 'ตลับหมึก / หมึก', 'accessory', NULL, 3, 0, 'normal', NULL, NULL, 1369, '2026-06-23 09:05:46', 0, 'returned', NULL, NULL, 1369, '2026-06-25 09:32:25'),
(6446, 9809, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-422-1794', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-16 09:08:42', 1, 'returned', NULL, NULL, 1369, '2026-06-17 09:30:16'),
(6447, 9809, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-06-16 09:08:42', 0, 'returned', NULL, NULL, 1369, '2026-06-17 09:30:16'),
(6448, 9810, 'ตัวเครื่อง / เคส', 'main', '7440-813-8422', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-15 10:15:02', 1, 'returned', NULL, NULL, 1369, '2026-06-15 13:52:44'),
(6449, 9810, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-15 10:15:02', 1, 'returned', NULL, NULL, 1369, '2026-06-15 13:52:44'),
(6450, 9810, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-15 10:15:02', 1, 'returned', NULL, NULL, 1369, '2026-06-15 13:52:44'),
(6451, 9811, 'ตัวเครื่อง', 'main', '7440-659-2942', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-24 09:20:39', 1, 'returned', NULL, NULL, 1368, '2026-06-29 08:40:00'),
(6452, 9811, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-06-24 09:20:39', 1, 'returned', NULL, NULL, 1368, '2026-06-29 08:40:00'),
(6453, 9812, 'ตัวเครื่อง / เคส', 'main', '7440-462-1317', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-30 09:12:24', 1, 'returned', NULL, NULL, 1369, '2026-07-01 08:40:59'),
(6454, 9812, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-30 09:12:24', 1, 'returned', NULL, NULL, 1369, '2026-07-01 08:40:59'),
(6455, 9812, 'จอภาพ', 'accessory', NULL, 2, 0, 'normal', NULL, NULL, 1369, '2026-06-30 09:12:24', 0, 'returned', NULL, NULL, 1369, '2026-07-01 08:40:59'),
(6456, 9813, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-864-1231', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-22 09:09:40', 1, 'returned', NULL, NULL, 1368, '2026-06-24 12:30:31'),
(6457, 9813, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-06-22 09:09:40', 1, 'returned', NULL, NULL, 1368, '2026-06-24 12:30:31'),
(6458, 9814, 'ตัวเครื่อง', 'main', '7440-679-4550', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-16 09:29:44', 1, 'returned', NULL, NULL, 1369, '2026-06-17 14:04:12'),
(6459, 9814, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-16 09:29:44', 1, 'returned', NULL, NULL, 1369, '2026-06-17 14:04:12'),
(6460, 9814, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-16 09:29:44', 1, 'returned', NULL, NULL, 1369, '2026-06-17 14:04:12'),
(6461, 9815, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-394-7010', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-24 14:38:08', 1, 'returned', NULL, NULL, 1368, '2026-06-25 08:39:26'),
(6462, 9815, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-06-24 14:38:08', 0, 'returned', NULL, NULL, 1368, '2026-06-25 08:39:26'),
(6463, 9816, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-527-3919', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-06-25 09:44:20', 1, 'returned', NULL, NULL, 1369, '2026-06-29 08:40:00'),
(6464, 9816, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-25 09:44:20', 1, 'returned', NULL, NULL, 1369, '2026-06-29 08:40:00'),
(6465, 9816, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-25 09:44:20', 1, 'returned', NULL, NULL, 1369, '2026-06-29 08:40:00'),
(6466, 9817, 'ตัวเครื่อง', 'main', '7440-615-1221', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-30 09:19:33', 1, 'returned', NULL, NULL, 1369, '2026-07-03 09:04:46'),
(6467, 9817, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-30 09:19:33', 1, 'returned', NULL, NULL, 1369, '2026-07-03 09:04:46'),
(6468, 9818, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-364-2921', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-16 10:03:57', 1, 'returned', NULL, NULL, 1368, '2026-06-16 11:44:36'),
(6469, 9818, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-06-16 10:03:57', 0, 'returned', NULL, NULL, 1368, '2026-06-16 11:44:36'),
(6470, 9818, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-06-16 10:03:57', 1, 'returned', NULL, NULL, 1368, '2026-06-16 11:44:36'),
(6471, 9820, 'ตัวเครื่อง / เคส', 'main', '7440-639-7496', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-22 09:06:11', 1, 'returned', NULL, NULL, 1368, '2026-06-22 12:24:27'),
(6472, 9820, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-06-22 09:06:11', 1, 'returned', NULL, NULL, 1368, '2026-06-22 12:24:27'),
(6473, 9820, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-06-22 09:06:11', 1, 'returned', NULL, NULL, 1368, '2026-06-22 12:24:27'),
(6474, 9820, 'เมาส์', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1368, '2026-06-22 09:06:11', 1, 'returned', NULL, NULL, 1368, '2026-06-22 12:24:27'),
(6475, 9821, 'ตัวเครื่อง', 'main', '7440-316-7558', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-18 09:16:50', 1, 'returned', NULL, NULL, 1369, '2026-06-19 09:19:11'),
(6476, 9821, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-18 09:16:50', 1, 'returned', NULL, NULL, 1369, '2026-06-19 09:19:11'),
(6477, 9822, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-587-5035', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-06-10 09:05:14', 1, 'returned', NULL, NULL, 1368, '2026-06-10 10:22:04'),
(6478, 9822, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-06-10 09:05:14', 1, 'returned', NULL, NULL, 1368, '2026-06-10 10:22:04'),
(6479, 9823, 'ตัวเครื่อง', 'main', '7440-720-8662', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-10 08:53:18', 1, 'returned', NULL, NULL, 1369, '2026-06-12 09:34:25'),
(6480, 9823, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-10 08:53:18', 1, 'returned', NULL, NULL, 1369, '2026-06-12 09:34:25'),
(6481, 9823, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-10 08:53:18', 1, 'returned', NULL, NULL, 1369, '2026-06-12 09:34:25'),
(6482, 9824, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-433-0031', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-19 09:09:37', 1, 'returned', NULL, NULL, 1369, '2026-06-22 08:40:00'),
(6483, 9824, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-19 09:09:37', 1, 'returned', NULL, NULL, 1369, '2026-06-22 08:40:00'),
(6484, 9824, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-19 09:09:37', 1, 'returned', NULL, NULL, 1369, '2026-06-22 08:40:00'),
(6485, 9825, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-871-6587', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-16 08:49:41', 1, 'returned', NULL, NULL, 1369, '2026-06-19 08:42:00'),
(6486, 9825, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-16 08:49:41', 1, 'returned', NULL, NULL, 1369, '2026-06-19 08:42:00'),
(6487, 9825, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-16 08:49:41', 1, 'returned', NULL, NULL, 1369, '2026-06-19 08:42:00'),
(6488, 9825, 'เสาอากาศ', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1369, '2026-06-16 08:49:41', 1, 'returned', NULL, NULL, 1369, '2026-06-19 08:42:00'),
(6489, 9826, 'ตัวเครื่อง', 'main', '7440-543-6772', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-05 09:08:21', 1, 'returned', NULL, NULL, 1369, '2026-06-05 15:04:14'),
(6490, 9826, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-05 09:08:21', 1, 'returned', NULL, NULL, 1369, '2026-06-05 15:04:14'),
(6491, 9826, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-05 09:08:21', 1, 'returned', NULL, NULL, 1369, '2026-06-05 15:04:14'),
(6492, 9827, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-292-8495', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-17 09:13:25', 1, 'returned', NULL, NULL, 1368, '2026-06-19 09:05:11'),
(6493, 9827, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-06-17 09:13:25', 1, 'returned', NULL, NULL, 1368, '2026-06-19 09:05:11'),
(6494, 9828, 'ตัวเครื่อง', 'main', '7440-852-1553', 0, 1, 'normal', NULL, NULL, 1368, '2026-06-15 09:17:57', 1, 'returned', NULL, NULL, 1368, '2026-06-16 12:08:24'),
(6495, 9828, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-06-15 09:17:57', 0, 'returned', NULL, NULL, 1368, '2026-06-16 12:08:24'),
(6496, 9829, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-118-5681', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-06-08 14:41:00', 1, 'returned', NULL, NULL, 1369, '2026-06-09 08:42:20'),
(6497, 9829, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-06-08 14:41:00', 1, 'returned', NULL, NULL, 1369, '2026-06-09 08:42:20'),
(6498, 9829, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-06-08 14:41:00', 1, 'returned', NULL, NULL, 1369, '2026-06-09 08:42:20'),
(6499, 9830, 'ตัวเครื่อง', 'main', '7440-879-2761', 0, 1, 'normal', NULL, NULL, 1369, '2026-06-10 09:57:06', 1, 'returned', NULL, NULL, 1369, '2026-06-10 12:03:31'),
(6500, 9830, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-06-10 09:57:06', 0, 'returned', NULL, NULL, 1369, '2026-06-10 12:03:31'),
(6501, 9831, 'ตัวเครื่อง', 'main', '7440-447-8751', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-06-16 09:19:29', 1, 'returned', NULL, NULL, 1368, '2026-06-17 08:46:31'),
(6502, 9831, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-06-16 09:19:29', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1368, '2026-06-17 08:46:31'),
(6503, 9832, 'ตัวเครื่อง', 'main', '7440-908-0997', 0, 1, 'normal', NULL, NULL, 1369, '2026-07-06 15:42:41', 1, 'returned', NULL, NULL, 1369, '2026-07-08 16:10:16'),
(6504, 9832, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-07-06 15:42:41', 1, 'returned', NULL, NULL, 1369, '2026-07-08 16:10:16'),
(6505, 9832, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-07-06 15:42:41', 1, 'returned', NULL, NULL, 1369, '2026-07-08 16:10:16'),
(6506, 9833, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-453-2634', 0, 1, 'normal', NULL, NULL, 1369, '2026-07-21 11:55:20', 1, 'returned', NULL, NULL, 1369, '2026-07-21 14:43:23'),
(6507, 9833, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-07-21 11:55:20', 0, 'returned', NULL, NULL, 1369, '2026-07-21 14:43:23'),
(6508, 9834, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-507-9033', 0, 1, 'normal', NULL, NULL, 1369, '2026-07-09 08:56:53', 1, 'returned', NULL, NULL, 1369, '2026-07-10 09:22:29'),
(6509, 9834, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-07-09 08:56:53', 0, 'returned', NULL, NULL, 1369, '2026-07-10 09:22:29'),
(6510, 9835, 'ตัวเครื่อง', 'main', '7440-840-5692', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-07-13 08:55:12', 1, 'returned', NULL, NULL, 1368, '2026-07-13 12:19:04'),
(6511, 9835, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-07-13 08:55:12', 1, 'returned', NULL, NULL, 1368, '2026-07-13 12:19:04'),
(6512, 9836, 'ตัวเครื่อง', 'main', '7440-490-3847', 0, 1, 'normal', NULL, NULL, 1368, '2026-07-06 12:43:57', 1, 'returned', NULL, NULL, 1368, '2026-07-07 09:08:24'),
(6513, 9836, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-07-06 12:43:57', 0, 'returned', NULL, NULL, 1368, '2026-07-07 09:08:24'),
(6514, 9836, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-07-06 12:43:57', 1, 'returned', NULL, NULL, 1368, '2026-07-07 09:08:24'),
(6515, 9836, 'ตลับหมึก / หมึก', 'accessory', NULL, 3, 0, 'normal', NULL, NULL, 1368, '2026-07-06 12:43:57', 0, 'returned', NULL, NULL, 1368, '2026-07-07 09:08:24'),
(6516, 9837, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-385-4167', 0, 1, 'normal', NULL, NULL, 1368, '2026-07-06 15:05:36', 1, 'returned', NULL, NULL, 1368, '2026-07-08 15:59:23'),
(6517, 9837, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-07-06 15:05:36', 0, 'returned', NULL, NULL, 1368, '2026-07-08 15:59:23'),
(6518, 9837, 'สาย LAN', 'accessory', NULL, 2, 0, 'normal', NULL, NULL, 1368, '2026-07-06 15:05:36', 0, 'returned', NULL, NULL, 1368, '2026-07-08 15:59:23'),
(6519, 9838, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-279-7893', 0, 1, 'normal', NULL, NULL, 1368, '2026-07-07 09:08:32', 1, 'returned', NULL, NULL, 1368, '2026-07-09 08:49:07'),
(6520, 9838, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-07-07 09:08:32', 1, 'returned', NULL, NULL, 1368, '2026-07-09 08:49:07'),
(6521, 9839, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-336-4392', 0, 1, 'normal', NULL, NULL, 1368, '2026-07-28 09:01:06', 1, 'returned', NULL, NULL, 1368, '2026-07-30 09:00:02'),
(6522, 9839, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-07-28 09:01:06', 0, 'returned', NULL, NULL, 1368, '2026-07-30 09:00:02'),
(6523, 9839, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-07-28 09:01:06', 1, 'returned', NULL, NULL, 1368, '2026-07-30 09:00:02'),
(6524, 9840, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-734-1104', 0, 1, 'normal', NULL, NULL, 1369, '2026-07-06 13:13:57', 1, 'returned', NULL, NULL, 1369, '2026-07-08 08:57:32'),
(6525, 9840, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-07-06 13:13:57', 0, 'returned', NULL, NULL, 1369, '2026-07-08 08:57:32'),
(6526, 9840, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-07-06 13:13:57', 1, 'returned', NULL, NULL, 1369, '2026-07-08 08:57:32'),
(6527, 9840, 'เสาอากาศ', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1369, '2026-07-06 13:13:57', 1, 'returned', NULL, NULL, 1369, '2026-07-08 08:57:32'),
(6528, 9842, 'ตัวเครื่อง', 'main', '7440-760-2670', 0, 1, 'normal', NULL, NULL, 1368, '2026-07-27 16:24:37', 1, 'returned', NULL, NULL, 1368, '2026-07-28 08:58:10'),
(6529, 9842, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-07-27 16:24:37', 1, 'returned', NULL, NULL, 1368, '2026-07-28 08:58:10'),
(6530, 9842, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-07-27 16:24:37', 1, 'returned', NULL, NULL, 1368, '2026-07-28 08:58:10'),
(6531, 9843, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-318-3130', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-07-06 16:24:38', 1, 'returned', NULL, NULL, 1369, '2026-07-07 09:15:46'),
(6532, 9843, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-07-06 16:24:38', 1, 'returned', NULL, NULL, 1369, '2026-07-07 09:15:46'),
(6533, 9843, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-07-06 16:24:38', 1, 'returned', NULL, NULL, 1369, '2026-07-07 09:15:46'),
(6534, 9844, 'ตัวเครื่อง / เคส', 'main', '7440-913-3917', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-08-03 08:49:32', 1, 'returned', NULL, NULL, 1369, '2026-08-05 16:04:43'),
(6535, 9844, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-08-03 08:49:32', 1, 'returned', NULL, NULL, 1369, '2026-08-05 16:04:43'),
(6536, 9844, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-08-03 08:49:32', 1, 'returned', NULL, NULL, 1369, '2026-08-05 16:04:43'),
(6537, 9844, 'เมาส์', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1369, '2026-08-03 08:49:32', 1, 'returned', NULL, NULL, 1369, '2026-08-05 16:04:43'),
(6538, 9845, 'ตัวเครื่อง / เคส', 'main', '7440-998-7499', 0, 1, 'normal', NULL, NULL, 1369, '2026-07-24 08:48:42', 1, 'returned', NULL, NULL, 1369, '2026-07-27 08:40:00'),
(6539, 9845, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-07-24 08:48:42', 1, 'returned', NULL, NULL, 1369, '2026-07-27 08:40:00'),
(6540, 9846, 'ตัวเครื่อง', 'main', '7440-727-6250', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-07-30 08:58:59', 1, 'returned', NULL, NULL, 1369, '2026-07-30 14:49:21'),
(6541, 9846, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-07-30 08:58:59', 1, 'returned', NULL, NULL, 1369, '2026-07-30 14:49:21'),
(6542, 9847, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-718-4403', 0, 1, 'normal', NULL, NULL, 1368, '2026-07-28 09:21:26', 1, 'returned', NULL, NULL, 1368, '2026-07-30 08:41:22'),
(6543, 9847, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-07-28 09:21:26', 1, 'returned', NULL, NULL, 1368, '2026-07-30 08:41:22'),
(6544, 9847, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-07-28 09:21:26', 1, 'returned', NULL, NULL, 1368, '2026-07-30 08:41:22'),
(6545, 9848, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-577-9393', 0, 1, 'normal', NULL, NULL, 1368, '2026-08-03 08:48:06', 1, 'returned', NULL, NULL, 1368, '2026-08-05 08:38:26'),
(6546, 9848, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-03 08:48:06', 1, 'returned', NULL, NULL, 1368, '2026-08-05 08:38:26'),
(6547, 9848, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-08-03 08:48:06', 1, 'returned', NULL, NULL, 1368, '2026-08-05 08:38:26'),
(6548, 9849, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-303-0625', 0, 1, 'normal', NULL, NULL, 1368, '2026-07-28 09:05:18', 1, 'returned', NULL, NULL, 1368, '2026-07-29 08:53:30'),
(6549, 9849, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-07-28 09:05:18', 1, 'returned', NULL, NULL, 1368, '2026-07-29 08:53:30'),
(6550, 9850, 'ตัวเครื่อง', 'main', '7440-127-6965', 0, 1, 'normal', NULL, NULL, 1369, '2026-07-09 10:16:01', 1, 'returned', NULL, NULL, 1369, '2026-07-09 13:15:05'),
(6551, 9850, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-07-09 10:16:01', 1, 'returned', NULL, NULL, 1369, '2026-07-09 13:15:05'),
(6552, 9851, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-451-6262', 0, 1, 'normal', NULL, NULL, 1368, '2026-07-29 09:59:44', 1, 'returned', NULL, NULL, 1368, '2026-07-29 11:18:20'),
(6553, 9851, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-07-29 09:59:44', 0, 'returned', NULL, NULL, 1368, '2026-07-29 11:18:20'),
(6554, 9853, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-493-3009', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-08-26 09:29:14', 1, 'returned', NULL, NULL, 1368, '2026-08-28 09:06:20'),
(6555, 9853, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-26 09:29:14', 1, 'returned', NULL, NULL, 1368, '2026-08-28 09:06:20'),
(6556, 9853, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-08-26 09:29:14', 1, 'returned', NULL, NULL, 1368, '2026-08-28 09:06:20'),
(6557, 9854, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-349-6121', 0, 1, 'normal', NULL, NULL, 1369, '2026-08-28 08:57:37', 1, 'returned', NULL, NULL, 1369, '2026-08-31 08:40:00'),
(6558, 9854, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-08-28 08:57:37', 0, 'returned', NULL, NULL, 1369, '2026-08-31 08:40:00'),
(6559, 9854, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-08-28 08:57:37', 1, 'returned', NULL, NULL, 1369, '2026-08-31 08:40:00'),
(6560, 9855, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-814-6219', 0, 1, 'normal', NULL, NULL, 1368, '2026-08-17 12:27:17', 1, 'returned', NULL, NULL, 1368, '2026-08-17 14:08:48'),
(6561, 9855, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-17 12:27:17', 1, 'returned', NULL, NULL, 1368, '2026-08-17 14:08:48'),
(6562, 9856, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-975-2120', 0, 1, 'normal', NULL, NULL, 1368, '2026-08-24 08:48:21', 1, 'returned', NULL, NULL, 1368, '2026-08-24 16:26:59'),
(6563, 9856, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-24 08:48:21', 1, 'returned', NULL, NULL, 1368, '2026-08-24 16:26:59'),
(6564, 9856, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-08-24 08:48:21', 1, 'returned', NULL, NULL, 1368, '2026-08-24 16:26:59'),
(6565, 9857, 'ตัวเครื่อง', 'main', '7440-414-0798', 0, 1, 'normal', NULL, NULL, 1368, '2026-08-31 13:56:40', 1, 'returned', NULL, NULL, 1368, '2026-09-03 08:36:19'),
(6566, 9857, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-31 13:56:40', 1, 'returned', NULL, NULL, 1368, '2026-09-03 08:36:19'),
(6567, 9858, 'ตัวเครื่อง', 'main', '7440-784-8022', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-08-27 10:16:59', 1, 'returned', NULL, NULL, 1369, '2026-08-27 12:59:51'),
(6568, 9858, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-08-27 10:16:59', 0, 'returned', NULL, NULL, 1369, '2026-08-27 12:59:51'),
(6569, 9859, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-488-6867', 0, 1, 'normal', NULL, NULL, 1368, '2026-08-31 08:56:47', 1, 'returned', NULL, NULL, 1368, '2026-09-02 09:06:19'),
(6570, 9859, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-31 08:56:47', 1, 'returned', NULL, NULL, 1368, '2026-09-02 09:06:19'),
(6571, 9859, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-08-31 08:56:47', 1, 'returned', NULL, NULL, 1368, '2026-09-02 09:06:19'),
(6572, 9860, 'ตัวเครื่อง / เคส', 'main', '7440-440-4516', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-08-17 12:45:23', 1, 'returned', NULL, NULL, 1368, '2026-08-18 09:32:19'),
(6573, 9860, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-17 12:45:23', 1, 'returned', NULL, NULL, 1368, '2026-08-18 09:32:19'),
(6574, 9860, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-08-17 12:45:23', 1, 'returned', NULL, NULL, 1368, '2026-08-18 09:32:19'),
(6575, 9860, 'เมาส์', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1368, '2026-08-17 12:45:23', 1, 'returned', NULL, NULL, 1368, '2026-08-18 09:32:19'),
(6576, 9862, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-786-0282', 0, 1, 'normal', NULL, NULL, 1369, '2026-08-04 09:24:30', 1, 'returned', NULL, NULL, 1369, '2026-08-04 11:55:32'),
(6577, 9862, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-08-04 09:24:30', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1369, '2026-08-04 11:55:32'),
(6578, 9863, 'ตัวเครื่อง', 'main', '7440-506-3376', 0, 1, 'normal', NULL, NULL, 1368, '2026-08-13 09:13:58', 1, 'returned', NULL, NULL, 1368, '2026-08-14 16:11:57'),
(6579, 9863, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-13 09:13:58', 1, 'returned', NULL, NULL, 1368, '2026-08-14 16:11:57'),
(6580, 9864, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-852-7070', 0, 1, 'normal', NULL, NULL, 1369, '2026-08-11 09:33:19', 1, 'returned', NULL, NULL, 1369, '2026-08-13 08:37:37'),
(6581, 9864, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-08-11 09:33:19', 1, 'returned', NULL, NULL, 1369, '2026-08-13 08:37:37'),
(6582, 9864, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-08-11 09:33:19', 1, 'returned', NULL, NULL, 1369, '2026-08-13 08:37:37'),
(6583, 9865, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-985-0589', 0, 1, 'normal', NULL, NULL, 1368, '2026-08-06 08:52:15', 1, 'returned', NULL, NULL, 1368, '2026-08-07 08:37:43'),
(6584, 9865, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-06 08:52:15', 1, 'returned', NULL, NULL, 1368, '2026-08-07 08:37:43'),
(6585, 9865, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-08-06 08:52:15', 1, 'returned', NULL, NULL, 1368, '2026-08-07 08:37:43'),
(6586, 9866, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-861-5299', 0, 1, 'normal', NULL, NULL, 1369, '2026-08-10 12:31:48', 1, 'returned', NULL, NULL, 1369, '2026-08-13 10:43:53'),
(6587, 9866, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-08-10 12:31:48', 1, 'returned', NULL, NULL, 1369, '2026-08-13 10:43:53'),
(6588, 9867, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-292-9086', 0, 1, 'normal', NULL, NULL, 1369, '2026-08-10 08:54:16', 1, 'returned', NULL, NULL, 1369, '2026-08-11 09:20:05'),
(6589, 9867, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-08-10 08:54:16', 1, 'returned', NULL, NULL, 1369, '2026-08-11 09:20:05'),
(6590, 9868, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-782-5355', 0, 1, 'normal', NULL, NULL, 1369, '2026-08-04 16:24:56', 1, 'returned', NULL, NULL, 1369, '2026-08-05 09:22:47'),
(6591, 9868, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-08-04 16:24:56', 1, 'returned', NULL, NULL, 1369, '2026-08-05 09:22:47'),
(6592, 9869, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-501-6485', 0, 1, 'normal', NULL, NULL, 1368, '2026-08-24 10:57:23', 1, 'returned', NULL, NULL, 1368, '2026-08-24 13:52:31'),
(6593, 9869, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-24 10:57:23', 1, 'returned', NULL, NULL, 1368, '2026-08-24 13:52:31'),
(6594, 9869, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-08-24 10:57:23', 1, 'returned', NULL, NULL, 1368, '2026-08-24 13:52:31'),
(6595, 9871, 'ตัวเครื่อง', 'main', '7440-721-5609', 0, 1, 'normal', NULL, NULL, 1369, '2026-09-01 08:56:42', 1, 'returned', NULL, NULL, 1369, '2026-09-01 10:52:12'),
(6596, 9871, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-09-01 08:56:42', 1, 'returned', NULL, NULL, 1369, '2026-09-01 10:52:12'),
(6597, 9872, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-311-6067', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-01 08:54:24', 1, 'returned', NULL, NULL, 1368, '2026-09-01 13:13:31'),
(6598, 9872, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-01 08:54:24', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1368, '2026-09-01 13:13:31'),
(6599, 9873, 'ตัวเครื่อง', 'main', '7440-833-5136', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-08-07 09:27:13', 1, 'returned', NULL, NULL, 1368, '2026-08-10 08:40:00'),
(6600, 9873, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-08-07 09:27:13', 1, 'returned', NULL, NULL, 1368, '2026-08-10 08:40:00'),
(6601, 9874, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-502-3312', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-09-21 09:13:19', 1, 'returned', NULL, NULL, 1368, '2026-09-24 09:11:27'),
(6602, 9874, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-21 09:13:19', 1, 'returned', NULL, NULL, 1368, '2026-09-24 09:11:27'),
(6603, 9874, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-09-21 09:13:19', 1, 'returned', NULL, NULL, 1368, '2026-09-24 09:11:27'),
(6604, 9875, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-430-0356', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-21 09:59:01', 1, 'returned', NULL, NULL, 1368, '2026-09-21 11:53:05'),
(6605, 9875, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-21 09:59:01', 1, 'returned', NULL, NULL, 1368, '2026-09-21 11:53:05'),
(6606, 9876, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-756-1657', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-09-25 09:56:15', 1, 'returned', NULL, NULL, 1368, '2026-09-28 08:40:00'),
(6607, 9876, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-25 09:56:15', 1, 'returned', NULL, NULL, 1368, '2026-09-28 08:40:00'),
(6608, 9877, 'ตัวเครื่อง / เคส', 'main', '7440-305-8010', 0, 1, 'normal', NULL, NULL, 1369, '2026-09-04 15:38:40', 1, 'returned', NULL, NULL, 1369, '2026-09-07 08:40:00'),
(6609, 9877, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-09-04 15:38:40', 0, 'returned', NULL, NULL, 1369, '2026-09-07 08:40:00'),
(6610, 9878, 'ตัวเครื่อง', 'main', '7440-705-6797', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-09-29 09:07:45', 1, 'returned', NULL, NULL, 1369, '2026-09-30 09:12:34'),
(6611, 9878, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-09-29 09:07:45', 1, 'returned', NULL, NULL, 1369, '2026-09-30 09:12:34'),
(6612, 9879, 'ตัวเครื่อง', 'main', '7440-918-8427', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-09-07 15:35:52', 1, 'returned', NULL, NULL, 1368, '2026-09-08 09:01:55'),
(6613, 9879, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-07 15:35:52', 1, 'returned', NULL, NULL, 1368, '2026-09-08 09:01:55'),
(6614, 9880, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-888-1711', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-28 08:47:12', 1, 'returned', NULL, NULL, 1368, '2026-09-29 08:41:30'),
(6615, 9880, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-28 08:47:12', 1, 'returned', NULL, NULL, 1368, '2026-09-29 08:41:30'),
(6616, 9881, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-767-5052', 0, 1, 'normal', NULL, NULL, 1369, '2026-09-04 14:09:17', 1, 'returned', NULL, NULL, 1369, '2026-09-07 08:40:00'),
(6617, 9881, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-09-04 14:09:17', 1, 'returned', NULL, NULL, 1369, '2026-09-07 08:40:00'),
(6618, 9882, 'ตัวเครื่อง', 'main', '7440-430-8479', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-09-23 13:50:10', 1, 'returned', NULL, NULL, 1368, '2026-09-25 09:24:57'),
(6619, 9882, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-23 13:50:10', 1, 'returned', NULL, NULL, 1368, '2026-09-25 09:24:57'),
(6620, 9882, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-09-23 13:50:10', 1, 'returned', NULL, NULL, 1368, '2026-09-25 09:24:57'),
(6621, 9883, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-304-2776', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-09-29 09:10:38', 1, 'returned', NULL, NULL, 1369, '2026-10-01 08:58:49'),
(6622, 9883, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-09-29 09:10:38', 1, 'returned', NULL, NULL, 1369, '2026-10-01 08:58:49'),
(6623, 9883, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-09-29 09:10:38', 1, 'returned', NULL, NULL, 1369, '2026-10-01 08:58:49'),
(6624, 9884, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-812-7838', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-09-15 09:29:01', 1, 'returned', NULL, NULL, 1369, '2026-09-17 09:01:25'),
(6625, 9884, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-09-15 09:29:01', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1369, '2026-09-17 09:01:25'),
(6626, 9885, 'ตัวเครื่อง', 'main', '7440-601-6062', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-14 08:55:32', 1, 'returned', NULL, NULL, 1368, '2026-09-16 08:49:44'),
(6627, 9885, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-14 08:55:32', 1, 'returned', NULL, NULL, 1368, '2026-09-16 08:49:44'),
(6628, 9885, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-09-14 08:55:32', 1, 'returned', NULL, NULL, 1368, '2026-09-16 08:49:44'),
(6629, 9885, 'ตลับหมึก / หมึก', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1368, '2026-09-14 08:55:32', 1, 'returned', NULL, NULL, 1368, '2026-09-16 08:49:44'),
(6630, 9886, 'ตัวเครื่อง', 'main', '7440-910-4645', 0, 1, 'normal', NULL, NULL, 1368, '2026-10-01 08:56:43', 1, 'returned', NULL, NULL, 1368, '2026-10-02 09:27:24'),
(6631, 9886, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-10-01 08:56:43', 1, 'returned', NULL, NULL, 1368, '2026-10-02 09:27:24'),
(6632, 9887, 'ตัวเครื่อง', 'main', '7440-607-8065', 0, 1, 'normal', NULL, NULL, 1369, '2026-09-15 09:27:59', 1, 'returned', NULL, NULL, 1369, '2026-09-18 09:27:58'),
(6633, 9887, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-09-15 09:27:59', 1, 'returned', NULL, NULL, 1369, '2026-09-18 09:27:58'),
(6634, 9887, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-09-15 09:27:59', 1, 'returned', NULL, NULL, 1369, '2026-09-18 09:27:58'),
(6635, 9888, 'ตัวเครื่อง / เคส', 'main', '7440-953-9688', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-09-22 15:49:00', 1, 'returned', NULL, NULL, 1368, '2026-09-23 08:53:30'),
(6636, 9888, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-22 15:49:00', 1, 'returned', NULL, NULL, 1368, '2026-09-23 08:53:30'),
(6637, 9888, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-09-22 15:49:00', 1, 'returned', NULL, NULL, 1368, '2026-09-23 08:53:30'),
(6638, 9888, 'เมาส์', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1368, '2026-09-22 15:49:00', 1, 'returned', NULL, NULL, 1368, '2026-09-23 08:53:30'),
(6639, 9889, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-113-1739', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-07 08:59:42', 1, 'returned', NULL, NULL, 1368, '2026-09-09 09:30:51'),
(6640, 9889, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-09-07 08:59:42', 0, 'returned', NULL, NULL, 1368, '2026-09-09 09:30:51'),
(6641, 9890, 'ตัวเครื่อง', 'main', '7440-924-6394', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-22 09:23:29', 1, 'returned', NULL, NULL, 1368, '2026-09-22 12:48:24'),
(6642, 9890, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-22 09:23:29', 1, 'returned', NULL, NULL, 1368, '2026-09-22 12:48:24'),
(6643, 9891, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-434-6529', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1369, '2026-10-01 09:14:48', 1, 'returned', NULL, NULL, 1369, '2026-10-05 08:40:00'),
(6644, 9891, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-10-01 09:14:48', 1, 'returned', NULL, NULL, 1369, '2026-10-05 08:40:00'),
(6645, 9891, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-10-01 09:14:48', 1, 'returned', NULL, NULL, 1369, '2026-10-05 08:40:00'),
(6646, 9892, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-834-7154', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-09-29 09:25:52', 1, 'returned', NULL, NULL, 1368, '2026-10-02 08:41:15'),
(6647, 9892, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-09-29 09:25:52', 0, 'returned', NULL, NULL, 1368, '2026-10-02 08:41:15'),
(6648, 9893, 'ตัวเครื่อง / เคส', 'main', '7440-790-1172', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-24 09:18:42', 1, 'returned', NULL, NULL, 1368, '2026-09-28 08:40:00'),
(6649, 9893, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-09-24 09:18:42', 0, 'returned', NULL, NULL, 1368, '2026-09-28 08:40:00'),
(6650, 9894, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-263-4629', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-15 09:42:44', 1, 'returned', NULL, NULL, 1368, '2026-09-16 08:40:20'),
(6651, 9894, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-09-15 09:42:44', 0, 'returned', NULL, NULL, 1368, '2026-09-16 08:40:20'),
(6652, 9895, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-793-1248', 0, 1, 'normal', NULL, NULL, 1369, '2026-09-28 09:50:01', 1, 'returned', NULL, NULL, 1369, '2026-09-28 12:58:22'),
(6653, 9895, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-09-28 09:50:01', 1, 'returned', NULL, NULL, 1369, '2026-09-28 12:58:22'),
(6654, 9895, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-09-28 09:50:01', 1, 'returned', NULL, NULL, 1369, '2026-09-28 12:58:22'),
(6655, 9896, 'ตัวเครื่อง', 'main', '7440-554-3750', 0, 1, 'normal', NULL, NULL, 1368, '2026-09-08 09:36:54', 1, 'returned', NULL, NULL, 1368, '2026-09-10 09:21:06'),
(6656, 9896, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-09-08 09:36:54', 0, 'returned', NULL, NULL, 1368, '2026-09-10 09:21:06'),
(6657, 9896, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-09-08 09:36:54', 1, 'returned', NULL, NULL, 1368, '2026-09-10 09:21:06'),
(6658, 9897, 'ตัวเครื่อง / เคส', 'main', '7440-673-7313', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-09-08 09:28:49', 1, 'returned', NULL, NULL, 1368, '2026-09-09 10:02:30'),
(6659, 9897, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-09-08 09:28:49', 1, 'returned', NULL, NULL, 1368, '2026-09-09 10:02:30'),
(6660, 9897, 'จอภาพ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-09-08 09:28:49', 1, 'returned', NULL, NULL, 1368, '2026-09-09 10:02:30'),
(6661, 9897, 'เมาส์', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1368, '2026-09-08 09:28:49', 1, 'returned', NULL, NULL, 1368, '2026-09-09 10:02:30'),
(6662, 9898, 'ตัวเครื่อง', 'main', '7440-188-9552', 0, 1, 'normal', NULL, NULL, 1369, '2026-10-14 09:24:22', 1, 'returned', NULL, NULL, 1369, '2026-10-15 13:46:22'),
(6663, 9898, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-10-14 09:24:22', 1, 'returned', NULL, NULL, 1369, '2026-10-15 13:46:22');
INSERT INTO `ticket_item_checks` (`id`, `ticket_id`, `item_name`, `item_type`, `serial_no`, `sort_order`, `qty_in`, `condition_in`, `note_in`, `photo_in`, `received_by`, `received_at`, `qty_out`, `result_out`, `note_out`, `photo_out`, `checked_out_by`, `checked_out_at`) VALUES
(6664, 9899, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-123-2397', 0, 1, 'normal', NULL, NULL, 1369, '2026-10-09 09:41:33', 1, 'returned', NULL, NULL, 1369, '2026-10-12 08:40:00'),
(6665, 9899, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-10-09 09:41:33', 1, 'returned', NULL, NULL, 1369, '2026-10-12 08:40:00'),
(6666, 9900, 'ตัวเครื่อง', 'main', '7440-138-3451', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1368, '2026-10-07 08:51:54', 1, 'returned', NULL, NULL, 1368, '2026-10-09 12:56:04'),
(6667, 9900, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1368, '2026-10-07 08:51:54', 0, 'returned', NULL, NULL, 1368, '2026-10-09 12:56:04'),
(6668, 9900, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-10-07 08:51:54', 1, 'returned', NULL, NULL, 1368, '2026-10-09 12:56:04'),
(6669, 9901, 'ตัวเครื่อง / เคส', 'main', '7440-399-7108', 0, 1, 'normal', NULL, NULL, 1368, '2026-10-05 15:34:27', 1, 'returned', NULL, NULL, 1368, '2026-10-07 09:06:56'),
(6670, 9901, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-10-05 15:34:27', 1, 'returned', NULL, NULL, 1368, '2026-10-07 09:06:56'),
(6671, 9901, 'จอภาพ', 'accessory', NULL, 2, 0, 'normal', NULL, NULL, 1368, '2026-10-05 15:34:27', 0, 'returned', NULL, NULL, 1368, '2026-10-07 09:06:56'),
(6672, 9901, 'เมาส์', 'accessory', NULL, 3, 1, 'normal', NULL, NULL, 1368, '2026-10-05 15:34:27', 1, 'returned', NULL, NULL, 1368, '2026-10-07 09:06:56'),
(6673, 9902, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-187-8026', 0, 1, 'normal', NULL, NULL, 1369, '2026-10-28 09:35:26', 1, 'returned', NULL, NULL, 1369, '2026-10-29 09:21:02'),
(6674, 9902, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-10-28 09:35:26', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1369, '2026-10-29 09:21:02'),
(6675, 9903, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-352-1789', 0, 1, 'normal', NULL, NULL, 1369, '2026-10-08 10:24:17', 1, 'returned', NULL, NULL, 1369, '2026-10-08 12:16:50'),
(6676, 9903, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-10-08 10:24:17', 1, 'returned', NULL, NULL, 1369, '2026-10-08 12:16:50'),
(6677, 9903, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1369, '2026-10-08 10:24:17', 1, 'returned', NULL, NULL, 1369, '2026-10-08 12:16:50'),
(6678, 9904, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-394-5320', 0, 1, 'normal', NULL, NULL, 1368, '2026-10-23 09:36:13', 1, 'returned', NULL, NULL, 1368, '2026-10-26 08:40:00'),
(6679, 9904, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-10-23 09:36:13', 1, 'returned', NULL, NULL, 1368, '2026-10-26 08:40:00'),
(6680, 9904, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1368, '2026-10-23 09:36:13', 1, 'returned', NULL, NULL, 1368, '2026-10-26 08:40:00'),
(6681, 9905, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-329-5855', 0, 1, 'normal', NULL, NULL, 1369, '2026-10-29 15:56:24', 1, 'returned', NULL, NULL, 1369, '2026-11-02 08:40:00'),
(6682, 9905, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-10-29 15:56:24', 1, 'returned', NULL, NULL, 1369, '2026-11-02 08:40:00'),
(6683, 9906, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-354-6124', 0, 1, 'normal', NULL, NULL, 1369, '2026-10-12 08:52:13', 1, 'returned', NULL, NULL, 1369, '2026-10-15 08:50:17'),
(6684, 9906, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1369, '2026-10-12 08:52:13', 0, 'returned', NULL, NULL, 1369, '2026-10-15 08:50:17'),
(6685, 9907, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-308-0029', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1370, '2026-11-02 09:57:17', NULL, NULL, NULL, NULL, NULL, NULL),
(6686, 9907, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-02 09:57:17', NULL, NULL, NULL, NULL, NULL, NULL),
(6687, 9908, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-265-0604', 0, 1, 'normal', NULL, NULL, 1368, '2026-10-27 09:43:42', 1, 'returned', NULL, NULL, 1368, '2026-10-29 08:48:28'),
(6688, 9908, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-10-27 09:43:42', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1368, '2026-10-29 08:48:28'),
(6689, 9909, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-126-5765', 0, 1, 'normal', NULL, NULL, 1368, '2026-10-20 09:46:35', 1, 'returned', NULL, NULL, 1368, '2026-10-21 09:22:29'),
(6690, 9909, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1368, '2026-10-20 09:46:35', 1, 'returned', NULL, NULL, 1368, '2026-10-21 09:22:29'),
(6691, 9910, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-372-1410', 0, 1, 'normal', NULL, NULL, 1369, '2026-10-27 09:35:36', 1, 'returned', NULL, NULL, 1369, '2026-10-30 09:17:50'),
(6692, 9910, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1369, '2026-10-27 09:35:36', 1, 'returned', NULL, NULL, 1369, '2026-10-30 09:17:50'),
(6693, 9911, 'ตัวเครื่อง', 'main', '7440-772-8775', 0, 1, 'normal', NULL, NULL, 1371, '2026-11-12 09:20:47', NULL, NULL, NULL, NULL, NULL, NULL),
(6694, 9911, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1371, '2026-11-12 09:20:47', NULL, NULL, NULL, NULL, NULL, NULL),
(6695, 9911, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 0, 'normal', NULL, NULL, 1371, '2026-11-12 09:20:47', NULL, NULL, NULL, NULL, NULL, NULL),
(6696, 9913, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-201-4405', 0, 1, 'normal', NULL, NULL, 1372, '2026-11-06 09:05:50', NULL, NULL, NULL, NULL, NULL, NULL),
(6697, 9913, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1372, '2026-11-06 09:05:50', NULL, NULL, NULL, NULL, NULL, NULL),
(6698, 9914, 'ตัวเครื่อง', 'main', '7440-136-8065', 0, 1, 'normal', NULL, NULL, 1371, '2026-11-03 12:16:27', 1, 'returned', NULL, NULL, 1371, '2026-11-04 09:15:43'),
(6699, 9914, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1371, '2026-11-03 12:16:27', 1, 'returned', NULL, NULL, 1371, '2026-11-04 09:15:43'),
(6700, 9917, 'ตัวเครื่อง', 'main', '7440-593-5688', 0, 1, 'normal', NULL, NULL, 1372, '2026-11-13 08:45:22', NULL, NULL, NULL, NULL, NULL, NULL),
(6701, 9917, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1372, '2026-11-13 08:45:22', NULL, NULL, NULL, NULL, NULL, NULL),
(6702, 9918, 'ตัวเครื่อง', 'main', '7440-913-5960', 0, 1, 'normal', NULL, NULL, 1371, '2026-11-11 09:33:09', NULL, NULL, NULL, NULL, NULL, NULL),
(6703, 9918, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1371, '2026-11-11 09:33:09', NULL, NULL, NULL, NULL, NULL, NULL),
(6704, 9918, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1371, '2026-11-11 09:33:09', NULL, NULL, NULL, NULL, NULL, NULL),
(6705, 9919, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-506-0216', 0, 1, 'normal', NULL, NULL, 1370, '2026-11-02 09:54:02', 1, 'returned', NULL, NULL, 1370, '2026-11-02 14:48:11'),
(6706, 9919, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1370, '2026-11-02 09:54:02', 0, 'returned', NULL, NULL, 1370, '2026-11-02 14:48:11'),
(6707, 9923, 'ตัวเครื่อง', 'main', '7440-315-5906', 0, 1, 'normal', NULL, NULL, 1370, '2026-11-05 09:27:04', 1, 'returned', NULL, NULL, 1370, '2026-11-06 09:26:38'),
(6708, 9923, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-05 09:27:04', 1, 'returned', NULL, NULL, 1370, '2026-11-06 09:26:38'),
(6709, 9923, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1370, '2026-11-05 09:27:04', 1, 'returned', NULL, NULL, 1370, '2026-11-06 09:26:38'),
(6710, 9925, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', '7440-356-3783', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1370, '2026-11-04 14:33:44', NULL, NULL, NULL, NULL, NULL, NULL),
(6711, 9925, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-04 14:33:44', NULL, NULL, NULL, NULL, NULL, NULL),
(6712, 9928, 'ตัวเครื่อง / เคส', 'main', '7440-263-6282', 0, 1, 'normal', NULL, NULL, 1370, '2026-11-13 09:41:31', NULL, NULL, NULL, NULL, NULL, NULL),
(6713, 9928, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-13 09:41:31', NULL, NULL, NULL, NULL, NULL, NULL),
(6714, 9929, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-260-3575', 0, 1, 'normal', NULL, NULL, 1370, '2026-11-06 10:39:08', 1, 'returned', NULL, NULL, 1370, '2026-11-06 11:47:30'),
(6715, 9929, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-06 10:39:08', 1, 'returned', NULL, NULL, 1370, '2026-11-06 11:47:30'),
(6716, 9929, 'กระเป๋า', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1370, '2026-11-06 10:39:08', 0, 'missing', 'ไม่พบตอนส่งคืน', NULL, 1370, '2026-11-06 11:47:30'),
(6717, 9930, 'ตัวเครื่อง', 'main', '7440-869-4849', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1370, '2026-11-06 09:10:34', NULL, NULL, NULL, NULL, NULL, NULL),
(6718, 9930, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-06 09:10:34', NULL, NULL, NULL, NULL, NULL, NULL),
(6719, 9931, 'ตัวเครื่อง', 'main', '7440-282-6935', 0, 1, 'normal', NULL, NULL, 1371, '2026-11-06 08:52:23', 1, 'returned', NULL, NULL, 1371, '2026-11-09 08:50:37'),
(6720, 9931, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1371, '2026-11-06 08:52:23', 0, 'returned', NULL, NULL, 1371, '2026-11-09 08:50:37'),
(6721, 9933, 'ตัวเครื่อง', 'main', '7440-826-6174', 0, 1, 'normal', NULL, NULL, 1370, '2026-11-12 10:43:09', 1, 'returned', NULL, NULL, 1370, '2026-11-12 14:08:38'),
(6722, 9933, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-12 10:43:09', 1, 'returned', NULL, NULL, 1370, '2026-11-12 14:08:38'),
(6723, 9934, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-713-3313', 0, 1, 'normal', NULL, NULL, 1370, '2026-11-14 11:15:32', 1, 'returned', NULL, NULL, 1370, '2026-11-14 12:32:11'),
(6724, 9934, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1370, '2026-11-14 11:15:32', 0, 'returned', NULL, NULL, 1370, '2026-11-14 12:32:11'),
(6725, 9935, 'ตัวเครื่อง', 'main', '7440-387-3151', 0, 1, 'normal', NULL, NULL, 1371, '2026-11-14 11:48:58', 1, 'returned', NULL, NULL, 1371, '2026-11-14 12:59:23'),
(6726, 9935, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1371, '2026-11-14 11:48:58', 1, 'returned', NULL, NULL, 1371, '2026-11-14 12:59:23'),
(6727, 9936, 'ตัวเครื่อง / โน้ตบุ๊ก', 'main', '7440-943-8562', 0, 1, 'normal', NULL, NULL, 1372, '2026-11-14 12:17:21', 1, 'returned', NULL, NULL, 1372, '2026-11-14 14:19:23'),
(6728, 9936, 'สายชาร์จ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1372, '2026-11-14 12:17:21', 1, 'returned', NULL, NULL, 1372, '2026-11-14 14:19:23'),
(6729, 9937, 'อุปกรณ์เครือข่าย (เราเตอร์/สวิตช์/AP)', 'main', '7440-651-3467', 0, 1, 'normal', NULL, NULL, 1370, '2026-11-14 12:29:57', 1, 'returned', NULL, NULL, 1370, '2026-11-14 14:49:23'),
(6730, 9937, 'อะแดปเตอร์ไฟ', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-14 12:29:57', 1, 'returned', NULL, NULL, 1370, '2026-11-14 14:49:23'),
(6731, 9937, 'สาย LAN', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1370, '2026-11-14 12:29:57', 1, 'returned', NULL, NULL, 1370, '2026-11-14 14:49:23'),
(6732, 9938, 'ตัวเครื่อง', 'main', '7440-579-7210', 0, 1, 'scratched', 'มีรอยขีดข่วนเดิม', NULL, 1371, '2026-11-14 09:47:29', 1, 'returned', NULL, NULL, 1371, '2026-11-14 12:29:23'),
(6733, 9938, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1371, '2026-11-14 09:47:29', 1, 'returned', NULL, NULL, 1371, '2026-11-14 12:29:23'),
(6734, 9938, 'สาย USB / สายสัญญาณ', 'accessory', NULL, 2, 1, 'normal', NULL, NULL, 1371, '2026-11-14 09:47:29', 1, 'returned', NULL, NULL, 1371, '2026-11-14 12:29:23'),
(6735, 9939, 'ตัวเครื่อง', 'main', '7440-265-8608', 0, 1, 'normal', NULL, NULL, 1372, '2026-11-14 13:20:08', NULL, NULL, NULL, NULL, NULL, NULL),
(6736, 9939, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1372, '2026-11-14 13:20:08', NULL, NULL, NULL, NULL, NULL, NULL),
(6737, 9940, 'ตัวเครื่อง', 'main', '7440-537-3340', 0, 1, 'normal', NULL, NULL, 1370, '2026-11-14 14:18:40', NULL, NULL, NULL, NULL, NULL, NULL),
(6738, 9940, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 1, 'normal', NULL, NULL, 1370, '2026-11-14 14:18:40', NULL, NULL, NULL, NULL, NULL, NULL),
(6739, 9915, 'ตัวเครื่อง / อุปกรณ์หลัก', 'main', NULL, 0, 1, 'normal', NULL, NULL, 1370, '2026-11-14 16:13:17', 1, 'returned', NULL, NULL, 1370, '2026-11-14 16:16:24'),
(6740, 9915, 'สายไฟ / อะแดปเตอร์', 'accessory', NULL, 1, 0, 'normal', NULL, NULL, 1370, '2026-11-14 16:13:17', 0, 'returned', NULL, NULL, 1370, '2026-11-14 16:16:24');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_logs`
--

CREATE TABLE `ticket_logs` (
  `id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `old_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `action_by` int DEFAULT NULL,
  `source` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เหตุผล/รายละเอียดประกอบการเปลี่ยนสถานะ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_logs`
--

INSERT INTO `ticket_logs` (`id`, `ticket_id`, `old_status`, `new_status`, `action_by`, `source`, `created_at`, `note`) VALUES
(26087, 6997, NULL, 'pending', 9, 'web', '2026-11-13 16:52:36', NULL),
(37714, 6997, 'pending', 'accepted', NULL, 'staff', '2026-11-14 05:52:42', NULL),
(46073, 9777, NULL, 'pending', 1430, 'web', '2026-05-15 10:44:18', NULL),
(46074, 9777, 'pending', 'accepted', 1368, 'staff', '2026-05-18 08:40:00', NULL),
(46075, 9777, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-18 08:55:24', NULL),
(46076, 9777, 'awaiting_intake_sign', 'in_progress', 1430, 'requester', '2026-05-18 10:13:49', NULL),
(46077, 9777, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-21 08:45:13', NULL),
(46078, 9777, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-21 09:23:46', NULL),
(46079, 9777, 'awaiting_requester_sign', 'awaiting_admin_sign', 1430, 'requester', '2026-05-21 10:34:22', NULL),
(46080, 9777, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-21 11:44:59', NULL),
(46081, 9778, NULL, 'pending', 1431, 'web', '2026-05-06 08:35:40', NULL),
(46082, 9778, 'pending', 'accepted', 1369, 'staff', '2026-05-06 09:46:08', NULL),
(46083, 9778, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-06 10:01:32', NULL),
(46084, 9778, 'awaiting_intake_sign', 'in_progress', 1431, 'requester', '2026-05-06 11:00:16', NULL),
(46085, 9778, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-06 11:42:58', NULL),
(46086, 9778, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-06 12:01:28', NULL),
(46087, 9778, 'awaiting_requester_sign', 'awaiting_admin_sign', 1431, 'requester', '2026-05-06 15:14:29', NULL),
(46088, 9778, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-07 14:46:53', NULL),
(46089, 9779, NULL, 'pending', 1429, 'web', '2026-05-11 08:40:00', NULL),
(46090, 9779, 'pending', 'accepted', 1368, 'staff', '2026-05-12 09:25:59', NULL),
(46091, 9779, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-12 09:40:38', NULL),
(46092, 9779, 'awaiting_intake_sign', 'in_progress', 1429, 'requester', '2026-05-12 10:07:49', NULL),
(46093, 9779, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-12 12:06:23', NULL),
(46094, 9779, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-12 12:43:58', NULL),
(46095, 9779, 'awaiting_requester_sign', 'awaiting_admin_sign', 1429, 'requester', '2026-05-12 14:03:17', NULL),
(46096, 9779, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-14 08:53:02', NULL),
(46097, 9780, NULL, 'pending', 1409, 'web', '2026-05-18 13:49:36', NULL),
(46098, 9780, 'pending', 'accepted', 1368, 'staff', '2026-05-19 10:53:18', NULL),
(46099, 9780, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-19 11:04:22', NULL),
(46100, 9780, 'awaiting_intake_sign', 'in_progress', 1409, 'requester', '2026-05-19 12:06:06', NULL),
(46101, 9780, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-20 10:35:52', NULL),
(46102, 9780, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-20 11:35:18', NULL),
(46103, 9780, 'awaiting_requester_sign', 'awaiting_admin_sign', 1409, 'requester', '2026-05-21 07:58:54', NULL),
(46104, 9780, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-21 12:50:59', NULL),
(46105, 9781, NULL, 'pending', 1388, 'web', '2026-05-26 08:39:43', NULL),
(46106, 9781, 'pending', 'accepted', 1369, 'staff', '2026-05-26 11:41:04', NULL),
(46107, 9781, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-26 11:52:12', NULL),
(46108, 9781, 'awaiting_intake_sign', 'in_progress', 1388, 'requester', '2026-05-26 12:52:39', NULL),
(46109, 9781, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-27 09:24:04', NULL),
(46110, 9781, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-27 10:12:05', NULL),
(46111, 9781, 'awaiting_requester_sign', 'awaiting_admin_sign', 1388, 'requester', '2026-05-28 06:51:34', NULL),
(46112, 9781, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-28 09:30:23', NULL),
(46113, 9782, NULL, 'pending', 1408, 'web', '2026-05-25 09:23:24', NULL),
(46114, 9782, 'pending', 'accepted', 1369, 'staff', '2026-05-25 16:11:15', NULL),
(46115, 9782, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-25 16:20:43', NULL),
(46116, 9782, 'awaiting_intake_sign', 'in_progress', 1408, 'requester', '2026-05-25 16:53:10', NULL),
(46117, 9782, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-26 10:34:19', NULL),
(46118, 9782, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-26 10:45:23', NULL),
(46119, 9782, 'awaiting_requester_sign', 'awaiting_admin_sign', 1408, 'requester', '2026-05-26 23:26:29', NULL),
(46120, 9782, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-28 08:43:37', NULL),
(46121, 9783, NULL, 'pending', 1417, 'web', '2026-05-05 09:32:33', NULL),
(46122, 9783, 'pending', 'accepted', 1368, 'staff', '2026-05-06 09:03:25', NULL),
(46123, 9783, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-06 09:16:15', NULL),
(46124, 9783, 'awaiting_intake_sign', 'in_progress', 1417, 'requester', '2026-05-06 09:39:47', NULL),
(46125, 9783, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-08 15:22:09', NULL),
(46126, 9783, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-08 15:45:42', NULL),
(46127, 9783, 'awaiting_requester_sign', 'awaiting_admin_sign', 1417, 'requester', '2026-05-09 07:19:01', NULL),
(46128, 9783, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-11 08:40:00', NULL),
(46129, 9784, NULL, 'pending', 1411, 'web', '2026-05-04 08:40:00', NULL),
(46130, 9784, 'pending', 'accepted', 1368, 'staff', '2026-05-04 10:06:35', NULL),
(46131, 9784, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-04 10:22:28', NULL),
(46132, 9784, 'awaiting_intake_sign', 'in_progress', 1411, 'requester', '2026-05-04 11:21:28', NULL),
(46133, 9784, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-04 13:06:29', NULL),
(46134, 9784, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-04 13:20:31', NULL),
(46135, 9784, 'awaiting_requester_sign', 'awaiting_admin_sign', 1411, 'requester', '2026-05-05 07:35:49', NULL),
(46136, 9784, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-05 13:35:53', NULL),
(46137, 9785, NULL, 'pending', 1418, 'web', '2026-05-20 15:08:33', NULL),
(46138, 9785, 'pending', 'cancelled', 1418, 'web', '2026-05-21 08:38:03', NULL),
(46139, 9786, NULL, 'pending', 1399, 'web', '2026-05-12 15:54:59', NULL),
(46140, 9786, 'pending', 'accepted', 1369, 'staff', '2026-05-13 09:15:57', NULL),
(46141, 9786, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-13 09:34:41', NULL),
(46142, 9786, 'awaiting_intake_sign', 'in_progress', 1399, 'requester', '2026-05-13 10:05:45', NULL),
(46143, 9786, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-15 08:48:31', NULL),
(46144, 9786, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-15 09:17:45', NULL),
(46145, 9786, 'awaiting_requester_sign', 'awaiting_admin_sign', 1399, 'requester', '2026-05-15 21:07:29', NULL),
(46146, 9786, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-18 08:40:00', NULL),
(46147, 9787, NULL, 'pending', 1408, 'web', '2026-05-22 09:29:43', NULL),
(46148, 9787, 'pending', 'accepted', 1369, 'staff', '2026-05-25 08:40:00', NULL),
(46149, 9787, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-25 08:59:49', NULL),
(46150, 9787, 'awaiting_intake_sign', 'in_progress', 1408, 'requester', '2026-05-25 10:38:10', NULL),
(46151, 9787, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-26 09:20:54', NULL),
(46152, 9787, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-26 09:49:42', NULL),
(46153, 9787, 'awaiting_requester_sign', 'awaiting_admin_sign', 1408, 'requester', '2026-05-26 19:37:46', NULL),
(46154, 9787, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-28 08:42:16', NULL),
(46155, 9788, NULL, 'pending', 1399, 'web', '2026-05-25 08:40:00', NULL),
(46156, 9788, 'pending', 'accepted', 1368, 'staff', '2026-05-26 09:09:37', NULL),
(46157, 9788, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-26 09:22:31', NULL),
(46158, 9788, 'awaiting_intake_sign', 'in_progress', 1399, 'requester', '2026-05-26 11:22:20', NULL),
(46159, 9788, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-29 09:07:42', NULL),
(46160, 9788, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-29 09:31:52', NULL),
(46161, 9788, 'awaiting_requester_sign', 'awaiting_admin_sign', 1399, 'requester', '2026-05-29 18:24:38', NULL),
(46162, 9788, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-01 08:40:00', NULL),
(46163, 9789, NULL, 'pending', 1411, 'web', '2026-05-11 08:40:00', NULL),
(46164, 9789, 'pending', 'accepted', 1369, 'staff', '2026-05-11 09:34:37', NULL),
(46165, 9789, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-11 09:53:39', NULL),
(46166, 9789, 'awaiting_intake_sign', 'in_progress', 1411, 'requester', '2026-05-11 10:08:56', NULL),
(46167, 9789, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-11 12:05:00', NULL),
(46168, 9789, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-11 12:36:32', NULL),
(46169, 9789, 'awaiting_requester_sign', 'awaiting_admin_sign', 1411, 'requester', '2026-05-11 12:57:02', NULL),
(46170, 9789, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-11 13:17:33', NULL),
(46171, 9790, NULL, 'pending', 1415, 'web', '2026-05-21 09:23:36', NULL),
(46172, 9790, 'pending', 'accepted', 1368, 'staff', '2026-05-21 10:16:08', NULL),
(46173, 9790, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-21 10:29:25', NULL),
(46174, 9790, 'awaiting_intake_sign', 'in_progress', 1415, 'requester', '2026-05-21 10:55:36', NULL),
(46175, 9790, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-21 13:50:59', NULL),
(46176, 9790, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-21 14:10:46', NULL),
(46177, 9790, 'awaiting_requester_sign', 'awaiting_admin_sign', 1415, 'requester', '2026-05-21 18:46:09', NULL),
(46178, 9790, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-22 09:22:00', NULL),
(46179, 9791, NULL, 'pending', 1378, 'web', '2026-05-08 12:31:41', NULL),
(46180, 9791, 'pending', 'accepted', 1369, 'staff', '2026-05-08 13:31:16', NULL),
(46181, 9791, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-08 13:37:40', NULL),
(46182, 9791, 'awaiting_intake_sign', 'in_progress', 1378, 'requester', '2026-05-08 14:22:00', NULL),
(46183, 9791, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-08 14:31:34', NULL),
(46184, 9791, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-08 15:25:52', NULL),
(46185, 9791, 'awaiting_requester_sign', 'awaiting_admin_sign', 1378, 'requester', '2026-05-08 19:49:09', NULL),
(46186, 9791, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-11 08:40:00', NULL),
(46187, 9792, NULL, 'pending', 1426, 'web', '2026-05-08 14:53:08', NULL),
(46188, 9792, 'pending', 'accepted', 1368, 'staff', '2026-05-11 08:40:00', NULL),
(46189, 9792, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-11 08:53:15', NULL),
(46190, 9792, 'awaiting_intake_sign', 'in_progress', 1426, 'requester', '2026-05-11 10:08:52', NULL),
(46191, 9792, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-13 09:33:47', NULL),
(46192, 9792, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-13 09:45:36', NULL),
(46193, 9792, 'awaiting_requester_sign', 'awaiting_admin_sign', 1426, 'requester', '2026-05-14 09:02:33', NULL),
(46194, 9792, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-15 09:10:54', NULL),
(46195, 9793, NULL, 'pending', 1376, 'web', '2026-05-06 08:36:27', NULL),
(46196, 9793, 'pending', 'accepted', 1369, 'staff', '2026-05-07 08:43:08', NULL),
(46197, 9793, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-07 08:51:29', NULL),
(46198, 9793, 'awaiting_intake_sign', 'in_progress', 1376, 'requester', '2026-05-07 09:27:12', NULL),
(46199, 9793, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-08 15:25:30', NULL),
(46200, 9793, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-08 16:09:20', NULL),
(46201, 9793, 'awaiting_requester_sign', 'awaiting_admin_sign', 1376, 'requester', '2026-05-09 05:55:05', NULL),
(46202, 9793, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-11 08:40:00', NULL),
(46203, 9794, NULL, 'pending', 1376, 'web', '2026-05-08 09:16:36', NULL),
(46204, 9794, 'pending', 'accepted', 1368, 'staff', '2026-05-11 08:40:00', NULL),
(46205, 9794, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-11 08:57:44', NULL),
(46206, 9794, 'awaiting_intake_sign', 'in_progress', 1376, 'requester', '2026-05-11 10:39:28', NULL),
(46207, 9794, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-14 09:01:58', NULL),
(46208, 9794, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-14 09:34:37', NULL),
(46209, 9794, 'awaiting_requester_sign', 'awaiting_admin_sign', 1376, 'requester', '2026-05-14 13:59:13', NULL),
(46210, 9794, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-18 08:40:00', NULL),
(46211, 9795, NULL, 'pending', 1407, 'web', '2026-05-11 08:40:00', NULL),
(46212, 9795, 'pending', 'accepted', 1369, 'staff', '2026-05-12 09:21:28', NULL),
(46213, 9795, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-12 09:29:20', NULL),
(46214, 9795, 'awaiting_intake_sign', 'in_progress', 1407, 'requester', '2026-05-12 09:53:29', NULL),
(46215, 9795, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-14 15:04:40', NULL),
(46216, 9795, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-14 16:02:18', NULL),
(46217, 9795, 'awaiting_requester_sign', 'awaiting_admin_sign', 1407, 'requester', '2026-05-15 11:40:44', NULL),
(46218, 9795, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-18 08:40:00', NULL),
(46219, 9796, NULL, 'pending', 1392, 'web', '2026-05-14 09:28:36', NULL),
(46220, 9796, 'pending', 'accepted', 1368, 'staff', '2026-05-15 09:22:06', NULL),
(46221, 9796, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-15 09:36:12', NULL),
(46222, 9796, 'awaiting_intake_sign', 'in_progress', 1392, 'requester', '2026-05-15 10:50:51', NULL),
(46223, 9796, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-18 08:40:00', NULL),
(46224, 9796, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-18 08:58:20', NULL),
(46225, 9796, 'awaiting_requester_sign', 'awaiting_admin_sign', 1392, 'requester', '2026-05-19 03:57:51', NULL),
(46226, 9796, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-19 14:17:01', NULL),
(46227, 9797, NULL, 'pending', 1390, 'web', '2026-05-22 08:58:14', NULL),
(46228, 9797, 'pending', 'accepted', 1369, 'staff', '2026-05-22 11:38:29', NULL),
(46229, 9797, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-22 11:57:26', NULL),
(46230, 9797, 'awaiting_intake_sign', 'in_progress', 1390, 'requester', '2026-05-22 12:07:31', NULL),
(46231, 9797, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-25 09:26:57', NULL),
(46232, 9797, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-25 09:44:41', NULL),
(46233, 9797, 'awaiting_requester_sign', 'awaiting_admin_sign', 1390, 'requester', '2026-05-26 08:09:09', NULL),
(46234, 9797, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-26 09:30:41', NULL),
(46235, 9798, NULL, 'pending', 1430, 'web', '2026-05-18 08:40:00', NULL),
(46236, 9798, 'pending', 'accepted', 1368, 'staff', '2026-05-18 15:11:50', NULL),
(46237, 9798, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-18 15:23:48', NULL),
(46238, 9798, 'awaiting_intake_sign', 'in_progress', 1430, 'requester', '2026-05-18 16:03:18', NULL),
(46239, 9798, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-21 09:34:03', NULL),
(46240, 9798, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-21 09:45:34', NULL),
(46241, 9798, 'awaiting_requester_sign', 'awaiting_admin_sign', 1430, 'requester', '2026-05-21 17:27:46', NULL),
(46242, 9798, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-25 08:40:00', NULL),
(46243, 9799, NULL, 'pending', 1385, 'web', '2026-05-12 09:23:27', NULL),
(46244, 9799, 'pending', 'cancelled', 1385, 'web', '2026-05-12 11:41:07', NULL),
(46245, 9800, NULL, 'pending', 1416, 'web', '2026-05-15 09:34:29', NULL),
(46246, 9800, 'pending', 'accepted', 1368, 'staff', '2026-05-15 09:47:15', NULL),
(46247, 9800, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-15 10:04:48', NULL),
(46248, 9800, 'awaiting_intake_sign', 'in_progress', 1416, 'requester', '2026-05-15 10:20:51', NULL),
(46249, 9800, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-15 11:48:16', NULL),
(46250, 9800, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-15 12:32:00', NULL),
(46251, 9800, 'awaiting_requester_sign', 'awaiting_admin_sign', 1416, 'requester', '2026-05-15 22:27:35', NULL),
(46252, 9800, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-18 08:40:00', NULL),
(46253, 9801, NULL, 'pending', 1390, 'web', '2026-05-14 11:18:19', NULL),
(46254, 9801, 'pending', 'accepted', 1368, 'staff', '2026-05-15 08:48:06', NULL),
(46255, 9801, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-05-15 08:57:46', NULL),
(46256, 9801, 'awaiting_intake_sign', 'in_progress', 1390, 'requester', '2026-05-15 10:33:41', NULL),
(46257, 9801, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-05-18 08:40:00', NULL),
(46258, 9801, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-05-18 09:07:59', NULL),
(46259, 9801, 'awaiting_requester_sign', 'awaiting_admin_sign', 1390, 'requester', '2026-05-18 19:11:04', NULL),
(46260, 9801, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-19 08:56:53', NULL),
(46261, 9802, NULL, 'pending', 1417, 'web', '2026-05-11 09:09:11', NULL),
(46262, 9802, 'pending', 'accepted', 1369, 'staff', '2026-05-11 10:28:39', NULL),
(46263, 9802, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-11 10:41:17', NULL),
(46264, 9802, 'awaiting_intake_sign', 'in_progress', 1417, 'requester', '2026-05-11 12:13:03', NULL),
(46265, 9802, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-13 09:31:35', NULL),
(46266, 9802, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-13 10:15:01', NULL),
(46267, 9802, 'awaiting_requester_sign', 'awaiting_admin_sign', 1417, 'requester', '2026-05-14 08:19:56', NULL),
(46268, 9802, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-05-15 09:12:19', NULL),
(46269, 9803, NULL, 'pending', 1409, 'web', '2026-05-19 09:13:44', NULL),
(46270, 9803, 'pending', 'accepted', 1369, 'staff', '2026-05-19 16:08:42', NULL),
(46271, 9803, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-05-19 16:13:51', NULL),
(46272, 9803, 'awaiting_intake_sign', 'in_progress', 1409, 'requester', '2026-05-19 16:26:16', NULL),
(46273, 9803, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-05-21 09:02:40', NULL),
(46274, 9803, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-05-21 09:15:26', NULL),
(46275, 9803, 'awaiting_requester_sign', 'awaiting_admin_sign', 1409, 'requester', '2026-05-21 11:26:34', NULL),
(46276, 9803, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-05-22 08:47:23', NULL),
(46277, 9804, NULL, 'pending', 1404, 'web', '2026-06-15 08:40:00', NULL),
(46278, 9804, 'pending', 'accepted', 1368, 'staff', '2026-06-16 09:07:30', NULL),
(46279, 9804, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-16 09:16:12', NULL),
(46280, 9804, 'awaiting_intake_sign', 'in_progress', 1404, 'requester', '2026-06-16 11:01:06', NULL),
(46281, 9804, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-16 15:53:29', NULL),
(46282, 9804, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-16 16:04:11', NULL),
(46283, 9804, 'awaiting_requester_sign', 'awaiting_admin_sign', 1404, 'requester', '2026-06-16 22:31:57', NULL),
(46284, 9804, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-18 15:44:48', NULL),
(46285, 9805, NULL, 'pending', 1417, 'web', '2026-06-18 09:07:14', NULL),
(46286, 9805, 'pending', 'cancelled', 1417, 'web', '2026-06-19 09:00:53', NULL),
(46287, 9806, NULL, 'pending', 1382, 'web', '2026-06-15 08:40:00', NULL),
(46288, 9806, 'pending', 'accepted', 1369, 'staff', '2026-06-16 08:41:27', NULL),
(46289, 9806, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-16 08:51:06', NULL),
(46290, 9806, 'awaiting_intake_sign', 'in_progress', 1382, 'requester', '2026-06-16 10:36:56', NULL),
(46291, 9806, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-17 09:27:26', NULL),
(46292, 9806, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-17 10:05:50', NULL),
(46293, 9806, 'awaiting_requester_sign', 'awaiting_admin_sign', 1382, 'requester', '2026-06-17 12:48:26', NULL),
(46294, 9806, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-18 08:45:45', NULL),
(46295, 9807, NULL, 'pending', 1431, 'web', '2026-06-11 08:56:33', NULL),
(46296, 9807, 'pending', 'accepted', 1368, 'staff', '2026-06-12 09:29:54', NULL),
(46297, 9807, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-12 09:37:50', NULL),
(46298, 9807, 'awaiting_intake_sign', 'in_progress', 1431, 'requester', '2026-06-12 10:24:17', NULL),
(46299, 9807, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-15 09:34:22', NULL),
(46300, 9807, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-15 10:25:51', NULL),
(46301, 9807, 'awaiting_requester_sign', 'awaiting_admin_sign', 1431, 'requester', '2026-06-15 11:02:59', NULL),
(46302, 9807, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-16 08:43:43', NULL),
(46303, 9808, NULL, 'pending', 1391, 'web', '2026-06-22 08:40:00', NULL),
(46304, 9808, 'pending', 'accepted', 1369, 'staff', '2026-06-23 08:54:12', NULL),
(46305, 9808, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-23 09:05:46', NULL),
(46306, 9808, 'awaiting_intake_sign', 'in_progress', 1391, 'requester', '2026-06-23 09:28:54', NULL),
(46307, 9808, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-25 09:32:25', NULL),
(46308, 9808, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-25 09:46:48', NULL),
(46309, 9808, 'awaiting_requester_sign', 'awaiting_admin_sign', 1391, 'requester', '2026-06-25 11:12:16', NULL),
(46310, 9808, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-25 12:37:45', NULL),
(46311, 9809, NULL, 'pending', 1375, 'web', '2026-06-15 08:40:00', NULL),
(46312, 9809, 'pending', 'accepted', 1369, 'staff', '2026-06-16 08:57:59', NULL),
(46313, 9809, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-16 09:08:42', NULL),
(46314, 9809, 'awaiting_intake_sign', 'in_progress', 1375, 'requester', '2026-06-16 10:28:32', NULL),
(46315, 9809, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-17 09:30:16', NULL),
(46316, 9809, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-17 09:50:52', NULL),
(46317, 9809, 'awaiting_requester_sign', 'awaiting_admin_sign', 1375, 'requester', '2026-06-17 19:05:58', NULL),
(46318, 9809, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-19 08:41:46', NULL),
(46319, 9810, NULL, 'pending', 1399, 'web', '2026-06-15 08:54:50', NULL),
(46320, 9810, 'pending', 'accepted', 1369, 'staff', '2026-06-15 09:56:36', NULL),
(46321, 9810, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-15 10:15:02', NULL),
(46322, 9810, 'awaiting_intake_sign', 'in_progress', 1399, 'requester', '2026-06-15 11:14:46', NULL),
(46323, 9810, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-15 13:52:44', NULL),
(46324, 9810, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-15 14:17:56', NULL),
(46325, 9810, 'awaiting_requester_sign', 'awaiting_admin_sign', 1366, 'admin', '2026-06-16 09:42:11', NULL),
(46326, 9810, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-17 12:33:33', NULL),
(46327, 9811, NULL, 'pending', 1383, 'web', '2026-06-23 14:37:58', NULL),
(46328, 9811, 'pending', 'accepted', 1368, 'staff', '2026-06-24 09:03:09', NULL),
(46329, 9811, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-24 09:20:39', NULL),
(46330, 9811, 'awaiting_intake_sign', 'in_progress', 1367, 'admin', '2026-06-24 10:57:26', NULL),
(46331, 9811, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-29 08:40:00', NULL),
(46332, 9811, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-29 09:30:06', NULL),
(46333, 9811, 'awaiting_requester_sign', 'awaiting_admin_sign', 1383, 'requester', '2026-06-29 22:27:00', NULL),
(46334, 9811, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-01 09:17:12', NULL),
(46335, 9812, NULL, 'pending', 1387, 'web', '2026-06-29 08:40:00', NULL),
(46336, 9812, 'pending', 'accepted', 1369, 'staff', '2026-06-30 09:02:45', NULL),
(46337, 9812, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-30 09:12:24', NULL),
(46338, 9812, 'awaiting_intake_sign', 'in_progress', 1387, 'requester', '2026-06-30 10:46:38', NULL),
(46339, 9812, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-01 08:40:59', NULL),
(46340, 9812, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-01 08:58:41', NULL),
(46341, 9812, 'awaiting_requester_sign', 'awaiting_admin_sign', 1387, 'requester', '2026-07-01 10:37:14', NULL),
(46342, 9812, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-01 12:15:47', NULL),
(46343, 9813, NULL, 'pending', 1381, 'web', '2026-06-22 08:40:00', NULL),
(46344, 9813, 'pending', 'accepted', 1368, 'staff', '2026-06-22 08:51:21', NULL),
(46345, 9813, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-22 09:09:40', NULL),
(46346, 9813, 'awaiting_intake_sign', 'in_progress', 1381, 'requester', '2026-06-22 10:47:07', NULL),
(46347, 9813, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-24 12:30:31', NULL),
(46348, 9813, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-24 13:26:19', NULL),
(46349, 9813, 'awaiting_requester_sign', 'awaiting_admin_sign', 1381, 'requester', '2026-06-24 23:28:12', NULL),
(46350, 9813, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-25 09:30:06', NULL),
(46351, 9814, NULL, 'pending', 1391, 'web', '2026-06-15 08:40:00', NULL),
(46352, 9814, 'pending', 'accepted', 1369, 'staff', '2026-06-16 09:17:29', NULL),
(46353, 9814, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-16 09:29:44', NULL),
(46354, 9814, 'awaiting_intake_sign', 'in_progress', 1391, 'requester', '2026-06-16 09:57:18', NULL),
(46355, 9814, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-17 14:04:12', NULL),
(46356, 9814, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-17 14:32:27', NULL),
(46357, 9814, 'awaiting_requester_sign', 'awaiting_admin_sign', 1391, 'requester', '2026-06-17 21:32:42', NULL),
(46358, 9814, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-18 08:58:16', NULL),
(46359, 9815, NULL, 'pending', 1418, 'web', '2026-06-24 14:02:44', NULL),
(46360, 9815, 'pending', 'accepted', 1368, 'staff', '2026-06-24 14:19:28', NULL),
(46361, 9815, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-24 14:38:08', NULL),
(46362, 9815, 'awaiting_intake_sign', 'in_progress', 1418, 'requester', '2026-06-24 15:24:37', NULL),
(46363, 9815, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-25 08:39:26', NULL),
(46364, 9815, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-25 08:57:55', NULL),
(46365, 9815, 'awaiting_requester_sign', 'awaiting_admin_sign', 1418, 'requester', '2026-06-26 04:06:16', NULL),
(46366, 9815, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-26 09:08:18', NULL),
(46367, 9816, NULL, 'pending', 1426, 'web', '2026-06-24 08:40:40', NULL),
(46368, 9816, 'pending', 'accepted', 1369, 'staff', '2026-06-25 09:33:03', NULL),
(46369, 9816, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-25 09:44:20', NULL),
(46370, 9816, 'awaiting_intake_sign', 'in_progress', 1426, 'requester', '2026-06-25 11:05:04', NULL),
(46371, 9816, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-29 08:40:00', NULL),
(46372, 9816, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-29 09:06:18', NULL),
(46373, 9816, 'awaiting_requester_sign', 'awaiting_admin_sign', 1426, 'requester', '2026-06-29 13:26:39', NULL),
(46374, 9816, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-30 09:25:14', NULL),
(46375, 9817, NULL, 'pending', 1430, 'web', '2026-06-29 08:40:00', NULL),
(46376, 9817, 'pending', 'accepted', 1369, 'staff', '2026-06-30 09:07:50', NULL),
(46377, 9817, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-30 09:19:33', NULL),
(46378, 9817, 'awaiting_intake_sign', 'in_progress', 1430, 'requester', '2026-06-30 09:38:43', NULL),
(46379, 9817, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-03 09:04:46', NULL),
(46380, 9817, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-03 09:54:25', NULL),
(46381, 9817, 'awaiting_requester_sign', 'awaiting_admin_sign', 1430, 'requester', '2026-07-03 19:50:54', NULL),
(46382, 9817, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-06 08:40:00', NULL),
(46383, 9818, NULL, 'pending', 1429, 'web', '2026-06-16 09:08:37', NULL),
(46384, 9818, 'pending', 'accepted', 1368, 'staff', '2026-06-16 09:45:44', NULL),
(46385, 9818, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-16 10:03:57', NULL),
(46386, 9818, 'awaiting_intake_sign', 'in_progress', 1429, 'requester', '2026-06-16 11:19:28', NULL),
(46387, 9818, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-16 11:44:36', NULL),
(46388, 9818, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-16 12:10:29', NULL),
(46389, 9818, 'awaiting_requester_sign', 'awaiting_admin_sign', 1429, 'requester', '2026-06-17 06:54:58', NULL),
(46390, 9818, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-17 08:55:21', NULL),
(46391, 9819, NULL, 'pending', 1387, 'web', '2026-06-24 10:30:57', NULL),
(46392, 9819, 'pending', 'cancelled', 1387, 'web', '2026-06-25 09:04:25', NULL),
(46393, 9820, NULL, 'pending', 1418, 'web', '2026-06-22 08:40:00', NULL),
(46394, 9820, 'pending', 'accepted', 1368, 'staff', '2026-06-22 08:56:06', NULL),
(46395, 9820, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-22 09:06:11', NULL),
(46396, 9820, 'awaiting_intake_sign', 'in_progress', 1418, 'requester', '2026-06-22 10:30:52', NULL),
(46397, 9820, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-22 12:24:27', NULL),
(46398, 9820, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-22 13:12:09', NULL),
(46399, 9820, 'awaiting_requester_sign', 'awaiting_admin_sign', 1418, 'requester', '2026-06-23 01:43:36', NULL),
(46400, 9820, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-24 08:52:10', NULL),
(46401, 9821, NULL, 'pending', 1407, 'web', '2026-06-17 13:59:19', NULL),
(46402, 9821, 'pending', 'accepted', 1369, 'staff', '2026-06-18 09:00:06', NULL),
(46403, 9821, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-18 09:16:50', NULL),
(46404, 9821, 'awaiting_intake_sign', 'in_progress', 1366, 'admin', '2026-06-18 09:34:11', NULL),
(46405, 9821, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-19 09:19:11', NULL),
(46406, 9821, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-19 09:40:28', NULL),
(46407, 9821, 'awaiting_requester_sign', 'awaiting_admin_sign', 1407, 'requester', '2026-06-20 07:43:01', NULL),
(46408, 9821, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-22 08:40:00', NULL),
(46409, 9822, NULL, 'pending', 1407, 'web', '2026-06-09 13:29:32', NULL),
(46410, 9822, 'pending', 'accepted', 1368, 'staff', '2026-06-10 08:59:54', NULL),
(46411, 9822, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-10 09:05:14', NULL),
(46412, 9822, 'awaiting_intake_sign', 'in_progress', 1407, 'requester', '2026-06-10 10:20:04', NULL),
(46413, 9822, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-10 10:22:04', NULL),
(46414, 9822, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-10 10:55:06', NULL),
(46415, 9822, 'awaiting_requester_sign', 'awaiting_admin_sign', 1407, 'requester', '2026-06-11 07:16:56', NULL),
(46416, 9822, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-11 09:13:46', NULL),
(46417, 9823, NULL, 'pending', 1414, 'web', '2026-06-09 09:03:43', NULL),
(46418, 9823, 'pending', 'accepted', 1369, 'staff', '2026-06-10 08:44:08', NULL),
(46419, 9823, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-10 08:53:18', NULL),
(46420, 9823, 'awaiting_intake_sign', 'in_progress', 1414, 'requester', '2026-06-10 09:59:59', NULL),
(46421, 9823, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-12 09:34:25', NULL),
(46422, 9823, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-12 10:08:10', NULL),
(46423, 9823, 'awaiting_requester_sign', 'awaiting_admin_sign', 1414, 'requester', '2026-06-13 08:48:28', NULL),
(46424, 9823, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-15 08:40:00', NULL),
(46425, 9824, NULL, 'pending', 1390, 'web', '2026-06-18 16:23:37', NULL),
(46426, 9824, 'pending', 'accepted', 1369, 'staff', '2026-06-19 08:55:28', NULL),
(46427, 9824, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-19 09:09:37', NULL),
(46428, 9824, 'awaiting_intake_sign', 'in_progress', 1390, 'requester', '2026-06-19 10:04:31', NULL),
(46429, 9824, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-22 08:40:00', NULL),
(46430, 9824, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-22 09:27:15', NULL),
(46431, 9824, 'awaiting_requester_sign', 'awaiting_admin_sign', 1390, 'requester', '2026-06-23 06:14:23', NULL),
(46432, 9824, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-23 08:52:46', NULL),
(46433, 9825, NULL, 'pending', 1390, 'web', '2026-06-15 09:19:45', NULL),
(46434, 9825, 'pending', 'accepted', 1369, 'staff', '2026-06-16 08:43:11', NULL),
(46435, 9825, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-16 08:49:41', NULL),
(46436, 9825, 'awaiting_intake_sign', 'in_progress', 1390, 'requester', '2026-06-16 10:34:06', NULL),
(46437, 9825, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-19 08:42:00', NULL),
(46438, 9825, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-19 08:52:11', NULL),
(46439, 9825, 'awaiting_requester_sign', 'awaiting_admin_sign', 1390, 'requester', '2026-06-19 11:59:29', NULL),
(46440, 9825, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-22 08:40:00', NULL),
(46441, 9826, NULL, 'pending', 1408, 'web', '2026-06-04 08:57:25', NULL),
(46442, 9826, 'pending', 'accepted', 1369, 'staff', '2026-06-05 08:50:19', NULL),
(46443, 9826, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-05 09:08:21', NULL),
(46444, 9826, 'awaiting_intake_sign', 'in_progress', 1366, 'admin', '2026-06-05 10:58:58', NULL),
(46445, 9826, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-05 15:04:14', NULL),
(46446, 9826, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-05 15:46:06', NULL),
(46447, 9826, 'awaiting_requester_sign', 'awaiting_admin_sign', 1408, 'requester', '2026-06-05 23:51:07', NULL),
(46448, 9826, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-08 08:40:00', NULL),
(46449, 9827, NULL, 'pending', 1399, 'web', '2026-06-16 09:14:38', NULL),
(46450, 9827, 'pending', 'accepted', 1368, 'staff', '2026-06-17 08:57:59', NULL),
(46451, 9827, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-17 09:13:25', NULL),
(46452, 9827, 'awaiting_intake_sign', 'in_progress', 1399, 'requester', '2026-06-17 11:04:10', NULL),
(46453, 9827, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-19 09:05:11', NULL),
(46454, 9827, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-19 09:29:24', NULL),
(46455, 9827, 'awaiting_requester_sign', 'awaiting_admin_sign', 1399, 'requester', '2026-06-19 14:27:05', NULL),
(46456, 9827, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-22 08:40:00', NULL),
(46457, 9828, NULL, 'pending', 1391, 'web', '2026-06-15 08:40:00', NULL),
(46458, 9828, 'pending', 'accepted', 1368, 'staff', '2026-06-15 09:07:17', NULL),
(46459, 9828, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-15 09:17:57', NULL),
(46460, 9828, 'awaiting_intake_sign', 'in_progress', 1391, 'requester', '2026-06-15 11:01:16', NULL),
(46461, 9828, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-16 12:08:24', NULL),
(46462, 9828, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-16 12:41:10', NULL),
(46463, 9828, 'awaiting_requester_sign', 'awaiting_admin_sign', 1391, 'requester', '2026-06-16 23:44:18', NULL),
(46464, 9828, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-06-17 09:25:03', NULL),
(46465, 9829, NULL, 'pending', 1383, 'web', '2026-06-08 08:40:00', NULL),
(46466, 9829, 'pending', 'accepted', 1369, 'staff', '2026-06-08 14:22:31', NULL),
(46467, 9829, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-08 14:41:00', NULL),
(46468, 9829, 'awaiting_intake_sign', 'in_progress', 1383, 'requester', '2026-06-08 15:00:53', NULL),
(46469, 9829, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-09 08:42:20', NULL),
(46470, 9829, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-09 08:57:28', NULL),
(46471, 9829, 'awaiting_requester_sign', 'awaiting_admin_sign', 1383, 'requester', '2026-06-09 10:07:41', NULL),
(46472, 9829, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-11 09:17:21', NULL),
(46473, 9830, NULL, 'pending', 1382, 'web', '2026-06-10 09:06:43', NULL),
(46474, 9830, 'pending', 'accepted', 1369, 'staff', '2026-06-10 09:39:01', NULL),
(46475, 9830, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-06-10 09:57:06', NULL),
(46476, 9830, 'awaiting_intake_sign', 'in_progress', 1382, 'requester', '2026-06-10 11:30:37', NULL),
(46477, 9830, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-06-10 12:03:31', NULL),
(46478, 9830, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-06-10 12:21:13', NULL),
(46479, 9830, 'awaiting_requester_sign', 'awaiting_admin_sign', 1382, 'requester', '2026-06-11 00:47:26', NULL),
(46480, 9830, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-11 08:35:35', NULL),
(46481, 9831, NULL, 'pending', 1379, 'web', '2026-06-15 08:40:00', NULL),
(46482, 9831, 'pending', 'accepted', 1368, 'staff', '2026-06-16 09:10:58', NULL),
(46483, 9831, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-06-16 09:19:29', NULL),
(46484, 9831, 'awaiting_intake_sign', 'in_progress', 1379, 'requester', '2026-06-16 10:45:06', NULL),
(46485, 9831, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-06-17 08:46:31', NULL),
(46486, 9831, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-06-17 09:29:20', NULL),
(46487, 9831, 'awaiting_requester_sign', 'awaiting_admin_sign', 1379, 'requester', '2026-06-18 03:53:18', NULL),
(46488, 9831, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-06-18 10:57:48', NULL),
(46489, 9832, NULL, 'pending', 1429, 'web', '2026-07-06 08:40:00', NULL),
(46490, 9832, 'pending', 'accepted', 1369, 'staff', '2026-07-06 15:24:14', NULL),
(46491, 9832, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-07-06 15:42:41', NULL),
(46492, 9832, 'awaiting_intake_sign', 'in_progress', 1429, 'requester', '2026-07-06 17:38:12', NULL),
(46493, 9832, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-08 16:10:16', NULL),
(46494, 9832, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-08 16:32:26', NULL),
(46495, 9832, 'awaiting_requester_sign', 'awaiting_admin_sign', 1429, 'requester', '2026-07-09 06:11:39', NULL),
(46496, 9832, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-09 08:43:23', NULL),
(46497, 9833, NULL, 'pending', 1394, 'web', '2026-07-21 09:12:16', NULL),
(46498, 9833, 'pending', 'accepted', 1369, 'staff', '2026-07-21 11:49:48', NULL),
(46499, 9833, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-07-21 11:55:20', NULL),
(46500, 9833, 'awaiting_intake_sign', 'in_progress', 1394, 'requester', '2026-07-21 12:35:34', NULL),
(46501, 9833, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-21 14:43:23', NULL),
(46502, 9833, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-21 15:35:59', NULL),
(46503, 9833, 'awaiting_requester_sign', 'awaiting_admin_sign', 1394, 'requester', '2026-07-21 18:58:31', NULL),
(46504, 9833, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-23 09:12:17', NULL),
(46505, 9834, NULL, 'pending', 1389, 'web', '2026-07-08 08:44:24', NULL),
(46506, 9834, 'pending', 'accepted', 1369, 'staff', '2026-07-09 08:48:47', NULL),
(46507, 9834, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-07-09 08:56:53', NULL),
(46508, 9834, 'awaiting_intake_sign', 'in_progress', 1389, 'requester', '2026-07-09 09:20:54', NULL),
(46509, 9834, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-10 09:22:29', NULL),
(46510, 9834, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-10 09:57:27', NULL),
(46511, 9834, 'awaiting_requester_sign', 'awaiting_admin_sign', 1389, 'requester', '2026-07-11 02:34:24', NULL),
(46512, 9834, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-13 08:40:00', NULL),
(46513, 9835, NULL, 'pending', 1375, 'web', '2026-07-10 09:08:00', NULL),
(46514, 9835, 'pending', 'accepted', 1368, 'staff', '2026-07-13 08:40:00', NULL),
(46515, 9835, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-13 08:55:12', NULL),
(46516, 9835, 'awaiting_intake_sign', 'in_progress', 1375, 'requester', '2026-07-13 09:47:50', NULL),
(46517, 9835, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-13 12:19:04', NULL),
(46518, 9835, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-13 13:18:42', NULL),
(46519, 9835, 'awaiting_requester_sign', 'awaiting_admin_sign', 1375, 'requester', '2026-07-13 18:37:10', NULL),
(46520, 9835, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-15 09:21:29', NULL),
(46521, 9836, NULL, 'pending', 1418, 'web', '2026-07-06 08:40:00', NULL),
(46522, 9836, 'pending', 'accepted', 1368, 'staff', '2026-07-06 12:26:40', NULL),
(46523, 9836, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-06 12:43:57', NULL),
(46524, 9836, 'awaiting_intake_sign', 'in_progress', 1418, 'requester', '2026-07-06 13:24:34', NULL),
(46525, 9836, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-07 09:08:24', NULL),
(46526, 9836, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-07 09:29:21', NULL),
(46527, 9836, 'awaiting_requester_sign', 'awaiting_admin_sign', 1418, 'requester', '2026-07-08 06:45:45', NULL),
(46528, 9836, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-08 09:32:31', NULL),
(46529, 9837, NULL, 'pending', 1403, 'web', '2026-07-06 08:40:00', NULL),
(46530, 9837, 'pending', 'accepted', 1368, 'staff', '2026-07-06 14:57:52', NULL),
(46531, 9837, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-06 15:05:36', NULL),
(46532, 9837, 'awaiting_intake_sign', 'in_progress', 1403, 'requester', '2026-07-06 16:18:08', NULL),
(46533, 9837, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-08 15:59:23', NULL),
(46534, 9837, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-08 16:14:31', NULL),
(46535, 9837, 'awaiting_requester_sign', 'awaiting_admin_sign', 1403, 'requester', '2026-07-09 00:33:42', NULL),
(46536, 9837, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-09 08:52:54', NULL),
(46537, 9838, NULL, 'pending', 1387, 'web', '2026-07-06 08:40:00', NULL),
(46538, 9838, 'pending', 'accepted', 1368, 'staff', '2026-07-07 08:59:22', NULL),
(46539, 9838, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-07 09:08:32', NULL),
(46540, 9838, 'awaiting_intake_sign', 'in_progress', 1387, 'requester', '2026-07-07 09:32:38', NULL),
(46541, 9838, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-09 08:49:07', NULL),
(46542, 9838, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-09 09:24:52', NULL),
(46543, 9838, 'awaiting_requester_sign', 'awaiting_admin_sign', 1387, 'requester', '2026-07-09 22:47:29', NULL),
(46544, 9838, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-10 09:12:06', NULL),
(46545, 9839, NULL, 'pending', 1375, 'web', '2026-07-27 08:40:00', NULL),
(46546, 9839, 'pending', 'accepted', 1368, 'staff', '2026-07-28 08:46:24', NULL),
(46547, 9839, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-28 09:01:06', NULL),
(46548, 9839, 'awaiting_intake_sign', 'in_progress', 1375, 'requester', '2026-07-28 10:08:27', NULL),
(46549, 9839, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-30 09:00:02', NULL),
(46550, 9839, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-30 09:20:26', NULL),
(46551, 9839, 'awaiting_requester_sign', 'awaiting_admin_sign', 1375, 'requester', '2026-07-31 00:26:16', NULL),
(46552, 9839, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-08-03 08:40:00', NULL),
(46553, 9840, NULL, 'pending', 1431, 'web', '2026-07-06 08:40:00', NULL),
(46554, 9840, 'pending', 'accepted', 1369, 'staff', '2026-07-06 13:03:26', NULL),
(46555, 9840, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-07-06 13:13:57', NULL),
(46556, 9840, 'awaiting_intake_sign', 'in_progress', 1431, 'requester', '2026-07-06 14:22:26', NULL),
(46557, 9840, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-08 08:57:32', NULL),
(46558, 9840, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-08 09:52:49', NULL),
(46559, 9840, 'awaiting_requester_sign', 'awaiting_admin_sign', 1431, 'requester', '2026-07-08 15:00:44', NULL),
(46560, 9840, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-09 09:05:42', NULL),
(46561, 9841, NULL, 'pending', 1409, 'web', '2026-07-17 08:46:57', NULL),
(46562, 9841, 'pending', 'cancelled', 1409, 'web', '2026-07-17 10:34:20', NULL),
(46563, 9842, NULL, 'pending', 1427, 'web', '2026-07-27 10:53:00', NULL),
(46564, 9842, 'pending', 'accepted', 1368, 'staff', '2026-07-27 16:19:37', NULL),
(46565, 9842, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-27 16:24:37', NULL),
(46566, 9842, 'awaiting_intake_sign', 'in_progress', 1427, 'requester', '2026-07-27 16:35:17', NULL),
(46567, 9842, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-28 08:58:10', NULL),
(46568, 9842, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-28 09:11:46', NULL),
(46569, 9842, 'awaiting_requester_sign', 'awaiting_admin_sign', 1427, 'requester', '2026-07-28 10:02:54', NULL),
(46570, 9842, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-29 08:40:47', NULL),
(46571, 9843, NULL, 'pending', 1422, 'web', '2026-07-06 08:40:00', NULL),
(46572, 9843, 'pending', 'accepted', 1369, 'staff', '2026-07-06 16:12:46', NULL),
(46573, 9843, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-07-06 16:24:38', NULL),
(46574, 9843, 'awaiting_intake_sign', 'in_progress', 1422, 'requester', '2026-07-06 18:22:34', NULL),
(46575, 9843, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-07 09:15:46', NULL),
(46576, 9843, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-07 09:41:15', NULL),
(46577, 9843, 'awaiting_requester_sign', 'awaiting_admin_sign', 1422, 'requester', '2026-07-08 07:13:20', NULL),
(46578, 9843, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-08 14:38:58', NULL),
(46579, 9844, NULL, 'pending', 1376, 'web', '2026-07-31 09:06:00', NULL),
(46580, 9844, 'pending', 'accepted', 1369, 'staff', '2026-08-03 08:40:00', NULL),
(46581, 9844, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-08-03 08:49:32', NULL),
(46582, 9844, 'awaiting_intake_sign', 'in_progress', 1376, 'requester', '2026-08-03 10:20:08', NULL),
(46583, 9844, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-08-05 16:04:43', NULL),
(46584, 9844, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-08-05 17:02:12', NULL),
(46585, 9844, 'awaiting_requester_sign', 'awaiting_admin_sign', 1376, 'requester', '2026-08-06 05:31:50', NULL),
(46586, 9844, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-06 09:34:24', NULL),
(46587, 9845, NULL, 'pending', 1382, 'web', '2026-07-23 09:33:11', NULL),
(46588, 9845, 'pending', 'accepted', 1369, 'staff', '2026-07-24 08:35:17', NULL),
(46589, 9845, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-07-24 08:48:42', NULL),
(46590, 9845, 'awaiting_intake_sign', 'in_progress', 1382, 'requester', '2026-07-24 10:19:56', NULL),
(46591, 9845, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-27 08:40:00', NULL),
(46592, 9845, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-27 09:15:30', NULL),
(46593, 9845, 'awaiting_requester_sign', 'awaiting_admin_sign', 1382, 'requester', '2026-07-27 22:04:31', NULL),
(46594, 9845, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-29 08:38:42', NULL),
(46595, 9846, NULL, 'pending', 1391, 'web', '2026-07-29 08:57:01', NULL),
(46596, 9846, 'pending', 'accepted', 1369, 'staff', '2026-07-30 08:53:02', NULL),
(46597, 9846, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-07-30 08:58:59', NULL),
(46598, 9846, 'awaiting_intake_sign', 'in_progress', 1391, 'requester', '2026-07-30 10:47:25', NULL),
(46599, 9846, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-30 14:49:21', NULL),
(46600, 9846, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-30 15:30:28', NULL),
(46601, 9846, 'awaiting_requester_sign', 'awaiting_admin_sign', 1391, 'requester', '2026-07-31 01:54:47', NULL),
(46602, 9846, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-31 11:47:44', NULL),
(46603, 9847, NULL, 'pending', 1425, 'web', '2026-07-27 08:40:00', NULL),
(46604, 9847, 'pending', 'accepted', 1368, 'staff', '2026-07-28 09:07:23', NULL),
(46605, 9847, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-28 09:21:26', NULL),
(46606, 9847, 'awaiting_intake_sign', 'in_progress', 1425, 'requester', '2026-07-28 10:59:09', NULL),
(46607, 9847, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-30 08:41:22', NULL),
(46608, 9847, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-30 09:10:40', NULL);
INSERT INTO `ticket_logs` (`id`, `ticket_id`, `old_status`, `new_status`, `action_by`, `source`, `created_at`, `note`) VALUES
(46609, 9847, 'awaiting_requester_sign', 'awaiting_admin_sign', 1425, 'requester', '2026-07-31 00:21:43', NULL),
(46610, 9847, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-03 08:40:00', NULL),
(46611, 9848, NULL, 'pending', 1422, 'web', '2026-07-31 09:05:55', NULL),
(46612, 9848, 'pending', 'accepted', 1368, 'staff', '2026-08-03 08:40:00', NULL),
(46613, 9848, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-03 08:48:06', NULL),
(46614, 9848, 'awaiting_intake_sign', 'in_progress', 1422, 'requester', '2026-08-03 10:39:53', NULL),
(46615, 9848, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-05 08:38:26', NULL),
(46616, 9848, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-05 09:18:47', NULL),
(46617, 9848, 'awaiting_requester_sign', 'awaiting_admin_sign', 1422, 'requester', '2026-08-06 09:09:30', NULL),
(46618, 9848, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-06 11:01:50', NULL),
(46619, 9849, NULL, 'pending', 1399, 'web', '2026-07-27 08:40:00', NULL),
(46620, 9849, 'pending', 'accepted', 1368, 'staff', '2026-07-28 08:47:23', NULL),
(46621, 9849, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-28 09:05:18', NULL),
(46622, 9849, 'awaiting_intake_sign', 'in_progress', 1399, 'requester', '2026-07-28 09:53:05', NULL),
(46623, 9849, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-29 08:53:30', NULL),
(46624, 9849, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-29 09:31:38', NULL),
(46625, 9849, 'awaiting_requester_sign', 'awaiting_admin_sign', 1367, 'admin', '2026-07-29 11:55:02', NULL),
(46626, 9849, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-29 14:18:27', NULL),
(46627, 9850, NULL, 'pending', 1429, 'web', '2026-07-09 09:23:05', NULL),
(46628, 9850, 'pending', 'accepted', 1369, 'staff', '2026-07-09 09:58:04', NULL),
(46629, 9850, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-07-09 10:16:01', NULL),
(46630, 9850, 'awaiting_intake_sign', 'in_progress', 1429, 'requester', '2026-07-09 12:04:00', NULL),
(46631, 9850, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-07-09 13:15:05', NULL),
(46632, 9850, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-07-09 13:50:08', NULL),
(46633, 9850, 'awaiting_requester_sign', 'awaiting_admin_sign', 1429, 'requester', '2026-07-10 04:51:20', NULL),
(46634, 9850, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-07-10 14:02:05', NULL),
(46635, 9851, NULL, 'pending', 1383, 'web', '2026-07-29 08:54:57', NULL),
(46636, 9851, 'pending', 'accepted', 1368, 'staff', '2026-07-29 09:53:07', NULL),
(46637, 9851, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-07-29 09:59:44', NULL),
(46638, 9851, 'awaiting_intake_sign', 'in_progress', 1383, 'requester', '2026-07-29 10:43:04', NULL),
(46639, 9851, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-07-29 11:18:20', NULL),
(46640, 9851, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-07-29 11:51:20', NULL),
(46641, 9851, 'awaiting_requester_sign', 'awaiting_admin_sign', 1383, 'requester', '2026-07-29 20:36:51', NULL),
(46642, 9851, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-07-30 08:55:20', NULL),
(46643, 9852, NULL, 'pending', 1416, 'web', '2026-07-22 10:19:55', NULL),
(46644, 9852, 'pending', 'cancelled', 1416, 'web', '2026-07-23 09:14:44', NULL),
(46645, 9853, NULL, 'pending', 1413, 'web', '2026-08-25 12:57:43', NULL),
(46646, 9853, 'pending', 'accepted', 1368, 'staff', '2026-08-26 09:09:50', NULL),
(46647, 9853, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-26 09:29:14', NULL),
(46648, 9853, 'awaiting_intake_sign', 'in_progress', 1413, 'requester', '2026-08-26 10:15:18', NULL),
(46649, 9853, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-28 09:06:20', NULL),
(46650, 9853, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-28 09:23:57', NULL),
(46651, 9853, 'awaiting_requester_sign', 'awaiting_admin_sign', 1413, 'requester', '2026-08-28 16:20:59', NULL),
(46652, 9853, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-31 08:40:00', NULL),
(46653, 9854, NULL, 'pending', 1404, 'web', '2026-08-27 13:39:50', NULL),
(46654, 9854, 'pending', 'accepted', 1369, 'staff', '2026-08-28 08:52:33', NULL),
(46655, 9854, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-08-28 08:57:37', NULL),
(46656, 9854, 'awaiting_intake_sign', 'in_progress', 1404, 'requester', '2026-08-28 09:44:45', NULL),
(46657, 9854, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-08-31 08:40:00', NULL),
(46658, 9854, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-08-31 09:04:58', NULL),
(46659, 9854, 'awaiting_requester_sign', 'awaiting_admin_sign', 1404, 'requester', '2026-09-01 08:11:03', NULL),
(46660, 9854, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-02 09:05:18', NULL),
(46661, 9855, NULL, 'pending', 1396, 'web', '2026-08-17 10:46:06', NULL),
(46662, 9855, 'pending', 'accepted', 1368, 'staff', '2026-08-17 12:14:06', NULL),
(46663, 9855, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-17 12:27:17', NULL),
(46664, 9855, 'awaiting_intake_sign', 'in_progress', 1396, 'requester', '2026-08-17 13:15:21', NULL),
(46665, 9855, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-17 14:08:48', NULL),
(46666, 9855, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-17 14:37:08', NULL),
(46667, 9855, 'awaiting_requester_sign', 'awaiting_admin_sign', 1396, 'requester', '2026-08-17 16:14:03', NULL),
(46668, 9855, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-19 13:37:51', NULL),
(46669, 9856, NULL, 'pending', 1382, 'web', '2026-08-21 08:46:52', NULL),
(46670, 9856, 'pending', 'accepted', 1368, 'staff', '2026-08-24 08:40:00', NULL),
(46671, 9856, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-24 08:48:21', NULL),
(46672, 9856, 'awaiting_intake_sign', 'in_progress', 1382, 'requester', '2026-08-24 10:01:28', NULL),
(46673, 9856, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-24 16:26:59', NULL),
(46674, 9856, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-24 17:26:06', NULL),
(46675, 9856, 'awaiting_requester_sign', 'awaiting_admin_sign', 1382, 'requester', '2026-08-24 18:39:36', NULL),
(46676, 9856, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-08-25 13:44:48', NULL),
(46677, 9857, NULL, 'pending', 1426, 'web', '2026-08-31 08:40:00', NULL),
(46678, 9857, 'pending', 'accepted', 1368, 'staff', '2026-08-31 13:38:28', NULL),
(46679, 9857, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-31 13:56:40', NULL),
(46680, 9857, 'awaiting_intake_sign', 'in_progress', 1426, 'requester', '2026-08-31 14:52:10', NULL),
(46681, 9857, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-03 08:36:19', NULL),
(46682, 9857, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-03 08:55:45', NULL),
(46683, 9857, 'awaiting_requester_sign', 'awaiting_admin_sign', 1426, 'requester', '2026-09-03 18:08:13', NULL),
(46684, 9857, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-04 12:11:52', NULL),
(46685, 9858, NULL, 'pending', 1419, 'web', '2026-08-27 09:21:01', NULL),
(46686, 9858, 'pending', 'accepted', 1369, 'staff', '2026-08-27 10:01:03', NULL),
(46687, 9858, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-08-27 10:16:59', NULL),
(46688, 9858, 'awaiting_intake_sign', 'in_progress', 1419, 'requester', '2026-08-27 11:58:20', NULL),
(46689, 9858, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-08-27 12:59:51', NULL),
(46690, 9858, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-08-27 13:37:28', NULL),
(46691, 9858, 'awaiting_requester_sign', 'awaiting_admin_sign', 1419, 'requester', '2026-08-28 10:20:30', NULL),
(46692, 9858, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-28 10:51:20', NULL),
(46693, 9859, NULL, 'pending', 1391, 'web', '2026-08-28 09:25:37', NULL),
(46694, 9859, 'pending', 'accepted', 1368, 'staff', '2026-08-31 08:40:00', NULL),
(46695, 9859, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-31 08:56:47', NULL),
(46696, 9859, 'awaiting_intake_sign', 'in_progress', 1391, 'requester', '2026-08-31 09:58:10', NULL),
(46697, 9859, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-02 09:06:19', NULL),
(46698, 9859, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-02 10:05:11', NULL),
(46699, 9859, 'awaiting_requester_sign', 'awaiting_admin_sign', 1391, 'requester', '2026-09-03 01:41:33', NULL),
(46700, 9859, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-03 09:26:56', NULL),
(46701, 9860, NULL, 'pending', 1421, 'web', '2026-08-17 08:40:00', NULL),
(46702, 9860, 'pending', 'accepted', 1368, 'staff', '2026-08-17 12:32:49', NULL),
(46703, 9860, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-17 12:45:23', NULL),
(46704, 9860, 'awaiting_intake_sign', 'in_progress', 1421, 'requester', '2026-08-17 13:26:21', NULL),
(46705, 9860, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-18 09:32:19', NULL),
(46706, 9860, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-18 09:45:48', NULL),
(46707, 9860, 'awaiting_requester_sign', 'awaiting_admin_sign', 1421, 'requester', '2026-08-18 10:29:53', NULL),
(46708, 9860, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-08-18 11:13:59', NULL),
(46709, 9861, NULL, 'pending', 1421, 'web', '2026-08-31 08:40:00', NULL),
(46710, 9861, 'pending', 'cancelled', 1421, 'web', '2026-09-01 09:09:53', NULL),
(46711, 9862, NULL, 'pending', 1385, 'web', '2026-08-04 08:47:23', NULL),
(46712, 9862, 'pending', 'accepted', 1369, 'staff', '2026-08-04 09:16:27', NULL),
(46713, 9862, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-08-04 09:24:30', NULL),
(46714, 9862, 'awaiting_intake_sign', 'in_progress', 1385, 'requester', '2026-08-04 09:49:31', NULL),
(46715, 9862, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-08-04 11:55:32', NULL),
(46716, 9862, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-08-04 12:10:06', NULL),
(46717, 9862, 'awaiting_requester_sign', 'awaiting_admin_sign', 1385, 'requester', '2026-08-04 12:57:15', NULL),
(46718, 9862, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-08-06 09:03:30', NULL),
(46719, 9863, NULL, 'pending', 1415, 'web', '2026-08-12 11:35:36', NULL),
(46720, 9863, 'pending', 'accepted', 1368, 'staff', '2026-08-13 08:57:18', NULL),
(46721, 9863, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-13 09:13:58', NULL),
(46722, 9863, 'awaiting_intake_sign', 'in_progress', 1415, 'requester', '2026-08-13 11:08:35', NULL),
(46723, 9863, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-14 16:11:57', NULL),
(46724, 9863, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-14 16:36:36', NULL),
(46725, 9863, 'awaiting_requester_sign', 'awaiting_admin_sign', 1415, 'requester', '2026-08-15 08:58:45', NULL),
(46726, 9863, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-17 08:40:00', NULL),
(46727, 9864, NULL, 'pending', 1396, 'web', '2026-08-10 08:40:00', NULL),
(46728, 9864, 'pending', 'accepted', 1369, 'staff', '2026-08-11 09:21:20', NULL),
(46729, 9864, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-08-11 09:33:19', NULL),
(46730, 9864, 'awaiting_intake_sign', 'in_progress', 1396, 'requester', '2026-08-11 10:21:30', NULL),
(46731, 9864, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-08-13 08:37:37', NULL),
(46732, 9864, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-08-13 08:51:51', NULL),
(46733, 9864, 'awaiting_requester_sign', 'awaiting_admin_sign', 1396, 'requester', '2026-08-13 11:07:24', NULL),
(46734, 9864, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-14 08:54:07', NULL),
(46735, 9865, NULL, 'pending', 1382, 'web', '2026-08-05 08:57:55', NULL),
(46736, 9865, 'pending', 'accepted', 1368, 'staff', '2026-08-06 08:35:49', NULL),
(46737, 9865, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-06 08:52:15', NULL),
(46738, 9865, 'awaiting_intake_sign', 'in_progress', 1382, 'requester', '2026-08-06 09:56:09', NULL),
(46739, 9865, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-07 08:37:43', NULL),
(46740, 9865, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-07 09:12:47', NULL),
(46741, 9865, 'awaiting_requester_sign', 'awaiting_admin_sign', 1382, 'requester', '2026-08-07 18:34:10', NULL),
(46742, 9865, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-10 08:40:00', NULL),
(46743, 9866, NULL, 'pending', 1381, 'web', '2026-08-10 08:40:00', NULL),
(46744, 9866, 'pending', 'accepted', 1369, 'staff', '2026-08-10 12:26:30', NULL),
(46745, 9866, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-08-10 12:31:48', NULL),
(46746, 9866, 'awaiting_intake_sign', 'in_progress', 1381, 'requester', '2026-08-10 14:18:43', NULL),
(46747, 9866, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-08-13 10:43:53', NULL),
(46748, 9866, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-08-13 11:40:06', NULL),
(46749, 9866, 'awaiting_requester_sign', 'awaiting_admin_sign', 1381, 'requester', '2026-08-13 21:17:46', NULL),
(46750, 9866, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-17 08:40:00', NULL),
(46751, 9867, NULL, 'pending', 1409, 'web', '2026-08-07 09:15:37', NULL),
(46752, 9867, 'pending', 'accepted', 1369, 'staff', '2026-08-10 08:40:00', NULL),
(46753, 9867, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-08-10 08:54:16', NULL),
(46754, 9867, 'awaiting_intake_sign', 'in_progress', 1409, 'requester', '2026-08-10 10:40:17', NULL),
(46755, 9867, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-08-11 09:20:05', NULL),
(46756, 9867, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-08-11 10:06:12', NULL),
(46757, 9867, 'awaiting_requester_sign', 'awaiting_admin_sign', 1409, 'requester', '2026-08-11 11:54:49', NULL),
(46758, 9867, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-12 09:06:27', NULL),
(46759, 9868, NULL, 'pending', 1379, 'web', '2026-08-04 15:13:59', NULL),
(46760, 9868, 'pending', 'accepted', 1369, 'staff', '2026-08-04 16:11:36', NULL),
(46761, 9868, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-08-04 16:24:56', NULL),
(46762, 9868, 'awaiting_intake_sign', 'in_progress', 1379, 'requester', '2026-08-04 18:02:50', NULL),
(46763, 9868, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-08-05 09:22:47', NULL),
(46764, 9868, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-08-05 10:15:12', NULL),
(46765, 9868, 'awaiting_requester_sign', 'awaiting_admin_sign', 1379, 'requester', '2026-08-05 23:00:32', NULL),
(46766, 9868, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-08-07 08:58:03', NULL),
(46767, 9869, NULL, 'pending', 1431, 'web', '2026-08-24 08:40:00', NULL),
(46768, 9869, 'pending', 'accepted', 1368, 'staff', '2026-08-24 10:44:32', NULL),
(46769, 9869, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-24 10:57:23', NULL),
(46770, 9869, 'awaiting_intake_sign', 'in_progress', 1431, 'requester', '2026-08-24 11:26:08', NULL),
(46771, 9869, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-24 13:52:31', NULL),
(46772, 9869, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-24 14:23:27', NULL),
(46773, 9869, 'awaiting_requester_sign', 'awaiting_admin_sign', 1431, 'requester', '2026-08-24 20:13:54', NULL),
(46774, 9869, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-25 13:59:59', NULL),
(46775, 9870, NULL, 'pending', 1396, 'web', '2026-08-17 08:40:00', NULL),
(46776, 9870, 'pending', 'cancelled', 1367, 'web', '2026-08-18 09:31:10', NULL),
(46777, 9871, NULL, 'pending', 1428, 'web', '2026-08-31 11:50:56', NULL),
(46778, 9871, 'pending', 'accepted', 1369, 'staff', '2026-09-01 08:46:53', NULL),
(46779, 9871, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-09-01 08:56:42', NULL),
(46780, 9871, 'awaiting_intake_sign', 'in_progress', 1428, 'requester', '2026-09-01 10:51:20', NULL),
(46781, 9871, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-09-01 10:52:12', NULL),
(46782, 9871, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-09-01 11:10:30', NULL),
(46783, 9871, 'awaiting_requester_sign', 'awaiting_admin_sign', 1428, 'requester', '2026-09-01 23:16:48', NULL),
(46784, 9871, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-03 09:09:36', NULL),
(46785, 9872, NULL, 'pending', 1388, 'web', '2026-08-31 08:40:00', NULL),
(46786, 9872, 'pending', 'accepted', 1368, 'staff', '2026-09-01 08:45:30', NULL),
(46787, 9872, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-01 08:54:24', NULL),
(46788, 9872, 'awaiting_intake_sign', 'in_progress', 1388, 'requester', '2026-09-01 09:58:18', NULL),
(46789, 9872, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-01 13:13:31', NULL),
(46790, 9872, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-01 13:33:48', NULL),
(46791, 9872, 'awaiting_requester_sign', 'awaiting_admin_sign', 1388, 'requester', '2026-09-02 03:58:06', NULL),
(46792, 9872, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-03 09:17:27', NULL),
(46793, 9873, NULL, 'pending', 1417, 'web', '2026-08-06 09:18:42', NULL),
(46794, 9873, 'pending', 'accepted', 1368, 'staff', '2026-08-07 09:18:55', NULL),
(46795, 9873, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-08-07 09:27:13', NULL),
(46796, 9873, 'awaiting_intake_sign', 'in_progress', 1417, 'requester', '2026-08-07 10:13:39', NULL),
(46797, 9873, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-08-10 08:40:00', NULL),
(46798, 9873, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-08-10 09:18:26', NULL),
(46799, 9873, 'awaiting_requester_sign', 'awaiting_admin_sign', 1417, 'requester', '2026-08-10 11:48:15', NULL),
(46800, 9873, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-08-11 14:31:30', NULL),
(46801, 9874, NULL, 'pending', 1426, 'web', '2026-09-21 08:40:00', NULL),
(46802, 9874, 'pending', 'accepted', 1368, 'staff', '2026-09-21 08:55:51', NULL),
(46803, 9874, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-21 09:13:19', NULL),
(46804, 9874, 'awaiting_intake_sign', 'in_progress', 1426, 'requester', '2026-09-21 10:28:40', NULL),
(46805, 9874, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-24 09:11:27', NULL),
(46806, 9874, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-24 09:57:17', NULL),
(46807, 9874, 'awaiting_requester_sign', 'awaiting_admin_sign', 1426, 'requester', '2026-09-24 19:55:44', NULL),
(46808, 9874, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-25 12:48:31', NULL),
(46809, 9875, NULL, 'pending', 1386, 'web', '2026-09-21 08:40:00', NULL),
(46810, 9875, 'pending', 'accepted', 1368, 'staff', '2026-09-21 09:40:35', NULL),
(46811, 9875, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-21 09:59:01', NULL),
(46812, 9875, 'awaiting_intake_sign', 'in_progress', 1386, 'requester', '2026-09-21 11:42:14', NULL),
(46813, 9875, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-21 11:53:05', NULL),
(46814, 9875, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-21 12:29:12', NULL),
(46815, 9875, 'awaiting_requester_sign', 'awaiting_admin_sign', 1366, 'admin', '2026-09-22 04:32:54', NULL),
(46816, 9875, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-22 09:02:18', NULL),
(46817, 9876, NULL, 'pending', 1374, 'web', '2026-09-25 08:56:30', NULL),
(46818, 9876, 'pending', 'accepted', 1368, 'staff', '2026-09-25 09:45:32', NULL),
(46819, 9876, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-25 09:56:15', NULL),
(46820, 9876, 'awaiting_intake_sign', 'in_progress', 1374, 'requester', '2026-09-25 11:32:54', NULL),
(46821, 9876, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-28 08:40:00', NULL),
(46822, 9876, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-28 09:02:09', NULL),
(46823, 9876, 'awaiting_requester_sign', 'awaiting_admin_sign', 1374, 'requester', '2026-09-28 17:54:37', NULL),
(46824, 9876, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-30 09:16:36', NULL),
(46825, 9877, NULL, 'pending', 1392, 'web', '2026-09-04 08:57:16', NULL),
(46826, 9877, 'pending', 'accepted', 1369, 'staff', '2026-09-04 15:32:33', NULL),
(46827, 9877, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-09-04 15:38:40', NULL),
(46828, 9877, 'awaiting_intake_sign', 'in_progress', 1392, 'requester', '2026-09-04 17:00:39', NULL),
(46829, 9877, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-09-07 08:40:00', NULL),
(46830, 9877, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-09-07 08:54:02', NULL),
(46831, 9877, 'awaiting_requester_sign', 'awaiting_admin_sign', 1392, 'requester', '2026-09-07 22:33:09', NULL),
(46832, 9877, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-08 11:21:21', NULL),
(46833, 9878, NULL, 'pending', 1420, 'web', '2026-09-28 08:40:00', NULL),
(46834, 9878, 'pending', 'accepted', 1369, 'staff', '2026-09-29 08:57:24', NULL),
(46835, 9878, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-09-29 09:07:45', NULL),
(46836, 9878, 'awaiting_intake_sign', 'in_progress', 1420, 'requester', '2026-09-29 10:59:26', NULL),
(46837, 9878, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-09-30 09:12:34', NULL),
(46838, 9878, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-09-30 09:50:43', NULL),
(46839, 9878, 'awaiting_requester_sign', 'awaiting_admin_sign', 1420, 'requester', '2026-09-30 19:52:45', NULL),
(46840, 9878, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-10-01 09:05:39', NULL),
(46841, 9879, NULL, 'pending', 1419, 'web', '2026-09-07 08:40:00', NULL),
(46842, 9879, 'pending', 'accepted', 1368, 'staff', '2026-09-07 15:20:43', NULL),
(46843, 9879, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-07 15:35:52', NULL),
(46844, 9879, 'awaiting_intake_sign', 'in_progress', 1419, 'requester', '2026-09-07 16:24:09', NULL),
(46845, 9879, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-08 09:01:55', NULL),
(46846, 9879, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-08 09:59:46', NULL),
(46847, 9879, 'awaiting_requester_sign', 'awaiting_admin_sign', 1419, 'requester', '2026-09-08 21:29:39', NULL),
(46848, 9879, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-09 08:59:33', NULL),
(46849, 9880, NULL, 'pending', 1406, 'web', '2026-09-25 08:55:14', NULL),
(46850, 9880, 'pending', 'accepted', 1368, 'staff', '2026-09-28 08:40:00', NULL),
(46851, 9880, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-28 08:47:12', NULL),
(46852, 9880, 'awaiting_intake_sign', 'in_progress', 1406, 'requester', '2026-09-28 09:08:02', NULL),
(46853, 9880, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-29 08:41:30', NULL),
(46854, 9880, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-29 09:01:00', NULL),
(46855, 9880, 'awaiting_requester_sign', 'awaiting_admin_sign', 1406, 'requester', '2026-09-29 11:52:52', NULL),
(46856, 9880, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-30 08:51:44', NULL),
(46857, 9881, NULL, 'pending', 1392, 'web', '2026-09-04 08:49:36', NULL),
(46858, 9881, 'pending', 'accepted', 1369, 'staff', '2026-09-04 13:54:17', NULL),
(46859, 9881, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-09-04 14:09:17', NULL),
(46860, 9881, 'awaiting_intake_sign', 'in_progress', 1392, 'requester', '2026-09-04 15:30:08', NULL),
(46861, 9881, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-09-07 08:40:00', NULL),
(46862, 9881, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-09-07 09:33:03', NULL),
(46863, 9881, 'awaiting_requester_sign', 'awaiting_admin_sign', 1392, 'requester', '2026-09-07 14:52:02', NULL),
(46864, 9881, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-09 08:59:25', NULL),
(46865, 9882, NULL, 'pending', 1391, 'web', '2026-09-23 10:52:50', NULL),
(46866, 9882, 'pending', 'accepted', 1368, 'staff', '2026-09-23 13:44:14', NULL),
(46867, 9882, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-23 13:50:10', NULL),
(46868, 9882, 'awaiting_intake_sign', 'in_progress', 1391, 'requester', '2026-09-23 15:06:19', NULL),
(46869, 9882, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-25 09:24:57', NULL),
(46870, 9882, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-25 09:59:19', NULL),
(46871, 9882, 'awaiting_requester_sign', 'awaiting_admin_sign', 1391, 'requester', '2026-09-25 14:11:58', NULL),
(46872, 9882, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-28 08:40:00', NULL),
(46873, 9883, NULL, 'pending', 1387, 'web', '2026-09-28 08:40:00', NULL),
(46874, 9883, 'pending', 'accepted', 1369, 'staff', '2026-09-29 09:05:25', NULL),
(46875, 9883, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-09-29 09:10:38', NULL),
(46876, 9883, 'awaiting_intake_sign', 'in_progress', 1387, 'requester', '2026-09-29 10:56:30', NULL),
(46877, 9883, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-10-01 08:58:49', NULL),
(46878, 9883, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-10-01 09:34:16', NULL),
(46879, 9883, 'awaiting_requester_sign', 'awaiting_admin_sign', 1387, 'requester', '2026-10-01 11:01:13', NULL),
(46880, 9883, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-10-02 11:18:22', NULL),
(46881, 9884, NULL, 'pending', 1428, 'web', '2026-09-14 08:40:00', NULL),
(46882, 9884, 'pending', 'accepted', 1369, 'staff', '2026-09-15 09:21:46', NULL),
(46883, 9884, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-09-15 09:29:01', NULL),
(46884, 9884, 'awaiting_intake_sign', 'in_progress', 1428, 'requester', '2026-09-15 10:38:55', NULL),
(46885, 9884, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-09-17 09:01:25', NULL),
(46886, 9884, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-09-17 09:21:04', NULL),
(46887, 9884, 'awaiting_requester_sign', 'awaiting_admin_sign', 1428, 'requester', '2026-09-17 20:21:04', NULL),
(46888, 9884, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-18 14:32:29', NULL),
(46889, 9885, NULL, 'pending', 1409, 'web', '2026-09-11 08:58:36', NULL),
(46890, 9885, 'pending', 'accepted', 1368, 'staff', '2026-09-14 08:40:00', NULL),
(46891, 9885, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-14 08:55:32', NULL),
(46892, 9885, 'awaiting_intake_sign', 'in_progress', 1409, 'requester', '2026-09-14 09:46:44', NULL),
(46893, 9885, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-16 08:49:44', NULL),
(46894, 9885, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-16 09:29:33', NULL),
(46895, 9885, 'awaiting_requester_sign', 'awaiting_admin_sign', 1409, 'requester', '2026-09-16 10:45:18', NULL),
(46896, 9885, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-16 12:01:03', NULL),
(46897, 9886, NULL, 'pending', 1396, 'web', '2026-09-30 08:50:12', NULL),
(46898, 9886, 'pending', 'accepted', 1368, 'staff', '2026-10-01 08:42:41', NULL),
(46899, 9886, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-10-01 08:56:43', NULL),
(46900, 9886, 'awaiting_intake_sign', 'in_progress', 1396, 'requester', '2026-10-01 09:27:34', NULL),
(46901, 9886, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-10-02 09:27:24', NULL),
(46902, 9886, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-10-02 10:23:47', NULL),
(46903, 9886, 'awaiting_requester_sign', 'awaiting_admin_sign', 1396, 'requester', '2026-10-02 14:04:41', NULL),
(46904, 9886, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-10-05 08:40:00', NULL),
(46905, 9887, NULL, 'pending', 1405, 'web', '2026-09-14 08:40:00', NULL),
(46906, 9887, 'pending', 'accepted', 1369, 'staff', '2026-09-15 09:12:24', NULL),
(46907, 9887, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-09-15 09:27:59', NULL),
(46908, 9887, 'awaiting_intake_sign', 'in_progress', 1405, 'requester', '2026-09-15 10:33:22', NULL),
(46909, 9887, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-09-18 09:27:58', NULL),
(46910, 9887, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-09-18 10:06:20', NULL),
(46911, 9887, 'awaiting_requester_sign', 'awaiting_admin_sign', 1405, 'requester', '2026-09-18 21:13:15', NULL),
(46912, 9887, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-21 08:40:00', NULL),
(46913, 9888, NULL, 'pending', 1373, 'web', '2026-09-22 14:23:41', NULL),
(46914, 9888, 'pending', 'accepted', 1368, 'staff', '2026-09-22 15:33:23', NULL),
(46915, 9888, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-22 15:49:00', NULL),
(46916, 9888, 'awaiting_intake_sign', 'in_progress', 1373, 'requester', '2026-09-22 17:15:58', NULL),
(46917, 9888, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-23 08:53:30', NULL),
(46918, 9888, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-23 09:06:58', NULL),
(46919, 9888, 'awaiting_requester_sign', 'awaiting_admin_sign', 1373, 'requester', '2026-09-23 17:53:01', NULL),
(46920, 9888, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-25 08:36:04', NULL),
(46921, 9889, NULL, 'pending', 1418, 'web', '2026-09-04 09:05:06', NULL),
(46922, 9889, 'pending', 'accepted', 1368, 'staff', '2026-09-07 08:40:00', NULL),
(46923, 9889, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-07 08:59:42', NULL),
(46924, 9889, 'awaiting_intake_sign', 'in_progress', 1418, 'requester', '2026-09-07 10:40:11', NULL),
(46925, 9889, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-09 09:30:51', NULL),
(46926, 9889, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-09 10:08:39', NULL),
(46927, 9889, 'awaiting_requester_sign', 'awaiting_admin_sign', 1418, 'requester', '2026-09-09 12:44:10', NULL),
(46928, 9889, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-10 08:43:19', NULL),
(46929, 9890, NULL, 'pending', 1382, 'web', '2026-09-22 08:52:54', NULL),
(46930, 9890, 'pending', 'accepted', 1368, 'staff', '2026-09-22 09:12:17', NULL),
(46931, 9890, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-22 09:23:29', NULL),
(46932, 9890, 'awaiting_intake_sign', 'in_progress', 1382, 'requester', '2026-09-22 09:50:23', NULL),
(46933, 9890, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-22 12:48:24', NULL),
(46934, 9890, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-22 13:33:23', NULL),
(46935, 9890, 'awaiting_requester_sign', 'awaiting_admin_sign', 1382, 'requester', '2026-09-22 16:20:42', NULL),
(46936, 9890, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-24 11:01:18', NULL),
(46937, 9891, NULL, 'pending', 1402, 'web', '2026-09-30 15:50:06', NULL),
(46938, 9891, 'pending', 'accepted', 1369, 'staff', '2026-10-01 09:01:31', NULL),
(46939, 9891, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-10-01 09:14:48', NULL),
(46940, 9891, 'awaiting_intake_sign', 'in_progress', 1402, 'requester', '2026-10-01 10:12:47', NULL),
(46941, 9891, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-10-05 08:40:00', NULL),
(46942, 9891, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-10-05 09:26:09', NULL),
(46943, 9891, 'awaiting_requester_sign', 'awaiting_admin_sign', 1402, 'requester', '2026-10-06 03:11:47', NULL),
(46944, 9891, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-10-06 09:04:41', NULL),
(46945, 9892, NULL, 'pending', 1426, 'web', '2026-09-28 14:37:28', NULL),
(46946, 9892, 'pending', 'accepted', 1368, 'staff', '2026-09-29 09:12:35', NULL),
(46947, 9892, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-29 09:25:52', NULL),
(46948, 9892, 'awaiting_intake_sign', 'in_progress', 1426, 'requester', '2026-09-29 11:25:02', NULL),
(46949, 9892, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-10-02 08:41:15', NULL),
(46950, 9892, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-10-02 09:33:05', NULL),
(46951, 9892, 'awaiting_requester_sign', 'awaiting_admin_sign', 1426, 'requester', '2026-10-03 06:02:42', NULL),
(46952, 9892, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-10-05 08:40:00', NULL),
(46953, 9893, NULL, 'pending', 1385, 'web', '2026-09-23 09:01:36', NULL),
(46954, 9893, 'pending', 'accepted', 1368, 'staff', '2026-09-24 09:08:06', NULL),
(46955, 9893, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-24 09:18:42', NULL),
(46956, 9893, 'awaiting_intake_sign', 'in_progress', 1385, 'requester', '2026-09-24 10:44:49', NULL),
(46957, 9893, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-28 08:40:00', NULL),
(46958, 9893, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-28 09:03:03', NULL),
(46959, 9893, 'awaiting_requester_sign', 'awaiting_admin_sign', 1385, 'requester', '2026-09-28 14:33:39', NULL),
(46960, 9893, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-29 09:16:15', NULL),
(46961, 9894, NULL, 'pending', 1428, 'web', '2026-09-14 08:40:00', NULL),
(46962, 9894, 'pending', 'accepted', 1368, 'staff', '2026-09-15 09:34:01', NULL),
(46963, 9894, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-15 09:42:44', NULL),
(46964, 9894, 'awaiting_intake_sign', 'in_progress', 1428, 'requester', '2026-09-15 11:40:34', NULL),
(46965, 9894, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-16 08:40:20', NULL),
(46966, 9894, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-16 09:14:57', NULL),
(46967, 9894, 'awaiting_requester_sign', 'awaiting_admin_sign', 1428, 'requester', '2026-09-16 22:05:51', NULL),
(46968, 9894, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-18 08:59:21', NULL),
(46969, 9895, NULL, 'pending', 1385, 'web', '2026-09-28 08:40:00', NULL),
(46970, 9895, 'pending', 'accepted', 1369, 'staff', '2026-09-28 09:43:49', NULL),
(46971, 9895, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-09-28 09:50:01', NULL),
(46972, 9895, 'awaiting_intake_sign', 'in_progress', 1385, 'requester', '2026-09-28 11:29:15', NULL),
(46973, 9895, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-09-28 12:58:22', NULL),
(46974, 9895, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-09-28 13:28:26', NULL),
(46975, 9895, 'awaiting_requester_sign', 'awaiting_admin_sign', 1385, 'requester', '2026-09-29 01:16:14', NULL),
(46976, 9895, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-29 15:34:01', NULL),
(46977, 9896, NULL, 'pending', 1420, 'web', '2026-09-07 08:40:00', NULL),
(46978, 9896, 'pending', 'accepted', 1368, 'staff', '2026-09-08 09:29:45', NULL),
(46979, 9896, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-08 09:36:54', NULL),
(46980, 9896, 'awaiting_intake_sign', 'in_progress', 1420, 'requester', '2026-09-08 10:58:42', NULL),
(46981, 9896, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-10 09:21:06', NULL),
(46982, 9896, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-10 09:32:07', NULL),
(46983, 9896, 'awaiting_requester_sign', 'awaiting_admin_sign', 1420, 'requester', '2026-09-10 14:38:10', NULL),
(46984, 9896, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-09-11 15:15:53', NULL),
(46985, 9897, NULL, 'pending', 1399, 'web', '2026-09-07 12:51:49', NULL),
(46986, 9897, 'pending', 'accepted', 1368, 'staff', '2026-09-08 09:23:14', NULL),
(46987, 9897, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-09-08 09:28:49', NULL),
(46988, 9897, 'awaiting_intake_sign', 'in_progress', 1399, 'requester', '2026-09-08 11:23:04', NULL),
(46989, 9897, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-09-09 10:02:30', NULL),
(46990, 9897, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-09-09 10:49:31', NULL),
(46991, 9897, 'awaiting_requester_sign', 'awaiting_admin_sign', 1399, 'requester', '2026-09-10 01:38:29', NULL),
(46992, 9897, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-09-10 10:05:58', NULL),
(46993, 9898, NULL, 'pending', 1389, 'web', '2026-10-13 09:33:08', NULL),
(46994, 9898, 'pending', 'accepted', 1369, 'staff', '2026-10-14 09:14:35', NULL),
(46995, 9898, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-10-14 09:24:22', NULL),
(46996, 9898, 'awaiting_intake_sign', 'in_progress', 1389, 'requester', '2026-10-14 09:50:31', NULL),
(46997, 9898, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-10-15 13:46:22', NULL),
(46998, 9898, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-10-15 14:45:40', NULL),
(46999, 9898, 'awaiting_requester_sign', 'awaiting_admin_sign', 1389, 'requester', '2026-10-15 17:01:49', NULL),
(47000, 9898, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-10-19 08:40:00', NULL),
(47001, 9899, NULL, 'pending', 1432, 'web', '2026-10-08 09:02:07', NULL),
(47002, 9899, 'pending', 'accepted', 1369, 'staff', '2026-10-09 09:34:06', NULL),
(47003, 9899, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-10-09 09:41:33', NULL),
(47004, 9899, 'awaiting_intake_sign', 'in_progress', 1432, 'requester', '2026-10-09 10:18:12', NULL),
(47005, 9899, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-10-12 08:40:00', NULL),
(47006, 9899, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-10-12 09:10:06', NULL),
(47007, 9899, 'awaiting_requester_sign', 'awaiting_admin_sign', 1432, 'requester', '2026-10-12 16:59:05', NULL),
(47008, 9899, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-10-13 13:37:47', NULL),
(47009, 9900, NULL, 'pending', 1405, 'web', '2026-10-06 09:08:24', NULL),
(47010, 9900, 'pending', 'accepted', 1368, 'staff', '2026-10-07 08:41:24', NULL),
(47011, 9900, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-10-07 08:51:54', NULL),
(47012, 9900, 'awaiting_intake_sign', 'in_progress', 1366, 'admin', '2026-10-07 09:47:38', NULL),
(47013, 9900, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-10-09 12:56:04', NULL),
(47014, 9900, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-10-09 13:40:36', NULL),
(47015, 9900, 'awaiting_requester_sign', 'awaiting_admin_sign', 1405, 'requester', '2026-10-09 21:23:52', NULL),
(47016, 9900, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-10-12 08:40:00', NULL),
(47017, 9901, NULL, 'pending', 1423, 'web', '2026-10-05 08:40:00', NULL),
(47018, 9901, 'pending', 'accepted', 1368, 'staff', '2026-10-05 15:19:58', NULL),
(47019, 9901, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-10-05 15:34:27', NULL),
(47020, 9901, 'awaiting_intake_sign', 'in_progress', 1423, 'requester', '2026-10-05 16:00:05', NULL),
(47021, 9901, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-10-07 09:06:56', NULL),
(47022, 9901, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-10-07 09:42:23', NULL),
(47023, 9901, 'awaiting_requester_sign', 'awaiting_admin_sign', 1423, 'requester', '2026-10-07 21:00:45', NULL),
(47024, 9901, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-10-09 09:11:38', NULL),
(47025, 9902, NULL, 'pending', 1406, 'web', '2026-10-27 08:46:21', NULL),
(47026, 9902, 'pending', 'accepted', 1369, 'staff', '2026-10-28 09:23:40', NULL),
(47027, 9902, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-10-28 09:35:26', NULL),
(47028, 9902, 'awaiting_intake_sign', 'in_progress', 1366, 'admin', '2026-10-28 10:06:39', NULL),
(47029, 9902, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-10-29 09:21:02', NULL),
(47030, 9902, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-10-29 09:57:32', NULL),
(47031, 9902, 'awaiting_requester_sign', 'awaiting_admin_sign', 1406, 'requester', '2026-10-29 21:13:22', NULL),
(47032, 9902, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-11-02 08:40:00', NULL),
(47033, 9903, NULL, 'pending', 1388, 'web', '2026-10-08 09:29:59', NULL),
(47034, 9903, 'pending', 'accepted', 1369, 'staff', '2026-10-08 10:08:01', NULL),
(47035, 9903, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-10-08 10:24:17', NULL),
(47036, 9903, 'awaiting_intake_sign', 'in_progress', 1388, 'requester', '2026-10-08 12:14:50', NULL),
(47037, 9903, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-10-08 12:16:50', NULL),
(47038, 9903, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-10-08 12:44:40', NULL),
(47039, 9903, 'awaiting_requester_sign', 'awaiting_admin_sign', 1388, 'requester', '2026-10-09 00:31:20', NULL),
(47040, 9903, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-10-12 08:40:00', NULL),
(47041, 9904, NULL, 'pending', 1373, 'web', '2026-10-22 08:55:32', NULL),
(47042, 9904, 'pending', 'accepted', 1368, 'staff', '2026-10-23 09:19:35', NULL),
(47043, 9904, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-10-23 09:36:13', NULL),
(47044, 9904, 'awaiting_intake_sign', 'in_progress', 1373, 'requester', '2026-10-23 10:03:26', NULL),
(47045, 9904, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-10-26 08:40:00', NULL),
(47046, 9904, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-10-26 09:24:57', NULL),
(47047, 9904, 'awaiting_requester_sign', 'awaiting_admin_sign', 1373, 'requester', '2026-10-26 22:09:18', NULL),
(47048, 9904, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-10-27 11:54:37', NULL),
(47049, 9905, NULL, 'pending', 1422, 'web', '2026-10-29 14:36:16', NULL),
(47050, 9905, 'pending', 'accepted', 1369, 'staff', '2026-10-29 15:43:44', NULL),
(47051, 9905, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-10-29 15:56:24', NULL),
(47052, 9905, 'awaiting_intake_sign', 'in_progress', 1422, 'requester', '2026-10-29 17:25:46', NULL),
(47053, 9905, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-11-02 08:40:00', NULL),
(47054, 9905, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-11-02 09:04:50', NULL),
(47055, 9905, 'awaiting_requester_sign', 'awaiting_admin_sign', 1422, 'requester', '2026-11-02 11:01:30', NULL),
(47056, 9905, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-11-03 10:22:48', NULL),
(47057, 9906, NULL, 'pending', 1386, 'web', '2026-10-09 09:54:49', NULL),
(47058, 9906, 'pending', 'accepted', 1369, 'staff', '2026-10-12 08:40:00', NULL),
(47059, 9906, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-10-12 08:52:13', NULL),
(47060, 9906, 'awaiting_intake_sign', 'in_progress', 1386, 'requester', '2026-10-12 09:55:52', NULL),
(47061, 9906, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-10-15 08:50:17', NULL),
(47062, 9906, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-10-15 09:14:04', NULL),
(47063, 9906, 'awaiting_requester_sign', 'awaiting_admin_sign', 1386, 'requester', '2026-10-15 10:11:09', NULL),
(47064, 9906, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-10-16 10:04:22', NULL),
(47065, 9907, NULL, 'pending', 1377, 'web', '2026-11-02 08:40:00', NULL),
(47066, 9907, 'pending', 'accepted', 1370, 'staff', '2026-11-02 09:48:40', NULL),
(47067, 9907, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-02 09:57:17', NULL),
(47068, 9908, NULL, 'pending', 1423, 'web', '2026-10-26 08:40:00', NULL),
(47069, 9908, 'pending', 'accepted', 1368, 'staff', '2026-10-27 09:33:38', NULL),
(47070, 9908, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-10-27 09:43:42', NULL),
(47071, 9908, 'awaiting_intake_sign', 'in_progress', 1423, 'requester', '2026-10-27 10:51:38', NULL),
(47072, 9908, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-10-29 08:48:28', NULL),
(47073, 9908, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-10-29 09:21:16', NULL),
(47074, 9908, 'awaiting_requester_sign', 'awaiting_admin_sign', 1423, 'requester', '2026-10-29 12:42:49', NULL),
(47075, 9908, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-11-02 08:40:00', NULL),
(47076, 9909, NULL, 'pending', 1396, 'web', '2026-10-19 08:40:00', NULL),
(47077, 9909, 'pending', 'accepted', 1368, 'staff', '2026-10-20 09:33:13', NULL),
(47078, 9909, 'accepted', 'awaiting_intake_sign', 1368, 'staff', '2026-10-20 09:46:35', NULL),
(47079, 9909, 'awaiting_intake_sign', 'in_progress', 1396, 'requester', '2026-10-20 11:20:55', NULL),
(47080, 9909, 'in_progress', 'inspected_ready', 1368, 'staff', '2026-10-21 09:22:29', NULL),
(47081, 9909, 'inspected_ready', 'awaiting_requester_sign', 1368, 'staff', '2026-10-21 10:09:29', NULL),
(47082, 9909, 'awaiting_requester_sign', 'awaiting_admin_sign', 1396, 'requester', '2026-10-22 01:08:19', NULL),
(47083, 9909, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-10-23 09:30:39', NULL),
(47084, 9910, NULL, 'pending', 1420, 'web', '2026-10-26 08:40:00', NULL),
(47085, 9910, 'pending', 'accepted', 1369, 'staff', '2026-10-27 09:30:09', NULL),
(47086, 9910, 'accepted', 'awaiting_intake_sign', 1369, 'staff', '2026-10-27 09:35:36', NULL),
(47087, 9910, 'awaiting_intake_sign', 'in_progress', 1420, 'requester', '2026-10-27 09:51:00', NULL),
(47088, 9910, 'in_progress', 'inspected_ready', 1369, 'staff', '2026-10-30 09:17:50', NULL),
(47089, 9910, 'inspected_ready', 'awaiting_requester_sign', 1369, 'staff', '2026-10-30 10:08:06', NULL),
(47090, 9910, 'awaiting_requester_sign', 'awaiting_admin_sign', 1420, 'requester', '2026-10-31 07:44:48', NULL),
(47091, 9910, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-11-02 08:40:00', NULL),
(47092, 9911, NULL, 'pending', 1388, 'web', '2026-11-11 15:32:45', NULL),
(47093, 9911, 'pending', 'accepted', 1371, 'staff', '2026-11-12 09:15:09', NULL),
(47094, 9911, 'accepted', 'awaiting_intake_sign', 1371, 'staff', '2026-11-12 09:20:47', NULL),
(47095, 9911, 'awaiting_intake_sign', 'in_progress', 1388, 'requester', '2026-11-12 10:03:48', NULL),
(47096, 9912, NULL, 'pending', 1416, 'web', '2026-11-02 14:00:08', NULL),
(47097, 9913, NULL, 'pending', 1409, 'web', '2026-11-05 08:40:00', NULL),
(47098, 9913, 'pending', 'accepted', 1372, 'staff', '2026-11-06 08:48:05', NULL),
(47099, 9913, 'accepted', 'awaiting_intake_sign', 1372, 'staff', '2026-11-06 09:05:50', NULL),
(47100, 9914, NULL, 'pending', 1408, 'web', '2026-11-03 09:10:54', NULL),
(47101, 9914, 'pending', 'accepted', 1371, 'staff', '2026-11-03 11:56:52', NULL),
(47102, 9914, 'accepted', 'awaiting_intake_sign', 1371, 'staff', '2026-11-03 12:16:27', NULL),
(47103, 9914, 'awaiting_intake_sign', 'in_progress', 1408, 'requester', '2026-11-03 13:58:21', NULL),
(47104, 9914, 'in_progress', 'inspected_ready', 1371, 'staff', '2026-11-04 09:15:43', NULL),
(47105, 9914, 'inspected_ready', 'awaiting_requester_sign', 1371, 'staff', '2026-11-04 09:55:28', NULL),
(47106, 9914, 'awaiting_requester_sign', 'awaiting_admin_sign', 1408, 'requester', '2026-11-04 11:52:33', NULL),
(47107, 9915, NULL, 'pending', 1395, 'web', '2026-11-02 13:32:52', NULL),
(47108, 9916, NULL, 'pending', 1380, 'web', '2026-11-09 08:40:00', NULL),
(47109, 9916, 'pending', 'cancelled', 1380, 'web', '2026-11-10 08:51:54', NULL),
(47110, 9917, NULL, 'pending', 1375, 'web', '2026-11-12 09:41:25', NULL),
(47111, 9917, 'pending', 'accepted', 1372, 'staff', '2026-11-13 08:36:51', NULL),
(47112, 9917, 'accepted', 'awaiting_intake_sign', 1372, 'staff', '2026-11-13 08:45:22', NULL),
(47113, 9917, 'awaiting_intake_sign', 'in_progress', 1375, 'requester', '2026-11-13 09:02:49', NULL),
(47114, 9918, NULL, 'pending', 1396, 'web', '2026-11-10 09:09:33', NULL),
(47115, 9918, 'pending', 'accepted', 1371, 'staff', '2026-11-11 09:21:04', NULL),
(47116, 9918, 'accepted', 'awaiting_intake_sign', 1371, 'staff', '2026-11-11 09:33:09', NULL),
(47117, 9919, NULL, 'pending', 1378, 'web', '2026-11-02 08:40:00', NULL),
(47118, 9919, 'pending', 'accepted', 1370, 'staff', '2026-11-02 09:37:03', NULL),
(47119, 9919, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-02 09:54:02', NULL),
(47120, 9919, 'awaiting_intake_sign', 'in_progress', 1378, 'requester', '2026-11-02 11:26:00', NULL),
(47121, 9919, 'in_progress', 'inspected_ready', 1370, 'staff', '2026-11-02 14:48:11', NULL),
(47122, 9919, 'inspected_ready', 'awaiting_requester_sign', 1370, 'staff', '2026-11-02 15:42:44', NULL),
(47123, 9919, 'awaiting_requester_sign', 'awaiting_admin_sign', 1378, 'requester', '2026-11-02 22:43:49', NULL),
(47124, 9919, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-11-04 08:58:23', NULL),
(47125, 9920, NULL, 'pending', 1392, 'web', '2026-11-09 08:40:00', NULL),
(47126, 9920, 'pending', 'cancelled', 1367, 'web', '2026-11-10 08:53:42', NULL),
(47127, 9921, NULL, 'pending', 1412, 'web', '2026-11-13 13:14:41', NULL),
(47128, 9922, NULL, 'pending', 1388, 'web', '2026-11-03 13:50:40', NULL),
(47129, 9923, NULL, 'pending', 1410, 'web', '2026-11-04 09:01:27', NULL),
(47130, 9923, 'pending', 'accepted', 1370, 'staff', '2026-11-05 09:11:39', NULL),
(47131, 9923, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-05 09:27:04', NULL),
(47132, 9923, 'awaiting_intake_sign', 'in_progress', 1410, 'requester', '2026-11-05 10:27:55', NULL),
(47133, 9923, 'in_progress', 'inspected_ready', 1370, 'staff', '2026-11-06 09:26:38', NULL),
(47134, 9923, 'inspected_ready', 'awaiting_requester_sign', 1370, 'staff', '2026-11-06 09:59:58', NULL),
(47135, 9923, 'awaiting_requester_sign', 'awaiting_admin_sign', 1410, 'requester', '2026-11-06 14:06:54', NULL),
(47136, 9924, NULL, 'pending', 1431, 'web', '2026-11-10 08:43:57', NULL),
(47137, 9925, NULL, 'pending', 1385, 'web', '2026-11-04 09:29:57', NULL),
(47138, 9925, 'pending', 'accepted', 1370, 'staff', '2026-11-04 14:14:03', NULL),
(47139, 9925, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-04 14:33:44', NULL),
(47140, 9925, 'awaiting_intake_sign', 'in_progress', 1367, 'admin', '2026-11-04 15:36:57', NULL),
(47141, 9926, NULL, 'pending', 1392, 'web', '2026-11-04 09:08:30', NULL),
(47142, 9926, 'pending', 'cancelled', 1367, 'web', '2026-11-05 08:45:25', NULL),
(47143, 9927, NULL, 'pending', 1388, 'web', '2026-11-02 08:40:00', NULL),
(47144, 9928, NULL, 'pending', 1386, 'web', '2026-11-12 14:22:56', NULL),
(47145, 9928, 'pending', 'accepted', 1370, 'staff', '2026-11-13 09:33:37', NULL),
(47146, 9928, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-13 09:41:31', NULL),
(47147, 9928, 'awaiting_intake_sign', 'in_progress', 1386, 'requester', '2026-11-13 10:47:07', NULL),
(47148, 9929, NULL, 'pending', 1408, 'web', '2026-11-06 09:10:51', NULL),
(47149, 9929, 'pending', 'accepted', 1370, 'staff', '2026-11-06 10:29:26', NULL);
INSERT INTO `ticket_logs` (`id`, `ticket_id`, `old_status`, `new_status`, `action_by`, `source`, `created_at`, `note`) VALUES
(47150, 9929, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-06 10:39:08', NULL),
(47151, 9929, 'awaiting_intake_sign', 'in_progress', 1408, 'requester', '2026-11-06 10:54:00', NULL),
(47152, 9929, 'in_progress', 'inspected_ready', 1370, 'staff', '2026-11-06 11:47:30', NULL),
(47153, 9929, 'inspected_ready', 'awaiting_requester_sign', 1370, 'staff', '2026-11-06 12:03:50', NULL),
(47154, 9929, 'awaiting_requester_sign', 'awaiting_admin_sign', 1408, 'requester', '2026-11-07 06:14:24', NULL),
(47155, 9929, 'awaiting_admin_sign', 'completed', 1367, 'signoff', '2026-11-09 08:40:00', NULL),
(47156, 9930, NULL, 'pending', 1391, 'web', '2026-11-05 09:11:51', NULL),
(47157, 9930, 'pending', 'accepted', 1370, 'staff', '2026-11-06 08:52:22', NULL),
(47158, 9930, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-06 09:10:34', NULL),
(47159, 9930, 'awaiting_intake_sign', 'in_progress', 1391, 'requester', '2026-11-06 09:51:56', NULL),
(47160, 9931, NULL, 'pending', 1403, 'web', '2026-11-05 09:18:38', NULL),
(47161, 9931, 'pending', 'accepted', 1371, 'staff', '2026-11-06 08:43:56', NULL),
(47162, 9931, 'accepted', 'awaiting_intake_sign', 1371, 'staff', '2026-11-06 08:52:23', NULL),
(47163, 9931, 'awaiting_intake_sign', 'in_progress', 1403, 'requester', '2026-11-06 09:23:31', NULL),
(47164, 9931, 'in_progress', 'inspected_ready', 1371, 'staff', '2026-11-09 08:50:37', NULL),
(47165, 9931, 'inspected_ready', 'awaiting_requester_sign', 1371, 'staff', '2026-11-09 09:13:20', NULL),
(47166, 9931, 'awaiting_requester_sign', 'awaiting_admin_sign', 1403, 'requester', '2026-11-10 07:31:22', NULL),
(47167, 9931, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-11-10 09:32:07', NULL),
(47168, 9932, NULL, 'pending', 1430, 'web', '2026-11-11 09:59:54', NULL),
(47169, 9933, NULL, 'pending', 1402, 'web', '2026-11-12 09:05:15', NULL),
(47170, 9933, 'pending', 'accepted', 1370, 'staff', '2026-11-12 10:34:06', NULL),
(47171, 9933, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-12 10:43:09', NULL),
(47172, 9933, 'awaiting_intake_sign', 'in_progress', 1402, 'requester', '2026-11-12 12:26:52', NULL),
(47173, 9933, 'in_progress', 'inspected_ready', 1370, 'staff', '2026-11-12 14:08:38', NULL),
(47174, 9933, 'inspected_ready', 'awaiting_requester_sign', 1370, 'staff', '2026-11-12 14:27:57', NULL),
(47175, 9933, 'awaiting_requester_sign', 'awaiting_admin_sign', 1402, 'requester', '2026-11-12 23:47:28', NULL),
(47176, 9933, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-11-13 09:07:00', NULL),
(47177, 9934, NULL, 'pending', 1417, 'web', '2026-11-14 10:49:23', NULL),
(47178, 9934, 'pending', 'accepted', 1370, 'staff', '2026-11-14 11:09:23', NULL),
(47179, 9934, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-14 11:15:32', NULL),
(47180, 9934, 'awaiting_intake_sign', 'in_progress', 1417, 'requester', '2026-11-14 12:30:11', NULL),
(47181, 9934, 'in_progress', 'inspected_ready', 1370, 'staff', '2026-11-14 12:32:11', NULL),
(47182, 9934, 'inspected_ready', 'awaiting_requester_sign', 1370, 'staff', '2026-11-14 13:14:41', NULL),
(47183, 9934, 'awaiting_requester_sign', 'awaiting_admin_sign', 1417, 'requester', '2026-11-14 13:17:02', NULL),
(47184, 9934, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-11-14 13:19:23', NULL),
(47185, 9935, NULL, 'pending', 1388, 'web', '2026-11-14 11:29:23', NULL),
(47186, 9935, 'pending', 'accepted', 1371, 'staff', '2026-11-14 11:39:23', NULL),
(47187, 9935, 'accepted', 'awaiting_intake_sign', 1371, 'staff', '2026-11-14 11:48:58', NULL),
(47188, 9935, 'awaiting_intake_sign', 'in_progress', 1388, 'requester', '2026-11-14 12:17:47', NULL),
(47189, 9935, 'in_progress', 'inspected_ready', 1371, 'staff', '2026-11-14 12:59:23', NULL),
(47190, 9935, 'inspected_ready', 'awaiting_requester_sign', 1371, 'staff', '2026-11-14 13:12:39', NULL),
(47191, 9935, 'awaiting_requester_sign', 'awaiting_admin_sign', 1388, 'requester', '2026-11-14 13:31:01', NULL),
(47192, 9935, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-11-14 13:49:23', NULL),
(47193, 9936, NULL, 'pending', 1427, 'web', '2026-11-14 11:49:23', NULL),
(47194, 9936, 'pending', 'accepted', 1372, 'staff', '2026-11-14 12:04:23', NULL),
(47195, 9936, 'accepted', 'awaiting_intake_sign', 1372, 'staff', '2026-11-14 12:17:21', NULL),
(47196, 9936, 'awaiting_intake_sign', 'in_progress', 1427, 'requester', '2026-11-14 13:12:49', NULL),
(47197, 9936, 'in_progress', 'inspected_ready', 1372, 'staff', '2026-11-14 14:19:23', NULL),
(47198, 9936, 'inspected_ready', 'awaiting_requester_sign', 1372, 'staff', '2026-11-14 14:50:14', NULL),
(47199, 9936, 'awaiting_requester_sign', 'awaiting_admin_sign', 1427, 'requester', '2026-11-14 15:19:48', NULL),
(47200, 9937, NULL, 'pending', 1391, 'web', '2026-11-14 11:59:23', NULL),
(47201, 9937, 'pending', 'accepted', 1370, 'staff', '2026-11-14 12:14:23', NULL),
(47202, 9937, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-14 12:29:57', NULL),
(47203, 9937, 'awaiting_intake_sign', 'in_progress', 1367, 'admin', '2026-11-14 13:45:11', NULL),
(47204, 9937, 'in_progress', 'inspected_ready', 1370, 'staff', '2026-11-14 14:49:23', NULL),
(47205, 9937, 'inspected_ready', 'awaiting_requester_sign', 1370, 'staff', '2026-11-14 15:18:03', NULL),
(47206, 9938, NULL, 'pending', 1379, 'web', '2026-11-14 09:09:23', NULL),
(47207, 9938, 'pending', 'accepted', 1371, 'staff', '2026-11-14 09:29:23', NULL),
(47208, 9938, 'accepted', 'awaiting_intake_sign', 1371, 'staff', '2026-11-14 09:47:29', NULL),
(47209, 9938, 'awaiting_intake_sign', 'in_progress', 1379, 'requester', '2026-11-14 10:27:22', NULL),
(47210, 9938, 'in_progress', 'inspected_ready', 1371, 'staff', '2026-11-14 12:29:23', NULL),
(47211, 9938, 'inspected_ready', 'awaiting_requester_sign', 1371, 'staff', '2026-11-14 13:28:20', NULL),
(47212, 9938, 'awaiting_requester_sign', 'disputed', 1379, 'requester', '2026-11-14 15:38:35', NULL),
(47213, 9939, NULL, 'pending', 1432, 'web', '2026-11-14 12:49:23', NULL),
(47214, 9939, 'pending', 'accepted', 1372, 'staff', '2026-11-14 13:09:23', NULL),
(47215, 9939, 'accepted', 'awaiting_intake_sign', 1372, 'staff', '2026-11-14 13:20:08', NULL),
(47216, 9939, 'awaiting_intake_sign', 'in_progress', 1432, 'requester', '2026-11-14 14:25:35', NULL),
(47217, 9940, NULL, 'pending', 1377, 'web', '2026-11-14 13:49:23', NULL),
(47218, 9940, 'pending', 'accepted', 1370, 'staff', '2026-11-14 14:09:23', NULL),
(47219, 9940, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-14 14:18:40', NULL),
(47220, 9941, NULL, 'pending', 1395, 'web', '2026-11-14 15:09:23', NULL),
(47221, 9941, 'pending', 'accepted', 1371, 'staff', '2026-11-14 15:19:23', NULL),
(47222, 9942, NULL, 'pending', 1391, 'web', '2026-11-14 15:04:23', NULL),
(47223, 9943, NULL, 'pending', 1393, 'web', '2026-11-14 15:34:23', NULL),
(47224, 9944, NULL, 'pending', 1419, 'web', '2026-11-14 16:01:30', NULL),
(47225, 9915, 'pending', 'accepted', 1370, 'staff', '2026-11-14 16:13:09', NULL),
(47226, 9915, 'accepted', 'awaiting_intake_sign', 1370, 'staff', '2026-11-14 16:13:17', 'บันทึกเช็คลิสต์ตอนรับ 1 รายการ'),
(47227, 9915, 'awaiting_intake_sign', 'in_progress', 1395, 'requester', '2026-11-14 16:15:03', 'ผู้แจ้งเซ็นรับทราบเช็คลิสต์ตอนรับ'),
(47228, 9915, 'in_progress', 'inspected_ready', 1370, 'staff', '2026-11-14 16:16:24', 'ตรวจซ้ำครบถ้วน 2 รายการ'),
(47229, 9915, 'inspected_ready', 'awaiting_requester_sign', 1370, 'staff', '2026-11-14 16:16:34', 'สตาฟเซ็นส่งมอบงาน'),
(47230, 9915, 'awaiting_requester_sign', 'awaiting_admin_sign', 1395, 'requester', '2026-11-14 16:17:05', 'ผู้แจ้งเซ็นรับคืน'),
(47231, 9915, 'awaiting_admin_sign', 'completed', 1366, 'signoff', '2026-11-14 16:22:42', NULL),
(47232, 9921, 'pending', 'accepted', 1366, 'staff', '2026-11-14 16:50:19', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_materials`
--

CREATE TABLE `ticket_materials` (
  `id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `item_id` int NOT NULL,
  `qty_used` int NOT NULL,
  `withdrawn_by` int NOT NULL COMMENT 'ผู้เบิก (user_id)',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_materials`
--

INSERT INTO `ticket_materials` (`id`, `ticket_id`, `item_id`, `qty_used`, `withdrawn_by`, `note`, `created_at`) VALUES
(3518, 9778, 149, 1, 1369, NULL, '2026-05-06 11:42:58'),
(3519, 9780, 147, 1, 1368, NULL, '2026-05-20 10:35:52'),
(3520, 9781, 147, 2, 1369, NULL, '2026-05-27 09:24:04'),
(3521, 9782, 144, 1, 1369, NULL, '2026-05-26 10:34:19'),
(3522, 9783, 146, 2, 1368, NULL, '2026-05-08 15:22:09'),
(3523, 9789, 146, 2, 1369, NULL, '2026-05-11 12:05:00'),
(3524, 9793, 144, 1, 1369, NULL, '2026-05-08 15:25:30'),
(3525, 9800, 149, 2, 1368, NULL, '2026-05-15 11:48:16'),
(3526, 9804, 143, 6, 1368, NULL, '2026-06-16 15:53:29'),
(3527, 9807, 142, 1, 1368, NULL, '2026-06-15 09:34:22'),
(3528, 9809, 142, 1, 1369, NULL, '2026-06-17 09:30:16'),
(3529, 9817, 144, 2, 1369, NULL, '2026-07-03 09:04:46'),
(3530, 9823, 146, 1, 1369, NULL, '2026-06-12 09:34:25'),
(3531, 9827, 144, 1, 1368, NULL, '2026-06-19 09:05:11'),
(3532, 9830, 146, 2, 1369, NULL, '2026-06-10 12:03:31'),
(3533, 9832, 144, 2, 1369, NULL, '2026-07-08 16:10:16'),
(3534, 9833, 145, 2, 1369, NULL, '2026-07-21 14:43:23'),
(3535, 9834, 145, 1, 1369, NULL, '2026-07-10 09:22:29'),
(3536, 9836, 147, 1, 1368, NULL, '2026-07-07 09:08:24'),
(3537, 9837, 146, 1, 1368, NULL, '2026-07-08 15:59:23'),
(3538, 9838, 145, 1, 1368, NULL, '2026-07-09 08:49:07'),
(3539, 9839, 149, 1, 1368, NULL, '2026-07-30 09:00:02'),
(3540, 9840, 142, 2, 1369, NULL, '2026-07-08 08:57:32'),
(3541, 9842, 148, 2, 1368, NULL, '2026-07-28 08:58:10'),
(3542, 9843, 144, 2, 1369, NULL, '2026-07-07 09:15:46'),
(3543, 9845, 148, 2, 1369, NULL, '2026-07-27 08:40:00'),
(3544, 9846, 147, 1, 1369, NULL, '2026-07-30 14:49:21'),
(3545, 9847, 142, 1, 1368, NULL, '2026-07-30 08:41:22'),
(3546, 9848, 146, 1, 1368, NULL, '2026-08-05 08:38:26'),
(3547, 9850, 145, 2, 1369, NULL, '2026-07-09 13:15:05'),
(3548, 9851, 143, 8, 1368, NULL, '2026-07-29 11:18:20'),
(3549, 9855, 147, 1, 1368, NULL, '2026-08-17 14:08:48'),
(3550, 9856, 146, 2, 1368, NULL, '2026-08-24 16:26:59'),
(3551, 9864, 143, 8, 1369, NULL, '2026-08-13 08:37:37'),
(3552, 9873, 148, 1, 1368, NULL, '2026-08-10 08:40:00'),
(3553, 9874, 147, 2, 1368, NULL, '2026-09-24 09:11:27'),
(3554, 9875, 147, 1, 1368, NULL, '2026-09-21 11:53:05'),
(3555, 9877, 146, 2, 1369, NULL, '2026-09-07 08:40:00'),
(3556, 9878, 142, 2, 1369, NULL, '2026-09-30 09:12:34'),
(3557, 9886, 148, 1, 1368, NULL, '2026-10-02 09:27:24'),
(3558, 9897, 147, 2, 1368, NULL, '2026-09-09 10:02:30'),
(3559, 9903, 149, 2, 1369, NULL, '2026-10-08 11:48:32'),
(3560, 9905, 144, 2, 1369, NULL, '2026-11-02 08:40:00'),
(3561, 9910, 144, 1, 1369, NULL, '2026-10-30 09:17:50'),
(3562, 9919, 147, 2, 1370, NULL, '2026-11-02 14:48:11');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_signoffs`
--

CREATE TABLE `ticket_signoffs` (
  `id` int NOT NULL,
  `ticket_id` int NOT NULL,
  `signer_role` enum('staff','requester','admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin' COMMENT 'บทบาทในการเซ็น',
  `stage` enum('intake','return') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'return' COMMENT 'intake = รับทราบเช็คลิสต์ตอนรับ, return = ส่งคืน/ปิดงาน',
  `round_no` int NOT NULL DEFAULT '1' COMMENT 'รอบการเซ็น',
  `signer_id` int NOT NULL COMMENT 'ผู้เซ็น',
  `signature_image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'path รูปลายเซ็น (private storage)',
  `method` enum('draw','saved','skip') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draw' COMMENT 'draw=วาดบนจอ, saved=ลายเซ็นที่อัปโหลดไว้, skip=admin ข้ามขั้นตามกติกา',
  `signed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'เวลาที่ลงนาม',
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IP ของผู้ลงนาม (รองรับ IPv6)',
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'หมายเหตุการรับรอง'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_signoffs`
--

INSERT INTO `ticket_signoffs` (`id`, `ticket_id`, `signer_role`, `stage`, `round_no`, `signer_id`, `signature_image`, `method`, `signed_at`, `ip`, `note`) VALUES
(15665, 9777, 'requester', 'intake', 1, 1430, 'ticket-private/dm_sig_1430.png', 'draw', '2026-05-18 10:13:49', '10.0.14.32', NULL),
(15666, 9777, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-21 09:23:46', '10.0.11.141', NULL),
(15667, 9777, 'requester', 'return', 1, 1430, 'ticket-private/dm_sig_1430.png', 'draw', '2026-05-21 10:34:22', '10.0.9.174', NULL),
(15668, 9777, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-21 11:44:59', '10.0.9.190', NULL),
(15669, 9778, 'requester', 'intake', 1, 1431, 'ticket-private/dm_sig_1431.png', 'draw', '2026-05-06 11:00:16', '10.0.6.188', NULL),
(15670, 9778, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-06 12:01:28', '10.0.10.73', NULL),
(15671, 9778, 'requester', 'return', 1, 1431, 'ticket-private/dm_sig_1431.png', 'draw', '2026-05-06 15:14:29', '10.0.14.193', NULL),
(15672, 9778, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-07 14:46:53', '10.0.14.201', NULL),
(15673, 9779, 'requester', 'intake', 1, 1429, 'ticket-private/dm_sig_1429.png', 'draw', '2026-05-12 10:07:49', '10.0.8.227', NULL),
(15674, 9779, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-12 12:43:58', '10.0.20.51', NULL),
(15675, 9779, 'requester', 'return', 1, 1429, 'ticket-private/dm_sig_1429.png', 'draw', '2026-05-12 14:03:17', '10.0.12.22', NULL),
(15676, 9779, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-14 08:53:02', '10.0.1.242', NULL),
(15677, 9780, 'requester', 'intake', 1, 1409, 'ticket-private/dm_sig_1409.png', 'draw', '2026-05-19 12:06:06', '10.0.9.189', NULL),
(15678, 9780, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-20 11:35:18', '10.0.20.84', NULL),
(15679, 9780, 'requester', 'return', 1, 1409, 'ticket-private/dm_sig_1409.png', 'draw', '2026-05-21 07:58:54', '10.0.12.137', NULL),
(15680, 9780, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-21 12:50:59', '10.0.6.225', NULL),
(15681, 9781, 'requester', 'intake', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-05-26 12:52:39', '10.0.5.180', NULL),
(15682, 9781, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-27 10:12:05', '10.0.20.169', NULL),
(15683, 9781, 'requester', 'return', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-05-28 06:51:34', '10.0.12.169', NULL),
(15684, 9781, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-28 09:30:23', '10.0.11.245', NULL),
(15685, 9782, 'requester', 'intake', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-05-25 16:53:10', '10.0.20.185', NULL),
(15686, 9782, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-26 10:45:23', '10.0.2.91', NULL),
(15687, 9782, 'requester', 'return', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-05-26 23:26:29', '10.0.4.162', NULL),
(15688, 9782, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-28 08:43:37', '10.0.12.14', NULL),
(15689, 9783, 'requester', 'intake', 1, 1417, 'ticket-private/dm_sig_1417.png', 'draw', '2026-05-06 09:39:47', '10.0.18.53', NULL),
(15690, 9783, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-08 15:45:42', '10.0.18.38', NULL),
(15691, 9783, 'requester', 'return', 1, 1417, 'ticket-private/dm_sig_1417.png', 'draw', '2026-05-09 07:19:01', '10.0.1.202', NULL),
(15692, 9783, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-11 08:40:00', '10.0.8.155', 'ตรวจรับเรียบร้อย'),
(15693, 9784, 'requester', 'intake', 1, 1411, 'ticket-private/dm_sig_1411.png', 'draw', '2026-05-04 11:21:28', '10.0.19.80', NULL),
(15694, 9784, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-04 13:20:31', '10.0.20.110', NULL),
(15695, 9784, 'requester', 'return', 1, 1411, 'ticket-private/dm_sig_1411.png', 'draw', '2026-05-05 07:35:49', '10.0.8.57', NULL),
(15696, 9784, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-05 13:35:53', '10.0.3.120', 'ตรวจรับเรียบร้อย'),
(15697, 9786, 'requester', 'intake', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-05-13 10:05:45', '10.0.2.225', NULL),
(15698, 9786, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-15 09:17:45', '10.0.1.220', NULL),
(15699, 9786, 'requester', 'return', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-05-15 21:07:29', '10.0.18.26', NULL),
(15700, 9786, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-18 08:40:00', '10.0.5.81', 'ตรวจรับเรียบร้อย'),
(15701, 9787, 'requester', 'intake', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-05-25 10:38:10', '10.0.7.113', NULL),
(15702, 9787, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-26 09:49:42', '10.0.10.3', NULL),
(15703, 9787, 'requester', 'return', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-05-26 19:37:46', '10.0.6.58', NULL),
(15704, 9787, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-28 08:42:16', '10.0.7.39', 'ตรวจรับเรียบร้อย'),
(15705, 9788, 'requester', 'intake', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-05-26 11:22:20', '10.0.13.46', NULL),
(15706, 9788, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-29 09:31:52', '10.0.10.50', NULL),
(15707, 9788, 'requester', 'return', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-05-29 18:24:38', '10.0.2.34', NULL),
(15708, 9788, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-01 08:40:00', '10.0.14.27', 'ตรวจรับเรียบร้อย'),
(15709, 9789, 'requester', 'intake', 1, 1411, 'ticket-private/dm_sig_1411.png', 'draw', '2026-05-11 10:08:56', '10.0.18.92', NULL),
(15710, 9789, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-11 12:36:32', '10.0.2.145', NULL),
(15711, 9789, 'requester', 'return', 1, 1411, 'ticket-private/dm_sig_1411.png', 'draw', '2026-05-11 12:57:02', '10.0.5.114', NULL),
(15712, 9789, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-11 13:17:33', '10.0.12.192', NULL),
(15713, 9790, 'requester', 'intake', 1, 1415, 'ticket-private/dm_sig_1415.png', 'draw', '2026-05-21 10:55:36', '10.0.17.8', NULL),
(15714, 9790, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-21 14:10:46', '10.0.15.228', NULL),
(15715, 9790, 'requester', 'return', 1, 1415, 'ticket-private/dm_sig_1415.png', 'draw', '2026-05-21 18:46:09', '10.0.18.232', NULL),
(15716, 9790, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-22 09:22:00', '10.0.6.98', NULL),
(15717, 9791, 'requester', 'intake', 1, 1378, 'ticket-private/dm_sig_1378.png', 'draw', '2026-05-08 14:22:00', '10.0.19.240', NULL),
(15718, 9791, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-08 15:25:52', '10.0.3.247', NULL),
(15719, 9791, 'requester', 'return', 1, 1378, 'ticket-private/dm_sig_1378.png', 'draw', '2026-05-08 19:49:09', '10.0.8.91', NULL),
(15720, 9791, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-11 08:40:00', '10.0.12.232', 'ตรวจรับเรียบร้อย'),
(15721, 9792, 'requester', 'intake', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-05-11 10:08:52', '10.0.4.176', NULL),
(15722, 9792, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-13 09:45:36', '10.0.10.200', NULL),
(15723, 9792, 'requester', 'return', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-05-14 09:02:33', '10.0.14.141', NULL),
(15724, 9792, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-15 09:10:54', '10.0.20.235', NULL),
(15725, 9793, 'requester', 'intake', 1, 1376, 'ticket-private/dm_sig_1376.png', 'draw', '2026-05-07 09:27:12', '10.0.5.71', NULL),
(15726, 9793, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-08 16:09:20', '10.0.13.76', NULL),
(15727, 9793, 'requester', 'return', 1, 1376, 'ticket-private/dm_sig_1376.png', 'draw', '2026-05-09 05:55:05', '10.0.10.220', NULL),
(15728, 9793, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-11 08:40:00', '10.0.18.12', 'ตรวจรับเรียบร้อย'),
(15729, 9794, 'requester', 'intake', 1, 1376, 'ticket-private/dm_sig_1376.png', 'draw', '2026-05-11 10:39:28', '10.0.20.24', NULL),
(15730, 9794, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-14 09:34:37', '10.0.1.229', NULL),
(15731, 9794, 'requester', 'return', 1, 1376, 'ticket-private/dm_sig_1376.png', 'draw', '2026-05-14 13:59:13', '10.0.20.9', NULL),
(15732, 9794, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-18 08:40:00', '10.0.15.95', NULL),
(15733, 9795, 'requester', 'intake', 1, 1407, 'ticket-private/dm_sig_1407.png', 'draw', '2026-05-12 09:53:29', '10.0.3.250', NULL),
(15734, 9795, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-14 16:02:18', '10.0.2.132', NULL),
(15735, 9795, 'requester', 'return', 1, 1407, 'ticket-private/dm_sig_1407.png', 'draw', '2026-05-15 11:40:44', '10.0.12.67', NULL),
(15736, 9795, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-18 08:40:00', '10.0.9.237', NULL),
(15737, 9796, 'requester', 'intake', 1, 1392, 'ticket-private/dm_sig_1392.png', 'draw', '2026-05-15 10:50:51', '10.0.6.118', NULL),
(15738, 9796, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-18 08:58:20', '10.0.12.40', NULL),
(15739, 9796, 'requester', 'return', 1, 1392, 'ticket-private/dm_sig_1392.png', 'draw', '2026-05-19 03:57:51', '10.0.6.11', NULL),
(15740, 9796, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-19 14:17:01', '10.0.16.63', NULL),
(15741, 9797, 'requester', 'intake', 1, 1390, 'ticket-private/dm_sig_1390.png', 'draw', '2026-05-22 12:07:31', '10.0.20.10', NULL),
(15742, 9797, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-25 09:44:41', '10.0.16.138', NULL),
(15743, 9797, 'requester', 'return', 1, 1390, 'ticket-private/dm_sig_1390.png', 'draw', '2026-05-26 08:09:09', '10.0.15.5', NULL),
(15744, 9797, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-26 09:30:41', '10.0.18.113', NULL),
(15745, 9798, 'requester', 'intake', 1, 1430, 'ticket-private/dm_sig_1430.png', 'draw', '2026-05-18 16:03:18', '10.0.11.72', NULL),
(15746, 9798, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-21 09:45:34', '10.0.19.242', NULL),
(15747, 9798, 'requester', 'return', 1, 1430, 'ticket-private/dm_sig_1430.png', 'draw', '2026-05-21 17:27:46', '10.0.16.107', NULL),
(15748, 9798, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-25 08:40:00', '10.0.5.118', NULL),
(15749, 9800, 'requester', 'intake', 1, 1416, 'ticket-private/dm_sig_1416.png', 'draw', '2026-05-15 10:20:51', '10.0.6.90', NULL),
(15750, 9800, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-15 12:32:00', '10.0.8.87', NULL),
(15751, 9800, 'requester', 'return', 1, 1416, 'ticket-private/dm_sig_1416.png', 'draw', '2026-05-15 22:27:35', '10.0.5.157', NULL),
(15752, 9800, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-18 08:40:00', '10.0.10.149', 'ตรวจรับเรียบร้อย'),
(15753, 9801, 'requester', 'intake', 1, 1390, 'ticket-private/dm_sig_1390.png', 'draw', '2026-05-15 10:33:41', '10.0.10.20', NULL),
(15754, 9801, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-05-18 09:07:59', '10.0.11.209', NULL),
(15755, 9801, 'requester', 'return', 1, 1390, 'ticket-private/dm_sig_1390.png', 'draw', '2026-05-18 19:11:04', '10.0.11.202', NULL),
(15756, 9801, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-19 08:56:53', '10.0.15.82', NULL),
(15757, 9802, 'requester', 'intake', 1, 1417, 'ticket-private/dm_sig_1417.png', 'draw', '2026-05-11 12:13:03', '10.0.12.169', NULL),
(15758, 9802, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-13 10:15:01', '10.0.19.71', NULL),
(15759, 9802, 'requester', 'return', 1, 1417, 'ticket-private/dm_sig_1417.png', 'draw', '2026-05-14 08:19:56', '10.0.3.217', NULL),
(15760, 9802, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-05-15 09:12:19', '10.0.8.76', NULL),
(15761, 9803, 'requester', 'intake', 1, 1409, 'ticket-private/dm_sig_1409.png', 'draw', '2026-05-19 16:26:16', '10.0.18.182', NULL),
(15762, 9803, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-05-21 09:15:26', '10.0.10.83', NULL),
(15763, 9803, 'requester', 'return', 1, 1409, 'ticket-private/dm_sig_1409.png', 'draw', '2026-05-21 11:26:34', '10.0.7.113', NULL),
(15764, 9803, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-05-22 08:47:23', '10.0.15.36', NULL),
(15765, 9804, 'requester', 'intake', 1, 1404, 'ticket-private/dm_sig_1404.png', 'draw', '2026-06-16 11:01:06', '10.0.11.37', NULL),
(15766, 9804, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-16 16:04:11', '10.0.13.178', NULL),
(15767, 9804, 'requester', 'return', 1, 1404, 'ticket-private/dm_sig_1404.png', 'draw', '2026-06-16 22:31:57', '10.0.12.199', NULL),
(15768, 9804, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-18 15:44:48', '10.0.1.191', NULL),
(15769, 9806, 'requester', 'intake', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-06-16 10:36:56', '10.0.12.124', NULL),
(15770, 9806, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-17 10:05:50', '10.0.2.126', NULL),
(15771, 9806, 'requester', 'return', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-06-17 12:48:26', '10.0.14.180', NULL),
(15772, 9806, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-18 08:45:45', '10.0.16.178', NULL),
(15773, 9807, 'requester', 'intake', 1, 1431, 'ticket-private/dm_sig_1431.png', 'draw', '2026-06-12 10:24:17', '10.0.14.109', NULL),
(15774, 9807, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-15 10:25:51', '10.0.9.174', NULL),
(15775, 9807, 'requester', 'return', 1, 1431, 'ticket-private/dm_sig_1431.png', 'draw', '2026-06-15 11:02:59', '10.0.12.213', NULL),
(15776, 9807, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-16 08:43:43', '10.0.16.201', NULL),
(15777, 9808, 'requester', 'intake', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-06-23 09:28:54', '10.0.3.35', NULL),
(15778, 9808, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-25 09:46:48', '10.0.12.63', NULL),
(15779, 9808, 'requester', 'return', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-06-25 11:12:16', '10.0.10.61', NULL),
(15780, 9808, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-25 12:37:45', '10.0.13.159', 'ตรวจรับเรียบร้อย'),
(15781, 9809, 'requester', 'intake', 1, 1375, 'ticket-private/dm_sig_1375.png', 'draw', '2026-06-16 10:28:32', '10.0.15.149', NULL),
(15782, 9809, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-17 09:50:52', '10.0.18.5', NULL),
(15783, 9809, 'requester', 'return', 1, 1375, 'ticket-private/dm_sig_1375.png', 'draw', '2026-06-17 19:05:58', '10.0.19.157', NULL),
(15784, 9809, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-19 08:41:46', '10.0.2.46', NULL),
(15785, 9810, 'requester', 'intake', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-06-15 11:14:46', '10.0.14.139', NULL),
(15786, 9810, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-15 14:17:56', '10.0.5.227', NULL),
(15787, 9810, 'requester', 'return', 1, 1366, NULL, 'skip', '2026-06-16 09:42:11', '10.0.7.68', 'ข้ามขั้นผู้แจ้ง: ผู้แจ้งไม่เข้าระบบเกินกำหนด รับอุปกรณ์คืนแล้ว'),
(15788, 9810, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-17 12:33:33', '10.0.13.87', NULL),
(15789, 9811, 'requester', 'intake', 1, 1367, NULL, 'skip', '2026-06-24 10:57:26', '10.0.19.100', 'ข้ามขั้นผู้แจ้ง: ติดต่อผู้แจ้งไม่ได้ ครูรับทราบรายการแทน'),
(15790, 9811, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-29 09:30:06', '10.0.5.77', NULL),
(15791, 9811, 'requester', 'return', 1, 1383, 'ticket-private/dm_sig_1383.png', 'draw', '2026-06-29 22:27:00', '10.0.4.205', NULL),
(15792, 9811, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-01 09:17:12', '10.0.11.47', NULL),
(15793, 9812, 'requester', 'intake', 1, 1387, 'ticket-private/dm_sig_1387.png', 'draw', '2026-06-30 10:46:38', '10.0.13.130', NULL),
(15794, 9812, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-01 08:58:41', '10.0.2.172', NULL),
(15795, 9812, 'requester', 'return', 1, 1387, 'ticket-private/dm_sig_1387.png', 'draw', '2026-07-01 10:37:14', '10.0.6.21', NULL),
(15796, 9812, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-01 12:15:47', '10.0.16.224', NULL),
(15797, 9813, 'requester', 'intake', 1, 1381, 'ticket-private/dm_sig_1381.png', 'draw', '2026-06-22 10:47:07', '10.0.13.44', NULL),
(15798, 9813, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-24 13:26:19', '10.0.6.93', NULL),
(15799, 9813, 'requester', 'return', 1, 1381, 'ticket-private/dm_sig_1381.png', 'draw', '2026-06-24 23:28:12', '10.0.16.32', NULL),
(15800, 9813, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-25 09:30:06', '10.0.18.93', 'ตรวจรับเรียบร้อย'),
(15801, 9814, 'requester', 'intake', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-06-16 09:57:18', '10.0.20.245', NULL),
(15802, 9814, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-17 14:32:27', '10.0.5.160', NULL),
(15803, 9814, 'requester', 'return', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-06-17 21:32:42', '10.0.18.51', NULL),
(15804, 9814, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-18 08:58:16', '10.0.13.177', NULL),
(15805, 9815, 'requester', 'intake', 1, 1418, 'ticket-private/dm_sig_1418.png', 'draw', '2026-06-24 15:24:37', '10.0.19.86', NULL),
(15806, 9815, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-25 08:57:55', '10.0.12.101', NULL),
(15807, 9815, 'requester', 'return', 1, 1418, 'ticket-private/dm_sig_1418.png', 'draw', '2026-06-26 04:06:16', '10.0.12.205', NULL),
(15808, 9815, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-26 09:08:18', '10.0.4.80', NULL),
(15809, 9816, 'requester', 'intake', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-06-25 11:05:04', '10.0.13.43', NULL),
(15810, 9816, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-29 09:06:18', '10.0.8.30', NULL),
(15811, 9816, 'requester', 'return', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-06-29 13:26:39', '10.0.9.121', NULL),
(15812, 9816, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-30 09:25:14', '10.0.9.17', NULL),
(15813, 9817, 'requester', 'intake', 1, 1430, 'ticket-private/dm_sig_1430.png', 'draw', '2026-06-30 09:38:43', '10.0.7.67', NULL),
(15814, 9817, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-03 09:54:25', '10.0.10.242', NULL),
(15815, 9817, 'requester', 'return', 1, 1430, 'ticket-private/dm_sig_1430.png', 'draw', '2026-07-03 19:50:54', '10.0.2.190', NULL),
(15816, 9817, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-06 08:40:00', '10.0.14.213', 'ตรวจรับเรียบร้อย'),
(15817, 9818, 'requester', 'intake', 1, 1429, 'ticket-private/dm_sig_1429.png', 'draw', '2026-06-16 11:19:28', '10.0.16.123', NULL),
(15818, 9818, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-16 12:10:29', '10.0.7.48', NULL),
(15819, 9818, 'requester', 'return', 1, 1429, 'ticket-private/dm_sig_1429.png', 'draw', '2026-06-17 06:54:58', '10.0.18.114', NULL),
(15820, 9818, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-17 08:55:21', '10.0.16.159', NULL),
(15821, 9820, 'requester', 'intake', 1, 1418, 'ticket-private/dm_sig_1418.png', 'draw', '2026-06-22 10:30:52', '10.0.16.7', NULL),
(15822, 9820, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-22 13:12:09', '10.0.8.139', NULL),
(15823, 9820, 'requester', 'return', 1, 1418, 'ticket-private/dm_sig_1418.png', 'draw', '2026-06-23 01:43:36', '10.0.11.56', NULL),
(15824, 9820, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-24 08:52:10', '10.0.18.41', NULL),
(15825, 9821, 'requester', 'intake', 1, 1366, NULL, 'skip', '2026-06-18 09:34:11', '10.0.19.210', 'ข้ามขั้นผู้แจ้ง: ติดต่อผู้แจ้งไม่ได้ ครูรับทราบรายการแทน'),
(15826, 9821, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-19 09:40:28', '10.0.11.242', NULL),
(15827, 9821, 'requester', 'return', 1, 1407, 'ticket-private/dm_sig_1407.png', 'draw', '2026-06-20 07:43:01', '10.0.17.197', NULL),
(15828, 9821, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-22 08:40:00', '10.0.15.136', NULL),
(15829, 9822, 'requester', 'intake', 1, 1407, 'ticket-private/dm_sig_1407.png', 'draw', '2026-06-10 10:20:04', '10.0.1.233', NULL),
(15830, 9822, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-10 10:55:06', '10.0.12.64', NULL),
(15831, 9822, 'requester', 'return', 1, 1407, 'ticket-private/dm_sig_1407.png', 'draw', '2026-06-11 07:16:56', '10.0.8.135', NULL),
(15832, 9822, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-11 09:13:46', '10.0.14.152', NULL),
(15833, 9823, 'requester', 'intake', 1, 1414, 'ticket-private/dm_sig_1414.png', 'draw', '2026-06-10 09:59:59', '10.0.10.148', NULL),
(15834, 9823, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-12 10:08:10', '10.0.17.131', NULL),
(15835, 9823, 'requester', 'return', 1, 1414, 'ticket-private/dm_sig_1414.png', 'draw', '2026-06-13 08:48:28', '10.0.4.18', NULL),
(15836, 9823, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-15 08:40:00', '10.0.13.185', 'ตรวจรับเรียบร้อย'),
(15837, 9824, 'requester', 'intake', 1, 1390, 'ticket-private/dm_sig_1390.png', 'draw', '2026-06-19 10:04:31', '10.0.5.211', NULL),
(15838, 9824, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-22 09:27:15', '10.0.2.5', NULL),
(15839, 9824, 'requester', 'return', 1, 1390, 'ticket-private/dm_sig_1390.png', 'draw', '2026-06-23 06:14:23', '10.0.3.157', NULL),
(15840, 9824, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-23 08:52:46', '10.0.3.12', NULL),
(15841, 9825, 'requester', 'intake', 1, 1390, 'ticket-private/dm_sig_1390.png', 'draw', '2026-06-16 10:34:06', '10.0.7.97', NULL),
(15842, 9825, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-19 08:52:11', '10.0.6.29', NULL),
(15843, 9825, 'requester', 'return', 1, 1390, 'ticket-private/dm_sig_1390.png', 'draw', '2026-06-19 11:59:29', '10.0.14.10', NULL),
(15844, 9825, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-22 08:40:00', '10.0.5.236', NULL),
(15845, 9826, 'requester', 'intake', 1, 1366, NULL, 'skip', '2026-06-05 10:58:58', '10.0.3.65', 'ข้ามขั้นผู้แจ้ง: ติดต่อผู้แจ้งไม่ได้ ครูรับทราบรายการแทน'),
(15846, 9826, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-05 15:46:06', '10.0.20.239', NULL),
(15847, 9826, 'requester', 'return', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-06-05 23:51:07', '10.0.2.221', NULL),
(15848, 9826, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-08 08:40:00', '10.0.14.78', NULL),
(15849, 9827, 'requester', 'intake', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-06-17 11:04:10', '10.0.17.78', NULL),
(15850, 9827, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-19 09:29:24', '10.0.12.120', NULL),
(15851, 9827, 'requester', 'return', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-06-19 14:27:05', '10.0.7.219', NULL),
(15852, 9827, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-22 08:40:00', '10.0.2.210', 'ตรวจรับเรียบร้อย'),
(15853, 9828, 'requester', 'intake', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-06-15 11:01:16', '10.0.15.4', NULL),
(15854, 9828, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-16 12:41:10', '10.0.20.227', NULL),
(15855, 9828, 'requester', 'return', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-06-16 23:44:18', '10.0.12.62', NULL),
(15856, 9828, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-06-17 09:25:03', '10.0.2.137', NULL),
(15857, 9829, 'requester', 'intake', 1, 1383, 'ticket-private/dm_sig_1383.png', 'draw', '2026-06-08 15:00:53', '10.0.10.53', NULL),
(15858, 9829, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-09 08:57:28', '10.0.20.9', NULL),
(15859, 9829, 'requester', 'return', 1, 1383, 'ticket-private/dm_sig_1383.png', 'draw', '2026-06-09 10:07:41', '10.0.11.12', NULL),
(15860, 9829, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-11 09:17:21', '10.0.11.154', NULL),
(15861, 9830, 'requester', 'intake', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-06-10 11:30:37', '10.0.16.116', NULL),
(15862, 9830, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-06-10 12:21:13', '10.0.6.216', NULL),
(15863, 9830, 'requester', 'return', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-06-11 00:47:26', '10.0.3.142', NULL),
(15864, 9830, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-11 08:35:35', '10.0.20.10', NULL),
(15865, 9831, 'requester', 'intake', 1, 1379, 'ticket-private/dm_sig_1379.png', 'draw', '2026-06-16 10:45:06', '10.0.12.29', NULL),
(15866, 9831, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-06-17 09:29:20', '10.0.13.237', NULL),
(15867, 9831, 'requester', 'return', 1, 1379, 'ticket-private/dm_sig_1379.png', 'draw', '2026-06-18 03:53:18', '10.0.15.243', NULL),
(15868, 9831, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-06-18 10:57:48', '10.0.10.118', NULL),
(15869, 9832, 'requester', 'intake', 1, 1429, 'ticket-private/dm_sig_1429.png', 'draw', '2026-07-06 17:38:12', '10.0.8.146', NULL),
(15870, 9832, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-08 16:32:26', '10.0.17.176', NULL),
(15871, 9832, 'requester', 'return', 1, 1429, 'ticket-private/dm_sig_1429.png', 'draw', '2026-07-09 06:11:39', '10.0.8.168', NULL),
(15872, 9832, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-09 08:43:23', '10.0.2.127', NULL),
(15873, 9833, 'requester', 'intake', 1, 1394, 'ticket-private/dm_sig_1394.png', 'draw', '2026-07-21 12:35:34', '10.0.3.108', NULL),
(15874, 9833, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-21 15:35:59', '10.0.14.78', NULL),
(15875, 9833, 'requester', 'return', 1, 1394, 'ticket-private/dm_sig_1394.png', 'draw', '2026-07-21 18:58:31', '10.0.2.208', NULL),
(15876, 9833, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-23 09:12:17', '10.0.4.10', NULL),
(15877, 9834, 'requester', 'intake', 1, 1389, 'ticket-private/dm_sig_1389.png', 'draw', '2026-07-09 09:20:54', '10.0.17.152', NULL),
(15878, 9834, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-10 09:57:27', '10.0.16.190', NULL),
(15879, 9834, 'requester', 'return', 1, 1389, 'ticket-private/dm_sig_1389.png', 'draw', '2026-07-11 02:34:24', '10.0.5.186', NULL),
(15880, 9834, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-13 08:40:00', '10.0.9.51', NULL),
(15881, 9835, 'requester', 'intake', 1, 1375, 'ticket-private/dm_sig_1375.png', 'draw', '2026-07-13 09:47:50', '10.0.13.115', NULL),
(15882, 9835, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-13 13:18:42', '10.0.15.72', NULL),
(15883, 9835, 'requester', 'return', 1, 1375, 'ticket-private/dm_sig_1375.png', 'draw', '2026-07-13 18:37:10', '10.0.12.30', NULL),
(15884, 9835, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-15 09:21:29', '10.0.7.128', 'ตรวจรับเรียบร้อย'),
(15885, 9836, 'requester', 'intake', 1, 1418, 'ticket-private/dm_sig_1418.png', 'draw', '2026-07-06 13:24:34', '10.0.12.36', NULL),
(15886, 9836, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-07 09:29:21', '10.0.16.19', NULL),
(15887, 9836, 'requester', 'return', 1, 1418, 'ticket-private/dm_sig_1418.png', 'draw', '2026-07-08 06:45:45', '10.0.1.247', NULL),
(15888, 9836, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-08 09:32:31', '10.0.7.17', 'ตรวจรับเรียบร้อย'),
(15889, 9837, 'requester', 'intake', 1, 1403, 'ticket-private/dm_sig_1403.png', 'draw', '2026-07-06 16:18:08', '10.0.1.90', NULL),
(15890, 9837, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-08 16:14:31', '10.0.9.47', NULL),
(15891, 9837, 'requester', 'return', 1, 1403, 'ticket-private/dm_sig_1403.png', 'draw', '2026-07-09 00:33:42', '10.0.1.5', NULL),
(15892, 9837, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-09 08:52:54', '10.0.17.237', NULL),
(15893, 9838, 'requester', 'intake', 1, 1387, 'ticket-private/dm_sig_1387.png', 'draw', '2026-07-07 09:32:38', '10.0.13.174', NULL),
(15894, 9838, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-09 09:24:52', '10.0.7.153', NULL),
(15895, 9838, 'requester', 'return', 1, 1387, 'ticket-private/dm_sig_1387.png', 'draw', '2026-07-09 22:47:29', '10.0.15.94', NULL),
(15896, 9838, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-10 09:12:06', '10.0.4.102', 'ตรวจรับเรียบร้อย'),
(15897, 9839, 'requester', 'intake', 1, 1375, 'ticket-private/dm_sig_1375.png', 'draw', '2026-07-28 10:08:27', '10.0.13.160', NULL),
(15898, 9839, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-30 09:20:26', '10.0.13.241', NULL),
(15899, 9839, 'requester', 'return', 1, 1375, 'ticket-private/dm_sig_1375.png', 'draw', '2026-07-31 00:26:16', '10.0.6.41', NULL),
(15900, 9839, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-08-03 08:40:00', '10.0.16.158', 'ตรวจรับเรียบร้อย'),
(15901, 9840, 'requester', 'intake', 1, 1431, 'ticket-private/dm_sig_1431.png', 'draw', '2026-07-06 14:22:26', '10.0.3.8', NULL),
(15902, 9840, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-08 09:52:49', '10.0.15.95', NULL),
(15903, 9840, 'requester', 'return', 1, 1431, 'ticket-private/dm_sig_1431.png', 'draw', '2026-07-08 15:00:44', '10.0.20.232', NULL),
(15904, 9840, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-09 09:05:42', '10.0.16.54', 'ตรวจรับเรียบร้อย'),
(15905, 9842, 'requester', 'intake', 1, 1427, 'ticket-private/dm_sig_1427.png', 'draw', '2026-07-27 16:35:17', '10.0.10.167', NULL),
(15906, 9842, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-28 09:11:46', '10.0.10.173', NULL),
(15907, 9842, 'requester', 'return', 1, 1427, 'ticket-private/dm_sig_1427.png', 'draw', '2026-07-28 10:02:54', '10.0.11.9', NULL),
(15908, 9842, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-29 08:40:47', '10.0.18.124', 'ตรวจรับเรียบร้อย'),
(15909, 9843, 'requester', 'intake', 1, 1422, 'ticket-private/dm_sig_1422.png', 'draw', '2026-07-06 18:22:34', '10.0.9.192', NULL),
(15910, 9843, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-07 09:41:15', '10.0.13.199', NULL),
(15911, 9843, 'requester', 'return', 1, 1422, 'ticket-private/dm_sig_1422.png', 'draw', '2026-07-08 07:13:20', '10.0.19.163', NULL),
(15912, 9843, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-08 14:38:58', '10.0.16.28', 'ตรวจรับเรียบร้อย'),
(15913, 9844, 'requester', 'intake', 1, 1376, 'ticket-private/dm_sig_1376.png', 'draw', '2026-08-03 10:20:08', '10.0.4.186', NULL),
(15914, 9844, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-08-05 17:02:12', '10.0.1.203', NULL),
(15915, 9844, 'requester', 'return', 1, 1376, 'ticket-private/dm_sig_1376.png', 'draw', '2026-08-06 05:31:50', '10.0.7.180', NULL),
(15916, 9844, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-06 09:34:24', '10.0.2.177', NULL),
(15917, 9845, 'requester', 'intake', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-07-24 10:19:56', '10.0.3.121', NULL),
(15918, 9845, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-27 09:15:30', '10.0.2.80', NULL),
(15919, 9845, 'requester', 'return', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-07-27 22:04:31', '10.0.7.244', NULL),
(15920, 9845, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-29 08:38:42', '10.0.3.120', 'ตรวจรับเรียบร้อย'),
(15921, 9846, 'requester', 'intake', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-07-30 10:47:25', '10.0.17.62', NULL),
(15922, 9846, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-30 15:30:28', '10.0.4.205', NULL),
(15923, 9846, 'requester', 'return', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-07-31 01:54:47', '10.0.19.4', NULL),
(15924, 9846, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-31 11:47:44', '10.0.18.24', NULL),
(15925, 9847, 'requester', 'intake', 1, 1425, 'ticket-private/dm_sig_1425.png', 'draw', '2026-07-28 10:59:09', '10.0.16.117', NULL),
(15926, 9847, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-30 09:10:40', '10.0.11.89', NULL),
(15927, 9847, 'requester', 'return', 1, 1425, 'ticket-private/dm_sig_1425.png', 'draw', '2026-07-31 00:21:43', '10.0.8.198', NULL),
(15928, 9847, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-03 08:40:00', '10.0.15.117', NULL),
(15929, 9848, 'requester', 'intake', 1, 1422, 'ticket-private/dm_sig_1422.png', 'draw', '2026-08-03 10:39:53', '10.0.11.185', NULL),
(15930, 9848, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-05 09:18:47', '10.0.19.235', NULL),
(15931, 9848, 'requester', 'return', 1, 1422, 'ticket-private/dm_sig_1422.png', 'draw', '2026-08-06 09:09:30', '10.0.18.110', NULL),
(15932, 9848, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-06 11:01:50', '10.0.3.187', 'ตรวจรับเรียบร้อย'),
(15933, 9849, 'requester', 'intake', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-07-28 09:53:05', '10.0.19.12', NULL),
(15934, 9849, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-29 09:31:38', '10.0.11.73', NULL),
(15935, 9849, 'requester', 'return', 1, 1367, NULL, 'skip', '2026-07-29 11:55:02', '10.0.4.129', 'ข้ามขั้นผู้แจ้ง: ผู้แจ้งไม่เข้าระบบเกินกำหนด รับอุปกรณ์คืนแล้ว'),
(15936, 9849, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-29 14:18:27', '10.0.10.34', 'ตรวจรับเรียบร้อย'),
(15937, 9850, 'requester', 'intake', 1, 1429, 'ticket-private/dm_sig_1429.png', 'draw', '2026-07-09 12:04:00', '10.0.1.233', NULL),
(15938, 9850, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-07-09 13:50:08', '10.0.19.43', NULL),
(15939, 9850, 'requester', 'return', 1, 1429, 'ticket-private/dm_sig_1429.png', 'draw', '2026-07-10 04:51:20', '10.0.16.96', NULL),
(15940, 9850, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-07-10 14:02:05', '10.0.14.161', NULL),
(15941, 9851, 'requester', 'intake', 1, 1383, 'ticket-private/dm_sig_1383.png', 'draw', '2026-07-29 10:43:04', '10.0.3.189', NULL),
(15942, 9851, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-07-29 11:51:20', '10.0.20.75', NULL),
(15943, 9851, 'requester', 'return', 1, 1383, 'ticket-private/dm_sig_1383.png', 'draw', '2026-07-29 20:36:51', '10.0.19.163', NULL),
(15944, 9851, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-07-30 08:55:20', '10.0.11.105', 'ตรวจรับเรียบร้อย'),
(15945, 9853, 'requester', 'intake', 1, 1413, 'ticket-private/dm_sig_1413.png', 'draw', '2026-08-26 10:15:18', '10.0.17.43', NULL),
(15946, 9853, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-28 09:23:57', '10.0.11.238', NULL),
(15947, 9853, 'requester', 'return', 1, 1413, 'ticket-private/dm_sig_1413.png', 'draw', '2026-08-28 16:20:59', '10.0.5.160', NULL),
(15948, 9853, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-31 08:40:00', '10.0.4.33', NULL),
(15949, 9854, 'requester', 'intake', 1, 1404, 'ticket-private/dm_sig_1404.png', 'draw', '2026-08-28 09:44:45', '10.0.9.208', NULL),
(15950, 9854, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-08-31 09:04:58', '10.0.4.27', NULL),
(15951, 9854, 'requester', 'return', 1, 1404, 'ticket-private/dm_sig_1404.png', 'draw', '2026-09-01 08:11:03', '10.0.9.11', NULL),
(15952, 9854, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-02 09:05:18', '10.0.3.47', NULL),
(15953, 9855, 'requester', 'intake', 1, 1396, 'ticket-private/dm_sig_1396.png', 'draw', '2026-08-17 13:15:21', '10.0.19.111', NULL),
(15954, 9855, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-17 14:37:08', '10.0.17.176', NULL),
(15955, 9855, 'requester', 'return', 1, 1396, 'ticket-private/dm_sig_1396.png', 'draw', '2026-08-17 16:14:03', '10.0.2.56', NULL),
(15956, 9855, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-19 13:37:51', '10.0.7.26', NULL),
(15957, 9856, 'requester', 'intake', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-08-24 10:01:28', '10.0.12.9', NULL),
(15958, 9856, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-24 17:26:06', '10.0.20.220', NULL),
(15959, 9856, 'requester', 'return', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-08-24 18:39:36', '10.0.10.18', NULL),
(15960, 9856, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-08-25 13:44:48', '10.0.12.162', NULL),
(15961, 9857, 'requester', 'intake', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-08-31 14:52:10', '10.0.17.153', NULL),
(15962, 9857, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-03 08:55:45', '10.0.11.250', NULL),
(15963, 9857, 'requester', 'return', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-09-03 18:08:13', '10.0.6.151', NULL),
(15964, 9857, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-04 12:11:52', '10.0.12.191', NULL),
(15965, 9858, 'requester', 'intake', 1, 1419, 'ticket-private/dm_sig_1419.png', 'draw', '2026-08-27 11:58:20', '10.0.3.204', NULL),
(15966, 9858, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-08-27 13:37:28', '10.0.15.91', NULL),
(15967, 9858, 'requester', 'return', 1, 1419, 'ticket-private/dm_sig_1419.png', 'draw', '2026-08-28 10:20:30', '10.0.1.124', NULL),
(15968, 9858, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-28 10:51:20', '10.0.3.99', NULL),
(15969, 9859, 'requester', 'intake', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-08-31 09:58:10', '10.0.20.180', NULL),
(15970, 9859, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-02 10:05:11', '10.0.7.176', NULL),
(15971, 9859, 'requester', 'return', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-09-03 01:41:33', '10.0.9.203', NULL),
(15972, 9859, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-03 09:26:56', '10.0.17.41', NULL),
(15973, 9860, 'requester', 'intake', 1, 1421, 'ticket-private/dm_sig_1421.png', 'draw', '2026-08-17 13:26:21', '10.0.1.36', NULL),
(15974, 9860, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-18 09:45:48', '10.0.7.200', NULL),
(15975, 9860, 'requester', 'return', 1, 1421, 'ticket-private/dm_sig_1421.png', 'draw', '2026-08-18 10:29:53', '10.0.1.127', NULL),
(15976, 9860, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-08-18 11:13:59', '10.0.6.226', NULL),
(15977, 9862, 'requester', 'intake', 1, 1385, 'ticket-private/dm_sig_1385.png', 'draw', '2026-08-04 09:49:31', '10.0.18.212', NULL),
(15978, 9862, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-08-04 12:10:06', '10.0.15.40', NULL),
(15979, 9862, 'requester', 'return', 1, 1385, 'ticket-private/dm_sig_1385.png', 'draw', '2026-08-04 12:57:15', '10.0.17.97', NULL),
(15980, 9862, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-08-06 09:03:30', '10.0.9.9', 'ตรวจรับเรียบร้อย'),
(15981, 9863, 'requester', 'intake', 1, 1415, 'ticket-private/dm_sig_1415.png', 'draw', '2026-08-13 11:08:35', '10.0.18.112', NULL),
(15982, 9863, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-14 16:36:36', '10.0.4.219', NULL),
(15983, 9863, 'requester', 'return', 1, 1415, 'ticket-private/dm_sig_1415.png', 'draw', '2026-08-15 08:58:45', '10.0.10.54', NULL),
(15984, 9863, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-17 08:40:00', '10.0.15.119', NULL),
(15985, 9864, 'requester', 'intake', 1, 1396, 'ticket-private/dm_sig_1396.png', 'draw', '2026-08-11 10:21:30', '10.0.14.66', NULL),
(15986, 9864, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-08-13 08:51:51', '10.0.7.239', NULL),
(15987, 9864, 'requester', 'return', 1, 1396, 'ticket-private/dm_sig_1396.png', 'draw', '2026-08-13 11:07:24', '10.0.11.166', NULL),
(15988, 9864, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-14 08:54:07', '10.0.5.82', NULL),
(15989, 9865, 'requester', 'intake', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-08-06 09:56:09', '10.0.19.243', NULL),
(15990, 9865, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-07 09:12:47', '10.0.7.162', NULL),
(15991, 9865, 'requester', 'return', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-08-07 18:34:10', '10.0.20.171', NULL),
(15992, 9865, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-10 08:40:00', '10.0.8.18', NULL),
(15993, 9866, 'requester', 'intake', 1, 1381, 'ticket-private/dm_sig_1381.png', 'draw', '2026-08-10 14:18:43', '10.0.7.89', NULL),
(15994, 9866, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-08-13 11:40:06', '10.0.3.213', NULL),
(15995, 9866, 'requester', 'return', 1, 1381, 'ticket-private/dm_sig_1381.png', 'draw', '2026-08-13 21:17:46', '10.0.20.45', NULL),
(15996, 9866, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-17 08:40:00', '10.0.4.68', 'ตรวจรับเรียบร้อย'),
(15997, 9867, 'requester', 'intake', 1, 1409, 'ticket-private/dm_sig_1409.png', 'draw', '2026-08-10 10:40:17', '10.0.6.210', NULL),
(15998, 9867, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-08-11 10:06:12', '10.0.16.171', NULL),
(15999, 9867, 'requester', 'return', 1, 1409, 'ticket-private/dm_sig_1409.png', 'draw', '2026-08-11 11:54:49', '10.0.19.116', NULL),
(16000, 9867, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-12 09:06:27', '10.0.16.157', 'ตรวจรับเรียบร้อย'),
(16001, 9868, 'requester', 'intake', 1, 1379, 'ticket-private/dm_sig_1379.png', 'draw', '2026-08-04 18:02:50', '10.0.20.216', NULL),
(16002, 9868, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-08-05 10:15:12', '10.0.11.30', NULL),
(16003, 9868, 'requester', 'return', 1, 1379, 'ticket-private/dm_sig_1379.png', 'draw', '2026-08-05 23:00:32', '10.0.9.173', NULL),
(16004, 9868, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-08-07 08:58:03', '10.0.3.144', NULL),
(16005, 9869, 'requester', 'intake', 1, 1431, 'ticket-private/dm_sig_1431.png', 'draw', '2026-08-24 11:26:08', '10.0.20.10', NULL),
(16006, 9869, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-24 14:23:27', '10.0.14.222', NULL),
(16007, 9869, 'requester', 'return', 1, 1431, 'ticket-private/dm_sig_1431.png', 'draw', '2026-08-24 20:13:54', '10.0.11.175', NULL),
(16008, 9869, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-25 13:59:59', '10.0.7.73', 'ตรวจรับเรียบร้อย'),
(16009, 9871, 'requester', 'intake', 1, 1428, 'ticket-private/dm_sig_1428.png', 'draw', '2026-09-01 10:51:20', '10.0.4.86', NULL),
(16010, 9871, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-09-01 11:10:30', '10.0.16.43', NULL),
(16011, 9871, 'requester', 'return', 1, 1428, 'ticket-private/dm_sig_1428.png', 'draw', '2026-09-01 23:16:48', '10.0.9.158', NULL),
(16012, 9871, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-03 09:09:36', '10.0.8.47', 'ตรวจรับเรียบร้อย'),
(16013, 9872, 'requester', 'intake', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-09-01 09:58:18', '10.0.10.115', NULL),
(16014, 9872, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-01 13:33:48', '10.0.8.34', NULL),
(16015, 9872, 'requester', 'return', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-09-02 03:58:06', '10.0.14.144', NULL),
(16016, 9872, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-03 09:17:27', '10.0.10.188', 'ตรวจรับเรียบร้อย'),
(16017, 9873, 'requester', 'intake', 1, 1417, 'ticket-private/dm_sig_1417.png', 'draw', '2026-08-07 10:13:39', '10.0.18.213', NULL),
(16018, 9873, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-08-10 09:18:26', '10.0.5.219', NULL),
(16019, 9873, 'requester', 'return', 1, 1417, 'ticket-private/dm_sig_1417.png', 'draw', '2026-08-10 11:48:15', '10.0.6.117', NULL),
(16020, 9873, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-08-11 14:31:30', '10.0.4.160', NULL),
(16021, 9874, 'requester', 'intake', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-09-21 10:28:40', '10.0.6.57', NULL),
(16022, 9874, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-24 09:57:17', '10.0.5.231', NULL),
(16023, 9874, 'requester', 'return', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-09-24 19:55:44', '10.0.20.91', NULL),
(16024, 9874, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-25 12:48:31', '10.0.4.223', NULL),
(16025, 9875, 'requester', 'intake', 1, 1386, 'ticket-private/dm_sig_1386.png', 'draw', '2026-09-21 11:42:14', '10.0.3.64', NULL),
(16026, 9875, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-21 12:29:12', '10.0.14.61', NULL),
(16027, 9875, 'requester', 'return', 1, 1366, NULL, 'skip', '2026-09-22 04:32:54', '10.0.19.161', 'ข้ามขั้นผู้แจ้ง: ผู้แจ้งไม่เข้าระบบเกินกำหนด รับอุปกรณ์คืนแล้ว'),
(16028, 9875, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-22 09:02:18', '10.0.20.105', NULL),
(16029, 9876, 'requester', 'intake', 1, 1374, 'ticket-private/dm_sig_1374.png', 'draw', '2026-09-25 11:32:54', '10.0.1.190', NULL),
(16030, 9876, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-28 09:02:09', '10.0.10.168', NULL),
(16031, 9876, 'requester', 'return', 1, 1374, 'ticket-private/dm_sig_1374.png', 'draw', '2026-09-28 17:54:37', '10.0.11.190', NULL),
(16032, 9876, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-30 09:16:36', '10.0.1.95', 'ตรวจรับเรียบร้อย'),
(16033, 9877, 'requester', 'intake', 1, 1392, 'ticket-private/dm_sig_1392.png', 'draw', '2026-09-04 17:00:39', '10.0.9.244', NULL),
(16034, 9877, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-09-07 08:54:02', '10.0.14.18', NULL),
(16035, 9877, 'requester', 'return', 1, 1392, 'ticket-private/dm_sig_1392.png', 'draw', '2026-09-07 22:33:09', '10.0.7.189', NULL),
(16036, 9877, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-08 11:21:21', '10.0.9.125', NULL),
(16037, 9878, 'requester', 'intake', 1, 1420, 'ticket-private/dm_sig_1420.png', 'draw', '2026-09-29 10:59:26', '10.0.6.222', NULL),
(16038, 9878, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-09-30 09:50:43', '10.0.2.131', NULL),
(16039, 9878, 'requester', 'return', 1, 1420, 'ticket-private/dm_sig_1420.png', 'draw', '2026-09-30 19:52:45', '10.0.5.99', NULL),
(16040, 9878, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-10-01 09:05:39', '10.0.3.183', NULL),
(16041, 9879, 'requester', 'intake', 1, 1419, 'ticket-private/dm_sig_1419.png', 'draw', '2026-09-07 16:24:09', '10.0.13.81', NULL),
(16042, 9879, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-08 09:59:46', '10.0.13.173', NULL),
(16043, 9879, 'requester', 'return', 1, 1419, 'ticket-private/dm_sig_1419.png', 'draw', '2026-09-08 21:29:39', '10.0.7.56', NULL),
(16044, 9879, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-09 08:59:33', '10.0.18.40', NULL),
(16045, 9880, 'requester', 'intake', 1, 1406, 'ticket-private/dm_sig_1406.png', 'draw', '2026-09-28 09:08:02', '10.0.17.196', NULL),
(16046, 9880, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-29 09:01:00', '10.0.15.63', NULL),
(16047, 9880, 'requester', 'return', 1, 1406, 'ticket-private/dm_sig_1406.png', 'draw', '2026-09-29 11:52:52', '10.0.4.62', NULL),
(16048, 9880, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-30 08:51:44', '10.0.13.68', 'ตรวจรับเรียบร้อย');
INSERT INTO `ticket_signoffs` (`id`, `ticket_id`, `signer_role`, `stage`, `round_no`, `signer_id`, `signature_image`, `method`, `signed_at`, `ip`, `note`) VALUES
(16049, 9881, 'requester', 'intake', 1, 1392, 'ticket-private/dm_sig_1392.png', 'draw', '2026-09-04 15:30:08', '10.0.16.138', NULL),
(16050, 9881, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-09-07 09:33:03', '10.0.17.25', NULL),
(16051, 9881, 'requester', 'return', 1, 1392, 'ticket-private/dm_sig_1392.png', 'draw', '2026-09-07 14:52:02', '10.0.4.230', NULL),
(16052, 9881, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-09 08:59:25', '10.0.16.93', NULL),
(16053, 9882, 'requester', 'intake', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-09-23 15:06:19', '10.0.19.54', NULL),
(16054, 9882, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-25 09:59:19', '10.0.17.116', NULL),
(16055, 9882, 'requester', 'return', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-09-25 14:11:58', '10.0.20.187', NULL),
(16056, 9882, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-28 08:40:00', '10.0.10.164', NULL),
(16057, 9883, 'requester', 'intake', 1, 1387, 'ticket-private/dm_sig_1387.png', 'draw', '2026-09-29 10:56:30', '10.0.16.102', NULL),
(16058, 9883, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-10-01 09:34:16', '10.0.15.162', NULL),
(16059, 9883, 'requester', 'return', 1, 1387, 'ticket-private/dm_sig_1387.png', 'draw', '2026-10-01 11:01:13', '10.0.1.143', NULL),
(16060, 9883, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-10-02 11:18:22', '10.0.18.209', NULL),
(16061, 9884, 'requester', 'intake', 1, 1428, 'ticket-private/dm_sig_1428.png', 'draw', '2026-09-15 10:38:55', '10.0.15.243', NULL),
(16062, 9884, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-09-17 09:21:04', '10.0.13.169', NULL),
(16063, 9884, 'requester', 'return', 1, 1428, 'ticket-private/dm_sig_1428.png', 'draw', '2026-09-17 20:21:04', '10.0.5.54', NULL),
(16064, 9884, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-18 14:32:29', '10.0.19.218', NULL),
(16065, 9885, 'requester', 'intake', 1, 1409, 'ticket-private/dm_sig_1409.png', 'draw', '2026-09-14 09:46:44', '10.0.20.88', NULL),
(16066, 9885, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-16 09:29:33', '10.0.2.77', NULL),
(16067, 9885, 'requester', 'return', 1, 1409, 'ticket-private/dm_sig_1409.png', 'draw', '2026-09-16 10:45:18', '10.0.19.250', NULL),
(16068, 9885, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-16 12:01:03', '10.0.8.110', NULL),
(16069, 9886, 'requester', 'intake', 1, 1396, 'ticket-private/dm_sig_1396.png', 'draw', '2026-10-01 09:27:34', '10.0.14.221', NULL),
(16070, 9886, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-10-02 10:23:47', '10.0.15.104', NULL),
(16071, 9886, 'requester', 'return', 1, 1396, 'ticket-private/dm_sig_1396.png', 'draw', '2026-10-02 14:04:41', '10.0.20.45', NULL),
(16072, 9886, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-10-05 08:40:00', '10.0.15.56', NULL),
(16073, 9887, 'requester', 'intake', 1, 1405, 'ticket-private/dm_sig_1405.png', 'draw', '2026-09-15 10:33:22', '10.0.11.67', NULL),
(16074, 9887, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-09-18 10:06:20', '10.0.3.108', NULL),
(16075, 9887, 'requester', 'return', 1, 1405, 'ticket-private/dm_sig_1405.png', 'draw', '2026-09-18 21:13:15', '10.0.13.24', NULL),
(16076, 9887, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-21 08:40:00', '10.0.4.53', NULL),
(16077, 9888, 'requester', 'intake', 1, 1373, 'ticket-private/dm_sig_1373.png', 'draw', '2026-09-22 17:15:58', '10.0.5.170', NULL),
(16078, 9888, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-23 09:06:58', '10.0.8.6', NULL),
(16079, 9888, 'requester', 'return', 1, 1373, 'ticket-private/dm_sig_1373.png', 'draw', '2026-09-23 17:53:01', '10.0.10.173', NULL),
(16080, 9888, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-25 08:36:04', '10.0.5.115', NULL),
(16081, 9889, 'requester', 'intake', 1, 1418, 'ticket-private/dm_sig_1418.png', 'draw', '2026-09-07 10:40:11', '10.0.12.163', NULL),
(16082, 9889, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-09 10:08:39', '10.0.18.37', NULL),
(16083, 9889, 'requester', 'return', 1, 1418, 'ticket-private/dm_sig_1418.png', 'draw', '2026-09-09 12:44:10', '10.0.5.11', NULL),
(16084, 9889, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-10 08:43:19', '10.0.10.81', NULL),
(16085, 9890, 'requester', 'intake', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-09-22 09:50:23', '10.0.16.36', NULL),
(16086, 9890, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-22 13:33:23', '10.0.5.138', NULL),
(16087, 9890, 'requester', 'return', 1, 1382, 'ticket-private/dm_sig_1382.png', 'draw', '2026-09-22 16:20:42', '10.0.19.42', NULL),
(16088, 9890, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-24 11:01:18', '10.0.2.35', NULL),
(16089, 9891, 'requester', 'intake', 1, 1402, 'ticket-private/dm_sig_1402.png', 'draw', '2026-10-01 10:12:47', '10.0.16.132', NULL),
(16090, 9891, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-10-05 09:26:09', '10.0.8.85', NULL),
(16091, 9891, 'requester', 'return', 1, 1402, 'ticket-private/dm_sig_1402.png', 'draw', '2026-10-06 03:11:47', '10.0.12.203', NULL),
(16092, 9891, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-10-06 09:04:41', '10.0.13.156', NULL),
(16093, 9892, 'requester', 'intake', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-09-29 11:25:02', '10.0.4.246', NULL),
(16094, 9892, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-10-02 09:33:05', '10.0.12.51', NULL),
(16095, 9892, 'requester', 'return', 1, 1426, 'ticket-private/dm_sig_1426.png', 'draw', '2026-10-03 06:02:42', '10.0.20.216', NULL),
(16096, 9892, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-10-05 08:40:00', '10.0.13.85', NULL),
(16097, 9893, 'requester', 'intake', 1, 1385, 'ticket-private/dm_sig_1385.png', 'draw', '2026-09-24 10:44:49', '10.0.20.128', NULL),
(16098, 9893, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-28 09:03:03', '10.0.1.125', NULL),
(16099, 9893, 'requester', 'return', 1, 1385, 'ticket-private/dm_sig_1385.png', 'draw', '2026-09-28 14:33:39', '10.0.20.250', NULL),
(16100, 9893, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-29 09:16:15', '10.0.15.97', NULL),
(16101, 9894, 'requester', 'intake', 1, 1428, 'ticket-private/dm_sig_1428.png', 'draw', '2026-09-15 11:40:34', '10.0.3.202', NULL),
(16102, 9894, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-16 09:14:57', '10.0.14.237', NULL),
(16103, 9894, 'requester', 'return', 1, 1428, 'ticket-private/dm_sig_1428.png', 'draw', '2026-09-16 22:05:51', '10.0.20.62', NULL),
(16104, 9894, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-18 08:59:21', '10.0.6.4', 'ตรวจรับเรียบร้อย'),
(16105, 9895, 'requester', 'intake', 1, 1385, 'ticket-private/dm_sig_1385.png', 'draw', '2026-09-28 11:29:15', '10.0.11.55', NULL),
(16106, 9895, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-09-28 13:28:26', '10.0.4.218', NULL),
(16107, 9895, 'requester', 'return', 1, 1385, 'ticket-private/dm_sig_1385.png', 'draw', '2026-09-29 01:16:14', '10.0.15.206', NULL),
(16108, 9895, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-29 15:34:01', '10.0.1.156', NULL),
(16109, 9896, 'requester', 'intake', 1, 1420, 'ticket-private/dm_sig_1420.png', 'draw', '2026-09-08 10:58:42', '10.0.18.122', NULL),
(16110, 9896, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-10 09:32:07', '10.0.2.144', NULL),
(16111, 9896, 'requester', 'return', 1, 1420, 'ticket-private/dm_sig_1420.png', 'draw', '2026-09-10 14:38:10', '10.0.19.193', NULL),
(16112, 9896, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-09-11 15:15:53', '10.0.3.75', NULL),
(16113, 9897, 'requester', 'intake', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-09-08 11:23:04', '10.0.12.32', NULL),
(16114, 9897, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-09-09 10:49:31', '10.0.7.33', NULL),
(16115, 9897, 'requester', 'return', 1, 1399, 'ticket-private/dm_sig_1399.png', 'draw', '2026-09-10 01:38:29', '10.0.15.193', NULL),
(16116, 9897, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-09-10 10:05:58', '10.0.20.121', 'ตรวจรับเรียบร้อย'),
(16117, 9898, 'requester', 'intake', 1, 1389, 'ticket-private/dm_sig_1389.png', 'draw', '2026-10-14 09:50:31', '10.0.20.125', NULL),
(16118, 9898, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-10-15 14:45:40', '10.0.15.98', NULL),
(16119, 9898, 'requester', 'return', 1, 1389, 'ticket-private/dm_sig_1389.png', 'draw', '2026-10-15 17:01:49', '10.0.17.113', NULL),
(16120, 9898, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-10-19 08:40:00', '10.0.9.208', NULL),
(16121, 9899, 'requester', 'intake', 1, 1432, 'ticket-private/dm_sig_1432.png', 'draw', '2026-10-09 10:18:12', '10.0.8.96', NULL),
(16122, 9899, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-10-12 09:10:06', '10.0.9.24', NULL),
(16123, 9899, 'requester', 'return', 1, 1432, 'ticket-private/dm_sig_1432.png', 'draw', '2026-10-12 16:59:05', '10.0.15.67', NULL),
(16124, 9899, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-10-13 13:37:47', '10.0.7.193', NULL),
(16125, 9900, 'requester', 'intake', 1, 1366, NULL, 'skip', '2026-10-07 09:47:38', '10.0.19.191', 'ข้ามขั้นผู้แจ้ง: ติดต่อผู้แจ้งไม่ได้ ครูรับทราบรายการแทน'),
(16126, 9900, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-10-09 13:40:36', '10.0.16.85', NULL),
(16127, 9900, 'requester', 'return', 1, 1405, 'ticket-private/dm_sig_1405.png', 'draw', '2026-10-09 21:23:52', '10.0.10.243', NULL),
(16128, 9900, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-10-12 08:40:00', '10.0.2.212', 'ตรวจรับเรียบร้อย'),
(16129, 9901, 'requester', 'intake', 1, 1423, 'ticket-private/dm_sig_1423.png', 'draw', '2026-10-05 16:00:05', '10.0.19.224', NULL),
(16130, 9901, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-10-07 09:42:23', '10.0.14.190', NULL),
(16131, 9901, 'requester', 'return', 1, 1423, 'ticket-private/dm_sig_1423.png', 'draw', '2026-10-07 21:00:45', '10.0.9.41', NULL),
(16132, 9901, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-10-09 09:11:38', '10.0.12.89', NULL),
(16133, 9902, 'requester', 'intake', 1, 1366, NULL, 'skip', '2026-10-28 10:06:39', '10.0.5.133', 'ข้ามขั้นผู้แจ้ง: ติดต่อผู้แจ้งไม่ได้ ครูรับทราบรายการแทน'),
(16134, 9902, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-10-29 09:57:32', '10.0.1.19', NULL),
(16135, 9902, 'requester', 'return', 1, 1406, 'ticket-private/dm_sig_1406.png', 'draw', '2026-10-29 21:13:22', '10.0.6.70', NULL),
(16136, 9902, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-11-02 08:40:00', '10.0.19.44', 'ตรวจรับเรียบร้อย'),
(16137, 9903, 'requester', 'intake', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-10-08 12:14:50', '10.0.14.15', NULL),
(16138, 9903, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-10-08 12:44:40', '10.0.16.113', NULL),
(16139, 9903, 'requester', 'return', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-10-09 00:31:20', '10.0.16.246', NULL),
(16140, 9903, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-10-12 08:40:00', '10.0.17.196', 'ตรวจรับเรียบร้อย'),
(16141, 9904, 'requester', 'intake', 1, 1373, 'ticket-private/dm_sig_1373.png', 'draw', '2026-10-23 10:03:26', '10.0.18.210', NULL),
(16142, 9904, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-10-26 09:24:57', '10.0.1.94', NULL),
(16143, 9904, 'requester', 'return', 1, 1373, 'ticket-private/dm_sig_1373.png', 'draw', '2026-10-26 22:09:18', '10.0.1.126', NULL),
(16144, 9904, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-10-27 11:54:37', '10.0.9.242', 'ตรวจรับเรียบร้อย'),
(16145, 9905, 'requester', 'intake', 1, 1422, 'ticket-private/dm_sig_1422.png', 'draw', '2026-10-29 17:25:46', '10.0.6.63', NULL),
(16146, 9905, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-11-02 09:04:50', '10.0.1.213', NULL),
(16147, 9905, 'requester', 'return', 1, 1422, 'ticket-private/dm_sig_1422.png', 'draw', '2026-11-02 11:01:30', '10.0.9.49', NULL),
(16148, 9905, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-11-03 10:22:48', '10.0.16.5', 'ตรวจรับเรียบร้อย'),
(16149, 9906, 'requester', 'intake', 1, 1386, 'ticket-private/dm_sig_1386.png', 'draw', '2026-10-12 09:55:52', '10.0.11.99', NULL),
(16150, 9906, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-10-15 09:14:04', '10.0.15.146', NULL),
(16151, 9906, 'requester', 'return', 1, 1386, 'ticket-private/dm_sig_1386.png', 'draw', '2026-10-15 10:11:09', '10.0.14.90', NULL),
(16152, 9906, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-10-16 10:04:22', '10.0.1.196', NULL),
(16153, 9908, 'requester', 'intake', 1, 1423, 'ticket-private/dm_sig_1423.png', 'draw', '2026-10-27 10:51:38', '10.0.17.247', NULL),
(16154, 9908, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-10-29 09:21:16', '10.0.20.119', NULL),
(16155, 9908, 'requester', 'return', 1, 1423, 'ticket-private/dm_sig_1423.png', 'draw', '2026-10-29 12:42:49', '10.0.13.185', NULL),
(16156, 9908, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-11-02 08:40:00', '10.0.9.30', 'ตรวจรับเรียบร้อย'),
(16157, 9909, 'requester', 'intake', 1, 1396, 'ticket-private/dm_sig_1396.png', 'draw', '2026-10-20 11:20:55', '10.0.5.50', NULL),
(16158, 9909, 'staff', 'return', 1, 1368, 'ticket-private/dm_sig_1368.png', 'draw', '2026-10-21 10:09:29', '10.0.11.140', NULL),
(16159, 9909, 'requester', 'return', 1, 1396, 'ticket-private/dm_sig_1396.png', 'draw', '2026-10-22 01:08:19', '10.0.9.126', NULL),
(16160, 9909, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-10-23 09:30:39', '10.0.16.11', NULL),
(16161, 9910, 'requester', 'intake', 1, 1420, 'ticket-private/dm_sig_1420.png', 'draw', '2026-10-27 09:51:00', '10.0.2.105', NULL),
(16162, 9910, 'staff', 'return', 1, 1369, 'ticket-private/dm_sig_1369.png', 'draw', '2026-10-30 10:08:06', '10.0.20.196', NULL),
(16163, 9910, 'requester', 'return', 1, 1420, 'ticket-private/dm_sig_1420.png', 'draw', '2026-10-31 07:44:48', '10.0.3.157', NULL),
(16164, 9910, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-11-02 08:40:00', '10.0.14.162', NULL),
(16165, 9911, 'requester', 'intake', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-11-12 10:03:48', '10.0.11.54', NULL),
(16166, 9914, 'requester', 'intake', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-11-03 13:58:21', '10.0.12.85', NULL),
(16167, 9914, 'staff', 'return', 1, 1371, 'ticket-private/dm_sig_1371.png', 'draw', '2026-11-04 09:55:28', '10.0.18.247', NULL),
(16168, 9914, 'requester', 'return', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-11-04 11:52:33', '10.0.11.94', NULL),
(16169, 9917, 'requester', 'intake', 1, 1375, 'ticket-private/dm_sig_1375.png', 'draw', '2026-11-13 09:02:49', '10.0.6.125', NULL),
(16170, 9919, 'requester', 'intake', 1, 1378, 'ticket-private/dm_sig_1378.png', 'draw', '2026-11-02 11:26:00', '10.0.3.205', NULL),
(16171, 9919, 'staff', 'return', 1, 1370, 'ticket-private/dm_sig_1370.png', 'draw', '2026-11-02 15:42:44', '10.0.13.128', NULL),
(16172, 9919, 'requester', 'return', 1, 1378, 'ticket-private/dm_sig_1378.png', 'draw', '2026-11-02 22:43:49', '10.0.1.129', NULL),
(16173, 9919, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-11-04 08:58:23', '10.0.1.138', NULL),
(16174, 9923, 'requester', 'intake', 1, 1410, 'ticket-private/dm_sig_1410.png', 'draw', '2026-11-05 10:27:55', '10.0.15.121', NULL),
(16175, 9923, 'staff', 'return', 1, 1370, 'ticket-private/dm_sig_1370.png', 'draw', '2026-11-06 09:59:58', '10.0.18.17', NULL),
(16176, 9923, 'requester', 'return', 1, 1410, 'ticket-private/dm_sig_1410.png', 'draw', '2026-11-06 14:06:54', '10.0.6.70', NULL),
(16177, 9925, 'requester', 'intake', 1, 1367, NULL, 'skip', '2026-11-04 15:36:57', '10.0.18.2', 'ข้ามขั้นผู้แจ้ง: ติดต่อผู้แจ้งไม่ได้ ครูรับทราบรายการแทน'),
(16178, 9928, 'requester', 'intake', 1, 1386, 'ticket-private/dm_sig_1386.png', 'draw', '2026-11-13 10:47:07', '10.0.20.238', NULL),
(16179, 9929, 'requester', 'intake', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-11-06 10:54:00', '10.0.11.163', NULL),
(16180, 9929, 'staff', 'return', 1, 1370, 'ticket-private/dm_sig_1370.png', 'draw', '2026-11-06 12:03:50', '10.0.13.49', NULL),
(16181, 9929, 'requester', 'return', 1, 1408, 'ticket-private/dm_sig_1408.png', 'draw', '2026-11-07 06:14:24', '10.0.4.204', NULL),
(16182, 9929, 'admin', 'return', 1, 1367, 'ticket-private/dm_sig_1367.png', 'draw', '2026-11-09 08:40:00', '10.0.4.214', NULL),
(16183, 9930, 'requester', 'intake', 1, 1391, 'ticket-private/dm_sig_1391.png', 'draw', '2026-11-06 09:51:56', '10.0.2.137', NULL),
(16184, 9931, 'requester', 'intake', 1, 1403, 'ticket-private/dm_sig_1403.png', 'draw', '2026-11-06 09:23:31', '10.0.9.199', NULL),
(16185, 9931, 'staff', 'return', 1, 1371, 'ticket-private/dm_sig_1371.png', 'draw', '2026-11-09 09:13:20', '10.0.2.191', NULL),
(16186, 9931, 'requester', 'return', 1, 1403, 'ticket-private/dm_sig_1403.png', 'draw', '2026-11-10 07:31:22', '10.0.4.14', NULL),
(16187, 9931, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-11-10 09:32:07', '10.0.19.206', NULL),
(16188, 9933, 'requester', 'intake', 1, 1402, 'ticket-private/dm_sig_1402.png', 'draw', '2026-11-12 12:26:52', '10.0.8.91', NULL),
(16189, 9933, 'staff', 'return', 1, 1370, 'ticket-private/dm_sig_1370.png', 'draw', '2026-11-12 14:27:57', '10.0.4.102', NULL),
(16190, 9933, 'requester', 'return', 1, 1402, 'ticket-private/dm_sig_1402.png', 'draw', '2026-11-12 23:47:28', '10.0.15.179', NULL),
(16191, 9933, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-11-13 09:07:00', '10.0.18.33', NULL),
(16192, 9934, 'requester', 'intake', 1, 1417, 'ticket-private/dm_sig_1417.png', 'draw', '2026-11-14 12:30:11', '10.0.17.178', NULL),
(16193, 9934, 'staff', 'return', 1, 1370, 'ticket-private/dm_sig_1370.png', 'draw', '2026-11-14 13:14:41', '10.0.1.171', NULL),
(16194, 9934, 'requester', 'return', 1, 1417, 'ticket-private/dm_sig_1417.png', 'draw', '2026-11-14 13:17:02', '10.0.12.67', NULL),
(16195, 9934, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-11-14 13:19:23', '10.0.17.82', NULL),
(16196, 9935, 'requester', 'intake', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-11-14 12:17:47', '10.0.9.64', NULL),
(16197, 9935, 'staff', 'return', 1, 1371, 'ticket-private/dm_sig_1371.png', 'draw', '2026-11-14 13:12:39', '10.0.18.48', NULL),
(16198, 9935, 'requester', 'return', 1, 1388, 'ticket-private/dm_sig_1388.png', 'draw', '2026-11-14 13:31:01', '10.0.15.224', NULL),
(16199, 9935, 'admin', 'return', 1, 1366, 'ticket-private/dm_sig_1366.png', 'draw', '2026-11-14 13:49:23', '10.0.9.118', NULL),
(16200, 9936, 'requester', 'intake', 1, 1427, 'ticket-private/dm_sig_1427.png', 'draw', '2026-11-14 13:12:49', '10.0.20.96', NULL),
(16201, 9936, 'staff', 'return', 1, 1372, 'ticket-private/dm_sig_1372.png', 'draw', '2026-11-14 14:50:14', '10.0.8.46', NULL),
(16202, 9936, 'requester', 'return', 1, 1427, 'ticket-private/dm_sig_1427.png', 'draw', '2026-11-14 15:19:48', '10.0.12.187', NULL),
(16203, 9937, 'requester', 'intake', 1, 1367, NULL, 'skip', '2026-11-14 13:45:11', '10.0.17.140', 'ข้ามขั้นผู้แจ้ง: ติดต่อผู้แจ้งไม่ได้ ครูรับทราบรายการแทน'),
(16204, 9937, 'staff', 'return', 1, 1370, 'ticket-private/dm_sig_1370.png', 'draw', '2026-11-14 15:18:03', '10.0.18.232', NULL),
(16205, 9938, 'requester', 'intake', 1, 1379, 'ticket-private/dm_sig_1379.png', 'draw', '2026-11-14 10:27:22', '10.0.9.175', NULL),
(16206, 9938, 'staff', 'return', 1, 1371, 'ticket-private/dm_sig_1371.png', 'draw', '2026-11-14 13:28:20', '10.0.10.79', NULL),
(16207, 9939, 'requester', 'intake', 1, 1432, 'ticket-private/dm_sig_1432.png', 'draw', '2026-11-14 14:25:35', '10.0.8.211', NULL),
(16208, 9915, 'requester', 'intake', 1, 1395, 'ticket-private/sig_e54b1665c302803a8d810960771937db.png', 'draw', '2026-11-14 16:15:03', '127.0.0.1', NULL),
(16209, 9915, 'staff', 'return', 1, 1370, 'ticket-private/sig_065cfb4e884f3f0ab9771383a559ed1c.png', 'draw', '2026-11-14 16:16:34', '127.0.0.1', NULL),
(16210, 9915, 'requester', 'return', 1, 1395, 'ticket-private/sig_2fadf1addfaac4edb62ea8c6f66d4971.png', 'draw', '2026-11-14 16:17:05', '127.0.0.1', NULL),
(16211, 9915, 'admin', 'return', 1, 1366, 'ticket-private/sig_b3e07f6baad839ad465b8e2d1c85aefd.png', 'saved', '2026-11-14 16:22:42', '127.0.0.1', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_thanks`
--

CREATE TABLE `ticket_thanks` (
  `id` int UNSIGNED NOT NULL,
  `ticket_id` int NOT NULL,
  `from_user_id` int NOT NULL,
  `to_user_id` int NOT NULL,
  `kind` enum('money','snack','drink','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(8,2) DEFAULT NULL,
  `item_note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `message` varchar(300) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `slip_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','confirmed','not_received','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `staff_note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_thanks`
--

INSERT INTO `ticket_thanks` (`id`, `ticket_id`, `from_user_id`, `to_user_id`, `kind`, `amount`, `item_note`, `message`, `slip_path`, `status`, `staff_note`, `confirmed_at`, `created_at`) VALUES
(25, 9779, 1429, 1368, 'money', 50.00, '', 'ขอบคุณที่อธิบายวิธีใช้ให้ด้วย', NULL, 'not_received', 'ยังไม่ได้รับ เช็คบัญชีแล้วไม่พบยอด', '2026-05-13 02:26:58', '2026-05-12 14:27:24'),
(26, 9781, 1388, 1369, 'snack', 60.00, 'ขนมถุงใหญ่', 'บริการดีมาก', NULL, 'confirmed', '', '2026-05-28 22:12:55', '2026-05-28 08:30:15'),
(27, 9791, 1378, 1369, 'snack', 35.00, 'ขนมปังกับนมกล่อง', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'confirmed', '', '2026-05-09 11:50:59', '2026-05-08 21:16:33'),
(28, 9792, 1426, 1368, 'money', 30.00, '', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'confirmed', '', '2026-05-14 15:42:32', '2026-05-14 09:31:43'),
(29, 9813, 1381, 1368, 'money', 50.00, '', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'confirmed', '', '2026-06-25 13:31:11', '2026-06-25 00:09:16'),
(30, 9816, 1426, 1369, 'snack', 35.00, 'ขนมปังกับนมกล่อง', 'บริการดีมาก', NULL, 'confirmed', '', '2026-06-30 03:35:50', '2026-06-29 14:49:18'),
(31, 9820, 1418, 1368, 'money', 30.00, '', 'ซ่อมเรียบร้อย ใช้งานได้ดีแล้ว', NULL, 'confirmed', '', '2026-06-23 13:10:59', '2026-06-23 02:37:09'),
(32, 9821, 1407, 1369, 'money', 20.00, '', 'ขอบคุณที่อธิบายวิธีใช้ให้ด้วย', NULL, 'confirmed', '', '2026-06-20 10:55:25', '2026-06-20 08:03:47'),
(33, 9826, 1408, 1369, 'money', 30.00, '', 'ขอบคุณที่อธิบายวิธีใช้ให้ด้วย', NULL, 'confirmed', '', '2026-06-06 15:28:17', '2026-06-06 01:21:24'),
(34, 9837, 1403, 1368, 'other', NULL, 'ผลไม้ 1 ถุง', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'confirmed', '', '2026-07-09 20:37:15', '2026-07-09 02:11:21'),
(35, 9840, 1431, 1369, 'drink', 90.00, 'กาแฟเย็น 2 แก้ว', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'confirmed', '', '2026-07-09 06:04:50', '2026-07-08 16:19:47'),
(36, 9846, 1391, 1369, 'snack', 35.00, 'ขนมปังกับนมกล่อง', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'confirmed', '', '2026-07-31 08:51:45', '2026-07-31 03:06:14'),
(37, 9851, 1383, 1368, 'money', 20.00, '', 'บริการดีมาก', NULL, 'confirmed', '', '2026-07-30 16:52:25', '2026-07-29 22:28:04'),
(38, 9854, 1404, 1369, 'drink', 40.00, 'ชาไทย 1 แก้ว', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'not_received', 'ยังไม่ได้รับ เช็คบัญชีแล้วไม่พบยอด', '2026-09-01 13:38:18', '2026-09-01 08:24:32'),
(39, 9872, 1388, 1368, 'money', 20.00, '', 'บริการดีมาก', NULL, 'confirmed', '', '2026-09-02 08:04:25', '2026-09-02 04:24:32'),
(40, 9876, 1374, 1368, 'money', 100.00, '', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'confirmed', '', '2026-09-29 13:26:52', '2026-09-28 18:02:22'),
(41, 9881, 1392, 1369, 'money', 30.00, '', 'ขอบคุณที่มาซ่อมให้เร็วมาก', NULL, 'confirmed', '', '2026-09-08 00:04:12', '2026-09-07 15:25:57'),
(42, 9883, 1387, 1369, 'money', 100.00, '', 'บริการดีมาก', NULL, 'confirmed', '', '2026-10-01 16:44:10', '2026-10-01 11:54:53'),
(43, 9895, 1385, 1369, 'drink', 40.00, 'ชาไทย 1 แก้ว', 'บริการดีมาก', NULL, 'confirmed', '', '2026-09-29 06:05:59', '2026-09-29 01:59:32'),
(44, 9898, 1389, 1369, 'drink', 40.00, 'ชาไทย 1 แก้ว', 'ซ่อมเรียบร้อย ใช้งานได้ดีแล้ว', NULL, 'confirmed', '', '2026-10-15 20:46:20', '2026-10-15 18:51:17'),
(45, 9905, 1422, 1369, 'other', NULL, 'ผลไม้ 1 ถุง', 'ซ่อมเรียบร้อย ใช้งานได้ดีแล้ว', NULL, 'confirmed', '', '2026-11-02 20:29:55', '2026-11-02 11:14:32'),
(46, 9931, 1403, 1371, 'drink', 90.00, 'กาแฟเย็น 2 แก้ว', 'ขอบคุณที่อธิบายวิธีใช้ให้ด้วย', NULL, 'confirmed', '', '2026-11-10 14:12:02', '2026-11-10 08:31:28'),
(47, 9915, 1395, 1370, 'money', 50.00, '', 'ดูแลอย่างดี ขอบคุณมาก', 'ticket-private/slip_5ffbde87c59995ccdc4d9a5de3ef0993.jpg', 'confirmed', 'ขอบคุณมากครับ ยินดีให้บริการ', '2026-11-14 16:19:55', '2026-11-14 16:19:26');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `id_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'รหัสนักศึกษา / รหัสบุคลากร',
  `first_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_type` enum('student','teacher','staff') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'student' COMMENT 'สถานภาพบุคคล',
  `department_id` int DEFAULT NULL,
  `phone_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `role` enum('admin','staff','user') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user' COMMENT 'สิทธิ์ในระบบ: admin (ครู) > staff > user',
  `status` enum('active','disabled','pending','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'สถานะบัญชี: pending=รออนุมัติ, active=ใช้งาน, disabled=ระงับ, rejected=ปฏิเสธ',
  `approved_by` int DEFAULT NULL COMMENT 'admin ผู้พิจารณาอนุมัติ/ปฏิเสธ',
  `approved_at` datetime DEFAULT NULL COMMENT 'เวลาที่อนุมัติหรือปฏิเสธ',
  `reject_reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'เหตุผลที่ปฏิเสธการสมัคร',
  `score` int NOT NULL DEFAULT '100' COMMENT 'คะแนนสะสมของสตาฟฝ่ายซ่อม',
  `queue_order` int DEFAULT NULL COMMENT 'ลำดับคิวรับงาน (NULL = ไม่อยู่ในคิว)',
  `is_active_queue` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1 = เปิดรับงานในคิวปัจจุบัน',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notif_seen_log_id` int NOT NULL DEFAULT '0' COMMENT 'ticket_logs.id ล่าสุดที่ผู้ใช้เปิดดูในกระดิ่งแจ้งเตือนแล้ว',
  `avatar_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'รูปโปรไฟล์ (relative path ใน private storage)',
  `signature_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ลายเซ็นอิเล็กทรอนิกส์ที่อัปโหลดไว้ (private storage)',
  `promptpay_qr_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `id_number`, `first_name`, `last_name`, `user_type`, `department_id`, `phone_number`, `email`, `password`, `must_change_password`, `role`, `status`, `approved_by`, `approved_at`, `reject_reason`, `score`, `queue_order`, `is_active_queue`, `created_at`, `notif_seen_log_id`, `avatar_path`, `signature_path`, `promptpay_qr_path`) VALUES
(6, '123123', 'นานา', 'ลลน', 'student', 3, '0987456544', 'ertyrt@gmail.com', '$2y$12$VRfuoApPFvPJKlkVcBOVZued5HEV2SY2X0b5cBE/ITjTrH1szMd7e', 0, 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:19:11', 23252, NULL, NULL, NULL),
(7, '123123123', 'นานา', 'ลัลน์ลัลน์', 'student', 3, '0811111111', 'tytyty@gmail.com', '$2y$12$kW1i741lHXeQxRiTdb2Noe6qD5OkwQaIGt3Sfyj.zkD.jzrzzxv.a', 0, 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:20:09', 23252, NULL, NULL, NULL),
(8, '1234', 'พงศธร', 'ศรีสมัย', 'teacher', 5, '0811024898', 'pongsathorn.cat@gmail.com', '$2y$12$ZV/CWbhj7dS6YW96gPzud.MuQ1gWJAvOAxplBgWYA7YOYFiv5MZTm', 0, 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:29:06', 23252, NULL, NULL, NULL),
(9, 'staff_nalan', 'สุทธิรรักษ์', 'ภูธร', 'student', 1, '0812761358', 'phuthorn2006@gmail.com', '$2y$12$01YFyc2dy9xKImpGR.ObxOtA3EIsS5mqEG240II7oIWWv9.cpV5kK', 0, 'staff', 'active', NULL, NULL, NULL, 100, NULL, 1, '2026-11-12 16:04:00', 23252, NULL, NULL, NULL),
(10, 'admin_nalan', 'นานา', 'ลัลน์ลัลน์', 'teacher', 5, '0810028001', 'nalan@gmail.com', '$2y$12$iJawgqthlUSx92Pv3xk3VegMmHMyZeZz91HgTseShEFoewgWimMs.', 0, 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 16:08:15', 23252, NULL, NULL, NULL),
(11, '12345678', 'got', 'got', 'student', 5, '0123456789', '1922@gmail.com', '$2y$12$WxTNtOZ1S7/PfgokrQiDpOZkl92a.Mi96VxeVbd8WTCp/qsLGJWiC', 0, 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 19:22:48', 23252, NULL, NULL, NULL),
(12, 't_admin', 'T-t_admin', 'ทดสอบ', 'teacher', 2, '0800000000', 't_admin@example.invalid', '$2y$10$06i/hD7dwMny6qa2CfwtBOOkQH5qg3n8LEA1Ba.zopO/2E9sinSue', 0, 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 23:24:28', 23252, 'ticket-private/av_eafa219dfb36a28642ddef365477d5dd.webp', NULL, NULL),
(13, 't_staff', 'T-t_staff', 'ทดสอบ', 'staff', 2, '0800000000', 't_staff@example.invalid', '$2y$10$06i/hD7dwMny6qa2CfwtBOOkQH5qg3n8LEA1Ba.zopO/2E9sinSue', 0, 'staff', 'active', NULL, NULL, NULL, 65, 1, 1, '2026-11-12 23:24:28', 23252, NULL, NULL, NULL),
(14, 't_staff2', 'T-t_staff2', 'ทดสอบ', 'staff', 2, '0800000000', 't_staff2@example.invalid', '$2y$10$06i/hD7dwMny6qa2CfwtBOOkQH5qg3n8LEA1Ba.zopO/2E9sinSue', 0, 'staff', 'active', NULL, NULL, NULL, 70, 2, 1, '2026-11-12 23:24:28', 23252, NULL, NULL, NULL),
(15, 't_user', 'T-แก้ไขแล้ว', 'ทดสอบ', 'student', 2, '0800000000', 't_user@example.invalid', '$2y$10$06i/hD7dwMny6qa2CfwtBOOkQH5qg3n8LEA1Ba.zopO/2E9sinSue', 0, 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 23:24:28', 23252, NULL, NULL, NULL),
(1366, 'demo_admin1', 'สุภาวดี', 'ศรีวงศ์', 'teacher', 2, '0865888538', 'demo_admin1@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-04-01 09:00:00', 0, NULL, 'ticket-private/usig_a19232a1f4dd72cd974aae2fd281073d.png', NULL),
(1367, 'demo_admin2', 'กมลชนก', 'ศรีสุข', 'teacher', 5, '0875705597', 'demo_admin2@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-04-11 09:00:00', 0, NULL, NULL, NULL),
(1368, 'lb2569-1-01', 'ณัฐวุฒิ', 'แก้วมณี', 'student', 13, '0867433513', 'lb2569-1-01@demo.reps.invalid', '$2y$10$Z/r5F9DISFB74NZs65RQ9OZ/e3dilj1o/sEKdyNEikP5QHYdLXPJG', 0, 'staff', 'active', 1366, '2026-04-24 10:00:00', NULL, 70, NULL, 0, '2026-04-24 09:00:00', 0, NULL, NULL, 'ticket-private/dm_tipqr_1368.png'),
(1369, 'lb2569-1-02', 'ธนพล', 'ศรีสุข', 'student', 13, '0821407825', 'lb2569-1-02@demo.reps.invalid', '$2y$10$xQ9gN4SV2tbuZSzvb7kV9ONbkLPdmZbkeAFpHPWL.dnt3h.kkuk4W', 0, 'staff', 'active', 1366, '2026-04-25 10:00:00', NULL, 45, NULL, 0, '2026-04-25 09:00:00', 0, NULL, NULL, 'ticket-private/dm_tipqr_1369.png'),
(1370, 'lb2569-2-01', 'ภาณุพงศ์', 'บุญเรือง', 'student', 13, '0872570876', 'lb2569-2-01@demo.reps.invalid', '$2y$10$mmdbEMhrajG06LTdgIxQn.n46Djzjoxavc.w5JQu5z21w61A7CL/.', 0, 'staff', 'active', 1366, '2026-10-25 10:00:00', NULL, 70, 1, 1, '2026-10-25 09:00:00', 0, NULL, NULL, 'ticket-private/dm_tipqr_1370.png'),
(1371, 'lb2569-2-02', 'ศุภกร', 'ทองคำ', 'student', 13, '0837429566', 'lb2569-2-02@demo.reps.invalid', '$2y$10$dCuZcooRooXc93xBHHQ0deVLi9edk4EPcHwXgCKNzVCTT5SvLoZUm', 0, 'staff', 'active', 1366, '2026-10-26 10:00:00', NULL, 65, 2, 1, '2026-10-26 09:00:00', 0, NULL, NULL, 'ticket-private/dm_tipqr_1371.png'),
(1372, 'lb2569-2-03', 'กมลชนก', 'พรหมมา', 'student', 13, '0864874644', 'lb2569-2-03@demo.reps.invalid', '$2y$10$2t1M1mEAk8c65zf00gJ18O5f.PEp7rBuOH7h11vI0ak335uuvvW4a', 0, 'staff', 'active', 1366, '2026-10-27 10:00:00', NULL, 55, 3, 1, '2026-10-27 09:00:00', 0, NULL, NULL, 'ticket-private/dm_tipqr_1372.png'),
(1373, 'demo_u001', 'สุรเชษฐ์', 'วงศ์ใหญ่', 'student', 8, '0829213484', 'demo_u001@demo.reps.invalid', '$2y$10$kyz/6rqAMToMOME.EOQOEeh/3snttW/3TZalsbtNJ4HkS4BVYHx.K', 0, 'user', 'active', 1366, '2026-05-11 01:59:16', NULL, 100, NULL, 0, '2026-05-10 05:42:57', 0, NULL, NULL, NULL),
(1374, 'demo_u002', 'มนัสนันท์', 'ศรีวงศ์', 'student', 6, '0891906004', 'demo_u002@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-06-10 11:05:17', NULL, 100, NULL, 0, '2026-06-09 19:18:53', 0, NULL, NULL, NULL),
(1375, 'demo_u003', 'สิทธิชัย', 'ทองดี', 'student', 9, '0805155297', 'demo_u003@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-07-24 03:22:00', NULL, 100, NULL, 0, '2026-07-23 20:00:11', 0, NULL, NULL, NULL),
(1376, 'demo_u004', 'จักรพันธ์', 'ปัญญาดี', 'student', 2, '0802791959', 'demo_u004@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-06-01 17:02:38', NULL, 100, NULL, 0, '2026-06-01 15:35:00', 0, NULL, NULL, NULL),
(1377, 'demo_u005', 'วีรยุทธ', 'นาคสวัสดิ์', 'student', 13, '0880676587', 'demo_u005@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-08-05 19:32:45', NULL, 100, NULL, 0, '2026-08-05 02:47:56', 0, NULL, NULL, NULL),
(1378, 'demo_u006', 'สุรเชษฐ์', 'แก้วมณี', 'student', 11, '0829463998', 'demo_u006@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-10-18 15:50:23', NULL, 100, NULL, 0, '2026-10-18 09:06:17', 0, NULL, NULL, NULL),
(1379, 'demo_u007', 'ศุภกร', 'รุ่งเรือง', 'student', 8, '0826707608', 'demo_u007@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-06-03 02:03:31', NULL, 100, NULL, 0, '2026-06-02 07:01:57', 0, NULL, NULL, NULL),
(1380, 'demo_u008', 'จิราพร', 'ศรีวงศ์', 'student', 3, '0812860013', 'demo_u008@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-07-27 20:35:19', NULL, 100, NULL, 0, '2026-07-27 13:43:38', 0, NULL, NULL, NULL),
(1381, 'demo_u009', 'สุรเชษฐ์', 'มั่นคง', 'student', 5, '0816330223', 'demo_u009@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-10-09 23:41:56', NULL, 100, NULL, 0, '2026-10-09 13:52:59', 0, NULL, NULL, NULL),
(1382, 'demo_u010', 'เบญจมาศ', 'ปัญญาดี', 'student', 1, '0897267154', 'demo_u010@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-05-09 03:36:56', NULL, 100, NULL, 0, '2026-05-09 02:32:28', 0, NULL, NULL, NULL),
(1383, 'demo_u011', 'ชยพล', 'ทองคำ', 'student', 9, '0895397277', 'demo_u011@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-05-07 16:59:29', NULL, 100, NULL, 0, '2026-05-07 02:06:23', 0, NULL, NULL, NULL),
(1384, 'demo_u012', 'วรเมธ', 'วงศ์ใหญ่', 'student', 12, '0809555819', 'demo_u012@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-09-11 18:28:13', NULL, 100, NULL, 0, '2026-09-11 10:09:12', 0, NULL, NULL, NULL),
(1385, 'demo_u013', 'อนุชา', 'บุญเรือง', 'student', 13, '0895717622', 'demo_u013@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-08-17 17:42:18', NULL, 100, NULL, 0, '2026-08-17 10:51:16', 0, NULL, NULL, NULL),
(1386, 'demo_u014', 'ปวีณา', 'แก้วมณี', 'student', 10, '0809921779', 'demo_u014@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-07-14 19:03:34', NULL, 100, NULL, 0, '2026-07-14 13:29:16', 0, NULL, NULL, NULL),
(1387, 'demo_u015', 'รัตนาภรณ์', 'อินทร์แก้ว', 'student', 7, '0859936296', 'demo_u015@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-09-15 06:24:28', NULL, 100, NULL, 0, '2026-09-15 05:12:37', 0, NULL, NULL, NULL),
(1388, 'demo_u016', 'ณัฐวุฒิ', 'ศรีวงศ์', 'student', 10, '0886215896', 'demo_u016@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-09-13 03:01:21', NULL, 100, NULL, 0, '2026-09-12 07:25:06', 0, NULL, NULL, NULL),
(1389, 'demo_u017', 'จิราพร', 'วงศ์ใหญ่', 'student', 3, '0804729138', 'demo_u017@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-06-11 14:56:42', NULL, 100, NULL, 0, '2026-06-11 02:52:10', 0, NULL, NULL, NULL),
(1390, 'demo_u018', 'สุภาวดี', 'วงศ์ใหญ่', 'student', 1, '0824300041', 'demo_u018@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-10-21 15:17:56', NULL, 100, NULL, 0, '2026-10-21 03:05:25', 0, NULL, NULL, NULL),
(1391, 'demo_u019', 'วรเมธ', 'ทองคำ', 'student', 1, '0814593171', 'demo_u019@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-03 23:46:19', NULL, 100, NULL, 0, '2026-05-03 21:02:50', 0, NULL, NULL, NULL),
(1392, 'demo_u020', 'พีรพัฒน์', 'เพชรรัตน์', 'student', 5, '0858298937', 'demo_u020@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-08-14 08:17:11', NULL, 100, NULL, 0, '2026-08-13 19:51:52', 0, NULL, NULL, NULL),
(1393, 'demo_u021', 'พิมพ์ชนก', 'ทองคำ', 'student', 9, '0844688369', 'demo_u021@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-02 16:47:13', NULL, 100, NULL, 0, '2026-05-02 01:02:37', 0, NULL, NULL, NULL),
(1394, 'demo_u022', 'ธนพล', 'นาคสวัสดิ์', 'student', 9, '0801917330', 'demo_u022@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-08-05 14:45:06', NULL, 100, NULL, 0, '2026-08-04 22:10:16', 0, NULL, NULL, NULL),
(1395, 'demo_u023', 'กิตติพงษ์', 'จันทร์เพ็ญ', 'student', 8, '0870389312', 'demo_u023@demo.reps.invalid', '$2y$10$MwD6fWgzBS5Ry.ZoMl5WIOZiD4zKxlVi8p9YfaRE5hIKc5/e4vlsK', 0, 'user', 'active', 1367, '2026-09-16 16:00:55', NULL, 100, NULL, 0, '2026-09-15 18:11:49', 47230, NULL, NULL, NULL),
(1396, 'demo_u024', 'วรเมธ', 'แก้วมณี', 'student', 10, '0803622514', 'demo_u024@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-10-24 09:27:42', NULL, 100, NULL, 0, '2026-10-24 05:27:22', 0, NULL, NULL, NULL),
(1397, 'demo_u025', 'เบญจมาศ', 'พรหมมา', 'student', 11, '0867863252', 'demo_u025@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-19 14:08:39', NULL, 100, NULL, 0, '2026-05-19 11:53:59', 0, NULL, NULL, NULL),
(1398, 'demo_u026', 'ณรงค์ศักดิ์', 'แก้วมณี', 'student', 13, '0851696030', 'demo_u026@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-07-16 09:50:27', NULL, 100, NULL, 0, '2026-07-16 05:09:48', 0, NULL, NULL, NULL),
(1399, 'demo_u027', 'พีรพัฒน์', 'อินทร์แก้ว', 'student', 12, '0885909064', 'demo_u027@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-08-18 06:16:18', NULL, 100, NULL, 0, '2026-08-17 20:41:55', 0, NULL, NULL, NULL),
(1400, 'demo_u028', 'วรเมธ', 'ศรีสุข', 'student', 8, '0843322184', 'demo_u028@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-12 23:15:57', NULL, 100, NULL, 0, '2026-05-12 18:10:38', 0, NULL, NULL, NULL),
(1401, 'demo_u029', 'ณรงค์ศักดิ์', 'แก้วมณี', 'student', 7, '0814673949', 'demo_u029@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-01 19:08:28', NULL, 100, NULL, 0, '2026-05-01 12:12:35', 0, NULL, NULL, NULL),
(1402, 'demo_u030', 'กมลชนก', 'ทองดี', 'student', 6, '0886134218', 'demo_u030@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-05-29 22:18:29', NULL, 100, NULL, 0, '2026-05-29 08:51:01', 0, NULL, NULL, NULL),
(1403, 'demo_u031', 'ธิดารัตน์', 'มั่นคง', 'student', 10, '0845019406', 'demo_u031@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-23 15:44:15', NULL, 100, NULL, 0, '2026-05-23 12:01:53', 0, NULL, NULL, NULL),
(1404, 'demo_u032', 'ประภาพร', 'อินทร์แก้ว', 'student', 12, '0896768058', 'demo_u032@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-07-01 05:45:02', NULL, 100, NULL, 0, '2026-06-30 16:17:54', 0, NULL, NULL, NULL),
(1405, 'demo_u033', 'อรอุมา', 'จันทร์เพ็ญ', 'student', 12, '0803185707', 'demo_u033@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-05-12 09:46:40', NULL, 100, NULL, 0, '2026-05-12 03:33:55', 0, NULL, NULL, NULL),
(1406, 'demo_u034', 'ณัฐธิดา', 'อินทร์แก้ว', 'student', 7, '0858914253', 'demo_u034@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-06-16 18:26:14', NULL, 100, NULL, 0, '2026-06-16 15:56:47', 0, NULL, NULL, NULL),
(1407, 'demo_u035', 'สมศักดิ์', 'บุญเรือง', 'student', 13, '0871954626', 'demo_u035@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-06-17 14:41:55', NULL, 100, NULL, 0, '2026-06-16 20:52:04', 0, NULL, NULL, NULL),
(1408, 'demo_u036', 'ศิริพร', 'บุญเรือง', 'student', 7, '0809144594', 'demo_u036@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-09-12 10:06:38', NULL, 100, NULL, 0, '2026-09-12 03:06:44', 0, NULL, NULL, NULL),
(1409, 'demo_u037', 'สุรเชษฐ์', 'เพชรรัตน์', 'student', 6, '0875543364', 'demo_u037@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-06-21 21:23:10', NULL, 100, NULL, 0, '2026-06-21 10:00:29', 0, NULL, NULL, NULL),
(1410, 'demo_u038', 'สมศักดิ์', 'จันทร์เพ็ญ', 'student', 3, '0884937392', 'demo_u038@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-08-16 05:18:24', NULL, 100, NULL, 0, '2026-08-15 10:15:57', 0, NULL, NULL, NULL),
(1411, 'demo_u039', 'อรอุมา', 'แก้วมณี', 'student', 11, '0826669706', 'demo_u039@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-07-02 19:49:46', NULL, 100, NULL, 0, '2026-07-01 22:48:19', 0, NULL, NULL, NULL),
(1412, 'demo_u040', 'ธนพล', 'บุญเรือง', 'student', 6, '0867590581', 'demo_u040@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-09-18 08:32:52', NULL, 100, NULL, 0, '2026-09-18 05:33:02', 0, NULL, NULL, NULL),
(1413, 'demo_u041', 'กมลชนก', 'กาญจนา', 'student', 11, '0879222493', 'demo_u041@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-09-14 00:54:24', NULL, 100, NULL, 0, '2026-09-13 09:53:14', 0, NULL, NULL, NULL),
(1414, 'demo_u042', 'นภัสสร', 'ศรีวงศ์', 'student', 5, '0837782097', 'demo_u042@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-30 11:56:11', NULL, 100, NULL, 0, '2026-05-29 14:13:47', 0, NULL, NULL, NULL),
(1415, 'demo_u043', 'ภาณุพงศ์', 'ทองคำ', 'student', 1, '0803568524', 'demo_u043@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-20 03:34:19', NULL, 100, NULL, 0, '2026-05-19 05:31:03', 0, NULL, NULL, NULL),
(1416, 'demo_u044', 'รัตนาภรณ์', 'ชัยมงคล', 'student', 3, '0837603709', 'demo_u044@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-20 07:25:45', NULL, 100, NULL, 0, '2026-05-19 09:40:37', 0, NULL, NULL, NULL),
(1417, 'demo_u045', 'ประภาพร', 'ทองดี', 'student', 3, '0893147128', 'demo_u045@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-07-30 13:34:48', NULL, 100, NULL, 0, '2026-07-30 02:31:21', 0, NULL, NULL, NULL),
(1418, 'demo_u046', 'ศุภกร', 'มั่นคง', 'teacher', 7, '0872546475', 'demo_u046@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-09-05 03:45:12', NULL, 100, NULL, 0, '2026-09-04 11:36:24', 0, NULL, NULL, NULL),
(1419, 'demo_u047', 'สุภาวดี', 'ศรีวงศ์', 'teacher', 11, '0840775408', 'demo_u047@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-24 01:03:36', NULL, 100, NULL, 0, '2026-05-23 06:13:39', 0, NULL, NULL, NULL),
(1420, 'demo_u048', 'อภิสิทธิ์', 'อินทร์แก้ว', 'teacher', 10, '0823031227', 'demo_u048@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-08-25 21:11:24', NULL, 100, NULL, 0, '2026-08-25 11:17:18', 0, NULL, NULL, NULL),
(1421, 'demo_u049', 'รัตนาภรณ์', 'ศรีวงศ์', 'teacher', 3, '0823503090', 'demo_u049@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-07-27 17:55:23', NULL, 100, NULL, 0, '2026-07-27 01:20:45', 0, NULL, NULL, NULL),
(1422, 'demo_u050', 'นภัสสร', 'ทองดี', 'teacher', 2, '0865358292', 'demo_u050@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-07-22 20:45:04', NULL, 100, NULL, 0, '2026-07-21 22:28:41', 0, NULL, NULL, NULL),
(1423, 'demo_u051', 'ธิดารัตน์', 'สายทอง', 'teacher', 5, '0884831485', 'demo_u051@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-10-24 12:28:37', NULL, 100, NULL, 0, '2026-10-24 05:30:47', 0, NULL, NULL, NULL),
(1424, 'demo_u052', 'รัตนาภรณ์', 'จันทร์เพ็ญ', 'teacher', 2, '0807475788', 'demo_u052@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-10-20 17:14:12', NULL, 100, NULL, 0, '2026-10-19 17:48:51', 0, NULL, NULL, NULL),
(1425, 'demo_u053', 'ปวีณา', 'จันทร์เพ็ญ', 'teacher', 5, '0834064954', 'demo_u053@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-09-14 03:34:47', NULL, 100, NULL, 0, '2026-09-13 05:21:14', 0, NULL, NULL, NULL),
(1426, 'demo_u054', 'ธีรเดช', 'ทองคำ', 'teacher', 11, '0896125541', 'demo_u054@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-10-23 11:59:47', NULL, 100, NULL, 0, '2026-10-22 14:19:34', 0, NULL, NULL, NULL),
(1427, 'demo_u055', 'วีรยุทธ', 'บุญเรือง', 'teacher', 3, '0885637140', 'demo_u055@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-26 09:11:12', NULL, 100, NULL, 0, '2026-05-25 18:23:23', 0, NULL, NULL, NULL),
(1428, 'demo_u056', 'ชนิดา', 'วงศ์ใหญ่', 'teacher', 11, '0881120737', 'demo_u056@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-06-18 12:05:16', NULL, 100, NULL, 0, '2026-06-17 13:58:54', 0, NULL, NULL, NULL),
(1429, 'demo_u057', 'จิราพร', 'รุ่งเรือง', 'teacher', 2, '0852921210', 'demo_u057@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-10-06 18:03:50', NULL, 100, NULL, 0, '2026-10-06 01:59:58', 0, NULL, NULL, NULL),
(1430, 'demo_u058', 'รัตนาภรณ์', 'ทองคำ', 'teacher', 9, '0871892966', 'demo_u058@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-10-06 19:37:52', NULL, 100, NULL, 0, '2026-10-06 09:54:57', 0, NULL, NULL, NULL),
(1431, 'demo_u059', 'นภัสสร', 'วงศ์ใหญ่', 'teacher', 6, '0889316014', 'demo_u059@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1366, '2026-09-08 11:27:08', NULL, 100, NULL, 0, '2026-09-08 03:54:52', 0, NULL, NULL, NULL),
(1432, 'demo_u060', 'จักรพันธ์', 'ทองคำ', 'teacher', 7, '0809798999', 'demo_u060@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'active', 1367, '2026-05-08 20:55:58', NULL, 100, NULL, 0, '2026-05-08 13:00:06', 0, NULL, NULL, NULL),
(1433, 'demo_new01', 'ประภาพร', 'ทองคำ', 'student', 10, '0877708263', 'demo_new01@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'pending', NULL, NULL, NULL, 100, NULL, 0, '2026-11-11 22:01:06', 0, NULL, NULL, NULL),
(1434, 'demo_new02', 'พีรพัฒน์', 'ชัยมงคล', 'student', 5, '0836887274', 'demo_new02@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'pending', NULL, NULL, NULL, 100, NULL, 0, '2026-11-11 08:23:52', 0, NULL, NULL, NULL),
(1435, 'demo_new03', 'ชนิดา', 'สุขสวัสดิ์', 'student', 6, '0809595542', 'demo_new03@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'pending', NULL, NULL, NULL, 100, NULL, 0, '2026-11-14 04:01:54', 0, NULL, NULL, NULL),
(1436, 'demo_new04', 'ธนพล', 'พรหมมา', 'student', 10, '0816295574', 'demo_new04@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'pending', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 22:08:37', 0, NULL, NULL, NULL),
(1437, 'demo_new05', 'ณรงค์ศักดิ์', 'จันทร์เพ็ญ', 'student', 3, '0840667590', 'demo_new05@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'pending', NULL, NULL, NULL, 100, NULL, 0, '2026-11-14 03:01:00', 0, NULL, NULL, NULL),
(1438, 'demo_new06', 'ธนพล', 'ศรีวงศ์', 'teacher', 13, '0856769172', 'demo_new06@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'pending', NULL, NULL, NULL, 100, NULL, 0, '2026-11-13 17:30:22', 0, NULL, NULL, NULL),
(1439, 'demo_rej1', 'เบญจมาศ', 'กาญจนา', 'student', 6, '0833326244', 'demo_rej1@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'rejected', 1367, '2026-10-06 13:01:50', 'ข้อมูลไม่ตรงกับทะเบียนนักศึกษา', 100, NULL, 0, '2026-10-05 15:49:23', 0, NULL, NULL, NULL),
(1440, 'demo_rej2', 'อนุชา', 'ปัญญาดี', 'student', 3, '0848189070', 'demo_rej2@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'rejected', 1367, '2026-10-24 08:03:09', 'สมัครซ้ำกับบัญชีเดิม', 100, NULL, 0, '2026-10-23 15:49:23', 0, NULL, NULL, NULL),
(1441, 'demo_off1', 'ชยพล', 'บุญเรือง', 'student', 8, '0840100691', 'demo_off1@demo.reps.invalid', '$2y$10$P.4g.fu4Wh04Be/EmSBuIeI9YGzFCNYAAO0ECyULh.SxAAx4ssffG', 0, 'user', 'disabled', 1366, '2026-07-01 04:26:39', NULL, 100, NULL, 0, '2026-06-30 09:00:00', 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_audit_logs`
--

CREATE TABLE `user_audit_logs` (
  `id` int NOT NULL,
  `target_user_id` int DEFAULT NULL COMMENT 'บัญชีเป้าหมาย (NULL = ถูกลบแล้ว ดูรายละเอียดใน action_detail)',
  `changed_by` int DEFAULT NULL COMMENT 'admin ผู้ทำรายการ (NULL = ระบบ หรือบัญชีถูกลบแล้ว)',
  `action_detail` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_audit_logs`
--

INSERT INTO `user_audit_logs` (`id`, `target_user_id`, `changed_by`, `action_detail`, `created_at`) VALUES
(1219, 1368, 1366, 'เปลี่ยน role: user → staff (ทีมภาคเรียน 1/2569)', '2026-04-24 11:00:00'),
(1220, 1369, 1366, 'เปลี่ยน role: user → staff (ทีมภาคเรียน 1/2569)', '2026-04-25 11:00:00'),
(1221, 1370, 1366, 'เปลี่ยน role: user → staff (ทีมภาคเรียน 2/2569)', '2026-10-25 11:00:00'),
(1222, 1371, 1366, 'เปลี่ยน role: user → staff (ทีมภาคเรียน 2/2569)', '2026-10-26 11:00:00'),
(1223, 1372, 1366, 'เปลี่ยน role: user → staff (ทีมภาคเรียน 2/2569)', '2026-10-27 11:00:00'),
(1224, 1373, 1366, 'อนุมัติการสมัคร #1373 demo_u001', '2026-05-10 06:42:57'),
(1225, 1374, 1366, 'อนุมัติการสมัคร #1374 demo_u002', '2026-06-09 20:18:53'),
(1226, 1375, 1367, 'อนุมัติการสมัคร #1375 demo_u003', '2026-07-23 21:00:11'),
(1227, 1376, 1367, 'อนุมัติการสมัคร #1376 demo_u004', '2026-06-01 16:35:00'),
(1228, 1377, 1367, 'อนุมัติการสมัคร #1377 demo_u005', '2026-08-05 03:47:56'),
(1229, 1378, 1367, 'อนุมัติการสมัคร #1378 demo_u006', '2026-10-18 10:06:17'),
(1230, 1379, 1366, 'อนุมัติการสมัคร #1379 demo_u007', '2026-06-02 08:01:57'),
(1231, 1380, 1366, 'อนุมัติการสมัคร #1380 demo_u008', '2026-07-27 14:43:38'),
(1232, 1381, 1367, 'อนุมัติการสมัคร #1381 demo_u009', '2026-10-09 14:52:59'),
(1233, 1382, 1366, 'อนุมัติการสมัคร #1382 demo_u010', '2026-05-09 03:32:28'),
(1234, 1383, 1366, 'อนุมัติการสมัคร #1383 demo_u011', '2026-05-07 03:06:23'),
(1235, 1384, 1366, 'อนุมัติการสมัคร #1384 demo_u012', '2026-09-11 11:09:12'),
(1236, 1385, 1366, 'อนุมัติการสมัคร #1385 demo_u013', '2026-08-17 11:51:16'),
(1237, 1386, 1366, 'อนุมัติการสมัคร #1386 demo_u014', '2026-07-14 14:29:16'),
(1238, 1387, 1367, 'อนุมัติการสมัคร #1387 demo_u015', '2026-09-15 06:12:37'),
(1239, 1388, 1366, 'อนุมัติการสมัคร #1388 demo_u016', '2026-09-12 08:25:06'),
(1240, 1389, 1367, 'อนุมัติการสมัคร #1389 demo_u017', '2026-06-11 03:52:10'),
(1241, 1390, 1367, 'อนุมัติการสมัคร #1390 demo_u018', '2026-10-21 04:05:25'),
(1242, 1391, 1367, 'อนุมัติการสมัคร #1391 demo_u019', '2026-05-03 22:02:50'),
(1243, 1392, 1367, 'อนุมัติการสมัคร #1392 demo_u020', '2026-08-13 20:51:52'),
(1244, 1393, 1367, 'อนุมัติการสมัคร #1393 demo_u021', '2026-05-02 02:02:37'),
(1245, 1394, 1367, 'อนุมัติการสมัคร #1394 demo_u022', '2026-08-04 23:10:16'),
(1246, 1395, 1367, 'อนุมัติการสมัคร #1395 demo_u023', '2026-09-15 19:11:49'),
(1247, 1396, 1367, 'อนุมัติการสมัคร #1396 demo_u024', '2026-10-24 06:27:22'),
(1248, 1397, 1367, 'อนุมัติการสมัคร #1397 demo_u025', '2026-05-19 12:53:59'),
(1249, 1398, 1366, 'อนุมัติการสมัคร #1398 demo_u026', '2026-07-16 06:09:48'),
(1250, 1399, 1366, 'อนุมัติการสมัคร #1399 demo_u027', '2026-08-17 21:41:55'),
(1251, 1400, 1367, 'อนุมัติการสมัคร #1400 demo_u028', '2026-05-12 19:10:38'),
(1252, 1401, 1367, 'อนุมัติการสมัคร #1401 demo_u029', '2026-05-01 13:12:35'),
(1253, 1402, 1366, 'อนุมัติการสมัคร #1402 demo_u030', '2026-05-29 09:51:01'),
(1254, 1403, 1367, 'อนุมัติการสมัคร #1403 demo_u031', '2026-05-23 13:01:53'),
(1255, 1404, 1367, 'อนุมัติการสมัคร #1404 demo_u032', '2026-06-30 17:17:54'),
(1256, 1405, 1366, 'อนุมัติการสมัคร #1405 demo_u033', '2026-05-12 04:33:55'),
(1257, 1406, 1367, 'อนุมัติการสมัคร #1406 demo_u034', '2026-06-16 16:56:47'),
(1258, 1407, 1367, 'อนุมัติการสมัคร #1407 demo_u035', '2026-06-16 21:52:04'),
(1259, 1408, 1367, 'อนุมัติการสมัคร #1408 demo_u036', '2026-09-12 04:06:44'),
(1260, 1409, 1366, 'อนุมัติการสมัคร #1409 demo_u037', '2026-06-21 11:00:29'),
(1261, 1410, 1366, 'อนุมัติการสมัคร #1410 demo_u038', '2026-08-15 11:15:57'),
(1262, 1411, 1367, 'อนุมัติการสมัคร #1411 demo_u039', '2026-07-01 23:48:19'),
(1263, 1412, 1366, 'อนุมัติการสมัคร #1412 demo_u040', '2026-09-18 06:33:02'),
(1264, 1413, 1366, 'อนุมัติการสมัคร #1413 demo_u041', '2026-09-13 10:53:14'),
(1265, 1414, 1367, 'อนุมัติการสมัคร #1414 demo_u042', '2026-05-29 15:13:47'),
(1266, 1415, 1367, 'อนุมัติการสมัคร #1415 demo_u043', '2026-05-19 06:31:03'),
(1267, 1416, 1367, 'อนุมัติการสมัคร #1416 demo_u044', '2026-05-19 10:40:37'),
(1268, 1417, 1367, 'อนุมัติการสมัคร #1417 demo_u045', '2026-07-30 03:31:21'),
(1269, 1418, 1367, 'อนุมัติการสมัคร #1418 demo_u046', '2026-09-04 12:36:24'),
(1270, 1419, 1367, 'อนุมัติการสมัคร #1419 demo_u047', '2026-05-23 07:13:39'),
(1271, 1420, 1367, 'อนุมัติการสมัคร #1420 demo_u048', '2026-08-25 12:17:18'),
(1272, 1421, 1366, 'อนุมัติการสมัคร #1421 demo_u049', '2026-07-27 02:20:45'),
(1273, 1422, 1366, 'อนุมัติการสมัคร #1422 demo_u050', '2026-07-21 23:28:41'),
(1274, 1423, 1366, 'อนุมัติการสมัคร #1423 demo_u051', '2026-10-24 06:30:47'),
(1275, 1424, 1366, 'อนุมัติการสมัคร #1424 demo_u052', '2026-10-19 18:48:51'),
(1276, 1425, 1367, 'อนุมัติการสมัคร #1425 demo_u053', '2026-09-13 06:21:14'),
(1277, 1426, 1367, 'อนุมัติการสมัคร #1426 demo_u054', '2026-10-22 15:19:34'),
(1278, 1427, 1367, 'อนุมัติการสมัคร #1427 demo_u055', '2026-05-25 19:23:23'),
(1279, 1428, 1367, 'อนุมัติการสมัคร #1428 demo_u056', '2026-06-17 14:58:54'),
(1280, 1429, 1366, 'อนุมัติการสมัคร #1429 demo_u057', '2026-10-06 02:59:58'),
(1281, 1430, 1367, 'อนุมัติการสมัคร #1430 demo_u058', '2026-10-06 10:54:57'),
(1282, 1431, 1366, 'อนุมัติการสมัคร #1431 demo_u059', '2026-09-08 04:54:52'),
(1283, 1432, 1367, 'อนุมัติการสมัคร #1432 demo_u060', '2026-05-08 14:00:06'),
(1284, 1439, 1367, 'ปฏิเสธการสมัคร เหตุผล: ข้อมูลไม่ตรงกับทะเบียนนักศึกษา', '2026-11-10 15:49:23'),
(1285, 1440, 1367, 'ปฏิเสธการสมัคร เหตุผล: สมัครซ้ำกับบัญชีเดิม', '2026-11-10 15:49:23'),
(1286, 1441, 1366, 'เปลี่ยนสถานะบัญชี: active → disabled', '2026-11-17 09:00:00'),
(1287, 1373, 12, 'รีเซ็ตรหัสผ่าน (รหัสชั่วคราว ต้องเปลี่ยนเมื่อเข้าสู่ระบบ) บัญชี demo_u001', '2026-11-14 16:07:16'),
(1288, 1370, 1366, 'รีเซ็ตรหัสผ่าน (รหัสชั่วคราว ต้องเปลี่ยนเมื่อเข้าสู่ระบบ) บัญชี lb2569-2-01', '2026-11-14 16:12:34'),
(1289, 1370, 1370, 'ตั้งรหัสผ่านใหม่หลังได้รับรหัสชั่วคราว', '2026-11-14 16:12:52'),
(1290, 1395, 1366, 'รีเซ็ตรหัสผ่าน (รหัสชั่วคราว ต้องเปลี่ยนเมื่อเข้าสู่ระบบ) บัญชี demo_u023', '2026-11-14 16:14:08'),
(1291, 1395, 1395, 'ตั้งรหัสผ่านใหม่หลังได้รับรหัสชั่วคราว', '2026-11-14 16:14:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_terms`
--
ALTER TABLE `academic_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_term` (`year_be`,`term_no`),
  ADD KEY `idx_term_dates` (`start_date`,`end_date`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_categories_name` (`name`);

--
-- Indexes for table `checklist_presets`
--
ALTER TABLE `checklist_presets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_presets_category` (`category_id`,`is_active`,`sort_order`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`d_id`),
  ADD UNIQUE KEY `uk_department_name` (`name`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_ticket` (`ticket_id`),
  ADD KEY `idx_email_status_created` (`status`,`created_at`),
  ADD KEY `idx_email_logs_ticket_key` (`ticket_id`,`template_key`,`created_at`);

--
-- Indexes for table `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_email_template_key` (`template_key`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_item_code` (`item_code`);

--
-- Indexes for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inv_move_item_created` (`item_id`,`created_at`),
  ADD KEY `idx_inv_move_ticket` (`ticket_id`),
  ADD KEY `idx_inv_move_user` (`action_by`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`user_id`,`notif_key`),
  ADD KEY `idx_notification_reads_time` (`read_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prt_user` (`user_id`),
  ADD KEY `idx_prt_hash` (`token_hash`);

--
-- Indexes for table `score_logs`
--
ALTER TABLE `score_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_score_user` (`user_id`),
  ADD KEY `idx_score_ticket` (`ticket_id`);

--
-- Indexes for table `score_rules`
--
ALTER TABLE `score_rules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_blocks`
--
ALTER TABLE `site_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_blocks_page` (`page_key`,`status`,`sort_order`);

--
-- Indexes for table `site_media`
--
ALTER TABLE `site_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_media_created` (`created_at`);

--
-- Indexes for table `site_menus`
--
ALTER TABLE `site_menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_site_menu_item` (`area`,`item_key`),
  ADD KEY `idx_site_menu_area` (`area`,`sort_order`);

--
-- Indexes for table `site_pages`
--
ALTER TABLE `site_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_site_page_slug` (`slug`);

--
-- Indexes for table `site_revisions`
--
ALTER TABLE `site_revisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site_revisions_entity` (`entity_type`,`entity_id`,`created_at`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `term_staff`
--
ALTER TABLE `term_staff`
  ADD PRIMARY KEY (`term_id`,`user_id`),
  ADD KEY `idx_term_staff_user` (`user_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tickets_ticket_no` (`ticket_no`),
  ADD KEY `idx_tickets_requester` (`requester_id`),
  ADD KEY `idx_tickets_assigned` (`assigned_to`),
  ADD KEY `idx_tickets_status` (`status`),
  ADD KEY `idx_tickets_created` (`created_at`),
  ADD KEY `idx_tickets_accepted` (`accepted_at`),
  ADD KEY `fk_tickets_category` (`category_id`),
  ADD KEY `idx_tickets_queue` (`queue_assigned_to`,`status`),
  ADD KEY `idx_tickets_stage_since` (`status`,`stage_since`);

--
-- Indexes for table `ticket_assignees`
--
ALTER TABLE `ticket_assignees`
  ADD PRIMARY KEY (`ticket_id`,`user_id`),
  ADD KEY `idx_assignees_user` (`user_id`),
  ADD KEY `fk_assignees_added_by` (`added_by`);

--
-- Indexes for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attachments_ticket` (`ticket_id`);

--
-- Indexes for table `ticket_completion_photos`
--
ALTER TABLE `ticket_completion_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_completion_photos` (`ticket_id`,`round_no`,`sort_order`);

--
-- Indexes for table `ticket_item_checks`
--
ALTER TABLE `ticket_item_checks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_checks_ticket` (`ticket_id`,`sort_order`),
  ADD KEY `fk_item_checks_received_by` (`received_by`),
  ADD KEY `fk_item_checks_checked_by` (`checked_out_by`);

--
-- Indexes for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_ticket` (`ticket_id`),
  ADD KEY `fk_logs_user` (`action_by`);

--
-- Indexes for table `ticket_materials`
--
ALTER TABLE `ticket_materials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tm_ticket` (`ticket_id`),
  ADD KEY `idx_tm_item` (`item_id`),
  ADD KEY `fk_tm_user` (`withdrawn_by`);

--
-- Indexes for table `ticket_signoffs`
--
ALTER TABLE `ticket_signoffs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_signoff_step` (`ticket_id`,`stage`,`signer_role`,`round_no`),
  ADD KEY `idx_signoff_user` (`signer_id`),
  ADD KEY `idx_signoff_signed_at` (`signed_at`);

--
-- Indexes for table `ticket_thanks`
--
ALTER TABLE `ticket_thanks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_thanks_to` (`to_user_id`,`status`),
  ADD KEY `idx_thanks_ticket` (`ticket_id`),
  ADD KEY `fk_thanks_from` (`from_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_users_id_number` (`id_number`),
  ADD UNIQUE KEY `uk_users_email` (`email`),
  ADD KEY `idx_users_department` (`department_id`),
  ADD KEY `idx_users_role_status` (`role`,`status`),
  ADD KEY `idx_users_approved_by` (`approved_by`);

--
-- Indexes for table `user_audit_logs`
--
ALTER TABLE `user_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_target` (`target_user_id`),
  ADD KEY `fk_audit_actor` (`changed_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_terms`
--
ALTER TABLE `academic_terms`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `checklist_presets`
--
ALTER TABLE `checklist_presets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `d_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8574;

--
-- AUTO_INCREMENT for table `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=152;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3885;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `score_logs`
--
ALTER TABLE `score_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10394;

--
-- AUTO_INCREMENT for table `score_rules`
--
ALTER TABLE `score_rules`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `site_blocks`
--
ALTER TABLE `site_blocks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_media`
--
ALTER TABLE `site_media`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_menus`
--
ALTER TABLE `site_menus`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_pages`
--
ALTER TABLE `site_pages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_revisions`
--
ALTER TABLE `site_revisions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9945;

--
-- AUTO_INCREMENT for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5902;

--
-- AUTO_INCREMENT for table `ticket_completion_photos`
--
ALTER TABLE `ticket_completion_photos`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `ticket_item_checks`
--
ALTER TABLE `ticket_item_checks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6741;

--
-- AUTO_INCREMENT for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47233;

--
-- AUTO_INCREMENT for table `ticket_materials`
--
ALTER TABLE `ticket_materials`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3563;

--
-- AUTO_INCREMENT for table `ticket_signoffs`
--
ALTER TABLE `ticket_signoffs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16212;

--
-- AUTO_INCREMENT for table `ticket_thanks`
--
ALTER TABLE `ticket_thanks`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1442;

--
-- AUTO_INCREMENT for table `user_audit_logs`
--
ALTER TABLE `user_audit_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1292;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `checklist_presets`
--
ALTER TABLE `checklist_presets`
  ADD CONSTRAINT `fk_presets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `fk_email_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  ADD CONSTRAINT `fk_inv_move_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_inv_move_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inv_move_user` FOREIGN KEY (`action_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD CONSTRAINT `fk_notification_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_prt_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `score_logs`
--
ALTER TABLE `score_logs`
  ADD CONSTRAINT `fk_score_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_score_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `term_staff`
--
ALTER TABLE `term_staff`
  ADD CONSTRAINT `fk_term_staff_term` FOREIGN KEY (`term_id`) REFERENCES `academic_terms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_term_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tickets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tickets_queue_staff` FOREIGN KEY (`queue_assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tickets_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `ticket_assignees`
--
ALTER TABLE `ticket_assignees`
  ADD CONSTRAINT `fk_assignees_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignees_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  ADD CONSTRAINT `fk_attachments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_completion_photos`
--
ALTER TABLE `ticket_completion_photos`
  ADD CONSTRAINT `fk_completion_photos_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_item_checks`
--
ALTER TABLE `ticket_item_checks`
  ADD CONSTRAINT `fk_item_checks_checked_by` FOREIGN KEY (`checked_out_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_item_checks_received_by` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_item_checks_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  ADD CONSTRAINT `fk_logs_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`action_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `ticket_materials`
--
ALTER TABLE `ticket_materials`
  ADD CONSTRAINT `fk_tm_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `fk_tm_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tm_user` FOREIGN KEY (`withdrawn_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `ticket_signoffs`
--
ALTER TABLE `ticket_signoffs`
  ADD CONSTRAINT `fk_signoff_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_signoff_user` FOREIGN KEY (`signer_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `ticket_thanks`
--
ALTER TABLE `ticket_thanks`
  ADD CONSTRAINT `fk_thanks_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_thanks_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_thanks_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`d_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_audit_logs`
--
ALTER TABLE `user_audit_logs`
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_audit_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
