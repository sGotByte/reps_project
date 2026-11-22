(function () {
   'use strict';

   var THREE = window.THREE;
   if (!THREE) return;

   document.querySelectorAll('[data-auth-3d]').forEach(function (host) {
      var canvas = host.querySelector('canvas');
      if (!canvas || host.dataset.sceneReady === 'true') return;

      var scene = new THREE.Scene();
      var camera = new THREE.PerspectiveCamera(38, 1, 0.1, 100);
      camera.position.set(0, 0, 13);
      var renderer;
      var frameId = 0;
      var visible = true;
      var disposed = false;
      var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
      var lastFrame = 0;

      try {
         renderer = new THREE.WebGLRenderer({
            canvas: canvas,
            alpha: true,
            antialias: window.innerWidth > 700,
            powerPreference: 'low-power',
            stencil: false,
            depth: true,
         });
         renderer.setClearColor(0x000000, 0);
         renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.5));
      } catch (error) {
         host.dataset.sceneFallback = 'true';
         return;
      }

      var pixelPitch = 0.31;
      var pixelGeometry = new THREE.PlaneGeometry(1, 1);
      var pixelMaterial = new THREE.MeshBasicMaterial({
         color: 0xffffff,
         transparent: true,
         opacity: 0.52,
         depthWrite: false,
      });
      var pixelTiles = null;
      var pixelCells = [];
      var pixelTransform = new THREE.Object3D();
      var pixelColor = new THREE.Color();
      var darkPixel = new THREE.Color(0xa8b2c0);
      var lightPixel = new THREE.Color(0xcbd2dc);

      function buildPixelWave(width, height) {
         if (pixelTiles) {
            scene.remove(pixelTiles);
            pixelTiles.dispose();
         }
         pixelCells = [];
         var columns = Math.ceil(width / pixelPitch) + 2;
         var rows = Math.ceil(height / pixelPitch) + 2;
         pixelTiles = new THREE.InstancedMesh(pixelGeometry, pixelMaterial, columns * rows);
         pixelTiles.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
         pixelTiles.frustumCulled = false;
         var index = 0;
         for (var row = 0; row < rows; row++) {
            for (var column = 0; column < columns; column++) {
               pixelCells.push({
                  x: -width / 2 + column * pixelPitch,
                  y: height / 2 - row * pixelPitch,
                  phase: (column * 0.42) + (row * 0.19),
               });
               pixelTransform.position.set(0, 0, 0);
               pixelTransform.scale.setScalar(0.001);
               pixelTransform.updateMatrix();
               pixelTiles.setMatrixAt(index++, pixelTransform.matrix);
            }
         }
         scene.add(pixelTiles);
         updatePixelWave(0);
      }

      function updatePixelWave(time) {
         if (!pixelTiles) return;
         for (var i = 0; i < pixelCells.length; i++) {
            var cell = pixelCells[i];
            var waveA = Math.sin(cell.x * 0.58 + cell.y * 0.5 + time);
            var waveB = Math.sin(cell.x * 0.34 - cell.y * 0.72 - time * 0.72);
            var wave = (waveA * 0.68) + (waveB * 0.32);
            var band = Math.max(0, 1 - Math.abs(wave) * 1.5);
            var edgeFade = Math.min(1, Math.max(0, (Math.abs(cell.x) - 1.5) / 2.2));
            var strength = band * (0.12 + edgeFade * 0.5);
            var size = 0.018 + (strength * 0.095);
            pixelTransform.position.set(cell.x, cell.y + wave * 0.035, 0);
            pixelTransform.scale.set(size, size, 1);
            pixelTransform.updateMatrix();
            pixelTiles.setMatrixAt(i, pixelTransform.matrix);
            pixelColor.copy(lightPixel).lerp(darkPixel, strength);
            pixelTiles.setColorAt(i, pixelColor);
         }
         pixelTiles.instanceMatrix.needsUpdate = true;
         if (pixelTiles.instanceColor) pixelTiles.instanceColor.needsUpdate = true;
      }

      function resize() {
         var width = Math.max(host.clientWidth, 1);
         var height = Math.max(host.clientHeight, 1);
         camera.aspect = width / height;
         camera.position.z = width < 640 ? 20 : 18;
         camera.updateProjectionMatrix();
         renderer.setSize(width, height, false);
         var worldHeight = 2 * camera.position.z * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
         buildPixelWave(worldHeight * camera.aspect, worldHeight);
         renderStatic();
      }

      function renderStatic() {
         if (disposed) return;
         renderer.render(scene, camera);
      }

      function stop() {
         if (frameId) cancelAnimationFrame(frameId);
         frameId = 0;
      }

      function animate(now) {
         frameId = 0;
         if (disposed || !visible || reducedMotion.matches || document.visibilityState === 'hidden') return;
         if (now - lastFrame < 1000 / 30) {
            frameId = requestAnimationFrame(animate);
            return;
         }
         lastFrame = now;
         var time = now * 0.00012;
         updatePixelWave(time);
         renderer.render(scene, camera);
         frameId = requestAnimationFrame(animate);
      }

      function start() {
         stop();
         if (reducedMotion.matches || document.visibilityState === 'hidden' || !visible) {
            renderStatic();
            return;
         }
         frameId = requestAnimationFrame(animate);
      }

      var observer = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
         visible = entries.some(function (entry) { return entry.isIntersecting; });
         start();
      }, { threshold: 0.01 }) : null;
      if (observer) observer.observe(host);

      var resizeObserver = 'ResizeObserver' in window ? new ResizeObserver(resize) : null;
      if (resizeObserver) resizeObserver.observe(host);
      else window.addEventListener('resize', resize, { passive: true });

      function onMotionChange() { start(); }
      if (reducedMotion.addEventListener) reducedMotion.addEventListener('change', onMotionChange);
      else if (reducedMotion.addListener) reducedMotion.addListener(onMotionChange);
      document.addEventListener('visibilitychange', start);

      canvas.addEventListener('webglcontextlost', function (event) {
         event.preventDefault();
         stop();
         host.dataset.sceneReady = 'false';
         host.dataset.sceneFallback = 'true';
      });

      window.addEventListener('pagehide', function () {
         disposed = true;
         stop();
         if (observer) observer.disconnect();
         if (resizeObserver) resizeObserver.disconnect();
         renderer.dispose();
         pixelGeometry.dispose();
         pixelMaterial.dispose();
         if (pixelTiles) pixelTiles.dispose();
      }, { once: true });

      resize();
      host.dataset.sceneReady = 'true';
      delete host.dataset.sceneFallback;
      start();
   });
})();
