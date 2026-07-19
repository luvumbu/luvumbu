-- phpMyAdmin SQL Dump
-- version 4.2.7.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jun 14, 2019 at 06:10 AM
-- Server version: 5.6.20-log
-- PHP Version: 5.4.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `hrconsulting`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
`user_id` int(11) NOT NULL,
  `user_name` varchar(50) NOT NULL,
  `user_email` varchar(50) NOT NULL,
  `user_password` varchar(100) NOT NULL,
  `user_jesuis` varchar(100) NOT NULL,
  `uset_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=20 ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `user_name`, `user_email`, `user_password`, `user_jesuis`, `uset_update`) VALUES
(1, 'ok', 'ok', 'ok', 'ok', '2019-06-14 01:49:06'),
(2, 'jjjjj', 'jjj', 'c84c766f873ecedf75aa6cf35f1e305e095fec83', 'freelace', '2019-06-14 01:52:42'),
(3, 'kkkk', 'kkkk', '54adbc768978d9574b682470bd1f568f5a3f43da', 'freelace', '2019-06-14 01:52:50'),
(4, 'kkkk', 'kkkk', '54adbc768978d9574b682470bd1f568f5a3f43da', 'freelace', '2019-06-14 01:54:01'),
(5, 'luvumbu', 'luvumbu.n@gmail.com', '123456', '123456', '2019-06-14 02:01:32'),
(6, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:10:19'),
(7, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:10:24'),
(8, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:10:46'),
(9, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:11:07'),
(10, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:11:34'),
(11, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:12:56'),
(12, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:13:13'),
(13, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:13:57'),
(14, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:14:56'),
(15, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:15:41'),
(16, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:17:54'),
(17, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:18:25'),
(18, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:18:56'),
(19, '', '', 'da39a3ee5e6b4b0d3255bfef95601890afd80709', 'recruteur', '2019-06-14 07:19:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `users`
--
ALTER TABLE `users`
 ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=20;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
