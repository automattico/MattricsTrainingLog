// ── CONFIG loaded from config.js (gitignored) ────────────────────────────────
const SHEET_URL = (window.MATTRICS_CONFIG && window.MATTRICS_CONFIG.SHEET_URL) || "";
const API_KEY   = (window.MATTRICS_CONFIG && window.MATTRICS_CONFIG.API_KEY)   || "";
// ─────────────────────────────────────────────────────────────────────────────

const TYPES = {
  Canoeing:      { icon:'🛶', color:'var(--canoe)',   label:'Canoeing'  },
  Canoe:         { icon:'🛶', color:'var(--canoe)',   label:'Canoeing'  },
  Run:           { icon:'🏃', color:'var(--run)',     label:'Run'       },
  WeightTraining:{ icon:'🏋️', color:'var(--lift)',   label:'Weights'   },
  Workout:       { icon:'💪', color:'var(--workout)', label:'Workout'   },
  Yoga:          { icon:'🧘', color:'var(--yoga)',    label:'Yoga'      },
  Ride:          { icon:'🚴', color:'var(--ride)',    label:'Ride'      },
  Walk:          { icon:'🚶', color:'var(--walk)',    label:'Walk'      },
  Hike:          { icon:'⛰️', color:'var(--hike)',   label:'Hike'      },
  WaterSport:    { icon:'🚣', color:'var(--water)',   label:'Water'     },
  Rowing:        { icon:'🚣', color:'var(--row)',     label:'Rowing'    },
  Surfing:       { icon:'🏄', color:'var(--surf)',    label:'Surf'      },
};
function tc(type) { return TYPES[type] || { icon:'⚡', color:'var(--muted)', label: type }; }
function canonicalType(type) {
  if (type === 'Canoe') return 'Canoeing';
  if (type === 'WaterSport') return 'Rowing';
  return type;
}

// ── State ──────────────────────────────────────────────────────────────────────
let allData    = [];   // every row from the sheet, sorted newest first
let windowDays = 7;    // current time window
let typeFilter = 'All';
let groupBy    = 'week';

