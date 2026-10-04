<?php
/**
 * 当前用户信息接口  GET api/profile.php
 * ---------------------------------------------------------------
 * 返回：
 *   {"ok":true,"logged_in":true,"username":"...","is_admin":false,"role":"...","flag":null}
 *   未登录时返回 401
 *
 * 权限判断用 session 里的 is_admin 角色位（整数严格等于）；
 * session 里没有该键时回库查一次。
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
