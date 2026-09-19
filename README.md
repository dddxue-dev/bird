# Wings for Life · 羽翼同行

一个**前后端分离**的 CTF Web 题目：鸟类保护宣传站 + **二次 SQL 注入**（Second-Order SQL Injection）。

- 前端：纯静态 HTML / CSS / JS（`*.html` + `assets/`）
- 后端：PHP JSON API（`api/`）
- 数据库：MySQL 5.x，`users` 表（`id` / `username` / `password`），预置 4 个账号
- 动态 Flag：环境变量 `FLAG` 或挂载 `/flag` 文件（CTFd Whale 兼容）

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
├── db/init.sql           手动导入用的建库脚本（可选）
├── docker/               Dockerfile + entrypoint.sh
├── docker-compose.yml    本地测试用
└── _build/               开发自测脚本（可整个删掉，不影响运行）
    ├── e2e_test.py       端到端验证：完整跑一遍利用链路
    ├── run_e2e.sh        一键起 MySQL + PHP 跑上面的测试
    ├── fetch_birds.py    下载首页 10 种鸟类照片
    ├── fetch_guangxi.py  下载「观鸟在广西」12 种鸟类照片（并发，按学名精确匹配）
    ├── reorder_guangxi.py 把图片编号重排成页面卡片顺序（gx-NN = 第 NN 张卡片）
    └── resize_images.py  统一压缩（可传 glob，如 "gx-*.jpg"，避免二次压缩）
```

---

## 一、PHPStudy 上跑起来（推荐先看这一节）

### 1. 放置文件

把整个目录放到 PHPStudy 的网站根目录下，例如：

```
D:\phpstudy_pro\WWW\wings\
```

**PHP 版本要求：5.4 及以上**（5.6 / 7.x / 8.x 都可以）。

> ⚠️ **务必确认 `mysqli` 扩展是开启的。**
> 本机实测踩过这个坑：PHP 7.4 的默认配置里 `mysqli` 是关闭的，
> 关闭时页面会直接报 `Call to undefined function mysqli_connect()`。
>
> 打开方式：PHPStudy 面板 → 软件管理 / 设置 → PHP 扩展 → 勾选 **`mysqli`** → 重启 Apache。
> 不确定的话，访问 `setup.php` 可以一眼看到 `mysqli 扩展` 那一行是不是 ✔。

### 2. 改数据库密码（**你之前报错的原因就在这里**）

打开 `api/db.config.php`，把 `'pass'` 改成你 PHPStudy 里 MySQL 的 root 密码：

```php
return array(
    'host' => '127.0.0.1',
    'port' => '3306',
    'user' => 'root',
    'pass' => '改成你的密码',   // ← 就是这里
    'name' => 'wings',
    'auto_try_common_passwords' => true,
);
```

> 报错 `Access denied for user 'root'@'localhost' (using password: YES)`
> 就是密码不对，或者 PHPStudy 里 MySQL 服务没启动。
> 密码在 **PHPStudy 面板 → 数据库** 里可以看到。
>
> 另外：如果 MySQL 的 root 密码是**空**的，直接把 `'pass'` 写成 `''` 即可，
> 程序不会把空密码当成「没配置」。
>
> 配置不对时，前端页面顶部会自动弹出一条红色的错误提示条，写明原因并给出 `setup.php` 链接，
> 不用再去猜。

### 3. 用配置向导自动搞定（懒人方案）

浏览器访问：

```
http://你的站点地址/setup.php
```

这个页面会：

- 显示 PHP 版本、`ext/mysql` / `mysqli` 是否可用、`FLAG` 是否注入成功等环境信息
- 提供图形界面填写 MySQL 账号密码，**先测试连接，成功才写入** `api/db.config.php`
- 顺手把数据库、`users` 表和 4 个预置账号建好

### 4. 完成

访问站点根目录即可。数据库、表、预置账号都会**自动创建**，不需要手动导入 SQL。

如果自动建表失败，也可以在 PHPStudy 面板里新建数据库 `wings`，然后导入 `db/init.sql`。

### 5. 本地测试 Flag

PHPStudy 下设置环境变量比较麻烦，最简单的办法是在站点根目录建一个 `flag.txt`：

```
D:\phpstudy_pro\WWW\wings\flag.txt   →   内容：flag{test_123}
```

`api/config.php` 里的 `wings_flag()` 会按这个顺序查找：

1. 环境变量 `FLAG` / `GZCTF_FLAG` / `CTF_FLAG` / `DASFLAG`
2. 文件 `/flag`、`/flag.txt`、`/tmp/flag`、`/tmp/flag.txt`、`<站点根>/flag.txt`
3. 都没有 → 返回 `flag{local_debug_no_flag_injected}`

> Apache 下也可以用 `SetEnv FLAG "flag{...}"` 写进 `httpd.conf` 或虚拟主机配置。

---

## 二、题目设计

### 场景

「羽翼同行」是一个鸟类保护公益宣传站，首页醒目位置写着主旨：

> **人或许长不出翅膀，但人可以选择是否同行。**

页面展示 10 种中国国家一级保护鸟类（图片 + 中文名 + 拉丁学名 + 栖息地 + 简介），右上角有登录 / 注册入口。

### 数据库

`users` 表，4 个预置账号：

| id | username    | password                        | 说明 |
|----|-------------|---------------------------------|------|
| 1  | `birdadmin` | `W1ngs@dm1n_2f8c41d9e7b3a6`     | 站点管理员，密码随机强口令，**猜不到** |
| 2  | `linxiaoyu` | `bird2024`                      | 普通志愿者 |
| 3  | `wangkai`   | `wing@123`                      | 普通志愿者 |
| 4  | `zhaoyun`   | `nest2024`                      | 普通志愿者 |

> `birdadmin` 的明文密码写在源码和 `db/init.sql` 里，仅出题人备忘。参赛者拿不到源码，
> 必须通过注入把它的密码改掉。

### 漏洞链路

| 步骤 | 位置 | 代码 | 说明 |
|------|------|------|------|
| ① | `api/register.php` | `$username = mysql_escape_string($_POST['username']);`<br>`INSERT INTO users (username,password) VALUES ('$username','$pass')` | 转义只保证**本次 INSERT** 不被破坏，恶意用户名被**原样存进数据库** |
| ② | `api/login.php` | `mysql_real_escape_string()` 转义用户名和密码<br>`SELECT * FROM users WHERE username='$username' and password='$password'` | 登录**本身不可注入**；但登录成功后 `$_SESSION['username']` = 数据库里的原始用户名 |
| ③ | `api/pass_change.php` | `$username = $_SESSION['username'];`<br>`UPDATE users SET password='$pass' where username='$username' and password='$curr_pass'` | `$username` **未做任何转义**就拼进 SQL → **二次注入触发点** |

### 预期解法

```
1. 注册一个用户名为：  birdadmin'#
   密码随便，比如 123456

