(function () {
   var form = document.querySelector('[data-vb-editor]');
   var list = document.getElementById('vbBlockList');
   var addType = document.getElementById('vb-add-type');
   var template = document.getElementById('vb-block-template');
   var canvas = document.getElementById('vbLiveCanvas');
   var undo = document.querySelector('[data-vb-undo]');
   var redo = document.querySelector('[data-vb-redo]');
   var copy = document.querySelector('[data-vb-copy]');
   var paste = document.querySelector('[data-vb-paste]');
   if (!form || !list || !addType || !template) return;

   var maxBlocks = Number(form.dataset.maxBlocks) || 40;
   var history = [];
   var historyIndex = -1;
   var clipboard = null;
   var historyTimer = 0;
   var dragged = null;
   var selectedCard = null;
   var cards = function () { return Array.prototype.slice.call(list.querySelectorAll('[data-vb-block]')); };

   function replaceIndex(fragment, index) {
      fragment.querySelectorAll('[name], [id], [for]').forEach(function (element) {
         ['name', 'id', 'for'].forEach(function (attribute) {
            var value = element.getAttribute(attribute);
            if (value && value.indexOf('__INDEX__') !== -1) element.setAttribute(attribute, value.replaceAll('__INDEX__', String(index)));
         });
      });
   }

   function reindex() {
      cards().forEach(function (card, index) {
         card.querySelectorAll('[name]').forEach(function (element) {
            element.name = element.name.replace(/^blocks\[[^\]]+\]/, 'blocks[' + index + ']');
         });
         card.querySelectorAll('[id]').forEach(function (element) {
            element.id = element.id.replace(/^vb-(?:type-)?\d+-/, function (prefix) {
               return prefix.indexOf('type-') !== -1 ? 'vb-type-' + index + '-' : 'vb-' + index + '-';
            });
         });
         card.querySelectorAll('[for]').forEach(function (element) {
            element.htmlFor = element.htmlFor.replace(/^vb-(?:type-)?\d+-/, function (prefix) {
               return prefix.indexOf('type-') !== -1 ? 'vb-type-' + index + '-' : 'vb-' + index + '-';
            });
         });
         var number = card.querySelector('[data-vb-layer-number]');
         if (number) number.textContent = String(index + 1);
         card.dataset.vbIndex = String(index);
      });
      renderCanvas();
   }

   function setProperties(card, type, index) {
      var source = document.getElementById('vb-fields-' + type);
      var target = card.querySelector('[data-vb-properties]');
      if (!source || !target) return;
      var fragment = source.content.cloneNode(true);
      replaceIndex(fragment, index);
      target.replaceChildren(fragment);
   }

   function syncTypeLabel(card) {
      var select = card.querySelector('[data-vb-type]');
      var label = card.querySelector('[data-vb-type-label]');
      if (label && select && select.selectedIndex >= 0) label.textContent = select.options[select.selectedIndex].textContent;
   }

   function blockState(card) {
      var type = card.querySelector('[data-vb-type]');
      var visible = card.querySelector('[name$="[is_visible]"]');
      var state = { type: type ? type.value : 'heading', visible: visible ? visible.checked : true, config: {} };
      card.querySelectorAll('[name^="blocks["]').forEach(function (field) {
         var match = field.name.match(/^blocks\[[^\]]+\]\[([^\]]+)\]$/);
         if (!match || ['type', 'is_visible', 'remove'].includes(match[1])) return;
         state.config[match[1]] = field.type === 'checkbox' ? field.checked : field.value;
      });
      state.config.animation = state.config.animation || 'none';
      return state;
   }

   function snapshot() { return cards().map(blockState); }
   function same(a, b) { return JSON.stringify(a) === JSON.stringify(b); }
   function updateButtons() {
      if (undo) undo.disabled = historyIndex <= 0;
      if (redo) redo.disabled = historyIndex < 0 || historyIndex >= history.length - 1;
      if (copy) copy.disabled = cards().length === 0;
      if (paste) paste.disabled = !clipboard || cards().length >= maxBlocks;
   }
   function recordHistory() {
      var next = snapshot();
      if (historyIndex >= 0 && same(next, history[historyIndex])) return;
      history = history.slice(0, historyIndex + 1);
      history.push(next);
      if (history.length > 100) history.shift();
      historyIndex = history.length - 1;
      updateButtons();
   }

   function restoreState(state) {
      window.clearTimeout(historyTimer);
      selectedCard = null;
      list.replaceChildren();
      state.forEach(function (item, index) {
         var fragment = template.content.cloneNode(true);
         replaceIndex(fragment, index);
         var card = fragment.querySelector('[data-vb-block]');
         card.querySelector('[data-vb-type]').value = item.type;
         setProperties(card, item.type, index);
         card.querySelectorAll('[name^="blocks["]').forEach(function (field) {
            var match = field.name.match(/^blocks\[[^\]]+\]\[([^\]]+)\]$/);
            if (!match) return;
            if (match[1] === 'type') field.value = item.type;
            else if (match[1] === 'is_visible') field.checked = item.visible;
            else if (Object.prototype.hasOwnProperty.call(item.config, match[1])) {
               if (field.type === 'checkbox') field.checked = Boolean(item.config[match[1]]);
               else field.value = item.config[match[1]];
            }
         });
         list.appendChild(fragment);
      });
      if (!state.length) {
         var empty = document.createElement('div');
         empty.className = 'vb-empty';
         empty.textContent = 'No blocks yet. Add the first block above.';
         list.appendChild(empty);
      }
      cards().forEach(bindCard);
      reindex();
      updateButtons();
   }

   function addElement(tag, className, text) {
      var element = document.createElement(tag);
      if (className) element.className = className;
      if (text !== undefined && text !== null) element.textContent = text;
      return element;
   }

   function renderBlock(item, index) {
      var c = item.config, root, child;
      switch (item.type) {
         case 'heading': root = addElement('section', 'vb-block vb-block--heading'); root.appendChild(addElement(c.level === 'h3' ? 'h3' : 'h2', '', c.text)); break;
         case 'paragraph': root = addElement('section', 'vb-block vb-block--paragraph'); root.appendChild(addElement('p', '', c.text)); break;
         case 'button':
            root = addElement('section', 'vb-block vb-block--button');
            child = addElement('a', 'vb-button vb-button--' + (c.variant === 'secondary' ? 'secondary' : 'primary'), c.label);
            if (/^(https?:\/\/[^\s<>"'`\\]+|[a-z0-9_-]+(?:\/[a-z0-9_-]+)*\.php(?:\?[a-z0-9_\-=&%.]*)?(?:#[a-z0-9_-]*)?|mailto:[^@\s]+@[^@\s]+|tel:\+?[0-9\-]{3,20})$/i.test(c.url || '')) {
               child.href = c.url;
               child.addEventListener('click', function (event) { event.preventDefault(); });
            } else child.setAttribute('aria-disabled', 'true');
            root.appendChild(child); break;
         case 'image':
            root = addElement('figure', 'vb-block vb-block--image');
            if (/^[1-9][0-9]*$/.test(String(c.media_id || ''))) {
               child = addElement('img', '', null); child.src = '../file.php?m=' + encodeURIComponent(c.media_id); child.alt = c.alt || ''; child.loading = 'lazy'; root.appendChild(child);
            } else root.appendChild(addElement('div', 'vb-live-image-placeholder', 'Choose an image from the media library'));
            break;
         case 'list':
            root = addElement('section', 'vb-block vb-block--list'); child = addElement(c.style === 'number' ? 'ol' : 'ul');
            (c.text || '').split(/\r?\n/).filter(Boolean).forEach(function (line) { child.appendChild(addElement('li', '', line)); }); root.appendChild(child); break;
         case 'quote':
            root = addElement('section', 'vb-block vb-block--quote'); child = addElement('blockquote'); child.appendChild(addElement('p', '', c.text));
            if (c.cite) child.appendChild(addElement('cite', '', c.cite)); root.appendChild(child); break;
         case 'callout':
            var tone = ['success', 'warning'].includes(c.tone) ? c.tone : 'info'; root = addElement('aside', 'vb-block vb-block--callout vb-callout vb-callout--' + tone); root.appendChild(addElement('p', '', c.text)); break;
         case 'divider': root = addElement('hr', 'vb-block vb-block--divider'); break;
         case 'spacer':
            var size = ['small', 'large'].includes(c.size) ? c.size : 'medium'; root = addElement('div', 'vb-block vb-block--spacer vb-spacer--' + size); root.setAttribute('aria-hidden', 'true'); break;
         case 'form':
            root = addElement('section', 'vb-block vb-block--form');
            root.appendChild(addElement('h2', '', 'Form #' + String(c.form_id || '—')));
            root.appendChild(addElement('p', 'vb-help', 'Published form fields appear in this page preview.'));
            break;
         default: return null;
      }
      if (['fade-in', 'fade-up', 'slide-left', 'slide-right', 'zoom-in'].includes(c.animation)) root.dataset.vbAnimation = c.animation;
      root.dataset.vbPreviewIndex = String(index);
      return root;
   }

   function renderCanvas() {
      if (!canvas) return;
      var page = addElement('div', 'vb-live-canvas-page');
      page.appendChild(addElement('h1', 'vb-live-page-title', form.elements.title.value));
      snapshot().forEach(function (item, index) {
         if (!item.visible) return;
         var block = renderBlock(item, index);
         if (block) page.appendChild(block);
      });
      canvas.replaceChildren(page);
   }

   function bindCard(card) {
      card.draggable = true;
      card.addEventListener('focusin', function () {
         if (selectedCard && selectedCard !== card) selectedCard.classList.remove('vb-block-card--selected');
         selectedCard = card;
         card.classList.add('vb-block-card--selected');
      });
      var select = card.querySelector('[data-vb-type]');
      if (select) select.addEventListener('change', function () {
         setProperties(card, select.value, cards().indexOf(card)); syncTypeLabel(card); reindex(); recordHistory();
      });
      syncTypeLabel(card);
      card.querySelectorAll('[data-vb-move]').forEach(function (button) {
         button.addEventListener('click', function () {
            var sibling = button.dataset.vbMove === 'up' ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling) return;
            if (button.dataset.vbMove === 'up') list.insertBefore(card, sibling); else list.insertBefore(sibling, card);
            reindex(); recordHistory();
         });
      });
      var remove = card.querySelector('[data-vb-remove]');
      if (remove) remove.addEventListener('click', function () {
         if (cards().length <= 1) return;
         if (selectedCard === card) selectedCard = null;
         card.remove(); reindex(); recordHistory();
      });
      card.addEventListener('dragstart', function (event) {
         dragged = card; card.classList.add('vb-block-card--dragging'); event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', card.dataset.vbIndex || '');
      });
      card.addEventListener('dragend', function () {
         card.classList.remove('vb-block-card--dragging'); dragged = null;
         cards().forEach(function (item) { item.classList.remove('vb-block-card--drop-target'); });
      });
      card.addEventListener('dragover', function (event) {
         if (!dragged || dragged === card) return; event.preventDefault(); card.classList.add('vb-block-card--drop-target');
      });
      card.addEventListener('dragleave', function () { card.classList.remove('vb-block-card--drop-target'); });
      card.addEventListener('drop', function (event) {
         event.preventDefault(); if (!dragged || dragged === card) return;
         var bounds = card.getBoundingClientRect();
         list.insertBefore(dragged, event.clientY > bounds.top + bounds.height / 2 ? card.nextElementSibling : card);
         reindex(); recordHistory();
      });
   }

   function addBlock(type, item) {
      if (cards().length >= maxBlocks) { window.alert('Maximum of ' + maxBlocks + ' components per page.'); return; }
      var index = cards().length, fragment = template.content.cloneNode(true);
      replaceIndex(fragment, index);
      var card = fragment.querySelector('[data-vb-block]'), select = card.querySelector('[data-vb-type]');
      select.value = type; setProperties(card, type, index);
      if (item) {
         card.querySelectorAll('[name^="blocks["]').forEach(function (field) {
            var match = field.name.match(/^blocks\[[^\]]+\]\[([^\]]+)\]$/); if (!match) return;
            if (match[1] === 'type') field.value = type;
            else if (match[1] === 'is_visible') field.checked = item.visible;
            else if (Object.prototype.hasOwnProperty.call(item.config, match[1])) field.value = item.config[match[1]];
         });
      }
      list.querySelectorAll('.vb-empty').forEach(function (empty) { empty.remove(); });
      list.appendChild(fragment); bindCard(list.lastElementChild); reindex(); recordHistory();
   }

   cards().forEach(bindCard);
   reindex();
   recordHistory();
   var addButton = document.querySelector('[data-vb-add]');
   if (addButton) addButton.addEventListener('click', function () { addBlock(addType.value); });
   if (undo) undo.addEventListener('click', function () { if (historyIndex > 0) { window.clearTimeout(historyTimer); historyIndex--; restoreState(history[historyIndex]); } });
   if (redo) redo.addEventListener('click', function () { if (historyIndex < history.length - 1) { window.clearTimeout(historyTimer); historyIndex++; restoreState(history[historyIndex]); } });
   if (copy) copy.addEventListener('click', function () {
      var activeCard = document.activeElement && document.activeElement.closest('[data-vb-block]');
      var selected = activeCard || selectedCard || cards()[cards().length - 1];
      if (selected) clipboard = blockState(selected); updateButtons();
   });
   if (paste) paste.addEventListener('click', function () { if (clipboard) addBlock(clipboard.type, JSON.parse(JSON.stringify(clipboard))); });
   list.addEventListener('input', function () {
      renderCanvas(); window.clearTimeout(historyTimer); historyTimer = window.setTimeout(recordHistory, 250);
   });
   list.addEventListener('change', function () { reindex(); recordHistory(); });
   form.elements.title.addEventListener('input', renderCanvas);
   if (canvas) canvas.addEventListener('click', function (event) {
      var preview = event.target.closest('[data-vb-preview-index]'); if (!preview) return;
      var card = cards()[Number(preview.dataset.vbPreviewIndex)];
      if (card) { card.scrollIntoView({ behavior: 'smooth', block: 'center' }); var focus = card.querySelector('[data-vb-type]'); if (focus) focus.focus(); }
   });
   form.addEventListener('keydown', function (event) {
      if (!(event.ctrlKey || event.metaKey)) return;
      if (event.key.toLowerCase() === 'z') { event.preventDefault(); if (event.shiftKey && redo) redo.click(); else if (undo) undo.click(); }
      if (event.key.toLowerCase() === 'y') { event.preventDefault(); if (redo) redo.click(); }
   });
   form.addEventListener('submit', function () { reindex(); });
   updateButtons();
})();
