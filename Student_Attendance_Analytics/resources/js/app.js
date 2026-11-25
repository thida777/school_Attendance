import ApexCharts from 'apexcharts';
import { createIcons, icons } from 'lucide';
import { initAttendance } from './attendance';
import { initDatePickers } from './date-picker';
import { initSelectPickers } from './select-picker';

/* ---------- Icons: <i data-lucide="name"> becomes an SVG icon ---------- */
const drawIcons = () => createIcons({ icons });
window.drawIcons = drawIcons;

/* ---------- Theme switch (light / dark), remembered in the browser ---------- */
const currentTheme = () => document.documentElement.dataset.theme || 'light';

function drawThemeButtons() {
    document.querySelectorAll('[data-action="theme"]').forEach((btn) => {
        btn.innerHTML = `<i data-lucide="${currentTheme() === 'dark' ? 'sun' : 'moon'}" class="size-4"></i>`;
    });
    drawIcons();
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('[data-action="theme"]')) return;
    const next = currentTheme() === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    try {
        localStorage.setItem('theme', next);
    } catch {
        // private mode: the choice lasts only for this page
    }
    drawThemeButtons();
    document.dispatchEvent(new Event('themechange'));
});

/* ---------- Show / hide a password ---------- */
document.addEventListener('click', (e) => {
    const toggle = e.target.closest('[data-action="toggle-password"]');
    if (!toggle) return;
    const input = document.getElementById(toggle.dataset.target);
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    toggle.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}" class="size-4"></i>`;
    drawIcons();
});

/* ---------- Pop-up forms (add / edit) ----------
   <button data-form-dialog="student-form" data-action="/students/5" data-method="PUT"
           data-title="Edit student" data-values='{"name_latin":"Sok Dara", ...}'>
   opens the dialog, points its form at the right address and fills in the values.
   An "Add" button sends no values, so the form starts empty. */
const RESERVED_FIELDS = ['_token', '_method', '_action', '_modal', '_title'];

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-form-dialog]');
    if (!btn) return;
    const dialog = document.getElementById(btn.dataset.formDialog);
    const form = dialog.querySelector('form[data-dialog-form]');
    const values = JSON.parse(btn.dataset.values || '{}');

    form.action = btn.dataset.action;
    form.elements._method.value = btn.dataset.method || 'POST';
    form.elements._action.value = btn.dataset.action;
    form.elements._title.value = btn.dataset.title;
    dialog.querySelector('[data-dialog-title]').textContent = btn.dataset.title;

    for (const el of form.elements) {
        if (!el.name || RESERVED_FIELDS.includes(el.name)) continue;
        if (el.type === 'checkbox') el.checked = Boolean(values[el.name]);
        // Choice buttons (e.g. gender): the one with the saved value, or the first one for "Add"
        else if (el.type === 'radio') el.checked = el.name in values ? String(values[el.name]) === el.value : el.defaultChecked;
        else if (el.type === 'file') el.value = '';
        else el.value = values[el.name] ?? (el.tagName === 'SELECT' ? el.options[0]?.value ?? '' : '');
        // Styled dropdowns and other helpers listen for "change" to show the new value
        if (el.tagName === 'SELECT' || el.type === 'hidden') el.dispatchEvent(new Event('change', { bubbles: true }));
    }
    // Extra values for the form's own script (e.g. the student photo): keys starting with "_"
    form.dispatchEvent(new CustomEvent('dialog:fill', { detail: values }));
    // Old error messages belong to the previous attempt
    dialog.querySelectorAll('[data-error]').forEach((m) => m.remove());
    dialog.querySelectorAll('.input-error, .select-error').forEach((f) => f.classList.remove('input-error', 'select-error'));
    dialog.showModal();
});

// After a failed save, Laravel sends the page back with the errors: open the same dialog again
document.querySelectorAll('dialog[data-reopen]').forEach((d) => d.showModal());

/* ---------- Delete confirmation ----------
   <button data-delete="/students/5" data-name="Sok Dara"> asks first, then sends a DELETE request. */
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-delete]');
    if (!btn) return;
    document.getElementById('confirm-delete-form').action = btn.dataset.delete;
    document.getElementById('confirm-delete-name').textContent = btn.dataset.name || '';
    // Own words for this button (e.g. "Remove the child from this parent?"), otherwise "Delete this record?"
    const title = document.getElementById('confirm-delete-title');
    const text = document.querySelector('#confirm-delete-text [data-text]');
    const button = document.getElementById('confirm-delete-button');
    title.textContent = btn.dataset.confirmTitle || title.dataset.default;
    text.textContent = btn.dataset.confirmText || document.getElementById('confirm-delete-text').dataset.default;
    button.textContent = btn.dataset.confirmButton || button.dataset.default;
    document.getElementById('confirm-delete').showModal();
});

/* ---------- QR codes ----------
   <div data-qr="https://t.me/…"></div> becomes a QR code picture. The QR library loads only on pages that use it. */
