import BaseAutocomplete from '../vendor/autocomplete.mjs';

const ACTIVE_CLASS = 'is-active';
const SHOW_CLASS = 'show';
const HOOK_ROW_CLASS = 'ac-row';
const HOOK_ROW_CHOSEN_CLASS = 'ac-row-chosen';
const HOOK_ROW_HOVER_CLASS = 'ac-row-hover';
const HOOK_MENU_KEYBOARD_CLASS = 'ac-mode-keyboard';
const HOOK_MENU_POINTER_CLASS = 'ac-mode-pointer';
const HOOK_BODY_MOBILE_OPEN_CLASS = 'ac-mobile-rich-open';
const HOOK_INPUT_MOBILE_CLASS = 'ac-mobile-rich-input';
const HOOK_MENU_MOBILE_CLASS = 'ac-mobile-rich-menu';
const HOOK_CLOSE_MOBILE_CLASS = 'ac-mobile-rich-close';
const MOBILE_TAKEOVER_QUERY = '(max-width: 575px)';
const MOBILE_LAYOUT_DEBUG_GLOBAL = 'GAZELLE_AC_DEBUG';
const MOBILE_LAYOUT_DEBUG_STORAGE_KEY = 'gazelle_ac_debug';
const MOBILE_VIEWPORT_CSS_VARS = [
  '--ac-mobile-viewport-top',
  '--ac-mobile-viewport-left',
  '--ac-mobile-viewport-width',
  '--ac-mobile-viewport-height',
];
const MOBILE_INPUT_INLINE_STYLE_PROPS = [
  'position',
  'top',
  'left',
  'width',
  'maxWidth',
  'zIndex',
  'boxSizing',
  'fontSize',
  'transition',
  'margin',
  'marginTop',
  'marginRight',
  'marginBottom',
  'marginLeft',
  'transform',
  'display',
];
const NEXT = 'next';
const PREV = 'prev';
const BBCODE_TAG_PATTERN = /\[\/?(?:[a-z][\w:-]*|\*)(?:=[^\]\r\n]*)?\]/gi;
// Mirrors Text::full_format() equal-heading shorthand: ==, ===, ====.
const BBCODE_HEADING_PATTERN = /^\s*(={2,4})([^=][\s\S]*)\1\s*$/;
const BIO_LINE_BLOCK_TAGS = new Set(['p']);

function removeDiacritics(str) {
  return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function normalize(str) {
  if (!str) {
    return '';
  }
  return removeDiacritics(str.toString()).toLowerCase();
}

function decodeHtml(html) {
  const txt = document.createElement('textarea');
  txt.innerHTML = html;
  return txt.value;
}

function normalizeBioLineText(text) {
  return (text || '').replace(/\u00a0/g, ' ').replace(/\s+/g, ' ').trim();
}

function stripBbcodeHeadingText(text) {
  return (text || '').replace(BBCODE_HEADING_PATTERN, (_match, _marks, heading) => heading.trim());
}

function shouldDropBioLine(nodes) {
  const line = document.createElement('span');
  nodes.forEach((node) => line.append(node.cloneNode(true)));
  const lineText = normalizeBioLineText(line.textContent);
  if (!lineText) {
    return true;
  }
  if (/^\[url(?:=[^\]]+)?\][\s\S]*\[\/url\]$/i.test(lineText)) {
    return true;
  }
  const anchors = line.querySelectorAll('a[href]');
  if (anchors.length !== 1) {
    return false;
  }
  const anchor = anchors[0];
  anchor.remove();
  return normalizeBioLineText(anchor.textContent) === lineText && normalizeBioLineText(line.textContent) === '';
}

function sanitizeBioLineNodes(nodes) {
  let changed = false;

  const line = document.createElement('span');
  nodes.forEach((node) => line.append(node.cloneNode(true)));

  line.querySelectorAll('img, picture, source').forEach((node) => {
    node.remove();
    changed = true;
  });

  Array.from(line.querySelectorAll('*')).reverse().forEach((node) => {
    node.replaceWith(...Array.from(node.childNodes));
    changed = true;
  });

  const walker = document.createTreeWalker(line, NodeFilter.SHOW_TEXT);
  let textNode = walker.nextNode();
  while (textNode) {
    const stripped = textNode.nodeValue.replace(BBCODE_TAG_PATTERN, '');
    if (stripped !== textNode.nodeValue) {
      textNode.nodeValue = stripped;
      changed = true;
    }
    textNode = walker.nextNode();
  }

  const strippedHeading = stripBbcodeHeadingText(line.textContent);
  if (strippedHeading !== line.textContent) {
    line.textContent = strippedHeading;
    changed = true;
  }

  return {
    changed,
    nodes: Array.from(line.childNodes),
  };
}

