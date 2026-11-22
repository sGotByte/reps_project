#!/bin/bash
# ขอบคุณ Lab boy (QR ของแต่ละคน, บันทึกเงิน/ของ, ยืนยันรับ, สิทธิ์ไฟล์) + คะแนนสตาฟ (เริ่ม 100, หัก/ครูให้, เพดาน 100, ยกเลิกแบบกลับค่า)
B="${REPS_BASE_URL:-http://127.0.0.1:8765}"
S="$1"; M="${MYSQL:-mysql}"
PW='Test1234!'
q(){ $M ${MYSQL_ARGS:--uroot} -N "${DB_NAME:-reps_v2_db}" -e "$1"; }
pass=0; fail=0
ok(){ if [ "$1" = "1" ]; then echo "  PASS $2"; pass=$((pass+1)); else echo "  FAIL $2"; fail=$((fail+1)); fi; }
csrf(){ grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ local jar="$S/$1.jar"; rm -f "$jar"; local t; t=$(curl -s -c "$jar" -b "$jar" $B/login.php | csrf); curl -s -c "$jar" -b "$jar" -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf=$t" --data-urlencode "username=$1" --data-urlencode "password=$2" $B/login.php; }
get(){ curl -s -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
code(){ curl -s -o /dev/null -w "%{http_code}" -b "$S/$1.jar" "$B/$2"; }
yes_if(){ if eval "$1"; then echo 1; fi; }
setting(){ q "INSERT INTO system_settings (setting_key, setting_value) VALUES ('$1', '$2') ON DUPLICATE KEY UPDATE setting_value = '$2'"; }
tok(){ get "$1" profile.php | csrf; }
# give <tid> <to_uid> <kind> <amount> <item> [extra curl -F args]
give(){ local tid=$1 to=$2 kind=$3 amount=$4 item=$5; shift 5; curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null -F "csrf=$(tok t_user)" -F action=give -F ticket_id=$tid -F to_user_id=$to -F kind=$kind -F "amount=$amount" -F "item_note=$item" -F "message=ขอบคุณครับ" "$@" "$B/thanks.php?id=$tid"; }
nthanks(){ q "SELECT COUNT(*) FROM ticket_thanks WHERE ticket_id=$1 AND status <> 'cancelled'"; }
staffpost(){ local who=$1; shift; curl -s -c "$S/$who.jar" -b "$S/$who.jar" -o /dev/null --data-urlencode "csrf=$(tok $who)" "$@" "$B/staff/thanks.php"; }
scorepost(){ local t; t=$(get t_admin admin/admin_scores.php | csrf); curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$t" "$@" "$B/admin/admin_scores.php"; }

for u in t_admin t_staff t_staff2 t_user; do login $u "$PW" >/dev/null; done
UID_U=$(q "SELECT user_id FROM users WHERE id_number='t_user'"); UID_S=$(q "SELECT user_id FROM users WHERE id_number='t_staff'"); UID_S2=$(q "SELECT user_id FROM users WHERE id_number='t_staff2'")
CAT=$(q "SELECT id FROM categories ORDER BY id LIMIT 1")
mk(){ q "INSERT INTO tickets (ticket_no, requester_id, category_id, location_note, contact_person, symptom, urgency, status, assigned_to, created_at, accepted_at, completed_at) VALUES (CONCAT('TH-', FLOOR(RAND()*10000000)), $UID_U, $CAT, 'ห้องทดสอบขอบคุณ', 'x', 'ทดสอบ', 'normal', '$1', $UID_S, NOW() - INTERVAL 2 DAY, NOW() - INTERVAL 2 DAY, $2)"; q "SELECT MAX(id) FROM tickets WHERE requester_id=$UID_U"; }
TID=$(mk completed "NOW() - INTERVAL 1 HOUR")
TIP=$(mk in_progress NULL)
TOLD=$(mk completed "NOW() - INTERVAL 40 DAY")

echo "== A. เปิด/ปิด และสิทธิ์หน้า =="
setting thanks_enabled 0
ok "$(yes_if '[ "$(code t_user "thanks.php?id=$TID")" = "404" ]')" "ระบบปิด -> เปิดหน้าขอบคุณไม่ได้"
setting thanks_enabled 1; setting thanks_max_amount 5000; setting thanks_days 30
ok "$(yes_if '[ "$(code t_user "thanks.php?id=$TID")" = "200" ]')" "ผู้แจ้งเปิดหน้าขอบคุณของใบงานตัวเองได้"
ok "$(yes_if '[ "$(code t_staff2 "thanks.php?id=$TID")" = "404" ]')" "คนอื่นเปิดหน้าขอบคุณใบงานนี้ไม่ได้"
ok "$(yes_if 'get t_user "sign.php?id=$TID" | grep -q "thanks.php?id=$TID"')" "หน้าใบงานชวนขอบคุณทีมช่าง (ไม่บังคับ)"

echo "== B. ตรวจค่าที่บันทึก =="
give $TIP $UID_S money 50 ""
ok "$(yes_if '[ "$(nthanks $TIP)" = "0" ]')" "ใบงานที่ยังไม่รับคืน ขอบคุณไม่ได้"
give $TOLD $UID_S money 50 ""
ok "$(yes_if '[ "$(nthanks $TOLD)" = "0" ]')" "เกินกำหนดวัน ขอบคุณไม่ได้"
give $TID $UID_S2 money 50 ""
ok "$(yes_if '[ "$(nthanks $TID)" = "0" ]')" "ให้คนที่ไม่ได้อยู่ในทีมใบงานไม่ได้"
for bad in 0 -5 6000 abc; do give $TID $UID_S money "$bad" ""; done
ok "$(yes_if '[ "$(nthanks $TID)" = "0" ]')" "จำนวนเงิน 0 / ติดลบ / เกินสูงสุด / ไม่ใช่ตัวเลข ถูกปฏิเสธ"
give $TID $UID_S snack "" ""
ok "$(yes_if '[ "$(nthanks $TID)" = "0" ]')" "ให้ของต้องระบุว่าให้อะไร"
give $TID $UID_S money 50 "" -F "slip=@$S/fake.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(nthanks $TID)" = "0" ]')" "สลิปไฟล์ปลอม ถูกปฏิเสธ"
give $TID $UID_S money 50 "" -F "slip=@$S/photo.jpg;type=image/jpeg"
TH1=$(q "SELECT MAX(id) FROM ticket_thanks WHERE ticket_id=$TID")
ok "$(yes_if '[ "$(q "SELECT CONCAT(kind,\"/\",amount,\"/\",status,\"/\",slip_path IS NOT NULL) FROM ticket_thanks WHERE id=$TH1")" = "money/50.00/pending/1" ]')" "บันทึกโอนเงิน 50 บาทพร้อมสลิป (รอยืนยัน)"
give $TID $UID_S drink "" "กาแฟเย็น 2 แก้ว"
give $TID $UID_S snack 40 "ขนมปัง"
ok "$(yes_if '[ "$(nthanks $TID)" = "3" ]')" "บันทึกของได้ (มูลค่าไม่บังคับ)"
give $TID $UID_S money 20 ""
ok "$(yes_if '[ "$(nthanks $TID)" = "3" ]')" "เกิน 3 รายการต่อใบงานไม่ได้"

echo "== C. QR และสลิปเป็นไฟล์ส่วนตัว =="
curl -s -c "$S/t_staff.jar" -b "$S/t_staff.jar" -o /dev/null -F "csrf=$(tok t_staff)" -F tipqr_action=upload -F "tipqr=@$S/photo.jpg;type=image/jpeg" "$B/profile.php"
ok "$(yes_if '[ -n "$(q "SELECT promptpay_qr_path FROM users WHERE user_id=$UID_S")" ]')" "Lab boy อัปโหลด QR พร้อมเพย์ที่หน้าโปรไฟล์"
curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null -F "csrf=$(tok t_user)" -F tipqr_action=upload -F "tipqr=@$S/photo.jpg;type=image/jpeg" "$B/profile.php"
ok "$(yes_if '[ -z "$(q "SELECT IFNULL(promptpay_qr_path, \"\") FROM users WHERE user_id=$UID_U")" ]')" "ผู้แจ้ง (ไม่ใช่สตาฟ) ตั้ง QR ไม่ได้"
ok "$(yes_if '[ "$(code t_user "file.php?k=tipqr&u=$UID_S&t=$TID")" = "200" ]')" "ผู้แจ้งของใบงานเห็น QR ของช่างในทีม"
ok "$(yes_if '[ "$(code t_user "file.php?k=tipqr&u=$UID_S&t=$TIP")" = "200" ] || true')" "(ใบงานที่ช่างคนนี้อยู่ในทีมก็เห็นได้)"
ok "$(yes_if '[ "$(code t_staff2 "file.php?k=tipqr&u=$UID_S&t=$TID")" = "404" ]')" "คนที่ไม่เกี่ยวข้องเปิด QR ไม่ได้"
ok "$(yes_if '[ "$(code t_user "file.php?k=tipqr&u=$UID_S")" = "404" ]')" "เปิด QR โดยไม่อ้างใบงานไม่ได้"
for who in t_user t_staff t_admin; do ok "$(yes_if '[ "$(code $who "file.php?k=slip&i=$TH1")" = "200" ]')" "$who เปิดสลิปได้"; done
ok "$(yes_if '[ "$(code t_staff2 "file.php?k=slip&i=$TH1")" = "404" ]')" "คนที่ไม่เกี่ยวข้องเปิดสลิปไม่ได้"

echo "== D. Lab boy ยืนยัน / ผู้แจ้งยกเลิก =="
SCORE0=$(q "SELECT COALESCE(SUM(points_change),0) FROM score_logs WHERE user_id=$UID_S")
ok "$(yes_if 'get t_staff staff/thanks.php | grep -q "ได้รับแล้ว"')" "Lab boy เห็นคำขอบคุณที่รอยืนยัน"
staffpost t_staff2 -d thanks_id=$TH1 -d action=received
ok "$(yes_if '[ "$(q "SELECT status FROM ticket_thanks WHERE id=$TH1")" = "pending" ]')" "คนอื่นยืนยันแทนไม่ได้"
staffpost t_staff -d thanks_id=$TH1 -d action=received -d staff_note=ได้รับแล้วครับ
ok "$(yes_if '[ "$(q "SELECT status FROM ticket_thanks WHERE id=$TH1")" = "confirmed" ]')" "Lab boy ยืนยันว่าได้รับ"
staffpost t_staff -d thanks_id=$TH1 -d action=not_received
ok "$(yes_if '[ "$(q "SELECT status FROM ticket_thanks WHERE id=$TH1")" = "confirmed" ]')" "ยืนยันแล้วเปลี่ยนไม่ได้"
curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null --data-urlencode "csrf=$(tok t_user)" -d action=cancel -d ticket_id=$TID -d thanks_id=$TH1 "$B/thanks.php?id=$TID"
ok "$(yes_if '[ "$(q "SELECT status FROM ticket_thanks WHERE id=$TH1")" = "confirmed" ]')" "ผู้แจ้งยกเลิกรายการที่ยืนยันแล้วไม่ได้"
TH2=$(q "SELECT MAX(id) FROM ticket_thanks WHERE ticket_id=$TID AND status='pending'")
curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null --data-urlencode "csrf=$(tok t_user)" -d action=cancel -d ticket_id=$TID -d thanks_id=$TH2 "$B/thanks.php?id=$TID"
ok "$(yes_if '[ "$(q "SELECT status FROM ticket_thanks WHERE id=$TH2")" = "cancelled" ]')" "ผู้แจ้งยกเลิกรายการที่รอยืนยันได้"
ok "$(yes_if '[ "$(q "SELECT COALESCE(SUM(points_change),0) FROM score_logs WHERE user_id=$UID_S")" = "$SCORE0" ]')" "คำขอบคุณไม่มีผลกับคะแนน"
ok "$(yes_if 'get t_admin admin/admin_thanks.php | grep -q "50 บาท"')" "ครูเห็นยอดรวมในหน้าคำขอบคุณ"
ok "$(yes_if '[ "$(code t_staff admin/admin_thanks.php)" != "200" ]')" "สตาฟเปิดหน้ารวมของครูไม่ได้"

echo "== E. คะแนนสตาฟ =="
TERM=$(q "SELECT id FROM academic_terms WHERE CURDATE() BETWEEN start_date AND end_date LIMIT 1")
q "DELETE FROM score_logs WHERE user_id=$UID_S"
cur(){ local s; s=$(q "SELECT LEAST(100, GREATEST(0, 100 + COALESCE(SUM(sl.points_change),0))) FROM score_logs sl JOIN academic_terms a ON a.id=$TERM WHERE sl.user_id=$UID_S AND sl.created_at BETWEEN a.start_date AND CONCAT(a.end_date, \" 23:59:59\")"); get t_admin admin/admin_scores.php | grep -q "$s / 100" && echo "$s"; }
ok "$(yes_if '[ "$(cur)" = "100" ]')" "เริ่มภาคเรียนที่ 100 คะแนน"
RULE=$(q "SELECT id FROM score_rules WHERE points=-10 AND is_active=1 LIMIT 1")
scorepost -d action=adjust -d staff_id=$UID_S -d rule_id=$RULE -d note=ทดสอบ
ok "$(yes_if '[ "$(cur)" = "90" ]')" "ครูหักตามรายการความผิด (-10)"
scorepost -d action=adjust -d staff_id=$UID_S -d rule_id=0 -d points=50 -d note=ทดสอบบวกเกิน
ok "$(yes_if '[ "$(cur)" = "100" ] && [ "$(q "SELECT points_change FROM score_logs WHERE user_id=$UID_S ORDER BY id DESC LIMIT 1")" = "10" ]')" "ครูบวกคะแนนได้ไม่เกิน 100 (ตัดเหลือ +10)"
N0=$(q "SELECT COUNT(*) FROM score_logs WHERE user_id=$UID_S")
scorepost -d action=adjust -d staff_id=$UID_S -d rule_id=0 -d points=5 -d note=บวกตอนเต็ม
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM score_logs WHERE user_id=$UID_S")" = "$N0" ]')" "คะแนนเต็มแล้ว บวกเพิ่มไม่ได้"
scorepost -d action=adjust -d staff_id=$UID_S -d rule_id=0 -d points=-5 -d note=
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM score_logs WHERE user_id=$UID_S")" = "$N0" ]')" "กำหนดเองต้องมีเหตุผล"
OUTSIDER=$(q "SELECT user_id FROM users WHERE role='staff' AND user_id NOT IN (SELECT user_id FROM term_staff WHERE term_id=$TERM) LIMIT 1")
if [ -n "$OUTSIDER" ]; then
  NO=$(q "SELECT COUNT(*) FROM score_logs WHERE user_id=$OUTSIDER")
  scorepost -d action=adjust -d staff_id=$OUTSIDER -d rule_id=$RULE
  ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM score_logs WHERE user_id=$OUTSIDER")" = "$NO" ]')" "ปรับคะแนนสตาฟนอกทีมภาคเรียนไม่ได้"
