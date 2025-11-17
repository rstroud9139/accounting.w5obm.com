 <!-- /accounting/utils/validate_input.php -->
 <?php
    /**
     * Input Validation
     * Functions for validating and sanitizing user input
     */

    /**
     * Sanitize a general input string.
     */
    function validate_input($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }

    /**
     * Validate and sanitize an email address.
     */
    function validate_email($email)
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        return false;
    }

    /**
     * Validate a date string.
     */
    function validate_date($date, $format = 'Y-m-d')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    /**
     * Validate a numeric value.
     */
    function validate_numeric($value)
    {
        return is_numeric($value);
    }

    /**
     * Validate a decimal value.
     */
    function validate_decimal($value)
    {
        return preg_match('/^-?\d+(\.\d+)?$/', $value);
    }

    /**
     * Validate a phone number.
     */
    function validate_phone($phone)
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phone);

        // Check if it has a valid length (most countries between 8 and 15 digits)
        if (strlen($phone) >= 8 && strlen($phone) <= 15) {
            return $phone;
        }

        return false;
    }

    /**
     * Validate an integer value.
     */
    function validate_integer($value)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    /**
     * Sanitize and validate against SQL injection.
     */
    function sanitize_sql($value, $conn = null)
    {
        if ($conn === null) {
            global $conn;
        }

        if ($conn) {
            return $conn->real_escape_string($value);
        }

        // Fallback if no connection available
        return addslashes($value);
    }

    /**
     * Validate a username.
     */
    function validate_username($username)
    {
        // Allow letters, numbers, underscores and hyphens, 3-20 characters
        return preg_match('/^[a-zA-Z0-9_-]{3,20}$/', $username);
    }

    /**
     * Validate a password strength.
     */
    function validate_password_strength($password)
    {
        // Minimum 8 characters, at least one uppercase letter, one lowercase letter, and one number
        return strlen($password) >= 8 &&
            preg_match('/[A-Z]/', $password) &&
            preg_match('/[a-z]/', $password) &&
            preg_match('/[0-9]/', $password);
    }

    /**
     * Sanitize an array of inputs.
     */
    function sanitize_array($array)
    {
        $sanitized = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = sanitize_array($value);
            } else {
                $sanitized[$key] = validate_input($value);
            }
        }
        return $sanitized;
    }
