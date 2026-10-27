<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';
require_once __DIR__ . '/proteger.php';

$pdo = conectarBanco();
$usuario = exigirLogin();

$tabela = strtolower(trim((string) ($_GET['tabela'] ?? '')));
$id = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : null;
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$colunas = [
    'usuarios' => [
        'nome', 'email', 'telefone', 'senha_hash', 'tipo', 'ativo'
    ],
    'clientes' => [
        'usuario_id', 'cpf', 'data_nascimento', 'observacoes'
    ],
    'quartos' => [
        'numero', 'nome', 'categoria', 'descricao', 'capacidade_pessoas',
        'preco_periodo', 'duracao_periodo_minutos', 'preco_hora_extra',
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

$somenteAdmin = [
    'usuarios', 'clientes', 'estadias', 'consumo', 'pagamentos', 'log_admin'
];

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
                $where[] = "status <> 'inativo'";
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

    /*
     * TODAS AS ALTERAÇÕES EXIGEM ADMINISTRADOR.
     */
    exigirAdmin();

    if (!in_array($metodo, ['POST', 'PUT', 'DELETE'], true)) {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'Método não permitido.'
        ], 405);
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

        $stmt = $pdo->prepare('DELETE FROM `' . $tabela . '` WHERE id = :id');
        $stmt->execute(['id' => $id]);

        if ($stmt->rowCount() === 0) {
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

        responderJson([
            'sucesso' => true,
            'mensagem' => 'Registro excluído.'
        ]);
    }

    $entrada = lerJson();
    $permitidas = $colunas[$tabela];

    // Campos sensíveis não podem ser manipulados livremente pelo cadastro.
    if ($tabela === 'usuarios') {
        if ($metodo === 'POST') {
            $permitidas = ['nome', 'email', 'telefone', 'senha_hash', 'tipo', 'ativo'];
        } else {
            $permitidas = ['nome', 'email', 'telefone', 'senha_hash', 'ativo'];
        }
    }

    $dados = filtrarDados($entrada, $permitidas);

    // Hashes de senha precisam ser gerados no servidor.
    if ($tabela === 'usuarios' && isset($dados['senha_hash'])) {
        $dados['senha_hash'] = password_hash(
            (string) $dados['senha_hash'],
            PASSWORD_DEFAULT
        );
    }

    // O usuário que registra consumo/pagamento é definido pela sessão.
    if ($tabela === 'consumo' || $tabela === 'pagamentos') {
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