(function () {
   var frame = document.getElementById('vbStudioFrame');
   var layers = document.getElementById('vbStudioLayers');
   var inspector = document.getElementById('vbStudioInspector');
   var themeButton = document.querySelector('[data-vb-theme]');
   var viewportSelect = document.getElementById('vbStudioViewport');
   var pageSelect = document.getElementById('vbStudioPage');
   var logoTemplate = document.getElementById('vbStudioLogoOptions');
   var themeTemplate = document.getElementById('vbStudioThemeConfig');
   var undoButton = document.querySelector('[data-vb-home-undo]');
   var redoButton = document.querySelector('[data-vb-home-redo]');
   var copyButton = document.querySelector('[data-vb-home-copy]');
   var pasteButton = document.querySelector('[data-vb-home-paste]');
   if (!frame || !layers || !inspector) return;

   var selectedNode = null;
   var targets = [];
   var initializedDocument = null;
   var history = [];
   var historyIndex = -1;
   var clipboard = null;
   var historyTimer = 0;

   function addField(form, labelText, control) {
      var label = document.createElement('label');
      var title = document.createElement('span');
      title.textContent = labelText;
      label.appendChild(title);
      label.appendChild(control);
      form.appendChild(label);
   }

   function addHidden(form, name, value) {
      var input = document.createElement('input');
      input.type = 'hidden';
      input.name = name;
      input.value = value;
      form.appendChild(input);
   }

   function createForm(action, key) {
      var form = document.createElement('form');
      form.method = 'post';
      form.className = 'vb-inspector-form';
      addHidden(form, 'csrf', frame.dataset.vbCsrf || '');
      addHidden(form, 'action', action);
      if (key) addHidden(form, 'key', key);
      return form;
   }

   function addSaveButton(form, text) {
      var button = document.createElement('button');
      button.type = 'submit';
      button.className = 'admin-btn';
      button.textContent = text;
      form.appendChild(button);
   }

   function captureState() {
      var doc = frame.contentDocument;
      if (!doc) return {};
      var state = { targets: {}, theme: '', themeFont: '', themeValues: {} };
      doc.querySelectorAll('[data-vb-key]').forEach(function (node) {
         var key = node.dataset.vbKey;
         if (node.dataset.vbKind === 'text') state.targets[key] = { kind: 'text', value: node.textContent };
         else if (node.dataset.vbKind === 'image') state.targets[key] = { kind: 'image', value: node.getAttribute('src') || '' };
         else if (node.dataset.vbKind === 'section') state.targets[key] = {
            kind: 'section', visible: node.dataset.vbVisible !== '0', size: node.dataset.vbSize || 'normal',
            background: node.dataset.vbBackground || '', order: Number(node.dataset.vbOrder) || 10
         };
      });
      var themeStyle = doc.getElementById('vb-studio-live-theme');
      state.theme = themeStyle ? themeStyle.textContent : '';
      var themeFont = doc.getElementById('vb-studio-live-font');
      state.themeFont = themeFont ? themeFont.href : '';
      inspector.querySelectorAll('.vb-inspector-form [name]').forEach(function (field) {
         state.themeValues[field.name] = field.type === 'checkbox' ? field.checked : field.value;
      });
      return state;
   }

   function updateHistoryButtons() {
      if (undoButton) undoButton.disabled = historyIndex <= 0;
      if (redoButton) redoButton.disabled = historyIndex < 0 || historyIndex >= history.length - 1;
      if (copyButton) copyButton.disabled = !selectedNode;
      if (pasteButton) pasteButton.disabled = !selectedNode || !clipboard || clipboard.kind !== selectedNode.dataset.vbKind;
   }

   function recordHistory() {
      var next = captureState();
      if (historyIndex >= 0 && JSON.stringify(next) === JSON.stringify(history[historyIndex])) return;
      history = history.slice(0, historyIndex + 1);
      history.push(next);
      if (history.length > 100) history.shift();
      historyIndex = history.length - 1;
      updateHistoryButtons();
   }

   function resetHistory() {
      history = [captureState()];
      historyIndex = 0;
      updateHistoryButtons();
   }

   function applyState(state) {
      var doc = frame.contentDocument;
      if (!doc) return;
      Object.keys(state.targets || {}).forEach(function (key) {
         var node = doc.querySelector('[data-vb-key="' + CSS.escape(key) + '"]');
         var item = state.targets[key];
         if (!node || !item) return;
         if (item.kind === 'text') node.textContent = item.value;
         else if (item.kind === 'image') node.setAttribute('src', item.value);
         else if (item.kind === 'section') {
            node.dataset.vbVisible = item.visible ? '1' : '0';
            node.dataset.vbSize = item.size;
            node.dataset.vbBackground = item.background;
            node.dataset.vbOrder = String(item.order);
            node.classList.toggle('vb-home-section--hidden', !item.visible);
            node.classList.remove('vb-home-size--compact', 'vb-home-size--normal', 'vb-home-size--wide');
            node.classList.add('vb-home-size--' + item.size);
            node.style.order = String(item.order * 2);
            node.style.backgroundColor = item.background;
            var divider = doc.querySelector('[data-vb-divider-after="' + key + '"]');
            if (divider) { divider.style.display = item.visible ? '' : 'none'; divider.style.order = String(item.order * 2 + 1); }
         }
      });
      var themeStyle = doc.getElementById('vb-studio-live-theme');
      if (themeStyle) themeStyle.textContent = state.theme || '';
      var themeFont = doc.getElementById('vb-studio-live-font');
      if (state.themeFont) {
         if (!themeFont) {
            themeFont = doc.createElement('link');
            themeFont.id = 'vb-studio-live-font';
            themeFont.rel = 'stylesheet';
            doc.head.appendChild(themeFont);
         }
         themeFont.href = state.themeFont;
      } else if (themeFont) {
         themeFont.remove();
      }
      inspector.querySelectorAll('.vb-inspector-form [name]').forEach(function (field) {
         if (!Object.prototype.hasOwnProperty.call(state.themeValues || {}, field.name)) return;
         if (field.type === 'checkbox') field.checked = Boolean(state.themeValues[field.name]);
         else field.value = state.themeValues[field.name];
      });
      if (selectedNode) {
         var key = selectedNode.dataset.vbKey;
         var restored = doc.querySelector('[data-vb-key="' + CSS.escape(key) + '"]');
         if (restored) selectNode(restored, false);
      }
      updateHistoryButtons();
   }

   function restoreHistory(index) {
      window.clearTimeout(historyTimer);
      if (index < 0 || index >= history.length) return;
      historyIndex = index;
      applyState(history[historyIndex]);
      updateHistoryButtons();
   }

   function recordAfterPreview() {
      window.clearTimeout(historyTimer);
      historyTimer = window.setTimeout(recordHistory, 200);
   }

   function clearSelection() {
      if (selectedNode) selectedNode.classList.remove('vb-studio-selected');
      selectedNode = null;
      layers.querySelectorAll('.vb-studio-layer').forEach(function (button) {
         button.setAttribute('aria-pressed', 'false');
      });
   }

   function selectNode(node, resetUndo) {
      if (!node) return;
      clearSelection();
      selectedNode = node;
      selectedNode.classList.add('vb-studio-selected');
      layers.querySelectorAll('.vb-studio-layer').forEach(function (button) {
         button.setAttribute('aria-pressed', button.dataset.vbKey === node.dataset.vbKey ? 'true' : 'false');
      });
      renderInspector(node);
      updateHistoryButtons();
   }

   function addSelect(form, name, label, options, selected) {
      var select = document.createElement('select');
      select.name = name;
      options.forEach(function (option) {
         var element = document.createElement('option');
         element.value = option.value;
         element.textContent = option.label;
         element.selected = option.value === selected;
         select.appendChild(element);
      });
      addField(form, label, select);
      return select;
   }

   function renderTextInspector(node) {
      var pageKey = node.dataset.vbPage || '';
      var form = createForm(pageKey ? 'save_content_text' : 'save_text', node.dataset.vbKey);
      var value = document.createElement('textarea');
      value.name = 'value';
      value.required = true;
      value.maxLength = Number(node.dataset.vbMaxLength) || 500;
      value.value = node.textContent.trim();
      addHidden(form, 'locale', frame.dataset.vbLocale || 'th');
      if (pageKey) addHidden(form, 'page', pageKey);
      addField(form, node.dataset.vbLabel || 'Text', value);
      value.addEventListener('input', function () {
         node.textContent = value.value;
         recordAfterPreview();
      });
      addSaveButton(form, 'บันทึกข้อความ');
      inspector.replaceChildren(form);
   }

   function renderImageInspector(node) {
      if (!logoTemplate) return;
      var form = createForm('save_logo', 'home.logo');
      var select = document.createElement('select');
      select.name = 'media_id';
      select.appendChild(logoTemplate.content.cloneNode(true));
      select.value = frame.dataset.vbLegacyLogo === '1' ? 'keep' : (frame.dataset.vbCurrentLogo || '0');
      addField(form, node.dataset.vbLabel || 'Logo', select);
      select.addEventListener('change', function () {
         var mediaId = Number(select.value);
         if (select.value !== 'keep') {
            node.src = mediaId > 0 ? '../file.php?m=' + encodeURIComponent(String(mediaId)) : '../assets/images/logo/reps-logo.svg';
            recordHistory();
         }
      });
      addSaveButton(form, 'บันทึกโลโก้');
      inspector.replaceChildren(form);
   }

   function renderSectionInspector(node) {
      var form = createForm('save_layout', node.dataset.vbKey);
      var visible = addSelect(form, 'visible', 'การแสดงผล', [
         { value: '1', label: 'แสดง' },
         { value: '0', label: 'ซ่อน' }
      ], node.dataset.vbVisible || '1');
      var size = addSelect(form, 'size', 'ขนาดส่วน', [
         { value: 'compact', label: 'กระชับ' },
         { value: 'normal', label: 'ปกติ' },
         { value: 'wide', label: 'กว้าง/โปร่ง' }
      ], node.dataset.vbSize || 'normal');
      var backgroundEnabled = document.createElement('input');
      backgroundEnabled.type = 'checkbox';
      backgroundEnabled.name = 'use_background';
      backgroundEnabled.value = '1';
      backgroundEnabled.checked = Boolean(node.dataset.vbBackground);
      addField(form, 'กำหนดสีพื้นหลังเอง', backgroundEnabled);
      var background = document.createElement('input');
      background.type = 'color';
      background.name = 'background';
      background.value = /^#[0-9a-f]{6}$/i.test(node.dataset.vbBackground || '') ? node.dataset.vbBackground : '#ffffff';
      background.disabled = !backgroundEnabled.checked;
      addField(form, 'สีพื้นหลัง', background);

      function dividerForSection() {
         var doc = frame.contentDocument;
         return doc ? doc.querySelector('[data-vb-divider-after="' + node.dataset.vbKey + '"]') : null;
      }

      visible.addEventListener('change', function () {
         node.classList.toggle('vb-home-section--hidden', visible.value !== '1');
         node.dataset.vbVisible = visible.value;
         var divider = dividerForSection();
         if (divider) divider.style.display = visible.value === '1' ? '' : 'none';
         recordHistory();
      });
      size.addEventListener('change', function () {
         node.classList.remove('vb-home-size--compact', 'vb-home-size--normal', 'vb-home-size--wide');
         node.classList.add('vb-home-size--' + size.value);
         node.dataset.vbSize = size.value;
         recordHistory();
      });
      backgroundEnabled.addEventListener('change', function () {
         background.disabled = !backgroundEnabled.checked;
         if (!backgroundEnabled.checked) {
            node.style.backgroundColor = '';
            node.dataset.vbBackground = '';
         }
         recordHistory();
      });
      background.addEventListener('input', function () {
         if (backgroundEnabled.checked && /^#[0-9a-f]{6}$/i.test(background.value)) {
            node.style.backgroundColor = background.value;
            node.dataset.vbBackground = background.value;
            recordAfterPreview();
         }
      });

      var actions = document.createElement('div');
      actions.className = 'vb-inspector-actions';
      [
         { value: 'up', label: 'เลื่อนขึ้น' },
         { value: 'down', label: 'เลื่อนลง' }
      ].forEach(function (move) {
         var button = document.createElement('button');
         button.type = 'submit';
         button.name = 'move';
         button.value = move.value;
         button.className = 'admin-btn admin-btn-secondary';
         button.textContent = move.label;
         actions.appendChild(button);
      });
      form.appendChild(actions);
      addSaveButton(form, 'บันทึกส่วนนี้');
      inspector.replaceChildren(form);
   }

   function updateThemePreview(form) {
      var doc = frame.contentDocument;
      if (!doc) return;
      var style = doc.getElementById('vb-studio-live-theme');
      if (!style) {
         style = doc.createElement('style');
         style.id = 'vb-studio-live-theme';
         doc.head.appendChild(style);
      }
      var primary = form.elements.primary_color.value;
      var accent = form.elements.theme_accent_color.value;
      var background = form.elements.theme_bg_color.value;
      if (!/^#[0-9a-f]{6}$/i.test(primary) || !/^#[0-9a-f]{6}$/i.test(accent) || !/^#[0-9a-f]{6}$/i.test(background)) return;
      var fontNames = {
         kanit: 'Kanit',
         prompt: 'Prompt',
         sarabun: 'Sarabun',
         noto: 'Noto Sans Thai',
         plex: 'IBM Plex Sans Thai',
         anuphan: 'Anuphan'
      };
      var fontKey = form.elements.theme_font.value;
      var font = fontNames[fontKey] || 'Kanit';
      var fontUrls = {
         kanit: '',
         prompt: 'https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap',
         sarabun: 'https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap',
         noto: 'https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@300;400;500;600;700&display=swap',
         plex: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@300;400;500;600;700&display=swap',
         anuphan: 'https://fonts.googleapis.com/css2?family=Anuphan:wght@300;400;500;600;700&display=swap'
      };
      var fontUrl = fontUrls[fontKey] || '';
      var fontLink = doc.getElementById('vb-studio-live-font');
      if (fontUrl) {
         if (!fontLink) {
            fontLink = doc.createElement('link');
            fontLink.id = 'vb-studio-live-font';
            fontLink.rel = 'stylesheet';
            doc.head.appendChild(fontLink);
         }
         fontLink.href = fontUrl;
      } else if (fontLink) {
         fontLink.remove();
      }
      var radii = { square: '4px', default: '14px', round: '999px' };
      var radius = radii[form.elements.theme_radius.value] || radii.default;
      style.textContent = ':root{--ink:' + primary + ';--theme-primary:' + primary + ';--theme-accent:' + accent + ';--admin-bg:' + background + '}body{background-color:' + background + '!important;font-family:"' + font + '",sans-serif!important}.vb-home-layout .rounded-lg,.vb-home-layout .rounded-xl{border-radius:' + radius + '!important}';
   }

   function renderThemeInspector() {
      if (!themeTemplate) return;
      var form = themeTemplate.content.querySelector('form').cloneNode(true);
      var palettes = {
         default: { primary: '#161616', accent: '#D97706' },
         blue: { primary: '#1D4ED8', accent: '#0284C7' },
         emerald: { primary: '#047857', accent: '#10B981' },
         rose: { primary: '#BE123C', accent: '#E11D48' },
         amber: { primary: '#B45309', accent: '#D97706' }
      };
      form.elements.theme_color.addEventListener('change', function () {
         var palette = palettes[form.elements.theme_color.value];
         if (!palette) return;
         form.elements.primary_color.value = palette.primary;
         form.elements.theme_accent_color.value = palette.accent;
         updateThemePreview(form);
      });
      form.querySelectorAll('input[type="color"],select').forEach(function (control) {
         control.addEventListener('input', function () { updateThemePreview(form); recordAfterPreview(); });
         control.addEventListener('change', function () { updateThemePreview(form); recordHistory(); });
      });
      updateThemePreview(form);
      inspector.replaceChildren(form);
   }

   function renderInspector(node) {
      if (node.dataset.vbKind === 'text') renderTextInspector(node);
      else if (node.dataset.vbKind === 'image') renderImageInspector(node);
      else if (node.dataset.vbKind === 'section') renderSectionInspector(node);
   }

   function buildLayers(doc) {
      var seen = new Set();
      targets = Array.prototype.slice.call(doc.querySelectorAll('[data-vb-key]')).filter(function (node) {
         var key = node.dataset.vbKey;
         if (!key || seen.has(key)) return false;
         seen.add(key);
         return true;
      });
      targets.sort(function (left, right) {
         function sectionFor(node) {
            return node.dataset.vbKind === 'section' ? node : node.closest('[data-vb-kind="section"]');
         }
         var leftSection = sectionFor(left);
         var rightSection = sectionFor(right);
         var leftOrder = Number(leftSection && leftSection.dataset.vbOrder) || 0;
         var rightOrder = Number(rightSection && rightSection.dataset.vbOrder) || 0;
         if (leftOrder !== rightOrder) return leftOrder - rightOrder;
         if (left.dataset.vbKind === 'section' && right.dataset.vbKind !== 'section') return -1;
         if (right.dataset.vbKind === 'section' && left.dataset.vbKind !== 'section') return 1;
         var leftRect = left.getBoundingClientRect();
         var rightRect = right.getBoundingClientRect();
         return leftRect.top - rightRect.top || leftRect.left - rightRect.left;
      });
      doc.querySelectorAll('[data-vb-key][data-vb-kind="section"]').forEach(function (section) {
         var divider = doc.querySelector('[data-vb-divider-after="' + section.dataset.vbKey + '"]');
         if (divider && section.dataset.vbVisible === '0') divider.style.display = 'none';
      });
      layers.replaceChildren();
      targets.forEach(function (node) {
         var button = document.createElement('button');
         button.type = 'button';
         button.className = 'vb-studio-layer';
         button.dataset.vbKey = node.dataset.vbKey;
         button.dataset.vbKind = node.dataset.vbKind || '';
         button.textContent = node.dataset.vbLabel || node.dataset.vbKey;
         button.setAttribute('aria-pressed', 'false');
         button.addEventListener('click', function () { selectNode(node); });
         layers.appendChild(button);
      });
   }

   function initializeFrame() {
      var doc = frame.contentDocument;
      if (doc && doc === initializedDocument) return;
      if (!doc || !doc.querySelector('[data-vb-key]')) {
         inspector.textContent = 'ไม่สามารถเปิดหน้าเดิมในโหมดแก้ไขได้';
         layers.replaceChildren();
         return;
      }
      clearSelection();
      initializedDocument = doc;
      var style = doc.createElement('style');
      style.textContent = '[data-vb-key]{cursor:pointer!important;outline:1px dashed rgba(37,99,235,.45);outline-offset:2px}[data-vb-key].vb-studio-selected{outline:2px solid #2563eb!important;outline-offset:2px;box-shadow:0 0 0 4px rgba(37,99,235,.16)}';
      doc.head.appendChild(style);
      doc.addEventListener('click', function (event) {
         var target = event.target.closest('[data-vb-key]');
         if (target) selectNode(target);
         event.preventDefault();
         event.stopPropagation();
      }, true);
      doc.addEventListener('submit', function (event) {
         event.preventDefault();
         event.stopPropagation();
      }, true);
      buildLayers(doc);
      resetHistory();
   }

   frame.addEventListener('load', initializeFrame);
   if (frame.contentDocument && frame.contentDocument.readyState === 'complete') initializeFrame();
   if (themeButton) themeButton.addEventListener('click', renderThemeInspector);
   if (viewportSelect) {
      viewportSelect.addEventListener('change', function () {
         frame.style.width = viewportSelect.value === 'mobile'
            ? '390px'
            : viewportSelect.value === 'tablet' ? '768px' : '100%';
      });
   }
   if (pageSelect) {
      pageSelect.addEventListener('change', function () {
         var option = pageSelect.options[pageSelect.selectedIndex];
         var page = pageSelect.value;
         var path = page === 'home' ? '../index.php' : option.dataset.path;
         if (!path) return;
         frame.dataset.vbPageTarget = page;
         frame.src = path + '?visual_builder_preview=1';
      });
   }
   if (undoButton) undoButton.addEventListener('click', function () { restoreHistory(historyIndex - 1); });
   if (redoButton) redoButton.addEventListener('click', function () { restoreHistory(historyIndex + 1); });
   if (copyButton) copyButton.addEventListener('click', function () {
      if (!selectedNode) return;
      var state = captureState().targets[selectedNode.dataset.vbKey];
      if (state) clipboard = JSON.parse(JSON.stringify(state));
      updateHistoryButtons();
   });
   if (pasteButton) pasteButton.addEventListener('click', function () {
      if (!selectedNode || !clipboard || clipboard.kind !== selectedNode.dataset.vbKind) return;
      if (clipboard.kind === 'text') selectedNode.textContent = clipboard.value;
      else if (clipboard.kind === 'image') selectedNode.setAttribute('src', clipboard.value);
      else if (clipboard.kind === 'section') {
         selectedNode.dataset.vbVisible = clipboard.visible ? '1' : '0';
         selectedNode.dataset.vbSize = clipboard.size;
         selectedNode.dataset.vbBackground = clipboard.background;
         selectedNode.classList.toggle('vb-home-section--hidden', !clipboard.visible);
         selectedNode.classList.remove('vb-home-size--compact', 'vb-home-size--normal', 'vb-home-size--wide');
         selectedNode.classList.add('vb-home-size--' + clipboard.size);
         selectedNode.style.backgroundColor = clipboard.background;
         var divider = frame.contentDocument.querySelector('[data-vb-divider-after="' + selectedNode.dataset.vbKey + '"]');
         if (divider) divider.style.display = clipboard.visible ? '' : 'none';
      }
      renderInspector(selectedNode);
      recordHistory();
   });
   document.addEventListener('keydown', function (event) {
      if (!(event.ctrlKey || event.metaKey)) return;
      if (event.key.toLowerCase() === 'z') { event.preventDefault(); if (event.shiftKey) restoreHistory(historyIndex + 1); else restoreHistory(historyIndex - 1); }
      else if (event.key.toLowerCase() === 'y') { event.preventDefault(); restoreHistory(historyIndex + 1); }
   });
})();
