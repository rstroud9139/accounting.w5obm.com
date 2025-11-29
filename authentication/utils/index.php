<?php
/**
 * Directory Protection - W5OBM Amateur Radio Club
 * File: /authentication/utils/index.php
 * Purpose: Prevent directory browsing and unauthorized access
 */

// Redirect to main site
header('Location: ../../index.php');
exit();
?>