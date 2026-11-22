/* ---------- Modern dropdown ----------
   Any <select data-picker> becomes a button + a styled list, matching the date picker.
   The real <select> stays in the form (hidden) and keeps the chosen value, so nothing changes on the server.
   Options on the select:
     data-icon="school"       lucide icon on the left of the box
     data-filter              a filter: the box is highlighted while something other than "All …" is chosen
   Options on each <option>:
     data-hint="07:00 – 08:00" small grey text on the right of that row
     data-dot="bg-success"     a small coloured dot before the text (e.g. Present / Absent)
   Keyboard: ↑ ↓ move, Enter picks, Esc closes, typing a letter jumps to it.
   If JavaScript fails, the normal browser dropdown still works. */

let openPicker = null; // only one list open at a time

export function initSelectPickers(drawIcons, root = document) {
    root.querySelectorAll('select[data-picker]:not([multiple])').forEach((select) => build(select, drawIcons));
}

const escape = (s) => s.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

function build(select, drawIcons) {
    const wrap = document.createElement('div');
    // Keep the width the <select> asked for: "w-auto" stays in a row with other filters, otherwise full width
    wrap.className = select.classList.contains('w-auto') ? 'relative w-auto min-w-48' : 'relative w-full';
    select.replaceWith(wrap);
    select.classList.add('hidden');
    select.tabIndex = -1;
    wrap.append(select);

    const icon = select.dataset.icon;
    const button = document.createElement('button');
    button.type = 'button';
    button.className = `input w-full cursor-pointer justify-start gap-2${select.classList.contains('select-error') ? ' input-error' : ''}`;
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');
    if (select.getAttribute('aria-label')) button.setAttribute('aria-label', select.getAttribute('aria-label'));
    if (select.id) {
        // <label for="…"> and the fieldset legend still point at the box
        button.id = `${select.id}-picker`;
        document.querySelectorAll(`label[for="${select.id}"]`).forEach((l) => (l.htmlFor = button.id));
    }
    button.innerHTML = `${icon ? `<i data-lucide="${icon}" class="size-4 shrink-0 text-primary"></i>` : ''}
        <span class="hidden size-2 shrink-0 rounded-full" data-dot></span>
        <span class="flex-1 truncate text-start" data-label></span>
        <span class="text-xs opacity-50" data-hint></span>
        <i data-lucide="chevron-down" class="size-4 shrink-0 opacity-50 transition-transform" data-chevron></i>`;
    wrap.append(button);

    const pop = document.createElement('ul');
    pop.className = 'pick-pop bg-base-100 border-base-300 rounded-box fixed z-50 hidden max-h-72 overflow-y-auto border p-1.5 shadow-xl';
    pop.setAttribute('role', 'listbox');
    document.body.append(pop);

    const options = () => [...select.options].filter((o) => !o.hidden);

    const label = () => {
        const o = select.selectedOptions[0];
        button.querySelector('[data-label]').textContent = o ? o.text : '';
        button.querySelector('[data-label]').classList.toggle('opacity-50', !o || o.value === '');
        button.querySelector('[data-hint]').textContent = o?.dataset.hint || '';
        const dot = button.querySelector('[data-dot]');
        dot.className = `size-2 shrink-0 rounded-full ${o?.dataset.dot || 'hidden'}`;
        if (select.hasAttribute('data-filter')) button.classList.toggle('picker-on', !!o && o.value !== '');
    };

    const draw = () => {
        pop.innerHTML = options().map((o, i) => `
            <li role="option" tabindex="-1" data-index="${i}" aria-selected="${o.selected}" class="pick-item${o.selected ? ' is-selected' : ''}${o.disabled ? ' is-disabled' : ''}">
                <i data-lucide="check" class="pick-check size-4 shrink-0"></i>
                ${o.dataset.dot ? `<span class="size-2 shrink-0 rounded-full ${escape(o.dataset.dot)}"></span>` : ''}
                <span class="flex-1 truncate">${escape(o.text)}</span>
                ${o.dataset.hint ? `<span class="text-xs opacity-60">${escape(o.dataset.hint)}</span>` : ''}
            </li>`).join('');
        drawIcons();
    };

    const place = () => {
        const r = button.getBoundingClientRect();
        pop.style.minWidth = `${r.width}px`;
        const h = pop.offsetHeight;
        const top = window.innerHeight - r.bottom < h + 12 && r.top > h + 12 ? r.top - h - 6 : r.bottom + 6;
        pop.style.top = `${top}px`;
        pop.style.left = `${Math.min(Math.max(8, r.left), window.innerWidth - pop.offsetWidth - 8)}px`;
    };

    const items = () => [...pop.querySelectorAll('.pick-item:not(.is-disabled)')];
    const focusItem = (el) => {
        if (!el) return;
        el.focus();
        el.scrollIntoView({ block: 'nearest' });
    };

    const open = () => {
        if (openPicker) openPicker();
        draw();
        pop.classList.remove('hidden');
        place();
        button.setAttribute('aria-expanded', 'true');
        button.querySelector('[data-chevron]').classList.add('rotate-180');
        openPicker = close;
        focusItem(pop.querySelector('.is-selected') || items()[0]);
    };

    function close() {
        pop.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
        button.querySelector('[data-chevron]')?.classList.remove('rotate-180');
        if (openPicker === close) openPicker = null;
    }

    const pick = (index) => {
        const o = options()[index];
        if (!o || o.disabled) return;
        const changed = !o.selected;
        o.selected = true;
        label();
        close();
        button.focus();
        if (changed) select.dispatchEvent(new Event('change', { bubbles: true }));
    };

    button.addEventListener('click', () => (pop.classList.contains('hidden') ? open() : close()));
    button.addEventListener('keydown', (e) => {
        if (['ArrowDown', 'ArrowUp'].includes(e.key)) {
            e.preventDefault();
            open();
        }
    });

    pop.addEventListener('click', (e) => {
        const li = e.target.closest('.pick-item');
        if (li) pick(Number(li.dataset.index));
    });

    let typed = '';
    let typedTimer;
    pop.addEventListener('keydown', (e) => {
        const list = items();
        const at = list.indexOf(document.activeElement);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            focusItem(list[Math.min(list.length - 1, Math.max(0, at + (e.key === 'ArrowDown' ? 1 : -1)))]);
        } else if (e.key === 'Home' || e.key === 'End') {
            e.preventDefault();
            focusItem(e.key === 'Home' ? list[0] : list.at(-1));
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (at >= 0) pick(Number(list[at].dataset.index));
        } else if (e.key === 'Escape' || e.key === 'Tab') {
            if (e.key === 'Escape') e.preventDefault();
            close();
            button.focus();
        } else if (e.key.length === 1) {
            // Type "1" or "Ma" to jump to the first row that starts with it
            typed += e.key.toLowerCase();
            clearTimeout(typedTimer);
            typedTimer = setTimeout(() => (typed = ''), 600);
            focusItem(list.find((li) => li.textContent.trim().toLowerCase().startsWith(typed)));
        }
    });

    document.addEventListener('pointerdown', (e) => {
        if (!pop.classList.contains('hidden') && !pop.contains(e.target) && !button.contains(e.target)) close();
    });
    window.addEventListener('resize', close);
    window.addEventListener('scroll', (e) => !pop.classList.contains('hidden') && !pop.contains(e.target) && place(), true);

    // Another script may change the select (e.g. form reset): keep the box in step
    select.addEventListener('change', label);
    select.form?.addEventListener('reset', () => setTimeout(label));

    label();
    drawIcons();
}
