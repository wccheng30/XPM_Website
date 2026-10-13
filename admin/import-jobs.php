<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

// Include database connection
require_once '../includes/database.php';

$error = '';
$success = '';
$import_data = [];
$column_mapping = [];
$preview_data = [];
$import_result = null;

// Handle file upload and processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload':
                // Handle file upload
                if (isset($_FILES['import_file']) && ($_FILES['import_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $file = $_FILES['import_file'];
                    $importError = auth_import_file_error($file);
                    if ($importError !== '') {
                        $error = $importError;
                    } else {
                        try {
                            // Process the file
                            $import_data = processImportFile($file);
                            if (!empty($import_data)) {
                                $_SESSION['jobs_import_data'] = $import_data;
                                unset($_SESSION['jobs_column_mapping'], $_SESSION['jobs_import_result'], $_SESSION['jobs_import_report']);
                                $success = 'File processed successfully! Please map your columns below.';
                            } else {
                                $error = 'No data found in the file or file format not supported.';
                            }
                        } catch (Exception $e) {
                            $error = 'The file could not be processed.';
                        }
                    }
                } else {
                    $error = 'Please select a file to upload.';
                }
                break;
                
            case 'preview':
                $import_data = $_SESSION['jobs_import_data'] ?? [];
                if (isset($_POST['column_mapping']) && !empty($import_data)) {
                    $column_mapping = $_POST['column_mapping'];
                    $_SESSION['jobs_column_mapping'] = $column_mapping;
                    $preview_data = generatePreview($import_data, $column_mapping);
                } else {
                    $error = 'No import data found. Please upload a file first.';
                }
                break;
                
            case 'import':
                $import_data = $_SESSION['jobs_import_data'] ?? [];
                $column_mapping = $_POST['column_mapping'] ?? $_SESSION['jobs_column_mapping'] ?? [];
                if (!empty($import_data) && !empty($column_mapping)) {
                    $import_result = performImport($import_data, $column_mapping);
                    
                    if ($import_result['success']) {
                        $success = "Import completed! {$import_result['imported']} jobs imported successfully.";
                        if ($import_result['errors'] > 0) {
                            $success .= " {$import_result['errors']} jobs had errors and were skipped.";
                        }
                        $_SESSION['jobs_import_result'] = $import_result;
                        $_SESSION['jobs_import_report'] = $import_result['report'] ?? [];
                        unset($_SESSION['jobs_import_data'], $_SESSION['jobs_column_mapping']);
                        $import_data = [];
                    } else {
                        $error = $import_result['message'];
                    }
                } else {
                    $error = 'No import data found. Please upload a file first.';
                }
                break;
        }
    }
}

function processImportFile($file) {
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $data = [];
    
    if ($file_extension === 'csv') {
        // Handle CSV files
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle !== false) {
            // Detect delimiter
            $delimiter = detectDelimiter($file['tmp_name']);
            
            // Read header row
            $headers = fgetcsv($handle, 0, $delimiter);
            if ($headers) {
                // Read data rows
                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    if (count($row) === count($headers)) {
                        $data[] = array_combine($headers, $row);
                    }
                }
            }
            fclose($handle);
        }
    } elseif (in_array($file_extension, ['xlsx', 'xls'])) {
        // Handle Excel files (requires PhpSpreadsheet library)
        // For now, we'll provide a fallback message
        throw new Exception('Excel file support requires PhpSpreadsheet library. Please convert to CSV format.');
    }
    
    return $data;
}

function detectDelimiter($file_path) {
    $handle = fopen($file_path, 'r');
    $first_line = fgets($handle);
    fclose($handle);
    
    $delimiters = [',', ';', '\t', '|'];
    $delimiter_counts = [];
    
    foreach ($delimiters as $delimiter) {
        $delimiter_counts[$delimiter] = substr_count($first_line, $delimiter);
    }
    
    return array_search(max($delimiter_counts), $delimiter_counts);
}

function generatePreview($data, $mapping) {
    $preview = [];
    $required_fields = ['title', 'location', 'type', 'description'];
    
    foreach (array_slice($data, 0, 10) as $index => $row) {
        $mapped_row = [];
        $has_errors = false;
        
        foreach ($mapping as $our_field => $their_column) {
            if ($their_column && isset($row[$their_column])) {
                $mapped_row[$our_field] = $row[$their_column];
            } else {
                $mapped_row[$our_field] = '';
                if (in_array($our_field, $required_fields)) {
                    $has_errors = true;
                }
            }
        }
        
        $mapped_row['_has_errors'] = $has_errors;
        $preview[] = $mapped_row;
    }
    
    return $preview;
}

