/**
 * ============================================================
 * MOTEL SAHARA — JAVASCRIPT PRINCIPAL
 * Tema: Árabe / Oriental / Luxo
 * Arquivo: assets/js/script.js
 * ============================================================
 *
 * Funcionalidades:
 * - Menu responsivo
 * - Formulários e validações
 * - Mostrar/ocultar senha
 * - Confirmações de ações
 * - Modais
 * - Notificações
 * - Pesquisa e filtros
 * - Galeria de imagens
 * - Abas
 * - Relógio e data
 * - Carrinho de consumo
 * - Controles de quantidade
 * - Máscaras de campos
 * - Animações de entrada
 * - Tabelas administrativas
 *
 * Observação:
 * Login, cadastro, pagamentos e persistência de dados
 * devem ser processados e validados no servidor PHP.
 * ============================================================
 */

"use strict";

/* ============================================================
   1. CONFIGURAÇÕES GERAIS
============================================================ */

const Sahara = {
    nome: "Motel Sahara",
    dadosPainel: {},

    seletores: {
        menuToggle: "[data-menu-toggle]",
        menu: "[data-menu]",
        modal: "[data-modal]",
        modalAbrir: "[data-modal-open]",
        modalFechar: "[data-modal-close]",
        senhaToggle: "[data-password-toggle], [data-toggle-password]",
        notificacao: "[data-toast]",
        formulario: "form[data-validate]",
        filtro: "[data-filter]",
        pesquisa: "[data-search]",
        tabela: "[data-table]",
        aba: "[data-tab]",
        conteudoAba: "[data-tab-content]"
    },

    configuracoes: {
        duracaoNotificacao: 3500,
        duracaoAnimacao: 250,
        breakpointMobile: 768
    }
};


/* ============================================================
   2. FUNÇÕES AUXILIARES
============================================================ */

/**
 * Seleciona um elemento do documento.
 */
function selecionar(seletor, contexto = document) {
    return contexto.querySelector(seletor);
}

/**
 * Seleciona vários elementos.
 */
function selecionarTodos(seletor, contexto = document) {
    return Array.from(contexto.querySelectorAll(seletor));
}

/**
 * Verifica se um elemento existe.
 */
function elementoExiste(elemento) {
    return elemento !== null && elemento !== undefined;
}

/**
 * Escapa caracteres especiais antes de inserir texto
 * proveniente de entradas do usuário em contextos HTML.
 */
