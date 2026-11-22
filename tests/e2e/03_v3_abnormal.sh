#!/bin/bash
# v3 abnormal paths: discrepancy, dispute, admin returns to staff (round 2), admin skip (time-gated), saved signature
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
SIG=$(cat "$S/sig.txt")
st(){ q "SELECT status FROM tickets WHERE id=$TID"; }
jobpost(){ local who=$1; shift; local t; t=$(get $who "staff/job.php?id=$TID" | csrf); curl -s -c "$S/$who.jar" -b "$S/$who.jar" -o /dev/null -F "csrf=$t" -F ticket_id=$TID "$@" "$B/staff/job.php?id=$TID"; }
signpost(){ local who=$1; shift; local t; t=$(get $who "sign.php?id=$TID" | csrf); curl -s -c "$S/$who.jar" -b "$S/$who.jar" -o /dev/null --data-urlencode "csrf=$t" -d ticket_id=$TID "$@" "$B/sign.php?id=$TID"; }
adminpost(){ local t; t=$(get t_admin "admin/admin_signoff.php?id=$TID" | csrf); curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$t" -d ticket_id=$TID "$@" $B/admin/admin_signoff.php; }

for u in t_admin t_staff t_user; do login $u "$PW" >/dev/null; done
UID_U=$(q "SELECT user_id FROM users WHERE id_number='t_user'"); UID_S=$(q "SELECT user_id FROM users WHERE id_number='t_staff'")
CAT=$(q "SELECT id FROM categories WHERE name='คอมพิวเตอร์และฮาร์ดแวร์' LIMIT 1")

echo "== A. setup ticket accepted by t_staff =="
q "INSERT INTO tickets (ticket_no, requester_id, category_id, location_note, contact_person, symptom, urgency, status, created_at) VALUES (CONCAT('T3-', FLOOR(RAND()*1000000)), $UID_U, $CAT, 'ห้องทดสอบ v3', 'x', 'จอไม่ติด', 'normal', 'pending', NOW())"
TID=$(q "SELECT id FROM tickets WHERE requester_id=$UID_U ORDER BY id DESC LIMIT 1")
T=$(get t_staff staff/staff_dashboard.php | csrf)
curl -s -c "$S/t_staff.jar" -b "$S/t_staff.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=accept_ticket -d ticket_id=$TID $B/staff/staff_dashboard.php
ok "$(yes_if '[ "$(st)" = "accepted" ]')" "accepted"
ok "$(yes_if 'get t_staff "staff/job.php?id=$TID" | grep -q "ตัวเครื่อง / เคส"')" "ฟอร์มเช็คลิสต์แสดงรายการตั้งต้นของหมวดหมู่"
jobpost t_staff -F action=save_intake -F "it_name[]=คอมพิวเตอร์" -F "it_type[]=main" -F "it_qty[]=1" -F "it_cond[]=normal" -F "it_name[]=สายไฟ" -F "it_type[]=accessory" -F "it_qty[]=2" -F "it_cond[]=normal"

echo "== B. admin skip intake is time-gated =="
adminpost -d action=skip --data-urlencode "reason=ติดต่อผู้แจ้งไม่ได้"
ok "$(yes_if '[ "$(st)" = "awaiting_intake_sign" ]')" "ยังไม่ครบกำหนด ข้ามขั้นไม่ได้"
q "UPDATE tickets SET stage_since = NOW() - INTERVAL 25 HOUR WHERE id=$TID"
adminpost -d action=skip --data-urlencode "reason=สั้น"
ok "$(yes_if '[ "$(st)" = "awaiting_intake_sign" ]')" "เหตุผลสั้นเกิน -> ไม่ผ่าน"
T=$(get t_staff "staff/job.php?id=$TID" | csrf)
curl -s -c "$S/t_staff.jar" -b "$S/t_staff.jar" -o /dev/null --data-urlencode "csrf=$T" -d ticket_id=$TID -d action=skip --data-urlencode "reason=สตาฟพยายามข้ามขั้น" $B/admin/admin_signoff.php
ok "$(yes_if '[ "$(st)" = "awaiting_intake_sign" ]')" "สตาฟใช้คำสั่งข้ามขั้นของครูไม่ได้"
adminpost -d action=skip --data-urlencode "reason=ผู้แจ้งไม่อยู่ ครูรับทราบแทน"
ok "$(yes_if '[ "$(st)" = "in_progress" ]')" "ครบกำหนดแล้วครูข้ามขั้นได้ -> in_progress"
ok "$(yes_if '[ "$(q "SELECT CONCAT(signer_role,\"/\",stage,\"/\",method,\"/\",signer_id=(SELECT user_id FROM users WHERE id_number=\"t_admin\")) FROM ticket_signoffs WHERE ticket_id=$TID")" = "requester/intake/skip/1" ]')" "บันทึกการข้ามขั้น (method=skip ผู้ข้าม=ครู)"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_logs WHERE ticket_id=$TID AND note LIKE \"ข้ามขั้นผู้แจ้ง:%\"")" = "1" ]')" "เหตุผลการข้ามขั้นอยู่ในประวัติ"

