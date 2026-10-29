<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $pdo = conectarBanco();

    $nome = 'Administrador Motel Sahara';
    $email = 'motel@gmail.com';
    $senha = 'Sahara@Adm#2026';

    $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'SELECT id FROM usuarios WHERE email = :email LIMIT 1'
    );

    $stmt->execute([
        'email' => $email
    ]);

    $usuarioId = $stmt->fetchColumn();

    if ($usuarioId !== false) {
        $stmt = $pdo->prepare(
            'UPDATE usuarios
             SET nome = :nome, email = :email, senha_hash = :senha_hash,
                 tipo = :tipo, ativo = :ativo
             WHERE id = :id'
        );
        $stmt->execute([
            'nome' => $nome,
            'email' => $email,
            'senha_hash' => $senhaHash,
            'tipo' => 'admin',
            'ativo' => 1,
            'id' => $usuarioId
        ]);
        echo "Administrador atualizado com sucesso!\n";
        echo "ID: " . $usuarioId . "\n";
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios
                (nome, email, senha_hash, tipo, ativo)
             VALUES
                (:nome, :email, :senha_hash, :tipo, :ativo)'
        );
        $stmt->execute([
            'nome' => $nome,
            'email' => $email,
            'senha_hash' => $senhaHash,
            'tipo' => 'admin',
            'ativo' => 1
        ]);
        echo "Administrador criado com sucesso!\n";
        echo "ID: " . $pdo->lastInsertId() . "\n";
    }

    echo "E-mail: " . $email . "\n";
    echo "Senha: " . $senha . "\n";

} catch (Throwable $e) {
    error_log($e->getMessage());
    echo "Erro ao criar administrador. Verifique a conexão e a estrutura do banco.\n";
}
