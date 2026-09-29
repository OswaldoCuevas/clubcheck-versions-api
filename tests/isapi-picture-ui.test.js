// Run: node tests/isapi-picture-ui.test.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
let script = fs.readFileSync(path.join(__dirname, '../app/Views/admin/isapi.php'), 'utf8').split('<script>')[1].split('</script>')[0];
script = script.replace(/<\?= \$endpointsJson \?>/, JSON.stringify({create: '/create', show: '/show/:id', index: '/index'}))
    .replace(/<\?= \$actionsJson \?>/, '[]').replace(/<\?= json_encode\(\$csrfToken \?\? ''\) \?>/, '"csrf"');
new vm.Script(script); // Parse the entire script, including form handlers.
script = script.slice(0, script.indexOf("    el('commandForm').addEventListener")) +
    'globalThis.subject = {normalizePicturePath, renderPicture, renderEventPictures, showDetail, clearPicture};})();';
const elements = new Map();
function element() {
    const classes = new Set();
    return {children: [], dataset: {}, textContent: '', value: '', innerHTML: '',
        classList: {add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name), toggle: (name, flag) => flag ? classes.add(name) : classes.delete(name)},
        appendChild(child) {this.children.push(child);}, replaceChildren(...children) {this.children = children;},
        removeAttribute(name) {delete this[name];}, addEventListener(name, fn) {this[name] = fn;}};
}
const get = id => {if (!elements.has(id)) elements.set(id, element()); return elements.get(id);};
let posted, imageCount = 0, revoked = 0;
class TestURL extends URL {}
TestURL.createObjectURL = blob => {imageCount++; assert.ok(['image/jpeg', 'image/png'].includes(blob.type)); return 'blob:test';};
TestURL.revokeObjectURL = () => revoked++;
const context = {URL: TestURL, Blob, Uint8Array, atob, console,
    document: {getElementById: get, createElement: element, querySelectorAll: () => []},
    bootstrap: {Modal: function () {this.show = () => {}; }},
    fetch: async (url, options = {}) => {
        if (url === '/create') {posted = JSON.parse(options.body); return {ok: true, json: async () => ({command: {Id: 'capture'}})};}
        if (url === '/show/capture') return {ok: true, json: async () => ({command: {Id: 'capture', Action: 'get_event_picture', Status: 'Pending', Parameters: {}}})};
        return {ok: true, json: async () => ({agents: [], commands: []})};
    }
};
vm.createContext(context);
vm.runInContext(script, context);
const {normalizePicturePath, renderPicture, renderEventPictures, clearPicture} = context.subject;
const relative = '/LOCALS/pic/acsLinkCap/202609_00/25_162800_30075_0.jpeg@WEB000000000063';
assert.equal(normalizePicturePath('https://192.168.1.72' + relative), relative);
assert.equal(normalizePicturePath(relative), relative);
for (const bad of ['/ISAPI/System/status', relative + '?x=1', relative + '#hash', '/LOCALS/pic/acsLinkCap/a.svg', 'https://user:pass@192.168.1.72' + relative, '/LOCALS/pic/acsLinkCap/../secret.jpeg']) {
    assert.throws(() => normalizePicturePath(bad));
}
const valid = {Status: 'Completed', ResponseContentType: 'image/png', ResponseMetadata: '{"bodyEncoding":"base64"}', ResponseBody: 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='};
renderPicture(valid);
assert.equal(imageCount, 1);
assert.equal(get('eventPicture').src, 'blob:test');
assert.equal(get('responseImage').classList.contains('d-none'), false);
clearPicture();
assert.equal(revoked, 1);
for (const changed of [{ResponseContentType: 'image/svg+xml'}, {ResponseBody: 'PGh0bWw+'}, {ResponseMetadata: '{}'}, {ResponseBody: 'A'.repeat(2097156)}]) {
    assert.throws(() => renderPicture({...valid, ...changed}));
}
renderPicture({Status: 'Pending'});
assert.match(get('responseBody').textContent, /Esperando la captura/);
renderPicture({Status: 'Failed'});
assert.match(get('responseBody').textContent, /No hay una imagen/);
(async () => {
    renderEventPictures({Action: 'get_recent_activity', Status: 'Completed', CustomerId: 'customer', AgentId: 'desktop-main', TerminalIndex: 2, DeviceId: 'entry', ResponseBody: JSON.stringify({AcsEvent: {InfoList: [{serialNo: 4260, name: '<script>test</script>', pictureURL: 'https://192.168.1.72' + relative}]}})});
    const button = get('eventPictures').children[1];
    assert.ok(button.textContent.includes('<script>test</script>')); // Safe textContent, never markup.
    await button.click();
    assert.equal(posted.action, 'get_event_picture');
    assert.equal(posted.customerId, 'customer');
    assert.equal(posted.terminalIndex, 2);
    assert.equal(posted.parameters.picturePath, relative);
    assert.equal('pictureURL' in posted.parameters, false);
    console.log('ISAPI picture UI: syntax, path restrictions, image validation, pending/error states and event request passed.');
})().catch(error => {console.error(error); process.exitCode = 1;});