echo "== C. recheck with discrepancy =="
C1=$(q "SELECT id FROM ticket_item_checks WHERE ticket_id=$TID ORDER BY sort_order LIMIT 1")
C2=$(q "SELECT id FROM ticket_item_checks WHERE ticket_id=$TID ORDER BY sort_order LIMIT 1 OFFSET 1")
jobpost t_staff -F action=submit_recheck -F "repair_method=เปลี่ยนจอ" -F "resolution_summary=ใช้ได้" -F "rc_result[$C1]=returned" -F "rc_qty[$C1]=1" -F "rc_result[$C2]=returned" -F "rc_qty[$C2]=1" -F "completion_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(st)" = "in_progress" ]')" "จำนวนไม่ตรงตอนรับแต่ไม่อธิบาย -> ส่งไม่ได้"
jobpost t_staff -F action=submit_recheck -F "repair_method=เปลี่ยนจอ" -F "resolution_summary=ใช้ได้" -F "rc_result[$C1]=returned" -F "rc_qty[$C1]=1" -F "completion_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(st)" = "in_progress" ]')" "ไม่ระบุผลตรวจครบทุกรายการ -> ส่งไม่ได้"
jobpost t_staff -F action=submit_recheck -F "repair_method=เปลี่ยนจอ" -F "resolution_summary=ใช้ได้" -F "rc_result[$C1]=returned" -F "rc_qty[$C1]=1" -F "rc_result[$C2]=missing" -F "rc_qty[$C2]=1" -F "discrepancy_note=สายไฟหาย 1 เส้น" -F "completion_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(st)" = "inspected_ready" ] && [ "$(q "SELECT has_discrepancy FROM tickets WHERE id=$TID")" = "1" ]')" "มีข้อแตกต่าง + คำอธิบาย -> inspected_ready, has_discrepancy=1"
jobpost t_staff -F action=staff_sign -F sig_method=draw --form-string "sig_data=$SIG"
ok "$(yes_if '[ "$(st)" = "awaiting_requester_sign" ]')" "สตาฟเซ็น (รอบ 1)"

echo "== D. requester must acknowledge; then disputes =="
signpost t_user -d action=sign_return -d sig_method=draw --data-urlencode "sig_data=$SIG"
ok "$(yes_if '[ "$(st)" = "awaiting_requester_sign" ]')" "ไม่ติ๊กรับทราบข้อแตกต่าง -> เซ็นไม่ได้"
signpost t_user -d action=dispute --data-urlencode "dispute_reason=สายไฟไม่ครบ ไม่ยอมรับ"
ok "$(yes_if '[ "$(st)" = "disputed" ]')" "ผู้แจ้งไม่ยอมรับ -> disputed"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM email_logs WHERE ticket_id=$TID AND template_key=\"disputed\"")" -ge 1 ]')" "อีเมลแจ้งครูเรื่องข้อโต้แย้ง"
adminpost -d action=sign -d confirm=1 -d sig_method=draw --data-urlencode "sig_data=$SIG"
ok "$(yes_if '[ "$(st)" = "disputed" ]')" "รับรองใบงานที่โต้แย้งโดยไม่มีเหตุผล -> ไม่ผ่าน"

