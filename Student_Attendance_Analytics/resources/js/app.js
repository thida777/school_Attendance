import ApexCharts from 'apexcharts';
import { createIcons, icons } from 'lucide';
import { initAttendance } from './attendance';

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
        else el.value = values[el.name] ?? (el.tagName === 'SELECT' ? el.options[0]?.value ?? '' : '');
    }
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
    document.getElementById('confirm-delete').showModal();
});

/* ---------- Toast messages disappear after a few seconds ---------- */
setTimeout(() => document.querySelectorAll('[data-toast]').forEach((t) => t.remove()), 4000);

/* ---------- Filters: changing a dropdown runs the search right away ---------- */
document.addEventListener('change', (e) => {
    if (e.target.matches('form[data-auto-submit] select')) e.target.form.submit();
});

/* ---------- Chart helper (ApexCharts) ----------
   makeChart('#id', (c) => options) draws a chart and redraws it when the theme changes.
   "c" holds the colours for the current theme. */
function chartColors() {
    const dark = currentTheme() === 'dark';
    return {
        dark,
        series: dark ? '#3fb8a9' : '#1f877b',
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
};

drawThemeButtons();

/* ---------- Take Attendance page ---------- */
const attendanceForm = document.querySelector('form[data-attendance]');
if (attendanceForm) initAttendance(attendanceForm, drawIcons);
