<?php
// get_links.php - Returns HTML for the links table
session_start();
require_once '../database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user links
$linksSQL = "SELECT * FROM links WHERE user_id = ? ORDER BY display_order";
$stmt = $conn->prepare($linksSQL);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$links = $stmt->get_result();

if ($links->num_rows > 0):
    while ($link = $links->fetch_assoc()): ?>
        <tr data-id="<?php echo $link['link_id']; ?>">
            <td>
                <div class="d-flex align-items-center">
                    <div class="platform-icon">
                        <i class="fab fa-<?php echo $link['platform']; ?>"></i>
                    </div>
                    <?php echo ucfirst($link['platform']); ?>
                </div>
            </td>
            <td><?php echo htmlspecialchars($link['display_text']); ?></td>
            <td>
                <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank"
                    class="text-truncate d-inline-block" style="max-width: 150px;">
                    <?php echo htmlspecialchars($link['url']); ?>
                </a>
            </td>
            <td>
                <button type="button" class="btn btn-sm btn-danger delete-link" 
                    onclick="deleteLink(<?php echo $link['link_id']; ?>)">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    <?php endwhile; 
else: ?>
    <tr>
        <td colspan="4">
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-link-slash"></i>
                </div>
                <h5>No links added yet</h5>
                <p class="text-muted">Start adding your social links using the form above!</p>
            </div>
        </td>
    </tr>
<?php endif; ?>