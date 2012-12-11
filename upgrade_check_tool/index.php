<?php

function log_write($level, $message)
{
    echo strtoupper($level) . ": " . $message . "<br />";
}

function glob_recursive($pattern, $flags = 0)
{
    $whitelist = array(
        './cache',
    );
    $files = glob($pattern, $flags);

    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        if (!in_array($dir, $whitelist)) {
            $files = array_merge($files, glob_recursive($dir.'/'.basename($pattern), $flags));
        }
    }

    return $files;
}

function filterSugarOwned($files, $bad_files = array())
{
    $sugar_files = array();
    if (!isset($_GET['include_sugar_owned'])) {
        include 'files.md5';
        foreach ($md5_string as $f => $hash) {
            if (strpos($f, '/', 2) === false) {
                $sugar_files[] = substr($f, 2);
            } else {
                $sugar_files[] = $f;
            }
        }
    }

    return array_merge(array_diff($files, $sugar_files), array_intersect($files, $bad_files));
}

// PHP version check
log_write('info', 'Checking PHP version. Sugar 7 requires at least PHP 5.3.0');
if (version_compare(PHP_VERSION, '5.3.0') < 0) {
    log_write('critical', 'Upgrade your PHP installation. You\'re currently running PHP '.PHP_VERSION);
} else {
    log_write('info', 'Your version of PHP (' . PHP_VERSION .  ') is fine.');
}

// File hash validity
log_write('info', 'Checking for presence of files.md5 file.');
$file_hashes_exist = file_exists("files.md5");
if ($file_hashes_exist) {
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

// Custom themes
if ($file_hashes_exist) {
    $theme_dirs = array_merge(glob('themes/*'), glob('custom/themes/*'));
    $file_names = array_keys($md5_string);

    // First we get the valid theme files.
    $valid_files = array();
    foreach ($file_names as $file) {
        $ret = strpos($file, './themes/');
        if ($ret === false) {
            $ret = strpos($file, './custom/themes/');
        }
        if ($ret !== false) {
            $valid_files[] = $file;
        }
    }

    // Then we filter out the directories.
    $valid_dirs = array();
    foreach ($valid_files as $val) {
        $str = '/themes/';
        $start = strpos($val, $str) + strlen($str);
        $length = strpos($val, '/', $start);
        // The 2s get rid of the starting dot and slash.
        $ret = substr($val, 2, $length - 2);
        $valid_dirs[] = $ret;
    }
    $valid_dirs = array_unique($valid_dirs);

    foreach ($theme_dirs as $dir) {
        if (!in_array($dir, $valid_dirs)) {
            log_write('warn', 'Theme ' . $dir . ' is a 3rd party theme.');
        }
    }
} else {
    log_write('error', 'Cannot run custom theme check as files.md5 is missing.');
}

// TODO: Custom modules that do "weird things"
// TODO: Custom views
// TODO: Custom entrypoints

// Checks for echo/die/exit/print/var_dump/print_r/ob*
// Warn on XTemplate usage.
$php_files = filterSugarOwned(glob_recursive("*.php"), $bad_files);
foreach ($php_files as $phpfile) {
    $contents = file_get_contents($phpfile);
    $tokens = token_get_all($contents);
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] == T_INLINE_HTML) {
                log_write('warn', 'Inline HTML found in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_ECHO) {
                log_write('error', 'Found "echo" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_PRINT) {
                log_write('error', 'Found "print" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_EXIT) {
                log_write('error', 'Found "die/exit" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_STRING && $token[1] == 'print_r') {
                log_write('error', 'Found "print_r" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_STRING && $token[1] == 'var_dump') {
                log_write('error', 'Found "var_dump" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_STRING && strpos($token[1], 'ob_') === 0) {
                log_write('error', 'Found output buffering (' . $token[1] . ') in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_STRING && $token[1] == 'XTemplate') {
                log_write('error', 'Found XTemplate usage in ' . $phpfile . ' on line '. $token[2]);
            }
        }
    }
    ob_flush();
}

// TODO: JQuery not owned by Sugar.
// TODO: Custom JS libraries.

// Log4PHP
if (is_dir('log4php')) {
    log_write('warn', 'Log4PHP directory still exists.');
}

// Warn on {php} inside Smarty.
$tpl_files = filterSugarOwned(glob_recursive("*.tpl"), $bad_files);
foreach ($tpl_files as $tplfile) {
    $contents = file_get_contents($tplfile);
    if (strpos($contents, "{php}") !== FALSE) {
        log_write('error', 'Template file ' . $tplfile . ' uses Smarty 2 PHP tags.');
    }
}

$xtpl_files = filterSugarOwned(glob_recursive("*.html"), $bad_files);
foreach ($xtpl_files as $xtplfile) {
    $contents = file_get_contents($xtplfile);
    if (strpos($contents, "BEGIN:") !== FALSE) {
        log_write('error', 'Template file ' . $xtplfile . ' is an XTemplate file.');
    }
}
