# Wings for Life · 羽翼同行

一个**前后端分离**的 CTF Web 题目：鸟类保护宣传站 + **二次 SQL 注入**（Second-Order SQL Injection）。

- 前端：纯静态 HTML / CSS / JS（`*.html` + `assets/`）
- 后端：PHP JSON API（`api/`）
- 数据库：MySQL 5.x，`users` 表（`id` / `username` / `password` / `is_admin` / `salt`），预置 4 个账号
- 部署方式：**PHPStudy 本地部署**（Apache/Nginx + PHP + MySQL，全是图形面板操作）

---

## 目录结构

```
.
├── index.html            首页（宣传语 + 10 种鸟类图鉴）
├── about.html            保护理念 / 关于本站（含管理员账号线索）
├── guangxi.html          「观鸟在广西」广西常见鸟类图鉴（12 种 + 观鸟点 + 观鸟礼仪）
├── login.html            登录
├── register.html         注册
├── profile.html          用户中心（功能面板：账号信息 / 功能入口）
├── password.html         修改密码（从用户中心进入）
├── index.php             兼容入口：目录索引优先 index.php 时转发到 index.html
├── setup.php             ★ 环境诊断 & 数据库配置向导（部署后请删除）
│
├── api/                  后端
│   ├── db.config.php     ★ 数据库配置（唯一需要按本机修改的文件）
│   ├── config.php        公共引导：连接 / 建表 / 预置账号 / Flag 读取 / JSON 工具
│   ├── register.php      注册接口
│   ├── login.php         登录接口
│   ├── logout.php        退出接口
│   ├── profile.php       当前用户信息接口（birdadmin 才会返回 flag）
│   ├── pass_change.php   修改密码接口  ★★★ 漏洞点 ★★★
│   └── inc/
│       └── mysql_compat.php   mysql_* 兼容层（PHP 7+ 用 mysqli 垫片）
│
├── assets/               前端资源
│   ├── style.css
│   ├── app.js            公共：请求封装 / 登录态 / Toast / 滚动动画
│   ├── auth.js           登录、注册页逻辑
│   ├── profile.js        用户中心（仪表盘）逻辑
│   ├── password.js       修改密码页逻辑
│   └── guangxi.js        「观鸟在广西」页逻辑（按生境筛选图鉴）
│
├── images/               23 张真实鸟类照片（本地化，来自 iNaturalist）
├── db/init.sql           手动导入用的建库脚本（可选，只建表 + 预置账号）
├── flag.txt              ★ 可选：放自己的 Flag（已在 .gitignore 里，不会提交）
└── _build/               开发自测脚本（可整个删掉，不影响运行）
    ├── e2e_test.py       端到端验证：完整跑一遍利用链路
    ├── run_e2e.sh        一键起 MySQL + PHP 跑上面的测试（bash / git-bash）
    ├── run_e2e.ps1       同上，纯 Windows PowerShell 版（无需 bash）
    ├── legacy_users.sql  老版本表结构样本（验证 bootstrap 自动升级）
    ├── legacy_bootstrap.php  触发一次老库升级（只给 e2e 用）
    ├── fetch_birds.py    下载首页 10 种鸟类照片
    ├── fetch_guangxi.py  下载「观鸟在广西」12 种鸟类照片（并发，按学名精确匹配）
    ├── reorder_guangxi.py 把图片编号重排成页面卡片顺序（gx-NN = 第 NN 张卡片）
    └── resize_images.py  统一压缩（可传 glob，如 "gx-*.jpg"，避免二次压缩）
```

## 题目设计

### 场景

「羽翼同行」是一个鸟类保护公益宣传站，首页醒目位置写着主旨：

> **人或许长不出翅膀，但人可以选择是否同行。**

页面展示 10 种中国国家一级保护鸟类（图片 + 中文名 + 拉丁学名 + 栖息地 + 简介），右上角有登录 / 注册入口。

### 数据库

`users` 表，4 个预置账号：

| id | username    | password                        | is_admin | 说明 |
|----|-------------|---------------------------------|----------|------|
| 1  | `birdadmin` | 见下面「管理员初始口令」         | **1**    | 站点管理员，**唯一能出 Flag 的身份** |
| 2  | `linxiaoyu` | `bird2024`                      | 0        | 普通志愿者 |
| 3  | `wangkai`   | `wing@123`                      | 0        | 普通志愿者 |
| 4  | `zhaoyun`   | `nest2024`                      | 0        | 普通志愿者 |

