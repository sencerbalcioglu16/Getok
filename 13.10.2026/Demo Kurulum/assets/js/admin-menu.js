(() => {
    const baslat = () => {
        const dugme = document.querySelector('.admin-menu-toggle');
        const menu = document.querySelector('.admin-sidebar');
        const ortu = document.querySelector('.admin-menu-overlay');
        if (!dugme || !menu || !ortu) return;

        const kapat = () => {
            menu.classList.remove('is-open');
            ortu.classList.remove('is-open');
            document.body.classList.remove('admin-menu-acik');
            dugme.setAttribute('aria-expanded', 'false');
            dugme.setAttribute('aria-label', 'Yönetim menüsünü aç');
        };
        const ac = () => {
            menu.classList.add('is-open');
            ortu.classList.add('is-open');
            document.body.classList.add('admin-menu-acik');
            dugme.setAttribute('aria-expanded', 'true');
            dugme.setAttribute('aria-label', 'Yönetim menüsünü kapat');
        };

        dugme.addEventListener('click', () => menu.classList.contains('is-open') ? kapat() : ac());
        ortu.addEventListener('click', kapat);
        menu.querySelectorAll('a').forEach((baglanti) => baglanti.addEventListener('click', kapat));
        document.addEventListener('keydown', (olay) => { if (olay.key === 'Escape') kapat(); });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', baslat, { once: true });
    else baslat();
})();
