<?php
declare(strict_types=1);

/**
 * helper สำหรับ prepared statement ที่โยน RuntimeException เมื่อผิดพลาด
 * (โปรเจกต์ปิด mysqli_report ไว้ จึงต้องตรวจผลเองทุกครั้ง)
 */

function db_stmt(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $conn->error);
    }
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new RuntimeException('Execute failed: ' . $error);
    }
    return $stmt;
}

/** คืนแถวแรก หรือ null */
function db_one(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = db_stmt($conn, $sql, $types, $params);
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function db_all(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = db_stmt($conn, $sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** รันคำสั่งเขียนข้อมูล คืนจำนวนแถวที่เปลี่ยน */
function db_exec(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = db_stmt($conn, $sql, $types, $params);
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function db_insert(mysqli $conn, string $sql, string $types = '', array $params = []): int
{
    $stmt = db_stmt($conn, $sql, $types, $params);
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return $id;
}

/** รันฟังก์ชันใน transaction: commit เมื่อสำเร็จ rollback เมื่อมี exception */
function db_transaction(mysqli $conn, callable $fn): mixed
{
    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Could not start transaction: ' . $conn->error);
    }
    try {
        $result = $fn();
        if (!$conn->commit()) {
            throw new RuntimeException('Commit failed: ' . $conn->error);
        }
        return $result;
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

function client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}
