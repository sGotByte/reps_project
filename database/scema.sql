-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql309.infinityfree.com
-- Generation Time: Nov 07, 2026 at 12:47 AM
-- Server version: 11.4.13-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_43255048_reps_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(5, 'เกี่ยวคอมพิวเตอร์'),
(8, 'ลงโปรแกรม'),
(9, 'ลงWindows'),
(10, 'ซ่อมเครื่องพิมพ์'),
(11, 'ปัญหาการเชื่อมต่อ WiFi');

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `d_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`d_id`, `name`) VALUES
(1, 'ช่างไฟฟ้ากำลัง'),
(2, 'ช่างยนต์'),
(3, 'ช่างกลโรงงาน'),
(4, 'ช่างเชื่อมโลหะ'),
(5, 'ช่างก่อสร้าง'),
(6, 'อิเล็กทรอนิกส์และหุ่นยนต์'),
(7, 'ช่างเทคนิคอุตสาหกรรม'),
(8, 'เทคโนโลยีสารสนเทศ'),
(9, 'เทคโนโลยีธุรกิจดิจิทัล'),
(10, 'การบัญชี'),
(11, 'ช่างเทคนิคคอมพิวเตอร์'),
(12, 'บุคลากร');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token_hash`, `created_at`, `expires_at`, `used_at`) VALUES
(1, 1, 'd30df6b90a9354035670fa31d866ca635fdc28c0278e2857609b012bbbbd7cdd', '2026-11-01 13:49:10', '2026-11-01 14:49:10', NULL),
(2, 10, '77854fc58cf1b6bdc9ef11851fb4bf69a39ff4d96e6d9d16e7e84205d0caa61b', '2026-11-01 13:52:16', '2026-11-01 14:52:16', '2026-11-01 13:55:42');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_no` varchar(50) NOT NULL,
  `requester_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `location_note` varchar(255) DEFAULT NULL,
  `symptom` text NOT NULL,
  `urgency` enum('urgent','normal','low') NOT NULL DEFAULT 'normal',
  `status` enum('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
  `assigned_to` int(11) DEFAULT NULL,
  `resolution_summary` text DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `tickets`
--

INSERT INTO `tickets` (`id`, `ticket_no`, `requester_id`, `category_id`, `location_note`, `symptom`, `urgency`, `status`, `assigned_to`, `resolution_summary`, `cancel_reason`, `created_at`, `accepted_at`, `completed_at`) VALUES
(1, 'LB-2026-DC7C69DF', 7, 5, 'ทดสอบระบบจริง', 'ทดสอบระบบจริง', 'urgent', 'pending', NULL, NULL, NULL, '2026-11-01 12:17:48', NULL, NULL),
(2, 'LB-2026-520AF6FA', 7, 10, 'ทดสอบระบบจริง', 'ทดสอบแจ้งเตือนline', 'urgent', 'pending', NULL, NULL, NULL, '2026-11-01 12:20:33', NULL, NULL),
(3, 'LB-2026-B5754EC4', 7, 11, 'ทดสอบระบบจริง', 'ระบบแจ้งเตือน', 'normal', 'pending', NULL, NULL, NULL, '2026-11-01 12:37:55', NULL, NULL),
(4, 'LB-2026-4D8FD319', 7, 9, 'ทดสอบแจ้งเตือน', 'ทดสอบแจ้งเตือน', 'low', 'pending', NULL, NULL, NULL, '2026-11-01 12:43:07', NULL, NULL),
(5, 'LB-2026-16816C12', 9, 5, 'อาคาร5ชั้น3 ห้องสมุด', 'อาการย่อย: ลูปหน้าwindows เข้าไม่ได้\n\nรายละเอียด: มาดูสิห้ามถาม', 'urgent', 'in_progress', 9, NULL, NULL, '2026-11-01 13:07:10', '2026-11-01 13:17:44', NULL),
(6, 'LB-2026-60E03DD5', 8, 9, 'asdf', 'fadsf', 'urgent', 'pending', NULL, NULL, NULL, '2026-11-01 13:42:00', NULL, NULL),
(7, 'LB-2026-96A0F8B1', 10, 5, 'ตึกอำนวยการ', 'ยกคอมช่วยครูหน่อย100 เครื่อง', 'urgent', 'in_progress', 1, NULL, NULL, '2026-11-01 13:58:40', '2026-11-02 17:28:29', NULL),
(8, 'LB-2026-31030F6F', 8, 11, 'ห้องสมุด', 'เน็ตเสีย', 'urgent', 'in_progress', 1, NULL, NULL, '2026-11-01 14:01:09', '2026-11-02 17:28:07', NULL),
(9, 'LB-2026-37C75BBA', 10, 11, 'ตึกคอม ห้อง224', 'เชื่อมเน็ตไม่ได้', 'urgent', 'completed', 1, 'เสร็จสิ้นภารกิจ', NULL, '2026-11-02 09:07:35', '2026-11-02 09:08:45', '2026-11-02 09:09:52'),
(10, 'LB-2026-45A1CD77', 11, 10, '224', 'อาการย่อย: ปวดหัว\n\nรายละเอียด: พิมพ์ตัวเล็กมากๆ', 'urgent', 'in_progress', 1, NULL, NULL, '2026-11-02 13:38:37', '2026-11-02 13:41:08', NULL),
(11, 'LB-2026-D3DF0FB3', 10, 8, '221', 'แอนตี้', 'low', 'pending', NULL, NULL, NULL, '2026-11-02 13:52:49', NULL, NULL),
(12, 'LB-2026-6EAB86C8', 8, 5, 'sdf', 'asdf', 'normal', 'completed', 1, 'jlkj', NULL, '2026-11-02 17:16:50', '2026-11-02 17:17:32', '2026-11-02 17:18:42');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_attachments`
--

CREATE TABLE `ticket_attachments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_attachments`
--

INSERT INTO `ticket_attachments` (`id`, `ticket_id`, `file_path`, `created_at`) VALUES
(1, 1, 'ticket-private/tk_30de424b6ca4f8d263177ee5bc42619e.png', '2026-11-01 12:17:48'),
(2, 5, 'ticket-private/tk_ef7514b0a53574f4a0fa64f5823ed031.jpg', '2026-11-01 13:07:10'),
(3, 7, 'ticket-private/tk_ac3c4111f07b77a2698d08f8e3d77a65.jpg', '2026-11-01 13:58:40'),
(4, 8, 'ticket-private/tk_f33069d6b5ab6fea31adbeda5cfadda4.jpg', '2026-11-01 14:01:09'),
(5, 9, 'ticket-private/tk_452af396f48c4b7ea0ac4d287ae5736b.png', '2026-11-02 09:07:35'),
(6, 10, 'ticket-private/tk_08665b9c5688fc93fc9c3e0575c026cd.jpg', '2026-11-02 13:38:37'),
(7, 11, 'ticket-private/tk_270e65f55b6932a770903277a149c826.jpg', '2026-11-02 13:52:49');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_logs`
--

CREATE TABLE `ticket_logs` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `action_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `source` varchar(10) NOT NULL DEFAULT 'web'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_logs`
--

INSERT INTO `ticket_logs` (`id`, `ticket_id`, `old_status`, `new_status`, `action_by`, `created_at`, `source`) VALUES
(1, 1, NULL, 'pending', 7, '2026-11-01 12:17:48', 'web'),
(2, 2, NULL, 'pending', 7, '2026-11-01 12:20:33', 'web'),
(3, 3, NULL, 'pending', 7, '2026-11-01 12:37:55', 'web'),
(4, 4, NULL, 'pending', 7, '2026-11-01 12:43:07', 'web'),
(5, 5, NULL, 'pending', 9, '2026-11-01 13:07:10', 'web'),
(6, 5, 'pending', 'in_progress', 9, '2026-11-01 13:17:44', 'web'),
(7, 6, NULL, 'pending', 8, '2026-11-01 13:42:00', 'web'),
(8, 7, NULL, 'pending', 10, '2026-11-01 13:58:40', 'web'),
(9, 8, NULL, 'pending', 8, '2026-11-01 14:01:09', 'web'),
(10, 9, NULL, 'pending', 10, '2026-11-02 09:07:35', 'web'),
(11, 9, 'pending', 'in_progress', 1, '2026-11-02 09:08:45', 'web'),
(12, 9, 'in_progress', 'completed', 1, '2026-11-02 09:09:52', 'web'),
(13, 10, NULL, 'pending', 11, '2026-11-02 13:38:37', 'web'),
(14, 10, 'pending', 'in_progress', 1, '2026-11-02 13:41:08', 'web'),
(15, 11, NULL, 'pending', 10, '2026-11-02 13:52:49', 'web'),
(16, 12, NULL, 'pending', 8, '2026-11-02 17:16:50', 'web'),
(17, 12, 'pending', 'in_progress', 1, '2026-11-02 17:17:32', 'web'),
(18, 12, 'in_progress', 'completed', 1, '2026-11-02 17:18:42', 'web'),
(19, 8, 'pending', 'in_progress', 1, '2026-11-02 17:28:07', 'web'),
(20, 7, 'pending', 'in_progress', 1, '2026-11-02 17:28:29', 'web');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `id_number` varchar(20) DEFAULT NULL,
  `user_type` enum('student','teacher','staff') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('requester','admin') NOT NULL DEFAULT 'requester',
  `status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `department_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `id_number`, `user_type`, `first_name`, `last_name`, `phone_number`, `email`, `password`, `role`, `status`, `created_at`, `department_id`) VALUES
