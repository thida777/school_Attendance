/* Shared layout for every page: sidebar, top bar, theme switch, language switch, chart helper.
   In Laravel this file becomes the Blade layout (layouts/app.blade.php). */

/* ---------- Who is logged in ----------
   Two roles: Admin (the whole school) and Teacher (only their own classes).
   The login page remembers the chosen role; Laravel will use the real session instead. */
const ROLE = (() => {
  try { return localStorage.getItem('role') === 'teacher' ? 'teacher' : 'admin'; } catch { return 'admin'; }
})();
const USER = ROLE === 'teacher'
  ? { name: 'Pen Sovann', nameKm: 'ប៉ែន សុវណ្ណ', initials: 'PS', email: 'sovann@school.edu.kh', phone: '012 345 678', role: 'Teacher', roleKm: 'គ្រូបង្រៀន', home: 'teacher-dashboard.html' }
  : { name: 'Sok Admin', nameKm: 'សុខ អេតមីន', initials: 'SA', email: 'admin@school.edu.kh', phone: '012 000 111', role: 'Administrator', roleKm: 'អ្នកគ្រប់គ្រង', home: 'index.html' };

// Pages a teacher may open; every other page is for admins only
const TEACHER_PAGES = ['teacher-dashboard', 'attendance', 'monitoring', 'my-classes', 'student-details', 'profile'];
const TEACHER_ONLY_PAGES = ['teacher-dashboard', 'my-classes'];
const thisPage = document.body.dataset.page;
if (!['login', 'forgot-password'].includes(thisPage)) {
  if (ROLE === 'teacher' && !TEACHER_PAGES.includes(thisPage)) location.replace('teacher-dashboard.html');
  if (ROLE === 'admin' && TEACHER_ONLY_PAGES.includes(thisPage)) location.replace('index.html');
}

const TEACHER_NAV = [
  {
    group: { en: 'Overview', km: 'ទិដ្ឋភាពទូទៅ' },
    items: [
      { id: 'teacher-dashboard', href: 'teacher-dashboard.html', icon: 'layout-dashboard', en: 'My Dashboard', km: 'ផ្ទាំងគ្រប់គ្រងរបស់ខ្ញុំ' },
    ],
  },
  {
    group: { en: 'Attendance', km: 'វត្តមាន' },
    items: [
      { id: 'attendance', href: 'attendance.html', icon: 'clipboard-check', en: 'Take Attendance', km: 'ស្រង់វត្តមាន' },
      { id: 'monitoring', href: 'monitoring.html', icon: 'history', en: 'Attendance History', km: 'ប្រវត្តិវត្តមាន' },
    ],
  },
  {
    group: { en: 'My teaching', km: 'ការបង្រៀនរបស់ខ្ញុំ' },
    items: [
      { id: 'my-classes', href: 'my-classes.html', icon: 'school', en: 'My Classes', km: 'ថ្នាក់របស់ខ្ញុំ' },
    ],
  },
  {
    group: { en: 'Account', km: 'គណនី' },
    items: [
      { id: 'profile', href: 'profile.html', icon: 'circle-user', en: 'Profile', km: 'ប្រវត្តិរូប' },
    ],
  },
];

const ADMIN_NAV = [
  {
    group: { en: 'Overview', km: 'ទិដ្ឋភាពទូទៅ' },
    items: [
      { id: 'dashboard', href: 'index.html', icon: 'layout-dashboard', en: 'Dashboard', km: 'ផ្ទាំងគ្រប់គ្រង' },
    ],
  },
  {
    group: { en: 'Attendance', km: 'វត្តមាន' },
    items: [
      { id: 'attendance', href: 'attendance.html', icon: 'clipboard-check', en: 'Take Attendance', km: 'ស្រង់វត្តមាន' },
      { id: 'monitoring', href: 'monitoring.html', icon: 'list-checks', en: 'Attendance Monitoring', km: 'តាមដានវត្តមាន' },
      { id: 'corrections', href: 'corrections.html', icon: 'file-pen-line', en: 'Corrections & Audit Log', km: 'ការកែតម្រូវ និងកំណត់ហេតុ' },
    ],
  },
  {
    group: { en: 'Analytics', km: 'ការវិភាគ' },
    items: [
      { id: 'insights', href: 'insights.html', icon: 'lightbulb', en: 'Insights', km: 'ការយល់ដឹង' },
      { id: 'at-risk', href: 'at-risk.html', icon: 'triangle-alert', en: 'At-Risk Students', km: 'សិស្សមានហានិភ័យ' },
      { id: 'interventions', href: 'interventions.html', icon: 'hand-heart', en: 'Interventions', km: 'អន្តរាគមន៍' },
      { id: 'reports', href: 'reports.html', icon: 'chart-column', en: 'Reports', km: 'របាយការណ៍' },
    ],
  },
  {
    group: { en: 'School data', km: 'ទិន្នន័យសាលា' },
    items: [
      { id: 'students', href: 'students.html', icon: 'users', en: 'Students', km: 'សិស្ស' },
      { id: 'classes', href: 'classes.html', icon: 'school', en: 'Classes', km: 'ថ្នាក់រៀន' },
      { id: 'subjects', href: 'subjects.html', icon: 'book-open', en: 'Subjects', km: 'មុខវិជ្ជា' },
      { id: 'teachers', href: 'teachers.html', icon: 'graduation-cap', en: 'Teachers', km: 'គ្រូបង្រៀន' },
      { id: 'parents', href: 'parents.html', icon: 'contact', en: 'Parents', km: 'អាណាព្យាបាល' },
      { id: 'assignments', href: 'assignments.html', icon: 'calendar-check', en: 'Teaching Assignments', km: 'ការចាត់តាំងបង្រៀន' },
    ],
  },
  {
    group: { en: 'Account', km: 'គណនី' },
    items: [
      { id: 'profile', href: 'profile.html', icon: 'circle-user', en: 'Profile', km: 'ប្រវត្តិរូប' },
    ],
  },
];
const NAV = ROLE === 'teacher' ? TEACHER_NAV : ADMIN_NAV;

