<<<<<<< HEAD
-- phpMyAdmin SQL Dump
-- version 5.2.3-3.fc45
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 12, 2026 at 01:26 PM
-- Server version: 12.3.2-MariaDB
-- PHP Version: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `reps_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
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
  `id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL COMMENT 'ใบงานที่เกี่ยวข้อง (NULL ได้ถ้าลบใบงานแล้ว)',
  `to_email` varchar(255) NOT NULL COMMENT 'อีเมลผู้รับ',
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending' COMMENT 'ผลการส่ง',
  `error` text DEFAULT NULL COMMENT 'ข้อความ error กรณีส่งไม่สำเร็จ',
  `sent_at` datetime DEFAULT NULL COMMENT 'เวลาที่ส่งสำเร็จ',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL COMMENT 'รหัสวัสดุ/อะไหล่',
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'ชิ้น',
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(11) NOT NULL DEFAULT 5 COMMENT 'จุดแจ้งเตือนของใกล้หมด',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_code`, `name`, `unit`, `stock_qty`, `min_stock`, `updated_at`) VALUES
(1, 'MAT-001', 'หัวแลน RJ45 Cat6', 'หัว', 100, 20, '2026-11-12 14:02:49'),
(2, 'MAT-002', 'สายแลน Cat6', 'เมตร', 300, 50, '2026-11-12 14:02:49'),
(3, 'MAT-003', 'เมาส์ USB มาตรฐาน', 'ตัว', 15, 5, '2026-11-12 14:02:49'),
(4, 'MAT-004', 'คีย์บอร์ด USB มาตรฐาน', 'ตัว', 15, 5, '2026-11-12 14:02:49'),
(5, 'MAT-005', 'ถ่านไบออส CR2032', 'ก้อน', 20, 5, '2026-11-12 14:02:49');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('receive','withdraw','adjust') NOT NULL COMMENT 'receive=รับเข้า, withdraw=เบิกออก, adjust=ปรับยอด',
  `qty_change` int(11) NOT NULL COMMENT 'จำนวนที่เปลี่ยน (บวก=เพิ่ม, ลบ=ลด)',
  `stock_before` int(11) NOT NULL COMMENT 'ยอดคงเหลือก่อนทำรายการ',
  `stock_after` int(11) NOT NULL COMMENT 'ยอดคงเหลือหลังทำรายการ',
  `ticket_id` int(11) DEFAULT NULL COMMENT 'ใบงานที่เบิกวัสดุ (ถ้ามี)',
  `action_by` int(11) DEFAULT NULL COMMENT 'ผู้ทำรายการ (NULL = ระบบ)',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `score_logs`
--

CREATE TABLE `score_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'สตาฟที่ถูกหัก/เพิ่มคะแนน',
  `ticket_id` int(11) DEFAULT NULL,
  `points_change` int(11) NOT NULL COMMENT 'จำนวนคะแนนที่เปลี่ยน เช่น -5, +2',
  `reason` varchar(255) NOT NULL,
  `action_by` int(11) DEFAULT NULL COMMENT 'NULL = ระบบหักอัตโนมัติ',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('academic_year_start_month', '5', 'เดือนที่เริ่มต้นปีการศึกษา (5 = พฤษภาคม)', '2026-11-12 14:02:49'),