function sanitizeKeptBioLines(lines) {
  let changed = false;
  const sanitized = lines.map((nodes) => {
    const result = sanitizeBioLineNodes(nodes);
    changed = result.changed || changed;
    return result.nodes;
  });
  return {changed, lines: sanitized};
}

function appendBioLines(content, lines) {
  const fragment = document.createDocumentFragment();
  lines.forEach((nodes, index) => {
    if (index > 0) {
      fragment.append(document.createElement('br'));
    }
    nodes.forEach((node) => fragment.append(node));
  });
  content.textContent = '';
  content.append(fragment);
}

function splitBioLines(content) {
  const lines = [[]];
  Array.from(content.childNodes).forEach((node) => {
    if (node.nodeType !== Node.ELEMENT_NODE) {
      lines[lines.length - 1].push(node);
      return;
    }
    const tagName = node.tagName.toLowerCase();
    if (tagName === 'br') {
      lines.push([]);
      return;
    }
    if (BIO_LINE_BLOCK_TAGS.has(tagName)) {
      if (lines[lines.length - 1].length > 0) {
        lines.push([]);
      }
      lines[lines.length - 1].push(...Array.from(node.childNodes));
      lines.push([]);
      node.remove();
      return;
    }
    lines[lines.length - 1].push(node);
  });
  return lines;
}

function prefilterRichArtistBioHtml(html) {
  if (!html || !html.includes('cae-bio-content')) {
    return html;
  }

  const template = document.createElement('template');
  template.innerHTML = html;
  let changed = false;
  template.content.querySelectorAll('.cae-bio-content').forEach((content) => {
    const lines = splitBioLines(content);
    const keptLines = lines.filter((nodes) => !shouldDropBioLine(nodes));
    const sanitized = sanitizeKeptBioLines(keptLines);
    if (keptLines.length !== lines.length || sanitized.changed) {
      changed = true;
      if (keptLines.length === 0) {
        const entry = content.closest('.collage-artist-entry');
        content.closest('.cae-bio')?.remove();
        entry?.classList.remove('cae-has-bio');
        entry?.classList.add('cae-no-bio');
        return;
      }
      appendBioLines(content, sanitized.lines);
    }
  });

  return changed ? template.innerHTML : html;
}

function isBackgroundOpenEvent(event) {
  return !!event && (event.button === 1 || event.metaKey || event.ctrlKey);
}

function armBackgroundOpen(inst) {
  inst._backgroundOpenKeepAliveUntil = Date.now() + 500;
}

function backgroundOpenLinkTarget(event) {
  const link = event?.target instanceof Element ? event.target.closest('a[href]') : null;
  return link instanceof HTMLAnchorElement ? link : null;
}

function dispatchBackgroundAnchor(sourceLink) {
  const link = document.createElement('a');
  link.href = sourceLink.href;
  link.target = sourceLink.target || '_blank';
  link.setAttribute('rel', sourceLink.getAttribute('rel') || sourceLink.rel || 'noopener');
  link.style.display = 'none';
  document.body.append(link);
  link.click();
  link.remove();
}

function restoreSearchFocus(inst) {
  if (document.contains(inst._searchInput) && document.activeElement !== inst._searchInput) {
    inst._searchInput.focus();
  }
}

class RichArtistAutocomplete extends BaseAutocomplete {
  constructor(el, config = {}) {
    super(el, config);

    this._backgroundOpenKeepAliveUntil = 0;
    this._mobileMediaQuery = window.matchMedia(MOBILE_TAKEOVER_QUERY);
    this._mobileTakeoverActive = false;
    this._mobileInputInlineStyle = null;
    this._mobileCloseControl = null;
    this._documentPointerBound = false;
    this._dropElementPortaled = false;
    this._dropElementOriginalParent = null;
    this._dropElementOriginalNextSibling = null;
    this._visualViewportBound = false;
    this._onMobileCloseClick = (event) => {
      event.preventDefault();
      this.hideSuggestions();
      this._searchInput.focus();
    };
    this._onMobileBreakpointChange = () => {
      this._syncMobileTakeoverMode(true);
    };
    if (this._mobileMediaQuery.addEventListener) {
      this._mobileMediaQuery.addEventListener('change', this._onMobileBreakpointChange);
    } else if (this._mobileMediaQuery.addListener) {
      this._mobileMediaQuery.addListener(this._onMobileBreakpointChange);
    }
    this._onVisualViewportChange = () => {
      if (!this._mobileTakeoverActive || !this.isDropdownVisible()) {
        return;
      }
      this._syncMobileViewportVars();
      this._positionMenu();
      this._debugMobileLayout();
    };

    this._dropElement.addEventListener('focusout', this);
  }