echo "== E. admin returns to staff -> round 2, previous signatures kept =="
adminpost -d action=return --data-urlencode "reason=ให้หาสายไฟที่หายก่อน"
ok "$(yes_if '[ "$(st)" = "in_progress" ] && [ "$(q "SELECT sign_round FROM tickets WHERE id=$TID")" = "2" ]')" "ส่งกลับ -> in_progress รอบเซ็น 2"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_signoffs WHERE ticket_id=$TID AND stage=\"return\" AND round_no=1")" = "1" ]')" "ลายเซ็นสตาฟรอบ 1 ยังอยู่"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM email_logs WHERE ticket_id=$TID AND template_key=\"returned_to_staff\"")" = "1" ]')" "อีเมลแจ้งทีมช่างว่าถูกส่งกลับ"
jobpost t_staff -F action=submit_recheck -F "repair_method=เปลี่ยนจอ หาสายไฟเจอ" -F "resolution_summary=ใช้ได้" -F "rc_result[$C1]=returned" -F "rc_qty[$C1]=1" -F "rc_result[$C2]=returned" -F "rc_qty[$C2]=2" -F "completion_image=@$S/photo.jpg;type=image/jpeg"
ok "$(yes_if '[ "$(st)" = "inspected_ready" ] && [ "$(q "SELECT has_discrepancy FROM tickets WHERE id=$TID")" = "0" ]')" "ตรวจซ้ำรอบ 2 ครบถ้วน"

echo "== F. saved signature + requester skip after N days =="
T=$(get t_staff profile.php | csrf)
curl -s -c "$S/t_staff.jar" -b "$S/t_staff.jar" -o /dev/null -F "csrf=$T" -F signature_action=upload -F "signature=@$S/sigfile.png;type=image/png" $B/profile.php
ok "$(yes_if '[ -n "$(q "SELECT signature_path FROM users WHERE user_id=$UID_S")" ]')" "สตาฟอัปโหลดลายเซ็นเก็บไว้"
jobpost t_staff -F action=staff_sign -F sig_method=saved
ok "$(yes_if '[ "$(st)" = "awaiting_requester_sign" ]')" "สตาฟเซ็นด้วยลายเซ็นที่บันทึกไว้"
ok "$(yes_if '[ "$(q "SELECT method FROM ticket_signoffs WHERE ticket_id=$TID AND stage=\"return\" AND round_no=2 AND signer_role=\"staff\"")" = "saved" ]')" "method=saved รอบ 2"
SAVED=$(q "SELECT signature_path FROM users WHERE user_id=$UID_S"); USED=$(q "SELECT signature_image FROM ticket_signoffs WHERE ticket_id=$TID AND round_no=2 AND signer_role='staff'")
ok "$(yes_if '[ "$SAVED" != "$USED" ]')" "ลายเซ็นในใบงานเป็นสำเนาแยกจากไฟล์ในโปรไฟล์"
adminpost -d action=skip --data-urlencode "reason=ผู้แจ้งไม่เข้าระบบ"
ok "$(yes_if '[ "$(st)" = "awaiting_requester_sign" ]')" "ยังไม่ครบ 3 วัน ข้ามขั้นผู้แจ้งไม่ได้"
q "UPDATE tickets SET stage_since = NOW() - INTERVAL 4 DAY WHERE id=$TID"
adminpost -d action=skip --data-urlencode "reason=ผู้แจ้งไม่ตอบ 4 วัน รับเครื่องคืนแล้ว"
ok "$(yes_if '[ "$(st)" = "awaiting_admin_sign" ]')" "ครบกำหนด ครูข้ามขั้นผู้แจ้งได้"
adminpost -d action=sign -d confirm=1 -d sig_method=draw --data-urlencode "sig_data=$SIG"
ok "$(yes_if '[ "$(st)" = "completed" ]')" "ครูรับรอง -> completed"
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_signoffs WHERE ticket_id=$TID")" = "5" ]')" "ประวัติลายเซ็นครบ 5: intake(ข้าม) + รอบ1 สตาฟ + รอบ2 สตาฟ/ผู้แจ้ง(ข้าม)/ครู"
ok "$(yes_if 'get t_user "certificate.php?id=$TID" | grep -q "ข้ามขั้นโดยครูผู้ดูแล"')" "ใบรับรองแสดงว่าขั้นผู้แจ้งถูกข้ามโดยครู"