/* Detail pages (e.g. student-details.html) set data-parent="students" so the sidebar keeps
   "Students" highlighted, and data-title / data-title-km for their own top-bar title. */
const page = document.body.dataset.parent || document.body.dataset.page;
const navItem =
  NAV.flatMap((g) => g.items).find((i) => i.id === page) ||
  (document.body.dataset.title ? { id: page } : undefined); // e.g. a teacher opening student details

// Buttons only admins may use (e.g. editing a student) are removed for teachers
if (ROLE === 'teacher') document.querySelectorAll('[data-admin-only]').forEach((el) => el.remove());
const currentItem =
  navItem && document.body.dataset.title
    ? { ...navItem, en: document.body.dataset.title, km: document.body.dataset.titleKm || document.body.dataset.title }
    : navItem;

/* ---------- Sidebar ---------- */
const sidebar = document.getElementById('sidebar');
if (sidebar) {
  const groups = NAV.map(
    (g) => `
      <li class="menu-title" data-km="${g.group.km}">${g.group.en}</li>
      ${g.items
        .map(
          (i) => `
        <li>
          <a href="${i.href}" class="${i.id === page ? 'menu-active' : ''}">
            <i data-lucide="${i.icon}" class="size-4"></i>
            <span data-km="${i.km}">${i.en}</span>
          </a>
        </li>`
        )
        .join('')}`
  ).join('');

  sidebar.innerHTML = `
    <label for="sidebar-toggle" aria-label="close sidebar" class="drawer-overlay"></label>
    <aside class="bg-base-100 border-base-300 flex min-h-full w-64 flex-col border-r">
      <a href="${USER.home}" class="flex items-center gap-3 px-5 py-4">
        <span class="brand-mark text-primary-content grid size-10 place-items-center rounded-2xl">
          <i data-lucide="clipboard-check" class="size-5"></i>
        </span>
        <span>
          <span class="block leading-tight font-semibold">Attendance</span>
          <span class="block text-xs opacity-60">Demo High School</span>
        </span>
      </a>
      <ul class="menu w-full grow gap-1 px-3">${groups}</ul>
      <p class="px-5 py-4 text-xs opacity-50">Academic year 2025–2026</p>
    </aside>`;
}

/* ---------- Top bar ---------- */
const topbar = document.getElementById('topbar');
if (topbar && currentItem) {
  topbar.innerHTML = `
    <div class="navbar bg-base-100 border-base-300 border-b px-4">
      <div class="navbar-start gap-2">
        <label for="sidebar-toggle" class="btn btn-ghost btn-square drawer-button lg:hidden" aria-label="open sidebar">
          <i data-lucide="menu" class="size-5"></i>
        </label>
        <h1 class="text-lg font-semibold" data-km="${currentItem.km}">${currentItem.en}</h1>
      </div>
      <div class="navbar-end gap-1">
        <button class="btn btn-ghost btn-sm" data-action="lang"></button>
        <button class="btn btn-ghost btn-sm btn-square" data-action="theme" aria-label="switch theme"></button>
        <details class="dropdown dropdown-end">
          <summary class="btn btn-ghost gap-2 px-2">
            <div class="avatar avatar-placeholder">
              <div class="bg-neutral text-neutral-content w-8 rounded-full"><span class="text-xs">${USER.initials}</span></div>
            </div>
            <span class="hidden text-left text-sm leading-tight sm:inline">${USER.name}<br /><span class="text-xs font-normal opacity-60" data-km="${USER.roleKm}">${USER.role}</span></span>
          </summary>
          <ul class="menu dropdown-content bg-base-100 rounded-box border-base-300 z-10 mt-2 w-48 border p-2 shadow-sm">
            <li><a href="profile.html"><i data-lucide="circle-user" class="size-4"></i><span data-km="ប្រវត្តិរូប">Profile</span></a></li>
            <li><a href="login.html"><i data-lucide="log-out" class="size-4"></i><span data-km="ចាកចេញ">Log out</span></a></li>
          </ul>
        </details>
      </div>
    </div>`;
}

