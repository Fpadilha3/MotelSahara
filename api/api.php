<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';
require_once __DIR__ . '/proteger.php';

try {
    $pdo = conectarBanco();
} catch (Throwable $e) {
    error_log('[MotelSahara api] ' . $e->getMessage());
    responderJson([
        'sucesso' => false,
        'mensagem' => 'Não foi possível conectar ao banco de dados.'
    ], 500);
}

$usuario = exigirLogin();

$tabela = strtolower(trim((string) ($_GET['tabela'] ?? '')));
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$acao = strtolower(trim((string) ($_GET['acao'] ?? '')));

if (isset($_GET['id']) && ($id === false || $id < 1)) {
    responderJson([
        'sucesso' => false,
        'mensagem' => 'Informe um ID válido.'
    ], 422);
}

$colunas = [
    'usuarios' => [
        'nome', 'email', 'telefone', 'senha_hash', 'tipo', 'ativo'
    ],
    'clientes' => [
        'usuario_id', 'cpf', 'data_nascimento', 'observacoes'
    ],
    'quartos' => [
        'numero', 'nome', 'categoria', 'descricao', 'capacidade_pessoas',
        'maximo_pessoas', 'preco_pessoa_extra',
        'preco_periodo', 'duracao_periodo_minutos',
        'percentual_taxa_servico', 'status', 'imagem_url'
    ],
    'frigobar' => [
        'nome', 'descricao', 'categoria', 'preco', 'estoque',
        'percentual_taxa', 'ativo', 'imagem_url'
    ],
    'estadias' => [
        'codigo', 'cliente_id', 'quarto_id', 'status', 'entrada_prevista',
        'entrada_real', 'saida_prevista', 'saida_real', 'quantidade_pessoas',
        'preco_periodo_aplicado', 'percentual_taxa_aplicado', 'observacoes'
    ],
    'consumo' => [
        'estadia_id', 'frigobar_id', 'quantidade', 'preco_unitario',
        'percentual_taxa_aplicado', 'subtotal', 'registrado_por', 'observacoes'
    ],
    'pagamentos' => [
        'estadia_id', 'codigo_transacao', 'valor', 'valor_cobrado_cliente',
        'desconto_aplicado',
        'prazo_recebimento_dias_aplicado', 'percentual_taxa_operacional_aplicada',
        'tarifa_fixa_operacional_aplicada', 'taxa_operacional_aplicada',
        'taxa_repassada_cliente_aplicada',
        'metodo', 'status', 'pago_em', 'registrado_por', 'observacoes'
    ],
    'log_admin' => [
        'usuario_id', 'acao', 'entidade', 'entidade_id', 'detalhes'
    ]
];

if (!isset($colunas[$tabela])) {
    responderJson([
        'sucesso' => false,
        'mensagem' => 'Tabela não reconhecida.'
    ], 400);
}

function registrarLog(
    PDO $pdo,
    int $usuarioId,
    string $acao,
    string $entidade,
    ?int $entidadeId = null,
    ?string $detalhes = null
): void {
    $stmt = $pdo->prepare(
        'INSERT INTO log_admin
            (usuario_id, acao, entidade, entidade_id, detalhes)
         VALUES
            (:usuario_id, :acao, :entidade, :entidade_id, :detalhes)'
    );

    $stmt->execute([
        'usuario_id' => $usuarioId,
        'acao' => $acao,
        'entidade' => $entidade,
        'entidade_id' => $entidadeId,
        'detalhes' => $detalhes
    ]);
}

function filtrarDados(array $dados, array $permitidas): array
{
    $resultado = [];

    foreach ($permitidas as $campo) {
        if (array_key_exists($campo, $dados)) {
            $resultado[$campo] = $dados[$campo];
        }
    }

    return $resultado;
}

function consultarSaldoEstadia(PDO $pdo, int $estadiaId, ?int $pagamentoIgnorado = null): array
{
    $stmt = $pdo->prepare(
        'SELECT id, status, valor_total_previsto, valor_desconto_promocional,
                promocao_cumulativa_aplicada
         FROM estadias
         WHERE id = :id
         LIMIT 1
         FOR UPDATE'
    );
    $stmt->execute(['id' => $estadiaId]);
    $estadia = $stmt->fetch();
    if (!$estadia) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(subtotal * (1 + percentual_taxa_aplicado / 100)), 0)
         FROM consumo
         WHERE estadia_id = :estadia_id'
    );
    $stmt->execute(['estadia_id' => $estadiaId]);
    $consumo = (float) $stmt->fetchColumn();

    $sqlPagamentos = "
        SELECT COALESCE(SUM(valor), 0) AS pago,
               COALESCE(SUM(desconto_aplicado), 0) AS desconto
        FROM pagamentos
        WHERE estadia_id = :estadia_id
          AND status = 'aprovado'
    ";
    $params = ['estadia_id' => $estadiaId];
    if ($pagamentoIgnorado !== null) {
        $sqlPagamentos .= ' AND id <> :pagamento_id';
        $params['pagamento_id'] = $pagamentoIgnorado;
    }
    $stmt = $pdo->prepare($sqlPagamentos);
    $stmt->execute($params);
    $pagamentos = $stmt->fetch();

    $total = round((float) $estadia['valor_total_previsto'] + $consumo, 2);
    $pago = round((float) $pagamentos['pago'], 2);
    $desconto = round((float) $pagamentos['desconto'], 2);

    return [
        'estadia' => $estadia,
        'total' => $total,
        'consumo' => round($consumo, 2),
        'pago' => $pago,
        'desconto' => $desconto,
        'saldo' => max(0, round($total - $pago - $desconto, 2))
    ];
}

