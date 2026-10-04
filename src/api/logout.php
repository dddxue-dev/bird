<?php
/**
 * 退出登录接口  POST api/logout.php
 */
define('WINGS_API', true);
require_once dirname(__FILE__) . '/config.php';

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();
setcookie('Auth', '', time() - 3600, '/');

wings_ok(array('message' => '已退出登录'));
