const { chromium } = require('C:/Users/M.Zulfi/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const fs = require('fs');
(async () => {
 const browser = await chromium.launch({headless:true, channel:'msedge'});
 const page = await browser.newPage();
 const source = fs.readFileSync('resources/views/users/index.blade.php','utf8');
 const script = source.match(/<script>([\s\S]*?)<\/script>/)[1];
 await page.setContent('<form id="editUserForm"></form><div id="editUserModal"></div>' + ['euName','euEmail','euPhone','euPosition','euTarget'].map(id=>`<input id="${id}">`).join('') + '<select id="euRole"><option>Super Admin</option><option>Sales Executive</option><option>Sales Manager</option></select><select id="euStatus"><option>Active</option><option>Non-Active</option></select>');
 await page.addScriptTag({path:'public/vendor/jquery/jquery.min.js'});
 await page.addScriptTag({path:'public/vendor/select2/select2.min.js'});
 await page.evaluate(()=>{window.bootstrap={Modal:class {show(){}}};jQuery('select').select2();});
 await page.addScriptTag({content:script});
 for(const [role,status] of [['Sales Executive','Non-Active'],['Super Admin','Active'],['Sales Manager','Non-Active']]) {
  await page.evaluate(({role,status})=>openEditUser(5,'Test','test@example.test','081234','Sales',role,status,0),{role,status});
  const actual=await page.evaluate(()=>({role:jQuery('#euRole').val(),label:document.querySelector('#select2-euRole-container').textContent,status:document.querySelector('#select2-euStatus-container').textContent}));
  if(actual.role!==role || actual.label!==role || actual.status!==status) throw Error(JSON.stringify(actual));
  console.log(`PASS edit user: ${role}, ${status}`);
 }
 await browser.close();
})().catch(e=>{console.error(e);process.exitCode=1});
