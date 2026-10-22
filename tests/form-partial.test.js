const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const { webcrypto } = require('node:crypto');
const values = new Map();
let requests = [];
const context = { window: { crypto: webcrypto }, console, setTimeout,
  document: { addEventListener() {}, createElement() { return { setAttribute() {} }; } },
  localStorage: { getItem: k => values.get(k), setItem: (k,v) => values.set(k,v), removeItem: k => values.delete(k) },
  FormData: class { constructor() { this.values = {}; } append(k,v) { this.values[k] = v; } },
  formBuilderFrontend: { apiUrl: '/api/', nonce: 'expired-cache' },
  fetch: (url, options) => { requests.push({url, options}); return Promise.resolve({ok:true, json:async()=>({success:true})}); }
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(require('node:path').join(__dirname,'../frontend/form.js'),'utf8'),context);
const Builder = context.window.FormBuilderInstance;
function instance() {
 const b = Object.create(Builder.prototype);
 Object.assign(b, { currentPage:1,totalPages:2,formId:'1',formData:{fname:'Alice'},config:{pages:[{},{}]},
 formElement:{querySelectorAll:()=>[],querySelector:()=>null}, validateCurrentPage:()=>true,
 normalizeAllPhoneNumbers(){}, showPage(){}, sendStepNotification(){}, trackFacebookPixelStep(){},trackFacebookPixelEvent(){},showSubmissionError(m){this.error=m;} });
 return b;
}
(async()=>{
 const b=instance(); let resolve; let saves=0; let hooks=0;
 b.sendStepNotification=()=>hooks++;
 b.saveSubmissionToDatabase=(partial)=>{assert.equal(partial,true);saves++;return new Promise(r=>resolve=r);};
 const pending=b.goToNextPage();
 assert.equal(b.currentPage,1,'must wait for persistence before advancing');
 assert.equal(hooks,0,'hooks must wait');
 await b.goToNextPage(); assert.equal(saves,1,'double click blocked');
 resolve({success:true}); assert.equal(await pending,true); assert.equal(b.currentPage,2);assert.equal(hooks,1);
 const failed=instance(); failed.saveSubmissionToDatabase=()=>Promise.reject(new Error('offline'));
 assert.equal(await failed.goToNextPage(),false); assert.equal(failed.currentPage,1);assert.match(failed.error,/try again/i);assert.equal(failed.submitting,false);
 failed.saveSubmissionToDatabase=()=>Promise.resolve({success:true}); assert.equal(await failed.goToNextPage(),true);
 const invalid=instance();invalid.validateCurrentPage=()=>false;invalid.saveSubmissionToDatabase=()=>{throw Error('invalid saved');};await invalid.goToNextPage();assert.equal(invalid.currentPage,1);
 const nextButton={dataset:{},textContent:'Next',disabled:false,setAttribute(){},removeAttribute(){}};
 const ui=instance();ui.formElement.querySelector=(s)=>s.includes('btn-next')?nextButton:null;
 ui.setSubmitting(true);assert.equal(nextButton.disabled,true,'Next button visibly disabled during persistence');
 ui.currentPage=2;ui.setSubmitting(false);assert.equal(nextButton.disabled,false,'original Next button restored after advance');
 const wire=instance();wire.submissionUuid='visitor';await wire.saveSubmissionToDatabase(true);
 assert.equal(requests.at(-1).options.body.values.partial,'1');assert.equal(requests.at(-1).options.body.values.page_number,'1');
 await wire.saveSubmissionToDatabase();assert.equal(requests.at(-1).options.body.values.partial,'0');
 wire.sendWebhook('https://example.test',1);wire.config.notifications={step_notifications:{enabled:true}};wire.sendStepNotification=Builder.prototype.sendStepNotification;wire.sendStepNotification(1);
 for(const request of requests) assert.equal(request.options.headers?.['X-WP-Nonce'],undefined,'public requests must omit cached nonce');
 const savedInit=Builder.prototype.init, savedShow=Builder.prototype.showPage;
 Builder.prototype.init=function(){};Builder.prototype.showPage=function(){};
 context.window.formBuilderData={demo:{formConfig:{pages:[{},{}]},submissionUuid:'shared-cached-uuid'}};
 const container={id:'demo',dataset:{formId:'1'},querySelector:()=>wire.formElement};values.clear();
 const a=new Builder(container),c=new Builder(container);
 assert.match(a.submissionUuid,/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i);assert.notEqual(a.submissionUuid,c.submissionUuid);
 a.saveData();assert.equal(new Builder(container).submissionUuid,a.submissionUuid,'resume same visitor');
 Builder.prototype.init=savedInit;Builder.prototype.showPage=savedShow;
 console.log('partial persistence behavior passed');
})().catch(e=>{console.error(e);process.exitCode=1;});
