<?php

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

function log_write($level, $message)
{
    echo strtoupper($level) . ": " . $message . "</br>";
}