function performImport($data, $mapping) {
    $pdo = get_db_connection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    $imported = 0;
    $errors = 0;
    $error_messages = [];
    $report = [];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($data as $row) {
            $job_data = [];
            $has_errors = false;
            
            // Map the data
            foreach ($mapping as $our_field => $their_column) {
                if ($their_column && isset($row[$their_column])) {
                    $job_data[$our_field] = trim($row[$their_column]);
                } else {
                    $job_data[$our_field] = '';
                }
            }
            
            // Validate required fields
            $required_fields = ['title', 'location', 'type', 'description'];
            foreach ($required_fields as $field) {
                if (empty($job_data[$field])) {
                    $has_errors = true;
                    break;
                }
            }
            
            if (!$has_errors) {
                // Transform data if needed
                $job_data = transformJobData($job_data);
                
                // Insert into database
                $stmt = $pdo->prepare("
                    INSERT INTO jobs (title, location, type, description, requirements, 
                                    company_name, salary_range, experience_level, 
                                    start_time, end_time, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $job_data['title'],
                    $job_data['location'],
                    $job_data['type'],
                    $job_data['description'],
                    $job_data['requirements'] ?? '',
                    $job_data['company_name'] ?? 'HR Training',
                    $job_data['salary_range'] ?? '',
                    $job_data['experience_level'] ?? '',
                    $job_data['start_time'] ?? null,
                    $job_data['end_time'] ?? null,
                    isset($job_data['is_active']) ? (strtolower($job_data['is_active']) === 'active' ? 1 : 0) : 1
                ]);
                
                $imported++;
            } else {
                $errors++;
                $report[] = [
                    'title' => $job_data['title'] ?? '',
                    'location' => $job_data['location'] ?? '',
                    'reason' => 'Missing required fields',
                ];
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'imported' => $imported, 'skipped' => $errors, 'failed' => 0, 'errors' => $errors, 'report' => $report];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => 'The import could not be completed.'];
    }
}

function transformJobData($data) {
    // Transform job types
    $job_type_mapping = [
        'ft' => 'Full-time',
        'pt' => 'Part-time',
        'contract' => 'Contract',
        'remote' => 'Remote',
        'intern' => 'Internship',
        'full time' => 'Full-time',
        'part time' => 'Part-time'
    ];
    
    if (isset($data['type'])) {
        $type_lower = strtolower(trim($data['type']));
        if (isset($job_type_mapping[$type_lower])) {
            $data['type'] = $job_type_mapping[$type_lower];
        }
    }
    
    // Transform time formats
    if (isset($data['start_time']) && !empty($data['start_time'])) {
        $data['start_time'] = formatTime($data['start_time']);
    }
    
    if (isset($data['end_time']) && !empty($data['end_time'])) {
        $data['end_time'] = formatTime($data['end_time']);
    }
    
    return $data;
}

function formatTime($time) {
    // Handle various time formats
    $time = trim($time);
    
    // If it's already in HH:MM format, return as is
    if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
        return $time;
    }
    
    // Try to parse other formats
    $timestamp = strtotime($time);
    if ($timestamp !== false) {
        return date('H:i', $timestamp);
    }
    
    return null;
}

if (empty($import_data) && isset($_SESSION['jobs_import_data'])) {
    $import_data = $_SESSION['jobs_import_data'];
}
if (empty($column_mapping) && isset($_SESSION['jobs_column_mapping'])) {
    $column_mapping = $_SESSION['jobs_column_mapping'];
}
if ($import_result === null && isset($_SESSION['jobs_import_result'])) {
    $import_result = $_SESSION['jobs_import_result'];
}

if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="jobs-import-template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['title', 'location', 'type', 'description', 'requirements', 'company_name', 'salary_range', 'experience_level', 'start_time', 'end_time', 'is_active']);
    fputcsv($out, ['HR Assistant', 'Melaka', 'Full-time', 'Describe the role.', 'Attention to detail', 'XpressPoint', 'RM 3,000 - RM 4,000', 'Entry Level', '09:00', '17:00', 'active']);
    fclose($out);
    exit;
}

