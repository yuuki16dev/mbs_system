<?php
header('Content-Type: application/json; charset=utf-8');
$keyword = $_GET['keyword'] ?? '';
if ($keyword === '') {
    echo json_encode([]);
    exit;
}
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');
    $stmt = $pdo->prepare("SELECT customer_id, name, address, phone_number FROM customers WHERE name LIKE ? ORDER BY customer_id ASC LIMIT 20");
    $stmt->execute(['%' . $keyword . '%']);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // address, phone_numberがnullの場合は空文字に変換
    foreach ($result as &$row) {
        if (!isset($row['address']) || $row['address'] === null) $row['address'] = '';
        if (!isset($row['phone_number']) || $row['phone_number'] === null) $row['phone_number'] = '';
        // phone_numberをphoneとしても返す（フロント互換）
        $row['phone'] = $row['phone_number'];
    }
    unset($row);
    echo json_encode($result);
} catch (Exception $e) {
    echo json_encode([]);
}
?>