function calcularDescontoVista(PDO $pdo, array $conta, string $metodo): float
{
    if (
        $conta['estadia']['status'] === 'cancelada'
        || (float) $conta['saldo'] <= 0
    ) {
        return 0.0;
    }

    $config = $pdo->query(
        'SELECT desconto_vista_ativo, desconto_vista_tipo, desconto_vista_valor,
                desconto_vista_cumulativo, metodos_elegiveis, inicio_em, fim_em
         FROM configuracoes_comerciais
         WHERE id = 1'
    )->fetch();
    if (!$config || (int) $config['desconto_vista_ativo'] !== 1) {
        return 0.0;
    }
    if (
        (float) $conta['estadia']['valor_desconto_promocional'] > 0
        && (
            (int) $conta['estadia']['promocao_cumulativa_aplicada'] !== 1
            || (int) $config['desconto_vista_cumulativo'] !== 1
        )
    ) {
        return 0.0;
    }

    $metodos = array_filter(array_map('trim', explode(',', (string) $config['metodos_elegiveis'])));
    if (!in_array($metodo, $metodos, true)) {
        return 0.0;
    }

    $agora = new DateTimeImmutable();
    if (
        ($config['inicio_em'] !== null && $agora < new DateTimeImmutable($config['inicio_em']))
        || ($config['fim_em'] !== null && $agora > new DateTimeImmutable($config['fim_em']))
    ) {
        return 0.0;
    }

    $saldo = (float) $conta['saldo'];
    $valor = (float) $config['desconto_vista_valor'];
    $desconto = $config['desconto_vista_tipo'] === 'percentual'
        ? round($saldo * $valor / 100, 2)
        : min($saldo, $valor);

    return min($saldo, max(0, $desconto));
}

function consultarTaxaOperacional(
    PDO $pdo,
    string $metodo,
    int $prazoRecebimento,
    float $valorBase
): array {
    $stmt = $pdo->prepare(
        'SELECT percentual, tarifa_fixa, ativa, repassar_cliente
         FROM taxas_pagamento
         WHERE metodo = :metodo
           AND prazo_recebimento_dias = :prazo
         LIMIT 1'
    );
    $stmt->execute([
        'metodo' => $metodo,
        'prazo' => $prazoRecebimento
    ]);
    $taxa = $stmt->fetch();
    if (!$taxa) {
        return [
            'prazo' => $prazoRecebimento,
            'percentual' => 0.0,
            'tarifa_fixa' => 0.0,
            'taxa' => 0.0,
            'repassar_cliente' => false
        ];
    }

    $ativa = (int) $taxa['ativa'] === 1;
    $percentual = $ativa ? (float) $taxa['percentual'] : 0.0;
    $tarifaFixa = $ativa ? (float) $taxa['tarifa_fixa'] : 0.0;

    return [
        'prazo' => $prazoRecebimento,
        'percentual' => $percentual,
        'tarifa_fixa' => $tarifaFixa,
        'taxa' => round($valorBase * $percentual / 100 + $tarifaFixa, 2),
        'repassar_cliente' => $ativa && (int) $taxa['repassar_cliente'] === 1
    ];
}

function separarValorPagamento(float $totalCobrado, array $taxa): array
{
    $repassarCliente = $taxa['repassar_cliente'];
    if (!$repassarCliente) {
        return [
            'valor' => round($totalCobrado, 2),
            'taxa_cobrada' => 0.0,
            'total_cobrado' => round($totalCobrado, 2)
        ];
    }

    $percentual = (float) $taxa['percentual'];
    $tarifaFixa = (float) $taxa['tarifa_fixa'];
    $estimativaBase = round(
        (round($totalCobrado, 2) - $tarifaFixa) / (1 + $percentual / 100),
        2
    );
    $centavosEstimados = (int) round($estimativaBase * 100);
    for ($ajuste = -5; $ajuste <= 5; $ajuste++) {
        $valorBase = ($centavosEstimados + $ajuste) / 100;
        if ($valorBase <= 0) {
            continue;
        }
        $taxaCalculada = round($valorBase * $percentual / 100 + $tarifaFixa, 2);
        $totalCalculado = round($valorBase + $taxaCalculada, 2);
        if ($totalCalculado === round($totalCobrado, 2)) {
            return [
                'valor' => $valorBase,
                'taxa_cobrada' => $taxaCalculada,
                'total_cobrado' => $totalCalculado
            ];
        }
    }

    responderJson([
        'sucesso' => false,
        'mensagem' => 'O total informado não corresponde à taxa configurada. Atualize o valor antes de registrar.'
    ], 422);
}

