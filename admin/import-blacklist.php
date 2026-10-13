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
                    $importError = auth_import_file_error($file, ['csv']);
                    if ($importError !== '') {
                        $error = $importError;
                    } else {
                        try {
                            $import_data = processImportFile($file);
                            if (!empty($import_data)) {
                                // Store data in session for multi-step workflow
                                $_SESSION['blacklist_import_data'] = $import_data;
                                $success = 'File processed successfully! ' . count($import_data) . ' rows found. Please map your columns below.';
                            } else {
                                $error = 'No data found in the file or the file is empty.';
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
                // Restore data from session
                $import_data = $_SESSION['blacklist_import_data'] ?? [];
                if (isset($_POST['column_mapping']) && !empty($import_data)) {
                    $column_mapping = $_POST['column_mapping'];
                    $_SESSION['blacklist_column_mapping'] = $column_mapping;
                    $preview_data = generateBlacklistPreview($import_data, $column_mapping);
                } else {
                    $error = 'No import data found. Please upload a file first.';
                }
                break;
                
            case 'import':
                // Restore data from session
                $import_data = $_SESSION['blacklist_import_data'] ?? [];
                $column_mapping = $_POST['column_mapping'] ?? $_SESSION['blacklist_column_mapping'] ?? [];
                
                if (!empty($import_data) && !empty($column_mapping)) {
                    $import_result = performBlacklistImport($import_data, $column_mapping);
                    
                    if ($import_result['success']) {
                        $success = "Import completed! {$import_result['imported']} blacklist entries imported successfully.";
                        if ($import_result['skipped'] > 0) {
                            $success .= " {$import_result['skipped']} entries were skipped (missing required name).";
                        }
                        if ($import_result['duplicates'] > 0) {
                            $success .= " {$import_result['duplicates']} duplicate entries were skipped.";
                        }
                        $_SESSION['blacklist_import_result'] = $import_result;
                        $_SESSION['blacklist_import_report'] = $import_result['report'] ?? [];
                        unset($_SESSION['blacklist_import_data']);
                        unset($_SESSION['blacklist_column_mapping']);
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

// Restore session data if available (for multi-step)
if (empty($import_data) && isset($_SESSION['blacklist_import_data'])) {
    $import_data = $_SESSION['blacklist_import_data'];
}
if (empty($column_mapping) && isset($_SESSION['blacklist_column_mapping'])) {
    $column_mapping = $_SESSION['blacklist_column_mapping'];
}
if ($import_result === null && isset($_SESSION['blacklist_import_result'])) {
    $import_result = $_SESSION['blacklist_import_result'];
}

function processImportFile($file) {
    $data = [];
    $handle = fopen($file['tmp_name'], 'r');
    
    if ($handle === false) {
        throw new Exception('Unable to open the uploaded file.');
    }
    
    // Detect delimiter
    $delimiter = detectCSVDelimiter($file['tmp_name']);
    
    // Read header row
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        fclose($handle);
        throw new Exception('No header row found in the CSV file.');
    }
    
    // Clean BOM from first header if present
    $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
    
    // Trim headers
    $headers = array_map('trim', $headers);
    
    // Read data rows
    $row_number = 1;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $row_number++;
        
        // Skip completely empty rows
        if (count(array_filter($row, function($v) { return trim($v) !== ''; })) === 0) {
            continue;
        }
        
        // Pad or trim row to match header count
        if (count($row) < count($headers)) {
            $row = array_pad($row, count($headers), '');
        } elseif (count($row) > count($headers)) {
            $row = array_slice($row, 0, count($headers));
        }
        
        $data[] = array_combine($headers, $row);
    }
    
    fclose($handle);
    return $data;
}

function detectCSVDelimiter($file_path) {
    $handle = fopen($file_path, 'r');
    $first_line = fgets($handle);
    fclose($handle);
    
    $delimiters = [',', ';', "\t", '|'];
    $delimiter_counts = [];
    
    foreach ($delimiters as $delimiter) {
        $delimiter_counts[$delimiter] = substr_count($first_line, $delimiter);
    }
    
    return array_search(max($delimiter_counts), $delimiter_counts);
}

function normalizeDate($date_str) {
    $date_str = trim($date_str);
    if (empty($date_str)) return '';
    
    // Already YYYY-MM-DD
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_str)) {
        return $date_str;
    }
    
    // DD/MM/YYYY or DD-MM-YYYY (common format from screenshot)
    if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})$#', $date_str, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    
    // MM/DD/YYYY
    if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{2})$#', $date_str, $m)) {
        $year = $m[3] > 50 ? '19' . $m[3] : '20' . $m[3];
        return sprintf('%04d-%02d-%02d', $year, $m[1], $m[2]);
    }
    
    // Try PHP's strtotime as fallback
    $timestamp = strtotime($date_str);
    if ($timestamp !== false) {
        return date('Y-m-d', $timestamp);
    }
    
    return $date_str;
}