function escAttr(s) {
  return String(s || '')
    .replace(/&/g,'&amp;')
    .replace(/"/g,'&quot;')
    .replace(/'/g,'&#39;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;');
}

// ── Fetch ──────────────────────────────────────────────────────────────────────
async function fetchData() {
  showLoading();
  if (!SHEET_URL || SHEET_URL === "PASTE_YOUR_WEB_APP_URL_HERE" || SHEET_URL === "YOUR_GOOGLE_APPS_SCRIPT_URL_HERE") {
    showError(
      "No Sheet URL configured.\n\nCreate config.js next to dashboard.html by copying config.example.js, then set MATTRICS_CONFIG.SHEET_URL to your Google Apps Script Web App URL.\n\nSee README.md and apps-script/Code.gs for setup instructions."
    );
    return;
  }
  try {
    const res = await fetch(SHEET_URL, { redirect: 'follow' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch { throw new Error("Response was not valid JSON. Check your Apps Script doGet()."); }
    if (json.error) throw new Error(json.error);
    allData = json.rows
      .filter(r => r.Date && r.Type)
      .sort((a,b) => new Date(b.Date) - new Date(a.Date));
    typeFilter = 'All';
    showApp();
    renderAll();
  } catch(e) {
    const msg = String(e && e.message ? e.message : e);
    const isCors =
      msg === 'Failed to fetch' ||
      msg === 'Load failed' ||
      msg.includes('NetworkError') ||
      msg.includes('CORS') ||
      msg.includes('fetch');
    showError(isCors
      ? "Browser blocked the request.\n\nYour Apps Script endpoint is live, but some browsers block fetches from a local file. Try opening this in Chrome, or serve this folder on localhost instead of opening dashboard.html via file://.\n\nIf needed, redeploy Apps Script as a Web App with Execute as: Me and Access: Anyone."
      : "Could not load data.\n\n" + msg);
  }
}

// ── Loading / Error helpers ────────────────────────────────────────────────────
function showLoading() {
  document.getElementById('loadScreen').classList.remove('hidden');
  document.getElementById('app').classList.remove('visible');
  document.getElementById('app').style.display = 'none';
  document.getElementById('loadSpinner').style.display = 'block';
  document.getElementById('loadMsg').style.display = 'block';
  document.getElementById('errorBox').style.display = 'none';
}
function showError(msg) {
  document.getElementById('loadSpinner').style.display = 'none';
  document.getElementById('loadMsg').style.display = 'none';
  document.getElementById('errorMsg').textContent = msg;
  document.getElementById('errorBox').style.display = 'flex';
}
function showApp() {
  document.getElementById('loadScreen').classList.add('hidden');
  document.getElementById('app').style.display = 'block';
  requestAnimationFrame(() => document.getElementById('app').classList.add('visible'));
}
function showSetupHelp() {
  document.getElementById('errorMsg').textContent =
    "SETUP STEPS:\n\n1. Open your Google Sheet\n2. Extensions -> Apps Script\n3. Paste the contents of Code.gs\n4. Deploy -> New Deployment -> Web App\n5. Execute as: Me | Access: Anyone\n6. Copy the Web App URL\n7. Copy config.example.js to config.js\n8. Set MATTRICS_CONFIG.SHEET_URL in config.js";
}

// ── Window filter ──────────────────────────────────────────────────────────────
function setWindow(days, el) {
  windowDays = days;
  typeFilter = 'All'; // reset type filter when changing window
  document.querySelectorAll('.window-btn').forEach(b => b.classList.remove('active'));
  el.classList.add('active');
  renderAll();
}

function getWindowedData() {
  if (windowDays === 0) return allData;
  const cutoff = new Date();
  cutoff.setDate(cutoff.getDate() - windowDays);
  cutoff.setHours(0,0,0,0);
  return allData.filter(a => new Date(a.Date) >= cutoff);
}

// ── Render all ─────────────────────────────────────────────────────────────────
function renderAll() {
  const windowed = getWindowedData();
  renderContextBar(windowed);
  renderHeader(windowed);
  renderFilters(windowed);
  renderFeed(windowed);
  renderTimeline(windowed);
  renderInsights(windowed);
  renderAiPreview();
}

// ── Context bar ────────────────────────────────────────────────────────────────
function renderContextBar(data) {
  if (!data.length) { document.getElementById('contextBar').textContent = 'No activities in this window.'; return; }
  const newest = data[0].Date, oldest = data[data.length-1].Date;
  const label = windowDays === 0 ? 'All time' : `Last ${windowDays} day${windowDays>1?'s':''}`;
  document.getElementById('contextBar').innerHTML =
    `<span class="context-period">${label}</span><span class="context-dot">·</span>${data.length} sessions<span class="context-dot">·</span>${fmtShort(oldest)} → ${fmtShort(newest)}`;
}

// ── Header stats ───────────────────────────────────────────────────────────────
function renderHeader(data) {
  const km  = data.reduce((s,a)=>s+(parseFloat(a['Distance (km)'])||0),0);
  const min = data.reduce((s,a)=>s+(parseFloat(a['Duration (min)'])||0),0);
  document.getElementById('headerStats').innerHTML = `
    <div class="hstat"><div class="hstat-val">${data.length}</div><div class="hstat-lab">Sessions</div></div>
    <div class="hstat"><div class="hstat-val">${km.toFixed(0)} km</div><div class="hstat-lab">Distance</div></div>
    <div class="hstat"><div class="hstat-val">${Math.round(min/60)} h</div><div class="hstat-lab">Time</div></div>
  `;
}

// ── Type filters ───────────────────────────────────────────────────────────────
function renderFilters(data) {
  const counts = {};
  data.forEach(a => {
    const type = canonicalType(a.Type);
    counts[type] = (counts[type] || 0) + 1;
  });
  const sorted = Object.entries(counts).sort((a,b)=>b[1]-a[1]);
  const allOn = typeFilter === 'All';
  let html = `<button class="filter-pill ${allOn?'on':''}" onclick="setFilter('All','${typeFilter}')" style="${allOn?'background:#fff;border-color:#fff;':'border-color:var(--border2)'}">
    All <span class="filter-count">${data.length}</span></button>`;
  sorted.forEach(([type, n]) => {
    const cfg = tc(type), on = typeFilter === type;
    html += `<button class="filter-pill ${on?'on':''}" onclick="setFilter('${type}')" style="${on?`background:${cfg.color};border-color:${cfg.color};`:''}">
      ${cfg.icon} ${cfg.label} <span class="filter-count">${n}</span></button>`;
  });
  document.getElementById('filterRow').innerHTML = html;
}

function setFilter(f) {
  typeFilter = f;
  const windowed = getWindowedData();
  renderFilters(windowed);
  renderFeed(applyTypeFilter(windowed));
}

function applyTypeFilter(data) {
  if (typeFilter === 'All') return data;
  return data.filter(a => canonicalType(a.Type) === typeFilter);
}

// ── Metric display per type ────────────────────────────────────────────────────
function cardMetrics(a) {
  const km    = parseFloat(a['Distance (km)'])    || 0;
  const min   = parseFloat(a['Duration (min)'])   || 0;
  const hr    = parseFloat(a['Avg HR'])            || 0;
  const elev  = parseFloat(a['Elevation Gain (m)'])|| 0;
  const pace  = parseFloat(a['Avg Pace (min/km)']) || 0;
  const cad   = parseFloat(a['Avg Cadence'])       || 0;
  const speed = parseFloat(a['Avg Speed (km/h)'])  || 0;
  const m = [];
  switch(a.Type) {
    case 'Canoeing': case 'Canoe':
      if(km)   m.push({val:km.toFixed(1),  lab:'km',      color:'var(--canoe)'});
      if(min)  m.push({val:fmt(min),       lab:'time',    color:'var(--text)' });
      if(elev) m.push({val:elev+'m',       lab:'elev',    color:'var(--muted)'});
      if(hr)   m.push({val:hr.toFixed(0),  lab:'avg♥',   color:'var(--muted)'});
      break;
    case 'Run':
      if(km)   m.push({val:km.toFixed(2),  lab:'km',      color:'var(--run)'  });
      if(pace) m.push({val:pace.toFixed(1),lab:'min/km',  color:'var(--text)' });
      if(hr)   m.push({val:hr.toFixed(0),  lab:'avg♥',   color:'var(--muted)'});
      if(elev) m.push({val:elev+'m',       lab:'elev',    color:'var(--muted)'});
      break;
    case 'Hike':
      if(km)   m.push({val:km.toFixed(1),  lab:'km',      color:'var(--hike)' });
      if(elev) m.push({val:elev+'m',       lab:'gain',    color:'var(--hike)' });
      if(min)  m.push({val:fmt(min),       lab:'time',    color:'var(--text)' });
      if(hr)   m.push({val:hr.toFixed(0),  lab:'avg♥',   color:'var(--muted)'});
      break;
    case 'Walk':
      if(km)   m.push({val:km.toFixed(1),  lab:'km',      color:'var(--walk)' });
      if(min)  m.push({val:fmt(min),       lab:'time',    color:'var(--text)' });
      if(elev) m.push({val:elev+'m',       lab:'gain',    color:'var(--muted)'});
      break;
    case 'Ride':
      if(km)   m.push({val:km.toFixed(1),  lab:'km',      color:'var(--ride)' });
      if(speed)m.push({val:speed.toFixed(1),lab:'km/h',   color:'var(--text)' });
      if(min)  m.push({val:fmt(min),       lab:'time',    color:'var(--muted)'});
      if(hr)   m.push({val:hr.toFixed(0),  lab:'avg♥',   color:'var(--muted)'});
      break;
    case 'Rowing': case 'WaterSport':
      if(km)   m.push({val:km.toFixed(2),  lab:'km',      color:'var(--row)'  });
      if(min)  m.push({val:fmt(min),       lab:'time',    color:'var(--text)' });
      if(hr)   m.push({val:hr.toFixed(0),  lab:'avg♥',   color:'var(--muted)'});
      if(cad)  m.push({val:cad.toFixed(0), lab:'s/m',     color:'var(--muted)'});
      break;
    case 'Yoga':
      if(min)  m.push({val:fmt(min),       lab:'time',    color:'var(--yoga)' });
      if(hr)   m.push({val:hr.toFixed(0),  lab:'avg♥',   color:'var(--muted)'});
      break;
    case 'Surfing':
      if(min)  m.push({val:fmt(min),       lab:'time',    color:'var(--surf)' });
      break;
    default:
      if(min)  m.push({val:fmt(min),       lab:'time',    color:'var(--lift)' });
      if(hr)   m.push({val:hr.toFixed(0),  lab:'avg♥',   color:'var(--muted)'});
  }
  return m;
}

function detailFacts(a) {
  const facts = [];
  const add = (val, lab) => { if (val !== '' && val !== null && val !== undefined) facts.push({ val, lab }); };
  const km = parseFloat(a['Distance (km)']) || 0;
  const min = parseFloat(a['Duration (min)']) || 0;
  const elev = parseFloat(a['Elevation Gain (m)']) || 0;
  const hr = parseFloat(a['Avg HR']) || 0;
  const maxHr = parseFloat(a['Max HR']) || 0;
  const pace = parseFloat(a['Avg Pace (min/km)']) || 0;
  const speed = parseFloat(a['Avg Speed (km/h)']) || 0;
  const cad = parseFloat(a['Avg Cadence']) || 0;
  if (km) add(km.toFixed(km >= 10 ? 1 : 2) + ' km', 'Distance');
  if (min) add(fmt(min), 'Duration');
  if (elev) add(elev + ' m', 'Elevation');
  if (hr) add(hr.toFixed(0), 'Avg HR');
  if (maxHr) add(maxHr.toFixed(0), 'Max HR');
  if (pace) add(pace.toFixed(1), 'Avg pace');
  if (speed) add(speed.toFixed(1) + ' km/h', 'Avg speed');
  if (cad) add(cad.toFixed(0), 'Cadence');
  if (a['Device Name']) add(a['Device Name'], 'Device');
  return facts;
}

function parseHevyDescription(desc) {
  const text = (desc || '').trim();
  if (!text.startsWith('Logged with Hevy')) return null;
  const blocks = text.replace(/^Logged with Hevy\s*/,'').trim().split(/\n\s*\n/).map(b => b.trim()).filter(Boolean);
  return blocks.map(block => {
    const lines = block.split('\n').map(line => line.trim()).filter(Boolean);
    if (!lines.length) return null;
    return { name: lines[0], sets: lines.slice(1) };
  }).filter(Boolean);
}

function openDetail(activityId) {
  const a = allData.find(row => String(row['Activity ID raw'] || row['Activity ID']) === String(activityId));
  if (!a) return;
  const cfg = tc(a.Type);
  const metrics = cardMetrics(a);
  const facts = detailFacts(a);
  const notes = (a.Description || '').trim();
  const hevy = parseHevyDescription(notes);

  document.getElementById('detailKicker').textContent = `${cfg.label} session`;
  document.getElementById('detailKicker').style.color = cfg.color;
  document.getElementById('detailTitle').textContent = a.Name || cfg.label;
  document.getElementById('detailDate').textContent = fmtDate(a.Date);
  document.getElementById('detailMetrics').innerHTML = metrics.map(m => `
    <div class="metric">
      <div class="metric-val" style="color:${m.color}">${m.val}</div>
      <div class="metric-lab">${m.lab}</div>
    </div>`).join('');
  document.getElementById('detailFacts').innerHTML = facts.map(f => `
    <div class="detail-fact">
      <div class="detail-fact-val">${esc(f.val)}</div>
      <div class="detail-fact-lab">${esc(f.lab)}</div>
    </div>`).join('');
  document.getElementById('detailFactsSection').style.display = facts.length ? 'block' : 'none';

  if (hevy && hevy.length) {
    document.getElementById('detailWorkoutList').innerHTML = hevy.map(ex => `
      <div class="hevy-exercise">
        <div class="hevy-ex-name">${esc(ex.name)}</div>
        ${ex.sets.map(set => `<div class="hevy-set">${esc(set)}</div>`).join('')}
      </div>`).join('');
    document.getElementById('detailWorkoutSection').style.display = 'block';
  } else {
    document.getElementById('detailWorkoutList').innerHTML = '';
    document.getElementById('detailWorkoutSection').style.display = 'none';
  }

  const cleanNotes = hevy ? '' : notes;
  if (cleanNotes) {
    document.getElementById('detailNotes').textContent = cleanNotes;
    document.getElementById('detailNotesSection').style.display = 'block';
  } else {
    document.getElementById('detailNotes').textContent = '';
    document.getElementById('detailNotesSection').style.display = 'none';
  }

  document.getElementById('detailOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDetail(event) {
  if (event && event.target && event.target !== event.currentTarget) return;
  document.getElementById('detailOverlay').classList.remove('open');
  document.body.style.overflow = '';
}

// ── Feed ───────────────────────────────────────────────────────────────────────
function renderFeed(data) {
  if (!data) data = applyTypeFilter(getWindowedData());
  if (!data.length) {
    document.getElementById('cardList').innerHTML =
      `<div class="empty-window"><div class="empty-window-icon">🏖️</div>No activities in this window.<br>Try a wider time range.</div>`;
    return;
  }
  document.getElementById('cardList').innerHTML = data.map(a => {
    const cfg = tc(a.Type);
    const metrics = cardMetrics(a);
    const desc = (a.Description || '').trim();
    const cleanDesc = desc.startsWith('Logged with Hevy') ? '' : desc.length>240 ? desc.slice(0,240)+'…' : desc;
    const activityId = escAttr(a['Activity ID raw'] || a['Activity ID'] || a.Name || '');
    return `<div class="a-card" style="border-left-color:${cfg.color}">
      <div class="a-card-stripe" style="background:${cfg.color}"></div>
      <button class="a-card-body a-card-btn" onclick="openDetail('${activityId}')" aria-label="Open details for ${escAttr(a.Name || cfg.label)}">
        <div class="a-card-top">
          <div class="a-card-left">
            <div class="a-card-icon">${cfg.icon}</div>
            <div class="a-card-main">
              <div class="a-card-name ${a.Type==='Yoga'?'yoga-card':''}">${esc(a.Name)}</div>
              <div class="a-card-date">${fmtDate(a.Date)}</div>
            </div>
          </div>
          ${metrics.length ? `<div class="a-card-metrics">${metrics.map(m=>`
            <div class="metric">
              <div class="metric-val" style="color:${m.color}">${m.val}</div>
              <div class="metric-lab">${m.lab}</div>
            </div>`).join('')}</div>` : ''}
        </div>
        ${cleanDesc ? `<div class="a-card-desc">${esc(cleanDesc)}</div>` : ''}
      </button>
    </div>`;
  }).join('');
}

// ── Timeline ───────────────────────────────────────────────────────────────────
function renderTimeline(data) {
  if (!data) data = getWindowedData();
  const groups = {};
  data.forEach(a => {
    const k = groupBy==='week' ? weekStart(a.Date) : a.Date.slice(0,7);
    (groups[k]=groups[k]||[]).push(a);
  });
  if (!Object.keys(groups).length) {
    document.getElementById('tlContent').innerHTML =
      `<div class="empty-window"><div class="empty-window-icon">📭</div>No activities in this window.</div>`;
    return;
  }
  document.getElementById('tlContent').innerHTML = Object.entries(groups)
    .sort((a,b)=>b[0].localeCompare(a[0]))
    .map(([key, acts]) => {
      const label = groupBy==='week'
        ? formatWeekRange(key)
        : new Date(key+'-01').toLocaleDateString('en-GB',{month:'long',year:'numeric'});
      const km  = acts.reduce((s,a)=>s+(parseFloat(a['Distance (km)'])||0),0);
      const min = acts.reduce((s,a)=>s+(parseFloat(a['Duration (min)'])||0),0);
      const icons = [...new Set(acts.map(a=>tc(a.Type).icon))].join(' ');
      const tiles = acts.map(a => {
        const cfg = tc(a.Type);
        const km2  = parseFloat(a['Distance (km)'])||0;
        const min2 = parseFloat(a['Duration (min)'])||0;
        return `<div class="tl-mini" style="border-top-color:${cfg.color}">
          <div class="tl-mini-icon">${cfg.icon}</div>
          <div class="tl-mini-name">${esc(a.Name.length>28?a.Name.slice(0,28)+'…':a.Name)}</div>
          <div class="tl-mini-stat" style="color:${cfg.color}">${km2>0?km2.toFixed(1)+' km':fmt(min2)}</div>
          <div class="tl-mini-date">${fmtShort(a.Date)}</div>
        </div>`;
      }).join('');
      return `<div class="tl-period">
        <div class="tl-period-header">
          <div class="tl-period-title">${label} <span style="font-size:13px;opacity:0.45">${icons}</span></div>
          <div class="tl-period-meta">${acts.length} sessions${km>0?' · '+km.toFixed(0)+' km':''} · ${fmt(min)}</div>
        </div>
        <div class="tl-grid">${tiles}</div>
      </div>`;
    }).join('');
}

function setGroup(g, el) {
  groupBy = g;
  document.querySelectorAll('.tl-sw-btn').forEach(b=>b.classList.remove('active'));
  el.classList.add('active');
  renderTimeline();
}

// ── Insights ───────────────────────────────────────────────────────────────────
function renderInsights(data) {
  if (!data) data = getWindowedData();
  const a = data;
  if (!a.length) {
    document.getElementById('insightsGrid').innerHTML =
      `<div class="empty-window" style="grid-column:1/-1"><div class="empty-window-icon">📊</div>No data in this window.</div>`;
    return;
  }

  const counts = {};
  a.forEach(x => counts[x.Type]=(counts[x.Type]||0)+1);
  const topTypes = Object.entries(counts).sort((a,b)=>b[1]-a[1]).slice(0,8);
  const maxC = topTypes[0][1];

  const canoes   = a.filter(x=>x.Type==='Canoeing'||x.Type==='Canoe');
  const canoeKm  = canoes.reduce((s,x)=>s+(parseFloat(x['Distance (km)'])||0),0);
  const runs     = a.filter(x=>x.Type==='Run');
  const runKm    = runs.reduce((s,x)=>s+(parseFloat(x['Distance (km)'])||0),0);
  const hikes    = a.filter(x=>x.Type==='Hike');
  const hikKm    = hikes.reduce((s,x)=>s+(parseFloat(x['Distance (km)'])||0),0);
  const hikElev  = hikes.reduce((s,x)=>s+(parseFloat(x['Elevation Gain (m)'])||0),0);
  const yoga     = a.filter(x=>x.Type==='Yoga');
  const yogaMin  = yoga.reduce((s,x)=>s+(parseFloat(x['Duration (min)'])||0),0);
  const halfM    = a.find(x=>x.Type==='Run'&&parseFloat(x['Distance (km)'])>=21);

  const byM = {};
  a.forEach(x=>{const m=x.Date.slice(0,7);byM[m]=(byM[m]||0)+1;});
  const bM = Object.entries(byM).sort((a,b)=>b[1]-a[1])[0];

  // streak within window
  const days = [...new Set(a.map(x=>x.Date.slice(0,10)))].sort();
  let maxS=1, curS=1;
  for(let i=1;i<days.length;i++){
    const d=(new Date(days[i])-new Date(days[i-1]))/86400000;
    curS=d===1?curS+1:1; if(curS>maxS)maxS=curS;
  }

  const winLabel = windowDays===0 ? 'all time' : `last ${windowDays}d`;

  document.getElementById('insightsGrid').innerHTML = `
    <div class="ins-card">
      <div class="ins-label">Activity mix · ${winLabel}</div>
      <div class="bar-group">
        ${topTypes.map(([type,n])=>{
          const cfg=tc(type);
          return `<div class="bar-row">
            <div class="bar-name">${cfg.icon} ${cfg.label}</div>
            <div class="bar-track"><div class="bar-fill" style="width:${(n/maxC*100).toFixed(0)}%;background:${cfg.color}"></div></div>
            <div class="bar-num">${n}</div>
          </div>`;
        }).join('')}
      </div>
    </div>

    <div class="ins-card">
      <div class="ins-label">Distance · ${winLabel}</div>
      ${canoeKm>0?`<div class="ins-big" style="color:var(--canoe)">${canoeKm.toFixed(0)}<span style="font-size:18px"> km</span></div>
      <div class="ins-big-sub">paddled · ${canoes.length} canoe session${canoes.length!==1?'s':''}</div>`:''}
      ${runKm>0?`<div style="margin-top:${canoeKm>0?14:0}px">
        <div class="ins-big" style="color:var(--run);font-size:${canoeKm>0?28:40}px">${runKm.toFixed(0)}<span style="font-size:16px"> km</span></div>
        <div class="ins-big-sub">run · ${runs.length} session${runs.length!==1?'s':''}</div>
      </div>`:''}
      ${hikKm>0?`<div style="margin-top:14px">
        <div class="ins-big" style="color:var(--hike);font-size:${canoeKm>0||runKm>0?24:40}px">${hikKm.toFixed(0)}<span style="font-size:14px"> km</span></div>
        <div class="ins-big-sub">hiked${hikElev>0?' · '+hikElev.toFixed(0)+'m gain':''}</div>
      </div>`:''}
      ${!canoeKm&&!runKm&&!hikKm?`<div class="ins-big-sub" style="margin-top:8px">No distance-based activities in this window.</div>`:''}
    </div>

    <div class="ins-card">
      <div class="ins-label">Highlights</div>
      <div class="highlight-list">
        ${yoga.length?`<div class="hl-item">
          <div class="hl-val" style="color:var(--yoga)">${yoga.length}× yoga</div>
          <div class="hl-desc">${fmt(yogaMin)} total · best recovery tool in the log</div>
        </div>`:''}
        <div class="hl-item">
          <div class="hl-val">${maxS} day${maxS!==1?'s':''}</div>
          <div class="hl-desc">Longest active streak in this window</div>
        </div>
        ${halfM?`<div class="hl-item">
          <div class="hl-val" style="color:var(--run)">${parseFloat(halfM['Distance (km)']).toFixed(1)} km</div>
          <div class="hl-desc">${esc(halfM.Name)} · ${fmtShort(halfM.Date)}</div>
        </div>`:''}
        ${bM?`<div class="hl-item">
          <div class="hl-val">${new Date(bM[0]+'-01').toLocaleDateString('en-GB',{month:'short',year:'numeric'})}</div>
          <div class="hl-desc">Most active month (${bM[1]} sessions)</div>
        </div>`:''}
      </div>
    </div>

    <div class="ins-card">
      <div class="ins-label">Training balance · ${winLabel}</div>
      <div class="bar-group">
        ${[
          ['Strength & Gym', a.filter(x=>x.Type==='WeightTraining'||x.Type==='Workout').length, 'var(--lift)'],
          ['Paddling',       a.filter(x=>['Canoeing','Canoe','WaterSport'].includes(x.Type)).length, 'var(--canoe)'],
          ['Running',        runs.length, 'var(--run)'],
          ['Mind & Body',    yoga.length, 'var(--yoga)'],
          ['Hiking / Walk',  a.filter(x=>['Hike','Walk'].includes(x.Type)).length, 'var(--hike)'],
          ['Rowing',         a.filter(x=>x.Type==='Rowing').length, 'var(--row)'],
          ['Cycling',        a.filter(x=>x.Type==='Ride').length, 'var(--ride)'],
          ['Surfing',        a.filter(x=>x.Type==='Surfing').length, 'var(--surf)'],
        ].filter(([,n])=>n>0).map(([label,n,color])=>`<div class="bar-row">
          <div class="bar-name">${label}</div>
          <div class="bar-track"><div class="bar-fill" style="width:${(n/a.length*100).toFixed(0)}%;background:${color}"></div></div>
          <div class="bar-num">${(n/a.length*100).toFixed(0)}%</div>
        </div>`).join('')}
      </div>
    </div>
  `;
}

// ── AI ─────────────────────────────────────────────────────────────────────────
function renderAiPreview() {
  const cutoff = new Date(); cutoff.setDate(cutoff.getDate()-10);
  const recent = allData.filter(a=>new Date(a.Date)>=cutoff);
  window._recent = recent;
  const box = document.getElementById('recentPreview');
  if (recent.length) {
    box.style.display = 'block';
    document.getElementById('recentItems').innerHTML = recent.map(a=>`
      <div class="rp-row">
        <span>${tc(a.Type).icon}</span>
        <span class="rp-name">${esc(a.Name)}</span>
        <span class="rp-date">${fmtShort(a.Date)}</span>
      </div>`).join('');
  }
  document.getElementById('aiDesc').textContent = recent.length
    ? `Analyzing your last ${recent.length} sessions to suggest what your body needs today.`
    : `No recent data — will suggest a session based on your training history.`;
}

async function generateWorkout() {
  if (API_KEY === 'YOUR_API_KEY_HERE') {
    document.getElementById('aiText').textContent = '⚠️ Add your API key first.\n\nOpen this HTML file in a text editor and replace YOUR_API_KEY_HERE.';
    document.getElementById('aiOutput').style.display = 'block';
    return;
  }
  const btn = document.querySelector('.gen-btn');
  btn.disabled = true;
  document.getElementById('aiThinking').style.display = 'block';
  document.getElementById('aiOutput').style.display = 'none';

  const recent = window._recent || [];
  const summary = recent.map(a=>{
    const km  = parseFloat(a['Distance (km)'])||0;
    const min = parseFloat(a['Duration (min)'])||0;
    const elev= parseFloat(a['Elevation Gain (m)'])||0;
    const desc= (a.Description||'').replace(/Logged with Hevy/g,'').slice(0,200).trim();
    return `• ${fmtShort(a.Date)}: [${a.Type}] ${a.Name}${km?` — ${km.toFixed(1)}km`:''}${min?` — ${fmt(min)}`:''}${elev?` — ${elev}m elev`:''}${desc?`\n  ${desc}`:''}`;
  }).join('\n');

  const prompt = `You are a smart training coach. This athlete has a varied active lifestyle: they canoe rivers for days at a time, do serious gym work (tracked in Hevy), run (including a half marathon), practice yoga regularly, hike, and row on a Concept2. They have a shoulder injury they're managing with targeted rehab (scapular pulls, external rotation, face pulls).

Recent activity (last 10 days):
${summary || '(no recent data — suggest a good general session)'}

Suggest ONE specific workout for today. Be concrete — if strength, give exercises with sets/reps/weights. If cardio, give distance/duration/intensity. Keep it brief.

Format:
**Why this:** (1 sentence based on what they've been doing)

**Session:**
(the workout, specific and actionable)

**Shoulder note:** (only if relevant)`;

  try {
    const res = await fetch('https://api.anthropic.com/v1/messages', {
      method: 'POST',
      headers: { 'Content-Type':'application/json','x-api-key':API_KEY,'anthropic-version':'2023-06-01','anthropic-dangerous-direct-browser-access':'true' },
      body: JSON.stringify({ model:'claude-sonnet-4-20250514', max_tokens:900, messages:[{role:'user',content:prompt}] })
    });
    const data = await res.json();
    document.getElementById('aiText').textContent = (data.content||[]).map(c=>c.text||'').join('') || 'No response.';
    document.getElementById('aiOutput').style.display = 'block';
  } catch(e) {
    document.getElementById('aiText').textContent = 'Error: '+e.message;
    document.getElementById('aiOutput').style.display = 'block';
  }
  document.getElementById('aiThinking').style.display = 'none';
  btn.disabled = false;
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function fmt(min) {
  const m = Math.round(parseFloat(min));
  if (!m) return '—';
  return m<60 ? m+'m' : Math.floor(m/60)+'h'+(m%60?' '+m%60+'m':'');
}
function fmtDate(ds) { return new Date(ds).toLocaleDateString('en-GB',{weekday:'short',day:'numeric',month:'short',year:'numeric'}); }
function fmtShort(ds) { return new Date(ds).toLocaleDateString('en-GB',{day:'numeric',month:'short'}); }
function formatWeekRange(startIso) {
  const start = new Date(startIso);
  const end = new Date(start);
  end.setDate(end.getDate() + 6);
  return `${start.toLocaleDateString('en-GB',{day:'numeric',month:'short'})} - ${end.toLocaleDateString('en-GB',{day:'numeric',month:'short'})}`;
}
function weekStart(ds) {
  const d=new Date(ds),day=d.getDay(),diff=d.getDate()-day+(day===0?-6:1);
  return new Date(new Date(d).setDate(diff)).toISOString().slice(0,10);
}
function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

function showView(id, el) {
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  document.querySelectorAll('.nav-btn').forEach(b=>b.classList.remove('active'));
  document.getElementById('view-'+id).classList.add('active');
  el.classList.add('active');
}

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeDetail();
});

// ── Boot ───────────────────────────────────────────────────────────────────────
fetchData();