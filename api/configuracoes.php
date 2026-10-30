<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';
require_once __DIR__ . '/proteger.php';

$usuario = exigirAdmin();
$metodoHttp = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$recurso = strtolower(trim((string) ($_GET['recurso'] ?? '')));

function validarDataOpcional($valor, string $campo): ?string
{
    if ($valor === null) {
        return null;
    }
    if (!is_scalar($valor)) {
        responderJson(['sucesso' => false, 'mensagem' => "Informe uma data válida para {$campo}."], 422);
    }

    $texto = trim((string) $valor);
    if ($texto === '') {
        return null;
    }
    $data = DateTime::createFromFormat('!Y-m-d\TH:i', $texto)
        ?: DateTime::createFromFormat('!Y-m-d H:i:s', $texto);

    if (!$data || ($data->format('Y-m-d\TH:i') !== $texto
        && $data->format('Y-m-d H:i:s') !== $texto)) {
        responderJson([
            'sucesso' => false,
            'mensagem' => "Informe uma data válida para {$campo}."
        ], 422);
    }

    return $data->format('Y-m-d H:i:s');
}

function validarUrlOpcional($valor, string $campo, bool $somenteGoogle = false): ?string
{
    if ($valor === null) {
        return null;
    }
    if (!is_scalar($valor)) {
        responderJson(['sucesso' => false, 'mensagem' => "Informe um link válido para {$campo}."], 422);
    }

    $url = trim((string) $valor);
    if ($url === '') {
        return null;
    }
    $partes = parse_url($url);

    if (
        mb_strlen($url) > 500
        ||
        filter_var($url, FILTER_VALIDATE_URL) === false
        || ($partes['scheme'] ?? '') !== 'https'
        || ($somenteGoogle && !in_array(
            strtolower((string) ($partes['host'] ?? '')),
            ['maps.google.com', 'google.com', 'www.google.com', 'maps.app.goo.gl'],
            true
        ))
    ) {
        responderJson([
            'sucesso' => false,
            'mensagem' => "Informe um link HTTPS válido para {$campo}."
        ], 422);
    }

    return $url;
}

function registrarLogConfiguracao(
    PDO $pdo,
    int $usuarioId,
    string $acao,
    string $entidade,
    ?int $entidadeId = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO log_admin (usuario_id, acao, entidade, entidade_id, detalhes)
         VALUES (:usuario_id, :acao, :entidade, :entidade_id, :detalhes)'
    );
    $stmt->execute([
        'usuario_id' => $usuarioId,
        'acao' => $acao,
        'entidade' => $entidade,
        'entidade_id' => $entidadeId,
        'detalhes' => 'Alteração feita pelo painel administrativo.'
    ]);
}

