/* =====================================================================
   Sélecteur de thème — bascule entre "mario" (rétro 8-bit) et "dark"
   (thème sombre d'origine). Le choix est mémorisé dans localStorage.
   Fonctionne en activant / désactivant la feuille mario-theme.css.
   Ce script est chargé dans le <head>, juste APRÈS le <link> du thème,
   pour appliquer le bon thème avant l'affichage (évite le clignotement).
   ===================================================================== */
(function () {
    var KEY = 'cvTheme';
    var DEFAULT = 'mario'; // thème actif par défaut

    function marioLink() { return document.getElementById('theme-mario'); }

    function current() { return localStorage.getItem(KEY) || DEFAULT; }

    function apply(theme) {
        var link = marioLink();
        if (link) link.disabled = (theme !== 'mario');
        document.documentElement.setAttribute('data-theme', theme);
    }

    function label() {
        return current() === 'mario' ? '🍄 Mario' : '🌙 Sombre';
    }

    // 1) Appliquer immédiatement le thème mémorisé (le <link> est déjà parsé).
    apply(current());

    // 2) Ajouter le bouton flottant une fois le <body> disponible.
    function addButton() {
        if (document.getElementById('themeSwitchBtn')) return;
        var b = document.createElement('button');
        b.id = 'themeSwitchBtn';
        b.type = 'button';
        b.title = 'Changer de thème';
        b.textContent = label();
        b.style.cssText =
            'position:fixed;right:14px;bottom:14px;z-index:99999;cursor:pointer;' +
            'padding:9px 13px;border:3px solid #000;background:#fac000;color:#000;' +
            'font-family:monospace;font-weight:700;font-size:13px;line-height:1;' +
            'box-shadow:3px 3px 0 #000;border-radius:0;';
        b.addEventListener('click', function () {
            var next = current() === 'mario' ? 'dark' : 'mario';
            localStorage.setItem(KEY, next);
            apply(next);
            b.textContent = label();
        });
        document.body.appendChild(b);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addButton);
    } else {
        addButton();
    }
})();