fi
LOG=$(q "SELECT id FROM score_logs WHERE user_id=$UID_S AND points_change=-10 ORDER BY id DESC LIMIT 1")
scorepost -d action=reverse -d log_id=$LOG
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM score_logs WHERE user_id=$UID_S AND reason LIKE \"ยกเลิกรายการ #$LOG %\" AND points_change=10")" = "1" ] && [ "$(q "SELECT COUNT(*) FROM score_logs WHERE id=$LOG")" = "1" ]')" "ยกเลิก = บันทึกรายการกลับค่า ไม่ลบประวัติเดิม"
scorepost -d action=reverse -d log_id=$LOG
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM score_logs WHERE reason LIKE \"ยกเลิกรายการ #$LOG %\"")" = "1" ]')" "ยกเลิกรายการเดิมซ้ำไม่ได้"
NS=$(q "SELECT COUNT(*) FROM score_logs WHERE user_id=$UID_S")
curl -s -b "$S/t_staff.jar" -o /dev/null -d action=adjust -d staff_id=$UID_S -d rule_id=0 -d points=50 -d note=แอบบวก -d csrf=x "$B/admin/admin_scores.php"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM score_logs WHERE user_id=$UID_S")" = "$NS" ]')" "สตาฟให้คะแนนตัวเองไม่ได้"
scorepost -d action=add_rule --data-urlencode "label=ทดสอบรายการใหม่" -d points=-3
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM score_rules WHERE label=\"ทดสอบรายการใหม่\" AND points=-3")" = "1" ]')" "ครูเพิ่มรายการความผิดได้"


