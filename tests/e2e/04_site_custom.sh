#!/bin/bash
# v3 หัวข้อ 6 เฟส 1: ปรับแต่งเว็บ (ธีม ข้อความ เมนู คลังสื่อ โลโก้ footer) + สิทธิ์ + การกรองค่าอันตราย
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
setting(){ q "SELECT setting_value FROM system_settings WHERE setting_key='$1'"; }
# sitepost <jar> <tab-query> <curl args...>
sitepost(){ local who=$1 qs=$2; shift 2; local t; t=$(get t_admin "admin/admin_site.php?tab=identity" | csrf); curl -s -c "$S/$who.jar" -b "$S/$who.jar" -o /dev/null -w "%{http_code}" --data-urlencode "csrf=$t" "$@" "$B/admin/admin_site.php?$qs"; }
sitepostF(){ local qs=$1; shift; local t; t=$(get t_admin "admin/admin_site.php?tab=identity" | csrf); curl -s -c "$S/t_admin.jar" -b "$S/t_admin.jar" -o /dev/null -F "csrf=$t" "$@" "$B/admin/admin_site.php?$qs"; }

for u in t_admin t_staff t_user; do login $u "$PW" >/dev/null; done
REV0=$(q "SELECT COUNT(*) FROM site_revisions")

echo "== A. สิทธิ์และ CSRF =="
CODE=$(curl -s -b "$S/t_staff.jar" -o /dev/null -w "%{http_code}" "$B/admin/admin_site.php")
ok "$(yes_if '[ "$CODE" = "302" ] || [ "$CODE" = "303" ]')" "สตาฟเปิดหน้าปรับแต่งเว็บไม่ได้ ($CODE)"
curl -s -b "$S/t_staff.jar" -o /dev/null -d action=reset_theme -d csrf=x "$B/admin/admin_site.php?tab=theme"
CODE=$(curl -s -b "$S/t_admin.jar" -o /dev/null -w "%{http_code}" -d action=save_theme -d theme_color=blue -d csrf=bad "$B/admin/admin_site.php?tab=theme")
ok "$(yes_if '[ "$CODE" = "403" ] && [ "$(setting theme_color)" != "blue" ]')" "CSRF ผิด -> 403 ไม่บันทึก"

echo "== B. ธีม =="
sitepost t_admin "tab=theme" -d action=save_theme -d theme_color=default -d primary_color='#123456' -d theme_accent_color='#AA5500' -d theme_bg_color='#F0F4F8' -d theme_font=sarabun -d theme_radius=round >/dev/null
ok "$(yes_if '[ "$(setting primary_color)" = "#123456" ] && [ "$(setting theme_font)" = "sarabun" ]')" "บันทึกสี ฟอนต์ ความโค้งมุม"
HTML=$(get t_user dashboard.php)
ok "$(yes_if 'echo "$HTML" | grep -q "reps-site-theme" && echo "$HTML" | grep -q "family=Sarabun" && echo "$HTML" | grep -q "#F0F4F8"')" "ธีมแสดงในหน้าผู้แจ้ง (CSS + ฟอนต์ + พื้นหลัง)"
ok "$(yes_if 'get t_staff staff/staff_dashboard.php | grep -q "#123456"')" "สีหลักแสดงในหน้าสตาฟ"
ok "$(yes_if '! get t_staff warboard.php | grep -q "background-color:#F0F4F8"')" "จอสรุปงานไม่เปลี่ยนสีพื้นหลัง"
sitepost t_admin "tab=theme" -d action=save_theme -d theme_color=default -d 'primary_color=#fff;}body{display:none' -d theme_font=kanit -d theme_radius=default >/dev/null
ok "$(yes_if '[ "$(setting primary_color)" = "#123456" ]')" "สีที่มี CSS แทรก ถูกปฏิเสธ"
sitepost t_admin "tab=theme" -d action=save_theme -d theme_color=default -d theme_font='kanit;x' -d theme_radius=evil >/dev/null
ok "$(yes_if '[ "$(setting theme_font)" = "kanit" ] && [ "$(setting theme_radius)" = "default" ]')" "ฟอนต์/มุมที่ไม่อยู่ในรายการ -> ค่าเริ่มต้น"
sitepost t_admin "tab=theme" -d action=reset_theme >/dev/null
ok "$(yes_if '[ "$(setting primary_color)" = "" ] && ! get t_user dashboard.php | grep -q "reps-site-theme"')" "คืนค่าธีม -> หน้าเว็บกลับเหมือนเดิม"

