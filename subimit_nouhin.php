<?php

// DB接続情報
$host = 'localhost';
$dbname = 'mbs_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// POSTデータ取得
$customerId = $_POST['customer_no'] ?? null;
$deliveryDate = $_POST['delivery_date'] ?? date('Y-m-d');
$remarks = $_POST['notes'] ?? '';

// 注文書から選択された注文明細ID（order_detail_id）
$orderDetailIds = isset($_POST['order_ids']) ? explode(',', $_POST['order_ids']) : [];

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->beginTransaction();

    // deliveriesテーブルへのINSERT
    $stmtDeliveries = $pdo->prepare("
        INSERT INTO deliveries (customer_id, delivery_date, remarks)
        VALUES (:customer_id, :delivery_date, :remarks)
    ");
    $stmtDeliveries->execute([
        ':customer_id' => $customerId,
        ':delivery_date' => $deliveryDate,
        ':remarks' => $remarks
    ]);

    // INSERTしたレコードの主キー（delivery_id）を取得
    $deliveryId = $pdo->lastInsertId();

    // ... 次のステップ（delivery_detailsへのINSERT）へ続く ...

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドリング
    echo "保存に失敗しました: " . htmlspecialchars($e->getMessage());
    // ... 前のステップから続く ...

    // order_detailsから納品明細情報を取得
    // ここでは、order_detailsテーブルから選択された明細（product_name, quantity, unit_priceなど）を取得します。
    // 仮に注文明細IDがGETパラメータで渡されると想定
    $inClause = implode(',', array_fill(0, count($orderDetailIds), '?'));
    $stmtOrderDetails = $pdo->prepare("
        SELECT order_id, order_detail_id, product_name, quantity, unit_price
        FROM order_details
        WHERE order_detail_id IN ($inClause)
    ");
    $stmtOrderDetails->execute($orderDetailIds);
    $orderDetails = $stmtOrderDetails->fetchAll(PDO::FETCH_ASSOC);

    // delivery_detailsテーブルへのINSERT
    $stmtDetails = $pdo->prepare("
        INSERT INTO delivery_details (delivery_id, order_id, order_detail_id, product_name, delivery_quantity, unit_price)
        VALUES (:delivery_id, :order_id, :order_detail_id, :product_name, :delivery_quantity, :unit_price)
    ");
    
    foreach ($orderDetails as $item) {
        $stmtDetails->execute([
            ':delivery_id' => $deliveryId,
            ':order_id' => $item['order_id'],
            ':order_detail_id' => $item['order_detail_id'],
            ':product_name' => $item['product_name'],
            ':delivery_quantity' => $item['quantity'],
            ':unit_price' => $item['unit_price']
        ]);
    }

    // トランザクションをコミット
    $pdo->commit();
    echo "納品書が正常に作成されました。";

} catch (Exception $e) {
    // エラーハンドリング
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "保存に失敗しました: " . htmlspecialchars($e->getMessage());
}