  dispose() {
    this._dropElement.removeEventListener('focusout', this);
    this._disableMobileTakeover();
    this._setVisualViewportBinding(false);
    if (this._mobileMediaQuery.removeEventListener) {
      this._mobileMediaQuery.removeEventListener('change', this._onMobileBreakpointChange);
    } else if (this._mobileMediaQuery.removeListener) {
      this._mobileMediaQuery.removeListener(this._onMobileBreakpointChange);
    }
    super.dispose();
  }

  _configure(config = {}) {
    super._configure(config);
    const options = { ...config, ...this._searchInput.dataset };
    this._config.menuClass = options.menuClass === undefined ? '' : options.menuClass.toString();
  }

  _configureDropElement() {
    super._configureDropElement();
    this._dropElement.hidden = true;
    if (this._config.menuClass) {
      this._dropElement.classList.add(...this._config.menuClass.split(' '));
    }
  }

  onblur(e) {
    const related = e.relatedTarget;
    if (
      this._isMouse
      && related instanceof HTMLElement
      && (related.classList.contains('modal') || related.classList.contains('autocomplete-menu'))
    ) {
      this._searchInput.focus();
      return;
    }
    setTimeout(() => {
      if ((this._backgroundOpenKeepAliveUntil ?? 0) > Date.now()) {
        restoreSearchFocus(this);
        return;
      }
      const active = this._getActiveElement();
      if (!(active instanceof Node) || !this._dropElement.contains(active)) {
        this.hideSuggestions();
      }
    }, 100);
  }

  onfocusout() {
    setTimeout(() => {
      const active = this._getActiveElement();
      if (!(active instanceof Node) || (!this._searchInput.contains(active) && !this._dropElement.contains(active))) {
        this.hideSuggestions();
      }
    }, 0);
  }

  onkeydown(e) {
    const key = e.keyCode || e.key;
    switch (key) {
      case 13:
      case 'Enter': {
        if (this.isDropdownVisible()) {
          const selection = this.getSelection();
          if (selection) {
            selection.click();
          }
          if (selection || !this._config.ignoreEnter) {
            e.preventDefault();
          }
        }
        break;
      }
      case 9:
      case 'Tab': {
        if (this.isDropdownVisible() && this._config.tabSelect) {
          const selection = this.getSelection();
          if (selection) {
            selection.click();
            e.preventDefault();
          }
        }
        break;
      }
      case 38:
      case 'ArrowUp':
        e.preventDefault();
        this._setKeyboardNavigation(true);
        this._moveSelection(PREV);
        break;
      case 40:
      case 'ArrowDown':
        e.preventDefault();
        this._setKeyboardNavigation(true);
        if (this.isDropdownVisible()) {
          this._moveSelection(NEXT);
        } else {
          this.showOrSearch(false);
        }
        break;
      case 27:
      case 'Escape':
        if (this.isDropdownVisible()) {
          this._searchInput.focus();
          this.hideSuggestions();
        }
        break;
    }
  }

  onmousemove(e) {
    this._isMouse = true;
    const hoverRow = e.target instanceof Element ? e.target.closest(`li.${HOOK_ROW_CLASS}`) : null;
    this._setHoverRow(hoverRow instanceof HTMLElement ? hoverRow : null);
    const movedFromKeyboard = this._keyboardNavigation;
    this._setKeyboardNavigation(false);
    if (movedFromKeyboard) {
      const hoverItem = hoverRow ? hoverRow.querySelector('.dropdown-item') : null;
      if (hoverItem instanceof HTMLElement) {
        this._setActiveItem(hoverItem);
      }
    }
  }

  onmouseleave() {
    this._isMouse = false;
    this._setHoverRow(null);
    this.removeSelection();
  }

  onpointerdown(e) {
    if (!this._mobileTakeoverActive || !this.isDropdownVisible() || !(e.target instanceof Node)) {
      return;
    }
    if (this._searchInput.contains(e.target) || this._dropElement.contains(e.target)) {
      return;
    }
    if (this._mobileCloseControl && this._mobileCloseControl.contains(e.target)) {
      return;
    }
    this.hideSuggestions();
  }