try {
    $pdo = conectarBanco();

    if ($recurso === 'promocoes') {
        if ($metodoHttp === 'GET') {
            $stmt = $pdo->query(
                'SELECT p.*, q.nome AS quarto_nome, q.numero AS quarto_numero,
                        q.preco_periodo AS preco_original
                 FROM promocoes p
                 INNER JOIN quartos q ON q.id = p.quarto_id
                 ORDER BY p.atualizado_em DESC, p.id DESC'
            );
            responderJson(['sucesso' => true, 'dados' => $stmt->fetchAll()]);
        }

        if (!in_array($metodoHttp, ['POST', 'PUT', 'DELETE'], true)) {
            responderJson(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
        }

        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($metodoHttp !== 'POST' && $id === false) {
            responderJson(['sucesso' => false, 'mensagem' => 'Informe uma promoção válida.'], 422);
        }

        $pdo->beginTransaction();
        if ($metodoHttp === 'DELETE') {
            $stmt = $pdo->prepare('SELECT id FROM promocoes WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            if (!$stmt->fetchColumn()) {
                $pdo->rollBack();
                responderJson(['sucesso' => false, 'mensagem' => 'Promoção não encontrada.'], 404);
            }
            $stmt = $pdo->prepare('UPDATE promocoes SET ativa = 0 WHERE id = :id');
            $stmt->execute(['id' => $id]);
            registrarLogConfiguracao($pdo, $usuario['id'], 'desativar', 'promocoes', (int) $id);
            $pdo->commit();
            responderJson(['sucesso' => true, 'mensagem' => 'Promoção desativada.']);
        }

        $entrada = lerJson();
        $quartoId = filter_var($entrada['quarto_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if ($metodoHttp === 'PUT') {
            $stmt = $pdo->prepare('SELECT id FROM promocoes WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            if (!$stmt->fetchColumn()) {
                $pdo->rollBack();
                responderJson(['sucesso' => false, 'mensagem' => 'Promoção não encontrada.'], 404);
            }
        }
        $tipoEntrada = $entrada['tipo'] ?? 'percentual';
        $tipo = is_string($tipoEntrada) ? $tipoEntrada : '';
        $valor = $entrada['valor'] ?? null;
        $ativa = filter_var($entrada['ativa'] ?? 1, FILTER_VALIDATE_INT);
        $cumulativa = filter_var($entrada['cumulativa'] ?? 0, FILTER_VALIDATE_INT);
        $condicoesEntrada = $entrada['condicoes'] ?? '';
        if (!is_scalar($condicoesEntrada)) {
            $pdo->rollBack();
            responderJson(['sucesso' => false, 'mensagem' => 'As condições da promoção são inválidas.'], 422);
        }
        $condicoes = trim((string) $condicoesEntrada);
        $inicio = validarDataOpcional($entrada['inicio_em'] ?? null, 'data de início');
        $fim = validarDataOpcional($entrada['fim_em'] ?? null, 'data de término');

        if (
            $quartoId === false
            || $tipo !== 'percentual'
            || !is_numeric($valor)
            || (float) $valor <= 0
            || ($tipo === 'percentual' && (float) $valor > 100)
            || !in_array($ativa, [0, 1], true)
            || !in_array($cumulativa, [0, 1], true)
            || mb_strlen($condicoes) > 500
            || ($inicio !== null && $fim !== null && $fim <= $inicio)
        ) {
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Confira a suíte, o tipo, o valor do desconto, as datas e as condições (até 500 caracteres).'
            ], 422);
        }

        $stmt = $pdo->prepare('SELECT id, preco_periodo FROM quartos WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $quartoId]);
        $quarto = $stmt->fetch();
        if (!$quarto) {
            $pdo->rollBack();
            responderJson(['sucesso' => false, 'mensagem' => 'Suíte não encontrada.'], 404);
        }
        $stmt = $pdo->prepare(
            'SELECT id FROM promocoes WHERE quarto_id = :quarto_id AND id <> :id LIMIT 1'
        );
        $stmt->execute(['quarto_id' => $quartoId, 'id' => $metodoHttp === 'PUT' ? $id : 0]);
        if ($stmt->fetchColumn()) {
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Esta suíte já possui uma promoção. Edite a existente para evitar descontos duplicados.'
            ], 409);
        }

        $dados = [
            'quarto_id' => $quartoId,
            'tipo' => $tipo,
            'valor' => round((float) $valor, 2),
            'inicio_em' => $inicio,
            'fim_em' => $fim,
            'ativa' => $ativa,
            'cumulativa' => $cumulativa,
            'condicoes' => $condicoes !== '' ? $condicoes : null
        ];
        if ($metodoHttp === 'POST') {
            $stmt = $pdo->prepare(
                'INSERT INTO promocoes
                    (quarto_id, tipo, valor, inicio_em, fim_em, ativa, cumulativa, condicoes)
                 VALUES
                    (:quarto_id, :tipo, :valor, :inicio_em, :fim_em, :ativa, :cumulativa, :condicoes)'
            );
            $stmt->execute($dados);
            $id = (int) $pdo->lastInsertId();
        } else {
            $dados['id'] = $id;
            $stmt = $pdo->prepare(
                'UPDATE promocoes
                 SET quarto_id = :quarto_id, tipo = :tipo, valor = :valor,
                     inicio_em = :inicio_em, fim_em = :fim_em, ativa = :ativa,
                     cumulativa = :cumulativa, condicoes = :condicoes
                 WHERE id = :id'
            );
            $stmt->execute($dados);
        }
        registrarLogConfiguracao(
            $pdo,
            $usuario['id'],
            $metodoHttp === 'POST' ? 'inserir' : 'atualizar',
            'promocoes',
            (int) $id
        );
        $pdo->commit();
        responderJson([
            'sucesso' => true,
            'mensagem' => $metodoHttp === 'POST' ? 'Promoção salva.' : 'Promoção atualizada.',
            'id' => $id
        ], $metodoHttp === 'POST' ? 201 : 200);
    }

    if ($recurso === 'informacoes') {
        if ($metodoHttp === 'GET') {
            $stmt = $pdo->query('SELECT * FROM informacoes_motel WHERE id = 1');
            responderJson(['sucesso' => true, 'dados' => $stmt->fetch()]);
        }
        if ($metodoHttp !== 'PUT') {
            responderJson(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
        }

        $entrada = lerJson();
        $campos = [
            'nome' => [120, true],
            'descricao' => [5000, false],
            'endereco' => [255, false],
            'bairro' => [120, false],
            'cidade' => [120, false],
            'estado' => [80, false],
            'referencia' => [255, false],
            'telefone' => [25, false],
            'whatsapp' => [25, false],
            'email' => [190, false],
            'horario_atendimento' => [255, false],
            'instagram_url' => [500, false],
            'facebook_url' => [500, false],
            'tiktok_url' => [500, false],
            'outras_informacoes' => [5000, false]
        ];
        $dados = [];
        foreach ($campos as $campo => [$limite, $obrigatorio]) {
            $valorEntrada = $entrada[$campo] ?? '';
            if (!is_scalar($valorEntrada)) {
                responderJson(['sucesso' => false, 'mensagem' => "Confira o campo {$campo}."], 422);
            }
            $valor = trim((string) $valorEntrada);
            if (($obrigatorio && $valor === '') || mb_strlen($valor) > $limite) {
                responderJson([
                    'sucesso' => false,
                    'mensagem' => "Confira o campo {$campo} (limite de {$limite} caracteres)."
                ], 422);
            }
            $dados[$campo] = $valor !== '' ? $valor : null;
        }
        $horarioConfirmado = filter_var(
            $entrada['horario_confirmado'] ?? 0,
            FILTER_VALIDATE_INT
        );
        if (!in_array($horarioConfirmado, [0, 1], true)) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Confirme se o horário informado está correto antes de publicá-lo.'
            ], 422);
        }
        if ($horarioConfirmado === 1 && $dados['horario_atendimento'] === null) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe o horário de funcionamento antes de confirmá-lo para publicação.'
            ], 422);
        }
        $dados['horario_confirmado'] = $horarioConfirmado;
        if ($dados['email'] !== null && !filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            responderJson(['sucesso' => false, 'mensagem' => 'Informe um e-mail válido.'], 422);
        }
        foreach (['telefone' => [8, 15], 'whatsapp' => [10, 15]] as $campo => [$min, $max]) {
            if ($dados[$campo] === null) {
                continue;
            }
            $digitos = preg_replace('/\D+/', '', $dados[$campo]);
            if (strlen($digitos) < $min || strlen($digitos) > $max) {
                responderJson(['sucesso' => false, 'mensagem' => "Informe um número válido para {$campo}."], 422);
            }
        }
        foreach (['instagram_url', 'facebook_url', 'tiktok_url'] as $campo) {
            $dados[$campo] = validarUrlOpcional($dados[$campo], $campo);
        }
        $dados['google_maps_url'] = validarUrlOpcional(
            $entrada['google_maps_url'] ?? null,
            'Google Maps',
            true
        );
        $set = [];
        foreach (array_keys($dados) as $campo) {
            $set[] = "`{$campo}` = :{$campo}";
        }
        $dados['id'] = 1;
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE informacoes_motel SET ' . implode(', ', $set) . ' WHERE id = :id');
        $stmt->execute($dados);
        registrarLogConfiguracao($pdo, $usuario['id'], 'atualizar', 'informacoes_motel', 1);
        $pdo->commit();
        responderJson(['sucesso' => true, 'mensagem' => 'Informações do motel salvas.']);
    }

    if ($recurso === 'comercial') {
        if ($metodoHttp === 'GET') {
            $comercial = $pdo->query('SELECT * FROM configuracoes_comerciais WHERE id = 1')->fetch();
            $taxas = $pdo->query('SELECT * FROM taxas_pagamento ORDER BY metodo, prazo_recebimento_dias')->fetchAll();
            responderJson(['sucesso' => true, 'dados' => ['comercial' => $comercial, 'taxas' => $taxas]]);
        }
        if ($metodoHttp !== 'PUT') {
            responderJson(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
        }

        $entrada = lerJson();
        $desconto = $entrada['desconto'] ?? [];
        if (!is_array($desconto)) {
            responderJson(['sucesso' => false, 'mensagem' => 'Configuração de desconto inválida.'], 422);
        }
        $tipoEntrada = $desconto['tipo'] ?? '';
        $tipo = is_string($tipoEntrada) ? $tipoEntrada : '';
        $valor = $desconto['valor'] ?? null;
        $ativo = filter_var($desconto['ativo'] ?? 0, FILTER_VALIDATE_INT);
        $cumulativo = filter_var($desconto['cumulativo'] ?? 0, FILTER_VALIDATE_INT);
        $metodos = $desconto['metodos_elegiveis'] ?? [];
        $permitidos = ['pix', 'credito', 'debito', 'dinheiro', 'transferencia', 'outro'];
        $inicio = validarDataOpcional($desconto['inicio_em'] ?? null, 'data de início');
        $fim = validarDataOpcional($desconto['fim_em'] ?? null, 'data de término');
        $condicoesEntrada = $desconto['condicoes'] ?? '';
        if (!is_scalar($condicoesEntrada)) {
            responderJson(['sucesso' => false, 'mensagem' => 'As condições do desconto à vista são inválidas.'], 422);
        }
        $condicoes = trim((string) $condicoesEntrada);
        if (
            !in_array($tipo, ['percentual', 'fixo'], true)
            || !is_numeric($valor)
            || (float) $valor <= 0
            || ($tipo === 'percentual' && (float) $valor > 100)
            || !in_array($ativo, [0, 1], true)
            || !in_array($cumulativo, [0, 1], true)
            || !is_array($metodos)
            || count($metodos) > count($permitidos)
            || array_filter($metodos, static fn($valor): bool =>
                !is_string($valor) || !in_array($valor, $permitidos, true)
            )
            || mb_strlen($condicoes) > 500
            || ($inicio !== null && $fim !== null && $fim <= $inicio)
        ) {
            responderJson(['sucesso' => false, 'mensagem' => 'Confira o desconto à vista, métodos, período e condições.'], 422);
        }
        $metodos = array_values(array_unique($metodos));
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'UPDATE configuracoes_comerciais
             SET desconto_vista_ativo = :ativo, desconto_vista_tipo = :tipo,
                 desconto_vista_valor = :valor, metodos_elegiveis = :metodos,
                 desconto_vista_cumulativo = :cumulativo, inicio_em = :inicio_em,
                 fim_em = :fim_em, condicoes = :condicoes
             WHERE id = 1'
        );
        $stmt->execute([
            'ativo' => $ativo,
            'tipo' => $tipo,
            'valor' => round((float) $valor, 2),
            'metodos' => implode(',', $metodos),
            'cumulativo' => $cumulativo,
            'inicio_em' => $inicio,
            'fim_em' => $fim,
            'condicoes' => $condicoes !== '' ? $condicoes : null
        ]);

        $taxas = $entrada['taxas'] ?? [];
        if (!is_array($taxas)) {
            responderJson(['sucesso' => false, 'mensagem' => 'Tabela de taxas inválida.'], 422);
        }
        $stmt = $pdo->prepare(
            'UPDATE taxas_pagamento
             SET percentual = :percentual, tarifa_fixa = :tarifa_fixa,
                 ativa = :ativa, repassar_cliente = :repassar_cliente,
                 condicoes = :condicoes
             WHERE id = :id'
        );
        foreach ($taxas as $taxa) {
            if (!is_array($taxa)) {
                responderJson(['sucesso' => false, 'mensagem' => 'Uma taxa está inválida.'], 422);
            }
            $idTaxa = filter_var($taxa['id'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            $percentual = $taxa['percentual'] ?? null;
            $fixa = $taxa['tarifa_fixa'] ?? null;
            $taxaAtiva = filter_var($taxa['ativa'] ?? 0, FILTER_VALIDATE_INT);
            $repassar = filter_var($taxa['repassar_cliente'] ?? 0, FILTER_VALIDATE_INT);
            $condicaoEntrada = $taxa['condicoes'] ?? '';
            if (!is_scalar($condicaoEntrada)) {
                responderJson(['sucesso' => false, 'mensagem' => 'As condições da taxa são inválidas.'], 422);
            }
            $condicaoTaxa = trim((string) $condicaoEntrada);
            if (
                $idTaxa === false || !is_numeric($percentual) || (float) $percentual < 0
                || (float) $percentual > 100 || !is_numeric($fixa) || (float) $fixa < 0
                || !in_array($taxaAtiva, [0, 1], true)
                || !in_array($repassar, [0, 1], true)
                || mb_strlen($condicaoTaxa) > 500
            ) {
                responderJson(['sucesso' => false, 'mensagem' => 'Confira os percentuais, valores fixos e políticas de repasse das taxas.'], 422);
            }
            $stmt->execute([
                'id' => $idTaxa,
                'percentual' => (float) $percentual,
                'tarifa_fixa' => (float) $fixa,
                'ativa' => $taxaAtiva,
                'repassar_cliente' => $repassar,
                'condicoes' => $condicaoTaxa !== '' ? $condicaoTaxa : null
            ]);
        }
        registrarLogConfiguracao($pdo, $usuario['id'], 'atualizar', 'configuracoes_comerciais', 1);
        $pdo->commit();
        responderJson([
            'sucesso' => true,
            'mensagem' => 'Regras comerciais salvas. Nenhuma tarifa é repassada sem integração de pagamento.'
        ]);
    }

    responderJson(['sucesso' => false, 'mensagem' => 'Configuração não reconhecida.'], 404);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[MotelSahara configuracoes] ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'mensagem' => 'Não foi possível salvar as configurações. Confira os dados e o log do PHP.'
    ], 500);
}