/* Any element with data-user="name|nameKm|initials|email|phone|role" shows the logged-in user's value */
document.querySelectorAll('[data-user]').forEach((el) => {
  const key = el.dataset.user;
  if (el.tagName === 'INPUT') el.value = USER[key];
  else el.textContent = USER[key];
  if (key === 'role') el.dataset.km = USER.roleKm;
});

/* ---------- Theme switch (light / dark), remembered in the browser ---------- */
function currentTheme() {
  return document.documentElement.dataset.theme || 'light';
}
function drawThemeButtons() {
  document.querySelectorAll('[data-action="theme"]').forEach((btn) => {
    btn.innerHTML = `<i data-lucide="${currentTheme() === 'dark' ? 'sun' : 'moon'}" class="size-4"></i>`;
  });
  lucide.createIcons();
}
document.addEventListener('click', (e) => {
  if (!e.target.closest('[data-action="theme"]')) return;
  const next = currentTheme() === 'dark' ? 'light' : 'dark';
  document.documentElement.dataset.theme = next;
  localStorage.setItem('theme', next);
  drawThemeButtons();
  document.dispatchEvent(new Event('themechange'));
});

/* ---------- Language switch (English / Khmer) ----------
   1. An element with data-km="..." shows that Khmer text.
   2. Any other text, placeholder or chart label that matches the KM dictionary (assets/km.js)
      is swapped too. Content drawn later by JavaScript is translated as soon as it appears. */
const kmFor = (text) => {
  if (typeof KM === 'undefined') return null;
  if (KM[text]) return KM[text];
  for (const [pattern, replacement] of KM_PATTERNS) if (pattern.test(text)) return text.replace(pattern, replacement);
  return null;
};
// The English text of an element, even while it is showing Khmer
function enText(el) {
  const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
  let text = '';
  for (let n; (n = walker.nextNode()); ) text += n._en ?? n.nodeValue;
  return text.replace(/\s+/g, ' ').trim();
}
function translateText(node, lang) {
  const parent = node.parentElement;
  if (!parent || parent.closest('script, style, [data-km], [data-action="lang"], [data-no-km]')) return;
  if (lang === 'en') {
    if (node._en !== undefined) node.nodeValue = node._en;
    return;
  }
  const en = node._en ?? node.nodeValue;
  const key = en.trim();
  const km = key && kmFor(key);
  if (!km) return;
  if (node._en === undefined) {
    node._en = en;
    // keep the English value on <option>, so filters still compare in English
    const option = parent.closest('option');
    if (option && !option.hasAttribute('value')) option.value = key;
  }
  node.nodeValue = en.replace(key, km);
}
function translateTree(root, lang) {
  if (root.nodeType === Node.TEXT_NODE) return translateText(root, lang);
  if (root.nodeType !== Node.ELEMENT_NODE) return;
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  for (let n; (n = walker.nextNode()); ) translateText(n, lang);
  const withKm = [...root.querySelectorAll('[data-km]')];
  if (root.matches('[data-km]')) withKm.push(root);
  withKm.forEach((el) => {
    if (!el.dataset.en) el.dataset.en = el.textContent;
    el.textContent = lang === 'km' ? el.dataset.km : el.dataset.en;
  });
  const withPlaceholder = [...root.querySelectorAll('[placeholder]')];
  withPlaceholder.forEach((el) => {
    if (!el.dataset.enPlaceholder) el.dataset.enPlaceholder = el.placeholder;
    el.placeholder = (lang === 'km' && kmFor(el.dataset.enPlaceholder)) || el.dataset.enPlaceholder;
  });
}
function applyLang(lang) {
  document.documentElement.lang = lang;
  translateTree(document.body, lang);
  document.querySelectorAll('[data-action="lang"]').forEach((btn) => {
    btn.innerHTML = `<i data-lucide="languages" class="size-4"></i>${lang === 'km' ? 'English' : 'ខ្មែរ'}`;
  });
  lucide.createIcons();
  labelTables();
}
new MutationObserver((mutations) => {
  const lang = document.documentElement.lang;
  for (const m of mutations) {
    m.addedNodes.forEach((node) => {
      if (lang === 'km') translateTree(node, 'km');
      if (node.nodeType === Node.ELEMENT_NODE && node.matches('tr, tbody, table')) labelTables();
    });
  }
}).observe(document.body, { childList: true, subtree: true });
document.addEventListener('click', (e) => {
  if (!e.target.closest('[data-action="lang"]')) return;
  const next = (localStorage.getItem('lang') || 'en') === 'en' ? 'km' : 'en';
  localStorage.setItem('lang', next);
  applyLang(next);
});