管理员身份**不再靠「用户名等于 birdadmin」这个字符串比较**来认定，而是查
`users.is_admin` 这一列（角色位）。`api/profile.php` 只有在
`(int)$_SESSION['is_admin'] === 1` 时才返回 Flag。

这样做的原因见「防意外捷径审计」：MySQL 在非二进制排序规则下会把
`'birdadmin '`（尾随空格）和 `'BirdAdmin'`（大小写）当成等于 `'birdadmin'`，
纯字符串比较是这类题最容易被选手蹭到权限的地方。现在即使名字比较松了，
角色位也不会跟着变。

另外 `username` / `password` 两列显式声明为 `COLLATE utf8mb4_bin`
（按字节比较），大小写变体在**唯一索引这一层**就被拦住。
注册查重与登录的 WHERE 又都加了 `BINARY`，把「用户名相同」收紧到字节级 ——
因为 `utf8mb4_bin` 仍然是 PAD SPACE 规则，单靠列排序规则挡不住尾随空格
（详见「防意外捷径审计」里的说明）。

以上这些都不会影响 payload 落库，`x' or username='birdadmin'#`
这类用户名照常写得进去、也照常能精确登录。

#### 管理员初始口令

`birdadmin` 的口令是一个固定值（写在 `api/config.php` 的
`wings_admin_seed_password()` 里，`db/init.sql` 里是同一个值）：

```
W1ngs@dm1n_2f8c41d9e7b3a6
```

固定值只为了本地调试方便：随时可以切成管理员视角看 Flag，
打完也能回库对比、确认注入确实改掉了管理员的密码。

> **但如果这个实例会被多人 / 多轮反复使用，就该换成随机的**：
> 本题的正确解法是「把 birdadmin 的口令改成自己知道的值」。
> 初始口令固定的话，先打通的人把口令改成某个值之后，
> 后来的人直接用它登录就能拿到 Flag，题目等于白送。
>
> 换法很简单 —— 给 PHP 设一个环境变量即可，代码不用动：
>
> ```
> WINGS_RANDOM_ADMIN=1
> ```
>
> 应用在**首次预置账号**时会现掷一条 24 位随机口令（源文件里查不到）。
> 想重置成新的随机口令，把 `wings` 库的 `users` 表清掉让应用重新预置即可。
> 注意如果走的是「手动导入 db/init.sql」，预置由 SQL 完成，
> 这个变量就不起作用了，此时要随机口令请改用应用自动建库。

### 漏洞链路

| 步骤 | 位置 | 代码 | 说明 |
|------|------|------|------|
| ① | `api/register.php` | `$username = mysql_escape_string($_POST['username']);`<br>`INSERT INTO users (username,password) VALUES ('$username','$pass')` | 转义只保证**本次 INSERT** 不被破坏，恶意用户名被**原样存进数据库** |
| ② | `api/login.php` | `mysql_real_escape_string()` 转义用户名和密码<br>`SELECT * FROM users WHERE username='$username' and password='$password'` | 登录**本身不可注入**（`'` 被转义成 `\'`，注释符绕过也无效）；但登录成功后 `$_SESSION['username']` = 数据库里的原始用户名 |
| ③ | `api/pass_change.php` | `$username = $_SESSION['username'];`<br>`UPDATE users SET password='$pass' where username='$username' and password='$curr_pass'` | `$username` **未做任何转义**就拼进 SQL → **二次注入触发点** |

三个接口的转义策略对比，是这道题的核心：

| 接口 | 用户名的处理 | 后果 |
|------|-------------|------|
| `register.php` | `mysql_escape_string()` —— 只防止 INSERT 被破坏，**不改落库内容** | 恶意用户名**原样入库** |
| `login.php` | `mysql_real_escape_string()` —— 本次查询安全 | 登录打不穿，只能"把脏名字带进来" |
| `pass_change.php` | **完全没过滤** —— 直接从 session 取值拼串 | 脏名字在这里爆发 |

> ⚠️ 注册接口**故意不做白名单校验**。这道题的前提就是「脏数据必须能落库」，
> 一旦给它加上「只允许字母数字」之类的校验，`x' or username='birdadmin'#`
> 就注册不进去，题目直接无解。要防的是**注不进去**，不是**存不进去**。

### 预期解法

