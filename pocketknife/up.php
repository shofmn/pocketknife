<?php
$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $uploadDir = __DIR__ . '/uploaded/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $file = $_FILES['file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $filename = basename($file['name']);
        $targetPath = $uploadDir . $filename;
        
        // Handle filename collisions by appending number
        $counter = 1;
        $pathInfo = pathinfo($filename);
        $baseName = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
        
        while (file_exists($targetPath)) {
            $targetPath = $uploadDir . $baseName . '_' . $counter . $extension;
            $counter++;
        }
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            $message = 'Upload successful';
            $success = true;
            // Redirect to prevent duplicate form submissions
            header('Location: /up?success=1');
            exit;
        } else {
            $message = 'Upload failed';
        }
    } else {
        $message = 'Upload error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocketknife - Upload</title>
    <link rel="stylesheet" href="/pocketknife/style.css">
</head>
<body class="form-page">
    <div class="container form form-small" id="upload-container">
        <h1 class="centered">Upload File</h1>
        <form method="POST" enctype="multipart/form-data" id="upload-form">
            <input type="file" name="file" id="file-input" required>
            <button type="submit" id="submit-button" style="display: none;">Upload</button>
        </form>
        <div id="success-messages"></div>
    </div>
    
    <script>
        const form = document.getElementById('upload-form');
        const fileInput = document.getElementById('file-input');
        const container = document.getElementById('upload-container');
        const successMessages = document.getElementById('success-messages');
        
        // Check if this is a successful upload (from URL parameter)
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            successMessages.insertAdjacentHTML('afterbegin', '<div class="upload-success">Upload successful</div>');
            // Reset form to allow new uploads
            form.reset();
            // Clean URL
            window.history.replaceState({}, document.title, '/up');
        }
        
        // Auto-submit when file is selected via file input
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                form.submit();
            }
        });
        
        // Drag and drop handlers
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            container.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            container.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            container.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            container.classList.add('drag-over');
        }
        
        function unhighlight() {
            container.classList.remove('drag-over');
        }
        
        container.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                // Reset form first to clear any previous file
                form.reset();
                
                // Set files using DataTransfer API
                const dataTransfer = new DataTransfer();
                Array.from(files).forEach(file => {
                    dataTransfer.items.add(file);
                });
                fileInput.files = dataTransfer.files;
                
                // Submit form
                form.submit();
            }
        }
    </script>
</body>
</html>

