'use strict';
// Browser regression checks for the real navigation component and login page.
// No account, database writes, or authentication bypass route required.
const {spawn, execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '..').replaceAll('\\', '/');
const scratch = path.join(process.env.LOCALAPPDATA, 'hermes/cache/scratch');
const chromePath = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const phpPath = process.env.PHP_PATH || 'C:/laragon/bin/php/php-8.5.10-Win32-vs17-x64/php.exe';
const base = process.env.TOKOKITA_URL || 'http://localhost/PustakaRapi/';
const selected = process.argv[2] || 'all';
if (!['all', 'username', 'logo'].includes(selected)) throw new Error('Use all, username, or logo');
fs.mkdirSync(scratch, {recursive:true});
const chrome = spawn(chromePath, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  '--remote-debugging-port=0', '--user-data-dir=' + path.join(scratch, 'tokokita-brand-' + Date.now()), 'about:blank'
], {stdio:['ignore','ignore','pipe']});
let socket, id=0, started=false, stderr='';
const pending = new Map();
const timeout = setTimeout(() => {chrome.kill(); console.error('Browser test timeout'); process.exit(1);}, 45000);
function send(method, params={}) {
  return new Promise((resolve,reject) => {
    const requestId=++id;
    pending.set(requestId,{resolve,reject});
    socket.send(JSON.stringify({id:requestId,method,params}));
  });
}
async function evaluate(expression) {
  const r = await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});
  if(r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails));
  return r.result.value;
}
async function navigate(url) {
  const loaded = new Promise(resolve => {
    const listener = ({data}) => {
      if(JSON.parse(data).method==='Page.loadEventFired') {
        socket.removeEventListener('message',listener); resolve();
      }
    };
    socket.addEventListener('message',listener);
  });
  await send('Page.navigate',{url});
  await loaded;
}
chrome.stderr.on('data',async data => {
  stderr+=data.toString();
  const match=stderr.match(/DevTools listening on ws:\/\/127\.0\.0\.1:(\d+)\//);
  if(!match || started) return;
  started=true;
  try {
    const targets=await(await fetch('http://127.0.0.1:'+match[1]+'/json/list')).json();
    socket=new WebSocket(targets.find(t=>t.type==='page').webSocketDebuggerUrl);
    await new Promise((resolve,reject)=>{socket.onopen=resolve;socket.onerror=reject;});
    socket.onmessage=({data})=>{
      const r=JSON.parse(data);
      if(pending.has(r.id)) {const p=pending.get(r.id);pending.delete(r.id);r.error?p.reject(new Error(JSON.stringify(r.error))):p.resolve(r.result);}
    };
    await send('Page.enable');
    await send('Network.enable');
    await send('Network.setCacheDisabled',{cacheDisabled:true});
    const results=[];
    if(selected==='all'||selected==='username') {
      const component=execFileSync(phpPath,['-d','opcache.enable_cli=0','-r',
        `function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
         function auth_username(){return 'Pengguna Uji';}
         function auth_role_name(){return 'Administrator';}
         function auth_has_permission($p){return true;}
         function csrf_field(){return '';}
         $_GET['page']='dashboard'; include ${JSON.stringify(root+'/includes/navigation.php')};`
      ],{encoding:'utf8'});
      for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await navigate(base+'?page=login');
        await evaluate(`(() => {
          document.querySelector('.login-screen').remove();
          const shell=document.createElement('div');shell.className='app-layout';
          shell.innerHTML=${JSON.stringify(component)};document.body.prepend(shell);
          document.querySelector('.app-sidebar').classList.add('is-open');
        })()`);
        const metrics=await evaluate(`(() => {
          const color=s=>s.match(/[\\d.]+/g).map(Number);
          const blend=(fg,bg)=>fg.slice(0,3).map((v,i)=>v*(fg[3]??1)+bg[i]*(1-(fg[3]??1)));
          const luminance=c=>c.slice(0,3).map(v=>{v/=255;return v<=0.04045?v/12.92:((v+0.055)/1.055)**2.4;}).reduce((s,v,i)=>s+v*[0.2126,0.7152,0.0722][i],0);
          const name=document.querySelector('.sidebar-footer .user-name');
          const footer=document.querySelector('.sidebar-footer');
          const bg=blend(color(getComputedStyle(footer).backgroundColor),color(getComputedStyle(document.querySelector('.app-sidebar')).backgroundColor));
          const foreground=color(getComputedStyle(name).color);const a=luminance(foreground),b=luminance(bg);
          const rect=name.getBoundingClientRect();
          return {foreground:getComputedStyle(name).color,background:bg,contrast:(Math.max(a,b)+0.05)/(Math.min(a,b)+0.05),visible:rect.width>0&&rect.left>=0&&rect.right<=innerWidth};
        })()`);
        results.push({test:'sidebar username contrast',width,...metrics,pass:metrics.visible&&metrics.contrast>=4.5});
      }
    }
    if(selected==='all'||selected==='logo') {
      for(const width of [1440,390]) {
        await send('Emulation.setDeviceMetricsOverride',{width,height:900,deviceScaleFactor:1,mobile:false});
        await navigate(base+'?page=login');
        const metrics=await evaluate(`(async () => {
          const img=document.querySelector('.login-logo-image');if(!img)throw new Error('Login logo missing');
          await img.decode();
          const style=getComputedStyle(img);const canvas=document.createElement('canvas');
          canvas.width=img.naturalWidth;canvas.height=img.naturalHeight;
          const ctx=canvas.getContext('2d');ctx.drawImage(img,0,0);const data=ctx.getImageData(0,0,canvas.width,canvas.height).data;
          let clear=0,opaque=0;for(let i=3;i<data.length;i+=4){if(data[i]===0)clear++;if(data[i]===255)opaque++;}
          return {background:style.backgroundColor,imageClearPixels:clear,imageOpaquePixels:opaque,loaded:img.complete,noOverflow:document.documentElement.scrollWidth<=innerWidth};
        })()`);
        results.push({test:'login logo transparent',width,...metrics,pass:metrics.background==='rgba(0, 0, 0, 0)'&&metrics.imageClearPixels>0&&metrics.imageOpaquePixels>0&&metrics.loaded&&metrics.noOverflow});
        if(width===390) {
          const shot=await send('Page.captureScreenshot',{format:'png',clip:{x:50,y:100,width:290,height:180,scale:1}});
          fs.writeFileSync(path.join(scratch,'tokokita-login-transparent-brand.png'),Buffer.from(shot.data,'base64'));
        }
      }
    }
    console.log(JSON.stringify({results,failed:results.filter(r=>!r.pass).length},null,2));
    await send('Browser.close').catch(()=>{});clearTimeout(timeout);
    process.exit(results.every(r=>r.pass)?0:1);
  }catch(error){console.error(error.stack);chrome.kill();clearTimeout(timeout);process.exit(1);}
});
