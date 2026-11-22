<?php
// พิมพ์ความกว้างรูป (px) ของไฟล์ใน private storage จาก path ที่เก็บในฐานข้อมูล เช่น ticket-private/done_xxx.jpg
$GLOBALS['APP_SKIP_SESSION'] = true;
$root = getenv('REPS_ROOT') ?: dirname(__DIR__, 3); // run.sh คัดลอกไฟล์นี้ไปโฟลเดอร์ชั่วคราว จึงอ่านตำแหน่งโปรเจกต์จาก REPS_ROOT
require $root . '/db_connect.php';
require $root . '/includes/private_storage.php';
$abs = app_private_file_abs($argv[1] ?? '');
$info = $abs ? @getimagesize($abs) : false;
echo $info ? $info[0] : 'none';
