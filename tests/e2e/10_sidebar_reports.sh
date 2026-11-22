#!/bin/bash
# แถบเมนูผู้ดูแลแบบใหม่ (5 กลุ่ม พับได้ ปุ่มตั้งค่าท้ายแถบ) + รายงานตามแผนก/งาน (สรุป ใบงานรายแผนก CSV สิทธิ์)
B="${REPS_BASE_URL:-http://127.0.0.1:8765}"
S="$1"; M="${MYSQL:-mysql}"
PW='Test1234!'
q(){ $M ${MYSQL_ARGS:--uroot} -N "${DB_NAME:-reps_v2_db}" -e "$1"; }
pass=0; fail=0
ok(){ if [ "$1" = "1" ]; then echo "  PASS $2"; pass=$((pass+1)); else echo "  FAIL $2"; fail=$((fail+1)); fi; }
csrf(){ grep -o 'name="csrf" value="[a-f0-9]*"' | head -1 | sed 's/.*value="//;s/"//'; }
login(){ local jar="$S/$1.jar"; rm -f "$jar"; local t; t=$(curl -s -c "$jar" -b "$jar" $B/login.php | csrf); curl -s -c "$jar" -b "$jar" -o /dev/null --data-urlencode "csrf=$t" --data-urlencode "username=$1" --data-urlencode "password=$2" $B/login.php; }
get(){ curl -s -c "$S/$1.jar" -b "$S/$1.jar" "$B/$2"; }
code(){ curl -s -o /dev/null -w "%{http_code}" -b "$S/$1.jar" "$B/$2"; }
yes_if(){ if eval "$1"; then echo 1; fi; }

for u in t_admin t_staff t_user; do login $u "$PW" >/dev/null; done

echo "== A. แถบเมนูผู้ดูแล =="
NAV=$(get t_admin admin/admin_dashboard.php | sed -n '/<aside id="sidebar"/,/<\/aside>/p')
ok "$(yes_if '[ "$(printf "%s" "$NAV" | grep -o "data-nav-group=\"[a-z]*\"" | tr "\n" " ")" = "data-nav-group=\"overview\" data-nav-group=\"repair\" data-nav-group=\"team\" data-nav-group=\"data\" data-nav-group=\"report\" " ]')" "เมนูแบ่ง 5 กลุ่มตามลำดับ: ภาพรวม งานซ่อม ทีมสตาฟ ข้อมูล รายงาน"
ok "$(yes_if 'printf "%s" "$NAV" | grep -q "aria-expanded=\"true\" aria-controls=\"navg-report\""')" "หัวกลุ่มเป็นปุ่มพับ/กางได้"
for item in "admin_reports.php" "admin_teacher_report.php" "admin_report_dept.php"; do
  ok "$(yes_if 'printf "%s" "$NAV" | sed -n "/navg-report\"/,/<\/div>/p" | grep -q "$item"')" "กลุ่มรายงานมี $item"
done
ok "$(yes_if '[ "$(printf "%s" "$NAV" | grep -c "aria-controls=\"navg-")" = "5" ] && ! printf "%s" "$NAV" | grep -q "admin_notifications.php\|warboard.php"')" "เอาหน้าการแจ้งเตือนและจอสรุปงานออกจากแถบเมนู"
ok "$(yes_if 'printf "%s" "$NAV" | grep -q "class=\"admin-foot-icon\"[^>]*aria-label=\"ตั้งค่าระบบ\"" && printf "%s" "$NAV" | grep -q "aria-label=\"ปรับแต่งเว็บ\""')" "ตั้งค่าระบบ/ปรับแต่งเว็บ เป็นปุ่มไอคอนท้ายแถบ"
ok "$(yes_if 'get t_admin staff/inventory.php | grep -q "href=\"../admin/admin_settings.php\" class=\"admin-foot-icon\""')" "ปุ่มตั้งค่าบนหน้าที่อยู่นอกโฟลเดอร์ admin ชี้ path ถูก"
ok "$(yes_if 'get t_admin admin/admin_settings.php | grep -q "admin_settings.php\" class=\"admin-foot-icon\" [^>]*aria-current=\"page\""')" "อยู่หน้าตั้งค่าระบบ ปุ่มไอคอนแสดงสถานะหน้าปัจจุบัน"
ok "$(yes_if 'get t_admin admin/admin_war_room.php | grep -q "warboard.php"')" "หน้าภาพรวมงานมีปุ่มเปิดจอสรุปงาน"
q "INSERT INTO site_menus (area, item_key, label, target, icon, group_key, sort_order, visible_roles, is_visible) VALUES ('admin', NULL, 'ลิงก์กลุ่มเดิมE2E', 'https://example.com/e2e', 'fa-link', 'manage', 999, 'admin', 1)"
ok "$(yes_if 'get t_admin admin/admin_dashboard.php | sed -n "/navg-data\"/,/<\/div>/p" | grep -q "ลิงก์กลุ่มเดิมE2E"')" "ลิงก์ที่ครูเพิ่มไว้ในกลุ่มเดิม (การจัดการ) ย้ายไปกลุ่มข้อมูล"
q "DELETE FROM site_menus WHERE label='ลิงก์กลุ่มเดิมE2E'"

