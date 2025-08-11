<?php
session_start();
if (isset($_SESSION['success'])) {
    echo "<div style='color: green; font-weight: bold; margin-bottom: 10px;'>" . htmlspecialchars($_SESSION['success']) . "</div>";
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    echo "<div style='color: red; font-weight: bold; margin-bottom: 10px;'>" . htmlspecialchars($_SESSION['error']) . "</div>";
    unset($_SESSION['error']);
}

// 店舗リスト取得
$storeOptions = [];
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');
    $stmt = $pdo->query("SELECT store_id, store_name FROM stores ORDER BY store_name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storeOptions[] = $row;
    }
} catch (Exception $e) {
    // エラー時は空のまま
}
$selectedStore = $_GET['store'] ?? '';

// 店舗が選ばれた場合はアップロード済みデータを破棄
if (isset($_GET['store'])) {
    unset($_SESSION['uploaded_customers']);
    unset($_SESSION['uploaded_excel_filename']);
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');

    // Excelアップロードデータがあり、店舗指定がないときだけ表示
    if (isset($_SESSION['uploaded_customers']) && is_array($_SESSION['uploaded_customers']) && !isset($_GET['store'])) {
        $uploaded = $_SESSION['uploaded_customers'];
        $excel_filename = isset($_SESSION['uploaded_excel_filename']) ? $_SESSION['uploaded_excel_filename'] : '';
        $limit = 7;
        $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $total_records = count($uploaded);
        $total_pages = max(1, ceil($total_records / $limit));
        $offset = ($current_page - 1) * $limit;
        $rows = array_slice($uploaded, $offset, $limit);
    } else {
        $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $limit = 7;
        $offset = ($current_page - 1) * $limit;

        $where = '';
        $params = [];
        if ($selectedStore !== '') {
            $where = 'WHERE store_id = :store_id';
            $params[':store_id'] = $selectedStore;
        }

        $stmt = $pdo->prepare("
            SELECT store_id, name, staff, address, phone_number, delivery_location, remarks, registration_date 
            FROM customers 
            $where
            ORDER BY customer_id DESC 
            LIMIT :limit OFFSET :offset
        ");
        if ($selectedStore !== '') {
            $stmt->bindValue(':store_id', $selectedStore, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM customers $where");
        if ($selectedStore !== '') {
            $countStmt->bindValue(':store_id', $selectedStore, PDO::PARAM_INT);
        }
        $countStmt->execute();
        $total_records = $countStmt->fetchColumn();
        $total_pages = max(1, ceil($total_records / $limit));
    }

    // 以下テーブル描画処理はそのままでOK
} catch (Exception $e) {
    echo "<tr><td colspan='7'>データの読み込みに失敗しました: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
}
?>



<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>顧客情報管理</title>
    <link rel="stylesheet" href="styles.css"> <!-- 共通のCSSファイル -->
    <style>
        .menu-button {
            font-size: 24px;
            background: none;
            border: none;
            cursor: pointer;
        }

        main {
            width: 90%;
            margin-top: 20px;
        }

        .table-container {
            display: flex;
            flex-direction: row;
            /* フォーム要素を横に並べる */
            justify-content: space-between;
            /* 要素間のスペースを均等に */
            align-items: center;
            /* 要素を縦方向に中央揃え */
            margin-bottom: 10px;
            /* フォーム全体の下の余白 */
            gap: 20px;
            /* 要素間に均等なスペースを追加 */
        }

        .table-container>div {
            display: flex;
            flex-direction: row;
            /* ラベルと入力フィールドを横並びに */
            align-items: center;
            /* ラベルと入力フィールドを中央揃え */
            gap: 10px;
            /* ラベルと入力フィールドの間隔 */
        }



        .table-container label,
        .table-container input {
            margin-bottom: px;
            /* 各要素間のスペースを広げる */
        }

        .table-container input {
            padding: 5px;
            /* 入力フィールドの内側の余白を調整 */
            width: 20%;
            /* 入力フィールドを横幅いっぱいに */
        }

        .table-container input[type="date"] {
            width: auto;
            /* 日付入力フィールドは自動サイズに */
        }

        table {
            border-collapse: collapse;
            height: 5px;
            width: 100%;
            margin: 20px 0;
            background-color: #fff;
        }

        table,
        th,
        td {
            height: 5px;
            border: 1px solid #333;
        }

        th,
        td {
            padding: 10px;
            height: 10Spx;
            text-align: center;
        }

        .input-field {
            width: 98%;
            /* 幅を広げる */
            min-width: 150px;
            /* 必要に応じて最小幅を指定 */
            border: none;
            background: none;
            text-align: center;
            padding: 8px;
            /* 入力欄の高さも少し広げる */
            box-sizing: border-box;
        }

        .button-container {
            width: 100%;
            display: flex;
            justify-content: center !important;
            align-items: center;
            margin-top: 20px;
        }

        .button-container .button {
            padding: 8px 24px;
            font-size: 16px;
            min-width: 80px;
            min-height: 36px;
            width: auto !important;
            max-width: 160px;
            display: inline-block !important;
            box-sizing: border-box;
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
            display: flex;
            justify-content: flex-start;
            margin-bottom: 20px;
        }

        .search-bar input[type="date"],
        .search-bar input[type="text"],
        .search-bar button {
            padding: 8px;
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
            bottom: 0;
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

        /* ソートボタンの見た目を▲に */
        .sort-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-left: 4px;
            color: black;
            padding: 0;
            vertical-align: middle;
        }

        .sort-btn::after {
            content: "⇅";
            font-size: 14px;
        }

        .sort-btn.asc::after {
            content: "⇅";
        }

        .sort-btn.desc::after {
            content: "⇅";
        }
    </style>
</head>

<body>
    <header>
        <div class="home-title">顧客情報管理</div>
    </header>
    <?php include('navbar.php'); ?>
    <main>

        <?php if (isset($_SESSION['last_updated_at'])): ?>
            <div style="color: #555; font-size: 14px; margin-bottom: 10px; text-align: right;">
                最終反映日：<?= htmlspecialchars($_SESSION['last_updated_at']) ?>
            </div>
        <?php endif; ?>

        <form method="get" action="kokyaku.php">
            <div class="search-bar">
                <select name="store" id="store" onchange="this.form.submit()">
                    <option value="">全店舗</option>
                    <?php foreach ($storeOptions as $store): ?>
                        <option value="<?= htmlspecialchars($store['store_id']) ?>" <?= ($selectedStore == $store['store_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($store['store_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <form id="excelUploadForm" action="upload_excel.php" method="post" enctype="multipart/form-data" style="margin-top: 20px;">
            <input type="file" name="excelFile" accept=".xlsx" required>
            <button type="submit" class="button">反映する</button>
        </form>

        <form id="createForm" method="post" action="submit_delivery.php">
            <input type="hidden" name="store_id[]" value="<?= htmlspecialchars($row['store_id'] ?? $selectedStore) ?>">
            <input type="hidden" name="page" value="<?= htmlspecialchars($_GET['page'] ?? 1) ?>">
            <input type="hidden" name="sort_column" value="<?= htmlspecialchars($_GET['sort_column'] ?? '') ?>">
            <input type="hidden" name="sort_order" value="<?= htmlspecialchars($_GET['sort_order'] ?? '') ?>">
            <input type="hidden" name="store" value="<?= htmlspecialchars($selectedStore) ?>">
            <div class="table-container" style="position: relative;">
                <?php
                // ファイル名をテーブルの右上に表示
                if (isset($_SESSION['uploaded_excel_filename']) && !empty($_SESSION['uploaded_excel_filename'])) {
                    echo '<div style="
                position: absolute;
                top: -30px;
                right: 0;
                color: green;
                font-weight: bold;
                background:rgb(248, 248, 248);
                padding: 4px 12px;
                border-radius: 6px;
                font-size: 15px;
                z-index: 2;
            ">表示中のExcelファイル：' . htmlspecialchars($_SESSION['uploaded_excel_filename']) . '</div>';
                }
                ?>
                <table>
                    <thead>
                        <tr>
                            <th class="sortable" data-column="0">
                                顧客No
                                <button type="button" class="sort-btn" data-column="0"></button>
                            </th>
                            <th class="sortable" data-column="1">
                                顧客名
                                <button type="button" class="sort-btn" data-column="1"></button>
                            </th>
                            <th class="sortable" data-column="7">
                                担当者名
                                <button type="button" class="sort-btn" data-column="7"></button>
                            </th>
                            <th class="sortable" data-column="2">
                                住所
                                <button type="button" class="sort-btn" data-column="2"></button>
                            </th>
                            <th class="sortable" data-column="3">
                                電話番号
                                <button type="button" class="sort-btn" data-column="3"></button>
                            </th>
                            <th class="sortable" data-column="4">
                                配達先住所
                                <button type="button" class="sort-btn" data-column="4"></button>
                            </th>
                            <th class="sortable" data-column="5">
                                備考欄
                                <button type="button" class="sort-btn" data-column="5"></button>
                            </th>
                            <th class="sortable" data-column="6">
                                顧客登録日
                                <button type="button" class="sort-btn" data-column="6"></button>
                            </th>
                        </tr>
                    </thead>
                    <?php

                    try {
                        $pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '');

                        // アップロードされた顧客データがある場合はページングも適用
                        if (isset($_SESSION['uploaded_customers']) && is_array($_SESSION['uploaded_customers'])) {
                            $uploaded = $_SESSION['uploaded_customers'];
                            $excel_filename = isset($_SESSION['uploaded_excel_filename']) ? $_SESSION['uploaded_excel_filename'] : '';
                            $limit = 7; // 1ページあたりの件数
                            $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                            $total_records = count($uploaded);
                            $total_pages = max(1, ceil($total_records / $limit));
                            $offset = ($current_page - 1) * $limit;
                            $rows = array_slice($uploaded, $offset, $limit);
                            // セッションはページングが終わるまで消さない
                            // unset($_SESSION['uploaded_customers']);
                        } else {
                            $current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
                            $limit = 7;
                            $offset = ($current_page - 1) * $limit;

                            // 店舗で絞り込み
                            $selectedStore = $_GET['store'] ?? '';

                            $where = '';
                            $params = [];
                            if ($selectedStore !== '') {
                                $where = 'WHERE store_id = :store_id';
                                $params[':store_id'] = $selectedStore;
                            }

                            $sortableColumns = [
                                0 => 'customer_id',
                                1 => 'name',
                                2 => 'address',
                                3 => 'phone_number',
                                4 => 'delivery_location',
                                5 => 'remarks',
                                6 => 'registration_date',
                                7 => 'staff'
                            ];
                            // 初期表示は顧客No昇順
                            if (!isset($_GET['sort_column'])) {
                                $sort_column = 'customer_id';
                                $sort_order = 'ASC';
                            } else {
                                $sort_column = isset($sortableColumns[$_GET['sort_column']]) ? $sortableColumns[$_GET['sort_column']] : 'customer_id';
                                $sort_order = (isset($_GET['sort_order']) && $_GET['sort_order'] === 'asc') ? 'ASC' : 'DESC';
                            }

                            $stmt = $pdo->prepare("
        SELECT customer_id, store_id, name, staff, address, phone_number, delivery_location, remarks, registration_date 
        FROM customers 
        $where
        ORDER BY $sort_column $sort_order
        LIMIT :limit OFFSET :offset
    ");
                            if ($selectedStore !== '') {
                                $stmt->bindValue(':store_id', $selectedStore, PDO::PARAM_INT);
                            }
                            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                            $stmt->execute();
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        }

                        if (empty($rows)) {
                            echo "<tr><td colspan='8' style='text-align: center;'>表示するデータがありません。</td></tr>";
                        } else {
                            $excelMode = isset($_SESSION['uploaded_customers']) && is_array($_SESSION['uploaded_customers']);
                            foreach ($rows as $i => $row):
                    ?>
                                <tr>
                                    <td>
                                        <?php
                                        // Excelアップロード時はcustomer_idがあればそれを、なければExcelの行番号（1からの連番）を表示
                                        if ($excelMode) {
                                            if (isset($row['customer_id']) && $row['customer_id'] !== '') {
                                                echo htmlspecialchars($row['customer_id']);
                                            } else {
                                                echo $offset + $i + 1; // ページング考慮
                                            }
                                        } else {
                                            echo htmlspecialchars($row['customer_id'] ?? '');
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <!-- store_idをhiddenでセット（各行ごとに1つ） -->
                                        <input type="hidden" name="store_id[]" value="<?= htmlspecialchars($row['store_id'] ?? $selectedStore) ?>">
                                        <input type="text" name="customer_name[]" class="input-field" value="<?= htmlspecialchars($row['name'] ?? '') ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="text" name="staff[]" class="input-field" value="<?= htmlspecialchars($row['staff'] ?? '') ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="text" name="address[]" class="input-field" value="<?= htmlspecialchars($row['address'] ?? '') ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="text" name="phone_number[]" class="input-field" value="<?= htmlspecialchars($row['phone_number'] ?? '') ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="text" name="delivery_location[]" class="input-field" value="<?= htmlspecialchars($row['delivery_location'] ?? '') ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="text" name="remarks[]" class="input-field" value="<?= htmlspecialchars($row['remarks'] ?? '') ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="text" name="registration_date[]" class="input-field" value="<?= htmlspecialchars($row['registration_date'] ?? '') ?>" readonly>
                                    </td>
                                </tr>
                    <?php
                            endforeach;
                        }
                    } catch (Exception $e) {
                        echo "<tr><td colspan='7'>データの読み込みに失敗しました: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                    }
                    ?>
                </table>
            </div>

            <div class="button-container">
                <button type="submit" class="button">保存</button>
            </div>

            <div class="pagination">
                <?php
                // 現在のクエリパラメータを維持
                $baseParams = $_GET;

                // Excelアップロード中はstore_idをアップロードデータから取得
                if (isset($_SESSION['uploaded_customers']) && is_array($_SESSION['uploaded_customers']) && count($rows) > 0) {
                    $firstStoreId = $rows[0]['store_id'] ?? '';
                    if ($firstStoreId !== '') {
                        $baseParams['store'] = $firstStoreId;
                    }
                } else {
                    $baseParams['store'] = $selectedStore;
                }

                if (isset($baseParams['page'])) unset($baseParams['page']);
                $queryStrPrev = http_build_query(array_merge($baseParams, ['page' => $current_page - 1]));
                $queryStrNext = http_build_query(array_merge($baseParams, ['page' => $current_page + 1]));
                ?>
                <?php if ($current_page > 1): ?>
                    <a href="?<?= $queryStrPrev ?>" class="button">前へ</a>
                <?php endif; ?>
                <span><?= $current_page ?>ページ / <?= $total_pages ?>ページ</span>
                <?php if ($current_page < $total_pages): ?>
                    <a href="?<?= $queryStrNext ?>" class="button">次へ</a>
                <?php endif; ?>
            </div>
        </form>

    </main>

    <a href="home.php" class="back-button">戻る</a>

    <div class="modal" id="confirmationModal">
        <div class="modal-content">
            <p>保存してもよろしいですか？</p>
            <div class="modal-buttons">
                <button class="confirm">はい</button>
                <button class="cancel">いいえ</button>
            </div>
        </div>
    </div>
    <script src="script.js"></script> <!-- 共通のJavaScriptファイル -->

    <script>
        document.querySelectorAll('.sort-btn').forEach(btn => {
            btn.addEventListener('click', function() {
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
                // urlParams.set('page', 1); // ←この行を削除
                window.location.search = urlParams.toString();
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            // 保存ボタンの送信をAjax化
            const form = document.getElementById('createForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault(); // 画面遷移を防ぐ

                    const formData = new FormData(form);

                    fetch('submit_delivery.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.text())
                        .then(data => {
                            alert('保存が完了しました');
                        })
                        .catch(error => {
                            alert('保存に失敗しました');
                        });
                });
            }
        });
    </script>

</body>

</html>