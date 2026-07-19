-- Base de données HR Consulting
-- À importer dans phpMyAdmin pour créer la base et les tables

CREATE DATABASE IF NOT EXISTS `hrconsulting` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `hrconsulting`;

-- --------------------------------------------------------
-- Table `users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_name` VARCHAR(50) NOT NULL,
  `user_email` VARCHAR(100) NOT NULL UNIQUE,
  `user_password` VARCHAR(255) NOT NULL,
  `user_jesuis` ENUM('freelance','recruteur') NOT NULL DEFAULT 'freelance',
  `user_telephone` VARCHAR(20) DEFAULT NULL,
  `user_ville` VARCHAR(50) DEFAULT NULL,
  `user_update` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_is_admin` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table `mission` (annonces)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `mission`;
CREATE TABLE `mission` (
  `mission_id` INT(11) NOT NULL AUTO_INCREMENT,
  `mission_id_user` INT(11) NOT NULL,
  `mission_titre_mission` VARCHAR(150) NOT NULL,
  `mission_description` TEXT NOT NULL,
  `mission_technologie` VARCHAR(255) DEFAULT NULL,
  `mission_profil` VARCHAR(100) DEFAULT NULL,
  `mission_niveau_etudes` VARCHAR(100) DEFAULT NULL,
  `mission_ville` VARCHAR(100) NOT NULL,
  `mission_type_contrat` VARCHAR(50) DEFAULT NULL,
  `mission_salaire` VARCHAR(50) DEFAULT NULL,
  `mission_date_up` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`mission_id`),
  KEY `idx_user` (`mission_id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------
-- Table `candidature` (postulations des freelances)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `candidature`;
CREATE TABLE `candidature` (
  `candidature_id` INT(11) NOT NULL AUTO_INCREMENT,
  `candidature_id_mission` INT(11) NOT NULL,
  `candidature_id_user` INT(11) NOT NULL,
  `candidature_message` TEXT NOT NULL,
  `candidature_statut` ENUM('en_attente','acceptee','refusee') NOT NULL DEFAULT 'en_attente',
  `candidature_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`candidature_id`),
  UNIQUE KEY `unique_candidature` (`candidature_id_mission`, `candidature_id_user`),
  KEY `idx_mission` (`candidature_id_mission`),
  KEY `idx_user` (`candidature_id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Base vide : les annonces seront créées par les recruteurs depuis le site.
