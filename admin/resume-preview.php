<?php
session_start();

require_once '../includes/auth.php';
auth_require(['admin', 'manager'], '403');

// Get resume path from query parameter
$resume_path = $_GET['path'] ?? '';

if (empty($resume_path)) {
    echo '<div style="text-align: center; padding: 40px 20px; color: #ef4444;">
            <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
            <h4 style="margin-bottom: 8px; color: #dc2626;">Invalid Resume Path</h4>
            <p style="color: #6b7280; margin: 0;">No resume path provided.</p>
          </div>';
    exit;
}

// Security: Ensure the path is within the uploads directory
$full_path = '../' . $resume_path;
$uploads_dir = '../uploads/resumes/';

if (!file_exists($full_path) || !is_file($full_path) || strpos(realpath($full_path), realpath($uploads_dir)) !== 0) {
    echo '<div style="text-align: center; padding: 40px 20px; color: #ef4444;">
            <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
            <h4 style="margin-bottom: 8px; color: #dc2626;">Resume Not Found</h4>
            <p style="color: #6b7280; margin: 0;">The requested resume file could not be found or accessed.</p>
          </div>';
    exit;
}

// Get file extension
$file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

// Read file content based on file type
$content = '';
$file_size = filesize($full_path);

if ($file_size > 5 * 1024 * 1024) { // 5MB limit
    echo '<div style="text-align: center; padding: 40px 20px; color: #ef4444;">
            <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
            <h4 style="margin-bottom: 8px; color: #dc2626;">File Too Large</h4>
            <p style="color: #6b7280; margin: 0;">This resume file is too large to preview. Please download it instead.</p>
            <div style="margin-top: 20px;">
                <a href="../' . htmlspecialchars($resume_path) . '" target="_blank" class="btn btn-primary" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                    <i class="fas fa-download" style="margin-right: 6px;"></i>
                    Download Resume
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
        echo '<div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-file-pdf" style="font-size: 48px; color: #ef4444; margin-bottom: 16px;"></i>
                <h4 style="margin-bottom: 8px; color: #1f2937;">PDF Resume</h4>
                <p style="color: #6b7280; margin-bottom: 20px;">PDF files cannot be previewed directly. Please download the file to view it.</p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="../' . htmlspecialchars($resume_path) . '" target="_blank" class="btn btn-primary" style="background: #3b82f6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500;">
                        <i class="fas fa-download" style="margin-right: 6px;"></i>
                        Download PDF
                    </a>
                    <a href="../' . htmlspecialchars($resume_path) . '" target="_blank" class="btn btn-secondary" style="background: #6b7280; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500;">
                        <i class="fas fa-external-link-alt" style="margin-right: 6px;"></i>
                        Open in New Tab
                    </a>
                </div>
                <div style="margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 8px; text-align: left;">
                    <h5 style="margin-bottom: 12px; color: #374151;">File Information:</h5>
                    <p style="margin: 4px 0; color: #6b7280;"><strong>File:</strong> ' . htmlspecialchars(basename($resume_path)) . '</p>
                    <p style="margin: 4px 0; color: #6b7280;"><strong>Size:</strong> ' . number_format($file_size / 1024, 1) . ' KB</p>
                    <p style="margin: 4px 0; color: #6b7280;"><strong>Type:</strong> PDF Document</p>
                </div>
              </div>';
        exit;
    case 'doc':
    case 'docx':
        // For Word documents, show a message and provide download link
        echo '<div style="text-align: center; padding: 40px 20px;">
                <i class="fas fa-file-word" style="font-size: 48px; color: #2563eb; margin-bottom: 16px;"></i>
                <h4 style="margin-bottom: 8px; color: #1f2937;">Word Document Resume</h4>
                <p style="color: #6b7280; margin-bottom: 20px;">Word documents cannot be previewed directly. Please download the file to view it.</p>
                <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                    <a href="../' . htmlspecialchars($resume_path) . '" target="_blank" class="btn btn-primary" style="background: #2563eb; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500;">
                        <i class="fas fa-download" style="margin-right: 6px;"></i>
                        Download Document
                    </a>
                    <a href="../' . htmlspecialchars($resume_path) . '" target="_blank" class="btn btn-secondary" style="background: #6b7280; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 500;">
                        <i class="fas fa-external-link-alt" style="margin-right: 6px;"></i>
                        Open in New Tab
                    </a>
                </div>
                <div style="margin-top: 20px; padding: 20px; background: #f8fafc; border-radius: 8px; text-align: left;">
                    <h5 style="margin-bottom: 12px; color: #374151;">File Information:</h5>
                    <p style="margin: 4px 0; color: #6b7280;"><strong>File:</strong> ' . htmlspecialchars(basename($resume_path)) . '</p>
                    <p style="margin: 4px 0; color: #6b7280;"><strong>Size:</strong> ' . number_format($file_size / 1024, 1) . ' KB</p>
                    <p style="margin: 4px 0; color: #6b7280;"><strong>Type:</strong> Microsoft Word Document</p>
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
    echo '<div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px; font-family: "Courier New", monospace; line-height: 1.6; color: #374151; white-space: pre-wrap; max-height: 600px; overflow-y: auto;">';
    echo htmlspecialchars($content);
    echo '</div>';
    
    echo '<div style="margin-top: 20px; padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #3b82f6;">
            <h5 style="margin-bottom: 8px; color: #374151;">File Information:</h5>
            <p style="margin: 4px 0; color: #6b7280;"><strong>File:</strong> ' . htmlspecialchars(basename($resume_path)) . '</p>
            <p style="margin: 4px 0; color: #6b7280;"><strong>Size:</strong> ' . number_format($file_size / 1024, 1) . ' KB</p>
            <p style="margin: 4px 0; color: #6b7280;"><strong>Type:</strong> ' . strtoupper($file_extension) . ' File</p>
          </div>';
} else {
    echo '<div style="text-align: center; padding: 40px 20px; color: #ef4444;">
            <i class="fas fa-exclamation-triangle" style="font-size: 32px; margin-bottom: 16px;"></i>
            <h4 style="margin-bottom: 8px; color: #dc2626;">Unable to Read File</h4>
            <p style="color: #6b7280; margin: 0;">The resume file could not be read or is empty.</p>
            <div style="margin-top: 20px;">
                <a href="../' . htmlspecialchars($resume_path) . '" target="_blank" class="btn btn-primary" style="background: #ef4444; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                    <i class="fas fa-download" style="margin-right: 6px;"></i>
                    Download Resume
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
