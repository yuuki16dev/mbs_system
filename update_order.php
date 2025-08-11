<?php
// update_order.php : 注文編集の保存処理
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: order_list.php');
    exit;
}

$orderId = isset($_GET['no']) ? (int)$_GET['no'] : null;
if (!$orderId) {
    // 編集画面からPOSTの場合はhiddenでorder_idを送るのが安全
    $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : null;
}
if (!$orderId) {
    header('Location: order_list.php');
    exit;
}

$date = $_POST['date'] ?? '';
$customer_id = $_POST['customer_no'] ?? '';
$name = $_POST['name'] ?? '';
$item_names = $_POST['item_name'] ?? [];
$quantities = $_POST['quantity'] ?? [];
$prices = $_POST['price'] ?? [];
$remarks = $_POST['remarks'] ?? [];
$notes = $_POST['notes'] ?? '';
$order_detail_ids = $_POST['order_detail_id'] ?? [];

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
    $pdo->beginTransaction();
    // 注文本体UPDATE
    $stmt = $pdo->prepare('UPDATE orders SET order_date=?, customer_id=?, remarks=? WHERE order_id=?');
    $stmt->execute([$date, $customer_id, $notes, $orderId]);

    // 既存明細IDを取得
    $stmt = $pdo->prepare('SELECT order_detail_id FROM order_details WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $db_detail_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $used_ids = [];
    for ($i = 0; $i < count($item_names); $i++) {
        $item = trim($item_names[$i] ?? '');
        $qty = (int)($quantities[$i] ?? 0);
        $price = preg_replace('/[^\d]/', '', $prices[$i] ?? '');
        $price = $price === '' ? 0 : (int)$price;
        $remark = $remarks[$i] ?? '';
        $detail_id = isset($order_detail_ids[$i]) && $order_detail_ids[$i] !== '' ? (int)$order_detail_ids[$i] : null;
        if ($item !== '' && $qty > 0) {
            if ($order_detail_id !== null && $order_detail_id !== '' && in_array($order_detail_id, $order_detail_ids)) {
                // 既存明細はUPDATEのみ
                $stmt_upd = $pdo->prepare('UPDATE order_details SET product_name=?, quantity=?, unit_price=?, remarks=? WHERE order_id=? AND order_detail_id=?');
                $stmt_upd->execute([$item, $qty, $price, $remark, $orderId, $detail_id]);
                $used_ids[] = $detail_id;
            } else if ($detail_id === null || $detail_id === '') {
                // 新規明細のみINSERT（order_id内で最大+1を採番）
                $new_id = 1;
                if (!empty($db_detail_ids) || !empty($used_ids)) {
                    $new_id = max(array_merge($db_detail_ids, $used_ids)) + 1;
                }
                $stmt_ins = $pdo->prepare('INSERT INTO order_details (order_id, order_detail_id, product_name, quantity, unit_price, remarks) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt_ins->execute([$orderId, $new_id, $item, $qty, $price, $remark]);
                $used_ids[] = $new_id;
                $db_detail_ids[] = $new_id;
            }
        }
    }
    // 画面から消えた明細はDELETE
    // 画面から消えた明細のみDELETE（UPDATE/INSERTしたものは削除しない）
    $delete_ids = array_diff($db_detail_ids, $used_ids);
    if (!empty($delete_ids)) {
        $in = str_repeat('?,', count($delete_ids) - 1) . '?';
        $params = array_merge([$orderId], $delete_ids);
        $stmt_del = $pdo->prepare("DELETE FROM order_details WHERE order_id=? AND order_detail_id IN ($in)");
        $stmt_del->execute($params);
    }
    $pdo->commit();
    // 更新後は注文表示画面へ
    header('Location: order_display.php?no=' . $orderId);
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: order_display.php?no=' . $orderId . '&error=1');
    exit;
}
