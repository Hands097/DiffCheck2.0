-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 11, 2026 at 10:17 AM
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
-- Database: `diffcheck_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `matches`
--

CREATE TABLE `matches` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `round_number` int(11) NOT NULL,
  `match_number` int(11) NOT NULL,
  `team1_id` int(11) DEFAULT NULL,
  `team2_id` int(11) DEFAULT NULL,
  `winner_id` int(11) DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `score1` int(11) DEFAULT 0,
  `score2` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `matches`
--

INSERT INTO `matches` (`id`, `tournament_id`, `round_number`, `match_number`, `team1_id`, `team2_id`, `winner_id`, `status`, `score1`, `score2`) VALUES
(1, 1, 2, 1, 6, 5, NULL, 'pending', 0, 0),
(2, 1, 2, 2, 9, 10, NULL, 'pending', 0, 0),
(3, 1, 3, 1, NULL, NULL, NULL, 'pending', 0, 0),
(4, 1, 1, 1, 6, NULL, 6, 'completed', 0, 0),
(5, 1, 1, 2, 5, 7, 5, 'completed', 3, 2),
(6, 1, 1, 3, 9, NULL, 9, 'completed', 0, 0),
(7, 1, 1, 4, 8, 10, 10, 'completed', 1, 4),
(8, 2, 2, 1, 16, 14, 16, 'completed', 4, 2),
(9, 2, 2, 2, 17, 12, 17, 'completed', 4, 2),
(10, 2, 3, 1, 16, 17, 17, 'completed', 2, 3),
(11, 2, 1, 1, 16, NULL, 16, 'completed', 0, 0),
(12, 2, 1, 2, 13, 14, 14, 'completed', 3, 2),
(13, 2, 1, 3, 17, NULL, 17, 'completed', 0, 0),
(14, 2, 1, 4, 12, 15, 12, 'completed', 4, 2);

-- --------------------------------------------------------

--
-- Table structure for table `players`
--

CREATE TABLE `players` (
  `id` int(11) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `ign` varchar(50) NOT NULL,
  `game` enum('Mobile Legends','Wild Rift','Honor of Kings','Valorant') NOT NULL,
  `role` enum('Mid','Jungle','EXP','Gold','Roam','Duelist','Initiator','Controller','Sentinel','Flex') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `players`
--

INSERT INTO `players` (`id`, `manager_id`, `name`, `ign`, `game`, `role`, `status`, `created_at`) VALUES
(1, 10, 'fghjk', 'rrrrrrrrr', 'Mobile Legends', 'Gold', 'active', '2026-04-11 04:01:13');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `tournament_id` int(11) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `squad_name` varchar(100) NOT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `tournament_id`, `manager_id`, `squad_name`, `status`, `created_at`) VALUES
(1, 1, 7, 'Team Alpha', 'rejected', '2026-04-11 07:11:22'),
(2, 1, 7, 'Beta Squad', 'rejected', '2026-04-11 07:11:22'),
(3, 1, 7, 'Gamma Gaming', 'rejected', '2026-04-11 07:11:22'),
(4, 1, 7, 'Delta Force', 'rejected', '2026-04-11 07:11:22'),
(5, 1, 7, 'Echo Esports', 'accepted', '2026-04-11 07:11:22'),
(6, 1, 7, 'Team Alpha', 'accepted', '2026-04-11 07:11:23'),
(7, 1, 7, 'Beta Squad', 'accepted', '2026-04-11 07:11:23'),
(8, 1, 7, 'Gamma Gaming', 'accepted', '2026-04-11 07:11:23'),
(9, 1, 7, 'Delta Force', 'accepted', '2026-04-11 07:11:23'),
(10, 1, 7, 'Echo Esports', 'accepted', '2026-04-11 07:11:23'),
(11, 2, 7, 'Team Alpha', 'rejected', '2026-04-11 07:28:36'),
(12, 2, 7, 'Beta Squad', 'accepted', '2026-04-11 07:28:36'),
(13, 2, 7, 'Gamma Gaming', 'accepted', '2026-04-11 07:28:36'),
(14, 2, 7, 'Delta Force', 'accepted', '2026-04-11 07:28:36'),
(15, 2, 7, 'Echo Esports', 'accepted', '2026-04-11 07:28:36'),
(16, 2, 7, 'Omega Strikers', 'accepted', '2026-04-11 07:28:36'),
(17, 2, 7, 'Zenith Pro', 'accepted', '2026-04-11 07:28:36');

-- --------------------------------------------------------

--
-- Table structure for table `squads`
--

CREATE TABLE `squads` (
  `id` int(11) NOT NULL,
  `manager_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `game` enum('Mobile Legends','Wild Rift','Honor of Kings','Valorant') NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `squad_members`
