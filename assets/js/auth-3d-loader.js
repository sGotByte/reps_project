(function () {
   'use strict';

   var host = document.querySelector('[data-auth-3d]');
   if (!host) return;

   var scriptUrl = document.currentScript && document.currentScript.src
      ? new URL('.', document.currentScript.src)
      : new URL('assets/js/', document.baseURI);
   var threeUrl = new URL('../vendor/three/three.min.js', scriptUrl).href;
   var sceneUrl = new URL('auth-3d.js', scriptUrl).href;
   var motion = window.matchMedia('(prefers-reduced-motion: reduce)');
   var loaded = false;

   function addScript(src, onload) {
      var script = document.createElement('script');
      script.src = src;
      script.async = true;
      script.onload = onload;
      script.onerror = function () { host.dataset.sceneFallback = 'true'; };
      document.head.appendChild(script);
   }

   function loadScene() {
      if (loaded || motion.matches || document.visibilityState === 'hidden') return;
      loaded = true;
      addScript(threeUrl, function () { addScript(sceneUrl); });
   }

   function onMotionChange() {
      if (!motion.matches && host.getBoundingClientRect().top < window.innerHeight) loadScene();
   }

   if (motion.addEventListener) motion.addEventListener('change', onMotionChange);
   else if (motion.addListener) motion.addListener(onMotionChange);

   document.addEventListener('visibilitychange', function () {
      if (document.visibilityState === 'visible' && !motion.matches
         && host.getBoundingClientRect().top < window.innerHeight) loadScene();
   });

   if ('IntersectionObserver' in window) {
      var observer = new IntersectionObserver(function (entries) {
         if (entries.some(function (entry) { return entry.isIntersecting; })) {
            observer.disconnect();
            loadScene();
         }
      }, { rootMargin: '120px 0px', threshold: 0.01 });
      observer.observe(host);
   } else if (!motion.matches) {
      var idle = window.requestIdleCallback || function (callback) { return window.setTimeout(callback, 250); };
      idle(loadScene, { timeout: 1500 });
   }
})();