('donate_enabled', '0', 'เปิดปุ่มสนับสนุนค่ากาแฟ (1=เปิด, 0=ปิด)', '2026-11-12 20:20:48'),
('donate_message', '', 'ข้อความขอบคุณ/รายละเอียดหน้าสนับสนุน (ข้อความล้วน)', '2026-11-12 20:20:48'),
('donate_qr_path', '', 'path รูป QR พร้อมเพย์ (โฟลเดอร์อัปโหลดที่ปลอดภัย)', '2026-11-12 20:20:48'),
('footer_text', '', 'ข้อความ footer (ข้อความล้วน)', '2026-11-12 20:20:48'),
('homepage_announcement', '', 'ประกาศบนหน้าแรก (ข้อความล้วน)', '2026-11-12 20:20:48'),
('homepage_intro', '', 'ข้อความต้อนรับ/แนะนำบนหน้าแรก (ข้อความล้วน)', '2026-11-12 20:20:48'),
('homepage_show_announcement', '0', 'แสดงประกาศหน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-12 20:20:48'),
('homepage_show_faq', '1', 'แสดง FAQ หน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-12 20:20:48'),
('homepage_show_steps', '1', 'แสดงขั้นตอนการแจ้งซ่อมหน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-12 20:20:48'),
('primary_color', '', 'สีหลักแบบกำหนดเอง (hex) ถ้าว่างให้ใช้ theme_color', '2026-11-12 20:20:48'),
('queue_dispatch_mode', 'auto', 'โหมดจ่ายงาน: auto=ตามคิวอัตโนมัติ, manual=admin เลือกสตาฟเอง', '2026-11-12 20:20:48'),
('queue_penalty_points', '5', 'จำนวนคะแนนที่หักเมื่อไม่รับงานตามคิว', '2026-11-12 14:02:49'),
('queue_timeout_minutes', '30', 'เวลาที่สตาฟต้องกดรับงานตามคิว (นาที)', '2026-11-12 14:02:49'),
('site_logo_path', '', 'path ไฟล์โลโก้เว็บ (โฟลเดอร์อัปโหลดที่ปลอดภัย)', '2026-11-12 20:20:48'),
('site_name', 'REPS - ระบบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์', 'ชื่อเว็บไซต์ที่แสดงบน Header และ Title', '2026-11-12 14:02:49'),
('theme_color', 'default', 'ธีมสีหลักของเว็บไซต์ (default, blue, emerald, rose, amber)', '2026-11-12 14:02:49');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_no` varchar(30) NOT NULL COMMENT 'เลขที่ใบงาน เช่น LB-2026-XXXX',
  `requester_id` int(11) NOT NULL COMMENT 'ผู้แจ้งซ่อม',
  `category_id` int(11) DEFAULT NULL,
  `custom_category` varchar(255) DEFAULT NULL COMMENT 'ระบุหมวดหมู่เองเมื่อเลือก อื่นๆ',
  `device_detail` text DEFAULT NULL COMMENT 'รายละเอียดอุปกรณ์: ชนิด ยี่ห้อ รุ่น รหัสครุภัณฑ์',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'จำนวนอุปกรณ์ที่ชำรุด',
  `location_note` varchar(255) NOT NULL COMMENT 'สถานที่ / ห้อง / เลขเครื่อง',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'ผู้ที่ติดต่อได้หน้างาน',
  `coordinator_teacher` varchar(255) DEFAULT NULL COMMENT 'ครูผู้ประสานงาน',
  `coordinator_phone` varchar(20) DEFAULT NULL COMMENT 'เบอร์โทรครูผู้ประสานงาน',
  `room_caretaker` varchar(255) DEFAULT NULL COMMENT 'ครูผู้ดูแลห้อง',
  `room_caretaker_phone` varchar(20) DEFAULT NULL COMMENT 'เบอร์โทรครูผู้ดูแลห้อง',
  `symptom` text NOT NULL COMMENT 'อาการเสียที่พบ',
  `urgency` enum('urgent','normal') NOT NULL DEFAULT 'normal' COMMENT 'ความเร่งด่วน: urgent=ด่วน (ต้องแก้ภายในวันนี้), normal=ปกติ',
  `urgency_reason` varchar(500) DEFAULT NULL COMMENT 'เหตุผลที่ต้องดำเนินการด่วน',
  `status` enum('pending','in_progress','awaiting_signoff','completed','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'สถานะงาน: pending=รอรับ, in_progress=กำลังซ่อม, awaiting_signoff=รอครูรับรอง, completed=เสร็จสมบูรณ์, cancelled=ยกเลิก',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'สตาฟ/ช่างผู้รับผิดชอบงาน',
  `queue_assigned_to` int(11) DEFAULT NULL COMMENT 'ID สตาฟที่ถึงคิวรับงานนี้',
  `queue_expires_at` datetime DEFAULT NULL COMMENT 'เวลาหมดเขตรับงานตามคิว',
  `resolution_summary` text DEFAULT NULL COMMENT 'สรุปผลการซ่อม / หมายเหตุปิดงาน',
  `repair_method` text DEFAULT NULL COMMENT 'วิธีดำเนินการ / สิ่งที่ซ่อมไปบ้าง',
  `completion_image` varchar(255) DEFAULT NULL COMMENT 'Path รูปหลักฐานตอนปิดงาน',
  `cancel_reason` varchar(255) DEFAULT NULL COMMENT 'เหตุผลการยกเลิก',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL COMMENT 'วันที่/เวลาที่กดรับงาน',
  `completed_at` datetime DEFAULT NULL COMMENT 'วันที่/เวลาที่ปิดงานเสร็จสิ้น'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_attachments`
