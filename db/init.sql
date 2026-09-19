-- ===============================================================
--  Wings for Life · 数据库初始化脚本
-- ===============================================================
--  说明：
--    本文件是「手动导入」用的。程序在首次访问时也会自动建库建表并
--    预置账号（见 api/config.php 里的 wings_install / wings_seed），
--    所以正常情况下你不需要手动跑这个脚本。
--
--  用 PHPStudy 手动导入的方法：
--    面板 → 数据库 → 找到 / 新建数据库 wings → 导入 → 选择本文件
--
--  MySQL 5.x 适用
-- ===============================================================

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

-- ---------------------------------------------------------------
-- 预置 4 个账号
-- ---------------------------------------------------------------
--  birdadmin  站点管理员，密码为随机强口令，参赛者不可能猜到，
--             只能通过「二次注入」把它的密码改成自己知道的值。
--             出题人备忘：W1ngs@dm1n_2f8c41d9e7b3a6
--  其余三个为普通志愿者账号，密码较弱，用于让页面看起来真实。
-- ---------------------------------------------------------------
INSERT INTO `users` (`username`, `password`) VALUES
('birdadmin', 'W1ngs@dm1n_2f8c41d9e7b3a6'),
('linxiaoyu', 'bird2024'),
('wangkai',   'wing@123'),
('zhaoyun',   'nest2024');
