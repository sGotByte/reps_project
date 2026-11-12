/**
 * REPS - 3D Computer & Hardware Repair Animation Engine
 * Theme: Monochrome / High-Contrast Black & White
 * Dedicated 3D Interactive Hardware Repair Studio Stage
 */

(() => {
   'use strict';

   const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

   // =========================================================================
   // 1. HERO 3D HARDWARE REPAIR STUDIO (Dedicated Viewport)
   // =========================================================================
   function initHero3D() {
      const canvas = document.getElementById('hero-3d');
      if (!canvas || !window.THREE) return;

      const stage = canvas.closest('.hero-3d-stage') || canvas.parentElement;
      if (!stage) return;

      let w = stage.clientWidth || 460;
      let h = stage.clientHeight || 480;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);

      let renderer;
      try {
         renderer = new THREE.WebGLRenderer({
            canvas,
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance'
         });
      } catch (err) {
         console.warn('Three.js WebGL not supported:', err);
         return;
      }

      renderer.setSize(w, h, false);
      renderer.setPixelRatio(dpr);
      renderer.toneMapping = THREE.ACESFilmicToneMapping;
      renderer.toneMappingExposure = 1.15;

      const scene = new THREE.Scene();

      // Camera: Positioned perfectly to frame the hardware rig inside the studio
      const camera = new THREE.PerspectiveCamera(40, w / h, 0.1, 100);
      camera.position.set(0, 0.7, 9.2);

      // Main 3D Rig Group
      const mainRig = new THREE.Group();
      scene.add(mainRig);

      // ----------------------------------------------------------------------
      // 1.1 Live Diagnostic Terminal Screen (CanvasTexture)
      // ----------------------------------------------------------------------
      const screenCanvas = document.createElement('canvas');
      screenCanvas.width = 1024;
      screenCanvas.height = 640;
      const sctx = screenCanvas.getContext('2d');
      const screenTexture = new THREE.CanvasTexture(screenCanvas);

      let waveOffset = 0;
      function drawScreen(t) {
         if (!sctx) return;
         // Deep obsidian background
         sctx.fillStyle = '#0a0b10';
         sctx.fillRect(0, 0, 1024, 640);

         // Terminal grid lines
         sctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
         sctx.lineWidth = 1;
         for (let x = 0; x < 1024; x += 64) {
            sctx.beginPath();
            sctx.moveTo(x, 0);
            sctx.lineTo(x, 640);
            sctx.stroke();
         }
         for (let y = 0; y < 640; y += 64) {
            sctx.beginPath();
            sctx.moveTo(0, y);
            sctx.lineTo(1024, y);
            sctx.stroke();
         }

         // Terminal Header
         sctx.fillStyle = '#161822';
         sctx.fillRect(0, 0, 1024, 72);
         sctx.fillStyle = '#ffffff';
         sctx.font = 'bold 24px "JetBrains Mono", monospace';
         sctx.fillText('REPS HARDWARE DIAGNOSTIC v3.4', 32, 46);

         // Live status pill
         sctx.fillStyle = '#22c55e';
         sctx.beginPath();
         sctx.arc(960, 36, 10, 0, Math.PI * 2);
         sctx.fill();

         sctx.fillStyle = '#94a3b8';
         sctx.font = '20px "JetBrains Mono", monospace';
         sctx.fillText('ONLINE', 864, 44);

         // Diagnostic metrics
         const metrics = [
            { label: 'CPU TEMP', val: 38 + Math.round(Math.sin(t * 1.5) * 3) + '°C', pct: 0.42 },
            { label: 'RAM LOAD', val: '8.4 / 16 GB', pct: 0.52 },
            { label: 'GPU CORE', val: 'OPTIMAL', pct: 0.78 },
            { label: 'NET LAN', val: '1000 Mbps', pct: 0.95 }
         ];

         metrics.forEach((m, idx) => {
            const my = 120 + idx * 60;
            sctx.fillStyle = '#94a3b8';
            sctx.font = '22px "JetBrains Mono", monospace';
            sctx.fillText(m.label, 32, my);

            sctx.fillStyle = '#ffffff';
            sctx.fillText(m.val, 200, my);

            // Progress bar
            sctx.fillStyle = '#27272a';
            sctx.fillRect(400, my - 18, 200, 14);
            sctx.fillStyle = '#ffffff';
            sctx.fillRect(400, my - 18, 200 * m.pct, 14);
         });

         // Oscilloscope / Heartbeat Waveform
         sctx.strokeStyle = '#ffffff';
         sctx.lineWidth = 3.5;
         sctx.beginPath();
         waveOffset += 0.08;
         for (let x = 32; x < 992; x++) {
            const relX = (x - 32) / 960;
            const wave = Math.sin(relX * 14 - waveOffset) * 32 * Math.sin(relX * Math.PI)
                       + Math.sin(relX * 28 + waveOffset * 0.7) * 12;
            const y = 440 + wave;
            if (x === 32) sctx.moveTo(x, y);
            else sctx.lineTo(x, y);
         }
         sctx.stroke();

         // Status banner
         sctx.fillStyle = 'rgba(255, 255, 255, 0.08)';
         sctx.fillRect(32, 520, 960, 84);
         sctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
         sctx.strokeRect(32, 520, 960, 84);

         sctx.fillStyle = '#ffffff';
         sctx.font = 'bold 24px "JetBrains Mono", monospace';
         const blink = Math.floor(t * 2) % 2 === 0 ? '_' : ' ';
         sctx.fillText('> SYSTEM READY: ALL CIRCUITS NOMINAL ' + blink, 56, 572);

         screenTexture.needsUpdate = true;
      }
      drawScreen(0);

      // ----------------------------------------------------------------------
      // 1.2 Cyber Laptop Computer (ตัวเครื่องโน้ตบุ๊กทรงพรีเมียม)
      // ----------------------------------------------------------------------
      const laptopGroup = new THREE.Group();
      laptopGroup.position.set(-0.8, -0.2, 0);
      mainRig.add(laptopGroup);

      // Laptop Base Chassis
      const baseMat = new THREE.MeshStandardMaterial({
         color: 0x14151a,
         roughness: 0.35,
         metalness: 0.85
      });
      const laptopBase = new THREE.Mesh(new THREE.BoxGeometry(3.6, 0.12, 2.4), baseMat);
      laptopGroup.add(laptopBase);

      // Keyboard Recess & Keycaps
      const keyMat = new THREE.MeshStandardMaterial({ color: 0x1f2029, roughness: 0.45, metalness: 0.5 });
      const keyboardArea = new THREE.Mesh(new THREE.BoxGeometry(3.2, 0.02, 1.3), keyMat);
      keyboardArea.position.set(0, 0.065, -0.35);
      laptopGroup.add(keyboardArea);

      // Trackpad
      const trackpad = new THREE.Mesh(
         new THREE.BoxGeometry(1.2, 0.015, 0.75),
         new THREE.MeshStandardMaterial({ color: 0x1a1b22, roughness: 0.25, metalness: 0.8 })
      );
      trackpad.position.set(0, 0.065, 0.65);
      laptopGroup.add(trackpad);

      // Screen Lid (Open at 108 degrees)
      const screenGroup = new THREE.Group();
      screenGroup.position.set(0, 0.06, -1.2);
      screenGroup.rotation.x = -0.32; // ~108 deg open
      laptopGroup.add(screenGroup);

      // Screen Lid Frame
      const screenLid = new THREE.Mesh(new THREE.BoxGeometry(3.6, 2.3, 0.08), baseMat);
      screenLid.position.set(0, 1.15, 0);
      screenGroup.add(screenLid);

      // Display Surface
      const screenSurface = new THREE.Mesh(
         new THREE.PlaneGeometry(3.4, 2.1),
         new THREE.MeshBasicMaterial({ map: screenTexture })
      );
      screenSurface.position.set(0, 1.15, 0.045);
      screenGroup.add(screenSurface);

      // Silver Screen Rim
      const screenRim = new THREE.LineSegments(
         new THREE.EdgesGeometry(new THREE.BoxGeometry(3.61, 2.31, 0.08)),
         new THREE.LineBasicMaterial({ color: 0x52525b })
      );
      screenRim.position.set(0, 1.15, 0);
      screenGroup.add(screenRim);

      // Hinges
      const hingeMat = new THREE.MeshStandardMaterial({ color: 0xd4d4d8, metalness: 0.95, roughness: 0.2 });
      const hingeL = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.5, 16), hingeMat);
      hingeL.rotation.z = Math.PI / 2;
      hingeL.position.set(-1.2, 0, 0);
      screenGroup.add(hingeL);

      const hingeR = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.5, 16), hingeMat);
      hingeR.rotation.z = Math.PI / 2;
      hingeR.position.set(1.2, 0, 0);
      screenGroup.add(hingeR);

      // ----------------------------------------------------------------------
      // 1.3 Motherboard Test Bench & CPU Cooler (ชุดเมนบอร์ดและพัดลมซีพียู)
      // ----------------------------------------------------------------------
      const pcRigGroup = new THREE.Group();
      pcRigGroup.position.set(1.4, -0.2, -0.3);
      mainRig.add(pcRigGroup);

      // Motherboard Base Plate
      const mbPcb = new THREE.Mesh(
         new THREE.BoxGeometry(2.0, 0.08, 2.4),
         new THREE.MeshStandardMaterial({ color: 0x121215, roughness: 0.45, metalness: 0.6 })
      );
      pcRigGroup.add(mbPcb);

      // Heatsink Fins
      const heatsinkGroup = new THREE.Group();
      heatsinkGroup.position.set(-0.2, 0.35, -0.2);
      pcRigGroup.add(heatsinkGroup);

      const finMat = new THREE.MeshStandardMaterial({ color: 0xa1a1aa, metalness: 0.88, roughness: 0.22 });
      for (let f = 0; f < 8; f++) {
         const fin = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.025, 1.0), finMat);
         fin.position.y = (f * 0.08) - 0.28;
         heatsinkGroup.add(fin);
      }

      // CPU Cooling Fan (หมุนได้จริง)
      const fanGroup = new THREE.Group();
      fanGroup.position.set(-0.2, 0.78, -0.2);
      pcRigGroup.add(fanGroup);

      const shroud = new THREE.Mesh(
         new THREE.CylinderGeometry(0.5, 0.5, 0.12, 24, 1, true),
         new THREE.MeshStandardMaterial({ color: 0x18181b, roughness: 0.3, metalness: 0.8 })
      );
      fanGroup.add(shroud);

      const fanHub = new THREE.Mesh(
         new THREE.CylinderGeometry(0.16, 0.16, 0.14, 16),
         new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 0.9, roughness: 0.2 })
      );
      fanGroup.add(fanHub);

      const bladesGroup = new THREE.Group();
      fanGroup.add(bladesGroup);
      for (let b = 0; b < 7; b++) {
         const blade = new THREE.Mesh(
            new THREE.BoxGeometry(0.28, 0.02, 0.09),
            new THREE.MeshStandardMaterial({ color: 0x27272a, roughness: 0.3, metalness: 0.6 })
         );
         const bAngle = (b / 7) * Math.PI * 2;
         blade.position.set(Math.cos(bAngle) * 0.28, 0, Math.sin(bAngle) * 0.28);
         blade.rotation.y = -bAngle;
         blade.rotation.z = 0.4;
         bladesGroup.add(blade);
      }

      // Dual RAM Modules with Silver Heatspreaders
      const ramGroup = new THREE.Group();
      pcRigGroup.add(ramGroup);
      for (let r = 0; r < 2; r++) {
         const ramStick = new THREE.Group();
         ramStick.position.set(0.6 + r * 0.22, 0.32, -0.2);

         const ramPcb = new THREE.Mesh(
            new THREE.BoxGeometry(0.04, 0.55, 1.3),
            new THREE.MeshStandardMaterial({ color: 0x18181b, roughness: 0.3, metalness: 0.7 })
         );
         ramStick.add(ramPcb);

         const ramTop = new THREE.Mesh(
            new THREE.BoxGeometry(0.06, 0.08, 1.32),
            new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 0.95, roughness: 0.15 })
         );
         ramTop.position.y = 0.28;
         ramStick.add(ramTop);
         ramGroup.add(ramStick);
      }

      // ----------------------------------------------------------------------
      // 1.4 Floating Computer Repair Tools (ประแจ & ไขควงช่างคอม)
      // ----------------------------------------------------------------------
      const toolsGroup = new THREE.Group();
      mainRig.add(toolsGroup);

      // Wrench (ประแจโครเมียมสะท้อนแสง)
      const wrenchGroup = new THREE.Group();
      wrenchGroup.position.set(-2.5, 0.4, 0.5);
      wrenchGroup.rotation.z = Math.PI / 5;
      wrenchGroup.rotation.y = 0.2;
      toolsGroup.add(wrenchGroup);

      const wrenchMat = new THREE.MeshStandardMaterial({ color: 0xd4d4d8, metalness: 0.96, roughness: 0.18 });
      const wrenchHandle = new THREE.Mesh(new THREE.BoxGeometry(0.22, 2.1, 0.08), wrenchMat);
      wrenchGroup.add(wrenchHandle);

      const wrenchJaw = new THREE.Mesh(new THREE.CylinderGeometry(0.38, 0.38, 0.1, 24), wrenchMat);
      wrenchJaw.position.y = 1.15;
      wrenchGroup.add(wrenchJaw);

      const wrenchRing = new THREE.Mesh(new THREE.TorusGeometry(0.32, 0.08, 16, 24), wrenchMat);
      wrenchRing.position.y = -1.15;
      wrenchGroup.add(wrenchRing);

      // Screwdriver (ไขควงช่าง)
      const screwdriverGroup = new THREE.Group();
      screwdriverGroup.position.set(2.6, 1.0, 0.4);
      screwdriverGroup.rotation.z = -Math.PI / 4;
      screwdriverGroup.rotation.x = 0.3;
      toolsGroup.add(screwdriverGroup);

      const sdHandle = new THREE.Mesh(
         new THREE.CylinderGeometry(0.16, 0.16, 1.0, 16),
         new THREE.MeshStandardMaterial({ color: 0x18181b, roughness: 0.5, metalness: 0.4 })
      );
      screwdriverGroup.add(sdHandle);

      const sdShaft = new THREE.Mesh(
         new THREE.CylinderGeometry(0.045, 0.045, 1.3, 12),
         new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 0.98, roughness: 0.12 })
      );
      sdShaft.position.y = -1.0;
      screwdriverGroup.add(sdShaft);

      // Mechanical Maintenance Gears (เฟืองซ่อมบำรุง)
      function createGear(radius, teeth, thickness) {
         const gGroup = new THREE.Group();
         const gMat = new THREE.MeshStandardMaterial({ color: 0x71717a, metalness: 0.9, roughness: 0.25 });
         const gBody = new THREE.Mesh(new THREE.CylinderGeometry(radius * 0.84, radius * 0.84, thickness, 32), gMat);
         gGroup.add(gBody);

         const toothW = (Math.PI * 2 * radius) / (teeth * 2);
         for (let i = 0; i < teeth; i++) {
            const angle = (i / teeth) * Math.PI * 2;
            const tooth = new THREE.Mesh(new THREE.BoxGeometry(toothW * 0.85, thickness, radius * 0.32), gMat);
            tooth.position.set(Math.cos(angle) * radius, 0, Math.sin(angle) * radius);
            tooth.rotation.y = -angle;
            gGroup.add(tooth);
         }
         return gGroup;
      }

      const gearsGroup = new THREE.Group();
      gearsGroup.position.set(-1.9, 1.9, -0.6);
      toolsGroup.add(gearsGroup);

      const gear1 = createGear(0.65, 12, 0.09);
      gearsGroup.add(gear1);

      const gear2 = createGear(0.44, 8, 0.09);
      gear2.position.set(1.05, 0, 0);
      gearsGroup.add(gear2);

      // Laser Scanner Sweep Bar
      const scanBeam = new THREE.Mesh(
         new THREE.BoxGeometry(3.4, 0.02, 0.02),
         new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.85 })
      );
      scanBeam.position.set(0, 1.15, 0.06);
      screenGroup.add(scanBeam);

      // ----------------------------------------------------------------------
      // 1.5 Studio Lighting
      // ----------------------------------------------------------------------
      const ambientLight = new THREE.AmbientLight(0xffffff, 0.9);
      scene.add(ambientLight);

      const keyLight = new THREE.DirectionalLight(0xffffff, 1.4);
      keyLight.position.set(5, 7, 6);
      scene.add(keyLight);

      const rimLight = new THREE.DirectionalLight(0x94a3b8, 0.8);
      rimLight.position.set(-5, -3, -4);
      scene.add(rimLight);

      const screenGlowLight = new THREE.PointLight(0xffffff, 1.2, 6);
      screenGlowLight.position.set(0, 1.15, 0.8);
      screenGroup.add(screenGlowLight);

      // ----------------------------------------------------------------------
      // 1.6 Physics & Interactivity
      // ----------------------------------------------------------------------
      let running = false;
      let rafId = 0;
      let clockTime = 0;
      let mouseX = 0;
      let mouseY = 0;
      let targetCamX = 0;
      let targetCamY = 0;

      let explodedMode = false;
      let explodedFactor = 0;

      let isDragging = false;
      let prevPointerX = 0;
      let prevPointerY = 0;
      let rotVelocityX = 0;
      let rotVelocityY = 0;

      function resize() {
         w = stage.clientWidth || 460;
         h = stage.clientHeight || 480;
         camera.aspect = w / h;
         camera.updateProjectionMatrix();
         renderer.setSize(w, h, false);
         renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
         if (!running) renderer.render(scene, camera);
      }

      function animate() {
         if (!running) return;
         clockTime += 0.016;

         // Live Screen updates
         if (Math.floor(clockTime * 60) % 2 === 0) {
            drawScreen(clockTime);
         }

         // Fan blade rotation
         bladesGroup.rotation.y += 0.18;

         // Gears synchronization
         gear1.rotation.y += 0.015;
         gear2.rotation.y -= 0.0225;

         // Floating tools bobbing
         wrenchGroup.position.y = 0.4 + Math.sin(clockTime * 1.5) * 0.12;
         wrenchGroup.rotation.x = Math.sin(clockTime * 0.8) * 0.15;

         screwdriverGroup.position.y = 1.1 + Math.sin(clockTime * 1.6 + 1) * 0.14;
         screwdriverGroup.rotation.y = Math.cos(clockTime * 0.7) * 0.2;

         scanBeam.position.y = 1.15 + Math.sin(clockTime * 2.5) * 0.92;

         // Exploded View Transition
         const targetFactor = explodedMode ? 1.0 : 0.0;
         explodedFactor += (targetFactor - explodedFactor) * 0.08;

         screenGroup.position.z = -1.2 - explodedFactor * 0.6;
         screenGroup.position.y = 0.06 + explodedFactor * 0.5;

         heatsinkGroup.position.y = 0.35 + explodedFactor * 0.6;
         fanGroup.position.y = 0.78 + explodedFactor * 1.1;
         ramGroup.position.y = explodedFactor * 0.5;

         wrenchGroup.position.x = -2.5 - explodedFactor * 0.5;
         screwdriverGroup.position.x = 2.6 + explodedFactor * 0.5;

         // Drag Inertia & Auto Sway
         if (!isDragging) {
            mainRig.rotation.y += 0.003 + rotVelocityX;
            mainRig.rotation.x += rotVelocityY;
            rotVelocityX *= 0.94;
            rotVelocityY *= 0.94;
         } else {
            mainRig.rotation.y += rotVelocityX;
            mainRig.rotation.x += rotVelocityY;
            rotVelocityX = 0;
            rotVelocityY = 0;
         }

         // Camera smooth parallax
         camera.position.x += (targetCamX - camera.position.x) * 0.05;
         camera.position.y += (0.9 + targetCamY - camera.position.y) * 0.05;
         camera.lookAt(0, 0.4, 0);

         renderer.render(scene, camera);
         rafId = requestAnimationFrame(animate);
      }

      function start() {
         if (running || reduceMotion.matches || document.hidden) return;
         running = true;
         rafId = requestAnimationFrame(animate);
      }

      function stop() {
         running = false;
         cancelAnimationFrame(rafId);
      }

      // Drag & Mouse Parallax inside the stage card only
      stage.addEventListener('pointermove', (e) => {
         const rect = stage.getBoundingClientRect();
         mouseX = ((e.clientX - rect.left) / rect.width - 0.5) * 2;
         mouseY = ((e.clientY - rect.top) / rect.height - 0.5) * 2;
         targetCamX = mouseX * 1.6;
         targetCamY = -mouseY * 0.9;

         if (isDragging) {
            const deltaX = e.clientX - prevPointerX;
            const deltaY = e.clientY - prevPointerY;
            rotVelocityX = deltaX * 0.007;
            rotVelocityY = deltaY * 0.007;
            prevPointerX = e.clientX;
            prevPointerY = e.clientY;
         }
      }, { passive: true });

      canvas.addEventListener('pointerdown', (e) => {
         isDragging = true;
         prevPointerX = e.clientX;
         prevPointerY = e.clientY;
         stage.classList.add('is-dragging');
      });

      const stopDrag = () => {
         if (isDragging) {
            isDragging = false;
            stage.classList.remove('is-dragging');
         }
      };
      window.addEventListener('pointerup', stopDrag);
      window.addEventListener('pointercancel', stopDrag);

      stage.addEventListener('pointerleave', () => {
         targetCamX = 0;
         targetCamY = 0;
         stopDrag();
      });

      // Exploded / Assembled View Toggle
      const btnExplode = document.getElementById('btn-3d-explode');
      const txtMode = document.getElementById('txt-3d-mode');
      const btnReset = document.getElementById('btn-3d-reset');

      if (btnExplode) {
         btnExplode.addEventListener('click', () => {
            explodedMode = !explodedMode;
            if (txtMode) {
               txtMode.textContent = explodedMode ? 'ประกอบคืน' : 'แยกชิ้นส่วน';
            }
            btnExplode.classList.toggle('bg-white/30', explodedMode);
            if (!running) renderer.render(scene, camera);
         });
      }

      if (btnReset) {
         btnReset.addEventListener('click', () => {
            mainRig.rotation.set(0, 0, 0);
            mainRig.position.set(0, 0, 0);
            targetCamX = 0;
            targetCamY = 0;
            camera.position.set(0, 0.7, 9.2);
            explodedMode = false;
            if (txtMode) txtMode.textContent = 'แยกชิ้นส่วน';
            if (btnExplode) btnExplode.classList.remove('bg-white/30');
            if (!running) renderer.render(scene, camera);
         });
      }

      if ('ResizeObserver' in window) new ResizeObserver(resize).observe(stage);
      else window.addEventListener('resize', resize);
      resize();

      if ('IntersectionObserver' in window) {
         new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) start();
            else stop();
         }, { threshold: 0 }).observe(stage);
      } else {
         start();
      }

      document.addEventListener('visibilitychange', () => {
         if (document.hidden) stop();
         else start();
      });

      reduceMotion.addEventListener('change', () => {
         if (reduceMotion.matches) {
            stop();
            renderer.render(scene, camera);
         } else {
            start();
         }
      });

      renderer.render(scene, camera);
      requestAnimationFrame(() => canvas.classList.add('is-ready'));
   }

   // =========================================================================
   // 2. CONTACT SECTION 3D COMPUTER REPAIR EMBLEM
   // =========================================================================
   function initContact3D() {
      const canvas = document.getElementById('contact-3d');
      if (!canvas || !window.THREE) return;

      const card = canvas.closest('.relative') || canvas.parentElement;
      if (!card) return;

      let w = card.clientWidth || 800;
      let h = card.clientHeight || 400;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);

      let renderer;
      try {
         renderer = new THREE.WebGLRenderer({
            canvas,
            antialias: true,
            alpha: true,
            powerPreference: 'high-performance'
         });
      } catch (e) {
         return;
      }

      renderer.setSize(w, h, false);
      renderer.setPixelRatio(dpr);

      const scene = new THREE.Scene();
      const camera = new THREE.PerspectiveCamera(45, w / h, 0.1, 100);
      camera.position.set(0, 0, 8);

      scene.add(new THREE.AmbientLight(0xffffff, 0.9));
      const dirLight = new THREE.DirectionalLight(0xffffff, 1.2);
      dirLight.position.set(5, 5, 5);
      scene.add(dirLight);

      // Crossed Wrench & Screwdriver emblem in deep space
      const emblemGroup = new THREE.Group();
      emblemGroup.position.set(3.4, 0.2, -1.2);
      scene.add(emblemGroup);

      const toolMat = new THREE.MeshStandardMaterial({ color: 0xd4d4d8, metalness: 0.95, roughness: 0.2 });

      const wrench1 = new THREE.Mesh(new THREE.BoxGeometry(0.18, 2.2, 0.08), toolMat);
      wrench1.rotation.z = Math.PI / 4;
      emblemGroup.add(wrench1);

      const sdriver = new THREE.Mesh(new THREE.BoxGeometry(0.14, 2.2, 0.08), toolMat);
      sdriver.rotation.z = -Math.PI / 4;
      emblemGroup.add(sdriver);

      const gearMesh = new THREE.Mesh(
         new THREE.CylinderGeometry(0.7, 0.7, 0.08, 16),
         new THREE.MeshStandardMaterial({ color: 0x52525b, metalness: 0.9, roughness: 0.3 })
      );
      gearMesh.rotation.x = Math.PI / 2;
      gearMesh.position.z = -0.15;
      emblemGroup.add(gearMesh);

      // Microchips
      const chips = [];
      for (let i = 0; i < 4; i++) {
         const chip = new THREE.Mesh(
            new THREE.BoxGeometry(0.5, 0.06, 0.5),
            new THREE.MeshStandardMaterial({ color: 0xa1a1aa, metalness: 0.9, roughness: 0.25 })
         );
         chip.position.set(-4 + i * 2.2, (Math.random() - 0.5) * 4, (Math.random() - 0.5) * 3);
         scene.add(chip);
         chips.push(chip);
      }

      // Constellation points
      const count = 90;
      const posArray = new Float32Array(count * 3);
      for (let i = 0; i < count; i++) {
         posArray[i * 3] = (Math.random() - 0.5) * 16;
         posArray[i * 3 + 1] = (Math.random() - 0.5) * 8;
         posArray[i * 3 + 2] = (Math.random() - 0.5) * 6;
      }
      const geom = new THREE.BufferGeometry();
      geom.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
      const points = new THREE.Points(
         geom,
         new THREE.PointsMaterial({ color: 0xffffff, size: 0.06, transparent: true, opacity: 0.5 })
      );
      scene.add(points);

      let running = false;
      let rafId = 0;
      let targetCamX = 0;
      let targetCamY = 0;

      function resize() {
         w = card.clientWidth || 800;
         h = card.clientHeight || 400;
         camera.aspect = w / h;
         camera.updateProjectionMatrix();
         renderer.setSize(w, h, false);
         if (!running) renderer.render(scene, camera);
      }

      function animate() {
         if (!running) return;

         emblemGroup.rotation.y += 0.008;
         emblemGroup.rotation.x = Math.sin(Date.now() * 0.001) * 0.2;

         for (let i = 0; i < chips.length; i++) {
            chips[i].rotation.y += 0.015;
            chips[i].rotation.x += 0.01;
         }

         camera.position.x += (targetCamX - camera.position.x) * 0.04;
         camera.position.y += (targetCamY - camera.position.y) * 0.04;
         camera.lookAt(0, 0, 0);

         renderer.render(scene, camera);
         rafId = requestAnimationFrame(animate);
      }

      function start() {
         if (running || reduceMotion.matches || document.hidden) return;
         running = true;
         rafId = requestAnimationFrame(animate);
      }

      function stop() {
         running = false;
         cancelAnimationFrame(rafId);
      }

      card.addEventListener('pointermove', (e) => {
         const rect = card.getBoundingClientRect();
         targetCamX = (((e.clientX - rect.left) / rect.width) - 0.5) * 1.5;
         targetCamY = -(((e.clientY - rect.top) / rect.height) - 0.5) * 1.0;
      }, { passive: true });

      card.addEventListener('pointerleave', () => {
         targetCamX = 0;
         targetCamY = 0;
      });

      if ('ResizeObserver' in window) new ResizeObserver(resize).observe(card);
      else window.addEventListener('resize', resize);
      resize();

      if ('IntersectionObserver' in window) {
         new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) start();
            else stop();
         }, { threshold: 0 }).observe(card);
      } else {
         start();
      }

      renderer.render(scene, camera);
      requestAnimationFrame(() => canvas.classList.add('is-ready'));
   }

   // =========================================================================
   // 3. 3D CARD TILT & SPECULAR LIGHT PHYSICS (Services & Bento Features)
   // =========================================================================
   function init3DTiltCards() {
      if (reduceMotion.matches) return;

      const tiltCards = document.querySelectorAll('.service-card, .feature-item, .tilt-card');
      if (!tiltCards.length) return;

      tiltCards.forEach((card) => {
         card.classList.add('tilt-card');

         let glare = card.querySelector('.tilt-glare');
         if (!glare) {
            glare = document.createElement('div');
            glare.className = 'tilt-glare';
            card.appendChild(glare);
         }

         let isHovered = false;

         card.addEventListener('pointerenter', () => {
            isHovered = true;
            card.style.transition = 'transform 0.15s ease-out';
         });

         card.addEventListener('pointermove', (e) => {
            if (!isHovered) return;
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const xNorm = (x / rect.width - 0.5) * 2;
            const yNorm = (y / rect.height - 0.5) * 2;

            const rotX = -yNorm * 7;
            const rotY = xNorm * 7;

            card.style.transform = `perspective(1000px) rotateX(${rotX.toFixed(2)}deg) rotateY(${rotY.toFixed(2)}deg) scale3d(1.02, 1.02, 1.02)`;

            const glareX = (x / rect.width) * 100;
            const glareY = (y / rect.height) * 100;
            glare.style.background = `radial-gradient(circle at ${glareX.toFixed(1)}% ${glareY.toFixed(1)}%, rgba(255, 255, 255, 0.35), transparent 65%)`;
         });

         card.addEventListener('pointerleave', () => {
            isHovered = false;
            card.style.transition = 'transform 0.5s cubic-bezier(0.2, 0.8, 0.2, 1)';
            card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
         });
      });
   }

   // Initialize on DOM load
   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', () => {
         initHero3D();
         initContact3D();
         init3DTiltCards();
      });
   } else {
      initHero3D();
      initContact3D();
      init3DTiltCards();
   }

})();