function escaparHTML(valor) {
    return String(valor)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/**
 * Formata um valor numérico como moeda brasileira.
 */
function formatarMoeda(valor) {
    const numero = Number(valor);

    if (!Number.isFinite(numero)) {
        return "R$ 0,00";
    }

    return numero.toLocaleString("pt-BR", {
        style: "currency",
        currency: "BRL"
    });
}

/**
 * Formata uma data para o padrão brasileiro.
 */
function formatarData(data) {
    const dataConvertida = data instanceof Date
        ? data
        : new Date(data);

    if (Number.isNaN(dataConvertida.getTime())) {
        return "";
    }

    return dataConvertida.toLocaleDateString("pt-BR");
}

/**
 * Formata data e horário.
 */
function formatarDataHora(data) {
    const dataConvertida = data instanceof Date
        ? data
        : new Date(data);

    if (Number.isNaN(dataConvertida.getTime())) {
        return "";
    }

    return dataConvertida.toLocaleString("pt-BR", {
        dateStyle: "short",
        timeStyle: "short"
    });
}


/* ============================================================
   3. MENU RESPONSIVO
============================================================ */

function inicializarMenu() {
    const botoesMenu = selecionarTodos(
        Sahara.seletores.menuToggle
    );

    botoesMenu.forEach((botao) => {
        const seletorMenu = botao.getAttribute("data-menu");
        const menu = seletorMenu
            ? selecionar(seletorMenu)
            : selecionar(Sahara.seletores.menu);

        if (!elementoExiste(menu)) {
            return;
        }

        botao.setAttribute("aria-expanded", "false");

        botao.addEventListener("click", () => {
            const aberto = menu.classList.toggle("is-open");

            botao.setAttribute(
                "aria-expanded",
                String(aberto)
            );

            document.body.classList.toggle(
                "menu-aberto",
                aberto
            );
        });

        selecionarTodos("a", menu).forEach((link) => {
            link.addEventListener("click", () => {
                menu.classList.remove("is-open");
                botao.setAttribute("aria-expanded", "false");

                document.body.classList.remove("menu-aberto");
            });
        });
    });

    document.addEventListener("click", (evento) => {
        if (window.innerWidth > Sahara.configuracoes.breakpointMobile) {
            return;
        }

        const menuAberto = selecionar(".is-open");

        if (
            menuAberto &&
            !menuAberto.contains(evento.target) &&
            !evento.target.closest(Sahara.seletores.menuToggle)
        ) {
            menuAberto.classList.remove("is-open");

            selecionarTodos(Sahara.seletores.menuToggle)
                .forEach((botao) => {
                    botao.setAttribute("aria-expanded", "false");
                });

            document.body.classList.remove("menu-aberto");
        }
    });
}


/* ============================================================
   4. BOTÃO DE MOSTRAR E OCULTAR SENHA
============================================================ */

function inicializarMostrarSenha() {
    selecionarTodos(Sahara.seletores.senhaToggle)
        .forEach((botao) => {
            botao.addEventListener("click", () => {
                const seletor = botao.getAttribute(
                    "data-password-toggle"
                );

                const campo = seletor
                    ? selecionar(seletor)
                    : botao.parentElement?.querySelector("input");

                if (!elementoExiste(campo)) {
                    return;
                }

                const mostrar = campo.type === "password";

                campo.type = mostrar ? "text" : "password";

                botao.setAttribute(
                    "aria-label",
                    mostrar ? "Ocultar senha" : "Mostrar senha"
                );

                botao.setAttribute(
                    "aria-pressed",
                    String(mostrar)
                );

                const texto = botao.querySelector(
                    "[data-password-label]"
                );

                if (texto) {
                    texto.textContent = mostrar
                        ? "Ocultar"
                        : "Mostrar";
                }
            });
        });
}


/* ============================================================
   5. NOTIFICAÇÕES E MENSAGENS
============================================================ */

function criarAreaNotificacoes() {
    let area = selecionar("#sahara-toasts");

    if (!area) {
        area = document.createElement("div");

        area.id = "sahara-toasts";
        area.className = "sahara-toasts";

        area.setAttribute("aria-live", "polite");
        area.setAttribute("aria-atomic", "false");

        document.body.appendChild(area);
    }

    return area;
}

/**
 * Exemplo:
 * notificar("Cadastro realizado!", "sucesso");
 * notificar("Confira os campos.", "aviso");
 * notificar("Ocorreu um erro.", "erro");
 */
function notificar(mensagem, tipo = "info", duracao) {
    const area = criarAreaNotificacoes();
    const notificacao = document.createElement("div");

    const tiposPermitidos = [
        "sucesso",
        "erro",
        "aviso",
        "info"
    ];

    const tipoSeguro = tiposPermitidos.includes(tipo)
        ? tipo
        : "info";

    notificacao.className =
        `sahara-toast sahara-toast--${tipoSeguro}`;

    const texto = document.createElement("span");
    texto.className = "sahara-toast__message";
    texto.textContent = String(mensagem);

    const fechar = document.createElement("button");
    fechar.type = "button";
    fechar.className = "sahara-toast__close";
    fechar.textContent = "×";
    fechar.setAttribute("aria-label", "Fechar notificação");

    fechar.addEventListener("click", () => {
        notificacao.remove();
    });

    notificacao.append(texto, fechar);
    area.appendChild(notificacao);

    const tempo = Number.isFinite(duracao)
        ? duracao
        : Sahara.configuracoes.duracaoNotificacao;

    window.setTimeout(() => {
        notificacao.remove();
    }, Math.max(1000, tempo));
}


/* ============================================================
   6. MODAIS
============================================================ */

function abrirModal(modal) {
    if (!elementoExiste(modal)) {
        return;
    }

    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");

    document.body.classList.add("modal-aberto");

    const primeiroCampo = modal.querySelector(
        "input, select, textarea, button"
    );

    if (primeiroCampo) {
        primeiroCampo.focus();
    }
}

function fecharModal(modal) {
    if (!elementoExiste(modal)) {
        return;
    }

    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");

    if (!selecionar(".modal.is-open, [data-modal].is-open")) {
        document.body.classList.remove("modal-aberto");
    }
}

function inicializarModais() {
    selecionarTodos(Sahara.seletores.modalAbrir)
        .forEach((botao) => {
            botao.addEventListener("click", () => {
                const seletor = botao.getAttribute(
                    "data-modal-open"
                );

                if (!seletor) {
                    return;
                }

                abrirModal(selecionar(seletor));
            });
        });

    selecionarTodos(Sahara.seletores.modalFechar)
        .forEach((botao) => {
            botao.addEventListener("click", () => {
                const modal = botao.closest(
                    "[data-modal], .modal"
                );

                fecharModal(modal);
            });
        });

    selecionarTodos(
        `${Sahara.seletores.modal}, .modal`
    ).forEach((modal) => {
        modal.setAttribute("aria-hidden", "true");

        modal.addEventListener("click", (evento) => {
            if (
                evento.target === modal &&
                modal.hasAttribute("data-close-outside")
            ) {
                fecharModal(modal);
            }
        });
    });

    document.addEventListener("keydown", (evento) => {
        if (evento.key === "Escape") {
            selecionarTodos(
                `${Sahara.seletores.modal}.is-open, .modal.is-open`
            ).forEach(fecharModal);
        }
    });
}


/* ============================================================
   7. CONFIRMAÇÃO DE AÇÕES IMPORTANTES
============================================================ */

/**
 * Use:
 * <button data-confirm="Deseja realmente excluir este registro?">
 *     Excluir
 * </button>
 *
 * A confirmação não substitui a validação de permissão no PHP.
 */
function inicializarConfirmacoes() {
    selecionarTodos("[data-confirm]").forEach((elemento) => {
        elemento.addEventListener("click", (evento) => {
            const mensagem = elemento.getAttribute("data-confirm");

            if (!mensagem) {
                return;
            }

            const confirmado = window.confirm(mensagem);

            if (!confirmado) {
                evento.preventDefault();
                evento.stopPropagation();
            }
        });
    });
}


/* ============================================================
   8. VALIDAÇÃO VISUAL DE FORMULÁRIOS
============================================================ */

function definirErro(campo, mensagem) {
    campo.classList.add("is-invalid");
    campo.setAttribute("aria-invalid", "true");

    let erro = campo.parentElement?.querySelector(
        `[data-error-for="${campo.name}"]`
    );

    if (!erro) {
        erro = document.createElement("small");
        erro.className = "field-error";
        erro.setAttribute("role", "alert");

        if (campo.name) {
            erro.setAttribute("data-error-for", campo.name);
        }

        campo.insertAdjacentElement("afterend", erro);
    }

    erro.textContent = mensagem;
}

function limparErro(campo) {
    campo.classList.remove("is-invalid");
    campo.removeAttribute("aria-invalid");

    const erro = campo.parentElement?.querySelector(
        `[data-error-for="${campo.name}"]`
    );

    if (erro) {
        erro.remove();
    }
}

function validarCampo(campo) {
    limparErro(campo);

    if (campo.disabled || campo.type === "hidden") {
        return true;
    }

    if (campo.required && !campo.value.trim()) {
        definirErro(campo, "Este campo é obrigatório.");
        return false;
    }

    if (
        campo.type === "email" &&
        campo.value.trim() !== "" &&
        !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(campo.value.trim())
    ) {
        definirErro(campo, "Informe um e-mail válido.");
        return false;
    }

    if (
        campo.minLength > 0 &&
        campo.value.length < campo.minLength
    ) {
        definirErro(
            campo,
            `Digite pelo menos ${campo.minLength} caracteres.`
        );

        return false;
    }

    if (
        campo.type === "number" &&
        campo.value !== "" &&
        !Number.isNaN(Number(campo.value))
    ) {
        const numero = Number(campo.value);

        if (
            campo.min !== "" &&
            numero < Number(campo.min)
        ) {
            definirErro(
                campo,
                `O valor mínimo permitido é ${campo.min}.`
            );

            return false;
        }

        if (
            campo.max !== "" &&
            numero > Number(campo.max)
        ) {
            definirErro(
                campo,
                `O valor máximo permitido é ${campo.max}.`
            );

            return false;
        }
    }

    if (!campo.checkValidity()) {
        definirErro(
            campo,
            campo.validationMessage || "Verifique este campo."
        );

        return false;
    }

    return true;
}

function inicializarValidacaoFormularios() {
    selecionarTodos(Sahara.seletores.formulario)
        .forEach((formulario) => {
            const campos = selecionarTodos(
                "input, select, textarea",
                formulario
            );

            campos.forEach((campo) => {
                campo.addEventListener("blur", () => {
                    validarCampo(campo);
                });

                campo.addEventListener("input", () => {
                    if (campo.classList.contains("is-invalid")) {
                        validarCampo(campo);
                    }
                });
            });

            formulario.addEventListener("submit", (evento) => {
                let formularioValido = true;
                let primeiroCampoInvalido = null;

                campos.forEach((campo) => {
                    const valido = validarCampo(campo);

                    if (!valido) {
                        formularioValido = false;

                        if (!primeiroCampoInvalido) {
                            primeiroCampoInvalido = campo;
                        }
                    }
                });

                if (!formularioValido) {
                    evento.preventDefault();

                    notificar(
                        "Confira os campos destacados antes de continuar.",
                        "aviso"
                    );

                    primeiroCampoInvalido?.focus();
                }

                /*
                 * Se todos os campos estiverem válidos, o formulário
                 * seguirá o fluxo normal definido no HTML/PHP.
                 *
                 * Não impeça o envio aqui, pois o PHP precisa receber
                 * os dados para processá-los no servidor.
                 */
            });
        });
}


/* ============================================================
   9. CONFIRMAÇÃO DE SENHA NO CADASTRO
============================================================ */

function inicializarConfirmacaoSenha() {
    const camposConfirmacao = selecionarTodos(
        "[data-password-confirm]"
    );

    camposConfirmacao.forEach((confirmacao) => {
        const seletorSenha = confirmacao.getAttribute(
            "data-password-confirm"
        );

        const senha = seletorSenha
            ? selecionar(seletorSenha)
            : selecionar("#senha");

        if (!senha) {
            return;
        }

        const verificar = () => {
            limparErro(confirmacao);

            if (
                confirmacao.value &&
                senha.value !== confirmacao.value
            ) {
                definirErro(
                    confirmacao,
                    "As senhas não são iguais."
                );
            }
        };

        confirmacao.addEventListener("input", verificar);
        senha.addEventListener("input", verificar);
    });
}


/* ============================================================
   10. PESQUISA EM TABELAS E LISTAGENS
============================================================ */

/**
 * Exemplo:
 * <input type="search" data-search="#tabela-registros">
 *
 * O campo pode pesquisar linhas de uma tabela ou cards.
 */
function inicializarPesquisa() {
    selecionarTodos(Sahara.seletores.pesquisa)
        .forEach((campoPesquisa) => {
            campoPesquisa.addEventListener("input", () => {
                const seletorAlvo = campoPesquisa.getAttribute(
                    "data-search"
                );

                if (!seletorAlvo) {
                    return;
                }

                const alvo = selecionar(seletorAlvo);

                if (!alvo) {
                    return;
                }

                const termo = campoPesquisa.value
                    .trim()
                    .toLocaleLowerCase("pt-BR");

                const itens = selecionarTodos(
                    "tbody tr, [data-search-item], .search-item",
                    alvo
                );

                itens.forEach((item) => {
                    const texto = item.textContent
                        .toLocaleLowerCase("pt-BR");

                    item.hidden = !texto.includes(termo);
                });
            });
        });
}


/* ============================================================
   11. FILTROS POR CATEGORIA OU STATUS
============================================================ */

/**
 * Exemplo:
 * <button data-filter="todos">Todos</button>
 * <button data-filter="disponivel">Disponíveis</button>
 *
 * Cards:
 * <article data-filter-item data-category="disponivel">
 */
function inicializarFiltros() {
    selecionarTodos("[data-filter]").forEach((botao) => {
        botao.addEventListener("click", () => {
            const filtro = botao.getAttribute("data-filter");
            const grupo = botao.getAttribute("data-filter-group");

            const seletorItens = grupo
                ? `[data-filter-item][data-filter-group="${grupo}"]`
                : "[data-filter-item]";

            const itens = selecionarTodos(seletorItens);

            selecionarTodos("[data-filter]").forEach((outroBotao) => {
                if (
                    outroBotao.getAttribute("data-filter-group") === grupo
                ) {
                    outroBotao.classList.remove("active");
                    outroBotao.setAttribute("aria-pressed", "false");
                }
            });

            botao.classList.add("active");
            botao.setAttribute("aria-pressed", "true");

            itens.forEach((item) => {
                const categorias = (
                    item.getAttribute("data-category") || ""
                )
                    .split(/\s+/)
                    .filter(Boolean);

                const mostrar =
                    !filtro ||
                    filtro === "todos" ||
                    categorias.includes(filtro);

                item.hidden = !mostrar;
            });
        });
    });
}


/* ============================================================
   12. ABAS DE CONTEÚDO
============================================================ */

/**
 * Exemplo:
 * <button data-tab="perfil">Perfil</button>
 * <section data-tab-content="perfil">...</section>
 */
function inicializarAbas() {
    selecionarTodos(Sahara.seletores.aba).forEach((botao) => {
        botao.addEventListener("click", () => {
            const nomeAba = botao.getAttribute("data-tab");
            const grupo = botao.getAttribute("data-tab-group");

            if (!nomeAba) {
                return;
            }

            const seletorBotoes = grupo
                ? `[data-tab][data-tab-group="${grupo}"]`
                : "[data-tab]";

            const seletorConteudos = grupo
                ? `[data-tab-content][data-tab-group="${grupo}"]`
                : "[data-tab-content]";

            selecionarTodos(seletorBotoes).forEach((outroBotao) => {
                outroBotao.classList.remove("active");
                outroBotao.setAttribute("aria-selected", "false");
            });

            selecionarTodos(seletorConteudos).forEach((conteudo) => {
                conteudo.hidden = true;
                conteudo.classList.remove("active");
            });

            botao.classList.add("active");
            botao.setAttribute("aria-selected", "true");

            const conteudoAtivo = selecionarTodos(
                seletorConteudos
            ).find((conteudo) => {
                return conteudo.getAttribute("data-tab-content") === nomeAba;
            });

            if (conteudoAtivo) {
                conteudoAtivo.hidden = false;
                conteudoAtivo.classList.add("active");
            }
        });
    });
}


/* ============================================================
   13. GALERIA DE IMAGENS
============================================================ */

function inicializarGaleria() {
    const imagens = selecionarTodos("[data-gallery-image]");

    imagens.forEach((imagem) => {
        imagem.style.cursor = "zoom-in";

        imagem.addEventListener("click", () => {
            const src = imagem.currentSrc || imagem.src;

            if (!src) {
                return;
            }

            const modal = document.createElement("div");
            modal.className = "sahara-lightbox";

            modal.setAttribute("role", "dialog");
            modal.setAttribute("aria-modal", "true");
            modal.setAttribute("aria-label", "Visualização da imagem");

            const imagemAmpliada = document.createElement("img");
            imagemAmpliada.src = src;
            imagemAmpliada.alt = imagem.alt || "Imagem ampliada";

            const fechar = document.createElement("button");
            fechar.type = "button";
            fechar.className = "sahara-lightbox__close";
            fechar.textContent = "×";
            fechar.setAttribute("aria-label", "Fechar imagem");

            modal.append(imagemAmpliada, fechar);
            document.body.appendChild(modal);

            const fecharLightbox = () => {
                modal.remove();
            };

            fechar.addEventListener("click", fecharLightbox);

            modal.addEventListener("click", (evento) => {
                if (evento.target === modal) {
                    fecharLightbox();
                }
            });

            const teclaEscape = (evento) => {
                if (evento.key === "Escape") {
                    fecharLightbox();
                    document.removeEventListener("keydown", teclaEscape);
                }
            };

            document.addEventListener("keydown", teclaEscape);
            fechar.focus();
        });
    });
}


/* ============================================================
   14. CONTROLES DE QUANTIDADE
============================================================ */

/**
 * Exemplo:
 * <div data-quantity-control>
 *   <button type="button" data-quantity-minus>-</button>
 *   <input type="number" value="1" min="1" max="20">
 *   <button type="button" data-quantity-plus>+</button>
 * </div>
 */
function inicializarQuantidade() {
    selecionarTodos("[data-quantity-control]")
        .forEach((controle) => {
            const campo = controle.querySelector(
                "input[type='number']"
            );

            const menos = controle.querySelector(
                "[data-quantity-minus]"
            );

            const mais = controle.querySelector(
                "[data-quantity-plus]"
            );

            if (!campo) {
                return;
            }

            const minimo = campo.min !== ""
                ? Number(campo.min)
                : 0;

            const maximo = campo.max !== ""
                ? Number(campo.max)
                : Infinity;

            const ajustar = (valor) => {
                const numero = Number(valor);

                if (!Number.isFinite(numero)) {
                    campo.value = String(minimo);
                    return;
                }

                campo.value = String(
                    Math.min(maximo, Math.max(minimo, numero))
                );

                campo.dispatchEvent(
                    new Event("change", { bubbles: true })
                );
            };

            menos?.addEventListener("click", () => {
                ajustar(Number(campo.value) - 1);
            });

            mais?.addEventListener("click", () => {
                ajustar(Number(campo.value) + 1);
            });

            campo.addEventListener("change", () => {
                ajustar(campo.value);
            });
        });
}


/* ============================================================
   15. CARRINHO VISUAL DE CONSUMO
============================================================ */

/**
 * Esta função calcula apenas o total visual dos itens já
 * existentes na página. Não grava pedidos nem cobra pagamentos.
 *
 * Cada item deve conter:
 * data-cart-item
 * data-price="10.50"
 *
 * A quantidade pode ser informada em:
 * input[data-cart-quantity]
 *
 * O total será exibido em:
 * [data-cart-total]
 */
function calcularTotalCarrinho() {
    const itens = selecionarTodos("[data-cart-item]");
    let total = 0;

    itens.forEach((item) => {
        const preco = Number(item.getAttribute("data-price"));

        const campoQuantidade = item.querySelector(
            "[data-cart-quantity]"
        );

        const quantidade = campoQuantidade
            ? Number(campoQuantidade.value)
            : 1;

        if (
            Number.isFinite(preco) &&
            Number.isFinite(quantidade) &&
            preco >= 0 &&
            quantidade >= 0
        ) {
            total += preco * quantidade;
        }
    });

    selecionarTodos("[data-cart-total]").forEach((elemento) => {
        elemento.textContent = formatarMoeda(total);
    });

    return total;
}

function inicializarCarrinho() {
    document.addEventListener("input", (evento) => {
        if (
            evento.target.matches("[data-cart-quantity]") ||
            evento.target.closest("[data-cart-item]")
        ) {
            calcularTotalCarrinho();
        }
    });

    document.addEventListener("change", (evento) => {
        if (evento.target.matches("[data-cart-quantity]")) {
            calcularTotalCarrinho();
        }
    });

    calcularTotalCarrinho();
}


/* ============================================================
   16. MÁSCARA DE TELEFONE
============================================================ */

/**
 * Formata números brasileiros de telefone.
 * A validação definitiva deve ocorrer no PHP.
 */
function formatarTelefone(valor) {
    const numeros = String(valor).replace(/\D/g, "").slice(0, 11);

    if (numeros.length <= 2) {
        return numeros ? `(${numeros}` : "";
    }

    if (numeros.length <= 6) {
        return `(${numeros.slice(0, 2)}) ${numeros.slice(2)}`;
    }

    if (numeros.length <= 10) {
        return (
            `(${numeros.slice(0, 2)}) ` +
            `${numeros.slice(2, 6)}-${numeros.slice(6)}`
        );
    }

    return (
        `(${numeros.slice(0, 2)}) ` +
        `${numeros.slice(2, 7)}-${numeros.slice(7)}`
    );
}

function inicializarMascaraTelefone() {
    selecionarTodos("[data-mask='telefone'], input[type='tel']")
        .forEach((campo) => {
            campo.addEventListener("input", () => {
                const inicio = campo.selectionStart;
                const valorAnterior = campo.value;

                campo.value = formatarTelefone(campo.value);

                /*
                 * A máscara pode alterar a posição do cursor.
                 * Para entradas normais, reposicionamos o cursor
                 * ao final para evitar saltos inesperados.
                 */
                if (inicio === valorAnterior.length) {
                    campo.setSelectionRange(
                        campo.value.length,
                        campo.value.length
                    );
                }
            });
        });
}


/* ============================================================
   17. FORMATAÇÃO VISUAL DE MOEDA
============================================================ */

function inicializarCamposMoeda() {
    selecionarTodos("[data-currency]").forEach((campo) => {
        campo.addEventListener("blur", () => {
            if (campo.value.trim() === "") {
                return;
            }

            const valor = Number(
                campo.value
                    .replace(/\s/g, "")
                    .replace(/^R\$\s?/, "")
                    .replace(/\./g, "")
                    .replace(",", ".")
            );

            if (Number.isFinite(valor)) {
                campo.value = valor.toLocaleString("pt-BR", {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        });
    });
}


/* ============================================================
   18. BOTÃO DE LIMPAR FORMULÁRIO
============================================================ */

function inicializarLimparFormularios() {
    selecionarTodos("[data-form-reset]").forEach((botao) => {
        botao.addEventListener("click", () => {
            const seletor = botao.getAttribute("data-form-reset");

            const formulario = seletor
                ? selecionar(seletor)
                : botao.closest("form");

            if (!formulario) {
                return;
            }

            formulario.reset();

            selecionarTodos(".is-invalid", formulario)
                .forEach((campo) => limparErro(campo));

            selecionarTodos(".field-error", formulario)
                .forEach((erro) => erro.remove());
        });
    });
}


/* ============================================================
   19. RELÓGIO E DATA
============================================================ */

function atualizarRelogios() {
    const agora = new Date();

    selecionarTodos("[data-clock]").forEach((elemento) => {
        elemento.textContent = agora.toLocaleTimeString("pt-BR", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit"
        });
    });

    selecionarTodos("[data-current-date]").forEach((elemento) => {
        elemento.textContent = agora.toLocaleDateString("pt-BR", {
            weekday: "long",
            day: "2-digit",
            month: "long",
            year: "numeric"
        });
    });
}

function inicializarRelogio() {
    atualizarRelogios();

    if (selecionar("[data-clock]")) {
        window.setInterval(atualizarRelogios, 1000);
    }
}


/* ============================================================
   20. ANIMAÇÕES DE ENTRADA
============================================================ */

function inicializarAnimacoes() {
    const elementos = selecionarTodos("[data-reveal]");

    if (!elementos.length) {
        return;
    }

    if (
        !("IntersectionObserver" in window) ||
        window.matchMedia("(prefers-reduced-motion: reduce)").matches
    ) {
        elementos.forEach((elemento) => {
            elemento.classList.add("is-visible");
        });

        return;
    }

    const observador = new IntersectionObserver(
        (entradas, observer) => {
            entradas.forEach((entrada) => {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add("is-visible");
                    observer.unobserve(entrada.target);
                }
            });
        },
        {
            threshold: 0.12
        }
    );

    elementos.forEach((elemento) => {
        observador.observe(elemento);
    });
}


/* ============================================================
   21. MENU LATERAL ADMINISTRATIVO
============================================================ */

function inicializarSidebar() {
    const botoes = selecionarTodos("[data-sidebar-toggle]");

    botoes.forEach((botao) => {
        botao.addEventListener("click", () => {
            const seletor = botao.getAttribute(
                "data-sidebar-toggle"
            );

            const sidebar = seletor
                ? selecionar(seletor)
                : selecionar(".sidebar");

            if (!sidebar) {
                return;
            }

            const aberta = sidebar.classList.toggle("is-open");

            botao.setAttribute(
                "aria-expanded",
                String(aberta)
            );

            document.body.classList.toggle(
                "sidebar-aberta",
                aberta
            );
        });
    });
}


/* ============================================================
   22. DESTAQUE DO LINK ATUAL
============================================================ */

function inicializarLinkAtivo() {
    const caminhoAtual = window.location.pathname
        .split("/")
        .pop();

    selecionarTodos(
        ".navbar a[href], .sidebar a[href], .nav-link[href]"
    ).forEach((link) => {
        const href = link.getAttribute("href");

        if (!href || href.startsWith("#") || href.startsWith("javascript:")) {
            return;
        }

        let caminhoLink = "";

        try {
            caminhoLink = new URL(href, window.location.href)
                .pathname
                .split("/")
                .pop();
        } catch {
            return;
        }

        if (caminhoLink && caminhoLink === caminhoAtual) {
            link.classList.add("active");
            link.setAttribute("aria-current", "page");
        }
    });
}


/* ============================================================
   23. CONTADOR DE CARACTERES
============================================================ */

/**
 * Exemplo:
 * <textarea maxlength="300" data-character-count="#contador"></textarea>
 * <small id="contador"></small>
 */
function inicializarContadorCaracteres() {
    selecionarTodos("[data-character-count]")
        .forEach((campo) => {
            const seletorContador = campo.getAttribute(
                "data-character-count"
            );

            const contador = seletorContador
                ? selecionar(seletorContador)
                : null;

            if (!contador) {
                return;
            }

            const atualizar = () => {
                const maximo = campo.maxLength > 0
                    ? campo.maxLength
                    : "∞";

                contador.textContent =
                    `${campo.value.length} / ${maximo}`;
            };

            campo.addEventListener("input", atualizar);
            atualizar();
        });
}


/* ============================================================
   24. PRÉ-VISUALIZAÇÃO DE IMAGEM
============================================================ */

/**
 * Exemplo:
 * <input type="file" accept="image/*" data-image-preview="#preview">
 * <img id="preview" alt="Prévia da imagem">
 */
function inicializarPreviewImagem() {
    selecionarTodos("[data-image-preview]").forEach((campo) => {
        campo.addEventListener("change", () => {
            const seletor = campo.getAttribute("data-image-preview");
            const imagem = seletor ? selecionar(seletor) : null;
            const arquivo = campo.files?.[0];

            if (!imagem || !arquivo) {
                return;
            }

            if (!arquivo.type.startsWith("image/")) {
                notificar(
                    "Selecione um arquivo de imagem válido.",
                    "aviso"
                );

                campo.value = "";
                return;
            }

            const tamanhoMaximo = 5 * 1024 * 1024;

            if (arquivo.size > tamanhoMaximo) {
                notificar(
                    "A imagem deve ter no máximo 5 MB.",
                    "aviso"
                );

                campo.value = "";
                return;
            }

            const leitor = new FileReader();

            leitor.onload = () => {
                if (typeof leitor.result === "string") {
                    imagem.src = leitor.result;
                    imagem.hidden = false;
                }
            };

            leitor.readAsDataURL(arquivo);
        });
    });
}


/* ============================================================
   25. CONTROLE DE EXIBIÇÃO DE SENHA FORTE
============================================================ */

function avaliarForcaSenha(senha) {
    let pontuacao = 0;

    if (senha.length >= 8) pontuacao++;
    if (/[a-z]/.test(senha)) pontuacao++;
    if (/[A-Z]/.test(senha)) pontuacao++;
    if (/[0-9]/.test(senha)) pontuacao++;
    if (/[^a-zA-Z0-9]/.test(senha)) pontuacao++;

    if (senha.length === 0) {
        return {
            pontuacao: 0,
            texto: "Digite uma senha",
            classe: ""
        };
    }

    if (pontuacao <= 2) {
        return {
            pontuacao,
            texto: "Senha fraca",
            classe: "fraca"
        };
    }

    if (pontuacao <= 4) {
        return {
            pontuacao,
            texto: "Senha média",
            classe: "media"
        };
    }

    return {
        pontuacao,
        texto: "Senha forte",
        classe: "forte"
    };
}

function inicializarForcaSenha() {
    selecionarTodos("[data-password-strength]").forEach((campo) => {
        const seletor = campo.getAttribute(
            "data-password-strength"
        );

        const indicador = seletor ? selecionar(seletor) : null;

        if (!indicador) {
            return;
        }

        campo.addEventListener("input", () => {
            const resultado = avaliarForcaSenha(campo.value);

            indicador.textContent = resultado.texto;
            indicador.className =
                `password-strength password-strength--${resultado.classe}`;

            indicador.setAttribute(
                "aria-live",
                "polite"
            );
        });
    });
}


/* ============================================================
   26. CONTROLE DE DISPONIBILIDADE VISUAL
============================================================ */

/**
 * Atualiza apenas a aparência de um seletor de status.
 * A disponibilidade real deve vir do banco de dados.
 */
function inicializarStatusVisual() {
    selecionarTodos("[data-status-select]").forEach((campo) => {
        const atualizar = () => {
            campo.classList.remove(
                "status-disponivel",
                "status-ocupado",
                "status-manutencao",
                "status-pendente",
                "status-cancelado"
            );

            const valor = campo.value
                .toLowerCase()
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .replace(/\s+/g, "-");

            const classesPermitidas = [
                "disponivel",
                "ocupado",
                "manutencao",
                "pendente",
                "cancelado"
            ];

            if (classesPermitidas.includes(valor)) {
                campo.classList.add(`status-${valor}`);
            }
        };

        campo.addEventListener("change", atualizar);
        atualizar();
    });
}


/* ============================================================
   27. BOTÕES DE COPIAR
============================================================ */

/**
 * Exemplo:
 * <button type="button" data-copy="#codigo-reserva">Copiar código</button>
 */
function inicializarCopiarTexto() {
    selecionarTodos("[data-copy]").forEach((botao) => {
        botao.addEventListener("click", async () => {
            const seletor = botao.getAttribute("data-copy");
            const alvo = seletor ? selecionar(seletor) : null;

            if (!alvo) {
                return;
            }

            const texto = "value" in alvo
                ? alvo.value
                : alvo.textContent;

            try {
                await navigator.clipboard.writeText(texto || "");
                notificar("Texto copiado com sucesso.", "sucesso");
            } catch {
                notificar(
                    "Não foi possível copiar automaticamente.",
                    "aviso"
                );
            }
        });
    });
}


/* ============================================================
   28. BOTÃO VOLTAR AO TOPO
============================================================ */

function inicializarVoltarTopo() {
    const botao = selecionar("[data-back-to-top]");

    if (!botao) {
        return;
    }

    const atualizar = () => {
        botao.hidden = window.scrollY < 350;
    };

    window.addEventListener("scroll", atualizar, {
        passive: true
    });

    botao.addEventListener("click", () => {
        window.scrollTo({
            top: 0,
            behavior: window.matchMedia(
                "(prefers-reduced-motion: reduce)"
            ).matches
                ? "auto"
                : "smooth"
        });
    });

    atualizar();
}


/* ============================================================
   29. ATUALIZAÇÃO DE ANO NO RODAPÉ
============================================================ */

function inicializarAnoRodape() {
    selecionarTodos("[data-current-year]").forEach((elemento) => {
        elemento.textContent = String(new Date().getFullYear());
    });
}


/* ============================================================
   30. DESTAQUE DE CAMPOS OBRIGATÓRIOS
============================================================ */

function inicializarCamposObrigatorios() {
    selecionarTodos("input[required], select[required], textarea[required]")
        .forEach((campo) => {
            const rotulo = campo.labels?.[0];

            if (
                rotulo &&
                !rotulo.querySelector(".required-mark") &&
                !rotulo.textContent.includes("*")
            ) {
                const marcador = document.createElement("span");

                marcador.className = "required-mark";
                marcador.textContent = " *";
                marcador.setAttribute("aria-hidden", "true");

                rotulo.appendChild(marcador);
            }
        });
}


/* ============================================================
   31. PREVENÇÃO DE CLIQUE DUPLO EM FORMULÁRIOS
============================================================ */

/**
 * Evita múltiplos envios acidentais.
 * O servidor ainda deve impedir operações duplicadas.
 */
function inicializarProtecaoEnvioDuplo() {
    selecionarTodos("form[data-prevent-double-submit]")
        .forEach((formulario) => {
            formulario.addEventListener("submit", (evento) => {
                if (formulario.dataset.enviando === "true") {
                    evento.preventDefault();
                    return;
                }

                if (!formulario.checkValidity()) {
                    return;
                }

                formulario.dataset.enviando = "true";

                const botoes = selecionarTodos(
                    "button[type='submit'], input[type='submit']",
                    formulario
                );

                botoes.forEach((botao) => {
                    botao.disabled = true;

                    if (botao.tagName === "BUTTON") {
                        botao.dataset.textoOriginal =
                            botao.textContent;

                        botao.textContent = "Processando...";
                    }
                });

                /*
                 * Em caso de erro de rede, o navegador pode não
                 * receber resposta. Reative o botão no tratamento
                 * de erro da sua integração com PHP/fetch.
                 */
            });
        });
}


/* ============================================================
   32. SELEÇÃO DE TODOS OS CHECKBOXES
============================================================ */

/**
 * Exemplo:
 * <input type="checkbox" data-select-all="itens">
 * <input type="checkbox" data-select-item="itens">
 */
function inicializarSelecionarTodos() {
    selecionarTodos("[data-select-all]").forEach((principal) => {
        principal.addEventListener("change", () => {
            const grupo = principal.getAttribute("data-select-all");

            if (!grupo) {
                return;
            }

            selecionarTodos(
                `[data-select-item="${grupo}"]`
            ).forEach((item) => {
                item.checked = principal.checked;
            });
        });
    });
}


/* ============================================================
   33. FILTRO DE DATAS
============================================================ */

function inicializarFiltroDatas() {
    selecionarTodos("[data-date-filter]").forEach((campo) => {
        campo.addEventListener("change", () => {
            const seletor = campo.getAttribute("data-date-filter");
            const alvo = seletor ? selecionar(seletor) : null;

            if (!alvo) {
                return;
            }

            const dataEscolhida = campo.value;

            selecionarTodos("[data-record-date]", alvo)
                .forEach((registro) => {
                    const dataRegistro = registro.getAttribute(
                        "data-record-date"
                    );

                    registro.hidden = Boolean(
                        dataEscolhida &&
                        dataRegistro !== dataEscolhida
                    );
                });
        });
    });
}


/* ============================================================
   34. ACESSIBILIDADE: FOCO VISÍVEL
============================================================ */

function inicializarAcessibilidade() {
    document.addEventListener("keydown", (evento) => {
        if (evento.key === "Tab") {
            document.body.classList.add("teclado-navegacao");
        }
    });

    document.addEventListener("pointerdown", () => {
        document.body.classList.remove("teclado-navegacao");
    });

    selecionarTodos("a[href='#']").forEach((link) => {
        link.addEventListener("click", (evento) => {
            evento.preventDefault();
        });
    });
}


/* ============================================================
   35. BOTÃO DE TEMA
============================================================ */

/**
 * Opcional: adiciona/remove uma classe para tema claro.
 * Não armazena preferências no navegador.
 */
function inicializarTema() {
    selecionarTodos("[data-theme-toggle]").forEach((botao) => {
        botao.addEventListener("click", () => {
            const ativo = document.body.classList.toggle(
                "tema-claro"
            );

            botao.setAttribute(
                "aria-pressed",
                String(ativo)
            );
        });
    });
}

async function solicitarAPI(url, opcoes = {}) {
    const resposta = await fetch(url, {
        ...opcoes,
        credentials: "same-origin",
        headers: {
            "Content-Type": "application/json",
            ...(opcoes.headers || {})
        }
    });
    const dados = await resposta.json();

    if (!resposta.ok || dados.sucesso === false) {
        throw new Error(dados.mensagem || "Não foi possível concluir a solicitação.");
    }

    return dados;
}

function mostrarMensagem(elemento, texto, sucesso = false) {
    if (!elemento) {
        return;
    }

    elemento.textContent = texto;
    elemento.hidden = false;
    elemento.classList.toggle("is-success", sucesso);
    elemento.classList.toggle("is-error", !sucesso);
}

function criarLinhaTabela(colunas) {
    return `<tr>${colunas.map((coluna) => `<td>${coluna}</td>`).join("")}</tr>`;
}

function desenharTabela(tbody, linhas, colunas, vazio = "Nenhum registro encontrado.") {
    if (!tbody) {
        return;
    }

    tbody.innerHTML = linhas.length
        ? linhas.join("")
        : `<tr><td colspan="${colunas}" class="empty-state">${escaparHTML(vazio)}</td></tr>`;
}

async function carregarTabela(nome) {
    const resultado = await solicitarAPI(`../api/api.php?tabela=${encodeURIComponent(nome)}`);
    return resultado.dados;
}

async function inicializarIntegracaoAPI() {
    const nascimento = selecionar("#data_nascimento");
    if (nascimento) {
        const limiteIdade = new Date();
        limiteIdade.setFullYear(limiteIdade.getFullYear() - 18);
        nascimento.max = [
            limiteIdade.getFullYear(),
            String(limiteIdade.getMonth() + 1).padStart(2, "0"),
            String(limiteIdade.getDate()).padStart(2, "0")
        ].join("-");
        nascimento.min = "1900-01-01";
    }

    const capacidadeQuarto = selecionar("#capacidade");
    const maximoPessoasQuarto = selecionar("#maximo_pessoas");
    const duracaoQuarto = selecionar("#duracao");
    if (capacidadeQuarto && maximoPessoasQuarto) {
        const validarCapacidadeQuarto = () => {
            const capacidade = Number(capacidadeQuarto.value);
            maximoPessoasQuarto.min = String(capacidade || 1);
            if (Number(maximoPessoasQuarto.value) < capacidade) {
                maximoPessoasQuarto.value = String(capacidade);
            }
            maximoPessoasQuarto.setCustomValidity(
                capacidade > 0 && Number(maximoPessoasQuarto.value) < capacidade
                    ? "O limite máximo não pode ser menor que as pessoas incluídas."
                    : ""
            );
        };
        capacidadeQuarto.addEventListener("input", validarCapacidadeQuarto);
        maximoPessoasQuarto.addEventListener("input", validarCapacidadeQuarto);
        validarCapacidadeQuarto();
    }

    if (duracaoQuarto) {
        duracaoQuarto.addEventListener("input", () => {
            duracaoQuarto.setCustomValidity(
                duracaoQuarto.value && duracaoQuarto.value !== "00:00"
                    ? ""
                    : "Informe uma duração maior que zero no formato HH:MM."
            );
        });
    }

    const formulariosAuth = [
        { seletor: "#loginForm", acao: "login", mensagem: "#loginMessage" },
        { seletor: "#registerForm", acao: "cadastro", mensagem: "#registerMessage" }
    ];

    formulariosAuth.forEach(({ seletor, acao, mensagem }) => {
        const formulario = selecionar(seletor);
        if (!formulario) return;

        formulario.addEventListener("submit", async (evento) => {
            evento.preventDefault();
            const campos = Object.fromEntries(new FormData(formulario).entries());
            const alvoMensagem = selecionar(mensagem);

            if (acao === "cadastro" && campos.senha !== campos.confirmar_senha) {
                mostrarMensagem(alvoMensagem, "As senhas informadas não coincidem.");
                return;
            }

            try {
                const resposta = await solicitarAPI(
                    `api/auth.php?acao=${acao}`,
                    { method: "POST", body: JSON.stringify(campos) }
                );
                mostrarMensagem(alvoMensagem, resposta.mensagem, true);

                if (resposta.redirecionar) {
                    window.location.assign(resposta.redirecionar);
                } else if (acao === "cadastro") {
                    window.setTimeout(() => window.location.assign("index.html"), 1200);
                }
            } catch (erro) {
                mostrarMensagem(alvoMensagem, erro.message);
            }
        });
    });

    selecionarTodos("form.data-form").forEach((formulario) => {
        formulario.dataset.createAction = formulario.action;
        const botaoEnviar = formulario.querySelector("button[type='submit']");
        const textoBotaoOriginal = botaoEnviar?.textContent.trim() || "";

        formulario.addEventListener("reset", () => {
            if (formulario.dataset.preserveEditMode === "true") {
                delete formulario.dataset.preserveEditMode;
                window.setTimeout(() => {
                    if (capacidadeQuarto && maximoPessoasQuarto) {
                        const capacidade = Number(capacidadeQuarto.value);
                        maximoPessoasQuarto.min = String(capacidade || 1);
                        maximoPessoasQuarto.setCustomValidity(
                            capacidade > 0 && Number(maximoPessoasQuarto.value) < capacidade
                                ? "O limite máximo não pode ser menor que as pessoas incluídas."
                                : ""
                        );
                    }
                    if (duracaoQuarto) duracaoQuarto.setCustomValidity("");
                }, 0);
                return;
            }

            window.setTimeout(() => {
                delete formulario.dataset.recordId;
                delete formulario.dataset.requestMethod;
                formulario.action = formulario.dataset.createAction;
                if (botaoEnviar) botaoEnviar.textContent = textoBotaoOriginal;
                if (capacidadeQuarto && maximoPessoasQuarto) {
                    maximoPessoasQuarto.setCustomValidity("");
                    maximoPessoasQuarto.min = "1";
                }
                if (duracaoQuarto) duracaoQuarto.setCustomValidity("");
            }, 0);
        });

        formulario.addEventListener("submit", async (evento) => {
            evento.preventDefault();
            const mensagem = selecionar(".form-message", formulario);
            const campos = Object.fromEntries(new FormData(formulario).entries());

            try {
                if (formulario.id === "roomForm") {
                    const duracao = String(campos.duracao_periodo_minutos || "");
                    const partesDuracao = duracao.split(":").map(Number);
                    if (partesDuracao.length !== 2 || partesDuracao.some((parte) => !Number.isInteger(parte))) {
                        throw new Error("Informe a duração do período-base no formato HH:MM.");
                    }
                    campos.duracao_periodo_minutos = partesDuracao[0] * 60 + partesDuracao[1];
                    if (campos.duracao_periodo_minutos < 1 || campos.duracao_periodo_minutos > 1439) {
                        throw new Error("A duração do período-base deve ser entre 00:01 e 23:59.");
                    }
                    if (Number(campos.maximo_pessoas) < Number(campos.capacidade_pessoas)) {
                        throw new Error("O limite máximo de pessoas não pode ser menor que a quantidade incluída no preço.");
                    }
                }
                const resposta = await solicitarAPI(formulario.action, {
                    method: formulario.dataset.requestMethod || "POST",
                    body: JSON.stringify(campos)
                });
                mostrarMensagem(mensagem, resposta.mensagem, true);
                formulario.reset();
                await carregarDadosPainel();
            } catch (erro) {
                mostrarMensagem(mensagem, erro.message);
            }
        });
    });

    selecionarTodos("[data-logout]").forEach((link) => {
        link.addEventListener("click", async (evento) => {
            evento.preventDefault();
            try {
                await solicitarAPI("../api/logout.php", { method: "POST", body: "{}" });
                window.location.assign("../index.html");
            } catch (erro) {
                notificar(erro.message, "aviso");
            }
        });
    });

    await carregarDadosPainel();
}

async function carregarDadosPainel() {
    const corpoQuartos = selecionar("#roomsTable");
    const corpoProdutos = selecionar("#productsTable");
    const corpoEstadias = selecionar("#staysTable");
    const corpoPagamentos = selecionar("#paymentsTable");
    const raizCliente = document.body.classList.contains("client-page");
    const raizAdmin = document.body.classList.contains("admin-page");
    const temPainel = raizCliente || raizAdmin;

    if (!temPainel) return;

    try {
        const sessao = await solicitarAPI("../api/auth.php?acao=sessao");
        if (!sessao.autenticado) {
            window.location.replace("../index.html");
            return;
        }

        if (raizAdmin && sessao.usuario.tipo !== "admin") {
            window.location.replace("../cliente/dashboard.html");
            return;
        }
        if (raizCliente && sessao.usuario.tipo !== "cliente") {
            window.location.replace("../admin/dashboard.html");
            return;
        }

        const nomeUsuario = selecionar("[data-client-name]");
        const inicialUsuario = selecionar("[data-client-initial]");
        if (nomeUsuario) nomeUsuario.textContent = sessao.usuario.nome;
        if (inicialUsuario) inicialUsuario.textContent = sessao.usuario.nome.charAt(0).toUpperCase();

        const [quartos, produtos, estadias, pagamentos, consumos] = await Promise.all([
            carregarTabela("quartos"),
            carregarTabela("frigobar"),
            carregarTabela("estadias"),
            carregarTabela("pagamentos"),
            carregarTabela("consumo")
        ]);
        const [usuarios, clientes] = raizAdmin
            ? await Promise.all([carregarTabela("usuarios"), carregarTabela("clientes")])
            : [[], []];

        Sahara.dadosPainel = {
            quartos,
            produtos,
            estadias,
            pagamentos,
            consumos,
            usuarios,
            clientes
        };

        atualizarFormularioPagamento();
        desenharListaQuartos(quartos, raizAdmin);
        desenharListaProdutos(produtos, raizAdmin, estadias);
        desenharListaEstadias(estadias, usuarios, clientes, quartos);
        desenharListaPagamentos(pagamentos, estadias);
        desenharListaConsumos(consumos, produtos);
        preencherIndicadores(quartos, estadias, pagamentos, consumos);
    } catch (erro) {
        const mensagem = selecionar("#consumptionMessage");
        if (mensagem) mostrarMensagem(mensagem, erro.message);
        else notificar(erro.message, "aviso");
    }
}

function desenharListaQuartos(quartos, admin) {
    const adminTable = selecionar("#roomsTable");
    const grid = selecionar("#clientRoomsGrid");
    const filtroBusca = selecionar("#roomSearch, #roomQuery");
    const filtroStatus = selecionar("#roomStatusFilter");
    const filtroCategoria = selecionar("#roomCategory");
    const buscar = () => {
        const busca = filtroBusca?.value.trim().toLowerCase() || "";
        const categoria = filtroCategoria?.value || "";
        const status = filtroStatus?.value || "";
        const lista = (Sahara.dadosPainel.quartos || quartos).filter((q) =>
            (!busca || `${q.numero} ${q.nome}`.toLowerCase().includes(busca)) &&
            (!categoria || q.categoria === categoria) &&
            (!status || q.status === status)
        );

        desenharTabela(adminTable, lista.map((q) => criarLinhaTabela([
            escaparHTML(q.numero),
            escaparHTML(q.nome),
            escaparHTML(q.categoria),
            formatarMoeda(q.preco_periodo),
            escaparHTML(q.status),
            admin
                ? `<button type="button" class="btn btn-secondary" data-edit-room="${Number(q.id)}">Editar</button>`
                : "—"
        ])), 6, "Nenhum quarto cadastrado.");

        if (grid) {
            grid.innerHTML = lista.length
                ? lista.map((q) => {
                    const imagem = String(q.imagem_url || "").trim();
                    const status = {
                        disponivel: { texto: "Disponível", classe: "badge-success" },
                        ocupado: { texto: "Ocupado", classe: "badge-info" },
                        manutencao: { texto: "Em manutenção", classe: "badge-warning" },
                        inativo: { texto: "Inativo", classe: "badge-danger" }
                    }[q.status] || { texto: q.status, classe: "" };

                    return `
                        <button type="button" class="room-card room-card-action" data-book-room="${Number(q.id)}" aria-label="Ver detalhes e agendar ${escaparHTML(q.nome)}">
                            <div class="room-card-image">
                                ${imagem
                                    ? `<img src="${escaparHTML(imagem)}" alt="${escaparHTML(q.nome)}" loading="lazy" decoding="async">`
                                    : '<span class="room-card-placeholder" aria-hidden="true">✦</span>'}
                                <span class="badge ${status.classe}">${escaparHTML(status.texto)}</span>
                                <span class="room-card-action-hint">Ver detalhes e agendar <span aria-hidden="true">→</span></span>
                            </div>
                            <div class="room-card-body">
                                <span class="room-card-category">${escaparHTML(q.categoria)}</span>
                                <h3>${escaparHTML(q.nome)}</h3>
                                ${q.descricao
                                    ? `<p class="room-card-description">${escaparHTML(q.descricao)}</p>`
                                    : ""}
                                <div class="room-card-meta">
                                    <span>Quarto ${escaparHTML(q.numero)}</span>
                                    <span>${Number(q.capacidade_pessoas)} pessoas</span>
                                    <span>${Number(q.duracao_periodo_minutos)} min por período</span>
                                </div>
                                <div class="room-card-price">
                                    <div>
                                        <span>VALOR DO PERÍODO</span>
                                        <strong>${formatarMoeda(q.preco_periodo)}</strong>
                                    </div>
                                </div>
                            </div>
                        </button>
                    `;
                }).join("")
                : '<div class="empty-state-card"><h3>Nenhuma acomodação disponível</h3></div>';

            if (grid.dataset.bookingHandler !== "true") {
                grid.dataset.bookingHandler = "true";
                const abrirAgendamento = (evento) => {
                    const cartao = evento.target.closest("[data-book-room]");
                    if (!cartao) return;

                    const quarto = (Sahara.dadosPainel.quartos || quartos).find(
                        (item) => Number(item.id) === Number(cartao.dataset.bookRoom)
                    );
                    if (!quarto) return;

                    const dialogo = selecionar("#roomBookingDialog");
                    const formulario = selecionar("#roomBookingForm");
                    const detalhes = selecionar("#roomBookingDetails");
                    const dataInput = selecionar("#bookingDate");
                    const horarioInput = selecionar("#bookingTime");
                    const duracaoInput = selecionar("#bookingDuration");
                    const pessoasInput = selecionar("#bookingPeople");
                    const pessoasHint = selecionar("#bookingPeopleHint");
                    const entradaHidden = formulario?.elements.namedItem("entrada_prevista");
                    const saidaHidden = formulario?.elements.namedItem("saida_prevista");
                    const saidaTexto = selecionar("#bookingDeparture");
                    const resumoPreco = selecionar("#roomBookingQuote");
                    const mensagem = selecionar("#roomBookingMessage");
                    if (
                        !dialogo || !formulario || !detalhes || !dataInput ||
                        !horarioInput || !duracaoInput ||
                        !pessoasInput || !entradaHidden || !saidaHidden ||
                        !saidaTexto || !resumoPreco
                    ) return;

                    formulario.reset();
                    formulario.elements.quarto_id.value = quarto.id;
                    const incluidos = Number(quarto.capacidade_pessoas);
                    const maximo = Math.max(incluidos, Number(quarto.maximo_pessoas || incluidos));
                    const periodo = Number(quarto.duracao_periodo_minutos);
                    pessoasInput.min = "1";
                    pessoasInput.max = String(maximo);
                    pessoasInput.value = String(Math.min(2, incluidos));
                    pessoasInput.setCustomValidity("");
                    if (pessoasHint) {
                        pessoasHint.textContent = maximo > incluidos
                            ? `${incluidos} pessoas incluídas; até ${maximo} permitidas. Cada pessoa excedente custa ${formatarMoeda(quarto.preco_pessoa_extra)}.`
                            : `${incluidos} pessoas incluídas. Esta suíte está configurada para no máximo ${maximo}; para permitir excedentes, a equipe deve aumentar o limite.`;
                    }
                    duracaoInput.value = `${String(Math.floor(periodo / 60)).padStart(2, "0")}:${String(periodo % 60).padStart(2, "0")}`;
                    duracaoInput.min = "00:01";
                    duracaoInput.max = "23:59";
                    const agora = new Date();
                    agora.setSeconds(0, 0);
                    agora.setMinutes(Math.ceil((agora.getMinutes() + 1) / 30) * 30);
                    dataInput.min = [
                        agora.getFullYear(),
                        String(agora.getMonth() + 1).padStart(2, "0"),
                        String(agora.getDate()).padStart(2, "0")
                    ].join("-");
                    detalhes.innerHTML = `
                        <span class="room-card-category">${escaparHTML(quarto.categoria)}</span>
                        <h3>${escaparHTML(quarto.nome)}</h3>
                        <p>${escaparHTML(quarto.descricao || "Conheça esta acomodação do Motel Sahara.")}</p>
                        <div class="room-card-meta">
                            <span>Quarto ${escaparHTML(quarto.numero)}</span>
                            <span>${incluidos} pessoas incluídas</span>
                            <span>Limite de ${maximo} pessoas</span>
                        </div>
                        <p class="room-booking-rate">Período-base: ${Number(periodo)} min · ${formatarMoeda(quarto.preco_periodo)}. ${maximo > incluidos
                            ? `Acima de ${incluidos} pessoas, cobra-se ${formatarMoeda(quarto.preco_pessoa_extra)} por pessoa excedente, até ${maximo} pessoas.`
                            : `O limite cadastrado é ${maximo} pessoas. A equipe pode aumentar esse limite para permitir pessoas excedentes.`}</p>
                    `;

                    const paraCampo = (data) => [
                        data.getFullYear(),
                        String(data.getMonth() + 1).padStart(2, "0"),
                        String(data.getDate()).padStart(2, "0")
                    ].join("-") + "T" + [
                        String(data.getHours()).padStart(2, "0"),
                        String(data.getMinutes()).padStart(2, "0")
                    ].join(":");
                    dataInput.value = paraCampo(agora).slice(0, 10);
                    horarioInput.value = paraCampo(agora).slice(11);

                    const atualizarResumo = () => {
                        const partesDuracao = duracaoInput.value.split(":").map(Number);
                        const duracaoMinutos = partesDuracao.length === 2
                            ? partesDuracao[0] * 60 + partesDuracao[1]
                            : 0;
                        const quantidadePessoas = Number(pessoasInput.value);
                        const inicio = new Date(`${dataInput.value}T${horarioInput.value}`);
                        const erroPessoas = quantidadePessoas > maximo
                            ? `Esta suíte permite até ${maximo} pessoas no total. Peça à equipe para aumentar o limite cadastrado.`
                            : "";
                        pessoasInput.setCustomValidity(erroPessoas);
                        const valido = Number.isFinite(inicio.getTime()) &&
                            duracaoMinutos > 0 &&
                            duracaoMinutos <= 1439 &&
                            quantidadePessoas >= 1 &&
                            quantidadePessoas <= maximo;
                        if (!valido) {
                            entradaHidden.value = "";
                            saidaHidden.value = "";
                            saidaTexto.textContent = erroPessoas || "Confira a data, o horário, a duração e a quantidade de pessoas.";
                            resumoPreco.innerHTML = "";
                            return;
                        }

                        const fim = new Date(inicio.getTime() + duracaoMinutos * 60000);
                        entradaHidden.value = paraCampo(inicio);
                        saidaHidden.value = paraCampo(fim);
                        saidaTexto.textContent = fim.toLocaleString("pt-BR", {
                            dateStyle: "short",
                            timeStyle: "short"
                        });

                        const precoPeriodo = Number(quarto.preco_periodo);
                        const precoTempo = Math.round(
                            precoPeriodo * duracaoMinutos / periodo * 100
                        ) / 100;
                        const quantidadeExtra = Math.max(0, quantidadePessoas - incluidos);
                        const valorPessoasExtras = Math.round(
                            quantidadeExtra * Number(quarto.preco_pessoa_extra) * 100
                        ) / 100;
                        const subtotal = Math.round((precoTempo + valorPessoasExtras) * 100) / 100;
                        const valorTaxa = Math.round(
                            subtotal * Number(quarto.percentual_taxa_servico || 0) / 100 * 100
                        ) / 100;
                        const total = Math.round((subtotal + valorTaxa) * 100) / 100;
                        resumoPreco.innerHTML = `
                            <div class="room-booking-summary-row"><span>Preço pelo tempo escolhido (${String(partesDuracao[0]).padStart(2, "0")}:${String(partesDuracao[1]).padStart(2, "0")})</span><strong>${formatarMoeda(precoTempo)}</strong></div>
                            ${quantidadeExtra
                                ? `<div class="room-booking-summary-row"><span>${quantidadeExtra} pessoa(s) excedente(s)</span><strong>${formatarMoeda(valorPessoasExtras)}</strong></div>`
                                : ""}
                            ${valorTaxa
                                ? `<div class="room-booking-summary-row"><span>Taxa de serviço (${Number(quarto.percentual_taxa_servico)}%)</span><strong>${formatarMoeda(valorTaxa)}</strong></div>`
                                : ""}
                            <div class="room-booking-summary-row room-booking-total"><span>Total estimado</span><strong>${formatarMoeda(total)}</strong></div>
                        `;
                    };
                    [dataInput, horarioInput, duracaoInput, pessoasInput].forEach((input) => {
                        input.oninput = atualizarResumo;
                        input.onchange = atualizarResumo;
                    });
                    atualizarResumo();
                    if (mensagem) {
                        mensagem.hidden = true;
                        mensagem.textContent = "";
                    }
                    if (!dialogo.open) dialogo.showModal();
                };

                grid.addEventListener("click", abrirAgendamento);
                const dialogo = selecionar("#roomBookingDialog");
                const formulario = selecionar("#roomBookingForm");
                selecionar("[data-close-room-dialog]")?.addEventListener("click", () => dialogo?.close());
                formulario?.addEventListener("submit", async (evento) => {
                    evento.preventDefault();
                    const mensagem = selecionar("#roomBookingMessage");
                    const botao = formulario.querySelector("button[type='submit']");
                    const campos = Object.fromEntries(new FormData(formulario).entries());

                    if (!campos.entrada_prevista || !campos.saida_prevista) {
                        mostrarMensagem(mensagem, "Confira a data, a duração e a quantidade de pessoas para ver o preço.");
                        return;
                    }

                    if (botao) botao.disabled = true;
                    try {
                        const resposta = await solicitarAPI(
                            "../api/api.php?tabela=estadias&acao=criar",
                            { method: "POST", body: JSON.stringify(campos) }
                        );
                        mostrarMensagem(
                            mensagem,
                            `${resposta.mensagem} Total previsto: ${formatarMoeda(resposta.valor_total_previsto)}. Código do pedido: ${resposta.codigo}.`,
                            true
                        );
                        await carregarDadosPainel();
                    } catch (erro) {
                        mostrarMensagem(mensagem, erro.message);
                    } finally {
                        if (botao) botao.disabled = false;
                    }
                });
            }
        }
    };

    [filtroBusca, filtroStatus, filtroCategoria].filter(Boolean).forEach((el) =>
        {
            if (el.dataset.filterHandler === "true") return;
            el.dataset.filterHandler = "true";
            el.addEventListener("input", buscar);
            el.addEventListener("change", buscar);
        }
    );
    const filtroForm = selecionar("#clientRoomFilter");
    if (filtroForm && filtroForm.dataset.filterHandler !== "true") {
        filtroForm.dataset.filterHandler = "true";
        filtroForm.addEventListener("submit", (evento) => evento.preventDefault());
    }

    if (adminTable && adminTable.dataset.editHandler !== "true") {
        adminTable.dataset.editHandler = "true";
        adminTable.addEventListener("click", (evento) => {
            const botao = evento.target.closest("[data-edit-room]");
            if (!botao) return;
            iniciarEdicaoFormulario(
                "#roomForm",
                "../api/api.php?tabela=quartos",
                botao.dataset.editRoom,
                (Sahara.dadosPainel.quartos || quartos).find((q) => Number(q.id) === Number(botao.dataset.editRoom)),
                "Atualizar acomodação"
            );
        });
    }
    buscar();
}

function desenharListaProdutos(produtos, admin, estadias = []) {
    const adminTable = selecionar("#productsTable");
    const grid = selecionar("#clientProductsGrid");
    const busca = selecionar("#productSearch");
    const categoria = selecionar("#productCategoryFilter, #productCategory");
    const estadiasAtuais = Sahara.dadosPainel.estadias || estadias;
    const estadiaAtiva = estadiasAtuais
        .find((estadia) => estadia.status === "em_andamento");
    const estadiaReservada = estadiasAtuais
        .filter((estadia) => estadia.status === "reservada")
        .sort((a, b) => String(b.entrada_prevista).localeCompare(String(a.entrada_prevista)))[0];
    const avisoEstadia = selecionar("#activeStayNotice");

    if (avisoEstadia) {
        if (estadiaAtiva) {
            mostrarMensagem(avisoEstadia, `Estadia ${estadiaAtiva.codigo} em andamento. O consumo pode ser solicitado e será lançado nela.`, true);
        } else if (estadiaReservada) {
            mostrarMensagem(avisoEstadia, `Estadia ${estadiaReservada.codigo} reservada. A equipe precisa confirmar o check-in e clicar em “Iniciar” na área de estadias para liberar o consumo.`);
        } else {
            mostrarMensagem(avisoEstadia, "Não há estadia em andamento vinculada à sua conta. Confirme com a equipe se o check-in já foi iniciado.");
        }
    }

    if (grid && grid.dataset.orderHandler !== "true") {
        grid.dataset.orderHandler = "true";
        grid.addEventListener("submit", async (evento) => {
            const formulario = evento.target.closest(".consumption-order");
            if (!formulario) return;

            evento.preventDefault();
            const quantidade = Number(new FormData(formulario).get("quantidade"));
            const produtoId = Number(new FormData(formulario).get("frigobar_id"));
            const botao = formulario.querySelector("button[type='submit']");
            const mensagem = selecionar("#consumptionMessage");

            if (botao) botao.disabled = true;
            try {
                const resposta = await solicitarAPI(
                    "../api/api.php?tabela=consumo&acao=registrar_consumo",
                    {
                        method: "POST",
                        body: JSON.stringify({
                            frigobar_id: produtoId,
                            quantidade
                        })
                    }
                );
                mostrarMensagem(mensagem, `${resposta.mensagem} Total: ${formatarMoeda(resposta.total)}.`, true);
                await carregarDadosPainel();
            } catch (erro) {
                mostrarMensagem(mensagem, erro.message);
                if (botao) botao.disabled = false;
            }
        });
    }

    const atualizar = () => {
        const termo = busca?.value.trim().toLowerCase() || "";
        const filtro = categoria?.value || "";
        const estadiaAtual = (Sahara.dadosPainel.estadias || estadias)
            .find((estadia) => estadia.status === "em_andamento");
        const lista = (Sahara.dadosPainel.produtos || produtos).filter((p) =>
            (admin || Number(p.ativo) === 1) &&
            (!termo || p.nome.toLowerCase().includes(termo)) &&
            (!filtro || p.categoria === filtro)
        );

        desenharTabela(adminTable, lista.map((p) => criarLinhaTabela([
            escaparHTML(p.nome),
            escaparHTML(p.categoria),
            formatarMoeda(p.preco),
            String(p.estoque),
            Number(p.ativo) === 1 ? "Ativo" : "Inativo",
            admin
                ? `<button type="button" class="btn btn-secondary" data-edit-product="${Number(p.id)}">Editar</button>`
                : "—"
        ])), 6, "Nenhum produto cadastrado.");

        if (grid) {
            grid.innerHTML = lista.length
                ? lista.map((p) => {
                    const indisponivel = Number(p.estoque) < 1 || !estadiaAtual;
                    return `<article class="product-card">
                        <div class="product-card-body">
                            <span class="product-card-category">${escaparHTML(p.categoria)}</span>
                            <h3>${escaparHTML(p.nome)}</h3>
                            <p class="product-card-description">${escaparHTML(p.descricao || "Disponível para consumo durante sua estadia.")}</p>
                            <div class="product-card-footer">
                                <strong class="product-card-price">${formatarMoeda(p.preco)}</strong>
                                <form class="consumption-order">
                                    <input type="hidden" name="frigobar_id" value="${Number(p.id)}">
                                    <label class="sr-only" for="quantity-${Number(p.id)}">Quantidade de ${escaparHTML(p.nome)}</label>
                                    <input class="form-control consumption-quantity" id="quantity-${Number(p.id)}" name="quantidade" type="number" min="1" max="${Math.min(50, Math.max(1, Number(p.estoque)))}" value="1" ${indisponivel ? "disabled" : ""} required>
                                    <button class="btn btn-primary" type="submit" ${indisponivel ? "disabled" : ""}>${Number(p.estoque) < 1 ? "Sem estoque" : "Solicitar"}</button>
                                </form>
                            </div>
                            <p class="field-hint">Estoque disponível: ${Number(p.estoque)}</p>
                        </div>
                    </article>`;
                }).join("")
                : '<div class="empty-state-card"><h3>Nenhum produto disponível</h3></div>';
        }
    };

    if (busca && busca.dataset.filterHandler !== "true") {
        busca.dataset.filterHandler = "true";
        busca.addEventListener("input", atualizar);
    }
    if (categoria && categoria.dataset.filterHandler !== "true") {
        categoria.dataset.filterHandler = "true";
        categoria.addEventListener("change", atualizar);
    }
    const filtroForm = selecionar("#clientProductFilter");
    if (filtroForm && filtroForm.dataset.filterHandler !== "true") {
        filtroForm.dataset.filterHandler = "true";
        filtroForm.addEventListener("submit", (evento) => evento.preventDefault());
    }

    if (adminTable && adminTable.dataset.editHandler !== "true") {
        adminTable.dataset.editHandler = "true";
        adminTable.addEventListener("click", (evento) => {
            const botao = evento.target.closest("[data-edit-product]");
            if (!botao) return;
            iniciarEdicaoFormulario(
                "#productForm",
                "../api/api.php?tabela=frigobar",
                botao.dataset.editProduct,
                (Sahara.dadosPainel.produtos || produtos).find((p) => Number(p.id) === Number(botao.dataset.editProduct)),
                "Atualizar produto"
            );
        });
    }
    atualizar();
}

function iniciarEdicaoFormulario(seletor, endpoint, id, registro, textoBotao) {
    const formulario = selecionar(seletor);
    if (!formulario || !registro) return;

    formulario.dataset.preserveEditMode = "true";
    formulario.reset();
    Object.entries(registro).forEach(([campo, valor]) => {
        const elemento = formulario.elements.namedItem(campo);
        if (elemento && elemento.type !== "hidden") {
            if (campo === "duracao_periodo_minutos" && elemento.type === "time") {
                const minutos = Number(valor);
                elemento.value = Number.isInteger(minutos) && minutos > 0 && minutos < 1440
                    ? `${String(Math.floor(minutos / 60)).padStart(2, "0")}:${String(minutos % 60).padStart(2, "0")}`
                    : "";
            } else {
                elemento.value = valor ?? "";
            }
        }
    });
    const capacidade = formulario.elements.namedItem("capacidade_pessoas");
    const maximoPessoas = formulario.elements.namedItem("maximo_pessoas");
    if (capacidade && maximoPessoas && maximoPessoas.type === "number") {
        maximoPessoas.min = String(Number(capacidade.value) || 1);
        maximoPessoas.setCustomValidity("");
    }

    const separador = endpoint.includes("?") ? "&" : "?";
    formulario.action = `${endpoint}${separador}id=${encodeURIComponent(id)}`;
    formulario.dataset.requestMethod = "PUT";
    formulario.dataset.recordId = String(id);

    const botaoEnviar = formulario.querySelector("button[type='submit']");
    if (botaoEnviar) botaoEnviar.textContent = textoBotao;
    formulario.scrollIntoView({ behavior: "smooth", block: "start" });
}

function calcularResumoFinanceiroEstadia(estadia, consumos, pagamentos) {
    const consumo = consumos
        .filter((item) => Number(item.estadia_id) === Number(estadia.id))
        .reduce((total, item) => total + Number(item.subtotal) *
            (1 + Number(item.percentual_taxa_aplicado) / 100), 0);
    const hospedagem = Number(estadia.valor_total_previsto || 0);
    const total = Math.round((hospedagem + consumo) * 100) / 100;
    const pago = Math.round(pagamentos
        .filter((pagamento) =>
            Number(pagamento.estadia_id) === Number(estadia.id) &&
            pagamento.status === "aprovado"
        )
        .reduce((soma, pagamento) => soma + Number(pagamento.valor), 0) * 100) / 100;

    return {
        hospedagem,
        consumo: Math.round(consumo * 100) / 100,
        total,
        pago,
        saldo: Math.max(0, Math.round((total - pago) * 100) / 100)
    };
}

function atualizarFormularioPagamento() {
    const formulario = selecionar("#paymentForm");
    const selecaoEstadia = selecionar("#pagamento_estadia");
    const campoValor = selecionar("#pagamento_valor");
    const resumoElemento = selecionar("#paymentStaySummary");
    if (!formulario || !selecaoEstadia || !campoValor || !resumoElemento) return;

    const { estadias = [], usuarios = [], clientes = [], consumos = [], pagamentos = [] } = Sahara.dadosPainel;
    const nomesUsuarios = new Map(usuarios.map((usuario) => [Number(usuario.id), usuario.nome]));
    const nomesClientes = new Map(clientes.map((cliente) => [
        Number(cliente.id),
        nomesUsuarios.get(Number(cliente.usuario_id)) || `Cliente #${cliente.id}`
    ]));
    const selecaoAnterior = selecaoEstadia.value;
    selecaoEstadia.innerHTML = '<option value="">Selecione uma estadia</option>';

    estadias
        .filter((estadia) => estadia.status !== "cancelada")
        .forEach((estadia) => {
            const conta = calcularResumoFinanceiroEstadia(estadia, consumos, pagamentos);
            const option = document.createElement("option");
            option.value = String(estadia.id);
            option.textContent = `${estadia.codigo} · ${nomesClientes.get(Number(estadia.cliente_id)) || `Cliente #${estadia.cliente_id}`} · Em aberto ${formatarMoeda(conta.saldo)}`;
            selecaoEstadia.append(option);
        });

    if (selecaoAnterior && estadias.some((estadia) => String(estadia.id) === selecaoAnterior)) {
        selecaoEstadia.value = selecaoAnterior;
    }

    const atualizarResumo = () => {
        const estadia = estadias.find((registro) => String(registro.id) === selecaoEstadia.value);
        if (!estadia) {
            resumoElemento.textContent = "Selecione uma estadia para consultar o total, os valores já pagos e o saldo em aberto.";
            return;
        }

        const conta = calcularResumoFinanceiroEstadia(estadia, consumos, pagamentos);
        resumoElemento.innerHTML = `
            <div class="room-booking-summary-row"><span>Hospedagem prevista</span><strong>${formatarMoeda(conta.hospedagem)}</strong></div>
            <div class="room-booking-summary-row"><span>Consumo</span><strong>${formatarMoeda(conta.consumo)}</strong></div>
            <div class="room-booking-summary-row"><span>Total da estadia</span><strong>${formatarMoeda(conta.total)}</strong></div>
            <div class="room-booking-summary-row"><span>Pagamentos confirmados</span><strong>${formatarMoeda(conta.pago)}</strong></div>
            <div class="room-booking-summary-row room-booking-total"><span>Saldo em aberto</span><strong>${formatarMoeda(conta.saldo)}</strong></div>
        `;
        campoValor.value = conta.saldo > 0 ? conta.saldo.toFixed(2) : "";
    };

    selecaoEstadia.onchange = atualizarResumo;
    atualizarResumo();
}

function desenharListaEstadias(estadias, usuarios, clientes, quartos) {
    const tbody = selecionar("#staysTable, #clientStaysTable, #recentStays");
    if (!tbody) return;

    const filtroTexto = selecionar("#staySearch");
    const filtroStatus = selecionar("#stayStatus");
    const dataInicio = selecionar("#stayStart");
    const dataFim = selecionar("#stayEnd");
    const atualizar = () => {
        const texto = filtroTexto?.value.trim().toLowerCase() || "";
        const status = filtroStatus?.value || "";
        const dadosAtuais = Sahara.dadosPainel;
        const nomesUsuario = new Map((dadosAtuais.usuarios || usuarios).map((u) => [Number(u.id), u.nome]));
        const nomesCliente = new Map((dadosAtuais.clientes || clientes).map((cliente) => [
            Number(cliente.id),
            nomesUsuario.get(Number(cliente.usuario_id)) || `#${cliente.id}`
        ]));
        const quartosAtuais = new Map((dadosAtuais.quartos || quartos).map((q) => [Number(q.id), q]));
        const lista = (dadosAtuais.estadias || estadias).filter((e) =>
            (!status || e.status === status) &&
            (!dataInicio?.value || String(e.entrada_prevista || "").slice(0, 10) >= dataInicio.value) &&
            (!dataFim?.value || String(e.entrada_prevista || "").slice(0, 10) <= dataFim.value) &&
            (!texto || `${e.codigo} ${nomesCliente.get(Number(e.cliente_id)) || e.cliente_id}`.toLowerCase().includes(texto))
        );
        const colunas = tbody.id === "staysTable" ? 8 : 6;
        desenharTabela(tbody, lista.map((e) => {
            const quarto = quartosAtuais.get(Number(e.quarto_id));
            const cliente = nomesCliente.get(Number(e.cliente_id)) || `#${e.cliente_id}`;
            const statusEstadia = {
                reservada: "Reservada · aguardando check-in",
                em_andamento: "Em andamento · consumo liberado",
                finalizada: "Finalizada",
                cancelada: "Cancelada"
            }[e.status] || e.status;
            if (tbody.id === "clientStaysTable") {
                return criarLinhaTabela([
                    escaparHTML(e.codigo),
                    escaparHTML(quarto?.nome || `#${e.quarto_id}`),
                    escaparHTML(e.entrada_prevista || ""),
                    escaparHTML(e.saida_prevista || ""),
                    formatarMoeda(e.valor_total_previsto),
                    escaparHTML(statusEstadia)
                ]);
            }
            if (tbody.id === "recentStays") {
                return criarLinhaTabela([
                    escaparHTML(e.codigo),
                    escaparHTML(cliente),
                    escaparHTML(quarto?.nome || `#${e.quarto_id}`),
                    escaparHTML(e.entrada_prevista || ""),
                    formatarMoeda(e.valor_total_previsto),
                    escaparHTML(statusEstadia)
                ]);
            }
            const acao = e.status === "reservada"
                ? `<button type="button" class="btn btn-secondary btn-small" data-stay-action="em_andamento" data-stay-id="${Number(e.id)}">Confirmar check-in</button><button type="button" class="btn btn-secondary btn-small" data-stay-action="cancelada" data-stay-id="${Number(e.id)}">Cancelar</button>`
                : e.status === "em_andamento"
                    ? `<button type="button" class="btn btn-primary btn-small" data-stay-action="finalizada" data-stay-id="${Number(e.id)}">Finalizar</button>`
                    : "—";
            return criarLinhaTabela([
                escaparHTML(e.codigo),
                escaparHTML(cliente),
                escaparHTML(quarto?.nome || `#${e.quarto_id}`),
                escaparHTML(e.entrada_prevista || ""),
                escaparHTML(e.saida_prevista || ""),
                formatarMoeda(e.valor_total_previsto),
                escaparHTML(statusEstadia),
                acao
            ]);
        }), colunas, "Nenhuma estadia cadastrada.");
    };

    if (tbody.id === "staysTable" && tbody.dataset.actionHandler !== "true") {
        tbody.dataset.actionHandler = "true";
        tbody.addEventListener("click", async (evento) => {
            const botao = evento.target.closest("[data-stay-action]");
            if (!botao) return;

            botao.disabled = true;
            try {
                const resposta = await solicitarAPI(
                    `../api/api.php?tabela=estadias&id=${encodeURIComponent(botao.dataset.stayId)}&acao=alterar_status`,
                    {
                        method: "PUT",
                        body: JSON.stringify({ status: botao.dataset.stayAction })
                    }
                );
                mostrarMensagem(selecionar("#stayMessage"), resposta.mensagem, true);
                await carregarDadosPainel();
            } catch (erro) {
                mostrarMensagem(selecionar("#stayMessage"), erro.message);
                botao.disabled = false;
            }
        });
    }

    [filtroTexto, filtroStatus, dataInicio, dataFim].filter(Boolean).forEach((el) => {
        el.addEventListener("input", atualizar);
        el.addEventListener("change", atualizar);
    });
    selecionar("#stayFilterForm")?.addEventListener("submit", (evento) => evento.preventDefault());
    atualizar();
}

function desenharListaPagamentos(pagamentos, estadias) {
    const tbody = selecionar("#paymentsTable, #clientPaymentsTable");
    if (!tbody) return;
    const status = selecionar("#paymentStatus");
    const metodo = selecionar("#paymentMethod");
    const pesquisa = selecionar("#paymentSearch");
    const dataInicio = selecionar("#paymentStart");
    const dataFim = selecionar("#paymentEnd");
    const atualizar = () => {
        const cliente = tbody.id === "clientPaymentsTable";
        const termo = pesquisa?.value.trim().toLowerCase() || "";
        const lista = (Sahara.dadosPainel.pagamentos || pagamentos).filter((p) => {
            const data = String(p.pago_em || p.criado_em || "").slice(0, 10);
            const referencia = `${p.codigo_transacao || ""} ${(Sahara.dadosPainel.estadias || estadias).find((e) => Number(e.id) === Number(p.estadia_id))?.codigo || ""}`.toLowerCase();
            return (!status?.value || p.status === status.value) &&
                (!metodo?.value || p.metodo === metodo.value) &&
                (!termo || referencia.includes(termo)) &&
                (!dataInicio?.value || data >= dataInicio.value) &&
                (!dataFim?.value || data <= dataFim.value);
        });
        const estadiaPorId = new Map(
            (Sahara.dadosPainel.estadias || estadias)
                .map((estadia) => [Number(estadia.id), estadia])
        );
        const rotulosPagamento = {
            pendente: "Pendente",
            aprovado: "Pago/confirmado",
            recusado: "Recusado",
            estornado: "Estornado",
            cancelado: "Cancelado"
        };
        const linhas = lista.map((p) => {
            const estadia = estadiaPorId.get(Number(p.estadia_id));
            const situacao = rotulosPagamento[p.status] || p.status;
            return cliente
                ? criarLinhaTabela([escaparHTML(estadia?.codigo || `#${p.estadia_id}`), escaparHTML(p.pago_em || p.criado_em || ""), escaparHTML(p.metodo), formatarMoeda(p.valor), escaparHTML(situacao)])
                : criarLinhaTabela([
                    String(p.id),
                    escaparHTML(estadia?.codigo || `#${p.estadia_id}`),
                    formatarMoeda(p.valor),
                    escaparHTML(p.metodo),
                    escaparHTML(p.pago_em || p.criado_em || ""),
                    escaparHTML(situacao),
                    p.status === "pendente"
                        ? `<button type="button" class="btn btn-primary btn-small" data-confirm-payment="${Number(p.id)}">Confirmar recebimento</button>`
                        : "—"
                ]);
        });
        desenharTabela(tbody, linhas, cliente ? 5 : 7, "Nenhum pagamento registrado.");
    };
    if (tbody.id === "paymentsTable" && tbody.dataset.confirmHandler !== "true") {
        tbody.dataset.confirmHandler = "true";
        tbody.addEventListener("click", async (evento) => {
            const botao = evento.target.closest("[data-confirm-payment]");
            if (!botao) return;

            botao.disabled = true;
            try {
                const resposta = await solicitarAPI(
                    `../api/api.php?tabela=pagamentos&id=${encodeURIComponent(botao.dataset.confirmPayment)}`,
                    {
                        method: "PUT",
                        body: JSON.stringify({ status: "aprovado" })
                    }
                );
                mostrarMensagem(selecionar("#paymentMessage"), `${resposta.mensagem} Pagamento marcado como pago/confirmado.`, true);
                await carregarDadosPainel();
            } catch (erro) {
                mostrarMensagem(selecionar("#paymentMessage"), erro.message);
                botao.disabled = false;
            }
        });
    }
    [status, metodo, pesquisa, dataInicio, dataFim].filter(Boolean).forEach((el) => {
        el.addEventListener("input", atualizar);
        el.addEventListener("change", atualizar);
    });
    selecionar("#paymentFilterForm, #clientPaymentFilter")?.addEventListener("submit", (evento) => evento.preventDefault());
    atualizar();
}

function desenharListaConsumos(consumos, produtos) {
    const tbody = selecionar("#clientConsumptionTable");
    if (!tbody) return;
    const produtoPorId = new Map(produtos.map((p) => [Number(p.id), p]));
    const total = consumos.reduce((soma, item) =>
        soma + Number(item.subtotal) * (1 + Number(item.percentual_taxa_aplicado) / 100), 0);
    desenharTabela(tbody, consumos.map((item) => criarLinhaTabela([
        escaparHTML(produtoPorId.get(Number(item.frigobar_id))?.nome || `#${item.frigobar_id}`),
        escaparHTML(item.registrado_em || ""),
        String(item.quantidade),
        formatarMoeda(item.preco_unitario),
        formatarMoeda(Number(item.subtotal) * (1 + Number(item.percentual_taxa_aplicado) / 100))
    ])), 5, "Nenhum consumo registrado para sua conta.");
    const totalElemento = selecionar("#clientConsumptionTotal");
    if (totalElemento) totalElemento.textContent = formatarMoeda(total);
}

function preencherIndicadores(quartos, estadias, pagamentos, consumos) {
    const textoIndicadores = {
        quartos: quartos.length,
        disponiveis: quartos.filter((q) => q.status === "disponivel").length,
        estadias: estadias.filter((e) => e.status === "em_andamento").length,
        pendentes: pagamentos.filter((p) => p.status === "pendente").length
    };
    Object.entries(textoIndicadores).forEach(([chave, valor]) => {
        const elemento = selecionar(`[data-stat="${chave}"]`);
        if (elemento) elemento.textContent = String(valor);
    });

    const estadiaAtual = estadias.find((e) => e.status === "em_andamento");
    const campoEstadia = selecionar('[data-client-stat="estadia"]');
    const campoConsumos = selecionar('[data-client-stat="consumos"]');
    const campoPendentes = selecionar('[data-client-stat="pendentes"]');
    if (campoEstadia) campoEstadia.textContent = estadiaAtual ? estadiaAtual.codigo : "Nenhuma";
    if (campoConsumos) campoConsumos.textContent = String(consumos.length);
    if (campoPendentes) campoPendentes.textContent = String(pagamentos.filter((p) => p.status === "pendente").length);

    const totaisAdmin = {
        reservada: estadias.filter((e) => e.status === "reservada").length,
        em_andamento: estadias.filter((e) => e.status === "em_andamento").length,
        finalizada: estadias.filter((e) => e.status === "finalizada").length,
        cancelada: estadias.filter((e) => e.status === "cancelada").length
    };
    Object.entries(totaisAdmin).forEach(([chave, valor]) => {
        const elemento = selecionar(`[data-stay-stat="${chave}"]`);
        if (elemento) elemento.textContent = String(valor);
    });

    ["aprovado", "pendente", "recusado", "estornado"].forEach((status) => {
        const elemento = selecionar(`[data-payment-stat="${status}s"]`);
        if (elemento) elemento.textContent = String(pagamentos.filter((p) => p.status === status).length);
    });
    ["aprovado", "pendente"].forEach((status) => {
        const elemento = selecionar(`[data-client-payment="${status}"]`);
        const soma = pagamentos.filter((p) => p.status === status).reduce((total, p) => total + Number(p.valor), 0);
        if (elemento) elemento.textContent = formatarMoeda(soma);
    });

    const saldoElemento = selecionar("[data-client-payment-balance]");
    const resumoCliente = selecionar("#clientBillingSummary");
    if (saldoElemento || resumoCliente) {
        const estadiasCliente = estadias.filter((estadia) => estadia.status !== "cancelada");
        const contas = estadiasCliente.map((estadia) => ({
            estadia,
            conta: calcularResumoFinanceiroEstadia(estadia, consumos, pagamentos)
        }));
        const saldoTotal = contas.reduce((soma, item) => soma + item.conta.saldo, 0);
        if (saldoElemento) saldoElemento.textContent = formatarMoeda(saldoTotal);
        if (resumoCliente) {
            resumoCliente.innerHTML = contas.length
                ? contas.map(({ estadia, conta }) => {
                    const situacao = conta.total <= 0
                        ? "Sem valores registrados"
                        : conta.saldo === 0
                            ? "Pago"
                            : conta.pago > 0
                                ? "Parcialmente pago"
                                : "Em aberto";
                    return `<article class="quick-action-card">
                        <span class="quick-action-icon">＄</span>
                        <h3>${escaparHTML(estadia.codigo)} · ${escaparHTML(situacao)}</h3>
                        <p>Hospedagem: ${formatarMoeda(conta.hospedagem)}<br>Consumo: ${formatarMoeda(conta.consumo)}<br>Pago/confirmado: ${formatarMoeda(conta.pago)}</p>
                        <strong>Saldo: ${formatarMoeda(conta.saldo)}</strong>
                    </article>`;
                }).join("")
                : '<p class="muted-text">Nenhuma estadia vinculada à sua conta.</p>';
        }
    }
}

/* ============================================================
   36. ANO, DATA E INICIALIZAÇÃO GERAL
============================================================ */

function inicializarSahara() {
    inicializarMenu();
    inicializarMostrarSenha();
    inicializarModais();
    inicializarConfirmacoes();

    inicializarValidacaoFormularios();
    inicializarConfirmacaoSenha();

    inicializarPesquisa();
    inicializarFiltros();
    inicializarAbas();

    inicializarGaleria();
    inicializarQuantidade();
    inicializarCarrinho();

    inicializarMascaraTelefone();
    inicializarCamposMoeda();
    inicializarLimparFormularios();

    inicializarRelogio();
    inicializarAnimacoes();
    inicializarSidebar();
    inicializarLinkAtivo();

    inicializarContadorCaracteres();
    inicializarPreviewImagem();
    inicializarForcaSenha();
    inicializarStatusVisual();

    inicializarCopiarTexto();
    inicializarVoltarTopo();
    inicializarAnoRodape();
    inicializarCamposObrigatorios();

    inicializarProtecaoEnvioDuplo();
    inicializarSelecionarTodos();
    inicializarFiltroDatas();

    inicializarAcessibilidade();
    inicializarTema();
    inicializarIntegracaoAPI().catch((erro) => {
        console.error("Motel Sahara: falha ao conectar com a API.", erro);
    });

    console.info("Motel Sahara: JavaScript inicializado.");
}

if (document.readyState === "loading") {
    document.addEventListener(
        "DOMContentLoaded",
        inicializarSahara
    );
} else {
    inicializarSahara();
}

/* ============================================================
   FIM DO JAVASCRIPT PRINCIPAL — MOTEL SAHARA
============================================================ */