echo "== G. cancel only before signing; status can't be set to completed manually =="
T=$(get t_admin admin/admin_manage_request.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d ticket_id=$TID -d status=cancelled --data-urlencode "status_reason=ลองยกเลิก" $B/admin/admin_manage_request.php
ok "$(yes_if '[ "$(st)" = "completed" ]')" "ใบงานปิดแล้วยกเลิกไม่ได้"
q "INSERT INTO tickets (ticket_no, requester_id, category_id, location_note, contact_person, symptom, urgency, status, created_at) VALUES (CONCAT('T3-', FLOOR(RAND()*1000000)), $UID_U, $CAT, 'ห้องทดสอบ 2', 'x', 'x', 'normal', 'pending', NOW())"
TID=$(q "SELECT id FROM tickets WHERE requester_id=$UID_U ORDER BY id DESC LIMIT 1")
T=$(get t_admin admin/admin_manage_request.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d ticket_id=$TID -d status=completed --data-urlencode "status_reason=ปิดเอง" $B/admin/admin_manage_request.php
ok "$(yes_if '[ "$(st)" = "pending" ]')" "ตั้งเป็น completed เองจากหน้าจัดการไม่ได้"
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d ticket_id=$TID -d status=cancelled --data-urlencode "status_reason=แจ้งซ้ำ" $B/admin/admin_manage_request.php
ok "$(yes_if '[ "$(st)" = "cancelled" ] && [ "$(q "SELECT note FROM ticket_logs WHERE ticket_id=$TID AND new_status=\"cancelled\"")" = "เหตุผล: แจ้งซ้ำ" ]')" "ยกเลิกก่อนรับงานได้ และบันทึกเหตุผลในประวัติ"

echo "== H. war room: roles, filters, nudge =="
q "INSERT INTO tickets (ticket_no, requester_id, category_id, location_note, contact_person, symptom, urgency, status, assigned_to, accepted_at, stage_since, created_at) VALUES (CONCAT('T3-', FLOOR(RAND()*1000000)), $UID_U, $CAT, 'ห้องค้างนาน', 'x', 'x', 'normal', 'in_progress', $UID_S, NOW() - INTERVAL 100 HOUR, NOW() - INTERVAL 100 HOUR, NOW() - INTERVAL 101 HOUR)"
TID=$(q "SELECT id FROM tickets WHERE requester_id=$UID_U ORDER BY id DESC LIMIT 1")
H=$(get t_admin admin/admin_war_room.php)
ok "$(yes_if 'echo "$H" | grep -q "wr-stale--crit" && echo "$H" | grep -q "ห้องค้างนาน"')" "งานค้างเกินเกณฑ์วิกฤตแสดงสีแดง"
ok "$(yes_if 'echo "$H" | grep -q "โทร 08"')" "admin เห็นเบอร์ติดต่อสตาฟ"
H=$(get t_staff admin/admin_war_room.php)
ok "$(yes_if '! echo "$H" | grep -q "โทร 08" && ! echo "$H" | grep -q "value=\"nudge\""')" "staff ไม่เห็นเบอร์และปุ่มเร่งงาน"
ok "$(yes_if 'get t_admin "admin/admin_war_room.php?partial=1&group=working" | grep -q "wr-kpi" && ! get t_admin "admin/admin_war_room.php?partial=1" | grep -q "<html"')" "polling (?partial=1) ส่งเฉพาะเนื้อหาบอร์ด"
T=$(get t_staff admin/admin_war_room.php | csrf)
curl -s -c "$S/t_staff.jar" -b "$S/t_staff.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=nudge -d ticket_id=$TID -d message=x $B/admin/admin_war_room.php
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_logs WHERE ticket_id=$TID AND source=\"nudge\"")" = "0" ]')" "staff ใช้คำสั่งเร่งงานไม่ได้"
T=$(get t_admin admin/admin_war_room.php | csrf)
curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null --data-urlencode "csrf=$T" -d action=nudge -d ticket_id=$TID --data-urlencode "message=ช่วยปิดงานวันนี้" $B/admin/admin_war_room.php
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM ticket_logs WHERE ticket_id=$TID AND source=\"nudge\"")" = "1" ] && [ "$(q "SELECT COUNT(*) FROM email_logs WHERE ticket_id=$TID AND template_key=\"staff_nudge\"")" = "1" ]')" "admin เร่งงาน: บันทึกประวัติ + อีเมลถึงทีมช่าง"

echo; echo "RESULT: pass=$pass fail=$fail"
