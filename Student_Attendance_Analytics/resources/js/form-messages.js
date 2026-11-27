/* ---------- Khmer words for the browser's own form checks ----------
   Before a form is sent, the browser checks it (empty box, wrong e-mail, too short…)
   and shows a small bubble in the browser's language, e.g. "Please fill in this field."
   On a Khmer page that bubble gets the same Khmer words as the server's messages,
   e.g. "សូមបញ្ចូល ឈ្មោះ (ខ្មែរ)។". English pages keep the browser's own text. */

// The name of the box: the fieldset title, or its <label>, without "(optional)"
function fieldName(el) {
    const legend = el.closest('fieldset')?.querySelector('legend');
    const label = legend || (el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`)) || el.closest('label');
    const text = label ? [...label.childNodes].filter((n) => n.nodeType === Node.TEXT_NODE).map((n) => n.textContent).join('') : '';
    return text.replace(/\s+/g, ' ').trim() || el.getAttribute('aria-label') || '';
}

function khmerMessage(el) {
    const v = el.validity;
    const name = fieldName(el);
    if (v.valueMissing) return name ? `សូមបញ្ចូល ${name}។` : 'សូមបំពេញប្រអប់នេះ។';
    if (v.typeMismatch && el.type === 'email') return `${name || 'អ៊ីមែល'} ត្រូវតែជាអ៊ីមែលត្រឹមត្រូវ។`;
    if (v.tooShort) return `${name || 'ប្រអប់នេះ'} ត្រូវមានយ៉ាងហោចណាស់ ${el.minLength} តួអក្សរ។`;
    if (v.tooLong) return `${name || 'ប្រអប់នេះ'} មិនអាចលើសពី ${el.maxLength} តួអក្សរទេ។`;
    if (v.rangeUnderflow) return `${name || 'តម្លៃនេះ'} ត្រូវមានយ៉ាងហោចណាស់ ${el.min}។`;
    if (v.rangeOverflow) return `${name || 'តម្លៃនេះ'} មិនអាចធំជាង ${el.max} ទេ។`;
    return `ទម្រង់ ${name || 'ប្រអប់នេះ'} មិនត្រឹមត្រូវទេ។`; // pattern, wrong number, …
}

export function initKhmerFormMessages() {
    if (document.documentElement.lang !== 'km') return;

    // "invalid" does not bubble: listen in the capture phase for every form on the page
    document.addEventListener('invalid', (e) => {
        const el = e.target;
        el.setCustomValidity(''); // look at the real problem, not the last message
        if (!el.validity.valid) el.setCustomValidity(khmerMessage(el));
    }, true);

    // As soon as the user types or picks, clear the message so the box can be checked again
    const clear = (e) => e.target.setCustomValidity?.('');
    document.addEventListener('input', clear, true);
    document.addEventListener('change', clear, true);

    // A click on Save always starts with a clean check, also when a script filled a box
    // (a message left from the last try would otherwise block the form)
    document.addEventListener('click', (e) => {
        const form = e.target.closest?.('button[type="submit"], input[type="submit"]')?.form;
        if (form) [...form.elements].forEach((el) => el.setCustomValidity?.(''));
    }, true);
}
