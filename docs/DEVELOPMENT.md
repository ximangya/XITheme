# 开发环境与流程

主题本身零依赖（无 npm、无构建），但建议用本地 WordPress 验证改动。下面这套环境在本机已经跑通。

## 1. 本机环境现状

| 组件 | 位置 / 版本 | 说明 |
| --- | --- | --- |
| PHP | 8.5.8 CLI，`C:\Users\曦芒\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.5_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe` | 已配置 php.ini，见下方「php.ini 是 GBK」 |
| WordPress | 7.1.3，`C:\Users\曦芒\Desktop\曦芒项目\dev-wp\site` | SQLite 驱动，不需要 MySQL |
| wp-cli | `C:\Users\曦芒\Desktop\曦芒项目\dev-wp\_dl\wp-cli.phar` | 用 `php wp-cli.phar` 调用 |
| Chrome | `C:\Program Files\Google\Chrome\Application\chrome.exe` | 用于无头截图验收 |
| Node / pnpm / git | 系统已装 | Node 只用于临时脚本，主题不依赖 |

开发站的关键约定：

- 站点地址 `http://127.0.0.1:8788`（**不要用 8080**，本机 8080 被某个 CEF 应用的调试端口占着）。
- 后台账号 `admin` / `admin123`；`WP_DEBUG=true`，日志在 `dev-wp\site\wp-content\debug.log`。
- `dev-wp\site\wp-content\themes\xi` 是指向本仓库 `xi\` 的**目录联接（Junction）**，改仓库文件 = 改站点主题，无需复制。

## 2. 启动与停止

```powershell
# 启动（后台跑，日志写到 dev-wp\server.log）
cd C:\Users\曦芒\Desktop\曦芒项目
Start-Process php -ArgumentList '-S','127.0.0.1:8788','-t','dev-wp\site' `
  -RedirectStandardOutput dev-wp\server.out.log -RedirectStandardError dev-wp\server.err.log
```

```powershell
# 确认站点可访问（最可靠的检查）
(Invoke-WebRequest http://127.0.0.1:8788/ -UseBasicParsing).StatusCode
# 端口被占时排查（普通权限下可能看不到其他用户的监听，需管理员）
Get-NetTCPConnection -LocalPort 8788 -ErrorAction SilentlyContinue
```

停止：关掉 `php -S` 进程（`Get-Process php | Stop-Process`，注意别误杀别的 PHP）。`php -S` 是单进程，**并发请求会排队**，压测/截图时不要同时开多个页面任务。

## 3. wp-cli 常用命令

```powershell
$wp = "php dev-wp\_dl\wp-cli.phar --path=dev-wp\site"
Invoke-Expression "$wp option get blogname"
Invoke-Expression "$wp post list --post_type=post --fields=ID,post_title,post_status"
Invoke-Expression "$wp theme list --fields=name,status,version"
Invoke-Expression "$wp plugin list"
Invoke-Expression "$wp rewrite flush --hard"
```

注意：

- wp-cli 在 PHP 8.5 下会打印无害的 `Deprecated:`（来自 phar 内的 php-cli-tools / react/promise），**不影响执行结果**，可忽略或 `2>$null`。
- 传含空格或中文的参数时用 `--opt="值"` 形式，经 `Start-Process` 调用时尤其容易被拆参（曾出现 `Error: Too many positional arguments`）。
- `wp eval-file` 可跑任意脚本，示例内容脚本就是 `dev-wp\seed.php`。

## 4. 示例内容 / 重置数据库

站点数据在 `dev-wp\site\wp-content\database\.ht.<hash>\.ht.sqlite`（SQLite 单文件）。

```powershell
# 重建示例内容（文章、页面、菜单、站点副标题），可重复执行，脚本自己判重
php dev-wp\_dl\wp-cli.phar --path=dev-wp\site eval-file dev-wp\seed.php
```

彻底重装（会丢数据）：

```powershell
# 停掉 php -S 后
Remove-Item -Recurse -Force dev-wp\site\wp-content\database
# 再启动，然后
php dev-wp\_dl\wp-cli.phar --path=dev-wp\site core install --url=http://127.0.0.1:8788 `
  --title=XIDev --admin_user=admin --admin_password=admin123 `
  --admin_email=dev@example.com --skip-email
php dev-wp\_dl\wp-cli.phar --path=dev-wp\site theme activate xi
```

`seed.php` 创建的内容：4 篇中文文章 + Hello world、4 个页面（关于本站 / 用户中心 `user-center.php` / 写文章 `write.php` / 论坛 `bbs-new.php`）、菜单「主导航」挂 `primary`、`posts_per_page=6`。测试商城/论坛前先确认对应开关和页面模板已就位。

## 5. 视觉验收（截图）