有**两条**都能通关的路径，都依赖「注册 → 登录 → 改密」这条三步链路。

#### 路径 A（推荐给选手理解）：普通账号，不冒充管理员用户名

```
1. 注册一个用户名为：  x' or username='birdadmin'#
   密码随便，比如 123456

   ★ 关键：这个账号本身叫 x，跟 birdadmin 毫无关系。
     它就是一个「普通账号」，这正是本题想表达的：
     攻击者不需要拥有管理员账号，也不需要用管理员的名字注册。

2. 用  x' or username='birdadmin'# / 123456  登录
   （登录查询把 ' 转义了，所以能正常匹配到刚注册的这行；
     $_SESSION['username'] 拿到的是数据库里的原始字符串）

3. 进入用户中心 → 「修改密码」（password.html）
   当前密码：随便填（比如 i_dont_know，# 会把校验注释掉）
   新密码：  hacked123

   服务端拼出的 SQL：
   UPDATE `users` SET `password`='hacked123'
     where username='x' or username='birdadmin'#' and password='i_dont_know'
                                                                       ↑
                                        # 把 " and password='...'" 注释掉，实际执行：
   UPDATE `users` SET `password`='hacked123'
     where username='x' or username='birdadmin'
   → OR 把 WHERE 条件扩宽到命中管理员那一行 → birdadmin 密码被改成 hacked123

4. 退出登录，用  birdadmin / hacked123  登录

5. 用户中心显示 Flag
```

#### 路径 B：用户名直接顶着管理员的名字

```
1. 注册用户名为：  birdadmin'#    （密码随便）

2. 用  birdadmin'# / 123456  登录

3. 修改密码，当前密码随便填，新密码填 hacked123
   → UPDATE `users` SET `password`='hacked123' where username='birdadmin'#' and password='...'
   → 同样把校验注释掉，直接改掉 birdadmin 的密码

4. 用 birdadmin / hacked123 登录 → 拿 Flag
```

两条路径的区别只是**怎么让 WHERE 命中管理员那一行**：
A 用 `OR` 扩宽条件，B 用 `#` 截断后让 `username='birdadmin'` 生效。

**`fish` 提示**：`about.html` 的「关于本站」里写明了管理员账号是 `birdadmin`，
这是有意给出的线索 —— 选手需要先知道要改谁。

#### ⚠ 改密报「当前密码不正确」的常见原因（都不是题目 bug）

```
a) 登录态不对。$username 取自 session，只有 session 里存的是那条待注入的用户名时，
   payload 才会出现在 SQL 里。
   如果以 linxiaoyu 之类的「干净账号」登录，SQL 会是
     UPDATE users SET password='...' where username='linxiaoyu' and password='...'
   匹配 0 行 → mysql_affected_rows()==0 → 403「当前密码不正确」。
   这是设计如此：干净账号必须填对当前密码，注入只能靠「注册时写坏的用户名」。

b) 重复执行。注入已经成功过一次后 birdadmin 的密码就是 hacked123 了，
   再填一次「新密码 hacked123」时 MySQL 匹配到行但值没变化，
   mysql_affected_rows() 返回 0 → 同样报 403。
   换个新密码（比如 hacked456）即可。

   ★ 这个坑是有意保留的：MySQL 的 affected_rows 统计的是「真正被改变的行数」，
     不是「匹配到的行数」。sqli-labs Less-24 原题用的是同样的判定方式。
```

#### 已修掉的判定 bug：`affected_rows == 1` 误伤路径 A

早期版本把成功判定写成 `if (mysql_affected_rows() == 1)`。这会误伤一种很正常的
路径 A 情形：

```
攻击者账号就叫 x，payload 是  x' or username='birdadmin'#
拼出的 WHERE： where username='x' or username='birdadmin'
→ 两行都被改到 → affected_rows 返回 2
→ 老代码判 `== 1` 不成立 → 明明注入成功了却回 403「当前密码不正确」
```

现在改成 `if ($row > 0)`：**只要真的有行被改到就算成功**，语义正确，两条路径都稳。
e2e 里加了专门的回归用例（`[4.6]`）把 `affected_rows=2` 这个场景钉住。

### 实测结论

在 **PHP 7.3.4 + MySQL 5.7.26**（PHPStudy 自带环境）上完整跑过，
**两轮各 85 项断言全部通过，0 失败（退出码 0）**：

