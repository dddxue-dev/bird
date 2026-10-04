# ===============================================================
#  Wings for Life · 靶机镜像
#  ---------------------------------------------------------------
#  单容器：Apache + PHP 8.0 + MariaDB，应用监听 80 端口。
#  配套 CTFd + ctfd-whale 使用（见 https://gitee.com/xinggongji/min-da-ctfd）。
#
#  ★ 动态 Flag 机制
#  ---------------------------------------------------------------
#  平台在选手点「启动靶机」时生成随机 flag，用 `-e FLAG=xxx` 注入容器；
#  service/docker-entrypoint.sh 接住它并 export，Apache 子进程继承，
#  PHP 里 api/config.php 的 wings_flag() 用 getenv('FLAG') 读出。
#  代码里**没有任何写死的 flag**。
# ===============================================================
FROM php:8.0-apache

LABEL author="CTF-Archives" \
      description="Wings for Life - second-order SQL injection challenge"

# 换国内源，装 MariaDB 服务端/客户端
#
# ★ 必须删掉 bullseye-security 源（否则 apt 必定 exit 100）
# ---------------------------------------------------------------
# bullseye 已进入 LTS 末期，Debian 把 security 套件的 **包池整片撤走** 了
# （pool/updates/... 下的 .deb 全部消失），但 dists 索引还挂在原地。
# 后果：apt-get update 正常，apt 从索引里解析出候选版本（如 mariadb 10.5.29），
# 去下载时全部 404，报 "Unable to fetch some archives" 并 exit 100。
#
# 实测过的镜像，表现完全一致（所以「换个源」是治不好的）：
#   deb.debian.org / security.debian.org / USTC / 清华 / 阿里
# 连 archive.debian.org 兜底也是 404。试过 10.5.23~10.5.32 八个版本号，全无。
#
# 删掉这一行源之后，apt 回退到主仓库 bullseye/main，mariadb 装 10.5.23。
# 已逐个核对：本次构建需要的 13 个包（mariadb 系列 / galera-4 / libmariadb3 /
# libbpf0 / libcap2(-bin) / libdbi-perl / libconfig-inifiles-perl / rsync）
# 在 bullseye/main 里全部齐备，不会缺包。
# bullseye-updates 是空套件（0 个包），留着无害。
#
# 代价：MariaDB 由 10.5.29 降到 10.5.23（少若干个月的安全补丁）。
# 靶机跑在 frp 内网里、不直接对外，可以接受；本题 SQL 也不依赖版本特性。
RUN sed -i 's/deb.debian.org/mirrors.ustc.edu.cn/g' /etc/apt/sources.list \
 && sed -i '/debian-security/d' /etc/apt/sources.list \
 && apt-get update \
 && apt-get install -y --no-install-recommends default-mysql-server default-mysql-client \
 && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------------
#  PHP 扩展
# -----------------------------------------------------------------
# 业务代码用的是 mysql_* 风格，PHP 8.0 已经没有 ext/mysql 了，
# 由 api/inc/mysql_compat.php 用 mysqli 实现同名函数，所以必须有 mysqli。
# pdo_mysql 属于常见配套，一起装上。
RUN docker-php-ext-install mysqli pdo_mysql

# -----------------------------------------------------------------
#  PHP 运行时配置
# -----------------------------------------------------------------
# variables_order 默认是 "GPCS"（不含 E），那样 $_ENV 是空的，
# 只能靠 getenv() 读环境变量。这里补上 E，让 $_ENV['FLAG'] 和
# getenv('FLAG') 两种写法都能拿到动态 flag，避免踩这个坑。
# 同时关掉错误回显，防止报错信息把源码路径泄给选手。
RUN { \
      echo 'variables_order = "EGPCS"'; \
      echo 'expose_php = Off'; \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
    } > /usr/local/etc/php/conf.d/wings-runtime.ini

# 顺带把 Apache 的版本与目录列表信息关掉
RUN printf 'ServerTokens Prod\nServerSignature Off\n' \
      > /etc/apache2/conf-available/wings-hardening.conf \
 && a2enconf wings-hardening

# -----------------------------------------------------------------
#  数据库初始化（构建期烤进镜像）
# -----------------------------------------------------------------
# 容器每次实例化都会拿到镜像层的独立可写副本，
# 所以这里"烤"进去的数据，每个靶机实例都是干净的初始状态。
COPY ./src/db.sql /var/db.sql
COPY ./service/build-init-db.sh /usr/local/bin/build-init-db
RUN chmod +x /usr/local/bin/build-init-db \
 && /usr/local/bin/build-init-db

# -----------------------------------------------------------------
#  站点源码
# -----------------------------------------------------------------
# 放在最容易变的一层：改页面不用重跑数据库初始化。
COPY ./src /var/www/html

# 构建期就把出题人用的配置向导拿掉（它能改数据库配置，不该留在靶机里）
RUN rm -f /var/www/html/setup.php /var/www/html/db.sql \
 && chown -R www-data:www-data /var/www/html

# -----------------------------------------------------------------
#  入口点
# -----------------------------------------------------------------
COPY ./service/docker-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80

# MariaDB 数据目录。不挂卷时容器内即用即弃，
# 每个实例启动都是干净的初始状态（这也是我们想要的）。
VOLUME ["/var/lib/mysql"]

ENTRYPOINT ["/docker-entrypoint.sh"]
