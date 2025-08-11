<?php
// DB接続を最初に
$pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 画面初期表示時はすべてnull/空値
$order = null;
$order_id = null;
$customer_id = '';
$order_date = '';
$remarks = '';
$customer_name = '';
$order_items = [];

// 注文選択画面から遷移した場合のみ、注文明細Noで注文内容を取得
if (isset($_GET['order_ids']) && $_GET['order_ids']) {
  $ids = explode(',', $_GET['order_ids']);
  $ids = array_filter($ids, function($id) { return is_numeric($id); });
  if (count($ids) > 0) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT order_id, order_detail_id, product_name, quantity, unit_price, remarks FROM order_details WHERE order_detail_id IN ($in) AND cancel_flag = 0 ORDER BY order_detail_id ASC");
    $stmt->execute($ids);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 注文Noリストを取得
    $orderIds = array_unique(array_column($order_items, 'order_id'));
    $orderRemarks = [];
    if (count($orderIds) > 0) {
      $inOrder = implode(',', array_fill(0, count($orderIds), '?'));
      $stmtOrder = $pdo->prepare("SELECT order_id, remarks FROM orders WHERE order_id IN ($inOrder)");
      $stmtOrder->execute($orderIds);
      foreach ($stmtOrder->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $orderRemarks[$row['order_id']] = $row['remarks'];
      }
    }
    // 注文明細のremarksが空なら注文remarksをセット
    foreach ($order_items as &$item) {
      if (empty($item['remarks']) && isset($orderRemarks[$item['order_id']])) {
        $item['remarks'] = $orderRemarks[$item['order_id']];
      }
    }
    unset($item);
  }
}

// --- 顧客名部分一致検索APIエンドポイント ---
// 顧客名検索API: 顧客情報＋その顧客の注文リストも返す
if (isset($_GET['search_customer'])) {
  $name = $_GET['search_customer'];
  $stmt = $pdo->prepare('SELECT customer_id, name, address, phone FROM customers WHERE name LIKE ? ORDER BY customer_id ASC LIMIT 20');
  $stmt->execute(['%' . $name . '%']);
  $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
  // 各顧客ごとに注文リストを取得
  foreach ($results as &$customer) {
    $order_stmt = $pdo->prepare('SELECT order_id, order_date, remarks FROM orders WHERE customer_id = ? AND cancel_flag = 0 ORDER BY order_date DESC, order_id DESC');
    $order_stmt->execute([$customer['customer_id']]);
    $orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);
    // 各注文に明細を付与
    foreach ($orders as &$order) {
      $detail_stmt = $pdo->prepare('SELECT product_name, quantity, unit_price FROM order_details WHERE order_id = ? AND cancel_flag = 0 ORDER BY order_detail_id ASC');
      $detail_stmt->execute([$order['order_id']]);
      $order['details'] = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $customer['orders'] = $orders;
  }
  header('Content-Type: application/json; charset=UTF-8');
  echo json_encode($results);
  exit;
}

// 納品日を決定（注文日があればそれ、なければ今日）
$delivery_date_for_submit = $order_date ? date('Y-m-d', strtotime($order_date)) : date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
 <meta charset="UTF-8" />
 <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
 <title>納品書作成</title>
 <script>
   // ページURLに?reset=1があればlocalStorageのorder_selectedを削除（script.jsより前に実行）
   (function() {
     const params = new URLSearchParams(window.location.search);
     if(params.get('reset') === '1') {
       localStorage.removeItem('order_selected');
     }
   })();

  
 </script>
 
 <style>
 body {
 font-family: sans-serif;
 }

 .table-container {
 display: flex;
 align-items: center;
 gap: 20px;
 margin: 20px 0;
 }

 .table-container label {
 margin-right: 10px;
 }

 .input-field {
 width: 100%;
 border: none;
 background: none;
 text-align: center;
 }

 table {
 border-collapse: collapse;
 width: 100%; /* テーブル全体をコンパクトに */
 margin: 20px 0 20px 0; /* 左寄せを強調 */
 float: left; /* 完全に左寄せ */
 }

 table, th, td {
 border: 1px solid #bbb;
 font-size: 0.8em; /* 少し大きく */
 padding: 0.7em 0.5em; /* パディングも少し大きく */
 }

 th, td {
  padding: 0.7em 0.5em; /* こちらも統一して少し大きく */
  text-align: center;
 }

 .button {
 padding: 15px 40px;
 background-color: #333;
 color: #fff;
 border: none;
 border-radius: 5px;
 cursor: pointer;
 padding: 10px 15px;
 background-color: #f0f0f0;
 border: 1px solid #ddd; /* さらに薄く */
 border-radius: 5px;
 cursor: pointer;
 font-weight: bold;
 }

 
 /* 顧客検索結果のボタンの見た目を整える */