--

CREATE TABLE `squad_members` (
  `id` int(11) NOT NULL,
  `squad_id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `member_type` enum('main','sub') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tournaments`
--

CREATE TABLE `tournaments` (
  `id` int(11) NOT NULL,
  `organizer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `game` enum('Mobile Legends','Wild Rift','Honor of Kings','Valorant') NOT NULL,
  `max_teams` int(11) NOT NULL CHECK (`max_teams` <= 16),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `status` enum('pending','active','completed') DEFAULT 'pending',
  `description` varchar(300) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tournaments`
--

INSERT INTO `tournaments` (`id`, `organizer_id`, `name`, `game`, `max_teams`, `created_at`, `is_deleted`, `status`, `description`) VALUES
(1, 9, 'Summers', 'Wild Rift', 8, '2026-04-11 07:11:06', 1, 'active', ''),
(2, 9, 'Spring', 'Mobile Legends', 8, '2026-04-11 07:26:29', 0, 'active', 'hello');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(30) NOT NULL,
  `last_name` varchar(30) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('manager','organizer','admin') NOT NULL DEFAULT 'manager',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `email`, `password`, `role`, `created_at`) VALUES
(7, 'Admin', 'daw to', 'admin@diffcheck.com', '$2y$10$cl.ox9tqRCvyJtYg0m5MlOO4zAmhTFGZ9bi43v7VyhSMOah6zcfGC', 'admin', '2026-03-31 11:49:20'),
(9, 'Michael john', 'Sampayan', 'michaeljohn0615@gmail.com', '$2y$10$VWRzAli/5Z3RKJo1OljDHuIW1p/z3OZJNmDuopJbJ/xAip8JXVBYm', 'organizer', '2026-04-11 03:33:47'),
(10, 'Mikel', 'Sampi', 'mikel@diffcheck.com', '$2y$10$hlpjNYIedovVjTXNNIgKqOtSn/tNvo2FRHPonwUtBGSH3AcDCND36', 'manager', '2026-04-11 03:55:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `matches`
--
ALTER TABLE `matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `team1_id` (`team1_id`),
  ADD KEY `team2_id` (`team2_id`),
  ADD KEY `winner_id` (`winner_id`);

--
-- Indexes for table `players`
--
ALTER TABLE `players`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tournament_id` (`tournament_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `squads`
--
ALTER TABLE `squads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `squad_members`
--
ALTER TABLE `squad_members`
  ADD PRIMARY KEY (`id`),
  ADD KEY `squad_id` (`squad_id`),
  ADD KEY `player_id` (`player_id`);

--
-- Indexes for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `organizer_id` (`organizer_id`);

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
-- AUTO_INCREMENT for table `matches`
--
ALTER TABLE `matches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `players`
--
ALTER TABLE `players`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `squads`
--
ALTER TABLE `squads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `squad_members`
--
ALTER TABLE `squad_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tournaments`
--
ALTER TABLE `tournaments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `matches`
--
ALTER TABLE `matches`
  ADD CONSTRAINT `matches_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `matches_ibfk_2` FOREIGN KEY (`team1_id`) REFERENCES `registrations` (`id`),
  ADD CONSTRAINT `matches_ibfk_3` FOREIGN KEY (`team2_id`) REFERENCES `registrations` (`id`),
  ADD CONSTRAINT `matches_ibfk_4` FOREIGN KEY (`winner_id`) REFERENCES `registrations` (`id`);

--
-- Constraints for table `players`
--
ALTER TABLE `players`
  ADD CONSTRAINT `players_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`tournament_id`) REFERENCES `tournaments` (`id`),
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `squads`
--
ALTER TABLE `squads`
  ADD CONSTRAINT `squads_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `squad_members`
--
ALTER TABLE `squad_members`
  ADD CONSTRAINT `squad_members_ibfk_1` FOREIGN KEY (`squad_id`) REFERENCES `squads` (`id`),
  ADD CONSTRAINT `squad_members_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `players` (`id`);

--
-- Constraints for table `tournaments`
--
ALTER TABLE `tournaments`
  ADD CONSTRAINT `tournaments_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`);
COMMIT;

ALTER TABLE squads ADD COLUMN logo VARCHAR(255) DEFAULT 'default_logo.png' AFTER game;

CREATE TABLE IF NOT EXISTS team_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    match_id INT NOT NULL,
    team_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    reason TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- for otp
CREATE TABLE otp_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    form_data TEXT NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- makes a column to mark if the user is verified or not, and marks all existing users as verified
ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0;
UPDATE users SET is_verified = 1; -- marks all existing users as verified


-- adds is_deleted column to users table to mark if the user is deleted or not, and marks all existing users as not deleted
ALTER TABLE users ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER role;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