echo "== C. ข้อความ =="
SRC='ประวัติการแจ้งซ่อม'
sitepost t_admin "tab=texts" -d action=save_text --data-urlencode "source=$SRC" --data-urlencode 'text_th=ประวัติงานซ่อมของฉัน' --data-urlencode 'text_en=My repair log' >/dev/null
ok "$(yes_if 'get t_user dashboard.php | grep -q "ประวัติงานซ่อมของฉัน"')" "ข้อความใหม่แสดงในหน้าผู้แจ้ง (TH)"
ok "$(yes_if '! get t_user dashboard.php | grep -q ">ประวัติการแจ้งซ่อม<"')" "ข้อความเดิมถูกแทนทั้งหมด"
ok "$(yes_if 'get t_admin "admin/admin_site.php?tab=texts" | grep -q "$SRC"')" "หน้าแก้ข้อความยังเห็นข้อความเดิม"
curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null "$B/lang.php?set=en&back=dashboard.php"
ok "$(yes_if 'get t_user dashboard.php | grep -q "My repair log"')" "โหมด EN ใช้ข้อความอังกฤษที่แก้"
curl -s -c "$S/t_user.jar" -b "$S/t_user.jar" -o /dev/null "$B/lang.php?set=th&back=dashboard.php"
sitepost t_admin "tab=texts" -d action=save_text --data-urlencode 'source=<script>alert(1)</script>' -d text_th=x >/dev/null
ok "$(yes_if '! setting site_text_overrides | grep -q "script"')" "ข้อความเดิมที่ไม่ใช่ภาษาไทย/มีแท็ก ถูกปฏิเสธ"
sitepost t_admin "tab=texts" -d action=save_text --data-urlencode 'source=ใบงานของฉัน' --data-urlencode 'text_th=<img src=x onerror=alert(1)>' >/dev/null
ok "$(yes_if 'get t_user dashboard.php | grep -q "&lt;img src=x" && ! get t_user dashboard.php | grep -q "<img src=x"')" "ข้อความใหม่ที่มี HTML ถูก escape"
sitepost t_admin "tab=texts" -d action=delete_text --data-urlencode "source=$SRC" >/dev/null
ok "$(yes_if 'get t_user dashboard.php | grep -q "ประวัติการแจ้งซ่อม"')" "คืนข้อความรายการเดียว"
sitepost t_admin "tab=texts" -d action=reset_texts >/dev/null
ok "$(yes_if '[ -z "$(setting site_text_overrides)" ]')" "คืนข้อความทั้งหมด"

echo "== D. เมนู =="
sitepost t_admin "tab=menus&area=admin" -d action=save_menu \
  -d 'm[0][key]=reports' --data-urlencode 'm[0][label]=รายงานสรุป' -d 'm[0][roles][]=admin' -d 'm[0][visible]=1' \
  -d 'm[1][key]=history' -d 'm[1][roles][]=admin' \
  -d 'm[2][key]=site' -d 'm[2][roles][]=admin' \
  --data-urlencode 'new_label=คู่มือการใช้งาน' -d 'new_target=https://example.com/manual' -d new_icon=fa-book -d new_group=links -d 'new_roles[]=admin' -d new_tab=1 >/dev/null
