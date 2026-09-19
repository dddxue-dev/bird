#!/bin/bash
# ===============================================================
#  Wings for Life · 容器启动脚本
#   1. 初始化并启动容器内的 MariaDB
#   2. 建库 / 建表 / 预置账号
#   3. 把 FLAG 环境变量同时落到 /flag 文件（两种注入方式都支持）
#   4. 启动 Apache
# ===============================================================
set -e

DB_NAME="${DB_NAME:-wings}"
DB_USER="${DB_USER:-wings}"
DB_PASS="${DB_PASS:-wingspass}"

echo "[wings] 初始化 MariaDB ..."
mkdir -p /var/run/mysqld /var/lib/mysql
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql

if [ ! -d /var/lib/mysql/mysql ]; then
    echo "[wings] 首次运行，初始化数据目录 ..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1 \
        || mariadb-install-db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1
fi

echo "[wings] 启动 mysqld ..."
mysqld_safe --skip-grant-tables=0 > /var/log/mysqld.log 2>&1 &

for i in $(seq 1 60); do
    if mysqladmin ping --silent > /dev/null 2>&1; then
        break
    fi
    sleep 1
done

if ! mysqladmin ping --silent > /dev/null 2>&1; then
    echo "[wings] mysqld 启动失败，日志："
    tail -n 40 /var/log/mysqld.log || true
    exit 1
fi
echo "[wings] mysqld 已就绪"

# ---- 建库 / 建账号 --------------------------------------------
mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

# ---- 导入初始数据 ----------------------------------------------
mysql -uroot "${DB_NAME}" < /var/www/html/db/init.sql
echo "[wings] 数据库初始化完成"

# ---- Flag 注入 -------------------------------------------------
# 支持两种方式：
#   a) 环境变量（CTFd Whale / GZCTF 默认方式）
#   b) 挂载 /flag 文件
if [ -n "${FLAG:-}" ]; then
    printf '%s' "${FLAG}" > /flag
    chmod 644 /flag
    echo "[wings] FLAG 已写入 /flag（同时保留环境变量）"
elif [ -f /flag ]; then
    echo "[wings] 检测到挂载的 /flag 文件"
else
    echo "[wings] 警告：未检测到 FLAG 环境变量或 /flag 文件，将使用本地调试占位值"
fi

# ---- 传给 PHP 的数据库连接信息 ---------------------------------
cat > /etc/apache2/conf-available/wings-env.conf <<EOF
SetEnv DB_HOST 127.0.0.1
SetEnv DB_PORT 3306
SetEnv DB_USER ${DB_USER}
SetEnv DB_PASS ${DB_PASS}
SetEnv DB_NAME ${DB_NAME}
EOF
a2enconf wings-env > /dev/null 2>&1

echo "[wings] 启动 Apache ..."
exec "$@"
