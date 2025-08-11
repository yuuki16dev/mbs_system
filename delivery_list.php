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
    <link rel="stylesheet" href="styles.css"> 
    <style>
        /* CSSスタイル */
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #fff; 
        }

        header { 
            background-color: #333; 
            color: #fff; 
            padding: 15px 0; 
            text-align: center; 
        }

        .home-title { font-size: 28px;
            position: fixed;
            top: 10px;
            left: 20px;
        }
        main {
            width: 90%;
            margin: 20px auto;
            padding: 20px;
            /* background-color: #fff; 削除 */
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(255, 255, 255, 0.1);
        }
 
        .search-bar {
            margin: 0 auto;
            width: 80%;
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            margin-bottom: 20px;
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

 
        table {
            border-collapse: collapse;
            width: 90%;
            margin: 20px 0;
            background-color: #fff;
        }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: center; }
        th { background-color: #f2f2f2; }
 
        .pagination { text-align: center; margin-top: 10px; }
        .pagination button {
            margin: 0 10px;
            padding: 6px 12px;
            background-color: #333;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .pagination button:hover { background-color: #555; }
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





                /* ソート可能な見出しのスタイル */
        .sortable {
            cursor: pointer;
            position: relative; /* ソートアイコンの位置調整のため */
            /*display: flex;  テキストとアイコンを横並びにする */
            justify-content: center; /* 中央揃え */
            align-items: center; /* 垂直方向中央揃え */
            padding-right: 20px; /* アイコン分のスペースを確保 */
            white-space: nowrap; /* テキストとアイコンが改行されないように */
        }

        .sort-icon {
            position: absolute;
            right: 5px; /* 右端からの距離 */
            top: 50px;
            font-size: 20px; /* アイコンのサイズ */
            color: black; /* デフォルトの色 */
            /* transformとtransitionはテキスト切り替えのため削除 */
            top: 50%; /* 親要素の中心に配置 */
            display: block; /* 確実な表示のため */
            line-height: 1; /* アイコンの行高さを調整 */
        }

        input[type="date"] {
            width: 100px;
            padding: 6px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
      
        /* No.列 */
        table th:nth-child(1),
        table td:nth-child(1) {
            width: 60px;
            max-width: 60px;
            white-space: nowrap;
            text-align: center;
        }

        /* 顧客名列 */
        table th:nth-child(3),
        table td:nth-child(3),
        /* 商品名列 */
        table th:nth-child(4),
        table td:nth-child(4) {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        table {
        margin-left: auto;
        margin-right: auto;
        }




        .search-bar input[type="text"],
        .search-bar button {
        height: 38px;              /* 入力欄に近い高さ */
        font-size: 14px;
        padding: 0 12px;
        box-sizing: border-box;
        margin-top: 2px;           /* ちょっとだけ調整（必要に応じて） */
        vertical-align: middle;
        }


    </style>
</head>

<body>
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
    <?php include('navbar.php'); ?>

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
    <script src="script.js"></script>


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