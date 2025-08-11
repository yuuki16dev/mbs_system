<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
  <div class="home-title">注文選択</div>
<link rel="stylesheet" href="styles.css"><!-- 共通のCSSファイル -->
<style>

body {
 font-family: Arial, sans-serif;
 margin: 0px;
 padding: 0px;
 background-color: #fff;
}

h1 {
 text-align: left;
 font-size: 2.2em;
}

.customer-info {
 display: flex;
 justify-content: space-between;
 margin-bottom: 20px;
 gap: 20px;
 font-size: 1.2em;
}

.customer-info p {
 margin: 0;
 white-space: nowrap;
}

table {
 width: 100%;
 min-width: 1200px;
 border-collapse: collapse;
 margin-bottom: 30px;
 overflow-x: auto;
 display: block;
 white-space: nowrap;
 font-size: 1.15em;
}
table, th, td {
  border: 1px solid #ccc; /* もう少し薄く */
}
th, td {
 padding: 8px 16px;
 text-align: left;
 font-size: 1.1em;
}
/* チェックボックスを大きく */
input[type="checkbox"][name="selected[]"] {
  width: 28px;
  height: 28px;
  accent-color: #2196f3;
  cursor: pointer;
}
th.select-col, td.select-col {
  width: 80px;
  text-align: center;
}
th.date-col, td.date-col {
 width: 260px; /* さらに広く */
}
th.name-col, td.name-col {
 width: 750px; /* さらに広く */
}
th.quantity-col, td.quantity-col {
 width: 220px; /* さらに広く */
}
th.price-col, td.price-col {
 width: 220px; /* さらに広く */
}
th.remarks-col, td.remarks-col {
 width: 220px; /* さらに広く */
}

.buttons {
 text-align: center;
 width: 100%;
 margin: 30px 0 0 0;
 display: flex;
 justify-content: center;
}
.buttons .confirm-button {
  margin: 0 auto;
}
.menu {
 position: absolute;
 top: 20px;
 right: 40px;
}
.menu-button {
 font-size: 32px;
 cursor: pointer;
 background: none;
 border: none;
}
.nav {
 display: none;
 position: absolute;
 top: 60px;
 right: 40px;
 background-color: #f9f9f9;
 border: 1px solid #ccc;
 padding: 10px;
}
.nav a {
 display: block;
 padding: 5px 10px;
 text-decoration: none;
 color: #333;
}
.nav a:hover {
 background-color: #eee;
}
.back-button {
 position: fixed;
 bottom: 20px;
 left: 20px;
 width: 40px;
 height: 40px;
 display: flex;
 align-items: center;
 justify-content: center;
 text-decoration: none;
 border: 2px solid #333;
 border-radius: 5px;
 background-color: #ffffff;
 color: #333;
 font-size: 1.1em;
 transition: 0.3s;
}
.back-button:hover {
 background-color: #333;
 color: #ffffff;
}
/* チェックされた行の背景色 */
.highlight {
  background-color: #ffe082; /* 明るい黄色系で見やすく */
}

/* 注文選択ボタンのデザイン */
.big-button {
  padding: 20px 60px;
  font-size: 20px;
  background-color: #4CAF50;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-weight: bold;
  box-shadow: 0 4px 8px rgba(0,0,0,0.2);
  transition: background-color 0.3s;
}

/* 確定ボタン（.confirm-button）と戻るボタン（.back-button）を同じレイアウト・デザインに */
.confirm-button, .back-button {
  background-color: #fff;
  color: #333;
  border: 2px solid #333;
  border-radius: 5px;
  font-size: 1.1em;
  padding: 15px 40px;
  cursor: pointer;
  transition: 0.3s;
  text-decoration: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}
.confirm-button:hover, .back-button:hover {
  background-color: #333;
  color: #fff;
}

/* 選択・注文日・商品名・単価のth（見出しセル）のみ背景色を変更 */
table th:nth-child(1), /* 選択 */
table th:nth-child(2), /* 注文日 */
table th:nth-child(3), /* 商品名 */
table th:nth-child(4), /* 単価 */
table th:nth-child(5)  /* 摘要 */
{
  background-color: #f2f2f2;
}
</style>
<script>
function toggleMenu() {
 var nav = document.getElementById("navMenu");
 nav.style.display = nav.style.display === "block" ? "none" : "block";
}
document.addEventListener("DOMContentLoaded", function () {
 const checkboxes = document.querySelectorAll('input[type="checkbox"][name="selected[]"]');
 checkboxes.forEach(function (checkbox) {
 checkbox.addEventListener("change", function () {
 const row = checkbox.closest("tr");
 if (checkbox.checked) {
 row.classList.add("highlight");
 } else {
 row.classList.remove("highlight");
 }
 });
 });
});
</script>
</head>
<script>
// 納品作成画面から遷移時、URLパラメータname/noがあれば表示欄に反映
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  const paramName = urlParams.get('name');
  const paramNo = urlParams.get('no');
  if (paramName) {
    // 顧客名表示欄があれば反映
    const nameElem = document.querySelector('.customer-info p strong');
    if (nameElem && nameElem.textContent.includes('顧客名:')) {
      nameElem.parentElement.innerHTML = '<strong>顧客名:</strong> ' + paramName + ' 様';
    }
  }
  if (paramNo) {
    const noElem = document.querySelectorAll('.customer-info p strong')[1];
    if (noElem && noElem.textContent.includes('顧客No:')) {
      noElem.parentElement.innerHTML = '<strong>顧客No:</strong> ' + paramNo;
    }
  }
});
</script>
<body>

