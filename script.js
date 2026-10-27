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

    seletores: {
        menuToggle: "[data-menu-toggle]",
        menu: "[data-menu]",
        modal: "[data-modal]",
        modalAbrir: "[data-modal-open]",
        modalFechar: "[data-modal-close]",
        senhaToggle: "[data-password-toggle]",
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

            if (rotulo && !rotulo.querySelector(".required-mark")) {
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