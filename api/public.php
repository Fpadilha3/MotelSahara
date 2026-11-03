<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    responderJson(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

try {
    $pdo = conectarBanco();
    $stmt = $pdo->query(
        'SELECT nome, descricao, endereco, bairro, cidade, estado, referencia,
                google_maps_url, telefone, whatsapp, email,
                horario_atendimento, horario_confirmado, instagram_url, facebook_url, tiktok_url,
                outras_informacoes
         FROM informacoes_motel
         WHERE id = 1'
    );
    $informacoes = $stmt->fetch();
    if (!$informacoes) {
        $informacoes = [
            'nome' => 'Motel Sahara',
            'descricao' => null,
            'endereco' => null,
            'bairro' => null,
            'cidade' => null,
            'estado' => null,
            'referencia' => null,
            'google_maps_url' => null,
            'telefone' => null,
            'whatsapp' => null,
            'email' => null,
            'horario_atendimento' => null,
            'horario_confirmado' => 0,
            'instagram_url' => null,
            'facebook_url' => null,
            'tiktok_url' => null,
            'outras_informacoes' => null
        ];
    }
    responderJson(['sucesso' => true, 'dados' => $informacoes]);
} catch (Throwable $e) {
    error_log('[MotelSahara public] ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'mensagem' => 'As informações não estão disponíveis no momento.'
    ], 500);
}
