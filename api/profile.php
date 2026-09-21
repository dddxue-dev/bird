<?php
/**
 * 当前用户信息接口  GET api/profile.php
 * ---------------------------------------------------------------
 * 返回：
 *   {"ok":true,"logged_in":true,"username":"...","is_admin":false,"role":"注册志愿者","flag":null}
 *   {"ok":true,"logged_in":true,"username":"birdadmin","is_admin":true,"role":"站点管理员","flag":"flag{...}"}
 *   未登录时返回 401
 *
 * 只有以「数据库里 is_admin=1 的那一行」登录，flag 字段才有值。
 * ---------------------------------------------------------------
 * 关于权限判断（这里是本题的第二道防线）：
 *
 *   早期版本写的是 $is_admin = ($me === 'birdadmin');  —— 用用户名字符串比较。
 *   那在 MySQL 的默认排序规则下是有隐患的：非二进制排序把 'birdadmin '
 *   （尾随空格）和 'BirdAdmin'（大小写）都视为等于 'birdadmin'，一旦哪一层
 *   判断写松了，选手不用注入、注册个变体名字就可能蹭到管理员视角。
 *
 *   现在改成：
 *     1. 登录时就把数据库里的 is_admin 角色位写进 session；
 *     2. 这里用 ((int)$_SESSION['is_admin'] === 1) 这种「整数严格等于」判断；
 *     3. session 里没有这个键（比如迁移前建的老 session）就回库查一次。
 *   字符串比较彻底消失，排序规则陷阱也就无从谈起了。
 */
define('WINGS_API', true);
require_once dirname(__FILE__) . '/config.php';

if (!isset($_SESSION['username']) || $_SESSION['username'] === '') {
    wings_json(array(
        'ok'        => false,
        'logged_in' => false,
        'error'     => 'unauthorized',
        'message'   => '请先登录',
    ), 401);
}

$me = $_SESSION['username'];

// 角色位：优先用登录时写进 session 的值；老 session 没有就回库查一次并补上。
if (isset($_SESSION['is_admin'])) {
    $is_admin = ((int) $_SESSION['is_admin']) === 1;
} else {
    $is_admin = wings_is_admin_user($me);
    $_SESSION['is_admin'] = $is_admin ? 1 : 0;
}

wings_ok(array(
    'logged_in' => true,
    'username'  => $me,
    'is_admin'  => $is_admin,
    'role'      => $is_admin ? '站点管理员 · 拥有全部数据维护权限' : '注册志愿者 · 感谢你的同行',
    // ★ Flag 出口：这里必须同时满足「登录着」+「是管理员角色」，缺一不可。
    'flag'      => $is_admin ? wings_flag() : null,
));
