<?php
/**
 * 当前用户信息接口  GET api/profile.php
 * ---------------------------------------------------------------
 * 返回：
 *   {"ok":true,"logged_in":true,"username":"...","is_admin":false,"role":"注册志愿者","flag":null}
 *   {"ok":true,"logged_in":true,"username":"birdadmin","is_admin":true,"role":"站点管理员","flag":"flag{...}"}
 *   未登录时返回 401
 *
 * 只有以 birdadmin 身份登录，flag 字段才有值。
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

$me       = $_SESSION['username'];
$is_admin = ($me === 'birdadmin');

wings_ok(array(
    'logged_in' => true,
    'username'  => $me,
    'is_admin'  => $is_admin,
    'role'      => $is_admin ? '站点管理员 · 拥有全部数据维护权限' : '注册志愿者 · 感谢你的同行',
    'flag'      => $is_admin ? wings_flag() : null,
));
