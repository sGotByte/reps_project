<?php
declare(strict_types=1);

/**
 * REPS · includes/stock_alert.php — ข้อมูลวัสดุใกล้หมด (stock_qty <= min_stock) สำหรับกระดิ่งแจ้งเตือนและป้ายเมนู
 *
 *   $alert = stock_alert_load($conn);   // ['count' => int, 'out' => int, 'items' => [...]]
 */

const STOCK_ALERT_LIST_LIMIT = 6;

/** @return array{count:int, out:int, items:list<array<string,mixed>>} */
function stock_alert_load(mysqli $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = ['count' => 0, 'out' => 0, 'items' => []];
    try {
        $res = $conn->query(
            'SELECT id, name, unit, stock_qty, min_stock FROM inventory_items
             WHERE stock_qty <= min_stock
             ORDER BY (stock_qty = 0) DESC, stock_qty / GREATEST(min_stock, 1) ASC, name ASC'
        );
    } catch (Throwable $e) {
        error_log('stock_alert_load: ' . $e->getMessage());
        return $cache;
    }
    if (!$res instanceof mysqli_result) {
        return $cache;
    }
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
    $cache['count'] = count($rows);
    $cache['out'] = count(array_filter($rows, static fn($r) => (int)$r['stock_qty'] === 0));
    $cache['items'] = array_slice($rows, 0, STOCK_ALERT_LIST_LIMIT);
    $cache['sig'] = substr(hash('sha256', json_encode(array_map(
        static fn($r) => [(int)$r['id'], (int)$r['stock_qty']],
        $rows
    ))), 0, 16);
    return $cache;
}
