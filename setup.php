<?php
/**
 * ===============================================================
 *  环境诊断 & 数据库配置向导
 * ===============================================================
 *  用途：不改代码，直接在浏览器里测试 MySQL 账号密码，
 *        测试通过后一键写入 api/db.config.php。
 *
 *  ⚠️ 正式部署（尤其放到 CTF 靶场）后请删除本文件。
 *     写入配置的操作只允许来自本机 / 内网地址。
 */

// 如果你确实需要从公网访问本页来写配置，把这里改成 true（不推荐）
$ALLOW_REMOTE_SETUP = false;

$CONFIG_FILE = dirname(__FILE__) . '/api/db.config.php';

// ---------------------------------------------------------------
// 当前配置
// ---------------------------------------------------------------
$current = array(
    'host' => '127.0.0.1',
    'port' => '3306',
    'user' => 'root',
    'pass' => 'root',
    'name' => 'wings',
    'auto_try_common_passwords' => true,
);
if (is_file($CONFIG_FILE)) {
    $tmp = include $CONFIG_FILE;
    if (is_array($tmp)) {
        $current = array_merge($current, $tmp);
    }
}

// ---------------------------------------------------------------
// 环境信息
// ---------------------------------------------------------------
$env = array(
    'PHP 版本'              => PHP_VERSION,
    '操作系统'              => PHP_OS,
    'SAPI'                  => php_sapi_name(),
    '原生 ext/mysql'        => function_exists('mysql_connect') ? '✔ 可用（PHP 5.x 原生）' : '✘ 不存在（将使用 mysqli 兼容层）',
    'mysqli 扩展'           => class_exists('mysqli') ? '✔ 可用' : '✘ 未开启（请到 PHPStudy 里勾选 mysqli）',
    'session 支持'          => function_exists('session_start') ? '✔ 可用' : '✘ 不可用',
    'mbstring 扩展'         => function_exists('mb_strlen') ? '✔ 可用' : '— 未开启（本题不依赖）',
    'variables_order'       => ini_get('variables_order'),
    '$_ENV[\'FLAG\'] 可读'  => (isset($_ENV['FLAG']) && $_ENV['FLAG'] !== '') ? '✔ 已注入' : '— 未通过 $_ENV 注入',
    'getenv(\'FLAG\') 可读' => (getenv('FLAG') !== false && getenv('FLAG') !== '') ? '✔ 已注入' : '— 未通过环境变量注入',
    'flag.txt 文件'         => is_readable(dirname(__FILE__) . '/flag.txt') ? '✔ 存在且可读' : '— 不存在（本地调试可自行创建）',
);

// ---------------------------------------------------------------
// 本机 / 内网判断
// ---------------------------------------------------------------
$remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$is_local = ($ALLOW_REMOTE_SETUP || $remote === '' || $remote === '127.0.0.1' || $remote === '::1'
    || preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.|127\.)/', $remote));

// ---------------------------------------------------------------
// 测试连接
// ---------------------------------------------------------------
function wings_try_connect($host, $port, $user, $pass)
{
    if (!function_exists('mysql_connect') && class_exists('mysqli')) {
        require_once dirname(__FILE__) . '/api/inc/mysql_compat.php';
    }
    if (function_exists('mysql_connect')) {
        $link = @mysql_connect($host . ':' . $port, $user, $pass);
        if (!$link) {
            return array(false, mysql_error());
        }
        return array(true, '');
    }
    if (class_exists('mysqli')) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $link = @new mysqli($host, $user, $pass, null, (int) $port);
        if ($link->connect_errno) {
            return array(false, $link->connect_error);
        }
        return array(true, '');
    }
    return array(false, '当前 PHP 既没有 ext/mysql 也没有 mysqli，无法连接数据库');
}

