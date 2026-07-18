'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const form          = document.getElementById('user-search');
  const filterPalette = document.getElementById('filter-palette');
  const filterActive  = document.getElementById('filter-active');
  const resultPalette = document.getElementById('result-palette');
  const resultActive  = document.getElementById('result-active');
  const viewInput     = document.getElementById('view-input');
  const savedValues   = new Map();

  const filterMap  = new Map();
  document.querySelectorAll('#filter-catalog > .widget-def').forEach(def => {
    const id   = def.dataset.widgetId;
    const name = def.querySelector('.name').textContent.trim();
    const body = def.querySelector('.widget-body');
    filterMap.set(
      id, {
        id,
        name,
        body,
      }
    );
  });

  const resultMap = new Map();
  document.querySelectorAll('#result-catalog > .widget-def').forEach(result => {
    const id   = result.dataset.widgetId;
    const name = result.querySelector('.name').textContent.trim();
    resultMap.set(
      id, {
        id,
        name,
      }
    );
  });

  function makeFilterChip(chip) {
    const el = document.createElement('div');
    el.dataset.widgetId = chip.id;
    el.dataset.kind     = 'filter';
    el.className        = 'chip filter-chip';
    el.draggable        = true;
    el.textContent      = chip.name;
    el.title            = 'Drag or double-click to activate';
    el.addEventListener('dblclick', () => addFilter(chip.id));
    return el;
  }
  for (const f of filterMap.values()) {
    filterPalette.appendChild(makeFilterChip(f));
  }

  function makeFilterCard(filter) {
    const card = document.createElement('div');
    card.className        = 'card filter-card';
    card.dataset.kind     = 'filter';
    card.dataset.widgetId = filter.id;

    const header = document.createElement('div');
    header.className = 'card-header';

    const handle = document.createElement('span');
    handle.className   = 'drag-handle';
    handle.textContent = '▦';
    handle.setAttribute('aria-hidden', 'true');
    header.appendChild(handle);

    const title = document.createElement('span');
    title.className   = 'card-title';
    title.textContent = filter.name;
    header.appendChild(title);

    const close = document.createElement('button');
    close.className   = 'close-btn';
    close.textContent = '×';
    close.title       = 'Send back to catalog';
    close.type        = 'button';
    close.addEventListener('click', (e) => {
        e.stopPropagation();
        removeFilter(card);
    });
    header.appendChild(close);
    card.appendChild(header);

    const body = filter.body.cloneNode(true);
    if (savedValues.has(filter.id)) {
        // restore any previously entered values
        applyValues(body, savedValues.get(filter.id));
    }
    card.appendChild(body);

    card.addEventListener('mousedown', (e) => {
      const el = e.target;
      card.draggable = el.closest('.card-header') !== null && el.closest('.close-btn') === null;
    });
    card.addEventListener('dragend', () => {
        card.draggable = false;
    });
    card.addEventListener('dblclick', (e) => {
      if (e.target.closest('.card-header') && !e.target.closest('.close-btn')) {
        removeFilter(card);
      }
    });
    return card;
  }
  for (const d of resultMap.values()) {
    resultPalette.appendChild(makeResultChip(d, false));
  }

  function makeResultChip(result, active) {
    const el = document.createElement('div');
    el.className        = 'chip result-chip' + (active ? ' active' : '');
    el.dataset.widgetId = result.id;
    el.dataset.kind     = 'result';
    el.draggable        = true;

    const name = document.createElement('span');
    name.textContent = result.name;
    el.appendChild(name);

    if (active) {
      const close = document.createElement('button');
      close.className   = 'close-btn';
      close.textContent = '×';
      close.title       = 'Remove';
      close.type        = 'button';
      close.addEventListener('click', (e) => {
        e.stopPropagation();
        removeResult(el);
      });
      el.appendChild(close);
    }

    el.addEventListener('dblclick', () => {
      if (active) {
        removeResult(el);
      } else {
        addResult(result.id);
      }
    });
    return el;
  }

  function readValues(root) {
    const list = {};
    root.querySelectorAll('[name]').forEach(el => {
      if (el.type === 'checkbox') {
        // -> array of selected values
        if (!Array.isArray(list[el.name])) {
          list[el.name] = [];
        }
        if (el.checked) {
          list[el.name].push(el.value);
        }
      } else if (el.type === 'radio') {
        // -> value of the checked one (or null)
        if (el.checked) {
          list[el.name] = el.value;
        }
        else if (!(el.name in list)) {
          list[el.name] = null;
        }
      } else {
        list[el.name] = el.value;
      }
    });
    return list;
  }

  function applyValues(root, values) {
    root.querySelectorAll('[name]').forEach(el => {
      if (!(el.name in values)) {
        return;
    }
      const v = values[el.name];
      if (el.type === 'checkbox') {
        if (Array.isArray(v)) {
          el.checked = v.includes(el.value);
        }
      } else if (el.type === 'radio') {
        el.checked = (el.value === v);
      } else if (v != null) {
        el.value = v;
      }
    });
  }

  // drag-n-drop actions

  function addFilter(id, before) {
    const filter = filterMap.get(id);
    if (!filter) {
      return;
    }
    const selector = '[data-widget-id="' + cssEscape(id) + '"]';
    const chip = filterPalette.querySelector(selector);

    if (chip) {
      chip.remove();
    }
    let card = filterActive.querySelector(selector);
    if (!card) {
      card = makeFilterCard(filter);
    }
    insertInto(filterActive, card, before);
  }
  function removeFilter(card) {
    const id = card.dataset.widgetId;
    savedValues.set(id, readValues(card));
    card.remove();
    if (!filterPalette.querySelector('[data-widget-id="' + cssEscape(id) + '"]')) {
      filterPalette.appendChild(makeFilterChip(filterMap.get(id)));
    }
  }

  function addResult(id, before) {
    const result = resultMap.get(id);
    if (!result) {
      return;
    }
    const selector = '[data-widget-id="' + cssEscape(id) + '"]';
    const prevResult = resultPalette.querySelector(selector);
    if (prevResult) {
      prevResult.remove();
    }
    let addResult = resultActive.querySelector(selector);
    if (!addResult) {
      addResult = makeResultChip(result, true);
    }
    insertInto(resultActive, addResult, before);
    updateForm();
  }
  function removeResult(result) {
    const id = result.dataset.widgetId;
    result.remove();
    if (!resultPalette.querySelector('[data-widget-id="' + cssEscape(id) + '"]')) {
      resultPalette.appendChild(makeResultChip(resultMap.get(id), false));
    }
    updateForm();
  }
  function insertInto(zone, el, before) {
    if (before && before.parentElement === zone && before !== el) {
      zone.insertBefore(el, before);
    } else {
      zone.appendChild(el);
    }
  }
  function updateForm() {
    const ids = [];
    for (const c of resultActive.children) {
      if (c.dataset && c.dataset.widgetId) {
        ids.push(c.dataset.widgetId);
      }
    }
    viewInput.value = ids.join('.');
  }
  function cssEscape(s) {
    return (window.CSS && CSS.escape)
        ? CSS.escape(s)
        : s.replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  let drag = null;

  document.addEventListener('dragstart', (e) => {
    const el = e.target.closest('[data-widget-id]');
    if (!el) {
      return;
    }
    const zone = el.closest('[data-zone]');
    if (!zone) {
      return;
    }
    drag = {
      element:    el,
      id:         el.dataset.widgetId,
      kind:       el.dataset.kind,
      sourceZone: zone.dataset.zone,
    };
    if (e.dataTransfer) {
      e.dataTransfer.setData('text/plain', el.dataset.widgetId);
      e.dataTransfer.effectAllowed = 'move';
    }
    setTimeout(() => el.classList.add('dragging'), 0);
  });

  document.addEventListener('dragend', () => {
    document.querySelectorAll('.dragging').forEach(x => x.classList.remove('dragging'));
    clearIndicators();
    drag = null;
  });

  function clearIndicators() {
    document.querySelectorAll('.drop-before, .drop-zone-hover').forEach(el => {
      el.classList.remove('drop-before', 'drop-zone-hover');
    });
  }

  function findInsertBefore(zone, x, y) {
    const children = Array.from(zone.children).filter(c =>
      c.dataset && c.dataset.widgetId && !c.classList.contains('dragging')
    );
    for (const child of children) {
      const r = child.getBoundingClientRect();
      if (y < r.bottom) {
        if (x < r.left + r.width / 2) {
          return child;
        }
      }
    }
    return null; // fallback to append
  }

  function bindZone(zone, kind) {
    zone.addEventListener('dragover', (e) => {
      if (!drag || drag.kind !== kind) {
        return;
      }
      e.preventDefault();
      if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'move';
      }
      clearIndicators();
      const before = findInsertBefore(zone, e.clientX, e.clientY);
      if (before) {
        before.classList.add('drop-before');
      } else {
        zone.classList.add('drop-zone-hover');
      }
    });
    zone.addEventListener('dragleave', (e) => {
      if (!zone.contains(e.relatedTarget)) {
        clearIndicators();
      }
    });
    zone.addEventListener('drop', (e) => {
      if (!drag || drag.kind !== kind) {
        return;
      }
      e.preventDefault();
      const before = findInsertBefore(zone, e.clientX, e.clientY);
      handleDrop(zone.dataset.zone, before);
      clearIndicators();
    });
  }
  bindZone(filterPalette, 'filter');
  bindZone(filterActive,  'filter');
  bindZone(resultPalette, 'result');
  bindZone(resultActive,  'result');

  function handleDrop(targetZone, before) {
    const { element, id, kind, sourceZone } = drag;

    if (kind === 'filter') {
      if (targetZone === 'filter-active') {
        if (sourceZone === 'filter-palette') {
          addFilter(id, before);
        } else {
          // reorder within active
          insertInto(filterActive, element, before);
        }
      } else if (targetZone === 'filter-palette') {
        if (sourceZone === 'filter-active') {
          removeFilter(element);
        }
      }
    } else if (kind === 'result') {
      if (targetZone === 'result-active') {
        if (sourceZone === 'result-palette') {
          addResult(id, before);
        } else {
          insertInto(resultActive, element, before);
          updateForm();
        }
      } else if (targetZone === 'result-palette') {
        if (sourceZone === 'result-active') {
          removeResult(element);
        }
      }
    }
  }
 
  // preload the previous values
  document.querySelectorAll('#filter-catalog > [data-default="true"]').forEach(result => {
    addFilter(result.dataset.widgetId);
  });
  const param  = new URLSearchParams(window.location.search);
  const view   = param.get('view') || 'username.joined.lastseen.inviter';
  view.split('.').forEach(column => {
    addResult(column);
  });

  // copiable url
  const urlText = document.getElementById('url');
  const urlCopy = document.getElementById('url-copy');
  if (urlText && urlCopy) {
    urlText.textContent = location.href;
    urlCopy.addEventListener('click', () => {
      navigator.clipboard.writeText(urlText.textContent);
    });
  }

  // strip empty fields to make a better bookmarkable query string.
  form.addEventListener('submit', () => {
    updateForm();
    form.querySelectorAll('input, select, textarea').forEach(el => {
      if (el.type === 'checkbox' || el.type === 'radio') {
        return;
      }
      if (el.value === '') {
        el.disabled = true;
      }
    });
    // if an "X" field is disabled/empty, drop the "X_op" field too.
    form.querySelectorAll('.widget-body').forEach(body => {
      body.querySelectorAll('input[type=radio][name$="_op"]').forEach(op => {
        const valueName = op.name.slice(0, -3);
        const valueField = body.querySelector('[name="' + cssEscape(valueName) + '"]');
        if (valueField && valueField.disabled) {
          op.disabled = true;
        }
      });
    });
  });
});
