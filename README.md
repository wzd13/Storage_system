# NimbusVault

A web file manager built with PHP and MySQL. Users sign in to upload, preview, download, and organize files. Administrators manage accounts and system settings.

## Features

- Email login with separate admin and user roles
- Folders, upload, rename, move, delete, and restore
- In-browser preview for images, PDFs, and text; other types download directly
- Storage usage and recent activity
- Admin tools to create users and set allowed file types, upload size, and trash retention

## Requirements

- PHP 8.1 or newer (PDO MySQL)
- MySQL 5.7 or newer
- Apache with `mod_rewrite` (`.htaccess` blocks direct access to config, includes, and uploads)

## Setup

1. Place the project in your web root, for example `htdocs/storage_system`.
2. Copy the config file:

   ```bash
   copy config\db.local.example.php config\db.local.php
   ```

3. Edit `config/db.local.php` and set your database host, name, user, and password. `APP_BASE_URL` must match the URL path. The default is `/storage_system`.
4. Open that path in a browser. The first connection creates the database, tables, and demo accounts.

`config/db.local.php` and uploaded files in `storage/` are listed in `.gitignore` and are not committed.

## Demo accounts

These accounts are for local demos only. Change the passwords before real use.

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@cloudvault.local | admin123 |
| User | demo@cloudvault.local | demo123 |

## Layout

```text
admin.php          Admin panel
dashboard.php      File workspace
login.php          Sign in
download.php       Download
view.php           Preview
config/            Database connection; real credentials live in db.local.php
includes/          Shared functions
assets/            Styles and scripts
storage/           User uploads (not committed)
```
