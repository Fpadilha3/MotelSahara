<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);

    session_start();
}

function usuarioAtual(): ?array
{
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['usuario_id'],
        'nome' => (string) ($_SESSION['usuario_nome'] ?? ''),
        'email' => (string) ($_SESSION['usuario_email'] ?? ''),
        'tipo' => (string) ($_SESSION['usuario_tipo'] ?? ''),
    ];
}

function exigirLogin(): array
{
    $usuario = usuarioAtual();

    if (!$usuario) {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'É necessário entrar na conta.',
            'codigo' => 'NAO_AUTENTICADO'
        ], 401);
    }

    return $usuario;
}

function exigirAdmin(): array
{
    $usuario = exigirLogin();

    if ($usuario['tipo'] !== 'admin') {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'Acesso permitido somente ao administrador.'
        ], 403);
    }

    return $usuario;
}

function clienteIdDoUsuario(PDO $pdo, int $usuarioId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM clientes WHERE usuario_id = :usuario_id LIMIT 1'
    );

    $stmt->execute(['usuario_id' => $usuarioId]);
    $id = $stmt->fetchColumn();

    return $id === false ? null : (int) $id;
}