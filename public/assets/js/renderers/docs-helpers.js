(function () {
  const M = window.Mattrics;

  M.docsEsc = function docsEsc(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  };

  M.docsTable = function docsTable(rows) {
    return `<table class="docs-table">
      <tbody>
        ${rows.map((row) => `<tr>
          <th scope="row">${M.docsEsc(row[0])}</th>
          <td>${row[1]}</td>
        </tr>`).join("")}
      </tbody>
    </table>`;
  };

  M.docsCards = function docsCards(items) {
    return `<div class="docs-card-grid">
      ${items.map((item) => `<article class="docs-card">
        <div class="docs-card-title">${M.docsEsc(item.title)}</div>
        <div class="docs-card-text">${item.text}</div>
      </article>`).join("")}
    </div>`;
  };

  M.docsList = function docsList(items) {
    return `<ul class="docs-list">
      ${items.map((item) => `<li>${item}</li>`).join("")}
    </ul>`;
  };

  M.docsSubsection = function docsSubsection(title, body) {
    return `<section class="docs-subsection">
      <h3 class="docs-subsection-title">${M.docsEsc(title)}</h3>
      ${body}
    </section>`;
  };

  M.docsMermaid = function docsMermaid(code) {
    return `<div class="docs-mermaid"><pre class="mermaid">${code}</pre></div>`;
  };

  M.docsTiers = function docsTiers(tiers) {
    return `<div class="docs-tier-list">
      ${tiers.map((t) => `<div class="docs-tier-item">
        <span class="docs-tier-dot" style="background:${t.color}"></span>
        <span class="docs-tier-name">${M.docsEsc(t.name)}</span>
        <span class="docs-tier-range">${M.docsEsc(t.range)}</span>
        <span class="docs-tier-desc">${M.docsEsc(t.desc)}</span>
        <span class="docs-tier-action">${M.docsEsc(t.action)}</span>
      </div>`).join("")}
    </div>`;
  };

  M.docsTree = function docsTree(text) {
    return `<pre class="docs-tree">${text}</pre>`;
  };
}());
