/*
 * Plugin for connecting to voice servers (Teamspeak, Teamspeak 3 and Ventrilo) and parsing the data.
 *
 * PHP versions 4 and 5
 *
 * Voice Server Plugin (http://www.boku.co.uk)
 * Copyright 2011-2011, Boku LLP. (http://www.boku.co.uk)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2011-2011, Boku LLP. (http://www.boku.co.uk)
 * @link          http://www.boku.co.uk Voice Server Plugin
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
 
 /*
	Drop the table
 */
DROP TABLE IF EXISTS `game_voice_servers`;

/*
	Table definition
 */
CREATE TABLE  `game_voice_servers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `port` int(5) unsigned NOT NULL,
  `query_port` int(5) unsigned DEFAULT NULL,
  `protocol` varchar(10) NOT NULL,
  `cache` longtext NOT NULL,
  `cache_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;