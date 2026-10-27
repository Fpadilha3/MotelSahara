<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function responderJson(
    array $dados,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $dados,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function lerJson(): array
{
    $conteudo = file_get_contents('php://input');

    if ($conteudo === false || trim($conteudo) === '') {
        return [];
    }

    $dados = json_decode($conteudo, true);

    if (!is_array($dados)) {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'O corpo da requisição não contém JSON válido.'
        ], 400);
    }

    return $dados;
}

function exigirMetodo(string ...$permitidos): void
{
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!in_array($metodo, $permitidos, true)) {
        header('Allow: ' . implode(', ', $permitidos));

        responderJson([
            'sucesso' => false,
            'mensagem' => 'Método HTTP não permitido.'
        ], 405);
    }
}