/**
 * REPS · i18n-client.js — แปลข้อความ UI ที่ JavaScript สร้างหลังโหลดหน้า (โหลดเฉพาะโหมด EN)
 * ใช้พจนานุกรมเดียวกับฝั่งเซิร์ฟเวอร์ (window.REPS_I18N จาก lang_js.php):
 *   - text node / attribute ที่เพิ่มหรือเปลี่ยนทีหลัง (แถบแบ่งหน้า หน้าต่างรายละเอียด ข้อความสถานะ)
 *   - กล่อง confirm() / alert()
 *   - ป้ายกำกับและชื่อชุดข้อมูลของ Chart.js
 * แปลเฉพาะข้อความที่ตรงทั้งก้อนกับพจนานุกรมหรือรูปแบบ — ข้อมูลที่ผู้ใช้กรอกไม่ถูกแตะ
 */
(function () {
    'use strict';
    var D = window.REPS_I18N;
    if (!D || !D.exact) return;

    var THAI = /[ก-๛]/;
    var ATTRS = ['placeholder', 'title', 'aria-label', 'alt', 'data-confirm', 'data-label', 'data-empty', 'label'];
    var SKIP = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, NOSCRIPT: 1 };
    var buckets = {}, general = [];

    D.patterns.forEach(function (p) {
        var entry;
        try { entry = [new RegExp(p[0], 'u'), p[1], p[2] === 1]; } catch (e) { return; }
        if (p[3]) (buckets[p[3]] = buckets[p[3]] || []).push(entry); else general.push(entry);
    });

    function lookup(text, isAttr, depth) {
        if (Object.prototype.hasOwnProperty.call(D.exact, text)) return D.exact[text];
        var list = (buckets[text.slice(0, 2)] || []).concat(general);
        for (var i = 0; i < list.length; i++) {
            var e = list[i];
            if (e[2] && !isAttr) continue;
            var m = e[0].exec(text);
            if (!m) continue;
            var k = 0;
            return e[1].replace(/\{[nx]\}/g, function (tok) {
                var v = m[++k] || '';
                if (tok === '{n}' || depth > 0) return v;
                if (Object.prototype.hasOwnProperty.call(D.overrides, v)) return D.overrides[v];
                var t = lookup(v, isAttr, depth + 1);
                return t === null ? v : t;
            });
        }
        return null;
    }

    /** คืนข้อความที่แปลแล้ว หรือ null ถ้าไม่มีคำแปล (คงช่องว่างหัวท้ายเดิม) */
    function translate(raw, isAttr) {
        if (typeof raw !== 'string' || !THAI.test(raw)) return null;
        var m = /^(\s*)([\s\S]*?)(\s*)$/.exec(raw);
        var text = m[2].replace(/\s+/g, ' ');
        if (!text) return null;
        var t = lookup(text, !!isAttr, 0);
        return t === null ? null : m[1] + t + m[3];
    }

    function translateTextNode(node) {
        var p = node.parentNode;
        if (!p || SKIP[p.nodeName] || (p.isContentEditable)) return;
        var t = translate(node.nodeValue, false);
        if (t !== null && t !== node.nodeValue) node.nodeValue = t;
    }

    function translateAttrs(el) {
        for (var i = 0; i < ATTRS.length; i++) {
            var v = el.getAttribute(ATTRS[i]);
            if (v) {
                var t = translate(v, true);
                if (t !== null && t !== v) el.setAttribute(ATTRS[i], t);
            }
        }
    }

    function translateTree(root) {
        if (root.nodeType === 3) { translateTextNode(root); return; }
        if (root.nodeType !== 1 || SKIP[root.nodeName]) return;
        translateAttrs(root);
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_ELEMENT | NodeFilter.SHOW_TEXT, {
            acceptNode: function (n) {
                return n.nodeType === 1 && SKIP[n.nodeName] ? NodeFilter.FILTER_REJECT : NodeFilter.FILTER_ACCEPT;
            }
        });
        var n;
        while ((n = walker.nextNode())) {
            if (n.nodeType === 3) translateTextNode(n); else translateAttrs(n);
        }
    }

    // ---------- ข้อความที่เพิ่ม/เปลี่ยนหลังโหลดหน้า
    function startObserver() {
        new MutationObserver(function (records) {
            for (var i = 0; i < records.length; i++) {
                var r = records[i];
                if (r.type === 'characterData') {
                    translateTextNode(r.target);
                } else if (r.type === 'attributes') {
                    translateAttrs(r.target);
                } else {
                    for (var j = 0; j < r.addedNodes.length; j++) translateTree(r.addedNodes[j]);
                }
            }
        }).observe(document.documentElement, {
            childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ATTRS
        });
    }
    if (document.body) startObserver();
    else document.addEventListener('DOMContentLoaded', startObserver);

    // ---------- confirm() / alert()
    ['confirm', 'alert'].forEach(function (name) {
        var native = window[name];
        if (typeof native !== 'function') return;
        window[name] = function (message) {
            var t = translate(String(message), false);
            return native.call(window, t === null ? message : t);
        };
    });

    // ---------- Chart.js: ป้ายกำกับแกน/ชุดข้อมูล (วาดบน canvas จึงต้องแปลก่อนวาด)
    function mapLabel(v) {
        if (Array.isArray(v)) return v.map(mapLabel);
        var t = translate(v, false);
        return t === null ? v : t;
    }
    function registerChart(Chart) {
        if (!Chart || typeof Chart.register !== 'function' || Chart.__repsI18n) return;
        Chart.__repsI18n = true;
        Chart.register({
            id: 'repsI18n',
            beforeUpdate: function (chart) {
                var d = chart.config && chart.config.data;
                if (!d) return;
                if (Array.isArray(d.labels)) d.labels = d.labels.map(mapLabel);
                (d.datasets || []).forEach(function (ds) {
                    if (typeof ds.label === 'string') ds.label = mapLabel(ds.label);
                });
            }
        });
    }
    if (window.Chart) {
        registerChart(window.Chart);
    } else {
        var chartRef;
        try {
            Object.defineProperty(window, 'Chart', {
                configurable: true,
                enumerable: true,
                get: function () { return chartRef; },
                set: function (v) { chartRef = v; try { registerChart(v); } catch (e) { /* ไม่ให้กราฟพังเพราะการแปล */ } }
            });
        } catch (e) { /* เบราว์เซอร์ไม่ยอมให้ดัก — ป้ายกราฟจะเป็นภาษาเดิม */ }
    }

    window.repsTranslate = function (s) { var t = translate(s, false); return t === null ? s : t; };
})();
