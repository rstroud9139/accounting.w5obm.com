<?php
/**
 * Error Handler
 * Custom error logging and display management.
 */

/**
 * Log errors to a specific file.
 *
 * @param string $message Error message to log.
 */
function log_error($message) {
    $file = '../logs/error_log.txt'; // Update path as needed
    $formatted_message = date('[Y-m-d H:i:s]') . " $message" . PHP_EOL;
    file_put_contents($file, $formatted_message, FILE_APPEND | LOCK_EX);
}

/**
 * Display a custom error message to the user.
 *
 * @param string $message The error message to display.
 */
function display_error($message) {
    echo "<div class='alert alert-danger'>$message</div>";
}
?>
