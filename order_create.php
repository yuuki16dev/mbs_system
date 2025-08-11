<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文書作成</title>
    <link rel="stylesheet" href="styles.css">  <!-- 共通のCSSファイル -->
    <style>
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
        <div class="home-title">注文作成</div>
    </header>
    <?php include('navbar.php'); ?>  <!-- 共通のナビゲーションバー（ハンバーガーメニュー）をインクルード -->

    <main>
        <form id="orderForm" method="post" action="submit_order.php">
            <!-- 顧客情報入力フォーム -->
            <table>
                <div class="table-container">
                    
                    <div style="display: flex; align-items: center; gap: 10px ;">
                        <label for="date">日付:</label>
                        <input type="date" id="date" name="date">
                    </div>
                    
                    <div>
                        <label for="customer_no">顧客No</label>
                        <input type="number" min="0" id="customer_no" name="customer_id" readonly>
                    </div>
                    <div class="name-row">
                        <input type="text" id="name" name="name" autocomplete="off">
                        <label for="name">様</label>
                        <div id="nameSuggestions"></div>
                    </div>
                </div>


            <!-- 注文内容のテーブル -->

                <tr>
                    
                    <th colspan="2">商品名</th>
                    <th>数量</th>
                    <th>単価</th>
                    <th>摘要</th>
                </tr>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <tr>
                        <td><?= $i ?></td>
                            <td><input type="text" name="item_name[]" class="input-field"></td>
                            <td><input type="number" min="0" name="quantity[]" class="input-field" oninput="calculateTotal()"></td>
                            <td><input type="text" name="price[]" class="input-field" oninput="addYenSymbol(this); calculateTotal()"></td>
                            <td><input type="text" name="remarks[]" class="input-field"></td>
                    </tr>
                <?php endfor; ?>
                </script>
                <tr>
                    <td colspan="2">合計</td>
                    <td colspan="1"><input type="text" name="total_quantity" class="input-field" readonly></td>
                    <td colspan="1"><input type="text" name="grand_total" class="input-field" readonly></td>
                </tr>
                <tr>
                    <td colspan="2">備考</td>
                    <td colspan="3"><input type="text" name="notes" class="input-field"></td>
                </tr>
                
            </table>
            <div class="button-container">
                <button type="button" class="button" onclick="showModal()">作成</button>
            </div>
        </form>
    </main>

    <a href="order.php" class="back-button">戻る</a>
    <div class="modal" id="confirmationModal" style="display:none;">
        <div class="modal-content">
            <p>本当に作成しますか？</p>
            <div class="modal-buttons">
                <button class="cancel" type="button" onclick="closeModal()">いいえ</button>
                <button class="confirm" type="button" onclick="submitForm()">はい</button>
            </div>
        </div>
    </div>

    <script src="script.js"></script>

    <script>
    // =============================
    // 注文作成画面 order_create.php
    // =============================

    // --- 合計数量・金額を自動計算する関数 ---
    function calculateTotal() {
        const quantities = document.getElementsByName('quantity[]');
        const prices = document.getElementsByName('price[]');
        let totalQuantity = 0;
        let grandTotal = 0;
        for (let i = 0; i < quantities.length; i++) {
            const qty = parseFloat(quantities[i].value) || 0;
            const price = parseFloat(prices[i].value.replace(/[^\d.]/g, '')) || 0;
            totalQuantity += qty;
            grandTotal += qty * price;
        }
        document.querySelector('input[name="total_quantity"]').value = totalQuantity;
        document.querySelector('input[name="grand_total"]').value = '￥' + grandTotal.toLocaleString();
    }

    window.addEventListener('DOMContentLoaded', function() {
        const quantityInputs = document.getElementsByName('quantity[]');
        const priceInputs = document.getElementsByName('price[]');
        [...quantityInputs, ...priceInputs].forEach(input => {
            input.addEventListener('input', calculateTotal);
        });
    });

    // --- 作成ボタンで確認モーダルを表示 ---
    function showModal() {
        document.getElementById('confirmationModal').style.display = 'flex';
    }
    // --- モーダルを閉じる ---
    function closeModal() {
        document.getElementById('confirmationModal').style.display = 'none';
    }
    // --- モーダル「はい」ボタンで注文データを送信 ---
    let isSubmitting = false;
    function submitForm() {
        // 必須項目チェック
        const customerId = document.getElementById('customer_no').value.trim();
        const date = document.getElementById('date').value.trim();
        let hasItem = false;
        const itemNames = document.getElementsByName('item_name[]');
        const quantities = document.getElementsByName('quantity[]');
        for (let i = 0; i < itemNames.length; i++) {
            if (itemNames[i].value.trim() !== '' && quantities[i].value.trim() !== '' && parseInt(quantities[i].value) > 0) {
                hasItem = true;
                break;
            }
        }
        if (!customerId || !date || !hasItem) {
            alert('入力されていない項目があります');
            return;
        }
        // サブミット時は確認ダイアログを出さない
        isSubmitting = true;
        setTimeout(() => {
            document.getElementById('orderForm').submit();
        }, 10);
    }

    function beforeUnloadHandler(e) {
        if (!isSubmitting && isFormDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    }
    window.addEventListener('beforeunload', beforeUnloadHandler);

    // --- 名前入力時の自動候補表示（ダミーデータ） ---
    document.getElementById('name').addEventListener('input', function() {
    const name = this.value;
    const suggestions = document.getElementById('nameSuggestions');
    if (!name) {
        suggestions.innerHTML = '';
        return;
    }
    // Ajaxでサーバーから候補取得
    fetch('customer_suggest.php?keyword=' + encodeURIComponent(name))
        .then(res => res.json())
        .then(data => {
            suggestions.innerHTML = '';
            data.forEach(cust => {
                const div = document.createElement('div');
                div.textContent = cust.name + '（No.' + cust.customer_id + '）';
                div.style.cursor = 'pointer';
                div.style.padding = '4px 8px';
                div.onmousedown = function(e) {
                    document.getElementById('name').value = cust.name;
                    document.getElementById('customer_no').value = cust.customer_id;
                    document.getElementById('customer_no').readOnly = true;
                    suggestions.innerHTML = '';
                };
                suggestions.appendChild(div);
            });
        });
});

    // --- 入力値があるかチェックする関数 ---
    function isFormDirty() {
        const inputs = document.querySelectorAll('#orderForm input[type="text"], #orderForm input[type="number"], #orderForm input[type="date"]');
        for (let input of inputs) {
            if (input.value && !input.readOnly) return true;
        }
        return false;
    }

    // --- 戻るボタン押下時の確認モーダル ---
    document.querySelector('.back-button').addEventListener('click', function(e) {
        if (isFormDirty()) {
            e.preventDefault();
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
                document.getElementById('orderForm').reset();
                window.location.href = 'order.php';
                modal.remove();
            };
            document.getElementById('backNoBtn').onclick = function() {
                modal.remove();
            };
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

    // 注文画面(order.php)に遷移した際にalertを表示
window.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    // localStorageのフラグがある場合のみalertを表示
    if (params.get('success') === '1' && localStorage.getItem('orderCreated') === '1') {
        alert('注文書が正しく作成されました');
        localStorage.removeItem('orderCreated');
    }
});

</script>

</body>
</html>