```bash
# Windows（纯 PowerShell，不需要 bash）
powershell -NoProfile -ExecutionPolicy Bypass -File _build\run_e2e.ps1

# Linux / macOS / git-bash
bash _build/run_e2e.sh
```

| 环节 | 结果 |
|------|------|
| 首次访问自动建库 / 建表 / 预置 4 个账号 | ✔ |
| `is_admin` 角色位预置正确（birdadmin=1，其余=0） | ✔ |
| `username` / `password` 列为 `utf8mb4_bin` | ✔ |
| 老库（无 `is_admin`、默认排序规则）被 bootstrap 自动升级 | ✔ |
| 直接注册 `birdadmin` 被重名拦截 | ✔ |
| 注册 `birdadmin'#` 成功，且**原样**存入数据库 | ✔ |
| 用 `birdadmin'#` 正常登录（登录处注入无效） | ✔ |
| 改密页触发二次注入，`birdadmin` 密码被改写（**路径 B**） | ✔ |
| ★ 注册 `x' or username='birdadmin'#` 的**普通账号**也能改掉 `birdadmin` 密码（**路径 A**） | ✔ |
| ★ 撞名场景（`affected_rows=2`）不再误报「当前密码不正确」 | ✔ |
| 用新密码登录 `birdadmin` → **拿到 Flag** | ✔ |
| 普通账号改密仍受当前密码校验保护 | ✔ |
| 防「意外捷径」审计（见下） | ✔ |
| 老 session（只有 `username`）能从数据库补齐角色位 | ✔ |

两轮的区别：第一轮用 root 删库（验证自动建库），
第二轮用只有 `wings.*` 权限的非 root 账号只删表
（验证「数据库账号没有建库权限时也能自建表 + 预置账号」）。

### 防「意外捷径」审计

Flag 出口有两层独立的防线，任何一层单独失效都不至于漏 Flag：

1. **角色位**：`api/profile.php` 只在 `(int)$_SESSION['is_admin'] === 1` 时返回 Flag。
   角色位来自 `users.is_admin`，**完全不看用户名**，所以「名字长得像管理员」
   这件事本身没有任何用处。
2. **字节比较**：`username` 列是 `utf8mb4_bin`；注册查重与登录的 WHERE 又都加了
   `BINARY`，所以「用户名相同」是按字节算的。

> ⚠️ 这里有个容易想当然的坑，已经实测确认：
> **`utf8mb4_bin` 只是大小写敏感，它仍然是 PAD SPACE 排序规则** ——
> `WHERE username='birdadmin '` 会命中 `birdadmin`（尾随空格被忽略）。
> 所以不能只靠列排序规则，必须像本例这样在应用层用 `BINARY` 把语义收紧。
> 好处是 `'birdadmin '` 这类变体在唯一索引上仍会被当成重复、注册不进来；
> 而登录侧因为用了 `BINARY`，也不存在「注册时叫 `birdadmin␠`、登录敲
> `birdadmin` 就进去了」这种事。

已逐项实测：

| 尝试 | 结果 |
|------|------|
| 猜测密码直接登录 `birdadmin` | ✘ 401 |
| 登录密码字段注入 `' OR '1'='1` / `UNION SELECT` | ✘ 401（两边都转义了） |
| 登录**用户名字段**用 `birdadmin'#` 绕过（想跳过密码直接登录） | ✘ 401（`'` 被转义成 `\'`，整体是个普通字符串） |
| 同上，`birdadmin'-- -` / `birdadmin'/*` / `birdadmin\'#` | ✘ 401，且没有建立任何登录态 |
| 注册 `birdadmin `（尾随空格） | ✘ 唯一索引判为重复，拒绝注册 |
| 注册 `BirdAdmin` / `BIRDADMIN`（大小写变体） | ⚠ 能注册（bin 大小写敏感），但 `is_admin=0`，**拿不到 Flag** |
| 用 `birdadmin` 这个名字去登录大小写变体注册的账号 | ✘ 401（登录用 `BINARY`，必须一字不差） |
| 注册 ` birdadmin`（前导空格）/ `birdadmin\t` | ⚠ 注册成功，但 `is_admin=0`，**拿不到 Flag** |
| 注册接口注入 payload 后检查 `birdadmin` 密码 | ✘ 密码未被影响 |
| 注册 `x' or '1'='1` 后登录 | ⚠ 能注册能登录，但 `is_admin` 仍是 false |
| 只伪造 `Auth` cookie（无 session） | ✘ 401 |
| 全库 `is_admin=1` 的行数 | 恒为 1，且只能是预置的那行 `birdadmin` |