$test_result = null;   // array(ok, msg, extra)
$write_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {

    $in = array(
        'host' => isset($_POST['host']) ? trim($_POST['host']) : '127.0.0.1',
        'port' => isset($_POST['port']) ? trim($_POST['port']) : '3306',
        'user' => isset($_POST['user']) ? trim($_POST['user']) : 'root',
        'pass' => isset($_POST['pass']) ? (string) $_POST['pass'] : '',
        'name' => isset($_POST['name']) ? trim($_POST['name']) : 'wings',
        'auto_try_common_passwords' => !empty($_POST['auto_try']),
    );

    list($ok, $err) = wings_try_connect($in['host'], $in['port'], $in['user'], $in['pass']);

    if (!$ok) {
        $test_result = array(false, '连接失败：' . $err, $in);
    } elseif (!$is_local) {
        $test_result = array(true, '连接成功，但当前来源 IP（' . htmlspecialchars($remote) . '）不是本机 / 内网，出于安全考虑未写入配置文件。', $in);
    } else {
        // 写配置
        $php = "<?php\n"
             . "/**\n"
             . " * 数据库配置（由 setup.php 自动生成于 " . date('Y-m-d H:i:s') . "）\n"
             . " * 也可以手动修改本文件，或用环境变量 DB_HOST/DB_PORT/DB_USER/DB_PASS/DB_NAME 覆盖。\n"
             . " */\n\n"
             . "return array(\n"
             . "    'host' => " . var_export($in['host'], true) . ",\n"
             . "    'port' => " . var_export($in['port'], true) . ",\n"
             . "    'user' => " . var_export($in['user'], true) . ",\n"
             . "    'pass' => " . var_export($in['pass'], true) . ",\n"
             . "    'name' => " . var_export($in['name'], true) . ",\n"
             . "    'auto_try_common_passwords' => " . ($in['auto_try_common_passwords'] ? 'true' : 'false') . ",\n"
             . ");\n";

        if (@file_put_contents($CONFIG_FILE, $php) === false) {
            $test_result = array(true, '连接成功，但写入 ' . htmlspecialchars($CONFIG_FILE) . ' 失败，请检查目录写权限，或手动修改该文件。', $in);
        } else {
            // 顺手把库、表、预置账号建好
            $extra = '';
            if (!function_exists('mysql_connect') && class_exists('mysqli')) {
                require_once dirname(__FILE__) . '/api/inc/mysql_compat.php';
            }
            mysql_query('CREATE DATABASE IF NOT EXISTS `' . $in['name'] . '` DEFAULT CHARACTER SET utf8mb4');
            if (mysql_select_db($in['name'])) {
                mysql_query('SET NAMES utf8mb4');
                // 表结构必须和 api/config.php 的 wings_install() 一致：
                // utf8mb4_bin 让用户名按字节比较（挡掉大小写/尾随空格变体），
                // is_admin 是管理员角色位（Flag 出口看的就是它）。
                mysql_query("CREATE TABLE IF NOT EXISTS `users` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `username` VARCHAR(64) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
                    `password` VARCHAR(128) COLLATE utf8mb4_bin NOT NULL DEFAULT '',
                    `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
                    `salt` VARCHAR(32) NOT NULL DEFAULT '',
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");

                $r = mysql_query('SELECT COUNT(*) FROM `users`');
                $row = mysql_fetch_row($r);
                if ($row && (int) $row[0] === 0) {
                    $seed = array(
                        array('birdadmin', 'W1ngs@dm1n_2f8c41d9e7b3a6', 1),
                        array('linxiaoyu', 'bird2024',                    0),
                        array('wangkai',   'wing@123',                    0),
                        array('zhaoyun',   'nest2024',                    0),
                    );
                    foreach ($seed as $u) {
                        mysql_query("INSERT IGNORE INTO `users` (`username`,`password`,`is_admin`) VALUES ('"
                            . mysql_escape_string($u[0]) . "','" . mysql_escape_string($u[1]) . "',"
                            . ($u[2] ? 1 : 0) . ")");
                    }
                    $extra = '已创建数据库与 users 表，并预置 4 个账号（birdadmin 为管理员）。';
                } else {
                    $extra = 'users 表已存在，未改动数据。';
                }
            } else {
                $extra = '数据库创建/选择失败：' . mysql_error();
            }

            $test_result = array(true, '配置已写入 api/db.config.php。' . $extra, $in);
            $current = array_merge($current, $in);
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>环境诊断 · 羽翼同行</title>
<style>
  :root { --g:#14372a; --g6:#2a6b4e; --line:#dfe5df; --ink:#1b2a22; --soft:#4a5c51; }
  * { box-sizing:border-box }
  body { margin:0; background:#faf8f2; color:var(--ink); line-height:1.75;
         font-family:"PingFang SC","Microsoft YaHei",system-ui,sans-serif; }
  .wrap { max-width:860px; margin:0 auto; padding:38px 22px 70px }
  h1 { font-size:24px; color:var(--g); margin:0 0 6px }
  .lead { color:var(--soft); margin:0 0 26px }
  .card { background:#fff; border:1px solid var(--line); border-radius:14px;
          padding:24px 26px; margin-bottom:22px; box-shadow:0 6px 24px rgba(15,42,30,.06) }
  .card h2 { font-size:16px; margin:0 0 16px; color:var(--g); letter-spacing:.4px }
  table { width:100%; border-collapse:collapse; font-size:14px }
  td { padding:7px 0; border-bottom:1px dashed #eef0ec; vertical-align:top }
  td:first-child { color:var(--soft); width:210px }
  code { background:#f2f0e9; padding:2px 6px; border-radius:5px; font-size:13px }
  .alert { border-radius:9px; padding:13px 16px; margin-bottom:20px; font-size:14px }
  .ok { background:#eef7f1; border:1px solid #cbe5d5; color:#22653f }
  .err { background:#fdf1ec; border:1px solid #f0d3c4; color:#9a4a26 }
  .warn { background:#fdf6e8; border:1px solid #f0e0bd; color:#8a6320 }
  label { display:block; font-size:13px; font-weight:600; color:var(--soft); margin-bottom:5px }
  input[type=text],input[type=password] { width:100%; padding:9px 11px; font-size:14px;
      border:1px solid var(--line); border-radius:8px; background:#fdfcf8; font-family:inherit }
  input:focus { outline:none; border-color:#3d8a64; box-shadow:0 0 0 3px rgba(61,138,100,.14) }
  .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px }
  .grid .full { grid-column:1 / -1 }
  button { margin-top:18px; background:var(--g6); color:#fff; border:0; border-radius:22px;
           padding:11px 26px; font-size:15px; cursor:pointer; font-family:inherit }
  button:hover { background:#3d8a64 }
  .muted { color:#7b8b81; font-size:13px }
  a { color:var(--g6); font-weight:600 }
  pre { background:#0f2a1e; color:#d9e8dd; padding:12px 14px; border-radius:9px;
        font-size:12.5px; overflow:auto; margin:0 }
</style>
</head>
<body>
<div class="wrap">

  <h1>环境诊断 &amp; 数据库配置向导</h1>
  <p class="lead">用来排查「数据库连接失败」，并把正确的账号密码写进 <code>api/db.config.php</code>。</p>

  <div class="alert warn">
    ⚠️ 本页面可以修改数据库配置。正式部署到靶场后请<b>删除 setup.php</b>。
    写入操作仅允许来自本机 / 内网地址。
  </div>

  <?php if ($test_result !== null): ?>
    <div class="alert <?php echo $test_result[0] ? 'ok' : 'err'; ?>">
      <?php echo htmlspecialchars($test_result[1]); ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>运行环境</h2>
    <table>
      <?php foreach ($env as $k => $v): ?>
        <tr><td><?php echo htmlspecialchars($k); ?></td><td><?php echo htmlspecialchars($v); ?></td></tr>
      <?php endforeach; ?>
      <tr><td>当前访问来源 IP</td>
          <td><?php echo htmlspecialchars($remote); ?>
              <?php echo $is_local ? '<span class="muted">（本机/内网，可写入配置）</span>'
                                   : '<span class="muted">（非内网，只读）</span>'; ?></td></tr>
    </table>
  </div>

  <div class="card">
    <h2>MySQL 连接配置</h2>
    <p class="muted" style="margin-top:-6px">
      在 PHPStudy 面板 →「数据库」里可以看到 root 的密码。填好后点下面的按钮，
      会先测试连接，成功才写入配置。
    </p>

    <form method="POST" autocomplete="off">
      <input type="hidden" name="action" value="save">
      <div class="grid">
        <div>
          <label>主机地址</label>
          <input type="text" name="host" value="<?php echo htmlspecialchars($current['host']); ?>">
        </div>
        <div>
          <label>端口</label>
          <input type="text" name="port" value="<?php echo htmlspecialchars($current['port']); ?>">
        </div>
        <div>
          <label>用户名</label>
          <input type="text" name="user" value="<?php echo htmlspecialchars($current['user']); ?>">
        </div>
        <div>
          <label>密码</label>
          <input type="password" name="pass" value="<?php echo htmlspecialchars($current['pass']); ?>">
        </div>
        <div>
          <label>数据库名（不存在会自动创建）</label>
          <input type="text" name="name" value="<?php echo htmlspecialchars($current['name']); ?>">
        </div>
        <div class="full">
          <label style="font-weight:400">
            <input type="checkbox" name="auto_try" value="1" <?php echo !empty($current['auto_try_common_passwords']) ? 'checked' : ''; ?>>
            连接失败时自动尝试常见本地密码（空 / root / 123456 ...）
          </label>
        </div>
      </div>
      <button type="submit">测试连接并保存</button>
    </form>
  </div>

  <div class="card">
    <h2>手动修改方式</h2>
    <p class="muted">不想用本页面，也可以直接用编辑器打开下面这个文件，改 <code>'pass'</code> 的值：</p>
    <pre><?php echo htmlspecialchars($CONFIG_FILE); ?></pre>
    <p class="muted" style="margin-top:14px">
      改完保存，刷新页面即可，不需要重启 Apache。也可以用环境变量覆盖：
      <code>DB_HOST</code> / <code>DB_PORT</code> / <code>DB_USER</code> / <code>DB_PASS</code> / <code>DB_NAME</code>。
    </p>
  </div>

  <p class="muted">配置没问题后，访问 <a href="index.html">首页</a> 开始使用。</p>
</div>
</body>
</html>
