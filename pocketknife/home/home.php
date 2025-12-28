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
    // Handle DELETE requests
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Check for errors before proceeding
        if ($errorMessage !== null) {
            throw new Exception($errorDetails);
        }
        
        if (isset($data['type'])) {
            if ($data['type'] === 'file' && isset($data['filename'])) {
                $filePath = __DIR__ . '/../uploaded/' . basename($data['filename']);
                if (file_exists($filePath) && is_file($filePath)) {
                    unlink($filePath);
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit;
                }
            } elseif ($data['type'] === 'link' && isset($data['code'])) {
                $shortlinksFile = __DIR__ . '/shortlinks.txt';
                if (file_exists($shortlinksFile)) {
                    $lines = file($shortlinksFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $updatedLines = [];
                    foreach ($lines as $line) {
                        $parts = explode('|', $line);
                        if (count($parts) >= 1 && $parts[0] !== $data['code']) {
                            $updatedLines[] = $line;
                        }
                    }
                    file_put_contents($shortlinksFile, implode("\n", $updatedLines) . "\n");
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                    exit;
                }
            } elseif ($data['type'] === 'note' && isset($data['filename'])) {
                $notesDir = __DIR__ . '/../notes/';
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
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
        exit;
    }
    
    // Handle PUT/PATCH requests for editing shortlink codes
    if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Check for errors before proceeding
        if ($errorMessage !== null) {
            throw new Exception($errorDetails);
        }
        
        if (isset($data['type']) && $data['type'] === 'link' && isset($data['oldCode']) && isset($data['newCode'])) {
            $oldCode = trim($data['oldCode']);
            $newCode = trim($data['newCode']);
            
            // Validate new code: 1-10 characters, a-z0-9 only
            if (!preg_match('/^[a-z0-9]{1,10}$/', $newCode)) {
                ob_clean();
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Invalid code. Must be 1-10 characters (a-z, 0-9 only)']);
                exit;
            }
            
            $shortlinksFile = __DIR__ . '/shortlinks.txt';
            if (file_exists($shortlinksFile)) {
                $lines = file($shortlinksFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $updatedLines = [];
                $found = false;
                $collision = false;
                
                // Load existing codes (excluding the current one being edited)
                $existingCodes = [];
                foreach ($lines as $line) {
                    $parts = explode('|', $line);
                    if (count($parts) >= 1 && $parts[0] !== $oldCode) {
                        $existingCodes[$parts[0]] = true;
                    }
                }
                
                // Check for collision
                if (isset($existingCodes[$newCode])) {
                    $collision = true;
                }
                
                if (!$collision) {
                    // Update the code
                    foreach ($lines as $line) {
                        $parts = explode('|', $line);
                        if (count($parts) === 4 && $parts[0] === $oldCode) {
                            $found = true;
                            $updatedLines[] = $newCode . '|' . $parts[1] . '|' . $parts[2] . '|' . $parts[3];
                        } else {
                            $updatedLines[] = $line;
                        }
                    }
                    
                    if ($found) {
                        file_put_contents($shortlinksFile, implode("\n", $updatedLines) . "\n");
                        ob_clean();
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true]);
                        exit;
                    }
                } else {
                    ob_clean();
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'error' => 'Code already exists']);
                    exit;
                }
            }
        }
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid request']);
        exit;
    }

// Format file size
function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Get uploaded files
$uploadDir = __DIR__ . '/../uploaded/';
$files = [];
$totalSize = 0;

if (is_dir($uploadDir)) {
    $fileList = scandir($uploadDir);
    foreach ($fileList as $file) {
        if ($file !== '.' && $file !== '..' && is_file($uploadDir . $file)) {
            $filePath = $uploadDir . $file;
            $size = filesize($filePath);
            $totalSize += $size;
            $files[] = [
                'name' => $file,
                'size' => $size,
                'date' => date('Y-m-d H:i:s', filemtime($filePath))
            ];
        }
    }
}

// Get short links
$shortlinksFile = __DIR__ . '/shortlinks.txt';
$shortLinks = [];

if (file_exists($shortlinksFile)) {
    $lines = file($shortlinksFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $parts = explode('|', $line);
        if (count($parts) === 4) {
            $shortLinks[] = [
                'code' => $parts[0],
                'url' => $parts[1],
                'datetime' => $parts[2],
                'counter' => intval($parts[3])
            ];
        }
    }
}

// Get notes
$notesDir = __DIR__ . '/../notes/';
$notes = [];

