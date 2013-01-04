-- phpMyAdmin SQL Dump
-- version 3.5.4
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Dec 31, 2012 at 09:55 AM
-- Server version: 5.5.28
-- PHP Version: 5.4.10

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `phpsrc2_mb`
--

-- --------------------------------------------------------

--
-- Table structure for table `follower`
--

CREATE TABLE IF NOT EXISTS `follower` (
  `follower_id` int(10) unsigned NOT NULL,
  `followed_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `follower_UNIQUE` (`follower_id`,`followed_id`),
  KEY `FollowerID_idx` (`follower_id`),
  KEY `FollowedID_idx` (`followed_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `mark`
--

CREATE TABLE IF NOT EXISTS `mark` (
  `picture_id` int(10) unsigned NOT NULL,
  `author_id` int(10) unsigned NOT NULL,
  `mark` int(10) unsigned NOT NULL,
  `comment` varchar(255) DEFAULT NULL,
  `private_comment` tinyint(1) DEFAULT NULL,
  `date` datetime NOT NULL,
  UNIQUE KEY `usermark_UNIQUE` (`picture_id`,`author_id`),
  KEY `UserID_idx` (`author_id`),
  KEY `PictureID_idx` (`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `picture`
--

CREATE TABLE IF NOT EXISTS `picture` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `author_id` int(10) unsigned NOT NULL,
  `average_mark` int(10) unsigned NOT NULL DEFAULT '0',
  `num_of_marks` int(10) unsigned NOT NULL DEFAULT '0',
  `private` tinyint(1) NOT NULL,
  `date` datetime NOT NULL,
  `license` char(255) DEFAULT NULL,
  `type` int(10) unsigned NOT NULL COMMENT '0 = PNG, 1 = JPG',
  PRIMARY KEY (`ID`),
  KEY `UserID_idx` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `picture_tag`
--

CREATE TABLE IF NOT EXISTS `picture_tag` (
  `tag_id` int(10) unsigned NOT NULL,
  `picture_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `picturetag_UNIQUE` (`tag_id`,`picture_id`),
  KEY `TagID_idx` (`tag_id`),
  KEY `PictureID_idx` (`picture_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `tag`
--

CREATE TABLE IF NOT EXISTS `tag` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Title_UNIQUE` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE IF NOT EXISTS `user` (
  `ID` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(20) NOT NULL,
  `passwd` char(64) NOT NULL,
  `passwd_salt` char(64) NOT NULL,
  `email` varchar(30) NOT NULL,
  `private_pictures` tinyint(1) NOT NULL DEFAULT '0',
  `private_comments` tinyint(1) NOT NULL DEFAULT '0',
  `description` varchar(255) DEFAULT NULL,
  `type` tinyint(2) unsigned NOT NULL DEFAULT '0' COMMENT '0 = user, 1 = admin',
  `tmp_session_id` char(32) DEFAULT NULL,
  `date` datetime NOT NULL,
  `num_of_pictures` int(10) unsigned NOT NULL DEFAULT '0',
  `num_of_marks` int(10) unsigned NOT NULL DEFAULT '0',
  `num_of_followers` int(10) unsigned NOT NULL DEFAULT '0',
  `num_of_followed` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Username_UNIQUE` (`username`),
  UNIQUE KEY `Email_UNIQUE` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `user_tag`
--

CREATE TABLE IF NOT EXISTS `user_tag` (
  `tag_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  UNIQUE KEY `usertag_UNIQUE` (`tag_id`,`user_id`),
  KEY `tag_id_idx` (`tag_id`),
  KEY `user_id_idx` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `follower`
--
ALTER TABLE `follower`
  ADD CONSTRAINT `FollowedID` FOREIGN KEY (`followed_id`) REFERENCES `user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `FollowerID` FOREIGN KEY (`follower_id`) REFERENCES `user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `mark`
--
ALTER TABLE `mark`
  ADD CONSTRAINT `mark_ibfk_1` FOREIGN KEY (`picture_id`) REFERENCES `picture` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `mark_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `picture`
--
ALTER TABLE `picture`
  ADD CONSTRAINT `UserID` FOREIGN KEY (`author_id`) REFERENCES `user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `picture_tag`
--
ALTER TABLE `picture_tag`
  ADD CONSTRAINT `PictureID` FOREIGN KEY (`picture_id`) REFERENCES `picture` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `TagID` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints for table `user_tag`
--
ALTER TABLE `user_tag`
  ADD CONSTRAINT `tag_id` FOREIGN KEY (`tag_id`) REFERENCES `tag` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `user_id` FOREIGN KEY (`user_id`) REFERENCES `user` (`ID`) ON DELETE NO ACTION ON UPDATE NO ACTION;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
