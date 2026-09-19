<?php
/**
 * mysql_* 兼容层
 * ---------------------------------------------------------------
 * PHP 7.0 起官方移除了 ext/mysql（mysql_connect / mysql_query ... 系列函数）。
 * 题目业务代码按要求使用 mysql_* 风格，为了让它在 PHP 5.x / 7.x / 8.x 上
 * 都能直接跑起来（PHPStudy 里随便切版本、Docker 里换镜像都不用改代码），
 * 这里用 mysqli 实现一份最小等价物。PHP 5.x 原生带 ext/mysql 时本文件不会被加载。
 *
 * 三个必须处理的兼容点：
 *   1. PHP 7+ 没有 mysqli 扩展时不能直接抛致命错误，要给出可读提示；
 *   2. PHP 8.1 起 mysqli 默认 MYSQLI_REPORT_ERROR|STRICT，失败会「抛异常」
 *      而不是返回 false，会把我们靠返回值判断错误的逻辑全部打乱；
 *   3. PHP 8.1 起 $GLOBALS 写入受限，连接状态改用静态类属性保存。
 *
 * 注意：这只是 API 垫片，不改变任何 SQL 拼接 / 转义行为，
 *       题目本身的漏洞（二次注入）完全保留。
 */

if (function_exists('mysql_connect')) {
    return; // PHP 5.x 原生支持，直接使用
}

/**
 * 连接状态容器。
 * 用静态类属性而不是 $GLOBALS —— PHP 8.1 起 $GLOBALS 的写入已被限制。
 */
class Wings_Mysql_State
{
    /** @var mysqli|null 当前连接 */
    public static $link = null;

    /** @var string 最近一次错误信息 */
    public static $error = '';

    /** @var bool mysqli_report 是否已经关掉 */
    public static $report_off = false;
}

/** 关掉 mysqli 的异常模式，恢复「失败返回 false」的传统行为 */
function _wings_mysqli_quiet()
{
    if (!Wings_Mysql_State::$report_off && function_exists('mysqli_report')) {
        // PHP 5.3+ / 7.x / 8.x 均可用；8.1+ 默认是 REPORT_ERROR|REPORT_STRICT，必须关掉
        @mysqli_report(MYSQLI_REPORT_OFF);
        Wings_Mysql_State::$report_off = true;
    }
}

function _wings_set_error($msg)
{
    Wings_Mysql_State::$error = $msg;
}

/** 取当前连接（或指定连接） */
function _wings_link($link = null)
{
    if ($link instanceof mysqli) {
        return $link;
    }
    return Wings_Mysql_State::$link;
}

/*
 * PHP 7+ 下必须有 mysqli 才能实现这份垫片。
 * 如果 mysqli 也没开，就提供一组「会失败但不会致命报错」的桩函数，
 * 让上层能把「扩展没开」这个信息友好地告诉使用者。
 */
if (!class_exists('mysqli')) {

    function _wings_no_ext_error()
    {
        return '当前 PHP 既没有 ext/mysql（仅 PHP 5.x 自带），也没有开启 mysqli 扩展，无法连接数据库。'
             . '请到 PHPStudy → PHP 扩展设置里勾选 mysqli，然后重启 Apache。';
    }

    function mysql_connect($host = null, $user = null, $pass = null, $new_link = false, $client_flags = 0)
    {
        _wings_set_error(_wings_no_ext_error());
        return false;
    }

    function mysql_pconnect($host = null, $user = null, $pass = null, $client_flags = 0)
    {
        return mysql_connect();
    }

    function mysql_select_db($db, $link = null) { return false; }
    function mysql_query($sql, $link = null) { return false; }
    function mysql_fetch_row($res) { return false; }
    function mysql_fetch_array($res, $type = null) { return false; }
    function mysql_fetch_assoc($res) { return false; }
    function mysql_num_rows($res) { return 0; }
    function mysql_num_fields($res) { return 0; }
    function mysql_free_result($res) { return true; }
    function mysql_affected_rows($link = null) { return -1; }
    function mysql_insert_id($link = null) { return 0; }
    function mysql_errno($link = null) { return 0; }
    function mysql_close($link = null) { return true; }

    function mysql_error($link = null)
    {
        return Wings_Mysql_State::$error;
    }

    function mysql_escape_string($string)
    {
        return str_replace(
            array("\\", "\0", "\n", "\r", "'", '"', "\x1a"),
            array("\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"),
            $string
        );
    }

    function mysql_real_escape_string($string, $link = null)
    {
        return mysql_escape_string($string);
    }

    return;
}

