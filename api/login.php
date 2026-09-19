<?php
/**
 * 登录接口  POST api/login.php
 * ---------------------------------------------------------------
 * 参数：username / password
 * 返回：{"ok":true,"username":"..."}
 *
 * 安全说明：
 *   用户名与密码都经过 mysql_real_escape_string()，
 *   所以「登录」这一步本身是注入不进去的。
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

$sql = "SELECT * FROM `users` WHERE username='$username' and password='$password'";
$res = mysql_query($sql);
if ($res === false) {
    wings_fail('登录失败，请稍后重试', 'query_error', 500);
}

$row = mysql_fetch_row($res);

if ($row && isset($row[1]) && $row[1] !== '') {
    $_SESSION['username'] = $row[1];
    setcookie('Auth', '1', time() + 3600, '/');
    wings_ok(array(
        'username' => $row[1],
        'message'  => '登录成功',
    ));
}

wings_fail('用户名或密码不正确', 'bad_credentials', 401);
