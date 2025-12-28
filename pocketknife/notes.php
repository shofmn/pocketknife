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
    
    $message = '';
    $success = false;
    
    // Handle POST requests (create new note only - editing is handled by /home endpoint)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $content = isset($_POST['content']) ? trim($_POST['content']) : '';
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        
        // Validate content length
        if (strlen($content) > 10000) {
            $message = 'Note content exceeds 10,000 characters';
        } elseif (empty($content)) {
            $message = 'Note content cannot be empty';
        } else {
            // Store raw content (we escape when displaying, not when storing)
            $rawContent = $content;
            $rawName = $name;
            
            // Create new note (editing is handled by /home endpoint)
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
        <h1 class="centered">Create Note</h1>
        <form method="POST">
            <input type="text" name="name" placeholder="Name (optional)" value="" maxlength="255">
            <textarea name="content" placeholder="Note content (max 10,000 characters)" rows="15" maxlength="10000" required></textarea>
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