/* ---------- Toast: a short message in the corner, e.g. after saving ---------- */
function showToast(message, type = 'success') {
  let box = document.getElementById('toast-box');
  if (!box) {
    box = document.createElement('div');
    box.id = 'toast-box';
    box.className = 'toast toast-end toast-bottom z-50';
    document.body.appendChild(box);
  }
  const alert = document.createElement('div');
  alert.className = `alert alert-${type}`;
  alert.innerHTML = `<i data-lucide="${type === 'success' ? 'circle-check' : 'info'}" class="size-5"></i><span></span>`;
  alert.querySelector('span').textContent = message;
  box.appendChild(alert);
  lucide.createIcons();
  setTimeout(() => alert.remove(), 3000);
}
const isKhmer = () => document.documentElement.lang === 'km';
// Buttons with data-toast="..." show that message (stands in for the real action, e.g. sending an SMS)
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-toast]');
  if (btn) showToast(btn.dataset.toast);
});

/* ---------- Delete confirmation ----------
   Every button with aria-label="delete" asks first, then removes its row.
   In Laravel the "Delete" button submits a DELETE request instead. */
document.body.insertAdjacentHTML(
  'beforeend',
  `<dialog id="confirm-delete" class="modal">
    <div class="modal-box max-w-sm">
      <div class="bg-error/10 text-error mb-4 grid size-12 place-items-center rounded-2xl"><i data-lucide="trash-2" class="size-6"></i></div>
      <h3 class="text-lg font-bold" data-km="លុបទិន្នន័យនេះ?">Delete this record?</h3>
      <p class="py-2 opacity-75"><span class="font-semibold" id="confirm-delete-name"></span> <span data-km="នឹងត្រូវលុបជាអចិន្ត្រៃយ៍។">will be removed permanently.</span></p>
      <div class="modal-action">
        <form method="dialog"><button class="btn btn-ghost" data-km="បោះបង់">Cancel</button></form>
        <button class="btn btn-error" id="confirm-delete-yes" data-km="លុប">Delete</button>
      </div>
    </div>
    <form method="dialog" class="modal-backdrop"><button>close</button></form>
  </dialog>`
);
let rowToDelete = null;
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[aria-label="delete"]');
  if (!btn) return;
  rowToDelete = btn.closest('tr, li, .card');
  const nameEl = rowToDelete && (rowToDelete.querySelector('.font-medium, .font-semibold, .card-title') || rowToDelete.querySelector('td'));
  document.getElementById('confirm-delete-name').textContent = nameEl ? nameEl.textContent.trim() : '';
  document.getElementById('confirm-delete').showModal();
});
document.getElementById('confirm-delete-yes').addEventListener('click', () => {
  if (rowToDelete) rowToDelete.remove();
  document.getElementById('confirm-delete').close();
  showToast(isKhmer() ? 'បានលុបរួចរាល់' : 'Record deleted');
});

/* ---------- Saving add/edit dialogs ----------
   The browser blocks the submit while a "required" field is empty or wrong; those fields turn red
   (daisyUI "validator") and show their hint. "Cancel" buttons use formnovalidate to skip this. */
document.addEventListener('submit', (e) => {
  const form = e.target;
  const inDialog = form.closest('dialog') && !form.classList.contains('modal-backdrop');
  if (!inDialog && !form.hasAttribute('data-save')) return; // forms on a page save with data-save
  if (!form.querySelector('input, select, textarea')) return;
  if (e.submitter && e.submitter.formNoValidate) return;
  if (!inDialog) e.preventDefault();
  showToast(isKhmer() ? 'បានរក្សាទុករួចរាល់' : 'Saved successfully');
  if (inDialog) setTimeout(() => form.reset());
});

/* ---------- Tables become cards on phones ----------
   Each cell gets data-label="<its column header>", and app.css shows that label above the
   value when the screen is narrow. */
function labelTables() {
  document.querySelectorAll('table.table').forEach((table) => {
    table.classList.add('table-cards');
    const headers = [...table.querySelectorAll('thead th')].map((th) => th.textContent.trim());
    table.querySelectorAll('tbody tr, tfoot tr').forEach((tr) => {
      [...tr.cells].forEach((cell, i) => (cell.dataset.label = tr.cells.length === 1 ? '' : headers[i] || ''));
    });
  });
}

/* ---------- Search box and filters above a table ----------
   A search box or <select> in the same card as a table filters its rows right away.
   A select whose first option is "All ..." means "no filter". */
function rowValues(tr) {
  const values = new Set();
  tr.querySelectorAll('td, td *').forEach((el) => {
    if (el.children.length) return;
    const text = enText(el);
    values.add(text);
    text.split(/,\s*/).forEach((part) => values.add(part));
  });
  return values;
}
function filterTable(body) {
  const table = body.querySelector('table.table');
  const search = body.querySelector('input[type="search"]');
  const selects = [...body.querySelectorAll('select')].filter((s) => !s.closest('table, dialog'));
  const q = search ? search.value.trim().toLowerCase() : '';
  const active = selects.filter((s) => s.selectedIndex > 0).map((s) => s.value);
  let shown = 0;
  table.querySelectorAll('tbody tr:not(.no-results)').forEach((tr) => {
    const values = rowValues(tr);
    const text = (enText(tr) + ' ' + tr.textContent).toLowerCase();
    const match = (!q || text.includes(q)) && active.every((v) => values.has(v));
    tr.hidden = !match;
    if (match) shown++;
  });
  let empty = table.querySelector('tr.no-results');
  if (!empty) {
    table.querySelector('tbody').insertAdjacentHTML(
      'beforeend',
      `<tr class="no-results"><td colspan="${table.querySelectorAll('thead th').length}" class="py-10 text-center opacity-60">No matching records</td></tr>`
    );
    empty = table.querySelector('tr.no-results');
  }
  empty.hidden = shown > 0;
}
document.querySelectorAll('.card-body').forEach((body) => {
  if (!body.querySelector('table.table')) return;
  const controls = [...body.querySelectorAll('input[type="search"], select')].filter((c) => !c.closest('table, dialog'));
  controls.forEach((c) => c.addEventListener('input', () => filterTable(body)));
  if (controls.length) filterTable(body);
});