.name-box {
 position: relative; /* ツールチップのための基準位置 */
 display: inline-block; /* インラインでブロック要素として表示 */
 margin: 5px; /* ボタン間の余白 */
 background: #f9f9f9; /* 背景色 */
 border: 1px solid #ccc; /* さらに薄く */
 border-radius: 5px; /* 角丸 */
 padding: 8px 16px; /* 内側余白 */
 cursor: pointer; /* カーソルをポインタに */
 font-weight: bold; /* 太字 */
}

/* 顧客ボタンのホバー時に表示されるツールチップの見た目 */
.name-box .tooltip {
 display: none; /* 通常は非表示 */
 position: absolute; /* 親要素基準で絶対配置 */
 left: 110%; /* ボタンの右側に表示 */
 top: 50%; /* 垂直中央 */
 transform: translateY(-50%); /* 完全な中央揃え */
 background: #fff; /* 背景色 */
 border: 1px solid #ccc; /* さらに薄く */
 border-radius: 5px; /* 角丸 */
 padding: 10px; /* 内側余白 */
 box-shadow: 0 2px 8px rgba(0,0,0,0.2); /* 影 */
 z-index: 10; /* 前面に表示 */
 min-width: 220px; /* 最小幅 */
 white-space: pre-line; /* 改行を反映 */
 font-weight: normal; /* 通常の太さ */
 color: #333; /* 文字色 */
}

/* ボタンにマウスを乗せた時にツールチップを表示 */
.name-box:hover .tooltip {
 display: block;
 }

 /* ページ下部のボタンを中央揃えにする */
.bottom-button-container {
 display: flex;
 justify-content: center;
 margin: 80px 0 40px;
}

/* 注文選択ボタンのデザイン */
.big-button {
  width: 260px; /* 横幅を完全に統一 */
  height: 64px; /* 縦幅をさらに伸ばす */
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

/* ボタンにマウスを乗せた時の色変化 */
.big-button:hover {
 background-color: #45a049;
}

/* 商品テーブルのカラム幅調整 */
/* 商品名を広く、数量・単価を短く、金額(税込み)を大きくする */
/* それぞれのth/tdに幅を指定 */
table th:nth-child(2),
table td:nth-child(2) {
  width: 32%; /* 商品名 */
}
table th:nth-child(3),
table td:nth-child(3) {
  width: 12%; /* 数量 */
}
table th:nth-child(3),
table td:nth-child(3) {
  width: 10%; /* 数量 */
}
table th:nth-child(4),
table td:nth-child(4) {
  width: 13%; /* 単価 */
}
table th:nth-child(5),
table td:nth-child(5) {
  width: 18%; /* 金額(税込み) */
}
table th:nth-child(6),
table td:nth-child(6) {
  width: 33%; /* 摘要 */
  min-width: 120px;
  max-width: 340px;
  word-break: break-all;
  white-space: pre-line;
}
table th:nth-child(4),
table td:nth-child(4) {
  width: 12%; /* 単価 */
}
table th:nth-child(5),
table td:nth-child(5) {
  width: 30%; /* 金額(税込み) */
}
/* 戻るボタンデザイン */
.back-button {
 position: fixed;
 bottom: 24px;
 left: 32px;
 width: 64px;
 height: 64px;
 display: flex;
 align-items: center;
 justify-content: center;
 text-decoration: none;
 border: 4px solid #444;
 border-radius: 16px;
 background-color: #ffffff;
}
th, td {
  padding: 1em 0.8em; /* こちらも統一して少し大きく */
  text-align: center;
  vertical-align: middle;
 color: #333;
 font-size: 16px;
 transition: 0.3s;
 font-weight: bold;
 letter-spacing: 0.05em;
 writing-mode: horizontal-tb !important;
 text-orientation: mixed !important;
  font-size: 1.13em; /* 少し大きく */
 overflow: hidden;
}
table th:nth-child(6) {
  font-size: 1.13em;
  font-weight: bold;
 z-index: 1002;
}
.back-button span {
  writing-mode: horizontal-tb !important;
  text-orientation: mixed !important;
  display: inline-block;
  white-space: nowrap;
  transform: rotate(0deg) !important;
}

/* 注文選択ボタン（.big-button）と戻るボタン（.back-button）を同じレイアウト・デザインに */
.big-button, .back-button {
  background-color: #fff;
  color: #333;
  border: 2px solid #444; /* 枠線をより濃く */
  border-radius: 8px;
  font-size: 20px;
  padding: 15px 40px;
  cursor: pointer;
  transition: 0.3s;
  text-decoration: none;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 400;
}
.big-button:hover, .back-button:hover {
  background-color: #333;
  color: #fff;
}

.hamburger {
    position: absolute;
    top: 20px;
    right: 20px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    cursor: pointer;
    z-index: 1001; /* メニューより前に表示 */
}

.hamburger div {
    width: 30px;
    height: 4px;
    background-color: #333;
    border-radius: 5px;
    transition: transform 0.3s ease-in-out, background-color 0.3s ease-in-out;
}

.hamburger.open div:nth-child(1) {
    transform: translateY(10px) rotate(45deg); /* 上の線を右上に回転 */
}

.hamburger.open div:nth-child(2) {
    opacity: 0; /* 中央の線を非表示に */
}

.hamburger.open div:nth-child(3) {
    transform: translateY(-10px) rotate(-45deg); /* 下の線を左下に回転 */
}

.nav-menu {
    position: absolute;
    top: 50px;
    right: 20px;
    background-color: #fff;
    border-radius: 5px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    display: none;
    flex-direction: column;
    gap: 10px;
    padding: 10px;
    z-index: 1000;
    transform: translateX(100%); /* 初期状態ではメニューを右に隠す */
    transition: transform 0.3s ease-in-out;
}

.nav-menu.show {
    display: flex;
    transform: translateX(0); /* メニューをスライドイン */
}

.nav-menu a {
    text-decoration: none;
    color: #333;
    font-size: 18px;
    padding: 10px;
    transition: background-color 0.3s ease;
}

.nav-menu a:hover {
    background-color: #f1f1f1;
}

/* 商品名、数量、単価、金額(税込み)のth（見出しセル）のみ背景色を変更 */
table th:nth-child(2), /* 商品名 */
table th:nth-child(3), /* 数量 */
table th:nth-child(4), /* 単価 */
table th:nth-child(5)  /* 金額(税込み) */
{
  background-color: #f2f2f2;
}

/* 商品名のth（見出しセル）を明示的に背景色変更（colspan=2、2列目） */
table th[colspan="2"],
table tr > th:nth-child(2) {
  background-color: #f2f2f2;
}

/* タイトルと日付の文字をさらに小さく */
h1 {
  text-align: left;
  font-size: 1.5em; /* さらに大きく */
  font-weight: bold;
  margin: 18px 0 10px 18px;
  letter-spacing: 0.05em;
  display: block;
}
.date-label {
  display: inline-block;
  font-size: 0.85em; /* 小さく */
  font-weight: bold;
  color: #333; /* 濃く戻す */
  margin-left: 18vw;
  margin-top: 0;
  margin-bottom: 0;
  vertical-align: middle;
}

/* 名前検索欄と顧客No欄のinputを控えめな丸み */
#name,
#customer_no {
  border-radius: 6px !important;
}

