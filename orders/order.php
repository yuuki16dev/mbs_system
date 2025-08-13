<?php
// ここにPHPで必要な処理を追加できます。
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>注文書</title>
    <!-- 共通のCSSファイル -->
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <!-- ホーム画面タイトル -->
    <div class="home-title">注文書</div>

    <!-- ハンバーガーメニュー -->
    <div class="hamburger" id="hamburger">
        <div></div>
        <div></div>
        <div></div>
    </div>

    <!-- ナビゲーションメニュー -->
    <div class="nav-menu" id="navMenu">
        <a href="../home.php">ホーム画面</a>
        <a href="deliveries/delivery.php">納品書</a>
        <a href="toukei.php">統計情報</a>
        <a href="kokyaku.php">顧客情報管理</a>
    </div>

    <!-- ボタンエリア -->
    <div class="button-container">
        <a href="order_create.php" class="custom-width">注文書作成</a>  <!-- 横幅指定 -->
        <a href="order_list.php" class="custom-width">注文書一覧</a>  <!-- 横幅指定 -->
        <a href="../home.php" class="back-button">戻る</a>   
        <div class="modal" id="confirmationModal">
        <div class="modal-content"></div>


    <script src="script.js"></script>  <!-- 共通のJavaScriptファイル -->
    <script>
        window.addEventListener('DOMContentLoaded', function() {
            const params = new URLSearchParams(window.location.search);
            if (params.get('success') === '1' && localStorage.getItem('orderCreated') === '1') {
                alert('注文書が正しく作成されました');
                localStorage.removeItem('orderCreated');
            }
        });
    </script>
</body>
</html>
