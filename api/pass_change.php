<?php
/**
 * 修改密码接口  POST api/pass_change.php
 * ---------------------------------------------------------------
 * 参数：current_password / password / re_password
 *
 * ★★★ 本题的漏洞就在这一行 ★★★
 *
 *   $username 直接取自 $_SESSION['username']，而这个值最初是由
 *   注册接口「原样写进数据库」的用户名，此处未做任何转义就拼进了
 *   UPDATE 语句 —— 典型的二次注入（Second-Order SQL Injection）。
 *
 *   有两条 payload 都能打穿（详见 README「预期解法」）：
 *
 *   【路径 A】普通账号，账号名与被攻击者无关
 *     注册用户名：  x' or username='birdadmin'#
 *     拼出的语句：  UPDATE `users` SET `password`='...'
 *                     where username='x' or username='birdadmin'#' and password='...'
 *                   # 注释掉 " and password='...'"，OR 把条件扩宽到命中管理员行
 *                   → 实际执行：where username='x' or username='birdadmin'
 *
 *   【路径 B】用户名直接顶着管理员的名字
 *     注册用户名：  birdadmin'#
 *     拼出的语句：  UPDATE `users` SET `password`='...'
 *                     where username='birdadmin'#' and password='...'
 *                   → 实际执行：where username='birdadmin'
 *
 *   两条路径都靠 `#` 顺带干掉了「当前密码校验」。
 *
 *   ⚠ 关于 affected_rows 的坑（有意保留，与 sqli-labs Less-24 一致）：
 *     MySQL 的 affected_rows 统计的是「真正被改变的行数」，不是「匹配到的行数」。
 *     所以把密码改成和现值一样时返回 0，会被下面的判断当成「当前密码不正确」。
 *
 *   ★ 2024 修订（不改漏洞、只修判定）：
 *     判定从 `$row == 1` 改成 `$row > 0`。
 *     原来写死等于 1，会误伤路径 A 的一种常见情形：攻击者注册的账号名恰好
 *     同时匹配了 OR 两侧（那一行也被改到），affected_rows 就是 2，
 *     明明注入成功却被判成「当前密码不正确」，选手会以为 payload 写错了。
 *     改成 > 0 之后：「改到至少一行 = 成功」，语义更正确，两条路径都稳。
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

$username  = $_SESSION['username'];   // ← 未过滤，注入点
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

// > 0 而不是 == 1：只要真的有行被改到就算成功。
// 这里对「普通账号 + 当前密码填对」和「payload 把 WHERE 扩宽/截断」都成立。
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
