<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Execute este arquivo pelo terminal do PHP.\n");
}

try {
    $pdo = conectarBanco();

    echo "=== Criar administrador do Motel Sahara ===\n";
    echo "Nome: ";
    $nome = trim((string) fgets(STDIN));

    echo "E-mail: ";
    $email = strtolower(trim((string) fgets(STDIN)));

    echo "Senha (mínimo 8 caracteres): ";
    $senha = trim((string) fgets(STDIN));

    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 120) {
        throw new RuntimeException('Nome inválido.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('E-mail inválido.');
    }

    if (strlen($senha) < 8) {
        throw new RuntimeException('A senha precisa ter pelo menos 8 caracteres.');
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM usuarios WHERE email = :email LIMIT 1'
    );
    $stmt->execute(['email' => $email]);

    if ($stmt->fetchColumn()) {
        throw new RuntimeException(
            'Já existe um usuário com esse e-mail. Nenhuma conta foi criada.'
        );
    }

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios
            (nome, email, senha_hash, tipo, ativo)
         VALUES
            (:nome, :email, :senha_hash, "admin", 1)'
    );

    $stmt->execute([
        'nome' => $nome,
        'email' => $email,
        'senha_hash' => password_hash($senha, PASSWORD_DEFAULT)
    ]);

    echo "\nAdministrador criado com sucesso!\n";
    echo "ID: " . $pdo->lastInsertId() . "\n";
    echo "E-mail: {$email}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\nErro: " . $e->getMessage() . "\n");
    exit(1);
}