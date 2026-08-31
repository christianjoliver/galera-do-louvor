<?php
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_file = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO('sqlite:' . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create tables if they do not exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            type TEXT NOT NULL,
            tone TEXT,
            video_url TEXT,
            chord_url TEXT
        );

        CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date DATE NOT NULL,
            description TEXT
        );

        CREATE TABLE IF NOT EXISTS event_songs (
            event_id INTEGER,
            song_id INTEGER,
            FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY(song_id) REFERENCES songs(id) ON DELETE CASCADE,
            PRIMARY KEY(event_id, song_id)
        );

        CREATE TABLE IF NOT EXISTS roles (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user' -- 'admin' or 'user'
        );

        CREATE TABLE IF NOT EXISTS user_roles (
            user_id INTEGER,
            role_id INTEGER,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE,
            PRIMARY KEY(user_id, role_id)
        );

        CREATE TABLE IF NOT EXISTS absences (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            date DATE NOT NULL,
            reason TEXT,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        
        -- New table for event roles linked to actual users
        CREATE TABLE IF NOT EXISTS event_user_roles (
            event_id INTEGER,
            role_id INTEGER,
            user_id INTEGER,
            FOREIGN KEY(event_id) REFERENCES events(id) ON DELETE CASCADE,
            FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            PRIMARY KEY(event_id, role_id, user_id)
        );
    ");

    // Migration: add tone column if not present (for existing databases)
    try {
        $pdo->exec("ALTER TABLE songs ADD COLUMN tone TEXT");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }

    // Insert default roles if table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    if ($stmt->fetchColumn() == 0) {
        $default_roles = [
            ['Baterista', 'Instrumental'],
            ['Baixista', 'Instrumental'],
            ['Guitarrista', 'Instrumental'],
            ['Violonista', 'Instrumental'],
            ['Tecladista', 'Instrumental'],
            ['Vocalista - Ministrante', 'Vocal'],
            ['Vocalista - Back 1', 'Vocal'],
            ['Vocalista - Back 2', 'Vocal']
        ];
        
        $insert = $pdo->prepare("INSERT INTO roles (name, category) VALUES (?, ?)");
        foreach ($default_roles as $role) {
            $insert->execute($role);
        }
    }

    // Insert default admin if users table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, password_hash, role) VALUES ('Administrador', 'admin', ?, 'admin')")->execute([$hash]);
    }

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Auth Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die("Acesso negado. Apenas administradores podem acessar esta página.");
    }
}