(1, '68319100009', 'student', 'พงศธร', 'ศรีสมัย', '0811024898', 'pongsathorn.cat@gmail.com', '$2y$12$R/MGjC9ZDwBAJtHrfoIk1eFtrPDI0GnZ/iaxGvK/RlhfPwcCIh.Oy', 'admin', 'active', '2026-10-31 21:38:27', 9),
(7, '68319100017', 'student', 'สุทธิรักษ์', 'ภูธร', '0812761358', 'phuthorn2006@gmail.com', '$2y$12$CXuY1qR3r4BfOvwbKK8OqOCjMhRekrBT5OWLGSeZquet3Dvb8iVEW', 'requester', 'active', '2026-11-01 12:14:00', 9),
(8, '1234', 'staff', 'wendy', 'wisteria', '0987654321', 'wendy@mail.com', '$2y$12$4ZdU2H1CGEYQW.YKs1rIR.YeTPWKxngQVTdFaoxsl/BhiDC.l3242', 'requester', 'active', '2026-11-01 13:00:13', 1),
(9, '12341234', 'student', 'นานา', 'ลัลน์ลัลน์', '0123456789', 'phuthorn@gmail.com', '$2y$12$TMWT9pdNVrdeYG3gTGzOTegi7oFy7aBZhmknwYgNjiE8UkKPEaPnO', 'admin', 'active', '2026-11-01 13:02:10', 11),
(10, '123123123', 'student', 'ณาลัลน์', 'อุอิ', '0123456777', 'nalanuuii@gmail.com', '$2y$12$bVnP2P9P6ovosp8DPPlfN.56TBqB3trWEF17ckl4.V6SOTKWTnNz.', 'requester', 'active', '2026-11-01 13:52:00', 7),
(11, '68319100020', 'student', 'มิค', 'โคตวย', '0890371867', 'mix11999@gmail.com', '$2y$12$HOnmS8PAgo6xKz1A/LbZG.395tONi1v.75u7YdAjcpnZjc1M3vYHK', 'requester', 'active', '2026-11-02 13:35:54', 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`d_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  ADD KEY `idx_password_reset_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_password_reset_expiry` (`expires_at`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tickets_ticket_no` (`ticket_no`),
  ADD KEY `idx_tickets_requester` (`requester_id`),
  ADD KEY `idx_tickets_category` (`category_id`),
  ADD KEY `idx_tickets_assigned` (`assigned_to`),
  ADD KEY `idx_tickets_status_created` (`status`,`created_at`);

--
-- Indexes for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attachments_ticket` (`ticket_id`);

--
-- Indexes for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_logs_ticket` (`ticket_id`),
  ADD KEY `idx_logs_user` (`action_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_users_email` (`email`),
  ADD UNIQUE KEY `uq_users_id_number` (`id_number`),
  ADD KEY `idx_users_department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `d_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `fk_tickets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `fk_tickets_requester` FOREIGN KEY (`requester_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  ADD CONSTRAINT `fk_attachments_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  ADD CONSTRAINT `fk_logs_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_logs_user` FOREIGN KEY (`action_by`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`d_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;