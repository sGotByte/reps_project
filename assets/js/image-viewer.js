/*
 * REPS · image-viewer.js — เปิดดูรูปใน modal แทนการเปิดแท็บใหม่ (ใช้ทั้งระบบ โหลดจาก includes/site_footer.php)
 *
 * ทำงานอัตโนมัติกับลิงก์ที่ชี้ไป file.php (รูปแนบ รูปหลังซ่อม สลิป QR รูปวัสดุ ฯลฯ) และลิงก์ที่มี data-image-viewer
 *   <a href="file.php?..." data-image-viewer>รูปตอนแจ้ง 1</a>
 *   กลุ่มรูป (เลื่อนดูรูปก่อน/ถัดไป): ลิงก์ใน [data-gallery] เดียวกัน หรือในกล่องเดียวกัน (dialog, section, article, li, tr)
 *   ไม่ต้องการให้เปิดใน modal: ใส่ data-no-viewer
 * - กด Ctrl/Cmd/Shift/ปุ่มกลางเมาส์ = เปิดแท็บใหม่ตามปกติของเบราว์เซอร์
 * - ปิดด้วย Esc / ปุ่มปิด / คลิกพื้นหลัง · ลูกศรซ้าย-ขวา หรือปัดบนมือถือเพื่อเลื่อนรูป
 * - ไม่มี JavaScript = ลิงก์เปิดรูปตามเดิม
 */
