<?php
session_start();
$_SESSION = array(); // Clear application array memory 
session_destroy();   // Terminate the storage structure entirely
header("Location: login.php");
exit();
?>