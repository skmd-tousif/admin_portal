-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Sep 01, 2025 at 08:33 AM
-- Server version: 10.11.10-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u522875338_PACE_DB`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `admin_id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `admin_name` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



-- --------------------------------------------------------

--
-- Table structure for table `adminclientpayment`
--

CREATE TABLE `adminclientpayment` (
  `transaction_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount_paid` int(11) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `client_id` int(11) NOT NULL,
  `payment_in_inr` float NOT NULL DEFAULT 0,
  `description` varchar(255) DEFAULT NULL,
  `payment_done` tinyint(1) DEFAULT 0,
  `discount` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--

-- --------------------------------------------------------

--
-- Table structure for table `admintlpayment`
--

CREATE TABLE `admintlpayment` (
  `transaction_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount_paid` int(11) NOT NULL,
  `tl_name` varchar(255) NOT NULL,
  `tl_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--

-- --------------------------------------------------------

--
-- Table structure for table `client`
--

CREATE TABLE `client` (
  `client_id` int(11) NOT NULL,
  `client_name` varchar(255) NOT NULL,
  `college_name` varchar(255) DEFAULT NULL,
  `reffered_by` varchar(255) DEFAULT NULL,
  `reffered_by_client_id` int(11) DEFAULT NULL,
  `due_payment` int(11) NOT NULL,
  `login_id` varchar(255) NOT NULL,
  `login_password` varchar(255) NOT NULL,
  `initial_dues` int(11) NOT NULL DEFAULT 0,
  `college_id` int(11) DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--

-- --------------------------------------------------------

--
-- Table structure for table `colleges`
--

CREATE TABLE `colleges` (
  `college_id` int(11) NOT NULL,
  `college_name` varchar(255) NOT NULL,
  `country` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--


-- --------------------------------------------------------

--
-- Table structure for table `expert`
--

CREATE TABLE `expert` (
  `expert_id` int(11) NOT NULL,
  `expert_name` varchar(255) NOT NULL,
  `mobile_no` bigint(20) NOT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `dues` int(11) DEFAULT 0,
  `initial_dues` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--


-- --------------------------------------------------------

--
-- Table structure for table `task`
--

CREATE TABLE `task` (
  `task_id` int(11) NOT NULL,
  `task_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_team_lead_name` varchar(255) NOT NULL,
  `assigned_expert_1` varchar(255) DEFAULT NULL,
  `assigned_expert_2` varchar(255) DEFAULT NULL,
  `assigned_expert_3` varchar(255) DEFAULT NULL,
  `price` int(11) NOT NULL,
  `tl_price` int(11) NOT NULL DEFAULT 0,
  `expert_price1` int(11) DEFAULT NULL,
  `expert_price2` int(11) DEFAULT NULL,
  `expert_price3` int(11) DEFAULT NULL,
  `task_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` varchar(255) NOT NULL,
  `word_count` int(11) DEFAULT NULL,
  `client_id` int(11) NOT NULL,
  `team_lead_id` int(11) NOT NULL,
  `expert_id_1` int(11) DEFAULT NULL,
  `expert_id_2` int(11) DEFAULT NULL,
  `expert_id_3` int(11) DEFAULT NULL,
  `issue` text DEFAULT NULL,
  `total_cost` int(11) DEFAULT 0,
  `incomplete_information` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--


-- --------------------------------------------------------

--
-- Table structure for table `teamlead`
--

CREATE TABLE `teamlead` (
  `team_lead_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `mobile_no` bigint(20) NOT NULL,
  `dues` int(11) DEFAULT 0,
  `initial_due` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--


-- --------------------------------------------------------

--
-- Table structure for table `tlexpertpayment`
--

CREATE TABLE `tlexpertpayment` (
  `transaction_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount_paid` int(11) NOT NULL,
  `expert_name` varchar(255) NOT NULL,
  `expert_id` int(11) NOT NULL,
  `team_lead_name` varchar(255) NOT NULL,
  `team_lead_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--

--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `adminclientpayment`
--
ALTER TABLE `adminclientpayment`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `client_id` (`client_id`);

--
-- Indexes for table `admintlpayment`
--
ALTER TABLE `admintlpayment`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `tl_id` (`tl_id`);

--
-- Indexes for table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`client_id`),
  ADD KEY `reffered_by_client_id` (`reffered_by_client_id`),
  ADD KEY `fk_client_college` (`college_id`);

--
-- Indexes for table `colleges`
--
ALTER TABLE `colleges`
  ADD PRIMARY KEY (`college_id`);

--
-- Indexes for table `expert`
--
ALTER TABLE `expert`
  ADD PRIMARY KEY (`expert_id`);

--
-- Indexes for table `task`
--
ALTER TABLE `task`
  ADD PRIMARY KEY (`task_id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `team_lead_id` (`team_lead_id`),
  ADD KEY `expert_id_1` (`expert_id_1`),
  ADD KEY `expert_id_2` (`expert_id_2`),
  ADD KEY `expert_id_3` (`expert_id_3`);

--
-- Indexes for table `teamlead`
--
ALTER TABLE `teamlead`
  ADD PRIMARY KEY (`team_lead_id`);

--
-- Indexes for table `tlexpertpayment`
--
ALTER TABLE `tlexpertpayment`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `expert_id` (`expert_id`),
  ADD KEY `team_lead_id` (`team_lead_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `adminclientpayment`
--
ALTER TABLE `adminclientpayment`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=571;

--
-- AUTO_INCREMENT for table `admintlpayment`
--
ALTER TABLE `admintlpayment`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `client`
--
ALTER TABLE `client`
  MODIFY `client_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=947;

--
-- AUTO_INCREMENT for table `colleges`
--
ALTER TABLE `colleges`
  MODIFY `college_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=135;

--
-- AUTO_INCREMENT for table `expert`
--
ALTER TABLE `expert`
  MODIFY `expert_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=75;

--
-- AUTO_INCREMENT for table `task`
--
ALTER TABLE `task`
  MODIFY `task_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=944;

--
-- AUTO_INCREMENT for table `teamlead`
--
ALTER TABLE `teamlead`
  MODIFY `team_lead_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `tlexpertpayment`
--
ALTER TABLE `tlexpertpayment`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `adminclientpayment`
--
ALTER TABLE `adminclientpayment`
  ADD CONSTRAINT `adminclientpayment_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `client` (`client_id`);

--
-- Constraints for table `admintlpayment`
--
ALTER TABLE `admintlpayment`
  ADD CONSTRAINT `admintlpayment_ibfk_1` FOREIGN KEY (`tl_id`) REFERENCES `teamlead` (`team_lead_id`);

--
-- Constraints for table `client`
--
ALTER TABLE `client`
  ADD CONSTRAINT `client_ibfk_1` FOREIGN KEY (`reffered_by_client_id`) REFERENCES `client` (`client_id`),
  ADD CONSTRAINT `fk_client_college` FOREIGN KEY (`college_id`) REFERENCES `colleges` (`college_id`) ON DELETE SET NULL;

--
-- Constraints for table `task`
--
ALTER TABLE `task`
  ADD CONSTRAINT `task_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `client` (`client_id`),
  ADD CONSTRAINT `task_ibfk_2` FOREIGN KEY (`team_lead_id`) REFERENCES `teamlead` (`team_lead_id`),
  ADD CONSTRAINT `task_ibfk_3` FOREIGN KEY (`expert_id_1`) REFERENCES `expert` (`expert_id`),
  ADD CONSTRAINT `task_ibfk_4` FOREIGN KEY (`expert_id_2`) REFERENCES `expert` (`expert_id`),
  ADD CONSTRAINT `task_ibfk_5` FOREIGN KEY (`expert_id_3`) REFERENCES `expert` (`expert_id`);

--
-- Constraints for table `tlexpertpayment`
--
ALTER TABLE `tlexpertpayment`
  ADD CONSTRAINT `tlexpertpayment_ibfk_1` FOREIGN KEY (`expert_id`) REFERENCES `expert` (`expert_id`),
  ADD CONSTRAINT `tlexpertpayment_ibfk_2` FOREIGN KEY (`team_lead_id`) REFERENCES `teamlead` (`team_lead_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
