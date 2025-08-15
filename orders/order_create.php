<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文書作成</title>
    <link rel="stylesheet" href="../style.css">  <!-- 共通のCSSファイル -->
</head>
<body class="order-create-page">
    <header>
        <div class="home-title">注文作成</div>
    </header>
    <?php include('../navbar.php'); ?>  <!-- 共通のナビゲーションバー（ハンバーガーメニュー）をインクルード -->

    <main>
        <form id="orderForm" method="post" action="submit_order.php">
            <!-- 顧客情報入力フォーム -->
            <table>
                <div class="table-container">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label for="date">日付:</label>
                        <input type="date" id="date" name="date">
                    </div>
                    
                    <div>
                        <label for="customer_no">顧客No</label>
                        <input type="number" min="0" id="customer_no" name="customer_id" readonly>
                    </div>
                    <div class=" name-row">
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
            <div class=" button-container">
                <button type="button" class=" button" onclick="showModal()">作成</button>
            </div>
        </form>
    </main>

    <a href="order.php" class=" back-button">戻る</a>
    <div class=" modal" id="confirmationModal" style="display:none;">
        <div class=" modal-content">
            <p>本当に作成しますか？</p>
            <div class=" modal-buttons">
                <button class=" cancel" type="button" onclick="closeModal()">いいえ</button>
                <button class=" confirm" type="button" onclick="submitForm()">はい</button>
            </div>
        </div>
    </div>

    <script src="../script.js"></script>

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
