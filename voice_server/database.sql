DROP TABLE IF EXISTS `game_voice_servers`;
CREATE TABLE  `game_voice_servers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `port` int(5) unsigned NOT NULL,
  `query_port` int(5) unsigned DEFAULT NULL,
  `protocol` varchar(10) NOT NULL,
  `cache` longtext NOT NULL,
  `cache_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;