SIDE=$(get t_admin admin/admin_dashboard.php)
ok "$(yes_if 'echo "$SIDE" | grep -q "รายงานสรุป"')" "เปลี่ยนชื่อเมนูผู้ดูแล"
ok "$(yes_if '! echo "$SIDE" | grep -q "admin_history.php"')" "ซ่อนเมนูประวัติ"
ok "$(yes_if 'echo "$SIDE" | grep -q "admin_site.php"')" "เมนูปรับแต่งเว็บซ่อนไม่ได้"
ok "$(yes_if 'echo "$SIDE" | grep -q "https://example.com/manual" && echo "$SIDE" | grep -q "ลิงก์เพิ่มเติม"')" "เพิ่มลิงก์ภายนอกในกลุ่มลิงก์เพิ่มเติม"
ok "$(yes_if '[ "$(get t_admin admin/admin_history.php | grep -c "admin-page-title")" -ge 1 ]')" "ซ่อนเมนูแล้วหน้ายังเปิดได้ตามสิทธิ์"
P1=$(echo "$SIDE" | grep -n "admin_reports.php" | head -1 | cut -d: -f1); P2=$(echo "$SIDE" | grep -n "admin_teacher_report.php" | head -1 | cut -d: -f1)
ok "$(yes_if '[ -n "$P1" ] && [ -n "$P2" ] && [ "$P1" -lt "$P2" ]')" "ลำดับตามที่ส่งมา"
sitepost t_admin "tab=menus&area=admin" -d action=save_menu --data-urlencode 'new_label=ลิงก์อันตราย' -d 'new_target=javascript:alert(1)' >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM site_menus WHERE target LIKE \"javascript%\"")" = "0" ]')" "ปฏิเสธลิงก์ javascript:"
sitepost t_admin "tab=menus&area=admin" -d action=save_menu --data-urlencode 'new_label=ย้อนโฟลเดอร์' -d 'new_target=../../etc/passwd.php' >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM site_menus WHERE target LIKE \"%..%\"")" = "0" ]')" "ปฏิเสธลิงก์ที่มี .."
sitepost t_admin "tab=menus&area=account" -d action=save_menu -d 'm[0][key]=my_requests' -d 'm[0][roles][]=user' -d 'm[0][roles][]=admin' -d 'm[0][visible]=1' \
  --data-urlencode 'new_label=ระเบียบการใช้ห้อง' -d 'new_target=donate.php' -d 'new_roles[]=user' >/dev/null
ok "$(yes_if 'get t_user dashboard.php | grep -q "ระเบียบการใช้ห้อง"')" "ลิงก์ในเมนูบัญชีแสดงกับผู้แจ้ง"
ok "$(yes_if '! get t_staff staff/staff_dashboard.php | grep -q "ระเบียบการใช้ห้อง"')" "ลิงก์ที่จำกัด role ไม่แสดงกับสตาฟ"
ok "$(yes_if '[ "$(q "SELECT visible_roles FROM site_menus WHERE area=\"account\" AND item_key=\"my_requests\"")" = "user" ]')" "เมนูของระบบให้ role นอกขอบเขตไม่ได้ (ใบงานของฉัน ไม่ขึ้นกับ admin)"
CID=$(q "SELECT id FROM site_menus WHERE area='admin' AND item_key IS NULL LIMIT 1")
sitepost t_admin "tab=menus&area=account" -d action=save_menu -d "m[0][id]=$CID" -d 'm[0][delete]=1' >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM site_menus WHERE id=$CID")" = "1" ]')" "แก้/ลบลิงก์ข้ามพื้นที่ไม่ได้"
sitepost t_admin "tab=menus&area=admin" -d action=reset_menu >/dev/null
sitepost t_admin "tab=menus&area=account" -d action=reset_menu >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM site_menus")" = "0" ] && get t_admin admin/admin_dashboard.php | grep -q "admin_history.php"')" "คืนค่าเมนู"

