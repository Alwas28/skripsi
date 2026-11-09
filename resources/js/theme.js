/* ════════════════════════════════════════════════════════════
   Sidebar / theme / accent-color / toast behavior ported from
   template.html for the authenticated app shell + auth pages.
   ════════════════════════════════════════════════════════════ */

const accentPalette = [
    { id: 'amber', label: 'Amber', ac: '#f59e0b', ac2: '#ef4444', rgb: '245,158,11' },
    { id: 'emerald', label: 'Hijau', ac: '#10b981', ac2: '#06b6d4', rgb: '16,185,129' },
    { id: 'blue', label: 'Biru', ac: '#4f6ef7', ac2: '#7c3aed', rgb: '79,110,247' },
    { id: 'violet', label: 'Ungu', ac: '#8b5cf6', ac2: '#ec4899', rgb: '139,92,246' },
    { id: 'rose', label: 'Merah', ac: '#f43f5e', ac2: '#f97316', rgb: '244,63,94' },
    { id: 'cyan', label: 'Cyan', ac: '#06b6d4', ac2: '#3b82f6', rgb: '6,182,212' },
    { id: 'lime', label: 'Lime', ac: '#84cc16', ac2: '#10b981', rgb: '132,204,22' },
    { id: 'pink', label: 'Pink', ac: '#ec4899', ac2: '#8b5cf6', rgb: '236,72,153' },
    { id: 'orange', label: 'Oranye', ac: '#f97316', ac2: '#f59e0b', rgb: '249,115,22' },
    { id: 'sky', label: 'Sky', ac: '#38bdf8', ac2: '#6366f1', rgb: '56,189,248' },
];

let currentAccent = localStorage.getItem('ui-accent') || 'amber';

/* ════════ SIDEBAR ════════ */
function toggleSB() {
    const sb = document.getElementById('sb');
    const ov = document.getElementById('ov');
    if (!sb || !ov) return;
    sb.classList.toggle('open');
    ov.style.display = sb.classList.contains('open') ? 'block' : 'none';
}
function closeSB() {
    const sb = document.getElementById('sb');
    const ov = document.getElementById('ov');
    if (!sb || !ov) return;
    sb.classList.remove('open');
    ov.style.display = 'none';
}

/* ════════ ACCENT COLORS ════════ */
function renderSwatches() {
    const container = document.getElementById('color-swatches');
    if (!container) return;
    container.innerHTML = accentPalette.map(c => `
        <div class="swatch${c.id === currentAccent ? ' active' : ''}"
            style="background:linear-gradient(135deg,${c.ac},${c.ac2})"
            title="${c.label}" data-accent="${c.id}">
            <span class="check"><i class="fa-solid fa-check" style="font-size:10px"></i></span>
        </div>
    `).join('');
    container.querySelectorAll('.swatch').forEach(el => {
        el.addEventListener('click', () => setAccent(el.dataset.accent, true));
    });
}

function setAccent(id, persist) {
    const c = accentPalette.find(x => x.id === id);
    if (!c) return;
    currentAccent = id;
    const root = document.documentElement;
    root.style.setProperty('--ac', c.ac);
    root.style.setProperty('--ac2', c.ac2);
    root.style.setProperty('--ac-rgb', c.rgb);
    root.style.setProperty('--ac-lt', `rgba(${c.rgb},.14)`);
    root.style.setProperty('--ac-lt2', `rgba(${c.rgb},.08)`);
    renderSwatches();
    document.querySelectorAll('.nav-active').forEach(el => {
        el.style.borderLeftColor = c.ac;
        el.style.color = c.ac;
        el.style.background = `rgba(${c.rgb},.14)`;
    });
    if (persist) {
        localStorage.setItem('ui-accent', id);
        showToast('success', `Warna diubah ke ${c.label}`);
    }
}

/* ════════ THEME ════════ */
let tpOpen = false;
function toggleTP() {
    const tp = document.getElementById('tp');
    if (!tp) return;
    tpOpen = !tpOpen;
    tp.classList.toggle('hidden', !tpOpen);
}
function setTheme(t, persist) {
    document.body.classList.toggle('light', t === 'light');
    const dark = document.getElementById('tp-dark');
    const light = document.getElementById('tp-light');
    if (dark && light) {
        dark.classList.toggle('active', t === 'dark');
        light.classList.toggle('active', t === 'light');
    }
    tpOpen = false;
    const tp = document.getElementById('tp');
    if (tp) tp.classList.add('hidden');
    if (persist) localStorage.setItem('ui-theme', t);
}

/* ════════ MODAL ════════ */
function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

/* ════════ TOAST ════════ */
function showToast(type, msg) {
    const ic = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info' };
    const container = document.getElementById('toast-container');
    if (!container) return;
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<i class="fa-solid ${ic[type] || ic.info} ti" style="font-size:16px;flex-shrink:0"></i><span>${msg}</span>`;
    container.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 2700);
    setTimeout(() => el.remove(), 3000);
}

/* ════════ HAMBURGER RESPONSIVE ════════ */
function checkWidth() {
    const hb = document.getElementById('hamburger');
    if (!hb) return;
    if (window.innerWidth < 1024) { hb.style.display = 'block'; }
    else { hb.style.display = 'none'; closeSB(); }
}

/* ════════ INIT ════════ */
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('hamburger')?.addEventListener('click', toggleSB);
    document.getElementById('sb-close-overlay')?.addEventListener('click', closeSB);
    document.getElementById('ov')?.addEventListener('click', closeSB);
    document.getElementById('theme-btn')?.addEventListener('click', toggleTP);
    document.getElementById('tp-dark')?.addEventListener('click', () => setTheme('dark', true));
    document.getElementById('tp-light')?.addEventListener('click', () => setTheme('light', true));

    document.addEventListener('click', e => {
        const btn = document.getElementById('theme-btn');
        const tp = document.getElementById('tp');
        if (!btn || !tp) return;
        if (!btn.contains(e.target) && !tp.contains(e.target)) {
            tpOpen = false;
            tp.classList.add('hidden');
        }
    });

    const savedTheme = localStorage.getItem('ui-theme') || 'dark';
    setTheme(savedTheme, false);
    setAccent(currentAccent, false);
    renderSwatches();
    checkWidth();

    window.addEventListener('resize', checkWidth);
});

window.showToast = showToast;
window.openModal = openModal;
window.closeModal = closeModal;
