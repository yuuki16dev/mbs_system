<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'localhost';
$dbname = 'mbs_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

// GETパラメータからdelivery_idを取得
$deliveryId = isset($_GET['no']) ? (int)$_GET['no'] : null;
$delivery = null;
$delivery_details = [];
$errorMsg = '';
if ($deliveryId) {
    try {
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        // 納品書ヘッダー取得
        $stmt = $pdo->prepare('SELECT d.delivery_id AS no, d.delivery_date AS order_date, c.customer_id, c.name AS customer_name FROM deliveries d JOIN customers c ON d.customer_id = c.customer_id WHERE d.delivery_id = ?');
        $stmt->execute([$deliveryId]);
        $delivery = $stmt->fetch();
        if ($delivery) {
            // 単価（unit_price）と摘要（remarks）を取得
            $stmt = $pdo->prepare('SELECT product_name, quantity, unit_price, remarks FROM delivery_details WHERE delivery_id = ? ORDER BY delivery_detail_id ASC');
            $stmt->execute([$deliveryId]);
            $delivery_details = $stmt->fetchAll();
        } else {
            $errorMsg = '該当する納品データがありません。';
        }
    } catch (Exception $e) {
        $errorMsg = 'データベースエラー: ' . htmlspecialchars($e->getMessage());
    }
} else {
    $errorMsg = 'URLに納品番号(no)パラメータがありません。';
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>納品書表示</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }

        main {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            /* background-color: #fff; 削除 */
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(255, 255, 255, 0.1);
        }

        header {
            display: block;
            margin-bottom: 20px;
        }

        header h1 {
            font-size: 28px;
            margin: 0;
            text-align: left;
            margin-bottom: 10px;
        }


        /* フォーム全体のレイアウト */
        .table-container {
            margin: 0 auto;
            width: 80%;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            margin-bottom: 20px;
            margin-top: 60px;
            gap: 20px;
        }

        .table-container>div {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
        }

        .table-container label {
            margin-bottom: 0;
        }

        .table-container input[type="number"],
        .table-container input[type="date"],
        .table-container input[type="text"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: auto;
            /* 幅を自動調整 */
        }

        .table-container label,
        .table-container input {
            margin-bottom: 30px;
        }

        .table-container input {
            padding: 8px;
            width: 40%;
        }

        .table-container input[type="date"] {
            width: 60%;
        }

        /* テーブルの見た目 */
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
            background-color: #fff;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f2f2f2;
        }

        table th:nth-child(1),
        table td:nth-child(1) {
            width: 50px;
            max-width: 50px;
            min-width: 30px;
            white-space: nowrap;
            text-align: center;
        }

        /* ==== ボタン共通スタイル ==== */
        .button,
        .create-button,
        .confirm,
        .back-button {
            font-size: 16px;
            padding: 25px 25px;
            border-radius: 6px;
            border: 2px solid #333;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* ==== 副ボタン（キャンセル・戻る） ==== */
        .cancel,
        .back-button {
            background-color: #fff;
            color: #333;
        }

        /* 戻るボタンの位置調整 */
        .back-button {
            position: fixed;
            bottom: 30px;
            left: 20px;
        }

        .cancel:hover,
        .back-button:hover {
            background-color: #333;
            color: #fff;
        }

        /* ==== ホバー効果 ==== */
        .button:hover,
        .create-button:hover,
        .confirm:hover {
            background-color: rgb(19, 207, 19);
            color: #fff;
        }

        .control-buttons {
            position: absolute;
            right: 40px;
            top: 200px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .control-buttons button {
            padding: 20px 40px;
            font-size: 24px;
            border: 2px solid #333;
            background-color: #fff;
            cursor: pointer;
            border-radius: 8px;
        }

        .control-buttons button:hover {
            background-color: #333;
            color: white;
        }



        .note {
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <header>
        <div class="home-title">納品書表示</div>
    </header>

    <?php include('navbar.php'); ?>


    <main>
        <?php if ($errorMsg): ?>
            <div style="color:red; font-weight:bold; margin:40px; text-align:center; font-size:1.2em;">
                <?php echo htmlspecialchars($errorMsg); ?>
            </div>
        <?php else: ?>
            <!-- 注文書の情報を表示 -->
            <div class="table-container">
                <div style="display: flex; align-items: center; gap: 10px ;">
                    <strong>納品番号：</strong>
                    <?php echo htmlspecialchars($delivery['no']); ?>
                </div>
                <div>
                    <strong>納品日：</strong>
                    <?php echo htmlspecialchars($delivery['order_date']); ?>
                </div>
                <div>
                    <strong>顧客No：</strong>
                    <?php echo htmlspecialchars($delivery['customer_id']); ?>
                </div>
                <div>
                    <strong>顧客名：</strong>
                    <?php echo htmlspecialchars($delivery['customer_name']) . "様"; ?>
                </div>
            </div>

            <div class="note">下記の通り納品いたします</div>

            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>商品名</th>
                        <th>数量</th>
                        <th>単価</th>
                        <th>摘要</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalQty = 0;
                    $totalAmount = 0;
                    if (!empty($delivery_details)) {
                        $rowNo = 1;
                        foreach ($delivery_details as $detail) {
                            // 数量は数値型で取得し、空やnullの場合は0とする
                            $qty = isset($detail['quantity']) && is_numeric($detail['quantity']) ? (float)$detail['quantity'] : 0;
                            $unitPrice = isset($detail['unit_price']) && is_numeric($detail['unit_price']) ? (int)$detail['unit_price'] : '';
                            $remarks = isset($detail['remarks']) ? $detail['remarks'] : '';
                            $totalQty += $qty;
                            if ($unitPrice !== '') {
                                $totalAmount += $qty * $unitPrice;
                            }
                            echo '<tr>';
                            echo '<td>' . $rowNo . '</td>';
                            echo '<td>' . htmlspecialchars($detail['product_name']) . '</td>';
                            echo '<td>' . rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.') . '</td>';
                            echo '<td>' . ($unitPrice !== '' ? '￥' . number_format($unitPrice) : '') . '</td>';
                            echo '<td>' . htmlspecialchars($remarks) . '</td>';
                            echo '</tr>';
                            $rowNo++;
                        }
                        // 空行で10行まで埋める
                        for ($i = $rowNo; $i <= 10; $i++) {
                            echo '<tr>';
                            echo '<td>' . $i . '</td>';
                            echo '<td></td><td></td><td></td><td></td>';
                            echo '</tr>';
                        }
                    } else {
                        // データがない場合は空行10行
                        for ($i = 1; $i <= 10; $i++) {
                            echo '<tr>';
                            echo '<td>' . $i . '</td>';
                            echo '<td></td><td></td><td></td><td></td>';
                            echo '</tr>';
                        }
                    }
                    ?>
                    <tr>
                        <td colspan="2">合計</td>
                        <td><?php echo $totalQty; ?></td>
                        <td>￥<?php echo number_format($totalAmount); ?></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="2">備考</td>
                        <td colspan="3"></td>
                    </tr>
                </tbody>
            </table>

            <div class="control-buttons">
                <button onclick="location.href='delivery_edit.php?no=<?php echo $delivery['no']; ?>'">編集</button>
                <form id="deleteDeliveryForm" method="post" action="update_delivery.php" style="margin:0;">
                    <input type="hidden" name="delivery_id" value="<?php echo htmlspecialchars($delivery['no']); ?>">
                    <input type="hidden" name="delete_flag" value="1">
                    <button type="button" onclick="deleteDelivery()">削除</button>
                </form>
                <script>
                function deleteDelivery() {
                    if (confirm('本当に削除しますか？')) {
                        document.getElementById('deleteDeliveryForm').submit();
                    }
                }
                </script>
            </div>

        <?php endif; ?>
        <a href="delivery_list.php" class="back-button">戻る</a>
        <div class="modal" id="confirmationModal">
    </main>


    <script src="script.js"></script>
</body>

</html>