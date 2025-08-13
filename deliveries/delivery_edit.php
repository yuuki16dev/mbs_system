<!-- ============================= -->
<!-- delivery_edit.php : 納品書編集画面 -->
<!-- ============================= -->
<?php
// --- 納品ID取得 ---
$deliveryId = isset($_GET['no']) ? (int)$_GET['no'] : null;
$delivery = null;
$delivery_details = [];
$pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --- POSTで編集内容が送信された場合 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delivery_id'])) {
    $deliveryId = (int)$_POST['delivery_id'];
    // 明細取得（delivery_detail_id, delivery_idを含む）
    $stmt = $pdo->prepare('SELECT delivery_detail_id, delivery_id, product_name, quantity, unit_price, remarks FROM delivery_details WHERE delivery_id = ? ORDER BY delivery_detail_id ASC');
    $stmt->execute([$deliveryId]);
    $old_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 入力値取得
    $item_names = $_POST['item_name'];
    $quantities = $_POST['quantity'];
    $prices = $_POST['price'];
    $remarks = $_POST['remarks'];

    // 明細ごとに変更があればupdate（delivery_idとdelivery_detail_id両方一致する場合のみ）
    for ($i = 0; $i < count($old_details); $i++) {
        $detail = $old_details[$i];
        $new_name = isset($item_names[$i]) ? $item_names[$i] : '';
        $new_quantity = isset($quantities[$i]) ? $quantities[$i] : '';
        $new_price = isset($prices[$i]) ? preg_replace('/[^\d]/', '', $prices[$i]) : '';
        $new_remarks = isset($remarks[$i]) ? $remarks[$i] : '';

        // 変更判定（値が異なる場合のみupdate）
        if (
            $detail['product_name'] !== $new_name ||
            $detail['quantity'] != $new_quantity ||
            $detail['unit_price'] != $new_price ||
            $detail['remarks'] !== $new_remarks
        ) {
            // delivery_idとdelivery_detail_id両方一致する場合のみupdate
            $update = $pdo->prepare('UPDATE delivery_details SET product_name = ?, quantity = ?, unit_price = ?, remarks = ? WHERE delivery_detail_id = ? AND delivery_id = ?');
            $update->execute([$new_name, $new_quantity, $new_price, $new_remarks, $detail['delivery_detail_id'], $deliveryId]);
        }
    }
    // 編集後は納品表示画面へリダイレクト
    header('Location: delivery_display.php?no=' . $deliveryId);
    exit;
}

// --- 納品ヘッダー取得 ---
if ($deliveryId) {
    $stmt = $pdo->prepare('SELECT d.delivery_id AS no, d.delivery_date AS date, c.customer_id, c.name AS customer_name FROM deliveries d JOIN customers c ON d.customer_id = c.customer_id WHERE d.delivery_id = ?');
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    // 明細取得
    $stmt = $pdo->prepare('SELECT product_name, quantity, unit_price, remarks FROM delivery_details WHERE delivery_id = ? ORDER BY delivery_detail_id ASC');
    $stmt->execute([$deliveryId]);
    $delivery_details = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>納品書編集</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* ...delivery_create.phpのスタイルをそのまま流用... */
        /* 基本スタイル */
    .menu-button {
        font-size: 24px;
        background: none;
        border: none;
        cursor: pointer;
    }
    
    body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #fff; }

    main {
        width: 80%;
        margin: 20px auto;
        padding: 20px;
        /* background-color: #fff; 削除 */
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(255, 255, 255, 0.1);
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

    .table-container > div {
        display: flex;
        flex-direction: row;
        align-items: center;
        gap: 10px;
    }

    .table-container label { margin-bottom: 0; }
    .table-container input[type="number"],
    .table-container input[type="date"],
    .table-container input[type="text"] {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: auto; /* 幅を自動調整 */
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

    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: center; }
    th { background-color: #f2f2f2; }

    .input-field {
        width: 100%;
        border: none;
        background: none;
        text-align: center;
    }

    /* ==== ボタン共通スタイル ==== */
    .button,
    .create-button,
    .confirm,
    .cancel,
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

    /* ==== 主ボタン（作成・はい） ==== */
    .button,
    .create-button,
    .confirm {
        background-color: #fff;
        color: #333;
    }

    /* ==== 副ボタン（キャンセル・戻る） ==== */
    .cancel,
    .back-button {
        background-color: #fff;
        color: #333;
    }

    /* ==== ホバー効果 ==== */
    .button:hover,
    .create-button:hover,
    .confirm:hover {
        background-color: rgb(19, 207, 19);
        color: #fff;
    }

    .cancel:hover,
    .back-button:hover {
        background-color: #333;
        color: #fff;
    }

    /* 作成ボタンの固定配置 */
    .button-container {
        position: fixed;
        bottom: 30px;
        right: 20px;
    }

    /* モーダル背景 */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
    }

    /* モーダル本体 */
    .modal-content {
        background: #fff;
        padding: 20px;
        border-radius: 5px;
        text-align: center;
        width: 300px;
    }

    .modal-content p {
        margin: 20px 0;
    }

    .modal-buttons {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-top: 20px;
    }

    .modal-buttons .confirm, .modal-buttons .cancel {
        font-size: 1.2em;
        padding: 12px 36px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        color: #fff;
        font-weight: bold;
        transition: background 0.2s;
    }

    .modal-buttons .confirm {
        background: #28a745;
        box-shadow: 0 2px 8px rgba(40,167,69,0.15);
    }

    .modal-buttons .confirm:hover {
        background: #218838;
    }

    .modal-buttons .cancel {
        background: #6c757d;
    }

    .modal-buttons .cancel:hover {
        background: #495057;
    }

    /* 戻るボタンの位置調整 */
    .back-button {
        position: fixed;
        bottom: 30px;
        left: 20px;
    }

        /* 名前入力欄と「様」を横並びに */
    .name-row {
        display: flex;
        align-items: center;
        gap: 8px; /* 入力欄と「様」の間隔 */
        position: relative; /* サジェスト位置調整用 */
    }

    .search-bar {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
        flex-direction: column;
        gap: 20px; /* 要素間の隙間 */
    }
    .search-bar > div { /* 日付と検索キーワードのグループ */
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
    }
    .search-bar label { margin-bottom: 0; }
    .search-bar input[type="date"],
    .search-bar input[type="text"] {
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 4px;
        width: auto; /* 幅を自動調整 */
    }
    .search-bar button {
        padding: 8px 15px;
        background-color: #007bff;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.3s ease;
    }
    .search-bar button:hover { background-color: #0056b3; }

#name {
    width: 250px;
    padding: 6px 8px;
    font-size: 1.1em;
}

#nameSuggestions {
    position: absolute;
    top: 36px; /* 入力欄の下に表示 */
    left: 0;
    width: 250px;
    background: #fff;
    border: 1px solid #ccc;
    z-index: 10;
    max-height: 150px;
    overflow-y: auto;
}

#nameSuggestions div {
    padding: 8px 12px;
    border-bottom: 1px solid #eee;
    cursor: pointer;
}
#nameSuggestions div:hover {
    background: #e0f7fa;
}
    </style>
