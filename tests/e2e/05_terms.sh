#!/bin/bash
# ภาคเรียนและทีมสตาฟ: สิทธิ์, ทีมไม่เกินจำนวนสูงสุด, ช่วงวันที่ห้ามซ้อน, สตาฟนอกทีมรับงาน/รับคิวไม่ได้, คะแนนเริ่มใหม่ทุกภาคเรียน
B="${REPS_BASE_URL:-http://127.0.0.1:8765}"
S="$1"; M="${MYSQL:-mysql}"
PW='Test1234!'
q(){ $M ${MYSQL_ARGS:--uroot} -N "${DB_NAME:-reps_v2_db}" -e "$1"; }
pass=0; fail=0
ok(){ if [ "$1" = "1" ]; then echo "  PASS $2"; pass=$((pass+1)); else echo "  FAIL $2"; fail=$((fail+1)); fi; }
csrf(){ grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ local jar="$S/$1.jar"; rm -f "$jar"; local t; t=$(curl -s -c "$jar" -b "$jar" $B/login.php | csrf); curl -s -c "$jar" -b "$jar" -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf=$t" --data-urlencode "username=$1" --data-urlencode "password=$2" $B/login.php; }
get(){ curl -s -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
yes_if(){ if eval "$1"; then echo 1; fi; }
termpost(){ local t; t=$(get t_admin "admin/admin_terms.php?new=1" | csrf); [ -n "$t" ] || echo "  (no csrf token)" >&2; curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$t" "$@" "$B/admin/admin_terms.php"; }

for u in t_admin t_staff t_staff2 t_user; do login $u "$PW" >/dev/null; done
TERM=$(q "SELECT id FROM academic_terms WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1")
UID_S=$(q "SELECT user_id FROM users WHERE id_number='t_staff'"); UID_S2=$(q "SELECT user_id FROM users WHERE id_number='t_staff2'")
UID_U=$(q "SELECT user_id FROM users WHERE id_number='t_user'")
CAT=$(q "SELECT id FROM categories ORDER BY id LIMIT 1")
newticket(){ q "INSERT INTO tickets (ticket_no, requester_id, category_id, location_note, contact_person, symptom, urgency, status, created_at) VALUES (CONCAT('TT-', FLOOR(RAND()*10000000)), $UID_U, $CAT, 'ห้องทดสอบภาคเรียน', 'x', 'ทดสอบ', 'normal', 'pending', NOW())"; q "SELECT MAX(id) FROM tickets WHERE requester_id=$UID_U"; }

echo "== A. สิทธิ์ =="
CODE=$(curl -s -b "$S/t_staff.jar" -o /dev/null -w "%{http_code}" "$B/admin/admin_terms.php")
ok "$(yes_if '[ "$CODE" = "302" ] || [ "$CODE" = "303" ]')" "สตาฟเปิดหน้าภาคเรียนไม่ได้ ($CODE)"
ok "$(yes_if 'get t_admin admin/admin_terms.php | grep -q "ภาคเรียนปัจจุบัน"')" "หน้าภาคเรียนแสดงภาคเรียนปัจจุบัน"

echo "== B. ตรวจค่าภาคเรียน =="
N0=$(q "SELECT COUNT(*) FROM academic_terms")
termpost -d action=save_term -d year_be=2569 -d term_no=3 -d start_date="$(date +%Y-%m-%d)" -d end_date="$(date -d '+5 day' +%Y-%m-%d 2>/dev/null || date +%Y-%m-%d)" -d max_staff=4 >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM academic_terms")" = "$N0" ]')" "ช่วงวันที่ซ้อนกับภาคเรียนเดิม ถูกปฏิเสธ"
termpost -d action=save_term -d year_be=2599 -d term_no=1 -d start_date=2056-05-10 -d end_date=2056-05-01 -d max_staff=4 >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM academic_terms")" = "$N0" ]')" "วันสิ้นสุดก่อนวันเริ่ม ถูกปฏิเสธ"
termpost -d action=save_term -d year_be=2599 -d term_no=1 -d start_date=2056-05-01 -d end_date=2056-10-31 -d max_staff=2 >/dev/null
FT=$(q "SELECT id FROM academic_terms WHERE year_be=2599 AND term_no=1")
ok "$(yes_if '[ -n "$FT" ]')" "สร้างภาคเรียนใหม่ได้"
termpost -d action=save_term -d year_be=2599 -d term_no=1 -d start_date=2056-11-01 -d end_date=2057-03-31 -d max_staff=4 >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM academic_terms WHERE year_be=2599 AND term_no=1")" = "1" ]')" "ภาคเรียน/ปีซ้ำ ถูกปฏิเสธ"
STAFF3=$(q "SELECT user_id FROM users WHERE role='staff' AND status='active' AND id_number NOT IN ('t_staff','t_staff2') LIMIT 1")
termpost -d action=save_roster -d term_id=$FT -d "staff[]=$UID_S" -d "staff[]=$UID_S2" -d "staff[]=$STAFF3" >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM term_staff WHERE term_id=$FT")" = "0" ]')" "เลือกทีมเกินจำนวนสูงสุด ถูกปฏิเสธ"
termpost -d action=save_roster -d term_id=$FT -d "staff[]=$UID_U" >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM term_staff WHERE term_id=$FT")" = "0" ]')" "ผู้แจ้ง (ไม่ใช่สตาฟ) ใส่ในทีมไม่ได้"
termpost -d action=delete_term -d term_id=$FT -d confirm=ผิด >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM academic_terms WHERE id=$FT")" = "1" ]')" "ลบภาคเรียนต้องพิมพ์ยืนยันให้ตรง"
termpost -d action=delete_term -d term_id=$FT -d confirm=1/2599 >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM academic_terms WHERE id=$FT")" = "0" ]')" "ลบภาคเรียนเมื่อยืนยันถูกต้อง"