function generateBlacklistPreview($data, $mapping) {
    $preview = [];
    
    foreach (array_slice($data, 0, 10) as $row) {
        $mapped_row = [];
        $has_errors = false;
        
        foreach ($mapping as $our_field => $their_column) {
            if (!empty($their_column) && isset($row[$their_column])) {
                $value = trim($row[$their_column]);
                // Normalize date field
                if ($our_field === 'date') {
                    $value = normalizeDate($value);
                }
                $mapped_row[$our_field] = $value;
            } else {
                $mapped_row[$our_field] = '';
            }
        }
        
        // Name is the only required field
        if (empty($mapped_row['name'])) {
            $has_errors = true;
        }
        
        $mapped_row['_has_errors'] = $has_errors;
        $preview[] = $mapped_row;
    }
    
    return $preview;
}

function performBlacklistImport($data, $mapping) {
    $pdo = get_db_connection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }
    
    $imported = 0;
    $skipped = 0;
    $duplicates = 0;
    $report = [];
    
    try {
        $pdo->beginTransaction();
        
        // Prepare duplicate check statement
        $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM blacklist WHERE name = ? AND phone_number = ?");
        
        // Prepare insert statement
        $insert_stmt = $pdo->prepare(
            "INSERT INTO blacklist (name, position_applied, phone_number, date, reason) VALUES (?, ?, ?, ?, ?)"
        );
        
        foreach ($data as $row) {
            $mapped = [];
            
            foreach ($mapping as $our_field => $their_column) {
                if (!empty($their_column) && isset($row[$their_column])) {
                    $mapped[$our_field] = trim($row[$their_column]);
                } else {
                    $mapped[$our_field] = '';
                }
            }
            
            // Normalize date
            $mapped['date'] = normalizeDate($mapped['date'] ?? '');
            
            // Skip rows without a name
            $name = $mapped['name'] ?? '';
            if (empty($name)) {
                $skipped++;
                $report[] = [
                    'name' => $name,
                    'phone' => $mapped['phone_number'] ?? '',
                    'reason' => 'Missing required name',
                ];
                continue;
            }
            
            $position = $mapped['position_applied'] ?? '';
            $phone = $mapped['phone_number'] ?? '';
            $date = $mapped['date'] ?? '';
            $reason = $mapped['reason'] ?? '';
            
            // Check for duplicates (same name + phone)
            $check_stmt->execute([$name, $phone]);
            if ($check_stmt->fetchColumn() > 0) {
                $duplicates++;
                $report[] = [
                    'name' => $name,
                    'phone' => $phone,
                    'reason' => 'Duplicate name and phone',
                ];
                continue;
            }
            
            $insert_stmt->execute([$name, $position, $phone, $date, $reason]);
            $imported++;
        }
        
        $pdo->commit();
        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $duplicates,
            'duplicates' => $duplicates,
            'report' => $report
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'The import could not be completed.'];
    }
}