--

CREATE TABLE `ticket_attachments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_logs`
--

CREATE TABLE `ticket_logs` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `action_by` int(11) DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'web',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_materials`
--

CREATE TABLE `ticket_materials` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty_used` int(11) NOT NULL,
  `withdrawn_by` int(11) NOT NULL COMMENT 'ผู้เบิก (user_id)',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_signoffs`
--

CREATE TABLE `ticket_signoffs` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `signed_by` int(11) NOT NULL COMMENT 'admin (ครู) ผู้รับรองงาน',
  `signature_image` varchar(255) DEFAULT NULL COMMENT 'path รูปลายเซ็น (private storage)',
  `signed_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'เวลาที่ลงนาม',
  `ip` varchar(45) NOT NULL COMMENT 'IP ของผู้ลงนาม (รองรับ IPv6)',
  `note` varchar(255) DEFAULT NULL COMMENT 'หมายเหตุการรับรอง'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL COMMENT 'รหัสนักศึกษา / รหัสบุคลากร',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `user_type` enum('student','teacher','staff') NOT NULL DEFAULT 'student' COMMENT 'สถานภาพบุคคล',
  `department_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','user') NOT NULL DEFAULT 'user' COMMENT 'สิทธิ์ในระบบ: admin (ครู) > staff > user',
  `status` enum('active','disabled','pending','rejected') NOT NULL DEFAULT 'pending' COMMENT 'สถานะบัญชี: pending=รออนุมัติ, active=ใช้งาน, disabled=ระงับ, rejected=ปฏิเสธ',
  `approved_by` int(11) DEFAULT NULL COMMENT 'admin ผู้พิจารณาอนุมัติ/ปฏิเสธ',
  `approved_at` datetime DEFAULT NULL COMMENT 'เวลาที่อนุมัติหรือปฏิเสธ',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT 'เหตุผลที่ปฏิเสธการสมัคร',
  `score` int(11) NOT NULL DEFAULT 100 COMMENT 'คะแนนสะสมของสตาฟฝ่ายซ่อม',
  `queue_order` int(11) DEFAULT NULL COMMENT 'ลำดับคิวรับงาน (NULL = ไม่อยู่ในคิว)',
  `is_active_queue` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = เปิดรับงานในคิวปัจจุบัน',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `id_number`, `first_name`, `last_name`, `user_type`, `department_id`, `phone_number`, `email`, `password`, `role`, `status`, `approved_by`, `approved_at`, `reject_reason`, `score`, `queue_order`, `is_active_queue`, `created_at`) VALUES
(6, '123123', 'นานา', 'ลลน', 'student', 3, '0987456544', 'ertyrt@gmail.com', '$2y$12$VRfuoApPFvPJKlkVcBOVZued5HEV2SY2X0b5cBE/ITjTrH1szMd7e', 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:19:11'),
(7, '123123123', 'นานา', 'ลัลน์ลัลน์', 'student', 3, '0811111111', 'tytyty@gmail.com', '$2y$12$kW1i741lHXeQxRiTdb2Noe6qD5OkwQaIGt3Sfyj.zkD.jzrzzxv.a', 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:20:09'),
(8, '1234', 'พงศธร', 'ศรีสมัย', 'teacher', 5, '0811024898', 'pongsathorn.cat@gmail.com', '$2y$12$ZV/CWbhj7dS6YW96gPzud.MuQ1gWJAvOAxplBgWYA7YOYFiv5MZTm', 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:29:06'),
(9, 'staff_nalan', 'สุทธิรรักษ์', 'ภูธร', 'student', 1, '0812761358', 'phuthorn2006@gmail.com', '$2y$12$01YFyc2dy9xKImpGR.ObxOtA3EIsS5mqEG240II7oIWWv9.cpV5kK', 'staff', 'active', NULL, NULL, NULL, 100, NULL, 1, '2026-11-12 16:04:00'),
(10, 'admin_nalan', 'นานา', 'ลัลน์ลัลน์', 'teacher', 5, '0810028001', 'nalan@gmail.com', '$2y$12$iJawgqthlUSx92Pv3xk3VegMmHMyZeZz91HgTseShEFoewgWimMs.', 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 16:08:15'),
(11, '12345678', 'got', 'got', 'student', 5, '0123456789', '1922@gmail.com', '$2y$12$WxTNtOZ1S7/PfgokrQiDpOZkl92a.Mi96VxeVbd8WTCp/qsLGJWiC', 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 19:22:48');

-- --------------------------------------------------------

--
-- Table structure for table `user_audit_logs`
--

CREATE TABLE `user_audit_logs` (
  `id` int(11) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `action_detail` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_categories_name` (`name`);

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
  ADD KEY `idx_email_status_created` (`status`,`created_at`);

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
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

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
  ADD KEY `fk_tickets_category` (`category_id`);

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
  ADD UNIQUE KEY `uk_signoff_ticket` (`ticket_id`),
  ADD KEY `idx_signoff_user` (`signed_by`),
  ADD KEY `idx_signoff_signed_at` (`signed_at`);

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
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `d_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `score_logs`
--
ALTER TABLE `score_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_materials`
--
ALTER TABLE `ticket_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_signoffs`
--
ALTER TABLE `ticket_signoffs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_audit_logs`
--
ALTER TABLE `user_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tickets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
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
  ADD CONSTRAINT `fk_signoff_user` FOREIGN KEY (`signed_by`) REFERENCES `users` (`user_id`);

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
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_audit_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
=======
-- phpMyAdmin SQL Dump
-- version 5.2.3-3.fc45
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Nov 12, 2026 at 01:26 PM
-- Server version: 12.3.2-MariaDB
-- PHP Version: 8.5.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `reps_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL
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
  `id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL COMMENT 'ใบงานที่เกี่ยวข้อง (NULL ได้ถ้าลบใบงานแล้ว)',
  `to_email` varchar(255) NOT NULL COMMENT 'อีเมลผู้รับ',
  `status` enum('pending','sent','failed') NOT NULL DEFAULT 'pending' COMMENT 'ผลการส่ง',
  `error` text DEFAULT NULL COMMENT 'ข้อความ error กรณีส่งไม่สำเร็จ',
  `sent_at` datetime DEFAULT NULL COMMENT 'เวลาที่ส่งสำเร็จ',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL,
  `item_code` varchar(50) DEFAULT NULL COMMENT 'รหัสวัสดุ/อะไหล่',
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'ชิ้น',
  `stock_qty` int(11) NOT NULL DEFAULT 0,
  `min_stock` int(11) NOT NULL DEFAULT 5 COMMENT 'จุดแจ้งเตือนของใกล้หมด',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_code`, `name`, `unit`, `stock_qty`, `min_stock`, `updated_at`) VALUES
(1, 'MAT-001', 'หัวแลน RJ45 Cat6', 'หัว', 100, 20, '2026-11-12 14:02:49'),
(2, 'MAT-002', 'สายแลน Cat6', 'เมตร', 300, 50, '2026-11-12 14:02:49'),
(3, 'MAT-003', 'เมาส์ USB มาตรฐาน', 'ตัว', 15, 5, '2026-11-12 14:02:49'),
(4, 'MAT-004', 'คีย์บอร์ด USB มาตรฐาน', 'ตัว', 15, 5, '2026-11-12 14:02:49'),
(5, 'MAT-005', 'ถ่านไบออส CR2032', 'ก้อน', 20, 5, '2026-11-12 14:02:49');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_movements`
--

