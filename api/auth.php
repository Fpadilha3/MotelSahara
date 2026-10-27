<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';
require_once __DIR__ . '/proteger.php';

$pdo = conectarBanco();
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$acao = strtolower((string) ($_GET['acao'] ?? ''));

try {
    if ($metodo === 'GET' && $acao === 'sessao') {
        $usuario = usuarioAtual();

        responderJson([
            'sucesso' => true,
            'autenticado' => $usuario !== null,
            'usuario' => $usuario
        ]);
    }

    exigirMetodo('POST');

    $dados = lerJson();

    if ($acao === 'login') {
        $email = strtolower(trim((string) ($dados['email'] ?? '')));
        $senha = (string) ($dados['senha'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe um e-mail válido e a senha.'
            ], 422);
        }

        $stmt = $pdo->prepare(
            'SELECT id, nome, email, telefone, senha_hash, tipo, ativo
             FROM usuarios
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if (
            !$usuario ||
            (int) $usuario['ativo'] !== 1 ||
            !password_verify($senha, $usuario['senha_hash'])
        ) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'E-mail ou senha incorretos.'
            ], 401);
        }

        session_regenerate_id(true);

        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['usuario_email'] = $usuario['email'];
        $_SESSION['usuario_tipo'] = $usuario['tipo'];

        unset($usuario['senha_hash'], $usuario['ativo']);

        responderJson([
            'sucesso' => true,
            'mensagem' => 'Login realizado.',
            'usuario' => $usuario,
            'redirecionar' => $usuario['tipo'] === 'admin'
                ? '../admin/dashboard.html'
                : '../cliente/dashboard.html'
        ]);
    }

    if ($acao === 'cadastro') {
        $nome = trim((string) ($dados['nome'] ?? ''));
        $email = strtolower(trim((string) ($dados['email'] ?? '')));
        $telefone = trim((string) ($dados['telefone'] ?? ''));
        $senha = (string) ($dados['senha'] ?? '');
        $cpf = preg_replace('/\D+/', '', (string) ($dados['cpf'] ?? ''));
        $nascimento = trim((string) ($dados['data_nascimento'] ?? ''));

        if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe um nome entre 2 e 120 caracteres.'
            ], 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe um e-mail válido.'
            ], 422);
        }

        if (strlen($senha) < 8) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'A senha precisa ter pelo menos 8 caracteres.'
            ], 422);
        }

        if ($telefone !== '' && strlen($telefone) > 25) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'O telefone é muito longo.'
            ], 422);
        }

        if ($cpf !== '' && strlen($cpf) !== 11) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'O CPF deve conter 11 dígitos.'
            ], 422);
        }

        if ($nascimento !== '') {
            $data = DateTime::createFromFormat('!Y-m-d', $nascimento);

            if (!$data || $data->format('Y-m-d') !== $nascimento) {
                responderJson([
                    'sucesso' => false,
                    'mensagem' => 'Data de nascimento inválida.'
                ], 422);
            }
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO usuarios
                (nome, email, telefone, senha_hash, tipo, ativo)
             VALUES
                (:nome, :email, :telefone, :senha_hash, "cliente", 1)'
        );

        $stmt->execute([
            'nome' => $nome,
            'email' => $email,
            'telefone' => $telefone !== '' ? $telefone : null,
            'senha_hash' => password_hash($senha, PASSWORD_DEFAULT)
        ]);

        $usuarioId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO clientes (usuario_id, cpf, data_nascimento)
             VALUES (:usuario_id, :cpf, :nascimento)'
        );

        $stmt->execute([
            'usuario_id' => $usuarioId,
            'cpf' => $cpf !== '' ? $cpf : null,
            'nascimento' => $nascimento !== '' ? $nascimento : null
        ]);

        $pdo->commit();

        responderJson([
            'sucesso' => true,
            'mensagem' => 'Cadastro realizado. Agora tu já pode entrar na conta.'
        ], 201);
    }

    responderJson([
        'sucesso' => false,
        'mensagem' => 'Ação de autenticação desconhecida.'
    ], 404);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[MotelSahara auth] ' . $e->getMessage());

    if ($e->getCode() === '23000') {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'Esse e-mail ou CPF já está cadastrado.'
        ], 409);
    }

    responderJson([
        'sucesso' => false,
        'mensagem' => 'Erro no banco durante a autenticação.'
    ], 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[MotelSahara auth] ' . $e->getMessage());

    responderJson([
        'sucesso' => false,
        'mensagem' => 'Ocorreu um erro ao processar a solicitação.'
    ], 500);
}