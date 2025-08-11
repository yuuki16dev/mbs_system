<?php
// DB接続
$pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// 検索・絞り込みパラメータの取得
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$storeFilter = isset($_GET['store']) ? $_GET['store'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';

// ソート設定
// ソート可能なカラムとデータベースカラム名のマッピング
$allowedSortColumns = [
    'customer_id' => 'c.customer_id',
    'customer_name' => 'c.name',
    'latest_order_date' => 'latest_order_date', // GROUP BYでエイリアスを使っているのでエイリアスを指定
    'total_sales' => 'total_sales' // GROUP BYでエイリアスを使っているのでエイリアスを指定
];
$sortColumn = isset($_GET['sort_col']) && array_key_exists($_GET['sort_col'], $allowedSortColumns) ? $_GET['sort_col'] : 'customer_id';
$sortDirection = (isset($_GET['sort_dir']) && $_GET['sort_dir'] === 'DESC') ? 'DESC' : 'ASC';

// ページネーション設定
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// --- SQLクエリの構築 ---
$sql = "
    SELECT
        c.customer_id,
        c.name AS customer_name,
        s.store_name,
        MAX(o.order_date) AS latest_order_date,
        SUM(od.quantity * od.unit_price * 1.1) AS total_sales
    FROM
        customers c
    JOIN
        stores s ON c.store_id = s.store_id
    LEFT JOIN
        orders o ON c.customer_id = o.customer_id
    LEFT JOIN
        order_details od ON o.order_id = od.order_id
    WHERE 1=1
";

$params = [];

// 検索キーワードによる絞り込み
if ($searchTerm !== '') {
    $sql .= " AND (c.customer_id LIKE ? OR c.name LIKE ?)";
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
}

// 店舗による絞り込み
if ($storeFilter !== '') {
    $sql .= " AND s.store_name = ?";
    $params[] = $storeFilter;
}

// 日付による絞り込み (ここでは注文日でフィルタリングします)
// ここでは o.order_date は NULL の可能性があるため、IS NOT NULL を追加
if ($dateFilter !== '') {
    $sql .= " AND o.order_date = ? AND o.order_date IS NOT NULL";
    $params[] = $dateFilter;
}

$sql .= " GROUP BY c.customer_id, c.name, s.store_name";

// ソート順
$sql .= " ORDER BY " . $allowedSortColumns[$sortColumn] . " " . $sortDirection;


// 総アイテム数を取得するためのクエリ (ページネーション用)
$countSql = "
    SELECT COUNT(DISTINCT c.customer_id)
    FROM customers c
    JOIN stores s ON c.store_id = s.store_id
    LEFT JOIN orders o ON c.customer_id = o.customer_id
    LEFT JOIN order_details od ON o.order_id = od.order_id
    WHERE 1=1
";
$countParams = [];

if ($searchTerm !== '') {
    $countSql .= " AND (c.customer_id LIKE ? OR c.name LIKE ?)";
    $countParams[] = '%' . $searchTerm . '%';
    $countParams[] = '%' . $searchTerm . '%';
}
if ($storeFilter !== '') {
    $countSql .= " AND s.store_name = ?";
    $countParams[] = $storeFilter;
}
if ($dateFilter !== '') {
    $countSql .= " AND o.order_date = ? AND o.order_date IS NOT NULL"; // ここにも同様の条件を追加
    $countParams[] = $dateFilter;
}

$stmtCount = $pdo->prepare($countSql);
$stmtCount->execute($countParams);
$totalItems = $stmtCount->fetchColumn();
$totalPages = ceil($totalItems / $perPage);


$sql .= " LIMIT " . (int)$perPage . " OFFSET " . (int)$offset;


$stmt = $pdo->prepare($sql);
$stmt->execute($params); 
$displayData = $stmt->fetchAll();

// 顧客ごとにリードタイムを計算
// リードタイム計算（商品ごとに注文明細と納品明細を紐付けて計算）
// 注文・納品両方に存在し、cancel_flag=0・return_flag=0の顧客のみリードタイム計算
$leadTimes = [];
// 注文・納品両方に存在する顧客ID一覧を取得
$sqlActiveCustomers = "
    SELECT DISTINCT o.customer_id
    FROM orders o
    JOIN order_details od ON o.order_id = od.order_id AND od.cancel_flag = 0
    JOIN deliveries d ON o.customer_id = d.customer_id
    JOIN delivery_details dd ON d.delivery_id = dd.delivery_id AND dd.return_flag = 0 AND od.product_name = dd.product_name
    WHERE o.order_date IS NOT NULL AND d.delivery_date IS NOT NULL
";
$activeCustomerIds = $pdo->query($sqlActiveCustomers)->fetchAll(PDO::FETCH_COLUMN);

foreach ($displayData as $row) {
    $customerId = $row['customer_id'];
    if (!in_array($customerId, $activeCustomerIds)) {
        // 注文・納品両方に存在しない顧客は計算対象外
        $leadTimes[$customerId] = '';
        continue;
    }
    $sqlLead = "
        SELECT
            o.order_date,
            d.delivery_date,
            dd.quantity
        FROM order_details od
        JOIN orders o ON od.order_id = o.order_id AND od.cancel_flag = 0
        JOIN delivery_details dd ON od.product_name = dd.product_name AND dd.return_flag = 0
        JOIN deliveries d ON dd.delivery_id = d.delivery_id AND d.customer_id = o.customer_id
        WHERE o.customer_id = ?
          AND o.order_date IS NOT NULL
          AND d.delivery_date IS NOT NULL
    ";
    $stmtLead = $pdo->prepare($sqlLead);
    $stmtLead->execute([$customerId]);
    $leadRows = $stmtLead->fetchAll();

    $leadtimeSum = 0;
    $totalQty = 0;
    foreach ($leadRows as $lead) {
        $qty = (int)$lead['quantity'];
        $orderDate = $lead['order_date']; // ordersテーブル
        $deliveryDate = $lead['delivery_date']; // deliveriesテーブル
        if ($qty > 0 && $orderDate && $deliveryDate) {
            $days = (strtotime($deliveryDate) - strtotime($orderDate)) / (60 * 60 * 24);
            $leadtimeSum += $qty * $days;
            $totalQty += $qty;
        }
    }
    $leadTimes[$customerId] = ($totalQty > 0) ? round($leadtimeSum / $totalQty, 1) : '';
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>統計情報</title>
    <link rel="stylesheet" href="styles.css"> 
    <style>
        /* CSSは以前のものをそのまま使用 */
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #fff;
        }

        .menu-button {
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
        }

        main {
            width: 80%;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(255,255,255,0.1);
        }

        .table-container {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            gap: 20px;
        }

        .table-container > div {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
        }

        .table-container label,
        .table-container input {
            margin-bottom: 30px;
        }

        .table-container input {
            padding: 8px;
            width: 20%;
        }

        .table-container input[type="date"] {
            width: auto;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin: 20px 0;
            background-color: #fff;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 10px;
            text-align: center;
        }

        #tr_header{
            background-color: #f2f2f2;
        }

        th.no-col {
            width: 120px;
            min-width: 40px;
            max-width:130px;
            text-align: center;
            padding-left: 0;
            padding-right: 0;
            background-color: #f2f2f2;
        }

        th.leadtime-col {
            width: 150px;
            min-width: 60px;
            max-width: 400px;
            text-align: center;
            padding-left: 0;
            padding-right: 0;
            background-color: #f2f2f2;
        }

        .input-field {
            width: 100%;
            border: none;
            background: none;
            text-align: center;
        }

        .button-container {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .button {
            padding: 10px 20px;
            text-decoration: none;
            color: #fff;
            background-color: #333;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }

        .button:hover {
            background-color: #555;
        }

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
            justify-content: space-around;
        }

        .modal-buttons button {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .modal-buttons .confirm {
            background-color: #333;
            color: #fff;
        }

        .modal-buttons .cancel {
            background-color: #ccc;
            color: #333;
        }

        .search-bar {
            margin: 0 auto;
            width: 80%;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            margin-bottom: -20px;
            margin-top: 60px;
            gap: 20px;
        }

        .search-bar>div {
            /* 日付と検索キーワードのグループ */
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .search-bar input[type="number"],
        .search-bar input[type="date"],
        .search-bar input[type="text"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: auto;
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

        .search-bar button:hover {
            background-color: #0056b3;
        }

        .pagination {
            text-align: center;
            margin-top: 10px;
        }

        .pagination button {
            margin: 0 10px;
            padding: 6px 12px;
            background-color: #333;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .pagination button:hover {
            background-color: #555;
        }

        .back-button {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: 2px solid #333;
            border-radius: 5px;
            background-color: #ffffff;
            color: #333;
            font-size: 14px;
            transition: 0.3s;
        }

        .back-button:hover {
            background-color: #333;
            color: #ffffff;
        }
        
        .search-bar input[type="text"] { /* 個別に指定 */
            height: 38px;
            font-size: 14px;
            padding: 0 12px;
            box-sizing: border-box;
            margin-top: 2px;
            vertical-align: middle;
        }
        .search-bar button { /* 個別に指定 */
            height: 38px;
            font-size: 14px;
            padding: 0 12px;
            box-sizing: border-box;
            margin-top: 2px;
            vertical-align: middle;
        }

        button.submit{
            display: flex;
        }
        /* ソートボタンの見た目 */
        th button {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-left: 4px;
            color: black;
            padding: 0;
            vertical-align: middle;
        }
        th button.asc::after {
            content: " ▲";
        }
        th button.desc::after {
            content: " ▼";
        }
    </style>
</head>
<body>
    <header>
        <div class="home-title">統計情報</div>
    </header>
    <?php include('navbar.php'); ?> 
    <main style="margin-top: 0;">

        <div class="search-bar">
            <form id="searchForm" method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="width: 100%;">
                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <label for="date">日付</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">

                        <label for="store" style="margin-left: 20px;">店舗:</label>
                        <select id="store" name="store" style="height: 38px;">
                            <option value="">店舗を選択</option>
                            <option value="緑橋本店" <?php if($storeFilter=="緑橋本店") echo "selected"; ?>>緑橋橋本店</option>
                            <option value="深江橋店" <?php if($storeFilter=="深江橋店") echo "selected"; ?>>深江橋店</option>
                            <option value="今里店" <?php if($storeFilter=="今里店") echo "selected"; ?>>今里店</option>
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 10px;">
                        <input type="text" id="search" name="search" placeholder="No. または 顧客名" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit">検索</button>
                    </div>
                </div>
                <input type="hidden" name="page" value="<?php echo htmlspecialchars($page); ?>" id="current_page_input">
                <input type="hidden" name="sort_col" value="<?php echo htmlspecialchars($sortColumn); ?>" id="sort_col_input">
                <input type="hidden" name="sort_dir" value="<?php echo htmlspecialchars($sortDirection); ?>" id="sort_dir_input">
            </form>
        </div>

        <table>
            <thead>
                <tr id="tr_header">
                    <th class="no-col">
                        No.
                        <button type="button" class="sort-btn" data-column="customer_id"></button>
                    </th>
                    <th>
                        顧客名
                        <button type="button" class="sort-btn" data-column="customer_name"></button>
                    </th>
                    <th class="leadtime-col">
                        リードタイム
                        <button type="button" class="sort-btn" data-column="latest_order_date"></button>
                    </th>
                    <th>
                        累計売上
                        <button type="button" class="sort-btn" data-column="total_sales"></button>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($displayData)): ?>
                    <tr>
                        <td colspan="4">該当するデータがありません。</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($displayData as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['customer_id']) ?></td>
                        <td><?= htmlspecialchars($row['customer_name']) ?></td>
                        <td>
                            <?= isset($leadTimes[$row['customer_id']]) && $leadTimes[$row['customer_id']] !== '' 
                                ? $leadTimes[$row['customer_id']] . '日' 
                                : '' ?>
                        </td>
                        <td><?= ($row['total_sales'] ?? 0) > 0 ? '¥' . number_format($row['total_sales']) : '' ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="pagination">
            <?php
            // 現在のクエリパラメータを維持するためのURLSearchParamsオブジェクトを構築
            // これにより、検索、店舗、日付、ソートの各パラメータを維持できる
            $baseParams = $_GET;
            unset($baseParams['page']); // ページ番号はこれから動的に設定するため削除

            // 「前へ」ボタンのリンク生成
            $queryStrPrev = http_build_query(array_merge($baseParams, ['page' => $page - 1]));
            // 「次へ」ボタンのリンク生成
            $queryStrNext = http_build_query(array_merge($baseParams, ['page' => $page + 1]));
            ?>
            <a href="?<?php echo $queryStrPrev; ?>" class="button" <?php echo ($page <= 1) ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>前へ</a>
            <span><?php echo $page; ?> / <?php echo $totalPages; ?> ページ</span>
            <a href="?<?php echo $queryStrNext; ?>" class="button" <?php echo ($page >= $totalPages) ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>次へ</a>
        </div>

    </main>
    
    <a href="home.php" class="back-button">戻る</a>

    <script src="script.js"></script> 
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const currentSortCol = urlParams.get('sort_col') || 'customer_id';
        const currentSortDir = urlParams.get('sort_dir') || 'ASC';

        // ソートボタンの初期状態を設定
        document.querySelectorAll('.sort-btn').forEach(button => {
            const column = button.getAttribute('data-column');
            button.classList.remove('asc', 'desc'); // クラスをリセット
            if (column === currentSortCol) {
                button.classList.add(currentSortDir.toLowerCase());
            } else {
                // デフォルトのアイコン（▼）を表示するために、初期状態ではdescクラスをつける
                // または、ソートされていないカラムのデフォルトアイコンを設定
                button.classList.add('desc');
            }
        });

        // ソートボタンのイベントリスナー
        document.querySelectorAll('.sort-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const column = this.getAttribute('data-column');
                const form = document.getElementById('searchForm');
                
                // 現在のソート方向を取得し、次のソート方向を決定
                let newSortDir = 'ASC';
                if (column === currentSortCol && currentSortDir === 'ASC') {
                    newSortDir = 'DESC';
                }

                // hiddenフィールドの値を更新
                document.getElementById('sort_col_input').value = column;
                document.getElementById('sort_dir_input').value = newSortDir;
                document.getElementById('current_page_input').value = 1; // ソート時は1ページ目に戻す

                // フォームを送信
                form.submit();
            });
        });
    });
    </script>
</body>
</html>