<?php
/**
 * Log a custom message.
 */
function log_message($message) {
    $file = '/w5obm.com/logs/system.log';
    file_put_contents($file, "[" . date('Y-m-d H:i:s') . "] $message" . PHP_EOL, FILE_APPEND);
}

/**
 * Log an error message.
 */
function log_error($error) {
    $file = '/w5obm.com/logs/error.log';
    file_put_contents($file, "[" . date('Y-m-d H:i:s') . "] ERROR: $error" . PHP_EOL, FILE_APPEND);
}
?>
