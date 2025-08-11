<?php

session_start();
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$host = 'localhost';
$db   = 'mbs_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
];

if (isset($_FILES['excelFile']) && $_FILES['excelFile']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['excelFile']['tmp_name'];
    $fileName = $_FILES['excelFile']['name']; // ファイル名を取得

    try {
        $spreadsheet = IOFactory::load($fileTmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        $pdo = new PDO($dsn, $user, $pass, $options);

        $stmt = $pdo->prepare("
            INSERT INTO customers (customer_id, store_id, name, staff, address, phone_number, delivery_location, remarks, registration_date, deletion_flag)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
        ");
        $storeStmt = $pdo->prepare("SELECT store_id FROM stores WHERE store_name = ?");

        $parsedData = [];
        $first = true;

        foreach ($rows as $row) {
            if ($first) {
                $first = false;
                continue;
            }

            $customer_id = trim($row['A'] ?? '');
            $storeName = trim($row['B'] ?? '');
            if ($storeName === '') continue;

            $storeStmt->execute([$storeName]);
            $store = $storeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$store) continue;

            $store_id = $store['store_id'];
            $name     = trim($row['C'] ?? '');
            $staff    = trim($row['D'] ?? '');
            $address  = trim($row['E'] ?? '');
            $phone    = trim($row['F'] ?? '');
            $delivery = trim($row['G'] ?? '');
            $remarks  = trim($row['H'] ?? '');
            $regDate  = !empty($row['I']) ? date('Y-m-d', strtotime($row['I'])) : date('Y-m-d');


            if ($name === '' || $customer_id === '') continue;


            $checkStmt = $pdo->prepare("SELECT customer_id FROM customers WHERE customer_id = ?");
            $checkStmt->execute([$customer_id]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $stmt->execute([$customer_id, $store_id, $name, $staff, $address, $phone, $delivery, $remarks, $regDate]);
            }

            // ここでstore_idも含めて保存
            $parsedData[] = [
                'customer_id' => $customer_id,
                'store_id' => $store_id,
                'name' => $name,
                'staff' => $staff,
                'address' => $address,
                'phone_number' => $phone,
                'delivery_location' => $delivery,
                'remarks' => $remarks,
                'registration_date' => $regDate
            ];
        }
        // ...existing code...
        $_SESSION['uploaded_customers'] = $parsedData;
        $_SESSION['uploaded_excel_filename'] = $fileName; // ファイル名をセッションに保存
        date_default_timezone_set('Asia/Tokyo'); // タイムゾーンを日本に設定
        $_SESSION['last_updated_at'] = date('Y-m-d H:i:s'); // 現在時刻をセッションに保存
        header("Location: kokyaku.php");
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = '処理中にエラーが発生しました: ' . $e->getMessage();
        header("Location: kokyaku.php");
        exit;
    }
} else {
    $_SESSION['error'] = 'ファイルのアップロードに失敗しました。';
    header("Location: kokyaku.php");
    exit;
}
