const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/request_orders/index.blade.php'), 'utf8');
const script = source.slice(source.indexOf('(function attachDoSubmitGuards()'), source.indexOf("document.querySelectorAll('.cancel-request-form')"));

function setup(responses, confirm = false) {
    let submit;
    let token = 'first-token';
    let serial = 0;
    const button = { disabled: false };
    const calls = [];
    const alerts = [];
    const redirects = [];
    const form = {
        action: '/request-orders', dataset: {},
        addEventListener: (_, callback) => { submit = callback; },
        setAttribute() {}, removeAttribute() {},
        querySelectorAll: () => [button],
        querySelector: () => token ? { value: token, remove: () => { token = null; } } : null,
    };
    class FormDataMock extends Map {
        constructor() { super([['_action_token', token], ['kode_sektor', 'S-17']]); }
    }
    vm.runInNewContext(script, {
        document: { getElementById: id => id === 'addDoForm' ? form : null },
        prepareDoSubmit: () => true,
        initActionTokens: () => { token ??= `replacement-${++serial}`; },
        FormData: FormDataMock,
        fetch: async (_, options) => {
            calls.push(new Map(options.body));
            const response = responses.shift();
            if (response instanceof Error) throw response;
            return response;
        },
        window: { confirm: () => confirm, alert: text => alerts.push(text), location: { assign: url => redirects.push(url) } },
    });
    return { submit: () => submit({ preventDefault() {} }), calls, alerts, redirects, button, form };
}

const duplicate = () => ({ status: 422, ok: false, json: async () => ({ errors: { duplicate: ['RDO yang sama sudah ada.'] } }) });
const success = () => ({ status: 200, ok: true, json: async () => ({ redirect: '/request-orders' }) });

test('confirmation retries with a fresh action token and preserves input', async () => {
    const ui = setup([duplicate(), success()], true);
    await ui.submit();
    assert.equal(ui.calls.length, 2);
    assert.notEqual(ui.calls[0].get('_action_token'), ui.calls[1].get('_action_token'));
    assert.equal(ui.calls[1].get('allow_duplicate'), '1');
    assert.equal(ui.calls[1].get('kode_sektor'), 'S-17');
    assert.deepEqual(ui.redirects, ['/request-orders']);
});

test('cancelling does not save and unlocks the form for correction', async () => {
    const ui = setup([duplicate(), success()]);
    await ui.submit();
    assert.equal(ui.calls.length, 1);
    assert.equal(ui.button.disabled, false);
    await ui.submit();
    assert.notEqual(ui.calls[0].get('_action_token'), ui.calls[1].get('_action_token'));
    assert.equal(ui.calls[1].has('allow_duplicate'), false);
});

test('network error preserves token to prevent duplicate creation on retry', async () => {
    const ui = setup([new Error('Connection lost'), success()]);
    await ui.submit();
    assert.deepEqual(ui.alerts, ['Connection lost']);
    assert.equal(ui.button.disabled, false);
    await ui.submit();
    assert.equal(ui.calls[0].get('_action_token'), ui.calls[1].get('_action_token'));
});

test('repeated click while saving sends only one request', async () => {
    const ui = setup([success()]);
    await Promise.all([ui.submit(), ui.submit()]);
    assert.equal(ui.calls.length, 1);
});
