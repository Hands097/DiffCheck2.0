<?php
session_start();

// 1. Empty all the session variables
$_SESSION = array();

// 2. Destroy the session on the server
session_destroy();

// 3. Send them back to login
header("Location: index.php");
exit();
?>