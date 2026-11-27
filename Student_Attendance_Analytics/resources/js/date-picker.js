/* ---------- Modern date picker ----------
   Any <input type="date" data-date-picker> becomes a button + a small calendar pop-up.
   The real input stays in the form (as hidden), so the server receives the same "YYYY-MM-DD" value.
   Options on the input:
     max="2026-11-15"        days after it are greyed out
     data-submit             send the form as soon as a day is picked (filters)
     data-clearable          show a "Clear" button (empty = all dates)
     data-placeholder="…"    text when no date is chosen
   If JavaScript fails, the normal browser date box still works. */

const TEXT = {
    en: {
        months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        short: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        days: ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
        weekdays: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
        today: 'Today', yesterday: 'Yesterday', clear: 'Clear', pick: 'Pick a date', prev: 'Previous month', next: 'Next month',
    },
    km: {
        months: ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'],
        short: ['មករា', 'កុម្ភៈ', 'មីនា', 'មេសា', 'ឧសភា', 'មិថុនា', 'កក្កដា', 'សីហា', 'កញ្ញា', 'តុលា', 'វិច្ឆិកា', 'ធ្នូ'],
        days: ['ច', 'អ', 'ពុ', 'ព្រ', 'សុ', 'ស', 'អា'],
        weekdays: ['អាទិត្យ', 'ច័ន្ទ', 'អង្គារ', 'ពុធ', 'ព្រហស្បតិ៍', 'សុក្រ', 'សៅរ៍'],
        today: 'ថ្ងៃនេះ', yesterday: 'ម្សិលមិញ', clear: 'សម្អាត', pick: 'ជ្រើសរើសកាលបរិច្ឆេទ', prev: 'ខែមុន', next: 'ខែបន្ទាប់',
    },
};

const pad = (n) => String(n).padStart(2, '0');
const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const fromIso = (s) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
    return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null;
};

let openPicker = null; // only one calendar open at a time

export function initDatePickers(drawIcons, root = document) {
    root.querySelectorAll('input[type="date"][data-date-picker]').forEach((input) => build(input, drawIcons));
}

