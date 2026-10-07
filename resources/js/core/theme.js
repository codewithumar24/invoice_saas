/** Light / Dark / System. The choice is saved on the user profile and mirrored in localStorage for instant paint. */
const root = document.documentElement;
const media = window.matchMedia('(prefers-color-scheme: dark)');

function apply(preference) {
    const dark = preference === 'dark' || (preference === 'system' && media.matches);
    root.classList.toggle('dark', dark);
}

const preference = root.dataset.theme || localStorage.getItem('theme') || 'light';
apply(preference);
localStorage.setItem('theme', preference);

media.addEventListener('change', () => apply(root.dataset.theme || localStorage.getItem('theme') || 'light'));

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-set-theme]');
    if (!button) return;
    root.dataset.theme = button.dataset.setTheme;
    localStorage.setItem('theme', button.dataset.setTheme);
    apply(button.dataset.setTheme);
});