// ---------------------------------------------------------------
// 有 mysqli，正式实现
// ---------------------------------------------------------------

function mysql_connect($host = null, $user = null, $pass = null, $new_link = false, $client_flags = 0)
{
    if ($host === null) {
        return _wings_link();
    }

    _wings_mysqli_quiet();

    $port = 3306;
    if (strpos($host, ':') !== false) {
        $parts = explode(':', $host, 2);
        $host  = $parts[0];
        $port  = (int) $parts[1];
    }

    try {
        $link = @mysqli_connect($host, $user, $pass, null, $port);
    } catch (Exception $e) {
        // 兜底：万一 mysqli_report 没关干净，也不能让异常冒出去
        _wings_set_error($e->getMessage());
        return false;
    }

    if (!$link) {
        _wings_set_error(mysqli_connect_error());
        return false;
    }

    Wings_Mysql_State::$link = $link;
    _wings_set_error('');
    return $link;
}

function mysql_pconnect($host = null, $user = null, $pass = null, $client_flags = 0)
{
    return call_user_func_array('mysql_connect', func_get_args());
}

function mysql_select_db($db, $link = null)
{
    $l = _wings_link($link);
    if (!$l) {
        _wings_set_error('no connection');
        return false;
    }
    _wings_mysqli_quiet();
    return mysqli_select_db($l, $db);
}

function mysql_query($sql, $link = null)
{
    $l = _wings_link($link);
    if (!$l) {
        _wings_set_error('no connection');
        return false;
    }

    _wings_mysqli_quiet();

    try {
        $res = @mysqli_query($l, $sql);
    } catch (Exception $e) {
        _wings_set_error($e->getMessage());
        return false;
    }

    if ($res === false) {
        _wings_set_error(mysqli_error($l));
    } else {
        _wings_set_error('');
    }
    return $res;
}

function mysql_fetch_row($res)
{
    if (!($res instanceof mysqli_result)) {
        return false;
    }
    $row = mysqli_fetch_row($res);
    return $row === null ? false : $row;
}

function mysql_fetch_array($res, $type = MYSQLI_BOTH)
{
    if (!($res instanceof mysqli_result)) {
        return false;
    }
    $row = mysqli_fetch_array($res, $type);
    return $row === null ? false : $row;
}

function mysql_fetch_assoc($res)
{
    if (!($res instanceof mysqli_result)) {
        return false;
    }
    $row = mysqli_fetch_assoc($res);
    return $row === null ? false : $row;
}

function mysql_num_rows($res)
{
    return ($res instanceof mysqli_result) ? mysqli_num_rows($res) : 0;
}

function mysql_num_fields($res)
{
    return ($res instanceof mysqli_result) ? mysqli_num_fields($res) : 0;
}

function mysql_free_result($res)
{
    if ($res instanceof mysqli_result) {
        mysqli_free_result($res);
    }
    return true;
}

function mysql_affected_rows($link = null)
{
    $l = _wings_link($link);
    return $l ? mysqli_affected_rows($l) : -1;
}

function mysql_insert_id($link = null)
{
    $l = _wings_link($link);
    return $l ? mysqli_insert_id($l) : 0;
}

function mysql_error($link = null)
{
    $l = _wings_link($link);
    if ($l) {
        $e = mysqli_error($l);
        if ($e !== '') {
            return $e;
        }
    }
    return Wings_Mysql_State::$error;
}

function mysql_errno($link = null)
{
    $l = _wings_link($link);
    return $l ? mysqli_errno($l) : 0;
}

function mysql_close($link = null)
{
    $l = _wings_link($link);
    if ($l) {
        mysqli_close($l);
        Wings_Mysql_State::$link = null;
    }
    return true;
}

/** 与 ext/mysql 的 mysql_real_escape_string 行为对齐 */
function mysql_real_escape_string($string, $link = null)
{
    $l = _wings_link($link);
    if ($l) {
        return mysqli_real_escape_string($l, $string);
    }
    return mysql_escape_string($string);
}

/** 与 ext/mysql 的 mysql_escape_string 行为对齐（不做字符集校验） */
function mysql_escape_string($string)
{
    $l = _wings_link();
    if ($l) {
        return mysqli_real_escape_string($l, $string);
    }
    return str_replace(
        array("\\", "\0", "\n", "\r", "'", '"', "\x1a"),
        array("\\\\", "\\0", "\\n", "\\r", "\\'", '\\"', "\\Z"),
        $string
    );
}
