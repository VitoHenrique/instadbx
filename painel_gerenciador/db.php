<?php
require_once __DIR__ . '/config.php';

if (!is_dir(dirname(DB_PATH))) {
    mkdir(dirname(DB_PATH), 0755, true);
}

$pdo = new PDO('sqlite:' . DB_PATH);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Cria tabelas
$pdo->exec("
    CREATE TABLE IF NOT EXISTS admins (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        name TEXT NOT NULL,
        role TEXT DEFAULT 'reseller',
        email_limit INTEGER DEFAULT 0,
        perfil_limit INTEGER DEFAULT 0,
        expires_at DATE,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS emails (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT UNIQUE NOT NULL,
        max_contas INTEGER DEFAULT 1,
        reseller_id INTEGER NOT NULL,
        expires_at DATE,
        is_active INTEGER DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS perfis (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        instagram TEXT UNIQUE NOT NULL,
        email TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tipo TEXT NOT NULL,
        usuario TEXT,
        ip TEXT,
        detalhes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
");

// Migrações
try { $pdo->exec("ALTER TABLE emails ADD COLUMN expires_at DATE"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE emails ADD COLUMN is_active INTEGER DEFAULT 1"); } catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE admins ADD COLUMN perfil_limit INTEGER DEFAULT 0"); } catch (Exception $e) {}

// Admin padrão
$stmt = $pdo->prepare("SELECT id FROM admins WHERE username = 'admin'");
$stmt->execute();
if (!$stmt->fetch()) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO admins (username, password_hash, name, role, email_limit) VALUES ('admin', ?, 'Administrador', 'admin', 0)")
        ->execute([$hash]);
}

// Função para registrar log
function registrarLog($tipo, $usuario = null, $detalhes = null) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO logs (tipo, usuario, ip, detalhes) VALUES (?, ?, ?, ?)");
    $stmt->execute([$tipo, $usuario, $ip, $detalhes]);
}

// Função para verificar se revendedor está válido
function revendedorValido($reseller_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at >= DATE('now'))");
    $stmt->execute([$reseller_id]);
    return $stmt->fetch() ? true : false;
}
