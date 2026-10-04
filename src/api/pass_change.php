<?php
/**
 * 修改密码接口  POST api/pass_change.php
 * ---------------------------------------------------------------
 * 参数：current_password / password / re_password
 * 返回：{"ok":true,"message":"..."}
 *       403 {"ok":false,"error":"wrong_current_password"}
 */
define('WINGS_API', true);
require_once dirname(__FILE__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wings_fail('请使用 POST 请求', 'method_not_allowed', 405);
}

if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
    wings_json(array(
        'ok'      => false,
        'error'   => 'unauthorized',
        'message' => '登录状态已失效，请重新登录',
    ), 401);
}

$username  = $_SESSION['username'];
$curr_pass = mysql_real_escape_string(wings_req('current_password'));
$pass      = mysql_real_escape_string(wings_req('password'));
$re_pass   = mysql_real_escape_string(wings_req('re_password'));

if ($pass === '' || $curr_pass === '') {
    wings_fail('请填写当前密码和新密码', 'empty', 400);
}

if ($pass !== $re_pass) {
    wings_fail('两次输入的新密码不一致', 'mismatch', 400);
}

$sql = "UPDATE `users` SET `password`='$pass' where username='$username' and password='$curr_pass'";
$res = mysql_query($sql);
if ($res === false) {
    wings_fail('密码修改失败，请稍后重试', 'query_error', 500);
}

$row = mysql_affected_rows();

// 只要有行真的被改到就算成功。
if ($row > 0) {
    wings_ok(array(
        'message' => '密码修改成功，请使用新密码重新登录',
    ));
}

wings_json(array(
    'ok'      => false,
    'error'   => 'wrong_current_password',
    'message' => '当前密码不正确',
), 403);
