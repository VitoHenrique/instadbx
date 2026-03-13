<?php
require_once __DIR__ . '/../db.php';

// =====================
// Flash Messages (PRG Pattern)
// =====================
function setFlash($msg, $tipo = 'info') {
    $_SESSION['flash_msg'] = $msg;
    $_SESSION['flash_tipo'] = $tipo;
}

$flashMsg = $_SESSION['flash_msg'] ?? '';
$flashTipo = $_SESSION['flash_tipo'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

// Logout
if (isset($_GET['logout'])) {
    registrarLog('logout', $_SESSION['user']['username'] ?? '');
    unset($_SESSION['user']);
    header('Location: index.php');
    exit;
}

// Login
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($_POST['password'], $user['password_hash'])) {
        if (!$user['is_active']) {
            $erro = 'Conta desativada.';
            registrarLog('login_falha', $username, 'Conta desativada');
        } elseif ($user['role'] !== 'admin' && $user['expires_at'] && $user['expires_at'] < date('Y-m-d')) {
            $erro = 'Conta expirada.';
            registrarLog('login_falha', $username, 'Conta expirada');
        } else {
            $_SESSION['user'] = $user;
            $_SESSION['api_token'] = base64_encode($username . ':' . $_POST['password']);
            registrarLog('login_ok', $username);
            header('Location: index.php');
            exit;
        }
    } else {
        $erro = 'Usuário ou senha inválidos.';
        registrarLog('login_falha', $username ?? '', 'Credenciais inválidas');
    }
}

// Tela de login
if (!isset($_SESSION['user'])) {
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Painel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass { background: rgba(30, 30, 46, 0.8); backdrop-filter: blur(10px); }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 flex items-center justify-center p-4">
    <div class="glass p-8 rounded-2xl shadow-2xl w-full max-w-sm border border-white/10">
        <div class="text-center mb-8">
            <div class="w-14 h-14 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center mx-auto mb-4">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white">Painel Admin</h1>
            <p class="text-gray-400 text-sm mt-1">Faça login para continuar</p>
        </div>
        <?php if ($erro): ?>
        <div class="bg-red-500/20 text-red-400 p-3 rounded-lg mb-4 text-sm flex items-center gap-2 border border-red-500/30">
            <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
            <?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <div>
                <input type="text" name="username" placeholder="Usuário" required autofocus
                       class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-all">
            </div>
            <div>
                <input type="password" name="password" placeholder="Senha" required
                       class="w-full px-4 py-3 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 focus:outline-none transition-all">
            </div>
            <button type="submit" name="login" value="1"
                    class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:from-purple-700 hover:to-indigo-700 font-semibold transition-all shadow-lg shadow-purple-500/25">
                Entrar
            </button>
        </form>
    </div>
</body>
</html>
<?php exit; }

// Atualiza dados do usuário da sessão com dados frescos do banco
$stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$user = $stmt->fetch();
$_SESSION['user'] = $user;

$isAdmin = $user['role'] === 'admin';
$aba = $_GET['aba'] ?? ($isAdmin ? 'revendedores' : 'clientes');

// =====================
// AÇÕES (POST) - Todas redirecionam após processar
// =====================

// Criar revendedor
if ($isAdmin && isset($_POST['criar_revendedor'])) {
    $username = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($_POST['r_username'])));
    $nome = trim($_POST['r_nome']);
    $senha = $_POST['r_senha'];
    $limite = (int)$_POST['r_limite'];
    $limitePerfis = (int)$_POST['r_limite_perfis'];
    $validade = $_POST['r_validade'] ?: null;

    if ($username && $senha && $nome) {
        try {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password_hash, name, role, email_limit, perfil_limit, expires_at) VALUES (?, ?, ?, 'reseller', ?, ?, ?)");
            $stmt->execute([$username, $hash, $nome, $limite, $limitePerfis, $validade]);
            setFlash('Revendedor criado com sucesso!', 'success');
            registrarLog('revendedor_criado', $user['username'], $username);
        } catch (Exception $e) {
            setFlash('Erro: usuário já existe.', 'error');
        }
    }
    header('Location: ?aba=revendedores');
    exit;
}

// Editar revendedor
if ($isAdmin && isset($_POST['editar_revendedor'])) {
    $id = (int)$_POST['id'];
    $nome = trim($_POST['e_nome']);
    $limite = (int)$_POST['e_limite'];
    $limitePerfis = (int)$_POST['e_limite_perfis'];
    $validade = $_POST['e_validade'] ?: null;
    $ativo = isset($_POST['e_ativo']) ? 1 : 0;
    $novaSenha = $_POST['e_senha'];

    $stmt = $pdo->prepare("UPDATE admins SET name = ?, email_limit = ?, perfil_limit = ?, expires_at = ?, is_active = ? WHERE id = ? AND role = 'reseller'");
    $stmt->execute([$nome, $limite, $limitePerfis, $validade, $ativo, $id]);

    if ($novaSenha) {
        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE admins SET password_hash = ? WHERE id = ?")->execute([$hash, $id]);
    }

    setFlash('Revendedor atualizado!', 'success');
    registrarLog('revendedor_editado', $user['username'], "ID: $id");
    header('Location: ?aba=revendedores');
    exit;
}

// Toggle revendedor
if ($isAdmin && isset($_POST['toggle_revendedor'])) {
    $id = (int)$_POST['id'];
    $stmt = $pdo->prepare("SELECT is_active, username FROM admins WHERE id = ? AND role = 'reseller'");
    $stmt->execute([$id]);
    $rev = $stmt->fetch();

    if ($rev) {
        $novoStatus = $rev['is_active'] ? 0 : 1;
        $pdo->prepare("UPDATE admins SET is_active = ? WHERE id = ?")->execute([$novoStatus, $id]);
        setFlash($novoStatus ? 'Revendedor ativado!' : 'Revendedor desativado!', 'success');
        registrarLog($novoStatus ? 'revendedor_ativado' : 'revendedor_desativado', $user['username'], $rev['username']);
    }
    header('Location: ?aba=revendedores');
    exit;
}

