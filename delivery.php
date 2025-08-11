<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>納品書</title>
    <!-- 共通のCSSファイル -->
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <!-- ホーム画面タイトル -->
    <div class="home-title">納品書</div>

    <!-- ハンバーガーメニュー -->
    <div class="hamburger" id="hamburger">
        <div></div>
        <div></div>
        <div></div>
    </div>

    <!-- ナビゲーションメニュー -->
    <div class="nav-menu" id="navMenu">
        <a href="home.php">ホーム画面</a>
        <a href="order.php">注文書</a>
        <a href="toukei.php">統計情報</a>
        <a href="toukei.php">顧客情報管理</a>
    </div>

    <!-- ボタンエリア -->
    <div class="button-container">
        <a href="delivery_create.php" class="custom-width">納品書作成</a>  <!-- 横幅指定 -->
        <a href="delivery_list.php" class="custom-width">納品書一覧</a>  <!-- 横幅指定 -->
        <a href="home.php" class="back-button">戻る</a>   
        <div class="modal" id="confirmationModal">
        <div class="modal-content"></div>

    </div>

    <script src="script.js"></script>  <!-- 共通のJavaScriptファイル -->
</body>
</html>
