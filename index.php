<?php
session_start();
$excelData = $_SESSION['excel_data'] ?? [];

for ($i = 1; $i <= 10; $i++):
    $row = $excelData[$i] ?? [];  // A列〜G列を仮定
?>
<tr>
    <td><input type="text" name="costomer_name[]" class="input-field" value="<?= htmlspecialchars($row['A'] ?? '') ?>"></td>
    <td><input type="text" name="" class="input-field" value="<?= htmlspecialchars($row['B'] ?? '') ?>"></td>
    <td><input type="text" name="" class="input-field" value="<?= htmlspecialchars($row['C'] ?? '') ?>"></td>
    <td><input type="text" name="" class="input-field" value="<?= htmlspecialchars($row['D'] ?? '') ?>"></td>
    <td><input type="text" name="" class="input-field" value="<?= htmlspecialchars($row['E'] ?? '') ?>"></td>
    <td><input type="text" name="" class="input-field" value="<?= htmlspecialchars($row['F'] ?? '') ?>"></td>
    <td><input type="date" name="" class="input-field" value="<?= htmlspecialchars($row['G'] ?? '') ?>"></td>
</tr>
<?php endfor; ?>
