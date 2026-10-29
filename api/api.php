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
        'estadia_id', 'codigo_transacao', 'valor', 'metodo', 'status',
        'pago_em', 'registrado_por', 'observacoes'
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

try {
    /*
     * LEITURA
     */
    if ($metodo === 'GET') {
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
                $where[] = "status = 'disponivel'";
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
            $where[] = 'id = :id';
            $params['id'] = $id;
        }

        $sql = 'SELECT * FROM `' . $tabela . '`';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY id DESC LIMIT 500';

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
                 preco_periodo_aplicado, preco_hora_extra_aplicado,
                 preco_pessoa_extra_aplicado, percentual_taxa_aplicado,
                 valor_total_previsto, observacoes)
             VALUES
                (:codigo, :cliente_id, :quarto_id, "reservada", :entrada_prevista,
                 :saida_prevista, :quantidade_pessoas, :pessoas_incluidas,
                 :preco_periodo, 0, :preco_pessoa_extra,
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
            'valor_total_previsto' => $valorTotalPrevisto
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

    // Campos sensíveis não podem ser manipulados livremente pelo cadastro.
    if ($tabela === 'usuarios') {
        if ($metodo === 'POST') {
            $permitidas = ['nome', 'email', 'telefone', 'senha_hash', 'tipo', 'ativo'];
        } else {
            $permitidas = ['nome', 'email', 'telefone', 'senha_hash', 'ativo'];
        }
    }

    $dados = filtrarDados($entrada, $permitidas);

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
        if ($dados['status'] === 'aprovado' && empty($dados['pago_em'])) {
            $dados['pago_em'] = date('Y-m-d H:i:s');
        }
    }

    if ($tabela === 'pagamentos' && $metodo === 'PUT' && isset($dados['status'])) {
        $statusValidos = [
            'pendente', 'aprovado', 'recusado', 'estornado', 'cancelado'
        ];
        if (!in_array($dados['status'], $statusValidos, true)) {
            responderJson([
                'sucesso' => false,
                'mensagem' => 'Informe uma situação válida para o pagamento.'
            ], 422);
        }
        if ($dados['status'] === 'aprovado' && empty($dados['pago_em'])) {
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

        $pdo->beginTransaction();

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

        $pdo->beginTransaction();
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