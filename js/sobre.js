"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const header = document.querySelector(".home-header");
    const menuButton = document.querySelector(".home-menu-toggle");
    const navigation = document.querySelector(".home-navigation");

    if (!header || !menuButton || !navigation) {
        return;
    }

    const fecharMenu = () => {
        menuButton.setAttribute("aria-expanded", "false");
        menuButton.setAttribute("aria-label", "Abrir menu");
        navigation.classList.remove("home-navigation-open");
    };

    menuButton.addEventListener("click", () => {
        const aberto = menuButton.getAttribute("aria-expanded") !== "true";
        menuButton.setAttribute("aria-expanded", String(aberto));
        menuButton.setAttribute(
            "aria-label",
            aberto ? "Fechar menu" : "Abrir menu"
        );
        navigation.classList.toggle("home-navigation-open", aberto);
    });

    navigation.querySelectorAll('a[href^="#"]').forEach((link) => {
        link.addEventListener("click", fecharMenu);
    });

    document.addEventListener("click", (event) => {
        if (
            menuButton.getAttribute("aria-expanded") === "true"
            && !header.contains(event.target)
        ) {
            fecharMenu();
        }
    });

    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && menuButton.getAttribute("aria-expanded") === "true") {
            fecharMenu();
            menuButton.focus();
        }
    });

    const atualizarCabecalho = () => {
        header.classList.toggle("is-scrolled", window.scrollY > 24);
    };

    atualizarCabecalho();
    window.addEventListener("scroll", atualizarCabecalho, { passive: true });

    const secoes = Array.from(navigation.querySelectorAll('a[href^="#"]'))
        .map((link) => ({
            link,
            secao: document.querySelector(link.getAttribute("href"))
        }))
        .filter((item) => item.secao);

    if ("IntersectionObserver" in window) {
        const observador = new IntersectionObserver(
            (entradas) => {
                entradas.forEach((entrada) => {
                    if (!entrada.isIntersecting) {
                        return;
                    }

                    secoes.forEach(({ link, secao }) => {
                        if (secao === entrada.target) {
                            link.setAttribute("aria-current", "location");
                        } else {
                            link.removeAttribute("aria-current");
                        }
                    });
                });
            },
            { rootMargin: "-25% 0px -65% 0px" }
        );

        secoes.forEach(({ secao }) => observador.observe(secao));
    }
});