function build(input, drawIcons) {
    const t = TEXT[document.documentElement.lang === 'km' ? 'km' : 'en'];
    const max = fromIso(input.max);
    const submit = input.hasAttribute('data-submit');
    const clearable = input.hasAttribute('data-clearable');
    const placeholder = input.dataset.placeholder || t.pick;
    const hasError = input.classList.contains('input-error');

    // The real input becomes hidden; a button takes its place
    input.type = 'hidden';
    input.removeAttribute('onchange');

    const wrap = document.createElement('div');
    wrap.className = 'relative w-full';
    input.replaceWith(wrap);
    wrap.append(input);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = `input w-full cursor-pointer justify-start gap-2 text-start${hasError ? ' input-error' : ''}`;
    button.setAttribute('aria-haspopup', 'dialog');
    button.setAttribute('aria-expanded', 'false');
    button.innerHTML = `<i data-lucide="calendar-days" class="size-4 text-primary"></i>
        <span class="flex-1 truncate text-start" data-label></span>
        <i data-lucide="chevron-down" class="size-4 opacity-50"></i>`;
    wrap.append(button);

    const pop = document.createElement('div');
    pop.className = 'date-pop bg-base-100 border-base-300 rounded-box fixed z-50 hidden w-72 border p-3 shadow-xl';
    pop.setAttribute('role', 'dialog');
    // Inside a pop-up form (<dialog>) the calendar must live in the dialog too:
    // an open dialog is drawn above the whole page, so a calendar in <body> would be hidden behind it.
    (input.closest('dialog') || document.body).append(pop);

    let view = fromIso(input.value) || max || new Date(); // the month on screen
    view = new Date(view.getFullYear(), view.getMonth(), 1);

    const label = () => {
        const d = fromIso(input.value);
        const span = button.querySelector('[data-label]') || button;
        span.textContent = d ? `${t.weekdays[d.getDay()]}, ${d.getDate()} ${t.short[d.getMonth()]} ${d.getFullYear()}` : placeholder;
        span.classList.toggle('opacity-50', !d);
        if (clearable) button.classList.toggle('picker-on', !!d); // a date filter is on
    };

    const pick = (iso) => {
        const changed = input.value !== iso;
        input.value = iso;
        label();
        close();
        button.focus();
        if (!changed) return;
        input.dispatchEvent(new Event('change', { bubbles: true }));
        if (submit && input.form) input.form.requestSubmit();
    };

    const draw = () => {
        const today = new Date();
        const selected = input.value;
        const y = view.getFullYear();
        const m = view.getMonth();
        const offset = (new Date(y, m, 1).getDay() + 6) % 7; // Monday first
        const daysInMonth = new Date(y, m + 1, 0).getDate();
        const nextDisabled = max && new Date(y, m + 1, 1) > max;

        let cells = '';
        for (let i = 0; i < offset; i++) cells += '<span></span>';
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(y, m, day);
            const iso = toIso(date);
            const off = max && date > max;
            const classes = ['date-day'];
            if (iso === selected) classes.push('is-selected');
            if (iso === toIso(today)) classes.push('is-today');
            if (date.getDay() === 0) classes.push('is-sunday');
            cells += `<button type="button" class="${classes.join(' ')}" data-day="${iso}" ${off ? 'disabled' : ''}
                aria-label="${t.weekdays[date.getDay()]} ${day} ${t.months[m]} ${y}" ${iso === selected ? 'aria-pressed="true"' : ''}>${day}</button>`;
        }

        const yesterday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
        const chip = (iso, text) => (max && fromIso(iso) > max ? '' : `<button type="button" class="btn btn-xs rounded-full" data-day="${iso}">${text}</button>`);

        pop.innerHTML = `
            <div class="mb-2 flex items-center justify-between">
                <button type="button" class="btn btn-ghost btn-sm btn-square" data-move="-1" aria-label="${t.prev}"><i data-lucide="chevron-left" class="size-4"></i></button>
                <div class="font-semibold">${t.months[m]} ${y}</div>
                <button type="button" class="btn btn-ghost btn-sm btn-square" data-move="1" aria-label="${t.next}" ${nextDisabled ? 'disabled' : ''}><i data-lucide="chevron-right" class="size-4"></i></button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-xs opacity-60">${t.days.map((d) => `<span class="py-1">${d}</span>`).join('')}</div>
            <div class="grid grid-cols-7 gap-1">${cells}</div>
            <div class="border-base-300 mt-3 flex items-center gap-2 border-t pt-3">
                ${chip(toIso(today), t.today)}
                ${chip(toIso(yesterday), t.yesterday)}
                ${clearable && input.value ? `<button type="button" class="btn btn-ghost btn-xs ms-auto rounded-full" data-clear><i data-lucide="x" class="size-3"></i>${t.clear}</button>` : ''}
            </div>`;
        drawIcons();
    };

    const place = () => {
        const r = button.getBoundingClientRect();
        const h = pop.offsetHeight;
        const below = window.innerHeight - r.bottom;
        let top = below < h + 12 && r.top > h + 12 ? r.top - h - 8 : r.bottom + 8;
        // On a short screen it fits neither below nor above: keep it whole on the screen
        top = Math.max(8, Math.min(top, window.innerHeight - h - 8));
        const left = Math.min(Math.max(8, r.left), window.innerWidth - pop.offsetWidth - 8);
        pop.style.top = `${top}px`;
        pop.style.left = `${left}px`;
    };

    const open = () => {
        if (openPicker) openPicker();
        const d = fromIso(input.value) || max || new Date();
        view = new Date(d.getFullYear(), d.getMonth(), 1);
        draw();
        pop.classList.remove('hidden');
        place();
        button.setAttribute('aria-expanded', 'true');
        openPicker = close;
        (pop.querySelector('.is-selected') || pop.querySelector('.is-today:not([disabled])') || pop.querySelector('.date-day:not([disabled])'))?.focus();
    };

    function close() {
        pop.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
        if (openPicker === close) openPicker = null;
    }

    button.addEventListener('click', () => (pop.classList.contains('hidden') ? open() : close()));

    pop.addEventListener('click', (e) => {
        const move = e.target.closest('[data-move]');
        if (move) {
            view = new Date(view.getFullYear(), view.getMonth() + Number(move.dataset.move), 1);
            draw();
            pop.querySelector(`[data-move="${move.dataset.move}"]:not([disabled])`)?.focus();
            return;
        }
        const day = e.target.closest('[data-day]');
        if (day && !day.disabled) return pick(day.dataset.day);
        if (e.target.closest('[data-clear]')) pick('');
    });

    // Keyboard: arrows move a day / a week, Esc closes
    pop.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            close();
            button.focus();
            return;
        }
        const step = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 }[e.key];
        const current = e.target.closest('.date-day');
        if (!step || !current) return;
        e.preventDefault();
        const d = fromIso(current.dataset.day);
        d.setDate(d.getDate() + step);
        if (max && d > max) return;
        if (d.getMonth() !== view.getMonth()) {
            view = new Date(d.getFullYear(), d.getMonth(), 1);
            draw();
        }
        pop.querySelector(`[data-day="${toIso(d)}"].date-day`)?.focus();
    });

    document.addEventListener('pointerdown', (e) => {
        if (!pop.classList.contains('hidden') && !pop.contains(e.target) && !button.contains(e.target)) close();
    });
    window.addEventListener('resize', close);
    window.addEventListener('scroll', () => !pop.classList.contains('hidden') && place(), true);

    label();
    drawIcons();
}
