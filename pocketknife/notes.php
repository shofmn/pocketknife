<?php
// Suppress default error display and set error reporting
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Error handling - catch all errors and display them
$errorMessage = null;
$errorDetails = null;

// Start output buffering
ob_start();

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) use (&$errorMessage, &$errorDetails) {
    $errorMessage = "Error occurred";
    $errorDetails = "Error: $errstr in $errfile on line $errline";
    return true; // Suppress default error handler
});

// Catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Clear any output
        while (ob_get_level()) {
            ob_end_clean();
        }
        // Set headers if not already sent
        if (!headers_sent()) {
            http_response_code(200); // Return 200 instead of 500
            header('Content-Type: text/html; charset=UTF-8');
        }
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocketknife - Error</title>
    <link rel="stylesheet" href="/pocketknife/style.css">
</head>
<body class="form-page">
    <div class="error-container">
        <h1 style="color: #dc3545;">Fatal Error</h1>
        <div class="error-message">
            A fatal error occurred
        </div>
        <div class="error-details">
<?php echo htmlspecialchars("Error: {$error['message']} in {$error['file']} on line {$error['line']}"); ?>
        </div>
    </div>
</body>
</html>
<?php
        exit;
    }
});

// Try-catch wrapper for the entire script
try {
    $notesDir = __DIR__ . '/notes/';
    
    // Create notes directory if it doesn't exist
    if (!is_dir($notesDir)) {
        mkdir($notesDir, 0755, true);
    }
    
    // Handle DELETE requests
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Check for errors before proceeding
        if ($errorMessage !== null) {
            throw new Exception($errorDetails);
        }
        
        if (isset($data['type']) && $data['type'] === 'note' && isset($data['filename'])) {
            $filename = basename($data['filename']); // Sanitize filename
            // Validate filename format (YYYY-MM-DD_hh-mm-ss.txt)
            if (preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.txt$/', $filename)) {
                $filePath = $notesDir . $filename;
                if (file_exists($filePath) && is_file($filePath)) {
                    // Verify file is within notes directory (prevent directory traversal)
                    $realPath = realpath($filePath);
                    $realNotesDir = realpath($notesDir);
                    if ($realPath && $realNotesDir && strpos($realPath, $realNotesDir) === 0) {
                        unlink($filePath);
                        ob_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit;
                    }
                }
            }
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
        exit;
    }
    
    $message = '';
    $success = false;
    $editFilename = '';
    $editName = '';
    $editContent = '';
    
    // Handle POST requests (save/edit note)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $editFile = isset($_POST['edit_file']) ? trim($_POST['edit_file']) : '';
        
        // Validate content length
        if (strlen($content) > 10000) {
            $message = 'Note content exceeds 10,000 characters';
        } elseif (empty($content)) {
            $message = 'Note content cannot be empty';
        } else {
            // Store raw content (we escape when displaying, not when storing)
            $rawContent = $content;
            $rawName = $name;
            
            if (!empty($editFile)) {
                // Edit existing note
                $filename = basename($editFile); // Sanitize filename
                // Validate filename format
                if (preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.txt$/', $filename)) {
                    $filePath = $notesDir . $filename;
                    // Verify file exists and is within notes directory
                    if (file_exists($filePath) && is_file($filePath)) {
                        $realPath = realpath($filePath);
                        $realNotesDir = realpath($notesDir);
                        if ($realPath && $realNotesDir && strpos($realPath, $realNotesDir) === 0) {
                            // Parse existing file to get original date
                            $existingContent = file_get_contents($filePath);
                            $lines = explode("\n", $existingContent);
                            $originalDate = '';
                            foreach ($lines as $line) {
                                if (preg_match('/^Date:\s*(.+)$/', $line, $matches)) {
                                    $originalDate = trim($matches[1]);
                                    break;
                                }
                            }
                            
                            // Use original date or current date if not found
                            $dateTime = !empty($originalDate) ? $originalDate : date('Y-m-d H:i:s');
                            
                            // Write updated note (store raw content)
                            $noteContent = "Date: $dateTime\n";
                            $noteContent .= "Name: $rawName\n";
                            $noteContent .= "\n";
                            $noteContent .= $rawContent;
                            
                            if (file_put_contents($filePath, $noteContent)) {
                                $message = 'Note updated successfully';
                                $success = true;
                            } else {
                                $message = 'Failed to update note';
                            }
                        } else {
                            $message = 'Invalid file path';
                        }
                    } else {
                        $message = 'Note file not found';
                    }
                } else {
                    $message = 'Invalid filename format';
                }
            } else {
                // Create new note
                $dateTime = date('Y-m-d H:i:s');
                $filename = date('Y-m-d_H-i-s') . '.txt';
                $filePath = $notesDir . $filename;
                
                $noteContent = "Date: $dateTime\n";
                $noteContent .= "Name: $rawName\n";
                $noteContent .= "\n";
                $noteContent .= $rawContent;
                
                if (file_put_contents($filePath, $noteContent)) {
                    $message = 'Note saved successfully';
                    $success = true;
                    // Clear form after successful save
                    $content = '';
                    $name = '';
                } else {
                    $message = 'Failed to save note';
                }
            }
        }
    }
    
    // Handle GET requests (display form, possibly with edit data)
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['edit'])) {
        $editFile = basename($_GET['edit']); // Sanitize filename
        // Validate filename format
        if (preg_match('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.txt$/', $editFile)) {
            $filePath = $notesDir . $editFile;
            // Verify file exists and is within notes directory
            if (file_exists($filePath) && is_file($filePath)) {
                $realPath = realpath($filePath);
                $realNotesDir = realpath($notesDir);
                if ($realPath && $realNotesDir && strpos($realPath, $realNotesDir) === 0) {
                    $fileContent = file_get_contents($filePath);
                    $lines = explode("\n", $fileContent);
                    
                    $editFilename = $editFile;
                    $editDate = '';
                    $editName = '';
                    $contentLines = [];
                    $inContent = false;
                    
                    foreach ($lines as $line) {
                        if (preg_match('/^Date:\s*(.+)$/', $line, $matches)) {
                            $editDate = trim($matches[1]);
                        } elseif (preg_match('/^Name:\s*(.+)$/', $line, $matches)) {
                            $editName = trim($matches[1]);
                        } elseif ($line === '' && !$inContent) {
                            $inContent = true;
                        } elseif ($inContent) {
                            $contentLines[] = $line;
                        }
                    }
                    
                    $editContent = implode("\n", $contentLines);
                    // Content is stored raw, no need to decode
                }
            }
        }
    }
    
    // Check for errors before proceeding
    if ($errorMessage !== null) {
        throw new Exception($errorDetails);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocketknife - Notes</title>
    <link rel="stylesheet" href="/pocketknife/style.css">
</head>
<body class="form-page">
    <div class="container form">
        <h1 class="centered"><?php echo !empty($editFilename) ? 'Edit Note' : 'Create Note'; ?></h1>
        <form method="POST">
            <?php if (!empty($editFilename)): ?>
                <input type="hidden" name="edit_file" value="<?php echo htmlspecialchars($editFilename); ?>">
            <?php endif; ?>
            <input type="text" name="name" placeholder="Name (optional)" value="<?php echo htmlspecialchars($editName); ?>" maxlength="255">
            <textarea name="content" placeholder="Note content (max 10,000 characters)" rows="15" maxlength="10000" required><?php echo htmlspecialchars($editContent); ?></textarea>
            <button type="submit">Save</button>
        </form>
        <?php if ($message): ?>
            <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            font-family: inherit;
            resize: vertical;
            width: 100%;
        }
        
        @media (prefers-color-scheme: dark) {
            textarea {
                background: #2d2d2d;
                border-color: #444;
                color: #e0e0e0;
            }
            
            textarea:hover {
                border-color: #666;
            }
        }
    </style>
</body>
</html>
<?php
} catch (Throwable $e) {
    $errorMessage = "Exception occurred";
    $errorDetails = "Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
}

// Display error if any occurred
if ($errorMessage !== null) {
    ob_clean(); // Clear any buffered output
    http_response_code(200); // Return 200 instead of 500
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocketknife - Error</title>
    <link rel="stylesheet" href="/pocketknife/style.css">
</head>
<body class="form-page">
    <div class="error-container">
        <h1 style="color: #dc3545;">Error</h1>
        <div class="error-message">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
        <div class="error-details">
            <?php echo htmlspecialchars($errorDetails); ?>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// If no error, output the buffered content
ob_end_flush();
?>

