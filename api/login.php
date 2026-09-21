<?php
/**
 * 登录接口  POST api/login.php
 * ---------------------------------------------------------------
 * 参数：username / password
 * 返回：{"ok":true,"username":"..."}
 *
 * 安全说明：
 *   用户名与密码都经过 mysql_real_escape_string()，
 *   所以「登录」这一步本身是注入不进去的，
 *   只能老老实实匹配「注册时原样写进库里的那个用户名」。
 *
 * 这一层的作用因此只剩一个：
 *   把数据库里那条「脏用户名」连同它的角色位搬进 session，
 *   等它到 api/pass_change.php 里再发作（二次注入）。
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
// BINARY 的作用：用户名按「字节」精确匹配。
//   username 列是 utf8mb4_bin，大小写已经敏感；但它是 PAD SPACE 排序规则，
//   MySQL 默认会忽略尾随空格 —— 那样 'birdadmin ' 就能登录成 'birdadmin'。
//   加 BINARY 后，注册的是什么名字，登录就必须一字不差地敲什么名字。
//   payload（birdadmin'# / x' or username='birdadmin'#）是原样入库的，
//   照旧可以精确登录，二次注入链路不受影响。
$sql = "SELECT * FROM `users` WHERE BINARY username='$username' and BINARY password='$password'";
$res = mysql_query($sql);
if ($res === false) {
    wings_fail('登录失败，请稍后重试', 'query_error', 500);
}

$row = mysql_fetch_assoc($res);

if ($row && isset($row['username'])) {

    // 关键：session 里存的是「数据库里的原始用户名」（可能就是 payload 字符串），
    // 而不是选手这次提交的输入。这正是二次注入的必经之路。
    $_SESSION['username'] = $row['username'];

    // 角色位也一起进 session，注入拿到管理员口令后重新登录时，
    // api/profile.php 就靠它放行 Flag。
    $_SESSION['is_admin'] = (isset($row['is_admin']) && (int) $row['is_admin'] === 1) ? 1 : 0;

    setcookie('Auth', '1', time() + 3600, '/');

    wings_ok(array(
        'username' => $row['username'],
        'is_admin' => $_SESSION['is_admin'] === 1,
        'message'  => '登录成功',
    ));
}

wings_fail('用户名或密码不正确', 'bad_credentials', 401);
