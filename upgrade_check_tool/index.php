<?php

function log_write($level, $message)
{
    echo strtoupper($level) . ": " . $message . "</br>";
}

// PHP version check
log_write('info', 'Checking PHP version. Sugar 7 requires at least PHP 5.3.0');
if (version_compare(PHP_VERSION, '5.3.0') < 0) {
    log_write('critical', 'Upgrade your PHP installation. You\'re currently running PHP '.PHP_VERSION);
} else {
    log_write('info', 'Your version of PHP (' . PHP_VERSION .  ') is fine.');
}

if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
    log_write('warn', 'However, SugarCRM does not support PHP 5.4.0 or later releases.');
}

// File hash validity
log_write('info', 'Checking for presence of files.md5 file.');
if (file_exists("files.md5")) {
    include 'files.md5';
    $bad_files = array();
    foreach ($md5_string as $file => $hash) {
        // Obviously, config.php is not going to be stock.
        if ($file != "./config.php") {
            if ($hash !== md5_file($file)) {
                $bad_files[] = $file;
            }
        }
    }
    foreach ($bad_files as $file) {
        log_write('warn', 'File ' . realpath($file) . ' is not how it was shipped.');
    }
} else {
    log_write('error', 'Could not check for file validity: files.md5 is missing.');
}

// TODO: Custom themes
// TODO: Custom modules that do "weird things"
// TODO: Custom views
// TODO: Custom entrypoints
// TODO: Checks for echo/die/exit/print/var_dump/print_r/ob*
// TODO: JQuery not owned by Sugar.
// TODO: Custom JS libraries.
// TODO: Log4PHP
