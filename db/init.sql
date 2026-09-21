-- ===============================================================
--  Wings for Life · 建库脚本
--  ---------------------------------------------------------------
--  两条使用路径，二选一即可：
--
--   A) 什么都不做 —— 直接访问站点任意页面，api/config.php 会自己建库、
--      建表、预置 4 个账号。这是 PHPStudy 本地调试最省事的方式。
--
--   B) 手动导入本文件。适合想先把库建好、或者数据库账号没有建库权限的情况：
--        mysql -uroot -p < db/init.sql
--      或在 HeidiSQL（PHPStudy 自带）里打开本文件直接执行。
--
--  注意：本文件里的表结构必须和 api/config.php 的 wings_install() 保持一致，
--        否则 bootstrap 里那次「老库自动升级」会白跑一轮。
-- ===============================================================

CREATE DATABASE IF NOT EXISTS `wings` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
USE `wings`;

DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------------
--  users 表
--
--  `username` / `password` 用 utf8mb4_bin（按字节比较）：
--    目的是让 'BirdAdmin'、'birdadmin '（尾随空格）这类变体在
--    唯一索引这一层就被挡住，而不是依赖 PHP 侧的名字比较兜底。
--    这只影响「名字是否算重复」，不影响 payload 原样入库 ——
--    `x' or username='birdadmin'#` 这类用户名照常写得进去，
--    二次注入链路完全不受影响。
--
--  `is_admin`：
--    管理员身份用角色位表达，而不是「用户名是不是等于 birdadmin」。
--    Flag 出口（api/profile.php）就看这一列。
--
--  `salt`：
--    预留列，当前版本明文存口令（与 sqli-labs Less-24 的形态一致，
--    选手改完密码后回库一眼就能看出哪一行被改到了）。
-- ===============================================================
CREATE TABLE `users` (
  `id`       INT(11)      NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64)  COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `password` VARCHAR(128) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
  `is_admin` TINYINT(1)   NOT NULL DEFAULT 0,
  `salt`     VARCHAR(32)  NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

-- ---------------------------------------------------------------
--  预置 4 个账号
--
--  birdadmin 是站点管理员（is_admin=1），下面这串口令是固定的，方便：
--    · 本地用 birdadmin / W1ngs@dm1n_2f8c41d9e7b3a6 切成管理员视角看 Flag；
--    · 打完之后回库对比，确认注入确实改掉了管理员的密码。
--
--  如果这个实例会被多人/多轮反复使用，建议改成随机的：
--  给 PHP 设环境变量 WINGS_RANDOM_ADMIN=1，应用自己预置账号时会现掷一条
--  随机口令。注意它只在「首次预置」时生效 —— 手动导入本文件的话，
--  这里写的就是最终值。
--
--  普通志愿者口令固定，方便选手对照「干净账号改密要走当前密码校验」。
-- ---------------------------------------------------------------
INSERT INTO `users` (`username`, `password`, `is_admin`) VALUES
('birdadmin', 'W1ngs@dm1n_2f8c41d9e7b3a6', 1),
('linxiaoyu', 'bird2024',                    0),
('wangkai',   'wing@123',                    0),
('zhaoyun',   'nest2024',                    0);
