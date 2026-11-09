/* Take Attendance, step 2 and 3 (only runs on the marking page).
   Each student card holds a hidden input statuses[id] = present / absent.
   Tapping a card switches it; the counts, ring, filter and review pop-up follow. */
export function initAttendance(form, drawIcons) {
    const text = window.attendanceText;
    const tiles = [...form.querySelectorAll('[data-tile]')];
    if (!tiles.length) return;

    const input = (tile) => tile.querySelector('input[type=hidden]');
    const isAbsent = (tile) => input(tile).value === 'absent';
    const dialog = form.querySelector('[data-review-dialog]');
    const steps = document.querySelectorAll('#steps .step');
    let filter = 'all';
    let lastChange = null;
    let undoTimer;

    function drawTile(tile) {
        const absent = isAbsent(tile);
        tile.classList.toggle('border-error', absent);
        tile.classList.toggle('bg-error/10', absent);
        tile.classList.toggle('att-tile', !absent);
        tile.classList.toggle('border-transparent', !absent);
        tile.classList.toggle('bg-base-200/60', !absent);
        tile.setAttribute('aria-pressed', absent);
        tile.setAttribute('aria-label', `${tile.dataset.name}: ${absent ? text.absent : text.present}`);

        const badge = tile.querySelector('[data-badge]');
        badge.className = `absolute end-2 top-2 grid size-6 place-items-center rounded-full ${absent ? 'bg-error text-error-content' : 'bg-success text-success-content'}`;
        badge.innerHTML = `<i data-lucide="${absent ? 'x' : 'check'}" class="size-3.5"></i>`;
        tile.querySelector('[data-avatar]').classList.toggle('grayscale', absent);
        tile.querySelector('[data-avatar]').classList.toggle('opacity-60', absent);

        const label = tile.querySelector('[data-label]');
        label.textContent = absent ? text.absent : text.present;
        label.className = `text-xs font-semibold ${absent ? 'text-error' : 'text-success'}`;
    }

    function draw() {
        const q = form.querySelector('[data-search]').value.trim().toLowerCase();
        let visible = 0;
        tiles.forEach((tile) => {
            const show = (filter === 'all' || input(tile).value === filter) && (!q || tile.dataset.searchText.includes(q));
            tile.classList.toggle('hidden', !show);
            if (show) visible++;
        });
        const empty = form.querySelector('[data-empty]');
        empty.classList.toggle('hidden', visible > 0);
        empty.classList.toggle('flex', visible === 0);

        const absent = tiles.filter(isAbsent).length;
        const present = tiles.length - absent;
        const rate = Math.round((100 * present) / tiles.length);
        form.querySelectorAll('[data-count="present"]').forEach((el) => (el.textContent = present));
        form.querySelectorAll('[data-count="absent"]').forEach((el) => (el.textContent = absent));
        form.querySelectorAll('[data-count="all"]').forEach((el) => (el.textContent = tiles.length));

        const ring = form.querySelector('[data-ring]');
        ring.style.setProperty('--value', rate);
        ring.setAttribute('aria-valuenow', rate);
        ring.textContent = `${rate}%`;
        ring.classList.toggle('text-success', rate >= 80);
        ring.classList.toggle('text-warning', rate < 80);

        form.querySelector('[data-bar]').innerHTML = absent === 0
            ? `<span class="text-success font-semibold">✓ ${text.allPresent}</span>`
            : `<span class="font-semibold">${present}</span> ${text.presentLower} · <span class="text-error font-semibold">${absent}</span> ${text.absentLower}`;

        // Review pop-up: a reason row only for absent students
        form.querySelectorAll('[data-reason-row]').forEach((row) => {
            const tile = tiles.find((t) => t.dataset.tile === row.dataset.reasonRow);
            row.classList.toggle('hidden', !isAbsent(tile));
        });
        form.querySelector('[data-reasons]').classList.toggle('hidden', absent === 0);
        drawIcons();
    }

    function showUndo(message) {
        const toast = document.querySelector('[data-undo]');
        toast.querySelector('[data-undo-text]').textContent = message;
        toast.classList.remove('hidden');
        clearTimeout(undoTimer);
        undoTimer = setTimeout(() => toast.classList.add('hidden'), 4000);
    }

    // Tap a card: Present ↔ Absent
    form.addEventListener('click', (e) => {
        const tile = e.target.closest('[data-tile]');
        if (!tile) return;
        lastChange = { tile, from: input(tile).value };
        input(tile).value = isAbsent(tile) ? 'present' : 'absent';
        drawTile(tile);
        draw();
        showUndo(`${tile.dataset.name} → ${isAbsent(tile) ? text.absent : text.present}`);
    });

    document.querySelector('[data-undo-button]').addEventListener('click', () => {
        if (!lastChange) return;
        input(lastChange.tile).value = lastChange.from;
        drawTile(lastChange.tile);
        lastChange = null;
        document.querySelector('[data-undo]').classList.add('hidden');
        draw();
    });

    form.querySelectorAll('[data-filter]').forEach((tab) => tab.addEventListener('click', () => {
        filter = tab.dataset.filter;
        form.querySelectorAll('[data-filter]').forEach((t) => t.classList.toggle('tab-active', t === tab));
        draw();
    }));
    form.querySelector('[data-search]').addEventListener('input', draw);
    // Enter in the search box must not submit the attendance
    form.querySelector('[data-search]').addEventListener('keydown', (e) => e.key === 'Enter' && e.preventDefault());

    form.querySelector('[data-all-present]').addEventListener('click', () => {
        tiles.forEach((tile) => {
            input(tile).value = 'present';
            drawTile(tile);
        });
        draw();
    });

    // Step 3: review pop-up
    form.querySelector('[data-review]').addEventListener('click', () => {
        steps[2]?.classList.add('step-primary');
        dialog.showModal();
    });
    dialog.querySelectorAll('[data-close]').forEach((b) => b.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => steps[2]?.classList.remove('step-primary'));
    if (dialog.open) steps[2]?.classList.add('step-primary');

    // Only one submit, even with a double tap
    form.addEventListener('submit', () => form.querySelectorAll('button:not([type=button])').forEach((b) => (b.disabled = true)));

    tiles.forEach(drawTile);
    draw();
}
