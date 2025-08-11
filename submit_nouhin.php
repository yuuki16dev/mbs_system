<?php
// submit_nouhin.php: 納品書作成データのDB登録処理

// DB接続
use Ramsey\Uuid\Uuid;
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // トランザクション開始
    $pdo->beginTransaction();

    $pdo->exec("SET SESSION sql_mode = ''");

    $pdo->exec("SET SESSION sql_mode = ''");

    // POSTデータ取得
    $customerId = $_POST['customer_no'] ?? null;
    $deliveryDate = $_POST['delivery_date'] ?? date('Y-m-d');
    // 備考欄は現在のフォームにないため空文字。必要であればフォームに追加してください。
    $remarks = ''; 

    // 入力チェック
    $item_names = $_POST['item_name'] ?? [];
    $hasItem = false;

    foreach($item_names as $item_name) {
        if (!empty(trim($item_name))) {
            $hasItem = true;
            break;
        }
    }

    if (!$customerId || !$hasItem) {
        // エラーメッセージは本番環境ではより具体的にしない方が良い場合もあります。

        throw new Exception("顧客Noまたは商品情報が不足しています。");
    }


    // deliveries テーブルに登録（delivery_idはauto_increment想定）
    $stmtDelivery = $pdo->prepare("INSERT INTO deliveries (customer_id, delivery_date, remarks) VALUES (?, ?, ?)");
    $stmtDelivery->execute([$customerId, $deliveryDate, $remarks]);
    $deliveryId = $pdo->lastInsertId();

    // delivery_details テーブルに登録
    $items = $_POST['item_name'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $prices = $_POST['price'] ?? [];
    $orderIds = $_POST['order_id'] ?? [];
    $orderDetailIds = $_POST['order_detail_id'] ?? [];
    $remarksArr = $_POST['remarks'] ?? [];

    $stmtDetail = $pdo->prepare("
        INSERT INTO delivery_details (delivery_detail_id, delivery_id, order_id, order_detail_id, product_name, quantity, unit_price, return_flag, remarks)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $deliveryDetailId = 1;
    for ($i = 0; $i < count($items); $i++) {
        $name = trim($items[$i]);
        $deliveryQuantity = (int)($quantities[$i] ?? 0);
        $price = (int)preg_replace('/[^0-9]/', '', $prices[$i] ?? '0');
        $orderId = isset($orderIds[$i]) && is_numeric($orderIds[$i]) && $orderIds[$i] > 0 ? (int)$orderIds[$i] : null;
        $orderDetailId = isset($orderDetailIds[$i]) && is_numeric($orderDetailIds[$i]) && $orderDetailIds[$i] > 0 ? (int)$orderDetailIds[$i] : null;
        $remarksVal = isset($remarksArr[$i]) ? $remarksArr[$i] : '';
        $returnFlag = 0;
        // 注文No(order_id)と注文明細No(order_detail_id)が両方とも有効な場合のみ登録
        if (
            $name !== '' &&
            $deliveryQuantity > 0 &&
            $orderId !== null &&
            $orderDetailId !== null
        ) {
            $stmtDetail->execute([
                $deliveryDetailId,
                $deliveryId,
                $orderId,
                $orderDetailId,
                $name,
                $deliveryQuantity,
                $price,
                $returnFlag,
                $remarksVal
            ]);
            $deliveryDetailId++;
        }
    }

    // 全ての処理が成功したらコミット
    $pdo->commit();

    // 登録成功後に納品一覧画面へリダイレクト
    // 成功したことを伝えるクエリパラメータを付与
    header("Location: delivery_list.php?success=1");
    exit;

} catch (Exception $e) {
    // エラーが発生した場合はロールバック
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドリング: エラーページにリダイレクトするか、エラーメッセージを表示
    http_response_code(500);
    error_log($e->getMessage()); // エラーログに記録

    // エラー詳細を画面に表示（開発用）
    echo "<div style='color:red; font-weight:bold; margin:2em;'>エラーが発生しました。<br>";
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "<br>";
    if ($e instanceof PDOException) {
        echo "<br>SQLSTATE: " . htmlspecialchars($e->getCode(), ENT_QUOTES, 'UTF-8') . "<br>";
        if (isset($stmtDetail) && $stmtDetail instanceof PDOStatement) {
            echo "<br>直前のSQL: " . htmlspecialchars($stmtDetail->queryString, ENT_QUOTES, 'UTF-8') . "<br>";
        }
    }
    echo "<pre>" . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . "</pre>";
    echo "</div>";
    exit;
}
?>

<!--
[PROMPT_SUGGESTION]delivery_list.php で登録した納品データを表示するにはどうすれば良いですか？[/PROMPT_SUGGESTION]
[PROMPT_SUGGESTION]注文選択画面で選択された注文IDと注文詳細IDを、納品明細に登録できるようにしてください。[/PROMPT_SUGGESTION]
-->