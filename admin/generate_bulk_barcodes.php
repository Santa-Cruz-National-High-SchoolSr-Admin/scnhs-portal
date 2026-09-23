<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header("Location: ../login.php");
    exit();
}

require_once('TCPDF-6.8.2/tcpdf/tcpdf.php');

$conn = new mysqli("localhost", "root", "", "enrollment_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['students'])) {
    $students = json_decode($_POST['students'], true);

    if (!is_array($students) || count($students) === 0) {
        header("Location: barcode_generator.php?error=no_students");
        exit();
    }

    // Create PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_PAGE_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('Attendance System');
    $pdf->SetAuthor('School Admin');
    $pdf->SetTitle('Student Barcodes');
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 10);

    // Add title page
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->Cell(0, 15, 'STUDENT ATTENDANCE BARCODES', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, 'Santa Cruz National High School', 0, 1, 'C');
    $pdf->Cell(0, 10, 'Generated on: ' . date('F j, Y, g:i a'), 0, 1, 'C');
    $pdf->Cell(0, 10, 'Total Students: ' . count($students), 0, 1, 'C');
    $pdf->Ln(5);

    // Add student barcodes
    $counter = 0;
    foreach ($students as $student) {
        $lrn = isset($student['lrn']) ? $student['lrn'] : '';
        $name = isset($student['name']) ? $student['name'] : '';

        if (empty($lrn)) {
            continue;
        }

        // Check if we need a new page (3 barcodes per page)
        if ($counter % 3 == 0 && $counter != 0) {
            $pdf->AddPage();
        }

        if ($counter == 0) {
            $pdf->AddPage();
        }

        // Add student information
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 8, htmlspecialchars($name), 0, 1);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, 'LRN: ' . htmlspecialchars($lrn), 0, 1);

        // Add barcode
        $pdf->SetFont('helvetica', '', 9);
        $y_before = $pdf->GetY();
        $pdf->write1DBarcode($lrn, 'C128', '', '', '', 18, 0.4, array(), 'N');
        $pdf->SetY($y_before + 25);

        $pdf->Ln(3);
        $counter++;
    }

    // Output PDF
    $filename = 'Student_Barcodes_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    exit();
} else {
    header("Location: barcode_generator.php");
    exit();
}
?>
