<?php
declare(strict_types=1);

/**
 * REPS · certificate.php — ใบรับรองงานซ่อม (เปิดดู/พิมพ์ได้)
 * v3: ลายเซ็นครบ 3 ฝ่าย (สตาฟ ผู้แจ้ง ครู) ของรอบที่ปิดงาน + เช็คลิสต์ตอนรับ/ตอนส่งคืน + ลายเซ็นรับทราบตอนรับ
 * สิทธิ์: admin และ staff ดูได้ทุกใบ ผู้แจ้งดูได้เฉพาะของตัวเอง
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/includes/user_auth.php';
require_once __DIR__ . '/includes/site_config.php';
require_once __DIR__ . '/includes/ticket_service.php';

$userId = app_require_active_user($conn, 'login.php', null);
$role = current_role();
$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

function ct_h(mixed $v): string
{
   return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function ct_date(?string $v): string
{
   if (!$v) return '-';
   $ts = strtotime($v);
   $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
   return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ' ' . ((int)date('Y', $ts) + 543) . ' ' . date('H:i', $ts) . ' น.';
}

$t = db_one(
   $conn,
   "SELECT t.*, c.name AS category_name,
           r.first_name AS req_first, r.last_name AS req_last, d.name AS req_department,
           s.first_name AS staff_first, s.last_name AS staff_last
      FROM tickets t
      LEFT JOIN categories c ON c.id = t.category_id
      JOIN users r ON r.user_id = t.requester_id
      LEFT JOIN department d ON d.d_id = r.department_id
      LEFT JOIN users s ON s.user_id = t.assigned_to
     WHERE t.id = ?",
   'i',
   [$ticketId]
);

if (!$t || (!can($role, 'ticket.view_all') && (int)$t['requester_id'] !== $userId)) {
   http_response_code(404);
   exit('ไม่พบใบรับรองงาน');
}
$signoffs = ticket_signoffs($conn, $ticketId);
$round = (int)$t['sign_round'];
$sig = [];
foreach ($signoffs as $so) {
   if ($so['stage'] === 'return' && (int)$so['round_no'] === $round) {
      $sig[$so['signer_role']] = $so;
   } elseif ($so['stage'] === 'intake') {
      $sig['intake'] = $so;
   }
}
$items = ticket_item_checks($conn, $ticketId);
if ($t['status'] !== 'completed' || empty($sig['admin'])) {
   http_response_code(404);
   exit('ใบงานนี้ยังไม่ได้รับการรับรอง');
}

$materials = db_all(
   $conn,
   'SELECT m.qty_used, m.note, i.name, i.unit FROM ticket_materials m JOIN inventory_items i ON i.id = m.item_id WHERE m.ticket_id = ? ORDER BY m.id',
   'i',
   [$ticketId]
);
$helperNames = array_map(
   static fn($r) => $r['first_name'] . ' ' . $r['last_name'],
   db_all($conn, 'SELECT u.first_name, u.last_name FROM ticket_assignees a JOIN users u ON u.user_id = a.user_id WHERE a.ticket_id = ? ORDER BY u.first_name', 'i', [$ticketId])
);
$siteName = app_site_name($conn);
$settings = app_get_settings($conn);
$orgName = trim((string)($settings['org_name'] ?? ''));
$orgUnit = trim((string)($settings['org_unit'] ?? '')) ?: 'แผนกเทคโนโลยีธุรกิจดิจิทัล · ห้องปฏิบัติการกลางคอมพิวเตอร์';
$person = static fn(?array $so): string => $so ? trim(($so['first_name'] ?? '') . ' ' . ($so['last_name'] ?? '')) : '';
$staffName = trim(($t['staff_first'] ?? '') . ' ' . ($t['staff_last'] ?? '')) ?: '-';
$closedAt = $t['completed_at'] ?: ($sig['admin']['signed_at'] ?? null);
$urgencyText = match ($t['urgency']) {
   'urgent' => 'ด่วน' . (!empty($t['urgency_reason']) ? ' · ' . $t['urgency_reason'] : ''),
   'low'    => 'ไม่เร่งด่วน',
   default  => 'ปกติ',
};
$facts = [
   ['ผู้แจ้ง', $t['req_first'] . ' ' . $t['req_last'] . ($t['req_department'] ? ' · ' . $t['req_department'] : '')],
   ['สถานที่', (string)$t['location_note']],
   ['หมวดหมู่', ($t['category_name'] ?? '-') . ($t['custom_category'] ? ' · ' . $t['custom_category'] : '')],
   ['อุปกรณ์ / จำนวน', ($t['device_detail'] ? $t['device_detail'] . ' · ' : '') . (int)$t['quantity'] . ' รายการ'],
   ['ช่างหลัก', $staffName],
   ['ผู้ช่วย', $helperNames ? implode(', ', $helperNames) : '-'],
   ['วันที่แจ้ง', ct_date($t['created_at'])],
   ['วันที่รับงาน', ct_date($t['accepted_at'])],
   ['ความเร่งด่วน', $urgencyText],
   ['วันที่ปิดงาน', ct_date($closedAt)],
];
$photos = ticket_completion_photos($conn, $t); // รูปหลังซ่อม 1–3 รูปของรอบที่ปิดงาน
$hasPhoto = count($photos) === 1;   // รูปเดียว: วางข้างรายละเอียด · 2–3 รูป: แถวรูปใต้รายละเอียด
?>
<!DOCTYPE html>
<html lang="th">
<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>ใบรับรองงาน <?= ct_h($t['ticket_no']) ?> | <?= ct_h($siteName) ?></title>
   <link rel="icon" type="image/svg+xml" href="assets/images/logo/reps-icon.svg">
   <link rel="preconnect" href="https://fonts.googleapis.com">
   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
   <link href="https://fonts.googleapis.com/css2?family=Mitr:wght@300;400;500;600;700&display=swap" rel="stylesheet">
   <style>
      /* A4 แนวตั้ง 1 แผ่น: กระดาษ 210×297 มม. ขอบ 12/14 มม. → พื้นที่เนื้อหา 182×272 มม.
         เนื้อหายาวเกิน สคริปต์ท้ายหน้าย่อทั้งแผ่นให้พอดี (ไม่ตัดข้อความทิ้ง) */
      @page { size: A4 portrait; margin: 0; }
      :root { --ink: #161616; --muted: #5F5C57; --line: #D9D6D0; --soft: #F5F4F1; }
      * { box-sizing: border-box; }
      html, body { margin: 0; }
      body { background: #E9E7E3; color: var(--ink); font-family: 'Mitr', 'Kanit', sans-serif; font-size: 12.5px; font-weight: 300; line-height: 1.5; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .toolbar { position: sticky; top: 0; z-index: 5; display: flex; justify-content: space-between; align-items: center; gap: 12px; max-width: 210mm; margin: 0 auto; padding: 12px 0; background: #E9E7E3; }
      .toolbar a { color: #3F3D39; font-size: 14px; text-decoration: none; }
      .toolbar a:hover { text-decoration: underline; }
      .toolbar button { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 0 16px; border: 1px solid var(--ink); border-radius: 10px; background: var(--ink); color: #fff; font: inherit; font-size: 14px; cursor: pointer; }
      .toolbar button svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.7; stroke-linecap: round; stroke-linejoin: round; }
      .fit-note { color: var(--muted); font-size: 12.5px; }
      .sheet-wrap { padding: 0 0 24px; }
      .sheet { width: 210mm; height: 297mm; margin: 0 auto; padding: 12mm 14mm; overflow: hidden; background: #fff; box-shadow: 0 2px 18px rgba(22, 22, 22, .12); }
      .sheet-body { display: flex; flex-direction: column; width: 182mm; transform-origin: top left; }
      .sheet-body > .signs { margin-top: auto; padding-top: 5mm; }

      .doc-head { display: grid; grid-template-columns: 1fr auto; gap: 6mm; align-items: end; padding-bottom: 3mm; border-bottom: 1.6px solid var(--ink); }
      .brand { display: flex; align-items: center; gap: 3.5mm; min-width: 0; }
      .brand img { height: 11mm; width: auto; max-width: 40mm; object-fit: contain; flex: none; }
      .brand-text { min-width: 0; }
      .brand-org { font-size: 13.5px; font-weight: 600; }
      .brand-unit { color: var(--muted); font-size: 11.5px; }
      .doc-id { text-align: right; }
      .doc-title { font-size: 21px; font-weight: 700; line-height: 1.2; }
      .doc-no { margin-top: .8mm; font-size: 12.5px; }
      .doc-no b { font-weight: 600; font-variant-numeric: tabular-nums; }
      .stamp { display: inline-block; margin-top: 1.2mm; padding: .3mm 2.5mm; border: 1.2px solid #12703A; border-radius: 999px; color: #12703A; font-size: 11px; font-weight: 600; }

      .sec { margin-top: 4mm; }
      .sec-title { display: flex; align-items: center; gap: 2mm; margin: 0 0 1.5mm; font-size: 12.5px; font-weight: 700; }
      .sec-title::before { content: ''; width: 1.2mm; height: 3.6mm; border-radius: 1mm; background: var(--ink); }

      .facts { display: grid; grid-template-columns: 1fr 1fr; margin: 0; border: 1px solid var(--line); border-radius: 2mm; overflow: hidden; }
      .fact { display: grid; grid-template-columns: 26mm 1fr; gap: 2mm; padding: 1.5mm 3mm; border-bottom: 1px solid var(--line); min-width: 0; }
      .fact:nth-child(odd) { border-right: 1px solid var(--line); }
      .fact.last-row { border-bottom: 0; }
      .fact dt { color: var(--muted); font-size: 11.5px; }
      .fact dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }

      .work { display: grid; grid-template-columns: 1fr 50mm; gap: 4mm; align-items: start; }
      .work.no-photo { grid-template-columns: 1fr; }
      .work-rows { border: 1px solid var(--line); border-radius: 2mm; }
      .work-row { display: grid; grid-template-columns: 26mm 1fr; gap: 2mm; padding: 1.5mm 3mm; border-bottom: 1px solid var(--line); }
      .work-row:last-child { border-bottom: 0; }
      .work-row .lbl { color: var(--muted); font-size: 11.5px; }
      .work-row .val { white-space: pre-line; overflow-wrap: anywhere; }
      .photo-box { display: flex; flex-direction: column; gap: 1mm; }
      .photo-box img { width: 50mm; height: 50mm; object-fit: cover; border: 1px solid var(--line); border-radius: 2mm; background: var(--soft); }
      .photo-box span { color: var(--muted); font-size: 11px; text-align: center; }
      .photo-row { display: grid; gap: 3mm; margin-top: 3mm; }
      .photo-row.n2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .photo-row.n3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
      .photo-row figure { margin: 0; display: flex; flex-direction: column; gap: 1mm; }
      .photo-row img { width: 100%; height: 46mm; object-fit: cover; border: 1px solid var(--line); border-radius: 2mm; background: var(--soft); }
      .photo-row figcaption { color: var(--muted); font-size: 11px; text-align: center; }

      table.chk { width: 100%; border-collapse: collapse; font-size: 12px; table-layout: fixed; }
      .chk th, .chk td { padding: 1.1mm 2mm; border: 1px solid var(--line); text-align: left; vertical-align: top; overflow-wrap: anywhere; }
      .chk th { background: var(--soft); color: var(--muted); font-weight: 500; font-size: 11.5px; }
      .chk tr.diff td { background: #FFF6E8; }
      .note { margin-top: 1.5mm; padding: 1.5mm 3mm; border: 1px solid #EBC99F; border-radius: 2mm; background: #FFFAF2; font-size: 12px; }
      .muted { color: var(--muted); }
      .small { font-size: 11.5px; }

      .signs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 4mm; }
      .sign { display: flex; flex-direction: column; align-items: center; padding: 2mm 2.5mm 2.5mm; border: 1px solid var(--line); border-radius: 2mm; text-align: center; min-width: 0; }
      .sign-img { display: flex; align-items: center; justify-content: center; width: 100%; height: 17mm; }
      .sign-img img { max-width: 100%; max-height: 17mm; object-fit: contain; }
      .sign-img .skip { color: var(--muted); font-size: 11px; line-height: 1.35; }
      .sign-line { width: 100%; margin-top: 1mm; padding-top: 1mm; border-top: 1px dotted var(--ink); font-size: 12.5px; overflow-wrap: anywhere; }
      .sign-role { font-weight: 600; font-size: 12px; }
      .sign-meta { color: var(--muted); font-size: 10.5px; line-height: 1.35; overflow-wrap: anywhere; }

      .doc-foot { display: flex; justify-content: space-between; gap: 4mm; margin-top: 4mm; padding-top: 2mm; border-top: 1px solid var(--line); color: var(--muted); font-size: 10.5px; }

      @media screen and (max-width: 860px) {
         .toolbar { padding: 12px 16px; }
         .sheet-wrap { overflow-x: auto; padding: 0 8px 24px; }
      }
      @media print {
         body { background: #fff; }
         .toolbar, .reps-site-footer, .no-print { display: none !important; }
         .sheet-wrap { padding: 0; overflow: visible; }
         .sheet { height: 296.5mm; margin: 0; box-shadow: none; }
      }
   </style>
</head>
<body>
   <div class="toolbar no-print">
      <a href="javascript:history.back()">&larr; กลับ</a>
      <span class="fit-note" id="fitNote"></span>
      <button type="button" onclick="window.print()">
         <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 9V4h10v5M7 17H5a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="6" rx="1"/></svg>
         พิมพ์ / บันทึก PDF
      </button>
   </div>
   <div class="sheet-wrap">
   <main class="sheet" aria-label="ใบรับรองการซ่อม">
      <div class="sheet-body" id="sheetBody">
         <header class="doc-head">
            <div class="brand">
               <img src="<?= ct_h(app_logo_url($conn)) ?>" alt="">
               <div class="brand-text">
                  <div class="brand-org"><?= ct_h($orgName !== '' ? $orgName : $siteName) ?></div>
                  <div class="brand-unit"><?= ct_h($orgName !== '' ? $orgUnit . ' · ' . $siteName : $orgUnit) ?></div>
               </div>
            </div>
            <div class="doc-id">
               <div class="doc-title">ใบรับรองการซ่อม</div>
               <div class="doc-no">เลขที่ <b><?= ct_h($t['ticket_no']) ?></b></div>
               <span class="stamp">ครูรับรองแล้ว <?= ct_h(ct_date($sig['admin']['signed_at'])) ?></span>
            </div>
         </header>

         <section class="sec">
            <h2 class="sec-title">ข้อมูลใบงาน</h2>
            <dl class="facts">
               <?php foreach ($facts as $i => [$label, $value]): ?>
                  <div class="fact<?= $i >= count($facts) - 2 ? ' last-row' : '' ?>"><dt><?= ct_h($label) ?></dt><dd><?= ct_h($value) ?></dd></div>
               <?php endforeach; ?>
            </dl>
         </section>

         <section class="sec">
            <h2 class="sec-title">รายละเอียดการซ่อม</h2>
            <div class="work<?= $hasPhoto ? '' : ' no-photo' ?>">
               <div class="work-rows">
                  <div class="work-row"><div class="lbl">อาการที่แจ้ง</div><div class="val"><?= ct_h($t['symptom']) ?></div></div>
                  <div class="work-row"><div class="lbl">วิธีดำเนินการ</div><div class="val"><?= ct_h($t['repair_method'] ?: '-') ?></div></div>
                  <div class="work-row"><div class="lbl">สรุปผลการซ่อม</div><div class="val"><?= ct_h($t['resolution_summary'] ?: '-') ?></div></div>
                  <div class="work-row"><div class="lbl">วัสดุที่ใช้</div><div class="val"><?= $materials
                     ? ct_h(implode(' · ', array_map(static fn($m) => $m['name'] . ' ' . (int)$m['qty_used'] . ' ' . $m['unit'] . ($m['note'] ? ' (' . $m['note'] . ')' : ''), $materials)))
                     : '-' ?></div></div>
               </div>
               <?php if ($hasPhoto): ?>
                  <div class="photo-box">
                     <img src="file.php?<?= ct_h($photos[0]['query']) ?>" alt="รูปหลักฐานหลังซ่อม">
                     <span>รูปหลักฐานหลังซ่อม</span>
                  </div>
               <?php endif; ?>
            </div>
            <?php if (count($photos) > 1): ?>
               <div class="photo-row n<?= count($photos) ?>">
                  <?php foreach ($photos as $n => $p): ?>
                     <figure><img src="file.php?<?= ct_h($p['query']) ?>" alt="รูปหลักฐานหลังซ่อม <?= $n + 1 ?>"><figcaption>รูปหลักฐานหลังซ่อม <?= $n + 1 ?></figcaption></figure>
                  <?php endforeach; ?>
               </div>
            <?php endif; ?>
         </section>

         <?php if ($items):
            $cond = ITEM_CONDITION_LABELS;
            $res = ITEM_RESULT_LABELS; ?>
            <section class="sec">
               <h2 class="sec-title">เช็คลิสต์อุปกรณ์ ตอนรับ / ตอนส่งคืน</h2>
               <table class="chk">
                  <thead><tr><th style="width:30%">อุปกรณ์</th><th style="width:20%">เลขเครื่อง / ครุภัณฑ์</th><th style="width:25%">ตอนรับ</th><th>ตอนส่งคืน</th></tr></thead>
                  <tbody>
                  <?php foreach ($items as $it): $diff = $it['result_out'] !== null && $it['result_out'] !== 'returned'; ?>
                     <tr class="<?= $diff ? 'diff' : '' ?>">
                        <td><?= ct_h($it['item_name']) ?><?= $it['item_type'] === 'main' ? ' <span class="muted">(หลัก)</span>' : '' ?></td>
                        <td><?= ct_h($it['serial_no'] ?: '-') ?></td>
                        <td><?= (int)$it['qty_in'] === 0 ? 'ไม่มีมา' : (int)$it['qty_in'] . ' · ' . ct_h($cond[$it['condition_in']] ?? '') ?><?= $it['note_in'] ? ' · ' . ct_h($it['note_in']) : '' ?></td>
                        <td><?= $it['result_out'] === null ? '-' : ct_h($res[$it['result_out']] ?? '') . ' · ' . (int)$it['qty_out'] ?><?= $it['note_out'] ? ' · ' . ct_h($it['note_out']) : '' ?></td>
                     </tr>
                  <?php endforeach; ?>
                  </tbody>
               </table>
               <?php if ((int)$t['has_discrepancy'] === 1): ?>
                  <div class="note"><strong>ข้อแตกต่างตอนตรวจซ้ำ:</strong> <?= ct_h($t['discrepancy_note']) ?></div>
               <?php endif; ?>
               <?php if (!empty($sig['intake'])): ?>
                  <div class="muted small" style="margin-top:1.2mm">
                     ผู้แจ้งรับทราบเช็คลิสต์ตอนรับ: <?= $sig['intake']['method'] === 'skip'
                        ? 'ข้ามขั้นโดยครู ' . ct_h($person($sig['intake'])) . ' (' . ct_h($sig['intake']['note']) . ')'
                        : ct_h($person($sig['intake'])) . ' เมื่อ ' . ct_h(ct_date($sig['intake']['signed_at'])) ?>
                  </div>
               <?php endif; ?>
            </section>
         <?php endif; ?>

         <section class="signs" aria-label="ลายเซ็นรับรอง">
            <?php foreach (['staff' => 'สตาฟผู้รับงาน', 'requester' => 'ผู้แจ้ง / ผู้รับอุปกรณ์คืน', 'admin' => 'ครูผู้รับรองงาน'] as $role_ => $roleLabel):
               $so = $sig[$role_] ?? null; ?>
               <div class="sign">
                  <div class="sign-img">
                     <?php if ($so === null): ?>
                        <span class="skip">ไม่มีลายเซ็น<br>(ใบงานก่อนระบบเซ็น 3 ฝ่าย)</span>
                     <?php elseif ($so['method'] === 'skip'): ?>
                        <span class="skip">ข้ามขั้นโดยครูผู้ดูแล<br><?= ct_h($so['note']) ?></span>
                     <?php elseif (!empty($so['signature_image'])): ?>
                        <img src="file.php?t=<?= (int)$t['id'] ?>&amp;g=<?= (int)$so['id'] ?>" alt="ลายเซ็น<?= ct_h($roleLabel) ?>">
                     <?php endif; ?>
                  </div>
                  <div class="sign-line">( <?= ct_h($person($so) ?: '..............................') ?> )</div>
                  <div class="sign-role"><?= ct_h($roleLabel) ?></div>
                  <?php if ($so): ?>
                     <div class="sign-meta"><?= ct_h(ct_date($so['signed_at'])) ?></div>
                     <?php if ($so['note'] && $so['method'] !== 'skip'): ?><div class="sign-meta">หมายเหตุ: <?= ct_h($so['note']) ?></div><?php endif; ?>
                  <?php endif; ?>
               </div>
            <?php endforeach; ?>
         </section>

         <footer class="doc-foot">
            <span>ออกจากระบบ <?= ct_h($siteName) ?><?= $round > 1 ? ' · ลายเซ็นรอบที่ ' . $round . ' (ใบงานถูกส่งกลับแก้ไขก่อนปิดงาน)' : '' ?></span>
            <span>พิมพ์เมื่อ <?= ct_h(ct_date(date('Y-m-d H:i:s'))) ?></span>
         </footer>
      </div>
   </main>
   </div>
<script>
/* ย่อทั้งแผ่นให้พอดี A4 1 หน้าเมื่อเนื้อหายาวเกิน (ไม่ตัดข้อความ · ย่อได้ต่ำสุด 70%) */
(function () {
   var body = document.getElementById('sheetBody');
   var note = document.getElementById('fitNote');
   if (!body) return;
   var probe = document.createElement('div');
   probe.style.cssText = 'position:absolute;visibility:hidden;width:182mm;height:272mm';
   document.body.appendChild(probe);
   var limitH = probe.getBoundingClientRect().height, fullW = probe.getBoundingClientRect().width;
   probe.remove();
   function fit() {
      body.style.transform = ''; body.style.minHeight = '';
      body.style.width = fullW + 'px';
      var scale = 1;
      for (var i = 0; i < 5 && body.scrollHeight * scale > limitH + 0.5; i++) {
         scale = Math.max(0.7, scale * limitH / (body.scrollHeight * scale));
         body.style.width = (fullW / scale) + 'px';   // ขยายความกว้างก่อนย่อ ให้ข้อความไม่ถูกบีบ
         if (scale === 0.7) break;
      }
      body.style.transform = scale < 1 ? 'scale(' + scale + ')' : '';
      body.style.minHeight = (limitH / scale) + 'px';   // ลายเซ็นชิดท้ายแผ่นเสมอ
      var over = body.scrollHeight * scale > limitH + 0.5;
      if (note) note.textContent = over ? 'เนื้อหายาวเกิน 1 แผ่นแม้ย่อแล้ว'
         : scale < 1 ? 'ย่อเหลือ ' + Math.round(scale * 100) + '% ให้พอดี A4 1 แผ่น' : 'A4 แนวตั้ง 1 แผ่น';
      document.querySelector('.sheet').style.overflow = over ? 'visible' : 'hidden';
      document.querySelector('.sheet').style.height = over ? 'auto' : '';
   }
   window.addEventListener('load', fit);
   window.addEventListener('beforeprint', fit);
   if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);
   fit();
})();
</script>
</body>
</html>