  onresize() {
    this._syncMobileTakeoverMode(true);
    if (this._mobileTakeoverActive) {
      this._debugMobileLayout();
    }
  }

  getSelection() {
    return this._dropElement.querySelector(`.dropdown-item.${ACTIVE_CLASS}`);
  }

  removeSelection() {
    const selections = this._dropElement.querySelectorAll(`.dropdown-item.${ACTIVE_CLASS}`);
    selections.forEach((selection) => {
      selection.classList.remove(...this._activeClasses());
      const row = selection.closest(`li.${HOOK_ROW_CLASS}`);
      if (row) {
        row.classList.remove(HOOK_ROW_CHOSEN_CLASS);
      }
    });
  }

  _setKeyboardNavigation(enabled) {
    this._keyboardNavigation = enabled;
    this._dropElement.classList.toggle(HOOK_MENU_KEYBOARD_CLASS, enabled);
    this._dropElement.classList.toggle(HOOK_MENU_POINTER_CLASS, !enabled);
    if (enabled) {
      this._setHoverRow(null);
    }
  }

  _setHoverRow(row = null) {
    const hovered = this._dropElement.querySelectorAll(`li.${HOOK_ROW_CLASS}.${HOOK_ROW_HOVER_CLASS}`);
    hovered.forEach((item) => item.classList.remove(HOOK_ROW_HOVER_CLASS));
    if (row) {
      row.classList.add(HOOK_ROW_HOVER_CLASS);
    }
  }

  _setActiveItem(item, updateInput = this._config.updateOnSelect) {
    this.removeSelection();
    item.classList.add(...this._activeClasses());
    const row = item.closest(`li.${HOOK_ROW_CLASS}`);
    if (row) {
      row.classList.add(HOOK_ROW_CHOSEN_CLASS);
    }
    this._searchInput.setAttribute('aria-activedescendant', item.id);
    if (updateInput) {
      this._searchInput.value = item.dataset.label;
    }
  }

  _isMobileLayoutDebugEnabled() {
    const globalFlag = String(window[MOBILE_LAYOUT_DEBUG_GLOBAL] ?? '').toLowerCase();
    if (globalFlag === '1' || globalFlag === 'true') {
      return true;
    }
    try {
      return ['1', 'true'].includes(window.localStorage.getItem(MOBILE_LAYOUT_DEBUG_STORAGE_KEY));
    } catch {
      return false;
    }
  }

  _formatMobileLayoutRect(rect) {
    const w = rect ? Math.round(rect.width) : 0;
    const h = rect ? Math.round(rect.height) : 0;
    const x = rect ? Math.round(rect.left) : -1;
    const y = rect ? Math.round(rect.top) : -1;
    return `(${w}x${h}) at (${x},${y})`;
  }

  _debugMobileLayout() {
    if (!this._isMobileLayoutDebugEnabled() || !this.isDropdownVisible()) {
      return;
    }
    const menuRect = this._dropElement.getBoundingClientRect();
    const inputRect = this._searchInput.getBoundingClientRect();
    const closeRect = this._mobileCloseControl ? this._mobileCloseControl.getBoundingClientRect() : null;
    const viewportHeight = this._viewportHeight();
    const row = this._dropElement.querySelector('.collage-artist-entry');
    const rowSummary = row instanceof HTMLElement
      ? `row:${Math.round(row.getBoundingClientRect().width)}x${Math.round(row.getBoundingClientRect().height)}`
      : 'row:none';
    console.debug(
      `Debug: AC-rich mode:${this._mobileTakeoverActive ? 'mobile' : 'desktop'} `
      + `body:${document.body.className || '(none)'} `
      + `menu:${this._dropElement.className || '(none)'} `
      + `${this._formatMobileLayoutRect(menuRect)}; `
      + `Searchbox ${this._formatMobileLayoutRect(inputRect)}; `
      + `Close button ${this._formatMobileLayoutRect(closeRect)}; `
      + `Viewport (${Math.round(window.innerWidth)}x${Math.round(viewportHeight)}); `
      + `Inline menu{h:${this._dropElement.style.height || '(empty)'}, max-h:${this._dropElement.style.maxHeight || '(empty)'}}; `
      + `${rowSummary}`
    );
  }

