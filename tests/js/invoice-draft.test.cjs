const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const view = fs.readFileSync(path.join(__dirname, '../../resources/views/invoices/index.blade.php'), 'utf8');
const functionSource = view.slice(view.indexOf('function applyCustomerTop()'), view.indexOf("document.getElementById('invTglBuat')?.addEventListener"));

test('localized date display uses the ISO form value and updates the datepicker', () => {
    let selectedDate;
    const elements = {
        invCustomer: {options: [{dataset: {topDays: '45'}}], selectedIndex: 0},
        invTglBuat: {value: '17 Sep 2026'},
        invTglTempo: {_airDatepicker: {selectDate(date) { selectedDate = date; }}},
        invTopHint: {},
        invoiceForm: {elements: {namedItem() { return {value: '2026-09-17'}; }}},
    };
    const context = vm.createContext({document: {getElementById: id => elements[id]}});
    vm.runInContext(functionSource + '\napplyCustomerTop();', context);
    assert.equal(selectedDate.getFullYear(), 2026);
    assert.equal(selectedDate.getMonth(), 10);
    assert.equal(selectedDate.getDate(), 1);
    assert.match(elements.invTopHint.textContent, /TOP 45 hari/);
});

test('an empty invoice date does not interrupt the invoice modal', () => {
    const elements = {
        invCustomer: {options: [{dataset: {topDays: '30'}}], selectedIndex: 0},
        invTglBuat: {value: ''}, invTglTempo: {value: ''}, invTopHint: {},
        invoiceForm: {elements: {namedItem() { return {value: ''}; }}},
    };
    const context = vm.createContext({document: {getElementById: id => elements[id]}});
    assert.doesNotThrow(() => vm.runInContext(functionSource + '\napplyCustomerTop();', context));
    assert.equal(elements.invTglTempo.value, '');
});
