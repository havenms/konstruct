const assert = require('assert');
const fs = require('fs');
const vm = require('vm');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'frontend', 'form.js'), 'utf8');
const windowObject = {};
const context = {
  window: windowObject,
  document: {
    addEventListener: function () {},
    querySelectorAll: function () { return []; },
  },
  console,
  setTimeout,
  clearTimeout,
  localStorage: { getItem() { return null; }, setItem() {}, removeItem() {} },
  FormData: function () {},
  fetch: function () { throw new Error('fetch should be stubbed'); },
};
vm.createContext(context);
vm.runInContext(source, context);

async function testSubmissionWaitsForPersistenceAndWebhook() {
  const FormBuilderInstance = context.window.FormBuilderInstance;
  assert.ok(FormBuilderInstance, 'FormBuilderInstance should be exposed for runtime testing');

  const instance = Object.create(FormBuilderInstance.prototype);
  instance.currentPage = 1;
  instance.config = {
    pages: [{ webhook: { enabled: true, url: 'https://example.test/webhook' }, customJS: '' }],
  };
  instance.formData = { fname: 'QA' };
  instance.validateCurrentPage = () => true;
  instance.normalizeAllPhoneNumbers = () => {};
  instance.trackFacebookPixelEvent = () => {};
  instance.clearSavedData = () => {};
  instance.setSubmitting = () => {};
  let successShown = false;
  instance.showSuccess = () => { successShown = true; };
  instance.showSubmissionError = (message) => { throw new Error('unexpected submission error: ' + message); };

  let resolveSave;
  const savePromise = new Promise((resolve) => { resolveSave = resolve; });
  let resolveWebhook;
  const webhookPromise = new Promise((resolve) => { resolveWebhook = resolve; });
  let webhookStarted = false;
  instance.saveSubmissionToDatabase = () => savePromise;
  instance.sendWebhook = () => { webhookStarted = true; return webhookPromise; };

  const pending = instance.submitForm();
  assert.ok(pending && typeof pending.then === 'function', 'submitForm should return a promise');
  assert.strictEqual(successShown, false, 'success must not show before persistence completes');
  assert.strictEqual(webhookStarted, false, 'webhook should start after persistence succeeds');

  resolveSave({ submission_uuid: 'qa-uuid' });
  await Promise.resolve();
  await Promise.resolve();
  assert.strictEqual(webhookStarted, true, 'webhook should start after persistence succeeds');
  assert.strictEqual(successShown, false, 'success must wait for webhook completion');

  resolveWebhook({ success: true });
  await pending;
  assert.strictEqual(successShown, true, 'success should show after all required work succeeds');
}

async function testWebhookFailureDoesNotShowSuccess() {
  const FormBuilderInstance = context.window.FormBuilderInstance;
  const instance = Object.create(FormBuilderInstance.prototype);
  instance.currentPage = 1;
  instance.config = {
    pages: [{ webhook: { enabled: true, url: 'https://example.test/webhook' }, customJS: '' }],
  };
  instance.formData = {};
  instance.validateCurrentPage = () => true;
  instance.normalizeAllPhoneNumbers = () => {};
  instance.trackFacebookPixelEvent = () => {};
  instance.clearSavedData = () => {};
  instance.setSubmitting = () => {};
  instance.saveSubmissionToDatabase = () => Promise.resolve({ submission_uuid: 'qa-uuid' });
  instance.sendWebhook = () => Promise.reject(new Error('Webhook failed'));
  let successShown = false;
  let shownError = '';
  instance.showSuccess = () => { successShown = true; };
  instance.showSubmissionError = (message) => { shownError = message; };

  await instance.submitForm();
  assert.strictEqual(successShown, false, 'failed webhook must not show success');
  assert.match(shownError, /Webhook failed/);
}

(async () => {
  await testSubmissionWaitsForPersistenceAndWebhook();
  await testWebhookFailureDoesNotShowSuccess();
  console.log('form-submit tests passed');
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
