<?php
// track_click.php - Track link clicks for analytics
require_once 'database.php';

if (isset($_GET['link_id']) && isset($_GET['user_id'])) {
    $link_id = (int)$_GET['link_id'];
    $user_id = (int)$_GET['user_id'];
    
    // Get the original URL to redirect to
    $linkSQL = "SELECT url FROM links WHERE link_id = ? AND user_id = ?";
    $stmt = $conn->prepare($linkSQL);
    $stmt->bind_param("ii", $link_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $link = $result->fetch_assoc();
        $redirect_url = $link['url'];
        
        // Record the click
        $visitor_ip = $_SERVER['REMOTE_ADDR'];
        $clickSQL = "INSERT INTO link_clicks (link_id, user_id, visitor_ip, click_time) 
                    VALUES (?, ?, ?, NOW())";
        $stmt = $conn->prepare($clickSQL);
        $stmt->bind_param("iis", $link_id, $user_id, $visitor_ip);
        $stmt->execute();
        
        // Redirect to the original URL
        header("Location: " . $redirect_url);
        exit;
    }
}

// If we get here, something went wrong
header("Location: index.php");
exit;