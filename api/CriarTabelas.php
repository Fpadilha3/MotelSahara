<?php
declare(strict_types=1);

require_once __DIR__ . '/conexao.php';
require_once __DIR__ . '/resposta.php';

function CriarTabelas(): void
{
    $pdoServidor = conectarServidor();

    $nomeBancoSeguro = '`' . str_replace('`', '``', DB_NAME) . '`';

    $pdoServidor->exec(
        "CREATE DATABASE IF NOT EXISTS {$nomeBancoSeguro}
         CHARACTER SET utf8mb4
         COLLATE utf8mb4_unicode_ci"
    );

    $pdo = conectarBanco();

    $sqls = [];

    $sqls[] = "
        CREATE TABLE IF NOT EXISTS usuarios (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            telefone VARCHAR(25) DEFAULT NULL,
            senha_hash VARCHAR(255) NOT NULL,
            tipo ENUM('admin','cliente') NOT NULL DEFAULT 'cliente',
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_usuarios_email (email),
            KEY idx_usuarios_tipo_ativo (tipo, ativo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $sqls[] = "
        CREATE TABLE IF NOT EXISTS clientes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED NOT NULL,
            cpf VARCHAR(14) DEFAULT NULL,
            data_nascimento DATE DEFAULT NULL,
            observacoes VARCHAR(500) DEFAULT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_clientes_usuario (usuario_id),
            UNIQUE KEY uq_clientes_cpf (cpf),
            CONSTRAINT fk_clientes_usuario
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $sqls[] = "
        CREATE TABLE IF NOT EXISTS quartos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            numero VARCHAR(20) NOT NULL,
            nome VARCHAR(100) NOT NULL,
            categoria ENUM('standard','luxo','premium','presidencial')
                NOT NULL DEFAULT 'standard',
            descricao TEXT DEFAULT NULL,
            capacidade_pessoas TINYINT UNSIGNED NOT NULL DEFAULT 2,
            maximo_pessoas SMALLINT UNSIGNED NOT NULL DEFAULT 2,
            preco_pessoa_extra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            preco_periodo DECIMAL(10,2) NOT NULL,
            duracao_periodo_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 180,
            preco_hora_extra DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            percentual_taxa_servico DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            status ENUM('disponivel','ocupado','manutencao','inativo')
                NOT NULL DEFAULT 'disponivel',
            imagem_url VARCHAR(500) DEFAULT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_quartos_numero (numero),
            KEY idx_quartos_status_categoria (status, categoria)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $sqls[] = "
        CREATE TABLE IF NOT EXISTS frigobar (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(120) NOT NULL,
            descricao VARCHAR(255) DEFAULT NULL,
            categoria ENUM('bebida','snack','doce','higiene','outro')
                NOT NULL DEFAULT 'outro',
            preco DECIMAL(10,2) NOT NULL,
            estoque INT UNSIGNED NOT NULL DEFAULT 0,
            percentual_taxa DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            imagem_url VARCHAR(500) DEFAULT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_frigobar_ativo_categoria (ativo, categoria)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $sqls[] = "
        CREATE TABLE IF NOT EXISTS estadias (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            codigo VARCHAR(30) NOT NULL,
            cliente_id BIGINT UNSIGNED NOT NULL,
            quarto_id BIGINT UNSIGNED NOT NULL,
            status ENUM('reservada','em_andamento','finalizada','cancelada')
                NOT NULL DEFAULT 'reservada',
            entrada_prevista DATETIME DEFAULT NULL,
            entrada_real DATETIME DEFAULT NULL,
            saida_prevista DATETIME DEFAULT NULL,
            saida_real DATETIME DEFAULT NULL,
            quantidade_pessoas TINYINT UNSIGNED NOT NULL DEFAULT 2,
            pessoas_incluidas_aplicado SMALLINT UNSIGNED NOT NULL DEFAULT 2,
            preco_periodo_aplicado DECIMAL(10,2) NOT NULL,
            preco_hora_extra_aplicado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            preco_pessoa_extra_aplicado DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            percentual_taxa_aplicado DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            valor_total_previsto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            observacoes VARCHAR(500) DEFAULT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_estadias_codigo (codigo),
            KEY idx_estadias_cliente (cliente_id),
            KEY idx_estadias_quarto_status (quarto_id, status),
            KEY idx_estadias_periodo (entrada_prevista, saida_prevista),
            CONSTRAINT fk_estadias_cliente
                FOREIGN KEY (cliente_id) REFERENCES clientes(id)
                ON UPDATE CASCADE,
            CONSTRAINT fk_estadias_quarto
                FOREIGN KEY (quarto_id) REFERENCES quartos(id)
                ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $sqls[] = "
        CREATE TABLE IF NOT EXISTS consumo (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            estadia_id BIGINT UNSIGNED NOT NULL,
            frigobar_id BIGINT UNSIGNED NOT NULL,
            quantidade INT UNSIGNED NOT NULL,
            preco_unitario DECIMAL(10,2) NOT NULL,
            percentual_taxa_aplicado DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            subtotal DECIMAL(12,2) NOT NULL,
            registrado_por BIGINT UNSIGNED DEFAULT NULL,
            registrado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            observacoes VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_consumo_estadia (estadia_id),
            KEY idx_consumo_produto (frigobar_id),
            KEY fk_consumo_usuario (registrado_por),
            CONSTRAINT fk_consumo_estadia
                FOREIGN KEY (estadia_id) REFERENCES estadias(id)
                ON UPDATE CASCADE,
            CONSTRAINT fk_consumo_frigobar
                FOREIGN KEY (frigobar_id) REFERENCES frigobar(id)
                ON UPDATE CASCADE,
            CONSTRAINT fk_consumo_usuario
                FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $sqls[] = "
        CREATE TABLE IF NOT EXISTS pagamentos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            estadia_id BIGINT UNSIGNED NOT NULL,
            codigo_transacao VARCHAR(100) DEFAULT NULL,
            valor DECIMAL(12,2) NOT NULL,
            metodo ENUM('dinheiro','pix','credito','debito','transferencia','outro')
                NOT NULL,
            status ENUM('pendente','aprovado','recusado','estornado','cancelado')
                NOT NULL DEFAULT 'pendente',
            pago_em DATETIME DEFAULT NULL,
            registrado_por BIGINT UNSIGNED DEFAULT NULL,
            observacoes VARCHAR(500) DEFAULT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_pagamentos_transacao (codigo_transacao),
            KEY idx_pagamentos_estadia_status (estadia_id, status),
            KEY idx_pagamentos_data (pago_em),
            KEY fk_pagamentos_usuario (registrado_por),
            CONSTRAINT fk_pagamentos_estadia
                FOREIGN KEY (estadia_id) REFERENCES estadias(id)
                ON UPDATE CASCADE,
            CONSTRAINT fk_pagamentos_usuario
                FOREIGN KEY (registrado_por) REFERENCES usuarios(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $sqls[] = "
        CREATE TABLE IF NOT EXISTS log_admin (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            usuario_id BIGINT UNSIGNED DEFAULT NULL,
            acao VARCHAR(100) NOT NULL,
            entidade VARCHAR(80) NOT NULL,
            entidade_id BIGINT UNSIGNED DEFAULT NULL,
            detalhes TEXT DEFAULT NULL,
            criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_log_admin_usuario_data (usuario_id, criado_em),
            CONSTRAINT fk_log_admin_usuario
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                ON DELETE SET NULL ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    foreach ($sqls as $sql) {
        $pdo->exec($sql);
    }

    $colunasNovas = [
        'quartos' => [
            'maximo_pessoas' => 'SMALLINT UNSIGNED NULL DEFAULT NULL',
            'preco_pessoa_extra' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00'
        ],
        'estadias' => [
            'pessoas_incluidas_aplicado' => 'SMALLINT UNSIGNED NULL DEFAULT NULL',
            'preco_hora_extra_aplicado' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
            'preco_pessoa_extra_aplicado' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00',
            'valor_total_previsto' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00'
        ]
    ];

    foreach ($colunasNovas as $tabela => $colunasTabela) {
        foreach ($colunasTabela as $coluna => $definicao) {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tabela
                   AND COLUMN_NAME = :coluna'
            );
            $stmt->execute([
                'tabela' => $tabela,
                'coluna' => $coluna
            ]);

            if ((int) $stmt->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE `' . $tabela . '` ADD COLUMN `'
                    . $coluna . '` ' . $definicao
                );
            }
        }
    }

    $pdo->exec(
        'UPDATE quartos
         SET maximo_pessoas = GREATEST(capacidade_pessoas, 1)
         WHERE maximo_pessoas IS NULL'
    );
    $pdo->exec(
        'ALTER TABLE quartos
         MODIFY maximo_pessoas SMALLINT UNSIGNED NOT NULL DEFAULT 2'
    );
    $pdo->exec(
        'UPDATE estadias e
         INNER JOIN quartos q ON q.id = e.quarto_id
         SET e.pessoas_incluidas_aplicado = q.capacidade_pessoas
         WHERE e.pessoas_incluidas_aplicado IS NULL'
    );
    $pdo->exec(
        'ALTER TABLE estadias
         MODIFY pessoas_incluidas_aplicado SMALLINT UNSIGNED NOT NULL DEFAULT 2'
    );
    $pdo->exec(
        'UPDATE estadias
         SET valor_total_previsto = ROUND(
             preco_periodo_aplicado * (1 + percentual_taxa_aplicado / 100),
             2
         )
         WHERE valor_total_previsto = 0 AND preco_periodo_aplicado > 0'
    );
}

try {
    // Evita que qualquer visitante da internet execute o instalador.
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    $localPermitido = PHP_SAPI === 'cli'
        || in_array($ip, ['127.0.0.1', '::1'], true)
        || in_array($host, ['localhost', 'localhost:80', 'localhost:8080'], true);

    if (!$localPermitido) {
        responderJson([
            'sucesso' => false,
            'mensagem' => 'Instalador permitido somente localmente.'
        ], 403);
    }

    CriarTabelas();

    responderJson([
        'sucesso' => true,
        'mensagem' => 'Banco e tabelas verificados/criados.',
        'banco' => DB_NAME,
        'tabelas' => [
            'usuarios', 'clientes', 'quartos', 'frigobar',
            'estadias', 'consumo', 'pagamentos', 'log_admin'
        ]
    ]);
} catch (Throwable $e) {
    error_log('[MotelSahara CriarTabelas] ' . $e->getMessage());

    responderJson([
        'sucesso' => false,
        'mensagem' => 'Não foi possível criar/verificar o banco. Confira as credenciais e o log do PHP.'
    ], 500);
}