echo "== E. คลังสื่อ โลโก้ ไอคอนแท็บ =="
sitepostF "tab=media" -F action=upload_media -F "media_file=@$S/photo.jpg;type=image/jpeg" -F "media_title=รูปทดสอบ" >/dev/null
MID=$(q "SELECT id FROM site_media ORDER BY id DESC LIMIT 1")
ok "$(yes_if '[ -n "$MID" ]')" "อัปโหลดรูปเข้าคลังสื่อ"
CT=$(curl -s -o /dev/null -w "%{content_type}" "$B/file.php?m=$MID")
ok "$(yes_if 'echo "$CT" | grep -q "^image/"')" "รูปในคลังเปิดได้แบบสาธารณะ ($CT)"
N0=$(q "SELECT COUNT(*) FROM site_media")
sitepostF "tab=media" -F action=upload_media -F "media_file=@$S/fake.jpg;type=image/jpeg" >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM site_media")" = "$N0" ]')" "ไฟล์ปลอมนามสกุล jpg ถูกปฏิเสธ"
sitepost t_admin "tab=identity" -d action=save_identity --data-urlencode 'site_name=ระบบแจ้งซ่อมทดสอบ' --data-urlencode 'site_tagline=คำโปรยทดสอบ' -d "logo_media=$MID" -d "favicon_media=$MID" >/dev/null
ok "$(yes_if '[ "$(setting site_logo_path)" = "$(q "SELECT file_path FROM site_media WHERE id=$MID")" ]')" "เลือกโลโก้จากคลังสื่อ"
PAGE=$(get t_user dashboard.php)
ok "$(yes_if 'echo "$PAGE" | grep -q "file.php?s=logo" && ! echo "$PAGE" | grep -q "reps-logo.svg"')" "โลโก้ใหม่แสดงแทนทุกจุดในหน้า"
ok "$(yes_if 'echo "$PAGE" | grep -q "rel=\"icon\" href=\"file.php?s=favicon\"" && [ "$(echo "$PAGE" | grep -c "rel=\"icon\"")" = "1" ]')" "favicon ใหม่ (แทนของเดิม ไม่ซ้ำ)"
ok "$(yes_if 'get t_staff staff/staff_dashboard.php | grep -q "../file.php?s=logo"')" "โลโก้ในหน้าโฟลเดอร์ย่อยใช้ path ถูกต้อง"
ok "$(yes_if 'curl -s "$B/index.php" | grep -q "คำโปรยทดสอบ"')" "คำโปรยแสดงใน footer หน้าแรก"
sitepost t_admin "tab=media" -d action=delete_media -d "media_id=$MID" >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM site_media WHERE id=$MID")" = "1" ]')" "ลบรูปที่ใช้เป็นโลโก้อยู่ไม่ได้"
sitepost t_admin "tab=identity" -d action=reset_identity >/dev/null
ok "$(yes_if '[ -z "$(setting site_logo_path)" ] && get t_user dashboard.php | grep -q "reps-logo.svg"')" "คืนค่าข้อมูลเว็บไซต์"
sitepost t_admin "tab=media" -d action=delete_media -d "media_id=$MID" >/dev/null
ok "$(yes_if '[ "$(q "SELECT COUNT(*) FROM site_media WHERE id=$MID")" = "0" ] && [ "$(curl -s -o /dev/null -w "%{http_code}" "$B/file.php?m=$MID")" = "404" ]')" "ลบรูปที่ไม่ได้ใช้ และไฟล์เปิดไม่ได้อีก"

echo "== F. Footer =="
sitepost t_admin "tab=footer" -d action=save_footer --data-urlencode 'org_name=วิทยาลัยทดสอบ' -d 'link_label[]=คู่มือ' -d 'link_url[]=https://example.com/doc' -d 'link_label[]=ติดต่อ' -d 'link_url[]=mailto:it@example.com' >/dev/null
FOOT=$(get t_user dashboard.php)
ok "$(yes_if 'echo "$FOOT" | grep -q "วิทยาลัยทดสอบ" && echo "$FOOT" | grep -q "https://example.com/doc" && echo "$FOOT" | grep -q "mailto:it@example.com"')" "ข้อมูลและลิงก์ใน footer"
sitepost t_admin "tab=footer" -d action=save_footer -d 'link_label[]=x' -d 'link_url[]=javascript:alert(1)' >/dev/null
ok "$(yes_if '! setting footer_links | grep -q javascript')" "ปฏิเสธลิงก์ javascript: ใน footer"
sitepost t_admin "tab=footer" -d action=reset_footer >/dev/null
ok "$(yes_if '[ -z "$(setting org_name)" ] && [ -z "$(setting footer_links)" ]')" "คืนค่า footer"

echo "== G. ประวัติการแก้ไข =="
REV1=$(q "SELECT COUNT(*) FROM site_revisions")
ok "$(yes_if '[ "$REV1" -gt "$REV0" ] && [ "$(q "SELECT COUNT(*) FROM site_revisions WHERE created_by IS NULL")" = "0" ]')" "ทุกการบันทึกเก็บประวัติพร้อมผู้แก้ ($((REV1-REV0)) รายการ)"

echo "RESULT: pass=$pass fail=$fail"
