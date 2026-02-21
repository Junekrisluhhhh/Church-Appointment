-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 01, 2026 at 03:41 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `appointment_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `action_history`
--

CREATE TABLE `action_history` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` varchar(255) NOT NULL,
  `target_table` varchar(255) NOT NULL,
  `target_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `action_history`
--

INSERT INTO `action_history` (`id`, `admin_id`, `action_type`, `target_table`, `target_id`, `timestamp`) VALUES
(1, 1, 'deny', 'appointments', 159, '2025-12-07 08:23:13'),
(2, 1, 'approve', 'appointments', 219, '2025-12-07 08:24:06'),
(68, 1, 'create_user', 'users', 4, '2025-12-09 11:25:06'),
(69, 1, 'deny', 'appointments', 10231, '2026-02-01 04:06:15'),
(70, 1, 'deny', 'appointments', 10235, '2026-02-01 04:06:18'),
(71, 1, 'deny', 'appointments', 10232, '2026-02-01 04:06:21');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(100) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `appointment_date` datetime DEFAULT NULL,
  `approved` tinyint(1) DEFAULT NULL,
  `assigned_slot` datetime DEFAULT NULL,
  `extra_info` text DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `location` varchar(255) DEFAULT NULL,
  `price` int(11) NOT NULL DEFAULT 0,
  `payment_status` varchar(20) DEFAULT 'Not Paid',
  `assigned_staff_id` int(11) DEFAULT NULL,
  `requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requirements`)),
  `survey_submitted` tinyint(1) DEFAULT 0,
  `survey_shown` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `user_id`, `type`, `reason`, `appointment_date`, `approved`, `assigned_slot`, `extra_info`, `is_deleted`, `location`, `price`, `payment_status`, `assigned_staff_id`, `requirements`, `survey_submitted`, `survey_shown`) VALUES
(1, 25, 'Wedding', '', '2026-12-16 00:00:00', 0, '0000-00-00 00:00:00', '{\"groom_name\":\"trgg\",\"bride_name\":\"dqd\"}', 0, NULL, 15000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/693559bfb6cae.jpg\"]', 0, 0),
(2, 25, 'Wedding', '', '2027-03-31 00:00:00', 1, '0000-00-00 00:00:00', '{\"groom_name\":\"fsfsf\",\"bride_name\":\"dqd\"}', 0, NULL, 15000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/69355a4e946bd.jpg\"]', 0, 0),
(3, 25, 'Funeral', '', '2025-12-07 00:00:00', NULL, '0000-00-00 00:00:00', '{\"deceased_name\":\"ffa\",\"family_contact\":\"12161836836\",\"death_date\":\"2025-12-30\",\"funeral_date\":\"2026-10-10\",\"funeral_type\":\"\",\"funeral_location\":\"rwff\",\"funeral_notes\":\"\"}', 0, NULL, 3000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/69355a8c99411.jpg\"]', 0, 0),
(4, 25, 'Blessing', '', '2026-01-12 00:00:00', NULL, '0000-00-00 00:00:00', '{\"blessing_type\":\"house\",\"location\":\"st fatima\"}', 0, NULL, 1000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/69355a9e2810f.jpg\"]', 0, 0),
(5, 25, 'Certificate Requesting', '3w354', '2025-12-07 00:00:00', NULL, '0000-00-00 00:00:00', '{\"full_name\":\"ttt\",\"certificate_type\":\"Confirmation\",\"baptism_date\":\"2026-02-12\"}', 0, NULL, 200, 'Not Paid', NULL, '[\"uploads\\/requirements\\/693560de9f47e.jpg\"]', 0, 0),
(6, 25, 'Wedding', '', '2026-10-10 00:00:00', 0, '0000-00-00 00:00:00', '{\"groom_name\":\"fsfsf\",\"bride_name\":\"ad\"}', 0, NULL, 15000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/69356a9c17e58.jpg\"]', 0, 0),
(7, 25, 'Baptism', '', '2025-12-28 09:30:00', NULL, '0000-00-00 00:00:00', '{\"baptism_type\":\"Regular\",\"child_name\":\"ewqe\",\"godparent\":\"32d\"}', 0, NULL, 500, 'Not Paid', NULL, '[\"uploads\\/requirements\\/69356c0725389.jpg\"]', 0, 0),
(8, 25, 'Blessing', '', '2026-01-11 00:00:00', NULL, '0000-00-00 00:00:00', '{\"blessing_type\":\"house\",\"location\":\"st fatima\"}', 0, NULL, 1000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/693574ada5c7a.jpg\"]', 0, 0),
(9, 25, 'Baptism', '', '2026-01-12 09:00:00', NULL, '0000-00-00 00:00:00', '{\"baptism_type\":\"Special\",\"child_name\":\"ewqe\",\"godparent\":\"32d\"}', 0, NULL, 2500, 'Not Paid', NULL, '[\"uploads\\/requirements\\/693594420ba22.jpg\"]', 0, 0),
(10, 25, '', '', '2025-12-07 00:00:00', NULL, '0000-00-00 00:00:00', '[]', 0, NULL, 0, 'Not Paid', NULL, '[]', 0, 0),
(11, 25, 'Wedding', '', '2025-12-07 00:00:00', NULL, '0000-00-00 00:00:00', '{\"groom_name\":\"fsfsf\",\"bride_name\":\"dqd\"}', 0, NULL, 15000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/693594ec7a23a.jpg\"]', 0, 0),
(12, 25, 'Baptism', '', '2026-01-30 10:00:00', 1, '0000-00-00 00:00:00', '{\"baptism_type\":\"Special\",\"child_name\":\"ewqe\",\"godparent\":\"32d\"}', 0, NULL, 2500, 'Not Paid', NULL, '[\"uploads\\/requirements\\/69359afd17473.jpg\"]', 0, 0),
(13, 25, 'Funeral', '', '2025-12-07 00:00:00', NULL, '0000-00-00 00:00:00', '{\"deceased_name\":\"ffa\",\"family_contact\":\"12161836836\",\"death_date\":\"2025-10-12\",\"funeral_date\":\"2026-01-10\",\"funeral_type\":\"Blessing Only\",\"funeral_location\":\"rwff\",\"funeral_notes\":\"\"}', 0, NULL, 3000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/6935a25fc5856.jpg\"]', 0, 0),
(14, 25, 'Funeral', '', '2025-12-07 00:00:00', NULL, '0000-00-00 00:00:00', '{\"deceased_name\":\"ffa\",\"family_contact\":\"12161836836\",\"death_date\":\"2025-10-10\",\"funeral_date\":\"2026-01-01\",\"funeral_type\":\"Mass\",\"funeral_location\":\"qwer\",\"funeral_notes\":\"\"}', 0, NULL, 3000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/6935a507adf9b.jpg\"]', 0, 0),
(15, 25, 'Wedding', '', '2026-12-07 00:00:00', NULL, '0000-00-00 00:00:00', '{\"groom_name\":\"fsfsf\",\"bride_name\":\"dfdf\"}', 0, NULL, 15000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/6935a54d0192f.jpg\"]', 0, 0),
(16, 25, 'Blessing', '', '2026-01-11 00:00:00', NULL, '0000-00-00 00:00:00', '{\"blessing_type\":\"house\",\"location\":\"st fatima\"}', 0, NULL, 1000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/6935a5c5880b2.jpg\"]', 0, 0),
(17, 25, 'Blessing', '', '2026-01-11 00:00:00', NULL, '0000-00-00 00:00:00', '{\"blessing_type\":\"house\",\"location\":\"st fatima\"}', 0, NULL, 1000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/6935a5d985b96.jpg\"]', 0, 0),
(18, 25, 'Funeral', '', '2025-12-07 00:00:00', NULL, '0000-00-00 00:00:00', '{\"deceased_name\":\"ffa\",\"family_contact\":\"12161836836\",\"death_date\":\"2025-10-10\",\"funeral_date\":\"2026-01-01\",\"funeral_type\":\"\",\"funeral_location\":\"qwer\",\"funeral_notes\":\"\"}', 0, NULL, 3000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/6935a60407528.jpg\"]', 0, 0),
(10230, 3, 'Pre-Cana Seminar', '', '2025-12-13 00:00:00', NULL, '0000-00-00 00:00:00', '{\"couple_name\":\"yals and glaysa\"}', 0, NULL, 500, 'Not Paid', NULL, '[\"uploads\\/requirements\\/69386e5d57cf7.jpg\"]', 0, 0),
(10231, 3, 'Baptism', '', '2026-12-10 00:00:00', 0, '0000-00-00 00:00:00', '{\"baptism_type\":\"Special\",\"child_name\":\"ewqe\",\"godparent\":\"32d\"}', 0, NULL, 2500, 'Full Paid', NULL, '[\"uploads\\/requirements\\/69386ebf31a88.jpg\"]', 0, 0),
(10232, 3, 'Wedding', '', '2026-03-31 13:00:00', 0, '0000-00-00 00:00:00', '{\"groom_name\":\"\",\"bride_name\":\"\"}', 0, NULL, 15000, 'Not Paid', NULL, '[]', 0, 0),
(10233, 3, 'Funeral', '', '2026-01-30 09:00:00', NULL, '0000-00-00 00:00:00', '{\"deceased_name\":\"\",\"family_contact\":\"\",\"death_date\":\"\",\"funeral_date\":\"2026-01-30\",\"funeral_type\":\"\",\"funeral_location\":\"\",\"funeral_notes\":\"\"}', 0, NULL, 3000, 'Not Paid', NULL, '[]', 0, 0),
(10234, 3, 'Pre-Cana Seminar', '', '2026-01-24 10:00:00', NULL, '0000-00-00 00:00:00', '{\"couple_name\":\"yals and glaysa\"}', 0, NULL, 500, 'Not Paid', NULL, '[\"uploads\\/requirements\\/6978929cc62f0.jpg\"]', 0, 0),
(10235, 3, 'Blessing', '', '2026-02-28 16:00:00', 0, '0000-00-00 00:00:00', '{\"blessing_type\":\"house\",\"location\":\"st fatima\"}', 0, NULL, 1000, 'Not Paid', NULL, '[\"uploads\\/requirements\\/697892edc49bc.jpg\"]', 0, 0),
(10236, 3, 'Wedding', '', '2026-02-24 13:00:00', NULL, '0000-00-00 00:00:00', '{\"groom_name\":\"trgg\",\"bride_name\":\"dfdf\"}', 0, NULL, 15000, 'Half Paid', NULL, '[\"uploads\\/requirements\\/697f31f47ca47.jpg\"]', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `appointment_history`
--

CREATE TABLE `appointment_history` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `available_slots`
--

CREATE TABLE `available_slots` (
  `id` int(11) NOT NULL,
  `slot_datetime` datetime NOT NULL,
  `is_booked` tinyint(1) DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `payment_status` enum('Not Paid','Half Paid','Full Paid') DEFAULT 'Not Paid',
  `capacity` int(11) NOT NULL DEFAULT 1,
  `booked_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `available_slots`
