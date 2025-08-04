<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ホーム画面</title>
    <link rel="stylesheet" href="styles.css">  <!-- 共通のCSSファイル -->
</head>
<body>

    <div class="home-title">ホーム画面</div>

    <!-- ハンバーガーメニュー -->
    <div class="hamburger" id="hamburger">
        <div></div>
        <div></div>
        <div></div>
    </div>

    <!-- ナビゲーションメニュー -->
    <div class="nav-menu" id="navMenu">
        <a href="tyuumon.php">注文書</a>
        <a href="nouhin.php">納品書</a>
        <a href="toukei.php">統計情報</a>
        <a href="kokyaku.php">顧客情報管理</a>
    </div>

    <div class="button-container">
        <a href="tyuumon.php" class="custom-width">注文書</a>
        <a href="nouhin.php" class="custom-width">納品書</a>
        <a href="toukei.php" class="back-button">統計情報</a>
        <a href="kokyaku.php" class="back-button">顧客情報管理</a>
    </div>

    <script src="script.js"></script>  <!-- 共通のJavaScriptファイル -->
</body>
</html>
テスト