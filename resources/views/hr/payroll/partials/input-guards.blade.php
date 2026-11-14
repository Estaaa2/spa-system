{{--
    Payroll input guards (validation hardening, Nov 2026). Include once per page.
    The server rules (InputLimits) stay the source of truth; this only stops bad
    input at the keyboard and blocks submit with the browser's own message.

    data-guard="digits"  data-digits="9,12,14"
        Statutory IDs: digits, spaces and hyphens only; extra digits past the
        largest allowed count are dropped; the count must be one of the list.
    data-guard="money"   data-max="50000"  [data-decimals="2"]
        [data-max-monthly="…"] — used instead of data-max when the form's
        [data-basis] select is "monthly".
        [data-max-percent="100"] [data-label-flat="…"] — used when the form's
        [data-method] select is "percent".
        Digits and one dot only, limited decimals, must be ≤ the max.
--}}
<script>
(function () {
    'use strict';
    if (window.__payrollInputGuards) return;
    window.__payrollInputGuards = true;

    const peso = n => '₱' + Number(n).toLocaleString('en-US');

    function guardDigits(el) {
        const allowed = (el.dataset.digits || '').split(',').map(Number).filter(Boolean);
        const most = Math.max.apply(null, allowed);
        let out = '', count = 0;
        for (const ch of el.value) {
            if (/[0-9]/.test(ch)) {
                if (count >= most) continue;
                count++; out += ch;
            } else if (ch === ' ' || ch === '-') {
                if (out !== '') out += ch;           // server needs a digit first
            }
        }
        if (out !== el.value) el.value = out;
        let msg = '';
        if (count > 0 && !allowed.includes(count)) {
            const list = allowed.length > 1
                ? allowed.slice(0, -1).join(', ') + ' or ' + allowed[allowed.length - 1]
                : String(allowed[0]);
            msg = 'Enter ' + list + ' digits (you have ' + count + ').';
        }
        el.setCustomValidity(msg);
    }

    function moneyMax(el) {
        const form = el.form;
        const method = form && form.querySelector('[data-method]');
        if (method && method.value === 'percent' && el.dataset.maxPercent) {
            return { max: Number(el.dataset.maxPercent), decimals: 4, percent: true };
        }
        const basis = form && form.querySelector('[data-basis]');
        if (basis && basis.value === 'monthly' && el.dataset.maxMonthly) {
            return { max: Number(el.dataset.maxMonthly), decimals: Number(el.dataset.decimals || 2) };
        }
        return { max: Number(el.dataset.max), decimals: Number(el.dataset.decimals || 2) };
    }

    function guardMoney(el) {
        const { max, decimals, percent } = moneyMax(el);
        let v = el.value.replace(/[^0-9.]/g, '');
        const dot = v.indexOf('.');
        if (dot !== -1) {
            v = v.slice(0, dot + 1) + v.slice(dot + 1).replace(/\./g, '').slice(0, decimals);
        }
        if (v !== el.value) el.value = v;
        let msg = '';
        if (v !== '' && v !== '.' && max && Number(v) > max) {
            msg = percent ? 'A percentage cannot exceed ' + max + '.' : 'The amount cannot exceed ' + peso(max) + '.';
        } else if (v === '.') {
            msg = 'Enter a number.';
        }
        el.setCustomValidity(msg);
    }

    function run(el) {
        if (el.dataset.guard === 'digits') guardDigits(el);
        else if (el.dataset.guard === 'money') guardMoney(el);
    }

    document.addEventListener('input', e => {
        if (e.target.matches && e.target.matches('[data-guard]')) run(e.target);
    });
    // Basis / method changes move the cap: re-check the amount in the same form.
    document.addEventListener('change', e => {
        if (e.target.matches && e.target.matches('[data-basis], [data-method]') && e.target.form) {
            e.target.form.querySelectorAll('[data-guard="money"]').forEach(run);
        }
    });
    // Values filled by script (edit modals, restored old input) are checked on
    // submit too. Capture phase: runs before the pages' double-submit guards.
    document.addEventListener('submit', e => {
        const f = e.target;
        const fields = f.querySelectorAll('[data-guard]');
        if (!fields.length) return;
        fields.forEach(run);
        if (!f.checkValidity()) {
            e.preventDefault();
            e.stopImmediatePropagation();
            f.reportValidity();
        }
    }, true);
}());
</script>
