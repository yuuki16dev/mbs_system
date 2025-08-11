<?php
$pdo = new PDO('mysql:host=localhost;dbname=mbs_db;charset=utf8mb4', 'root', '', [
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci"
]);

// POSTデータを受け取る
$names = $_POST['customer_name'];
$staffs = $_POST['staff'];
$addresses = $_POST['address'];
$phones = $_POST['phone_number'];
$deliveries = $_POST['delivery_location'];
$remarksList = $_POST['remarks'];
$dates = $_POST['registration_date'];

// トランザクション開始
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare("INSERT INTO customers (name, staff, address, phone_number, delivery_location, remarks, registration_date, deletion_flag) 
                       VALUES (:name, :staff, :address, :phone, :delivery, :remarks, :reg_date, 0)");

    for ($i = 0; $i < count($names); $i++) {
        if (empty($names[$i])) continue; // 空行は無視
        $stmt->execute([
            ':name' => $names[$i],
            ':staff' => $staffs[$i],
            ':address' => $addresses[$i],
            ':phone' => $phones[$i],
            ':delivery' => $deliveries[$i],
            ':remarks' => $remarksList[$i],
            ':reg_date' => $dates[$i],
        ]);
    }

    $pdo->commit();
    echo "保存が完了しました。<br><a href='kokyaku.php'>戻る</a>";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "保存に失敗しました: " . htmlspecialchars($e->getMessage());
}
