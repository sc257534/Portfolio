<?php
// Start the session
session_start();

// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// This script is called by JavaScript, so no visible output is needed.
// It will simply end the server-side session.
?>