(function () {
    'use strict';
    if (window.RepsImageViewer) return;

    var EN = (document.documentElement.lang || '').toLowerCase().indexOf('en') === 0;
    var T = EN
        ? { close: 'Close', prev: 'Previous image', next: 'Next image', download: 'Download', loading: 'Loading image...', error: 'This image could not be opened. It may have been removed, or you may not have access.', photo: 'Image', dialog: 'Image viewer' }
        : { close: 'ปิด', prev: 'รูปก่อนหน้า', next: 'รูปถัดไป', download: 'ดาวน์โหลด', loading: 'กำลังโหลดรูป...', error: 'เปิดรูปนี้ไม่ได้ รูปอาจถูกลบไปแล้ว หรือคุณไม่มีสิทธิ์ดู', photo: 'รูปภาพ', dialog: 'ดูรูปภาพ' };

    var svg = function (d) { return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + d + '</svg>'; };
    var ICON = {
        close: svg('<path d="M6 6l12 12M18 6L6 18"/>'),
        prev: svg('<path d="M15 5l-7 7 7 7"/>'),
        next: svg('<path d="M9 5l7 7-7 7"/>'),
        download: svg('<path d="M12 4v11m0 0l-4.5-4.5M12 15l4.5-4.5M5 19h14"/>')
    };

    var CSS = ''
        + '.rv-dialog{position:fixed;inset:0;width:100vw;max-width:100vw;height:100dvh;max-height:100dvh;margin:0;padding:0;border:0;background:rgba(12,12,11,.94);color:#fff;overflow:hidden}'
        + '.rv-dialog::backdrop{background:rgba(12,12,11,.5)}'
        + '.rv-dialog[open]{display:grid;grid-template-rows:auto minmax(0,1fr) auto}'
        + '.rv-bar{display:flex;align-items:center;gap:8px;padding:10px 12px 10px 18px;font:400 14px/1.4 Kanit,system-ui,sans-serif}'
        + '.rv-title{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500}'
        + '.rv-count{color:rgba(255,255,255,.7);font-variant-numeric:tabular-nums;white-space:nowrap}'
        + '.rv-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:44px;height:44px;padding:0 10px;border:0;border-radius:12px;background:rgba(255,255,255,.08);color:#fff;font:500 14px Kanit,system-ui,sans-serif;text-decoration:none;cursor:pointer}'
        + '.rv-btn:hover{background:rgba(255,255,255,.18)}.rv-btn[hidden]{display:none}'
        + '.rv-btn:focus-visible{outline:2px solid #fff;outline-offset:2px}'
        + '.rv-btn svg{width:20px;height:20px}'
        + '.rv-stage{position:relative;display:grid;place-items:center;min-height:0;padding:0 64px;touch-action:pan-y}'
        + '.rv-img{display:block;max-width:100%;max-height:100%;object-fit:contain;border-radius:6px;background:#fff;box-shadow:0 20px 50px rgba(0,0,0,.45);opacity:0;transition:opacity .2s ease}'
        + '.rv-img.is-ready{opacity:1}'
        + '.rv-msg{position:absolute;max-width:28rem;padding:0 20px;text-align:center;font:400 15px/1.6 Kanit,system-ui,sans-serif;color:rgba(255,255,255,.85)}'
        + '.rv-nav{position:absolute;top:50%;transform:translateY(-50%);width:48px;height:48px;border-radius:999px}'
        + '.rv-nav--prev{left:10px}.rv-nav--next{right:10px}'
        + '.rv-foot{height:14px}'
        + '@media (max-width:640px){.rv-stage{padding:0 8px}.rv-nav{top:auto;bottom:-58px;transform:none}.rv-dialog[open]{grid-template-rows:auto minmax(0,1fr) 72px}.rv-nav--prev{left:calc(50% - 56px)}.rv-nav--next{right:calc(50% - 56px)}.rv-dl-label{display:none}}'
        + '@media (prefers-reduced-motion:reduce){.rv-img{transition:none}}';

    var dialog, img, titleEl, countEl, msgEl, prevBtn, nextBtn, dlLink, items = [], index = 0, opener = null, token = 0;

    function build() {
        var style = document.createElement('style');
        style.textContent = CSS;
        document.head.appendChild(style);

        dialog = document.createElement('dialog');
        dialog.className = 'rv-dialog';
        dialog.setAttribute('aria-label', T.dialog);
        dialog.innerHTML = ''
            + '<div class="rv-bar"><span class="rv-title"></span><span class="rv-count"></span>'
            + '<a class="rv-btn rv-dl" download>' + ICON.download + '<span class="rv-dl-label">' + T.download + '</span></a>'
            + '<button type="button" class="rv-btn rv-close" aria-label="' + T.close + '">' + ICON.close + '</button></div>'
            + '<div class="rv-stage"><img class="rv-img" alt=""><p class="rv-msg" role="status"></p>'
            + '<button type="button" class="rv-btn rv-nav rv-nav--prev" aria-label="' + T.prev + '">' + ICON.prev + '</button>'
            + '<button type="button" class="rv-btn rv-nav rv-nav--next" aria-label="' + T.next + '">' + ICON.next + '</button></div>'
            + '<div class="rv-foot"></div>';
        document.body.appendChild(dialog);

        img = dialog.querySelector('.rv-img');
        titleEl = dialog.querySelector('.rv-title');
        countEl = dialog.querySelector('.rv-count');
        msgEl = dialog.querySelector('.rv-msg');
        prevBtn = dialog.querySelector('.rv-nav--prev');
        nextBtn = dialog.querySelector('.rv-nav--next');
        dlLink = dialog.querySelector('.rv-dl');

        dialog.querySelector('.rv-close').addEventListener('click', close);
        prevBtn.addEventListener('click', function () { show(index - 1); });
        nextBtn.addEventListener('click', function () { show(index + 1); });
        // คลิกพื้นหลัง (นอกรูปและปุ่ม) = ปิด
        dialog.addEventListener('click', function (e) {
            if (e.target === dialog || e.target.classList.contains('rv-stage') || e.target.classList.contains('rv-foot')) close();
        });
        dialog.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); show(index - 1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); show(index + 1); }
        });
        dialog.addEventListener('close', function () {
            img.removeAttribute('src');
            document.documentElement.style.removeProperty('overflow');
            if (opener && document.contains(opener)) opener.focus();
        });
        // ปัดซ้าย/ขวาบนจอสัมผัส
        var sx = null, sy = 0;
        dialog.addEventListener('touchstart', function (e) { if (e.touches.length === 1) { sx = e.touches[0].clientX; sy = e.touches[0].clientY; } }, { passive: true });
        dialog.addEventListener('touchend', function (e) {
            if (sx === null) return;
            var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
            sx = null;
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) show(index + (dx < 0 ? 1 : -1));
        }, { passive: true });
        img.addEventListener('load', function () { img.classList.add('is-ready'); msgEl.textContent = ''; });
        img.addEventListener('error', function () {
            if (!img.getAttribute('src')) return;
            img.classList.remove('is-ready');
            msgEl.textContent = T.error;
        });
    }

    function labelOf(a) {
        var inner = a.querySelector('img');
        var text = (a.getAttribute('data-caption') || a.getAttribute('aria-label') || a.textContent || '').replace(/\s+/g, ' ').trim();
        if ((!text || text === T.dialog) && inner) text = (inner.getAttribute('alt') || '').trim();
        return text || T.photo;
    }

    function isViewerLink(a) {
        if (!a || a.tagName !== 'A' || a.hasAttribute('data-no-viewer') || a.hasAttribute('download')) return false;
        if (a.hasAttribute('data-image-viewer')) return true;
        var href = a.getAttribute('href') || '';
        return /(^|\/)file\.php\?/.test(href);
    }

    function groupFor(a) {
        var root = a.closest('[data-gallery]') || a.closest('dialog, section, article, li, tr') || document;
        var seen = {};
        var list = [];
        root.querySelectorAll('a[href]').forEach(function (el) {
            if (!isViewerLink(el) || el.closest('.rv-dialog')) return;
            if (el.offsetParent === null && el !== a) return; // ลิงก์ที่ซ่อนอยู่ไม่นับ
            var url = el.href;
            if (seen[url]) return;
            seen[url] = true;
            list.push({ url: url, label: labelOf(el), el: el });
        });
        return list;
    }

    function show(i) {
        if (!items.length) return;
        index = (i + items.length) % items.length;
        var it = items[index];
        var my = ++token;
        img.classList.remove('is-ready');
        msgEl.textContent = T.loading;
        img.alt = it.label;
        titleEl.textContent = it.label;
        countEl.textContent = items.length > 1 ? (index + 1) + ' / ' + items.length : '';
        prevBtn.hidden = nextBtn.hidden = items.length < 2;
        dlLink.href = it.url;
        // โหลดล่วงหน้า แล้วสลับเมื่อพร้อม (กันรูปเก่าค้างตอนกดถัดไปเร็ว ๆ)
        var pre = new Image();
        pre.onload = pre.onerror = function () { if (my === token) img.src = it.url; };
        pre.src = it.url;
    }

    function open(list, start, from) {
        if (!dialog) build();
        items = list;
        opener = from || document.activeElement;
        document.documentElement.style.overflow = 'hidden';
        if (!dialog.open) dialog.showModal();
        show(start || 0);
        dialog.querySelector('.rv-close').focus();
    }

    function close() { if (dialog && dialog.open) dialog.close(); }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var a = e.target.closest && e.target.closest('a[href]');
        if (!isViewerLink(a) || typeof HTMLDialogElement !== 'function') return;
        e.preventDefault();
        var list = groupFor(a);
        var start = 0;
        list.forEach(function (it, k) { if (it.el === a) start = k; });
        if (!list.length) list = [{ url: a.href, label: labelOf(a), el: a }];
        open(list, start, a);
    }, true);

    window.RepsImageViewer = {
        /** เปิดรูปเองจากโค้ด: RepsImageViewer.open([{url, label}], 0) */
        open: function (list, start) { open(list.map(function (x) { return { url: x.url, label: x.label || T.photo }; }), start || 0, null); },
        close: close
    };
})();
