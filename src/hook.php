<?php

/**
 * Git pull script
 * @author 8ctopus
 * @note When called the script automatically git pulls
 */

declare(strict_types=1);

// secret key
define('_KEY', 'SECRET');

// path to repository to pull
define('_REPOPATH', $_SERVER['DOCUMENT_ROOT'] . '/../');

// path to logs
define('_LOGPATH', $_SERVER['DOCUMENT_ROOT'] . '/../logs/git-hook/');

$logBase = 'git hook - ';

// get section to pull
$section = $_GET['section'] ?? '';

// check section
if (empty($section)) {
    error_log($logBase . ' - FAILED - no section');
    http_response_code(401);
    exit();
}

// add section to env name
$logBase .= $section;

switch ($section) {
    case 'site':
        $path = _REPOPATH . 'public_html';
        break;

    case 'store':
        $path = _REPOPATH . 'store';
        break;

    default:
        error_log($logBase . ' - FAILED - unknown section - ' . $section);
        http_response_code(401);
        exit();
}

// check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log($logBase . ' - FAILED - not POST - ' . $_SERVER['REQUEST_METHOD']);
    http_response_code(401);
    exit();
}

// get content type
$contentType = isset($_SERVER['CONTENT_TYPE']) ? mb_strtolower(trim($_SERVER['CONTENT_TYPE'])) : '';

switch ($contentType) {
    case 'application/json':
        // get RAW post data
        $payload = trim(file_get_contents('php://input'));
        break;

    case '':
        // get payload
        $payload = $_POST['payload'] ?? '';
        break;

    default:
        error_log($logBase . ' - FAILED - unknown content type - ' . $contentType);
        http_response_code(401);
        exit();
}

// check payload exists
if (empty($payload)) {
    error_log($logBase . ' - FAILED - no payload');
    http_response_code(401);
    exit();
}

// get header signature
$headerSignature = isset($_SERVER['HTTP_X_GITEA_SIGNATURE']) ? $_SERVER['HTTP_X_GITEA_SIGNATURE'] : '';

if (empty($headerSignature)) {
    error_log($logBase . ' - FAILED - header signature missing');
    http_response_code(401);
    exit();
}

// calculate payload signature
$payloadSignature = hash_hmac('sha256', $payload, _KEY, false);

// check payload signature against header signature
if ($headerSignature !== $payloadSignature) {
    error_log($logBase . ' - FAILED - payload signature');
    http_response_code(401);
    exit();
}

// convert json to array
$decoded = json_decode($payload, true);

// check for json decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log($logBase . ' - FAILED - json decode - ' . json_last_error());
    //file_put_contents('/var/tmp/git-debug.log', var_export($payload, true));
    http_response_code(401);
    exit();
}

// prepare command
$command = "cd $path;";

if ($section === 'site') {
    // pull and run composer
    $command .= '/usr/bin/git pull 2>&1;';
    $command .= '/usr/bin/composer install --no-interaction --no-dev 2>&1;';
} elseif ($section === 'store') {
    // pull, run composer then php artisan
    // adjust to your needs
    $command .= '/usr/bin/git pull 2>&1;';
    $command .= '/usr/bin/composer install --no-interaction --no-dev 2>&1;';
    $command .= 'php artisan optimize:clear 2>&1;';
    $command .= 'php artisan migrate --force 2>&1;';
} else {
    // just pull
    $command .= '/usr/bin/git pull;';
}

// execute commands
exec($command, $output, $status);

// save log
$dir = _LOGPATH;

if (!file_exists($dir)) {
    if (!mkdir($dir)) {
        error_log($logBase . ' - FAILED - create dir');
    }
}

if (!file_put_contents($dir . date('Ymd-H:i:s') . '.log', $command . "\n\n" . print_r($output, true))) {
    error_log($logBase . ' - FAILED - save log');
}

$outputStr = '';

foreach ($output as $str) {
    $outputStr .= $str . "\n";
}

// check command return code
if ($status !== 0) {
    header('HTTP/1.0 409 Conflict');
    error_log($logBase . ' - FAILED - command return code - make sure server git remote -v contains password and git branch --set-upstream-to=origin/master master - ' . $outputStr);
    exit();
}

error_log($logBase . ' - OK - ' . $outputStr);
