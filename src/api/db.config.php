<?php
/**
 * ===============================================================
 *  数据库配置
 * ===============================================================
 *
 *  ★ 这份配置是给 CTFd 靶机容器（Dockerfile + service/docker-entrypoint.sh）
 *    用的：容器里 MySQL 由 docker-entrypoint.sh 启动，root 口令固定为 root，
 *    库名 wings。
 *
 *  ── 本地 PHPStudy 调试怎么办？ ──────────────────────────────
 *  ★ src/ 里的这份是**靶机镜像专用**的，正常不用动。
 *    本地调试请改 web3/api/db.config.php（那份带 setup.php 图形向导），
 *    或者用环境变量覆盖本文件的值：
 *      DB_HOST / DB_PORT / DB_USER / DB_PASS / DB_NAME
 */

return array(

    // 数据库地址。走 127.0.0.1（TCP），不要写 localhost
    'host' => '127.0.0.1',

    // 端口，MySQL 默认 3306
    'port' => '3306',

    // 容器内 MySQL 账号（entrypoint 里设置的固定值）
    'user' => 'root',

    // 容器内 MySQL 口令
    'pass' => 'root',

    // 数据库名。不存在的话，程序会自动创建
    'name' => 'wings',

    // 连接失败时是否自动尝试几个常见本地密码（空 / root / 123456 ...）
    // 容器里口令是确定的，关掉可以让"连不上"更快暴露成错误，而不是被兜底掩盖
    'auto_try_common_passwords' => false,
);
