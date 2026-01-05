<?php
// admin/export_approved.php
session_start();
if (empty($_SESSION['admin_logged_in'])) { exit; }

require __DIR__ . '/../includes/db.php';

// Header agar browser membacanya sebagai file Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Data_Peserta_Approved_" . date('Y-m-d') . ".xls");

// Query yang SAMA PERSIS dengan dashboard tab approved
$stmt = $pdo->query("
    SELECT u.name, u.email, u.phone, u.handicap, u.institution, u.size,
           o.order_code, o.created_at, o.status
    FROM orders o
    JOIN users u ON u.id = o.user_id
    WHERE (o.status = 'approved' OR o.status = '') 
      AND o.id NOT IN (SELECT order_id FROM payment_uploads)
    ORDER BY o.created_at DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Output Table HTML (Excel akan membacanya sebagai tabel)
echo "<table border='1'>";
echo "<thead>
        <tr style='background-color:#f2f2f2;'>
            <th>No</th>
            <th>Order Code</th>
            <th>Nama Peserta</th>
            <th>Email</th>
            <th>No HP (WA)</th>
            <th>Instansi</th>
            <th>Handicap</th>
            <th>Size Baju</th>
            <th>Tanggal Daftar</th>
        </tr>
      </thead>";
echo "<tbody>";

$no = 1;
foreach($rows as $r) {
    // Format No HP biar rapi di Excel (biar terbaca string, bukan angka ilmiah)
    $hp = "'" . $r['phone']; 
    
    echo "<tr>";
    echo "<td>" . $no++ . "</td>";
    echo "<td>" . htmlspecialchars($r['order_code']) . "</td>";
    echo "<td>" . htmlspecialchars($r['name']) . "</td>";
    echo "<td>" . htmlspecialchars($r['email']) . "</td>";
    echo "<td>" . htmlspecialchars($hp) . "</td>";
    echo "<td>" . htmlspecialchars($r['institution']) . "</td>";
    echo "<td>" . htmlspecialchars($r['handicap']) . "</td>";
    echo "<td>" . htmlspecialchars($r['size']) . "</td>";
    echo "<td>" . htmlspecialchars($r['created_at']) . "</td>";
    echo "</tr>";
}
echo "</tbody>";
echo "</table>";
?>