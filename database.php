<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sociallinks";

$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// // Create DB
// if (!$conn->query("CREATE DATABASE IF NOT EXISTS $dbname")) {
//     die("Database creation failed: " . $conn->error);
// }

$conn->select_db($dbname);

// // Create users table
// $createUsersTableSQL = "CREATE TABLE IF NOT EXISTS users (
//     user_id INT AUTO_INCREMENT PRIMARY KEY,
//     username VARCHAR(50) UNIQUE NOT NULL,
//     email VARCHAR(100) UNIQUE NOT NULL,
//     password VARCHAR(255) NOT NULL,
//     profile_image VARCHAR(255),
//     bio TEXT,
//     theme VARCHAR(50) DEFAULT 'default',
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
// )";

// if (!$conn->query($createUsersTableSQL)) {
//     die("Users table creation failed: " . $conn->error);
// }

// // Create links table=
// $createLinksTableSQL = "CREATE TABLE IF NOT EXISTS links (
//     link_id INT AUTO_INCREMENT PRIMARY KEY,
//     user_id INT NOT NULL,
//     platform VARCHAR(50) NOT NULL,
//     url VARCHAR(255) NOT NULL,
//     display_text VARCHAR(100),
//     icon VARCHAR(50),
//     display_order INT DEFAULT 0,
//     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
//     FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
// )";

// if (!$conn->query($createLinksTableSQL)) {
//     die("Links table creation failed: " . $conn->error);
// }

// echo "Database and tables setup complete successfully.";
?>