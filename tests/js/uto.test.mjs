import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {JSDOM} from 'jsdom';
import jquery from 'jquery';

function setup() {
    const dom = new JSDOM('<div class="uto-search"><input data-role="uto-search-input-field" '
        + 'data-action="ilias.php?cmd=findTargets"></div><div id="members"></div>',
    {url: 'https://example.org/', runScripts: 'outside-only'});
    const {window} = dom;
    window.$ = window.jQuery = jquery(window);
    window.il = {existingCoreService: true};
    window.eval(readFileSync(new URL('../../js/dist/tiny-autocomplete.js', import.meta.url), 'utf8'));
    window.eval(readFileSync(new URL('../../js/dist/main.js', import.meta.url), 'utf8'));
    return dom;
}

test('bundle preserves core namespace and initializes each input once', () => {
    const dom = setup();
    const {window} = dom;
    window.il.Plugins.UserTakeOver.init('.uto-search');
    window.il.Plugins.UserTakeOver.init('.uto-search');
    window.il.Plugins.UserTakeOver.init('.missing');
    assert.equal(window.il.existingCoreService, true);
    assert.equal(window.document.querySelectorAll('.uto-search-field').length, 1);
    assert.equal(window.document.querySelector('input').tinyAutocomplete.settings.minChars, 3);
    dom.window.close();
});

test('search sends the existing endpoint contract and renders safe keyboard-selectable links', async () => {
    const dom = setup();
    const {window} = dom;
    const $ = window.$;
    let request;
    $.ajax = options => {
        request = options;
        options.success([{value: 42, display: '<img src=x onerror=alert(1)>'}, {value: 'bad"id', display: 'invalid'}]);
    };
    window.il.Plugins.UserTakeOver.init('.uto-search');
    $('input').val('abc').trigger($.Event('keyup', {keyCode: 65}));
    await new Promise(resolve => setTimeout(resolve, 450));
    assert.equal(request.url, 'ilias.php?cmd=findTargets');
    assert.equal(request.data.usrto_search_term, 'abc');
    assert.equal(request.method, 'get');
    assert.equal(window.document.querySelectorAll('.autocomplete-item').length, 1);
    assert.equal(window.document.querySelector('img'), null);
    const link = window.document.querySelector('.autocomplete-item a');
    assert.equal(link.textContent, '<img src=x onerror=alert(1)>');
    assert.equal(new URL(link.href).searchParams.get('usr_id'), '42');
    let clicked;
    window.HTMLAnchorElement.prototype.click = function () { clicked = this.href; };
    $('input').trigger($.Event('keydown', {keyCode: 40}));
    $('input').trigger($.Event('keydown', {keyCode: 13}));
    assert.equal(clicked, link.href);
    dom.window.close();
});

test('stored member labels remain text and IDs remain unchanged', async () => {
    const dom = setup();
    const {window} = dom;
    window.il.Plugins.UserTakeOver.labelMembers('members', {'42': 'login (<Admin>)'});
    window.document.getElementById('members').innerHTML = '<div class="tagify__tag" value="42">'
        + '<span class="tagify__tag-text">42</span></div>';
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(window.document.querySelector('.tagify__tag-text').textContent, 'login (<Admin>)');
    assert.equal(window.document.querySelector('.tagify__tag').getAttribute('value'), '42');
    assert.equal(window.document.querySelector('admin'), null);
    dom.window.close();
});