/* 顧客Noラベルを小さく、色は濃く */
label[for="customer_no"] {
  color: #333 !important;
  font-size: 0.95em !important;
  font-weight: normal !important;
}

#result {
  /* 検索結果は通常フローで表示。絶対位置指定をやめる */
  position: static !important;
  top: unset !important;
  left: unset !important;
  min-width: 220px;
  background: #fff;
  border: none;
  z-index: 10;
  margin-top: 0 !important;
  margin-bottom: 12px;
}

/* 商品テーブルの見出し（商品名・数量・単価・金額（税込み））の文字サイズを調整 */
table th[colspan="2"],
table th:nth-child(2),
table th:nth-child(3),
table th:nth-child(4),
table th:nth-child(5) {
  font-size: 1.1em; /* 少し大きく */
  font-weight: bold;
}
 </style>
</head>
<body>
 <!-- タイトルと日付表示 -->
 <h1 style="text-align: left;">
  納品書作成
  <span class="date-label">日付：<input type="date" id="delivery_date_input" name="delivery_date" value="<?php echo htmlspecialchars($delivery_date_for_submit); ?>" style="font-size:1em; padding:2px 8px; margin-left:8px; width:160px;"></span>
 </h1>

 <!-- ナビゲーションバーの読み込み -->
 <?php include('navbar.php'); ?>

 <!-- 納品書作成フォーム -->
 <form id="createForm" method="post" action="submit_nouhin.php">
<!-- <input type="hidden" name="delivery_date" value="<?php echo htmlspecialchars($delivery_date_for_submit); ?>"> -->