if (isset($_GET['error_report']) && !empty($_SESSION['jobs_import_report'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="jobs-import-errors.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['title', 'location', 'reason']);
    foreach ($_SESSION['jobs_import_report'] as $row) {
        fputcsv($out, [$row['title'] ?? '', $row['location'] ?? '', $row['reason'] ?? '']);
    }
    fclose($out);
    exit;
}

$step = 1;
if (!empty($import_result) && !empty($import_result['success'])) {
    $step = 3;
} elseif (!empty($preview_data)) {
    $step = 3;
} elseif (!empty($import_data)) {
    $step = 2;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Jobs - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/import.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Import Jobs', 'fas fa-upload', ['url' => 'jobs.php', 'label' => 'Jobs']); ?>
    
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('jobs'); ?>
        
        <div class="import-container">
            <ol class="import-steps">
                <li class="<?php echo $step === 1 ? 'is-current' : ($step > 1 ? 'is-done' : ''); ?>"><span class="step-index">1</span> Upload</li>
                <li class="<?php echo $step === 2 ? 'is-current' : ($step > 2 ? 'is-done' : ''); ?>"><span class="step-index">2</span> Map and preview</li>
                <li class="<?php echo $step === 3 ? 'is-current' : ''; ?>"><span class="step-index">3</span> Confirm</li>
            </ol>

            <?php if ($error): ?>
                <div class="alert alert-error"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success && empty($import_result['success'])): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="import-step" id="step-upload">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <h2 class="step-title">Upload</h2>
                </div>
                <p class="step-help">Drop a CSV or Excel file, or choose one with the keyboard. A template is ready if you need the column names.</p>
                <form method="POST" enctype="multipart/form-data" id="upload-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="upload">
                    <div class="file-upload-area" id="file-upload-area" role="button" tabindex="0" aria-label="Upload a jobs file. Drop a file here or press Enter to browse.">
                        <i class="fas fa-cloud-upload-alt file-upload-icon" aria-hidden="true"></i>
                        <h3>Drop your file here or press Enter to browse</h3>
                        <p>Supported formats: CSV, Excel (.xlsx, .xls)</p>
                        <input class="file-input" type="file" name="import_file" id="import_file" accept=".csv,.xlsx,.xls">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('import_file').click()">
                            <i class="fas fa-folder-open" aria-hidden="true"></i> Choose file
                        </button>
                    </div>
                    <div id="file-info" class="file-info" hidden>
                        <i class="fas fa-file" aria-hidden="true"></i>
                        <div>
                            <div id="file-name" class="file-info-name"></div>
                            <div id="file-size" class="file-info-size"></div>
                        </div>
                    </div>
                </form>
                <div class="template-row">
                    <div>
                        <strong>Need a starting file?</strong>
                        <code class="template-code">title, location, type, description, requirements, company_name, salary_range, experience_level, start_time, end_time, is_active</code>
                    </div>
                    <a class="btn btn-secondary" href="import-jobs.php?template=1"><i class="fas fa-download" aria-hidden="true"></i> Download template</a>
                </div>
            </div>

            <?php if (!empty($import_data)): ?>
            <div class="import-step" id="step-mapping">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <h2 class="step-title">Map and preview</h2>
                </div>
                <p class="step-help">Map your file columns to job fields. Required fields are marked with <span class="required">*</span>. The table shows the first 10 rows.</p>
                <form method="POST" id="mapping-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="preview">
                    <div class="column-mapping">
                        <?php
                        $required_fields = [
                            'title' => 'Job Title *',
                            'location' => 'Location *',
                            'type' => 'Job Type *',
                            'description' => 'Description *'
                        ];
                        $optional_fields = [
                            'requirements' => 'Requirements',
                            'company_name' => 'Company Name',
                            'salary_range' => 'Salary Range',
                            'experience_level' => 'Experience Level',
                            'start_time' => 'Start Time',
                            'end_time' => 'End Time',
                            'is_active' => 'Status'
                        ];
                        $all_fields = array_merge($required_fields, $optional_fields);
                        $file_columns = array_keys($import_data[0] ?? []);
                        ?>
                        <?php foreach ($all_fields as $field => $label): ?>
                        <div class="mapping-row">
                            <label class="mapping-label" for="map-<?php echo htmlspecialchars($field); ?>"><?php echo htmlspecialchars($label); ?></label>
                            <select id="map-<?php echo htmlspecialchars($field); ?>" name="column_mapping[<?php echo $field; ?>]" class="mapping-select">
                                <option value="">-- Select Column --</option>
                                <?php foreach ($file_columns as $column): ?>
                                <option value="<?php echo htmlspecialchars($column); ?>" <?php echo (isset($column_mapping[$field]) && $column_mapping[$field] === $column) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($column); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-eye" aria-hidden="true"></i> Preview data</button>
                    </div>
                </form>
                <?php if (!empty($preview_data)): ?>
                <div class="table-wrap">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th scope="col">Title</th>
                                <th scope="col">Location</th>
                                <th scope="col">Type</th>
                                <th scope="col">Description</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $row): ?>
                            <tr class="<?php echo $row['_has_errors'] ? 'is-error' : ''; ?>">
                                <td data-label="Title" title="<?php echo htmlspecialchars($row['title']); ?>"><?php echo htmlspecialchars($row['title']); ?></td>
                                <td data-label="Location"><?php echo htmlspecialchars($row['location']); ?></td>
                                <td data-label="Type"><?php echo htmlspecialchars($row['type']); ?></td>
                                <td data-label="Description" title="<?php echo htmlspecialchars($row['description']); ?>"><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : ''); ?></td>
                                <td data-label="Status">
                                    <?php if ($row['_has_errors']): ?>
                                        <span class="status-bad">Missing required fields</span>
                                    <?php else: ?>
                                        <span class="status-ok">Ready to import</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($preview_data) && empty($import_result['success'])): ?>
            <div class="import-step" id="step-confirm">
                <div class="step-header">
                    <div class="step-number">3</div>
                    <h2 class="step-title">Confirm</h2>
                </div>
                <p class="step-help">This will add every valid row from the file, not only the 10-row preview.</p>
                <div class="import-summary">
                    <div class="summary-item"><span class="summary-label">Rows in file</span><span class="summary-value"><?php echo count($import_data); ?></span></div>
                    <div class="summary-item"><span class="summary-label">Previewed</span><span class="summary-value"><?php echo count($preview_data); ?></span></div>
                    <div class="summary-item"><span class="summary-label">Ready in preview</span><span class="summary-value is-ok"><?php echo count(array_filter($preview_data, function ($row) { return empty($row['_has_errors']); })); ?></span></div>
                    <div class="summary-item"><span class="summary-label">Errors in preview</span><span class="summary-value is-bad"><?php echo count(array_filter($preview_data, function ($row) { return !empty($row['_has_errors']); })); ?></span></div>
                </div>
                <form method="POST" id="import-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="import">
                    <?php foreach ($column_mapping as $field => $column): ?>
                    <input type="hidden" name="column_mapping[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($column); ?>">
                    <?php endforeach; ?>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-success" data-confirm-title="Import these jobs?" data-confirm="The jobs in this file will be added to the site." data-confirm-ok="Import" data-confirm-icon="fas fa-upload">
                            <i class="fas fa-upload" aria-hidden="true"></i> Import jobs
                        </button>
                        <a href="import-jobs.php" class="btn btn-secondary"><i class="fas fa-redo" aria-hidden="true"></i> Start over</a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <?php if (!empty($import_result['success'])): ?>
            <div class="import-step" id="step-result">
                <div class="step-header">
                    <div class="step-number">3</div>
                    <h2 class="step-title">Confirm</h2>
                </div>
                <p class="step-help"><?php echo htmlspecialchars($success); ?></p>
                <div class="import-summary">
                    <div class="summary-item"><span class="summary-label">Imported</span><span class="summary-value is-ok"><?php echo (int) ($import_result['imported'] ?? 0); ?></span></div>
                    <div class="summary-item"><span class="summary-label">Skipped</span><span class="summary-value"><?php echo (int) ($import_result['skipped'] ?? $import_result['errors'] ?? 0); ?></span></div>
                    <div class="summary-item"><span class="summary-label">Failed</span><span class="summary-value is-bad"><?php echo (int) ($import_result['failed'] ?? 0); ?></span></div>
                </div>
                <div class="btn-group">
                    <?php if (!empty($_SESSION['jobs_import_report'])): ?>
                    <a class="btn btn-secondary" href="import-jobs.php?error_report=1"><i class="fas fa-download" aria-hidden="true"></i> Download error report</a>
                    <?php endif; ?>
                    <a class="btn btn-primary" href="jobs.php">View jobs</a>
                    <a class="btn btn-secondary" href="import-jobs.php">Import another file</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script defer src="<?php echo admin_asset('js/import.js'); ?>"></script>
</body>
</html>
