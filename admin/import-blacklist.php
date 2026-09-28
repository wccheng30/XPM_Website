<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';

$error = '';
$success = '';
$import_data = [];
$column_mapping = [];
$preview_data = [];

// Handle file upload and processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'upload':
                // Handle file upload
                if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['import_file'];
                    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    // Validate file type
                    if ($file_extension !== 'csv') {
                        $error = 'Please upload a CSV file (.csv)';
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
                            $error = 'Error processing file: ' . $e->getMessage();
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
                        // Clear session data after successful import
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
            'duplicates' => $duplicates
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Blacklist - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin-styles.css">
    <style>
        .import-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .import-step {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
        }
        
        .step-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .step-number {
            background: #ef4444;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .step-title {
            font-size: 18px;
            font-weight: 600;
            color: #1e293b;
        }
        
        .file-upload-area {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .file-upload-area:hover {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .file-upload-area.dragover {
            border-color: #ef4444;
            background: #fef2f2;
        }
        
        .column-mapping {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .column-mapping {
                grid-template-columns: 1fr;
            }
        }
        
        .mapping-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .mapping-label {
            font-weight: 600;
            color: #374151;
            min-width: 140px;
            font-size: 14px;
        }
        
        .mapping-select {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            font-size: 14px;
        }
        
        .required {
            color: #dc2626;
        }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        
        .preview-table th,
        .preview-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }
        
        .preview-table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }
        
        .preview-table tr.error-row {
            background: #fef2f2;
        }
        
        .preview-table tr.error-row td {
            color: #dc2626;
        }
        
        .import-summary {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .summary-item:last-child {
            margin-bottom: 0;
        }
        
        .summary-label {
            font-weight: 600;
            color: #1e293b;
        }
        
        .summary-value {
            color: #64748b;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-top: 24px;
        }
        
        .hidden {
            display: none;
        }

        .template-section {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            padding: 16px;
            margin-top: 20px;
        }

        .template-section h4 {
            margin: 0 0 8px 0;
            color: #0369a1;
            font-size: 14px;
        }

        .template-section p {
            margin: 0 0 12px 0;
            color: #64748b;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <h1>
            <i class="fas fa-upload"></i>
            Import Blacklist
        </h1>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </header>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('blacklist'); ?>
        
        <div class="import-container">
            <!-- Back Button -->
            <div style="margin-bottom: 20px;">
                <a href="blacklist.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Blacklist
                </a>
            </div>
            
            <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <?php if (strpos($success, 'Import completed') !== false): ?>
                        <a href="blacklist.php" class="btn btn-sm btn-primary" style="margin-left: 10px;">
                            View Blacklist
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- Step 1: File Upload -->
            <div class="import-step" id="step-upload">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <div class="step-title">Upload Your CSV File</div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="upload-form">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="file-upload-area" id="file-upload-area">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px;"></i>
                        <h3 style="margin-bottom: 8px; color: #374151;">Drop your CSV file here or click to browse</h3>
                        <p style="color: #64748b; margin-bottom: 16px;">
                            Supported format: CSV (.csv)
                        </p>
                        <input type="file" name="import_file" id="import_file" accept=".csv" style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('import_file').click()" style="background: #ef4444; border-color: #ef4444;">
                            <i class="fas fa-folder-open"></i>
                            Choose File
                        </button>
                    </div>
                    
                    <div id="file-info" class="hidden" style="margin-top: 16px; padding: 16px; background: #fef2f2; border-radius: 8px; border: 1px solid #fecaca;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-file-csv" style="color: #ef4444; font-size: 24px;"></i>
                            <div>
                                <div id="file-name" style="font-weight: 600; color: #1e293b;"></div>
                                <div id="file-size" style="font-size: 14px; color: #64748b;"></div>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="template-section">
                    <h4><i class="fas fa-info-circle"></i> Expected CSV Columns</h4>
                    <p>Your CSV file should contain some or all of these columns. You'll be able to map them in the next step.</p>
                    <code style="display: block; background: white; padding: 10px; border-radius: 4px; font-size: 13px; color: #374151;">
                        Full Name, Position Applied, Phone Number, Date Added, Reason
                    </code>
                </div>
            </div>
            
            <!-- Step 2: Column Mapping -->
            <?php if (!empty($import_data)): ?>
            <div class="import-step" id="step-mapping">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <div class="step-title">Map Your Columns</div>
                </div>
                
                <p style="color: #64748b; margin-bottom: 20px;">
                    Map your CSV columns to the blacklist fields. Required fields are marked with <span class="required">*</span>.
                </p>
                
                <form method="POST" id="mapping-form">
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
                        <button type="submit" class="btn btn-primary" style="background: #ef4444; border-color: #ef4444;">
                            <i class="fas fa-eye"></i>
                            Preview Data
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Step 3: Data Preview & Import -->
            <?php if (!empty($preview_data)): ?>
            <div class="import-step" id="step-preview">
                <div class="step-header">
                    <div class="step-number">3</div>
                    <div class="step-title">Preview & Import</div>
                </div>
                
                <div class="import-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total Records:</span>
                        <span class="summary-value"><?php echo count($import_data); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Preview (first <?php echo min(10, count($import_data)); ?>):</span>
                        <span class="summary-value"><?php echo count($preview_data); ?></span>
                    </div>
                    <?php 
                    $valid_count = count(array_filter($preview_data, function($row) { return !$row['_has_errors']; }));
                    $error_count = count($preview_data) - $valid_count;
                    ?>
                    <div class="summary-item">
                        <span class="summary-label">Valid Records (in preview):</span>
                        <span class="summary-value" style="color: #059669; font-weight: 600;"><?php echo $valid_count; ?></span>
                    </div>
                    <?php if ($error_count > 0): ?>
                    <div class="summary-item">
                        <span class="summary-label">Rows with Errors (in preview):</span>
                        <span class="summary-value" style="color: #dc2626; font-weight: 600;"><?php echo $error_count; ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Full Name</th>
                                <th>Position Applied</th>
                                <th>Phone Number</th>
                                <th>Date</th>
                                <th>Reason</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($preview_data as $index => $row): ?>
                            <tr class="<?php echo $row['_has_errors'] ? 'error-row' : ''; ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['position_applied'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['phone_number'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
                                <td style="max-width: 200px; white-space: normal;"><?php echo htmlspecialchars(substr($row['reason'] ?? '', 0, 80)) . (strlen($row['reason'] ?? '') > 80 ? '...' : ''); ?></td>
                                <td>
                                    <?php if ($row['_has_errors']): ?>
                                        <span style="color: #dc2626; font-weight: 500;">
                                            <i class="fas fa-times-circle"></i> Missing name
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #059669; font-weight: 500;">
                                            <i class="fas fa-check-circle"></i> Ready
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <form method="POST" id="import-form">
                    <input type="hidden" name="action" value="import">
                    <?php foreach ($column_mapping as $field => $column): ?>
                    <input type="hidden" name="column_mapping[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($column); ?>">
                    <?php endforeach; ?>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-success" style="background: #059669; border-color: #059669; color: white; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer;" onclick="return confirm('Are you sure you want to import <?php echo count($import_data); ?> blacklist entries?')">
                            <i class="fas fa-upload"></i>
                            Import <?php echo count($import_data); ?> Entries
                        </button>
                        <a href="import-blacklist.php" class="btn btn-secondary" style="padding: 10px 24px; border-radius: 8px; text-decoration: none;">
                            <i class="fas fa-redo"></i>
                            Start Over
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // File upload handling
        const fileUploadArea = document.getElementById('file-upload-area');
        const fileInput = document.getElementById('import_file');
        const fileInfo = document.getElementById('file-info');
        const fileName = document.getElementById('file-name');
        const fileSize = document.getElementById('file-size');
        
        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });
        
        fileUploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
        });
        
        fileUploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });
        
        fileInput.addEventListener('change', handleFileSelect);
        
        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                // Validate file type
                if (!file.name.toLowerCase().endsWith('.csv')) {
                    alert('Please select a CSV file.');
                    return;
                }
                
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.classList.remove('hidden');
                
                // Auto-submit the form
                document.getElementById('upload-form').submit();
            }
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Auto-hide alerts after 8 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.querySelector('.btn')) { // Don't auto-hide alerts with action buttons
                    alert.style.transition = 'opacity 0.3s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            });
        }, 8000);
    </script>
</body>
</html>
