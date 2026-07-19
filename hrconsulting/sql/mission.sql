-- phpMyAdmin SQL Dump
-- version 4.2.7.1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jun 14, 2019 at 12:20 PM
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
-- Table structure for table `mission`
--

CREATE TABLE IF NOT EXISTS `mission` (
`mission_id` int(11) NOT NULL,
  `mission_id_user` int(11) NOT NULL,
  `mission_titre_mission` varchar(100) NOT NULL,
  `mission_description` varchar(500) NOT NULL,
  `mission_technologie` varchar(500) DEFAULT NULL,
  `mission_profil` varchar(50) DEFAULT NULL,
  `mission_niveau_etudes` varchar(50) DEFAULT NULL,
  `mission_ville` varchar(50) NOT NULL,
  `mission_type_contrat` varchar(50) DEFAULT NULL,
  `mission_date_up` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

--
-- Dumping data for table `mission`
--

INSERT INTO `mission` (`mission_id`, `mission_id_user`, `mission_titre_mission`, `mission_description`, `mission_technologie`, `mission_profil`, `mission_niveau_etudes`, `mission_ville`, `mission_type_contrat`, `mission_date_up`) VALUES
(1, 15, 'Concepteur Développeur Java JEE', '  Capgemini Toulouse c''est plus de 1300 collaborateurs qui travaillent sur de multiples secteurs d''activités et sur les dernières technologies. Nous travaillons sur le secteur de l''aéronautique, fortement présent sur le bassin toulousain mais pas seulement, nous travaillons aussi avec le secteur pharmaceutique, le secteur des télécoms ou encore le secteur du service public.', NULL, NULL, NULL, '', NULL, '2019-06-14 14:16:57'),
(2, 15, 'Ingénieur Développeur JAVA', 'MISSIONSVous interviendrez dans le développement d''outils informatiques destinés aux industriels et aux scientifiques :Portage en Java d''applications existantes développées en C Création d''interfaces Web de gestion de codes de calculs scientifiques Outils de traitement et visualisation de données (datamining, 3D,)', NULL, NULL, NULL, '', NULL, '2019-06-14 14:18:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `mission`
--
ALTER TABLE `mission`
 ADD PRIMARY KEY (`mission_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `mission`
--
ALTER TABLE `mission`
MODIFY `mission_id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=3;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