2. 用 birdadmin'# / 123456 登录
   （登录查询把 ' 转义了，所以能正常匹配到刚注册的这行）

3. 进入用户中心 → 点击「修改密码」入口（password.html）
   当前密码：随便填（比如 111111，不用填对！）
   新密码：  hacked123

   此时服务端拼出的 SQL 是：
   UPDATE `users` SET `password`='hacked123' where username='birdadmin'#' and password='111111'
                                                                    ↑
                                             # 把后面全部注释掉，实际执行：
   UPDATE `users` SET `password`='hacked123' where username='birdadmin'
   → 管理员密码被改成了 hacked123

   ★ 注意：因为 " and password='...'" 整段被注释掉了，
     连「当前密码校验」都一并消失，当前密码填什么都能成功。
     （已实测验证）

4. 退出登录，用  birdadmin / hacked123  登录

5. 用户中心显示 Flag
```

### 实测结论

在 **PHP 7.3.4 + MySQL 5.7.26**（PHPStudy 自带环境）上完整跑过一遍，30 项断言全部通过：

| 环节 | 结果 |
|------|------|
| 首次访问自动建库 / 建表 / 预置 4 个账号 | ✔ |
| 直接注册 `birdadmin` 被重名拦截 | ✔ |
| 注册 `birdadmin'#` 成功，且**原样**存入数据库 | ✔ |
| 用 `birdadmin'#` 正常登录（登录处注入无效） | ✔ |
| 改密页触发二次注入，`birdadmin` 密码被改写 | ✔ |
| 用新密码登录 `birdadmin` → **拿到 Flag** | ✔ |
| 普通账号改密仍受当前密码校验保护 | ✔ |
| 防「意外捷径」审计（11 项，见下） | ✔ |