echo "== B. รายงานตามแผนก/งาน =="
ok "$(yes_if '[ "$(code t_admin admin/admin_report_dept.php)" = "200" ]')" "ครูเปิดรายงานได้"
ok "$(yes_if '[ "$(code t_staff admin/admin_report_dept.php)" = "303" ] && [ "$(code t_user admin/admin_report_dept.php)" = "303" ]')" "สตาฟ/ผู้แจ้งเปิดไม่ได้"
FROM="2026-01-01"; TO="2026-12-31"
R="admin/admin_report_dept.php?term=range&from=$FROM&to=$TO"
EXP=$(q "SELECT COUNT(*) FROM tickets WHERE created_at BETWEEN '$FROM 00:00:00' AND '$TO 23:59:59'")
GOT=$(get t_admin "$R" | grep -o '<span>ใบงานทั้งหมด</span><strong>[0-9,]*' | sed 's/.*<strong>//;s/,//g')
ok "$(yes_if '[ "$GOT" = "$EXP" ]')" "ยอดใบงานทั้งหมดตรงกับฐานข้อมูล ($GOT)"
DID=$(q "SELECT u.department_id FROM tickets t JOIN users u ON u.user_id=t.requester_id WHERE u.department_id IS NOT NULL AND t.created_at BETWEEN '$FROM 00:00:00' AND '$TO 23:59:59' GROUP BY u.department_id ORDER BY COUNT(*) DESC LIMIT 1")
DN=$(q "SELECT COUNT(*) FROM tickets t JOIN users u ON u.user_id=t.requester_id WHERE u.department_id=$DID AND t.created_at BETWEEN '$FROM 00:00:00' AND '$TO 23:59:59'")
CSV=$(curl -s -b "$S/t_admin.jar" "$B/$R&export=csv")
DNAME=$(q "SELECT name FROM department WHERE d_id=$DID")
CSV_DEPT_MATCH=$(printf '%s' "$CSV" | "${PHP:-php}" -r '
$csv = preg_replace("/^\\xEF\\xBB\\xBF/", "", stream_get_contents(STDIN));
$stream = fopen("php://memory", "r+");
fwrite($stream, $csv);
rewind($stream);
while (($row = fgetcsv($stream)) !== false) {
    if (($row[0] ?? null) === $argv[1] && ($row[1] ?? null) === $argv[2]) {
        exit(0);
    }
}
exit(1);
' "$DNAME" "$DN" && echo 1 || echo 0)
ok "$CSV_DEPT_MATCH" "CSV: ยอดของแผนกที่แจ้งมากสุดตรงกับฐานข้อมูล ($DNAME $DN)"
ok "$(yes_if '[ "$(printf "%s" "$CSV" | head -c 3 | od -An -tx1 | tr -d " \n")" = "efbbbf" ]')" "CSV มี BOM ให้ Excel อ่านภาษาไทยถูก"
ok "$(yes_if 'curl -s -D - -o /dev/null -b "$S/t_admin.jar" "$B/$R&export=csv" | grep -qi "content-type: text/csv"')" "CSV ส่งเป็น text/csv"
LIST=$(get t_admin "$R&d=$DID")
ROWS=$(printf "%s" "$LIST" | sed -n '/id="dept-tickets"/,/<\/section>/p' | grep -c 'admin_manage_request.php?q=')
EXPROWS=$([ "$DN" -gt 100 ] && echo 100 || echo "$DN")
ok "$(yes_if '[ "$ROWS" = "$EXPROWS" ]')" "ดูใบงานของแผนก: แสดงครบ ($ROWS รายการ)"
ok "$(yes_if 'get t_admin "admin/admin_report_dept.php?term=range&from=2020-01-01&to=2026-12-31" | grep -q "ยาวเกิน 3 ปี"')" "ช่วงวันที่ยาวเกิน 3 ปี -> ปรับและแจ้งเตือน"
ok "$(yes_if '[ "$(code t_admin "admin/admin_report_dept.php?term=range&from=abc&to=1%27%20OR%201=1")" = "200" ]')" "วันที่ไม่ถูกต้อง -> ใช้ค่าเริ่มต้น ไม่ error"
q "INSERT INTO department (name) VALUES ('=HYPERLINK(E2E)')"
ok "$(yes_if 'curl -s -b "$S/t_admin.jar" "$B/$R&export=csv" | grep -q "^'"'"'=HYPERLINK(E2E),0,"')" "CSV กันสูตร Excel (ชื่อที่ขึ้นต้นด้วย = ใส่ ' นำหน้า)"
ok "$(yes_if 'get t_admin "$R" | grep -q "=HYPERLINK(E2E)"')" "แผนกที่ไม่มีใบงานก็แสดงในรายงาน (เป็น 0)"
q "DELETE FROM department WHERE name='=HYPERLINK(E2E)'"

echo "RESULT: pass=$pass fail=$fail"
