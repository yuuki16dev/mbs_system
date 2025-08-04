<?php
// データベース接続情報
// 実際の接続情報に合わせて修正してください
$dsn = 'mysql:host=localhost;dbname=mbs_db;charset=utf8mb4';
$user = 'root';
$password = '';

try {
    // データベースに接続
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // トランザクション開始
    $pdo->beginTransaction();

    // POSTデータから顧客IDと注文明細IDの配列を取得
    // 'customer_id' は、納品先の顧客を特定するために必要です
    // 'order_detail_ids' は、どの注文明細を納品するかのIDリストです
    if (!isset($_POST['customer_id']) || !isset($_POST['order_detail_ids']) || !is_array($_POST['order_detail_ids'])) {
        // エラーログに出力し、詳細なエラーメッセージを返す
        error_log("Missing required data: customer_id or order_detail_ids");
        throw new Exception("必要なデータが不足しています (customer_id または order_detail_ids)。");
    }

    $customer_id = $_POST['customer_id'];
    $order_detail_ids = $_POST['order_detail_ids']; // これは配列

    // delivery_date の取得 (フォームから送られてくる場合は $_POST['delivery_date'] を使用)
    // ここでは現在の日付を使用
    $delivery_date = date('Y-m-d');
    // remarks (備考) もフォームから取得できるようにする。なければ空文字列。
    $remarks_delivery = isset($_POST['delivery_remarks']) ? $_POST['delivery_remarks'] : '';

    // deliveriesテーブルに挿入
    // テーブル名を 'delivery' から 'deliveries' に修正
    // カラム名を 'delivery_address_id' から 'customer_id' に修正
    // 'delivery_status' はスキーマに存在しないため削除
    $sql_deliveries = "INSERT INTO deliveries (customer_id, delivery_date, remarks) VALUES (?, ?, ?)";
    $stmt_deliveries = $pdo->prepare($sql_deliveries);
    $stmt_deliveries->execute([$customer_id, $delivery_date, $remarks_delivery]);

    // 最後に挿入されたdelivery_idを取得
    $delivery_id = $pdo->lastInsertId();

    // delivery_detailsテーブルに挿入する前に、
    // 関連するorder_detailsの情報を取得
    // これにより、product_name, quantity, unit_priceを取得できる
    $placeholders = implode(',', array_fill(0, count($order_detail_ids), '?'));
    $sql_get_order_details = "
        SELECT
            order_id,          -- delivery_detailsに必要なorder_id
            order_detail_id,
            product_name,
            quantity,
            unit_price,
            remarks            -- order_detailsのremarksも取得してdelivery_detailsに渡す
        FROM
            order_details
        WHERE
            order_detail_id IN ($placeholders)
    ";
    $stmt_get_order_details = $pdo->prepare($sql_get_order_details);
    $stmt_get_order_details->execute($order_detail_ids);
    $orderDetailsToDeliver = $stmt_get_order_details->fetchAll();

    // delivery_detailsテーブルに挿入
    // スキーマに合わせて product_name, quantity, unit_price, remarks, return_flag も含める
    $sql_delivery_details = "
        INSERT INTO delivery_details
            (delivery_id, order_id, order_detail_id, product_name, quantity, unit_price, remarks, return_flag)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, 0) -- return_flagはデフォルト0 (未返品)
    ";
    $stmt_delivery_details = $pdo->prepare($sql_delivery_details);

    foreach ($orderDetailsToDeliver as $detail) {
        $stmt_delivery_details->execute([
            $delivery_id,
            $detail['order_id'], // orders.order_id を delivery_details.order_id に設定
            $detail['order_detail_id'],
            $detail['product_name'],
            $detail['quantity'],
            $detail['unit_price'],
            $detail['remarks'] // order_detailsのremarksをdelivery_detailsのremarksに設定
        ]);
    }
    
    // order_detailsテーブルの納品ステータスを更新
    // スキーマに合わせて 'shipped_flag' を 'delivery_status' に修正し、値を1に設定 (納品済み)
    $sql_update_order_details = "UPDATE order_details SET delivery_status = 1 WHERE order_detail_id = ?";
    $stmt_update_order_details = $pdo->prepare($sql_update_order_details);

    foreach ($order_detail_ids as $order_detail_id) {
        $stmt_update_order_details->execute([$order_detail_id]);
    }

    // コミット
    $pdo->commit();

    // 成功レスポンス
    echo json_encode(['status' => 'success', 'message' => '納品データが正常に登録されました。']);

} catch (PDOException $e) {
    // ロールバック
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーレスポンス
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'データベースエラー: ' . $e->getMessage()]);
    // デバッグのため、エラーログにも出力
    error_log("PDOException in submit_delivery.php: " . $e->getMessage());
} catch (Exception $e) {
    // その他の例外（例: 必要なデータ不足）
    // ロールバック
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'エラー: ' . $e->getMessage()]);
    // デバッグのため、エラーログにも出力
    error_log("Exception in submit_delivery.php: " . $e->getMessage());
}
?>