<?php
/**
 * W5OBM Environment Configuration Example
 * Example file with placeholder values for version control
 * Copy this file to .env and fill in the actual values
 * 
 * Version: 1.0.0
 * Date: August 25, 2025
 */

// Database Connection Settings
define('DB_HOST', 'localhost');      // Database host address
define('DB_NAME', 'database_name');  // Database name
define('DB_USER', 'database_user');  // Database username
define('DB_PASS', 'database_password'); // Database password

// Email Configuration
define('MAIL_HOST', 'mail.example.com');     // SMTP host
define('MAIL_PORT', 587);                    // SMTP port
define('MAIL_USERNAME', 'user@example.com'); // SMTP username
define('MAIL_PASSWORD', 'email_password');   // SMTP password
define('MAIL_ENCRYPTION', 'tls');            // SMTP encryption
define('MAIL_FROM_ADDRESS', 'user@example.com'); // Default from address
define('MAIL_FROM_NAME', 'Your Name');       // Default from name

// PayPal Configuration
define('PAYPAL_CLIENT_ID', 'your_paypal_client_id'); // PayPal client ID
define('PAYPAL_CLIENT_SECRET', 'your_paypal_client_secret'); // PayPal client secret
define('PAYPAL_SANDBOX', true); // Use PayPal sandbox (true) or production (false)

// QRZ.com API Configuration
define('QRZ_USERNAME', 'your_qrz_username'); // QRZ.com username
define('QRZ_PASSWORD', 'your_qrz_password'); // QRZ.com password

// Google Maps API Key
define('GOOGLE_MAPS_API_KEY', 'your_google_maps_api_key');

// reCAPTCHA Configuration
define('RECAPTCHA_SITE_KEY', 'your_recaptcha_site_key');
define('RECAPTCHA_SECRET_KEY', 'your_recaptcha_secret_key');

// Application Settings
define('APP_ENV', 'development'); // Environment (development, staging, production)
define('APP_DEBUG', true); // Enable debugging output
define('APP_URL', 'https://example.com'); // Application URL
define('APP_TIMEZONE', 'UTC'); // Application timezone
define('APP_LOCALE', 'en_US'); // Application locale

// Security Settings
define('ENCRYPTION_KEY', 'random_encryption_key'); // Key for encryption functions
define('PASSWORD_PEPPER', 'random_password_pepper'); // Additional security for password hashing
define('SESSION_SECRET', 'random_session_secret'); // Session security key
define('JWT_SECRET', 'random_jwt_secret'); // JWT token secret

// Error Handling Settings
define('LOG_ERRORS', true); // Log errors to file
define('ERROR_LOG_PATH', __DIR__ . '/../logs/error.log'); // Path to error log file
define('SHOW_DB_ERRORS', true); // Show database errors (development only)

// File Upload Settings
define('UPLOAD_MAX_SIZE', 10 * 1024 * 1024); // Maximum file size (10MB)
define('UPLOAD_ALLOWED_TYPES', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt'); // Allowed file types
define('UPLOAD_PATH', __DIR__ . '/../uploads'); // Path to upload directory

// API Throttling Settings
define('API_RATE_LIMIT', 100); // Maximum number of requests per minute
define('API_RATE_WINDOW', 60); // Time window in seconds

// Social Media Integration
define('FACEBOOK_APP_ID', 'your_facebook_app_id');
define('FACEBOOK_APP_SECRET', 'your_facebook_app_secret');
define('TWITTER_API_KEY', 'your_twitter_api_key');
define('TWITTER_API_SECRET', 'your_twitter_api_secret');

// Website Features Control
define('ENABLE_MARKETPLACE', true); // Enable/disable marketplace feature
define('ENABLE_MEMBER_DIRECTORY', true); // Enable/disable member directory
define('ENABLE_EVENTS_CALENDAR', true); // Enable/disable events calendar
define('ENABLE_PHOTO_GALLERY', true); // Enable/disable photo gallery
define('ENABLE_NEWSLETTER', true); // Enable/disable newsletter feature
define('ENABLE_RAFFLE', true); // Enable/disable raffle feature
define('ENABLE_SURVEY', true); // Enable/disable survey feature
define('ENABLE_CONTEST', true); // Enable/disable contest feature

// Set timezone
date_default_timezone_set(APP_TIMEZONE);
