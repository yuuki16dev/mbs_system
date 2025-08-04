<?php
// DB接続
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', ''); // ← ここでDB名を mbs_db に設定
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // トランザクション開始
    $pdo->beginTransaction();

    // POSTデータ取得
    $customerId = $_POST['customer_id'] ?? null;
    $orderDate = $_POST['date'] ?? date('Y-m-d');
    $remarks = $_POST['notes'] ?? '';

    // 入力チェック
    if (!$customerId || empty($_POST['item_name'])) {
        throw new Exception("顧客Noまたは商品情報が不足しています。");
    }

    // 注文テーブルに登録
    $stmtOrder = $pdo->prepare("INSERT INTO orders (customer_id, order_date, remarks) VALUES (?, ?, ?)");
    $stmtOrder->execute([$customerId, $orderDate, $remarks]);
    $orderId = $pdo->lastInsertId();

    // 注文明細の登録
    $items = $_POST['item_name'];
    $quantities = $_POST['quantity'];
    $prices = $_POST['price'];
    $itemRemarks = $_POST['remarks'];

    $stmtDetail = $pdo->prepare("
        INSERT INTO order_details (order_id, order_detail_id, product_name, quantity, unit_price, remarks)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $detailId = 1;
    for ($i = 0; $i < count($items); $i++) {
        $name = trim($items[$i]);
        $qty = (int)$quantities[$i];
        $price = (int)preg_replace('/[^\d]/', '', $prices[$i]); // ￥やカンマを除去
        $note = trim($itemRemarks[$i]);

        if ($name !== '' && $qty > 0) {
            $stmtDetail->execute([$orderId, $detailId, $name, $qty, $price, $note]);
            $detailId++;
        }
    }

    // コミット
    $pdo->commit();

    // 登録成功後に注文一覧画面へリダイレクト
    header("Location: tyuumon.php?success=1");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo "エラーが発生しました: " . htmlspecialchars($e->getMessage());
}
?>
