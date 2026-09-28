<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

require_once '../includes/database.php';
require '../vendor/autoload.php';

$pdo = get_db_connection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid application ID.");
}

$application_id = (int)$_GET['id'];
$application = null;

try {
    $stmt = $pdo->prepare("
        SELECT a.*, j.title as job_title, j.company_name, j.location, j.type as job_type 
        FROM applications a 
        LEFT JOIN jobs j ON a.job_id = j.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch();
} catch (PDOException $e) {
    die("Error loading application details.");
}

if (!$application) {
    die("Application not found.");
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Xpress Point Management');
$pdf->SetAuthor('Xpress Point Management');
$pdf->SetTitle('Candidate CV Submission');
$pdf->SetSubject('Candidate Details');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins
$pdf->SetMargins(15, 15, 15);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 10);

$html = '';

// Header with Logo and Date
$logoPath = '../assets/images/XPMLogo.png';
$logoHtml = '';
if (file_exists($logoPath)) {
    // We want a reasonable width, e.g., 150px
    $logoHtml = '<img src="' . $logoPath . '" width="150" />';
}

$dateApplied = date('d/m/Y H:i', strtotime($application['created_at']));

$html .= '
<table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td width="50%">' . $logoHtml . '</td>
        <td width="50%" align="right" style="vertical-align: middle; font-weight: bold;"><br><br><br>Date Applied &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $dateApplied . '</td>
    </tr>
</table>
<br><br>
<div style="text-align: center;">
    <h2>Candidate CV Submission</h2>
    <h3>(Private Confidential)</h3>
</div>
<br><br>
';

// Helper function to build a row only if the value is not empty
function buildRow($label, $value, $colspan = 1) {
    if (empty(trim($value))) return '';
    $val = htmlspecialchars($value);
    $html = '<tr>';
    $html .= '<td style="font-weight: bold; width: 25%; border: 1px solid #000; padding: 5px;">' . htmlspecialchars($label) . '</td>';
    $html .= '<td ' . ($colspan > 1 ? 'colspan="3" style="width: 75%; border: 1px solid #000; padding: 5px;"' : 'style="width: 25%; border: 1px solid #000; padding: 5px;"') . '>' . nl2br($val) . '</td>';
    $html .= '</tr>';
    return $html;
}

// Helper function to build a 2-item row only if at least one value is not empty
function buildDoubleRow($label1, $value1, $label2, $value2) {
    $v1Empty = empty(trim((string)$value1));
    $v2Empty = empty(trim((string)$value2));
    
    if ($v1Empty && $v2Empty) return '';
    
    $html = '<tr>';
    
    // First pair
    if (!$v1Empty) {
        $html .= '<td style="font-weight: bold; width: 25%; border: 1px solid #000; padding: 5px;">' . htmlspecialchars($label1) . '</td>';
        $html .= '<td style="width: 25%; border: 1px solid #000; padding: 5px;">' . nl2br(htmlspecialchars($value1)) . '</td>';
    } else {
        $html .= '<td colspan="2" style="width: 50%; border: 1px solid #000; padding: 5px;"></td>';
    }
    
    // Second pair
    if (!$v2Empty) {
        $html .= '<td style="font-weight: bold; width: 25%; border: 1px solid #000; padding: 5px;">' . htmlspecialchars($label2) . '</td>';
        $html .= '<td style="width: 25%; border: 1px solid #000; padding: 5px;">' . nl2br(htmlspecialchars($value2)) . '</td>';
    } else {
        $html .= '<td colspan="2" style="width: 50%; border: 1px solid #000; padding: 5px;"></td>';
    }
    
    $html .= '</tr>';
    return $html;
}

// Personal details
$html .= '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; border: 1px solid #000;">';
$html .= buildRow('Candidate Name', $application['name'] ?? '', 3);
$html .= buildRow('IC Number', $application['ic_number'] ?? '', 3);
$html .= buildRow('Contact Number', $application['phone'] ?? '', 3);
$html .= buildRow('Email', $application['email'] ?? '', 3);

// For missing DB fields, we only map them if they exist in $application array.
$address = $application['address'] ?? '';
$html .= buildRow('Address', $address, 3);

$html .= buildDoubleRow('State', $application['state'] ?? '', 'City', $application['city'] ?? '');
$html .= buildDoubleRow('Age', $application['age'] ?? '', 'Height (cm)', $application['height'] ?? '');
$html .= buildDoubleRow('Weight (kg)', $application['weight'] ?? '', 'Gender', $application['gender'] ?? '');
$html .= buildDoubleRow('Race', $application['race'] ?? '', 'Marital Status', $application['marital_status'] ?? '');
$html .= buildDoubleRow('Nationality', $application['nationality'] ?? '', 'Religion', $application['religion'] ?? '');
$html .= '</table><br><br>';


// Questionnaire (if these fields exist in future)
$questionnaireFields = [
    'Smoker' => 'smoker',
    'Convicted of crime' => 'convicted_of_crime',
    'Currently Pregnant' => 'currently_pregnant',
    'Willing to relocate' => 'willing_to_relocate',
    'Own Transportation' => 'own_transportation',
    'Having relative in company' => 'having_relative_in_company',
    'Able To Work Shift' => 'able_to_work_shift',
    'Current Health / Medication' => 'current_health_medication'
];

$qHtml = '';
foreach ($questionnaireFields as $label => $key) {
    if (!empty($application[$key])) {
        $qHtml .= buildRow($label, $application[$key], 3);
    }
}
if (!empty($qHtml)) {
    $html .= '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; border: 1px solid #000;">';
    $html .= $qHtml;
    $html .= '</table><br><br>';
}


// Current Employment
$empTitle = !empty($application['employment1_position']) ? $application['employment1_position'] : '';
$empDesc = !empty($application['employment1_description']) ? $application['employment1_description'] : '';
// Expected salary / notice period
$salary = !empty($application['expected_salary']) ? $application['expected_salary'] : '';
$avail = !empty($application['availability_date']) ? $application['availability_date'] : '';

if ($empTitle || $empDesc || $salary || $avail) {
    $html .= '<h3><u>Current Employment</u></h3>';
    $html .= '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; border: 1px solid #000;">';
    $html .= buildRow('Current Position', $empTitle, 3);
    $html .= buildRow('Job Description', $empDesc, 3);
    $html .= buildRow('Current/Expected Salary', $salary, 3);
    $html .= buildRow('Notice Period/Availability', $avail, 3); 
    $html .= '</table><br><br>';
}


// Working Experience (Past)
$expHtml = '';
$hasExp = false;
for ($i = 1; $i <= 3; $i++) {
    $company = $application["employment{$i}_company"] ?? '';
    $duration = $application["employment{$i}_duration"] ?? '';
    $position = $application["employment{$i}_position"] ?? '';
    $desc = $application["employment{$i}_description"] ?? '';
    
    if ($company || $duration || $position || $desc) {
        $hasExp = true;
        if ($i > 1) {
            $expHtml .= '<tr><td colspan="4" style="border: 1px solid #000; background-color: #f0f0f0;"></td></tr>';
        }
        $expHtml .= buildRow('Company Name', $company, 3);
        $expHtml .= buildRow('Duration', $duration, 3);
        $expHtml .= buildRow('Position Title (Level)', $position, 3);
        $expHtml .= buildRow('Job Description', $desc, 3);
    }
}
if ($hasExp) {
    $html .= '<h3><u>Working Experience</u></h3>';
    $html .= '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; border: 1px solid #000;">';
    $html .= $expHtml;
    $html .= '</table><br><br>';
}


// Education Background
$eduLevel = $application['education_level'] ?? '';
$eduDetails = $application['education_details'] ?? '';
$certs = $application['certifications'] ?? '';

if ($eduLevel || $eduDetails || $certs) {
    $html .= '<h3><u>Education Background</u></h3>';
    $html .= '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; border: 1px solid #000;">';
    $html .= buildRow('Education Level', ucfirst(str_replace('_', ' ', $eduLevel)), 3);
    $html .= buildRow('Education Details', $eduDetails, 3);
    $html .= buildRow('Certifications', $certs, 3);
    $html .= '</table><br><br>';
}


// Job Applied Information
$jobTitle = $application['job_title'] ?? '';
$availDate = $application['availability_date'] ?? '';
$expSalary = $application['expected_salary'] ?? '';
$jobType = $application['job_type'] ?? '';

if ($jobTitle || $availDate || $expSalary || $jobType) {
    $html .= '<h3><u>Job Applied Information</u></h3>';
    $html .= '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; border: 1px solid #000;">';
    $html .= buildRow('Position Applied', $jobTitle, 3);
    $html .= buildRow('Available Start Date', $availDate, 3);
    $html .= buildRow('Expected Salary', $expSalary, 3);
    $html .= buildRow('Employment Type', $jobType, 3);
    $html .= '</table><br><br>';
}


// Languages / Skills
$langs = $application['languages'] ?? '';
$techSkills = $application['technical_skills'] ?? '';
$softSkills = $application['soft_skills'] ?? '';

if ($langs || $techSkills || $softSkills) {
    $html .= '<h3><u>Skills & Languages</u></h3>';
    $html .= '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; border: 1px solid #000;">';
    $html .= buildRow('Languages', $langs, 3);
    $html .= buildRow('Technical Skills', $techSkills, 3);
    $html .= buildRow('Soft Skills', $softSkills, 3);
    $html .= '</table>';
}


// Output the HTML content
$pdf->writeHTML($html, true, false, true, false, '');

// Close and output PDF document
$safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $application['name'] ?? 'Candidate');
$pdfFileName = 'Candidate_CV_' . $safeName . '_' . $application_id . '.pdf';
$pdf->Output($pdfFileName, 'D'); // D for download