</head>
<body>
    <header>
        <!-- 画面タイトル -->
        <div class="home-title">納品編集</div>
    </header>
    <?php include('navbar.php'); ?>
    <main>
        <!-- 納品編集フォーム（既存データを編集） -->
        <form id="orderEditForm" method="post" action="delivery_edit.php">
            <!-- hidden: 納品ID（編集・削除時に使用） -->
            <input type="hidden" name="delivery_id" value="<?= htmlspecialchars($deliveryId) ?>">
            <input type="hidden" name="delete_flag" id="delete_flag" value="0">
            <!-- ここで既存納品データを取得し、各inputのvalue属性にセットする処理をPHPで記述してください -->
            <table>
                <div class="table-container">
                    <!-- 日付・顧客No・顧客名の入力欄 -->
                    <div style="display: flex; align-items: center; gap: 10px ;">
                        <label for="date">日付:</label>
                        <input type="date" id="date" name="date" value="<?= isset($delivery['date']) ? htmlspecialchars($delivery['date']) : '' ?>">
                    </div>
                    <div>
                        <label for="customer_no">顧客No</label>
                        <input type="number" min="0" id="customer_no" name="customer_no" value="<?= isset($delivery['customer_id']) ? htmlspecialchars($delivery['customer_id']) : '' ?>" readonly disabled>
                    </div>
                    <div class="name-row">
                        <input type="text" id="name" name="name" autocomplete="off" value="<?= isset($delivery['customer_name']) ? htmlspecialchars($delivery['customer_name']) : '' ?>" readonly disabled>
                        <label for="name">様</label>
                        <div id="nameSuggestions"></div>
                    </div>
                </div>
                <!-- 商品明細テーブル -->
                <tr>
                    <th colspan="2">商品名</th>
                    <th>数量</th>
                    <th>単価</th>
                    <th>摘要</th>
                </tr>
                <?php for ($i = 0; $i < 10; $i++): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><input type="text" name="item_name[]" class="input-field" value="<?= isset($delivery_details[$i]['product_name']) ? htmlspecialchars($delivery_details[$i]['product_name']) : '' ?>"></td>
                        <td><input type="number" min="0" name="quantity[]" class="input-field" value="<?= isset($delivery_details[$i]['quantity']) ? htmlspecialchars($delivery_details[$i]['quantity']) : '' ?>" oninput="removeLeadingZero(this); calculateTotal()"></td>
                        <td><input type="text" name="price[]" class="input-field" value="<?= isset($delivery_details[$i]['unit_price']) ? '￥'.number_format($delivery_details[$i]['unit_price']) : '' ?>" oninput="addYenSymbol(this); calculateTotal()"></td>
                        <td><input type="text" name="remarks[]" class="input-field" value="<?= isset($delivery_details[$i]['remarks']) ? htmlspecialchars($delivery_details[$i]['remarks']) : '' ?>"></td>
                    </tr>
                <?php endfor; ?>
                <tr>
                    <td colspan="2">合計</td>
                    <td colspan="1"><input type="text" name="total_quantity" class="input-field" value="<?php /* echo $order['total_quantity'] ?? ''; */ ?>" readonly></td>
                    <td colspan="1"><input type="text" name="grand_total" class="input-field" value="<?php /* echo $order['grand_total'] ?? ''; */ ?>" readonly></td>
                </tr>
                <tr>
                    <td colspan="2">備考</td>
                    <td colspan="3"><input type="text" name="notes" class="input-field" value="<?php /* echo $order['notes'] ?? ''; */ ?>"></td>
                </tr>
            </table>
            <!-- 保存ボタン・注文選択ボタン -->
            <div class="button-container">
                <button type="button" class="button" onclick="showModal()">保存</button>
                <!-- 注文選択画面へ遷移するボタン（保存ボタンの下・同じ大きさ） -->
                <button type="button" class="button" style="margin-top:16px;" onclick="location.href='order_selection.php'">注文選択</button>
            </div>
        </form>
    </main>
    <!-- 戻るボタン -->
    <button type="button" class="back-button" onclick="goBackToDisplay()">戻る</button>
    <!-- 保存確認モーダル -->
    <div class="modal" id="confirmationModal" style="display:none;">
        <div class="modal-content">
            <p>本当に保存しますか？</p>
            <div class="modal-buttons">
                <button class="cancel" onclick="closeModal()">いいえ</button>
                <button class="confirm" onclick="submitForm()">はい</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>

    <script>
        // --- 削除ボタン処理 ---
        function deleteDelivery() {
            if (confirm('本当に削除しますか？')) {
                document.getElementById('delete_flag').value = '1';
                document.getElementById('orderEditForm').submit();
            }
        }
        // --- 保存確認モーダル表示 ---
        function showModal() {
            document.getElementById('confirmationModal').style.display = 'flex';
        }
        // --- モーダルを閉じる ---
        function closeModal() {
            document.getElementById('confirmationModal').style.display = 'none';
        }
        // --- モーダル「はい」ボタンでフォーム送信 ---
        function submitForm() {
            document.getElementById('orderEditForm').submit();
        }
        // --- 数量欄の先頭ゼロを除去 ---
        function removeLeadingZero(input) {
            let value = input.value.toString();
            value = value.replace(/^0+(?!$)/, '');
            input.value = value;
        }
        // --- 金額欄に「￥」を自動付与しカンマ区切りに整形 ---
        function addYenSymbol(input) {
            const value = input.value.replace(/[^\d.]/g, '');
            if (value) {
                input.value = '￥' + parseFloat(value).toLocaleString();
            }
        }
        // --- 合計数量・金額を自動計算 ---
        function calculateTotal() {
            let quantities = document.getElementsByName("quantity[]");
            let prices = document.getElementsByName("price[]");
            let totalQuantity = 0;
            let grandTotal = 0;
            for (let i = 0; i < quantities.length; i++) {
                let qty = parseFloat(quantities[i].value) || 0;
                let price = parseFloat(prices[i].value.replace(/[^\d]/g, '')) || 0;
                totalQuantity += qty;
                grandTotal += qty * price;
            }
            let totalQuantityInput = document.getElementsByName("total_quantity")[0];
            let grandTotalInput = document.getElementsByName("grand_total")[0];
            if (totalQuantityInput) totalQuantityInput.value = totalQuantity;
            if (grandTotalInput) grandTotalInput.value = grandTotal ? '￥' + grandTotal.toLocaleString() : '';
        }
        // --- 入力値があるかチェックする関数 ---
        function isFormDirty() {
            const inputs = document.querySelectorAll('#orderEditForm input[type="text"], #orderEditForm input[type="number"], #orderEditForm input[type="date"]');
            for (let input of inputs) {
                if (input.value && !input.readOnly) return true;
            }
            return false;
        }

        // --- 戻るボタン押下時に納品表示画面へ値を保持したまま遷移 ---
        function goBackToDisplay() {
            if (isFormDirty()) {
                if (confirm('入力内容が破棄されます。よろしいですか？')) {
                    const params = new URLSearchParams();
                    params.set('no', <?= json_encode($deliveryId) ?>);
                    window.location.href = 'delivery_display.php?' + params.toString();
                }
            } else {
                const params = new URLSearchParams();
                params.set('no', <?= json_encode($deliveryId) ?>);
                window.location.href = 'delivery_display.php?' + params.toString();
            }
        }

        // --- ブラウザ戻る・リロード時は標準の確認ダイアログのみ利用 ---
        // --- ブラウザ戻る・リロード時は標準の確認ダイアログも出さない（戻るボタンでのみ確認） ---
        window.addEventListener('beforeunload', function(e) {
            // 何もしない（戻るボタンでのみ確認ダイアログを出す）
        });
    </script>
</body>
</html>
