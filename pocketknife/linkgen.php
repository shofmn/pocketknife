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
        
        // Generate random 5-character code
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $maxAttempts = 100;
        $code = '';
        $found = false;
        
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
        
        // Generate unique code
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = substr(str_shuffle($chars), 0, 5);
            if (!isset($existingCodes[$code])) {
                $found = true;
                break;
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
        } else {
            $message = 'Failed to generate unique code';
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
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