  _setDocumentPointerBinding(enabled) {
    if (this._documentPointerBound === enabled) {
      return;
    }
    if (enabled) {
      document.addEventListener('pointerdown', this, true);
    } else {
      document.removeEventListener('pointerdown', this, true);
    }
    this._documentPointerBound = enabled;
  }

  _setVisualViewportBinding(enabled) {
    if (this._visualViewportBound === enabled) {
      return;
    }
    const viewport = window.visualViewport;
    if (!viewport || typeof viewport.addEventListener !== 'function' || typeof viewport.removeEventListener !== 'function') {
      return;
    }
    if (enabled) {
      viewport.addEventListener('resize', this._onVisualViewportChange);
      viewport.addEventListener('scroll', this._onVisualViewportChange);
    } else {
      viewport.removeEventListener('resize', this._onVisualViewportChange);
      viewport.removeEventListener('scroll', this._onVisualViewportChange);
    }
    this._visualViewportBound = enabled;
  }

  _syncMobileViewportVars() {
    const viewport = window.visualViewport;
    const bodyStyle = document.body.style;
    const px = (value) => `${Math.max(0, Math.round(value))}px`;
    const width = viewport?.width || window.innerWidth || document.documentElement?.clientWidth || 0;
    const height = viewport?.height || window.innerHeight || document.documentElement?.clientHeight || 0;
    const top = viewport?.offsetTop || 0;
    const left = viewport?.offsetLeft || 0;
    bodyStyle.setProperty('--ac-mobile-viewport-top', px(top));
    bodyStyle.setProperty('--ac-mobile-viewport-left', px(left));
    bodyStyle.setProperty('--ac-mobile-viewport-width', px(width));
    bodyStyle.setProperty('--ac-mobile-viewport-height', px(height));
  }

  _clearMobileViewportVars() {
    MOBILE_VIEWPORT_CSS_VARS.forEach((name) => {
      document.body.style.removeProperty(name);
    });
  }

  _layoutPx(variable, fallback = 0, axis = 'width', element = document.body) {
    const raw = window.getComputedStyle(element).getPropertyValue(variable).trim();
    if (!raw) {
      return fallback;
    }
    const probe = document.createElement('div');
    probe.style.position = 'fixed';
    probe.style.visibility = 'hidden';
    probe.style.pointerEvents = 'none';
    probe.style.left = '-99999px';
    probe.style.top = '-99999px';
    probe.style.width = '0';
    probe.style.height = '0';
    probe.style[axis] = raw;
    document.body.append(probe);
    const rect = probe.getBoundingClientRect();
    probe.remove();
    const value = axis === 'height' ? rect.height : rect.width;
    return Number.isFinite(value) ? value : fallback;
  }

  _ensureMobileCloseControl() {
    if (this._mobileCloseControl) {
      return;
    }
    const close = document.createElement('button');
    close.type = 'button';
    close.classList.add(HOOK_CLOSE_MOBILE_CLASS);
    close.setAttribute('aria-label', 'Close search suggestions');
    close.textContent = '×';
    close.addEventListener('mousedown', (event) => {
      event.preventDefault();
    });
    close.addEventListener('click', this._onMobileCloseClick);
    document.body.append(close);
    this._mobileCloseControl = close;
  }

  _portalDropElementToBody() {
    if (this._dropElementPortaled || this._dropElement.parentElement === document.body) {
      this._dropElementPortaled = true;
      return;
    }
    this._dropElementOriginalParent = this._dropElement.parentElement;
    this._dropElementOriginalNextSibling = this._dropElement.nextSibling;
    document.body.append(this._dropElement);
    this._dropElementPortaled = true;
  }

  _restoreDropElementParent() {
    if (!this._dropElementPortaled) {
      return;
    }
    if (this._dropElementOriginalParent) {
      if (
        this._dropElementOriginalNextSibling
        && this._dropElementOriginalNextSibling.parentNode === this._dropElementOriginalParent
      ) {
        this._dropElementOriginalParent.insertBefore(this._dropElement, this._dropElementOriginalNextSibling);
      } else {
        this._dropElementOriginalParent.append(this._dropElement);
      }
    }
    this._dropElementOriginalParent = null;
    this._dropElementOriginalNextSibling = null;
    this._dropElementPortaled = false;
  }

  _removeMobileCloseControl() {
    if (!this._mobileCloseControl) {
      return;
    }
    this._mobileCloseControl.removeEventListener('click', this._onMobileCloseClick);
    this._mobileCloseControl.remove();
    this._mobileCloseControl = null;
  }

