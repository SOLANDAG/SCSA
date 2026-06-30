-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2026 at 08:49 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `scsa_group5`
--

-- --------------------------------------------------------

--
-- Table structure for table `medication_history`
--

CREATE TABLE `medication_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `medicine_id` int(10) UNSIGNED NOT NULL,
  `status` enum('Taken','Missed','Skipped') NOT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medication_history`
--

INSERT INTO `medication_history` (`id`, `user_id`, `medicine_id`, `status`, `scheduled_at`, `confirmed_at`) VALUES
(1, 3, 3, 'Taken', NULL, '2026-06-30 21:22:20'),
(2, 3, 4, 'Taken', NULL, '2026-06-30 21:58:24'),
(3, 3, 1, 'Taken', NULL, '2026-06-30 21:58:32'),
(6, 3, 4, 'Taken', NULL, '2026-07-01 00:05:10'),
(7, 3, 1, 'Taken', NULL, '2026-07-01 00:05:15'),
(8, 3, 3, 'Taken', NULL, '2026-07-01 00:05:17'),
(9, 3, 7, 'Taken', NULL, '2026-07-01 00:07:48'),
(10, 3, 8, 'Taken', NULL, '2026-07-01 02:37:36');

-- --------------------------------------------------------

--
-- Table structure for table `medicines`
--

CREATE TABLE `medicines` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `medicine_name` varchar(150) NOT NULL,
  `medicine_type` enum('Medicine','Vitamin') NOT NULL,
  `dosage` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `quantity_unit` varchar(50) NOT NULL DEFAULT 'Tablet',
  `low_stock_level` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `schedule_time` time NOT NULL,
  `schedule_days` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicines`
--

INSERT INTO `medicines` (`id`, `user_id`, `medicine_name`, `medicine_type`, `dosage`, `description`, `quantity`, `quantity_unit`, `low_stock_level`, `schedule_time`, `schedule_days`, `created_at`, `updated_at`) VALUES
(1, 3, 'Vitamin C', 'Vitamin', '1 Tablet', 'TAKE EVERYDAY! YAY!', 48, 'Tablet', 5, '12:00:00', 'Daily', '2026-06-30 11:17:21', '2026-06-30 16:05:15'),
(3, 3, 'Centrum Advance', 'Vitamin', '1 Tablet', 'TAKE AFTER BREAKFAST', 48, 'Tablet', 5, '06:00:00', 'Daily', '2026-06-30 11:25:25', '2026-06-30 16:05:17'),
(4, 3, 'Gut Healthy Vitamin', 'Vitamin', '1', 'TAKE EVERYDAY! YAY!', 10, 'Capsule', 5, '12:00:00', 'Daily', '2026-06-30 12:10:51', '2026-06-30 18:39:30'),
(7, 3, 'Apple', 'Vitamin', '1', 'TAKE EVERYDAY! YAY!', 29, 'Tablet', 5, '12:00:00', 'Daily', '2026-06-30 16:07:39', '2026-06-30 16:07:48'),
(8, 3, 'Strawberry', 'Medicine', '1', 'TAKE AFTER BREAKFAST', 9, 'Capsule', 5, '12:00:00', 'Daily', '2026-06-30 18:37:17', '2026-06-30 18:37:36'),
(9, 3, 'Mango', 'Medicine', '1', 'TAKE AFTER BREAKFAST', 30, 'Capsule', 5, '12:00:00', 'Daily', '2026-06-30 18:40:18', '2026-06-30 18:40:18'),
(10, 3, 'Melon', 'Medicine', '1', 'TAKE AFTER BREAKFAST', 100, 'Tablet', 5, '12:00:00', 'Tuesday, Wednesday, Friday, Saturday', '2026-06-30 18:41:47', '2026-06-30 18:41:47'),
(11, 3, 'Lychee', 'Medicine', '2', 'TAKE AFTER BREAKFAST', 90, 'Bottle', 5, '12:00:00', 'Monday, Wednesday, Friday, Sunday', '2026-06-30 18:42:32', '2026-06-30 18:42:32');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `user_category` varchar(50) DEFAULT NULL,
  `accessibility_needs` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `birth_date`, `user_category`, `accessibility_needs`, `password`, `created_at`) VALUES
(1, 'admin', 'admin@gmail.com', NULL, NULL, NULL, '$2y$10$zGJNVwoMIbi0DCKo8RvO8OchY5HM6Of55.PCKMfqxZk46WqxdSRd6', '2026-06-29 08:25:30'),
(2, 'Amelia', 'amelia@gmail.com', NULL, NULL, NULL, '$2y$10$.f6Ox/u6bqEvPhW6SBl9F.NKJATQvv51T6pYFUzI9s2pgwHIUaTuW', '2026-06-30 08:27:33'),
(3, 'Lemonade', 'lemon@gmail.com', '2001-01-01', 'Student', NULL, '$2y$10$BDNHFsEiGqUBPbl6U3BqneCqM7XZ9vTYHvgYE/jcIAmclnTzEZujm', '2026-06-30 10:37:44');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `medication_history`
--
ALTER TABLE `medication_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_history_user` (`user_id`),
  ADD KEY `fk_history_medicine` (`medicine_id`);

--
-- Indexes for table `medicines`
--
ALTER TABLE `medicines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_medicine_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `medication_history`
--
ALTER TABLE `medication_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `medicines`
--
ALTER TABLE `medicines`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `medication_history`
--
ALTER TABLE `medication_history`
  ADD CONSTRAINT `fk_history_medicine` FOREIGN KEY (`medicine_id`) REFERENCES `medicines` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `medicines`
--
ALTER TABLE `medicines`
  ADD CONSTRAINT `fk_medicine_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
