<?php

// Sanity checks.
if (!extension_loaded('tokenizer')) {
    echo 'Tokenizer extension is not loaded. Please enable the extension and try again.';
    // Takes us out of the current file without 'die'ing - see view.classic.php.
    return;
}

function logWrite($level, $message)
{
    echo strtoupper($level) . ": " . $message . "<br />";
}

function globRecursive($pattern, $flags = 0)
{
    $whitelist = array(
        './cache',
    );
    $files = glob($pattern, $flags);

    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        if (!in_array($dir, $whitelist)) {
            $files = array_merge($files, globRecursive($dir.'/'.basename($pattern), $flags));
        }
    }

    return $files;
}

function filterSugarOwned($files, $bad_files = array(), $override_get = false)
{
    $sugar_files = array();
    if (!isset($_GET['include_sugar_owned']) || $override_get) {
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
logWrite('info', 'Checking PHP version. Sugar 7 requires at least PHP 5.3.0');
if (version_compare(PHP_VERSION, '5.3.0') < 0) {
    logWrite('critical', 'Upgrade your PHP installation. You\'re currently running PHP '.PHP_VERSION);
} else {
    logWrite('info', 'Your version of PHP (' . PHP_VERSION .  ') is fine.');
}

// File hash validity
logWrite('info', 'Checking for presence of files.md5 file.');
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
        logWrite('warn', 'File ' . realpath($file) . ' is not how it was shipped.');
    }
} else {
    logWrite('error', 'Could not check for file validity: files.md5 is missing.');
    // Do not execute the rest if we cannot validate existing files.
    return;
}

// Custom themes
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
        logWrite('warn', 'Theme ' . $dir . ' is a 3rd party theme.');
    }
}

// Modules that have customCode in vardefs.
$def_files = filterSugarOwned(globRecursive("*defs.php"), $bad_files);
foreach ($def_files as $file) {
    $contents = file_get_contents($file);
    if (strpos($contents, 'customCode') !== false) {
        logWrite('warn', 'Custom code found in vardef ' . $file . '.');
    }
}

// Custom views
$view_files = filterSugarOwned(globRecursive("view.*.php"), $bad_files);
foreach ($view_files as $file) {
    logWrite('warn', 'Custom view file found at ' . $file . '.');
}

// Custom entrypoints
$php_files = filterSugarOwned(globRecursive("*.php"), $bad_files, true);
foreach ($php_files as $phpfile) {
    $contents = file_get_contents($phpfile);
    $tokens = token_get_all($contents);
    $last_token = null;
    $entrypointStrings = array('"sugarEntry"', "'sugarEntry");
    foreach ($tokens as $token) {
        if (is_array($token) && $token[0] != T_WHITESPACE) {
            if ($last_token[0] == T_STRING && $last_token[1] == 'define') {
                if ($token[0] == T_CONSTANT_ENCAPSED_STRING) {
                    // Encapsed string returns the surrounding quotes as well.
                    if (in_array($token[1], $entrypointStrings)) {
                        logWrite('warn', 'Custom entrypoint found in ' . $phpfile . '.');
                    }
                }
            }
            $last_token = $token;
        }
    }
}

// Checks for echo/die/exit/print/var_dump/print_r/ob*
// Warn on XTemplate usage.
$php_files = filterSugarOwned(globRecursive("*.php"), $bad_files);
foreach ($php_files as $phpfile) {
    $contents = file_get_contents($phpfile);
    $tokens = token_get_all($contents);
    foreach ($tokens as $token) {
        if (is_array($token)) {
            if ($token[0] == T_INLINE_HTML) {
                logWrite('warn', 'Inline HTML found in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_ECHO) {
                logWrite('error', 'Found "echo" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_PRINT) {
                logWrite('error', 'Found "print" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_EXIT) {
                logWrite('error', 'Found "die/exit" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_STRING && $token[1] == 'print_r') {
                logWrite('error', 'Found "print_r" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_STRING && $token[1] == 'var_dump') {
                logWrite('error', 'Found "var_dump" in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_STRING && strpos($token[1], 'ob_') === 0) {
                logWrite('error', 'Found output buffering (' . $token[1] . ') in ' . $phpfile . ' on line '. $token[2]);
            } elseif ($token[0] == T_STRING && $token[1] == 'XTemplate') {
                logWrite('error', 'Found XTemplate usage in ' . $phpfile . ' on line '. $token[2]);
            }
        }
    }
    ob_flush();
}

// JQuery not owned by Sugar.
// Custom JS libraries.
$js_files = filterSugarOwned(globRecursive("*.js"), $bad_files);
foreach ($js_files as $js_file) {
    if (strpos(strtolower($js_file), 'jquery')) {
        logWrite('error', 'Custom jQuery code found in ' . $js_file . '.');
    } else {
        logWrite('warn', 'Custom javascript code found in ' . $js_file . '.');
    }
}

// Log4PHP
if (is_dir('log4php')) {
    logWrite('warn', 'Log4PHP directory still exists.');
}

// Warn on {php} inside Smarty.
$tpl_files = filterSugarOwned(globRecursive("*.tpl"), $bad_files);
foreach ($tpl_files as $tplfile) {
    $contents = file_get_contents($tplfile);
    if (strpos($contents, "{php}") !== false) {
        logWrite('error', 'Template file ' . $tplfile . ' uses Smarty 2 PHP tags.');
    }
    if (strpos($contents, "customCode") !== false) {
        logWrite('warn', 'Template file ' . $tplfile . ' uses custom code.');
    }
    if (strpos($contents, "if $") !== false || strpos($contents, "if !$") !== false) {
        logWrite('info', 'Template file ' . $tplfile . ' checks for variable truthiness directly. Consider using empty() to avoid notices.');
    }
}

$xtpl_files = filterSugarOwned(globRecursive("*.html"), $bad_files);
foreach ($xtpl_files as $xtplfile) {
    $contents = file_get_contents($xtplfile);
    if (strpos($contents, "BEGIN:") !== false) {
        logWrite('error', 'Template file ' . $xtplfile . ' is an XTemplate file.');
    }
}