  _applyMobileInputInlineStyle() {
    if (this._mobileInputInlineStyle !== null) {
      this._syncMobileInputInlineGeometry();
      return;
    }
    const style = this._searchInput.style;
    this._mobileInputInlineStyle = Object.fromEntries(
      MOBILE_INPUT_INLINE_STYLE_PROPS.map((prop) => [prop, style[prop]])
    );
    this._syncMobileInputInlineGeometry();
  }

  _viewportHeight() {
    return window.visualViewport?.height || document.documentElement?.clientHeight || window.innerHeight;
  }

  _syncMobileInputInlineGeometry() {
    if (!this._mobileTakeoverActive && this._mobileInputInlineStyle === null) {
      return;
    }
    const style = this._searchInput.style;
    style.setProperty('position', 'fixed', 'important');
    style.setProperty('top', 'var(--ac-mobile-top-row-top)', 'important');
    style.setProperty('left', 'var(--ac-mobile-panel-left)', 'important');
    style.setProperty('width', 'var(--ac-mobile-input-width)', 'important');
    style.setProperty('max-width', 'var(--ac-mobile-input-width)', 'important');
    style.setProperty('z-index', 'var(--ac-mobile-input-z)', 'important');
    style.setProperty('box-sizing', 'border-box', 'important');
    style.setProperty('font-size', 'var(--ac-mobile-input-font-size)', 'important');
    style.setProperty('transition', 'none', 'important');
    style.setProperty('margin', '0', 'important');
    style.setProperty('margin-top', '0', 'important');
    style.setProperty('margin-right', '0', 'important');
    style.setProperty('margin-bottom', '0', 'important');
    style.setProperty('margin-left', '0', 'important');
    style.setProperty('transform', 'none', 'important');
    style.setProperty('display', 'block', 'important');
  }

  _restoreMobileInputInlineStyle() {
    if (this._mobileInputInlineStyle === null) {
      return;
    }
    const style = this._searchInput.style;
    for (const [prop, value] of Object.entries(this._mobileInputInlineStyle)) {
      style[prop] = value;
    }
    this._mobileInputInlineStyle = null;
  }

  _enableMobileTakeover() {
    if (!this.isDropdownVisible()) {
      return;
    }
    this._mobileTakeoverActive = true;
    this._portalDropElementToBody();
    document.body.classList.add(HOOK_BODY_MOBILE_OPEN_CLASS);
    this._searchInput.classList.add(HOOK_INPUT_MOBILE_CLASS);
    this._dropElement.classList.add(HOOK_MENU_MOBILE_CLASS);
    this._syncMobileViewportVars();
    this._setVisualViewportBinding(true);
    this._ensureMobileCloseControl();
    this._applyMobileInputInlineStyle();
    this._setDocumentPointerBinding(true);
  }

  _disableMobileTakeover() {
    this._mobileTakeoverActive = false;
    document.body.classList.remove(HOOK_BODY_MOBILE_OPEN_CLASS);
    this._searchInput.classList.remove(HOOK_INPUT_MOBILE_CLASS);
    this._dropElement.classList.remove(HOOK_MENU_MOBILE_CLASS);
    this._setDocumentPointerBinding(false);
    this._setVisualViewportBinding(false);
    this._removeMobileCloseControl();
    this._restoreMobileInputInlineStyle();
    this._clearMobileViewportVars();
    this._restoreDropElementParent();
  }

  _syncMobileTakeoverMode(forceWhenVisible = false) {
    if (!this._dropElement.classList.contains('ac-rich')) {
      this._disableMobileTakeover();
      return;
    }
    if (!this.isDropdownVisible() && !forceWhenVisible) {
      this._disableMobileTakeover();
      return;
    }
    if (this._mobileMediaQuery.matches) {
      this._enableMobileTakeover();
    } else {
      this._disableMobileTakeover();
    }
    if (this.isDropdownVisible()) {
      this._positionMenu();
      this._debugMobileLayout();
    }
  }

  _isItemEnabled(li) {
    const fc = li.firstElementChild;
    if (li.style.display === 'none' || !(fc instanceof HTMLElement)) {
      return false;
    }
    return fc.classList.contains('dropdown-item') && !fc.classList.contains('disabled');
  }

