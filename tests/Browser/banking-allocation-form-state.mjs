import assert from 'node:assert/strict';
import fs from 'node:fs';
import puppeteer from 'puppeteer';

const blade = fs.readFileSync('resources/views/banking/payments/fields.blade.php', 'utf8');
const script = blade.match(/<script>([\s\S]*)<\/script>/)?.[1];
assert.ok(script, 'Banking allocation script must be present.');

const html = `<!doctype html>
<form id="logout-form"></form>
<form id="banking-form" data-banking-payment-form>
    <select data-payment-type>
        <option value="customer_receipt">Customer receipt</option>
        <option value="supplier_payment" selected>Supplier payment</option>
    </select>
    <select data-payment-relation>
        <option value="customer">Customer</option>
        <option value="supplier" selected>Supplier</option>
    </select>
    <input data-transaction-amount value="121">
    <table><tbody>
        <tr data-open-item data-type="supplier_payment" data-relation="supplier">
            <td><input data-allocation-checkbox type="checkbox"></td>
            <td><input data-allocation-amount disabled></td>
            <td><span data-allocation-required class="hidden"></span></td>
        </tr>
        <tr data-open-item data-type="customer_receipt" data-relation="customer">
            <td><input data-allocation-checkbox type="checkbox"></td>
            <td><input data-allocation-amount disabled></td>
            <td><span data-allocation-required class="hidden"></span></td>
        </tr>
    </tbody></table>
    <span data-total-transaction></span>
    <span data-total-allocated></span>
    <span data-total-remaining></span>
</form>
<script>${script}<\/script>`;

const browser = await puppeteer.launch({headless: true, args: ['--no-sandbox']});
const page = await browser.newPage();
let assertions = 0;
const equal = (actual, expected, message) => {
    assert.equal(actual, expected, message);
    assertions++;
};
const state = () => page.evaluate(() => {
    const rows = Array.from(document.querySelectorAll('[data-open-item]'));
    const rowState = row => ({
        hidden: row.hidden,
        checked: row.querySelector('[data-allocation-checkbox]').checked,
        disabled: row.querySelector('[data-allocation-amount]').disabled,
        required: row.querySelector('[data-allocation-amount]').required,
        value: row.querySelector('[data-allocation-amount]').value,
    });

    return {
        firstForm: document.querySelector('form').id,
        supplier: rowState(rows[0]),
        customer: rowState(rows[1]),
        allocated: document.querySelector('[data-total-allocated]').textContent,
        remaining: document.querySelector('[data-total-remaining]').textContent,
    };
});

try {
    await page.setContent(html, {waitUntil: 'domcontentloaded'});
    let current = await state();
    equal(current.firstForm, 'logout-form', 'The regression layout must put logout first.');
    equal(current.supplier.hidden, false, 'Restored supplier row must be visible.');
    equal(current.supplier.checked, false, 'Supplier checkbox starts unchecked.');
    equal(current.supplier.disabled, true, 'Unchecked supplier amount starts disabled.');
    equal(current.supplier.required, false, 'Unchecked supplier amount is not required.');
    equal(current.customer.hidden, true, 'Incompatible customer row starts hidden.');
    equal(current.allocated, '0.00', 'Initial allocated total is zero.');
    equal(current.remaining, '121.00', 'Initial remaining total equals transaction amount.');

    await page.click('[data-type="supplier_payment"] [data-allocation-checkbox]');
    current = await state();
    equal(current.supplier.checked, true, 'Click checks the supplier allocation.');
    equal(current.supplier.disabled, false, 'Checked supplier amount is enabled.');
    equal(current.supplier.required, true, 'Checked supplier amount is required.');

    await page.type('[data-type="supplier_payment"] [data-allocation-amount]', '121');
    current = await state();
    equal(current.supplier.value, '121', 'Supplier amount accepts 121.');
    equal(current.allocated, '121.00', 'Supplier allocation updates the allocated total.');
    equal(current.remaining, '0.00', 'Supplier allocation updates the remaining total.');

    await page.click('[data-type="supplier_payment"] [data-allocation-checkbox]');
    current = await state();
    equal(current.supplier.checked, false, 'Second click unchecks the supplier allocation.');
    equal(current.supplier.disabled, true, 'Unchecked supplier amount is disabled again.');
    equal(current.supplier.required, false, 'Unchecked supplier amount is no longer required.');
    equal(current.allocated, '0.00', 'Unchecked allocation is excluded from totals.');
    equal(current.remaining, '121.00', 'Remaining total returns to transaction amount.');

    await page.evaluate(() => {
        const row = document.querySelector('[data-type="supplier_payment"]');
        row.querySelector('[data-allocation-checkbox]').checked = true;
        row.querySelector('[data-allocation-amount]').value = '121';
        window.dispatchEvent(new Event('pageshow'));
    });
    current = await state();
    equal(current.supplier.hidden, false, 'Pageshow retains the restored supplier row.');
    equal(current.supplier.disabled, false, 'Pageshow enables a restored checked amount.');
    equal(current.supplier.required, true, 'Pageshow restores required state.');
    equal(current.allocated, '121.00', 'Pageshow restores allocated total.');
    equal(current.remaining, '0.00', 'Pageshow restores remaining total.');

    await page.select('[data-payment-type]', 'customer_receipt');
    await page.select('[data-payment-relation]', 'customer');
    current = await state();
    equal(current.supplier.hidden, true, 'Type/Relation change hides supplier row.');
    equal(current.supplier.checked, false, 'Hidden supplier allocation is neutralized.');
    equal(current.supplier.disabled, true, 'Hidden supplier amount is disabled.');
    equal(current.customer.hidden, false, 'Compatible customer row is visible.');

    await page.click('[data-type="customer_receipt"] [data-allocation-checkbox]');
    await page.type('[data-type="customer_receipt"] [data-allocation-amount]', '50');
    current = await state();
    equal(current.customer.disabled, false, 'Customer allocation amount is enabled.');
    equal(current.customer.required, true, 'Customer allocation amount is required.');
    equal(current.allocated, '50.00', 'Customer amount updates allocated total.');
    equal(current.remaining, '71.00', 'Customer amount updates remaining total.');

    console.log(JSON.stringify({result: 'passed', assertions}));
} finally {
    await browser.close();
}
