# NimbusVault

基于 PHP 和 MySQL 的网页文件管理系统。用户可以登录后上传、预览、下载、整理文件，管理员可以管理账号和系统设置。

## 功能

- 邮箱登录，区分管理员和普通用户
- 文件夹、上传、重命名、移动、删除和恢复
- 图片、PDF、文本在线预览，其他类型直接下载
- 存储用量和最近操作记录
- 管理员创建用户，并设置允许的文件类型、上传大小和回收站保留天数

## 环境

- PHP 8.1 或更高版本（需要 PDO MySQL）
- MySQL 5.7 或更高版本
- Apache，并开启 `mod_rewrite`（项目用 `.htaccess` 禁止直接访问配置、函数和上传目录）

## 安装

1. 把项目放到网站目录，例如 `htdocs/storage_system`。
2. 复制配置文件：

   ```bash
   copy config\db.local.example.php config\db.local.php
   ```

3. 编辑 `config/db.local.php`，填入本机数据库主机、库名、用户名和密码。`APP_BASE_URL` 要和实际访问路径一致，默认是 `/storage_system`。
4. 用浏览器打开该路径。第一次连接数据库时会自动建库、建表，并写入演示账号。

`config/db.local.php` 和 `storage/` 里的用户文件已加入 `.gitignore`，不会提交到仓库。

## 演示账号

这些账号只用于本地演示，正式使用前请修改密码。

| 角色 | 邮箱 | 密码 |
| --- | --- | --- |
| 管理员 | admin@cloudvault.local | admin123 |
| 普通用户 | demo@cloudvault.local | demo123 |

## 目录

```text
admin.php          管理后台
dashboard.php      文件工作台
login.php          登录
download.php       下载
view.php           预览
config/            数据库连接；真实密码放在 db.local.php
includes/          公共函数
assets/            样式和脚本
storage/           用户上传文件（不入库）
```
