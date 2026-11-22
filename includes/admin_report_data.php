<?php

/**
 * Load all data used by the admin report page and its PDF export.
 *
 * @return array{summary: array, statusRows: array, categories: array, daily: array, tickets: array}
 */
function load_admin_report_data(mysqli $conn, string $from, string $to): array
{
   $summary = [];
   $statusRows = [];
   $categories = [];
   $daily = [];
   $tickets = [];

   // 1. สรุปภาพรวมคำขอ
   $sql1 = 'SELECT
                  COUNT(*) AS total,
                  SUM(status = "completed") AS completed,
                  SUM(status IN ("pending", "in_progress")) AS open_count,
                  SUM(urgency = "urgent") AS urgent_count,
                  AVG(CASE WHEN completed_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, completed_at) END) AS avg_hours
               FROM tickets
               WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)';
   $stmt1 = $conn->prepare($sql1);
   $stmt1->bind_param('ss', $from, $to);
   $stmt1->execute();
   $summary = $stmt1->get_result()->fetch_assoc() ?? [];
   $stmt1->close();

   // 2. สรุปแยกตามสถานะ
   $sql2 = 'SELECT status, COUNT(*) AS total
               FROM tickets
               WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
               GROUP BY status';
   $stmt2 = $conn->prepare($sql2);
   $stmt2->bind_param('ss', $from, $to);
   $stmt2->execute();
   $res2 = $stmt2->get_result();
   while ($row = $res2->fetch_assoc()) {
      $statusRows[$row['status']] = (int)$row['total'];
   }
   $stmt2->close();

   // 3. สรุปแยกตามหมวดหมู่
   $sql3 = 'SELECT c.name, COUNT(t.id) AS total
               FROM tickets t
               JOIN categories c ON c.id = t.category_id
               WHERE t.created_at >= ? AND t.created_at < DATE_ADD(?, INTERVAL 1 DAY)
               GROUP BY c.id, c.name
               ORDER BY total DESC';
   $stmt3 = $conn->prepare($sql3);
   $stmt3->bind_param('ss', $from, $to);
   $stmt3->execute();
   $categories = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
   $stmt3->close();

   // 4. สรุปรายวัน
   $sql4 = 'SELECT DATE(created_at) AS day, COUNT(*) AS total
               FROM tickets
               WHERE created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)
               GROUP BY DATE(created_at)
               ORDER BY day DESC
               LIMIT 15';
   $stmt4 = $conn->prepare($sql4);
   $stmt4->bind_param('ss', $from, $to);
   $stmt4->execute();
   $daily = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);
   $stmt4->close();

   // 5. รายการคำขอล่าสุด 100 รายการ
   $sql5 = 'SELECT t.ticket_no, t.status, t.urgency, t.created_at, t.completed_at,
                      u.first_name, u.last_name, u.id_number, c.name AS category_name
               FROM tickets t
               JOIN users u ON u.user_id = t.requester_id
               JOIN categories c ON c.id = t.category_id
               WHERE t.created_at >= ? AND t.created_at < DATE_ADD(?, INTERVAL 1 DAY)
               ORDER BY t.created_at DESC
               LIMIT 100';
   $stmt5 = $conn->prepare($sql5);
   $stmt5->bind_param('ss', $from, $to);
   $stmt5->execute();
   $tickets = $stmt5->get_result()->fetch_all(MYSQLI_ASSOC);
   $stmt5->close();

   return compact('summary', 'statusRows', 'categories', 'daily', 'tickets');
}