  _scrollItemIntoView(item) {
    const menu = item.parentNode;
    if (!(menu instanceof HTMLElement)) {
      return;
    }

    const menuRect = menu.getBoundingClientRect();
    const itemRect = item.getBoundingClientRect();
    if (itemRect.top < menuRect.top) {
      menu.scrollTop -= menuRect.top - itemRect.top;
    } else if (itemRect.bottom > menuRect.bottom) {
      menu.scrollTop += itemRect.bottom - menuRect.bottom;
    }
  }

  _moveSelection(dir = NEXT, sel = null) {
    const active = this.getSelection();

    if (!active) {
      if (dir === PREV) {
        return sel;
      }
      if (!sel) {
        sel = this._dropElement.firstChild;
        while (sel && !this._isItemEnabled(sel)) {
          sel = sel.nextSibling;
        }
      }
    } else {
      const sibling = dir === NEXT ? 'nextSibling' : 'previousSibling';
      sel = active.parentNode;
      do {
        sel = sel[sibling];
      } while (sel && !this._isItemEnabled(sel));
      if (sel) {
        this.removeSelection();
        this._scrollItemIntoView(sel);
      } else if (active) {
        sel = active.parentElement;
      }
    }

    if (sel) {
      const item = sel.firstElementChild;
      if (item instanceof HTMLElement) {
        this._setActiveItem(item, this._config.updateOnSelect);
      }
    } else {
      this._searchInput.setAttribute('aria-activedescendant', '');
    }
    return sel;
  }

  _createItem(lookup, item) {
    let label = item.label;

    if (this._config.highlightTyped) {
      const idx = normalize(label).indexOf(lookup);
      if (idx !== -1) {
        label =
          label.substring(0, idx)
          + `<mark class="${this._config.highlightClass}">${label.substring(idx, idx + lookup.length)}</mark>`
          + label.substring(idx + lookup.length, label.length);
      }
    }

    label = this._config.onRenderItem(item, label, this);
    label = prefilterRichArtistBioHtml(label);

    const newChild = this._createLi();
    newChild.classList.add(HOOK_ROW_CLASS);
    const newChildItem = document.createElement('div');
    newChild.append(newChildItem);
    newChildItem.id = `${this._dropElement.id}-${this._dropElement.children.length}`;
    newChildItem.classList.add('dropdown-item', 'text-truncate');
    if (this._config.itemClass) {
      newChildItem.classList.add(...this._config.itemClass.split(' '));
    }
    newChildItem.setAttribute('data-value', item.value);
    newChildItem.setAttribute('data-label', item.label);
    newChildItem.setAttribute('tabindex', '-1');
    newChildItem.setAttribute('role', 'menuitem');
    newChildItem.innerHTML = label;
    if (item.data) {
      Object.assign(newChildItem.dataset, item.data);
    }

    if (this._config.fillIn) {
      const fillIn = document.createElement('button');
      fillIn.type = 'button';
      fillIn.classList.add('btn', 'btn-link', 'border-0');
      fillIn.innerHTML = `<svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
      <path fill-rule="evenodd" d="M2 2.5a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1H3.707l10.147 10.146a.5.5 0 0 1-.708.708L3 3.707V8.5a.5.5 0 0 1-1 0z"/>
      </svg>`;
      newChild.append(fillIn);
      newChild.classList.add('d-flex', 'justify-content-between');
      fillIn.addEventListener('click', () => {
        this._searchInput.value = item.label;
        this._searchInput.focus();
      });
    }

    newChildItem.addEventListener('mouseenter', () => {
      if (this._keyboardNavigation || !this._isMouse) {
        return;
      }
      this._setHoverRow(newChild);
      this._setActiveItem(newChildItem, false);
    });

    newChildItem.addEventListener('mousedown', (event) => {
      const link = backgroundOpenLinkTarget(event);
      if (link) {
        if (isBackgroundOpenEvent(event)) {
          armBackgroundOpen(this);
          event.preventDefault();
          return;
        }
        return;
      }
      event.preventDefault();
    });

    const backgroundOpenLink = (link) => {
      armBackgroundOpen(this);
      dispatchBackgroundAnchor(link);
      restoreSearchFocus(this);
    };

    const activateItem = (event) => {
      const link = backgroundOpenLinkTarget(event);
      if (link) {
        if (isBackgroundOpenEvent(event)) {
          event.preventDefault();
          event.stopPropagation();
          backgroundOpenLink(link);
          return;
        }
        return;
      }

      if (isBackgroundOpenEvent(event)) {
        armBackgroundOpen(this);
        this._config.onSelectItem(item, this, event);
        restoreSearchFocus(this);
        return;
      }

      this._preventInput = true;
      this._searchInput.value = decodeHtml(item.label);
      if (this._hiddenInput) {
        this._hiddenInput.value = item.value;
      }
      this._config.onSelectItem(item, this, event);
      this.hideSuggestions();
      this._preventInput = false;
    };

    newChildItem.addEventListener('click', activateItem);
    newChildItem.addEventListener('auxclick', (event) => {
      if (event.button !== 1) {
        return;
      }
      activateItem(event);
    });

    return newChild;
  }

