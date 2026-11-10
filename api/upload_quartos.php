<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';
require_once __DIR__ . '/proteger.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    responderJson(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
}

$usuario = exigirAdmin();
$quartoIdEntrada = $_GET['quarto_id'] ?? null;
$quartoId = is_scalar($quartoIdEntrada)
    ? filter_var((string) $quartoIdEntrada, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ])
    : false;
if ($quartoId === false) {
    responderJson(['sucesso' => false, 'mensagem' => 'Informe uma suíte válida.'], 422);
}

if (
    (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
    && $_POST === []
    && $_FILES === []
) {
    responderJson([
        'sucesso' => false,
        'mensagem' => 'O envio ultrapassou o limite de tamanho configurado no servidor. Tente novamente com menos fotos ou arquivos menores.'
    ], 413);
}

$arquivos = $_FILES['fotos'] ?? null;
if (
    !is_array($arquivos)
    || !isset($arquivos['name'], $arquivos['tmp_name'], $arquivos['error'], $arquivos['size'])
    || !is_array($arquivos['name'])
    || !is_array($arquivos['tmp_name'])
    || !is_array($arquivos['error'])
    || !is_array($arquivos['size'])
) {
    responderJson(['sucesso' => false, 'mensagem' => 'Selecione pelo menos uma foto para enviar.'], 422);
}

$quantidade = count($arquivos['name']);
if ($quantidade < 1 || $quantidade > 10) {
    responderJson(['sucesso' => false, 'mensagem' => 'Envie entre 1 e 10 fotos por vez.'], 422);
}

try {
    $pdo = conectarBanco();
    $stmt = $pdo->prepare('SELECT id FROM quartos WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $quartoId]);
    if (!$stmt->fetchColumn()) {
        responderJson(['sucesso' => false, 'mensagem' => 'Suíte não encontrada.'], 404);
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM quarto_imagens WHERE quarto_id = :id');
    $stmt->execute(['id' => $quartoId]);
    if ((int) $stmt->fetchColumn() + $quantidade > 20) {
        responderJson(['sucesso' => false, 'mensagem' => 'Cada suíte pode ter até 20 fotos.'], 422);
    }

    $diretorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'
        . DIRECTORY_SEPARATOR . 'quartos';
    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Não foi possível preparar a pasta de fotos.');
    }

    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $lote = [];
    $tamanhoTotal = 0;

    foreach ($arquivos['name'] as $indice => $_nomeOriginal) {
        $erroUpload = (int) ($arquivos['error'][$indice] ?? UPLOAD_ERR_NO_FILE);
        if ($erroUpload !== UPLOAD_ERR_OK) {
            $mensagem = $erroUpload === UPLOAD_ERR_INI_SIZE || $erroUpload === UPLOAD_ERR_FORM_SIZE
                ? 'Uma das fotos ultrapassa o limite permitido pelo servidor.'
                : 'Não foi possível receber uma das fotos selecionadas.';
            responderJson(['sucesso' => false, 'mensagem' => $mensagem], 422);
        }

        $temporario = (string) ($arquivos['tmp_name'][$indice] ?? '');
        $tamanho = (int) ($arquivos['size'][$indice] ?? 0);
        if (
            $tamanho < 1
            || $tamanho > 5 * 1024 * 1024
            || !is_uploaded_file($temporario)
        ) {
            responderJson(['sucesso' => false, 'mensagem' => 'Cada foto deve ter até 5 MB e ser enviada pelo formulário.'], 422);
        }
        $tamanhoTotal += $tamanho;
        if ($tamanhoTotal > 20 * 1024 * 1024) {
            responderJson(['sucesso' => false, 'mensagem' => 'O tamanho total das fotos não pode ultrapassar 20 MB.'], 422);
        }

        $mime = $finfo->file($temporario);
        $informacoesImagem = @getimagesize($temporario);
        if (
            !is_string($mime)
            || !isset($tiposPermitidos[$mime])
            || !is_array($informacoesImagem)
            || ($informacoesImagem['mime'] ?? '') !== $mime
        ) {
            responderJson(['sucesso' => false, 'mensagem' => 'Use somente imagens JPG, PNG ou WEBP válidas.'], 422);
        }

        $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $tiposPermitidos[$mime];
        $lote[] = [
            'temporario' => $temporario,
            'nome' => $nomeArquivo,
            'caminho' => 'uploads/quartos/' . $nomeArquivo,
            'mime' => $mime,
            'tamanho' => $tamanho
        ];
    }

    $arquivosSalvos = [];
    try {
        foreach ($lote as $imagem) {
            $destino = $diretorio . DIRECTORY_SEPARATOR . $imagem['nome'];
            if (!move_uploaded_file($imagem['temporario'], $destino)) {
                throw new RuntimeException('Não foi possível salvar uma das imagens enviadas.');
            }
            $arquivosSalvos[] = $destino;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'INSERT INTO quarto_imagens (quarto_id, caminho, tipo, tamanho_bytes)
             VALUES (:quarto_id, :caminho, :tipo, :tamanho)'
        );
        foreach ($lote as $imagem) {
            $stmt->execute([
                'quarto_id' => $quartoId,
                'caminho' => $imagem['caminho'],
                'tipo' => $imagem['mime'],
                'tamanho' => $imagem['tamanho']
            ]);
        }
        $stmt = $pdo->prepare(
            'INSERT INTO log_admin (usuario_id, acao, entidade, entidade_id, detalhes)
             VALUES (:usuario_id, :acao, :entidade, :entidade_id, :detalhes)'
        );
        $stmt->execute([
            'usuario_id' => $usuario['id'],
            'acao' => 'adicionar_fotos',
            'entidade' => 'quartos',
            'entidade_id' => $quartoId,
            'detalhes' => count($lote) . ' foto(s) adicionada(s) à suíte.'
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($arquivosSalvos as $arquivo) {
            if (is_file($arquivo)) {
                unlink($arquivo);
            }
        }
        throw $e;
    }

    responderJson([
        'sucesso' => true,
        'mensagem' => count($lote) . ' foto(s) salva(s) com sucesso.'
    ], 201);
} catch (Throwable $e) {
    error_log('[MotelSahara upload_quartos] ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'mensagem' => 'Não foi possível salvar as fotos. Confira a configuração do servidor e tente novamente.'
    ], 500);
}
