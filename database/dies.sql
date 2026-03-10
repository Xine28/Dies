-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 05, 2026 at 06:53 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dies`
--

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(100) NOT NULL,
  `supervisor_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_name`, `supervisor_name`) VALUES
(1, 'IT Department', 'Adrian Reyes'),
(2, 'Marketing Department', 'Yesha Malibong'),
(3, 'Inventory Department', 'Julianna Margallo'),
(4, 'Customer Service Department', 'TBA');

-- --------------------------------------------------------

--
-- Table structure for table `evaluations`
--

CREATE TABLE `evaluations` (
  `id` int(11) NOT NULL,
  `supervisor_id` int(11) NOT NULL,
  `intern_id` int(11) NOT NULL,
  `comm` tinyint(4) DEFAULT NULL,
  `problem` tinyint(4) DEFAULT NULL,
  `teamwork` tinyint(4) DEFAULT NULL,
  `score` smallint(6) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `eval_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `intern_name` varchar(100) DEFAULT NULL,
  `intern_email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `evaluations`
--

INSERT INTO `evaluations` (`id`, `supervisor_id`, `intern_id`, `comm`, `problem`, `teamwork`, `score`, `comments`, `eval_date`, `created_at`, `intern_name`, `intern_email`) VALUES
(3, 0, 5, 5, 5, 5, 100, '', '2026-03-04', '2026-03-03 03:05:37', NULL, NULL),
(4, 0, 6, 5, 5, 5, 100, 'dawdawdwa', '2026-03-05', '2026-03-05 15:32:31', 'Erica Marie Jara', 'ericajara031@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `student_time_logs`
--

CREATE TABLE `student_time_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `log_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `reminder_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role` enum('student','supervisor','boss') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `resume` varchar(255) DEFAULT NULL,
  `profile_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL,
  `last_student_dashboard_at` datetime DEFAULT NULL,
  `last_supervisor_view_at` datetime DEFAULT NULL,
  `last_supervisor_viewer` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `full_name`, `email`, `password`, `department_id`, `resume`, `profile_completed`, `created_at`, `profile_pic`) VALUES
(1, 'boss', 'Xine Fajardo', 'boss@dies.com', '4988ec12e3d9a8db3943f47d4ca37c62', NULL, NULL, 0, '2026-03-02 14:48:18', NULL),
(2, 'supervisor', 'Adrian Reyes', 'it@dies.com', 'f35364bc808b079853de5a1e343e7159', 1, NULL, 0, '2026-03-02 14:48:18', NULL),
(3, 'supervisor', 'Yesha Malibong', 'marketing@dies.com', 'f35364bc808b079853de5a1e343e7159', 2, NULL, 0, '2026-03-02 14:48:18', NULL),
(4, 'supervisor', 'Julianna Margallo', 'inventory@dies.com', 'f35364bc808b079853de5a1e343e7159', 3, NULL, 0, '2026-03-02 14:48:18', NULL),
(6, 'student', 'Erica Marie Jara', 'ericajara031@gmail.com', '$2y$10$/rL7b8jfzbdP56zc9MrxJ.3vtrdaKOO3lmO2km6x95mPDJY/Z3NQK', 1, '1772724873_XINE_Resume.pdf', 1, '2026-03-05 15:17:28', '6_1772724842_WIN20250813191252Pro.jpg');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `evaluations`
--
ALTER TABLE `evaluations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `department_id` (`department_id`);

--
-- Indexes for table `student_time_logs`
--
ALTER TABLE `student_time_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_student_date` (`student_id`,`log_date`),
  ADD KEY `idx_student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `evaluations`
--
ALTER TABLE `evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `student_time_logs`
--
ALTER TABLE `student_time_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
