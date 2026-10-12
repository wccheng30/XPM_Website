<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);

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
                    if (!in_array($file_extension, ['csv', 'xlsx', 'xls'])) {
                        $error = 'Please upload a CSV or Excel file (.csv, .xlsx, .xls)';
                    } else {
                        try {
                            // Process the file
                            $import_data = processImportFile($file);
                            if (!empty($import_data)) {
                                $success = 'File processed successfully! Please map your columns below.';
                            } else {
                                $error = 'No data found in the file or file format not supported.';
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
                // Handle column mapping and preview
                if (isset($_POST['column_mapping']) && !empty($import_data)) {
                    $column_mapping = $_POST['column_mapping'];
                    $preview_data = generatePreview($import_data, $column_mapping);
                }
                break;
                
            case 'import':
                // Handle actual import
                if (isset($_POST['column_mapping']) && !empty($import_data)) {
                    $column_mapping = $_POST['column_mapping'];
                    $import_result = performImport($import_data, $column_mapping);
                    
                    if ($import_result['success']) {
                        $success = "Import completed! {$import_result['imported']} jobs imported successfully.";
                        if ($import_result['errors'] > 0) {
                            $success .= " {$import_result['errors']} jobs had errors and were skipped.";
                        }
                        $import_data = []; // Clear data after successful import
                    } else {
                        $error = $import_result['message'];
                    }
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
    
    foreach (array_slice($data, 0, 5) as $index => $row) { // Show first 5 rows
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
            }
        }
        
        $pdo->commit();
        return ['success' => true, 'imported' => $imported, 'errors' => $errors];
        
    } catch (Exception $e) {
        $pdo->rollback();
        return ['success' => false, 'message' => 'Import failed: ' . $e->getMessage()];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Jobs - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="css/import-jobs.css?v=20260930-4">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Import Jobs', 'fas fa-upload', ['url' => 'jobs.php', 'label' => 'Jobs']); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('jobs'); ?>
        
        <div class="import-container">
            <!-- Back Button -->
            <div style="margin-bottom: 20px;">
                <a href="jobs.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Jobs
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
                </div>
            <?php endif; ?>
            
            <!-- Step 1: File Upload -->
            <div class="import-step" id="step-upload">
                <div class="step-header">
                    <div class="step-number">1</div>
                    <div class="step-title">Upload Your File</div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="upload-form">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="file-upload-area" id="file-upload-area">
                        <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #94a3b8; margin-bottom: 16px;"></i>
                        <h3 style="margin-bottom: 8px; color: #374151;">Drop your file here or click to browse</h3>
                        <p style="color: #64748b; margin-bottom: 16px;">
                            Supported formats: CSV, Excel (.xlsx, .xls)
                        </p>
                        <input type="file" name="import_file" id="import_file" accept=".csv,.xlsx,.xls" style="display: none;">
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('import_file').click()">
                            <i class="fas fa-folder-open"></i>
                            Choose File
                        </button>
                    </div>
                    
                    <div id="file-info" class="hidden" style="margin-top: 16px; padding: 16px; background: #f4f4f5; border-radius: 8px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <i class="fas fa-file" style="color: #111111;"></i>
                            <div>
                                <div id="file-name" style="font-weight: 600; color: #1e293b;"></div>
                                <div id="file-size" style="font-size: 14px; color: #64748b;"></div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Step 2: Column Mapping -->
            <?php if (!empty($import_data)): ?>
            <div class="import-step" id="step-mapping">
                <div class="step-header">
                    <div class="step-number">2</div>
                    <div class="step-title">Map Your Columns</div>
                </div>
                
                <p style="color: #64748b; margin-bottom: 20px;">
                    Map your file columns to our job fields. Required fields are marked with <span class="required">*</span>.
                </p>
                
                <form method="POST" id="mapping-form">
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
                            <label class="mapping-label">
                                <?php echo htmlspecialchars($label); ?>
                            </label>
                            <select name="column_mapping[<?php echo $field; ?>]" class="mapping-select">
                                <option value="">-- Select Column --</option>
                                <?php foreach ($file_columns as $column): ?>
                                <option value="<?php echo htmlspecialchars($column); ?>"
                                    <?php echo (isset($column_mapping[$field]) && $column_mapping[$field] === $column) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($column); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-eye"></i>
                            Preview Data
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Step 3: Data Preview -->
            <?php if (!empty($preview_data)): ?>
            <div class="import-step" id="step-preview">
                <div class="step-header">
                    <div class="step-number">3</div>
                    <div class="step-title">Preview Your Data</div>
                </div>
                
                <div class="import-summary">
                    <div class="summary-item">
                        <span class="summary-label">Total Records:</span>
                        <span class="summary-value"><?php echo count($import_data); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Preview Records:</span>
                        <span class="summary-value"><?php echo count($preview_data); ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="summary-label">Valid Records:</span>
                        <span class="summary-value" style="color: #059669;">
                            <?php echo count(array_filter($preview_data, function($row) { return !$row['_has_errors']; })); ?>
                        </span>
                    </div>
                </div>
                
                <table class="preview-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Location</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview_data as $row): ?>
                        <tr class="<?php echo $row['_has_errors'] ? 'error-row' : ''; ?>">
                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo htmlspecialchars($row['location']); ?></td>
                            <td><?php echo htmlspecialchars($row['type']); ?></td>
                            <td><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : ''); ?></td>
                            <td>
                                <?php if ($row['_has_errors']): ?>
                                    <span style="color: #dc2626;">Missing required fields</span>
                                <?php else: ?>
                                    <span style="color: #059669;">Ready to import</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <form method="POST" id="import-form">
                    <input type="hidden" name="action" value="import">
                    <?php foreach ($column_mapping as $field => $column): ?>
                    <input type="hidden" name="column_mapping[<?php echo $field; ?>]" value="<?php echo htmlspecialchars($column); ?>">
                    <?php endforeach; ?>
                    
                    <div class="btn-group">
                        <button type="submit" class="btn btn-success" data-confirm-title="Import these jobs?" data-confirm="The jobs in this file will be added to the site." data-confirm-ok="Import" data-confirm-icon="fas fa-upload">
                            <i class="fas fa-upload"></i>
                            Import Jobs
                        </button>
                        <a href="import-jobs.php" class="btn btn-secondary">
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
        
        // Auto-submit mapping form when all required fields are selected
        document.addEventListener('DOMContentLoaded', function() {
            const mappingForm = document.getElementById('mapping-form');
            if (mappingForm) {
                const selects = mappingForm.querySelectorAll('select[name^="column_mapping"]');
                const requiredSelects = Array.from(selects).slice(0, 4); // First 4 are required
                
                function checkRequiredFields() {
                    const allSelected = requiredSelects.every(select => select.value !== '');
                    if (allSelected) {
                        // Auto-submit after a short delay
                        setTimeout(() => {
                            mappingForm.submit();
                        }, 500);
                    }
                }
                
                requiredSelects.forEach(select => {
                    select.addEventListener('change', checkRequiredFields);
                });
            }
        });
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