/* ---------- Page numbers under a table ---------- */
document.addEventListener('click', (e) => {
  const btn = e.target.closest('.join .join-item');
  if (!btn) return;
  const pages = [...btn.parentElement.querySelectorAll('.join-item')].filter((b) => /^\d+$/.test(b.textContent.trim()));
  if (!pages.length) return;
  const current = pages.findIndex((b) => b.classList.contains('btn-active'));
  let next = pages.indexOf(btn);
  if (btn.textContent.trim() === '«') next = Math.max(0, current - 1);
  if (btn.textContent.trim() === '»') next = Math.min(pages.length - 1, current + 1);
  if (next < 0) return;
  pages.forEach((b, i) => b.classList.toggle('btn-active', i === next));
  // Update "Showing 1–8 of 248"; Laravel's paginator will load the real rows for each page
  const label = [...btn.closest('.card-body').querySelectorAll('span')].find((s) => /^Showing /.test(enText(s)));
  if (label) {
    const [, size, total] = enText(label).match(/^Showing \d+–(\d+) of (\d+)$/) || [];
    if (size) label.textContent = `Showing ${next * size + 1}–${Math.min((next + 1) * size, total)} of ${total}`;
  }
});

/* ---------- Edit buttons ----------
   Opens the page's "Add ..." dialog as "Edit ...", filled with the values of that row. */
const addButton = document.querySelector('main .btn-primary[onclick*=".showModal"]');
const editDialog = addButton && document.getElementById(addButton.getAttribute('onclick').match(/(\w+)\.showModal/)[1]);
function setDialogMode(mode) {
  const title = editDialog.querySelector('h3');
  if (!title.dataset.addEn) {
    title.dataset.addEn = title.dataset.en || title.textContent;
    title.dataset.addKm = title.dataset.km || '';
  }
  const en = mode === 'edit' ? title.dataset.addEn.replace(/^Add /, 'Edit ') : title.dataset.addEn;
  const km = mode === 'edit' ? title.dataset.addKm.replace(/^បន្ថែម/, 'កែប្រែ') : title.dataset.addKm;
  title.dataset.en = en;
  if (km) title.dataset.km = km;
  title.textContent = isKhmer() && km ? km : en;
}
if (editDialog) {
  addButton.addEventListener('click', () => {
    editDialog.querySelector('form').reset();
    setDialogMode('add');
  });
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[aria-label="edit"]');
    if (!btn) return;
    const row = btn.closest('tr, .card');
    const form = editDialog.querySelector('form');
    form.reset();
    setDialogMode('edit');
    const values = row.matches('tr') ? rowValues(row) : new Set([...row.querySelectorAll('*')].filter((el) => !el.children.length).map(enText));
    const table = row.closest('table');
    const headers = table ? [...table.querySelectorAll('thead th')].map(enText) : [];
    const name = row.querySelector('.font-medium, .card-title');
    const khmer = [...row.querySelectorAll('*')].find((el) => !el.children.length && /[ក-៿]/.test(el.textContent));
    form.querySelectorAll('fieldset').forEach((fs) => {
      const legend = enText(fs.querySelector('legend')).toLowerCase();
      const field = fs.querySelector('input:not([type="file"]):not([type="password"]):not([type="date"]), select');
      if (!field) return;
      // a value can also sit inside longer text, e.g. "Grade 11 · Room 201"
      const inText = (pattern) => [...values].map((v) => v.match(pattern)).find(Boolean);
      const escape = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      if (field.tagName === 'SELECT') {
        const option =
          [...field.options].find((o) => values.has(o.value) || values.has(enText(o))) ||
          [...field.options].find((o) => inText(new RegExp(`\\b${escape(legend)} ${escape(o.value)}\\b`, 'i')));
        if (option) field.value = option.value;
        return;
      }
      if (/khmer/.test(legend) && khmer) field.value = khmer.textContent.trim();
      else if (/^name|class name/.test(legend) && name) field.value = enText(name);
      else {
        const i = headers.findIndex((h) => {
          h = h.toLowerCase();
          return h && (legend === h || legend.endsWith(' ' + h) || legend.startsWith(h + ' '));
        });
        const found = inText(new RegExp(`\\b${escape(legend)} (\\S+)`, 'i'));
        if (i >= 0 && row.cells[i]) field.value = enText(row.cells[i]);
        else if (found) field.value = found[1];
      }
    });
    editDialog.showModal();
  });
}

