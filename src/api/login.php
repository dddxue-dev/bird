<?php
/**
 * 登录接口  POST api/login.php
 * ---------------------------------------------------------------
 * 参数：username / password
 * 返回：{"ok":true,"username":"..."}
 */
define('WINGS_API', true);
require_once dirname(__FILE__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wings_fail('请使用 POST 请求', 'method_not_allowed', 405);
}

$username = mysql_real_escape_string(wings_req('username'));
$password = mysql_real_escape_string(wings_req('password'));

if ($username === '' || $password === '') {
    wings_fail('请输入用户名和密码', 'empty', 400);
}

// 用 SELECT * 并把列按名字取出来 —— 以后给 users 加列也不会错位。
//
// BINARY：用户名按「字节」精确匹配。
//   username 列是 utf8mb4_bin，大小写已经敏感；但它是 PAD SPACE 排序规则，
//   MySQL 默认会忽略尾随空格。加 BINARY 后，注册的是什么名字，
//   登录就必须一字不差地敲什么名字。
$sql = "SELECT * FROM `users` WHERE BINARY username='$username' and BINARY password='$password'";
$res = mysql_query($sql);
if ($res === false) {
    wings_fail('登录失败，请稍后重试', 'query_error', 500);
}

$row = mysql_fetch_assoc($res);

if ($row && isset($row['username'])) {

    // session 里存的是「数据库里的原始用户名」，而不是本次提交的输入。
    $_SESSION['username'] = $row['username'];

    // 角色位一起进 session，后续接口据此判断权限。
    $_SESSION['is_admin'] = (isset($row['is_admin']) && (int) $row['is_admin'] === 1) ? 1 : 0;

    setcookie('Auth', '1', time() + 3600, '/');

    wings_ok(array(
        'username' => $row['username'],
        'is_admin' => $_SESSION['is_admin'] === 1,
        'message'  => '登录成功',
    ));
}

wings_fail('用户名或密码不正确', 'bad_credentials', 401);
