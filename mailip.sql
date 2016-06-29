CREATE TABLE IF NOT EXISTS `mailip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `login` varchar(30) NOT NULL,
  `uid` char(32) NOT NULL,
  `passhash` char(32) NOT NULL,
  PRIMARY KEY (`id`)
) DEFAULT CHARSET=utf8;