/* "Repeat password" must match "New password" */
const newPassword = document.getElementById('new-password');
const repeatPassword = document.getElementById('repeat-password');
if (newPassword && repeatPassword) {
  const check = () => repeatPassword.setCustomValidity(repeatPassword.value === newPassword.value ? '' : 'Passwords do not match');
  newPassword.addEventListener('input', check);
  repeatPassword.addEventListener('input', check);
}

drawThemeButtons();
applyLang(localStorage.getItem('lang') || 'en');

/* ---------- Chart helper (ApexCharts) ----------
   makeChart('#id', (c) => options) draws a chart and redraws it when the theme changes.
   "c" holds the colours for the current theme. */
function chartColors() {
  const dark = currentTheme() === 'dark';
  return {
    dark,
    series: dark ? '#3fb8a9' : '#1f877b',
    present: dark ? '#4fb888' : '#3aa876',
    absent: dark ? '#e07a7a' : '#dc6b6b',
    muted: '#898781',
    grid: dark ? '#2c2c2a' : '#e1e0d9',
    axis: dark ? '#383835' : '#c3c2b7',
  };
}
function chartBase(c) {
  return {
    chart: {
      toolbar: { show: false },
      zoom: { enabled: false },
      background: 'transparent',
      fontFamily: 'inherit',
      foreColor: c.muted,
    },
    colors: [c.series],
    dataLabels: { enabled: false },
    grid: { borderColor: c.grid, strokeDashArray: 0 },
    tooltip: { theme: c.dark ? 'dark' : 'light' },
    theme: { mode: c.dark ? 'dark' : 'light' },
    legend: { show: false },
  };
}
function makeChart(selector, build) {
  const el = document.querySelector(selector);
  if (!el || typeof ApexCharts === 'undefined') return;
  let chart;
  const draw = () => {
    if (chart) chart.destroy();
    const c = chartColors();
    const base = chartBase(c);
    const own = build(c);
    chart = new ApexCharts(el, { ...base, ...own, chart: { ...base.chart, ...own.chart } });
    chart.render();
  };
  draw();
  document.addEventListener('themechange', draw);
}

/* ---------- Demo data saved in this browser ----------
   A refresh keeps what you did today: attendance, interventions and corrections.
   Laravel will save these in PostgreSQL instead. A new day starts fresh. */
const DEMO_KEY = 'site-demo-v1';
const DEMO_DAY = new Date().toLocaleDateString('en-CA'); // YYYY-MM-DD, local time
const DEMO = (() => {
  try {
    const saved = JSON.parse(localStorage.getItem(DEMO_KEY));
    if (saved && saved.day === DEMO_DAY) return saved;
  } catch { /* nothing saved yet */ }
  return { day: DEMO_DAY, sessions: {}, interventions: [], measured: [], decided: [], audit: [] };
})();
function saveDemo() {
  try { localStorage.setItem(DEMO_KEY, JSON.stringify(DEMO)); } catch { /* private mode: lasts until the tab closes */ }
}
function auditStamp() {
  const now = new Date();
  return `${now.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }).replace(',', '')}, ${now.toTimeString().slice(0, 5)}`;
}
document.addEventListener('click', (e) => {
  if (!e.target.closest('[data-action="reset-demo"]')) return;
  try { localStorage.removeItem(DEMO_KEY); } catch { /* private mode */ }
  location.reload();
});

/* ---------- Class rosters (the same students and IDs as the Android app) ---------- */
const CLASS_SIZES = { '10A': 42, '10B': 41, '11A': 43, '11B': 40, '12A': 41, '12B': 41 };
const NAMED = {
  '10A': { 1: 'Sok Dara', 2: 'Chan Sreyneang', 3: 'Ouk Sreypov', 4: 'Tep Mony', 5: 'Heng Sophea', 6: 'Em Sothea', 7: 'Lim Kimheng', 8: 'Noun Pisey' },
  '10B': { 1: 'Nget Thida', 2: 'Phan Chenda', 3: 'Sim Kunthea', 4: 'Keo Makara', 5: 'Yim Ratanak', 9: 'Duong Panha', 14: 'Touch Vuthy' },
  '11A': { 1: 'Hun Chanra', 2: 'Prak Sokunthea', 3: 'In Sopheak', 4: 'Khiev Sreymom', 5: 'Seng Rattana', 6: 'Chhun Lina', 7: 'Kim Vannak', 12: 'Kong Mealea' },
  '11B': { 1: 'Chea Rithy', 2: 'Nhem Vichea', 3: 'Run Veasna', 4: 'Sao Bunthoeun' },
  '12A': { 1: 'Meas Sokha' },
  '12B': { 1: 'San Devi', 2: 'Ly Bopha' },
};
const ROSTER = (() => {
  const FAMILY = ['Chhay', 'Sar', 'Mom', 'Vong', 'Soun', 'Chhim', 'Leng', 'Peou', 'Huot', 'Tan', 'Meng', 'Rath', 'Svay', 'Choeun', 'Bun', 'Kheang', 'Ung', 'Phal', 'Sous', 'Yean'];
  const GIVEN = ['Sokunthea', 'Visal', 'Monyroth', 'Sovanna', 'Rachana', 'Vibol', 'Nita', 'Leakena', 'Kosal', 'Daravy', 'Sreyneath', 'Panharith', 'Bormey', 'Chanthy', 'Rotha', 'Sreyleak', 'Vuthea', 'Kanika', 'Soriya', 'Malis', 'Sopheap', 'Ratha', 'Seyha'];
  const used = new Set(Object.values(NAMED).flatMap((c) => Object.values(c)));
  return Object.keys(CLASS_SIZES).flatMap((cls, c) =>
    Array.from({ length: CLASS_SIZES[cls] }, (_, i) => {
      const n = i + 1;
      let name = NAMED[cls][n] || '';
      for (let k = 0; !name; k++) {
        const candidate = `${FAMILY[(n * 7 + c * 5 + k) % FAMILY.length]} ${GIVEN[(n * 3 + c * 11 + k * 2) % GIVEN.length]}`;
        if (!used.has(candidate)) { name = candidate; used.add(candidate); }
      }
      return { id: `S-${cls}-${String(n).padStart(3, '0')}`, name, cls };
    }));
})();
const rosterOf = (cls) => ROSTER.filter((s) => s.cls === cls);

