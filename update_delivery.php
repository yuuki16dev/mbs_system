<?php
// update_delivery.php : 納品データの論理削除（return_flag更新）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $deliveryId = isset($_POST['delivery_id']) ? (int)$_POST['delivery_id'] : null;
    $deleteFlag = isset($_POST['delete_flag']) ? (int)$_POST['delete_flag'] : 0;
    if ($deliveryId && $deleteFlag === 1) {
        try {
            $pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // 納品明細テーブルのreturn_flagを1に更新（論理削除）
            $stmt = $pdo->prepare('UPDATE delivery_details SET return_flag = 1 WHERE delivery_id = ?');
            $stmt->execute([$deliveryId]);
            // 完了後、納品一覧画面へリダイレクト（必ず遷移）
            header('Location: delivery_list.php');
            exit;
        } catch (Exception $e) {
            // エラー時も納品一覧画面に遷移
            header('Location: delivery_list.php');
            exit;
        }
    } else {
        // 不正リクエスト時も納品一覧画面に遷移
        header('Location: delivery_list.php');
        exit;
    }
} else {
    // POST以外も納品一覧画面に遷移
    header('Location: delivery_list.php');
    exit;
}
?>