window.drawQr = async (root = document) => {
    const boxes = [...root.querySelectorAll('[data-qr]')].filter((b) => b.dataset.qr);
    if (!boxes.length) return;
    const QRCode = (await import('qrcode')).default;
    for (const box of boxes) {
        box.innerHTML = await QRCode.toString(box.dataset.qr, { type: 'svg', margin: 1, errorCorrectionLevel: 'M', color: { dark: '#0f2f2c', light: '#ffffff' } });
        box.querySelector('svg')?.setAttribute('aria-label', box.dataset.qrLabel || 'QR code');
        box.querySelector('svg')?.setAttribute('role', 'img');
    }
};
window.drawQr();

/* ---------- Toast messages disappear after a few seconds ---------- */
setTimeout(() => document.querySelectorAll('[data-toast]').forEach((t) => t.remove()), 4000);

/* ---------- Filters: changing a dropdown runs the search right away ---------- */
document.addEventListener('change', (e) => {
    if (e.target.matches('form[data-auto-submit] select')) e.target.form.submit();
});

/* ---------- Chart helper (ApexCharts) ----------
   makeChart('#id', (c) => options) draws a chart and redraws it when the theme changes.
   It returns { redraw() } for charts whose data changes on the page.
   "c" holds the colours for the current theme. */
function chartColors() {
    const dark = currentTheme() === 'dark';
    return {
        dark,
        series: dark ? '#3fb8a9' : '#1f877b',
        present: dark ? '#4fb888' : '#3aa876',
        absent: dark ? '#e07a7a' : '#dc6b6b',
        warning: '#e0a83a',
        text: dark ? '#e3eaf6' : '#1f2d3a',
        muted: '#898781',
        grid: dark ? '#2c3a5c' : '#e1e0d9',
        axis: dark ? '#3a4b75' : '#c3c2b7',
    };
}

window.makeChart = function makeChart(selector, build) {
    const el = document.querySelector(selector);
    if (!el) return;
    let chart;
    const draw = () => {
        if (chart) chart.destroy();
        const c = chartColors();
        const own = build(c);
        chart = new ApexCharts(el, {
            colors: [c.series],
            dataLabels: { enabled: false },
            grid: { borderColor: c.grid, strokeDashArray: 0 },
            tooltip: { theme: c.dark ? 'dark' : 'light' },
            theme: { mode: c.dark ? 'dark' : 'light' },
            legend: { show: false },
            ...own,
            chart: {
                toolbar: { show: false },
                zoom: { enabled: false },
                background: 'transparent',
                fontFamily: 'inherit',
                foreColor: c.muted,
                ...own.chart,
            },
        });
        chart.render();
    };
    draw();
    document.addEventListener('themechange', draw);
    // e.g. Daily / Weekly / Monthly tabs: change what build() reads, then redraw()
    return { redraw: draw };
};

drawThemeButtons();

/* ---------- Date boxes with a modern calendar ---------- */
initDatePickers(drawIcons);
initSelectPickers(drawIcons);

/* Live summary under a form: <p data-summary> lists what is picked, e.g. "10A · Biology · Mon, 9 Nov 2026 · Period 1" */
document.querySelectorAll('form [data-summary]').forEach((summary) => {
    const form = summary.closest('form');
    const draw = () => (summary.textContent = [...form.querySelectorAll('[data-label]')].map((l) => l.textContent.trim()).filter(Boolean).join(' · '));
    form.addEventListener('change', () => setTimeout(draw));
    draw();
});

/* ---------- Take Attendance page ---------- */
const attendanceForm = document.querySelector('form[data-attendance]');
if (attendanceForm) initAttendance(attendanceForm, drawIcons);

/* ---------- Page numbers without reloading the page ----------
   A click on 2, 3, « or » under a list (<div data-list> … <div data-pagination>) loads only that list
   and swaps it in place: no refresh, no jump to the top. The address bar follows, so Refresh and Back work.
   If anything fails, the link simply opens the normal way. */
