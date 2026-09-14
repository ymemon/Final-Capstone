'use strict';
const API_BASE = '../../api';
const el = id => document.getElementById(id);
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const today = new Intl.DateTimeFormat('en-CA', {timeZone:'America/Phoenix',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
let month = today.slice(0,7), selectedDay = today, appointments = [], calendarSequence = 0, previewKey = null, sending = false;
let refreshTimer;
function statusLabel(status) { return status.replaceAll('_',' ').replace(/\b\w/g, c=>c.toUpperCase()); }
function pill(status) { return `<span class="status-pill status-${escapeHtml(status)}">${escapeHtml(statusLabel(status))}</span>`; }
async function api(path, fields = {}) {
    const res = await fetch(API_BASE + '/admin/' + path + '.php', {
        method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({admin_token:sessionStorage.getItem('admin_token') || '', ...fields})
    });
    const data = await res.json();
    if (res.status === 401) showLogin(data.error);
    if (!res.ok) throw new Error(data.error || 'Unable to complete the request. Please try again.');
    return data;
}
function showLogin(message = '') {
    clearInterval(refreshTimer);
    sessionStorage.removeItem('admin_token');
    el('loginView').style.display = 'block'; el('dashboardView').style.display = 'none';
    el('loginError').textContent = message;
}
async function requestLogin(event) {
    event.preventDefault();
    el('loginBtn').disabled = true; el('loginError').textContent = ''; el('loginSent').style.display = 'none';
    try { await api('login', {email:el('loginEmail').value}); el('loginSent').style.display = 'block'; }
    catch(error) { el('loginError').textContent = error.message; }
    finally { el('loginBtn').disabled = false; }
}
async function loadRequests() {
    const data = await api('requests');
    el('requestsList').innerHTML = data.requests.map(r=>`<div class="req-row"><div class="req-info">
        <b>${escapeHtml(r.client_name)}</b><div class="meta">${escapeHtml(r.service_name)} · ${r.duration_min} min · ${escapeHtml(r.when)}</div>
        <div class="meta">${escapeHtml(r.client_email)} ${escapeHtml(r.client_phone)}</div>
        ${r.client_note ? `<div class="note">${escapeHtml(r.client_note)}</div>` : ''}
        ${r.technique_pref ? `<div class="note">Preference: ${escapeHtml(r.technique_pref)}</div>` : ''}</div>
        <div class="req-actions"><button class="btn-good" data-id="${r.id}" data-action="approve">Approve</button>
        <button class="btn-bad" data-id="${r.id}" data-action="decline">Decline</button></div></div>`).join('') || '<p class="empty-state">No pending requests right now.</p>';
}
async function decide(id, action, container) {
    container.querySelectorAll('button').forEach(b=>b.disabled=true);
    try { await api('approve',{appointment_id:id,action}); await refresh(); }
    catch(error) { el('dashboardError').textContent = error.message; container.querySelectorAll('button').forEach(b=>b.disabled=false); }
}
async function loadCalendar() {
    const sequence = ++calendarSequence;
    const data = await api('calendar',{month});
    if (sequence !== calendarSequence) return;
    appointments = data.appointments;
    renderCalendar();
}
function dateLabel(date, options) { return new Intl.DateTimeFormat('en-US',{timeZone:'UTC',...options}).format(new Date(date+'T12:00:00Z')); }
function renderCalendar() {
    el('monthTitle').textContent = dateLabel(month+'-01',{month:'long',year:'numeric'});
    const [year, monthNumber] = month.split('-').map(Number);
    const offset = new Date(Date.UTC(year,monthNumber-1,1)).getUTCDay();
    const length = new Date(Date.UTC(year,monthNumber,0)).getUTCDate();
    let cells = Array.from({length:offset},()=>'<div class="calendar-blank" aria-hidden="true"></div>');
    for(let day=1;day<=length;day++) {
        const date = month+'-'+String(day).padStart(2,'0');
        const events = appointments.filter(a=>a.date===date && !['cancelled','declined'].includes(a.status));
        cells.push(`<button class="calendar-day ${selectedDay===date?'selected':''} ${today===date?'today':''}" data-date="${date}" aria-pressed="${selectedDay===date}" aria-label="${escapeHtml(dateLabel(date,{weekday:'long',month:'long',day:'numeric'}))}, ${events.length} appointments">
            <span class="day-number">${day}</span><span class="day-count">${events.length ? events.length+' appt' : ''}</span>
            <span class="day-events">${events.slice(0,2).map(a=>`<span>${escapeHtml(a.time)} · ${escapeHtml(a.client_name)}</span>`).join('')}${events.length>2?'<span>+'+(events.length-2)+' more</span>':''}</span></button>`);
    }
    el('calendarGrid').innerHTML = cells.join(''); renderDay();
}
function appointmentRow(a) {
    return `<div class="appt-row"><div><b>${escapeHtml(a.client_name)}</b><div>${escapeHtml(a.service_name)} · ${escapeHtml(a.time)}–${escapeHtml(a.end_time)}</div><div class="meta">${escapeHtml(a.client_email)} ${escapeHtml(a.client_phone)}</div></div><div>${pill(a.status)}${a.status==='confirmed'?`<div class="appointment-actions"><button data-appt="${a.id}" data-terminal="complete">Completed</button><button data-appt="${a.id}" data-terminal="no_show">No-show</button><button data-appt="${a.id}" data-terminal="cancel">Cancel</button></div>`:''}${a.package_purchase_id?'<p class="hint">Package #'+a.package_purchase_id+'</p>':''}</div></div>`;
}
function renderDay() {
    el('dayTitle').textContent = dateLabel(selectedDay,{weekday:'long',month:'long',day:'numeric',year:'numeric'});
    el('dayAppointments').innerHTML = appointments.filter(a=>a.date===selectedDay).map(appointmentRow).join('') || '<p class="empty-state">No appointments for this day.</p>';
    el('calendarList').innerHTML = appointments.map(a=>`<div><p class="list-date">${escapeHtml(a.when)}</p>${appointmentRow(a)}</div>`).join('') || '<p class="empty-state">No appointments this month.</p>';
}
function navigateMonth(change) {
    const [y,m] = month.split('-').map(Number), next = new Date(Date.UTC(y,m-1+change,1));
    month = next.toISOString().slice(0,7); selectedDay = month===today.slice(0,7)?today:month+'-01';
    loadCalendar().catch(reportError);
}
function invalidatePreview() { if (sending) return; previewKey=null; el('campaignPreview').hidden=true; el('sendCampaign').disabled=true; }
async function loadMarketing() {
    const data = await api('marketing');
    el('recipientCount').textContent = `${data.recipients} customers currently receive marketing emails.`;
    el('clientsList').innerHTML = data.clients.map(c=>`<div class="client-row"><div><b>${escapeHtml(c.name)}</b><div>${escapeHtml(c.email)}</div></div>
        <label>Marketing emails <select data-client="${c.id}" data-current="${c.marketing_consent===1 && !c.unsubscribed_at?'1':'0'}" aria-label="Marketing emails for ${escapeHtml(c.name || c.email)}"><option value="1" ${c.marketing_consent===1 && !c.unsubscribed_at?'selected':''}>Yes</option><option value="0" ${c.marketing_consent!==1 || c.unsubscribed_at?'selected':''}>No</option></select></label></div>`).join('') || '<p class="empty-state">Customers appear here after their first booking request.</p>';
    el('campaignHistory').innerHTML = data.campaigns.map(c=>`<p><b>${escapeHtml(c.subject)}</b><br>${c.recipient_count} recipients queued · ${escapeHtml(c.created_at)} UTC</p>`).join('') || '<p>No campaigns queued yet.</p>';
}
async function changePreference(select) {
    const enabled = select.value;
    if (enabled==='1' && !window.confirm('Has this customer asked to receive marketing emails again?')) { select.value=select.dataset.current; return; }
    select.disabled=true;
    try {
        await api('marketing',{action:'preference',client_id:select.dataset.client,enabled,customer_requested:enabled});
        el('marketingMessage').textContent='Saved. Appointment confirmations and reminders continue as usual.';
        invalidatePreview(); await loadMarketing();
    } catch(error) { el('marketingMessage').textContent=error.message; select.value=select.dataset.current; select.disabled=false; }
}
async function previewCampaign(event) {
    event.preventDefault(); el('previewCampaign').disabled=true; el('marketingMessage').textContent='';
    const subject=el('campaignSubject').value.trim(), body=el('campaignBody').value.trim();
    try {
        const data=await api('marketing',{action:'preview',subject,body});
        if(subject!==el('campaignSubject').value.trim() || body!==el('campaignBody').value.trim()) return;
        previewKey=crypto.randomUUID(); el('previewSubject').textContent=data.subject; el('previewBody').textContent=data.text;
        el('sendCampaign').textContent=`Send to ${data.recipients} customers`; el('sendCampaign').disabled=data.recipients===0; el('campaignPreview').hidden=false;
    } catch(error) { el('marketingMessage').textContent=error.message; }
    finally { el('previewCampaign').disabled=false; }
}
async function sendCampaign() {
    if (!previewKey || sending) return;
    sending=true; el('sendCampaign').disabled=true; el('previewCampaign').disabled=true;
    el('campaignSubject').disabled=true; el('campaignBody').disabled=true;
    try {
        const data=await api('marketing',{action:'send',subject:el('campaignSubject').value.trim(),body:el('campaignBody').value.trim(),request_key:previewKey});
        el('marketingMessage').textContent=`Campaign queued for ${data.queued} customers. Anyone who opts out before delivery is skipped.`;
        previewKey=null; el('campaignPreview').hidden=true; el('campaignSubject').value=''; el('campaignBody').value=''; await loadMarketing();
    } catch(error) { el('marketingMessage').textContent=error.message+' You can retry this send safely.'; el('sendCampaign').disabled=false; }
    finally { sending=false; el('previewCampaign').disabled=false; el('campaignSubject').disabled=false; el('campaignBody').disabled=false; }
}
function reportError(error) { el('dashboardError').textContent=error.message; }
async function refresh() {
    el('dashboardError').textContent='';
    const results=await Promise.allSettled([loadRequests(),loadCalendar(),loadPackages()]);
    results.filter(r=>r.status==='rejected').forEach(r=>reportError(r.reason));
    el('lastUpdated').textContent='Updated '+new Intl.DateTimeFormat('en-US',{timeZone:'America/Phoenix',hour:'numeric',minute:'2-digit'}).format(new Date());
}
function showDashboard() {
    el('loginView').style.display='none'; el('dashboardView').style.display='block';
    refresh(); loadMarketing().catch(error=>el('marketingMessage').textContent=error.message);
    refreshTimer=setInterval(()=>{ if(!document.hidden) refresh(); },60000);
}
el('loginForm').addEventListener('submit',requestLogin);
el('logoutLink').addEventListener('click',e=>{e.preventDefault();showLogin();});
el('requestsList').addEventListener('click',e=>{const b=e.target.closest('button[data-action]'); if(b) decide(b.dataset.id,b.dataset.action,b.closest('.req-actions'));});
el('prevMonth').addEventListener('click',()=>navigateMonth(-1)); el('nextMonth').addEventListener('click',()=>navigateMonth(1));
el('todayButton').addEventListener('click',()=>{month=today.slice(0,7);selectedDay=today;loadCalendar().catch(reportError);});
el('calendarGrid').addEventListener('click',e=>{const b=e.target.closest('[data-date]'); if(b){selectedDay=b.dataset.date;renderCalendar();}});
el('refreshButton').addEventListener('click',()=>{refresh();loadMarketing().catch(reportError);});
el('clientsList').addEventListener('change',e=>{if(e.target.matches('select[data-client]')) changePreference(e.target);});
el('campaignForm').addEventListener('submit',previewCampaign); el('sendCampaign').addEventListener('click',sendCampaign);
el('campaignSubject').addEventListener('input',invalidatePreview);el('campaignBody').addEventListener('input',invalidatePreview);
const urlToken=new URLSearchParams(location.search).get('token');
if(urlToken){sessionStorage.setItem('admin_token',urlToken);history.replaceState({},'',location.pathname);}
if(sessionStorage.getItem('admin_token')) showDashboard(); else showLogin();

function randomPackageKey(){return Array.from(crypto.getRandomValues(new Uint8Array(32)),b=>b.toString(16).padStart(2,'0')).join('');}
async function loadPackages(){
    const data=await api('packages');
    el('squareConnection').textContent=data.square.enabled
        ? (data.square.environment==='sandbox'?'Square sandbox testing is enabled.':'Square online payments are connected.')
        : data.square.connected?'Square account configured. Online checkout is awaiting activation.':'Square connection pending for massage@reboundbodystudio.com. Package orders and studio-recorded payments remain available.';
    // Do not replace an in-progress receipt or adjustment while the periodic refresh runs.
    if(el('packageOrders').contains(document.activeElement)) return;
    renderPackages(data.orders);
}
function renderPackages(orders){
    el('packageOrders').innerHTML=orders.map(p=>`<article class="package-order"><h3>#${p.id} · ${escapeHtml(p.package_name)}</h3>
        <p>${escapeHtml(p.client_name)} · ${escapeHtml(p.client_email)} · ${escapeHtml(p.client_phone)}</p>
        <p>${escapeHtml(statusLabel(p.status))} · $${(p.quoted_price_cents/100).toFixed(2)}${p.paid_at?' · Paid via '+escapeHtml(p.payment_method)+' · Receipt '+escapeHtml(p.payment_reference):''}</p>
        <p class="balance-line">${p.status==='active'?p.remaining:0} available · ${p.reserved} reserved · ${p.completed_or_used} used</p>
        ${p.expires_at?'<p class="hint">Redeem by '+escapeHtml(p.expires_at.slice(0,10))+'</p>':''}
        ${p.square?'<p>Square checkout: '+escapeHtml(statusLabel(p.square.status))+(p.square.refunded_cents?' · Refunded $'+(p.square.refunded_cents/100).toFixed(2):'')+'</p>'+(p.square.review_reason?'<p class="error">'+escapeHtml(p.square.review_reason)+'</p>':'')+'<button data-square-action="square_refresh" data-purchase="'+p.id+'">Check Square payment</button>':''}
        ${p.status==='pending_payment'&&p.square&&['ready','creating'].includes(p.square.status)?'<p class="hint">An online checkout exists. Close it before accepting a different payment or cancelling this order.</p><button data-square-action="square_close" data-purchase="'+p.id+'">Close online checkout</button>':''}
        ${p.status==='pending_payment'&&(!p.square||p.square.status==='closed')?`<form data-package-action="pay" data-id="${p.id}" data-amount="${p.quoted_price_cents}"><label>Payment method<select name="method"><option>Square</option><option>Cash</option><option>Other</option></select></label><label>Amount received ($)<input name="amount" type="number" min="0" step="0.01" value="${(p.quoted_price_cents/100).toFixed(2)}" required></label><label>Receipt / reference<input name="reference" minlength="3" maxlength="120" required></label><button type="submit" class="btn-good">Record payment and activate</button></form><button data-void="${p.id}" class="btn-bad">Cancel unpaid order</button>`:''}
        ${p.status==='active'?`<details><summary>Record a session used outside online booking, or restore a used session</summary><p class="hint">Online bookings update the balance automatically. To release a reserved session, cancel or decline its appointment.</p><form data-package-action="adjust" data-id="${p.id}" data-key="${randomPackageKey()}"><label>Adjustment<select name="delta"><option value="-1">Use one session</option><option value="1">Restore one used session</option></select></label><label>Reason<input name="note" minlength="5" maxlength="500" required></label><button type="submit">Save adjustment</button></form></details>`:''}
        ${p.adjustments.length?'<details><summary>Adjustment history</summary>'+p.adjustments.map(a=>'<p>'+escapeHtml(a.created_at)+' UTC · '+(a.delta===-1?'Used one':'Restored one')+' · '+escapeHtml(a.note)+'</p>').join('')+'</details>':''}</article>`).join('')||'<p class="empty-state">No package orders yet. New orders will appear here.</p>';
}
el('packageOrders').addEventListener('submit',async e=>{
    const form=e.target.closest('form[data-package-action]');if(!form)return;e.preventDefault();
    const action=form.dataset.packageAction,fields=Object.fromEntries(new FormData(form));
    if(action==='pay'){
        if(!window.confirm('Confirm that this payment has already been received. This activates the package; it does not charge a card.'))return;
        fields.amount_cents=Math.round(Number(fields.amount)*100);
    }else fields.request_key=form.dataset.key;
    form.querySelectorAll('button').forEach(b=>b.disabled=true);
    try{const data=await api('packages',{...fields,action,purchase_id:form.dataset.id});renderPackages(data.orders);el('packageMessage').textContent='Saved. The package balance is up to date.';}
    catch(error){el('packageMessage').textContent=error.message;form.querySelectorAll('button').forEach(b=>b.disabled=false);}
});
el('packageOrders').addEventListener('click',async e=>{
    const squareButton=e.target.closest('[data-square-action]');
    if(squareButton){
        if(squareButton.dataset.squareAction==='square_close'&&!window.confirm('Close this unpaid online checkout before arranging another payment? No refund will be issued.'))return;
        squareButton.disabled=true;
        try{const data=await api('packages',{action:squareButton.dataset.squareAction,purchase_id:squareButton.dataset.purchase});renderPackages(data.orders);el('packageMessage').textContent='Square status checked.';}
        catch(error){el('packageMessage').textContent=error.message;squareButton.disabled=false;}return;
    }
    const b=e.target.closest('[data-void]');if(!b||!window.confirm('Cancel this unpaid package order?'))return;b.disabled=true;
    try{const data=await api('packages',{action:'void',purchase_id:b.dataset.void});renderPackages(data.orders);el('packageMessage').textContent='Unpaid order cancelled.';}catch(error){el('packageMessage').textContent=error.message;b.disabled=false;}
});
for(const id of ['dayAppointments','calendarList'])el(id).addEventListener('click',async e=>{
    const b=e.target.closest('[data-terminal]');if(!b)return;
    const warning=b.dataset.terminal==='no_show'?'The package session remains used.':b.dataset.terminal==='cancel'?'Any reserved package session will be returned.':'';
    if(!window.confirm('Mark this appointment '+statusLabel(b.dataset.terminal)+'? '+warning))return;
    await decide(b.dataset.appt,b.dataset.terminal,b.parentElement);
});
