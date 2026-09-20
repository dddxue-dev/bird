
CREATE DATABASE IF NOT EXISTS `wings` DEFAULT CHARACTER SET utf8mb4;
USE `wings`;

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64)  NOT NULL DEFAULT '',
  `password` VARCHAR(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


INSERT INTO `users` (`username`, `password`) VALUES
('birdadmin', 'W1ngs@dm1n_2f8c41d9e7b3a6'),
('linxiaoyu', 'bird2024'),
('wangkai',   'wing@123'),
('zhaoyun',   'nest2024');
