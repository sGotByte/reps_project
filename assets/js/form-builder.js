(function () {
   var editor = document.getElementById('fbEditor');
   var list = document.getElementById('fbFieldList');
   if (!editor || !list) return;
   var maxFields = Number(editor.dataset.maxFields) || 20;

   function fields() { return Array.prototype.slice.call(list.querySelectorAll('[data-form-field]')); }
   function reindex() {
      fields().forEach(function (card, index) {
         card.querySelectorAll('[name]').forEach(function (control) {
            control.name = control.name.replace(/definition\[fields\]\[\d+\]/, 'definition[fields][' + index + ']');
         });
         card.querySelector('legend').textContent = 'Field ' + (index + 1);
         card.querySelectorAll('[data-field-move="up"]').forEach(function (button) { button.disabled = index === 0; });
         card.querySelectorAll('[data-field-move="down"]').forEach(function (button) { button.disabled = index === fields().length - 1; });
         var type = card.querySelector('[data-form-field-type]');
         var options = card.querySelector('.fb-options');
         if (options) options.hidden = !type || type.value !== 'select';
      });
      var removeButtons = list.querySelectorAll('[data-field-remove]');
      removeButtons.forEach(function (button) { button.disabled = removeButtons.length <= 1; });
      var add = document.querySelector('[data-field-add]');
      if (add) add.disabled = fields().length >= maxFields;
   }

   list.addEventListener('click', function (event) {
      var card = event.target.closest('[data-form-field]');
      if (!card) return;
      var remove = event.target.closest('[data-field-remove]');
      if (remove && fields().length > 1) { card.remove(); reindex(); return; }
      var move = event.target.closest('[data-field-move]');
      if (!move) return;
      var sibling = move.dataset.fieldMove === 'up' ? card.previousElementSibling : card.nextElementSibling;
      if (!sibling) return;
      if (move.dataset.fieldMove === 'up') list.insertBefore(card, sibling);
      else list.insertBefore(sibling, card);
      reindex();
   });
   list.addEventListener('change', function (event) {
      if (event.target.matches('[data-form-field-type]')) reindex();
   });
   var add = document.querySelector('[data-field-add]');
   if (add) add.addEventListener('click', function () {
      var current = fields();
      if (!current.length || current.length >= maxFields) return;
      var card = current[0].cloneNode(true);
      card.querySelectorAll('input').forEach(function (input) {
         if (input.type === 'checkbox') input.checked = false;
         else input.value = '';
      });
      var type = card.querySelector('[data-form-field-type]');
      if (type) type.value = 'text';
      var options = card.querySelector('.fb-options textarea');
      if (options) options.value = '';
      list.appendChild(card);
      reindex();
      var firstInput = card.querySelector('input:not([type="checkbox"])');
      if (firstInput) firstInput.focus();
   });
   reindex();
})();
