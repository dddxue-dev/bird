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
//
// 为什么要 BINARY：
//   username 列虽然是 utf8mb4_bin（大小写敏感），但它仍然是 PAD SPACE 排序规则 ——
//   MySQL 比较时会忽略尾随空格，于是 'birdadmin ' 和 'birdadmin' 会被视为重复。
//   这对唯一索引是好事（变体注册不进来），但会让「查重」和「登录」的语义出现
//   细微偏差。这里加 BINARY 强制按字节比较，把「用户名相同」定义得没有歧义：
//     大小写不同 → 不是同一个名字（会被唯一索引放行，但也没关系，见下）
//     尾随空格   → 不是同一个名字，但唯一索引仍会拦住（PAD SPACE 生效）
//   两种情况下都不可能蹭到 birdadmin 的 is_admin —— 角色位只看那一行数据。
$sql = "SELECT count(*) FROM `users` WHERE BINARY username='$username'";
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
