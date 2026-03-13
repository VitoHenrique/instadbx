<?php
require_once __DIR__ . '/db.php';

// =====================
// API - POST JSON da extensão
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    header('Content-Type: text/plain');
    header('Access-Control-Allow-Origin: *');

    $json = json_decode(file_get_contents('php://input'), true);
    $instagram = $json['ig_username'] ?? '';

    if ($instagram) {
        $stmt = $pdo->prepare("
            SELECT e.reseller_id
            FROM perfis p
            INNER JOIN emails e ON p.email = e.email
            WHERE p.instagram = ?
            AND e.is_active = 1
            AND (e.expires_at IS NULL OR e.expires_at >= DATE('now'))
        ");
        $stmt->execute([$instagram]);
        $result = $stmt->fetch();

        if ($result && revendedorValido($result['reseller_id'])) {
            registrarLog('api_check', $instagram, 'Autorizado');
            echo "1";
        } else {
            registrarLog('api_check', $instagram, 'Negado');
            echo "0";
        }
    } else {
        echo "0";
    }
    exit;
}

// =====================
// Flash Messages (PRG Pattern)
// =====================
$msg = $_SESSION['flash_msg'] ?? '';
$tipo = $_SESSION['flash_tipo'] ?? '';
unset($_SESSION['flash_msg'], $_SESSION['flash_tipo']);

function setFlash($msg, $tipo = 'info') {
    $_SESSION['flash_msg'] = $msg;
    $_SESSION['flash_tipo'] = $tipo;
}

// =====================
// Processar vinculação (POST)
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vincular'])) {
    $email = trim($_POST['email']);
    $instagram = preg_replace('/[^a-zA-Z0-9._]/', '', ltrim(trim($_POST['instagram']), '@'));

    if (empty($email) || empty($instagram)) {
        setFlash('Preencha todos os campos.', 'error');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('Email inválido.', 'error');
    } else {
        $stmt = $pdo->prepare("
            SELECT e.*, a.name as revendedor_nome, a.id as reseller_id, a.perfil_limit
            FROM emails e
            INNER JOIN admins a ON e.reseller_id = a.id
            WHERE e.email = ?
            AND e.is_active = 1
            AND a.is_active = 1
            AND (a.expires_at IS NULL OR a.expires_at >= DATE('now'))
            AND (e.expires_at IS NULL OR e.expires_at >= DATE('now'))
        ");
        $stmt->execute([$email]);
        $emailInfo = $stmt->fetch();

        if (!$emailInfo) {
            setFlash('Este email não está autorizado ou expirou.', 'error');
            registrarLog('vincular_falha', $email, "Email não autorizado: $instagram");
        } else {
            // Verifica limite do cliente (email)
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM perfis WHERE email = ?");
            $stmt->execute([$email]);
            $countCliente = $stmt->fetch()['total'];

            // Verifica limite global do revendedor
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as total FROM perfis p
                INNER JOIN emails e ON p.email = e.email
                WHERE e.reseller_id = ?
            ");
            $stmt->execute([$emailInfo['reseller_id']]);
            $countRevendedor = $stmt->fetch()['total'];

            if ($countCliente >= $emailInfo['max_contas']) {
                setFlash("Limite de {$emailInfo['max_contas']} perfil(s) atingido.", 'error');
                registrarLog('vincular_falha', $email, "Limite cliente atingido: $instagram");
            } elseif ($emailInfo['perfil_limit'] > 0 && $countRevendedor >= $emailInfo['perfil_limit']) {
                setFlash('Limite de perfis do revendedor atingido.', 'error');
                registrarLog('vincular_falha', $email, "Limite revendedor atingido: $instagram");
            } else {
                $stmt = $pdo->prepare("SELECT email FROM perfis WHERE instagram = ?");
                $stmt->execute([$instagram]);
                $jaVinculado = $stmt->fetch();

                if ($jaVinculado) {
                    if ($jaVinculado['email'] === $email) {
                        setFlash('Este perfil já está vinculado ao seu email.', 'info');
                    } else {
                        setFlash('Este perfil já está vinculado a outro email.', 'error');
                    }
                } else {
                    $stmt = $pdo->prepare("INSERT INTO perfis (instagram, email) VALUES (?, ?)");
                    $stmt->execute([$instagram, $email]);
                    setFlash('Perfil vinculado com sucesso!', 'success');
                    registrarLog('vincular_ok', $email, "Vinculado: @$instagram");
                }
            }
        }
    }
    header('Location: ?email=' . urlencode($email));
    exit;
}

// =====================
// Consulta de dados (GET)
// =====================
$perfisVinculados = [];
$emailInfo = null;
$emailBusca = trim($_GET['email'] ?? '');

