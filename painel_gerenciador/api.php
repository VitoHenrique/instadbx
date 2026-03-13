<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para responder JSON
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função para autenticar via Basic Auth
function authenticate() {
    global $pdo;

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (!$authHeader || !preg_match('/Basic\s+(.+)$/i', $authHeader, $matches)) {
        return null;
    }

    $decoded = base64_decode($matches[1]);
    if (!$decoded || strpos($decoded, ':') === false) {
        return null;
    }

    list($username, $password) = explode(':', $decoded, 2);

    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? AND is_active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    // Verifica expiração para revendedores
    if ($user['role'] !== 'admin' && $user['expires_at'] && $user['expires_at'] < date('Y-m-d')) {
        return null;
    }

    return $user;
}

// Autenticação obrigatória
$user = authenticate();
if (!$user) {
    registrarLog('api_auth_falha', null, 'Token inválido');
    jsonResponse(['error' => 'Não autorizado. Verifique seu token.'], 401);
}

$isAdmin = $user['role'] === 'admin';

// Rota da API
$action = $_GET['action'] ?? '';

// =====================
// API: Criar Revendedor (apenas admin)
// =====================
if ($action === 'criar_revendedor' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        jsonResponse(['error' => 'Acesso negado. Apenas administradores.'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $username = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($input['username'] ?? '')));
    $nome = trim($input['nome'] ?? '');
    $senha = $input['senha'] ?? '';
    $limiteClientes = (int)($input['limite_clientes'] ?? 10);
    $limitePerfis = (int)($input['limite_perfis'] ?? 50);
    $validade = $input['validade'] ?? null;

    if (!$username || !$senha || !$nome) {
        jsonResponse(['error' => 'Campos obrigatórios: username, nome, senha'], 400);
    }

    if (strlen($username) < 3) {
        jsonResponse(['error' => 'Username deve ter pelo menos 3 caracteres'], 400);
    }

    if (strlen($senha) < 4) {
        jsonResponse(['error' => 'Senha deve ter pelo menos 4 caracteres'], 400);
    }

    try {
        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, name, role, email_limit, perfil_limit, expires_at) VALUES (?, ?, ?, 'reseller', ?, ?, ?)");
        $stmt->execute([$username, $hash, $nome, $limiteClientes, $limitePerfis, $validade]);

        $id = $pdo->lastInsertId();
        $token = base64_encode("$username:$senha");

        registrarLog('api_revendedor_criado', $user['username'], $username);

        jsonResponse([
            'success' => true,
            'message' => 'Revendedor criado com sucesso',
            'data' => [
                'id' => (int)$id,
                'username' => $username,
                'nome' => $nome,
                'limite_clientes' => $limiteClientes,
                'limite_perfis' => $limitePerfis,
                'validade' => $validade,
                'token' => $token
            ]
        ], 201);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Erro: username já existe'], 409);
    }
}

// =====================
// API: Listar Revendedores (apenas admin)
// =====================
if ($action === 'listar_revendedores' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!$isAdmin) {
        jsonResponse(['error' => 'Acesso negado. Apenas administradores.'], 403);
    }

    $stmt = $pdo->query("
        SELECT a.id, a.username, a.name, a.email_limit, a.perfil_limit, a.expires_at, a.is_active, a.created_at,
            (SELECT COUNT(*) FROM emails WHERE reseller_id = a.id) as clientes_usados,
            (SELECT COUNT(*) FROM emails e INNER JOIN perfis p ON e.email = p.email WHERE e.reseller_id = a.id) as perfis_usados
        FROM admins a
        WHERE a.role = 'reseller'
        ORDER BY a.name
    ");

    jsonResponse([
        'success' => true,
        'data' => $stmt->fetchAll()
    ]);
}

// =====================
// API: Criar Cliente (admin ou revendedor)
// =====================
if ($action === 'criar_cliente' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $email = trim($input['email'] ?? '');
    $maxPerfis = max(1, (int)($input['max_perfis'] ?? 1));
    $validade = $input['validade'] ?? null;

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Email inválido'], 400);
    }

    // Verifica limite de clientes do revendedor
    if (!$isAdmin && $user['email_limit'] > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as t FROM emails WHERE reseller_id = ?");
        $stmt->execute([$user['id']]);
        if ($stmt->fetch()['t'] >= $user['email_limit']) {
            jsonResponse(['error' => 'Limite de clientes atingido'], 403);
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO emails (email, max_contas, reseller_id, expires_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$email, $maxPerfis, $user['id'], $validade]);

        $id = $pdo->lastInsertId();

        registrarLog('api_cliente_criado', $user['username'], $email);

        jsonResponse([
            'success' => true,
            'message' => 'Cliente criado com sucesso',
            'data' => [
                'id' => (int)$id,
                'email' => $email,
                'max_perfis' => $maxPerfis,
                'validade' => $validade
            ]
        ], 201);
    } catch (Exception $e) {
        jsonResponse(['error' => 'Erro: email já existe'], 409);
    }
}

// =====================
// API: Listar Clientes
// =====================
if ($action === 'listar_clientes' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($isAdmin) {
        $stmt = $pdo->query("
            SELECT e.id, e.email, e.max_contas, e.expires_at, e.is_active, e.created_at, a.name as revendedor,
                (SELECT COUNT(*) FROM perfis WHERE email = e.email) as perfis_vinculados
            FROM emails e
            LEFT JOIN admins a ON e.reseller_id = a.id
            ORDER BY e.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT e.id, e.email, e.max_contas, e.expires_at, e.is_active, e.created_at,
                (SELECT COUNT(*) FROM perfis WHERE email = e.email) as perfis_vinculados
            FROM emails e
            WHERE e.reseller_id = ?
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$user['id']]);
    }

    jsonResponse([
        'success' => true,
        'data' => $isAdmin ? $stmt->fetchAll() : $stmt->fetchAll()
    ]);
}

// =====================
// API: Estatísticas
// =====================
if ($action === 'stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($isAdmin) {
        $stats = [
            'total_revendedores' => $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'reseller'")->fetchColumn(),
            'total_clientes' => $pdo->query("SELECT COUNT(*) FROM emails")->fetchColumn(),
            'total_perfis' => $pdo->query("SELECT COUNT(*) FROM perfis")->fetchColumn(),
        ];
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM emails WHERE reseller_id = ?");
        $stmt->execute([$user['id']]);
        $clientes = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM perfis p
            INNER JOIN emails e ON p.email = e.email
            WHERE e.reseller_id = ?
        ");
        $stmt->execute([$user['id']]);
        $perfis = $stmt->fetchColumn();

        $stats = [
            'limite_clientes' => $user['email_limit'] ?: 'ilimitado',
            'clientes_usados' => (int)$clientes,
            'clientes_restantes' => $user['email_limit'] ? $user['email_limit'] - $clientes : 'ilimitado',
            'limite_perfis' => $user['perfil_limit'] ?: 'ilimitado',
            'perfis_usados' => (int)$perfis,
            'perfis_restantes' => $user['perfil_limit'] ? $user['perfil_limit'] - $perfis : 'ilimitado',
            'validade' => $user['expires_at']
        ];
    }

    jsonResponse([
        'success' => true,
        'data' => $stats
    ]);
}

// Rota não encontrada
jsonResponse(['error' => 'Endpoint não encontrado. Ações disponíveis: criar_revendedor, listar_revendedores, criar_cliente, listar_clientes, stats'], 404);
