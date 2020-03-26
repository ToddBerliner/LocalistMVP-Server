<?php
namespace App\Shell;

use Cake\Console\Shell;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;

class LocalistShell extends Shell {

    public function main() {
        $seedSql = <<<ENDQUERY
CREATE TABLE IF NOT EXISTS `localist_alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `localist_id` int(10) unsigned NOT NULL,
  `action` int(10) unsigned NOT NULL,
  `meta` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_alert_list_idx` (`localist_id`),
  CONSTRAINT `localist_alerts_localist_fk` FOREIGN KEY (`localist_id`) REFERENCES `localists` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `localists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `updated` int(11) unsigned NOT NULL,
  `json` text NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `updated_idx` (`updated`)
) ENGINE=InnoDB AUTO_INCREMENT=110 DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_localist_alerts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `localist_id` int(10) unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  `action` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_lcoalist_alerts_user_idx` (`user_id`),
  KEY `user_lcoalist_alerts_localist_idx` (`localist_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_localists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `localist_id` int(10) unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_localists_user_fk` (`user_id`),
  KEY `user_localists_localist_fk` (`localist_id`),
  CONSTRAINT `user_localists_localist_fk` FOREIGN KEY (`localist_id`) REFERENCES `localists` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_localists_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_localists` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_syncs_user_idx` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `user_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `other_user_id` int(10) unsigned NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_users_user_fk` (`user_id`),
  KEY `user_users_other_user_fk` (`other_user_id`),
  CONSTRAINT `user_users_other_user_fk` FOREIGN KEY (`other_user_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `user_users_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `udid` varchar(45) DEFAULT NULL,
  `apns` varchar(255) DEFAULT NULL,
  `name` varchar(45) NOT NULL,
  `phone` varchar(45) NOT NULL,
  `first_name` varchar(45) DEFAULT NULL,
  `image_name` varchar(1024) DEFAULT 'Contact',
  `chat_user` varchar(45) DEFAULT NULL,
  `chat_login` varchar(45) DEFAULT NULL,
  `chat_password` varchar(63) DEFAULT NULL,
  `chat_registered` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `users_phone_idx` (`phone`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4;
ENDQUERY;

        $db = ConnectionManager::get('default');
        var_dump($db->execute($seedSql));
    }
}
