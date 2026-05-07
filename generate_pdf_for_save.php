<?php
// This file returns PDF content as string for automatic saving
$return_pdf = true; // Flag to indicate we want PDF content returned

session_start();
include('includes/db_connect.php');

if (!isset($_SESSION['student_id'])) {
    die("Unauthorized");
}

$student_id = $_SESSION['student_id'];

// --- FUNCTIONS ---
function getAcademicYear($conn) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'academic_year'");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? $row['setting_value'] : '2025-2026';
}

// --- FETCH CURRENT SEMESTER & ACADEMIC YEAR ---
$stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'current_semester'");
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$current_semester = $row ? intval($row['setting_value']) : 1; // default to 1 if not found

// --- GET SUBMISSION DATE ---
$stmt = $conn->prepare("
    SELECT created_at 
    FROM submissions 
    WHERE student_id = ? AND type = 'enrollment' 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([$student_id]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

// Use submission date if available, otherwise use current date
if ($submission && $submission['created_at']) {
    $submission_date = date('m/d/Y', strtotime($submission['created_at']));
} else {
    $submission_date = date('m/d/Y');
}

// Convert semester number to string
$semester_str = $current_semester . '<sup>' . ($current_semester == 1 ? 'ST' : ($current_semester == 2 ? 'ND' : ($current_semester == 3 ? 'RD' : 'TH'))) . '</sup>';

// --- FETCH STUDENT DATA ---
$stmt = $conn->prepare("
    SELECT 
        s.id,
        s.student_id,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.email,
        s.mobile_number,
        s.secondary_mobile_number,
        s.year_level,
        s.major
    FROM students s
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student data not found.");
}

// --- FETCH ENROLLED SUBJECTS ---
$stmt = $conn->prepare("
    SELECT 
        c.course_code,
        c.course_title,
        c.units AS credit_units,
        sec.section_name,
        GROUP_CONCAT(
            CONCAT(scs.day_of_week, ' (', TIME_FORMAT(scs.start_time, '%h:%i %p'), ' - ', TIME_FORMAT(scs.end_time, '%h:%i %p'), ')')
            ORDER BY FIELD(scs.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
            scs.start_time
            SEPARATOR ' & '
        ) AS schedule
    FROM student_enrollments se
    JOIN section_courses sc ON se.section_course_id = sc.section_course_id
    JOIN courses c ON sc.course_id = c.course_id
    JOIN sections sec ON sc.section_id = sec.section_id
    JOIN section_course_schedules scs ON sc.section_course_id = scs.section_course_id
    WHERE se.student_id = ?
    GROUP BY c.course_code, c.course_title, c.units, sec.section_name
    ORDER BY c.course_code
");
$stmt->execute([$student_id]);
$enrolled_subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_units = 0;
foreach ($enrolled_subjects as $sub) {
    $total_units += floatval($sub['credit_units']);
}

// --- INCLUDE TCPDF ---
require_once __DIR__ . '/vendor/autoload.php';
$pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Document Info
$pdf->SetCreator('DHVSU E-RIS');
$pdf->SetAuthor('DHVSU');
$pdf->SetTitle('Pre-Registration Form');
$pdf->SetSubject('Enrollment Form');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 12, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 10);

// --- HEADER ---
$logoPath = __DIR__ . '/assets/img/vid.jpg';
$logoHTML = file_exists($logoPath)
    ? '<img src="' . $logoPath . '" width="65" height="65">'
    : '';

$headerHTML = '
<table width="100%" border="0" cellspacing="0" cellpadding="2" >
  <tr>
    <td width="10%"></td>
    <td width="20%" valign="middle" style="padding-right: 2px; text-align:right;" >
        ' . $logoHTML . '
    </td>
    <td width="50%" align="center" valign="middle">
        <span style="font-size:8pt;">Republic of the Philippines</span><br>
        <span style="font-size:10pt; font-weight:bold;">DON HONORIO VENTURA STATE UNIVERSITY</span><br>
        <span style="font-size:8pt;">Bacolor, Pampanga</span><br><br>
        <span style="font-size:10pt; font-weight:bold;">PRE-REGISTRATION FORM</span><br>
        <span style="font-size:10pt; font-weight:bold;"><u>'. $semester_str .'</u> SEMESTER, ACADEMIC YEAR <u>2025-2026</u></span>
    </td>
    <td width="13%"></td>
  </tr>
</table>
';
$pdf->writeHTML($headerHTML, true, false, true, false, '');

// --- DEMOGRAPHIC INFORMATION ---
$demographicHTML = '
<style>
td.label {font-weight:bold; text-align:center; border:.5px solid #000; padding:5px; font-size:10px;}
td.value {border:.5px solid #000; text-align:center; padding:8px; font-size:10px; line-height: 2;}
.small-label {font-size:8.5px; font-style:italic; color:#333;}
.section-title {font-weight:bold; font-size:10.5px; padding:10px 0px 4px 0px; border-left:.5px solid #000; border-right:.5px solid #000; border-top:none; border-bottom:none;}
</style>

<div style="font-weight:bold; font-size:11px; margin-bottom:8px;">I. DEMOGRAPHIC INFORMATION</div>

<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">
  <!-- Student Info Row -->
  <tr>
    <td class="label" width="33%">Student Number</td>
    <td class="label" width="50%">Program of Study and Major</td>
    <td class="label" width="17%">Year Level</td>
  </tr>
  <tr>
    <td class="value"><u>' . htmlspecialchars($student['student_id']) . '</u></td>
    <td class="value"><u>' . htmlspecialchars($student['major']) . '</u></td>
    <td class="value"><u>' . htmlspecialchars($student['year_level']) . '</u></td>
  </tr>
  
  <!-- Name Section -->
  <tr>
    <td colspan="3" class="section-title" style="border-left:.5px solid #000; border-right:.5px solid #000;"> Name of Student</td>
  </tr>
  <tr>
    <td width="33%" height ="20"style="border:.5px solid #000; text-align:center; padding:8px; ">
      <u><br>' . htmlspecialchars($student['last_name']) . '</u><br><span class="small-label">Family Name</span>
    </td>
    <td width="34%" height ="20"style="border:.5px solid #000; text-align:center; padding:8px;">
      <u><br>' . htmlspecialchars($student['first_name']) . '</u><br><span class="small-label">Given Name</span>
    </td>
    <td width="33%" height ="20"style="border:.5px solid #000; text-align:center; padding:8px;">
      <u><br>' . (empty($student['middle_name']) ? 'N/A' : htmlspecialchars($student['middle_name'])) . '</u><br><span class="small-label">Middle Name</span>
    </td>
  </tr>
  
  <!-- Contact Details Section -->
  <tr>
    <td colspan="3" class="section-title" style="border-left:.5px solid #000; border-right:.5px solid #000;"> Contact Details</td>
  </tr>
  <tr>
    <td width="33%" height ="20" style="border:.5px solid #000; text-align:center; padding:8px;">
      <u><br>' . htmlspecialchars($student['mobile_number']) . '</u><br><span class="small-label">Primary Mobile Number</span>
    </td>
    <td width="34%" height ="20" style="border:.5px solid #000; text-align:center; padding:8px;">
      <u><br>' . (empty($student['secondary_mobile_number']) ? 'N/A' : htmlspecialchars($student['secondary_mobile_number'])) . '</u><br><span class="small-label">Secondary Mobile Number</span>
    </td>
    <td width="33%" height ="20" style="border:.5px solid #000; text-align:center; padding:8px;">
      <u><br>' . htmlspecialchars($student['email']) . '</u><br><span class="small-label">Active Email Address</span>
    </td>
  </tr>
</table>
';
$pdf->writeHTML($demographicHTML, true, false, true, false, '');

// --- ENROLLMENT DETAILS ---
$pdf->Ln(0); 
$enrollHTML = '
<br>
<div style="font-weight:bold; font-size:11px; margin-bottom:2px;">II. ENROLLMENT DETAILS</div>
<table cellpadding="2" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
  <tr>
    <th style="width:15%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Course Code</b></th>
    <th style="width:35%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Course Title</b></th>
    <th style="width:10%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Units</b></th>
    <th style="width:15%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Section</b></th>
    <th style="width:25%; border:.5px solid #000; background-color:#f0f0f0; text-align:center; padding:5px;"><b>Schedule/ Room</b></th>
  </tr>';

if (count($enrolled_subjects) > 0) {
    foreach ($enrolled_subjects as $sub) {
        $sched = str_replace(' & ', '<br>', htmlspecialchars($sub['schedule']));
        $enrollHTML .= '
        <tr>
            <td style="border:.5px solid #000; padding:5px; text-align:center;">' . htmlspecialchars($sub['course_code']) . '</td>
            <td style="border:.5px solid #000; padding:5px;">   ' . htmlspecialchars($sub['course_title']) . '</td>
            <td style="border:.5px solid #000; padding:5px; text-align:center;">' . htmlspecialchars($sub['credit_units']) . '</td>
            <td style="border:.5px solid #000; padding:5px; text-align:center;">' . htmlspecialchars($sub['section_name']) . '</td>
            <td style="border:.5px solid #000; padding:5px;">' . $sched . '</td>
        </tr>';
    }
} else {
    $enrollHTML .= '<tr><td colspan="5" style="border:.5px solid #000; padding:8px; text-align:center;">No enrolled subjects found.</td></tr>';
}

$enrollHTML .= '
  <tr>
    <td colspan="2" style="border:.5px solid #000; padding:6px; text-align:right;"><b>Total Units  </b></td>
    <td style="border:.5px solid #000; padding:6px; text-align:center;"><b>' . $total_units . '</b></td>
    <td colspan="2" style="border:.5px solid #000;"></td>
  </tr>
</table>
';
$pdf->writeHTML($enrollHTML, true, false, true, false, '');

// Check available space before adding declaration section
$current_y = $pdf->GetY();
$page_height = 297; // A4 height in mm
$bottom_margin = 15;

// If less than 120mm space left, add new page (adjust this value as needed)
if (($page_height - $current_y) < 80) {
    $pdf->AddPage();
}

// --- DECLARATION SECTION ---
$declHTML = '
<div style="font-size:9px; text-align:justify; margin-top:2px;">
  <table cellpadding="0" cellspacing="0" width="100%">
    <tr>
      <td width="95%" style="font-size:9px;"><strong>By signing this document:</strong></td>
      <td width="5%" style="font-size:9px;"></td>
    </tr>
    <tr>
      <td width="3%" style="font-size:9px; vertical-align:top;">1.</td>
      <td width="97%" style="font-size:9px;">I fully understand and agree that the information above are true and correct; and that any false statement on this document (as well as on the supporting documents) constitutes a perjury and could result in denial of the registration and disciplinary proceedings with sanction as per the Don Honorio Ventura State University (the "University") policies;</td>
    </tr>
    <tr>
      <td width="3%" style="font-size:9px; vertical-align:top;">2.</td>
      <td width="97%" style="font-size:9px;">I am fully aware and understand that registration/enrollment in any course for the abovementioned academic period is allowed only upon passing the pre-requisite(s) of the said course (if any); a course registered/enrolled in violation of this rule will not be given any credit regardless of the grade obtained;</td>
    </tr>
    <tr>
      <td width="3%" style="font-size:9px; vertical-align:top;">3.</td>
      <td width="97%" style="font-size:9px;">I authorize the University to collect and process any information declared herein with utmost confidentiality; and</td>
    </tr>
    <tr>
      <td width="3%" style="font-size:9px; vertical-align:top;">4.</td>
      <td width="97%" style="font-size:9px;">I allow the University to disclose the collected information to its affiliates and lawful third parties for legitimate purposes only.</td>
    </tr>
  </table>
</div>

<!-- Student signature table -->
<table width="100%" border="1" cellspacing="0" cellpadding="4" style="font-size:9px; margin-top:10px; border-collapse:collapse;">
  <tr>
    <td width="70%" align="center"><b>Signature over printed name of Student</b></td>
    <td width="30%" align="center"><b>Date signed by the Student</b></td>
  </tr>
  <tr>
    <td align="center" style="padding: 15mm 0 8px 0; height: 5mm; vertical-align: bottom;">
        <br><br><br> <!-- Creates space for signature -->
        ' . htmlspecialchars($student['last_name'] . ", " . $student['first_name'] . " " . (empty($student['middle_name']) ? '' : substr($student['middle_name'], 0, 1) . ".")) . '
    <br></td>
    <td align="center" style="padding: 8px 0;"><br><br><br>' . $submission_date . '</td>
</tr>
</table>
';

$pdf->writeHTML($declHTML, true, false, true, false, '');

// Get the Y position after writing the student signature section
$after_signature_section = $pdf->GetY();

// Calculate the position for the signature - it should be in the student signature cell
// The signature cell starts approximately 25mm above the current Y position
$signature_cell_top = $after_signature_section - 30; // Adjust this based on actual cell height
$signature_y_position = $signature_cell_top + 1; // Position 5mm from top of signature cell

// Check if student has a signature and add it to the PDF
$stmt = $conn->prepare("SELECT signature_form FROM files WHERE student_id = ? AND signature_form IS NOT NULL");
$stmt->execute([$student_id]);
$signature = $stmt->fetch(PDO::FETCH_ASSOC);

if ($signature && file_exists($signature['signature_form'])) {
    // Position the signature exactly on top of the printed name in the signature cell
    $signature_x = 50; // Left margin - adjust as needed
    $signature_width = 80; // Width of signature image
    $signature_height = 20; // Height of signature image
    
    $pdf->Image($signature['signature_form'], $signature_x, $signature_y_position, $signature_width, $signature_height);
}

// Continue with the rest of the PDF
$personnelHTML = '
<br>

<!-- Personnel section header -->
<div style="text-align:center; font-size:9px; padding:15px 0;">
  ------------------------------------------------------------<b>FOR DHVSU PERSONNEL ONLY</b>------------------------------------------------------------
</div>
<br>

<!-- Recommending Approval -->
<table width="100%" border="1" cellspacing="0" cellpadding="4" style="font-size:9px; border-collapse:collapse;">
  <tr>
    <td align="center" style="font-weight:bold;">Recommending Approval</td>
  </tr>
  <tr>
    <td>
      By signing this document, I, hereby, declare that I have fully assessed the status of the student and advised him/her with the appropriate courses to enroll in the academic period stated above.
      <br><br><br>
      <table width="100%" border="0" cellspacing="0" cellpadding="0" style="font-size:9px;">
        <tr>
          <!-- Signature (70%) -->
          <td width="65%" style="vertical-align:top; text-align:center;">
            ___________________________________________________________<br>
            <span style="font-size:8px;"><i>Signature over printed name of Program Chairperson</i></span>
          </td>
          <!-- Date (30%) -->
          <td width="35%" style="vertical-align:top; text-align:center;">
            _______________________________<br>
            <span style="font-size:8px;"><i>Date recommended for approval</i></span>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- Footer -->
<div style="text-align:left; margin-top:5px; font-size:8px;">DHVSU-QSP-REG-001-FO001-R00</div>
';

$pdf->writeHTML($personnelHTML, true, false, true, false, '');

// Check available space before adding approved by section
$current_y = $pdf->GetY();
// If less than 40mm space left, add new page for approved by section
if (($page_height - $current_y) < 40) {
    $pdf->AddPage();
} else {
    // Add some space if there's enough room on the same page
    $pdf->Ln(10);
}

$approvedByHTML = '
<!-- Approved by -->
<table width="100%" border="1" cellspacing="0" cellpadding="4" style="font-size:9px; border-collapse:collapse;">
  <tr>
    <td width="65%" align="center"><b>Approved by</b></td>
    <td width="35%" align="center"><b>Date approved</b></td>
  </tr>
  <tr>
    <td align="center" height="60">
      <br><br><br>______________________________________________________________<br>
      <span style="font-size:8px;"><i>Signature over printed name of College Dean</i></span>
    </td>
    <td align="center" height="60">
      <br><br><br>______________________________
    </td>
  </tr>
</table>

<!-- Footer for second page -->
<div style="text-align:left; margin-top:10px; font-size:8px;">DHVSU-QSP-REG-001-FO001-R00</div>
';

$pdf->writeHTML($subjectHTML, true, false, true, false, '');
$pdf->writeHTML($approvedByHTML, true, false, true, false, '');

ob_end_clean();

// --- SAVE PDF silently without showing or echoing anything ---
$outputDir = __DIR__ . '/uploads/advising_forms/';
if (!is_dir($outputDir)) mkdir($outputDir, 0777, true);

$pdfFileName = 'advising_form_' . $student['student_id'] . '.pdf';
$pdfFilePath = $outputDir . $pdfFileName;

// Save PDF file
$pdf->Output($pdfFilePath, 'F');

// Save relative path to DB
$relativePath = 'uploads/advising_forms/' . $pdfFileName;
$stmt = $conn->prepare("UPDATE submissions SET advising_form = ? WHERE student_id = ?");
$stmt->execute([$relativePath, $student['id']]);

$conn->commit(); // ensure transaction finishes cleanly
$conn = null;    // release lock

return;
