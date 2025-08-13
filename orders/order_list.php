<?php
// PHPエラー表示設定 (開発時のみONにすることを推奨)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// DBから注文データを取得
$host = 'localhost';
$db   = 'mbs_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // 検索条件
    $searchDate = $_GET['date'] ?? '';
    $searchTerm = $_GET['search'] ?? '';
    $sortOrder = $_GET['sort'] ?? 'asc';
    $orderBy = ($sortOrder === 'desc') ? 'o.order_id DESC' : 'o.order_id ASC';

    $sql = "SELECT o.order_id AS no, o.order_date, c.name AS customer_name,
            GROUP_CONCAT(od.product_name SEPARATOR ', ') AS item_name
            FROM orders o
            JOIN customers c ON o.customer_id = c.customer_id
            JOIN order_details od ON o.order_id = od.order_id AND od.cancel_flag = 0
            WHERE EXISTS (SELECT 1 FROM order_details od2 WHERE od2.order_id = o.order_id AND od2.cancel_flag = 0)";
    $params = [];
    if (!empty($searchDate)) {
        $sql .= " AND o.order_date = :order_date";
        $params[':order_date'] = $searchDate;
    }
    if (!empty($searchTerm)) {
        // 数字のみならNo完全一致、それ以外は顧客名・商品名部分一致
        if (ctype_digit($searchTerm)) {
            $sql .= " AND o.order_id = :term_no";
            $params[':term_no'] = $searchTerm;
        } else {
            $sql .= " AND (c.name LIKE :term OR od.product_name LIKE :term)";
            $params[':term'] = '%' . $searchTerm . '%';
        }
    }
    $sql .= " GROUP BY o.order_id, o.order_date, c.name ORDER BY $orderBy";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allDeliveryNotes = $stmt->fetchAll();
} catch (Exception $e) {
    $allDeliveryNotes = [];
}

// ページネーション
$perPage = 10;
$totalCount = count($allDeliveryNotes);
$totalPages = max(1, ceil($totalCount / $perPage));
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
if ($currentPage > $totalPages) $currentPage = $totalPages;
$startIndex = ($currentPage - 1) * $perPage;
$displayNotes = array_slice($allDeliveryNotes, $startIndex, $perPage);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文書一覧</title>
    <link rel="stylesheet" href="../style.css"> 
    <style>


    </style>
