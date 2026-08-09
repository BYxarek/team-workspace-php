const $=(s,c=document)=>c.querySelector(s), $$=(s,c=document)=>[...c.querySelectorAll(s)];
function appUrl(path=''){const base=(window.APP_BASE||'').replace(/\/$/,'');return base+'/'+String(path).replace(/^\//,'')}
function toast(msg){const t=$('#toast');if(!t)return;t.textContent=msg;t.classList.add('show');clearTimeout(window.__toastTimer);window.__toastTimer=setTimeout(()=>t.classList.remove('show'),2800)}
async function api(url,opt={}){opt.headers={...(opt.headers||{}),'Content-Type':'application/json','X-CSRF-Token':window.CSRF||''};const r=await fetch(/^https?:\/\//i.test(url)?url:appUrl(url),opt);const j=await r.json().catch(()=>({ok:false,error:'Некорректный ответ сервера'}));if(!r.ok){const err=new Error(j.error||`HTTP ${r.status}`);err.status=r.status;err.payload=j;throw err}return j}
function esc(s){return String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]))}
function openDialog(id){const d=document.getElementById(id);if(d&&!d.open)d.showModal()}
function closeDialog(el){const d=typeof el==='string'?document.getElementById(el):el?.closest?.('dialog');if(d?.open)d.close()}
$$('[data-open]').forEach(b=>b.addEventListener('click',()=>openDialog(b.dataset.open)));
$$('[data-close]').forEach(b=>b.addEventListener('click',()=>closeDialog(b)));
$$('dialog').forEach(d=>d.addEventListener('click',e=>{if(e.target===d)d.close()}));
$$('dialog[data-auto-open]').forEach(d=>{if(!d.open)d.showModal()});
$$('[data-history-back]').forEach(b=>b.addEventListener('click',()=>history.back()));


function renderTodoLists(lists){
  const grid=$('#todo-lists-grid');if(!grid)return;grid.innerHTML='';
  for(const l of lists){
    const a=document.createElement('a');a.className='card board-link';a.href=appUrl(`/todos/${l.id}`);
    a.innerHTML=`<div><h3>${esc(l.title)}</h3><p class="muted">${esc(l.description||'')}</p></div><span class="visibility-badge">${esc(l.visibility_label||l.visibility||'')}</span>`;
    grid.append(a);
  }
  if(!lists.length){const empty=document.createElement('div');empty.className='empty card';empty.textContent='Доступных списков пока нет.';grid.append(empty)}
}
let todoListsPolling=false,todoListsNextPollAt=Date.now()+5000;
function setTodoListsSync(type,text){const box=$('#todo-lists-sync'),label=$('#todo-lists-sync-text');if(!box||!label)return;box.classList.toggle('loading',type==='loading');box.classList.toggle('error',type==='error');label.textContent=text}
function resetTodoListsCountdown(){todoListsNextPollAt=Date.now()+5000}
async function loadTodoLists(force=false){
  if(!window.TODO_LISTS_PAGE||todoListsPolling||document.visibilityState==='hidden')return;
  todoListsPolling=true;setTodoListsSync('loading','AJAX · обновление…');
  try{const data=await api('/api/todo-lists',{method:'GET'});renderTodoLists(data.lists||[]);resetTodoListsCountdown();setTodoListsSync('ok','AJAX · 5с')}
  catch(e){if(e.status===401){window.location.href=appUrl('/login');return}setTodoListsSync('error','AJAX · ошибка');if(force)toast(e.message)}
  finally{todoListsPolling=false}
}
if(window.TODO_LISTS_PAGE){
  const listTimer=setInterval(loadTodoLists,5000);
  const listCountdown=setInterval(()=>{if(todoListsPolling||document.visibilityState==='hidden')return;const left=Math.max(0,Math.ceil((todoListsNextPollAt-Date.now())/1000));setTodoListsSync('ok',`AJAX · ${left}с`)},250);
  document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'){resetTodoListsCountdown();loadTodoLists(true)}else setTodoListsSync('ok','AJAX · пауза')});
  window.addEventListener('beforeunload',()=>{clearInterval(listTimer);clearInterval(listCountdown)});
}

function renderBoard(state,canWrite=false,founder=false){
  const board=$('#board');if(!board)return;board.innerHTML='';
  for(const c of state.categories){
    const col=document.createElement('section');col.className='column';col.dataset.category=c.id;
    col.innerHTML=`<div class="column-head"><b>${esc(c.title)}</b><span class="badge">${state.tasks.filter(t=>String(t.category_id)===String(c.id)).length}</span></div><div class="task-list"></div>`;
    const list=$('.task-list',col);
    for(const t of state.tasks.filter(t=>String(t.category_id)===String(c.id))){
      const card=document.createElement('article');card.className='task';card.dataset.id=t.id;card.draggable=canWrite;
      card.innerHTML=`<h4>${esc(t.title)}</h4>${t.description?`<p>${esc(t.description)}</p>`:''}<div class="tags">${(t.tags||[]).map(g=>`<span class="tag">${esc(g.name)}</span>`).join('')}</div><div class="task-meta"><span>${t.author?`@${esc(t.author)}`:''}</span>${canWrite?`<button type="button" class="btn small danger task-delete" title="Удалить задачу" aria-label="Удалить задачу">×</button>`:''}</div>`;
      if(canWrite){
        card.addEventListener('dblclick',async e=>{if(e.target.closest('button'))return;const title=prompt('Название задачи',t.title);if(title===null)return;const description=prompt('Описание задачи',t.description||'');const currentTags=(t.tags||[]).map(x=>x.name).join(', ');const tagNames=prompt('Теги через запятую',currentTags);const tagIds=tagNames===null?undefined:(BOARD.tags||[]).filter(x=>tagNames.split(',').map(v=>v.trim()).includes(x.name)).map(x=>x.id);try{await api(`/api/tasks/${t.id}`,{method:'PATCH',body:JSON.stringify({title,description,tags:tagIds})});await loadBoard(true);toast('Задача обновлена')}catch(err){toast(err.message)}});
        card.addEventListener('dragstart',()=>card.classList.add('dragging'));card.addEventListener('dragend',()=>card.classList.remove('dragging'));
        $('.task-delete',card).addEventListener('click',async e=>{e.stopPropagation();if(!confirm('Удалить задачу?'))return;try{await api(`/api/tasks/${t.id}`,{method:'DELETE'});await loadBoard(true);toast('Задача удалена')}catch(err){toast(err.message)}});
      }
      list.append(card);
    }
    if(canWrite){
      list.addEventListener('dragover',e=>e.preventDefault());
      list.addEventListener('drop',async e=>{e.preventDefault();const d=$('.task.dragging');if(!d)return;const id=d.dataset.id;list.append(d);try{await api(`/api/tasks/${id}/move`,{method:'PATCH',body:JSON.stringify({category_id:Number(c.id),position:list.children.length-1})});toast('Задача перемещена')}catch(err){toast(err.message);await loadBoard(true)}});
    }
    board.append(col);
  }
}
let polling=false,lastState=null,nextPollAt=Date.now()+5000;
function setSyncState(type,text){const box=$('#ajax-sync'),label=$('#ajax-sync-text');if(!box||!label)return;box.classList.toggle('loading',type==='loading');box.classList.toggle('error',type==='error');label.textContent=text}
function resetSyncCountdown(){nextPollAt=Date.now()+5000}
async function loadBoard(force=false){if(polling||document.visibilityState==='hidden')return;polling=true;if(window.BOARD)setSyncState('loading','AJAX · обновление…');try{let data;if(window.BOARD)data=await api(`/api/todo-lists/${BOARD.id}/state`,{method:'GET'});else if(window.PUBLIC_BOARD)data=await api(`/api/public/todos/${PUBLIC_BOARD.slug}/state`,{method:'GET'});if(data){lastState=data;renderBoard(data,!!window.BOARD?.canWrite,!!window.BOARD?.founder);resetSyncCountdown();if(window.BOARD)setSyncState('ok','AJAX · 5с')}}catch(e){if(window.BOARD&&(e.status===403||e.status===404)){setSyncState('error','Доступ отозван');toast('Доступ к To-do списку отозван');setTimeout(()=>{window.location.href=appUrl('/dashboard')},450);return}if(window.BOARD&&e.status===401){window.location.href=appUrl('/login');return}if(window.BOARD)setSyncState('error','AJAX · ошибка');if(force)toast(e.message)}finally{polling=false}}
if(window.BOARD||window.PUBLIC_BOARD){loadBoard(true);const timer=setInterval(loadBoard,5000);const countdown=setInterval(()=>{if(!window.BOARD||polling||document.visibilityState==='hidden')return;const left=Math.max(0,Math.ceil((nextPollAt-Date.now())/1000));setSyncState('ok',`AJAX · ${left}с`)},250);document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible'){resetSyncCountdown();loadBoard(true)}else if(window.BOARD)setSyncState('ok','AJAX · пауза')});window.addEventListener('beforeunload',()=>{clearInterval(timer);clearInterval(countdown)})}

$('#new-task-form')?.addEventListener('submit',async e=>{
  e.preventDefault();const form=e.currentTarget;if(!form.reportValidity())return;const f=new FormData(form);
  try{await api(`/api/todo-lists/${BOARD.id}/tasks`,{method:'POST',body:JSON.stringify({title:f.get('title'),description:f.get('description'),category_id:Number(f.get('category_id')),tags:f.getAll('tags[]').map(Number)})});form.reset();closeDialog('new-task');await loadBoard(true);toast('Задача создана')}catch(err){toast(err.message)}
});

function visibilityText(v){return {TEAM_ONLY:'Список доступен только разработчикам и основателю.',SELECTED_USERS:'Список доступен команде и отмеченным ниже обычным пользователям только для просмотра.',PUBLIC_READ:'Список доступен любому человеку по публичной ссылке только для просмотра.'}[v]||''}
function syncVisibilityUI(){const sel=$('#board-visibility');if(!sel)return;const v=sel.value;const help=$('#visibility-help');if(help)help.textContent=visibilityText(v);const viewers=$('#viewer-settings');if(viewers)viewers.classList.toggle('dimmed',v!=='SELECTED_USERS');}
$('#board-visibility')?.addEventListener('change',syncVisibilityUI);syncVisibilityUI();

function markSettingsDirty(){const s=$('#settings-status');if(s){s.textContent='Есть несохранённые изменения';s.classList.add('dirty')}}
$('#board-settings-form')?.addEventListener('input',e=>{if(!e.target.matches('[name="new_category_title"],[name="new_tag_name"]'))markSettingsDirty()});

$('#board-settings-form')?.addEventListener('submit',async e=>{
  e.preventDefault();const form=e.currentTarget;if(!form.reportValidity())return;const f=new FormData(form);const categoryTitles={},tagTitles={};
  $$('[data-cat-title]',form).forEach(i=>categoryTitles[i.dataset.catTitle]=i.value.trim());
  $$('[data-tag-title]',form).forEach(i=>tagTitles[i.dataset.tagTitle]=i.value.trim());
  const archived=!!BOARD.archived;
  const payload=archived?{title:f.get('title'),description:f.get('description'),category_titles:categoryTitles,tag_titles:tagTitles}:{title:f.get('title'),description:f.get('description'),visibility:f.get('visibility'),viewer_ids:f.getAll('viewer_ids[]').map(Number),category_titles:categoryTitles,tag_titles:tagTitles};
  const btn=$('#save-board-settings');if(btn)btn.disabled=true;
  try{const endpoint=archived?`/api/todo-lists/${BOARD.id}/archive-settings`:`/api/todo-lists/${BOARD.id}/settings`;const r=await api(endpoint,{method:'PATCH',body:JSON.stringify(payload)});if(!archived)BOARD.visibility=payload.visibility;BOARD.tags=$$('[data-tag-title]',form).map(i=>({id:Number(i.dataset.tagTitle),name:i.value.trim()}));const linkBox=$('#public-link-box'),link=$('#public-link');if(!archived){if(r.public_url){if(link)link.textContent=r.public_url;if(linkBox)linkBox.hidden=false}else if(linkBox)linkBox.hidden=true}const h1=document.querySelector('.page-head h1');const desc=document.querySelector('.page-head p.muted');if(h1)h1.textContent=payload.title;if(desc)desc.textContent=payload.description||'';const s=$('#settings-status');if(s){s.textContent='Все изменения сохранены';s.classList.remove('dirty')}await loadBoard(true);toast('Настройки сохранены')}catch(err){toast(err.message)}finally{if(btn)btn.disabled=false}
});

function addCategoryRow(category){const manager=$('#category-manager');if(!manager)return;const row=document.createElement('div');row.className='manager-row';row.dataset.categoryRow=category.id;row.innerHTML=`<label class="sr-only" for="category-title-${category.id}">Название категории</label><input id="category-title-${category.id}" name="category_titles[${category.id}]" value="${esc(category.title)}" data-cat-title="${category.id}" autocomplete="off"><button type="button" class="btn small danger" data-cat-delete="${category.id}">Удалить</button>`;manager.append(row);bindCategoryDelete(row.querySelector('[data-cat-delete]'));row.querySelector('input').addEventListener('input',markSettingsDirty)}
$('#add-category')?.addEventListener('click',async()=>{const input=$('#new-category-title');const title=input?.value.trim();if(!title){toast('Введите название категории');input?.focus();return}try{const r=await api(`/api/todo-lists/${BOARD.id}/categories`,{method:'POST',body:JSON.stringify({title})});addCategoryRow(r.category);const option=document.createElement('option');option.value=r.category.id;option.textContent=r.category.title;$('#task-category')?.append(option);input.value='';await loadBoard(true);toast('Категория добавлена')}catch(e){toast(e.message)}});
function bindCategoryDelete(button){button?.addEventListener('click',async()=>{const id=button.dataset.catDelete;const row=button.closest('[data-category-row]');button.disabled=true;try{await api(`/api/categories/${id}`,{method:'DELETE'});row?.remove();$(`#task-category option[value="${id}"]`)?.remove();await loadBoard(true);toast('Категория удалена')}catch(e){toast(e.message);button.disabled=false}})}
$$('[data-cat-delete]').forEach(bindCategoryDelete);

function addTagRow(tag){const manager=$('#tag-manager');if(!manager)return;const row=document.createElement('div');row.className='manager-row';row.dataset.tagRow=tag.id;row.innerHTML=`<label class="sr-only" for="tag-title-${tag.id}">Название тега</label><input id="tag-title-${tag.id}" name="tag_titles[${tag.id}]" value="${esc(tag.name)}" data-tag-title="${tag.id}" autocomplete="off"><button type="button" class="btn small danger" data-tag-delete="${tag.id}">Удалить</button>`;manager.append(row);bindTagDelete(row.querySelector('[data-tag-delete]'));row.querySelector('input').addEventListener('input',markSettingsDirty)}
$('#add-tag')?.addEventListener('click',async()=>{const input=$('#new-tag-name');const name=input?.value.trim();if(!name){toast('Введите название тега');input?.focus();return}try{const r=await api('/api/tags',{method:'POST',body:JSON.stringify({name})});addTagRow(r.tag);BOARD.tags.push(r.tag);const option=document.createElement('option');option.value=r.tag.id;option.textContent=r.tag.name;$('#task-tags')?.append(option);input.value='';toast('Тег добавлен')}catch(e){toast(e.message)}});
function bindTagDelete(button){button?.addEventListener('click',async()=>{const id=button.dataset.tagDelete;button.disabled=true;try{await api(`/api/tags/${id}`,{method:'DELETE'});$(`[data-tag-row="${id}"]`)?.remove();$(`#task-tags option[value="${id}"]`)?.remove();BOARD.tags=BOARD.tags.filter(t=>String(t.id)!==String(id));await loadBoard(true);toast('Тег удалён')}catch(e){toast(e.message);button.disabled=false}})}
$$('[data-tag-delete]').forEach(bindTagDelete);

$('#archive-board')?.addEventListener('click',async()=>{if(!confirm('Архивировать эту доску?'))return;try{await api(`/api/todo-lists/${BOARD.id}`,{method:'PATCH',body:JSON.stringify({is_archived:true})});location.href=appUrl('/todos')}catch(e){toast(e.message)}});

// Registration validation: readable feedback in the page instead of browser pattern errors.
const registerForm=$('#register-form'), registerLogin=$('#register-login'), loginWarning=$('#login-warning');
function validateRegisterLogin(showEmpty=false){if(!registerLogin||!loginWarning)return true;const value=registerLogin.value.trim();let message='';if(!value&&showEmpty)message='Введите логин.';else if(value&&/[^A-Za-z0-9_-]/.test(value))message='Логин содержит недопустимые символы. Используйте только латинские буквы, цифры, _ и -.';else if(value&&value.length<3)message='Логин должен содержать минимум 3 символа.';else if(value.length>32)message='Логин должен содержать не более 32 символов.';loginWarning.textContent=message;loginWarning.hidden=!message;registerLogin.classList.toggle('input-invalid',!!message);return !message}
registerLogin?.addEventListener('input',()=>validateRegisterLogin(false));registerLogin?.addEventListener('blur',()=>validateRegisterLogin(true));registerForm?.addEventListener('submit',e=>{if(!validateRegisterLogin(true)){e.preventDefault();registerLogin?.focus()}});

function passkeyDecode(value){const base64=String(value).replace(/-/g,'+').replace(/_/g,'/').padEnd(Math.ceil(value.length/4)*4,'=');return Uint8Array.from(atob(base64),c=>c.charCodeAt(0)).buffer}
function passkeyEncode(value){const bytes=new Uint8Array(value);let binary='';for(const byte of bytes)binary+=String.fromCharCode(byte);return btoa(binary).replace(/\+/g,'-').replace(/\//g,'_').replace(/=+$/,'')}
function passkeySupported(){if(!window.PublicKeyCredential){toast('Этот браузер не поддерживает Passkey.');return false}return true}

$('#passkey-login-button')?.addEventListener('click',async e=>{
  if(!passkeySupported())return;const button=e.currentTarget,login=$('#login-username');if(!login?.reportValidity())return;button.disabled=true;
  try{const options=(await api('/api/passkeys/login/options',{method:'POST',body:JSON.stringify({login:login.value})})).publicKey;options.challenge=passkeyDecode(options.challenge);options.allowCredentials=(options.allowCredentials||[]).map(item=>({...item,id:passkeyDecode(item.id)}));const credential=await navigator.credentials.get({publicKey:options});const result=await api('/api/passkeys/login',{method:'POST',body:JSON.stringify({rawId:passkeyEncode(credential.rawId),response:{clientDataJSON:passkeyEncode(credential.response.clientDataJSON),authenticatorData:passkeyEncode(credential.response.authenticatorData),signature:passkeyEncode(credential.response.signature),userHandle:credential.response.userHandle?passkeyEncode(credential.response.userHandle):null}})});location.href=result.redirect}
  catch(error){if(error.name!=='NotAllowedError')toast(error.message||'Не удалось войти с Passkey.')}
  finally{button.disabled=false}
});

$('#passkey-register-form')?.addEventListener('submit',async e=>{
  e.preventDefault();if(!passkeySupported())return;const form=e.currentTarget;if(!form.reportValidity())return;const button=$('button',form);button.disabled=true;
  try{const options=(await api('/api/passkeys/register/options',{method:'POST',body:'{}'})).publicKey;options.challenge=passkeyDecode(options.challenge);options.user.id=passkeyDecode(options.user.id);options.excludeCredentials=(options.excludeCredentials||[]).map(item=>({...item,id:passkeyDecode(item.id)}));const credential=await navigator.credentials.create({publicKey:options});await api('/api/passkeys/register',{method:'POST',body:JSON.stringify({name:new FormData(form).get('name'),rawId:passkeyEncode(credential.rawId),transports:credential.response.getTransports?.()||[],response:{clientDataJSON:passkeyEncode(credential.response.clientDataJSON),attestationObject:passkeyEncode(credential.response.attestationObject)}})});location.reload()}
  catch(error){if(error.name!=='NotAllowedError')toast(error.message||'Не удалось добавить Passkey.')}
  finally{button.disabled=false}
});

$$('[data-passkey-delete]').forEach(button=>button.addEventListener('click',async()=>{if(!confirm('Удалить этот Passkey?'))return;button.disabled=true;try{await api(`/api/passkeys/${button.dataset.passkeyDelete}`,{method:'DELETE'});button.closest('[data-passkey-row]')?.remove();toast('Passkey удалён')}catch(error){toast(error.message);button.disabled=false}}));
