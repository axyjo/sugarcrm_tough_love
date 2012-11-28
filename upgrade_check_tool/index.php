<?php

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
    var_dump($bad_files);
} else {
    // Throw error due to not being able to check validity of base files.
}
