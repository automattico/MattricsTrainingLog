(function () {
  const M = window.Mattrics;
  const panel = document.getElementById("workoutReview");
  const toggle = document.getElementById("reviewWorkouts");
  if (!panel || !toggle) return;
  const esc = value => M.escAttr(String(value ?? ""));
  const comparisonPageSize = 6;
  let filter = "priority", query = "", limit = comparisonPageSize;
  let data, groupOf, left, right, manual = false, busy = false;

  M.setWorkoutReviewAvailable = available => {
    toggle.hidden = !available;
    if (!available) {
      panel.hidden = true;
      toggle.setAttribute("aria-expanded", "false");
    }
  };
  const source = id => data.sources[id];
  const provider = s => ({garmin: "Garmin", strava: "Strava", hevy: "Hevy"}[s.provider] || s.provider);
  const title = s => (s.title || "Untitled activity") + " · " + provider(s);
  const providerIcon = s => ["garmin", "strava"].includes(s.provider)
    ? `<img class="review-source-icon" src="/icons/review-${s.provider}.jpg" alt="" width="20" height="20">` : "";
  const dateTime = new Intl.DateTimeFormat(undefined, {
    timeZone: "Europe/Berlin", day: "numeric", month: "short", year: "numeric",
    hour: "2-digit", minute: "2-digit", second: "2-digit", hourCycle: "h23"
  });
  const clock = value => value ? dateTime.format(new Date(value)) : "—";
  function duration(seconds) {
    if (seconds == null) return "—";
    const n = Math.round(seconds);
    return [Math.floor(n / 3600), Math.floor(n / 60) % 60, n % 60].map((v, i) => i ? String(v).padStart(2, "0") : v).join(":");
  }
  function stationaryZeroMovingTime(recording) {
    return recording.moving === 0 && recording.km === 0 && recording.timer > 0
      && ["Workout", "WeightTraining"].includes(recording.sport);
  }
  const distanceThreshold = [.5, .1];
  const timestampThreshold = [300000, 0];
  const durationThreshold = [300, .15];
  function gapRatio(first, second, [minimum, fraction]) {
    if (!Number.isFinite(first) || !Number.isFinite(second)) return 0;
    return Math.abs(first - second) / Math.max(minimum, fraction * Math.max(first, second));
  }
  function discrepancy(a, b) {
    const reasons = [];
    let score = 0;
    function compare(first, second, threshold, describe) {
      const ratio = gapRatio(first, second, threshold);
      if (ratio < 1) return;
      reasons.push(describe(Math.abs(first - second)));
      score = Math.max(score, ratio);
    }
    compare(a.km, b.km, distanceThreshold, delta => `${delta.toFixed(2)} km distance gap`);
    for (const [field, label] of [["selectedStart", "start"], ["selectedFinish", "finish"]]) {
      compare(Date.parse(a[field]), Date.parse(b[field]), timestampThreshold,
        delta => `${Math.round(delta / 60000)} min ${label} gap`);
    }
    for (const field of ["moving", "timer", "elapsed"]) {
      if (field === "moving" && [a,b].some(stationaryZeroMovingTime)) continue;
      compare(a[field], b[field], durationThreshold, delta => `${Math.round(delta / 60)} min ${field} time gap`);
    }
    return {score, reasons};
  }
  const filters = {
    priority: "Priority",
    all: "All suggestions",
    saved: "Saved for later",
    linked: "Already linked"
  };
  function reviewEntries(kind) {
    if (kind === "linked") {
      return data.groups.filter(group => group.ids.length > 1).map(group => ({
        left: group.ids[0], right: group.ids[1], ids: group.ids,
        key: `g${group.ids[0]}`, date: source(group.preferred).start, reasons: []
      })).sort((a, b) => Date.parse(b.date) - Date.parse(a.date));
    }
    return data.queue.filter(pair => kind === "saved" ? pair.later
      : !pair.later && (kind === "all" || pair.score > 0));
  }
  function matchesQuery(entry) {
    const text = (entry.ids || [entry.left, entry.right]).map(id => {
      const recording = source(id);
      return `${title(recording)} ${clock(recording.start)} ${recording.start || ""}`;
    }).join(" ").toLocaleLowerCase();
    return query.trim().toLocaleLowerCase().split(/\s+/).every(term => text.includes(term));
  }
  const isSelected = pair => groupOf.has(left) && groupOf.has(right)
    && decisionKey(pair) === decisionKey({left, right});
  const decisionKey = pair => [groupOf.get(pair.left).activity, groupOf.get(pair.right).activity].sort().join("/");
  function accept(payload) {
    data = payload;
    groupOf = new Map();
    for (const group of data.groups) for (const id of group.ids) groupOf.set(id, group);
    const decisions = new Map();
    const ranked = data.queue.map(pair => ({
      ...pair, date: source(pair.left).start,
      ...discrepancy(source(pair.left), source(pair.right))
    })).sort((a, b) => Number(b.later) - Number(a.later)
      || (b.confidence || 0) - (a.confidence || 0) || b.score - a.score
      || Date.parse(b.date) - Date.parse(a.date));
    for (const pair of ranked) {
      const key = decisionKey(pair);
      if (decisions.has(key)) continue;
      const ids = [...groupOf.get(pair.left).ids, ...groupOf.get(pair.right).ids];
      decisions.set(key, {...pair, key, ids, score: pair.confidence > 0 ? pair.score : 0});
    }
    data.queue = [...decisions.values()];
  }
  function select(a, b) {
    left = a;
    right = b;
  }
  function selectNext() {
    const next = reviewEntries(filter).find(matchesQuery);
    select(next?.left, next?.right);
  }
  const options = active => '<option value="">Select recording</option>' + Object.values(data.sources).slice().reverse().map(s =>
    `<option value="${esc(s.id)}" ${s.id === active ? "selected" : ""}>${esc(clock(s.start))} · ${esc(title(s))}</option>`).join("");
  function comparison(reason) {
    if (!left || !right || left === right) {
      return "<p>Choose a comparison from the list or compare two recordings manually.</p>";
    }
    const leftGroup = groupOf.get(left);
    const rightGroup = groupOf.get(right);
    const linked = leftGroup === rightGroup;
    const ids = [...new Set([...leftGroup.ids, ...rightGroup.ids])];
    const showGroups = !linked && ids.length > 2;
    const sources = ids.map(source);
    const fields = [
      ["Sport", s => s.sport || "—"],
      ["Start", s => clock(s.start), "start"], ["Finish", s => clock(s.finish), "finish"],
      ...(sources.some(s => s.edited) ? [["Summary start", s => clock(s.selectedStart), "selectedStart"], ["Estimated summary finish", s => clock(s.selectedFinish), "selectedFinish"]] : []),
      ["Distance", s => s.km == null ? "—" : s.km.toFixed(2) + " km", "km"],
      ...["moving","timer","elapsed"].map(k => [k[0].toUpperCase() + k.slice(1) + " time", s => duration(s[k]), k])
    ];
    const current = linked ? groupOf.get(left).preferred : null;
    const recordingHeaders = sources.map(s => {
      const name = esc(provider(s));
      const recording = esc(s.title || "Untitled activity");
      const action = linked ? `Use ${name}` : `Merge using ${name}`;
      const link = s.url ? `<a href="${esc(s.url)}" target="_blank" rel="noopener noreferrer">Open in ${name} ↗</a>`
        : `<small>Original link unavailable · no ${name} activity ID</small>`;
      const group = groupOf.get(s.id);
      const groupLabel = showGroups ? `<small>Workout ${group === leftGroup ? 1 : 2}${group.ids.length > 1 ? " · already linked" : ""}</small>` : "";
      return `<th scope="col"><div class="review-recording"><strong>${recording}</strong>${groupLabel}<div class="review-source">${providerIcon(s)}${link}</div>
        ${s.id === current ? '<span class="review-in-use">In use</span>' :
          `<button type="button" class="review-primary" data-review-action="${linked ? "prefer" : "link"}"
            data-review-source="${esc(s.id)}" aria-label="${action} · ${recording}">${action}</button>`}
      </div></th>`;
    });
    const rows = fields.map(([label, format, field]) => {
      const values = sources.map(format);
      const comparable = values.filter((value, i) => value !== "—"
        && !(field === "moving" && stationaryZeroMovingTime(sources[i])));
      let unequal = new Set(comparable).size > 1;
      if (field) {
        const timestamp = ["start", "finish", "selectedStart", "selectedFinish"].includes(field);
        const numbers = sources.filter(s => field !== "moving" || !stationaryZeroMovingTime(s))
          .map(s => timestamp ? Date.parse(s[field]) : s[field]).filter(Number.isFinite);
        const threshold = timestamp ? timestampThreshold : field === "km" ? distanceThreshold : durationThreshold;
        unequal = numbers.length > 1 && gapRatio(Math.min(...numbers), Math.max(...numbers), threshold) >= 1;
      }
      const marker = unequal ? '<span class="review-marker" role="img" aria-label="Different" title="Different">⚠</span>&nbsp;' : "";
      return {different: unequal || (values.includes("—") && comparable.length > 0), html: `<tr class="${unequal ? "review-diff" : ""}">
        <th scope="row">${marker}${label}</th>
        ${values.map(v => `<td>${v === "—" ? "<small>Unavailable</small>" : esc(v)}</td>`).join("")}</tr>`};
    }).sort((a,b) => Number(b.different) - Number(a.different));
    const explanations = {
      "Same recording start": "start at the same second",
      "Starts within 90 seconds": "start within 90 seconds of each other",
      "Recording periods overlap": "overlap in time",
      "Previously matched recording": "were previously matched"
    };
    return `${linked ? "" : showGroups
        ? "<p>These columns represent two workouts. A merge combines both workouts into one.</p>"
        : "<p>If these are the same workout, choose which recording Mattrics should use.</p>"}
      <div class="review-table-wrap" tabindex="0" role="region" aria-label="Recording differences"><table><thead><tr>
      <th scope="col">Measurement</th>${recordingHeaders.join("")}</tr></thead>
      <tbody>${rows.map(r => r.html).join("")}</tbody></table></div>
      <div class="review-decision">
      ${reason ? `<p>Suggested because the recordings ${esc(explanations[reason] || "have similar timing")}.</p>` : ""}
      <div class="review-actions"><button type="button" data-review-action="separate">${linked ? "Keep selected recordings separate" : "Keep separate"}</button>
      ${linked ? "" : '<button type="button" data-review-action="later">Review later</button>'}</div></div>`;
  }
  function renderList() {
    const results = reviewEntries(filter).filter(matchesQuery);
    const shown = Math.min(results.length, limit);
    panel.querySelector(".review-result-count").textContent = results.length > shown
      ? `Showing ${shown} of ${results.length} comparisons`
      : `${results.length} ${results.length === 1 ? "comparison" : "comparisons"}`;
    panel.querySelector(".review-criteria").hidden = filter !== "priority";
    panel.querySelector(".review-results").innerHTML = results.length ? `
      <ul class="review-list">${results.slice(0, shown).map(entry => `
        <li><button type="button" data-review-entry="${esc(entry.key)}" ${isSelected(entry) ? 'aria-current="true"' : ""}>
          <strong class="review-entry-sources">${(entry.ids || [entry.left, entry.right]).map(id => {
            const recording = source(id);
            return `<span class="review-source">${providerIcon(recording)}${esc(title(recording))}</span>`;
          }).join("")}</strong>
          <small>${esc(clock(entry.date))}${entry.reasons.length ? ` · ${esc(entry.reasons.join(" · "))}` : ""}</small>
        </button></li>`).join("")}</ul>
      ${results.length > limit ? `<button type="button" class="review-more" data-review-step="more">Show ${Math.min(comparisonPageSize, results.length - limit)} more</button>` : ""}`
      : `<p class="review-empty">${query.trim() ? "No comparisons match your search." : filter === "priority" ?
        "No priority comparisons. Open All suggestions to review smaller differences." : "No comparisons in this view."}</p>`;
  }
  function render(message = "") {
    const selected = data.queue.find(isSelected);
    const linked = groupOf.has(left) && groupOf.get(left) === groupOf.get(right);
    panel.innerHTML = `<section class="review-browser" aria-labelledby="reviewListHeading">
        <h3 id="reviewListHeading" tabindex="-1" class="review-list-heading">Workout comparisons</h3>
        <div class="review-search-tools">
          <label for="reviewSearch">Search comparisons</label>
          <input id="reviewSearch" type="search" value="${esc(query)}" placeholder="Title, source, or date">
          <button type="button" data-review-step="manual" aria-expanded="${manual}">Compare manually</button>
        </div>
        ${manual ? `<div class="review-manual">
          <label>First recording <select id="reviewLeft">${options(left)}</select></label>
          <label>Second recording <select id="reviewRight">${options(right)}</select></label>
          <button type="button" data-review-step="compare" ${!left || !right || left === right ? "disabled" : ""}>Open comparison</button>
        </div>` : ""}
        <div class="review-filters" role="group" aria-label="Filter comparisons">${Object.entries(filters).map(([key, label]) =>
          `<button type="button" data-review-filter="${key}" aria-pressed="${filter === key}">${label}<span>${reviewEntries(key).length}</span></button>`).join("")}</div>
        <div class="review-list-meta">
          <span class="review-result-count" aria-live="polite"></span>
          <details class="review-criteria"><summary>What gets priority?</summary><p>Likely copies with a gap of at least 0.5 km and 10%, 5 minutes in start or finish, or 5 minutes and 15% in moving, timer, or elapsed time. A stationary workout can report zero moving time while its timer is running.</p></details>
        </div>
        <div class="review-results"></div>
      </section>
      <section class="review-detail" aria-labelledby="reviewDetailHeading">
        <header>
          <h3 id="reviewDetailHeading" class="review-heading" tabindex="-1">${linked ? "Linked workout" : "Recording comparison"}</h3>
          ${data.canUndo ? '<button type="button" data-review-action="undo">Undo last decision</button>' : ""}
        </header>
        <p class="review-feedback" role="status">${esc(message)}</p>
        ${comparison(selected?.reason)}
      </section>`;
    renderList();
  }
  async function load(message = "") {
    const res = await M.apiFetch("/api/workout-review");
    if (!res.ok) throw new Error("Could not load workout comparisons.");
    accept(await res.json());
    if (!groupOf.has(left) || !groupOf.has(right)) {
      selectNext();
    }
    render(message);
  }
  toggle.addEventListener("click", async () => {
    if (busy) return;
    panel.hidden = !panel.hidden;
    toggle.setAttribute("aria-expanded", String(!panel.hidden));
    if (panel.hidden) return;
    busy = true;
    panel.textContent = "Loading workouts…";
    try { await load(); } catch (e) { panel.textContent = e.message; } finally { busy = false; }
  });
  panel.addEventListener("change", event => {
    if (busy) return;
    if (!["reviewLeft", "reviewRight"].includes(event.target.id)) return;
    const first = panel.querySelector("#reviewLeft").value;
    const second = panel.querySelector("#reviewRight").value;
    panel.querySelector('[data-review-step="compare"]').disabled = !first || !second || first === second;
  });
  panel.addEventListener("input", event => {
    if (event.target.id !== "reviewSearch" || busy) return;
    query = event.target.value;
    limit = comparisonPageSize;
    renderList();
  });
  panel.addEventListener("click", async event => {
    const step = event.target.closest("[data-review-step]")?.dataset.reviewStep;
    if (busy) return;
    const filterButton = event.target.closest("[data-review-filter]");
    if (filterButton) {
      filter = filterButton.dataset.reviewFilter;
      limit = comparisonPageSize;
      for (const control of panel.querySelectorAll("[data-review-filter]")) {
        control.setAttribute("aria-pressed", String(control === filterButton));
      }
      renderList();
      return;
    }
    const entryButton = event.target.closest("[data-review-entry]");
    if (entryButton) {
      const entry = reviewEntries(filter).find(item => item.key === entryButton.dataset.reviewEntry);
      manual = false;
      select(entry.left, entry.right);
      render();
      panel.querySelector(".review-heading")?.focus();
      return;
    }
    if (step === "more") {
      const previousLimit = limit;
      limit += comparisonPageSize;
      renderList();
      panel.querySelectorAll("[data-review-entry]")[previousLimit]?.focus();
      return;
    }
    if (step === "manual") {
      manual = !manual;
      render();
      panel.querySelector(manual ? "#reviewLeft" : '[data-review-step="manual"]')?.focus();
      return;
    }

    if (step === "compare") {
      select(panel.querySelector("#reviewLeft").value, panel.querySelector("#reviewRight").value);
      manual = false;
      render();
      panel.querySelector(".review-heading")?.focus();
      return;
    }

    const button = event.target.closest("[data-review-action]");
    if (!button) return;
    const {reviewAction: action, reviewSource: preferred} = button.dataset;
    const undoPair = data.undoPair;
    const messages = {
      link: () => `Merged using ${provider(source(preferred))}.`,
      prefer: () => `Now using ${provider(source(preferred))}.`,
      separate: () => "Kept separate.",
      later: () => "Saved for later.",
      undo: () => "Decision undone."
    };
    let saved = false;
    busy = true;
    panel.inert = true;
    panel.setAttribute("aria-busy", "true");
    try {
      const res = await M.apiFetch("/api/workout-review", {
        method: "POST", headers: {"Content-Type": "application/json"},
        body: JSON.stringify({action,left,right,preferred,version:data.version})
      });
      const result = await res.json();
      if (res.status === 409) {
        await load(result.error);
      } else {
        if (!res.ok) throw new Error(result.error || "Review could not be saved.");
        accept(result);
        if (action === "undo" && undoPair?.every(id => groupOf.has(id))) {
          select(...undoPair);
        } else if (action !== "prefer") {
          manual = false;
          if (filter === "all" || filter === "priority") selectNext();
          else select();
        }
        render(messages[action]());
        saved = true;
      }
    } catch (error) {
      render(error.message);
    } finally {
      panel.inert = false;
      panel.removeAttribute("aria-busy");
      busy = false;
      const focusTarget = panel.querySelector(".review-heading") || panel.querySelector(".review-list-heading");
      focusTarget?.focus();
    }
    if (saved && action !== "later") await M.fetchData({background:true});
  });
}());
