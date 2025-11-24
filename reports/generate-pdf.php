<?php
// reports/generate-pdf.php
require_once '../db.php';                    // Go up one level to db.php
require_once 'tcpdf/tcpdf.php';              // tcpdf folder MUST be inside reports/

// Prevent any output before PDF
ob_clean();

// Create PDF
$pdf = new TCPDF();
$pdf->SetCreator('Agri Dashboard');
$pdf->SetAuthor('PSA Philippines');
$pdf->SetTitle('Farmgate Price Report');
$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 15, 'Philippine Farmgate Vegetable Prices Report', 0, 1, 'C');
$pdf->Ln(5);
$pdf->SetFont('helvetica', '', 10);

// Build table
$html = '<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr style="background-color:#2e7d32;color:white;">
            <th width="25%"><b>Region</b></th>
            <th width="30%"><b>Commodity</b></th>
            <th width="15%"><b>Year</b></th>
            <th width="15%"><b>Period</b></th>
            <th width="15%"><b>Price (₱/kg)</b></th>
        </tr>
    </thead>
    <tbody>';

$stmt = $pdo->query("SELECT geolocation, commodity, year, period, price 
                     FROM farmgate_prices 
                     ORDER BY year DESC, commodity 
                     LIMIT 1000");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $html .= "<tr>
        <td>{$row['geolocation']}</td>
        <td>{$row['commodity']}</td>
        <td>{$row['year']}</td>
        <td>{$row['period']}</td>
        <td align='right'>" . number_format($row['price'], 2) . "</td>
    </tr>";
}

$html .= '</tbody></table>
<p><small>Report generated on: ' . date('F d, Y h:i A') . '</small></p>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('Farmgate_Price_Report_' . date('Y-m-d') . '.pdf', 'D');
exit;
?>