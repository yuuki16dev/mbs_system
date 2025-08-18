<?php
// PHPエラー表示設定 (開発時のみONにすることを推奨)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// delivery_notes_list.php

// 検索条件の変数を初期化
// $_GETから日付と検索キーワードを取得。もし存在しない場合は空文字列をセット。
$searchDate = $_GET['date'] ?? '';
$searchTerm = $_GET['search'] ?? '';


// ソート順取得（デフォルトは昇順）
$sortOrder = $_GET['sort'] ?? 'asc';

// DBから納品データを取得
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
    $where = [];
    $params = [];
    // return_flag=0の納品明細のみ表示
    $where[] = 'EXISTS (SELECT 1 FROM delivery_details dd2 WHERE dd2.delivery_id = d.delivery_id AND dd2.return_flag = 0)';
    if (!empty($searchDate)) {
        $where[] = 'd.delivery_date = :delivery_date';
        $params[':delivery_date'] = $searchDate;
    }
    if (!empty($searchTerm)) {
        $where[] = '(
            CAST(d.delivery_id AS CHAR) = :term_exact
            OR c.name LIKE :term
            OR dd.product_name LIKE :term
        )';
        $params[':term_exact'] = $searchTerm;
        $params[':term'] = "%$searchTerm%";
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "
        SELECT d.delivery_id AS no, d.delivery_date AS order_date, c.name AS customer_name,
        GROUP_CONCAT(dd.product_name SEPARATOR ', ') AS item_name
        FROM deliveries d
        JOIN customers c ON d.customer_id = c.customer_id
        LEFT JOIN delivery_details dd ON d.delivery_id = dd.delivery_id AND dd.return_flag = 0
        $whereSql
        GROUP BY d.delivery_id, d.delivery_date, c.name
        ORDER BY d.delivery_id " . ($sortOrder === 'desc' ? 'DESC' : 'ASC');
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $allDeliveryNotes = $stmt->fetchAll();
} catch (Exception $e) {
    $allDeliveryNotes = [];
}

// ソート順取得（デフォルトは昇順）
$sortOrder = $_GET['sort'] ?? 'asc';

// ★全データをまずソート
usort($allDeliveryNotes, function ($a, $b) use ($sortOrder) {
    if ($a['no'] == $b['no']) return 0;
    if ($sortOrder === 'desc') {
        return ($a['no'] < $b['no']) ? 1 : -1;
    } else {
        return ($a['no'] > $b['no']) ? 1 : -1;
    }
});

$filteredDeliveryNotes = [];

foreach ($allDeliveryNotes as $note) {
    $match = true;

    // 日付でフィルタリング
    // 検索日付が入力されており、かつ納品書の納品日と一致しない場合、マッチしない
    if (!empty($searchDate) && $note['order_date'] !== $searchDate) {
        $match = false;
    }

    // 検索キーワードでフィルタリング (大文字小文字を区別しない)
    // 検索キーワードが入力されており、かつ納品書のどのフィールドにも含まれない場合、マッチしない
    if ($match && !empty($searchTerm)) {
        $foundInRow = false;
        // No（番号）は完全一致、それ以外は部分一致
        if (
            (string)$note['no'] === $searchTerm ||
            stripos($note['customer_name'], $searchTerm) !== false ||
            stripos($note['item_name'], $searchTerm) !== false
        ) {
            $foundInRow = true;
        }
        if (!$foundInRow) {
            $match = false;
        }
    }

    // すべての条件にマッチした場合、フィルタリングされたリストに追加
    if ($match) {
        $filteredDeliveryNotes[] = $note;
    }
}

// ページネーション用の変数
$perPage = 10; // 1ページあたりの件数
$totalCount = count($filteredDeliveryNotes);
$totalPages = max(1, ceil($totalCount / $perPage));
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($currentPage < 1) $currentPage = 1;
if ($currentPage > $totalPages) $currentPage = $totalPages;

// 表示するデータのスライス
$startIndex = ($currentPage - 1) * $perPage;
$displayNotes = array_slice($filteredDeliveryNotes, $startIndex, $perPage);


?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>納品書一覧</title>
    <link rel="stylesheet" href="../style.css"> 
</head>

<body class="delivery-list-page">
    <header>
        <div class="home-title">納品書一覧</div>
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
                    <input type="text" id="search" name="search" placeholder="No. または 顧客名 または 商品名" value="<?php echo htmlspecialchars($searchTerm); ?>">
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
                        <th>納品日</th>
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
    <a href="delivery.php" class="back-button">戻る</a>
    <div class="modal" id="confirmationModal">
        <div class="modal-content"></div>
    </div>
    <script src="../script.js"></script>


    <script>
        // DOMコンテンツが完全に読み込まれた後に実行
        document.addEventListener('DOMContentLoaded', function() {
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
        document.addEventListener('DOMContentLoaded', function() {
            // テーブル行クリックで詳細画面に遷移
            var rows = document.querySelectorAll('.selectable-row');
            rows.forEach(function(row) {
                row.addEventListener('click', function() {
                    var no = this.getAttribute('data-no');
                    if (no && !isNaN(no)) {
                        window.location.href = 'delivery_display.php?no=' + encodeURIComponent(no);
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