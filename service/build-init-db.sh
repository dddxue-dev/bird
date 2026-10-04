#!/bin/bash
# ===============================================================
#  构建期 MySQL 初始化（只在 `docker build` 时运行，不进最终镜像逻辑）
#  ---------------------------------------------------------------
#  做两件事：
#    1. 初始化数据目录，把 root 口令设成 root
#    2. 导入本项目的建库脚本（/var/db.sql）
#
#  单独拆成一个脚本，而不是塞进 Dockerfile 的 RUN 里：
#  构建期要把 mysqld 拉起来、等它就绪、执行 SQL 再停掉，
#  写成一行 `RUN` 时 `mysqld_safe &` 和后面的等号循环极易粘成一条命令，
#  失败起来非常难查。放成脚本可以用 `bash -n` 静态检查。
# ===============================================================
set -eu

wait_mysql() {
    local pass="$1"
    local i
    for i in $(seq 1 60); do
        if mysql -uroot -p"$pass" -e "SELECT 1" >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    echo "[build] !! MySQL 未能在 120 秒内就绪"
    return 1
}

echo "[build] 初始化 MySQL 数据目录 ..."
mysql_install_db --user=mysql --datadir=/var/lib/mysql >/dev/null

echo "[build] 启动 mysqld（构建期临时实例）..."
mysqld_safe >/dev/null 2>&1 &

# 首次启动时 root 还没有口令
if ! wait_mysql ""; then
    wait_mysql "root" || exit 1
fi

# 第一次进来：root 无口令 -> 设成 root
if mysql -uroot -e "SELECT 1" >/dev/null 2>&1; then
    echo "[build] 设置 root 口令 ..."
    mysqladmin -uroot password 'root'
fi

if ! wait_mysql "root"; then
    echo "[build] !! root 口令设置后连不上"
    exit 1
fi

echo "[build] 导入 /var/db.sql ..."
mysql -uroot -proot < /var/db.sql

echo "[build] 校验导入结果："
mysql -uroot -proot -N -B -e \
    "SELECT CONCAT('  users=', COUNT(*)) FROM wings.users;" || true
mysql -uroot -proot -N -B -e \
    "SELECT CONCAT('  admin=', username, ' is_admin=', is_admin)
       FROM wings.users WHERE is_admin=1;" || true

echo "[build] 停止构建期 mysqld ..."
mysqladmin -uroot -proot shutdown || true
sleep 2

echo "[build] 数据库初始化完成"
