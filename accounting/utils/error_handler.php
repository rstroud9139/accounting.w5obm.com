<?php
// /accounting/utils/error_handler.php
    /**
     * Error Handler
     * Functions for handling and logging errors
     */

    /**
     * Log an error message to the error log file.
     */
    function log_error($message)
    {
        $log_dir = __DIR__ . '/../../logs';

        // Create logs directory if it doesn't exist
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $log_file = $log_dir . '/error_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message" . PHP_EOL;

        return file_put_contents($log_file, $log_message, FILE_APPEND);
    }

    /**
     * Display an error message to the user.
     */
    function display_error($message, $is_critical = false)
    {
        $class = $is_critical ? 'alert-danger' : 'alert-warning';
        echo "<div class='alert $class'>$message</div>";

        // Log critical errors
        if ($is_critical) {
            log_error($message);
        }
    }

    /**
     * Custom error handler function.
     */
    function custom_error_handler($errno, $errstr, $errfile, $errline)
    {
        $error_message = "Error [$errno]: $errstr in $errfile on line $errline";

        // Log all errors
        log_error($error_message);

        // Only display certain errors to the user
        if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            display_error("A critical error occurred. Please contact the administrator.", true);
            // For critical errors, terminate script execution
            exit(1);
        }

        // Let PHP handle the error normally
        return false;
    }

    /**
     * Custom exception handler function.
     */
    function custom_exception_handler($exception)
    {
        $error_message = "Exception: " . $exception->getMessage() .
            " in " . $exception->getFile() .
            " on line " . $exception->getLine();

        // Log the exception
        log_error($error_message);

        // Display user-friendly message
        display_error("An unexpected error occurred. Please try again or contact the administrator.", true);

        exit(1);
    }

    /**
     * Register custom error and exception handlers.
     */
    function register_error_handlers()
    {
        set_error_handler('custom_error_handler');
        set_exception_handler('custom_exception_handler');
    }

    /**
     * Initialize the error handling system.
     */
    function init_error_handling()
    {
        register_error_handlers();

        // Define a shutdown function to catch fatal errors
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                log_error("Fatal Error: " . $error['message'] .
                    " in " . $error['file'] .
                    " on line " . $error['line']);
            }
        });
    }

    // Initialize error handling when this file is included
    init_error_handling();
