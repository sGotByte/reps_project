<?php

$page_title = 'Report PDF';
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/../db_connect.php';
require_once __DIR__ . '/../includes/admin_report_data.php';

function report_pdf_h($value): string
{
   return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function report_pdf_date(string $date): string
{
   $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
   return $parsed ? $parsed->format('d/m/Y') : $date;
}

$from = is_string($_GET['from'] ?? null) ? $_GET['from'] : date('Y-m-01');
$to = is_string($_GET['to'] ?? null) ? $_GET['to'] : date('Y-m-d');
$isValidDate = static function (string $date): bool {
   $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
   return $parsed !== false && $parsed->format('Y-m-d') === $date;
};
if (!$isValidDate($from)) $from = date('Y-m-01');
if (!$isValidDate($to)) $to = date('Y-m-d');

$labels = [
   'pending'     => 'รอรับเรื่อง',
   'in_progress' => 'กำลังดำเนินการ',
   'completed'   => 'เสร็จสิ้น',
   'cancelled'   => 'ยกเลิก'
];

$mpdfTempDir = null;

/* mPDF writes font/cache data to tempDir; clean only the directory created for this request. */
$cleanup = static function () use (&$mpdfTempDir): void {
   if (is_string($mpdfTempDir) && is_dir($mpdfTempDir)) {
      $iterator = new RecursiveIteratorIterator(
         new RecursiveDirectoryIterator($mpdfTempDir, FilesystemIterator::SKIP_DOTS),
         RecursiveIteratorIterator::CHILD_FIRST
      );
      foreach ($iterator as $item) {
         if ($item->isDir()) {
            @rmdir($item->getPathname());
         } else {
            @unlink($item->getPathname());
         }
      }
      @rmdir($mpdfTempDir);
   }
};

try {
   $data = load_admin_report_data($conn, $from, $to);
   $summary = $data['summary'];
   $statusRows = $data['statusRows'];
   $categories = $data['categories'];
   $daily = $data['daily'];
   $tickets = $data['tickets'];

   $summaryTotal = (int)($summary['total'] ?? 0);
   $summaryCompleted = (int)($summary['completed'] ?? 0);
   $summaryOpen = (int)($summary['open_count'] ?? 0);
   $summaryUrgent = (int)($summary['urgent_count'] ?? 0);
   $avgHours = $summary['avg_hours'] ?? null;

   ob_start();
   ?>
<!doctype html>
<html lang="th">
<head>
   <meta charset="utf-8">
   <style>
      * { box-sizing: border-box; }
      body { margin: 0; color: #171717; font-family: "Noto Sans Thai", "Droid Sans Thai", Arial, sans-serif; font-size: 10pt; line-height: 1.45; }
      h1, h2, p { margin: 0; }
      .header { border-bottom: 2px solid #171717; padding-bottom: 9px; margin-bottom: 14px; }
      .eyebrow { color: #77736d; font-size: 8pt; letter-spacing: 1.2px; text-transform: uppercase; }
      h1 { margin-top: 3px; font-size: 20pt; font-weight: 700; }
      .range { margin-top: 4px; color: #77736d; font-size: 9pt; }
      .summary { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px 14px; }
      .summary td { width: 25%; padding: 9px 10px; border: 1px solid #e5e2dc; background: #fafaf8; vertical-align: top; }
      .summary-label { color: #77736d; font-size: 8pt; }
      .summary-value { margin-top: 3px; font-size: 17pt; font-weight: 700; }
      .section { margin-top: 13px; }
      .section-title { padding-bottom: 5px; border-bottom: 1px solid #d9d6d0; font-size: 11pt; font-weight: 700; }
      .columns { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin: 0 -10px; }
      .columns td { width: 50%; vertical-align: top; }
      table.data { width: 100%; border-collapse: collapse; margin-top: 7px; }
      .data th { padding: 6px 7px; background: #f3f2ef; color: #55534f; font-size: 8pt; font-weight: 700; text-align: left; }
      .data td { padding: 6px 7px; border-bottom: 1px solid #eceae6; vertical-align: top; }
      .data .number { text-align: right; white-space: nowrap; }
      .muted { color: #77736d; }
      .status { display: inline-block; padding: 2px 6px; border: 1px solid #d9d6d0; background: #fafaf8; font-size: 8pt; }
      .small { font-size: 8.5pt; }
      .empty { padding: 12px 7px !important; color: #77736d; text-align: center; }
      .tickets { page-break-inside: auto; }
      .tickets table { page-break-inside: auto; }
      .tickets thead { display: table-header-group; }
      .tickets tr { page-break-inside: avoid; }
      .footer-note { margin-top: 12px; color: #77736d; font-size: 8pt; }
   </style>
</head>
<body>
   <div class="header">
      <div class="eyebrow">REPS · ADMIN REPORT</div>
      <h1>รายงานสรุปคำขอแจ้งซ่อม</h1>
      <div class="range">ช่วงวันที่ <?= report_pdf_h(report_pdf_date($from)) ?> – <?= report_pdf_h(report_pdf_date($to)) ?></div>
   </div>

   <table class="summary">
      <tr>
         <td><div class="summary-label">คำขอทั้งหมด</div><div class="summary-value"><?= number_format($summaryTotal) ?></div></td>
         <td><div class="summary-label">ปิดงานแล้ว</div><div class="summary-value"><?= number_format($summaryCompleted) ?></div></td>
         <td><div class="summary-label">กำลังดำเนินการ</div><div class="summary-value"><?= number_format($summaryOpen) ?></div></td>
         <td><div class="summary-label">งานด่วน</div><div class="summary-value"><?= number_format($summaryUrgent) ?></div></td>
      </tr>
   </table>

   <table class="columns">
      <tr>
         <td>
            <div class="section-title">สรุปตามสถานะ</div>
            <table class="data">
               <thead><tr><th>สถานะ</th><th class="number">จำนวน</th></tr></thead>
               <tbody>
               <?php foreach ($labels as $key => $label): ?>
                  <tr><td><?= report_pdf_h($label) ?></td><td class="number"><?= number_format((int)($statusRows[$key] ?? 0)) ?></td></tr>
               <?php endforeach; ?>
               </tbody>
            </table>
            <?php if ($avgHours !== null && $avgHours !== ''): ?>
               <p class="small muted" style="margin-top:8px">เวลาเฉลี่ยจนปิดงาน: <strong><?= number_format((float)$avgHours, 1) ?> ชั่วโมง</strong></p>
            <?php endif; ?>
         </td>
         <td>
            <div class="section-title">สรุปตามหมวดหมู่</div>
            <table class="data">
               <thead><tr><th>หมวดหมู่</th><th class="number">จำนวน</th></tr></thead>
               <tbody>
               <?php if (!$categories): ?><tr><td colspan="2" class="empty">ไม่มีข้อมูล</td></tr><?php endif; ?>
               <?php foreach ($categories as $category): ?>
                  <tr><td><?= report_pdf_h($category['name']) ?></td><td class="number"><?= number_format((int)$category['total']) ?></td></tr>
               <?php endforeach; ?>
               </tbody>
            </table>
         </td>
      </tr>
   </table>

   <div class="section">
      <div class="section-title">ปริมาณคำขอรายวัน</div>
      <table class="data">
         <thead><tr><th>วันที่</th><th class="number">จำนวน</th></tr></thead>
         <tbody>
         <?php if (!$daily): ?><tr><td colspan="2" class="empty">ไม่มีข้อมูล</td></tr><?php endif; ?>
         <?php foreach ($daily as $row): ?>
            <tr><td><?= report_pdf_h(report_pdf_date((string)$row['day'])) ?></td><td class="number"><?= number_format((int)$row['total']) ?></td></tr>
         <?php endforeach; ?>
         </tbody>
      </table>
   </div>

   <pagebreak />
   <div class="section tickets">
      <div class="section-title">รายการคำขอ (สูงสุด 100 รายการ)</div>
      <table class="data small">
         <thead><tr><th>รหัส / วันที่</th><th>ผู้แจ้ง</th><th>หมวดหมู่</th><th>สถานะ</th><th>ความเร่งด่วน</th></tr></thead>
         <tbody>
         <?php if (!$tickets): ?><tr><td colspan="5" class="empty">ไม่มีข้อมูล</td></tr><?php endif; ?>
         <?php foreach ($tickets as $ticket): ?>
            <tr>
               <td>#<?= report_pdf_h($ticket['ticket_no']) ?><br><span class="muted"><?= report_pdf_h(date('d/m/Y H:i', strtotime($ticket['created_at']))) ?></span></td>
               <td><?= report_pdf_h(trim($ticket['first_name'] . ' ' . $ticket['last_name'])) ?><br><span class="muted"><?= report_pdf_h($ticket['id_number'] ?? '—') ?></span></td>
               <td><?= report_pdf_h($ticket['category_name']) ?></td>
               <td><span class="status"><?= report_pdf_h($labels[$ticket['status']] ?? $ticket['status']) ?></span></td>
               <td><?= report_pdf_h($ticket['urgency'] === 'urgent' ? 'ด่วน' : ($ticket['urgency'] === 'low' ? 'ไม่เร่งด่วน' : 'ปกติ')) ?></td>
            </tr>
         <?php endforeach; ?>
         </tbody>
      </table>
   </div>

   <p class="footer-note">สร้างเมื่อ <?= report_pdf_h(date('d/m/Y H:i')) ?> · ระบบ REPS</p>
</body>
</html>
   <?php
   $html = ob_get_clean();

   // mPDF may return blank pages when a large HTML/CSS block is parsed at once.
   ini_set('pcre.backtrack_limit', '1000000');
   ini_set('pcre.recursion_limit', '1000000');
   $css = '';
   $body = $html;
   if (preg_match('/<style\\b[^>]*>(.*?)<\\/style>/is', $html, $cssMatch)) {
      $css = $cssMatch[1];
   }
   if (preg_match('/<body\\b[^>]*>(.*?)<\\/body>/is', $html, $bodyMatch)) {
      $body = $bodyMatch[1];
   }

   $autoload = __DIR__ . '/../vendor/autoload.php';
   if (!is_file($autoload)) {
      throw new RuntimeException('ไม่พบ mPDF dependency กรุณารัน composer install');
   }
   require_once $autoload;
   if (!class_exists('Mpdf\\Mpdf')) {
      throw new RuntimeException('ไม่พบคลาส mPDF กรุณารัน composer install');
   }

   $mpdfTempDir = sys_get_temp_dir() . '/reps-mpdf-' . bin2hex(random_bytes(8));
   if (!mkdir($mpdfTempDir, 0700, true) && !is_dir($mpdfTempDir)) {
      throw new RuntimeException('สร้างโฟลเดอร์ชั่วคราวสำหรับ mPDF ไม่สำเร็จ');
   }

   $mpdf = new \Mpdf\Mpdf([
      'mode' => 'utf-8',
      'format' => 'A4',
      'margin_left' => 12,
      'margin_right' => 12,
      'margin_top' => 14,
      'margin_bottom' => 14,
      'default_font' => 'garuda',
      'autoScriptToLang' => true,
      'autoLangToFont' => true,
      'tempDir' => $mpdfTempDir
   ]);
   $mpdf->SetTitle('รายงานสรุปคำขอแจ้งซ่อม');
   $mpdf->SetDisplayMode('fullpage');
   if ($css !== '') {
      $mpdf->WriteHTML($css, \Mpdf\HTMLParserMode::HEADER_CSS);
   }
   $mpdf->WriteHTML($body, \Mpdf\HTMLParserMode::HTML_BODY);

   $downloadName = 'reps-report-' . $from . '-to-' . $to . '.pdf';
   while (ob_get_level() > 0) {
      ob_end_clean();
   }
   $mpdf->Output($downloadName, \Mpdf\Output\Destination::DOWNLOAD);
   $cleanup();
   exit;
} catch (Throwable $e) {
   if (ob_get_level() > 0) {
      ob_end_clean();
   }
   $cleanup();
   error_log('Admin report PDF failed: ' . $e->getMessage());
   http_response_code(500);
   header('Content-Type: text/plain; charset=UTF-8');
   echo 'ไม่สามารถสร้างรายงาน PDF ได้ กรุณาติดตั้ง mPDF ด้วยคำสั่ง composer install';
}
