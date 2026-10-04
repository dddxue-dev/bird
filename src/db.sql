-- ===============================================================
--  Wings for Life · 数据库初始化
--  ---------------------------------------------------------------
--  在镜像构建阶段执行（见 Dockerfile）：
--      mysql -uroot -proot < /var/db.sql
--
--  只负责「建库 + 建表 + 预置账号」。
--
--  表结构必须和 api/config.php 的 wings_install() 保持一致，
--  否则应用启动时那次「老库自动升级」会白跑一轮。
-- ===============================================================

CREATE DATABASE IF NOT EXISTS `wings` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin;
USE `wings`;

DROP TABLE IF EXISTS `users`;

-- ---------------------------------------------------------------
--  users 表
--
--  `username` / `password` 用 utf8mb4_bin（按字节比较，大小写敏感）。
--  `is_admin` 管理员角色位，权限判断只看这一列。
--  `salt`     预留列，当前版本明文存口令。
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
--  ⚠ 管理员口令在容器启动时会被 docker-entrypoint.sh 重新掷成随机的，
--    所以下面这串只是「构建期的占位值」，每个实例都不一样。
-- ---------------------------------------------------------------
INSERT INTO `users` (`username`, `password`, `is_admin`) VALUES
('birdadmin', 'W1ngs@dm1n_2f8c41d9e7b3a6', 1),
('linxiaoyu', 'bird2024',                    0),
('wangkai',   'wing@123',                    0),
('zhaoyun',   'nest2024',                    0);