if (is_dir($notesDir)) {
    $fileList = scandir($notesDir);
    foreach ($fileList as $file) {
        if ($file !== '.' && $file !== '..' && is_file($notesDir . $file) && preg_match('/\.txt$/', $file)) {
            $filePath = $notesDir . $file;
            $fileContent = file_get_contents($filePath);
            $lines = explode("\n", $fileContent);
            
            $name = '';
            $date = '';
            $content = '';
            $inContent = false;
            
            foreach ($lines as $line) {
                if (preg_match('/^Date:\s*(.+)$/', $line, $matches)) {
                    $date = trim($matches[1]);
                } elseif (preg_match('/^Name:\s*(.+)$/', $line, $matches)) {
                    $name = trim($matches[1]);
                } elseif ($line === '' && !$inContent) {
                    $inContent = true;
                } elseif ($inContent) {
                    $content .= ($content === '' ? '' : "\n") . $line;
                }
            }
            
            // Content is stored raw, no need to decode
            
            // Truncate content to 60 characters for display
            $truncatedContent = mb_strlen($content) > 60 ? mb_substr($content, 0, 60) . '...' : $content;
            
            $notes[] = [
                'filename' => $file,
                'name' => $name,
                'content' => $content,
                'truncatedContent' => $truncatedContent,
                'date' => $date
            ];
        }
    }
    
    // Sort notes by date (newest first)
    usort($notes, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
}

// Get current domain for short links
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocketknife</title>
    <link rel="stylesheet" href="/pocketknife/style.css">
</head>
<body class="dashboard">
    <div class="container dashboard">
        <h1><svg
  xmlns="http://www.w3.org/2000/svg"
  width="24"
  height="24"
  viewBox="0 0 24 24"
  fill="none"
  stroke="#4a9eff"
  stroke-width="2"
  stroke-linecap="round"
  stroke-linejoin="round"
>
  <path d="M3 2v1c0 1 2 1 2 2S3 6 3 7s2 1 2 2-2 1-2 2 2 1 2 2" />
  <path d="M18 6h.01" />
  <path d="M6 18h.01" />
  <path d="M20.83 8.83a4 4 0 0 0-5.66-5.66l-12 12a4 4 0 1 0 5.66 5.66Z" />
  <path d="M18 11.66V22a4 4 0 0 0 4-4V6" />
</svg> Pocketknife</h1>
        
        <section>
            <h2>Uploaded Files</h2>
            <?php if (count($files) > 0): ?>
                <div class="total-size">
                    Total folder size: <?php echo formatSize($totalSize); ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td><a href="/pocketknife/uploaded/<?php echo htmlspecialchars($file['name']); ?>" target="_blank"><?php echo htmlspecialchars($file['name']); ?></a></td>
                                <td><?php echo formatSize($file['size']); ?></td>
                                <td><?php echo htmlspecialchars($file['date']); ?></td>
                                <td>
                                    <a href="#" class="delete-link" onclick="deleteFile('<?php echo htmlspecialchars($file['name']); ?>'); return false;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">No files uploaded yet</div>
            <?php endif; ?>
        </section>
        
        <section>
            <h2>Short Links</h2>
            <?php if (count($shortLinks) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Short Link</th>
                            <th>Original URL</th>
                            <th>Created</th>
                            <th>Accessed</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shortLinks as $link): ?>
                            <tr>
                                <td><a href="<?php echo htmlspecialchars($protocol . $host . '/s/' . $link['code']); ?>" target="_blank"><?php echo htmlspecialchars($link['code']); ?></a></td>
                                <td><?php echo htmlspecialchars($link['url']); ?></td>
                                <td><?php echo htmlspecialchars($link['datetime']); ?></td>
                                <td><?php echo $link['counter']; ?></td>
                                <td>
                                    <a href="#" class="delete-link" onclick="editLinkCode('<?php echo htmlspecialchars($link['code']); ?>'); return false;">Edit</a> |
                                    <a href="#" class="delete-link" onclick="deleteLink('<?php echo htmlspecialchars($link['code']); ?>'); return false;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">No short links created yet</div>
            <?php endif; ?>
        </section>
        
        <section>
            <h2>Notes</h2>
            <?php if (count($notes) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Text</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notes as $note): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($note['name'] ?: '(No name)'); ?></td>
                                <td><a href="/pocketknife/notes/<?php echo htmlspecialchars($note['filename']); ?>" target="_blank"><?php echo htmlspecialchars($note['truncatedContent']); ?></a></td>
                                <td><?php echo htmlspecialchars($note['date']); ?></td>
                                <td>
                                    <a href="/note?edit=<?php echo urlencode($note['filename']); ?>">Edit</a> |
                                    <a href="#" class="delete-link" onclick="deleteNote('<?php echo htmlspecialchars($note['filename']); ?>'); return false;">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty">No notes created yet</div>
            <?php endif; ?>
        </section>
    </div>
    
    <script>
        function deleteFile(filename) {
            if (confirm('Delete ' + filename + '?')) {
                fetch(window.location.href, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: 'file',
                        filename: filename
                    })
                }).then(() => {
                    location.reload();
                });
            }
        }
        
        function editLinkCode(code) {
            var newCode = prompt('Enter new code (1-10 characters, a-z0-9 only):', code);
            if (newCode !== null && newCode.trim() !== '') {
                newCode = newCode.trim().toLowerCase();
                if (!/^[a-z0-9]{1,10}$/.test(newCode)) {
                    alert('Invalid code. Must be 1-10 characters (a-z, 0-9 only)');
                    return;
                }
                fetch(window.location.href, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: 'link',
                        oldCode: code,
                        newCode: newCode
                    })
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error || 'Failed to update code');
                    }
                });
            }
        }
        
        function deleteLink(code) {
            if (confirm('Delete short link ' + code + '?')) {
                fetch(window.location.href, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: 'link',
                        code: code
                    })
                }).then(() => {
                    location.reload();
                });
            }
        }
        
        function deleteNote(filename) {
            if (confirm('Delete note ' + filename + '?')) {
                fetch(window.location.href, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        type: 'note',
                        filename: filename
                    })
                }).then(() => {
                    location.reload();
                });
            }
        }
    </script>
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