/* ---------- Today's attendance: sample + what was submitted in this browser ---------- */
const sessionKey = (s) => `${s.cls}|${s.subject}|${s.period}`;
const SEED_SESSIONS = { '10A|Mathematics|1': { absent: ['S-10A-003', 'S-10A-004'], reasons: {} } };
const todaySessions = () => ({ ...SEED_SESSIONS, ...DEMO.sessions });
// 'A' if absent in any lesson today, 'P' if only present, undefined if not recorded yet
function dayStatus(id, sessions) {
  const cls = id.split('-')[1];
  let status;
  Object.entries(sessions).forEach(([key, s]) => {
    if (key.split('|')[0] !== cls) return;
    status = s.absent.includes(id) ? 'A' : status || 'P';
  });
  return status;
}

/* ---------- Risk rules (Plan v2.0, the same rules as the app) ----------
   Critical: under 70% or 5+ days in a row · High: 70–79%, 3–4 days in a row or down 10+ points
   Medium: 80–89% or down 5–9 points · Low: everything else */
// [rate, trend, days in a row] from the sample data, including the lesson already recorded today
const RISK_BASE = {
  'Chea Rithy': [68.4, -9.8, 4], 'San Devi': [90.2, -3.1, 5], 'Touch Vuthy': [77.5, -11.3, 2], 'Ouk Sreypov': [86.0, -8.2, 3],
  'Kong Mealea': [88.5, -6.0, 3], 'Meas Sokha': [74.1, -4.1, 1], 'Nhem Vichea': [76.2, -2.0, 0], 'Duong Panha': [78.8, -1.5, 0],
  'Run Veasna': [79.3, -0.8, 1], 'Sao Bunthoeun': [87.9, -6.4, 1], 'Ly Bopha': [84.2, 0.5, 0],
};
const SEMESTER_SESSIONS = 60;
const RISK_ACTION = {
  Critical: ['Admin review · meet parent', 'អ្នកគ្រប់គ្រងពិនិត្យ · ជួបអាណាព្យាបាល'],
  High: ['Follow up · message parent', 'តាមដាន · ផ្ញើសារអាណាព្យាបាល'],
  Medium: ['Watch · check reason', 'តាមដាន · ពិនិត្យមូលហេតុ'],
  Low: ['No action needed', 'មិនចាំបាច់ធ្វើអ្វី'],
};
const RISK_BADGE = { Critical: 'badge-error', High: 'badge-warning', Medium: 'badge-info', Low: 'badge-success' };
function riskOf(name, sessions = todaySessions()) {
  const st = ROSTER.find((s) => s.name === name);
  const [rate0, trend0, streak0] = RISK_BASE[name] || [96, 0, 0];
  if (!st) return { rate: rate0, trend: trend0, streak: streak0, level: 'Low' };
  const seed = dayStatus(st.id, SEED_SESSIONS);
  const now = dayStatus(st.id, sessions);
  const present = (now === 'P' ? 1 : 0) - (seed === 'P' ? 1 : 0);
  const added = !seed && now ? 1 : 0;
  const rate = Math.round((((rate0 / 100) * SEMESTER_SESSIONS + present) / (SEMESTER_SESSIONS + added)) * 1000) / 10;
  const trend = Math.round((trend0 + (rate - rate0) * 3) * 10) / 10; // recent weeks count more than the semester
  const before = streak0 - (seed === 'A' ? 1 : 0); // days in a row up to yesterday
  const streak = now === 'A' ? before + 1 : now === 'P' ? 0 : streak0;
  const level = rate < 70 || streak >= 5 ? 'Critical'
    : rate < 80 || streak >= 3 || trend <= -10 ? 'High'
    : rate < 90 || trend <= -5 ? 'Medium' : 'Low';
  return { rate, trend, streak, level };
}

