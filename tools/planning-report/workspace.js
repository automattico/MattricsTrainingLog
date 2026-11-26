(() => {
  'use strict';

  const tabList = document.querySelector('[data-tabs]');
  const tabs = [...tabList.querySelectorAll('[data-tab]')];
  const panels = [...document.querySelectorAll('[data-section]')];

  tabList.setAttribute('role', 'tablist');
  tabs.forEach((tab, index) => {
    tab.setAttribute('role', 'tab');
    tab.setAttribute('aria-controls', panels[index].id);
    panels[index].setAttribute('role', 'tabpanel');
    panels[index].setAttribute('aria-labelledby', tab.id);
    panels[index].tabIndex = 0;
  });

  tabList.addEventListener('keydown', event => {
    const index = tabs.indexOf(event.target);
    if (index === -1) return;

    let next;
    switch (event.key) {
      case 'ArrowLeft': next = (index + tabs.length - 1) % tabs.length; break;
      case 'ArrowRight': next = (index + 1) % tabs.length; break;
      case 'Home': next = 0; break;
      case 'End': next = tabs.length - 1; break;
      case ' ': next = index; break;
      default: return;
    }
    event.preventDefault();
    tabs[next].focus();
    tabs[next].click();
  });

  function showRoute({ navigate = false } = {}) {
    const fragment = location.hash.slice(1);
    if (fragment === 'main' && navigate) return;

    const target = document.getElementById(fragment);
    const panel = target?.closest('[data-section]') ?? panels[0];
    tabs.forEach((tab, index) => {
      const active = panels[index] === panel;
      panels[index].hidden = !active;
      tab.setAttribute('aria-selected', String(active));
      tab.tabIndex = active ? 0 : -1;
    });

    if (!target || !panel.contains(target) || target === panel) {
      if (navigate) window.scrollTo(0, 0);
      return;
    }

    const disclosure = target.closest('details');
    for (let detail = disclosure; detail; detail = detail.parentElement.closest('details')) {
      detail.open = true;
    }
    if (navigate) disclosure?.querySelector('summary')?.focus({ preventScroll: true });
    target.scrollIntoView({ block: 'nearest' });
  }

  showRoute();
  window.addEventListener('hashchange', () => showRoute({ navigate: true }));
})();
