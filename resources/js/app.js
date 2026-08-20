const menu = document.querySelector('[data-mobile-menu]');
const backdrop = document.querySelector('[data-menu-backdrop]');
const openButton = document.querySelector('[data-menu-open]');
const closeButton = document.querySelector('[data-menu-close]');

if (menu && backdrop && openButton && closeButton) {
    const close = () => {
        menu.classList.add('hidden');
        backdrop.classList.add('hidden');
        openButton.setAttribute('aria-expanded', 'false');
        openButton.focus();
    };
    const open = () => {
        menu.classList.remove('hidden');
        backdrop.classList.remove('hidden');
        openButton.setAttribute('aria-expanded', 'true');
        closeButton.focus();
    };

    openButton.addEventListener('click', open);
    closeButton.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && openButton.getAttribute('aria-expanded') === 'true') close();
    });
}
