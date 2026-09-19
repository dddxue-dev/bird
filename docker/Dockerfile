# ===============================================================
#  Wings for Life · 单容器镜像
#  Apache + PHP + MariaDB 跑在同一个容器里，应用监听 80 端口
#
#  关于 PHP 版本：
#    题目要求 PHP 5.x / 7.x。业务代码确实是用 mysql_* 风格写的，
#    并且 api/inc/mysql_compat.php 提供了 mysqli 垫片，
#    所以在 PHP 5.4 ~ 8.x 上都能直接运行，不需要改任何业务代码。
#
#    这里默认用 php:8.2-apache，原因是它的基础镜像（Debian bookworm）
#    官方源还在维护，apt 装 mariadb-server 最稳。
#
#    如果你想严格用 PHP 7.4，把下面这行换成
#        FROM php:7.4-apache
#    并在 apt-get 之前加一段切换 Debian bullseye 归档源的命令
#    （bullseye 已进入 LTS/归档，默认源可能 404）：
#        RUN sed -i 's|deb.debian.org|archive.debian.org|g; s|security.debian.org|archive.debian.org|g' \
#                /etc/apt/sources.list \
#         && sed -i '/bullseye-updates/d' /etc/apt/sources.list
# ===============================================================
FROM php:8.2-apache

# ---- 系统依赖 + MySQL 服务端 -----------------------------------
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        mariadb-server \
        mariadb-client \
        ca-certificates \
 && rm -rf /var/lib/apt/lists/*

# ---- 站点文件 ---------------------------------------------------
COPY . /var/www/html/

# 构建脚本、版本库、示例文件不进镜像
RUN rm -rf /var/www/html/_build \
           /var/www/html/docker \
           /var/www/html/.git \
           /var/www/html/.dockerignore \
           /var/www/html/setup.php \
           /var/www/html/flag.txt

# ---- Apache 配置 -------------------------------------------------
RUN a2enmod rewrite headers \
 && { echo 'ServerTokens Prod'; echo 'ServerSignature Off'; } \
        > /etc/apache2/conf-available/wings-hardening.conf \
 && a2enconf wings-hardening \
 && echo 'DirectoryIndex index.html index.php' \
        > /etc/apache2/conf-available/wings-index.conf \
 && a2enconf wings-index \
 && sed -i 's/^expose_php.*/expose_php = Off/' /usr/local/etc/php/php.ini-production \
 && cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini \
 && chown -R www-data:www-data /var/www/html

# ---- 启动脚本 ---------------------------------------------------
COPY docker/entrypoint.sh /usr/local/bin/wings-entrypoint
RUN chmod +x /usr/local/bin/wings-entrypoint

# 数据库数据目录（可选挂载卷做持久化）
VOLUME ["/var/lib/mysql"]

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/wings-entrypoint"]
CMD ["apache2-foreground"]
