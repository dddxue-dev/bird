<?php
/**
 * 注册接口  POST api/register.php
 * ---------------------------------------------------------------
 * 参数：username / password / re_password
 * 返回：{"ok":true,"username":"..."}
 *
 * 安全说明：
 *   这里对输入做了 mysql_escape_string()，INSERT 语句本身是安全的。
 *   但用户名是「原样落库」的 —— 转义只保证本次 INSERT 不被破坏，
 *   并不会改变存进数据库的字符串内容。这正是二次注入的起点。
 */
define('WINGS_API', true);
require_once dirname(__FILE__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wings_fail('请使用 POST 请求', 'method_not_allowed', 405);
}

$raw_username = wings_req('username');
$raw_password = wings_req('password');
$raw_repass   = wings_req('re_password');

$username = mysql_escape_string($raw_username);
$pass     = mysql_escape_string($raw_password);
$re_pass  = mysql_escape_string($raw_repass);

if ($username === '' || $pass === '') {
    wings_fail('用户名和密码都不能为空', 'empty', 400);
}

if ($pass !== $re_pass) {
    wings_fail('两次输入的密码不一致', 'mismatch', 400);
}

// 查重（已转义，注入不进来）
$sql = "SELECT count(*) FROM `users` WHERE username='$username'";
$res = mysql_query($sql);
if ($res === false) {
    wings_fail('注册失败，请稍后重试', 'query_error', 500);
}
$row = mysql_fetch_row($res);

if ($row && (int) $row[0] > 0) {
    wings_fail('该用户名已被占用，请换一个', 'exists', 409);
}

// 写入账号
$sql = "INSERT INTO `users` (username, password) VALUES ('$username', '$pass')";
if (mysql_query($sql) === false) {
    wings_fail('注册失败，请稍后重试', 'insert_error', 500);
}

wings_ok(array(
    'username' => $raw_username,
    'message'  => '注册成功，请登录',
));
