<?php
$message = '';
$shortLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['url'])) {
    $url = trim($_POST['url']);
    
    // Basic URL validation
    if (filter_var($url, FILTER_VALIDATE_URL)) {
        $shortlinksFile = __DIR__ . '/home/shortlinks.txt';
        $shortlinksDir = dirname($shortlinksFile);
        
        if (!is_dir($shortlinksDir)) {
            mkdir($shortlinksDir, 0755, true);
        }
        
        // Load existing codes
        $existingCodes = [];
        if (file_exists($shortlinksFile)) {
            $lines = file($shortlinksFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode('|', $line);
                if (count($parts) >= 1) {
                    $existingCodes[$parts[0]] = true;
                }
            }
        }
        
        $code = '';
        $found = false;
        
        // Check if custom code provided
        $customCode = isset($_POST['custom_code']) ? trim($_POST['custom_code']) : '';
        
        if (!empty($customCode)) {
            // Validate custom code: 1-10 characters, a-z0-9 only
            if (preg_match('/^[a-z0-9]{1,10}$/', $customCode)) {
                // Check for collision
                if (!isset($existingCodes[$customCode])) {
                    $code = $customCode;
                    $found = true;
                } else {
                    $message = 'Custom code already exists';
                }
            } else {
                $message = 'Invalid custom code. Must be 1-10 characters (a-z, 0-9 only)';
            }
        } else {
            // Generate random 5-character code
            $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $maxAttempts = 100;
            
            // Generate unique code
            for ($i = 0; $i < $maxAttempts; $i++) {
                $code = substr(str_shuffle($chars), 0, 5);
                if (!isset($existingCodes[$code])) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $message = 'Failed to generate unique code';
            }
        }
        
        if ($found) {
            $datetime = gmdate('Y-m-d H:i:s') . ' UTC';
            $line = $code . '|' . $url . '|' . $datetime . '|0' . "\n";
            
            file_put_contents($shortlinksFile, $line, FILE_APPEND);
            
            // Get current domain
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $shortLink = $protocol . $host . '/s/' . $code;
            $message = 'Short link created';
        }
    } else {
        $message = 'Invalid URL';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pocketknife - Link Shortener</title>
    <link rel="stylesheet" href="/pocketknife/style.css">
</head>
<body class="form-page">
    <div class="container form">
        <h1 class="centered">Shorten Link</h1>
        <form method="POST">
            <input type="url" name="url" placeholder="https://example.com" required>
            <input type="text" name="custom_code" placeholder="Custom code (optional, 1-10 chars, a-z0-9)" maxlength="10" pattern="[a-z0-9]{1,10}">
            <button type="submit">Shorten</button>
        </form>
        <?php if ($message): ?>
            <div class="message <?php echo $shortLink ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        <?php if ($shortLink): ?>
            <div class="short-link">
                <a href="<?php echo htmlspecialchars($shortLink); ?>" target="_blank"><?php echo htmlspecialchars($shortLink); ?></a>
                <button type="button" class="copy-button" onclick="copyToClipboard('<?php echo htmlspecialchars($shortLink); ?>', this)">Copy</button>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        async function copyToClipboard(text, button) {
            try {
                // Try modern Clipboard API first (works on iOS 13.4+, modern browsers)
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    await navigator.clipboard.writeText(text);
                    showCopySuccess(button);
                    return;
                }
                
                // Fallback for older browsers (including older iOS)
                // Create a temporary textarea element
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                textarea.style.left = '-999999px';
                document.body.appendChild(textarea);
                
                // Select and copy
                textarea.select();
                textarea.setSelectionRange(0, text.length); // For iOS
                
                try {
                    const successful = document.execCommand('copy');
                    if (successful) {
                        showCopySuccess(button);
                    } else {
                        throw new Error('Copy command failed');
                    }
                } catch (err) {
                    // If execCommand fails, try selecting the text for manual copy
                    alert('Please copy manually: ' + text);
                }
                
                document.body.removeChild(textarea);
            } catch (err) {
                // Final fallback: show the text for manual copying
                alert('Please copy manually: ' + text);
            }
        }
        
        function showCopySuccess(button) {
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.add('copied');
            setTimeout(function() {
                button.textContent = originalText;
                button.classList.remove('copied');
            }, 2000);
        }
    </script>
</body>
</html>

