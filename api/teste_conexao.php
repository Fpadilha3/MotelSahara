<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';

exigirMetodo('GET');

try {
    $servidor = conectarServidor();

    $stmt = $servidor->prepare(
        'SELECT SCHEMA_NAME
         FROM INFORMATION_SCHEMA.SCHEMATA
         WHERE SCHEMA_NAME = :nome'
    );

    $stmt->execute(['nome' => DB_NAME]);
    $existe = (bool) $stmt->fetchColumn();

    responderJson([
        'sucesso' => true,
        'mysql' => 'conectado',
        'banco' => DB_NAME,
        'banco_existe' => $existe,
        'mensagem' => $existe
            ? 'Conexão OK e banco encontrado.'
            : 'Conexão OK, mas o banco ainda não existe. Execute CriarTabelas.php.'
    ]);
} catch (Throwable $e) {
    error_log('[MotelSahara teste_conexao] ' . $e->getMessage());

    responderJson([
        'sucesso' => false,
        'mensagem' => 'Não foi possível conectar ao MySQL. Confira o XAMPP e config.php.'
    ], 500);
}