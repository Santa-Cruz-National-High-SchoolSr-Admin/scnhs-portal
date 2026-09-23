<?php
require_once('TCPDF-6.8.2/TCPDF-6.8.2/tcpdf.php');

// Create a new PDF document
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetTitle('Certificate of Completion');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Set background image (optional)
$backgroundImg = '../certificate_bg.jpg'; // Change this to your certificate template
$pdf->Image($backgroundImg, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);

// Set certificate title
$pdf->SetFont('Times', 'B', 30);
$pdf->SetXY(50, 50);
$pdf->Cell(200, 10, 'Certificate of Completion', 0, 1, 'C');

// Student Name (Dynamic)
$pdf->SetFont('Times', 'B', 24);
$pdf->SetXY(50, 80);
$studentName = isset($_GET['name']) ? $_GET['name'] : 'John Doe'; // Fetch from URL parameter
$pdf->Cell(200, 10, $studentName, 0, 1, 'C');

// Course Name
$pdf->SetFont('Times', '', 18);
$pdf->SetXY(50, 100);
$courseName = isset($_GET['course']) ? $_GET['course'] : 'Senior High School Program';
$pdf->Cell(200, 10, 'has successfully completed the ' . $courseName, 0, 1, 'C');

// Date of Completion
$pdf->SetXY(50, 120);
$completionDate = date('F d, Y');
$pdf->Cell(200, 10, 'on ' . $completionDate, 0, 1, 'C');

// Signature
$pdf->Image('../signature.png', 120, 150, 50, 15, '', '', '', false, 300, '', false, false, 0);
$pdf->SetXY(50, 170);
$pdf->Cell(200, 10, 'School Principal', 0, 1, 'C');

// Output PDF
$pdf->Output('certificate.pdf', 'I');
?>
