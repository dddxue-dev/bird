<?php
/**
 * ===============================================================
 *  后端公共引导文件（所有 API 入口都会先 require 它）
 * ===============================================================
 *  职责：
 *   1. 读取数据库配置（api/db.config.php，可被环境变量覆盖）
 *   2. 建立 mysql_* 连接
 *   3. 首次运行自动建库 / 建表 / 预置 4 个账号
 *   4. 读取 Flag（环境变量 FLAG 或项目根目录的 flag.txt）
 *   5. 提供 JSON 输入输出的小工具
 */

if (!defined('WINGS_API')) {
    define('WINGS_API', false);
}

// 老版本 PHP（<5.4）没有这个常量，补一个空值避免报错
if (!defined('JSON_UNESCAPED_UNICODE')) {
    define('JSON_UNESCAPED_UNICODE', 0);
}

// ---------------------------------------------------------------
// 0. 会话
// ---------------------------------------------------------------
if (function_exists('session_status')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
} elseif (!isset($_SESSION)) {
    session_start();
}

if (!function_exists('wings_env')) {

    /** 读环境变量（兼容 getenv / $_ENV / $_SERVER 三种来源） */
    function wings_env($key, $default = '')
    {
        $v = getenv($key);
        if ($v !== false && $v !== '') {
            return $v;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }

    /**
     * 读环境变量，但保留「空字符串」语义。
     * 用于数据库密码 —— PHPStudy 里 root 密码为空是很常见的情况，
     * 不能把空串当成「没配置」而回退到默认值。
     */
    function wings_env_raw($key, $default = null)
    {
        $v = getenv($key);
        if ($v !== false) {
            return $v;
        }
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return $default;
    }

    /** 输出 HTTP 状态码（兼容老 PHP） */
    function wings_status($code)
    {
        if (function_exists('http_response_code')) {
            http_response_code($code);
            return;
        }
        $texts = array(
            200 => 'OK', 400 => 'Bad Request', 401 => 'Unauthorized',
            403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed',
            409 => 'Conflict', 500 => 'Internal Server Error',
        );
        $t = isset($texts[$code]) ? $texts[$code] : 'OK';
        header('HTTP/1.1 ' . $code . ' ' . $t);
    }

    /** 致命错误：API 返回 JSON，页面返回可读的诊断 HTML */
    function wings_fatal($message, $detail = '', $hint = '')
    {
        if (WINGS_API) {
            wings_status(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array(
                'ok'      => false,
                'error'   => 'db_error',
                'message' => $message,
                'detail'  => $detail,
                'hint'    => $hint,
            ), JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="utf-8">'
           . '<title>数据库连接失败</title><style>'
           . 'body{font-family:"Microsoft YaHei",system-ui,sans-serif;background:#faf8f2;color:#1b2a22;'
           . 'display:flex;justify-content:center;padding:60px 20px;line-height:1.8}'
           . '.box{max-width:640px;background:#fff;border:1px solid #dfe5df;border-radius:14px;'
           . 'padding:34px 36px;box-shadow:0 8px 30px rgba(15,42,30,.08)}'
           . 'h1{margin:0 0 6px;font-size:22px;color:#14372a}'
           . 'code{background:#f2f0e9;padding:2px 7px;border-radius:5px;font-size:13px}'
           . 'pre{background:#0f2a1e;color:#d9e8dd;padding:14px 16px;border-radius:9px;'
           . 'overflow:auto;font-size:13px;line-height:1.7}'
           . 'ol{padding-left:22px}li{margin-bottom:8px}'
           . 'a{color:#2a6b4e;font-weight:600}'
           . '</style></head><body><div class="box">'
           . '<h1>数据库连接失败</h1>'
           . '<p>' . htmlspecialchars($message) . '</p>';

        if ($detail !== '') {
            echo '<pre>' . htmlspecialchars($detail) . '</pre>';
        }
        if ($hint !== '') {
            echo $hint;
        }

        echo '<p><a href="setup.php">→ 打开配置向导（测试并写入数据库密码）</a></p>'
           . '</div></body></html>';
        exit;
    }

    /**
     * 读取 Flag。
     * 优先级：环境变量 FLAG  ->  项目根目录的 flag.txt  ->  静态占位值
     *
     * ===============================================================
     *  ★ 静态占位 Flag 就在下面最后一行 ★
     * ===============================================================
     *  现在写死的是  flag{123455678}
     *
     *  本地 PHPStudy 调试时，页面显示的就是这个值，不用做任何配置。
     *
     *  想换成自己的 Flag，**两种方式都行，都不用改代码**：
     *    1) 在项目根目录放一个 flag.txt，里面写一行真 Flag
     *       （flag.txt 已在 .gitignore 里，不会被提交）；
     *    2) 或者给 PHP 设一个环境变量 FLAG。
     *  两者都没有时，才回退到下面这个占位值。
     */
    function wings_flag()
    {
        // 1) 环境变量
        $v = wings_env('FLAG', '');
        if ($v !== '') {
            return trim($v);
        }

        // 2) 项目根目录的 flag.txt
        $file = dirname(dirname(__FILE__)) . '/flag.txt';
        if (is_readable($file)) {
            $v = trim(file_get_contents($file));
            if ($v !== '') {
                return $v;
            }
        }

        // 3) 静态占位 Flag：本地调试显示的就是这个值
        return 'flag{123455678}';
    }

    // ----------------------- JSON 小工具 -----------------------

    /** 统一 JSON 输出并结束请求 */
    function wings_json($data, $code = 200)
    {
        if (!headers_sent()) {
            wings_status($code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    function wings_ok($extra = array())
    {
        $out = array('ok' => true);
        if (is_array($extra)) {
            $out = array_merge($out, $extra);
        }
        wings_json($out, 200);
    }

    function wings_fail($message, $error = 'error', $code = 400)
    {
        wings_json(array('ok' => false, 'error' => $error, 'message' => $message), $code);
    }

    /** 合并读取请求参数：同时支持表单 POST 与 JSON body */
    function wings_input()
    {
        static $data = null;
        if ($data !== null) {
            return $data;
        }
        $data = $_POST;
        $raw  = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $data = array_merge($data, $j);
            }
        }
        return $data;
    }

    function wings_req($key, $default = '')
    {
        $d = wings_input();
        return (isset($d[$key]) && is_scalar($d[$key])) ? (string) $d[$key] : $default;
    }

    // ----------------------- 建表 / 预置数据 -----------------------

    /**
     * 生成管理员随机口令。
     *
     * 为什么管理员口令最好别写死：
     *   这是一道二次注入题，正确解法是「把 birdadmin 的口令改成自己知道的值」。
     *   如果 birdadmin 的口令是固定的、而且这个实例被反复使用，
     *   那么先打通的人把口令改成某个值之后，后来的人直接用它登录就能拿到 Flag，
     *   题目就退化成「猜一个已知口令」。
     *
     *   需要「每个实例的口令都不一样」时，把环境变量 WINGS_RANDOM_ADMIN 设成 1，
     *   预置账号时就会现掷一条随机口令 —— 出题人不去读数据库也拿不到它，
     *   唯一能拿到 Flag 的路就只剩「把它改掉，再用新口令登录」。
     *
     * 兼容性：random_bytes() 要 PHP 7，openssl_random_pseudo_bytes() 要 PHP 5.3，
     *         两者都没有时退到 mt_rand()（本地极老的 PHP 才会走到这里）。
     */
    function wings_random_admin_password($len = 24)
    {
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789@#%+=?';
        $max      = strlen($alphabet) - 1;
        $out      = '';

        if (function_exists('random_bytes')) {
            try {
                $bytes = random_bytes($len);
            } catch (Exception $e) {
                $bytes = null;
            }
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($len);
        } else {
            $bytes = null;
        }

        if (is_string($bytes) && strlen($bytes) >= $len) {
            for ($i = 0; $i < $len; $i++) {
                $out .= $alphabet[ord($bytes[$i]) % ($max + 1)];
            }
            return $out;
        }

        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[mt_rand(0, $max)];
        }
        return $out;
    }

    /**
     * 取预置账号时要用的管理员口令。
     *
     * 默认是下面这个固定的字符串 —— 方便本地 PHPStudy 调试：
     * 你随时可以用 birdadmin / W1ngs@dm1n_2f8c41d9e7b3a6 登录看看管理员视角，
     * 也可以用它来确认「改密注入确实改掉了管理员的密码」。
     *
     * 如果这个实例会被多人/多轮反复使用，建议改成随机的：
     * 给 PHP 设环境变量 WINGS_RANDOM_ADMIN=1 即可，代码不用动。
     * 注意只在「首次预置账号」时生效 —— 库里已经有账号时不会再掷。
     */
    function wings_admin_seed_password()
    {
        $rand = wings_env('WINGS_RANDOM_ADMIN', '');
        if ($rand !== '' && $rand !== '0') {
            return wings_random_admin_password();
        }
        return 'W1ngs@dm1n_2f8c41d9e7b3a6';
    }

    /**
     * 建表 + 预置账号（仅在首次运行时执行）
     *
     * === 关于建表语句里的两个「防御性」设置（都不影响解题链路）===
     *
     *   1. `is_admin` 列 —— 权限判断不再依赖「用户名是不是等于 birdadmin」
     *      这个字符串比较，而是查数据库里的角色位。这样即便 MySQL 的非二进制
     *      排序规则把 'birdadmin ' / 'BirdAdmin' 当成相等的名字，也拿不到管理员
     *      身份（唯一索引 + 角色位是两层独立的防线）。
     *
     *   2. COLLATE utf8mb4_bin —— 用户名按「字节」比较，大小写/尾随空格变体
     *      会被唯一索引直接拦在注册这一步。
     *      注意：这不会挡住 payload，payload 里的 ' # 等字符照常原样入库，
     *      二次注入链路完全不受影响。
     */
    function wings_install()
    {
        $create = "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
            `password` VARCHAR(128) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
            `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
            `salt` VARCHAR(32) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin";

        if (mysql_query($create) === false) {
            return false;
        }
        return wings_seed();
    }

    /**
     * 老库升级：把早期版本的 users 表补成当前结构。
     *
     * 已经跑过旧版题目的机器（表里只有 id/username/password），
     * 直接换成新代码会报「Unknown column 'is_admin'」，所以这里做一次幂等升级。
     * 每一句都用「失败了也无所谓」的方式执行 —— 迁移不该把站点拖垮。
     */
    function wings_upgrade_schema()
    {
        // 1) 补 is_admin 列
        $fields = array();
        $res = mysql_query("SHOW COLUMNS FROM `users` LIKE 'is_admin'");
        if ($res !== false) {
            while ($r = mysql_fetch_row($res)) {
                $fields[] = $r[0];
            }
        }
        if (empty($fields)) {
            mysql_query("ALTER TABLE `users` ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0");
            mysql_query("UPDATE `users` SET `is_admin`=1 WHERE `username`='birdadmin'");
        }

        // 2) 补 salt 列（预留字段，保持与建表语句一致）
        $res = mysql_query("SHOW COLUMNS FROM `users` LIKE 'salt'");
        $has_salt = false;
        if ($res !== false) {
            while ($r = mysql_fetch_row($res)) {
                $has_salt = true;
            }
        }
        if (!$has_salt) {
            mysql_query("ALTER TABLE `users` ADD COLUMN `salt` VARCHAR(32) NOT NULL DEFAULT ''");
        }

        // 3) 用户名/密码列改成字节比较，挡掉大小写与尾随空格变体
        $res = mysql_query("SELECT COLLATION_NAME FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'
                              AND COLUMN_NAME='username' LIMIT 1");
        if ($res !== false) {
            $r = mysql_fetch_row($res);
            if ($r && isset($r[0]) && $r[0] !== '' && strtolower($r[0]) !== 'utf8mb4_bin') {
                mysql_query("ALTER TABLE `users`
                             MODIFY `username` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
                             MODIFY `password` VARCHAR(128) COLLATE utf8mb4_bin NOT NULL DEFAULT ''");
            }
        }
        return true;
    }

    /**
     * 预置 4 个账号。
     *
     *   birdadmin  —— 站点管理员，is_admin=1，口令随机（见 wings_admin_seed_password）
     *   linxiaoyu / wangkai / zhaoyun —— 普通志愿者，固定口令，方便选手对照
     *
     * 注意：这里必须用 INSERT IGNORE / 容错处理 —— 多队伍并发首次访问时，
     *       两个请求可能同时走到这一步，唯一索引会把晚到的那条顶掉，属正常情况。
     */
    function wings_seed()
    {
        wings_upgrade_schema();

        $res = mysql_query("SELECT COUNT(*) FROM `users`");
        $row = mysql_fetch_row($res);
        if ($row && (int) $row[0] > 0) {
            return true;
        }

        $admin_pass = wings_admin_seed_password();

        $seed = array(
            array('birdadmin', $admin_pass,     1),
            array('linxiaoyu', 'bird2024',      0),
            array('wangkai',   'wing@123',      0),
            array('zhaoyun',   'nest2024',      0),
        );
        foreach ($seed as $u) {
            $n = mysql_escape_string($u[0]);
            $p = mysql_escape_string($u[1]);
            $a = $u[2] ? 1 : 0;
            mysql_query("INSERT IGNORE INTO `users` (`username`, `password`, `is_admin`)
                         VALUES ('$n', '$p', $a)");
        }
        return true;
    }

    /**
     * 判断某个用户名在库里是不是管理员（按角色位，不做字符串比较）。
     *
     * 用 SELECT * 取列而不是写死索引位置 —— 以后加列也不会错位。
     * 返回：true / false。查不到这个用户、或者库还是旧结构没有 is_admin 列时返回 false。
     */
    function wings_is_admin_user($username)
    {
        $u = mysql_escape_string($username);
        $res = mysql_query("SELECT `is_admin` FROM `users` WHERE `username`='$u' LIMIT 1");
        if ($res === false) {
            return false;
        }
        $row = mysql_fetch_row($res);
        if (!$row) {
            return false;
        }
        return ((int) $row[0]) === 1;
    }
}

// ---------------------------------------------------------------
// 1. 数据库参数
// ---------------------------------------------------------------
$__dbcfg = array();
if (is_file(dirname(__FILE__) . '/db.config.php')) {
    $__tmp = include dirname(__FILE__) . '/db.config.php';
    if (is_array($__tmp)) {
        $__dbcfg = $__tmp;
    }
}

$db_host = wings_env_raw('DB_HOST', isset($__dbcfg['host']) ? $__dbcfg['host'] : '127.0.0.1');
$db_port = wings_env_raw('DB_PORT', isset($__dbcfg['port']) ? $__dbcfg['port'] : '3306');
$db_user = wings_env_raw('DB_USER', isset($__dbcfg['user']) ? $__dbcfg['user'] : 'root');
$db_pass = wings_env_raw('DB_PASS', isset($__dbcfg['pass']) ? $__dbcfg['pass'] : 'root');
$db_name = wings_env_raw('DB_NAME', isset($__dbcfg['name']) ? $__dbcfg['name'] : 'wings');
$db_auto = !empty($__dbcfg['auto_try_common_passwords']);

if ($db_pass === null) {
    $db_pass = '';
}

// ---------------------------------------------------------------
// 2. mysql_* 兼容层（PHP 7+ 已移除 ext/mysql）
// ---------------------------------------------------------------
if (!function_exists('mysql_connect')) {
    if (!class_exists('mysqli')) {
        wings_fatal(
            'PHP 环境缺少数据库扩展',
            '当前 PHP 版本：' . PHP_VERSION . '。它既没有 ext/mysql（只有 PHP 5.x 自带），也没有开启 mysqli 扩展。',
            '<p><strong>解决方法：</strong></p><ol>'
            . '<li>打开 PHPStudy 面板 → 软件管理 / 设置 → PHP 扩展</li>'
            . '<li>勾选 <code>mysqli</code>（建议同时勾选 <code>mysqlnd</code>）</li>'
            . '<li>重启 Apache / Nginx</li>'
            . '</ol>'
            . '<p>改完刷新本页即可。也可以打开 <a href="setup.php">setup.php</a> 查看当前扩展状态。</p>'
        );
    }
    require_once dirname(__FILE__) . '/inc/mysql_compat.php';
}

// ---------------------------------------------------------------
// 3. 建立连接
// ---------------------------------------------------------------
$conn = mysql_connect($db_host . ':' . $db_port, $db_user, $db_pass);

// 本地调试便利：配置里的密码不对时，尝试几个 PHPStudy 常见密码
// （仅当没有通过环境变量显式指定 DB_PASS 时才启用）
if (!$conn && $db_auto && wings_env_raw('DB_PASS', null) === null) {
    $common = array('', 'root', '123456', 'root123', 'password', 'mysql', 'admin', '12345678', '1234');
    foreach ($common as $try) {
        if ($try === $db_pass) {
            continue;
        }
        $conn = mysql_connect($db_host . ':' . $db_port, $db_user, $try);
        if ($conn) {
            $db_pass = $try;
            break;
        }
    }
}

if (!$conn) {
    $err = mysql_error();
    wings_fatal(
        '无法连接到 MySQL（账号：' . htmlspecialchars($db_user) . '，地址：' . htmlspecialchars($db_host . ':' . $db_port) . '）',
        $err,
        '<p><strong>常见原因：</strong></p><ol>'
        . '<li>PHPStudy 里 MySQL 服务没启动 → 打开面板点「启动」</li>'
        . '<li><code>api/db.config.php</code> 里的密码和你 PHPStudy 的 root 密码不一致 → 这是最常见的原因</li>'
        . '<li>端口不是 3306（被占用时 PHPStudy 会改成 3307 等）</li>'
        . '</ol>'
        . '<p>修改 <code>api/db.config.php</code> 里的 <code>\'pass\'</code> 为你 PHPStudy 面板「数据库」中显示的密码，'
        . '保存后刷新即可。也可以直接用下面的配置向导自动测试并写入。</p>'
    );
}

mysql_query('CREATE DATABASE IF NOT EXISTS `' . $db_name . '` DEFAULT CHARACTER SET utf8mb4');
if (!mysql_select_db($db_name, $conn)) {
    wings_fatal(
        '无法使用数据库 `' . htmlspecialchars($db_name) . '`',
        mysql_error(),
        '<p>请确认当前 MySQL 账号有创建 / 使用该数据库的权限。</p>'
    );
}

if (!mysql_query('SET NAMES utf8mb4')) {
    mysql_query('SET NAMES utf8');
}

// ---------------------------------------------------------------
// 4. 首次运行自动建表 + 预置账号（老库自动升级到当前结构）
// ---------------------------------------------------------------
$__t = mysql_query('SELECT COUNT(*) FROM `users`');
if ($__t === false) {
    // 表不存在（全新机器 / 用户删过库）→ 建表并预置账号
    wings_install();
} else {
    // 表在，但可能是旧版本建的 —— 先补齐 is_admin / salt 列与列排序规则。
    wings_upgrade_schema();

    $__r = mysql_fetch_row($__t);
    $__n = ($__r && isset($__r[0])) ? (int) $__r[0] : 0;

    if ($__n === 0) {
        // 情况一：表是空的（用户清过数据）
        wings_seed();
    } else {
        // 情况二：表里有数据，但一个管理员都没有 —— 大多是 db/init.sql 或
        // 老版本预置的数据（那时还没有 is_admin 列）。自动补一个管理员，
        // 避免出现「站点没有 birdadmin，题目直接无解」的情况。
        $__a = mysql_query("SELECT COUNT(*) FROM `users` WHERE `is_admin`=1 AND `username`='birdadmin'");
        $__ar = $__a !== false ? mysql_fetch_row($__a) : false;
        if (!$__ar || (int) $__ar[0] === 0) {
            $__ap = mysql_escape_string(wings_admin_seed_password());
            mysql_query("INSERT IGNORE INTO `users` (`username`, `password`, `is_admin`)
                         VALUES ('birdadmin', '$__ap', 1)");
            mysql_query("UPDATE `users` SET `is_admin`=1 WHERE `username`='birdadmin'");
        }
        unset($__a, $__ar, $__ap);
    }
}
unset($__t, $__r, $__n, $__tmp);