结论：**唯一可行路径就是二次注入**。

> 特别说明「登录用户名注释符绕过」这一项：这是本类题目最容易翻车的地方。
> 如果 `login.php` 漏掉转义或只转义密码字段，选手直接拿 `birdadmin'#` 当用户名登录
> 就能得到管理员的 session，**完全不需要二次注入**，题目就废了。
> 本题两边都转义了，且 e2e 里专门加了断言把这条路堵死。

Flag 只在 `api/profile.php` 中、且登录身份是管理员角色时才返回：

```php
// 角色位优先取 session（登录时从数据库写入），老 session 回库补查
$is_admin = ((int) $_SESSION['is_admin']) === 1;

'is_admin' => $is_admin,
'flag'     => $is_admin ? wings_flag() : null,
```

### Flag 占位值

默认显示的占位值是 **`flag{123455678}`**，写在 `api/config.php` 的
`wings_flag()` 最后一行。换掉它有两种方式，**都不用改代码**：

| 方式 | 怎么做 | 优先级 |
|------|--------|--------|
| 环境变量 | 给 PHP 设 `FLAG=flag{你的真flag}` | 高 |
| 文件 | 在项目根目录建 `flag.txt`，内容写一行真 Flag | 中 |
| 占位值 | 什么都不做，显示 `flag{123455678}` | 兜底 |

`flag.txt` 已经在 `.gitignore` 里，不会被误提交。
涉及到占位值的文件（改动时要一起改）：

| 文件 | 用途 |
|------|------|
| `api/config.php` | 静态兜底值（`wings_flag()` 最后一行） |
| `_build/run_e2e.sh` | `STATIC_FLAG`，e2e 校验静态兜底值用 |
| `_build/run_e2e.ps1` | `-StaticFlag`，同上（Windows 版） |

---

## 本地部署（PHPStudy）

全程在 PHPStudy 图形面板里点几下就能跑起来，不需要命令行。

### 1. 放好站点文件

把本目录整个放到 PHPStudy 的网站根目录下，例如：

```
D:\phpstudy_pro\WWW\wings\
```

然后在 PHPStudy 面板 →「网站」→ 新建一个站点：

| 项 | 填什么 |
|----|--------|
| 域名 | `wings.local`（或直接 `localhost`） |
| 根目录 | 选到上面那个 `wings` 目录 |
| PHP 版本 | 5.4 ~ 8.x 都行（推荐 7.3，本项目的自测环境就是它） |
| 备注 | 随便 |

> **只要保证 mysqli 扩展是开的**（面板 → 软件管理 → PHP 扩展 → 勾 `mysqli`）。
> `api/inc/mysql_compat.php` 会用 mysqli 实现一整套 `mysql_*` 函数，
> 所以 PHP 7 / 8 下业务代码不用改。

### 2. 确认 MySQL 是启动状态

面板首页点 MySQL 的「启动」。记下面板上「数据库」那一页的 root 密码
（很多人装完 PHPStudy 后 root 密码并不是 `root`）。

### 3. 配好数据库连接

打开 `api/db.config.php`，把 `'pass'` 改成你实际的 MySQL 密码：

```php
'user' => 'root',
'pass' => 'root',     // ←←← 出问题基本都是这里
'name' => 'wings',
```

改完保存，刷新页面即可，**不需要重启 Apache**。

不确定密码是什么，或者懒得改文件？直接访问：

```
http://wings.local/setup.php
```

这是环境诊断 + 配置向导页：它会测试连接、把正确配置写进 `api/db.config.php`，
并顺手建库建表预置账号。

### 4. 打开首页

```
http://wings.local/
```

第一次访问任意页面时，`api/config.php` 会**自动建库、建表、预置 4 个账号**
（`wings` 库不存在就创建）。所以正常情况下一步都不用多做。

如果连接用的 MySQL 账号**没有建库权限**（不是 root，只被授权了某些库），
那自动建库那一步会失败。两种解决办法：

- 用 HeidiSQL（PHPStudy 自带）先手动建好 `wings` 库，
  再打开 `db/init.sql` 执行——应用之后只建表、预置账号，不再需要建库权限；
- 或者干脆不用普通账号，直接用 root。

