<?php
// Short link redirect handler
$code = isset($_GET['code']) ? $_GET['code'] : '';

// If no code provided, serve index.html if it exists
if (empty($code)) {
    $indexHtml = __DIR__ . '/index.html';
    if (file_exists($indexHtml)) {
        readfile($indexHtml);
        exit;
    } else {
        http_response_code(404);
        die('Page not found');
    }
}

// Validate code format
if (!preg_match('/^[a-z0-9]{5}$/', $code)) {
    http_response_code(404);
    die('Short link not found');
}

$shortlinksFile = __DIR__ . '/pocketknife/home/shortlinks.txt';

if (!file_exists($shortlinksFile)) {
    http_response_code(404);
    die('Short link not found');
}

$lines = file($shortlinksFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$found = false;
$updatedLines = [];

foreach ($lines as $line) {
    $parts = explode('|', $line);
    if (count($parts) === 4 && $parts[0] === $code) {
        $found = true;
        $url = $parts[1];
        $datetime = $parts[2];
        $counter = intval($parts[3]) + 1;
        $updatedLines[] = $code . '|' . $url . '|' . $datetime . '|' . $counter;
    } else {
        $updatedLines[] = $line;
    }
}

if (!$found) {
    http_response_code(404);
    die('Short link not found');
}

// Update the file with incremented counter
file_put_contents($shortlinksFile, implode("\n", $updatedLines) . "\n");

// HTTP 307 redirect
header('Location: ' . $url, true, 307);
exit;

