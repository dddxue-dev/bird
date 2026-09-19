<?php
/**
 * ===============================================================
 *  后端公共引导文件（所有 API 入口都会先 require 它）
 * ===============================================================
 *  职责：
 *   1. 读取数据库配置（api/db.config.php，可被环境变量覆盖）
 *   2. 建立 mysql_* 连接
 *   3. 首次运行自动建库 / 建表 / 预置 4 个账号
 *   4. 读取 Flag（环境变量 或 挂载文件）
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
     * 优先级：环境变量  ->  挂载文件  ->  本地调试兜底
     * 环境变量名兼容 CTFd Whale / GZCTF 常见约定。
     */
    function wings_flag()
    {
        foreach (array('FLAG', 'GZCTF_FLAG', 'CTF_FLAG', 'DASFLAG') as $k) {
            $v = wings_env($k, '');
            if ($v !== '') {
                return trim($v);
            }
        }
        $files = array(
            '/flag',
            '/flag.txt',
            '/tmp/flag',
            '/tmp/flag.txt',
            dirname(dirname(__FILE__)) . '/flag.txt',
        );
        foreach ($files as $f) {
            if (is_readable($f)) {
                $v = trim(file_get_contents($f));
                if ($v !== '') {
                    return $v;
                }
            }
        }
        return 'flag{local_debug_no_flag_injected}';
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

    /** 建表 + 预置账号（仅在首次运行时执行） */
    function wings_install()
    {
        $create = "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(64) NOT NULL DEFAULT '',
            `password` VARCHAR(128) NOT NULL DEFAULT '',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (mysql_query($create) === false) {
            return false;
        }
        return wings_seed();
    }

    /** 预置 4 个账号（birdadmin 的密码为随机强口令，仅出题人可知） */
    function wings_seed()
    {
        $res = mysql_query("SELECT COUNT(*) FROM `users`");
        $row = mysql_fetch_row($res);
        if ($row && (int) $row[0] > 0) {
            return true;
        }

        $seed = array(
            array('birdadmin', 'W1ngs@dm1n_2f8c41d9e7b3a6'),
            array('linxiaoyu', 'bird2024'),
            array('wangkai',   'wing@123'),
            array('zhaoyun',   'nest2024'),
        );
        foreach ($seed as $u) {
            $n = mysql_escape_string($u[0]);
            $p = mysql_escape_string($u[1]);
            mysql_query("INSERT INTO `users` (`username`, `password`) VALUES ('$n', '$p')");
        }
        return true;
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
// 4. 首次运行自动建表 + 预置账号
// ---------------------------------------------------------------
$__t = mysql_query('SELECT COUNT(*) FROM `users`');
if ($__t === false) {
    wings_install();
} else {
    $__r = mysql_fetch_row($__t);
    if ($__r && (int) $__r[0] === 0) {
        wings_seed();
    }
}
unset($__t, $__r, $__tmp);