document.addEventListener('click', async (e) => {
    const link = e.target.closest('[data-pagination] a[href]');
    if (!link || link.getAttribute('href') === '#' || e.ctrlKey || e.metaKey || e.shiftKey) return;
    const list = link.closest('[data-list]');
    if (!list) return;
    e.preventDefault();

    const index = [...document.querySelectorAll('[data-list]')].indexOf(list);
    list.classList.add('opacity-60', 'pointer-events-none');
    try {
        const response = await fetch(link.href, { headers: { Accept: 'text/html' } });
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const fresh = page.querySelectorAll('[data-list]')[index];
        if (!response.ok || !fresh) throw new Error('list not found');

        list.replaceWith(fresh);
        history.pushState({ list: true }, '', link.href);
        drawIcons();
        // Bring the top of the list into view only when it is above the screen
        if (fresh.getBoundingClientRect().top < 0) fresh.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch {
        location.href = link.href;
    }
});
// Back / Forward after a swapped page: show that page
window.addEventListener('popstate', (e) => {
    if (e.state?.list || document.querySelector('[data-list]')) location.reload();
});

/* ---------- Stay in the same place after a pop-up form or a delete ----------
   Saving reloads the page; remember where we were and scroll back there. */
const scrollKey = `scroll:${location.pathname}`;
document.addEventListener('submit', (e) => {
    if (!e.target.matches('form[data-dialog-form], #confirm-delete-form')) return;
    try {
        sessionStorage.setItem(scrollKey, String(window.scrollY));
    } catch {
        // private mode: the page opens at the top as before
    }
});
try {
    const savedScroll = sessionStorage.getItem(scrollKey);
    if (savedScroll !== null) {
        sessionStorage.removeItem(scrollKey);
        window.addEventListener('load', () => window.scrollTo(0, Number(savedScroll)));
    }
} catch {
    // nothing saved
}

/* ---------- Profile photo: preview before saving ----------
   Choosing a picture shows it in the circle at once, with Save / Cancel.
   Too big or wrong type is caught here, before anything is uploaded. */
document.querySelectorAll('form[data-photo-form]').forEach((form) => {
    const input = form.querySelector('[data-photo-input]');
    const img = form.querySelector('[data-photo-preview]');
    const initials = form.querySelector('[data-photo-initials]');
    const actions = form.querySelector('[data-photo-actions]');
    const error = form.querySelector('[data-photo-error]');
    const original = { src: img.getAttribute('src'), hadPhoto: !img.classList.contains('hidden') };
    let previewUrl = null;

    const show = (src) => {
        img.classList.toggle('hidden', !src);
        initials.classList.toggle('hidden', !!src);
        if (src) img.src = src;
    };
    const reset = () => {
        input.value = '';
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = null;
        show(original.hadPhoto ? original.src : null);
        actions.classList.replace('flex', 'hidden');
    };

    input.addEventListener('change', () => {
        const file = input.files[0];
        error.classList.add('hidden');
        if (!file) return reset();
        const problem = !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ? error.dataset.wrongType
            : file.size > 2 * 1024 * 1024 ? error.dataset.tooBig : null;
        if (problem) {
            reset();
            error.textContent = problem;
            error.classList.remove('hidden');
            return;
        }
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);
        show(previewUrl);
        actions.classList.replace('hidden', 'flex');
    });
    form.querySelector('[data-photo-cancel]').addEventListener('click', reset);
    form.addEventListener('submit', () => form.querySelector('button:not([type])')?.classList.add('btn-disabled'));
});

/* ---------- Side menu on computer screens: slim icon bar + menu panel ----------
   Pointing at an icon shows that group's links. Unpinned, the panel slides out while
   the mouse is on the icon bar or the panel, and closes 460 ms after it leaves.
   The pin choice is remembered in the browser (read in <head>, so nothing jumps). */
(function sideMenu() {
    const root = document.documentElement;
    const sidebar = document.getElementById('sidebar');
    const shell = sidebar?.querySelector('.side-shell');
    if (!shell || !document.getElementById('side-rail')) return;
    const currentGroup = sidebar.dataset.currentGroup;
    let closeTimer;

    const showGroup = (group) => {
        sidebar.querySelectorAll('.side-group, .rail-item').forEach((el) => {
            el.classList.toggle('is-shown', el.dataset.group === group);
        });
    };
    const open = () => {
        clearTimeout(closeTimer);
        sidebar.classList.add('peek');
    };
    const closeSoon = () => {
        clearTimeout(closeTimer);
        closeTimer = setTimeout(() => {
            sidebar.classList.remove('peek');
            showGroup(currentGroup);
        }, 460);
    };

    sidebar.querySelectorAll('.rail-item').forEach((item) => {
        item.addEventListener('mouseenter', () => showGroup(item.dataset.group));
        item.addEventListener('focus', () => showGroup(item.dataset.group));
    });
    shell.addEventListener('mouseenter', open);
    shell.addEventListener('mouseleave', closeSoon);
    shell.addEventListener('focusout', (e) => {
        if (!shell.contains(e.relatedTarget)) showGroup(currentGroup);
    });

    const drawPinButton = () => {
        const unpinned = root.dataset.menu === 'unpinned';
        document.querySelectorAll('[data-action="pin-menu"]').forEach((btn) => {
            const label = unpinned ? btn.dataset.labelPin : btn.dataset.labelUnpin;
            btn.setAttribute('aria-label', label);
            btn.title = label;
            btn.setAttribute('aria-pressed', String(!unpinned));
        });
    };

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="pin-menu"]');
        if (!btn) return;
        const unpin = root.dataset.menu !== 'unpinned';
        if (unpin) root.dataset.menu = 'unpinned';
        else delete root.dataset.menu;
        sidebar.classList.remove('peek');
        showGroup(currentGroup);
        // A mouse click leaves focus on the button; drop it so the menu can close at once
        btn.blur();
        try {
            localStorage.setItem('menu', unpin ? 'unpinned' : 'pinned');
        } catch {
            // private mode: the choice lasts only for this page
        }
        drawPinButton();
    });

    drawPinButton();
})();
