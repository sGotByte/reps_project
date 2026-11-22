/*
 * REPS · photo-input.js — ช่องอัปโหลดรูปที่ใช้บนมือถือได้จริง
 *
 *   <input type="file" name="x" accept="image/jpeg,image/png,image/webp" data-photo-input
 *          [multiple data-max="3"] [data-max-mb="5"] [required]>
 *
 * - ทุกอุปกรณ์มี 2 ปุ่ม "ถ่ายรูป" และ "เลือกรูป" สูงเท่าช่องกรอก
 *   มือถือ/แท็บเล็ต: ถ่ายรูปเปิดแอปกล้องหลัง (capture=environment)
 *   คอมพิวเตอร์/โน้ตบุ๊ก: ถ่ายรูปเปิดเว็บแคมในหน้าต่างของเว็บ (getUserMedia, ต้องเป็น HTTPS) ไม่มีกล้อง/ไม่อนุญาต -> แจ้งให้ใช้เลือกรูป
 *   ลากไฟล์มาวางที่แถวปุ่มได้
 * - แสดงรูปตัวอย่าง ลบทีละรูป ถ่ายเพิ่มทีละรูปได้จนครบ data-max (ช่อง multiple)
 * - ย่อรูปจากกล้องที่ใหญ่เกิน 1.2 MB ให้ด้านยาวไม่เกิน 2000 px (JPEG) ก่อนส่ง ส่งเร็วขึ้นและไม่ติดเพดานขนาด
 *   (เซิร์ฟเวอร์ยังตรวจ MIME/ขนาด และย่อซ้ำเสมอ ไม่ได้พึ่งฝั่งนี้)
 * - ไม่มี JavaScript = ใช้ช่องเลือกไฟล์ปกติของเบราว์เซอร์ได้เหมือนเดิม
 * แถวที่ clone มาจากแถวเดิม เรียก RepsPhotoInput.reset(element) เพื่อล้างรูปที่ติดมา
 */