<?php include('navbar.php'); ?>

<main>


<?php
// customer_name/customer_noパラメータを優先して受け取る
$customer_id = isset($_GET['customer_no']) ? (int)$_GET['customer_no'] : (isset($_GET['no']) ? (int)$_GET['no'] : 0);
$customer_name = isset($_GET['customer_name']) ? $_GET['customer_name'] : (isset($_GET['name']) ? $_GET['name'] : '');
if ($customer_id) {
    $pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // 顧客名はGET優先、なければDBから取得
    if (!$customer_name) {
        $stmt = $pdo->prepare('SELECT name FROM customers WHERE customer_id = ?');
        $stmt->execute([$customer_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $customer_name = $row ? $row['name'] : '';
    }
}
?>
<div class="customer-info">
 <p><strong>顧客名:</strong> <?php echo htmlspecialchars($customer_name); ?> 様</p>
 <p><strong>顧客No:</strong> <?php echo htmlspecialchars($customer_id); ?></p>
</div>

<form method="post" action="submit.php">
 <form id="orderSelectForm">
 <table>
 <thead>
 <tr>
 <th class="select-col">選択</th>
 <th class="date-col">注文日</th>
 <th class="name-col">商品名</th>
 <th class="quantity-col">数量</th>
 <th class="price-col">単価</th>
 <th class="remarks-col">摘要</th>
 </tr>
 </thead>
 <tbody>
<?php
if ($customer_id) {
    // 注文明細（order_details）のcancel_flag=0のみ表示
    $stmt = $pdo->prepare('
        SELECT o.order_id, o.order_date, d.order_detail_id, d.product_name, d.quantity, d.unit_price, d.remarks
        FROM orders o
        JOIN order_details d ON o.order_id = d.order_id
        WHERE o.customer_id = ? AND d.cancel_flag = 0
        ORDER BY o.order_date DESC, o.order_id DESC, d.order_detail_id ASC
        LIMIT 50
    ');
    $stmt->execute([$customer_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        // 注文明細No（order_detail_id）をdata属性に持たせる
        echo '<tr data-order-detail-id="' . htmlspecialchars($row['order_detail_id']) . '">';
        echo '<td class="select-col"><input type="checkbox" name="selected[]"></td>';
        echo '<td class="date-col">' . htmlspecialchars($row['order_date']) . '</td>';
        echo '<td class="name-col">' . htmlspecialchars($row['product_name']) . '</td>';
        echo '<td class="quantity-col">' . htmlspecialchars($row['quantity']) . '</td>';
        echo '<td class="price-col">' . htmlspecialchars($row['unit_price']) . '</td>';
        echo '<td class="remarks-col">' . htmlspecialchars($row['remarks']) . '</td>';
        echo '</tr>';
    }
}
?>
 </tbody>
 </table>

 <div class="buttons">
   <button type="button" id="confirmBtn" class="big-button confirm-button">確定</button>
 </div>
</form>
<!-- JSによる自動POST・hiddenフォームは廃止 -->

<a href="javascript:history.back()" class="back-button">戻る</a>

<div class="modal" id="confirmationModal">
 <div class="modal-content">
 </div>
</div>

<script src="script.js"></script>
<script>
// 確定ボタンで選択注文内容をlocalStorageに保存し納品作成画面へ遷移
document.getElementById('confirmBtn').addEventListener('click', function() {
  const rows = Array.from(document.querySelectorAll('tbody tr'));
  const selectedDetails = [];
  rows.forEach(row => {
    const checkbox = row.querySelector('input[type="checkbox"]');
    if (checkbox && checkbox.checked) {
      const detailId = row.getAttribute('data-order-detail-id');
      const productName = row.querySelector('.name-col').textContent.trim();
      // 数量と単価のカラム取得
      const quantity = row.querySelector('.quantity-col').textContent.trim();
      const unitPrice = row.querySelector('.price-col').textContent.trim();
      const remarks = row.querySelector('.remarks-col').textContent.trim();
      selectedDetails.push({
        order_detail_id: detailId,
        product_name: productName,
        quantity: quantity,
        unit_price: unitPrice,
        remarks: remarks
      });
    }
  });
  if (selectedDetails.length === 0) {
    alert('注文内容を選択してください');
    return;
  }
  // 顧客名・Noも保存
  const customerName = document.querySelector('.customer-info p strong').parentElement.textContent.replace('顧客名:', '').replace('様', '').trim();
  const customerNo = document.querySelectorAll('.customer-info p strong')[1].parentElement.textContent.replace('顧客No:', '').trim();
  localStorage.setItem('order_selected', JSON.stringify({ name: customerName, no: customerNo, details: selectedDetails }));
  // 注文明細Noと顧客NoをGETで渡す（選択された明細のみ）
  const selectedDetailIds = selectedDetails.map(d => d.order_detail_id);
  location.href = 'nouhinsakusei.php?order_ids=' + encodeURIComponent(selectedDetailIds.join(',')) + '&customer_no=' + encodeURIComponent(customerNo);
});
</script>
</main>
</body>
</html>
