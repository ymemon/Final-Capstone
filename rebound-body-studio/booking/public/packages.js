'use strict';
const el=id=>document.getElementById(id);
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money=v=>new Intl.NumberFormat('en-US',{style:'currency',currency:'USD',maximumFractionDigits:0}).format(v/100);
const randomKey=()=>Array.from(crypto.getRandomValues(new Uint8Array(32)),b=>b.toString(16).padStart(2,'0')).join('');
const returningFromSquare=location.hash==='#square-return';
let onlinePayments=false,sandbox=false,returnChecks=returningFromSquare?6:0;
let token=new URLSearchParams(location.hash.slice(1)).get('token')||'';
if(token){sessionStorage.setItem('packageToken',token);history.replaceState(null,'',location.pathname+location.search);}
else if(location.hash!=='#recover') token=(returningFromSquare?sessionStorage.getItem('squareCheckoutToken'):null)||sessionStorage.getItem('packageToken')||'';
if(returningFromSquare)history.replaceState(null,'',location.pathname+location.search);
async function api(fields){const r=await fetch('../api/packages.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(fields),cache:'no-store'});const d=await r.json();if(!r.ok)throw Error(d.error||'Please try again.');return d;}
function report(error){el('message').textContent=error.message;}
function packageUrl(){return location.origin+location.pathname+'?v=20260912-square-3#token='+token;}
function showBalance(p){
    el('shop').hidden=true;el('balance').hidden=false;
    const active=p.status==='active',pending=p.status==='pending_payment';
    el('balance').innerHTML=`<p class="eyebrow">Package #${p.id}</p><h2>${esc(p.package_name)}</h2>
        <p>${pending?'Payment pending':active?'Your package is active':esc(p.status.replaceAll('_',' '))} · ${money(p.quoted_price_cents)}</p>
        ${pending?'<p class="notice">Your order is saved and payment confirmation is pending. '+(onlinePayments&&p.online_checkout_status!=='closed'?'Use secure Square checkout below. If you already paid, check your payment before trying again.':'Call <a href="tel:+14809440494">(480) 944-0494</a> to arrange payment with the studio.')+' Sessions activate after payment is confirmed.</p>':''}
        ${p.status==='payment_review'?'<p class="notice">Your payment needs a studio review before more sessions can be booked. Please call (480) 944-0494.</p>':''}
        ${p.status==='refunded'?'<p class="notice">A refund has been recorded for this package. Please contact the studio about any upcoming appointments.</p>':''}
        ${pending&&onlinePayments&&p.online_checkout_status!=='closed'?'<button type="button" id="paySquare">'+(sandbox?'Test checkout — no real charge':'Pay securely with Square')+'</button>':''}
        <div class="stats"><div class="stat"><strong>${active?p.remaining:0}</strong><span>Available to book</span></div><div class="stat"><strong>${p.reserved}</strong><span>Reserved</span></div><div class="stat"><strong>${p.completed_or_used}</strong><span>Used</span></div></div>
        <p>${p.sessions_total} sessions in this package.${p.expires_at?' Redeem by '+esc(new Date(p.expires_at.replace(' ','T')+'Z').toLocaleDateString('en-US',{timeZone:'America/Phoenix',month:'long',day:'numeric',year:'numeric'}))+'.':''}</p>
        ${active?'<p>Eligible for '+esc(p.services.map(s=>s.name).join(', '))+'.</p>':''}
        <div class="actions">${active&&p.remaining>0?'<a class="button" href="index.html?v=20260912-packages-2#package='+token+'">Book with a package session</a>':''}<button type="button" id="refreshBalance" class="secondary">Refresh balance</button><button type="button" id="newOrder" class="secondary">Order another package</button></div>
        <details><summary>Keep or share your package link</summary><p class="muted">Anyone with this link can use the remaining sessions. A gift recipient can book with their own name and email. Share it only with someone you choose.</p><label>Private package link<input readonly id="privateLink" value="${esc(packageUrl())}"></label><button type="button" id="copyLink" class="secondary">Copy link</button></details>
        <h3>Session history</h3><p class="muted">A request reserves one session. Cancellation or decline returns it. A completed visit or no-show uses it.</p>
        <ul class="history">${p.history.map(h=>'<li>'+esc(h.reason.replaceAll('_',' '))+' · '+(h.delta<0?'1 session reserved or used':'1 session returned')+(h.when?' · '+esc(h.when):'')+(h.service_name?'<br>'+esc(h.service_name):'')+(h.status?' · '+esc(h.status.replaceAll('_',' ')):'')+'</li>').join('')||'<li>No sessions used yet.</li>'}</ul>`;
    el('refreshBalance').textContent=p.online_checkout_status?'Check payment and refresh balance':'Refresh balance';
    el('refreshBalance').onclick=()=>loadBalance(true);
    if(el('paySquare'))el('paySquare').onclick=startCheckout;
    el('newOrder').onclick=()=>{token='';returnChecks=0;sessionStorage.removeItem('packageToken');el('balance').hidden=true;el('shop').hidden=false;el('message').textContent='';};
    el('copyLink').onclick=async()=>{try{await navigator.clipboard.writeText(packageUrl());el('copyLink').textContent='Copied';}catch{el('privateLink').select();el('copyLink').textContent='Select and copy the link above';}};
}
async function loadBalance(checkPayment=false){const requestedToken=token;try{
    const p=await api({action:checkPayment?'refresh_payment':'view',token:requestedToken});if(token!==requestedToken)return;showBalance(p);el('message').textContent='';
    if(returnChecks>0&&p.status==='pending_payment'){returnChecks--;setTimeout(()=>{if(token===requestedToken)loadBalance(true);},2500);}else returnChecks=0;
}catch(e){if(token===requestedToken){returnChecks=0;report(e);}}}
async function startCheckout(){
    const checkoutToken=token;
    const b=el('paySquare');if(b)b.disabled=true;
    try{
        const d=await api({action:'checkout',token:checkoutToken});if(token!==checkoutToken)return;
        if(d.package){showBalance(d.package);return;}
        const u=new URL(d.checkout_url);const allowed=d.environment==='sandbox'?['sandbox.square.link','sandbox.checkout.square.site']:['square.link','checkout.square.site'];
        if(u.protocol!=='https:'||!allowed.includes(u.hostname)||u.username||u.password||u.port)throw Error('Unable to open Square checkout. Please contact the studio.');
        sessionStorage.setItem('squareCheckoutToken',checkoutToken);location.assign(u.href);
    }catch(e){report(e);if(b)b.disabled=false;}
}
el('orderForm').addEventListener('submit',async e=>{
    e.preventDefault();const b=el('orderButton');b.disabled=true;el('message').textContent='';
    const fields={action:'order',packageId:Number(new FormData(e.target).get('package')),name:el('orderName').value,email:el('orderEmail').value,phone:el('orderPhone').value,marketingOptOut:el('optOut').checked};
    const fingerprint=JSON.stringify(fields);let attempt;
    try{attempt=JSON.parse(sessionStorage.getItem('packageOrderAttempt')||'null');}catch{}
    if(!attempt||attempt.fingerprint!==fingerprint){attempt={fingerprint,key:randomKey()};sessionStorage.setItem('packageOrderAttempt',JSON.stringify(attempt));}
    try{const p=await api({...fields,requestKey:attempt.key});token=attempt.key;sessionStorage.setItem('packageToken',token);sessionStorage.removeItem('packageOrderAttempt');showBalance(p);el('message').textContent='Order saved. Check your email for your private package link.';el('balance').scrollIntoView({behavior:'smooth',block:'start'});if(onlinePayments)await startCheckout();}
    catch(error){report(error);}finally{b.disabled=false;}
});
el('recoverForm').addEventListener('submit',async e=>{e.preventDefault();el('recoverButton').disabled=true;try{const d=await api({action:'recover',email:el('recoverEmail').value});el('recoverMessage').textContent=d.message;}catch(e){el('recoverMessage').textContent=e.message;}finally{el('recoverButton').disabled=false;}});
async function init(){try{const r=await fetch('../api/packages.php',{cache:'no-store'});const d=await r.json();if(!r.ok)throw Error(d.error||'Packages are unavailable right now.');
onlinePayments=d.online_payment===true;sandbox=d.sandbox===true;
if(onlinePayments){el('paymentDescription').textContent=sandbox?'Test checkout only. No real payments or live package credits.':'Pay securely with Square. Your three sessions activate after payment is confirmed, and you can book them whenever you are ready.';el('orderButton').textContent=sandbox?'Continue to test checkout':'Continue to Square checkout';}
el('catalog').innerHTML=d.packages.map((p,i)=>`<label class="choice"><input type="radio" name="package" value="${p.id}" ${i===0?'checked':''} required>${p.sessions} × ${p.duration_min}-minute massages<strong>${money(p.price_cents)}</strong><span class="muted">${esc(p.name)}</span></label>`).join('')||'<p>Please call the studio for package availability.</p>';el('orderButton').disabled=d.packages.length===0;if(token)await loadBalance(returningFromSquare);
else if(returningFromSquare)el('message').textContent='Use the private package link in your order email, or recover it below, to check your payment and balance.';
}catch(e){report(e);el('catalog').textContent='Please call (480) 944-0494 for help.';}}
init();