CREATE TABLE `inventory_movements` (
  `id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `movement_type` enum('receive','withdraw','adjust') NOT NULL COMMENT 'receive=รับเข้า, withdraw=เบิกออก, adjust=ปรับยอด',
  `qty_change` int(11) NOT NULL COMMENT 'จำนวนที่เปลี่ยน (บวก=เพิ่ม, ลบ=ลด)',
  `stock_before` int(11) NOT NULL COMMENT 'ยอดคงเหลือก่อนทำรายการ',
  `stock_after` int(11) NOT NULL COMMENT 'ยอดคงเหลือหลังทำรายการ',
  `ticket_id` int(11) DEFAULT NULL COMMENT 'ใบงานที่เบิกวัสดุ (ถ้ามี)',
  `action_by` int(11) DEFAULT NULL COMMENT 'ผู้ทำรายการ (NULL = ระบบ)',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `score_logs`
--

CREATE TABLE `score_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'สตาฟที่ถูกหัก/เพิ่มคะแนน',
  `ticket_id` int(11) DEFAULT NULL,
  `points_change` int(11) NOT NULL COMMENT 'จำนวนคะแนนที่เปลี่ยน เช่น -5, +2',
  `reason` varchar(255) NOT NULL,
  `action_by` int(11) DEFAULT NULL COMMENT 'NULL = ระบบหักอัตโนมัติ',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('academic_year_start_month', '5', 'เดือนที่เริ่มต้นปีการศึกษา (5 = พฤษภาคม)', '2026-11-12 14:02:49'),
('donate_enabled', '0', 'เปิดปุ่มสนับสนุนค่ากาแฟ (1=เปิด, 0=ปิด)', '2026-11-12 20:20:48'),
('donate_message', '', 'ข้อความขอบคุณ/รายละเอียดหน้าสนับสนุน (ข้อความล้วน)', '2026-11-12 20:20:48'),
('donate_qr_path', '', 'path รูป QR พร้อมเพย์ (โฟลเดอร์อัปโหลดที่ปลอดภัย)', '2026-11-12 20:20:48'),
('footer_text', '', 'ข้อความ footer (ข้อความล้วน)', '2026-11-12 20:20:48'),
('homepage_announcement', '', 'ประกาศบนหน้าแรก (ข้อความล้วน)', '2026-11-12 20:20:48'),
('homepage_intro', '', 'ข้อความต้อนรับ/แนะนำบนหน้าแรก (ข้อความล้วน)', '2026-11-12 20:20:48'),
('homepage_show_announcement', '0', 'แสดงประกาศหน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-12 20:20:48'),
('homepage_show_faq', '1', 'แสดง FAQ หน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-12 20:20:48'),
('homepage_show_steps', '1', 'แสดงขั้นตอนการแจ้งซ่อมหน้าแรก (1=แสดง, 0=ซ่อน)', '2026-11-12 20:20:48'),
('primary_color', '', 'สีหลักแบบกำหนดเอง (hex) ถ้าว่างให้ใช้ theme_color', '2026-11-12 20:20:48'),
('queue_dispatch_mode', 'auto', 'โหมดจ่ายงาน: auto=ตามคิวอัตโนมัติ, manual=admin เลือกสตาฟเอง', '2026-11-12 20:20:48'),
('queue_penalty_points', '5', 'จำนวนคะแนนที่หักเมื่อไม่รับงานตามคิว', '2026-11-12 14:02:49'),
('queue_timeout_minutes', '30', 'เวลาที่สตาฟต้องกดรับงานตามคิว (นาที)', '2026-11-12 14:02:49'),
('site_logo_path', '', 'path ไฟล์โลโก้เว็บ (โฟลเดอร์อัปโหลดที่ปลอดภัย)', '2026-11-12 20:20:48'),
('site_name', 'REPS - ระบบแจ้งซ่อมอุปกรณ์คอมพิวเตอร์', 'ชื่อเว็บไซต์ที่แสดงบน Header และ Title', '2026-11-12 14:02:49'),
('theme_color', 'default', 'ธีมสีหลักของเว็บไซต์ (default, blue, emerald, rose, amber)', '2026-11-12 14:02:49');

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `id` int(11) NOT NULL,
  `ticket_no` varchar(30) NOT NULL COMMENT 'เลขที่ใบงาน เช่น LB-2026-XXXX',
  `requester_id` int(11) NOT NULL COMMENT 'ผู้แจ้งซ่อม',
  `category_id` int(11) DEFAULT NULL,
  `custom_category` varchar(255) DEFAULT NULL COMMENT 'ระบุหมวดหมู่เองเมื่อเลือก อื่นๆ',
  `device_detail` text DEFAULT NULL COMMENT 'รายละเอียดอุปกรณ์: ชนิด ยี่ห้อ รุ่น รหัสครุภัณฑ์',
  `quantity` int(11) NOT NULL DEFAULT 1 COMMENT 'จำนวนอุปกรณ์ที่ชำรุด',
  `location_note` varchar(255) NOT NULL COMMENT 'สถานที่ / ห้อง / เลขเครื่อง',
  `contact_person` varchar(255) DEFAULT NULL COMMENT 'ผู้ที่ติดต่อได้หน้างาน',
  `coordinator_teacher` varchar(255) DEFAULT NULL COMMENT 'ครูผู้ประสานงาน',
  `coordinator_phone` varchar(20) DEFAULT NULL COMMENT 'เบอร์โทรครูผู้ประสานงาน',
  `room_caretaker` varchar(255) DEFAULT NULL COMMENT 'ครูผู้ดูแลห้อง',
  `room_caretaker_phone` varchar(20) DEFAULT NULL COMMENT 'เบอร์โทรครูผู้ดูแลห้อง',
  `symptom` text NOT NULL COMMENT 'อาการเสียที่พบ',
  `urgency` enum('urgent','normal') NOT NULL DEFAULT 'normal' COMMENT 'ความเร่งด่วน: urgent=ด่วน (ต้องแก้ภายในวันนี้), normal=ปกติ',
  `urgency_reason` varchar(500) DEFAULT NULL COMMENT 'เหตุผลที่ต้องดำเนินการด่วน',
  `status` enum('pending','in_progress','awaiting_signoff','completed','cancelled') NOT NULL DEFAULT 'pending' COMMENT 'สถานะงาน: pending=รอรับ, in_progress=กำลังซ่อม, awaiting_signoff=รอครูรับรอง, completed=เสร็จสมบูรณ์, cancelled=ยกเลิก',
  `assigned_to` int(11) DEFAULT NULL COMMENT 'สตาฟ/ช่างผู้รับผิดชอบงาน',
  `queue_assigned_to` int(11) DEFAULT NULL COMMENT 'ID สตาฟที่ถึงคิวรับงานนี้',
  `queue_expires_at` datetime DEFAULT NULL COMMENT 'เวลาหมดเขตรับงานตามคิว',
  `resolution_summary` text DEFAULT NULL COMMENT 'สรุปผลการซ่อม / หมายเหตุปิดงาน',
  `repair_method` text DEFAULT NULL COMMENT 'วิธีดำเนินการ / สิ่งที่ซ่อมไปบ้าง',
  `completion_image` varchar(255) DEFAULT NULL COMMENT 'Path รูปหลักฐานตอนปิดงาน',
  `cancel_reason` varchar(255) DEFAULT NULL COMMENT 'เหตุผลการยกเลิก',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL COMMENT 'วันที่/เวลาที่กดรับงาน',
  `completed_at` datetime DEFAULT NULL COMMENT 'วันที่/เวลาที่ปิดงานเสร็จสิ้น'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_attachments`
--

CREATE TABLE `ticket_attachments` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_logs`
--

CREATE TABLE `ticket_logs` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `old_status` varchar(30) DEFAULT NULL,
  `new_status` varchar(30) NOT NULL,
  `action_by` int(11) DEFAULT NULL,
  `source` varchar(30) NOT NULL DEFAULT 'web',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_materials`
--

CREATE TABLE `ticket_materials` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `qty_used` int(11) NOT NULL,
  `withdrawn_by` int(11) NOT NULL COMMENT 'ผู้เบิก (user_id)',
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_signoffs`
--

CREATE TABLE `ticket_signoffs` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `signed_by` int(11) NOT NULL COMMENT 'admin (ครู) ผู้รับรองงาน',
  `signature_image` varchar(255) DEFAULT NULL COMMENT 'path รูปลายเซ็น (private storage)',
  `signed_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'เวลาที่ลงนาม',
  `ip` varchar(45) NOT NULL COMMENT 'IP ของผู้ลงนาม (รองรับ IPv6)',
  `note` varchar(255) DEFAULT NULL COMMENT 'หมายเหตุการรับรอง'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `id_number` varchar(20) NOT NULL COMMENT 'รหัสนักศึกษา / รหัสบุคลากร',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `user_type` enum('student','teacher','staff') NOT NULL DEFAULT 'student' COMMENT 'สถานภาพบุคคล',
  `department_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff','user') NOT NULL DEFAULT 'user' COMMENT 'สิทธิ์ในระบบ: admin (ครู) > staff > user',
  `status` enum('active','disabled','pending','rejected') NOT NULL DEFAULT 'pending' COMMENT 'สถานะบัญชี: pending=รออนุมัติ, active=ใช้งาน, disabled=ระงับ, rejected=ปฏิเสธ',
  `approved_by` int(11) DEFAULT NULL COMMENT 'admin ผู้พิจารณาอนุมัติ/ปฏิเสธ',
  `approved_at` datetime DEFAULT NULL COMMENT 'เวลาที่อนุมัติหรือปฏิเสธ',
  `reject_reason` varchar(255) DEFAULT NULL COMMENT 'เหตุผลที่ปฏิเสธการสมัคร',
  `score` int(11) NOT NULL DEFAULT 100 COMMENT 'คะแนนสะสมของสตาฟฝ่ายซ่อม',
  `queue_order` int(11) DEFAULT NULL COMMENT 'ลำดับคิวรับงาน (NULL = ไม่อยู่ในคิว)',
  `is_active_queue` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = เปิดรับงานในคิวปัจจุบัน',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `id_number`, `first_name`, `last_name`, `user_type`, `department_id`, `phone_number`, `email`, `password`, `role`, `status`, `approved_by`, `approved_at`, `reject_reason`, `score`, `queue_order`, `is_active_queue`, `created_at`) VALUES
(6, '123123', 'นานา', 'ลลน', 'student', 3, '0987456544', 'ertyrt@gmail.com', '$2y$12$VRfuoApPFvPJKlkVcBOVZued5HEV2SY2X0b5cBE/ITjTrH1szMd7e', 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:19:11'),
(7, '123123123', 'นานา', 'ลัลน์ลัลน์', 'student', 3, '0811111111', 'tytyty@gmail.com', '$2y$12$kW1i741lHXeQxRiTdb2Noe6qD5OkwQaIGt3Sfyj.zkD.jzrzzxv.a', 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:20:09'),
(8, '1234', 'พงศธร', 'ศรีสมัย', 'teacher', 5, '0811024898', 'pongsathorn.cat@gmail.com', '$2y$12$ZV/CWbhj7dS6YW96gPzud.MuQ1gWJAvOAxplBgWYA7YOYFiv5MZTm', 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 15:29:06'),
(9, 'staff_nalan', 'สุทธิรรักษ์', 'ภูธร', 'student', 1, '0812761358', 'phuthorn2006@gmail.com', '$2y$12$01YFyc2dy9xKImpGR.ObxOtA3EIsS5mqEG240II7oIWWv9.cpV5kK', 'staff', 'active', NULL, NULL, NULL, 100, NULL, 1, '2026-11-12 16:04:00'),
(10, 'admin_nalan', 'นานา', 'ลัลน์ลัลน์', 'teacher', 5, '0810028001', 'nalan@gmail.com', '$2y$12$iJawgqthlUSx92Pv3xk3VegMmHMyZeZz91HgTseShEFoewgWimMs.', 'admin', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 16:08:15'),
(11, '12345678', 'got', 'got', 'student', 5, '0123456789', '1922@gmail.com', '$2y$12$WxTNtOZ1S7/PfgokrQiDpOZkl92a.Mi96VxeVbd8WTCp/qsLGJWiC', 'user', 'active', NULL, NULL, NULL, 100, NULL, 0, '2026-11-12 19:22:48');

-- --------------------------------------------------------

--
-- Table structure for table `user_audit_logs`
--

CREATE TABLE `user_audit_logs` (
  `id` int(11) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `changed_by` int(11) NOT NULL,
  `action_detail` varchar(500) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_categories_name` (`name`);

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
  ADD KEY `idx_email_status_created` (`status`,`created_at`);

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
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

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
  ADD KEY `fk_tickets_category` (`category_id`);

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
  ADD UNIQUE KEY `uk_signoff_ticket` (`ticket_id`),
  ADD KEY `idx_signoff_user` (`signed_by`),
  ADD KEY `idx_signoff_signed_at` (`signed_at`);

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
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `d_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `inventory_movements`
--
ALTER TABLE `inventory_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `score_logs`
--
ALTER TABLE `score_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_attachments`
--
ALTER TABLE `ticket_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_logs`
--
ALTER TABLE `ticket_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_materials`
--
ALTER TABLE `ticket_materials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_signoffs`
--
ALTER TABLE `ticket_signoffs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_audit_logs`
--
ALTER TABLE `user_audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

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
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `fk_tickets_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tickets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
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
  ADD CONSTRAINT `fk_signoff_user` FOREIGN KEY (`signed_by`) REFERENCES `users` (`user_id`);

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
  ADD CONSTRAINT `fk_audit_actor` FOREIGN KEY (`changed_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_audit_target` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
>>>>>>> c08963be28603300a3a15f3774c665c05d933fff
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;