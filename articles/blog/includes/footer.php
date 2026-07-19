</main>
<footer class="site-footer">
    <p>&copy; <?= date('Y') ?> <?= e(get_setting('site_name')) ?> — <?= e(get_setting('tagline')) ?></p>
</footer>

<!-- Visionneuse plein écran : voir une photo en entier (clic image ou bouton 🔍) -->
<div id="lightbox" class="lightbox" hidden>
    <button type="button" class="lightbox-close" id="lightbox-close" aria-label="Fermer">✕</button>
    <img id="lightbox-img" alt="Photo en plein écran">
</div>
<script>
(function () {
    const lb  = document.getElementById('lightbox');
    const img = document.getElementById('lightbox-img');
    if (!lb) return;
    const open  = (url) => { if (!url) return; img.src = url; lb.hidden = false; document.body.style.overflow = 'hidden'; };
    const close = () => { lb.hidden = true; img.src = ''; document.body.style.overflow = ''; };
    document.querySelectorAll('[data-full]').forEach(el => {
        el.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); open(el.getAttribute('data-full')); });
    });
    document.getElementById('lightbox-close').addEventListener('click', close);
    lb.addEventListener('click', (e) => { if (e.target.id === 'lightbox' || e.target.id === 'lightbox-img') close(); });
    window.addEventListener('keydown', (e) => { if (e.key === 'Escape') close(); });
})();
</script>
</body>
</html>
