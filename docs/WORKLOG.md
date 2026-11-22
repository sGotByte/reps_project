# REPS — บันทึกงานทั้งหมดใน branch `fix/v2-review`

บันทึกนี้สรุปทุกอย่างที่ทำตั้งแต่เริ่มงานรอบนี้ (12–14 พ.ย. 2569) เรียงตามลำดับเวลา
อ้างอิงจาก commit จริงใน branch `fix/v2-review` เทียบกับ `main`: **47 commits · 205 ไฟล์ · +19,888 / −4,815 บรรทัด**
สถานะ: commit ครบทั้งหมดแล้ว **ยังไม่ได้ push และยังไม่ได้ deploy**

> โครงสร้างโค้ดและวิธีใช้งานโดยละเอียดอยู่ใน `AGENTS.md` (อัปเดตทุกครั้งที่เพิ่มระบบใหม่) เอกสารนี้เน้นว่า "ทำอะไรไปบ้าง และค้างอะไรอยู่"

---

## สารบัญ

1. [ข้อตกลงที่ใช้ตลอดงาน](#1-ข้อตกลงที่ใช้ตลอดงาน)
2. [รอบที่ 1 — แก้บั๊กและฟีเจอร์ v2](#2-รอบที่-1--แก้บั๊กและฟีเจอร์-v2-12-พย-2569)
3. [รอบที่ 2 — ปรับ UX/UI ทั้งระบบ](#3-รอบที่-2--ปรับ-uxui-ทั้งระบบ-13-พย-2569)
4. [รอบที่ 3 — แจ้งเตือน รูปโปรไฟล์ สองภาษา จอสรุปงาน](#4-รอบที่-3--แจ้งเตือน-รูปโปรไฟล์-สองภาษา-จอสรุปงาน-13-พย-2569)
5. [รอบที่ 4 — v3 ขั้นตอนใบงานใหม่และ War room](#5-รอบที่-4--v3-ขั้นตอนใบงานใหม่และ-war-room-14-พย-2569)
6. [รอบที่ 5 — ชุดทดสอบอัตโนมัติ](#6-รอบที่-5--ชุดทดสอบอัตโนมัติ)
7. [รอบที่ 6 — ระบบปรับแต่งเว็บ (v3 ข้อ 6 เฟส 1)](#7-รอบที่-6--ระบบปรับแต่งเว็บ-v3-ข้อ-6-เฟส-1)
8. [รอบที่ 7 — ใช้งานจริงบนมือถือ และหน้าสตาฟ](#8-รอบที่-7--ใช้งานจริงบนมือถือ-และหน้าสตาฟ)
9. [รอบที่ 8 — ใบรับรองงาน A4](#9-รอบที่-8--ใบรับรองงาน-a4)
10. [รอบที่ 9 — ภาคเรียนและทีมสตาฟ](#10-รอบที่-9--ภาคเรียนและทีมสตาฟ)
11. [รอบที่ 10 — เก็บงาน layout และเปลี่ยนชื่อจอสรุปงาน](#11-รอบที่-10--เก็บงาน-layout-และเปลี่ยนชื่อจอสรุปงาน)
    - [รอบที่ 11 — ขอบคุณ Lab boy และคะแนนสตาฟแบบใหม่](#111-รอบที่-11--ขอบคุณ-lab-boy-และคะแนนสตาฟแบบใหม่)
12. [Migration ที่ต้องรันบนเซิร์ฟเวอร์จริง](#12-migration-ที่ต้องรันบนเซิร์ฟเวอร์จริง)
13. [เหตุการณ์ที่ต้องรู้](#13-เหตุการณ์ที่ต้องรู้)
14. [งานที่ยังค้าง / ต้องตัดสินใจ](#14-งานที่ยังค้าง--ต้องตัดสินใจ)
15. [ข้อมูลอ้างอิงเครื่องพัฒนา](#15-ข้อมูลอ้างอิงเครื่องพัฒนา)
16. [รายการ commit ทั้งหมด](#16-รายการ-commit-ทั้งหมด)

---

## 1. ข้อตกลงที่ใช้ตลอดงาน

- ทำงานระดับ production: prepared statements ทุก query, ตรวจสิทธิ์ฝั่งเซิร์ฟเวอร์, CSRF ทุกฟอร์ม, escape ทุกค่าที่แสดง
- ใช้ธีมสีเดิมของระบบ, ห้ามใช้ emoji ในหน้าเว็บ (ใช้ SVG/ไอคอนแทน), footer ใส่เครดิต "GOD & LUN" พร้อมไอคอนโค้ด
- commit เฉพาะใน branch `fix/v2-review` · การ push / deploy / เปลี่ยนรหัสใน `.env` ให้ผู้ใช้ตัดสินใจเอง
- ระหว่างทดสอบในเครื่อง ปิดการส่ง LINE และอีเมลจริงทุกครั้ง
- **ข้อ 1 ของ feedback (เปลี่ยนรหัสลับใน `.env` ที่ติดอยู่ใน git) ผู้ใช้สั่งว่า "ยังไม่ต้อง"**

---

## 2. รอบที่ 1 — แก้บั๊กและฟีเจอร์ v2 (12 พ.ย. 2569)

`01c63b8` แก้ตามผลรีวิว v2 ครั้งใหญ่:

- **ระบบสิทธิ์กลาง** `includes/permissions.php`: บทบาท `admin > staff > user`, `can()` และ `ROLE_CAPABILITIES`
- **ตรวจ session กับฐานข้อมูลทุก endpoint** `includes/user_auth.php` (บัญชีถูกปิด/เปลี่ยนรหัส = หลุดทันที)
- **helper ฐานข้อมูล** `includes/db_helpers.php` (`db_one/db_all/db_exec/db_transaction` โยน exception เมื่อผิดพลาด)
- **ขั้นตอนปิดงาน**: รับงาน → ส่งปิด → ครูเซ็นรับรอง (วาดลายเซ็นบนจอ) + อีเมล + `email_logs`
- **ระบบคิว** `includes/queue_manager.php`: คิว auto/manual, ช่วงเวลาทำงาน, หักคะแนนไม่วนซ้ำคนเดิม, สถานะคิวให้ผู้แจ้งเห็น
- **ไฟล์ส่วนตัว** `file.php` เปิดรูปแนบ/รูปปิดงาน/ลายเซ็นพร้อมตรวจสิทธิ์ · อัปโหลดรูปปลอดภัย (ตรวจ MIME จริง ย่อด้วย GD ตัด EXIF ตั้งชื่อสุ่ม)
- **หน้าใหม่**: ใบรับรองงาน `certificate.php`, `cron_queue.php` (ให้ cron ภายนอกเรียกตรวจคิว), `donate.php` (QR พร้อมเพย์), `admin/admin_signoff.php`, `admin/admin_settings.php`, `staff/inventory.php` (สต็อกวัสดุ + ประวัติ)
- migration `20261112_v2_review_fixes.sql`

---

## 3. รอบที่ 2 — ปรับ UX/UI ทั้งระบบ (13 พ.ย. 2569)

- `77b458f` ปรับ UX/UI ตามผลรีวิว: contrast, ฟอนต์, มือถือ, sidebar
- `4a88665` แทน emoji ด้วย SVG ทั้งระบบ และจัดหน้าสต็อกใหม่
- `5c05ee6` ใช้ฟอนต์ Kanit ทั้งระบบ (กราฟ Chart.js, ตัวเลข/รหัส, รายงาน PDF)
- `a4b3725` แก้รายงาน PDF error 500 (ปิด OTL ของฟอนต์ Kanit ที่ mPDF ไม่รองรับ)
- `0fa51a6` เพิ่มสคริปต์ข้อมูลจำลอง `database/seeds/seed_demo.php` และแบ่งหน้าหน้าจัดการคำขอซ่อม
- `344f146` `d07ec6e` จัด layout หน้าจัดการคำขอซ่อม, เลือกสถานะแล้วกรองทันที, แดชบอร์ดภาษาไทย
- `86537bb` **มอบหมายงานเป็นทีม** (ช่างหลัก + ผู้ช่วยหลายคน) และหน้าต่างมอบหมายแบบใหม่ · migration `20261113_ticket_assignees.sql`
- `fd8b068` จัดตารางผู้สมัครรออนุมัติ (ปฏิเสธต้องใส่เหตุผล, อนุมัติหลายคนพร้อมกัน)
- `0c55c6f` `e0f1c38` footer อย่างเป็นทางการทุกหน้า (ตั้งค่าได้), แบ่งหน้าทุกตาราง, จัดตารางผู้ใช้ใหม่
- `8e99314` `588d864` `1af4ff0` `30b9654` หน้าสต็อก: การ์ดภาพรวมพร้อมกราฟเล็ก แถบระดับสต็อก ใช้สีระบบ · footer แบบเข้มกะทัดรัด

---

## 4. รอบที่ 3 — แจ้งเตือน รูปโปรไฟล์ สองภาษา จอสรุปงาน (13 พ.ย. 2569)

- `3d4f604` แจ้งเตือนวัสดุใกล้หมดบน navbar (สตาฟและผู้ดูแล)
- `11ccd41` **กระดิ่งแจ้งเตือนตามบทบาท** `includes/notifications.php`
  - ผู้ดูแล: รอรับรอง, ผู้สมัครรออนุมัติ, งานด่วนยังไม่มีคนรับ, งานค้างเกิน 24 ชม., วัสดุใกล้หมด
  - สตาฟ: งานถึงคิว/คิวรวม, งานที่กำลังซ่อม, วัสดุใกล้หมด
  - ผู้แจ้ง: ความคืบหน้าใบงาน 30 วัน พร้อมสถานะอ่านแล้ว · migration `20261114_notification_seen.sql`
- `58fc53f` **รูปโปรไฟล์** + เมนูบัญชีเมื่อกดที่รูปในทุก navbar · migration `20261114_user_avatar.sql`
- `6a5902a` แก้บั๊ก redirect ตาม role ที่ได้ host `//admin` บน Windows
- `3e4e4cb` `e555da0` **สลับภาษา TH/EN ทั้งเว็บ**: แปลตอนแสดงผลจากพจนานุกรม `lang/en.php` (ไม่ต้องแก้ซอร์สทุกหน้า), แปลข้อความที่ JS สร้าง/กราฟ, ปุ่ม TH | EN · แก้ scrollbar ซ้อนในหน้าต่างมอบหมาย
- `6251439` scrollbar แบบบางทั้งระบบ
- `dc9cd9b` ข้อมูลจำลองใช้รูปถ่ายจริงสัญญาอนุญาตเสรี (Wikimedia Commons พร้อมเครดิตใน `database/seeds/photos/CREDITS.md`)
- `f841b33` **จอสรุปงานสำหรับจอ LED/ทีวี** (ไม่ต้อง login, อัปเดตเองทุก 20 วินาที, ตั้งรหัสเปิดจอได้, ไม่แสดงข้อมูลส่วนตัวผู้แจ้ง) · migration `20261114_wallboard.sql`
- `da00255` `ffb69ce` จัดเส้นแนวของหน้าแรก หน้าเข้าสู่ระบบ/สมัครสมาชิก ให้ตรงกับ container

---

## 5. รอบที่ 4 — v3 ขั้นตอนใบงานใหม่และ War room (14 พ.ย. 2569)

ตาม `feedback-todo-v3.md` ข้อ 1–5 และคำตอบของผู้ใช้ (ผู้แจ้งต้องเซ็นก่อนเริ่มซ่อม, เซ็นได้ทั้งวาดบนจอและใช้ลายเซ็นที่อัปโหลดไว้, ครูข้ามขั้นผู้แจ้งได้หลังครบกำหนด)

`afd1e5a` **ขั้นตอนใบงาน v3** · migration `20261115_v3_workflow.sql`

```
pending → accepted (บันทึกเช็คลิสต์ตอนรับ) → awaiting_intake_sign (ผู้แจ้งเซ็นรับทราบ) → in_progress
→ inspected_ready (ซ่อมเสร็จ + ตรวจซ้ำ) → awaiting_requester_sign (สตาฟเซ็นแล้ว)
→ awaiting_admin_sign (ผู้แจ้งเซ็นแล้ว) → completed (ครูเซ็น)
```

- เช็คลิสต์อุปกรณ์ตอนรับและตอนส่งคืน (ของหาย/สภาพเปลี่ยน ต้องอธิบาย) · รายการตั้งต้นต่อหมวดหมู่ `admin/admin_checklists.php`
- เซ็น 3 ฝ่ายตามลำดับ แยกรอบ (ครูส่งกลับ = รอบใหม่ ลายเซ็นรอบก่อนเก็บไว้) · ผู้แจ้งไม่ยอมรับ → `disputed`
- ครูข้ามขั้นผู้แจ้งได้เมื่อเกินเวลาที่ตั้ง (`intake_sign_skip_hours`, `requester_sign_skip_days`) พร้อมเหตุผล
- อีเมลแจ้งทุกขั้น + อีเมลเตือนผู้แจ้งที่ยังไม่เซ็น · ช่องทางส่งอีเมลกลาง `includes/mailer.php` (Gmail SMTP หรือ Brevo API สำหรับโฮสต์ที่ปิดพอร์ต SMTP)
- หน้าใหม่ `staff/job.php` (หน้างานของช่าง), `sign.php` (ผู้แจ้งเซ็น), โมดูลเซ็นกลาง `includes/signature.php`, ส่วนแสดงผลร่วม `includes/ticket_view.php`

`fc59acc` **War room** `admin/admin_war_room.php`: งานใหม่, คิวถัดไป, ใครทำอะไรอยู่, งานค้างตามเกณฑ์ที่ตั้งได้, งานรอเซ็น, กรองตามสตาฟ/สถานะ/หมวดหมู่/ช่วงเวลา, รีเฟรชอัตโนมัติ, ผู้ดูแลส่งอีเมลเร่งงานได้

`6a74c54` แก้แถบขั้นตอนให้เห็นชัด, หน้าเซ็นบนมือถือไม่ล้นจอ, ข้อมูลจำลองมีรูปลายเซ็น

---

## 6. รอบที่ 5 — ชุดทดสอบอัตโนมัติ

- `d7d1c2c` ชุดทดสอบ e2e ในโปรเจกต์ `tests/e2e/` ยิง HTTP จริงและตรวจฐานข้อมูล
  - `run.sh` สำรองฐานข้อมูลและโฟลเดอร์รูปก่อน แล้วคืนให้เสมอ พร้อมตรวจจำนวนแถวหลังคืนค่า
  - `.htaccess` กันการเปิด `tests/` ผ่านเว็บ
- `0f85a5f` บังคับไฟล์ `.sh` เป็น LF เพื่อให้รันบน Windows ได้หลัง checkout

ผลล่าสุด: **6 ชุด 265 ข้อ ผ่านทั้งหมด**

| ชุด | ตรวจอะไร | จำนวน |
|---|---|---|
| `01_core.sh` | สมัคร/อนุมัติ/login, สิทธิ์ทุกบทบาท, แจ้งซ่อม, รูปแนบ, ไฟล์ส่วนตัว, สต็อก, ตั้งค่า | 105 |
| `02_queue_admin.sh` | คิว auto/manual, หักคะแนน, มอบหมาย | 9 |
| `03_v3_abnormal.sh` | ของหาย, ผู้แจ้งไม่ยอมรับ, ครูส่งกลับ, ข้ามขั้นตามเวลา, ลายเซ็นที่บันทึกไว้ | 38 |
| `04_site_custom.sh` | ปรับแต่งเว็บ + ค่าอันตราย (CSS แทรก, javascript:, ไฟล์ปลอม, CSRF) | 45 |
| `05_terms.sh` | ภาคเรียน, ทีมไม่เกินกำหนด, สตาฟนอกทีม, คะแนนรายภาค, รายงาน | 20 |
| `06_thanks_scores.sh` | ขอบคุณ Lab boy, คะแนนสตาฟ (เพดาน 100, ยกเลิกแบบกลับค่า), รูปหลังซ่อม 1–3 รูป | 48 |

วิธีรัน (ต้องเปิดเว็บทดสอบโดยปิดอีเมล/LINE ก่อน): `bash tests/e2e/run.sh`

---

## 7. รอบที่ 6 — ระบบปรับแต่งเว็บ (v3 ข้อ 6 เฟส 1)

`fd98468` หน้าใหม่ **ปรับแต่งเว็บ** `admin/admin_site.php` · migration `20261116_site_customize.sql`

| แท็บ | ทำอะไรได้ |
|---|---|
| ข้อมูลเว็บไซต์ | ชื่อเว็บ, คำโปรย, โลโก้, ไอคอนแท็บ (favicon) |
| ธีม | ชุดสี, สีหลัก/รอง/พื้นหลัง, ฟอนต์ 6 แบบ, ความโค้งมุม + ตัวอย่างสด |
| เมนู | แถบข้างผู้ดูแล, แท็บทีมช่าง, เมนูบัญชี — แก้ชื่อไทย/อังกฤษ เรียงลำดับ ซ่อน เลือก role ที่เห็น เพิ่มลิงก์เอง |
| ข้อความ | ค้นหาข้อความบนหน้าเว็บแล้วแก้ได้ทั้งไทยและอังกฤษ |
| Footer | ข้อมูลหน่วยงาน ช่องทางติดต่อ ลิงก์สูงสุด 8 ลิงก์ |
| คลังสื่อ | อัปโหลด ตั้งชื่อ ลบรูป (กันลบรูปที่ใช้อยู่) |

- ทุกแท็บมีปุ่มคืนค่าเริ่มต้น และบันทึกว่าใครแก้เมื่อไร (`site_revisions`)
- ทำงานผ่านตัวกรอง output จุดเดียว (`includes/site_custom.php`) ไม่มีการปรับแต่ง = หน้าเว็บเหมือนเดิมทุกประการ
- ชื่อเว็บ/ธีม/โลโก้/footer ย้ายออกจาก "ตั้งค่าระบบ" มาอยู่ที่นี่ที่เดียว
- การซ่อนเมนูไม่ได้ปิดสิทธิ์เข้าหน้า · เมนูตั้งค่าระบบ/ปรับแต่งเว็บ/งานของฉัน ซ่อนไม่ได้

---

## 8. รอบที่ 7 — ใช้งานจริงบนมือถือ และหน้าสตาฟ

- `950221b` `1262d3e` **ช่องอัปโหลดรูปแบบใหม่** `assets/js/photo-input.js` ใช้ทุกจุดที่อัปโหลดรูป
  - ทุกอุปกรณ์มีปุ่ม "ถ่ายรูป" และ "เลือกรูป" สูงเท่าช่องกรอก
  - มือถือ: ถ่ายรูปเปิดกล้องหลัง · คอมพิวเตอร์: เปิดเว็บแคมในหน้าเว็บ (ต้องเป็น HTTPS)
  - รูปตัวอย่างพร้อมปุ่มลบ, ลากไฟล์มาวางได้, ย่อรูปจากกล้องที่ใหญ่ก่อนส่ง (7 MB เหลือราว 850 KB)
  - กดบันทึกระหว่างย่อรูป ระบบรอแล้วส่งให้เอง
- `09889ba` **หน้างานของสตาฟ**: "คิวงานที่รอรับ" กับ "งานที่กำลังทำ" อยู่การ์ดเดียว สลับด้วยแท็บ (เริ่มที่คิวงาน)
- `d544fb9` `384598a` **จัด layout หน้า `staff/job.php` ใหม่**
  - คอมพิวเตอร์ 2 คอลัมน์: งานที่ต้องทำ (ซ้าย) · ข้อมูลอ้างอิง งานนี้/เช็คลิสต์/ลายเซ็น/ประวัติ (ขวา)
  - มือถือ: สรุปงาน + ปุ่ม "โทรหาผู้แจ้ง" ขึ้นก่อนฟอร์ม
  - รายการอุปกรณ์มีหัว "รายการที่ n" และปุ่มลบในหัว · ทุกแถวแบ่งครึ่งตรงกัน ช่องรูปอยู่คอลัมน์ขวาแนวเดียวกัน
  - แก้ dropdown ในมุมมองผู้ดูแลให้อยู่ใต้ป้ายและเต็มช่อง

---

## 9. รอบที่ 8 — ใบรับรองงาน A4

`40b6d36` `certificate.php` พิมพ์ A4 แนวตั้ง **1 แผ่น**

- ฟอนต์ Sarabun แบบเอกสารราชการ · หัวกระดาษ โลโก้ เลขที่ ตรา "ครูรับรองแล้ว"
- ข้อมูลใบงาน 2 คอลัมน์ · รายละเอียดการซ่อมพร้อมรูปหลักฐาน · เช็คลิสต์ · ลายเซ็น 3 ฝ่ายชิดท้ายแผ่น
- เนื้อหายาวเกิน ระบบย่อทั้งแผ่นให้พอดี (ต่ำสุด 70%) ไม่ตัดข้อความ
- ทดสอบพิมพ์เป็น PDF จริงทั้งกรณีปกติและกรณีข้อความยาวมาก ได้ 1 หน้าทั้งคู่

---

## 10. รอบที่ 9 — ภาคเรียนและทีมสตาฟ

`74c6ea7` · migration `20261117_academic_terms.sql`

**ที่มา:** สตาฟ (Lab boy) เปลี่ยนทุกภาคเรียน ไม่เกิน 4 คน ข้อมูลแต่ละภาคต้องไม่ปนกัน

- หน้าใหม่ **ภาคเรียนและทีมสตาฟ** `admin/admin_terms.php` (เมนู การจัดการ): สร้างภาค (วันเปิด–สิ้นสุด ห้ามซ้อนกัน), เลือกทีมไม่เกินจำนวนสูงสุด พร้อมลำดับคิว
- **ระบบเลือกภาคปัจจุบันเองตามวันที่** (ภาคที่ครอบวันนี้ ถ้าไม่มีใช้ภาคล่าสุดที่เริ่มแล้ว · ยังไม่มีภาคเลย = ทำงานแบบเดิม)
- เฉพาะทีมภาคปัจจุบันที่รับคิว รับงาน ถูกมอบหมาย และแสดงใน war room / จอสรุป / แดชบอร์ด
- คะแนนเริ่ม 100 ใหม่ทุกภาค · สตาฟภาคก่อนล็อกอินได้แต่รับงานใหม่ไม่ได้ (งานค้างทำต่อได้)
- รายงาน `admin_teacher_report.php` เลือกดูรายภาค หรือทั้งปีการศึกษา
- **ข้อมูลจำลองทำใหม่ให้ตรงความจริง**: เริ่ม 1 พ.ค. 2569
  - 1/2569 (1 พ.ค.–31 ต.ค. 2569): สตาฟ 2 คน `lb2569-1-01`, `lb2569-1-02`
  - 2/2569 (1 พ.ย. 2569–30 เม.ย. 2570, ภาคปัจจุบัน): สตาฟ 3 คน `lb2569-2-01` ถึง `lb2569-2-03`
  - รหัสผ่านแยกทุกบัญชี เก็บที่ `database/seeds/demo_credentials.local.txt` (ไม่เข้า git)
  - ลบข้อมูลปี 2567–2568 และสตาฟจำลองเดิม 8 คนออก

---

## 11. รอบที่ 10 — เก็บงาน layout และเปลี่ยนชื่อจอสรุปงาน

- `f4af457` ย่อชื่อเมนู "รายงานภาคเรียน" (เดิมยาวจนขึ้น 2 บรรทัด) และให้ชื่อเมนูบรรทัดเดียวเสมอ
- Sidebar ผู้ดูแล: ลองบีบให้เมนูพอดีจอโดยไม่ต้องเลื่อน (`f4e9473`) → ผู้ใช้ให้กลับแบบเดิม (`eb0acfe`) → ปุ่มออกจากระบบติดขอบล่างเสมอ (`52323f8`) → ชิดขึ้นเล็กน้อย แถว 36 px (`1ab4a4c`)
- `bad88b5` **เปลี่ยนชื่อ `wallboard.php` เป็น `warboard.php`** · ลิงก์เดิมยังเปิดได้ (ส่งต่อพร้อมรหัส `?k=`) · ชื่อค่าตั้งค่าในฐานข้อมูลคงเดิม
- `5c5b762` ขั้นตอนล้างข้อมูลจำลองลบใบงานที่บัญชีจำลองเกี่ยวข้องด้วย และตัวรันทดสอบแจ้งข้อผิดพลาดชัดเจน (ดูข้อ 13)

---

## 11.1 รอบที่ 11 — ขอบคุณ Lab boy และคะแนนสตาฟแบบใหม่

migration `20261118_thanks_scores.sql`

**ขอบคุณ Lab boy (แทนหน้า Donate แบบ QR กลาง)** — ที่มา: ก่อนมีระบบ ครูมักให้ขนม น้ำ กาแฟเป็นน้ำใจ อยากให้ระบบรองรับแบบไม่บังคับและบันทึกไว้
- Lab boy อัปโหลด QR พร้อมเพย์ของตัวเองที่หน้าโปรไฟล์
- ผู้แจ้งเข้าหน้า `thanks.php` หลังเซ็นรับคืน (มีการ์ดชวนในหน้าใบงาน และลิงก์ในประวัติ) เลือกช่างในทีม → สแกน QR ของคนนั้น หรือบันทึกว่าให้ขนม/เครื่องดื่ม แนบสลิปได้
- Lab boy กด "ได้รับแล้ว / ไม่ได้รับ" ที่แท็บ "คำขอบคุณ" · ครูดูทั้งหมดตามภาคเรียนที่ "คำขอบคุณ Lab boy"
- ไม่เกี่ยวกับคะแนน ไม่จัดอันดับ · QR และสลิปเป็นไฟล์ส่วนตัว · ระบบไม่รับเงินและไม่ตรวจสลิป
- เปิด/ปิด ยอดสูงสุด และจำนวนวันที่ขอบคุณได้ ตั้งที่ ตั้งค่าระบบ (ค่าเริ่มต้น **ปิด**)

**คะแนนสตาฟ** — กติกาตามผู้ใช้: ทุกคนเริ่มภาคเรียน 100 (เต็ม) หักเมื่อทำผิดข้อกำหนด ครูผู้ควบคุมบวกคืนได้ไม่เกิน 100
- หน้าใหม่ "คะแนนสตาฟ" `admin/admin_scores.php`: หักตามรายการความผิดที่ตั้งไว้ (แก้/เพิ่มได้) หรือกำหนดเอง พร้อมเหตุผลและเลขใบงาน
- ยกเลิกรายการ = บันทึกรายการกลับค่า ประวัติไม่ถูกลบ · ปรับได้เฉพาะทีมภาคเรียนปัจจุบัน
- เลิกโบนัส +2 อัตโนมัติเมื่อปิดงาน · ยังหักอัตโนมัติเมื่อไม่กดรับคิวทันเวลา

ข้อมูลจำลอง: QR ตัวอย่าง (ไม่ใช่ QR จ่ายเงินได้จริง) ของ Lab boy ทุกคน, คำขอบคุณจำลอง, ครูหัก/ให้คะแนนจำลอง

**ใบรับรองงาน (ปรับเพิ่ม):** ฟอนต์ Mitr · ป้ายลายเซ็น "สตาฟผู้รับงาน" · รูปหลักฐานหลังซ่อม 1–3 รูป (ช่างแนบได้สูงสุด 3 รูปตอนบันทึกผลซ่อม · รูปเดียววางข้างรายละเอียด 2–3 รูปเรียงเป็นแถว ยังพอดี A4 1 แผ่น) · migration `20261119_completion_photos.sql`

## 11.2 รอบที่ 12 — ครูรีเซ็ตรหัสผ่านให้ผู้ใช้/สตาฟ

migration `20261120_must_change_password.sql` (คอลัมน์ `users.must_change_password`)

ที่มา: ผู้ใช้/สตาฟลืมรหัสผ่านได้ และบางคนไม่มีอีเมลที่ใช้ได้ หน้า "แก้ไขข้อมูลผู้ใช้" จึงมีส่วน **รีเซ็ตรหัสผ่าน** 2 วิธี
- **ตั้งรหัสผ่านชั่วคราว**: ระบบสุ่มรหัส (เช่น `h2wR-8n3H` ไม่มีตัวที่สับสน 0/O 1/l) แสดงให้ครูครั้งเดียวพร้อมปุ่มคัดลอก ไม่เก็บเป็นข้อความ · ผู้ใช้ล็อกอินด้วยรหัสนี้แล้วถูกส่งไป `change_password.php` ทุกหน้าจนกว่าจะตั้งรหัสใหม่ (ต้องไม่ซ้ำรหัสชั่วคราว) · POST/polling ระหว่างนั้นได้ 403
- **ส่งลิงก์ทางอีเมล**: ใช้ token แบบเดียวกับ "ลืมรหัสผ่าน" (1 ชั่วโมง ใช้ครั้งเดียว) · ส่งไม่สำเร็จ → ยกเลิกลิงก์และแนะนำให้ใช้รหัสชั่วคราว
- ทุกวิธี: บัญชีหลุดออกจากระบบทุกเครื่อง ลิงก์รีเซ็ตเดิมใช้ไม่ได้ บันทึก audit ว่าครูคนไหนรีเซ็ต
- ห้ามรีเซ็ตบัญชีตัวเอง (ใช้หน้าโปรไฟล์) และบัญชีครู/ผู้ดูแลคนอื่น (ใช้ "ลืมรหัสผ่าน") · บัญชีที่ยังไม่อนุมัติรีเซ็ตไม่ได้
- โค้ด: `includes/password_reset.php`, `change_password.php`, `includes/user_auth.php` (บังคับเปลี่ยน), `admin/admin_edit_user.php` · ทดสอบ `tests/e2e/07_password_reset.sh` (30 ข้อ)

## 11.3 รอบที่ 13 — รูปวัสดุ และเปิดรูปใน modal ทั้งระบบ

migration `20261121_inventory_image.sql` (คอลัมน์ `inventory_items.image_path`)

- **สต็อกวัสดุ:** หน้าต่าง "เพิ่มวัสดุใหม่" และ "แก้ไขข้อมูลวัสดุ" ใส่รูปได้ (ถ่ายรูป/เลือกรูป ไม่บังคับ) · แก้ไขแล้วเปลี่ยนรูปหรือติ๊ก "ลบรูปนี้" ได้ ไฟล์เก่าถูกลบ · ตารางยอดคงเหลือมีรูปย่อหน้าชื่อวัสดุให้กดดู (ไม่มีรูปแสดงไอคอนเส้นประ) · รูปเป็นไฟล์ส่วนตัว เปิดได้เฉพาะสตาฟ/ครู
- **เปิดรูปทั้งระบบเป็น modal แทนแท็บใหม่:** `assets/js/image-viewer.js` ทำงานอัตโนมัติกับลิงก์รูปทุกจุด (รูปแนบใบงาน รูปตอนรับ/ส่งคืน รูปหลังซ่อม สลิป คลังสื่อ รูปวัสดุ) · มีชื่อรูป ลำดับ "2 / 3" ปุ่มก่อน/ถัดไป ปัดบนมือถือ ลูกศรคีย์บอร์ด ดาวน์โหลด ปิดด้วย Esc/คลิกพื้นหลัง · ใบรับรองงานและจอสรุปงานยังเปิดแท็บใหม่ตามเดิม (เป็นหน้าเอกสาร/จอแสดงผล ไม่ใช่รูป)
- ทดสอบ `tests/e2e/08_inventory_images.sh` (15 ข้อ)

## 11.4 รอบที่ 14 — กระดิ่งแจ้งเตือนอ่านทีละรายการ

migration `20261122_notification_reads.sql`

- เดิม: ครู/สตาฟเห็นรายการค้างจนกว่าจะจัดการเสร็จ (กดดูแล้วไม่หาย) · ผู้แจ้งเปิดกระดิ่งครั้งเดียวถือว่าอ่านหมด
- ใหม่ (ตามที่ผู้ใช้ขอ "มี 3 การแจ้งเตือน กดดู 1 ต้องเหลือ 2"): กดรายการไหน รายการนั้นหายและตัวเลขลดลงทันที ทุกบทบาท · มีปุ่ม "อ่านทั้งหมด" · เปิดกระดิ่งเฉย ๆ ไม่นับว่าอ่าน
- เรื่องเดิมที่เปลี่ยนขั้นตอน (เช่น งานจากคิวรวมกลายเป็นถึงคิวคุณ, ครูส่งกลับรอบใหม่, วัสดุลดลงอีก, มีงานค้างเกิน 24 ชม. เพิ่ม) แจ้งเตือนใหม่อีกครั้ง
- หน้าที่ต้องทำงานจริง (รับรองงาน, จัดการคำขอ, คิวงาน) ยังแสดงรายการครบเหมือนเดิม การอ่านในกระดิ่งไม่ได้ซ่อนงาน
- แก้บั๊ก: กลุ่ม "ความคืบหน้าใบงานของคุณ" ของผู้แจ้งแสดงซ้ำ 2 ครั้ง ทำให้ตัวเลขเป็น 2 เท่า
- ทดสอบ `tests/e2e/09_notifications.sh` (20 ข้อ)

## 11.5 รอบที่ 15 — จัดแถบเมนูผู้ดูแลใหม่ และรายงานตามแผนก/งาน

ไม่มี migration · หารือก่อนลงมือ: ทำภาพตัวอย่างเทียบ 4 แบบ ผู้ใช้เลือก "จัดกลุ่มใหม่ + พับกลุ่มเมื่อจอเตี้ย" และขอให้รายงานเป็นกลุ่มที่มีเมนูย่อย เผื่อเพิ่มรายงานอื่นภายหลัง

- เมนูเหลือ 13 รายการใน 5 กลุ่ม: ภาพรวม (แดชบอร์ด, ภาพรวมงาน) · งานซ่อม (จัดการคำขอซ่อม, รับรองงาน, ประวัติ) · ทีมสตาฟ (ภาคเรียนและทีม, คะแนนสตาฟ, คำขอบคุณ Lab boy) · ข้อมูล (จัดการผู้ใช้, สต็อกวัสดุ) · รายงาน (ตามช่วงวันที่, ตามภาคเรียน, ตามแผนก/งาน)
- ตั้งค่าระบบ / ปรับแต่งเว็บ เป็นปุ่มไอคอนข้างปุ่มออกจากระบบ · จอสรุปงานย้ายเป็นปุ่มในหน้าภาพรวมงาน · มุมมองสตาฟอยู่ในเมนูรูปโปรไฟล์ · หน้าการแจ้งเตือนใช้กระดิ่งแทน (หน้าเดิมยังเปิดได้)
- หัวกลุ่มกดพับ/กางได้ · จอเตี้ยพับกลุ่มที่ไม่ใช่หน้าปัจจุบันให้พอดีจอเอง ไม่มีแถบเลื่อน · กลุ่มที่พับแสดงตัวเลขรวม · ตัวเลขทุกเมนูเป็นวงกลมสีส้มชิดขวา (เดิมเขียนในวงเล็บ)
- ชื่อ/ลำดับ/การซ่อนเมนูที่ครูเคยปรับไว้ยังใช้ได้ (key เดิม) · ลิงก์ที่ครูเพิ่มเองในกลุ่ม "การจัดการ" ย้ายไป "ข้อมูล" และ "การติดตาม" ย้ายไป "ภาพรวม"
- **รายงานตามแผนก/งาน** (ใหม่): เลือกภาคเรียน ทั้งปีการศึกษา หรือช่วงวันที่ · สรุปต่อแผนก ใบงาน เสร็จ ยังไม่ปิด ยกเลิก ด่วน เวลารับงาน/เสร็จเฉลี่ย หมวดที่แจ้งบ่อย · แผนกที่ไม่มีใบงานก็แสดง · กดดูใบงานของแผนกได้ · ดาวน์โหลด CSV และพิมพ์
- ทดสอบ `tests/e2e/10_sidebar_reports.sh` (22 ข้อ) · แก้ตัวรันทดสอบให้เลือกไฟล์ชุดที่ 10 ขึ้นไปด้วย

## 12. Migration ที่ต้องรันบนเซิร์ฟเวอร์จริง

รันตามลำดับ ทุกไฟล์รันซ้ำได้ (ใช้ได้ทั้ง MySQL 8 และ MariaDB) · ควรสำรองฐานข้อมูลก่อน

1. `database/migrations/20261112_v2_review_fixes.sql`
2. `database/migrations/20261113_ticket_assignees.sql`
3. `database/migrations/20261114_notification_seen.sql`
4. `database/migrations/20261114_user_avatar.sql`
5. `database/migrations/20261114_wallboard.sql`
6. `database/migrations/20261115_v3_workflow.sql`
7. `database/migrations/20261116_site_customize.sql`
8. `database/migrations/20261117_academic_terms.sql`
9. `database/migrations/20261118_thanks_scores.sql`
10. `database/migrations/20261119_completion_photos.sql`
11. `database/migrations/20261120_must_change_password.sql`
12. `database/migrations/20261121_inventory_image.sql`
13. `database/migrations/20261122_notification_reads.sql`
14. `database/migrations/20261122_reps4_contact_snapshots.sql`

หลังรัน: เข้าหน้า **ภาคเรียนและทีมสตาฟ** สร้างภาคเรียนปัจจุบันและเลือกทีม (ถ้ายังไม่สร้าง ระบบทำงานแบบเดิม)

---

## 13. เหตุการณ์ที่ต้องรู้

- **ทดสอบส่ง LINE จริง 1 ครั้ง** (ตามที่ผู้ใช้สั่ง "ทดสอบส่งหน่อย"): ข้อความทดสอบเข้ากลุ่ม "REPS demo" ผ่านบอต "REPS Notify" — token ใน `.env` ยังใช้งานได้
- **รูปในโฟลเดอร์ทดสอบเคยหายระหว่างพัฒนาตัวรันทดสอบ** (ก่อนแก้ path บน Windows) — กู้คืนจากสำเนาสำรองครบแล้ว
- **14 พ.ย. 2569 รูปในเครื่องหายระหว่างหาสาเหตุชุดทดสอบไม่รัน**: รันขั้นตอนล้างข้อมูลจำลองกับสำเนาฐานข้อมูลที่ใช้โฟลเดอร์รูปร่วมกับฐานข้อมูลจริง (ความผิดพลาดของผู้พัฒนา)
  - รูปจำลอง 456 ไฟล์: สร้างใหม่ได้ภาพเดิมทุกไฟล์
  - **รูปจริง 2 ไฟล์ของใบงาน `LB-2026-2733055B`** (มีคนลองแจ้งซ่อมด้วยบัญชี `demo_u046`): กู้คืนไม่ได้
  - ป้องกันแล้ว: เพิ่มคำเตือนในไฟล์ seed และตัวรันทดสอบแจ้งข้อผิดพลาดแทนการจบเงียบ
- **อย่าใช้บัญชี `demo_*` หรือ `lb2569-*` ทดลองระบบด้วยข้อมูลที่ต้องการเก็บ**: ข้อมูลจากบัญชีจำลองถูกลบทุกครั้งที่สร้างข้อมูลจำลองใหม่หรือรันชุดทดสอบ

---

## 14. งานที่ยังค้าง / ต้องตัดสินใจ

**ความปลอดภัย (สำคัญที่สุด)**
- `.env` ที่มีรหัสจริง (รหัสฐานข้อมูล, LINE Channel Access Token, Gmail App Password) **ยังอยู่ใน git และเคย push ขึ้น GitHub** ต้องเปลี่ยนรหัสทั้งหมดและลบออกจากประวัติ git — ผู้ใช้สั่งให้รอไว้ก่อน

**ก่อนใช้งานจริง**
- push branch และ deploy, รัน migration ข้อ 12
- ทดสอบส่งอีเมลจริงบนโฮสต์ (InfinityFree อาจปิดพอร์ต SMTP — มีตัวเลือก Brevo)
- กรอกชื่อสถานศึกษา/ช่องทางติดต่อในหน้าปรับแต่งเว็บ → Footer
- ทดลองถ่ายรูปบนโทรศัพท์จริง (Android และ iPhone) ตอนรับงานและบันทึกเช็คลิสต์
- ตรวจวันเปิด–ปิดภาคเรียนจริงของวิทยาลัย แล้วแก้ในหน้าภาคเรียน
- บัญชี `staff_nalan` ยังมีบทบาทสตาฟแต่ไม่ได้อยู่ในทีมภาคใด — ถ้าไม่ใช่สตาฟแล้วให้เปลี่ยนบทบาท
- เปิดระบบขอบคุณ Lab boy ที่ ตั้งค่าระบบ (ปิดไว้เป็นค่าเริ่มต้น) และให้ Lab boy อัปโหลด QR ของตัวเอง
- ตรวจรายการความผิด/คะแนนตั้งต้นในหน้าคะแนนสตาฟ (ระบบเสนอไว้ 7 รายการ) ให้ตรงข้อกำหนดจริงของห้อง

**v3 ที่ยังไม่ได้ทำ**
- ระบบปรับแต่งเว็บ เฟส 2: หน้าแรกแบบบล็อกลากเรียงได้, หน้าเนื้อหา (rich text), ปรับแต่งฟอร์มแจ้งซ่อม
- เฟส 3: ร่าง/เผยแพร่, ดูตัวอย่างก่อนเผยแพร่, ย้อนเวอร์ชัน, แก้แม่แบบอีเมล (ตารางในฐานข้อมูลสร้างไว้แล้ว)
- โหมดมืด (ต้องปรับสีที่เขียนตายตัวในแต่ละหน้าให้ดึงจากจุดเดียวก่อน)
- ประเด็นที่ยังไม่ได้ยืนยันใน v3 ข้อ 9: ครูผู้ดูแลกับ admin คนเดียวกันหรือไม่, เกณฑ์งานค้าง (ตั้งไว้ 24/72 ชม.), รายการเช็คลิสต์ตั้งต้น (ระบบเสนอให้แล้ว), สำเนาอีเมลถึงใคร

**อื่น ๆ**
- อีเมล ข้อความ LINE และ PDF ยังเป็นภาษาไทยอย่างเดียว
- ตรวจวรรณยุกต์ในรายงาน PDF
- Tailwind ยังโหลดจาก CDN (ควร compile เป็นไฟล์ CSS สำหรับ production)
- ไฟล์ที่ยังไม่ได้อยู่ใน git: `feedback-todo-v3.md`, `.claude/`, `skills-lock.json`

---

## 15. ข้อมูลอ้างอิงเครื่องพัฒนา

| รายการ | ค่า |
|---|---|
| โปรเจกต์ | `D:\00-Project\reps_project` (branch `fix/v2-review`) |
| ฐานข้อมูล | `reps_v2_db` บน MySQL 8.0.30 (Laragon) `127.0.0.1:3306` ตั้งค่าใน `.env` |
| ไฟล์ข้อมูล MySQL | `D:\laragon\data\mysql-8\reps_v2_db\` |
| รูปส่วนตัว | `private_uploads/tickets/` (ไม่เข้า git) |
| PHP | 8.1.10 (Laragon) |
| บัญชีทดสอบอัตโนมัติ | `t_admin`, `t_staff`, `t_staff2`, `t_user` |
| บัญชีจำลอง | `demo_admin1/2`, `demo_u001`–`demo_u060` และสตาฟ `lb2569-*` |
| สร้างข้อมูลจำลองใหม่ | `php database/seeds/seed_demo.php` (`--clean` = ลบอย่างเดียว · ปฏิเสธถ้า DB ไม่ใช่เครื่องตัวเอง) |

รหัสผ่านไม่ได้เขียนไว้ในเอกสารนี้ — บัญชีสตาฟดูที่ `database/seeds/demo_credentials.local.txt` ในเครื่อง

---

## 16. รายการ commit ทั้งหมด

| commit | วันที่ | รายละเอียด |
|---|---|---|
| `01c63b8` | 12 พ.ย. | แก้บั๊กสิทธิ์/คิว/ปิดงาน และเพิ่มฟีเจอร์ v2 (รับรองงาน สต็อก ตั้งค่า Donate) |
| `77b458f` | 13 พ.ย. | ปรับ UX/UI ทั้งระบบ (contrast, ฟอนต์, มือถือ, sidebar) |
| `4a88665` | 13 พ.ย. | แทน emoji ด้วย SVG และจัดหน้าสต็อกใหม่ |
| `5c05ee6` | 13 พ.ย. | ฟอนต์ Kanit ทั้งระบบ รวมกราฟและ PDF |
| `a4b3725` | 13 พ.ย. | แก้รายงาน PDF error 500 |
| `0fa51a6` | 13 พ.ย. | สคริปต์ข้อมูลจำลอง และแบ่งหน้าหน้าจัดการคำขอซ่อม |
| `344f146` | 13 พ.ย. | layout หน้าจัดการคำขอซ่อม, รูปในข้อมูลจำลอง, แดชบอร์ดภาษาไทย |
| `d07ec6e` | 13 พ.ย. | เลือกสถานะแล้วกรองทันที |
| `86537bb` | 13 พ.ย. | มอบหมายงานเป็นทีม |
| `fd8b068` | 13 พ.ย. | ตารางผู้สมัครรออนุมัติ |
| `0c55c6f` | 13 พ.ย. | footer อย่างเป็นทางการทุกหน้า |
| `e0f1c38` | 13 พ.ย. | แบ่งหน้าทุกตาราง, ตารางผู้ใช้ใหม่, ชื่อผู้พัฒนาใน footer |
| `8e99314` | 13 พ.ย. | หน้าสต็อก: การ์ดภาพรวมและแถบระดับสต็อก |
| `588d864` | 13 พ.ย. | footer แบบเข้มกะทัดรัด |
| `1af4ff0` | 13 พ.ย. | ป้ายของใกล้หมดใช้ชิปของระบบ |
| `30b9654` | 13 พ.ย. | จำกัดสีหน้าสต็อกตามธีม |
| `3d4f604` | 13 พ.ย. | แจ้งเตือนวัสดุใกล้หมดบน navbar |
| `11ccd41` | 13 พ.ย. | กระดิ่งแจ้งเตือนตามบทบาท |
| `58fc53f` | 13 พ.ย. | รูปโปรไฟล์และเมนูบัญชี |
| `6a5902a` | 13 พ.ย. | แก้ redirect ตาม role บน Windows |
| `3e4e4cb` | 13 พ.ย. | ปุ่มสลับภาษา TH/EN |
| `e555da0` | 13 พ.ย. | แปลภาษาทั้งเว็บตอนแสดงผล |
| `6251439` | 13 พ.ย. | scrollbar แบบบางทั้งระบบ |
| `dc9cd9b` | 13 พ.ย. | รูปถ่ายจริงในข้อมูลจำลอง (Wikimedia) |
| `f841b33` | 13 พ.ย. | จอสรุปงานสำหรับจอ LED/ทีวี |
| `da00255` | 13 พ.ย. | จัดแนวหน้าแรก |
| `ffb69ce` | 13 พ.ย. | จัดแนวหน้าเข้าสู่ระบบ/สมัครสมาชิก |
| `afd1e5a` | 14 พ.ย. | v3 ขั้นตอนใบงาน: เช็คลิสต์ เซ็น 3 ฝ่าย อีเมล |
| `fc59acc` | 14 พ.ย. | v3 War room |
| `6a74c54` | 14 พ.ย. | v3 แก้แถบขั้นตอนและหน้าเซ็นบนมือถือ |
| `d7d1c2c` | 14 พ.ย. | ชุดทดสอบ e2e ในโปรเจกต์ |
| `0f85a5f` | 14 พ.ย. | ไฟล์ .sh เป็น LF |
| `fd98468` | 14 พ.ย. | ระบบปรับแต่งเว็บ เฟส 1 |
| `950221b` | 14 พ.ย. | ช่องอัปโหลดรูปสำหรับมือถือ |
| `1262d3e` | 14 พ.ย. | ปุ่มถ่ายรูป/เลือกรูปทุกอุปกรณ์ + เว็บแคม |
| `09889ba` | 14 พ.ย. | หน้าสตาฟ: แท็บคิวงาน/งานที่กำลังทำ |
| `40b6d36` | 14 พ.ย. | ใบรับรองงาน A4 1 แผ่น |
| `74c6ea7` | 14 พ.ย. | ภาคเรียนและทีมสตาฟ + ข้อมูลจำลองตามจริง |
| `f4af457` | 14 พ.ย. | ย่อชื่อเมนูรายงานภาคเรียน |
| `f4e9473` | 14 พ.ย. | sidebar แบบบีบให้พอดีจอ (ภายหลัง revert) |
| `d544fb9` | 14 พ.ย. | จัด layout หน้า job.php |
| `384598a` | 14 พ.ย. | job.php: dropdown มุมมองผู้ดูแล และแนวคอลัมน์ |
| `eb0acfe` | 14 พ.ย. | revert sidebar กลับแบบเดิม + ปุ่มออกจากระบบ |
| `52323f8` | 14 พ.ย. | ปุ่มออกจากระบบติดขอบล่าง sidebar |
| `1ab4a4c` | 14 พ.ย. | sidebar ชิดขึ้นเล็กน้อย (36 px) |
| `bad88b5` | 14 พ.ย. | เปลี่ยนชื่อเป็น warboard.php |
| `5c5b762` | 14 พ.ย. | ล้างข้อมูลจำลองครอบคลุมขึ้น + ตัวรันทดสอบแจ้ง error |

---

## REPS 4.0 — Phase 0: Audit & Stabilization (22 พ.ย. 2569)

ตรวจ Source Code, schema SQL, WORKLOG, authentication/permissions และ service/helper ที่เกี่ยวข้อง โดยยังไม่แก้ backend behavior หรือ schema

### Existing Implementation

- ระบบเดิมเป็น PHP/MySQL แบบ modular บางส่วน: `includes/ticket_service.php`, `includes/signature.php`, `includes/permissions.php`, `includes/db_helpers.php` และ `includes/private_storage.php` มี logic ที่ใช้ร่วมกันอยู่แล้ว
- Authentication ตรวจบัญชีและสถานะจากฐานข้อมูล, ผูก session กับ password hash, เปลี่ยน session ID หลัง login, มี CSRF และ rate limit; สิทธิ์มี role/capability กลาง
- Ticket workflow v3, ลายเซ็นผู้แจ้ง/สตาฟ/admin, บันทึก sign-off แบบ immutable และอีเมลที่พากลับ `sign.php` เพื่อ login มีอยู่แล้ว ต้องต่อยอดจากบริการเดิม
- แบบแจ้งซ่อมยังเป็น 6 ขั้น; มี urgency reason ที่ตรวจฝั่ง server, ผู้ติดต่อหน้างาน และข้อมูลครูผู้ประสานงาน/ผู้ดูแลห้อง แต่ยังไม่พบ secondary contact หรือ contact history/snapshot แยกเฉพาะ
- Visual customization ปัจจุบันมี identity/theme/menu/text/footer/media; schema dump มี `site_pages`, `site_blocks`, `site_revisions`, `email_templates` แต่ source ยังไม่ใช้ตารางหน้า/บล็อกเป็น CMS หรือ Builder และยังไม่พบ draft-preview-publish/rollback UI
- มี Three.js และ `assets/js/home-3d.js` สำหรับหน้าแรก แต่ยังไม่พบการใช้ scene ร่วมใน Login/Register

### Audit Findings / Blockers

- `database/migrations/` และไฟล์ migration ที่ WORKLOG ระบุไม่อยู่ใน checkout นี้; `database/scema.sql` เป็น schema เก่า ส่วน `reps_v2_db.sql` เป็น dump ที่ใหม่กว่าและมีตาราง/คอลัมน์ v3 หลายรายการ จึงยังยืนยันลำดับ migration ที่ติดตั้งจริงหรือความเข้ากันได้กับฐานข้อมูล production ไม่ได้
- Git checkout นี้ไม่มี commit และไฟล์โครงการแสดงเป็น untracked จึงตรวจ diff/ประวัติ secret ตามข้อความเดิมใน WORKLOG ไม่ได้; `.env` อยู่ในเครื่องแต่ไม่ได้เปิดอ่าน
- E2E runner สำรองและแก้ไขฐานข้อมูลกับโฟลเดอร์อัปโหลดก่อนคืนค่า จึงไม่รันโดยไม่มีการยืนยันว่าเป็น environment ทดสอบที่แยกจากข้อมูลจริง

### Next Work Plan

1. Phase 1: ย่อ Request Wizard เป็น 4 ขั้น และเพิ่ม secondary contact/contact snapshot-history แบบ additive migration หลังตรวจรับ schema/migration ที่ใช้งานจริง; reuse ticket/signature/auth services.
2. Phase 2–4: วาง Component Schema/Safe Renderer และทำ Builder, revision workflow, CMS/Form Builder บนตาราง site เดิมก่อนพิจารณาตารางใหม่.
3. Phase 5: ทำ shared Three.js auth background โดยแยกจาก authentication logic และตรวจ reduced-motion/fallback/lazy loading.
4. Phase 6: integration, regression และ production readiness หลัง migration/test environment ชัดเจน.

### Validation

- PHP syntax lint: ผ่านทุกไฟล์ PHP ในโปรเจกต์ (ไม่นับ `vendor/`).
- E2E: ไม่ได้รัน; ต้องใช้เว็บและฐานข้อมูลทดสอบเฉพาะ เนื่องจาก runner มีการสำรอง/แก้ไข/restore ข้อมูล.
- Database changes ใน Phase 0: ไม่มี.

## REPS 4.0 — Phase 1: Repair Workflow (22 พ.ย. 2569)

### Existing Features Reused

- ใช้ `app_require_active_user()`, CSRF helpers, DB transaction/helpers, ticket workflow และ queue เดิม
- ไม่แก้ ticket engine, authentication, permission หรือ signature engine; ฟอร์มยังใช้การสร้างใบงาน/คิวเดิม

### Implemented

- Request Wizard เหลือ 4 ขั้น: สถานที่และผู้ติดต่อ · ปัญหาและอุปกรณ์ · ตรวจสอบ · ส่งใบงาน; รวม urgency และการตรวจเหตุผลด่วนไว้ในขั้น 2
- ผู้แจ้งเป็นผู้ติดต่อหลักโดยใช้ข้อมูลบัญชีจากฐานข้อมูล (ไม่รับค่า primary จาก browser); เพิ่มผู้ติดต่อสำรองพร้อมตรวจชื่อ เบอร์โทร และอีเมลทั้ง client/server
- บันทึก snapshot ผู้ติดต่อหลัก/สำรองลงในใบงาน และ append ทั้งสอง snapshot ใน `ticket_contact_history` ภายใต้ transaction เดียวกับการสร้างใบงาน
- หน้ารายละเอียดของ staff/admin แสดงข้อมูล snapshot; อีเมลแจ้งขั้นตอนใช้ primary-contact email snapshot และ fallback เป็นอีเมลบัญชีสำหรับ ticket เก่า
- รักษา `tickets.contact_person` เดิมโดยเขียนค่า primary-contact รูปแบบเดิมเพื่อ compatibility

### Files

- แก้ไข: `request_form.php`, `includes/ticket_view.php`, `includes/ticket_mail.php`, `staff/staff_dashboard.php`, `staff/job.php`, `admin/admin_manage_request.php`, `tests/e2e/01_core.sh`, `docs/WORKLOG.md`
- เพิ่ม: `database/migrations/20261122_reps4_contact_snapshots.sql`

### Database / Security / Validation

- Migration เพิ่มคอลัมน์ nullable ใน `tickets` และตารางประวัติแบบ append-only; ไม่ backfill snapshot เก่าจากข้อมูลบัญชีปัจจุบัน เพราะไม่ใช่หลักฐานที่ยืนยันได้ว่าเป็นข้อมูล ณ เวลาสร้างใบงาน
- Migration นี้ออกแบบกับ `reps_v2_db.sql` ตามที่ผู้ใช้อนุมัติ และต้องรันหนึ่งครั้งก่อน deploy โค้ด; production schema ยังไม่ได้ตรวจเทียบ
- Snapshot มาจากบัญชีที่ผ่าน authentication; ค่าผู้ติดต่อสำรองตรวจความยาว/รูปแบบ และแสดงผลผ่าน HTML escaping; writes ใช้ prepared statements และ transaction
- PHP syntax lint ผ่านทุกไฟล์ PHP (ไม่นับ `vendor/`); `bash -n tests/e2e/01_core.sh` ผ่าน; ตรวจจำนวน SQL placeholders/bind types (23/23) และ wizard steps (1–4) ผ่าน
- E2E ไม่ได้รัน เพราะ runner มีการแก้ไขฐานข้อมูลและโฟลเดอร์อัปโหลด; ต้องรันใน environment ทดสอบที่แยกจากข้อมูลจริงหลัง apply migration

### Remaining

- Migration ยังไม่ถูก apply และยังไม่มีผล E2E บนฐานข้อมูลทดสอบ
- ประวัติเก็บ initial snapshots แล้ว; ยังไม่มี UI/endpoint สำหรับแก้ไข contact ภายหลัง ซึ่งหากเพิ่มภายหลังต้อง append snapshot ใหม่แทนการแก้ประวัติเดิม

### Next Phase Recommendation

Phase 2 ยัง blocked จน Phase 1 validation ผ่านและได้รับการอนุมัติ; หลังจากนั้นจึงสำรวจ `site_pages`, `site_blocks`, `site_revisions` ก่อนวาง Visual Builder foundation.

## Phase 1 — Isolated Test Preparation and Validation

- สร้าง MariaDB instance ใหม่โดยใช้ data directory แยกใน session storage, bind เฉพาะ `127.0.0.1:3307` และสร้าง `reps_v2_db_test`; ไม่เชื่อมต่อหรือแก้ไขฐานข้อมูลเดิม
- ใช้เฉพาะ DDL ที่สกัดจาก `reps_v2_db.sql` โดยไม่มี INSERT/row data และตัด AUTO_INCREMENT counters; fixtures เป็นข้อมูลสังเคราะห์สำหรับหมวดงาน แผนก วัสดุ checklist และ score rule (ไม่มี user/ticket rows ใน baseline)
- เตรียม env/client config ใน session storage ด้วยสิทธิ์ `0600`; PHP และ MariaDB CLI ตรวจ identity ได้ตรงกัน (`reps_v2_db_test`, port `3307`, server id เดียวกัน)
- เตรียม upload root แยกนอก document root พร้อม marker และ JPEG fixture สังเคราะห์; ตรวจ SHA-256 manifest แล้ว และตรวจว่าไม่ทับซ้อนกับ upload path ที่ตั้งไว้เดิม
- ก่อน migration ตรวจ compatibility: `tickets.contact_person` มีอยู่; `tickets.id` และ `users.user_id` เป็น signed `int` บน InnoDB; snapshot columns และ history table ยังไม่มี
- หลังได้รับอนุญาต apply migration เฉพาะกับ `reps_v2_db_test`; ตรวจพบ 6 contact columns, `ticket_contact_history` และ 2 foreign keys
- ปรับ E2E runner ให้ fail-closed ต่อ endpoint/database/account/upload path อื่น, บังคับ MySQL CLI ทุก suite ใช้ defaults file ที่สิทธิ์ `0600` พร้อม host/port/database explicit, ให้ PHP CLI/HTTP ใช้ env ชุดเดียวกัน, ปิด mail/LINE ผ่าน `REPS_E2E`, และตรวจ DB fingerprint กับ SHA-256 หลัง restore
- E2E รอบแรกหยุดหลังพบว่า child suites ยังใช้ implicit MySQL defaults; คำสั่งเดิม `mysql -uroot` ถูกปฏิเสธสิทธิ์และไม่ทำ SQL writes; isolated DB/upload ถูก restore และตรวจ hash ผ่าน. แก้ `MYSQL_ARGS` ให้ทุก suite ใช้ test endpoint อย่าง explicit
- รอบ E2E ที่ถูกต้องรันครบ 10 suites: **350 ผ่าน, 6 ไม่ผ่าน**. `01_core.sh` มี 109 ผ่าน/1 ไม่ผ่าน (“Request Wizard แสดง 4 ขั้นตอน”); suites `02`, `03`, `06`, `07`, `08` ผ่านทั้งหมด. เหลือ failures ใน `04_site_custom` (1), `05_terms` (1), `09_notifications` (2), `10_sidebar_reports` (1)
- หลัง E2E ตรวจการคืนฐานข้อมูล: users/tickets/ticket_logs กลับเป็น 0, migration และ synthetic baseline fixtures ยังคงอยู่; upload manifest SHA-256 ผ่าน; PHP test server หยุดแล้ว
- แก้ไข: `tests/e2e/run.sh`, `includes/mailer.php`, `admin/line_notify.php`, `docs/WORKLOG.md`; Phase 1 workflow implementation ไม่ได้แก้ไข. ไม่มีการเปลี่ยนฐานข้อมูลที่ใช้งานเดิม
- PHP lint และ runner shell syntax ผ่าน. ไม่มีการส่ง email/LINE จริง; migration และ E2E ใช้ isolated instance เท่านั้น

### Remaining / Gate

Phase 1 validation ยังไม่ผ่านครบเนื่องจาก 6 E2E assertions รวมถึง wizard-step assertion; Phase 2 ยังคง blocked. ต้องวิเคราะห์ failure ที่เหลือและทำให้ Phase 1 validation ผ่านก่อนขออนุมัติ Phase 2.

## Phase 1 — E2E Failure Resolution

### Root Causes and Fixes

- `01_core`: `yes_if` already prints success; the wizard assertion appended a second `echo 1`, making a correct four-step count fail. Removed the duplicate output without changing the assertion.
- `04_site_custom`: the shared staff navigation rendered only the default icon and never used the configured site logo. It now reuses `app_logo_url()` with the staff-relative prefix.
- `05_terms`: the test hard-coded a regular-term title, but the isolated fixture intentionally creates a current summer term. The assertion now checks the actual current term title from the fixture; report selection logic is unchanged.
- `09_notifications`: the existing staff notification service was not rendered by the staff navigation. The shared navigation now renders the existing `notif_bell()` once; notification generation and read semantics are unchanged.
- `10_sidebar_reports`: `fputcsv()` correctly quotes department names containing spaces. Replaced the raw line-prefix grep with CSV parsing and an exact department/count comparison; CSV output is unchanged.

### Files

- Modified: `includes/staff_nav.php`, `tests/e2e/run.sh`, `tests/e2e/01_core.sh`, `tests/e2e/05_terms.sh`, `tests/e2e/10_sidebar_reports.sh`, `docs/WORKLOG.md`.
- Database/schema changes: none for this resolution; the existing Phase 1 migration remains applied only to `reps_v2_db_test`.

### Validation and Safety

- Targeted E2E: `01_core` 110/0, `04_site_custom` 45/0, `05_terms` 20/0, `09_notifications` 20/0. `10_sidebar_reports` 22/0 when run after `01_core`, which creates the synthetic ticket data its report assertions require.
- Full E2E: all 10 suites, **356 passed, 0 failed, 0 skipped, 0 blocked**.
- PHP and MySQL CLI identities match (`reps_v2_db_test`, port `3307`, server id `1`). After restoration, users/tickets/logs/contact-history rows are all zero; six Phase 1 contact columns and `ticket_contact_history` remain present.
- Upload SHA-256 manifest matched, the test upload marker remained valid, and the test PHP server was stopped (`127.0.0.1:8765` returned no response). E2E continued to block outbound email and LINE.
- PHP lint, shell syntax checks, and `git diff --check` passed. This checkout has no commits and its project files are untracked, so Git cannot provide a baseline diff; the touched sections were reviewed directly.

Phase 1 validation is complete. Phase 2 was approved; the homepage Visual Builder foundation and the CMS page editor are implemented.

## Phase 2 — Visual Builder Foundation

### Direction

- The initial blank-page CMS composer did not match the requested workflow. The admin Visual Builder has been pivoted to edit the existing homepage in its original layout, with an admin-only iframe preview and selection by click or Layers.
- CMS page editing was approved as a separate admin flow on the existing `site_pages` / `site_blocks` tables, so the homepage Visual Builder stays focused on the existing homepage. The CMS editor is documented in the next section.

### Implemented

- Added click-to-select and Layers editing for allowlisted homepage text, logo, and footer fields; reuses existing site settings, `site_media`, and theme services.
- Added allowlisted section visibility, size presets, order, and background-color settings. Visibility for existing Steps/FAQ sections remains synchronized with their original settings.
- Stores localized text and homepage layout as validated JSON in existing `system_settings` rows and records edits through existing `site_revisions`. No user-provided HTML, CSS, or JavaScript is executed.
- Added desktop/tablet/mobile preview widths. The embedded edit mode requires an active admin session and blocks preview navigation and form submission.
- Fixed the preview gate to evaluate the already-authenticated homepage user; it was previously evaluated before `$currentUser` was populated, so admin preview mode stayed disabled.
- Firefox visual QA showed the canvas was below the Layers/Inspector stack on tablet and mobile. The responsive layout now places the existing-page canvas first below 820px, so the original page is visible before the editing panels.

### Files

- Modified: `index.php`, `includes/site_footer.php`, `docs/WORKLOG.md`.
- Added for this editor: `includes/visual_builder.php`, `admin/admin_visual_builder.php`, `assets/css/visual-home.css`, `assets/css/visual-studio.css`, `assets/js/visual-studio.js`, `tests/visual_builder.php`.
- CMS page files added separately: `page.php`, `admin/admin_pages.php`, `includes/visual_builder.php` (shared page helpers), `assets/css/visual-builder.css`, `assets/js/visual-builder.js`, `assets/js/cms-pages.js`.
- Database schema changes: none. Smoke validation wrote only a synthetic admin, homepage/theme settings, and four revisions to the isolated test DB.

### Validation and Safety

- `php tests/visual_builder.php`: passed (`Visual Builder schema and safe-render tests passed`).
- PHP lint passed for `index.php`, `includes/visual_builder.php`, `includes/site_footer.php`, `admin/admin_visual_builder.php`, and `tests/visual_builder.php`.
- `node --check assets/js/visual-studio.js` and `git diff --check` passed.
- Local HTTP smoke checks passed: unauthenticated admin access redirects to login, public preview has no edit mode, synthetic admin login enables edit mode, invalid CSRF returns HTTP 403, and text/layout/theme/default-logo saves reload successfully. Saved markup escapes HTML-like text.
- Firefox screenshots were reviewed at 1600x1000, 768x1024, and 390x844; the canvas and existing content fit without horizontal overflow at all three viewport widths.
- PHP and MySQL CLI identities matched `127.0.0.1:3307/reps_v2_db_test` before and after the smoke checks. Before testing, users/tickets/logs/revisions were empty; afterward, the test DB contains one synthetic admin, zero tickets/logs, zero CMS pages/blocks, and six expected revisions. `CHECK TABLE` returned OK for the affected tables.
- A private pre-test database backup and SHA-256 were verified. No restore was performed. The upload directory remained isolated and its SHA-256 manifest matched the baseline; no file upload occurred. The local PHP test server was stopped.
- The project `.env` targets `127.0.0.1:3306/reps_db` and contains real notification configuration; all smoke-test PHP requests instead received explicit isolated DB/upload settings, `REPS_E2E=1`, and disabled email/LINE credentials. No migration, E2E suite, or outbound notification was run.

### Remaining

- CMS pages are implemented as a new admin flow on `site_pages` / `site_blocks`; see the next section.
- Undo/redo, rollback, copy/paste, animation controls, and a complete set of editable homepage elements remain future work.
- Synthetic smoke-test data remains only in the isolated test DB; the verified pre-test backup is retained in session storage. No database restore was performed.

## Phase 2 — CMS Pages on `site_pages` / `site_blocks`

### Direction

- Approved direction: add a separate CMS renderer/editor on the existing `site_pages` / `site_blocks` tables while leaving the homepage Visual Builder unchanged.
- Draft and published block sets are stored separately (`site_blocks.status = 'draft' | 'published'`). The editor always writes the draft; publishing copies the draft set to the published set and sets `site_pages.status = 'published'`.
- Public visibility is controlled by `site_pages.status`. A draft is only viewable by an active admin through `page.php?slug=...&preview=1`.
- No user-supplied HTML, CSS, or JavaScript is accepted or executed. Every component is allowlisted and rendered server-side with escaped values.

### Implemented

- Added page helpers in `includes/visual_builder.php`: title/slug validation, slug uniqueness, page lookup by ID/slug, page listing with draft/published block counts, create, save draft, publish, unpublish, revert draft from published, delete, and revision history.
- Added guarded transitions: saving or publishing an empty page is rejected, and reverting requires existing published content.
- Added allowlisted components beyond heading/paragraph/button/image: `list` (bullet or numbered, 1–30 items), `quote`, `callout` (info/success/warning), `divider`, and `spacer` (small/medium/large). Existing component field contracts were not changed, so previously stored blocks remain valid.
- Added `admin/admin_pages.php`: page list, create form, block editor, draft save, save-and-publish, unpublish, revert draft, delete, revision history, and a responsive draft-preview panel (desktop/tablet/mobile widths).
- Reused the existing `assets/js/visual-builder.js` editor contract and `.vb-*` styles; added `assets/js/cms-pages.js` for preview widths and destructive-action confirmations.
- Upgraded `page.php` to render published pages from `site_pages` / `site_blocks`, return 404 for unknown or unpublished slugs, and allow admin-only draft preview with a visible banner and `noindex, nofollow`. Public requests do not start a session.
- Registered “หน้าเว็บ (CMS)” in `includes/site_menu.php` and `admin/admin_header.php`, and cross-linked it from `admin/admin_visual_builder.php`.
- Fixed the incomplete tail of `admin/admin_visual_builder.php` (missing wrapper close, footer, and closing document tags).
- Extended `site_media_usage()` so images referenced by CMS image blocks are reported as “หน้าเว็บ” and protected from deletion.
- Added validation/safe-render tests for slug and title rules, list, quote, callout, divider, spacer, and invalid option rejection.

### Files

- Added: `admin/admin_pages.php`, `assets/js/cms-pages.js`.
- Modified: `page.php`, `includes/visual_builder.php`, `includes/site_menu.php`, `includes/site_custom.php`, `admin/admin_header.php`, `admin/admin_visual_builder.php`, `assets/js/visual-builder.js`, `assets/css/visual-builder.css`, `tests/visual_builder.php`, `docs/WORKLOG.md`.
- Database schema changes: none. The flow uses the existing `site_pages`, `site_blocks`, `site_revisions`, and `site_media` tables.

### Validation and Safety

- `php tests/visual_builder.php`: passed (`Visual Builder schema and safe-render tests passed`).
- PHP lint passed for every project PHP file (`find . -name '*.php' ... | xargs php -l`).
- `node --check assets/js/visual-builder.js`, `node --check assets/js/cms-pages.js`, and `git diff --check` passed.
- An isolated MariaDB instance (`127.0.0.1:3307`, database `reps_cms_test`) was created from `reps_v2_db.sql` and used with an isolated PHP built-in server (`127.0.0.1:8765`) and a synthetic admin. The project `.env`/production database was not used.
- HTTP smoke checks passed: admin login with CSRF, page list and editor render without PHP warnings, create page, save draft containing eight of the nine component types (all except `image`, which needs a media-library file; image validation and media usage were tested separately), draft 404 for the public, unauthenticated preview redirects to login, admin preview renders the draft with its banner, publish makes the page public, unpublish hides it again, revert copies published content back to draft, missing CSRF returns 403, an invalid block type returns a validation error, a duplicate slug is rejected, and delete removes the page and its blocks while retaining revision history.
- HTML tag-balance checks were clean for the admin list, editor, draft preview, published page, and Visual Builder responses.
- The homepage Visual Builder still renders in edit mode and the public homepage still returns 200 after the changes.
- `site_media_usage()` returned `หน้าเว็บ` for an image referenced by a CMS image block, confirming deletion protection.
- No outbound email, LINE notification, migration, or E2E suite was run.

### Remaining

- Browser-based visual QA for the new CMS editor (Firefox at desktop/tablet/mobile) has not been performed in this round; the responsive layout follows the already-QA’d Visual Builder pattern (preview panel first below 820px).
- Production schema verification for `site_pages` / `site_blocks` / `site_revisions` / `site_media` remains part of the open Phase 0 migration check.
- Revision history is visible but there is no restore-from-revision UI yet.
- `site_media_usage()` scans all image blocks; acceptable for a small CMS, but a dedicated reference table would scale better.
- Nothing has been committed or pushed. The new CMS files remain untracked and the modified files are unstaged.

## Phase 3 — Advanced Visual Builder (implementation in progress)

### Existing Features Reused

- Reused the `VISUAL_BUILDER_COMPONENTS` allowlist, `visual_builder_validate_config()`, `visual_builder_render_blocks()`, and the existing draft/published/revision storage in `site_pages`, `site_blocks`, and `site_revisions`.
- Reused admin authentication and the existing CSRF-protected CMS POST flow. No raw user HTML, CSS, or JavaScript is interpreted.

### Implemented in this increment

- Added a live CMS canvas that renders the current unsaved component fields with DOM APIs and `textContent`; image IDs and link destinations are constrained before preview.
- Added native drag-and-drop ordering alongside the existing move buttons, bounded undo/redo history (100 states), keyboard shortcuts, and in-memory block copy/paste.
- Connected the existing animation preset list to the CMS component registry, field controls, server validation, and safe renderer. Older component JSON without an animation remains valid and defaults to `none`.
- Added CSS animations scoped to CMS pages and the editor canvas, with `prefers-reduced-motion` support.
- Added restore-to-draft actions for page revisions. The server scopes the lookup to the current page, revalidates title/slug/component schema, writes only to the draft, and records the restore as a new revision.
- Added homepage undo/redo, in-memory copy/paste, keyboard shortcuts, and bounded preview history for text, logo, section layout, and theme edits.
- Added admin-only preview and allowlisted text targets for Login, Register, requester dashboard, staff dashboard, and admin dashboard. These targets edit display text only; form behavior, dashboard actions, and ticket logic stay outside the Visual Builder.
- Added page-scoped text revisions and validated restore actions for those screens. Homepage revision history now includes safe restore for text, layout, theme, and logo snapshots, with every restore recorded as a new revision.
- Kept shared navigation and footer editing on the existing site customization system. The homepage preview exposes its navigation and footer text targets through the Visual Builder; menu structure and role visibility continue to use the existing menu editor.
- Added locale-aware fallback labels to the new dashboard targets and kept page `lang` attributes aligned with the active REPS locale.
- Homepage section copy/paste now updates the associated divider visibility in the live canvas.

### Files

- Modified: `admin/admin_pages.php`, `admin/admin_visual_builder.php`, `admin/admin_dashboard.php`, `assets/css/visual-builder.css`, `assets/css/visual-studio.css`, `assets/js/visual-builder.js`, `assets/js/visual-studio.js`, `dashboard.php`, `includes/visual_builder.php`, `login.php`, `register.php`, `staff/staff_dashboard.php`, `tests/visual_builder.php`, `docs/WORKLOG.md`.
- New project files: none.
- Database changes: none. The integration check used an isolated temporary MariaDB database with only the existing CMS table shapes; no project or configured database was modified.

### Security and Compatibility

- Component animation names are selected from the server allowlist; renderer output only emits a validated `data-vb-animation` value.
- Revision restore rejects a revision belonging to another page and never overwrites published blocks. Historical snapshots are revalidated before saving.
- Existing CMS blocks without animation metadata are normalized to `none`; no migration is required.

### Test Results

- `php tests/visual_builder.php`: passed, including safe rendering, legacy configuration normalization, supported/unsupported animation presets, posted block validation, and allowlisted localized page text.
- PHP lint for all project PHP files excluding `vendor/`: passed.
- `node --check` for `visual-builder.js`, `visual-studio.js`, and `cms-pages.js`: passed.
- `git diff --check`: passed.
- Earlier isolated MariaDB integration: passed for CMS revision restore into draft, preserving a new revision record, and rejecting a cross-page restore.
- Ran Phase 3 HTTP/database integration against a fresh isolated MariaDB schema made from DDL only (30 table definitions and 74 index/constraint statements); inserted only synthetic test users and settings. The configured project database and SQL dump data were not used.
- Passed 34 HTTP assertions covering admin-only Visual Builder access, all five authenticated page previews and target metadata, CSRF rejection, localized homepage/page text saves, page and homepage revision restores, escaped HTML-like text, CMS page creation, safe animation rendering, published/draft separation, and cross-page revision rejection.
- Passed 15 more HTTP/database assertions for save, revision restore, and preview output on the requester, staff, and admin dashboard targets.
- Firefox 160 headless screenshots were reviewed at desktop (1365×900), tablet (768×1024), and mobile (390×844). This found a toolbar wrapping issue; the toolbar now wraps its controls and keeps its description on a separate line. Interactive drag/drop and undo/redo were not automated because no browser automation driver was available.
- No project database migration or outbound email/LINE notification was run.

### Remaining / Phase Gate

- Phase 3 HTTP/database verification and desktop/tablet/mobile visual review are complete. Shared menu structure and footer links continue to use the existing admin editors; homepage navigation/footer text is editable in the Visual Builder.
- Remaining test limitation: pointer-driven drag/drop, undo/redo, and copy/paste were not exercised in a browser automation run; those paths have static/unit checks and the surrounding UI was visually reviewed.
- Phase 4 was not started during Phase 3 testing.

## Phase 4 — CMS & Form Builder (complete)

### Audit Findings

- Reuse `site_pages`, `site_blocks`, `site_revisions`, `VISUAL_BUILDER_COMPONENTS`, `visual_builder_validate_config()`, `visual_builder_render_blocks()`, admin authentication, and CSRF-protected CMS actions.
- The CMS currently supports safe content components and page draft/publish/revision flows. It has no form component, form schema editor, submission storage, or submission review UI.
- `site_pages`, `site_blocks`, `site_revisions`, `site_media`, and `email_templates` already exist in the schema dump. No `site_forms` or `site_form_submissions` table and no form-builder migration exist.
- Registration and repair-request forms are fixed PHP forms. Their validation, account creation, ticket submission, and workflow remain unchanged by this phase.
- Reuse `db_transaction()` / prepared-statement helpers, `site_revision_save()`, existing admin RBAC, and the session CSRF service. New content must stay within an allowlisted schema and escaped server renderer.

### Planned Scope

- Add standalone forms that can be embedded in CMS pages, with allowlisted text/email/telephone/textarea/select/checkbox fields. No raw HTML, JavaScript, file uploads, custom endpoints, or workflow mutation.
- Store form draft/published snapshots and submission records additively; retain form schema revisions in `site_revisions` and expose submissions only to admins.
- Accept submissions only through a fixed page handler with session CSRF, schema-based validation, a honeypot, bounded values, and escaped output.
- Add an additive migration after confirming the relevant existing columns, keys, and foreign-key types. No existing table or migration will be renamed or removed.

### Implemented

- Added the `form` component to the Visual Builder component allowlist. Page drafts can select a CMS form; page publish validates that every visible embedded form is published. Existing page and block storage is unchanged.
- Added an admin-only Form Builder for form drafts, publishing/unpublishing, field ordering/add/remove, revision history and draft restore, plus viewing and deleting individual submissions. It reuses admin auth, session CSRF, prepared statements, and `site_revisions`.
- Added allowlisted text, email, telephone, textarea, select, and checkbox fields. Definitions reject unknown properties/types, duplicate keys, invalid options, oversized values, and malformed keys. Forms render only from the validated schema; labels, options, responses, and messages are escaped.
- Added a fixed handler to `page.php` for embedded public forms. It requires a published form embedded in that published page, session CSRF, schema validation, a honeypot, a 5-per-hour session limit, bounded answers, and POST/redirect/GET. Draft preview renders the form disabled and rejects submissions.
- Form submissions store a SHA-256 session identifier rather than the raw PHP session ID. Admin submission access is protected by the existing admin route. Unpublishing a form is blocked while a visible published CMS page references it.
- Registration and repair-request flows, authentication, and ticket behavior remain unchanged.
- Added the additive `database/migrations/20261122_reps4_visual_forms.sql` for `site_forms` and `site_form_submissions`. The migration was applied only to a temporary isolated test database; no configured or production database was changed.

### Files

- Modified: `admin/admin_header.php`, `admin/admin_pages.php`, `assets/css/visual-builder.css`, `assets/js/visual-builder.js`, `docs/WORKLOG.md`, `includes/site_menu.php`, `includes/visual_builder.php`, `page.php`, `tests/visual_builder.php`.
- New: `admin/admin_forms.php`, `assets/css/form-builder.css`, `assets/js/form-builder.js`, `database/migrations/20261122_reps4_visual_forms.sql`, `includes/form_builder.php`.
- Database changes: additive migration creates only `site_forms` and `site_form_submissions`, with keys and foreign keys to the existing `users` and new forms tables. Migration history in this project has no central runner/table; deployment must apply this SQL migration before enabling the form features.

### Security and Compatibility

- No user-provided HTML, CSS, JavaScript, upload, or arbitrary endpoint is accepted or executed. Component data is schema-validated and rendered by fixed PHP code.
- CSRF, active-admin RBAC, prepared statements, output escaping, field/value bounds, honeypot, and session-based rate limiting are applied at the corresponding boundaries.
- Existing calls to `visual_builder_render_blocks()` remain compatible through optional arguments. Existing page CMS tables and workflows are not altered.
- Form submissions can contain personal information. They are visible to admins, kept until an admin deletes them, and no automatic retention policy was part of this phase.

### Test Results

- `php tests/visual_builder.php`: passed, including form schema allowlist, duplicate-key and unsupported-type rejection, email/select answer validation, CSRF field rendering, and escaping checks.
- PHP lint passed for `includes/form_builder.php`, `includes/visual_builder.php`, `page.php`, `admin/admin_forms.php`, `admin/admin_pages.php`, and `tests/visual_builder.php`.
- `node --check` passed for `assets/js/form-builder.js` and `assets/js/visual-builder.js`; `git diff --check` passed for the Phase 4 files.
- Applied the migration to an isolated MariaDB 12.3 database populated from DDL only (no SQL dump records). Verified both new table definitions and foreign keys, synthetic form/submission persistence, session rate limiting at the fifth submission, and the unpublish guard against an actually published CMS page. Synthetic rows were removed afterward.
- Ran the Form Builder and public CMS page through a local PHP server backed only by the isolated test database. Both pages returned successfully; a valid synthetic form submission completed POST/redirect/GET and rendered its success message. Synthetic admin, page, form, submission, and DB account were removed afterward.
- Firefox desktop visual QA of the Form Builder found labels and controls sharing one line and overlapping. Scoped `.vb-field` styles now stack labels above controls; the refreshed desktop screenshot was reviewed and the overlap is resolved. Mobile layout and pointer interaction of add/move/remove controls were not browser-automated.
- No production migration was applied and no outbound notification was sent.

### Remaining / Phase Gate

- Phase 4 implementation, isolated migration checks, public submission HTTP check, and desktop editor visual review are complete. Before deployment, apply the additive migration and perform mobile browser QA plus interactive field editing and admin response review.
- Phase 5 has not started. Proceed only when explicitly requested, per the phase gate.

### Visual Builder follow-up — requester dashboard UI

- The requester dashboard UI now includes the registered Visual Builder text targets for its greeting, introduction, ticket heading, and ticket introduction. It loads the existing page text settings and remains tied to `dashboard.php`, including its requester navigation partial.
- Admin preview requests may view the requester dashboard through the existing Visual Builder preview query, while other roles receive HTTP 403. Normal dashboard requests continue to require the requester role. Staff dashboard preview is restricted to admins and now renders the staff UI shell rather than the admin shell; the normal admin staff view remains unchanged. Added a visible role label target alongside the queue/workflow targets.
- Verified PHP lint for requester/staff dashboards and `php tests/visual_builder.php`; all passed. Browser preview was not run for this follow-up.

## Phase 5 — 3D Authentication (in progress)

### Audit Findings

- The project already vendors Three.js at `assets/vendor/three/three.min.js` and uses it on the homepage through the separate `assets/js/home-3d.js` scene.
- Login and Register were not loading a Three.js scene. They already share `assets/css/auth.css`, while their authentication handlers, CSRF fields, login throttling, and registration handler remain in their existing PHP flows.
- No database changes are needed for a decorative auth background.

### Implemented

- Added one shared scene loader/engine for Login and Register. The loader waits for the background to intersect the viewport before downloading the vendored Three.js runtime and scene script.
- Redesigned the auth background as a slowly moving wave of square pixel tiles in soft gray-blue, over a white surface with thin dashed rails. A CSS pixel-grid fallback appears when WebGL is unavailable. The scene does not receive pointer events and is marked `aria-hidden`; it does not wrap or replace either auth form.
- Added responsive sizing and capped pixel ratio; the scene pauses when offscreen or the document is hidden, and disables continuous animation for `prefers-reduced-motion`.
- Added a static CSS rails fallback and a renderer error/context-loss fallback. If reduced motion is enabled, the Three.js scripts are not loaded and the static index-style background remains.
- Kept login verification, registration, CSRF, throttling, and redirect logic unchanged.

### Files

- Modified: `assets/css/auth.css`, `docs/WORKLOG.md`, `login.php`, `register.php`.
- New: `assets/js/auth-3d-loader.js`, `assets/js/auth-3d.js`.
- Database changes: none.

### Test Results

- `php -l login.php` and `php -l register.php`: passed.
- `node --check assets/js/auth-3d-loader.js` and `node --check assets/js/auth-3d.js`: passed.
- An earlier synthetic headless preview reported the scene-ready state, but its screenshot did not clearly show the geometry. The Phase 6 isolated Firefox preview fetched the loader, Three.js, and scene script, but still did not confirm visible pixel rendering.
- No authentication POST, registration POST, or project database was used or changed.

### Remaining / Phase Gate

- Phase 5 implementation is in progress. Confirm visible WebGL output and reduced-motion / WebGL-unavailable fallback in a browser capable of rendering WebGL before marking this phase complete.
- Phase 6 is now in progress; Phase 5 browser rendering and fallback confirmation remain open.

## Phase 6 — Integration & Production Readiness (in progress)

### Audit Findings

- Reused integration points remain the existing ticket/workflow/signature services, permission and CSRF helpers, the safe Visual Builder/CMS renderer, `site_revisions`, the additive form tables, and the shared lazy-loaded auth scene. No replacement auth, ticket, signature, permission, or theme subsystem was introduced.
- The checkout contains only `20261122_reps4_contact_snapshots.sql` and `20261122_reps4_visual_forms.sql` under `database/migrations/`. The historical migration sequence documented above is not present here, so the current production schema and its migration state cannot be reconstructed from this checkout alone.
- The E2E runner is guarded to require the isolated `127.0.0.1:3307/reps_v2_db_test` database, test account, and marked upload directory. Those test-environment variables are unset in this session; no database or upload directory was touched.
- The Phase 4 mobile editor/form interaction QA and Phase 5 visible WebGL/reduced-motion/fallback browser confirmation remain open. A database-free Firefox headless preview fetched the Three.js and scene scripts, but its screenshot did not show the pixel canvas; this is inconclusive visual evidence and does not close Phase 5 QA.

### Implemented

- The initial Phase 6 pass made no application or database changes because no isolated E2E database or verified production schema was available.
- Follow-up integration fix: added desktop requester navigation links for “My requests” and “History” to the existing requester top bar. Mobile drawer/bottom navigation and the existing new-request action remain the same.
- Removed the requester drawer's `rq-me` profile card and its dedicated CSS/data variables; account access continues through the existing account menu.
- Added a shared, requester-scoped pink accent theme for primary actions, active dashboard filters, request form focus/current step, history hover, and profile controls. The body class is set only on requester pages (including the requester dashboard preview); staff/admin pages keep their existing colors.
- Corrected the requester top-bar “New request” action, whose local navigation CSS still set a black background and overrode the shared pink theme.
- Scoped the Request Form's pink primary button override to `#nextStep` and the submit button inside `#requestForm`, since the page shares `.admin-btn` with other roles and modules. The secondary/back buttons and unrelated `.admin-btn` controls are not targeted by this form-specific rule.

### Files

- Modified: `dashboard.php`, `docs/WORKLOG.md`, `history.php`, `includes/requester_nav.php`, `profile.php`, `request_form.php`.
- New: `assets/css/requester-theme.css`.
- Database changes: none; neither configured nor production database was accessed.

### Security and Compatibility

- No live authentication flow, permission policy, ticket data, CMS content, or database schema was changed.
- The E2E runner's isolated-target and upload-root guards were reviewed; the suite was not run because its required isolated environment is absent.
- The static auth preview used a temporary PHP server and did not include `db_connect.php` or any authentication handler. The server was stopped after review.

### Test Results

- `php tests/visual_builder.php`: passed.
- `php -l` for every project PHP file outside `vendor/`: passed.
- `bash -n` for all `tests/e2e/*.sh` and `node --check` for the Phase 3–5 Visual Builder/CMS/auth scripts: passed.
- Firefox headless fetched the auth loader, vendored Three.js, and `auth-3d.js`; screenshot output did not confirm visible pixel rendering. Renderer readiness, reduced-motion behavior, and WebGL fallback remain unverified in a browser session with usable WebGL and diagnostics.
- The requester navigation issue was traced to the desktop breakpoint hiding the drawer and there being no desktop inline links; the desktop links were added. No browser regression run was made for this small CSS/PHP-template change.
- E2E HTTP/database suites, real mobile interaction review, production schema comparison, and migration execution were not run.

### Remaining / Phase Gate

- Phase 6 remains in progress. Run the guarded E2E suite and mobile/editor/form interaction QA in the isolated test environment; confirm the auth scene and both fallback modes with a browser that can expose renderer state.
- Before deployment, compare the actual target schema and applied migration state against both additive REPS 4.0 migrations and the existing ticket/CMS tables. Do not apply migrations to production from this checkout until that comparison and an explicit deployment plan are available; the legacy migration files are absent here.
- No production deployment or production migration was performed.
