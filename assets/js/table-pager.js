/*
 * REPS · table-pager.js — แบ่งหน้าตาราง/รายการฝั่งเบราว์เซอร์
 *
 *   <table data-pager>             แบ่งแถวใน <tbody>
 *   <ul data-pager> / <div data-pager>  แบ่งลูกโดยตรง
 *   data-pager-size="10"           จำนวนเริ่มต้น (ค่าที่ผู้ใช้เลือกจะถูกจำไว้ต่อหน้า+ตาราง)
 *   data-pager-sizes="10,25,50,100"
 *   data-pager-ignore              ใส่ที่แถว/รายการที่ไม่นับ (เช่น ข้อความว่าง)
 * แถบแบ่งหน้าแสดงเมื่อมีรายการมากกว่าตัวเลือกที่น้อยที่สุด
 */
(function () {
   'use strict';

   function storageKey(container, index) {
      return 'reps-pager:' + location.pathname + ':' + (container.id || container.dataset.pager || index);
   }
   function readSize(key, fallback) {
      try {
         const v = Number(localStorage.getItem(key));
         return v > 0 ? v : fallback;
      } catch (e) {
         return fallback;
      }
   }
   function saveSize(key, size) {
      try { localStorage.setItem(key, String(size)); } catch (e) { /* ignore */ }
   }
   function itemsOf(container) {
      const source = container.tagName === 'TABLE' ? container.tBodies[0] : container;
      if (!source) return [];
      return Array.from(source.children).filter(function (el) {
         return !el.hasAttribute('data-pager-ignore') && el.tagName !== 'SCRIPT' && el.tagName !== 'TEMPLATE';
      });
   }
   function pageList(current, total) {
      // 1 … 4 5 [6] 7 8 … 20
      const pages = new Set([1, total, current - 1, current, current + 1]);
      if (current <= 3) { pages.add(2); pages.add(3); pages.add(4); }
      if (current >= total - 2) { pages.add(total - 1); pages.add(total - 2); pages.add(total - 3); }
      const sorted = Array.from(pages).filter(function (p) { return p >= 1 && p <= total; }).sort(function (a, b) { return a - b; });
      const out = [];
      sorted.forEach(function (p, i) {
         if (i > 0 && p - sorted[i - 1] > 1) out.push(null);
         out.push(p);
      });
      return out;
   }

   function setup(container, index) {
      const sizes = (container.dataset.pagerSizes || '10,25,50,100').split(',').map(Number).filter(Boolean);
      const key = storageKey(container, index);
      let size = readSize(key, Number(container.dataset.pagerSize) || sizes[0]);
      if (sizes.indexOf(size) === -1) size = sizes[0];
      let page = 1;
      const items = itemsOf(container);
      if (items.length <= sizes[0]) return;

      const anchor = container.closest('.overflow-x-auto, .admin-table-wrap') || container;
      const nav = document.createElement('nav');
      nav.className = 'reps-pager';
      nav.setAttribute('aria-label', 'แบ่งหน้า');
      anchor.insertAdjacentElement('afterend', nav);

      const info = document.createElement('span');
      info.className = 'reps-pager__info';
      info.setAttribute('aria-live', 'polite');

      const sizeLabel = document.createElement('label');
      sizeLabel.className = 'reps-pager__size';
      sizeLabel.append('แถวต่อหน้า ');
      const select = document.createElement('select');
      select.setAttribute('data-customized', 'true');
      sizes.forEach(function (s) {
         const o = document.createElement('option');
         o.value = String(s);
         o.textContent = String(s);
         if (s === size) o.selected = true;
         select.appendChild(o);
      });
      sizeLabel.appendChild(select);

      const pagesBox = document.createElement('div');
      pagesBox.className = 'reps-pager__pages';

      nav.append(info, sizeLabel, pagesBox);

      function button(label, target, opts) {
         const b = document.createElement('button');
         b.type = 'button';
         b.className = 'reps-pager__btn' + (opts && opts.current ? ' is-current' : '');
         b.textContent = label;
         if (opts && opts.aria) b.setAttribute('aria-label', opts.aria);
         if (opts && opts.current) b.setAttribute('aria-current', 'page');
         b.disabled = !!(opts && opts.disabled);
         b.addEventListener('click', function () {
            page = target;
            render();
            const top = container.getBoundingClientRect().top + window.scrollY - 90;
            if (window.scrollY > top) window.scrollTo({ top: top, behavior: 'smooth' });
         });
         return b;
      }

      function render() {
         const total = items.length;
         const pages = Math.max(1, Math.ceil(total / size));
         page = Math.min(Math.max(1, page), pages);
         const start = (page - 1) * size;
         items.forEach(function (el, i) { el.hidden = i < start || i >= start + size; });
         info.textContent = 'แสดง ' + (start + 1) + '–' + Math.min(total, start + size) + ' จาก ' + total.toLocaleString('th-TH');
         pagesBox.replaceChildren();
         pagesBox.appendChild(button('‹', page - 1, { disabled: page === 1, aria: 'หน้าก่อนหน้า' }));
         pageList(page, pages).forEach(function (p) {
            if (p === null) {
               const gap = document.createElement('span');
               gap.className = 'reps-pager__gap';
               gap.textContent = '…';
               pagesBox.appendChild(gap);
            } else {
               pagesBox.appendChild(button(String(p), p, { current: p === page, aria: 'หน้า ' + p }));
            }
         });
         pagesBox.appendChild(button('›', page + 1, { disabled: page === pages, aria: 'หน้าถัดไป' }));
      }

      select.addEventListener('change', function () {
         size = Number(select.value);
         saveSize(key, size);
         page = 1;
         render();
      });
      render();
   }

   function init() {
      document.querySelectorAll('[data-pager]').forEach(setup);
   }
   if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
   else init();
})();
