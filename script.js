// script.js
const api = 'api.php';
const form = document.getElementById('ticketForm');
const ticketsList = document.getElementById('ticketsList');
const searchInput = document.getElementById('search');
const filterStatus = document.getElementById('filterStatus');
const exportCsvBtn = document.getElementById('exportCsv');
const clearBtn = document.getElementById('clearBtn');

async function fetchTickets(){
  const q = encodeURIComponent(searchInput.value.trim());
  const status = encodeURIComponent(filterStatus.value);
  const res = await fetch(`${api}?action=list&q=${q}&status=${status}`);
  const rows = await res.json();
  renderTickets(rows);
}

function renderTickets(rows){
  ticketsList.innerHTML = '';
  if (!rows || rows.length === 0) {
    ticketsList.innerHTML = '<div class="ticket"><em>No tickets yet</em></div>';
    return;
  }
  rows.forEach(t => {
    const div = document.createElement('div');
    div.className = 'ticket';
    const endAt = t.end_at ? new Date(t.end_at).toLocaleString() : '—';
    div.innerHTML = `
      <h3>${escapeHtml(t.title)} <span class="badge">${escapeHtml(t.priority)}</span></h3>
      <div class="meta">
        <div>Dept: ${escapeHtml(t.department)}</div>
        <div>Status: ${escapeHtml(t.status)}</div>
        <div>Created: ${escapeHtml(t.created_at)}</div>
        <div>End at: ${escapeHtml(endAt)}</div>
        ${t.github_url ? `<div>GitHub: <a href="${escapeAttr(t.github_url)}" target="_blank">link</a></div>` : ''}
      </div>
      <div class="desc">${escapeHtml(t.description || '')}</div>
      <div class="attachments">
        ${t.attachment ? `<a href="${api}?action=download&file=${encodeURIComponent(t.attachment)}" class="small-btn">Download Attachment</a>` : ''}
      </div>
      <div class="actions">
        <button class="small-btn" onclick="openEdit(${t.id})">Edit</button>
        <button class="small-btn" onclick="deleteTicket(${t.id})">Delete</button>
      </div>
    `;
    ticketsList.appendChild(div);
  });
}

function escapeHtml(s){ if(!s) return ''; return s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;'); }
function escapeAttr(s){ return s ? s.replace(/"/g,'&quot;') : s; }

form.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const data = new FormData(form);
  // submit to API
  const resp = await fetch(api + '?action=create', {method:'POST', body: data});
  const j = await resp.json();
  if (j.ok) {
    alert('Ticket created');
    form.reset();
    fetchTickets();
  } else {
    alert('Error: ' + (j.error || JSON.stringify(j)));
  }
});

async function deleteTicket(id){
  if (!confirm('Delete ticket?')) return;
  const formData = new FormData();
  formData.append('id', id);
  const resp = await fetch(api + '?action=delete', {method:'POST', body: formData});
  const j = await resp.json();
  if (j.ok) fetchTickets(); else alert('Error deleting');
}

async function openEdit(id){
  // get ticket and populate form for quick edit (basic)
  const res = await fetch(`${api}?action=get&id=${id}`);
  const t = await res.json();
  if (!t) return alert('Ticket not found');
  // populate form fields and change to update mode
  document.getElementById('title').value = t.title;
  document.getElementById('department').value = t.department;
  document.getElementById('description').value = t.description;
  document.getElementById('priority').value = t.priority;
  document.getElementById('github_url').value = t.github_url || '';
  if (t.end_at) {
    // convert to local datetime-local format
    const dt = new Date(t.end_at);
    const iso = dt.toISOString().slice(0,16);
    document.getElementById('end_at').value = iso;
  } else {
    document.getElementById('end_at').value = '';
  }

  // change form behavior to update
  if (!form.dataset.editId) {
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.id = 'editId';
    form.appendChild(idInput);
  }
  form.dataset.editId = id;
  document.getElementById('editId').value = id;

  // change submit handler to update
  const submit = form.querySelector('button[type="submit"]');
  submit.textContent = 'Update';
  submit.onclick = async (ev) => {
    ev.preventDefault();
    const fd = new FormData(form);
    const resp = await fetch(api + '?action=update', { method:'POST', body: fd });
    const j = await resp.json();
    if (j.ok) {
      alert('Updated');
      delete form.dataset.editId;
      document.getElementById('editId').remove();
      submit.textContent = 'Create';
      form.reset();
      fetchTickets();
    } else {
      alert('Error updating: ' + (j.error || JSON.stringify(j)));
    }
  };
}

clearBtn.addEventListener('click', ()=>{
  form.reset();
  // if in edit mode, restore create mode
  if (form.dataset.editId) {
    delete form.dataset.editId;
    const submit = form.querySelector('button[type="submit"]');
    if (document.getElementById('editId')) document.getElementById('editId').remove();
    submit.textContent = 'Create';
  }
});

searchInput.addEventListener('input', debounce(fetchTickets, 350));
filterStatus.addEventListener('change', fetchTickets);
exportCsvBtn.addEventListener('click', ()=> { window.location = api + '?action=export_csv'; });

function debounce(fn, delay){
  let t;
  return function(...args){ clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), delay); }
}

window.addEventListener('load', fetchTickets);