(function () {
    'use strict';
    if (window.RepsPhotoInput) return;

    var EN = (document.documentElement.lang || '').toLowerCase().indexOf('en') === 0;
    var T = EN ? {
        camera: 'Take photo', gallery: 'Gallery', pick: 'Choose image', more: 'Add another',
        drop: 'or drag an image here', remove: 'Remove', processing: 'Preparing image...',
        type: 'Only JPG, PNG or WEBP images', big: 'Image is larger than {n} MB',
        max: 'Up to {n} images', required: 'Please add a photo', count: '{n} of {m} images',
        camTitle: 'Take a photo', close: 'Close', shoot: 'Capture', retake: 'Retake', use: 'Use photo', switchCam: 'Switch camera',
        camDenied: 'Camera access was blocked. Allow the camera in the browser, or use Choose image.',
        camNone: 'No camera found on this device. Use Choose image instead.'
    } : {
        camera: 'ถ่ายรูป', gallery: 'คลังภาพ', pick: 'เลือกรูป', more: 'ถ่าย/เลือกเพิ่ม',
        drop: 'หรือลากรูปมาวางที่นี่', remove: 'ลบรูป', processing: 'กำลังเตรียมรูป...',
        type: 'รับเฉพาะรูป JPG, PNG หรือ WEBP', big: 'รูปใหญ่เกิน {n} MB',
        max: 'ได้สูงสุด {n} รูป', required: 'กรุณาแนบรูป', count: '{n} จาก {m} รูป',
        camTitle: 'ถ่ายรูป', close: 'ปิด', shoot: 'ถ่าย', retake: 'ถ่ายใหม่', use: 'ใช้รูปนี้', switchCam: 'สลับกล้อง',
        camDenied: 'เบราว์เซอร์ไม่อนุญาตให้ใช้กล้อง กรุณากดอนุญาตกล้อง หรือใช้ปุ่มเลือกรูปแทน',
        camNone: 'ไม่พบกล้องบนอุปกรณ์นี้ กรุณาใช้ปุ่มเลือกรูปแทน'
    };
    var ICON = {
        camera: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8.5A2.5 2.5 0 0 1 6.5 6h1.4l1.2-1.6A1 1 0 0 1 9.9 4h4.2a1 1 0 0 1 .8.4L16.1 6h1.4A2.5 2.5 0 0 1 20 8.5v8A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5z"/><circle cx="12" cy="12.5" r="3.4"/></svg>',
        image: '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9" cy="10" r="1.6"/><path d="M20.5 15.5l-4.6-4.6a1 1 0 0 0-1.4 0L7 18.5"/></svg>',
        close: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 7l10 10M17 7L7 17"/></svg>'
    };
    var TOUCH = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
    var CAN_SET_FILES = (function () { try { return typeof DataTransfer === 'function' && !!new DataTransfer().items; } catch (e) { return false; } })();
    var pending = 0;

    function fmt(s, n, m) { return s.replace('{n}', n).replace('{m}', m); }
    function maxFiles(input) { return input.multiple ? Math.max(1, parseInt(input.dataset.max || '10', 10)) : 1; }
    function maxBytes(input) { return (parseFloat(input.dataset.maxMb || '5') || 5) * 1048576; }
    function allowed(input, file) {
        var accept = (input.getAttribute('accept') || 'image/*').split(',').map(function (s) { return s.trim(); });
        return accept.some(function (a) { return a === file.type || (a.slice(-2) === '/*' && file.type.indexOf(a.slice(0, -1)) === 0); });
    }

    /* ย่อรูปใหญ่จากกล้อง (คงทิศทางตาม EXIF) — ไม่สำเร็จหรือไม่เล็กลงก็ใช้ไฟล์เดิม */
    function shrink(file) {
        if (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size <= 1258291 || !window.HTMLCanvasElement) {
            return Promise.resolve(file);
        }
        var load = window.createImageBitmap
            ? createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return createImageBitmap(file); })
            : new Promise(function (ok, fail) {
                var img = new Image(); var url = URL.createObjectURL(file);
                img.onload = function () { URL.revokeObjectURL(url); ok(img); };
                img.onerror = function () { URL.revokeObjectURL(url); fail(); };
                img.src = url;
            });
        return load.then(function (bmp) {
            var w = bmp.width, h = bmp.height, scale = Math.min(1, 2000 / Math.max(w, h));
            var canvas = document.createElement('canvas');
            canvas.width = Math.round(w * scale); canvas.height = Math.round(h * scale);
            var ctx = canvas.getContext('2d');
            ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
            if (bmp.close) bmp.close();
            return new Promise(function (ok) { canvas.toBlob(ok, 'image/jpeg', 0.85); });
        }).then(function (blob) {
            if (!blob || blob.size >= file.size) return file;
            var name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
            return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
        }).catch(function () { return file; });
    }

    function setFiles(input, files) {
        if (!CAN_SET_FILES) return;
        var dt = new DataTransfer();
        files.forEach(function (f) { dt.items.add(f); });
        input.files = dt.files;
    }

    function wrapOf(el) { return el.closest('.photo-in'); }

    function render(input) {
        var wrap = wrapOf(input); if (!wrap) return;
        var list = wrap.querySelector('.photo-in__list');
        list.querySelectorAll('img').forEach(function (img) { URL.revokeObjectURL(img.src); });
        list.innerHTML = '';
        var files = Array.prototype.slice.call(input.files || []);
        files.forEach(function (f, i) {
            var li = document.createElement('li'); li.className = 'photo-in__item';
            var img = document.createElement('img'); img.alt = ''; img.src = URL.createObjectURL(f);
            var meta = document.createElement('span'); meta.className = 'photo-in__meta';
            meta.textContent = (f.size / 1048576 >= 1 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB');
            li.appendChild(img); li.appendChild(meta);
            if (CAN_SET_FILES) {
                var rm = document.createElement('button');
                rm.type = 'button'; rm.className = 'photo-in__rm'; rm.dataset.piRemove = String(i);
                rm.setAttribute('aria-label', T.remove + ' ' + (i + 1)); rm.innerHTML = ICON.close;
                li.appendChild(rm);
            }
            list.appendChild(li);
        });
        var max = maxFiles(input);
        var full = files.length >= max;
        wrap.classList.toggle('has-files', files.length > 0);
        wrap.classList.toggle('is-full', full);
        var count = wrap.querySelector('.photo-in__count');
        if (count) count.textContent = input.multiple && files.length ? fmt(T.count, files.length, max) : '';
        // ช่องเดี่ยวที่มีรูปแล้ว ปุ่มยังใช้แทนรูปเดิมได้ · ช่องหลายรูปที่ครบแล้วซ่อนปุ่ม
        wrap.querySelectorAll('[data-pi-open]').forEach(function (b) { b.hidden = input.multiple && full; });
        var camLabel = wrap.querySelector('[data-pi-open="camera"] .photo-in__label');
        if (camLabel) camLabel.textContent = input.multiple && files.length ? T.more : T.camera;
    }

    function message(input, text) {
        var wrap = wrapOf(input); if (!wrap) return;
        var msg = wrap.querySelector('.photo-in__msg');
        msg.textContent = text || '';
        wrap.classList.toggle('has-error', !!text);
    }

    /* รวมไฟล์ใหม่กับของเดิม ตรวจชนิด/ขนาด/จำนวน และย่อรูป */
    function accept(input, incoming) {
        var wrap = wrapOf(input);
        var keep = input.multiple ? (input._piKeep || []).slice() : [];
        var max = maxFiles(input), limit = maxBytes(input), err = '';
        var picked = [];
        Array.prototype.forEach.call(incoming, function (f) {
            if (!allowed(input, f)) { err = T.type; return; }
            if (keep.length + picked.length >= max) { err = fmt(T.max, max); return; }
            picked.push(f);
        });
        if (!picked.length) {
            setFiles(input, keep); input._piKeep = keep; render(input); message(input, err);
            return Promise.resolve();
        }
        pending++; wrap.classList.add('is-busy'); message(input, T.processing);
        return Promise.all(picked.map(shrink)).then(function (files) {
            files = files.filter(function (f) {
                if (f.size > limit) { err = fmt(T.big, Math.round(limit / 1048576)); return false; }
                return true;
            });
            var all = keep.concat(files);
            input._piKeep = all;
            setFiles(input, all);
        }).then(function () {
            pending--; wrap.classList.remove('is-busy');
            render(input); message(input, err);
            if (!CAN_SET_FILES) { input._piKeep = Array.prototype.slice.call(input.files || []); }
            input.dispatchEvent(new CustomEvent('photo-input:change', { bubbles: true }));
        });
    }

    /* กล้องบนคอมพิวเตอร์/โน้ตบุ๊ก: เปิดเว็บแคมในหน้าเว็บ (ต้องเป็น HTTPS หรือ localhost) */
    var WEBCAM = !TOUCH && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia) && window.isSecureContext !== false;
    var cam = null;

    function camDialog() {
        if (cam) return cam;
        var d = document.createElement('dialog');
        d.className = 'photo-cam';
        d.setAttribute('aria-label', T.camTitle);
        d.innerHTML = '<div class="photo-cam__head"><span>' + T.camTitle + '</span>'
            + '<button type="button" class="photo-cam__x" data-cam="close" aria-label="' + T.close + '">' + ICON.close + '</button></div>'
            + '<div class="photo-cam__stage"><video playsinline muted autoplay></video><img alt="" hidden><p class="photo-cam__err" hidden></p></div>'
            + '<div class="photo-cam__bar">'
            + '<button type="button" class="photo-in__btn" data-cam="switch" hidden>' + T.switchCam + '</button>'
            + '<button type="button" class="photo-in__btn photo-in__btn--primary" data-cam="shoot">' + ICON.camera + '<span>' + T.shoot + '</span></button>'
            + '<button type="button" class="photo-in__btn" data-cam="retake" hidden>' + T.retake + '</button>'
            + '<button type="button" class="photo-in__btn photo-in__btn--primary" data-cam="use" hidden>' + T.use + '</button>'
            + '</div>';
        document.body.appendChild(d);
        cam = { d: d, video: d.querySelector('video'), img: d.querySelector('img'), err: d.querySelector('.photo-cam__err'), stream: null, input: null, blob: null, facing: 'environment' };
        d.addEventListener('close', camStop);
        d.addEventListener('click', function (e) {
            var b = e.target.closest('[data-cam]');
            if (!b) return;
            var act = b.dataset.cam;
            if (act === 'close') d.close();
            if (act === 'switch') { cam.facing = cam.facing === 'environment' ? 'user' : 'environment'; camStart(); }
            if (act === 'shoot') camShoot();
            if (act === 'retake') camStart();
            if (act === 'use' && cam.blob) {
                var file = new File([cam.blob], 'camera-' + Date.now() + '.jpg', { type: 'image/jpeg', lastModified: Date.now() });
                var input = cam.input;
                d.close();
                accept(input, [file]);
            }
        });
        return cam;
    }
    function camMode(shooting) {
        cam.video.hidden = !shooting; cam.img.hidden = shooting;
        cam.d.querySelector('[data-cam="shoot"]').hidden = !shooting;
        cam.d.querySelector('[data-cam="retake"]').hidden = shooting;
        cam.d.querySelector('[data-cam="use"]').hidden = shooting;
    }
    function camStop() {
        if (cam && cam.stream) { cam.stream.getTracks().forEach(function (t) { t.stop(); }); cam.stream = null; }
    }
    function camStart() {
        camStop();
        camMode(true);
        cam.err.hidden = true;
        cam.blob = null;
        return navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: cam.facing }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false })
            .then(function (stream) {
                cam.stream = stream;
                cam.video.srcObject = stream;
                return navigator.mediaDevices.enumerateDevices();
            })
            .then(function (devices) {
                var n = devices.filter(function (x) { return x.kind === 'videoinput'; }).length;
                cam.d.querySelector('[data-cam="switch"]').hidden = n < 2;
                cam.d.querySelector('[data-cam="shoot"]').focus();
            })
            .catch(function (err) {
                cam.video.hidden = true;
                cam.d.querySelector('[data-cam="shoot"]').hidden = true;
                cam.err.hidden = false;
                cam.err.textContent = (err && (err.name === 'NotAllowedError' || err.name === 'SecurityError')) ? T.camDenied : T.camNone;
            });
    }
    function camShoot() {
        var v = cam.video;
        if (!v.videoWidth) return;
        var scale = Math.min(1, 2000 / Math.max(v.videoWidth, v.videoHeight));
        var c = document.createElement('canvas');
        c.width = Math.round(v.videoWidth * scale); c.height = Math.round(v.videoHeight * scale);
        c.getContext('2d').drawImage(v, 0, 0, c.width, c.height);
        c.toBlob(function (blob) {
            if (!blob) return;
            cam.blob = blob;
            if (cam.img.src) URL.revokeObjectURL(cam.img.src);
            cam.img.src = URL.createObjectURL(blob);
            camStop();
            camMode(false);
            cam.d.querySelector('[data-cam="use"]').focus();
        }, 'image/jpeg', 0.88);
    }
    function openWebcam(input) {
        camDialog();
        cam.input = input;
        if (typeof cam.d.showModal === 'function') cam.d.showModal(); else cam.d.setAttribute('open', '');
        camStart();
    }

    function open(input, mode) {
        if (mode === 'camera' && WEBCAM && CAN_SET_FILES) { openWebcam(input); return; }
        if (mode === 'camera') input.setAttribute('capture', 'environment');
        else input.removeAttribute('capture');
        input._piOpening = true;
        input.click();
        setTimeout(function () { input.removeAttribute('capture'); }, 1000);
    }

    function enhance(input) {
        if (input._piDone || input.closest('.photo-in')) return;
        input._piDone = true;
        var wrap = document.createElement('div');
        wrap.className = 'photo-in';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);
        input.classList.add('photo-in__native');
        input.tabIndex = -1;
        // ทุกอุปกรณ์มีให้เลือกทั้ง "ถ่ายรูป" และ "เลือกรูป" (คอมพิวเตอร์รับการลากไฟล์มาวางที่แถวปุ่มได้ด้วย)
        var html = '<div class="photo-in__drop" title="' + (TOUCH ? '' : T.drop) + '">'
            + '<button type="button" class="photo-in__btn" data-pi-open="camera">' + ICON.camera + '<span class="photo-in__label">' + T.camera + '</span></button>'
            + '<button type="button" class="photo-in__btn" data-pi-open="gallery">' + ICON.image + '<span class="photo-in__label">' + T.pick + '</span></button>'
            + '</div><span class="photo-in__count" aria-live="polite"></span><ul class="photo-in__list"></ul><p class="photo-in__msg" role="alert"></p>';
        wrap.insertAdjacentHTML('beforeend', html);
        input._piKeep = Array.prototype.slice.call(input.files || []);
        render(input);
    }

    function enhanceAll(root) {
        (root || document).querySelectorAll('input[type=file][data-photo-input]').forEach(enhance);
    }

    /* ล้างรูปในแถวที่ clone มา */
    function reset(root) {
        (root || document).querySelectorAll('input[type=file][data-photo-input]').forEach(function (input) {
            input.value = ''; input._piKeep = []; input._piDone = true;
            render(input); message(input, '');
        });
    }

    document.addEventListener('click', function (e) {
        var openBtn = e.target.closest('[data-pi-open]');
        if (openBtn) {
            var input = wrapOf(openBtn).querySelector('.photo-in__native');
            open(input, openBtn.dataset.piOpen);
            return;
        }
        var rm = e.target.closest('[data-pi-remove]');
        if (rm) {
            var inp = wrapOf(rm).querySelector('.photo-in__native');
            var keep = Array.prototype.slice.call(inp.files || []);
            keep.splice(parseInt(rm.dataset.piRemove, 10), 1);
            inp._piKeep = keep; setFiles(inp, keep); render(inp); message(inp, '');
            var next = wrapOf(inp).querySelector('[data-pi-open]:not([hidden])');
            if (next) next.focus();
            inp.dispatchEvent(new CustomEvent('photo-input:change', { bubbles: true }));
        }
    });

    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('.photo-in__native')) return;
        var files = Array.prototype.slice.call(input.files || []);
        // ผู้ใช้กดยกเลิกในหน้าเลือกไฟล์: คงรูปเดิมไว้
        if (!files.length) { setFiles(input, input._piKeep || []); render(input); return; }
        accept(input, files);
    });

    document.addEventListener('invalid', function (e) {
        var input = e.target;
        if (!input.matches || !input.matches('.photo-in__native')) return;
        e.preventDefault();
        message(input, T.required);
        var btn = wrapOf(input).querySelector('[data-pi-open]');
        if (btn) { btn.focus(); btn.scrollIntoView({ block: 'center', behavior: 'smooth' }); }
    }, true);

    ['dragenter', 'dragover'].forEach(function (type) {
        document.addEventListener(type, function (e) {
            var wrap = e.target.closest && e.target.closest('.photo-in');
            if (!wrap) return;
            e.preventDefault(); wrap.classList.add('is-drag');
        });
    });
    ['dragleave', 'drop'].forEach(function (type) {
        document.addEventListener(type, function (e) {
            var wrap = e.target.closest && e.target.closest('.photo-in');
            if (!wrap) return;
            e.preventDefault(); wrap.classList.remove('is-drag');
            if (type === 'drop' && e.dataTransfer && e.dataTransfer.files.length) {
                accept(wrap.querySelector('.photo-in__native'), e.dataTransfer.files);
            }
        });
    });

    // ส่งฟอร์มระหว่างกำลังย่อรูป: รอให้เสร็จก่อนแล้วส่งต่อ (รันก่อน handler อื่นของฟอร์ม)
    document.addEventListener('submit', function (e) {
        if (!pending) return;
        var form = e.target;
        e.preventDefault();
        e.stopImmediatePropagation();
        var wait = setInterval(function () {
            if (pending) return;
            clearInterval(wait);
            if (form.requestSubmit) form.requestSubmit(); else form.submit();
        }, 120);
    }, true);

    window.RepsPhotoInput = { enhance: enhanceAll, reset: reset };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { enhanceAll(); });
    else enhanceAll();
})();