<!-- 名前検索欄 -->
<div class="table-container" style="justify-content: flex-start; align-items: center; gap: 8px; width: 100%; position: relative; margin-bottom: 0;">
  <!-- 虫眼鏡ボタンを小さく -->
  <button type="button" class="search-button" onclick="searchCustomer()" style="background: #fff; border: 1px solid #aaa; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; margin-right: 3px; padding: 0;">
    <svg width="14" height="14" viewBox="0 0 20 20"><circle cx="9" cy="9" r="7" stroke="#333" stroke-width="2" fill="none"/><line x1="15" y1="15" x2="19" y2="19" stroke="#333" stroke-width="2"/></svg>
  </button>
  <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($customer_name); ?>" style="width: 420px; height: 28px; font-size: 0.95em; padding: 3px 6px;">
  <label for="name" style="font-size: 0.95em;">様</label>
  <div style="flex:1;"></div>
  <div style="font-size: 1em; font-weight: bold; color: #333; min-width: 120px; text-align: right; display: flex; align-items: center; gap: 6px; justify-content: flex-end;">
    <label for="customer_no" style="font-weight: bold; font-size: 0.95em; margin-bottom: 0;">顧客No</label>
    <input type="text" id="customer_no" name="customer_no" value="<?php echo htmlspecialchars($customer_id); ?>" readonly style="background-color: #f5f5f5; width: 44px; height: 28px; font-size: 1em; text-align: center; font-weight: bold; margin-left: 0;">
  </div>
</div>
<!-- 検索メッセージ表示欄（検索バーのすぐ下） -->
<div id="search-message" style="font-size: 0.88em; color: #888; margin: 2px 0 2px 32px; min-height: 1.2em;"></div>
<!-- 検索結果表示欄（検索バーのすぐ下、表に被らないように） -->
<div id="result" style="margin: 0 0 12px 32px; min-height: 1.2em; z-index: 10;"></div>

<!-- 商品入力テーブルと注文選択ボタンを横並びに配置 -->
<div style="display: flex; align-items: flex-start; justify-content: flex-end; gap: 32px; width: 100%;">
  <div style="width: 100%; min-width: 700px; max-width: 1400px;">
    <table style="width: 100%; min-width: 700px; max-width: 1400px; margin: 0 auto; float: none;">
      <tr>
        <th colspan="2">商品名</th>
        <th>数量</th>
        <th>単価</th>
        <th>金額(税込み)</th>
        <th>摘要</th>
      </tr>
      <?php for ($i = 0; $i < 10; $i++): ?>
      <tr>
        <td><?php echo $i+1; ?></td>
        <td>
          <input type="text" name="item_name[]" class="input-field" value="<?php echo isset($order_items[$i]) ? htmlspecialchars($order_items[$i]['product_name']) : ''; ?>" readonly>
          <input type="hidden" name="order_id[]" value="<?php echo isset($order_items[$i]['order_id']) ? htmlspecialchars($order_items[$i]['order_id']) : ''; ?>">
          <input type="hidden" name="order_detail_id[]" value="<?php echo isset($order_items[$i]['order_detail_id']) ? htmlspecialchars($order_items[$i]['order_detail_id']) : ''; ?>">
        </td>
        <td><input type="number" name="quantity[]" class="input-field" value="<?php echo isset($order_items[$i]['quantity']) ? htmlspecialchars($order_items[$i]['quantity']) : ''; ?>" readonly></td>
        <td><input type="text" name="price[]" class="input-field" value="<?php echo isset($order_items[$i]['unit_price']) ? '¥'.number_format($order_items[$i]['unit_price']) : ''; ?>" readonly></td>
        <td><input type="text" name="total[]" class="input-field" value="<?php echo (isset($order_items[$i]) && $order_items[$i]['quantity'] && $order_items[$i]['unit_price']) ? '¥'.number_format($order_items[$i]['quantity'] * $order_items[$i]['unit_price'] * 1.1) : ''; ?>" readonly></td>
        <td><input type="text" name="remarks[]" class="input-field" value="<?php echo isset($order_items[$i]['remarks']) ? htmlspecialchars($order_items[$i]['remarks']) : ''; ?>" readonly></td>
      </tr>
      <?php endfor; ?>
      <!-- 合計・税率・消費税額表示欄 -->
      <?php
        $sum = 0; $sumWithTax = 0; $sumQty = 0;
        foreach ($order_items as $item) {
          $sum += $item['unit_price'] * $item['quantity'];
          $sumWithTax += $item['unit_price'] * $item['quantity'] * 1.1;
          $sumQty += $item['quantity'];
        }
      ?>
      <tr>
        <td colspan="2">合計</td>
        <td><input type="text" name="total_quantity" class="input-field" value="<?php echo $sumQty ?: ''; ?>" readonly></td>
        <td><input type="text" name="grand_total" class="input-field" value="<?php echo $sum ? '¥'.number_format($sum) : ''; ?>" readonly></td>
        <td><input type="text" name="total_with_tax" class="input-field" value="<?php echo $sumWithTax ? '¥'.number_format($sumWithTax) : ''; ?>" readonly></td>
      </tr>
      <tr>
        <td colspan="2">税率</td>
        <td colspan="3"><input type="number" name="tax_rate" class="input-field" value="10" readonly></td>
      </tr>
      <tr>
        <td colspan="2">消費税額</td>
        <td colspan="3"><input type="text" name="tax_amount" class="input-field" value="<?php echo $sumWithTax ? '¥'.number_format($sumWithTax - $sum) : ''; ?>" readonly></td>
      </tr>
    </table>
  </div>
  <!-- 注文選択ボタンと作成ボタンを縦並びで右端に配置 -->
  <div style="display: flex; flex-direction: column; align-items: flex-end; justify-content: flex-start; height: 100%; min-width: 220px; width: 220px;">
    <button type="button" id="orderSelectBtn" class="big-button" style="width:180px; height:40px; font-size:15px; margin-top: 120px; margin-bottom: 16px; margin-right: 0; margin-left: 80px; align-self: flex-end;" onclick="saveOrderAndMove()" disabled>注文選択</button>
    <div id="createBtnContainer" style="width: 100%; text-align: right; margin: 0;">
      <button type="button" class="big-button" style="width:180px; height:40px; font-size:15px; padding:0 0; margin: 0;" onclick="showCreateModal()">作成</button>
    </div>
  </div>
