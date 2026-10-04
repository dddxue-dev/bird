#!/bin/bash
# ===============================================================
#  Wings for Life · 容器入口点
#  ---------------------------------------------------------------
#  做的事：
#    1. 启动容器内的 MariaDB，等它真正可用
#    2. 从环境变量取出「平台生成的动态 Flag」，并规范化成 FLAG
#    3. 校验数据库/表/管理员账号都在（不在就补上）
#    4. 把管理员口令重新掷成随机值（防止跨实例复用）
#    5. 启动 Apache（Apache 子进程会继承上面设置的 FLAG 环境变量）
#
#  ★ 动态 Flag 的传递方式
#  ---------------------------------------------------------------
#  CTFd Whale（本仓库用的就是这套）会在选手点「启动靶机」时自动生成一个
#  随机 flag，然后用 `-e FLAG=xxxx` 注入容器。所以：
#    平台  --(docker -e FLAG=xxx)-->  容器进程环境
#                                     |
#                                     `--> Apache --(继承)--> PHP
#                                                                 |
#                                          api/config.php 里 getenv('FLAG')
#
#  这里**不把 flag 写进数据库** —— 写库唯一的好处是"能被注入读出来"，
#  但本题的注入点是 UPDATE，读不回来；而且写库还要处理 SQL 转义。
#  直接读环境变量最干净，也是插件 README 推荐的用法。
# ===============================================================
set -u

echo "[wings] 启动 MariaDB ..."
mysqld_safe &

# --- 等 MySQL 真正可连接（-e 走一次性连接，成功即就绪） -------------
MYSQL_READY=0
for i in $(seq 1 60); do
    if mysql -uroot -proot -e "SELECT 1" >/dev/null 2>&1; then
        MYSQL_READY=1
        break
    fi
    echo "[wings] 等待 MySQL 就绪 ... ($i/60)"
    sleep 2
done

if [ "$MYSQL_READY" != "1" ]; then
    echo "[wings] !! MySQL 未能就绪，下面打印错误日志尾部"
    tail -n 40 /var/log/mysql/error.log 2>/dev/null || true
    tail -n 40 /var/log/mysqld.log 2>/dev/null || true
    exit 1
fi
echo "[wings] MySQL 已就绪"

# -----------------------------------------------------------------
#  1. 取动态 Flag
# -----------------------------------------------------------------
# 兼容三个平台的注入名（CTFd Whale / DASCTF / GZCTF），统一成 FLAG 往下传。
if [ -n "${FLAG:-}" ]; then
    INJECT_FLAG="$FLAG"
elif [ -n "${DASFLAG:-}" ]; then
    INJECT_FLAG="$DASFLAG"
elif [ -n "${GZCTF_FLAG:-}" ]; then
    INJECT_FLAG="$GZCTF_FLAG"
else
    # 本地 docker-compose 调试时没注入 flag 会走到这里。
    # 给一个明显的占位值，而不是让页面显示空白 —— 方便一眼看出"没注入成功"。
    INJECT_FLAG="flag{NO_FLAG_INJECTED_check_your_platform}"
    echo "[wings] !! 警告：环境变量里没有 FLAG/DASFLAG/GZCTF_FLAG，使用占位值"
fi

# 覆盖掉环境里所有的可能来源，确保 Apache/PHP 只看到规范化后的 FLAG
export FLAG="$INJECT_FLAG"
unset DASFLAG GZCTF_FLAG 2>/dev/null || true

# 不打印 flag 明文，只打印长度和前缀，方便部署时确认"确实注入进来了"
echo "[wings] 已注入动态 Flag：长度 ${#FLAG}，形如 ${FLAG:0:5}..."

# -----------------------------------------------------------------
#  2. 校验数据库 / 表 / 管理员账号
# -----------------------------------------------------------------
# db.sql 已经在构建期导入过，正常情况下这里什么都不用做。
# 但卷挂载、换库、手动清表等情况都可能让数据缺失，检测到就补上，
# 避免出现"站点能开但题目无解"。
DB_STATE=$(mysql -uroot -proot -N -B -e \
    "SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA='wings' AND TABLE_NAME='users';" 2>/dev/null)

if [ "$DB_STATE" != "1" ]; then
    echo "[wings] users 表不存在，导入 /var/db.sql ..."
    mysql -uroot -proot < /var/db.sql
fi

# 管理员账号必须存在，否则题目无解
ADMIN_CNT=$(mysql -uroot -proot -N -B -e \
    "SELECT COUNT(*) FROM wings.users WHERE username='birdadmin';" 2>/dev/null)
if [ "${ADMIN_CNT:-0}" = "0" ]; then
    echo "[wings] 缺少 birdadmin，补一个 ..."
    mysql -uroot -proot -e \
        "INSERT IGNORE INTO wings.users (username,password,is_admin)
         VALUES ('birdadmin','W1ngs@dm1n_2f8c41d9e7b3a6',1);"
fi

# -----------------------------------------------------------------
#  3. 管理员口令随机化（每个靶机实例都不同）
# -----------------------------------------------------------------
# 为什么必须随机：
#   本题正确解是「把 birdadmin 的口令改成自己知道的值」。如果所有实例的
#   初始口令都相同，先打通的人把口令改成某个值之后，后来的人直接拿去登录
#   就能拿 Flag，题目就废了。flag 已经每实例随机，初始口令也应当如此。
#
# 字符集只取字母数字：这条口令要拼进 SQL 字面量，
# 混入引号/反斜杠会破坏语句。24 位大小写字母+数字（约 143 bit）足够。
RANDOM_ADMIN_PASS=$(head -c 64 /dev/urandom | LC_ALL=C tr -dc 'A-Za-z0-9' | head -c 24)
if [ -n "${RANDOM_ADMIN_PASS}" ]; then
    mysql -uroot -proot -e \
        "UPDATE wings.users SET password='${RANDOM_ADMIN_PASS}', is_admin=1
          WHERE username='birdadmin';" >/dev/null 2>&1 \
        && echo "[wings] birdadmin 初始口令已随机化（仅本实例有效，不回显）"
else
    echo "[wings] !! 警告：随机口令生成失败，birdadmin 沿用构建期口令"
fi

# `-e` 会在命令行里暴露口令，虽然只是容器内部的瞬时进程，还是多清一手
unset RANDOM_ADMIN_PASS

# -----------------------------------------------------------------
#  4. 启动 Apache
# -----------------------------------------------------------------
# 注意：FLAG 是通过 export 进进程环境，Apache 的子进程会继承，
# PHP 里用 getenv('FLAG') 就能读到（$_ENV 需要 variables_order 含 E，
# 镜像里已经配成 EGPCS，两种写法都能用）。
source /etc/apache2/envvars 2>/dev/null || true

echo "[wings] 启动 Apache ..."
exec apache2 -D FOREGROUND
