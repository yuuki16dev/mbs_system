document.querySelectorAll('.count-up').forEach(btn => {
  btn.addEventListener('click', function() {
    const input = this.previousElementSibling;
    input.value = parseInt(input.value) + 1;
  });
});
document.querySelectorAll('.count-down').forEach(btn => {
  btn.addEventListener('click', function() {
    const input = this.nextElementSibling;
    if (parseInt(input.value) > 1) {
      input.value = parseInt(input.value) - 1;
    }
  });
});

// ハンバーガーメニューの開閉制御
// メニューアイコンをクリックするとメニュー表示/非表示を切り替える
document.addEventListener('DOMContentLoaded', function () {
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('navMenu');

    hamburger.addEventListener('click', () => {
        navMenu.classList.toggle('show');
        hamburger.classList.toggle('open');  // メニューの表示/非表示とアイコンのアニメーションを切り替える
    });
});

// モーダル（ポップアップ）表示
function showModal() {
    document.getElementById("confirmationModal").style.display = "flex";
}
// モーダル（ポップアップ）非表示
function closeModal() {
    document.getElementById("confirmationModal").style.display = "none";
}
// モーダルの「はい」ボタンでフォーム送信
function submitForm() {
    document.getElementById("createForm").submit();
}
// 金額欄に「￥」を自動付与し、カンマ区切りに整形
function addYenSymbol(input) {
    let value = input.value.replace(/[^\d]/g, ''); // 数字以外削除
    if (value) {
        input.value = '￥' + Number(value).toLocaleString();
    } else {
        input.value = '';
    }
    calculateTotal(); // フォーマット後再計算
}
// 合計金額・数量を自動計算し、各欄に反映
function calculateTotal() {
    let quantities = document.getElementsByName("quantity[]");
    let prices = document.getElementsByName("price[]");
    let totals = document.getElementsByName("total[]");
    let taxRate = parseFloat(document.getElementsByName("tax_rate")[0].value) || 10;

    let subtotal = 0;
    let totalQuantity = 0;

    for (let i = 0; i < quantities.length; i++) {
        let qty = parseFloat(quantities[i].value) || 0;
        let price = parseFloat(prices[i].value.replace(/[^\d]/g, '')) || 0;

        let totalPerItem = Math.floor(qty * price * (1 + taxRate / 100));
        if (qty > 0 && price > 0) {
            totals[i].value = '￥' + totalPerItem.toLocaleString();
            subtotal += qty * price;
            totalQuantity += qty;
        } else {
            totals[i].value = '';
        }
    }

    let tax = Math.floor(subtotal * taxRate / 100);
    let totalWithTax = subtotal + tax;

    document.getElementsByName("total_quantity")[0].value = totalQuantity;
    document.getElementsByName("grand_total")[0].value = '￥' + subtotal.toLocaleString();
    document.getElementsByName("tax_amount")[0].value = '￥' + tax.toLocaleString();
    document.getElementsByName("total_with_tax")[0].value = '￥' + totalWithTax.toLocaleString();
}

document.querySelectorAll('.sort-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const column = this.getAttribute('data-column');
        const urlParams = new URLSearchParams(window.location.search);
        let currentColumn = urlParams.get('sort_column');
        let currentOrder = urlParams.get('sort_order') || 'desc';
        let newOrder = 'desc';
        if (currentColumn === column && currentOrder === 'desc') {
            newOrder = 'asc';
        }
        urlParams.set('sort_column', column);
        urlParams.set('sort_order', newOrder);
        urlParams.set('page', 1); // ソート時は1ページ目に戻す
        window.location.search = urlParams.toString();
    });
});

document.addEventListener('DOMContentLoaded', function () {
    // ...既存のDOMContentLoaded処理...

    // ソート状態の反映
    const urlParams = new URLSearchParams(window.location.search);
    const sortColumn = urlParams.get('sort_column');
    const sortOrder = urlParams.get('sort_order');
    if (sortColumn) {
        document.querySelectorAll('.sort-btn').forEach(btn => {
            if (btn.getAttribute('data-column') === sortColumn) {
                btn.classList.add(sortOrder === 'asc' ? 'asc' : 'desc');
            } else {
                btn.classList.remove('asc', 'desc');
            }
        });
    }
});