try {
    /*
     * LEITURA
     */
    if ($metodo === 'GET') {
        if ($tabela === 'estadias' && $acao === 'pagamentos_pendentes') {
            if ($usuario['tipo'] !== 'admin') {
                responderJson([
                    'sucesso' => false,
                    'mensagem' => 'Acesso permitido somente ao administrador.'
                ], 403);
            }

            $stmt = $pdo->query(
                "SELECT e.*,
                        COALESCE(c.total, 0) AS consumo_total,
                        COALESCE(p.valor_pago, 0) AS pagamentos_confirmados,
                        COALESCE(p.descontos_aplicados, 0) AS descontos_aplicados,
                        ROUND(e.valor_total_previsto + COALESCE(c.total, 0)
                            - COALESCE(p.valor_pago, 0)
                            - COALESCE(p.descontos_aplicados, 0), 2) AS saldo_pendente
                 FROM estadias e
                 LEFT JOIN (
                    SELECT estadia_id,
                           SUM(subtotal * (1 + percentual_taxa_aplicado / 100)) AS total
                    FROM consumo
                    GROUP BY estadia_id
                 ) c ON c.estadia_id = e.id
                 LEFT JOIN (
                    SELECT estadia_id, SUM(valor) AS valor_pago,
                           SUM(desconto_aplicado) AS descontos_aplicados
                    FROM pagamentos
                    WHERE status = 'aprovado'
                    GROUP BY estadia_id
                 ) p ON p.estadia_id = e.id
                 WHERE e.status <> 'cancelada'
                   AND ROUND(e.valor_total_previsto + COALESCE(c.total, 0)
                       - COALESCE(p.valor_pago, 0)
                       - COALESCE(p.descontos_aplicados, 0), 2) > 0
                 ORDER BY e.id DESC
                 LIMIT 500"
            );
            $dados = $stmt->fetchAll();
            responderJson([
                'sucesso' => true,
                'quantidade' => count($dados),
                'dados' => $dados
            ]);
        }

        if ($tabela === 'log_admin' && $usuario['tipo'] !== 'admin') {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Acesso permitido somente ao administrador.'
            ], 403);
        }

        $where = [];
        $params = [];

        if ($usuario['tipo'] !== 'admin') {
            if (in_array($tabela, ['usuarios', 'clientes', 'log_admin'], true)) {
                if ($tabela === 'clientes') {
                    $where[] = 'usuario_id = :usuario_id';
                    $params['usuario_id'] = $usuario['id'];
                } else {
                    responderJson([
                        'sucesso' => false,
                        'mensagem' => 'Não tens permissão para consultar esses dados.'
                    ], 403);
                }
            } elseif ($tabela === 'quartos') {
                $where[] = "q.status = 'disponivel'";
            } elseif ($tabela === 'frigobar') {
                $where[] = 'ativo = 1';
            } elseif (in_array($tabela, ['estadias', 'consumo', 'pagamentos'], true)) {
                $clienteId = clienteIdDoUsuario($pdo, $usuario['id']);

                if ($clienteId === null) {
                    responderJson([
                        'sucesso' => true,
                        'dados' => []
                    ]);
                }

                $subquery = "
                    SELECT e.id
                    FROM estadias e
                    WHERE e.cliente_id = :cliente_id
                ";

                if ($tabela === 'estadias') {
                    $where[] = 'cliente_id = :cliente_id';
                    $params['cliente_id'] = $clienteId;
                } else {
                    $where[] = 'estadia_id IN (' . $subquery . ')';
                    $params['cliente_id'] = $clienteId;
                }
            }
        }

        if ($id !== null) {
            $where[] = $tabela === 'quartos' ? 'q.id = :id' : 'id = :id';
            $params['id'] = $id;
        }

        $sql = $tabela === 'quartos'
            ? "SELECT q.*, p.id AS promocao_id, p.tipo AS promocao_tipo,
                      p.valor AS promocao_valor, p.inicio_em AS promocao_inicio_em,
                      p.fim_em AS promocao_fim_em, p.cumulativa AS promocao_cumulativa,
                      p.condicoes AS promocao_condicoes, p.ativa AS promocao_ativa
               FROM quartos q
               LEFT JOIN promocoes p ON p.quarto_id = q.id"
            : 'SELECT * FROM `' . $tabela . '`';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= $tabela === 'quartos'
            ? ' ORDER BY q.id DESC LIMIT 500'
            : ' ORDER BY id DESC LIMIT 500';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $dados = $stmt->fetchAll();

        // Nunca enviar hashes de senha ao frontend.
        if ($tabela === 'usuarios') {
            foreach ($dados as &$linha) {
                unset($linha['senha_hash']);
            }
            unset($linha);
        }

        responderJson([
            'sucesso' => true,
            'quantidade' => count($dados),
            'dados' => $dados
        ]);
    }

    if ($metodo === 'POST' && $tabela === 'consumo' && $acao === 'registrar_consumo') {
        if ($usuario['tipo'] !== 'cliente') {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Este fluxo de consumo é exclusivo da área do cliente.'
            ], 403);
        }

        $entrada = lerJson();
        $produtoId = filter_var(
            $entrada['frigobar_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $quantidade = filter_var(
            $entrada['quantidade'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 50]]
        );
        $clienteId = clienteIdDoUsuario($pdo, $usuario['id']);

        if ($produtoId === false || $quantidade === false || $clienteId === null) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Não foi possível validar o produto, a quantidade ou o cadastro do cliente.'
            ], 422);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "SELECT id
             FROM estadias
             WHERE cliente_id = :cliente_id
               AND status = 'em_andamento'
             ORDER BY COALESCE(entrada_real, entrada_prevista) DESC
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute(['cliente_id' => $clienteId]);
        $estadiaId = $stmt->fetchColumn();

        if ($estadiaId === false) {
            $stmt = $pdo->prepare(
                "SELECT codigo
                 FROM estadias
                 WHERE cliente_id = :cliente_id
                   AND status = 'reservada'
                 ORDER BY entrada_prevista DESC
                 LIMIT 1"
            );
            $stmt->execute(['cliente_id' => $clienteId]);
            $reserva = $stmt->fetchColumn();
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => $reserva
                    ? 'Sua estadia ' . $reserva . ' está reservada. A equipe precisa confirmar o check-in e iniciá-la antes de liberar o consumo.'
                    : 'Não há uma estadia em andamento vinculada à sua conta. Confirme com a equipe se o check-in já foi iniciado.'
            ], 409);
        }

        $stmt = $pdo->prepare(
            'SELECT id, nome, preco, estoque, percentual_taxa
             FROM frigobar
             WHERE id = :id AND ativo = 1
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['id' => $produtoId]);
        $produto = $stmt->fetch();

        if (!$produto) {
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Este produto não está disponível.'
            ], 404);
        }

        if ((int) $produto['estoque'] < $quantidade) {
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Estoque insuficiente. Atualize a quantidade e tente novamente.'
            ], 409);
        }

        $precoUnitario = (float) $produto['preco'];
        $subtotal = round($precoUnitario * $quantidade, 2);
        $total = round(
            $subtotal * (1 + (float) $produto['percentual_taxa'] / 100),
            2
        );

        $stmt = $pdo->prepare(
            'UPDATE frigobar
             SET estoque = estoque - :quantidade_retirar
             WHERE id = :id AND estoque >= :quantidade_minima'
        );
        $stmt->execute([
            'quantidade_retirar' => $quantidade,
            'quantidade_minima' => $quantidade,
            'id' => $produtoId
        ]);

        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => 'O estoque mudou. Atualize a página e tente novamente.'
            ], 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO consumo
                (estadia_id, frigobar_id, quantidade, preco_unitario,
                 percentual_taxa_aplicado, subtotal, registrado_por)
             VALUES
                (:estadia_id, :frigobar_id, :quantidade, :preco_unitario,
                 :percentual_taxa, :subtotal, :registrado_por)'
        );
        $stmt->execute([
            'estadia_id' => (int) $estadiaId,
            'frigobar_id' => $produtoId,
            'quantidade' => $quantidade,
            'preco_unitario' => $precoUnitario,
            'percentual_taxa' => $produto['percentual_taxa'],
            'subtotal' => $subtotal,
            'registrado_por' => $usuario['id']
        ]);
        $consumoId = (int) $pdo->lastInsertId();
        $pdo->commit();

        responderJson([
            'sucesso' => true,
            'mensagem' => 'Consumo registrado na sua estadia.',
            'id' => $consumoId,
            'subtotal' => $subtotal,
            'total' => $total,
            'estoque' => (int) $produto['estoque'] - $quantidade
        ], 201);
    }

    $solicitacaoCliente = $metodo === 'POST'
        && $tabela === 'estadias'
        && $acao === 'criar'
        && $usuario['tipo'] === 'cliente';

    /*
     * Clientes podem solicitar estadias; as demais alterações são administrativas.
     */
    if (!$solicitacaoCliente) {
        exigirAdmin();
    }

    if (!in_array($metodo, ['POST', 'PUT', 'DELETE'], true)) {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'Método não permitido.'
        ], 405);
    }

    $entrada = $metodo === 'DELETE' ? [] : lerJson();
    if ($acao === '') {
        $acao = strtolower(trim((string) ($entrada['acao'] ?? '')));
    }

    if ($metodo === 'PUT' && $tabela === 'estadias' && $acao === 'alterar_status') {
        if ($id === null || $id < 1) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe um ID válido para atualizar a estadia.'
            ], 422);
        }

        $novoStatus = (string) ($entrada['status'] ?? '');
        $transicoes = [
            'reservada' => ['em_andamento', 'cancelada'],
            'em_andamento' => ['finalizada']
        ];
        if (!in_array($novoStatus, ['em_andamento', 'finalizada', 'cancelada'], true)) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Situação de estadia inválida.'
            ], 422);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT id, status, quarto_id, entrada_prevista, saida_prevista
             FROM estadias
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['id' => $id]);
        $estadia = $stmt->fetch();

        if (!$estadia) {
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Estadia não encontrada.'
            ], 404);
        }

        if (!in_array($novoStatus, $transicoes[$estadia['status']] ?? [], true)) {
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Esta mudança de situação não é permitida.'
            ], 409);
        }

        if ($novoStatus === 'em_andamento') {
            $stmt = $pdo->prepare(
                'SELECT id FROM quartos WHERE id = :id LIMIT 1 FOR UPDATE'
            );
            $stmt->execute(['id' => $estadia['quarto_id']]);

            $stmt = $pdo->prepare(
                "SELECT id
                 FROM estadias
                 WHERE quarto_id = :quarto_id
                   AND id <> :id
                   AND status IN ('reservada', 'em_andamento')
                   AND COALESCE(entrada_real, entrada_prevista) < :saida_prevista
                   AND (
                        COALESCE(saida_real, saida_prevista) IS NULL
                        OR COALESCE(saida_real, saida_prevista) > NOW()
                   )
                 LIMIT 1"
            );
            $stmt->execute([
                'quarto_id' => $estadia['quarto_id'],
                'id' => $id,
                'saida_prevista' => $estadia['saida_prevista']
            ]);
            if ($stmt->fetchColumn()) {
                $pdo->rollBack();
                responderJson([
                    'sucesso' => false,
                    'mensagem' => 'Não é possível iniciar: há outra estadia ocupando parte deste período.'
                ], 409);
            }

            $stmt = $pdo->prepare(
                "UPDATE estadias
                 SET status = 'em_andamento', entrada_real = NOW()
                 WHERE id = :id"
            );
        } elseif ($novoStatus === 'finalizada') {
            $stmt = $pdo->prepare(
                "UPDATE estadias
                 SET status = 'finalizada', saida_real = NOW()
                 WHERE id = :id"
            );
        } else {
            $stmt = $pdo->prepare(
                "UPDATE estadias SET status = 'cancelada' WHERE id = :id"
            );
        }

        $stmt->execute(['id' => $id]);
        registrarLog(
            $pdo,
            $usuario['id'],
            'atualizar_status',
            'estadias',
            $id,
            'Situação alterada para ' . $novoStatus . '.'
        );
        $pdo->commit();

        responderJson([
            'sucesso' => true,
            'mensagem' => 'Situação da estadia atualizada.',
            'status' => $novoStatus
        ]);
    }

    if ($metodo === 'POST' && $tabela === 'estadias' && $acao === 'criar') {
        $clienteId = $solicitacaoCliente
            ? clienteIdDoUsuario($pdo, $usuario['id'])
            : filter_var(
                $entrada['cliente_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
        $quartoId = filter_var(
            $entrada['quarto_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $pessoas = filter_var(
            $entrada['quantidade_pessoas'] ?? 2,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]
        );
        $entradaPrevista = trim((string) ($entrada['entrada_prevista'] ?? ''));
        $saidaPrevista = trim((string) ($entrada['saida_prevista'] ?? ''));
        $observacoes = trim((string) ($entrada['observacoes'] ?? ''));

        $inicio = DateTime::createFromFormat('!Y-m-d\TH:i', $entradaPrevista);
        $fim = DateTime::createFromFormat('!Y-m-d\TH:i', $saidaPrevista);

        if (
            $clienteId === false ||
            $clienteId === null ||
            $quartoId === false ||
            $pessoas === false ||
            !$inicio ||
            !$fim ||
            $inicio->format('Y-m-d\TH:i') !== $entradaPrevista ||
            $fim->format('Y-m-d\TH:i') !== $saidaPrevista ||
            $fim <= $inicio ||
            ($solicitacaoCliente && $inicio <= new DateTime()) ||
            mb_strlen($observacoes) > 500
        ) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Confira os dados da estadia, o período e o limite de 500 caracteres das observações.'
            ], 422);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            '            SELECT q.id, q.status, q.capacidade_pessoas, q.maximo_pessoas,
                    q.preco_pessoa_extra, q.preco_periodo,
                    q.duracao_periodo_minutos,
                    q.percentual_taxa_servico
             FROM quartos q
             WHERE q.id = :quarto_id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['quarto_id' => $quartoId]);
        $quarto = $stmt->fetch();

        if (!$quarto || $quarto['status'] !== 'disponivel') {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'O quarto não existe ou não está disponível para reserva.'
            ], 409);
        }

        if ($pessoas > (int) $quarto['maximo_pessoas']) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Esta suíte permite até ' . (int) $quarto['maximo_pessoas']
                    . ' pessoas no total. Pessoas excedentes são cobradas até esse limite; peça à equipe para aumentar o limite cadastrado se necessário.'
            ], 422);
        }

        $duracaoMinutos = intdiv(
            $fim->getTimestamp() - $inicio->getTimestamp(),
            60
        );
        $duracaoPeriodo = (int) $quarto['duracao_periodo_minutos'];
        if ($duracaoMinutos < 1 || $duracaoMinutos > 1439 || $duracaoPeriodo < 1) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'A duração escolhida deve ser de pelo menos 1 minuto e menor que 24 horas.'
            ], 422);
        }

        $precoPeriodo = (float) $quarto['preco_periodo'];
        $precoPeriodoOriginal = $precoPeriodo;
        $valorDescontoPromocional = 0.0;
        $stmt = $pdo->prepare(
            'SELECT tipo, valor, cumulativa
             FROM promocoes
             WHERE quarto_id = :quarto_id
               AND ativa = 1
               AND (inicio_em IS NULL OR inicio_em <= :inicio_em)
               AND (fim_em IS NULL OR fim_em >= :vigencia_inicio)
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            'quarto_id' => $quartoId,
            'inicio_em' => $inicio->format('Y-m-d H:i:s'),
            'vigencia_inicio' => $inicio->format('Y-m-d H:i:s')
        ]);
        $promocao = $stmt->fetch();
        $promocaoCumulativaAplicada = $promocao
            ? (int) $promocao['cumulativa']
            : 0;
        if ($promocao) {
            $valorDescontoPromocional = $promocao['tipo'] === 'percentual'
                ? round($precoPeriodoOriginal * (float) $promocao['valor'] / 100, 2)
                : min($precoPeriodoOriginal, (float) $promocao['valor']);
            $precoPeriodo = max(0, round(
                $precoPeriodoOriginal - $valorDescontoPromocional,
                2
            ));
        }
        $precoPessoaExtra = (float) $quarto['preco_pessoa_extra'];
        $precoTempo = round(
            $precoPeriodo * $duracaoMinutos / $duracaoPeriodo,
            2
        );
        $quantidadeExtra = max(0, $pessoas - (int) $quarto['capacidade_pessoas']);
        $valorPessoasExtras = round($quantidadeExtra * $precoPessoaExtra, 2);
        $subtotalPrevisto = round($precoTempo + $valorPessoasExtras, 2);
        $valorTaxa = round(
            $subtotalPrevisto * (float) $quarto['percentual_taxa_servico'] / 100,
            2
        );
        $valorTotalPrevisto = round($subtotalPrevisto + $valorTaxa, 2);
        $valorDescontoPromocionalAplicado = round(
            $valorDescontoPromocional * $duracaoMinutos / $duracaoPeriodo,
            2
        );

        $stmt = $pdo->prepare('SELECT id FROM clientes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $clienteId]);
        if (!$stmt->fetchColumn()) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Cliente não encontrado.'
            ], 404);
        }

        $stmt = $pdo->prepare(
            "SELECT id
             FROM estadias
             WHERE quarto_id = :quarto_id
               AND status IN ('reservada', 'em_andamento')
               AND COALESCE(entrada_real, entrada_prevista) < :saida_prevista
               AND (
                    COALESCE(saida_real, saida_prevista) IS NULL
                    OR COALESCE(saida_real, saida_prevista) > :entrada_prevista
               )
             LIMIT 1"
        );
        $stmt->execute([
            'quarto_id' => $quartoId,
            'saida_prevista' => $saidaPrevista,
            'entrada_prevista' => $entradaPrevista
        ]);
        if ($stmt->fetchColumn()) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Já existe uma estadia ativa nesse quarto para o período informado.'
            ], 409);
        }

        $codigo = 'MS' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));
        $stmt = $pdo->prepare(
            'INSERT INTO estadias
                (codigo, cliente_id, quarto_id, status, entrada_prevista,
                 saida_prevista, quantidade_pessoas, pessoas_incluidas_aplicado,
                 preco_periodo_aplicado, preco_periodo_original_aplicado,
                 valor_desconto_promocional, promocao_cumulativa_aplicada, preco_hora_extra_aplicado,
                 preco_pessoa_extra_aplicado, percentual_taxa_aplicado,
                 valor_total_previsto, observacoes)
             VALUES
                (:codigo, :cliente_id, :quarto_id, "reservada", :entrada_prevista,
                 :saida_prevista, :quantidade_pessoas, :pessoas_incluidas,
                 :preco_periodo, :preco_periodo_original,
                 :valor_desconto_promocional, :promocao_cumulativa, 0, :preco_pessoa_extra,
                 :percentual_taxa, :valor_total_previsto, :observacoes)'
        );
        $stmt->execute([
            'codigo' => $codigo,
            'cliente_id' => $clienteId,
            'quarto_id' => $quartoId,
            'entrada_prevista' => $inicio->format('Y-m-d H:i:s'),
            'saida_prevista' => $fim->format('Y-m-d H:i:s'),
            'quantidade_pessoas' => $pessoas,
            'pessoas_incluidas' => $quarto['capacidade_pessoas'],
            'preco_periodo' => $precoPeriodo,
            'preco_periodo_original' => $precoPeriodoOriginal,
            'valor_desconto_promocional' => $valorDescontoPromocionalAplicado,
            'promocao_cumulativa' => $promocaoCumulativaAplicada,
            'preco_pessoa_extra' => $precoPessoaExtra,
            'percentual_taxa' => $quarto['percentual_taxa_servico'],
            'valor_total_previsto' => $valorTotalPrevisto,
            'observacoes' => $observacoes !== '' ? $observacoes : null
        ]);

        $novoId = (int) $pdo->lastInsertId();
        registrarLog(
            $pdo,
            $usuario['id'],
            'inserir',
            'estadias',
            $novoId,
            $solicitacaoCliente
                ? 'Estadia solicitada pelo cliente.'
                : 'Estadia criada pelo painel administrativo.'
        );
        $pdo->commit();

        responderJson([
            'sucesso' => true,
            'mensagem' => 'Estadia registrada.',
            'id' => $novoId,
            'codigo' => $codigo,
            'valor_total_previsto' => $valorTotalPrevisto,
            'valor_desconto_promocional' => $valorDescontoPromocionalAplicado
        ], 201);
    }

    if ($metodo === 'POST' && $tabela === 'estadias') {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'Use a ação criar para validar e registrar a estadia.'
        ], 422);
    }

    /*
     * EXCLUSÃO
     */
    if ($metodo === 'DELETE') {
        if ($id === null || $id < 1) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe um ID válido para excluir.'
            ], 422);
        }

        // Não permitir apagar a própria conta por este endpoint.
        if ($tabela === 'usuarios' && $id === $usuario['id']) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Não podes excluir a própria conta por este endpoint.'
            ], 422);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM `' . $tabela . '` WHERE id = :id');
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Registro não encontrado.'
            ], 404);
        }

        registrarLog(
            $pdo,
            $usuario['id'],
            'excluir',
            $tabela,
            $id,
            'Registro excluído pela API.'
        );

        $pdo->commit();
        responderJson([
            'sucesso' => true,
            'mensagem' => 'Registro excluído.'
        ]);
    }

    $permitidas = $colunas[$tabela];

    if ($tabela === 'estadias' && $metodo === 'PUT') {
        $permitidas = array_values(array_diff(
            $permitidas,
            ['status', 'entrada_real', 'saida_real']
        ));
    }
    if ($tabela === 'pagamentos' && $metodo === 'PUT') {
        $permitidas = array_values(array_diff(
            $permitidas,
            [
                'estadia_id',
                'desconto_aplicado',
                'valor_cobrado_cliente',
                'prazo_recebimento_dias_aplicado',
                'percentual_taxa_operacional_aplicada',
                'tarifa_fixa_operacional_aplicada',
                'taxa_operacional_aplicada',
                'taxa_repassada_cliente_aplicada'
            ]
        ));
    }

    // Campos sensíveis não podem ser manipulados livremente pelo cadastro.
    if ($tabela === 'usuarios') {
        if ($metodo === 'POST') {
            $permitidas = ['nome', 'email', 'telefone', 'senha_hash', 'tipo', 'ativo'];
        } else {
            $permitidas = ['nome', 'email', 'telefone', 'senha_hash', 'ativo'];
        }
    }

    $dados = filtrarDados($entrada, $permitidas);

    if (
        $tabela === 'frigobar'
        && in_array($metodo, ['POST', 'PUT'], true)
        && array_key_exists('imagem_url', $dados)
    ) {
        $imagemEntrada = $dados['imagem_url'];
        if (!is_scalar($imagemEntrada)) {
            responderJson(['sucesso' => false, 'mensagem' => 'Informe uma URL de imagem válida.'], 422);
        }
        $imagemUrl = trim((string) $imagemEntrada);
        $partesImagem = $imagemUrl !== '' ? parse_url($imagemUrl) : [];
        if (
            mb_strlen($imagemUrl) > 500
            || ($imagemUrl !== '' && (
                filter_var($imagemUrl, FILTER_VALIDATE_URL) === false
                || !in_array(strtolower((string) ($partesImagem['scheme'] ?? '')), ['http', 'https'], true)
            ))
        ) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe uma URL HTTP ou HTTPS válida para a imagem (até 500 caracteres).'
            ], 422);
        }
        $dados['imagem_url'] = $imagemUrl !== '' ? $imagemUrl : null;
    }

    if ($tabela === 'quartos' && in_array($metodo, ['POST', 'PUT'], true)) {
        $capacidade = filter_var(
            $dados['capacidade_pessoas'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]
        );
        $maximoPessoas = filter_var(
            $dados['maximo_pessoas'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 100]]
        );
        $precoPessoaExtra = $dados['preco_pessoa_extra'] ?? null;
        $duracaoPeriodo = filter_var(
            $dados['duracao_periodo_minutos'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 1439]]
        );

        if ($capacidade === false) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe a quantidade de pessoas incluída no preço (de 1 a 100).'
            ], 422);
        }

        if ($maximoPessoas === false || $maximoPessoas < $capacidade) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'O limite máximo de pessoas deve ser igual ou maior que a quantidade incluída.'
            ], 422);
        }

        if (!is_numeric($precoPessoaExtra) || (float) $precoPessoaExtra < 0) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe um adicional por pessoa válido (zero ou maior).'
            ], 422);
        }

        if ($duracaoPeriodo === false) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe a duração do período-base entre 1 e 1439 minutos.'
            ], 422);
        }
    }

    if ($tabela === 'pagamentos' && $metodo === 'POST') {
        $estadiaId = filter_var(
            $dados['estadia_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $metodosValidos = [
            'dinheiro', 'pix', 'credito', 'debito', 'transferencia', 'outro'
        ];
        $statusValidos = [
            'pendente', 'aprovado', 'recusado', 'estornado', 'cancelado'
        ];

        if (
            $estadiaId === false ||
            !isset($dados['valor']) ||
            !is_numeric($dados['valor']) ||
            (float) $dados['valor'] <= 0 ||
            !in_array($dados['metodo'] ?? '', $metodosValidos, true) ||
            !in_array($dados['status'] ?? '', $statusValidos, true) ||
            mb_strlen((string) ($dados['observacoes'] ?? '')) > 500
        ) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Confira a estadia, o valor, o método, a situação e as observações do pagamento.'
            ], 422);
        }

        $dados['estadia_id'] = $estadiaId;
        $prazoRecebimento = filter_var(
            $dados['prazo_recebimento_dias_aplicado'] ?? 0,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 3650]]
        );
        if ($prazoRecebimento === false) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Selecione um prazo de recebimento válido.'
            ], 422);
        }
        $dados['prazo_recebimento_dias_aplicado'] = $prazoRecebimento;
        if ($dados['status'] === 'aprovado' && empty($dados['pago_em'])) {
            $dados['pago_em'] = date('Y-m-d H:i:s');
        }
    }

    if ($tabela === 'pagamentos' && $metodo === 'PUT') {
        $statusValidos = [
            'pendente', 'aprovado', 'recusado', 'estornado', 'cancelado'
        ];
        if ($id === null || $id < 1) {
            responderJson(['sucesso' => false, 'mensagem' => 'Informe um pagamento válido.'], 422);
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT id, estadia_id, valor, valor_cobrado_cliente, metodo, status,
                    prazo_recebimento_dias_aplicado,
                    percentual_taxa_operacional_aplicada,
                    tarifa_fixa_operacional_aplicada,
                    taxa_operacional_aplicada,
                    taxa_repassada_cliente_aplicada
             FROM pagamentos
             WHERE id = :id
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute(['id' => $id]);
        $pagamentoAtual = $stmt->fetch();
        if (!$pagamentoAtual) {
            $pdo->rollBack();
            responderJson(['sucesso' => false, 'mensagem' => 'Pagamento não encontrado.'], 404);
        }

        $statusPagamento = (string) ($dados['status'] ?? $pagamentoAtual['status']);
        $metodoPagamento = (string) ($dados['metodo'] ?? $pagamentoAtual['metodo']);
        $valorCobrado = $dados['valor'] ?? (
            (float) $pagamentoAtual['valor_cobrado_cliente'] > 0
                ? $pagamentoAtual['valor_cobrado_cliente']
                : $pagamentoAtual['valor']
        );
        if (
            !in_array($statusPagamento, $statusValidos, true)
            || !in_array($metodoPagamento, ['dinheiro', 'pix', 'credito', 'debito', 'transferencia', 'outro'], true)
            || !is_numeric($valorCobrado)
            || (float) $valorCobrado <= 0
        ) {
            $pdo->rollBack();
            responderJson(['sucesso' => false, 'mensagem' => 'Confira o valor, o método e a situação do pagamento.'], 422);
        }

        $conta = consultarSaldoEstadia($pdo, (int) $pagamentoAtual['estadia_id'], (int) $id);
        if (!$conta || $conta['estadia']['status'] === 'cancelada') {
            $pdo->rollBack();
            responderJson(['sucesso' => false, 'mensagem' => 'A estadia não está disponível para pagamentos.'], 409);
        }
        $valorCobrado = round((float) $valorCobrado, 2);
        $recalcularTaxa = array_key_exists('valor', $dados)
            || array_key_exists('metodo', $dados);
        if ($recalcularTaxa) {
            $taxaOperacional = consultarTaxaOperacional(
                $pdo,
                $metodoPagamento,
                (int) $pagamentoAtual['prazo_recebimento_dias_aplicado'],
                $valorCobrado
            );
            $valoresPagamento = separarValorPagamento($valorCobrado, $taxaOperacional);
            $valorPagamento = $valoresPagamento['valor'];
            $valorCobrado = $valoresPagamento['total_cobrado'];
            $taxaOperacional['taxa'] = round(
                $valorPagamento * $taxaOperacional['percentual'] / 100
                    + $taxaOperacional['tarifa_fixa'],
                2
            );
        } else {
            $valorPagamento = round((float) $pagamentoAtual['valor'], 2);
            $taxaOperacional = [
                'prazo' => (int) $pagamentoAtual['prazo_recebimento_dias_aplicado'],
                'percentual' => (float) $pagamentoAtual['percentual_taxa_operacional_aplicada'],
                'tarifa_fixa' => (float) $pagamentoAtual['tarifa_fixa_operacional_aplicada'],
                'taxa' => (float) $pagamentoAtual['taxa_operacional_aplicada'],
                'repassar_cliente' => (int) $pagamentoAtual['taxa_repassada_cliente_aplicada'] === 1
            ];
        }
        $descontoDisponivel = calcularDescontoVista($pdo, $conta, $metodoPagamento);
        $descontoAplicado = 0.0;
        if ($valorPagamento > $conta['saldo']) {
            $pdo->rollBack();
            responderJson(['sucesso' => false, 'mensagem' => 'O valor excede o saldo atual da estadia. Atualize os dados antes de confirmar.'], 409);
        }
        if ($statusPagamento === 'aprovado' && $descontoDisponivel > 0) {
            $valorComDesconto = round($conta['saldo'] - $descontoDisponivel, 2);
            if ($valorPagamento === round($conta['saldo'], 2)) {
                $totalComDesconto = round(
                    $valorComDesconto
                    + ($taxaOperacional['repassar_cliente']
                        ? round(
                            $valorComDesconto * $taxaOperacional['percentual'] / 100
                                + $taxaOperacional['tarifa_fixa'],
                            2
                        )
                        : 0),
                    2
                );
                $pdo->rollBack();
                responderJson([
                    'sucesso' => false,
                    'mensagem' => 'Este método tem desconto à vista. O total para quitar o saldo é R$ '
                        . number_format($totalComDesconto, 2, ',', '.') . '.'
                ], 422);
            }
            if ($valorPagamento === $valorComDesconto) {
                $descontoAplicado = $descontoDisponivel;
            }
        }
        $dados['valor'] = $valorPagamento;
        $dados['valor_cobrado_cliente'] = $valorCobrado;
        $dados['desconto_aplicado'] = $statusPagamento === 'aprovado' ? $descontoAplicado : 0;
        $dados['prazo_recebimento_dias_aplicado'] = $taxaOperacional['prazo'];
        $dados['percentual_taxa_operacional_aplicada'] = $taxaOperacional['percentual'];
        $dados['tarifa_fixa_operacional_aplicada'] = $taxaOperacional['tarifa_fixa'];
        $dados['taxa_operacional_aplicada'] = $taxaOperacional['taxa'];
        $dados['taxa_repassada_cliente_aplicada'] = $taxaOperacional['repassar_cliente'] ? 1 : 0;
        if ($statusPagamento === 'aprovado' && empty($dados['pago_em'])) {
            $dados['pago_em'] = date('Y-m-d H:i:s');
        }
    }

    // Hashes de senha precisam ser gerados no servidor.
    if ($tabela === 'usuarios' && isset($dados['senha_hash'])) {
        $dados['senha_hash'] = password_hash(
            (string) $dados['senha_hash'],
            PASSWORD_DEFAULT
        );
    }

    // O usuário que registra consumo/pagamento é definido pela sessão.
    if (
        ($tabela === 'consumo' || $tabela === 'pagamentos')
        && $metodo === 'POST'
    ) {
        $dados['registrado_por'] = $usuario['id'];
    }

    $transacaoPagamentoAberta = $tabela === 'pagamentos'
        && $metodo === 'PUT'
        && $pdo->inTransaction();

    if ($tabela === 'pagamentos' && $metodo === 'POST') {
        $pdo->beginTransaction();
        $transacaoPagamentoAberta = true;
        $conta = consultarSaldoEstadia($pdo, (int) $dados['estadia_id']);
        if (!$conta || $conta['estadia']['status'] === 'cancelada') {
            $pdo->rollBack();
            responderJson(['sucesso' => false, 'mensagem' => 'A estadia não está disponível para pagamentos.'], 409);
        }
        $valorCobrado = round((float) $dados['valor'], 2);
        $prazoRecebimento = (int) $dados['prazo_recebimento_dias_aplicado'];
        $taxaOperacional = consultarTaxaOperacional(
            $pdo,
            (string) $dados['metodo'],
            $prazoRecebimento,
            $valorCobrado
        );
        $valoresPagamento = separarValorPagamento($valorCobrado, $taxaOperacional);
        $valorPagamento = $valoresPagamento['valor'];
        $valorCobrado = $valoresPagamento['total_cobrado'];
        $taxaOperacional['taxa'] = round(
            $valorPagamento * $taxaOperacional['percentual'] / 100
                + $taxaOperacional['tarifa_fixa'],
            2
        );
        $descontoDisponivel = calcularDescontoVista($pdo, $conta, (string) $dados['metodo']);
        $descontoAplicado = 0.0;
        if ($valorPagamento > $conta['saldo']) {
            $pdo->rollBack();
            responderJson(['sucesso' => false, 'mensagem' => 'O valor excede o saldo atual da estadia. Atualize os dados antes de registrar.'], 409);
        }
        if ($dados['status'] === 'aprovado' && $descontoDisponivel > 0) {
            $valorComDesconto = round($conta['saldo'] - $descontoDisponivel, 2);
            if ($valorPagamento === round($conta['saldo'], 2)) {
                $totalComDesconto = round(
                    $valorComDesconto
                    + ($taxaOperacional['repassar_cliente']
                        ? round(
                            $valorComDesconto * $taxaOperacional['percentual'] / 100
                                + $taxaOperacional['tarifa_fixa'],
                            2
                        )
                        : 0),
                    2
                );
                $pdo->rollBack();
                responderJson([
                    'sucesso' => false,
                    'mensagem' => 'Este método tem desconto à vista. O total para quitar o saldo é R$ '
                        . number_format($totalComDesconto, 2, ',', '.') . '.'
                ], 422);
            }
            if ($valorPagamento === $valorComDesconto) {
                $descontoAplicado = $descontoDisponivel;
            }
        }
        $dados['valor'] = $valorPagamento;
        $dados['valor_cobrado_cliente'] = $valorCobrado;
        $dados['desconto_aplicado'] = $dados['status'] === 'aprovado' ? $descontoAplicado : 0;
        $dados['percentual_taxa_operacional_aplicada'] = $taxaOperacional['percentual'];
        $dados['tarifa_fixa_operacional_aplicada'] = $taxaOperacional['tarifa_fixa'];
        $dados['taxa_operacional_aplicada'] = $taxaOperacional['taxa'];
        $dados['taxa_repassada_cliente_aplicada'] = $taxaOperacional['repassar_cliente'] ? 1 : 0;
    }

    /*
     * INSERÇÃO
     */
    if ($metodo === 'POST') {
        if (!$dados) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Nenhum campo válido foi enviado.'
            ], 422);
        }

        // Não criar administradores pela API genérica.
        if ($tabela === 'usuarios') {
            $dados['tipo'] = 'cliente';

            if (empty($dados['senha_hash'])) {
                responderJson([
                    'sucesso' => false,
                    'mensagem' => 'Informe uma senha.'
                ], 422);
            }
        }

        $campos = array_keys($dados);
        $nomes = array_map(
            static fn($campo) => '`' . $campo . '`',
            $campos
        );
        $marcadores = array_map(
            static fn($campo) => ':' . $campo,
            $campos
        );

        $sql = 'INSERT INTO `' . $tabela . '` ('
            . implode(', ', $nomes)
            . ') VALUES ('
            . implode(', ', $marcadores)
            . ')';

        if (!$transacaoPagamentoAberta) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($dados);

        $novoId = (int) $pdo->lastInsertId();

        registrarLog(
            $pdo,
            $usuario['id'],
            'inserir',
            $tabela,
            $novoId,
            'Novo registro criado pela API.'
        );

        $pdo->commit();

        responderJson([
            'sucesso' => true,
            'mensagem' => 'Registro criado.',
            'id' => $novoId
        ], 201);
    }

    /*
     * ATUALIZAÇÃO
     */
    if ($metodo === 'PUT') {
        if ($id === null || $id < 1) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe um ID válido para atualizar.'
            ], 422);
        }

        if (!$dados) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Nenhum campo válido foi enviado para atualizar.'
            ], 422);
        }

        $set = [];

        foreach (array_keys($dados) as $campo) {
            $set[] = '`' . $campo . '` = :' . $campo;
        }

        $dados['__id'] = $id;

        $sql = 'UPDATE `' . $tabela . '` SET '
            . implode(', ', $set)
            . ' WHERE id = :__id';

        if (!$transacaoPagamentoAberta) {
            $pdo->beginTransaction();
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($dados);

        registrarLog(
            $pdo,
            $usuario['id'],
            'atualizar',
            $tabela,
            $id,
            'Registro atualizado pela API.'
        );

        $pdo->commit();
        responderJson([
            'sucesso' => true,
            'mensagem' => 'Registro atualizado.'
        ]);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[MotelSahara api] ' . $e->getMessage());

    responderJson([
        'sucesso' => false,
        'mensagem' => 'Não foi possível concluir a operação. Confira os dados enviados e o log do PHP.'
    ], 500);
}