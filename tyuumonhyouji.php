<?php
// PHPコードを最初に移動
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// DB接続情報

$host = 'localhost';
$dbname = 'mbs_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';


// GETパラメータからorder_idを取得
$orderId = isset($_GET['no']) ? (int)$_GET['no'] : null;
if (!$orderId) {
    header('Location: tyuumonitiran.php');
    exit;
}

try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // 注文情報取得（orders, customers）
    $stmt = $pdo->prepare('SELECT o.order_id, o.order_date, o.customer_id, c.name AS customer_name, o.remarks FROM orders o JOIN customers c ON o.customer_id = c.customer_id WHERE o.order_id = ?');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        // データがなければ一覧にリダイレクト
        header('Location: tyuumonitiran.php');
        exit;
    }
    // 注文明細取得（order_details）
    $stmt = $pdo->prepare('SELECT product_name, quantity, unit_price, remarks FROM order_details WHERE order_id = ?');
    $stmt->execute([$orderId]);
    $order_details = $stmt->fetchAll();
} catch (Exception $e) {
    // エラー時は一覧にリダイレクト
    header('Location: tyuumonitiran.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文書表示</title>
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

        .input-field {
            width: 100%;
            border: none;
            background: none;
            text-align: center;
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
            right: 8px;
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
        <div class="home-title">注文書表示</div>
    </header>

    <?php include('navbar.php'); ?>

    <main>
        <!-- 注文書の情報を表示（作成画面と同じレイアウト） -->
        <form>
            <div class="table-container">
                <div style="display: flex; align-items: center; gap: 10px ;">
                    <label for="date">日付:</label>
                    <input type="text" id="date" value="<?php echo htmlspecialchars($order['order_date']); ?>" readonly>
                </div>
                <div>
                    <label for="customer_no">顧客No</label>
                    <input type="text" id="customer_no" value="<?php echo htmlspecialchars($order['customer_id']); ?>" readonly>
                </div>
                <div class="name-row">
                    <input type="text" id="name" value="<?php echo htmlspecialchars($order['customer_name']); ?>" readonly>
                    <label for="name">様</label>
                </div>
                <div>
                    <label for="order_id">注文番号</label>
                    <input type="text" id="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>" readonly>
                </div>
            </div>

            <table>
                <tr>
                    <th>#</th>
                    <th>商品名</th>
                    <th>数量</th>
                    <th>単価</th>
                    <th>摘要</th>
                </tr>
                <?php 
                $total_qty = 0;
                $total_price = 0;
                for ($i = 0; $i < 10; $i++):
                    $detail = isset($order_details[$i]) ? $order_details[$i] : null;
                    $qty = $detail ? $detail['quantity'] : '';
                    $price = $detail ? $detail['unit_price'] : '';
                    $total_qty += $qty ? $qty : 0;
                    $total_price += ($qty && $price) ? $qty * $price : 0;
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><input type="text" name="item_name[]" class="input-field" value="<?= $detail ? htmlspecialchars($detail['product_name']) : '' ?>" readonly></td>
                    <td><input type="number" min="0" name="quantity[]" class="input-field" value="<?= $qty !== '' ? htmlspecialchars($qty) : '' ?>" readonly></td>
                    <td><input type="text" name="price[]" class="input-field" value="<?= $price !== '' ? number_format($price) : '' ?>" readonly></td>
                    <td><input type="text" name="remarks[]" class="input-field" value="<?= $detail ? htmlspecialchars($detail['remarks'] ?? '') : '' ?>" readonly></td>
                </tr>
                <?php endfor; ?>
                <tr>
                    <td colspan="2">合計</td>
                    <td colspan="1"><input type="text" name="total_quantity" class="input-field" value="<?= $total_qty ?>" readonly></td>
                    <td colspan="1"><input type="text" name="grand_total" class="input-field" value="<?= '￥' . number_format($total_price) ?>" readonly></td>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="2">備考</td>
                    <td colspan="3"><input type="text" name="notes" class="input-field" value="<?= htmlspecialchars($order['remarks'] ?? '') ?>" readonly></td>
                </tr>
            </table>
        </form>
        <div class="control-buttons">
            <button onclick="location.href='tyuumon_henshu.php?no=<?php echo $order['order_id']; ?>'">編集</button>
            <button type="button" id="deleteBtn">削除</button>
        </div>
        <!-- 削除確認モーダル -->
        <div class="modal" id="deleteModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.4); justify-content:center; align-items:center; z-index:1000;">
            <div class="modal-content" style="background:#fff; padding:32px 24px; border-radius:8px; text-align:center; min-width:300px;">
                <p style="font-size:1.2em;">本当に削除しますか？</p>
                <div class="modal-buttons" style="margin-top:20px; display:flex; gap:30px; justify-content:center;">
                    <button class="cancel" id="deleteCancelBtn" style="background:#6c757d; color:#fff; padding:10px 32px; border:none; border-radius:6px; font-size:1.1em;">いいえ</button>
                    <button class="confirm" id="deleteConfirmBtn" style="background:#28a745; color:#fff; padding:10px 32px; border:none; border-radius:6px; font-size:1.1em;">はい</button>
                </div>
            </div>
        </div>
        <a href="tyuumonitiran.php" class="back-button">戻る</a>
        <div class="modal" id="confirmationModal"></div>
    </main>


    <script src="script.js"></script>
    <script>
    // 削除ボタンのモーダル制御
    document.addEventListener('DOMContentLoaded', function() {
        var deleteBtn = document.getElementById('deleteBtn');
        var deleteModal = document.getElementById('deleteModal');
        var deleteCancelBtn = document.getElementById('deleteCancelBtn');
        var deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
        deleteBtn.addEventListener('click', function() {
            deleteModal.style.display = 'flex';
        });
        deleteCancelBtn.addEventListener('click', function() {
            deleteModal.style.display = 'none';
        });
        deleteConfirmBtn.addEventListener('click', function() {
            // 注文Noをパラメータで渡して論理削除（キャンセルフラグを1に）
            fetch('delete_order.php?no=<?php echo $order['order_id']; ?>', { method: 'POST' })
                .then(res => {
                    if (res.redirected) {
                        window.location.href = res.url;
                    } else {
                        window.location.href = 'tyuumonitiran.php';
                    }
                })
                .catch(() => {
                    window.location.href = 'tyuumonitiran.php';
                });
        });
    });
    </script>
</body>

</html>