/* ---------- Show the live numbers ----------
   [data-risk="Name"] holds one student; its [data-live] children get the new values.
   [data-kpi] tiles move by the change from the sample numbers. */
function applyLive() {
  const km = isKhmer();
  document.querySelectorAll('[data-risk]').forEach((row) => {
    const r = riskOf(row.dataset.risk);
    row.querySelectorAll('[data-live]').forEach((el) => {
      const key = el.dataset.live;
      if (key === 'rate') el.textContent = r.rate.toFixed(1) + '%';
      if (key === 'streak') el.textContent = r.streak;
      if (key === 'streak-text') el.textContent = r.streak >= 2 ? (km ? ` · ${r.streak} ថ្ងៃជាប់គ្នា` : ` · ${r.streak} in a row`) : '';
      if (key === 'action') el.textContent = RISK_ACTION[r.level][km ? 1 : 0];
      if (key === 'trend') {
        el.textContent = `${r.trend < 0 ? '▼' : '▲'} ${Math.abs(r.trend).toFixed(1)}`;
        el.classList.toggle('text-error', r.trend <= -3);
      }
      if (key === 'level') {
        el.classList.remove(...Object.values(RISK_BADGE));
        el.classList.add(RISK_BADGE[r.level]);
        el.textContent = (km && kmFor(r.level)) || r.level;
      }
    });
  });

  // How many students moved between levels since the sample numbers
  const seedRisk = Object.keys(RISK_BASE).map((n) => riskOf(n, SEED_SESSIONS));
  const liveRisk = Object.keys(RISK_BASE).map((n) => riskOf(n));
  const change = (test) => liveRisk.filter(test).length - seedRisk.filter(test).length;
  const delta = {
    Critical: change((r) => r.level === 'Critical'),
    High: change((r) => r.level === 'High'),
    Medium: change((r) => r.level === 'Medium'),
    Low: change((r) => r.level === 'Low'),
    'high-plus': change((r) => r.level === 'High' || r.level === 'Critical'),
    streak3: change((r) => r.streak >= 3),
    'not-recorded': -Object.keys(DEMO.sessions).filter((k) => !SEED_SESSIONS[k]).length,
  };
  document.querySelectorAll('[data-kpi]').forEach((el) => {
    el.dataset.base ??= el.textContent.trim();
    el.textContent = Math.max(0, +el.dataset.base + (delta[el.dataset.kpi] || 0));
  });

  // Today's lessons on the teacher dashboard: done, up next or not marked
  const sessions = todaySessions();
  let nextFound = false;
  document.querySelectorAll('[data-session]').forEach((li) => {
    const s = sessions[li.dataset.session];
    const size = CLASS_SIZES[li.dataset.session.split('|')[0]];
    const badge = li.querySelector('.badge');
    const btn = li.querySelector('a.btn');
    if (s) {
      badge.className = 'badge badge-soft badge-success';
      badge.innerHTML = `<i data-lucide="check" class="size-3.5"></i>${km ? 'រួចរាល់' : 'Done'} ${size - s.absent.length}/${size}`;
      btn.className = 'btn btn-sm';
      btn.textContent = km ? 'កែប្រែ' : 'Edit';
    } else {
      const isNext = !nextFound;
      nextFound = true;
      badge.className = `badge badge-soft ${isNext ? 'badge-primary' : ''}`;
      badge.textContent = isNext ? (km ? 'បន្ទាប់' : 'Up next') : km ? 'មិនទាន់ស្រង់' : 'Not marked';
      btn.className = `btn btn-sm ${isNext ? 'btn-primary' : ''}`;
      btn.innerHTML = `<i data-lucide="play" class="size-4"></i>${km ? 'ចាប់ផ្តើម' : 'Start'}`;
    }
  });
  // Teacher tiles: absent today and lessons marked
  const lessons = [...document.querySelectorAll('[data-session]')].map((li) => li.dataset.session);
  const setToday = (key, text) => document.querySelectorAll(`[data-today="${key}"]`).forEach((el) => (el.textContent = text));
  if (lessons.length) {
    const done = lessons.filter((k) => sessions[k]);
    const withAbsent = done.filter((k) => sessions[k].absent.length);
    const left = lessons.length - done.length;
    setToday('absent', withAbsent.reduce((n, k) => n + sessions[k].absent.length, 0));
    setToday('absent-where', withAbsent.map((k) => { const [cls, , p] = k.split('|'); return km ? `${cls} ម៉ោងទី${p}` : `${cls}, period ${p}`; }).join(' · ') || '—');
    setToday('marked', `${done.length}/${lessons.length}`);
    setToday('left', km ? `នៅសល់ ${left} ម៉ោង` : `${left} ${left === 1 ? 'period' : 'periods'} still to mark`);
  }
  if (window.lucide) lucide.createIcons();
}
applyLive();
document.addEventListener('click', (e) => {
  if (e.target.closest('[data-action="lang"]')) setTimeout(applyLive);
});