if (isset($_GET['template'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="blacklist-import-template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Full Name', 'Position Applied', 'Phone Number', 'Date Added', 'Reason']);
    fputcsv($out, ['Jane Example', 'HR Assistant', '+60123456789', date('Y-m-d'), 'Sample reason']);
    fclose($out);
    exit;
}

if (isset($_GET['error_report']) && !empty($_SESSION['blacklist_import_report'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="blacklist-import-errors.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['name', 'phone', 'reason']);
    foreach ($_SESSION['blacklist_import_report'] as $row) {
        fputcsv($out, [$row['name'] ?? '', $row['phone'] ?? '', $row['reason'] ?? '']);
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
    <title>Import Blacklist - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/import.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Import Blacklist', 'fas fa-upload', ['url' => 'blacklist.php', 'label' => 'Blacklist']); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('blacklist'); ?>
        
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
                <p class="step-help">Drop a CSV file, or choose one with the keyboard. A template is ready if you need the column names.</p>
                <form method="POST" enctype="multipart/form-data" id="upload-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="upload">
                    <div class="file-upload-area" id="file-upload-area" role="button" tabindex="0" aria-label="Upload a blacklist CSV. Drop a file here or press Enter to browse.">
                        <i class="fas fa-cloud-upload-alt file-upload-icon" aria-hidden="true"></i>
                        <h3>Drop your CSV file here or press Enter to browse</h3>
                        <p>Supported format: CSV (.csv)</p>
                        <input class="file-input" type="file" name="import_file" id="import_file" accept=".csv">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('import_file').click()">
                            <i class="fas fa-folder-open" aria-hidden="true"></i> Choose file
                        </button>
                    </div>
                    <div id="file-info" class="file-info" hidden>
                        <i class="fas fa-file-csv" aria-hidden="true"></i>
                        <div>
                            <div id="file-name" class="file-info-name"></div>
                            <div id="file-size" class="file-info-size"></div>
                        </div>
                    </div>
                </form>
                <div class="template-row">
                    <div>
                        <strong>Need a starting file?</strong>
                        <code class="template-code">Full Name, Position Applied, Phone Number, Date Added, Reason</code>
                    </div>
                    <a class="btn btn-secondary" href="import-blacklist.php?template=1"><i class="fas fa-download" aria-hidden="true"></i> Download template</a>
                </div>
            </div>
            
            <!-- Step 2: Column Mapping -->
            <?php if (!empty($import_data)): ?>
            <div class="import-step" id="step-mapping">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <h2 class="step-title">Map and preview</h2>
                </div>
                <p class="step-help">Map your CSV columns to blacklist fields. Required fields are marked with <span class="required">*</span>. The table shows the first 10 rows.</p>
                
                <form method="POST" id="mapping-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="preview">
                    
                    <div class="column-mapping">
                        <?php
                        $blacklist_fields = [
                            'name' => ['label' => 'Full Name', 'required' => true],
                            'position_applied' => ['label' => 'Position Applied', 'required' => false],
                            'phone_number' => ['label' => 'Phone Number', 'required' => false],
                            'date' => ['label' => 'Date Added', 'required' => false],
                            'reason' => ['label' => 'Reason', 'required' => false]
                        ];
                        
                        $file_columns = array_keys($import_data[0] ?? []);
                        
                        // Auto-mapping: try to match columns by similarity
                        $auto_map = [];
                        $column_aliases = [
                            'name' => ['name', 'full name', 'fullname', 'person', 'candidate'],
                            'position_applied' => ['position', 'position applied', 'job', 'role', 'title'],
                            'phone_number' => ['phone', 'phone number', 'telephone', 'tel', 'contact', 'mobile', 'hp'],
                            'date' => ['date', 'date added', 'blacklist date', 'created', 'added'],
                            'reason' => ['reason', 'remark', 'remarks', 'note', 'notes', 'description', 'comment']
                        ];
                        
                        foreach ($blacklist_fields as $field => $info) {
                            foreach ($file_columns as $col) {
                                $col_lower = strtolower(trim($col));
                                if (in_array($col_lower, $column_aliases[$field] ?? [])) {
                                    $auto_map[$field] = $col;
                                    break;
                                }
                            }
                        }
                        ?>
                        
                        <?php foreach ($blacklist_fields as $field => $info): ?>
                        <div class="mapping-row">
                            <label class="mapping-label">
                                <?php echo htmlspecialchars($info['label']); ?>
                                <?php if ($info['required']): ?><span class="required">*</span><?php endif; ?>
                            </label>
                            <select name="column_mapping[<?php echo $field; ?>]" class="mapping-select">
                                <option value="">-- Select Column --</option>
                                <?php foreach ($file_columns as $column): ?>
                                <option value="<?php echo htmlspecialchars($column); ?>"
                                    <?php 
                                    $selected = false;
                                    if (isset($column_mapping[$field]) && $column_mapping[$field] === $column) {
                                        $selected = true;
                                    } elseif (empty($column_mapping) && isset($auto_map[$field]) && $auto_map[$field] === $column) {
                                        $selected = true;
                                    }
                                    echo $selected ? 'selected' : '';
                                    ?>>
                                    <?php echo htmlspecialchars($column); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                            Preview data
                        </button>
                    </div>
                </form>
                <?php if (!empty($preview_data)): ?>
                <div class="table-wrap">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Full Name</th>
                                <th scope="col">Position Applied</th>
                                <th scope="col">Phone Number</th>
                                <th scope="col">Date</th>
                                <th scope="col">Reason</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $index => $row): ?>
                            <tr class="<?php echo $row['_has_errors'] ? 'is-error' : ''; ?>">
                                <td data-label="#"><?php echo $index + 1; ?></td>
                                <td data-label="Full Name" title="<?php echo htmlspecialchars($row['name'] ?? ''); ?>"><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                                <td data-label="Position Applied"><?php echo htmlspecialchars($row['position_applied'] ?? ''); ?></td>
                                <td data-label="Phone Number"><?php echo htmlspecialchars($row['phone_number'] ?? ''); ?></td>
                                <td data-label="Date"><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
                                <td class="reason-cell" data-label="Reason" title="<?php echo htmlspecialchars($row['reason'] ?? ''); ?>"><?php echo htmlspecialchars(substr($row['reason'] ?? '', 0, 80)) . (strlen($row['reason'] ?? '') > 80 ? '...' : ''); ?></td>
                                <td data-label="Status">
                                    <?php if ($row['_has_errors']): ?>
                                        <span class="status-bad"><i class="fas fa-times-circle" aria-hidden="true"></i> Missing name</span>
                                    <?php else: ?>
                                        <span class="status-ok"><i class="fas fa-check-circle" aria-hidden="true"></i> Ready</span>
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
                <?php
                $valid_count = count(array_filter($preview_data, function ($row) { return empty($row['_has_errors']); }));
                $error_count = count($preview_data) - $valid_count;
                ?>
                <div class="import-summary">
                    <div class="summary-item"><span class="summary-label">Rows in file</span><span class="summary-value"><?php echo count($import_data); ?></span></div>
                    <div class="summary-item"><span class="summary-label">Previewed</span><span class="summary-value"><?php echo count($preview_data); ?></span></div>
                    <div class="summary-item"><span class="summary-label">Ready in preview</span><span class="summary-value is-ok"><?php echo $valid_count; ?></span></div>
                    <div class="summary-item"><span class="summary-label">Errors in preview</span><span class="summary-value is-bad"><?php echo $error_count; ?></span></div>
                </div>
                <form method="POST" id="import-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="import">
                    <?php foreach ($column_mapping as $field => $column): ?>
                    <input type="hidden" name="column_mapping[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($column); ?>">
                    <?php endforeach; ?>
                    <div class="btn-group">
                        <button type="submit" class="btn btn-success" data-confirm-title="Import blacklist entries?" data-confirm="Import <?php echo count($import_data); ?> blacklist entries?" data-confirm-ok="Import" data-confirm-icon="fas fa-upload">
                            <i class="fas fa-upload" aria-hidden="true"></i>
                            Import <?php echo count($import_data); ?> entries
                        </button>
                        <a href="import-blacklist.php" class="btn btn-secondary"><i class="fas fa-redo" aria-hidden="true"></i> Start over</a>
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
                    <div class="summary-item"><span class="summary-label">Skipped</span><span class="summary-value"><?php echo (int) ($import_result['skipped'] ?? 0); ?></span></div>
                    <div class="summary-item"><span class="summary-label">Failed</span><span class="summary-value is-bad"><?php echo (int) ($import_result['failed'] ?? $import_result['duplicates'] ?? 0); ?></span></div>
                </div>
                <div class="btn-group">
                    <?php if (!empty($_SESSION['blacklist_import_report'])): ?>
                    <a class="btn btn-secondary" href="import-blacklist.php?error_report=1"><i class="fas fa-download" aria-hidden="true"></i> Download error report</a>
                    <?php endif; ?>
                    <a class="btn btn-primary" href="blacklist.php">View blacklist</a>
                    <a class="btn btn-secondary" href="import-blacklist.php">Import another file</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script defer src="<?php echo admin_asset('js/import.js'); ?>"></script>
</body>
</html>