if ($emailBusca) {
    $stmt = $pdo->prepare("
        SELECT e.*, a.name as revendedor_nome
        FROM emails e
        INNER JOIN admins a ON e.reseller_id = a.id
        WHERE e.email = ?
        AND e.is_active = 1
        AND a.is_active = 1
        AND (a.expires_at IS NULL OR a.expires_at >= DATE('now'))
        AND (e.expires_at IS NULL OR e.expires_at >= DATE('now'))
    ");
    $stmt->execute([$emailBusca]);
    $emailInfo = $stmt->fetch();

    if ($emailInfo) {
        $stmt = $pdo->prepare("SELECT instagram, created_at FROM perfis WHERE email = ? ORDER BY created_at");
        $stmt->execute([$emailBusca]);
        $perfisVinculados = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ativar Licença</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .glass { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); }
        .input-icon { position: relative; }
        .input-icon svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
        .input-icon input { padding-left: 40px; }
        .shake { animation: shake 0.5s ease-in-out; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-violet-600 via-purple-600 to-indigo-700 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="glass rounded-3xl shadow-2xl p-8 border border-white/20">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-gradient-to-br from-pink-500 via-red-500 to-yellow-500 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <span class="text-white text-3xl font-bold">@</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">Ativar Licença</h1>
                <p class="text-gray-500 mt-1">Vincule seu perfil do Instagram</p>
            </div>

            <!-- Mensagem Flash -->
            <?php if ($msg): ?>
            <div id="flash" class="mb-6 p-4 rounded-xl flex items-center gap-3 <?= $tipo === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : ($tipo === 'error' ? 'bg-red-50 text-red-700 border border-red-200 shake' : 'bg-blue-50 text-blue-700 border border-blue-200') ?>">
                <?php if ($tipo === 'success'): ?>
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                <?php elseif ($tipo === 'error'): ?>
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                <?php else: ?>
                <svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                <?php endif; ?>
                <span class="text-sm"><?= htmlspecialchars($msg) ?></span>
            </div>
            <?php endif; ?>

            <!-- Perfis Vinculados -->
            <?php if ($emailInfo && count($perfisVinculados) > 0): ?>
            <div class="mb-6 p-4 bg-gradient-to-r from-purple-50 to-indigo-50 rounded-xl border border-purple-100">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-purple-800">Perfis vinculados</h3>
                    <span class="text-xs font-medium px-2 py-1 bg-purple-200 text-purple-700 rounded-full">
                        <?= count($perfisVinculados) ?>/<?= $emailInfo['max_contas'] ?>
                    </span>
                </div>
                <ul class="space-y-2">
                    <?php foreach ($perfisVinculados as $p): ?>
                    <li class="flex items-center gap-3 text-sm bg-white p-2 rounded-lg">
                        <span class="w-8 h-8 bg-gradient-to-br from-pink-500 to-purple-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">@</span>
                        </span>
                        <span class="text-gray-700 font-medium">@<?= htmlspecialchars($p['instagram']) ?></span>
                        <svg class="w-4 h-4 text-green-500 ml-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($perfisVinculados) >= $emailInfo['max_contas']): ?>
                <p class="text-xs text-gray-500 mt-3 text-center">Para trocar um perfil, contate seu revendedor.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Formulário -->
            <form method="POST" id="formVincular" class="space-y-5" novalidate>
                <!-- Email -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email autorizado</label>
                    <div class="input-icon">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        <input type="email" name="email" id="email" required
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 focus:outline-none transition-all"
                               placeholder="seu@email.com"
                               value="<?= htmlspecialchars($emailBusca) ?>">
                    </div>
                    <p id="emailError" class="text-red-500 text-xs mt-1 hidden">Digite um email válido</p>
                </div>

                <!-- Instagram -->
                <?php if (!$emailInfo || count($perfisVinculados) < $emailInfo['max_contas']): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Perfil do Instagram</label>
                    <div class="input-icon">
                        <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-lg font-bold">@</span>
                        <input type="text" name="instagram" id="instagram"
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 focus:outline-none transition-all"
                               placeholder="seuusuario">
                    </div>
                </div>

                <button type="submit" name="vincular" value="1" id="btnSubmit"
                        class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all font-semibold shadow-lg shadow-purple-500/30 flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    Vincular Perfil
                </button>
                <?php else: ?>
                <button type="submit"
                        class="w-full py-3 bg-gray-200 text-gray-600 rounded-xl font-semibold flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    Consultar Perfis
                </button>
                <?php endif; ?>
            </form>

            <p class="text-xs text-gray-400 text-center mt-6">
                Não tem email autorizado? Contate um revendedor.
            </p>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('formVincular');
        const emailInput = document.getElementById('email');
        const emailError = document.getElementById('emailError');
        const instagramInput = document.getElementById('instagram');

        // Validação de email em tempo real
        emailInput.addEventListener('blur', function() {
            const email = this.value.trim();
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

            if (email && !isValid) {
                emailError.classList.remove('hidden');
                this.classList.add('border-red-300');
                this.classList.remove('border-gray-200');
            } else {
                emailError.classList.add('hidden');
                this.classList.remove('border-red-300');
                this.classList.add('border-gray-200');
            }
        });

        // Limpa @ do início do Instagram
        if (instagramInput) {
            instagramInput.addEventListener('input', function() {
                this.value = this.value.replace(/^@/, '').replace(/[^a-zA-Z0-9._]/g, '');
            });
        }

        // Validação no submit
        form.addEventListener('submit', function(e) {
            const email = emailInput.value.trim();
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

            if (!isValid) {
                e.preventDefault();
                emailError.classList.remove('hidden');
                emailInput.classList.add('border-red-300', 'shake');
                emailInput.focus();
                setTimeout(() => emailInput.classList.remove('shake'), 500);
            }
        });

        // Auto-hide flash message
        const flash = document.getElementById('flash');
        if (flash) {
            setTimeout(() => {
                flash.style.transition = 'opacity 0.5s';
                flash.style.opacity = '0';
                setTimeout(() => flash.remove(), 500);
            }, 5000);
        }
    });
    </script>
</body>
</html>