// Excluir revendedor
if ($isAdmin && isset($_POST['excluir_revendedor'])) {
    $id = (int)$_POST['id'];
    $pdo->prepare("DELETE FROM admins WHERE id = ? AND role = 'reseller'")->execute([$id]);
    $pdo->prepare("DELETE FROM emails WHERE reseller_id = ?")->execute([$id]);
    setFlash('Revendedor excluído!', 'success');
    registrarLog('revendedor_excluido', $user['username'], "ID: $id");
    header('Location: ?aba=revendedores');
    exit;
}

// Criar cliente
if (isset($_POST['criar_cliente'])) {
    $email = trim($_POST['email']);
    $max = max(1, (int)$_POST['max_contas']);
    $validade = $_POST['email_validade'] ?? null;
    $validade = $validade ?: null;

    $podeAdicionar = true;
    if (!$isAdmin && $user['email_limit'] > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as t FROM emails WHERE reseller_id = ?");
        $stmt->execute([$user['id']]);
        if ($stmt->fetch()['t'] >= $user['email_limit']) {
            setFlash('Seu limite de clientes foi atingido!', 'error');
            $podeAdicionar = false;
        }
    }

    if ($podeAdicionar && $email) {
        try {
            $stmt = $pdo->prepare("INSERT INTO emails (email, max_contas, reseller_id, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$email, $max, $user['id'], $validade]);
            setFlash('Cliente cadastrado com sucesso!', 'success');
            registrarLog('cliente_criado', $user['username'], $email);
        } catch (Exception $e) {
            setFlash('Erro: email já existe.', 'error');
        }
    }
    header('Location: ?aba=clientes');
    exit;
}

// Editar cliente
if (isset($_POST['editar_cliente'])) {
    $id = (int)$_POST['id'];
    $max = max(1, (int)$_POST['edit_max_contas']);
    $validade = $_POST['edit_validade'] ?? null;
    $validade = $validade ?: null;
    $ativo = isset($_POST['edit_ativo']) ? 1 : 0;

    $cond = $isAdmin ? "" : " AND reseller_id = " . $user['id'];
    $stmt = $pdo->prepare("UPDATE emails SET max_contas = ?, expires_at = ?, is_active = ? WHERE id = ? $cond");
    $stmt->execute([$max, $validade, $ativo, $id]);

    setFlash('Cliente atualizado!', 'success');
    registrarLog('cliente_editado', $user['username'], "ID: $id");
    header('Location: ?aba=clientes');
    exit;
}

// Toggle cliente
if (isset($_POST['toggle_cliente'])) {
    $id = (int)$_POST['id'];
    $cond = $isAdmin ? "" : " AND reseller_id = " . $user['id'];

    $stmt = $pdo->prepare("SELECT is_active, email FROM emails WHERE id = ? $cond");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();

    if ($cliente) {
        $novoStatus = $cliente['is_active'] ? 0 : 1;
        $pdo->prepare("UPDATE emails SET is_active = ? WHERE id = ?")->execute([$novoStatus, $id]);
        setFlash($novoStatus ? 'Cliente ativado!' : 'Cliente desativado!', 'success');
        registrarLog($novoStatus ? 'cliente_ativado' : 'cliente_desativado', $user['username'], $cliente['email']);
    }
    header('Location: ?aba=clientes');
    exit;
}

// Excluir cliente
if (isset($_POST['excluir_cliente'])) {
    $id = (int)$_POST['id'];
    $cond = $isAdmin ? "" : " AND reseller_id = " . $user['id'];

    $stmt = $pdo->prepare("SELECT email FROM emails WHERE id = ? $cond");
    $stmt->execute([$id]);
    $emailDel = $stmt->fetch();

    if ($emailDel) {
        $pdo->prepare("DELETE FROM perfis WHERE email = ?")->execute([$emailDel['email']]);
        $pdo->exec("DELETE FROM emails WHERE id = $id");
        setFlash('Cliente excluído!', 'success');
        registrarLog('cliente_excluido', $user['username'], $emailDel['email']);
    }
    header('Location: ?aba=clientes');
    exit;
}

// Excluir perfil
if (isset($_POST['excluir_perfil'])) {
    $id = (int)$_POST['id'];
    $cond = $isAdmin ? "1=1" : "e.reseller_id = " . $user['id'];

    $stmt = $pdo->prepare("SELECT p.instagram FROM perfis p INNER JOIN emails e ON p.email = e.email WHERE p.id = ? AND $cond");
    $stmt->execute([$id]);
    $perfil = $stmt->fetch();

    if ($perfil) {
        $pdo->prepare("DELETE FROM perfis WHERE id = ?")->execute([$id]);
        setFlash('Perfil removido!', 'success');
        registrarLog('perfil_excluido', $user['username'], '@' . $perfil['instagram']);
    }
    header('Location: ?aba=clientes');
    exit;
}

// =====================
// DADOS (GET)
// =====================

// Revendedores
$revendedores = [];
if ($isAdmin) {
    $revendedores = $pdo->query("
        SELECT a.*,
            (SELECT COUNT(*) FROM emails WHERE reseller_id = a.id) as emails_usados,
            (SELECT COUNT(*) FROM emails e INNER JOIN perfis p ON e.email = p.email WHERE e.reseller_id = a.id) as perfis_total
        FROM admins a
        WHERE a.role = 'reseller'
        ORDER BY a.name
    ")->fetchAll();
}

// Clientes
if ($isAdmin) {
    $clientes = $pdo->query("
        SELECT e.*, a.name as revendedor,
            (SELECT COUNT(*) FROM perfis WHERE email = e.email) as vinculados
        FROM emails e
        LEFT JOIN admins a ON e.reseller_id = a.id
        ORDER BY e.created_at DESC
    ")->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT e.*,
            (SELECT COUNT(*) FROM perfis WHERE email = e.email) as vinculados
        FROM emails e
        WHERE e.reseller_id = ?
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $clientes = $stmt->fetchAll();
}

// Estatísticas
$stmt = $pdo->prepare("SELECT COUNT(*) as t FROM emails WHERE reseller_id = ?");
$stmt->execute([$user['id']]);
$meusClientes = $stmt->fetch()['t'];

// Estatísticas de perfis do revendedor
$stmt = $pdo->prepare("
    SELECT COUNT(*) as t FROM perfis p
    INNER JOIN emails e ON p.email = e.email
    WHERE e.reseller_id = ?
");
$stmt->execute([$user['id']]);
$meusPerfis = $stmt->fetch()['t'];

// Cliente para edição
$editarCliente = null;
if (isset($_GET['editar_cliente'])) {
    $cond = $isAdmin ? "" : " AND reseller_id = " . $user['id'];
    $stmt = $pdo->prepare("SELECT * FROM emails WHERE id = ? $cond");
    $stmt->execute([(int)$_GET['editar_cliente']]);
    $editarCliente = $stmt->fetch();
}

// Logs com filtros e paginação
$logs = [];
$totalLogs = 0;
$totalPaginas = 1;
$logsPorPagina = 50;
$paginaAtual = max(1, (int)($_GET['pagina'] ?? 1));
$filtroTipo = $_GET['tipo'] ?? '';
$filtroUsuario = $_GET['usuario'] ?? '';
$filtroData = $_GET['data'] ?? '';
$tiposLogs = [];

if ($isAdmin && $aba === 'logs') {
    $where = [];
    $params = [];

    if ($filtroTipo) {
        $where[] = "tipo LIKE ?";
        $params[] = "%$filtroTipo%";
    }
    if ($filtroUsuario) {
        $where[] = "usuario LIKE ?";
        $params[] = "%$filtroUsuario%";
    }
    if ($filtroData) {
        $where[] = "DATE(created_at) = ?";
        $params[] = $filtroData;
    }

    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    $stmt = $pdo->prepare("SELECT COUNT(*) as t FROM logs $whereSql");
    $stmt->execute($params);
    $totalLogs = $stmt->fetch()['t'];
    $totalPaginas = max(1, ceil($totalLogs / $logsPorPagina));
    $paginaAtual = min($paginaAtual, $totalPaginas);
    $offset = ($paginaAtual - 1) * $logsPorPagina;

    $stmt = $pdo->prepare("SELECT * FROM logs $whereSql ORDER BY created_at DESC LIMIT $logsPorPagina OFFSET $offset");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $tiposLogs = $pdo->query("SELECT DISTINCT tipo FROM logs ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
}

// Revendedor para edição
$editarRev = null;
if ($isAdmin && isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ? AND role = 'reseller'");
    $stmt->execute([(int)$_GET['editar']]);
    $editarRev = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .sidebar { transition: transform 0.3s ease; }
        .sidebar-overlay { transition: opacity 0.3s ease; }
        @media (max-width: 1024px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
        }
        .card { background: rgba(30, 30, 46, 0.6); backdrop-filter: blur(10px); }
        .btn { transition: all 0.2s ease; }
        .btn:active { transform: scale(0.98); }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 min-h-screen text-gray-100">
    <!-- Mobile Menu Button -->
    <button id="menuBtn" class="lg:hidden fixed top-4 left-4 z-50 p-2 bg-purple-600 rounded-lg shadow-lg">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>

    <!-- Sidebar Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay fixed inset-0 bg-black/50 z-40 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed top-0 left-0 h-full w-64 bg-slate-900/95 border-r border-white/10 z-40 lg:translate-x-0">
        <div class="p-6">
            <div class="flex items-center gap-3 mb-8">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-white">Painel Admin</h1>
                    <span class="text-xs text-gray-500"><?= $isAdmin ? 'Administrador' : 'Revendedor' ?></span>
                </div>
            </div>

            <nav class="space-y-2">
                <?php if ($isAdmin): ?>
                <a href="?aba=revendedores" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $aba === 'revendedores' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:bg-white/5 hover:text-white' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Revendedores
                </a>
                <?php endif; ?>

                <a href="?aba=clientes" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $aba === 'clientes' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:bg-white/5 hover:text-white' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Clientes
                    <?php if (!$isAdmin): ?>
                    <div class="ml-auto flex gap-1">
                        <?php if ($user['email_limit']): ?>
                        <span class="text-xs bg-white/10 px-2 py-0.5 rounded-full"><?= $meusClientes ?>/<?= $user['email_limit'] ?></span>
                        <?php endif; ?>
                        <?php if ($user['perfil_limit']): ?>
                        <span class="text-xs bg-pink-500/20 text-pink-400 px-2 py-0.5 rounded-full">@<?= $meusPerfis ?>/<?= $user['perfil_limit'] ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </a>

                <a href="?aba=api" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $aba === 'api' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:bg-white/5 hover:text-white' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                    </svg>
                    API
                </a>

                <?php if ($isAdmin): ?>
                <a href="?aba=logs" class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all <?= $aba === 'logs' ? 'bg-purple-600 text-white' : 'text-gray-400 hover:bg-white/5 hover:text-white' ?>">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    Logs
                </a>
                <?php endif; ?>
            </nav>
        </div>

        <!-- User Info -->
        <div class="absolute bottom-0 left-0 right-0 p-6 border-t border-white/10">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-sm font-bold">
                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-white truncate max-w-[120px]"><?= htmlspecialchars($user['name']) ?></p>
                        <?php if (!$isAdmin && $user['expires_at']): ?>
                        <p class="text-xs <?= $user['expires_at'] < date('Y-m-d', strtotime('+7 days')) ? 'text-red-400' : 'text-gray-500' ?>">
                            Exp: <?= date('d/m/Y', strtotime($user['expires_at'])) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="?logout=1" class="p-2 text-gray-400 hover:text-red-400 transition-colors" title="Sair">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="lg:ml-64 min-h-screen">
        <div class="p-4 lg:p-8">
            <!-- Flash Message -->
            <?php if ($flashMsg): ?>
            <div id="flash" class="mb-6 p-4 rounded-xl flex items-center gap-3 <?= $flashTipo === 'success' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : ($flashTipo === 'error' ? 'bg-red-500/20 text-red-400 border border-red-500/30' : 'bg-blue-500/20 text-blue-400 border border-blue-500/30') ?>">
                <?php if ($flashTipo === 'success'): ?>
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <?php elseif ($flashTipo === 'error'): ?>
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <?php endif; ?>
                <span><?= htmlspecialchars($flashMsg) ?></span>
                <button onclick="this.parentElement.remove()" class="ml-auto text-current opacity-70 hover:opacity-100">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                </button>
            </div>
            <?php endif; ?>

            <!-- ===================== ABA REVENDEDORES ===================== -->
            <?php if ($isAdmin && $aba === 'revendedores'): ?>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white">Revendedores</h2>
                <p class="text-gray-400">Gerencie os revendedores do sistema</p>
            </div>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Form -->
                <div class="card rounded-2xl p-6 border border-white/10">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <?= $editarRev ? 'Editar Revendedor' : 'Novo Revendedor' ?>
                    </h3>

                    <?php if ($editarRev): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="id" value="<?= $editarRev['id'] ?>">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Usuário</label>
                            <input type="text" value="<?= htmlspecialchars($editarRev['username']) ?>" disabled
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-gray-400">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Nome</label>
                            <input type="text" name="e_nome" value="<?= htmlspecialchars($editarRev['name']) ?>" required
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Nova Senha (vazio = manter)</label>
                            <input type="password" name="e_senha"
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Limite Clientes</label>
                                <input type="number" name="e_limite" value="<?= $editarRev['email_limit'] ?>" min="0"
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Limite Perfis @</label>
                                <input type="number" name="e_limite_perfis" value="<?= $editarRev['perfil_limit'] ?? 0 ?>" min="0"
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Validade</label>
                            <input type="date" name="e_validade" value="<?= $editarRev['expires_at'] ?>"
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="e_ativo" <?= $editarRev['is_active'] ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-600 bg-white/5 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm">Ativo</span>
                        </label>
                        <div class="flex gap-2 pt-2">
                            <button type="submit" name="editar_revendedor" value="1" class="btn flex-1 py-2.5 bg-purple-600 rounded-xl hover:bg-purple-700 font-medium">Salvar</button>
                            <a href="?aba=revendedores" class="btn px-4 py-2.5 bg-white/10 rounded-xl hover:bg-white/20 text-center">Cancelar</a>
                        </div>
                    </form>
                    <?php else: ?>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Usuário</label>
                            <input type="text" name="r_username" placeholder="usuario" required
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:border-purple-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Nome</label>
                            <input type="text" name="r_nome" placeholder="Nome completo" required
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:border-purple-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Senha</label>
                            <input type="password" name="r_senha" placeholder="••••••" required
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:border-purple-500 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Limite Clientes</label>
                                <input type="number" name="r_limite" value="10" min="0"
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Limite Perfis @</label>
                                <input type="number" name="r_limite_perfis" value="50" min="0"
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                            </div>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Validade</label>
                            <input type="date" name="r_validade"
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                        </div>
                        <button type="submit" name="criar_revendedor" value="1" class="btn w-full py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl hover:from-purple-700 hover:to-indigo-700 font-medium">
                            Criar Revendedor
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Lista -->
                <div class="lg:col-span-2 card rounded-2xl border border-white/10 overflow-hidden">
                    <div class="p-4 border-b border-white/10 flex items-center justify-between">
                        <h3 class="font-semibold">Lista de Revendedores</h3>
                        <span class="text-sm text-gray-500"><?= count($revendedores) ?> total</span>
                    </div>
                    <div class="divide-y divide-white/5 max-h-[600px] overflow-y-auto">
                        <?php foreach ($revendedores as $r):
                            $expirado = $r['expires_at'] && $r['expires_at'] < date('Y-m-d');
                            $ativo = $r['is_active'] && !$expirado;
                            $status = !$r['is_active'] ? 'Inativo' : ($expirado ? 'Expirado' : 'Ativo');
                            $statusColor = !$r['is_active'] ? 'red' : ($expirado ? 'yellow' : 'green');
                        ?>
                        <div class="p-4 hover:bg-white/5 transition-colors <?= !$ativo ? 'opacity-50' : '' ?>">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center text-sm font-bold">
                                        <?= strtoupper(substr($r['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-medium"><?= htmlspecialchars($r['name']) ?></span>
                                            <span class="text-xs px-2 py-0.5 rounded-full bg-<?= $statusColor ?>-500/20 text-<?= $statusColor ?>-400"><?= $status ?></span>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            @<?= $r['username'] ?> · <?= $r['emails_usados'] ?>/<?= $r['email_limit'] ?: '∞' ?> clientes · <?= $r['perfis_total'] ?>/<?= $r['perfil_limit'] ?: '∞' ?> perfis
                                            <?php if ($r['expires_at']): ?> · Exp: <?= date('d/m/Y', strtotime($r['expires_at'])) ?><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex gap-2 ml-auto sm:ml-0">
                                    <a href="?aba=revendedores&editar=<?= $r['id'] ?>" class="btn px-3 py-1.5 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 text-sm">Editar</a>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button type="submit" name="toggle_revendedor" value="1" class="btn px-3 py-1.5 bg-yellow-500/20 text-yellow-400 rounded-lg hover:bg-yellow-500/30 text-sm">
                                            <?= $r['is_active'] ? 'Desativar' : 'Ativar' ?>
                                        </button>
                                    </form>
                                    <form method="POST" class="inline" onsubmit="return confirm('Excluir revendedor e todos seus clientes?')">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button type="submit" name="excluir_revendedor" value="1" class="btn px-3 py-1.5 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 text-sm">Excluir</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($revendedores)): ?>
                        <div class="p-12 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Nenhum revendedor cadastrado
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===================== ABA CLIENTES ===================== -->
            <?php if ($aba === 'clientes'): ?>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white">Clientes</h2>
                <p class="text-gray-400">Gerencie os clientes e seus perfis</p>
            </div>

            <?php if (!$isAdmin): ?>
            <!-- Dashboard Revendedor -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="card rounded-2xl p-4 border border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Clientes</p>
                            <p class="text-lg font-bold text-white"><?= $meusClientes ?>/<?= $user['email_limit'] ?: '∞' ?></p>
                        </div>
                    </div>
                    <?php if ($user['email_limit'] > 0): ?>
                    <div class="mt-3 h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full" style="width: <?= min(100, ($meusClientes / $user['email_limit']) * 100) ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1"><?= $user['email_limit'] - $meusClientes ?> restantes</p>
                    <?php endif; ?>
                </div>

                <div class="card rounded-2xl p-4 border border-white/10">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-pink-500/20 rounded-xl flex items-center justify-center">
                            <span class="text-pink-400 font-bold text-lg">@</span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Perfis</p>
                            <p class="text-lg font-bold text-white"><?= $meusPerfis ?>/<?= $user['perfil_limit'] ?: '∞' ?></p>
                        </div>
                    </div>
                    <?php if ($user['perfil_limit'] > 0): ?>
                    <div class="mt-3 h-1.5 bg-white/10 rounded-full overflow-hidden">
                        <div class="h-full bg-pink-500 rounded-full" style="width: <?= min(100, ($meusPerfis / $user['perfil_limit']) * 100) ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1"><?= $user['perfil_limit'] - $meusPerfis ?> restantes</p>
                    <?php endif; ?>
                </div>

                <?php if ($user['expires_at']): ?>
                <div class="card rounded-2xl p-4 border border-white/10 col-span-2 lg:col-span-2">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 <?= $user['expires_at'] < date('Y-m-d', strtotime('+7 days')) ? 'bg-red-500/20' : 'bg-green-500/20' ?> rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 <?= $user['expires_at'] < date('Y-m-d', strtotime('+7 days')) ? 'text-red-400' : 'text-green-400' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500">Validade da conta</p>
                            <p class="text-lg font-bold <?= $user['expires_at'] < date('Y-m-d', strtotime('+7 days')) ? 'text-red-400' : 'text-white' ?>">
                                <?= date('d/m/Y', strtotime($user['expires_at'])) ?>
                            </p>
                        </div>
                    </div>
                    <?php
                    $diasRestantes = max(0, (strtotime($user['expires_at']) - time()) / 86400);
                    if ($diasRestantes < 30):
                    ?>
                    <p class="text-xs <?= $diasRestantes < 7 ? 'text-red-400' : 'text-yellow-400' ?> mt-2">
                        <?= floor($diasRestantes) ?> dia(s) restante(s)
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="grid lg:grid-cols-3 gap-6">
                <!-- Form -->
                <div class="card rounded-2xl p-6 border border-white/10">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        <?= $editarCliente ? 'Editar Cliente' : 'Novo Cliente' ?>
                    </h3>

                    <?php if ($editarCliente): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="id" value="<?= $editarCliente['id'] ?>">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Email</label>
                            <input type="email" value="<?= htmlspecialchars($editarCliente['email']) ?>" disabled
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-gray-400">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Máx. Perfis</label>
                                <input type="number" name="edit_max_contas" value="<?= $editarCliente['max_contas'] ?>" min="1"
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Validade</label>
                                <input type="date" name="edit_validade" value="<?= $editarCliente['expires_at'] ?>"
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                            </div>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="edit_ativo" <?= ($editarCliente['is_active'] ?? 1) ? 'checked' : '' ?> class="w-4 h-4 rounded border-gray-600 bg-white/5 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm">Ativo</span>
                        </label>
                        <div class="flex gap-2 pt-2">
                            <button type="submit" name="editar_cliente" value="1" class="btn flex-1 py-2.5 bg-purple-600 rounded-xl hover:bg-purple-700 font-medium">Salvar</button>
                            <a href="?aba=clientes" class="btn px-4 py-2.5 bg-white/10 rounded-xl hover:bg-white/20 text-center">Cancelar</a>
                        </div>
                    </form>
                    <?php else: ?>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="text-xs text-gray-500 block mb-1">Email</label>
                            <input type="email" name="email" placeholder="cliente@email.com" required
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:border-purple-500 focus:outline-none">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Máx. Perfis</label>
                                <input type="number" name="max_contas" value="1" min="1"
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                            </div>
                            <div>
                                <label class="text-xs text-gray-500 block mb-1">Validade</label>
                                <input type="date" name="email_validade"
                                       class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                            </div>
                        </div>
                        <button type="submit" name="criar_cliente" value="1" class="btn w-full py-2.5 bg-gradient-to-r from-purple-600 to-indigo-600 rounded-xl hover:from-purple-700 hover:to-indigo-700 font-medium">
                            Adicionar Cliente
                        </button>
                    </form>
                    <?php endif; ?>
                </div>

                <!-- Lista -->
                <div class="lg:col-span-2 card rounded-2xl border border-white/10 overflow-hidden">
                    <div class="p-4 border-b border-white/10 flex items-center justify-between">
                        <h3 class="font-semibold">Lista de Clientes</h3>
                        <span class="text-sm text-gray-500"><?= count($clientes) ?> total</span>
                    </div>
                    <div class="divide-y divide-white/5 max-h-[600px] overflow-y-auto">
                        <?php foreach ($clientes as $c):
                            $expirado = $c['expires_at'] && $c['expires_at'] < date('Y-m-d');
                            $ativo = ($c['is_active'] ?? 1) && !$expirado;
                            $statusText = !($c['is_active'] ?? 1) ? 'Inativo' : ($expirado ? 'Expirado' : 'Ativo');
                            $statusColor = !($c['is_active'] ?? 1) ? 'red' : ($expirado ? 'yellow' : 'green');
                        ?>
                        <div class="p-4 hover:bg-white/5 transition-colors <?= !$ativo ? 'opacity-50' : '' ?>">
                            <div class="flex flex-col gap-3">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-medium"><?= htmlspecialchars($c['email']) ?></span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-<?= $statusColor ?>-500/20 text-<?= $statusColor ?>-400"><?= $statusText ?></span>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-400"><?= $c['vinculados'] ?>/<?= $c['max_contas'] ?> perfis</span>
                                        <?php if ($c['expires_at']): ?>
                                        <span class="text-xs text-gray-500">Exp: <?= date('d/m/Y', strtotime($c['expires_at'])) ?></span>
                                        <?php endif; ?>
                                        <?php if ($isAdmin && isset($c['revendedor'])): ?>
                                        <span class="text-xs text-gray-500">por <?= htmlspecialchars($c['revendedor']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="?aba=clientes&editar_cliente=<?= $c['id'] ?>" class="btn px-3 py-1.5 bg-blue-500/20 text-blue-400 rounded-lg hover:bg-blue-500/30 text-sm">Editar</a>
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" name="toggle_cliente" value="1" class="btn px-3 py-1.5 bg-yellow-500/20 text-yellow-400 rounded-lg hover:bg-yellow-500/30 text-sm">
                                                <?= ($c['is_active'] ?? 1) ? 'Desativar' : 'Ativar' ?>
                                            </button>
                                        </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Excluir cliente e todos os perfis?')">
                                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                            <button type="submit" name="excluir_cliente" value="1" class="btn px-3 py-1.5 bg-red-500/20 text-red-400 rounded-lg hover:bg-red-500/30 text-sm">Excluir</button>
                                        </form>
                                    </div>
                                </div>
                                <?php
                                $stmt = $pdo->prepare("SELECT * FROM perfis WHERE email = ?");
                                $stmt->execute([$c['email']]);
                                $perfis = $stmt->fetchAll();
                                if ($perfis):
                                ?>
                                <div class="flex flex-wrap gap-2 pl-2 border-l-2 border-purple-500/30">
                                    <?php foreach ($perfis as $p): ?>
                                    <div class="flex items-center gap-2 bg-white/5 rounded-lg px-3 py-1.5">
                                        <span class="text-pink-400 font-bold">@</span>
                                        <span class="text-sm text-gray-300"><?= htmlspecialchars($p['instagram']) ?></span>
                                        <form method="POST" class="inline" onsubmit="return confirm('Remover perfil?')">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" name="excluir_perfil" value="1" class="text-gray-500 hover:text-red-400 transition-colors">
                                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($clientes)): ?>
                        <div class="p-12 text-center text-gray-500">
                            <svg class="w-12 h-12 mx-auto mb-3 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Nenhum cliente cadastrado
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===================== ABA LOGS ===================== -->
            <?php if ($isAdmin && $aba === 'logs'): ?>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white">Logs</h2>
                <p class="text-gray-400">Histórico de atividades do sistema</p>
            </div>

            <div class="space-y-4">
                <!-- Filtros -->
                <div class="card rounded-2xl p-4 border border-white/10">
                    <form method="GET" class="flex flex-wrap gap-4 items-end">
                        <input type="hidden" name="aba" value="logs">
                        <div class="flex-1 min-w-[150px]">
                            <label class="text-xs text-gray-500 block mb-1">Tipo</label>
                            <select name="tipo" class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                                <option value="">Todos</option>
                                <?php foreach ($tiposLogs as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $filtroTipo === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label class="text-xs text-gray-500 block mb-1">Usuário</label>
                            <input type="text" name="usuario" value="<?= htmlspecialchars($filtroUsuario) ?>" placeholder="Buscar..."
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white placeholder-gray-500 focus:border-purple-500 focus:outline-none">
                        </div>
                        <div class="flex-1 min-w-[150px]">
                            <label class="text-xs text-gray-500 block mb-1">Data</label>
                            <input type="date" name="data" value="<?= htmlspecialchars($filtroData) ?>"
                                   class="w-full px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-white focus:border-purple-500 focus:outline-none">
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="btn px-5 py-2.5 bg-purple-600 rounded-xl hover:bg-purple-700 font-medium">Filtrar</button>
                            <a href="?aba=logs" class="btn px-5 py-2.5 bg-white/10 rounded-xl hover:bg-white/20">Limpar</a>
                        </div>
                    </form>
                </div>

                <!-- Tabela -->
                <div class="card rounded-2xl border border-white/10 overflow-hidden">
                    <div class="p-4 border-b border-white/10 flex items-center justify-between">
                        <h3 class="font-semibold">Atividades</h3>
                        <span class="text-sm text-gray-500"><?= $totalLogs ?> registro(s)</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-white/5 text-gray-400 text-left">
                                <tr>
                                    <th class="px-4 py-3 font-medium">Data</th>
                                    <th class="px-4 py-3 font-medium">Tipo</th>
                                    <th class="px-4 py-3 font-medium">Usuário</th>
                                    <th class="px-4 py-3 font-medium">IP</th>
                                    <th class="px-4 py-3 font-medium">Detalhes</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php foreach ($logs as $log):
                                    $tipoColor = match(true) {
                                        str_contains($log['tipo'], 'falha') || str_contains($log['tipo'], 'Negado') || str_contains($log['tipo'], 'desativado') || str_contains($log['tipo'], 'excluido') => 'red',
                                        str_contains($log['tipo'], 'ok') || str_contains($log['tipo'], 'Autorizado') || str_contains($log['tipo'], 'ativado') || str_contains($log['tipo'], 'criado') => 'green',
                                        default => 'gray'
                                    };
                                ?>
                                <tr class="hover:bg-white/5 transition-colors">
                                    <td class="px-4 py-3 text-gray-400 whitespace-nowrap"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-1 rounded-lg bg-<?= $tipoColor ?>-500/20 text-<?= $tipoColor ?>-400 text-xs"><?= htmlspecialchars($log['tipo']) ?></span>
                                    </td>
                                    <td class="px-4 py-3"><?= htmlspecialchars($log['usuario'] ?? '-') ?></td>
                                    <td class="px-4 py-3 text-gray-500"><?= htmlspecialchars($log['ip']) ?></td>
                                    <td class="px-4 py-3 text-gray-400"><?= htmlspecialchars($log['detalhes'] ?? '-') ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-12 text-center text-gray-500">Nenhum log encontrado</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($totalPaginas > 1): ?>
                    <div class="p-4 border-t border-white/10 flex flex-col sm:flex-row justify-between items-center gap-4">
                        <span class="text-sm text-gray-500">Página <?= $paginaAtual ?> de <?= $totalPaginas ?></span>
                        <div class="flex gap-1">
                            <?php
                            $queryParams = http_build_query(array_filter([
                                'aba' => 'logs',
                                'tipo' => $filtroTipo,
                                'usuario' => $filtroUsuario,
                                'data' => $filtroData
                            ]));
                            ?>
                            <?php if ($paginaAtual > 1): ?>
                            <a href="?<?= $queryParams ?>&pagina=1" class="btn px-3 py-1.5 bg-white/10 rounded-lg hover:bg-white/20 text-sm">««</a>
                            <a href="?<?= $queryParams ?>&pagina=<?= $paginaAtual - 1 ?>" class="btn px-3 py-1.5 bg-white/10 rounded-lg hover:bg-white/20 text-sm">«</a>
                            <?php endif; ?>

                            <?php
                            $inicio = max(1, $paginaAtual - 2);
                            $fim = min($totalPaginas, $paginaAtual + 2);
                            for ($i = $inicio; $i <= $fim; $i++):
                            ?>
                            <a href="?<?= $queryParams ?>&pagina=<?= $i ?>" class="btn px-3 py-1.5 rounded-lg text-sm <?= $i === $paginaAtual ? 'bg-purple-600' : 'bg-white/10 hover:bg-white/20' ?>"><?= $i ?></a>
                            <?php endfor; ?>

                            <?php if ($paginaAtual < $totalPaginas): ?>
                            <a href="?<?= $queryParams ?>&pagina=<?= $paginaAtual + 1 ?>" class="btn px-3 py-1.5 bg-white/10 rounded-lg hover:bg-white/20 text-sm">»</a>
                            <a href="?<?= $queryParams ?>&pagina=<?= $totalPaginas ?>" class="btn px-3 py-1.5 bg-white/10 rounded-lg hover:bg-white/20 text-sm">»»</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===================== ABA API ===================== -->
            <?php if ($aba === 'api'):
                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']);
                $baseUrl = rtrim($baseUrl, '/');
                $apiUrl = $baseUrl . '/api.php';
                $apiPath = '/gerenciar/api.php';
                $token = $_SESSION['api_token'] ?? null;
            ?>
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-white">API</h2>
                <p class="text-gray-400">Documentacao e acesso a API</p>
            </div>

            <div class="space-y-6">
                <!-- Token -->
                <div class="card rounded-2xl p-6 border border-white/10">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                        </svg>
                        Seu Token de Acesso
                    </h3>
                    <p class="text-sm text-gray-400 mb-4">Use este token no header <code class="bg-white/10 px-2 py-0.5 rounded">Authorization</code> das requisicoes.</p>

                    <?php if ($token): ?>
                    <div class="bg-black/30 rounded-xl p-4 font-mono text-sm break-all">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <span class="text-gray-500">Basic </span>
                                <span class="text-green-400" id="tokenDisplay"><?= htmlspecialchars($token) ?></span>
                            </div>
                            <button onclick="copyToken()" class="btn px-4 py-2 bg-purple-600 rounded-lg hover:bg-purple-700 text-sm flex items-center gap-2 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                </svg>
                                Copiar
                            </button>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-yellow-500/20 border border-yellow-500/30 rounded-xl p-4">
                        <p class="text-yellow-400 text-sm">
                            <strong>Token nao disponivel.</strong> Faca <a href="?logout=1" class="underline">logout</a> e login novamente para gerar seu token.
                        </p>
                    </div>
                    <?php endif; ?>

                    <div class="mt-4 p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                        <p class="text-sm text-blue-400">
                            <strong>Como funciona:</strong> O token e <code class="bg-white/10 px-1 rounded">base64(usuario:senha)</code>
                        </p>
                        <p class="text-xs text-gray-400 mt-1">Usuario: <code class="bg-white/10 px-1 rounded"><?= htmlspecialchars($user['username']) ?></code></p>
                    </div>

                    <?php if ($token): ?>
                    <div class="mt-4 p-4 bg-green-500/10 border border-green-500/20 rounded-xl">
                        <p class="text-sm text-green-400 mb-2">
                            <strong>Teste rapido no navegador:</strong>
                        </p>
                        <code class="text-xs text-gray-300 bg-black/30 px-2 py-1 rounded block break-all">
                            <?= $apiUrl ?>?action=stats&token=<?= $token ?>
                        </code>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Endpoints -->
                <div class="card rounded-2xl p-6 border border-white/10">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                        </svg>
                        Endpoints Disponiveis
                    </h3>

                    <div class="space-y-6">
                        <?php if ($isAdmin): ?>
                        <!-- Criar Revendedor -->
                        <div class="border-b border-white/10 pb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs font-bold">POST</span>
                                <code class="text-white"><?= $apiPath ?>?action=criar_revendedor</code>
                            </div>
                            <p class="text-sm text-gray-400 mb-3">Cria um novo revendedor no sistema.</p>

                            <div class="bg-black/30 rounded-xl p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 mb-2"># Exemplo cURL</p>
                                <pre class="text-sm text-gray-300 whitespace-pre-wrap break-all"><code>curl -X POST "<?= $apiUrl ?>?action=criar_revendedor" \
  -H "Authorization: Basic <?= $token ?>" \
  -H "Content-Type: application/json" \
  -d '{
    "username": "novorevendedor",
    "nome": "Nome do Revendedor",
    "senha": "senha123",
    "limite_clientes": 10,
    "limite_perfis": 50,
    "validade": "2025-12-31"
  }'</code></pre>
                            </div>

                            <div class="mt-3 text-xs text-gray-500">
                                <strong>Campos:</strong> username*, nome*, senha*, limite_clientes, limite_perfis, validade (YYYY-MM-DD)
                            </div>
                        </div>

                        <!-- Listar Revendedores -->
                        <div class="border-b border-white/10 pb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs font-bold">GET</span>
                                <code class="text-white"><?= $apiPath ?>?action=listar_revendedores</code>
                            </div>
                            <p class="text-sm text-gray-400 mb-3">Lista todos os revendedores.</p>

                            <div class="bg-black/30 rounded-xl p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 mb-2"># Exemplo cURL</p>
                                <pre class="text-sm text-gray-300"><code>curl "<?= $apiUrl ?>?action=listar_revendedores" \
  -H "Authorization: Basic <?= $token ?>"</code></pre>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Criar Cliente -->
                        <div class="border-b border-white/10 pb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-1 bg-green-500/20 text-green-400 rounded text-xs font-bold">POST</span>
                                <code class="text-white"><?= $apiPath ?>?action=criar_cliente</code>
                            </div>
                            <p class="text-sm text-gray-400 mb-3">Cria um novo cliente (email autorizado).</p>

                            <div class="bg-black/30 rounded-xl p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 mb-2"># Exemplo cURL</p>
                                <pre class="text-sm text-gray-300 whitespace-pre-wrap break-all"><code>curl -X POST "<?= $apiUrl ?>?action=criar_cliente" \
  -H "Authorization: Basic <?= $token ?>" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "cliente@email.com",
    "max_perfis": 3,
    "validade": "2025-12-31"
  }'</code></pre>
                            </div>

                            <div class="mt-3 text-xs text-gray-500">
                                <strong>Campos:</strong> email*, max_perfis (padrao: 1), validade (YYYY-MM-DD)
                            </div>
                        </div>

                        <!-- Listar Clientes -->
                        <div class="border-b border-white/10 pb-6">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs font-bold">GET</span>
                                <code class="text-white"><?= $apiPath ?>?action=listar_clientes</code>
                            </div>
                            <p class="text-sm text-gray-400 mb-3">Lista todos os clientes<?= $isAdmin ? '' : ' do revendedor' ?>.</p>

                            <div class="bg-black/30 rounded-xl p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 mb-2"># Exemplo cURL</p>
                                <pre class="text-sm text-gray-300"><code>curl "<?= $apiUrl ?>?action=listar_clientes" \
  -H "Authorization: Basic <?= $token ?>"</code></pre>
                            </div>
                        </div>

                        <!-- Estatísticas -->
                        <div>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded text-xs font-bold">GET</span>
                                <code class="text-white"><?= $apiPath ?>?action=stats</code>
                            </div>
                            <p class="text-sm text-gray-400 mb-3">Retorna estatisticas <?= $isAdmin ? 'gerais do sistema' : 'de uso (limites e consumo)' ?>.</p>

                            <div class="bg-black/30 rounded-xl p-4 overflow-x-auto">
                                <p class="text-xs text-gray-500 mb-2"># Exemplo cURL</p>
                                <pre class="text-sm text-gray-300"><code>curl "<?= $apiUrl ?>?action=stats" \
  -H "Authorization: Basic <?= $token ?>"</code></pre>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Respostas -->
                <div class="card rounded-2xl p-6 border border-white/10">
                    <h3 class="text-lg font-semibold mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Formato das Respostas
                    </h3>

                    <div class="grid md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-green-400 mb-2">Sucesso (200/201)</p>
                            <div class="bg-black/30 rounded-xl p-4">
                                <pre class="text-sm text-gray-300"><code>{
  "success": true,
  "message": "...",
  "data": { ... }
}</code></pre>
                            </div>
                        </div>
                        <div>
                            <p class="text-sm text-red-400 mb-2">Erro (4xx)</p>
                            <div class="bg-black/30 rounded-xl p-4">
                                <pre class="text-sm text-gray-300"><code>{
  "error": "Mensagem de erro"
}</code></pre>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 text-sm text-gray-400">
                        <strong>Codigos HTTP:</strong>
                        <span class="text-green-400">200</span> OK,
                        <span class="text-green-400">201</span> Criado,
                        <span class="text-yellow-400">400</span> Dados invalidos,
                        <span class="text-red-400">401</span> Nao autorizado,
                        <span class="text-red-400">403</span> Acesso negado,
                        <span class="text-red-400">409</span> Conflito (ja existe)
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
    // Copiar token
    function copyToken() {
        const token = document.getElementById('tokenDisplay')?.innerText;
        if (token) {
            navigator.clipboard.writeText('Basic ' + token).then(() => {
                alert('Token copiado!');
            });
        }
    }

    // Mobile menu
    const menuBtn = document.getElementById('menuBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    menuBtn?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('hidden');
    });

    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.add('hidden');
    });

    // Auto-hide flash
    const flash = document.getElementById('flash');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity 0.5s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 500);
        }, 4000);
    }
    </script>
</body>
</html>