  _showDropdown() {
    this._dropElement.hidden = false;
    this._dropElement.classList.add(SHOW_CLASS);
    this._setKeyboardNavigation(false);
    this._syncMobileTakeoverMode();
    this._dropElement.setAttribute('role', 'menu');
    this._searchInput.setAttribute('aria-expanded', 'true');
    this._positionMenu();
    this._resetMenuScroll();
    window.requestAnimationFrame(() => {
      if (this.isDropdownVisible()) {
        this._resetMenuScroll();
      }
    });
    this._debugMobileLayout();
  }

  _resetMenuScroll() {
    this._dropElement.scrollTop = 0;
    this._dropElement.scrollLeft = 0;
  }

  hideSuggestions() {
    this._dropElement.classList.remove(SHOW_CLASS);
    this._searchInput.setAttribute('aria-activedescendant', '');
    this._searchInput.setAttribute('aria-expanded', 'false');
    this._disableMobileTakeover();
    this._keyboardNavigation = false;
    this._dropElement.classList.remove(HOOK_MENU_KEYBOARD_CLASS, HOOK_MENU_POINTER_CLASS);
    this._setHoverRow(null);
    this.removeSelection();
    this._dropElement.innerHTML = '';
    this._resetMenuScroll();
    this._dropElement.hidden = true;
  }

  _positionMenu() {
    if (this._mobileTakeoverActive) {
      this._syncMobileViewportVars();
      this._dropElement.style.transform = 'unset';
      this._dropElement.style.left = '';
      this._dropElement.style.top = '';
      this._dropElement.style.width = '';
      this._dropElement.style.removeProperty('height');
      this._dropElement.style.removeProperty('max-height');
      this._dropElement.style.removeProperty('max-width');
      this._syncMobileInputInlineGeometry();
      return;
    }

    const bounds = this._searchInput.getBoundingClientRect();
    const isRTL = this._searchInput.dir === 'rtl' || (this._searchInput.dir === '' && document.dir === 'rtl');
    const fullWidth = this._config.fullWidth;
    const fixed = this._config.fixed;

    let left = null;
    let top = null;

    if (fixed) {
      left = bounds.x;
      top = bounds.y + bounds.height;
      if (isRTL && !fullWidth) {
        left -= this._dropElement.offsetWidth - bounds.width;
      }
    }

    this._dropElement.style.transform = 'unset';

    if (fullWidth) {
      this._dropElement.style.width = `${this._searchInput.offsetWidth}px`;
    }

    if (left !== null) {
      this._dropElement.style.left = `${left}px`;
    }
    if (top !== null) {
      this._dropElement.style.top = `${top}px`;
    }

    let dropBounds = this._dropElement.getBoundingClientRect();

    if (fixed && left !== null) {
      const sideGutter = this._layoutPx('--ac-menu-side-gutter', 20, 'width', this._dropElement);
      const maxLeft = Math.max(sideGutter, window.innerWidth - sideGutter - dropBounds.width);
      const boundedLeft = Math.min(Math.max(sideGutter, left), maxLeft);
      if (boundedLeft !== left) {
        left = boundedLeft;
        this._dropElement.style.left = `${left}px`;
        dropBounds = this._dropElement.getBoundingClientRect();
      }
    }

    if (fixed) {
      const bottomGutter = this._layoutPx('--ac-menu-bottom-gutter', 20, 'height', this._dropElement);
      const maxHeight = Math.max(0, this._viewportHeight() - dropBounds.y - bottomGutter);
      this._dropElement.style.setProperty('max-height', `${maxHeight}px`, 'important');
    }

    const boundedDrop = this._dropElement.getBoundingClientRect();
    const h = this._viewportHeight();

    if (boundedDrop.y + boundedDrop.height > h) {
      const topOffset = fullWidth ? bounds.height + 4 : bounds.height;
      this._dropElement.style.transform = `translateY(calc(-100.1% - ${topOffset}px))`;
    }
  }
}

export default RichArtistAutocomplete;
