(() => {
  document.documentElement.classList.add('js-ready');
  const csrf = () => document.querySelector('input[name="_csrf"]')?.value || '';
  const toast = (message, type = 'success') => {
    const node = document.createElement('div'); node.className = `ajax-toast ${type}`; node.textContent = message;
    document.body.appendChild(node); requestAnimationFrame(() => node.classList.add('visible')); setTimeout(() => node.remove(), 3600);
  };
  const setRichTextValue = (field, value) => {
    field.value = value;
    const editor = field.parentNode.querySelector('.rich-editor');
    if (editor) editor.innerHTML = value.replace(/\n/g, '<br>');
    field.dispatchEvent(new Event('input', { bubbles: true }));
  };
  const modal = document.createElement('dialog'); modal.className = 'ajax-modal'; modal.innerHTML = '<div class="modal-card"><button class="modal-close" type="button" aria-label="Close">×</button><div class="modal-content"></div></div>'; document.body.appendChild(modal);
  const linkDialog = document.createElement('dialog'); linkDialog.className = 'ajax-modal link-dialog'; linkDialog.innerHTML = '<form method="dialog" class="modal-card link-card"><h3>Add link</h3><p>Enter the full address for the selected text.</p><label>Link URL<input name="url" type="url" placeholder="https://example.com" required></label><div class="confirm-actions"><button class="button ghost" value="cancel" type="submit">Cancel</button><button class="button primary" value="apply" type="submit">Add link</button></div></form>'; document.body.appendChild(linkDialog);
  const confirmDialog = document.createElement('dialog'); confirmDialog.className = 'ajax-modal confirm-dialog'; confirmDialog.innerHTML = '<div class="modal-card confirm-card"><div class="confirm-icon">!</div><h3 class="confirm-title">Confirm action</h3><p class="confirm-message"></p><div class="confirm-actions"><button class="button ghost" type="button" data-confirm-cancel>Cancel</button><button class="button danger" type="button" data-confirm-accept>Continue</button></div></div>'; document.body.appendChild(confirmDialog);
  let confirmResolve = null;
  const finishConfirm = (answer) => { const resolve = confirmResolve; confirmResolve = null; if (confirmDialog.open) confirmDialog.close(); resolve?.(answer); };
  confirmDialog.querySelector('[data-confirm-cancel]').addEventListener('click', () => finishConfirm(false));
  confirmDialog.querySelector('[data-confirm-accept]').addEventListener('click', () => finishConfirm(true));
  confirmDialog.addEventListener('cancel', (event) => { event.preventDefault(); finishConfirm(false); });
  const confirmAction = (message, title = 'Confirm action') => new Promise((resolve) => { confirmResolve = resolve; confirmDialog.querySelector('.confirm-title').textContent = title; confirmDialog.querySelector('.confirm-message').textContent = message; confirmDialog.showModal(); confirmDialog.querySelector('[data-confirm-cancel]').focus(); });
  const requestLink = (editor) => new Promise((resolve) => {
    const selection = window.getSelection(); const range = selection?.rangeCount ? selection.getRangeAt(0).cloneRange() : null;
    const form = linkDialog.querySelector('form'); const input = form.elements.url; form.reset();
    linkDialog.addEventListener('close', () => {
      const apply = linkDialog.returnValue === 'apply'; const url = input.value.trim();
      if (apply && url && range) { const current = window.getSelection(); current.removeAllRanges(); current.addRange(range); editor.focus(); document.execCommand('createLink', false, url); }
      resolve(apply && Boolean(url));
    }, { once: true });
    linkDialog.showModal(); input.focus();
  });
  modal.querySelector('.modal-close').addEventListener('click', () => modal.close());
  let activePanel = null; let lastTrigger = null;
  const closePanel = (panel) => { if (!panel) return; panel.hidden = true; panel.classList.remove('is-modal'); panel.removeAttribute('aria-modal'); activePanel = null; lastTrigger?.focus(); };
  document.querySelectorAll('[data-open-panel]').forEach((button) => button.addEventListener('click', () => { const panel = document.getElementById(button.dataset.openPanel); if (panel) { lastTrigger=button;activePanel=panel;panel.hidden=false;panel.classList.add('is-modal');panel.setAttribute('role','dialog');panel.setAttribute('aria-modal','true');if(button.dataset.openPanel==='userCreatePanel'){const form=panel.querySelector('form');form?.reset();form?.querySelector('[name="id"]')?.setAttribute('value','0');form?.querySelector('[name="password"]')?.setAttribute('required','');panel.querySelector('#userPanelTitle').textContent='Create subordinate administrator';panel.querySelector('#userSaveButton').textContent='Create administrator';}panel.querySelector('input:not([type="hidden"]),textarea,select,button')?.focus(); } }));
  document.querySelectorAll('[data-close-panel]').forEach((button) => button.addEventListener('click', () => closePanel(document.getElementById(button.dataset.closePanel))));
  const activeNavigation = document.querySelector('.sidebar .nav-parent.is-active');
  if (activeNavigation) requestAnimationFrame(() => activeNavigation.scrollIntoView({ block: 'nearest', inline: 'nearest' }));
  document.querySelectorAll('[data-user-edit]').forEach((button) => button.addEventListener('click', () => { const open=document.querySelector('[data-open-panel="userCreatePanel"]');open?.click();const panel=document.getElementById('userCreatePanel');const form=panel?.querySelector('form');if(!form)return;form.querySelector('[name="id"]').value=button.dataset.id;form.querySelector('[name="username"]').value=button.dataset.username;form.querySelector('[name="email"]').value=button.dataset.email;form.querySelector('[name="display_name"]').value=button.dataset.name;form.querySelector('[name="role_id"]').value=button.dataset.role;const password=form.querySelector('[name="password"]');password.required=false;password.value='';panel.querySelector('#userPanelTitle').textContent='Edit administrator';panel.querySelector('#userSaveButton').textContent='Save changes'; }));
  document.querySelectorAll('[data-mail-edit]').forEach((button) => button.addEventListener('click', () => {
    const form=document.getElementById('mailTemplateForm'); if(!form)return;
    form.querySelector('[name="template_key"]').value=button.dataset.key||''; form.querySelector('[name="subject"]').value=button.dataset.subject||''; setRichTextValue(form.querySelector('[name="body"]'),button.dataset.body||'');
    const title=document.getElementById('mailTemplateFormTitle'); if(title)title.textContent='Edit template: '+(button.dataset.key||'');
    const cancelButton=document.getElementById('mailTemplateCancelButton'); if(cancelButton)cancelButton.hidden=false;
    form.scrollIntoView({behavior:'smooth',block:'start'});
    form.querySelector('[name="template_key"]').focus();
  }));
  document.getElementById('mailTemplateCancelButton')?.addEventListener('click', () => {
    const form=document.getElementById('mailTemplateForm'); form.reset();
    setRichTextValue(form.querySelector('[name="body"]'), '');
    const title=document.getElementById('mailTemplateFormTitle'); if(title)title.textContent='Mail templates';
    document.getElementById('mailTemplateCancelButton').hidden=true;
  });
  document.querySelectorAll('[data-open-panel="mailComposePanel"]').forEach((button) => button.addEventListener('click', () => { const form=document.getElementById('mailComposeForm'); form?.reset(); if(form){form.querySelector('[name="to_name"]').value='';form.querySelector('[name="in_reply_to"]').value='';} const title=document.getElementById('mailComposeTitle');if(title)title.textContent='Compose mail'; }));
  document.querySelectorAll('[data-mail-reply]').forEach((button) => button.addEventListener('click', () => {
    const trigger=document.querySelector('[data-open-panel="mailComposePanel"]'); trigger?.click();
    const form=document.getElementById('mailComposeForm'); if(!form)return;
    const subject=button.dataset.subject||'Website enquiry'; const date=button.dataset.date||''; const name=button.dataset.name||''; const quoted=(button.dataset.message||'').split('\n').map((line)=>'> '+line).join('\n');
    form.reset(); form.querySelector('[name="to"]').value=button.dataset.email||''; form.querySelector('[name="to_name"]').value=name; form.querySelector('[name="in_reply_to"]').value=button.dataset.id||''; form.querySelector('[name="subject"]').value=/^re:/i.test(subject)?subject:'Re: '+subject;
    setRichTextValue(form.querySelector('[name="body"]'), '\n\n---\nOn '+date+', '+name+' wrote:\n'+quoted);
    document.getElementById('mailComposeTitle').textContent='Reply to '+name;
  }));
  document.addEventListener('keydown', (event) => { if (event.key === 'Escape') { if (modal.open) modal.close(); else closePanel(activePanel); } });
  const enhanceRichText = (scope = document) => {
    scope.querySelectorAll('textarea[name="body"],textarea[name="bio"],textarea[name="answer"]').forEach((textarea) => {
      if (textarea.dataset.enhanced) return; textarea.dataset.enhanced = 'true';
      const toolbar = document.createElement('div'); toolbar.className = 'rich-toolbar';
      [['bold','B'],['italic','I'],['underline','U'],['formatBlock','H2'],['insertUnorderedList','•'],['insertOrderedList','1.'],['createLink','↗'],['removeFormat','×']].forEach(([command,label]) => { const button=document.createElement('button');button.type='button';button.textContent=label;button.title=command;button.addEventListener('click',async()=>{if(command==='createLink'){await requestLink(editor);sync();return;} editor.focus();document.execCommand(command,false,command==='formatBlock'?'h2':null);sync();});toolbar.appendChild(button); });
      const editor=document.createElement('div');editor.className='rich-editor';editor.contentEditable='true';editor.setAttribute('data-placeholder',textarea.getAttribute('placeholder')||'Write here...');editor.innerHTML=textarea.value;textarea.hidden=true;textarea.parentNode.insertBefore(toolbar,textarea);textarea.parentNode.insertBefore(editor,textarea);const sync=()=>{textarea.value=editor.innerHTML;};editor.addEventListener('input',sync);textarea.closest('form')?.addEventListener('submit',sync);
    });
  };
  const standaloneModules = new Set(['pages','posts','projects','portfolio','services','team','certifications','equipment','galleries']);
  const contentModule = (link) => new URL(link.href, location.href).searchParams.get('module') || '';
  enhanceRichText();
  const setMediaSelection = (picker, selections) => {
    const mode=picker.dataset.mediaMode;
    if(mode==='gallery') { const selected=picker.querySelector('[data-media-selected]'); if(!selected)return; const name=(picker.dataset.mediaName||'gallery')+'[]'; selected.innerHTML=''; selected.classList.toggle('is-empty',!selections.length); if(!selections.length){const hint=document.createElement('span');hint.className='media-picker-selected-empty';hint.dataset.mediaEmptyHint='true';hint.textContent='No images selected yet';selected.appendChild(hint);} selections.forEach(({value,src})=>{const chip=document.createElement('span');chip.className='selected-media-chip';const image=document.createElement('img');image.src=src;image.alt='';const input=document.createElement('input');input.type='hidden';input.name=name;input.value=value;input.dataset.mediaValue='true';chip.append(image,input);selected.appendChild(chip);}); return; }
    const item=selections[0]; if(!item)return; const input=picker.querySelector('input[data-media-value]');if(input)input.value=item.value;const preview=picker.querySelector('[data-media-preview]');if(preview){preview.innerHTML='';const image=document.createElement('img');image.src=item.src;image.alt='';preview.appendChild(image);preview.hidden=false;}const remove=picker.querySelector('[data-media-remove]');if(remove)remove.checked=false;
  };
  const openMediaLibrary = (picker) => {
    const options=Array.from(picker.querySelectorAll('[data-media-select]')); if(!options.length){toast('No images are available in the media library.','error');return;}
    const mode=picker.dataset.mediaMode; const current=new Set(mode==='gallery'?Array.from(picker.querySelectorAll('[data-media-selected] input')).map((input)=>input.value):[picker.querySelector('input[data-media-value]')?.value].filter(Boolean));
    modal.querySelector('.modal-content').innerHTML='<section class="library-picker"><div class="panel-head"><div><h3>Choose from media library</h3><p class="muted">'+(mode==='gallery'?'Select one or more images, then confirm your selection.':'Select an image for this field.')+'</p></div></div><div class="library-picker-grid"></div><div class="editor-save-bar"><button type="button" class="button primary" data-library-use>'+ (mode==='gallery'?'Use selected images':'Use selected image') +'</button><button type="button" class="button ghost" data-close-library>Cancel</button></div></section>';
    const grid=modal.querySelector('.library-picker-grid'); const selected=[];
    options.forEach((option)=>{const value=option.dataset.mediaSelect||'';const src=option.dataset.mediaSrc||'';const card=document.createElement('button');card.type='button';card.className='library-picker-card'+(current.has(value)?' is-selected':'');card.dataset.value=value;card.dataset.src=src;card.setAttribute('aria-pressed',String(current.has(value)));const image=option.querySelector('img')?.cloneNode(true);const label=option.querySelector('span')?.cloneNode(true);if(image)card.appendChild(image);if(label)card.appendChild(label);card.addEventListener('click',()=>{if(mode!=='gallery'){grid.querySelectorAll('.library-picker-card').forEach((item)=>{item.classList.remove('is-selected');item.setAttribute('aria-pressed','false');});card.classList.add('is-selected');card.setAttribute('aria-pressed','true');return;}card.classList.toggle('is-selected');card.setAttribute('aria-pressed',String(card.classList.contains('is-selected')));});grid.appendChild(card);});
    modal.querySelector('[data-library-use]').addEventListener('click',()=>{grid.querySelectorAll('.library-picker-card.is-selected').forEach((card)=>selected.push({value:card.dataset.value,src:card.dataset.src}));if(!selected.length){toast('Select at least one image.','error');return;}setMediaSelection(picker,selected);modal.close();});
    modal.querySelector('[data-close-library]').addEventListener('click',()=>modal.close()); modal.classList.add('library-modal'); modal.showModal();
  };
  const bindMediaPicker = (picker) => { if(picker.dataset.mediaBound)return;picker.dataset.mediaBound='true';picker.querySelector('[data-media-open]')?.addEventListener('click',()=>openMediaLibrary(picker));picker.querySelector('[data-media-remove]')?.addEventListener('change',(event)=>{if(event.target.checked){const input=picker.querySelector('input[data-media-value]');if(input)input.value='';const preview=picker.querySelector('[data-media-preview]');if(preview)preview.hidden=true;}}); };
  document.querySelectorAll('[data-media-picker]').forEach(bindMediaPicker);
  modal.addEventListener('close',()=>modal.classList.remove('library-modal'));
  document.querySelectorAll('[data-dropzone]').forEach((zone) => {
    const input = zone.querySelector('[data-drop-input]');
    if (!input) return;
    const label = zone.querySelector('[data-drop-label]');
    const update = () => { const count = input.files?.length || 0; if (count && label) label.textContent = count + ' file' + (count === 1 ? '' : 's') + ' selected'; zone.classList.toggle('has-files', count > 0); };
    ['dragenter', 'dragover'].forEach((eventName) => zone.addEventListener(eventName, (event) => { event.preventDefault(); zone.classList.add('is-dragging'); }));
    ['dragleave', 'drop'].forEach((eventName) => zone.addEventListener(eventName, (event) => { event.preventDefault(); zone.classList.remove('is-dragging'); }));
    zone.addEventListener('drop', (event) => { if (!event.dataTransfer?.files?.length) return; const transfer = new DataTransfer(); Array.from(event.dataTransfer.files).forEach((file) => transfer.items.add(file)); input.files = transfer.files; input.dispatchEvent(new Event('change', { bubbles: true })); update(); });
    input.addEventListener('change', update);
  });
  const activateDynamicMedia = (scope) => {
    scope.querySelectorAll('[data-media-picker]').forEach(bindMediaPicker);
    scope.querySelectorAll('[data-dropzone]').forEach((zone) => {
      const input=zone.querySelector('[data-drop-input]');if(!input||input.dataset.dropBound)return;input.dataset.dropBound='true';
      const label=zone.querySelector('[data-drop-label]');const update=()=>{const count=input.files?.length||0;if(count&&label)label.textContent=count+' file'+(count===1?'':'s')+' selected';zone.classList.toggle('has-files',count>0);};
      ['dragenter','dragover'].forEach((name)=>zone.addEventListener(name,(event)=>{event.preventDefault();zone.classList.add('is-dragging');}));['dragleave','drop'].forEach((name)=>zone.addEventListener(name,(event)=>{event.preventDefault();zone.classList.remove('is-dragging');}));zone.addEventListener('drop',(event)=>{if(!event.dataTransfer?.files?.length)return;const transfer=new DataTransfer();Array.from(event.dataTransfer.files).forEach((file)=>transfer.items.add(file));input.files=transfer.files;input.dispatchEvent(new Event('change',{bubbles:true}));update();});input.addEventListener('change',update);
    });
  };
  document.querySelectorAll('[data-select-all]').forEach((master) => {
    const scope = master.closest('.panel'); const rows = () => Array.from(scope?.querySelectorAll('[data-row-select]') || []);
    const toolbar = scope?.querySelector('[data-bulk-toolbar]'); const count = scope?.querySelector('[data-selected-count]');
    const refresh = () => { const selected = rows().filter((input) => input.checked); if (count) count.textContent = selected.length; if (toolbar) toolbar.hidden = selected.length === 0; master.checked = rows().length > 0 && selected.length === rows().length; master.indeterminate = selected.length > 0 && selected.length < rows().length; };
    master.addEventListener('change', () => { rows().forEach((input) => { input.checked = master.checked; }); refresh(); }); rows().forEach((input) => input.addEventListener('change', refresh)); refresh();
    scope?.querySelector('[data-bulk-delete]')?.addEventListener('click', async () => { const selected = rows().filter((input) => input.checked).map((input) => input.value); if (!selected.length || !(await confirmAction('Move ' + selected.length + ' selected item(s) to trash? You can restore them later.', 'Move selected items to trash'))) return; const data = new FormData(); const toolbarAction=scope.querySelector('[data-bulk-toolbar]')?.dataset.bulkAction||'content.bulk_delete'; const idName=scope.querySelector('[data-bulk-toolbar]')?.dataset.bulkAction==='users.bulk_delete'?'ids[]':scope.querySelector('[data-bulk-toolbar]')?.dataset.bulkIdsName||'ids[]'; data.set('action',toolbarAction); data.set('module',new URLSearchParams(location.search).get('module')||''); data.set('_csrf',csrf()); selected.forEach((id) => data.append(idName,id)); try { const response=await fetch('api.php',{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}}); const payload=await response.json(); if(!response.ok||!payload.ok) throw new Error(payload.message||'Unable to move selected items to trash.'); toast(payload.message); selected.forEach((id) => rows().find((input) => input.value === id)?.closest('[data-content-row],.media-card')?.remove()); refresh(); } catch(error) { toast(error.message,'error'); } });
  });
  document.querySelectorAll('[data-icon-picker]').forEach((picker) => picker.querySelectorAll('[data-icon-value]').forEach((button) => button.addEventListener('click', () => { picker.querySelector('input[type="hidden"]').value = button.dataset.iconValue || ''; picker.querySelectorAll('[data-icon-value]').forEach((item) => item.classList.toggle('is-selected', item === button)); })));
  document.querySelectorAll('input[name="category"]').forEach((input) => {
    const form = input.form; const source = `${location.search} ${form?.action || ''}`;
    if (!source.includes('team')) return;
    const select = document.createElement('select'); select.name = input.name; select.required = input.required;
    ['','Management Team','Executive Team','Technical Team','Operations Team','Other'].forEach((option) => { const node=document.createElement('option');node.value=option;node.textContent=option||'Choose team category';if(option===input.value)node.selected=true;select.appendChild(node); }); input.replaceWith(select);
  });
  document.querySelectorAll('a[href*="edit=0#editor"]').forEach((link) => link.addEventListener('click', async (event) => {
    if (standaloneModules.has(contentModule(link))) return;
    event.preventDefault(); const url = link.href.replace('content.php?', 'content-form.php?').replace('#editor', '');
    try { const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }); if (!response.ok) throw new Error('Unable to open the editor.'); modal.querySelector('.modal-content').innerHTML = await response.text(); enhanceRichText(modal); activateDynamicMedia(modal); modal.querySelector('[data-close-modal]')?.addEventListener('click', () => modal.close()); modal.querySelectorAll('input[name="category"]').forEach((input) => { const select=document.createElement('select');select.name=input.name;['','Management Team','Executive Team','Technical Team','Operations Team','Other'].forEach((option)=>{const node=document.createElement('option');node.value=option;node.textContent=option||'Choose team category';if(option===input.value)node.selected=true;select.appendChild(node);});input.replaceWith(select); }); modal.showModal(); modal.querySelector('input:not([type="hidden"]),textarea,select')?.focus(); } catch (error) { toast(error.message, 'error'); }
  }));
  document.querySelectorAll('a[href*="edit="]:not([href*="edit=0"])').forEach((link) => link.addEventListener('click', async (event) => {
    if (!link.href.includes('content.php?module=') || standaloneModules.has(contentModule(link))) return; event.preventDefault(); const url=link.href.replace('content.php?','content-form.php?');
    try { const response=await fetch(url,{headers:{'X-Requested-With':'XMLHttpRequest'}});if(!response.ok)throw new Error('Unable to open the editor.');modal.querySelector('.modal-content').innerHTML=await response.text();enhanceRichText(modal);activateDynamicMedia(modal);modal.querySelector('[data-close-modal]')?.addEventListener('click', () => modal.close());modal.showModal(); } catch(error){toast(error.message,'error');}
  }));
  document.querySelectorAll('form label').forEach((label) => {
    const field = label.querySelector('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), textarea');
    if (!field || field.hasAttribute('placeholder') || field.type === 'file') return;
    const text = Array.from(label.childNodes).filter((node) => node.nodeType === Node.TEXT_NODE).map((node) => node.textContent).join(' ').trim();
    if (text) field.placeholder = `Enter ${text.toLowerCase()}`;
  });
  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-modal-form]'); if (!form) return;
    event.preventDefault(); const button = form.querySelector('button'); if (button) button.disabled = true;
    try { const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } }); if (!response.ok) throw new Error('Unable to save this item.'); modal.close(); location.reload(); } catch (error) { toast(error.message, 'error'); if (button) button.disabled = false; }
  });
  document.querySelectorAll('form[onsubmit]').forEach((form) => { const match = (form.getAttribute('onsubmit') || '').match(/confirm\(['"]([^'"]+)['"]\)/); if (match) { form.removeAttribute('onsubmit'); form.dataset.confirm = match[1]; } });
  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-confirm]');
    if (!form) return;
    event.preventDefault(); event.stopImmediatePropagation();
    if (await confirmAction(form.dataset.confirm || 'Are you sure?', 'Confirm action')) form.submit();
  }, true);
  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-content-trash]');
    if (!button) return;
    event.preventDefault();
    if (!(await confirmAction('Move this item to trash? You can restore it later.', 'Move to trash'))) return;
    const form = button.closest('form');
    if (!form) return;
    let action = form.querySelector('input[name="action"]');
    if (!action) { action = document.createElement('input'); action.type = 'hidden'; action.name = 'action'; form.appendChild(action); }
    action.value = 'trash';
    form.submit();
  });
  document.querySelectorAll('[data-ajax-form]').forEach((form) => form.addEventListener('submit', async (event) => {
    event.preventDefault(); const button = form.querySelector('button[type="submit"],button:not([type])'); if (button) button.disabled = true;
    const data = new FormData(form); data.set('action', form.dataset.ajaxAction || 'settings.save'); data.set('_csrf', csrf());
    try { const response = await fetch('api.php', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json(); if (!response.ok || !payload.ok) throw new Error(payload.message || 'Request failed.'); toast(payload.message); if (form.dataset.reload === 'true') setTimeout(() => location.reload(), 350); } catch (error) { toast(error.message, 'error'); } finally { if (button) button.disabled = false; }
  }));
  document.querySelectorAll('[data-ajax-delete]').forEach((form) => form.addEventListener('submit', async (event) => {
    event.preventDefault(); if (!(await confirmAction(form.dataset.confirm || 'Delete this item?', 'Delete item'))) return; const data = new FormData(form); data.set('action', 'content.delete'); data.set('_csrf', csrf());
    try { const response = await fetch('api.php', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json(); if (!response.ok || !payload.ok) throw new Error(payload.message || 'Delete failed.'); form.closest('tr')?.remove(); toast(payload.message); } catch (error) { toast(error.message, 'error'); }
  }));
  document.querySelectorAll('form.inline:not([data-entity="user"]) input[name="action"][value="delete"]').forEach((input) => input.form?.addEventListener('submit', async (event) => {
    event.preventDefault(); if (!(await confirmAction('Delete this item?', 'Delete item'))) return;
    const form = input.form; const data = new FormData(form); data.set('action', 'content.delete'); data.set('module', new URLSearchParams(location.search).get('module') || ''); data.set('_csrf', csrf());
    try { const response = await fetch('api.php', { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest' } }); const payload = await response.json(); if (!response.ok || !payload.ok) throw new Error(payload.message || 'Delete failed.'); form.closest('tr')?.remove(); toast(payload.message); } catch (error) { toast(error.message, 'error'); }
  }));
  document.querySelectorAll('form.inline input[name="action"][value="delete"]').forEach((input) => input.form?.addEventListener('submit', async (event) => {
    if (input.form.dataset.entity !== 'user') return; event.preventDefault(); if (!(await confirmAction('Delete this administrator?', 'Delete administrator'))) return;
    const form=input.form,data=new FormData(form);data.set('action','user.delete');data.set('_csrf',csrf());
    try{const response=await fetch('api.php',{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});const payload=await response.json();if(!response.ok||!payload.ok)throw new Error(payload.message||'Delete failed.');form.closest('tr')?.remove();toast(payload.message);}catch(error){toast(error.message,'error');}
  }));
  document.querySelectorAll('[data-role-delete]').forEach((button) => button.addEventListener('click', async () => {
    if (!(await confirmAction('Delete this role?', 'Delete role'))) return; const data=new FormData();data.set('action','role.delete');data.set('role_id',button.dataset.roleDelete);data.set('_csrf',csrf());
    try{const response=await fetch('api.php',{method:'POST',body:data,headers:{'X-Requested-With':'XMLHttpRequest'}});const payload=await response.json();if(!response.ok||!payload.ok)throw new Error(payload.message||'Unable to delete role.');toast(payload.message);setTimeout(()=>location.href='roles.php',350);}catch(error){toast(error.message,'error');}
  }));
})();
