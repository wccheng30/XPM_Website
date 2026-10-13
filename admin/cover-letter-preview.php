<?php
session_start();

require_once '../includes/auth.php';
auth_require(['admin', 'manager'], '403');
require_once __DIR__ . '/includes/assets.php';
admin_icons_begin();

// Get cover letter path from query parameter
$cover_letter_path = $_GET['path'] ?? '';

if (empty($cover_letter_path)) {
    echo '<div class="empty-state is-compact is-error">
            <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
            <h4 class="mb-2">Invalid Cover Letter Path</h4>
            <p class="page-sub">No cover letter path provided.</p>
          </div>';
    exit;
}

// Security: Ensure the path is within the uploads directory
$full_path = '../' . $cover_letter_path;
$uploads_dir = '../uploads/resumes/';

if (!file_exists($full_path) || !is_file($full_path) || strpos(realpath($full_path), realpath($uploads_dir)) !== 0) {
    echo '<div class="empty-state is-compact is-error">
            <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
            <h4 class="mb-2">Cover Letter Not Found</h4>
            <p class="page-sub">The requested cover letter file could not be found or accessed.</p>
          </div>';
    exit;
}

// Get file extension
$file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

// Read file content based on file type
$content = '';
$file_size = filesize($full_path);

if ($file_size > 5 * 1024 * 1024) { // 5MB limit
    echo '<div class="empty-state is-compact is-error">
            <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
            <h4 class="mb-2">File Too Large</h4>
            <p class="page-sub">This cover letter file is too large to preview. Please download it instead.</p>
            <div class="u-mt-5">
                <a href="../' . htmlspecialchars($cover_letter_path) . '" target="_blank" class="btn btn-danger">
                    <i class="fas fa-download ico-md" aria-hidden="true"></i>
                    Download Cover Letter
                </a>
            </div>
          </div>';
    exit;
}

// Read content based on file type
switch ($file_extension) {
    case 'txt':
        $content = file_get_contents($full_path);
        break;
    case 'pdf':
        // For PDF files, we'll show a message and provide download link
        echo '<div class="empty-state is-compact">
                <i class="fas fa-file-pdf empty-icon-lg is-danger" aria-hidden="true"></i>
                <h4 class="mb-2">PDF Cover Letter</h4>
                <p class="u-mb-5">PDF files cannot be previewed directly. Please download the file to view it.</p>
                <div class="cluster cluster-center">
                    <a href="../' . htmlspecialchars($cover_letter_path) . '" target="_blank" class="btn btn-primary">
                        <i class="fas fa-download ico-md" aria-hidden="true"></i>
                        Download PDF
                    </a>
                    <a href="../' . htmlspecialchars($cover_letter_path) . '" target="_blank" class="btn btn-ghost">
                        <i class="fas fa-external-link-alt ico-md" aria-hidden="true"></i>
                        Open in New Tab
                    </a>
                </div>
                <div class="panel u-mt-5">
                    <h5 class="u-mb-3">File Information:</h5>
                    <p class="note-line"><strong>File:</strong> ' . htmlspecialchars(basename($cover_letter_path)) . '</p>
                    <p class="note-line"><strong>Size:</strong> ' . number_format($file_size / 1024, 1) . ' KB</p>
                    <p class="note-line"><strong>Type:</strong> PDF Document</p>
                </div>
              </div>';
        exit;
    case 'doc':
    case 'docx':
        // For Word documents, show a message and provide download link
        echo '<div class="empty-state is-compact">
                <i class="fas fa-file-word empty-icon-lg ink" aria-hidden="true"></i>
                <h4 class="mb-2">Word Document Cover Letter</h4>
                <p class="u-mb-5">Word documents cannot be previewed directly. Please download the file to view it.</p>
                <div class="cluster cluster-center">
                    <a href="../' . htmlspecialchars($cover_letter_path) . '" target="_blank" class="btn btn-primary">
                        <i class="fas fa-download ico-md" aria-hidden="true"></i>
                        Download Document
                    </a>
                    <a href="../' . htmlspecialchars($cover_letter_path) . '" target="_blank" class="btn btn-ghost">
                        <i class="fas fa-external-link-alt ico-md" aria-hidden="true"></i>
                        Open in New Tab
                    </a>
                </div>
                <div class="panel u-mt-5">
                    <h5 class="u-mb-3">File Information:</h5>
                    <p class="note-line"><strong>File:</strong> ' . htmlspecialchars(basename($cover_letter_path)) . '</p>
                    <p class="note-line"><strong>Size:</strong> ' . number_format($file_size / 1024, 1) . ' KB</p>
                    <p class="note-line"><strong>Type:</strong> Microsoft Word Document</p>
                </div>
              </div>';
        exit;
    default:
        // For other file types, try to read as text
        $content = file_get_contents($full_path);
        break;
}

// Display the content
if (!empty($content)) {
    echo '<div class="preview-sheet">';
    echo htmlspecialchars($content);
    echo '</div>';
    
    echo '<div class="callout callout-info u-mt-5">
            <h5 class="mb-2">File Information:</h5>
            <p class="note-line"><strong>File:</strong> ' . htmlspecialchars(basename($cover_letter_path)) . '</p>
            <p class="note-line"><strong>Size:</strong> ' . number_format($file_size / 1024, 1) . ' KB</p>
            <p class="note-line"><strong>Type:</strong> ' . strtoupper($file_extension) . ' File</p>
          </div>';
} else {
    echo '<div class="empty-state is-compact is-error">
            <i class="fas fa-exclamation-triangle empty-icon" aria-hidden="true"></i>
            <h4 class="mb-2">Unable to Read File</h4>
            <p class="page-sub">The cover letter file could not be read or is empty.</p>
            <div class="u-mt-5">
                <a href="../' . htmlspecialchars($cover_letter_path) . '" target="_blank" class="btn btn-danger">
                    <i class="fas fa-download ico-md" aria-hidden="true"></i>
                    Download Cover Letter
                </a>
            </div>
          </div>';
}
?>

<style>
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-weight: 500;
    transition: all 0.2s;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>
