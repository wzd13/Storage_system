<?php

declare(strict_types=1);

session_start();

$localConfig = __DIR__ . DIRECTORY_SEPARATOR . 'db.local.php';
if (!is_file($localConfig)) {
    http_response_code(500);
    exit('Missing config/db.local.php. Copy config/db.local.example.php and fill in your database settings.');
}

require_once $localConfig;

const APP_BASE_PATH = __DIR__ . DIRECTORY_SEPARATOR . '..';
const STORAGE_ROOT = APP_BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR;

function appConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        $basePdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $basePdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

function seedDefaultData(): void
{
    $pdo = appConnection();

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(80) NOT NULL,
            last_name VARCHAR(80) NOT NULL,
            email VARCHAR(120) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('admin','user') NOT NULL DEFAULT 'user',
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            storage_quota BIGINT UNSIGNED NOT NULL DEFAULT 10737418240,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS folders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            parent_id INT UNSIGNED NULL,
            name VARCHAR(150) NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_folders_user_parent (user_id, parent_id),
            CONSTRAINT fk_folders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_folders_parent FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS files (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            folder_id INT UNSIGNED NULL,
            name VARCHAR(150) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(150) NOT NULL,
            size_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
            storage_path VARCHAR(255) NOT NULL,
            version_count INT UNSIGNED NOT NULL DEFAULT 1,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            deleted_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_files_user_folder (user_id, folder_id),
            CONSTRAINT fk_files_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_files_folder FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS file_versions (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_id INT UNSIGNED NOT NULL,
            version_name VARCHAR(255) NOT NULL,
            storage_path VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_file_versions_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS tags (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            name VARCHAR(80) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS file_tags (
            file_id INT UNSIGNED NOT NULL,
            tag_id INT UNSIGNED NOT NULL,
            PRIMARY KEY (file_id, tag_id),
            CONSTRAINT fk_file_tags_file FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
            CONSTRAINT fk_file_tags_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shares (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            owner_user_id INT UNSIGNED NOT NULL,
            entity_type ENUM('file','folder') NOT NULL,
            entity_id INT UNSIGNED NOT NULL,
            access_token VARCHAR(64) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_shares_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activities (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_activities_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(80) PRIMARY KEY,
            setting_value TEXT NOT NULL
        ) ENGINE=InnoDB
    ");

    $adminExists = $pdo->query("SELECT id FROM users WHERE email = 'admin@cloudvault.local' LIMIT 1")->fetch();
    if (!$adminExists) {
        $adminHash = password_hash('admin123', PASSWORD_DEFAULT);
        $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role, status, storage_quota) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute(['Admin', 'User', 'admin@cloudvault.local', $adminHash, 'admin', 'active', 107374182400]);

        $demoHash = password_hash('demo123', PASSWORD_DEFAULT);
        $statement = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password_hash, role, status, storage_quota) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute(['Demo', 'Member', 'demo@cloudvault.local', $demoHash, 'user', 'active', 5368709120]);
    }

    $defaultSettings = [
        'allowed_extensions' => 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,zip,rar,txt,csv,mp4,mp3',
        'max_upload_size' => '104857600',
        'trash_retention_days' => '30',
    ];

    foreach ($defaultSettings as $key => $value) {
        $check = $pdo->prepare('SELECT setting_key FROM settings WHERE setting_key = :key');
        $check->execute([':key' => $key]);

        if (!$check->fetch()) {
            $insert = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)');
            $insert->execute([':key' => $key, ':value' => $value]);
        }
    }
}

seedDefaultData();
