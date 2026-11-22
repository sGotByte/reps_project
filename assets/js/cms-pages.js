(function () {
   var frame = document.getElementById('vbPagePreview');
   var frameWrap = document.querySelector('[data-vb-preview-frame]');
   var viewportButtons = Array.prototype.slice.call(document.querySelectorAll('[data-vb-viewport]'));

   if (frame && frameWrap && viewportButtons.length) {
      var widths = { desktop: '100%', tablet: '768px', mobile: '390px' };
      viewportButtons.forEach(function (button) {
         button.addEventListener('click', function () {
            var size = button.getAttribute('data-vb-viewport');
            frameWrap.style.setProperty('--vb-preview-width', widths[size] || '100%');
            viewportButtons.forEach(function (other) {
               other.setAttribute('aria-pressed', other === button ? 'true' : 'false');
            });
         });
      });
   }

   document.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-cms-confirm]');
      if (!trigger) return;
      var message = trigger.getAttribute('data-cms-confirm');
      if (message && !window.confirm(message)) {
         event.preventDefault();
      }
   }, true);
})();
