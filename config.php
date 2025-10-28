<?php
// config.php — Database connection file using PDO

$host = 'localhost';         // Your database host (usually localhost)
$dbname = 'crud';            // Your database name
$username = 'root';          // Your MySQL username (default in XAMPP)
$password = '';              // Your MySQL password (empty by default in XAMPP)

try {
    // Create PDO connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);

    // Set PDO attributes for better error handling and security
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // If connection fails, stop script and show error
    die("❌ Database connection failed: " . $e->getMessage());
}
?>