> 这两种场景在自测脚本里都有覆盖：第二轮就是专门用
> 「只授权了库、没有建库权限」的账号跑一遍（见「实测结论」）。

### 5. 出题人自查清单

- [ ] 首页能打开，图片正常显示
- [ ] 注册一个随便的账号 → 能登录 → 用户中心显示「注册志愿者」
- [ ] 用 `birdadmin` / `W1ngs@dm1n_2f8c41d9e7b3a6` 登录 → 用户中心显示 Flag
- [ ] 走一遍预期解法：注册 `x' or username='birdadmin'#` → 登录 → 改密 →
      用 `birdadmin` + 自己设的新密码登录 → 拿到 Flag
- [ ] 跑一遍 `_build\run_e2e.ps1`（或 `bash _build/run_e2e.sh`）→ 退出码 0
- [ ] **把 `setup.php` 删掉或移走**（它能改数据库配置，不该留在对外环境里）
- [ ] 如果这个实例会给多个人/多轮用，设 `WINGS_RANDOM_ADMIN=1` 让管理员口令随机

---

## 观鸟在广西（广西常见鸟类图鉴）

`guangxi.html` 是第二个图鉴页，页面名 **观鸟在广西**，面向广西本地物种。
它和首页图鉴的定位不同：首页讲的是**全国的、濒危的、多数人一生见不到的**十种鸟；
这一页讲的是**广西的、常见的、出门就可能遇到的** 12 种鸟，并且主张「先找对环境，再找鸟」。

内容结构：

| 板块 | 说明 |
|------|------|
| 为什么是广西 | 南亚热带 + 喀斯特峰丛 + 山地常绿阔叶林 + 滨海红树林 + 水田，多生境在小尺度内叠加；东亚—澳大利西亚迁飞通道关键节点 |
| 数据条 | 757 种（23 目 93 科）、其中国家一级 32 种、你出门最可能遇见的 12 种（本页） |
| 图鉴 · 12 种 | 按生境分三组，可点击标签即时筛选（逻辑在 `assets/guangxi.js`） |
| 观鸟点 · 6 处 | 青秀山、南湖公园与邕江沿岸、会仙喀斯特湿地、山口红树林、大明山、弄岗 |
| 观鸟礼仪 · 4 条 | 保持距离 / 不诱拍不用鸟音 / 不惊扰巢址 / 留下可核查的记录 |

图鉴的 12 种鸟按生境分三组（`data-cat` 即筛选键）：

| 分组 | `data-cat` | 物种 |
|------|-----------|------|
| 枝叶之间（3） | `canopy` | 暗绿绣眼鸟、长尾缝叶莺、大山雀 |
| 林缘与庭院（5） | `garden` | 红耳鹎、鹊鸲、白头鹎、珠颈斑鸠、白喉红臀鹎 |
| 水边与开阔地（4） | `water` | 白鹡鸰、白鹭、池鹭、普通翠鸟 |

**这 12 种全部是「三有」动物**（《有重要生态、科学、社会价值的陆生野生动物名录》2023 年，
国家林草局公告 2023 年第 17 号），**没有一种在国家重点保护名录内**。
这不是疏漏，而是这一页的论点：它们不珍稀，所以最容易被忽略——正好呼应站点的「生命本无高低」。

卡片角标显示的是**居留型**（留鸟 / 夏候鸟 / 冬候鸟），而不是保护级别，因为 12 种全为「三有」，
角标用来承载更有信息量的属性：留鸟 10 种、夏候鸟 1 种（池鹭）、冬候鸟 1 种（白鹡鸰）。

> 学名有两处容易写错，已核对：
> 暗绿绣眼鸟是 **Zosterops simplex**（Swinhoe's White-eye，华南种群，曾被归入 *Z. japonicus*）；
> 大山雀是 **Parus cinereus**（Cinereous Tit，东亚种群，曾为欧亚大山雀 *P. major* 的亚种）。
> 珠颈斑鸠用 **Spilopelia chinensis**（旧名 *Streptopelia chinensis*）。

**这一页与漏洞无关**，只是让「鸟类保护宣传站」这个设定更可信的常规内容。
如果要最小化题目，删掉 `guangxi.html` + `assets/guangxi.js` + `images/gx-*.jpg`，
再把各页导航里的「观鸟在广西」那一行去掉即可，不影响 API 与漏洞链路。

---

## 图片版权