```powershell
& 'C:\Program Files\Google\Chrome\Application\chrome.exe' --headless=new --disable-gpu `
  --hide-scrollbars --window-size=1440,2000 --virtual-time-budget=8000 `
  --screenshot=C:\Users\曦芒\Desktop\曦芒项目\dev-wp\shot-home.png http://127.0.0.1:8788/
```

- 截图前先确认 `php -S` 正在跑，且页面返回 200（截图空白基本都是服务器没起来）。
- 深浅色都要看：主题在 `localStorage.xi-theme` 存选择，无头模式默认跟随系统；要强制深色可在 URL 后无参数情况下改设置里的默认模式，或用 `--force-dark-mode` 试（不保证等同）。
- 移动端用 `--window-size=390,844`。
- 玻璃效果在 `--disable-gpu` 下 Chrome 仍会做软件合成，可正常渲染；但**不要**用 `--disable-blink-features` 关掉 backdrop-filter 来验证，那会得到错误结论。

## 6. 打包发布

```powershell
cd C:\Users\曦芒\Desktop\曦芒项目
python build_xi.py
```

- 源目录 `xi\`，输出 `XRes\xi-<Version>.zip`（zip 内根目录名 `xi/`）。
- 自动排除 `.git`、`.github`、`samples`、`__pycache__`、`.gitignore`、`.DS_Store`、`Thumbs.db`、`*.zip`、`*.log`。
- `--screenshot` 参数会用 Pillow 重绘 1200×900 封面图。

发版步骤：

1. `Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }` 全绿。
2. 更新 `CHANGELOG.md`（新增一节，写清新增/修复/兼容性）。
3. 同步版本号：`style.css` 的 `Version:` 与 `functions.php` 的 `XI_BUILD`。
   - 对外版本 `V1.x`；`XI_BUILD` 是 7 位内部构建号（当前 `2610110`，形如 `年-月-日-序号`），**每次发版必须递增**，它参与资源 URL，用于强制刷新缓存。
4. `python build_xi.py` 出 zip，安装到干净站点冒烟一遍。
5. 提交并打 tag：`git tag V1.1 && git push origin main --tags`。

## 7. Git 工作流

- `origin` = `https://github.com/ximangya/XITheme.git`，主分支 `main`。
- 提交信息用中文、一句话说清「做了什么」，例如 `修复深色模式下信息条对比度不足`。
- 提交前确认 `git status` 只包含本次改动的文件；`inc/codestar-framework/samples/` 已被 gitignore，不要强加。
- 主题目录与上游 `XITheme` 同源，保持 `main` 可直接推送。
- 换行：仓库里存在 CRLF（Windows 端编辑导致），`git` 会提示 `LF will be replaced by CRLF`，属正常，不要批量转换（会污染 diff）。

## 8. 排障

### php.ini 必须是 GBK 编码（最容易踩的坑）

PHP 在 Windows 上按系统 ANSI（GBK/936）解码 php.ini 的字节。用 UTF-8 写含中文的 `extension_dir`，路径会变成 `C:\Users\鏇﹁姃\...`，于是所有扩展报 `Unable to load dynamic library`。修复：

```powershell
$ini = Get-Content -Raw -Encoding UTF8 'C:\path\to\php.ini'
[System.IO.File]::WriteAllText('C:\path\to\php.ini', $ini, [System.Text.Encoding]::GetEncoding(936))
```

验证：`php -m` 里应出现 `mysqli, mbstring, curl, gd, zip, openssl, pdo_sqlite`；`php -i | Select-String extension_dir` 路径显示乱码是控制台编码问题，不影响 PHP 加载。

### 常用检查

```powershell
php -m                                   # 已加载扩展
php --ini                                # 用的是哪个 ini
php -l .\inc\functions\xi-bbs.php        # 单文件语法
Get-Content dev-wp\site\wp-content\debug.log -Tail 50   # PHP 错误
(Invoke-WebRequest http://127.0.0.1:8788/ -UseBasicParsing).StatusCode  # 站点是否活着
```

- `debug.log` 里的 `phar://...wp-cli.phar...Deprecated` 是 wp-cli 自身的问题，不是主题 bug；清空即可。
- 页面白屏：先看 `debug.log`，再看 `server.err.log`；`XI_SAFE_MODE` 可在主题内兜底关闭危险模块。
- 改了 CSS 不生效：确认不是浏览器缓存（资源 URL 带 mtime，正常会自动变）；`php -S` 不缓存 PHP 输出。
- 后台设置保存后前台没变：检查是否走了 CSF 的 Ajax 保存（`csf_xi_options`），以及 `_xz()` 的静态缓存是否在同一请求里已读过旧值。

### 环境备注

- 本机 8080 端口被占用（CEF 远程调试），所以开发站用 8788。
- `Start-Process php -ArgumentList ...` 传中文参数可行，但参数里有空格必须写成单个字符串，否则会被拆开。
- 读取长输出时不要 `| Select-Object -First N` 接在后台进程流上（会让任务看起来挂住），用 `-RedirectStandardOutput` 落盘再读。