### 防「意外捷径」审计

这道题的 Flag 判断是 PHP 严格比较 `$_SESSION['username'] === 'birdadmin'`。
MySQL 的字符串比较有个著名陷阱：非二进制排序规则下 `'birdadmin '`（尾随空格）
和 `'BirdAdmin'`（大小写）都会被 `=` 当成等于 `'birdadmin'`。
如果唯一索引或判断写得不严谨，选手可能不注入就能通关。已逐项实测：

| 尝试 | 结果 |
|------|------|
| 猜测密码直接登录 `birdadmin` | ✘ 401 |
| 登录密码字段注入 `' OR '1'='1` / `UNION SELECT` | ✘ 401（两边都转义了） |
| 注册 `BirdAdmin` / `BIRDADMIN` / `birdadmin `（尾随空格） | ✘ 被唯一索引拦下（排序规则视为重复） |
| 注册 ` birdadmin`（前导空格）/ `birdadmin\t` | ⚠ 注册成功，但 `is_admin` 为 false，**拿不到 Flag** |
| 注册接口注入 payload 后检查 `birdadmin` 密码 | ✘ 密码未被影响 |
| 只伪造 `Auth` cookie（无 session） | ✘ 401 |

结论：**唯一可行路径就是二次注入**。防线是双层的 ——
唯一索引挡住大部分变体，PHP 严格比较兜住剩下的。

Flag 只在 `api/profile.php` 中、且 `$_SESSION['username'] === 'birdadmin'` 时返回：

```php
$is_admin = ($me === 'birdadmin');
'flag' => $is_admin ? wings_flag() : null,
```

---

## 三、Docker / CTFd Whale 部署

> ⚠️ 本机没有 Docker 环境，**这个镜像我没有实际 build 过**。
> 如果你已经有现成容器，直接用你原来的容器 + 本目录的站点文件即可。

单容器：Apache + PHP + MariaDB，应用监听 80 端口。

```bash
docker build -t wings-for-life:latest -f docker/Dockerfile .
docker run -d --name wings -p 80:80 \
  -e FLAG='flag{this_is_the_dynamic_flag}' \
  wings-for-life:latest
```

或本地测试：

```bash
docker compose up --build      # 打开 http://localhost:8080
```

### 动态 Flag 注入

`docker/entrypoint.sh` 同时支持两种方式：

1. **环境变量**（CTFd Whale / GZCTF 默认）：平台把 `FLAG` 传进来，
   entrypoint 会把值同时写一份到 `/flag`；
2. **文件挂载**：直接 `-v /host/flag:/flag`。

`api/config.php` 的 `wings_flag()` 两种都能读到。

### 数据库连接（容器内）

容器内使用独立账号 `wings` / `wingspass`（`wings` 库权限），
通过 Apache `SetEnv` 传给 PHP，也可以用环境变量覆盖：
`DB_HOST` / `DB_PORT` / `DB_USER` / `DB_PASS` / `DB_NAME`。

### 平台侧建议

- 单容器，映射 80 端口
- 环境变量注入 `FLAG`
- 不需要额外暴露数据库端口
- **上线前删掉 `setup.php`**（它能改数据库配置）

---

## 四、观鸟在广西（广西常见鸟类图鉴）

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

## 五、图片版权

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

## 六、FAQ

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
   Docker 镜像默认用 `php:8.2-apache`，想严格用 7.4 见 `docker/Dockerfile` 里的注释。

**Q：访问站点根目录 403 / 目录列表？**
A：`index.php` 会转发到 `index.html`。如果你的服务器连 `index.php` 都不作为目录索引，
   直接把 `index.html` 改名为 `index.php` 也行（它里面没有任何 PHP 代码）。

**Q：怎么改题库里的 Flag 显示位置？**
A：改 `api/profile.php` 里的判断条件即可，例如改成 `$is_admin = ($me === 'birdadmin');`
   以外的人，或把 flag 放到别的接口。

**Q：想让漏洞更难一点？**
A：把 `api/register.php` 里的 `mysql_escape_string` 换成更严格的过滤（如只允许字母数字），
   或者在注册时对用户名长度做限制 —— 但注意别把 `birdadmin'#` 这种 payload 直接堵死，
   否则题目就无解了。
