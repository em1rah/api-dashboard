<?php
require '../vendor/autoload.php';
require '../includes/db.php';

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetTitle('Agricultural Price Trends Report');
$pdf->AddPage();

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Philippine Farmgate Prices Report', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 10, 'Generated on ' . date('F d, Y'), 0, 1, 'C');
$pdf->Ln(5);

// Same query as CSV
// (Copy the $where, $params, $sql from csv.php here)

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$html = '<table border="1" cellpadding="5">
<tr><th>Commodity</th><th>Region</th><th>Year</th><th>Period</th><th>Price (₱/kg)</th></tr>';
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $html .= '<tr>
        <td>' . htmlspecialchars($row['commodity']) . '</td>
        <td>' . htmlspecialchars($row['region']) . '</td>
        <td>' . $row['year'] . '</td>
        <td>' . htmlspecialchars($row['period']) . '</td>
        <td>' . number_format($row['price_php'], 2) . '</td>
    </tr>';
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('agri_report_' . date('Y-m-d') . '.pdf', 'D');
?>