</div>

</form>

<!-- 戻るボタン（画面左下固定） -->
<button type="button" class="back-button" onclick="showBackModal()"><span style="writing-mode:horizontal-tb;text-orientation:mixed;display:inline-block;transform:rotate(0deg);white-space:nowrap;">戻る</span></button>

<!-- 共通のJavaScriptファイル読み込み -->
<script src="script.js"></script>
 <script>
    // 顧客名検索ボタン押下時の処理
    function searchCustomer() {

      const nameInput = document.getElementById('name');
      const resultDiv = document.getElementById('result');
      const orderSelectBtn = document.getElementById('orderSelectBtn');
      const searchMsg = document.getElementById('search-message');
      const name = nameInput.value.trim();

      resultDiv.innerHTML = '';
      searchMsg.textContent = '';

      if (name !== "") {
        fetch('customer_suggest.php?keyword=' + encodeURIComponent(name))
          .then(res => res.json())
          .then(matches => {
            if (matches.length > 0) {
              resultDiv.innerHTML = matches.map(c => {
                const safeName = c.name.replace(/'/g, "&#39;");
                // address/phoneが空なら未登録
                const address = c.address && c.address.trim() ? c.address : '未登録';
                const phone = c.phone && c.phone.trim() ? c.phone : '未登録';
                return `<button type="button" class="name-box" onclick="handleCustomerClick('${safeName}', '${c.customer_id}', event)" data-address="${address}" data-phone="${phone}" data-no="${c.customer_id}">${safeName} 様（顧客No:${c.customer_id}）</button>`;
              }).join("<br>");
              // ツールチップ表示用イベントを付与
              Array.from(resultDiv.querySelectorAll('.name-box')).forEach(btn => {
                btn.addEventListener('mouseenter', function(e) {
                  // 既存のツールチップを削除
                  let tip = btn.querySelector('.tooltip');
                  if (tip) btn.removeChild(tip);
                  // ツールチップ生成
                  let tooltip = document.createElement('span');
                  tooltip.className = 'tooltip';
                  tooltip.innerHTML = `<strong>住所:</strong> ${btn.getAttribute('data-address')}<br><strong>電話番号:</strong> ${btn.getAttribute('data-phone')}`;
                  btn.appendChild(tooltip);
                });
                btn.addEventListener('mouseleave', function(e) {
                  let tip = btn.querySelector('.tooltip');
                  if (tip) btn.removeChild(tip);
                });
              });
              orderSelectBtn.disabled = false;
            } else {
              searchMsg.textContent = "該当する顧客が見つかりません｡";
              orderSelectBtn.disabled = true;
            }
          })
          .catch(() => {
            searchMsg.textContent = "検索中にエラーが発生しました｡";
            orderSelectBtn.disabled = true;
          });
      } else {
        searchMsg.textContent = "名前を入力してください｡";
        orderSelectBtn.disabled = true;
      }
    }

// 顧客ボタン選択時の処理
function handleCustomerClick(name, no, event) {
  event.preventDefault();
  document.getElementById("customer_no").value = no;
  document.getElementById("customer_no").readOnly = true;
  document.getElementById("name").value = name;
  // 顧客名候補を非表示
  document.getElementById("result").innerHTML = '';
  document.getElementById("orderSelectBtn").disabled = false;
  // 顧客情報をlocalStorageに保存
  localStorage.setItem('order_customer', JSON.stringify({ name, no }));
  // 注文内容を取得して商品欄に反映
  fetch('customer_suggest.php?keyword=' + encodeURIComponent(name))
    .then(res => res.json())
    .then(data => {
      const customer = data.find(c => c.customer_id == no);
      if (customer && customer.orders && customer.orders.length > 0) {
        const items = [];
        customer.orders.forEach(order => {
          if (order.details) {
            order.details.forEach(detail => {
              items.push(detail);
            });
          }
        });
        const itemNames = document.getElementsByName("item_name[]");
        const quantities = document.getElementsByName("quantity[]");
        const prices = document.getElementsByName("price[]");
        const remarks = document.getElementsByName("remarks[]");
        for (let i = 0; i < itemNames.length; i++) {
          if (items[i]) {
            itemNames[i].value = items[i].product_name || '';
            quantities[i].value = items[i].quantity !== undefined ? items[i].quantity : '';
            prices[i].value = items[i].unit_price !== undefined ? '¥' + Number(items[i].unit_price).toLocaleString() : '';
            remarks[i].value = items[i].remarks || '';
            // 金額計算
            var qty = parseFloat(items[i].quantity) || 0;
            var price = parseFloat(items[i].unit_price) || 0;
            var total = qty && price ? Math.round(qty * price * 1.1) : '';
            document.getElementsByName('total[]')[i].value = total ? '¥' + total.toLocaleString() : '';
          } else {
            itemNames[i].value = '';
            quantities[i].value = '';
            prices[i].value = '';
            remarks[i].value = '';
            document.getElementsByName('total[]')[i].value = '';
          }
          itemNames[i].readOnly = true;
          quantities[i].readOnly = true;
          prices[i].readOnly = true;
          remarks[i].readOnly = true;
        }
        calculateTotal();
      }
    });
  alert(name + " を選択しました｡");
}

function calculateTotal() {
  const itemNames = document.getElementsByName("item_name[]");
  const quantities = document.getElementsByName("quantity[]");
  const prices = document.getElementsByName("price[]");
  const totals = document.getElementsByName("total[]");
  const taxRateInput = document.getElementsByName("tax_rate")[0];
  const taxRate = parseFloat(taxRateInput.value) || 10;

  let sumQty = 0;
  let sum = 0;
  let sumWithTax = 0;
  let hasValue = false;

  for (let i = 0; i < itemNames.length; i++) {
    let qty = parseFloat(quantities[i].value) || 0;
    let price = parseFloat(prices[i].value.replace(/[^\d]/g, '')) || 0;
    if (itemNames[i].value && price > 0) {
      hasValue = true;
      // 合計数量は商品名が空でない行の数量合計
      sumQty += qty;
      let total = qty * price;
      sum += total;
      let totalWithTax = Math.round(total * (1 + taxRate / 100));
      sumWithTax += totalWithTax;
      totals[i].value = '¥' + totalWithTax.toLocaleString();
    } else {
      totals[i].value = '';
    }
  }

  document.getElementsByName("total_quantity")[0].value = hasValue ? sumQty : '';
  document.getElementsByName("grand_total")[0].value = hasValue ? '¥' + sum.toLocaleString() : '';
  document.getElementsByName("total_with_tax")[0].value = hasValue ? '¥' + sumWithTax.toLocaleString() : '';
  document.getElementsByName("tax_amount")[0].value = hasValue ? '¥' + (sumWithTax - sum).toLocaleString() : '';
}

function saveOrderAndMove() {
  // beforeunload警告を抑制
  if (typeof suppressBeforeUnload !== 'undefined') suppressBeforeUnload = true;
  // 顧客名・No
  const name = document.getElementById('name').value;
  const no = document.getElementById('customer_no').value;
  // 注文日（本日日付）
  const date = new Date();
  const orderDate = date.getFullYear() + '年' + (date.getMonth()+1) + '月' + date.getDate() + '日';
  // 仮の商品データ
  const items = [
    { name: '週刊BCM', price: 363, quantity: 1, total: Math.round(363 * 1.1) },
    { name: '日経コンピュータ', price: 1300, quantity: 2, total: Math.round(1300 * 2 * 1.1) },
    { name: '日経ネットワーク', price: 652, quantity: 3, total: Math.round(652 * 3 * 1.1) },
    { name: '医療情報', price: 1500, quantity: 4, total: Math.round(1500 * 4 * 1.1) }
  ];
  localStorage.setItem('order_customer', JSON.stringify({ name, no, orderDate, items }));
  // 顧客名・Noをcustomer_name/customer_noでGETパラメータとして渡して遷移
  const params = new URLSearchParams({ customer_name: name, customer_no: no });
  location.href = 'order_selection.php?' + params.toString();
}

document.addEventListener('DOMContentLoaded', function() {
  // --- 追加: URLパラメータにname/noがあれば入力欄にセット ---
  const urlParams = new URLSearchParams(window.location.search);
  const paramName = urlParams.get('name');
  const paramNo = urlParams.get('no');
  const isReset = urlParams.get('reset') === '1';
  if (isReset) {
    document.getElementById('name').value = '';
    document.getElementById('customer_no').value = '';
  } else {
    if (paramName) document.getElementById('name').value = paramName;
    if (paramNo) document.getElementById('customer_no').value = paramNo;
  }

  const selected = localStorage.getItem('order_selected');
  // デバッグ用: order_selectedの値を表示
  console.log('order_selected:', selected);
  try {
    if (selected) {
      const data = JSON.parse(selected);
      // 顧客名・Noの自動反映
      if(data.name) document.getElementById('name').value = data.name;
      if(data.no) document.getElementById('customer_no').value = data.no;
      // 商品テーブル反映（選択された明細のみ）
      if(Array.isArray(data.details) && data.details.length > 0) {
        const itemNames = document.getElementsByName('item_name[]');
        const quantities = document.getElementsByName('quantity[]');
        const prices = document.getElementsByName('price[]');
        const remarks = document.getElementsByName('remarks[]');
        for(let i=0; i<itemNames.length; i++) {
          if(data.details[i]) {
            itemNames[i].value = data.details[i].product_name || '';
            quantities[i].value = data.details[i].quantity || '';
            prices[i].value = data.details[i].unit_price ? '¥' + data.details[i].unit_price.replace(/[^0-9]/g,'') : '';
            remarks[i].value = data.details[i].remarks || '';
            // 金額計算
            var qty = parseFloat(data.details[i].quantity) || 0;
            var price = parseFloat(data.details[i].unit_price) || 0;
            var total = qty && price ? Math.round(qty * price * 1.1) : '';
            document.getElementsByName('total[]')[i].value = total ? '¥' + total.toLocaleString() : '';
          } else {
            itemNames[i].value = '';
            quantities[i].value = '';
            prices[i].value = '';
            remarks[i].value = '';
            document.getElementsByName('total[]')[i].value = '';
          }
          itemNames[i].readOnly = true;
          quantities[i].readOnly = true;
          prices[i].readOnly = true;
          remarks[i].readOnly = true;
        }
      }
    } else {
      // order_selectedが空なら何も表示しない（アラート・リダイレクトなし）
      const itemNames = document.getElementsByName('item_name[]');
      const quantities = document.getElementsByName('quantity[]');
      const prices = document.getElementsByName('price[]');
      for(let i=0; i<itemNames.length; i++) {
        itemNames[i].value = '';
        quantities[i].value = '';
        prices[i].value = '';
        itemNames[i].readOnly = true;
        quantities[i].readOnly = true;
        prices[i].readOnly = true;
      }
    }
  } catch(e) {
    // order_selectedが不正な場合も空欄表示
    const itemNames = document.getElementsByName('item_name[]');
    const quantities = document.getElementsByName('quantity[]');
    const prices = document.getElementsByName('price[]');
    for(let i=0; i<itemNames.length; i++) {
      itemNames[i].value = '';
      quantities[i].value = '';
      prices[i].value = '';
      itemNames[i].readOnly = true;
      quantities[i].readOnly = true;
      prices[i].readOnly = true;
    }
  }
});
// 作成ボタンのモーダル表示
function showCreateModal() {
  var modal = document.getElementById('createModal');
  modal.style.display = 'flex';
}
function hideCreateModal() {
  var modal = document.getElementById('createModal');
  modal.style.display = 'none';
}
function confirmCreate() {
  // beforeunload警告を抑制
  if (typeof suppressBeforeUnload !== 'undefined') suppressBeforeUnload = true;
  hideCreateModal();
  document.getElementById('createForm').submit();
}
// 戻るボタンのモーダル表示
function showBackModal() {
  var selected = localStorage.getItem('order_selected');
  if (selected) {
    var modal = document.getElementById('backModal');
    modal.style.display = 'flex';
  } else {
    // 情報がない場合は確認なしで即遷移
    location.href = 'delivery.php?reset=1';
  }
}
function hideBackModal() {
  var modal = document.getElementById('backModal');
  modal.style.display = 'none';
}
function confirmBack() {
  hideBackModal();
  // 入力内容を破棄
  localStorage.removeItem('order_selected');
  // フォームの値を空に
  document.getElementById('name').value = '';
  document.getElementById('customer_no').value = '';
  const itemNames = document.getElementsByName('item_name[]');
  const quantities = document.getElementsByName('quantity[]');
  const prices = document.getElementsByName('price[]');
  const totals = document.getElementsByName('total[]');
  for(let i=0; i<itemNames.length; i++) {
    itemNames[i].value = '';
    quantities[i].value = '';
    prices[i].value = '';
    totals[i].value = '';
  }
  document.getElementsByName('total_quantity')[0].value = '';
  document.getElementsByName('grand_total')[0].value = '';
  document.getElementsByName('tax_amount')[0].value = '';
  document.getElementsByName('total_with_tax')[0].value = '';
  // 破棄後に遷移
  location.href = 'delivery.php?reset=1';
}
function isFormDirty() {
  const itemNames = document.getElementsByName('item_name[]');
  const quantities = document.getElementsByName('quantity[]');
  for (let i = 0; i < itemNames.length; i++) {
    if ((itemNames[i].value && itemNames[i].value.trim() !== '') ||
        (quantities[i].value && parseFloat(quantities[i].value) > 0)) {
      return true;
    }
  }
  return false;
}
 </script>
<script>
// --- ブラウザバック時の警告制御（注文選択・戻る・作成ボタンは警告なし） ---
(function() {
  let suppressBeforeUnload = false;

  // 注文選択・戻る・作成ボタン押下時は警告を抑制
  document.getElementById('orderSelectBtn')?.addEventListener('click', function() {
    suppressBeforeUnload = true;
  });
  document.querySelector('.back-button')?.addEventListener('click', function() {
    suppressBeforeUnload = true;
  });
  document.querySelector('#createBtnContainer button')?.addEventListener('click', function() {
    suppressBeforeUnload = true;
  });

  function hasTableData() {
    const itemNames = document.getElementsByName('item_name[]');
    const quantities = document.getElementsByName('quantity[]');
    for (let i = 0; i < itemNames.length; i++) {
      if ((itemNames[i].value && itemNames[i].value.trim() !== '') ||
          (quantities[i].value && parseFloat(quantities[i].value) > 0)) {
        return true;
      }
    }
    return false;
  }

  window.addEventListener('beforeunload', function(e) {
    if (suppressBeforeUnload) return;
    if (hasTableData()) {
      e.preventDefault();
      e.returnValue = 'このページを離れますか？入力内容が失われます。';
      return 'このページを離れますか？入力内容が失われます。';
    }
  });

  // popstate（ブラウザの戻る矢印ボタン）でも警告
  window.addEventListener('popstate', function(e) {
    if (suppressBeforeUnload) return;
    if (hasTableData()) {
      if (!confirm('このページを離れますか？入力内容が失われます。')) {
        history.pushState(null, '', location.href); // 戻る操作をキャンセル
      }
    }
  });
})();
</script>

<!-- 作成確認モーダル -->
<div id="createModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; padding:40px 56px; border-radius:16px; text-align:center; max-width: 420px; min-width: 320px; box-shadow:0 4px 24px rgba(0,0,0,0.22); margin:0 auto;">
    <div style="font-size:1.2em; margin-bottom:24px; white-space:nowrap;">本当に作成しますか？</div>
    <div style="display: flex; justify-content: center; gap: 16px;">
      <button onclick="hideCreateModal()" style="margin:0; padding:8px 24px; background:#555; color:#fff; border:none; border-radius:6px; font-weight:bold;">いいえ</button>
      <button onclick="confirmCreate()" style="margin:0; padding:8px 24px; background:#4CAF50; color:#fff; border:none; border-radius:6px; font-weight:bold;">はい</button>
    </div>
  </div>
</div>
<!-- 戻る確認モーダル -->
<div id="backModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:9999; align-items:center; justify-content:center;">
  <div style="background:#fff; padding:40px 56px; border-radius:16px; text-align:center; max-width: 420px; min-width: 320px; box-shadow:0 4px 24px rgba(0,0,0,0.22); margin:0 auto;">
    <div style="font-size:1.2em; margin-bottom:24px; white-space:nowrap;">入力内容が廃棄されます。よろしいですか？</div>
    <div style="display: flex; justify-content: center; gap: 16px;">
      <button onclick="hideBackModal()" style="margin:0; padding:8px 24px; background:#555; color:#fff; border:none; border-radius:6px; font-weight:bold;">いいえ</button>
      <button onclick="confirmBack()" style="margin:0; padding:8px 24px; background:#4CAF50; color:#fff; border:none; border-radius:6px; font-weight:bold;">はい</button>
    </div>
  </div>
</div>
</body>
</html>