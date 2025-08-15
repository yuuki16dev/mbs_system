<!-- ============================= -->
<!-- order_edit.php : 注文書編集画面 -->
<!-- ============================= -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文書編集</title>
    <link rel="stylesheet" href="/mbs_system/style.css">
</head>
<body class="order-edit-page">
    <header>
        <!-- 画面タイトル -->
        <div class="home-title">注文編集</div>
    </header>
    <?php include('../navbar.php'); ?>
    <?php
    // --- 注文No取得 ---
    $orderId = isset($_GET['no']) ? (int)$_GET['no'] : null;
    $order = null;
    $order_details = [];
    if ($orderId) {
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
            // 注文情報取得
            $stmt = $pdo->prepare('SELECT o.order_id, o.order_date, o.customer_id, c.name AS customer_name, o.remarks FROM orders o JOIN customers c ON o.customer_id = c.customer_id WHERE o.order_id = ?');
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            // 注文明細取得
            $stmt = $pdo->prepare('SELECT product_name, quantity, unit_price, remarks FROM order_details WHERE order_id = ?');
            $stmt->execute([$orderId]);
            $order_details = $stmt->fetchAll();
        } catch (Exception $e) {
            $order = null;
            $order_details = [];
        }
    }
    ?>
    <main>
        <!-- 注文編集フォーム（既存データを編集） -->
        <form id="orderEditForm" method="post" action="update_order.php">
            <!-- 注文Noをhiddenで保持 -->
            <input type="hidden" name="order_id" value="<?php echo isset($order['order_id']) ? (int)$order['order_id'] : 0; ?>">
            <div class="table-container">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label for="date">日付:</label>
                    <input type="date" id="date" name="date" value="<?php echo isset($order['order_date']) ? htmlspecialchars($order['order_date']) : ''; ?>">
                </div>
                <div>
                    <label for="customer_no">顧客No</label>
                    <input type="number" min="0" id="customer_no" name="customer_id" value="<?php echo isset($order['customer_id']) ? htmlspecialchars($order['customer_id']) : ''; ?>" readonly>
                </div>
                <div class="name-row">
                    <input type="text" id="name" name="name" autocomplete="off" value="<?php echo isset($order['customer_name']) ? htmlspecialchars($order['customer_name']) : ''; ?>" readonly>
                    <label for="name">様</label>
                    <div id="nameSuggestions"></div>
                </div>
            </div>
            <table>
                <tr>
                    <th colspan="2">商品名</th>
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
                    <td><input type="text" name="item_name[]" class="input-field" value="<?= $detail ? htmlspecialchars($detail['product_name']) : '' ?>"></td>
                    <td><input type="number" min="0" name="quantity[]" class="input-field" value="<?= $qty !== '' ? htmlspecialchars($qty) : '' ?>" oninput="removeLeadingZero(this); calculateTotal()"></td>
                    <td><input type="text" name="price[]" class="input-field" value="<?= $price !== '' ? number_format($price) : '' ?>" oninput="formatPriceInput(this); calculateTotal()"></td>
                    <td><input type="text" name="remarks[]" class="input-field" value="<?= $detail ? htmlspecialchars($detail['remarks'] ?? '') : '' ?>"></td>
                </tr>
                <?php endfor; ?>
                <tr>
                    <td colspan="2">合計</td>
                    <td colspan="1"><input type="text" name="total_quantity" class="input-field" value="<?= $total_qty ?>" readonly></td>
                    <td colspan="1"><input type="text" name="grand_total" class="input-field" value="<?= '￥' . number_format($total_price) ?>" readonly></td>
                </tr>
                <tr>
                    <td colspan="2">備考</td>
                    <td colspan="3"><input type="text" name="notes" class="input-field" value="<?= isset($order['remarks']) ? htmlspecialchars($order['remarks']) : '' ?>"></td>
                </tr>
            </table>
            <!-- 保存ボタン -->
            <div class="button-container">
                <button type="button" class="button" onclick="showModal()">保存</button>
            </div>
        </form>
    </main>
    <!-- 戻るボタン -->
    <a href="order_display.php" class="back-button">戻る</a>
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

    <script src="../script.js"></script>

    <script>
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
            // window.location.href = 'order_display.php'; // ここで遷移させるとPOSTが無効化されるため削除
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

        function formatPriceInput(input) {
        let value = input.value.replace(/[^\d]/g, '');
        if (value) {
            input.value = '￥' + Number(value).toLocaleString();
        } else {
            input.value = '';
        }
    } 

        // --- 戻るボタン押下時の確認モーダル ---
        document.querySelector('.back-button').addEventListener('click', function(e) {
            e.preventDefault();
            var orderId = "<?php echo isset($order['order_id']) ? (int)$order['order_id'] : 0; ?>";
            if (isFormDirty()) {
                // 既存のモーダルがあれば削除
                let oldModal = document.getElementById('backConfirmModal');
                if (oldModal) oldModal.remove();
                // モーダル生成
                const modal = document.createElement('div');
                modal.id = 'backConfirmModal';
                modal.style.position = 'fixed';
                modal.style.top = 0;
                modal.style.left = 0;
                modal.style.width = '100vw';
                modal.style.height = '100vh';
                modal.style.background = 'rgba(0,0,0,0.4)';
                modal.style.display = 'flex';
                modal.style.justifyContent = 'center';
                modal.style.alignItems = 'center';
                modal.innerHTML = `
                    <div style="background:#fff;padding:32px 24px;border-radius:8px;text-align:center;min-width:300px;">
                        <p style="font-size:1.2em;">入力内容が破棄されます。よろしいですか？</p>
                        <div style="margin-top:20px;display:flex;gap:30px;justify-content:center;">
                            <button id="backNoBtn" style="background:#6c757d;color:#fff;padding:10px 32px;border:none;border-radius:6px;font-size:1.1em;">いいえ</button>
                            <button id="backYesBtn" style="background:#28a745;color:#fff;padding:10px 32px;border:none;border-radius:6px;font-size:1.1em;">はい</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                document.getElementById('backYesBtn').onclick = function() {
                    document.getElementById('orderEditForm').reset();
                    if (orderId && orderId !== "0") {
                        window.location.href = 'order_display.php?no=' + encodeURIComponent(orderId);
                    } else {
                        window.location.href = 'order_display.php';
                    }
                    modal.remove();
                };
                document.getElementById('backNoBtn').onclick = function() {
                    modal.remove();
                };
            } else {
                if (orderId && orderId !== "0") {
                    window.location.href = 'order_display.php?no=' + encodeURIComponent(orderId);
                } else {
                    window.location.href = 'order_display.php';
                }
            }
        });

        // --- ブラウザ戻る・リロード時は標準の確認ダイアログのみ利用 ---
        window.addEventListener('beforeunload', function(e) {
            if (isFormDirty()) {
                e.preventDefault();
                e.returnValue = '';
                // カスタムモーダルは出さず、標準の確認ダイアログのみ
            }
        });
    </script>
</body>
</html>