echo "== C. ทีมภาคเรียนปัจจุบัน =="
termpost -d action=save_roster -d term_id=$TERM -d "staff[]=$UID_S" >/dev/null
ok "$(yes_if '[ "$(q "SELECT GROUP_CONCAT(user_id) FROM term_staff WHERE term_id=$TERM")" = "$UID_S" ]')" "บันทึกทีมภาคเรียนปัจจุบัน (เหลือ t_staff คนเดียว)"
ok "$(yes_if '[ "$(q "SELECT is_active_queue FROM users WHERE user_id=$UID_S2")" = "0" ]')" "สตาฟนอกทีมถูกปิดคิวอัตโนมัติ"
TID=$(newticket)
T=$(get t_staff2 staff/staff_dashboard.php | csrf)
curl -s -b "$S/t_staff2.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=accept_ticket -d ticket_id=$TID $B/staff/staff_dashboard.php
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TID")" = "pending" ]')" "สตาฟนอกทีมรับงานไม่ได้"
ok "$(yes_if 'get t_staff2 staff/staff_dashboard.php | grep -q "ไม่ได้อยู่ในทีมสตาฟของ"')" "สตาฟนอกทีมเห็นคำแจ้งเตือน"
q "UPDATE users SET is_active_queue=1 WHERE user_id=$UID_S2"
ok "$(yes_if '! get t_admin admin/admin_war_room.php | grep -q "T-t_staff2"')" "ภาพรวมงานไม่แสดงสตาฟนอกทีม"
T=$(get t_staff staff/staff_dashboard.php | csrf)
curl -s -b "$S/t_staff.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=accept_ticket -d ticket_id=$TID $B/staff/staff_dashboard.php
ok "$(yes_if '[ "$(q "SELECT assigned_to FROM tickets WHERE id=$TID")" = "$UID_S" ]')" "สตาฟในทีมรับงานได้"

echo "== D. คะแนนเริ่มใหม่ทุกภาคเรียน =="
q "INSERT INTO score_logs (user_id, points_change, reason, created_at) VALUES ($UID_S, -40, 'ทดสอบ: ภาคเรียนก่อน', (SELECT DATE_SUB(start_date, INTERVAL 10 DAY) FROM academic_terms WHERE id=$TERM))"
q "INSERT INTO score_logs (user_id, points_change, reason, created_at) VALUES ($UID_S, -7, 'ทดสอบ: ภาคเรียนนี้', NOW())"
EXPECT=$(q "SELECT LEAST(100, GREATEST(0, 100 + COALESCE(SUM(points_change),0))) FROM score_logs s JOIN academic_terms a ON a.id=$TERM WHERE s.user_id=$UID_S AND s.created_at BETWEEN a.start_date AND CONCAT(a.end_date,' 23:59:59')")
ok "$(yes_if 'get t_staff staff/staff_dashboard.php | grep -q "คะแนนภาคเรียนนี้ $EXPECT คะแนน"')" "คะแนนนับเฉพาะภาคเรียนนี้ ($EXPECT)"
ok "$(yes_if '! get t_staff staff/staff_dashboard.php | grep -q "ทดสอบ: ภาคเรียนก่อน"')" "ประวัติคะแนนไม่แสดงของภาคเรียนก่อน"

echo "== E. รายงานตามภาคเรียน =="
EXPECTED_TERM_TITLE=$(q "SELECT CONCAT(CASE WHEN term_no=3 THEN 'ภาคฤดูร้อน ' ELSE 'ภาคเรียนที่ ' END, term_no, '/', year_be) FROM academic_terms WHERE id=$TERM")
REPORT=$(get t_admin admin/admin_teacher_report.php)
ok "$(yes_if 'printf "%s" "$REPORT" | grep -Fq "รายงานสรุป$EXPECTED_TERM_TITLE"')" "รายงานเริ่มที่ภาคเรียนปัจจุบัน ($EXPECTED_TERM_TITLE)"
ok "$(yes_if 'get t_admin "admin/admin_teacher_report.php?term=all" | grep -q "รายงานสรุปปีการศึกษา"')" "เลือกดูทั้งปีการศึกษาได้"

echo "RESULT: pass=$pass fail=$fail"