`images/` 下 23 张照片（首页 11 张 + 「观鸟在广西」12 张）均来自
[iNaturalist](https://www.inaturalist.org/) 的公开观察记录（CC 授权），
已本地化到项目中，离线也能正常显示，不依赖外链。

原始照片 ID：

首页图鉴（10 张）：

| 文件 | 物种 | iNaturalist 照片 ID |
|------|------|------------------|
| bird-01.jpg | 丹顶鹤 | 122932380 |
| bird-02.jpg | 朱鹮 | 85404012 |
| bird-03.jpg | 绿孔雀 | 210255393 |
| bird-04.jpg | 中华秋沙鸭 | 112454433 |
| bird-05.jpg | 黑颈鹤 | 260820140 |
| bird-06.jpg | 褐马鸡 | 607401216 |
| bird-07.jpg | 东方白鹳 | 754972 |
| bird-08.jpg | 黄腹角雉 | 631233095 |
| bird-09.jpg | 大鸨 | 102883570 |
| bird-10.jpg | 勺嘴鹬 | 246421543 |
| hero.jpg | 首页大图 | — |

「观鸟在广西」（12 张，编号与页面卡片顺序一致）：

| 文件 | 物种 | 学名 | 居留型 | iNaturalist 照片 ID |
|------|------|------|--------|------------------|
| gx-01.jpg | 暗绿绣眼鸟 | *Zosterops simplex* | 留鸟 | 20010236 |
| gx-02.jpg | 长尾缝叶莺 | *Orthotomus sutorius* | 留鸟 | 463709344 |
| gx-03.jpg | 大山雀 | *Parus cinereus* | 留鸟 | 62897600 |
| gx-04.jpg | 红耳鹎 | *Pycnonotus jocosus* | 留鸟 | 291201941 |
| gx-05.jpg | 鹊鸲 | *Copsychus saularis* | 留鸟 | 62295683 |
| gx-06.jpg | 白头鹎 | *Pycnonotus sinensis* | 留鸟 | 59227685 |
| gx-07.jpg | 珠颈斑鸠 | *Spilopelia chinensis* | 留鸟 | 230618423 |
| gx-08.jpg | 白喉红臀鹎 | *Pycnonotus aurigaster* | 留鸟 | 401459136 |
| gx-09.jpg | 白鹡鸰 | *Motacilla alba* | 冬候鸟 | 39786089 |
| gx-10.jpg | 白鹭 | *Egretta garzetta* | 留鸟 | 205293169 |
| gx-11.jpg | 池鹭 | *Ardeola bacchus* | 夏候鸟 | 42885489 |
| gx-12.jpg | 普通翠鸟 | *Alcedo atthis* | 留鸟 | 61266631 |

> gx-03 与 gx-08 用的是观察记录里的照片，而不是 iNaturalist 的「物种代表图」——
> 那两张代表图带着摄影师的署名水印，放在卡片上很扎眼。
> 脚本 `_build/fetch_candidates.py` 专门用来抓备选图并拼成对照表供人工挑选。

12 张照片按学名从 iNaturalist 检索后**逐张人工核对**过物种（`_build/gx-contact-sheet.jpg` 是核对用的拼图），
并统一压缩为最长边 1200px、quality 82，合计约 1.26 MB。

如用于公开赛事，建议在页面保留来源说明（页脚已注明）。

---

## FAQ

**Q：页面显示「数据库连接失败」？**
A：改 `api/db.config.php` 里的 `'pass'`，或访问 `setup.php` 用图形界面测试写入。
   确认 PHPStudy 面板里 MySQL 服务是启动状态。

**Q：PHP 7 / 8 下报 `Call to undefined function mysql_connect()`？**
A：`api/inc/mysql_compat.php` 已经用 mysqli 实现了全套 `mysql_*` 垫片，
   会自动加载，不需要额外配置。PHPStudy 里只要保证 **mysqli 扩展是开启的**。

**Q：代码到底支持哪些 PHP 版本？**
A：**PHP 5.4 ~ 8.x 全部支持**，业务代码统一用 `mysql_*` 风格（符合题目要求），
   底层由兼容层适配：

   | 环境 | 行为 |
   |------|------|
   | PHP 5.x（原生 `ext/mysql`） | 兼容层自动跳过，直接用原生函数 |
   | PHP 7.x / 8.x（有 mysqli） | 兼容层用 mysqli 实现同名函数 |
   | PHP 7+ 且 mysqli 未开启 | 给出「请去 PHPStudy 勾选 mysqli」的可读提示，不会 500 |

   兼容层里专门处理了两个 PHP 8.1+ 的行为变更：
   `mysqli` 默认改为抛异常（已用 `mysqli_report(MYSQLI_REPORT_OFF)` + try/catch 兜底），
   以及 `$GLOBALS` 写入受限（连接状态改用静态类属性保存）。

   PHPStudy 里在「软件管理 → PHP 扩展」中切换版本、开关扩展即可，代码不用动。

**Q：访问站点根目录 403 / 目录列表？**
A：`index.php` 会转发到 `index.html`。如果你的服务器连 `index.php` 都不作为目录索引，
   直接把 `index.html` 改名为 `index.php` 也行（它里面没有任何 PHP 代码）。

**Q：怎么改 Flag 的显示位置 / 判定方式？**
A：改 `api/profile.php` 里的 `$is_admin` 判定即可（现在是读 session 里的角色位），
   或者把 `'flag' => $is_admin ? wings_flag() : null` 挪到别的接口去。
   注意别退回「比较用户名」的写法 —— 原因见「防意外捷径审计」。

**Q：怎么让管理员初始口令每次都变？**
A：给 PHP 设环境变量 `WINGS_RANDOM_ADMIN=1`（Apache 用 `SetEnv`，
   或 php.ini 里 `env[WINGS_RANDOM_ADMIN]=1`），`api/config.php` 在
   **首次预置账号**时就会现掷一条 24 位随机口令。
   这个实例会被多人/多轮反复使用时**建议打开**，
   否则先打通的人会把口令固定成某个值，后来的人可以直接用。
   注意：它只在「应用自动预置」时生效；如果是手动导入 `db/init.sql`，
   管理员口令就是 SQL 里写死的那个固定值。

**Q：想让漏洞更难一点？**
A：几个不影响解题的方向：
   - 在 `password.html` 上去掉「当前密码」这个字段的提示文案，增加一点摸索成本；
   - 把 `birdadmin` 这个用户名的线索藏得更深（比如只在某张图片的 EXIF 里）；
   - 给注册接口加一个「用户名不能包含引号」之外、但仍然放过 SQL 元字符的宽松校验
     （比如只限制长度与首字符），提高一点构造 payload 的门槛。

   ⚠ 但**千万不要**把注册接口的 `mysql_escape_string` 换成只允许字母数字的白名单 ——
   那样 `x' or username='birdadmin'#` 和 `birdadmin'#` 都注册不进去，题目直接无解。
   这类题的「脏数据」必须能落库，否则二次注入无从谈起。

**Q：`_build/run_e2e.sh` / `run_e2e.ps1` 怎么跑？**
A：本机装上 PHPStudy（自带 PHP 7.3 + MySQL 5.7）后：

```bash
# Windows（推荐，纯 PowerShell，不依赖 bash/cygpath）
powershell -NoProfile -ExecutionPolicy Bypass -File _build\run_e2e.ps1

# Linux / macOS / git-bash
bash _build/run_e2e.sh
```

两个脚本会自己起一个**独立数据目录、独立端口（3399）**的 MySQL 和
`php -S`（端口 8099），不会碰到你现有的数据库。跑完自动收尾，
退出码 0 表示全部通过（当前版本是两轮各 85 项断言）。

- PHP / MySQL 装在别的位置 → `run_e2e.sh` 设 `PHPSTUDY_DIR`、`PHP_VERS`、`MYSQL_VERS`；
  `run_e2e.ps1` 用 `-PhpExe` / `-MySqlBin` / `-MySqlBase`
- 只想验证静态占位 Flag → `TESTFLAG='' bash _build/run_e2e.sh`（或 `-TestFlag ''`）
- 跳过「非 root 账号」那一轮 → `E2E_NONROOT=0`（或 `-SkipNonRoot`）
- 端口被占 → `-DbPort` / `-WebPort`（脚本会自动清掉占用 3399 的**残留** mysqld）
- 单独跑 `python _build/e2e_test.py` 时，脚本会自动去找 mysql 客户端
  （PHPStudy 默认路径 → XAMPP → 系统路径），找不到再用 `MYSQL=` 显式指定；
  用 `PHP=` 指定 `php.exe`（跑老库升级用例要用）
