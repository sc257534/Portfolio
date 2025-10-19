<?php
/**
 * Database configuration file.
 * * This file contains the credentials for the database connection.
 * It's recommended to use environment variables for sensitive data in a production environment
 * instead of hardcoding them directly in the file.
 */

// --- Database Credentials ---
// It is strongly advised to move these to a more secure location,
// such as environment variables, for production systems.
define('DB_SERVER', 'Your Host Name');
define('DB_USERNAME', 'Your UserName');
define('DB_PASSWORD', 'Password'); // WARNING: Hardcoding passwords is a security risk.
define('DB_NAME', 'portfolio');

// --- Establish Database Connection ---

// Suppress default PHP errors to handle them manually.
mysqli_report(MYSQLI_REPORT_STRICT);

try {
    // Attempt to connect to the MySQL database using the defined credentials.
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    // Set the character set to utf8mb4 to support a wide range of characters, including emojis.
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // If the connection fails, terminate the script and provide a user-friendly error message.
    // In a production environment, you might log the detailed error ($e->getMessage()) instead of displaying it.
    die("ERROR: Unable to connect to the database. Please try again later.");
}

// At this point, the $conn variable can be used throughout your application
// to interact with the database.

?>
