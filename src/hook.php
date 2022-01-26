<?php

declare(strict_types=1);

/**
 * Git pull script
 * @author 8ctopus
 * @note When called the script automatically git pulls
 */

// secret key
define('_KEY', 'SECRET');

// path to repository to pull
define('_REPOPATH', $_SERVER['DOCUMENT_ROOT'] . '/../');

// path to logs
define('_LOGPATH', $_SERVER['DOCUMENT_ROOT'] . '/../logs/git-hook/');

$log_base = 'git hook - ';

// get section to pull
$section = @$_GET['section'];

// check section
if (empty($section)) {
    error_log($log_base . ' - FAILED - no section');
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

// add section to env name
$log_base .= $section;

switch ($section) {
    case 'site':
        $path = _REPOPATH . 'public_html';
        break;

    case 'store':
        $path = _REPOPATH . 'store';
        break;

    default:
        error_log($log_base . ' - FAILED - unknown section - ' . $section);
        header('HTTP/1.0 401 Unauthorized');
        exit();
}

// check for POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log($log_base . ' - FAILED - not POST - ' . $_SERVER['REQUEST_METHOD']);
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

// get content type
$content_type = isset($_SERVER['CONTENT_TYPE']) ? mb_strtolower(trim($_SERVER['CONTENT_TYPE'])) : '';

switch ($content_type) {
    case 'application/json':
        // get RAW post data
        $payload = trim(file_get_contents('php://input'));
        break;

    case '':
        // get payload
        $payload = @$_POST['payload'];
        break;

    default:
        error_log($log_base . ' - FAILED - unknown content type - ' . $content_type);
        header('HTTP/1.0 401 Unauthorized');
        exit();
}

// check payload exists
if (empty($payload)) {
    error_log($log_base . ' - FAILED - no payload');
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

// get header signature
$header_signature = isset($_SERVER['HTTP_X_GITEA_SIGNATURE']) ? $_SERVER['HTTP_X_GITEA_SIGNATURE'] : '';

if (empty($header_signature)) {
    error_log($log_base . ' - FAILED - header signature missing');
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

// calculate payload signature
$payload_signature = hash_hmac('sha256', $payload, _KEY, false);

// check payload signature against header signature
if ($header_signature !== $payload_signature) {
    error_log($log_base . ' - FAILED - payload signature');
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

// convert json to array
$decoded = json_decode($payload, true);

// check for json decode errors
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log($log_base . ' - FAILED - json decode - ' . json_last_error());
    //file_put_contents('/var/tmp/git-debug.log', var_export($payload, true));
    header('HTTP/1.0 401 Unauthorized');
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
        error_log($log_base . ' - FAILED - create dir');
    }
}

if (!file_put_contents($dir . date('Ymd-H:i:s') . '.log', $command . "\n\n" . print_r($output, true))) {
    error_log($log_base . ' - FAILED - save log');
}

$output_str = '';

foreach ($output as $str) {
    $output_str .= $str . "\n";
}

// check command return code
if ($status !== 0) {
    header('HTTP/1.0 409 Conflict');
    error_log($log_base . ' - FAILED - command return code - make sure server git remote -v contains password and git branch --set-upstream-to=origin/master master - ' . $output_str);
    exit();
}

error_log($log_base . ' - OK - ' . $output_str);
