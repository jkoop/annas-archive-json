<?php

$tokens = file_get_contents('/tokens.txt') ?: abort(500, 'unable to read tokens.txt');
$tokens = explode("\n", $tokens);
$tokens = array_map('trim', $tokens);
$tokens = array_filter($tokens, 'strlen');
$tokens = array_values($tokens);

if ($_SERVER['REQUEST_METHOD'] != 'GET' && $_SERVER['REQUEST_METHOD'] != 'HEAD') abort(405);

$requestToken = trim($_SERVER['HTTP_AUTHORIZATION'] ?? abort(401, 'missing authorization header'));
if (!str_starts_with(strtolower($requestToken), 'basic ') ?? abort(401, 'wrong authorization scheme'));
$requestToken = trim(substr($requestToken, 6)); // strlen('basic ')
if (!in_array($requestToken, $tokens)) abort(401, 'invalid authorization token');

$requestHash = hash('sha256', '/' . ltrim($_SERVER['REQUEST_URI'], '/'));
$requestCachePath = '/storage/' . $requestHash;

if (file_exists($requestCachePath) && is_file($requestCachePath) && is_readable($requestCachePath) && filemtime($requestCachePath) > time() - 60 * 60) {
    header('Date: ' . date('r', filemtime($requestCachePath)));
    header('Content-Type: application/json');
    header('Content-Length: ' . filesize($requestCachePath));
    readfile($requestCachePath);
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://annas-archive.org/' . ltrim($_SERVER['REQUEST_URI'], '/'));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// https://stackoverflow.com/a/41135574/3642588
$responseHeaders = [];
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers) {
    $len = strlen($header);
    $header = explode(':', $header, 2);
    if (count($header) < 2) return $len; // ignore invalid headers
    $headers[strtolower(trim($header[0]))][] = trim($header[1]);
    return $len;
});

$responseBody = curl_exec($ch) ?: abort(502, 'http call to annas-archive failed');
$responseDate = strtotime($headers['date'][0]);
$response = @json_decode($responseBody);

// find and parse the JSON in the web page
if ($response === null) {
    $response = $responseBody;
    $response = explode("\n", $response);
    $response = array_filter($response, fn ($line) => str_contains($line, '<div class="text-xs p-4 font-mono break-words bg-[#0000000d]">'));

    if (count($response) == 0) {
        $response = null;
    } else {
        $response = array_values($response)[0];
        $response = strip_tags($response);
        $response = str_replace('&nbsp;', ' ', $response);
        $response = html_entity_decode($response);
        $response = trim($response);
        $response = json_decode($response) ?? abort(502, 'received invalid json');
    }
}

// save the response JSON to disk
$response = json_encode($response);
file_put_contents($requestCachePath, $response) ?: abort(500, 'unable to write cache file');
touch($requestCachePath, $responseDate) ?: abort(500, 'unable to write cache file');

// send the JSON
header('Date: ' . date('r', filemtime($requestCachePath)));
header('Content-Type: application/json');
header('Content-Length: ' . filesize($requestCachePath));
readfile($requestCachePath);
exit;

/********/

function abort(int $status, string $message = ''): never {
    http_response_code($status);
    if (strlen($message) > 0) {
        header('Content-Type: text/plain');
        echo $message;
    }
    exit;
}