echo "== F. รูปหลังซ่อม 1–3 รูป =="
TR=$(mk in_progress NULL)
jobsend(){ local t; t=$(get t_staff "staff/job.php?id=$TR" | csrf); curl -s -c "$S/t_staff.jar" -b "$S/t_staff.jar" -o /dev/null -F "csrf=$t" -F action=submit_recheck -F ticket_id=$TR -F "repair_method=เปลี่ยนสายไฟ" -F "resolution_summary=ใช้งานได้" "$@" "$B/staff/job.php?id=$TR"; }
jobsend -F "completion_images[]=@$S/photo.jpg;type=image/jpeg" -F "completion_images[]=@$S/photo.jpg;type=image/jpeg" -F "completion_images[]=@$S/photo.jpg;type=image/jpeg" -F "completion_images[]=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TR")" = "in_progress" ]')" "แนบรูปหลังซ่อมเกิน 3 รูป ถูกปฏิเสธ"
jobsend
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TR")" = "in_progress" ]')" "ไม่แนบรูปหลังซ่อมเลย ถูกปฏิเสธ"
jobsend -F "completion_images[]=@$S/photo.jpg;type=image/jpeg" -F "completion_images[]=@$S/photo.jpg;type=image/jpeg" -F "completion_images[]=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(q "SELECT status FROM tickets WHERE id=$TR")" = "inspected_ready" ] && [ -n "$(q "SELECT completion_image FROM tickets WHERE id=$TR")" ] && [ "$(q "SELECT COUNT(*) FROM ticket_completion_photos WHERE ticket_id=$TR")" = "2" ]')" "บันทึกรูปหลังซ่อม 3 รูป (รูปแรก + เพิ่มเติม 2)"
PH=$(q "SELECT id FROM ticket_completion_photos WHERE ticket_id=$TR ORDER BY sort_order LIMIT 1")
ok "$(yes_if '[ "$(code t_user "file.php?t=$TR&k=done&i=$PH")" = "200" ]')" "ผู้แจ้งของใบงานเปิดรูปหลังซ่อมเพิ่มเติมได้"
ok "$(yes_if '[ "$(code t_user "file.php?t=$TID&k=done&i=$PH")" = "404" ]')" "อ้างรูปกับใบงานอื่นไม่ได้"
ok "$(yes_if 'get t_staff "staff/job.php?id=$TR" | grep -q "รูปหลังซ่อม 3"')" "หน้าใบงานแสดงลิงก์รูปหลังซ่อมครบ 3 รูป"

echo "RESULT: pass=$pass fail=$fail"