</head>
<body class="order-list-page">
    <header>
        <div class="home-title">注文書一覧</div>
        <div class="hamburger" id="hamburger-menu">
            <span></span>
            <span></span>
            <span></span>
        </div>
        <nav class="menu" id="menu-nav">
        </nav>
    </header>
            <?php include('../navbar.php'); ?>

    <main>
        <form id="searchForm" method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="search-bar">
                <div style="display: flex; align-items: center; gap: 10px ;">
                    <label for="date">日付:</label>
                    <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($searchDate); ?>">
                </div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <input type="text" id="search" name="search" placeholder="No.または顧客名または商品名" value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit">検索</button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th class="sortable" data-column="0">
        No.
        <?php
        // ソート順をトグルするためのURL生成
        $nextSort = ($sortOrder === 'asc') ? 'desc' : 'asc';
        $sortQuery = $_GET;
        $sortQuery['sort'] = $nextSort;
        $sortQuery['page'] = 1; // ソート時は1ページ目に戻す
        $sortUrl = htmlspecialchars($_SERVER['PHP_SELF']) . '?' . http_build_query($sortQuery);
        ?>
        <a href="<?php echo $sortUrl; ?>">
            <button type="button" id="sortNoBtn"><?php echo $sortOrder === 'asc' ? '▲' : '▼'; ?></button>
        </a>
                        </th>
                        <th>注文日</th>
                        <th>顧客名</th>
                        <th>商品名</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($displayNotes)): ?>
                        <?php foreach ($displayNotes as $note): ?>
                            <tr class="selectable-row" data-no="<?php echo htmlspecialchars($note['no']); ?>" style="cursor:pointer;">
                                <td><?php echo htmlspecialchars($note['no']); ?></td>
                                <td><?php echo htmlspecialchars($note['order_date']); ?></td>
                                <td><?php echo htmlspecialchars($note['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($note['item_name']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">該当するデータがありません。</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="pagination">
                <?php
                // クエリパラメータを維持してページ番号だけ変える
                $queryBase = $_GET;
                unset($queryBase['page']);
                $baseUrl = htmlspecialchars($_SERVER['PHP_SELF']) . '?' . http_build_query($queryBase);

                // 前へボタン
                if ($currentPage > 1) {
                    echo '<a href="' . $baseUrl . '&page=' . ($currentPage - 1) . '"><button type="button">前へ</button></a>';
                }
                // 1ページ目は「前へ」ボタンを表示しない

                // ページ番号表示
                echo '<span>' . $currentPage . ' / ' . $totalPages . 'ページ</span>';

                // 次へボタン
                if ($currentPage < $totalPages) {
                    echo '<a href="' . $baseUrl . '&page=' . ($currentPage + 1) . '"><button type="button">次へ</button></a>';
                } else {
                    echo '<button type="button" disabled>次へ</button>';
                }
                ?>
            </div>
        </form>
    </main>
    <a href="order.php" class="back-button">戻る</a>
    <div class="modal" id="confirmationModal">
        <div class="modal-content"></div>
    </div>
 <script src="script.js"></script>
    
<script>
    // DOMコンテンツが完全に読み込まれた後に実行
    document.addEventListener('DOMContentLoaded', function () {
        const dateInput = document.getElementById('date');
        const searchInput = document.getElementById('search');
 
        // 日付入力が変更されたらフォームを自動的に送信
        dateInput.addEventListener('change', function() {
            this.form.submit();
        });
 
        // 検索入力でEnterキーが押されたらフォームを送信
        searchInput.addEventListener('keypress', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault(); // デフォルトの動作（例：テキストエリアでの改行）を防止
                this.form.submit();
            }
        });
 
        // ページネーションの仮の現在ページ（実際にはサーバーから取得するか、URLのクエリパラメータから取得することが多い）
        let currentPage = 1;
 
        const prevButton = document.querySelector('.pagination button:first-of-type');
        const nextButton = document.querySelector('.pagination button:last-of-type');
        const pageLabel = document.querySelector('.pagination span');
 
        // ハンバーガーメニューの開閉
        const hamburger = document.getElementById('hamburger-menu');
        const menuNav = document.getElementById('menu-nav');
        hamburger.addEventListener('click', function() {
            menuNav.classList.toggle('open');
        });
        // メニュー外クリックで閉じる
        document.addEventListener('click', function(e) {
            if (!hamburger.contains(e.target) && !menuNav.contains(e.target)) {
                menuNav.classList.remove('open');
            }
        });

        // --- テーブルソート機能 ---
        const getCellValue = (tr, idx) => {
            const inputElement = tr.children[idx].querySelector('input');
            return inputElement ? inputElement.value : tr.children[idx].textContent || tr.children[idx].innerText;
        };

        const comparer = (idx, asc) => (a, b) => {
            const v1 = getCellValue(a, idx);
            const v2 = getCellValue(b, idx);
            return asc ? (parseFloat(v1) - parseFloat(v2)) : (parseFloat(v2) - parseFloat(v1));
        };

        const sortNoBtn = document.getElementById('sortNoBtn');
        if (sortNoBtn) {
            let asc = true; // 初期状態は昇順
            sortNoBtn.addEventListener('click', () => {
                const th = sortNoBtn.closest('th');
                const table = th.closest('table');
                const tbody = table.querySelector('tbody');
                const column = parseInt(th.dataset.column, 10);

                asc = !asc; // 昇順・降順をトグル

                // ボタンのテキストを切り替え
                sortNoBtn.textContent = asc ? '▲' : '▼';

                // 行をソートして再配置
                Array.from(tbody.querySelectorAll('tr'))
                    .sort(comparer(column, asc))
                    .forEach(tr => tbody.appendChild(tr));
            });
        }
        
    });
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // tbody内のtrのみ対象
    var rows = document.querySelectorAll('tbody .selectable-row');
    rows.forEach(function(row) {
        row.addEventListener('click', function(e) {
            // aタグやボタン直上クリック時は無視
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
            var no = this.getAttribute('data-no');
            if(no) {
                window.location.href = 'order_display.php?no=' + encodeURIComponent(no);
            }
        });
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#e0f7fa';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
});
</script>
 
</body>
</html>