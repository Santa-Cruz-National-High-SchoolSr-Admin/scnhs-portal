<?php
// Check if we need to generate a barcode image or a PDF

require_once('../admin/TCPDF-6.8.2/tcpdf/include/barcodes/qrcode.php');
require_once('../admin/TCPDF-6.8.2/tcpdf/tcpdf.php');

$lrn = isset($_GET['lrn']) ? $_GET['lrn'] : '';
$name = isset($_GET['name']) ? $_GET['name'] : '';
$download = isset($_GET['download']) ? $_GET['download'] : 0;

if (empty($lrn)) {
    header("HTTP/1.0 404 Not Found");
    die("LRN not provided");
}

if ($download) {
    // Generate PDF document for single barcode
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Attendance System');
    $pdf->SetAuthor('School Admin');
    $pdf->SetTitle('Student Barcode');
    $pdf->SetMargins(10, 10, 10);
    $pdf->AddPage();

    // Add school header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'STUDENT ATTENDANCE BARCODE', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Santa Cruz National High School', 0, 1, 'C');
    $pdf->Ln(5);

    // Student Information
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(50, 7, 'Student Name:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 7, htmlspecialchars($name), 0, 1);

    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(50, 7, 'LRN:', 0, 0);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 7, htmlspecialchars($lrn), 0, 1);

    $pdf->Ln(10);

    // Generate barcode
    $pdf->SetFont('helvetica', '', 10);
    $barcode_data = $lrn; // Use LRN as barcode data
    
    // Use Code128 barcode
    $pdf->write1DBarcode($barcode_data, 'C128', null, null, null, 20, 0.4, array(), 'N');

    $pdf->Ln(5);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 7, 'Scan this barcode for attendance', 0, 1, 'C');

    // Output PDF
    $filename = str_replace(' ', '_', $name) . '_' . $lrn . '.pdf';
    $pdf->Output($filename, 'D'); // D = download, I = inline
    exit();
} else {
    // Generate barcode image for display
    $pdf = new TCPDF('P', 'mm', 'A6', true, 'UTF-8', false);
    $pdf->SetCreator('Attendance System');
    $pdf->SetAuthor('School Admin');
    $pdf->SetMargins(5, 5, 5);
    $pdf->AddPage();

    // Barcode data
    $barcode_data = $lrn;
    
    // Use Code128 barcode for better scanning
    $pdf->write1DBarcode($barcode_data, 'C128', null, null, null, 15, 0.4, array(), 'N');

    // Output as image
    $pdf->Output('php://output', 'I');
    exit();
}
?>
