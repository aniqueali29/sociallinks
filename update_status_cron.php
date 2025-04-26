<?php
// update_status_cron.php - Run via cron job to update user status
require_once 'database.php';

// Update users who were active within the last 3 hours to 'away'
$updateAwaySQL = "UPDATE users 
                 SET online_status = 'away' 
                 WHERE last_activity < DATE_SUB(NOW(), INTERVAL 10 MINUTE) 
                 AND last_activity > DATE_SUB(NOW(), INTERVAL 3 HOUR)
                 AND online_status = 'online'";
$conn->query($updateAwaySQL);

// Update users who were inactive for more than 3 hours to 'offline'
$updateOfflineSQL = "UPDATE users 
                    SET online_status = 'offline' 
                    WHERE last_activity < DATE_SUB(NOW(), INTERVAL 3 HOUR) 
                    AND online_status IN ('online', 'away')";
$conn->query($updateOfflineSQL);

echo "Status update completed: " . date('Y-m-d H:i:s');