--

INSERT INTO `available_slots` (`id`, `slot_datetime`, `is_booked`, `is_deleted`, `payment_status`, `capacity`, `booked_count`) VALUES
(1, '2025-12-13 11:16:00', 1, 1, 'Not Paid', 1, 1),
(2, '2025-12-12 10:17:00', 1, 1, 'Not Paid', 1, 1),
(3, '2026-01-01 11:44:00', 1, 1, 'Not Paid', 1, 0),
(55, '2025-12-11 14:26:00', 0, 1, 'Not Paid', 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `fixed_schedules`
--

CREATE TABLE `fixed_schedules` (
  `id` int(11) NOT NULL,
  `service_type` varchar(50) DEFAULT NULL,
  `day_of_week` varchar(10) DEFAULT NULL,
  `time_slot` time DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fixed_schedules`
--

INSERT INTO `fixed_schedules` (`id`, `service_type`, `day_of_week`, `time_slot`, `is_active`) VALUES
(1, 'Wedding', 'mon', '09:00:00', 1),
(2, 'Wedding', 'mon', '10:30:00', 1),
(3, 'Wedding', 'mon', '13:00:00', 1),
(4, 'Wedding', 'mon', '14:30:00', 1),
(5, 'Wedding', 'mon', '16:00:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `staff_id`, `message`, `is_read`, `created_at`, `status`) VALUES
(1, 25, 2, 'hi', 0, '2025-12-07 10:05:46', 1),
(2, 25, 2, 'This is a message from the staff of the Archdiocesan Shrine of St. Therese of the Child Jesus, Lahug, Cebu City.\r\n\r\nThank you for choosing our shrine. To confirm your booking, a 50% deposit is required within 24 hours. Failure to make this payment will result in the cancellation of your reservation.\r\n\r\nThank you for your understanding, and God bless.', 0, '2025-12-07 10:08:19', 1),
(13, 3, 0, 'Official Receipt — Reservation Deposit\r\n\r\nService: Pre-Cana Seminar\r\nAppointment #: 10230\r\nScheduled: 2025-12-13\r\n\r\nTotal Fee: ₱500.00\r\nDownpayment (50%): ₱250.00\r\n\r\nPlease pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.\r\n\r\nFor payment arrangements or questions, please contact the parish office.\r\nThank you and God bless.', 0, '2025-12-09 18:45:49', 1),
(14, 3, 0, 'Official Receipt — Reservation Deposit\r\n\r\nService: Baptism\r\nAppointment #: 10231\r\nScheduled: 2026-12-10\r\n\r\nTotal Fee: ₱2,500.00\r\nDownpayment (50%): ₱1,250.00\r\n\r\nPlease pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.\r\n\r\nFor payment arrangements or questions, please contact the parish office.\r\nThank you and God bless.', 0, '2025-12-09 18:47:27', 1),
(15, 3, 0, 'Official Receipt — Reservation Deposit\r\n\r\nService: Wedding\r\nAppointment #: 10232\r\nScheduled: 2026-03-31 13:00:00\r\n\r\nTotal Fee: ₱15,000.00\r\nDownpayment (50%): ₱7,500.00\r\n\r\nPlease pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.\r\n\r\nFor payment arrangements or questions, please contact the parish office.\r\nThank you and God bless.', 0, '2026-01-27 10:23:02', 1),
(16, 3, 0, 'Official Receipt — Reservation Deposit\r\n\r\nService: Funeral\r\nAppointment #: 10233\r\nScheduled: 2026-01-30 09:00:00\r\n\r\nTotal Fee: ₱3,000.00\r\nDownpayment (50%): ₱1,500.00\r\n\r\nPlease pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.\r\n\r\nFor payment arrangements or questions, please contact the parish office.\r\nThank you and God bless.', 0, '2026-01-27 10:23:17', 1),
(17, 3, 0, 'Official Receipt — Reservation Deposit\r\n\r\nService: Pre-Cana Seminar\r\nAppointment #: 10234\r\nScheduled: 2026-01-24 10:00:00\r\n\r\nTotal Fee: ₱500.00\r\nDownpayment (50%): ₱250.00\r\n\r\nPlease pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.\r\n\r\nFor payment arrangements or questions, please contact the parish office.\r\nThank you and God bless.', 0, '2026-01-27 10:25:32', 1),
(18, 3, 0, 'Official Receipt — Reservation Deposit\r\n\r\nService: Blessing\r\nAppointment #: 10235\r\nScheduled: 2026-02-28 16:00:00\r\n\r\nTotal Fee: ₱1,000.00\r\nDownpayment (50%): ₱500.00\r\n\r\nPlease pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.\r\n\r\nFor payment arrangements or questions, please contact the parish office.\r\nThank you and God bless.', 0, '2026-01-27 10:26:53', 1),
(19, 3, 0, 'Official Receipt — Reservation Deposit\r\n\r\nService: Wedding\r\nAppointment #: 10236\r\nScheduled: 2026-02-24 13:00:00\r\n\r\nTotal Fee: ₱15,000.00\r\nDownpayment (50%): ₱7,500.00\r\n\r\nPlease pay the 50% downpayment within 48 hours from receipt of this notice to confirm your booking. Failure to pay within 48 hours will result in cancellation of your reservation.\r\n\r\nFor payment arrangements or questions, please contact the parish office.\r\nThank you and God bless.', 0, '2026-02-01 10:59:00', 1);

-- --------------------------------------------------------

--
-- Table structure for table `service_availability`
--

CREATE TABLE `service_availability` (
  `id` int(11) NOT NULL,
  `available_date` date NOT NULL,
  `service_type` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `slot_services`
--

CREATE TABLE `slot_services` (
  `id` int(11) NOT NULL,
  `slot_id` int(11) NOT NULL,
  `service_type` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `slot_services`
--

INSERT INTO `slot_services` (`id`, `slot_id`, `service_type`) VALUES
(1, 1, 'Wedding'),
(2, 2, 'Blessing'),
(3, 3, 'Wedding'),
(32, 55, 'Blessing');

-- --------------------------------------------------------

--
-- Table structure for table `slot_trash`
--

CREATE TABLE `slot_trash` (
  `id` int(11) NOT NULL,
  `slot_datetime` datetime DEFAULT NULL,
  `is_booked` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `slot_trash`
--

INSERT INTO `slot_trash` (`id`, `slot_datetime`, `is_booked`, `created_at`) VALUES
(1, '2025-12-12 10:17:00', 1, '2025-12-07 15:11:11'),
(22, '2025-12-13 11:16:00', 1, '2025-12-09 18:16:19'),
(23, '2026-01-01 11:44:00', 1, '2025-12-09 18:16:24'),
(24, '2025-12-11 14:26:00', 0, '2026-02-01 11:08:06');

-- --------------------------------------------------------

--
-- Table structure for table `staff_services`
--

CREATE TABLE `staff_services` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `service_type` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `surveys`
--

CREATE TABLE `surveys` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `nps` tinyint(4) NOT NULL,
  `helpful` enum('yes','no') DEFAULT 'no',
  `reasons` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `service_type` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `surveys`
--

INSERT INTO `surveys` (`id`, `appointment_id`, `user_id`, `rating`, `nps`, `helpful`, `reasons`, `comments`, `created_at`, `service_type`) VALUES
(1, 1, 25, 4, 5, 'yes', '[\"Ease of booking\",\"Availability of slots\",\"Timeliness\"]', '', '2025-12-07 11:53:05', NULL),
(2, 2, 25, 4, 5, 'yes', '[\"Ease of booking\",\"Clear requirements\",\"Availability of slots\",\"Staff helpfulness\",\"Timeliness\",\"Website \\/ UX\"]', 'fettety', '2025-12-07 11:59:11', NULL),
(3, 3, 25, 1, 5, 'yes', '[\"Ease of booking\"]', '', '2025-12-07 15:08:31', ''),
(5, 4, 25, 3, 1, 'no', '[]', '', '2025-12-07 15:18:25', ''),
(6, 5, 25, 2, 5, 'no', '[\"Ease of booking\"]', '', '2025-12-07 15:19:30', ''),
(7, 6, 25, 1, 5, 'no', '[\"Ease of booking\"]', '', '2025-12-07 16:05:55', ''),
(8, 7, 25, 1, 0, 'no', '[]', '', '2025-12-07 16:06:37', ''),
(9, 10231, 3, 2, 5, 'yes', '[\"Ease of booking\"]', '', '2025-12-09 18:47:42', ''),
(10, 10234, 3, 3, 5, 'yes', '[\"Ease of booking\",\"Availability of slots\",\"Timeliness\"]', '', '2026-01-27 10:25:53', ''),
(11, 10236, 3, 2, 5, 'yes', '[\"Ease of booking\",\"Availability of slots\"]', 'vsgsgsgsgs', '2026-02-01 11:03:06', '');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('user','admin','staff') NOT NULL DEFAULT 'user',
  `contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `old_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `birthday`, `email`, `password`, `role`, `contact`, `created_at`, `is_deleted`, `old_id`) VALUES
(1, 'Admin', NULL, 'admin@example.com', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'admin', NULL, '2025-12-09 16:49:01', 0, NULL),
(2, 'staff staff', NULL, 'staff@example.com', '0bfae2c02d75d51604bc51fb4ba48c42ecf8e5ad518bfbe5f88f09a7129a8f0a', 'staff', '', '2025-12-09 16:49:01', 0, NULL),
(3, 'yal', '1999-10-23', 'riverayal73@gmail.com', '1ce6c6aef2ce4a8734de707caf9d0116d490bfc6f7fa1eeea5fbd5766e85eef8', 'user', '093232323232', '2025-12-09 16:49:01', 0, NULL),
(4, 'glaysa', '2000-01-01', 'staff123@example.com', '$2y$10$L.mz8u2sQCna/5/QmoYANON31IUDO1QNq8HEHWFxzd/ixsgK9SSwC', 'staff', '', '2025-12-09 11:25:06', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users_new`
--

CREATE TABLE `users_new` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('user','admin','staff') NOT NULL DEFAULT 'user',
  `contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `old_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users_new`
--

INSERT INTO `users_new` (`id`, `name`, `birthday`, `email`, `password`, `role`, `contact`, `created_at`, `is_deleted`, `old_id`) VALUES
(1, 'Admin', NULL, 'admin@example.com', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'admin', NULL, '2025-12-09 16:51:36', 0, NULL),
(2, 'staff staff', NULL, 'staff@example.com', '0bfae2c02d75d51604bc51fb4ba48c42ecf8e5ad518bfbe5f88f09a7129a8f0a', 'staff', '', '2025-12-09 16:51:36', 0, NULL),
(3, 'yal', NULL, 'riverayal73@gmail.com', '1ce6c6aef2ce4a8734de707caf9d0116d490bfc6f7fa1eeea5fbd5766e85eef8', 'user', '093232323232', '2025-12-09 16:51:36', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_map`
--

CREATE TABLE `user_map` (
  `old_id` int(11) NOT NULL DEFAULT 0,
  `new_id` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_map`
--

INSERT INTO `user_map` (`old_id`, `new_id`) VALUES
(1, 1),
(25, 3),
(2, 2);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `action_history`
--
ALTER TABLE `action_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `action_history`
--
ALTER TABLE `action_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `appointment_history`
--
ALTER TABLE `appointment_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `appointment_history_ibfk_2` (`user_id`);

--
-- Indexes for table `available_slots`
--
ALTER TABLE `available_slots`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slot_datetime` (`slot_datetime`);

--
-- Indexes for table `fixed_schedules`
--
ALTER TABLE `fixed_schedules`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `service_availability`
--
ALTER TABLE `service_availability`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `available_date` (`available_date`,`service_type`);

--
-- Indexes for table `slot_services`
--
ALTER TABLE `slot_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `slot_id` (`slot_id`);

--
-- Indexes for table `slot_trash`
--
ALTER TABLE `slot_trash`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff_services`
--
ALTER TABLE `staff_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `surveys`
--
ALTER TABLE `surveys`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users_new`
--
ALTER TABLE `users_new`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `action_history`
--
ALTER TABLE `action_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10237;

--
-- AUTO_INCREMENT for table `appointment_history`
--
ALTER TABLE `appointment_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `available_slots`
--
ALTER TABLE `available_slots`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `fixed_schedules`
--
ALTER TABLE `fixed_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `service_availability`
--
ALTER TABLE `service_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `slot_services`
--
ALTER TABLE `slot_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `slot_trash`
--
ALTER TABLE `slot_trash`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `staff_services`
--
ALTER TABLE `staff_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `surveys`
--
ALTER TABLE `surveys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users_new`
--
ALTER TABLE `users_new`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointment_history`
--
ALTER TABLE `appointment_history`
  ADD CONSTRAINT `appointment_history_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`);

--
-- Constraints for table `slot_services`
--
ALTER TABLE `slot_services`
  ADD CONSTRAINT `slot_services_ibfk_1` FOREIGN KEY (`slot_id`) REFERENCES `available_slots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
