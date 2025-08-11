<?php
// delete_order.php : 注文論理削除（キャンセルフラグを1に）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// POST/GET両対応
// fetchのPOSTリクエストでもnoはクエリで渡されるため、$_GET['no']で取得
$orderId = isset($_GET['no']) ? (int)$_GET['no'] : null;
if (!$orderId) {
    http_response_code(400);
    exit('注文番号が指定されていません');
}

$host = 'localhost';
$dbname = 'mbs_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    // 注文明細のみ論理削除（キャンセルフラグを1に）
    $stmt = $pdo->prepare('UPDATE order_details SET cancel_flag = 1 WHERE order_id = ?');
    $stmt->execute([$orderId]);
    // 一覧画面へリダイレクト
    header('Location: tyuumonitiran.php');
    exit;
} catch (Exception $e) {
    http_response_code(500);
    header('Location: tyuumonitiran.php?error=1');
    exit;
}
