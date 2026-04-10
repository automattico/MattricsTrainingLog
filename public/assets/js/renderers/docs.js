(function () {
  const M = window.Mattrics;

  function esc(value) {
    return M.esc ? M.esc(value) : String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function sectionButton(item, activeId) {
    const isActive = item.id === activeId;
    return `<button class="docs-nav-btn${isActive ? " active" : ""}" type="button" onclick="Mattrics.showDocsSection('${esc(item.id)}')">
      <span class="docs-nav-label">${esc(item.label)}</span>
    </button>`;
  }

  function getActiveSectionId(id) {
    const sections = M.docsSections || [];
    if (sections.some((section) => section.id === id)) return id;
    const hash = window.location.hash ? window.location.hash.slice(1) : "";
    if (sections.some((section) => section.id === hash)) return hash;
    return sections.length ? sections[0].id : "";
  }

  function renderSection(section) {
    return `<section class="docs-section docs-active-panel" id="${esc(section.id)}">
      <div class="docs-section-head">
        <h2 class="docs-section-title">${esc(section.title)}</h2>
        <p class="docs-section-intro">${esc(section.intro)}</p>
      </div>
      <div class="docs-section-body">${section.body}</div>
    </section>`;
  }

  M.showDocsSection = function showDocsSection(id) {
    M.renderDocsView(id);
  };

  M.renderDocsView = function renderDocsView(id) {
    const mount = document.getElementById("docsContent");
    if (!mount) return;
    const sections = M.docsSections || [];
    const activeId = getActiveSectionId(id);
    const activeSection = sections.find((section) => section.id === activeId) || sections[0];

    if (window.history && window.history.replaceState) {
      try {
        const url = new URL(window.location.href);
        url.hash = activeId;
        window.history.replaceState({}, "", url.toString());
      } catch {
        // Hash persistence is helpful but not required.
      }
    }

    mount.innerHTML = `
      <div class="docs-page">
        <header class="docs-hero">
          <h1 class="docs-title">Documentation</h1>
        </header>
        <div class="docs-layout">
          <nav class="docs-sidebar" aria-label="Documentation sections">
            ${sections.map((section) => sectionButton(section, activeId)).join("")}
          </nav>
          <div class="docs-section-list">
            ${activeSection ? renderSection(activeSection) : ""}
          </div>
        </div>
      </div>
    `;

  };
}());
