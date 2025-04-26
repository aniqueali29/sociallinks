       <!-- QR Code Modal -->
       <div class="modal fade qr-modal" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel"
           aria-hidden="true">
           <div class="modal-dialog modal-dialog-centered">
               <div class="modal-content">
                   <div class="modal-header">
                       <h5 class="modal-title" id="qrCodeModalLabel">My QR Code</h5>
                       <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                   </div>
                   <div class="modal-body">
                       <p class="text-center mb-4">Scan this QR code to access your profile directly.</p>
                       <div class="qr-code-container">
                           <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" class="img-fluid">
                       </div>
                       <p class="text-center mt-4 text-muted">Share your profile with anyone by showing them this QR
                           code.
                       </p>
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                       <a href="<?php echo $qrCodeUrl; ?>" class="btn btn-primary" download="sociallinks-qr.png">
                           <i class="fas fa-download me-2"></i> Download QR Code
                       </a>
                   </div>
               </div>
           </div>
       </div>

       <!-- Username Change Modal -->
       <div class="modal fade" id="usernameChangeModal" tabindex="-1" aria-labelledby="usernameChangeModalLabel"
           aria-hidden="true">
           <div class="modal-dialog modal-dialog-centered">
               <div class="modal-content">
                   <div class="modal-header bg-primary text-white">
                       <h5 class="modal-title" id="usernameChangeModalLabel">Change Username</h5>
                       <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                           aria-label="Close"></button>
                   </div>
                   <div class="modal-body">
                       <?php if (isset($_GET['username_updated'])): ?>
                       <div class="alert alert-success">
                           <i class="fas fa-check-circle me-2"></i> Your username has been updated successfully!
                       </div>
                       <?php endif; ?>

                       <?php if (isset($error_message) && !empty($error_message)): ?>
                       <div class="alert alert-danger">
                           <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                       </div>
                       <?php endif; ?>

                       <form method="POST" action="">
                           <div class="mb-3">
                               <label for="modal_new_username" class="form-label">New Username</label>
                               <input type="text" class="form-control" id="modal_new_username" name="new_username"
                                   value="<?php echo htmlspecialchars($userData['username']); ?>"
                                   <?php echo $canChangeUsername ? '' : 'disabled'; ?>>

                               <?php if ($canChangeUsername): ?>
                               <small class="text-muted">You can change your username once every 14 days.</small>
                               <?php else: ?>
                               <small class="text-danger">You can change your username again in
                                   <?php echo $daysUntilNextChange; ?> days.</small>
                               <?php endif; ?>
                           </div>
                       </form>
                   </div>
                   <div class="modal-footer">
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                       <button type="button" class="btn btn-primary" id="usernameChangeButton">
                           <i class="fas fa-save me-2"></i> Update Username
                       </button>
                   </div>
               </div>
           </div>
       </div>

       <!-- Analytics Details Modal -->
       <div class="modal fade analytics-details-modal" id="analyticsDetailsModal" tabindex="-1"
           aria-labelledby="analyticsDetailsModalLabel" aria-hidden="true">
           <div class="modal-dialog modal-lg modal-dialog-centered">
               <div class="modal-content">
                   <div class="modal-header">
                       <h5 class="modal-title" id="analyticsDetailsModalLabel">Detailed Analytics</h5>
                       <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                   </div>
                   <div class="modal-body">
                       <ul class="nav nav-tabs mb-4" id="analyticsTab" role="tablist">
                           <li class="nav-item" role="presentation">
                               <button class="nav-link active" id="visits-tab" data-bs-toggle="tab"
                                   data-bs-target="#visits" type="button" role="tab" aria-controls="visits"
                                   aria-selected="true">Profile Visits</button>
                           </li>
                           <li class="nav-item" role="presentation">
                               <button class="nav-link" id="geo-tab" data-bs-toggle="tab" data-bs-target="#geo"
                                   type="button" role="tab" aria-controls="geo" aria-selected="false">Geography</button>
                           </li>
                           <li class="nav-item" role="presentation">
                               <button class="nav-link" id="tech-tab" data-bs-toggle="tab" data-bs-target="#tech"
                                   type="button" role="tab" aria-controls="tech" aria-selected="false">Devices &
                                   Browsers</button>
                           </li>
                           <li class="nav-item" role="presentation">
                               <button class="nav-link" id="clicks-tab" data-bs-toggle="tab" data-bs-target="#clicks"
                                   type="button" role="tab" aria-controls="clicks" aria-selected="false">Link
                                   Clicks</button>
                           </li>
                       </ul>
                       <div class="tab-content" id="analyticsTabContent">
                           <!-- Profile Visits Tab -->
                           <div class="tab-pane fade show active" id="visits" role="tabpanel"
                               aria-labelledby="visits-tab">
                               <h6 class="mb-3">Daily Profile Visits (Last 30 Days)</h6>
                               <div class="chart-container">
                                   <canvas id="detailedVisitsChart"></canvas>
                               </div>

                               <h6 class="mt-4 mb-3">Visit Statistics</h6>
                               <div class="row">
                                   <div class="col-md-4 mb-3">
                                       <div class="stat-card">
                                           <h5 class="text-primary">Total Views</h5>
                                           <h3><?php echo $totalVisits; ?></h3>
                                       </div>
                                   </div>
                                   <div class="col-md-4 mb-3">
                                       <div class="stat-card">
                                           <h5 class="text-success">Unique Visitors</h5>
                                           <h3><?php echo $uniqueVisitors; ?></h3>
                                       </div>
                                   </div>
                                   <div class="col-md-4 mb-3">
                                       <div class="stat-card">
                                           <h5 class="text-info">Average Daily Views</h5>
                                           <h3><?php echo count($dates) > 0 ? round($totalVisits / count($dates), 1) : 0; ?>
                                           </h3>
                                       </div>
                                   </div>
                               </div>

                               <h6 class="mt-4 mb-3">Traffic Sources</h6>
                               <div class="table-responsive">
                                   <table class="table">
                                       <thead>
                                           <tr>
                                               <th>Referrer</th>
                                               <th>Visits</th>
                                               <th>Percentage</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php 
                                    if ($referrerData && $referrerData->num_rows > 0) {
                                        while ($row = $referrerData->fetch_assoc()): 
                                            $percentage = ($row['visit_count'] / $totalVisits) * 100;
                                    ?>
                                           <tr>
                                               <td>
                                                   <?php echo $row['referrer'] ? htmlspecialchars($row['referrer']) : 'Direct'; ?>
                                               </td>
                                               <td><?php echo $row['visit_count']; ?></td>
                                               <td>
                                                   <div class="progress">
                                                       <div class="progress-bar" role="progressbar"
                                                           style="width: <?php echo $percentage; ?>%;"
                                                           aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0"
                                                           aria-valuemax="100">
                                                           <?php echo round($percentage, 1); ?>%
                                                       </div>
                                                   </div>
                                               </td>
                                           </tr>
                                           <?php 
                                        endwhile;
                                    } else {
                                    ?>
                                           <tr>
                                               <td colspan="3" class="text-center">No referrer data available</td>
                                           </tr>
                                           <?php } ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>

                           <!-- Geographic Data Tab -->
                           <div class="tab-pane fade" id="geo" role="tabpanel" aria-labelledby="geo-tab">
                               <div class="row">
                                   <div class="col-md-6">
                                       <h6 class="mb-3">Top Countries</h6>
                                       <div class="chart-container">
                                           <canvas id="countryChart"></canvas>
                                       </div>

                                       <div class="table-responsive mt-4">
                                           <table class="table">
                                               <thead>
                                                   <tr>
                                                       <th>Country</th>
                                                       <th>Visits</th>
                                                       <th>Percentage</th>
                                                   </tr>
                                               </thead>
                                               <tbody>
                                                   <?php 
                                            if ($countryData && $countryData->num_rows > 0) {
                                                mysqli_data_seek($countryData, 0);
                                                while ($row = $countryData->fetch_assoc()): 
                                                    $percentage = ($row['visit_count'] / $totalVisits) * 100;
                                            ?>
                                                   <tr>
                                                       <td><?php echo htmlspecialchars($row['country']); ?></td>
                                                       <td><?php echo $row['visit_count']; ?></td>
                                                       <td>
                                                           <div class="progress">
                                                               <div class="progress-bar bg-primary" role="progressbar"
                                                                   style="width: <?php echo $percentage; ?>%;"
                                                                   aria-valuenow="<?php echo $percentage; ?>"
                                                                   aria-valuemin="0" aria-valuemax="100">
                                                                   <?php echo round($percentage, 1); ?>%
                                                               </div>
                                                           </div>
                                                       </td>
                                                   </tr>
                                                   <?php 
                                                endwhile;
                                            } else {
                                            ?>
                                                   <tr>
                                                       <td colspan="3" class="text-center">No country data available
                                                       </td>
                                                   </tr>
                                                   <?php } ?>
                                               </tbody>
                                           </table>
                                       </div>
                                   </div>

                                   <div class="col-md-6">
                                       <h6 class="mb-3">Top Cities</h6>
                                       <div class="chart-container">
                                           <canvas id="cityChart"></canvas>
                                       </div>

                                       <div class="table-responsive mt-4">
                                           <table class="table">
                                               <thead>
                                                   <tr>
                                                       <th>City</th>
                                                       <th>Country</th>
                                                       <th>Visits</th>
                                                   </tr>
                                               </thead>
                                               <tbody>
                                                   <?php 
                                            if ($cityData && $cityData->num_rows > 0) {
                                                mysqli_data_seek($cityData, 0);
                                                while ($row = $cityData->fetch_assoc()): 
                                            ?>
                                                   <tr>
                                                       <td><?php echo htmlspecialchars($row['city']); ?></td>
                                                       <td><?php echo htmlspecialchars($row['country']); ?></td>
                                                       <td>
                                                           <span
                                                               class="badge bg-primary"><?php echo $row['visit_count']; ?></span>
                                                       </td>
                                                   </tr>
                                                   <?php 
                                                endwhile;
                                            } else {
                                            ?>
                                                   <tr>
                                                       <td colspan="3" class="text-center">No city data available</td>
                                                   </tr>
                                                   <?php } ?>
                                               </tbody>
                                           </table>
                                       </div>
                                   </div>
                               </div>
                           </div>

                           <!-- Technology Tab -->
                           <div class="tab-pane fade" id="tech" role="tabpanel" aria-labelledby="tech-tab">
                               <div class="row">
                                   <div class="col-md-6">
                                       <h6 class="mb-3">Device Types</h6>
                                       <div class="chart-container">
                                           <canvas id="deviceChart"></canvas>
                                       </div>

                                       <div class="table-responsive mt-4">
                                           <table class="table">
                                               <thead>
                                                   <tr>
                                                       <th>Device Type</th>
                                                       <th>Visits</th>
                                                       <th>Percentage</th>
                                                   </tr>
                                               </thead>
                                               <tbody>
                                                   <?php 
                                            if ($deviceData && $deviceData->num_rows > 0) {
                                                mysqli_data_seek($deviceData, 0);
                                                while ($row = $deviceData->fetch_assoc()): 
                                                    $percentage = ($row['visit_count'] / $totalVisits) * 100;
                                            ?>
                                                   <tr>
                                                       <td><?php echo htmlspecialchars($row['device_type']); ?></td>
                                                       <td><?php echo $row['visit_count']; ?></td>
                                                       <td>
                                                           <div class="progress">
                                                               <div class="progress-bar bg-success" role="progressbar"
                                                                   style="width: <?php echo $percentage; ?>%;"
                                                                   aria-valuenow="<?php echo $percentage; ?>"
                                                                   aria-valuemin="0" aria-valuemax="100">
                                                                   <?php echo round($percentage, 1); ?>%
                                                               </div>
                                                           </div>
                                                       </td>
                                                   </tr>
                                                   <?php 
                                                endwhile;
                                            } else {
                                            ?>
                                                   <tr>
                                                       <td colspan="3" class="text-center">No device data available</td>
                                                   </tr>
                                                   <?php } ?>
                                               </tbody>
                                           </table>
                                       </div>
                                   </div>

                                   <div class="col-md-6">
                                       <h6 class="mb-3">Browsers</h6>
                                       <div class="chart-container">
                                           <canvas id="browserChart"></canvas>
                                       </div>

                                       <div class="table-responsive mt-4">
                                           <table class="table">
                                               <thead>
                                                   <tr>
                                                       <th>Browser</th>
                                                       <th>Visits</th>
                                                       <th>Percentage</th>
                                                   </tr>
                                               </thead>
                                               <tbody>
                                                   <?php 
                                            if ($browserData && $browserData->num_rows > 0) {
                                                mysqli_data_seek($browserData, 0);
                                                while ($row = $browserData->fetch_assoc()): 
                                                    $percentage = ($row['visit_count'] / $totalVisits) * 100;
                                            ?>
                                                   <tr>
                                                       <td><?php echo htmlspecialchars($row['browser']); ?></td>
                                                       <td><?php echo $row['visit_count']; ?></td>
                                                       <td>
                                                           <div class="progress">
                                                               <div class="progress-bar bg-info" role="progressbar"
                                                                   style="width: <?php echo $percentage; ?>%;"
                                                                   aria-valuenow="<?php echo $percentage; ?>"
                                                                   aria-valuemin="0" aria-valuemax="100">
                                                                   <?php echo round($percentage, 1); ?>%
                                                               </div>
                                                           </div>
                                                       </td>
                                                   </tr>
                                                   <?php 
                                                endwhile;
                                            } else {
                                            ?>
                                                   <tr>
                                                       <td colspan="3" class="text-center">No browser data available
                                                       </td>
                                                   </tr>
                                                   <?php } ?>
                                               </tbody>
                                           </table>
                                       </div>
                                   </div>
                               </div>
                           </div>

                           <!-- Link Clicks Tab (keep your existing one) -->
                           <div class="tab-pane fade" id="clicks" role="tabpanel" aria-labelledby="clicks-tab">
                               <h6 class="mb-3">Link Click Performance</h6>
                               <div class="chart-container">
                                   <canvas id="linkClicksChart"></canvas>
                               </div>

                               <h6 class="mt-4 mb-3">Most Clicked Links</h6>
                               <div class="table-responsive">
                                   <table class="table">
                                       <thead>
                                           <tr>
                                               <th>Platform</th>
                                               <th>Link</th>
                                               <th>Clicks</th>
                                           </tr>
                                       </thead>
                                       <tbody>
                                           <?php
                                    // Reset the link clicks result pointer
                                    if ($linkClicks) {
                                        mysqli_data_seek($linkClicks, 0);
                                        while ($linkClick = $linkClicks->fetch_assoc()): 
                                    ?>
                                           <tr>
                                               <td>
                                                   <div class="d-flex align-items-center">
                                                       <div class="platform-icon">
                                                           <i class="fab fa-<?php echo $linkClick['platform']; ?>"></i>
                                                       </div>
                                                       <?php echo ucfirst($linkClick['platform']); ?>
                                                   </div>
                                               </td>
                                               <td><?php echo htmlspecialchars($linkClick['display_text']); ?></td>
                                               <td>
                                                   <span
                                                       class="badge bg-primary"><?php echo $linkClick['click_count']; ?>
                                                       clicks</span>
                                               </td>
                                           </tr>
                                           <?php 
                                        endwhile;
                                    }
                                    ?>
                                       </tbody>
                                   </table>
                               </div>
                           </div>
                       </div>
                   </div>
                   <div class="modal-footer">
                       <a href="#" class="btn btn-outline-primary me-2">
                           <i class="fas fa-file-export me-1"></i> Export Data
                       </a>
                       <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                   </div>
               </div>
           </div>
       </div>

       <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <a href="index.php" class="footer-logo">Social<span>Links</span></a>
                <div class="footer-links">
                    <a href="#">About Us</a>
                    <a href="#">Features</a>
                    <a href="#">Pricing</a>
                    <a href="#">Support</a>
                    <a href="#">Terms</a>
                    <a href="#">Privacy</a>
                </div>
                <div class="social-links">
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            <div class="copyright">
                <p>© 2025 SocialLinks. All rights reserved.</p>
            </div>
        </div>
    </footer>

       <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
       <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
       <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.min.js"></script>

       <script>
// Initialize analytics charts
document.addEventListener('DOMContentLoaded', function() {
    // Data for charts
    const dates = <?php echo json_encode($dates); ?>;
    const visitCounts = <?php echo json_encode($visitCounts); ?>;

    // Main dashboard visits chart
    const visitsCtx = document.getElementById('visitsChart').getContext('2d');
    new Chart(visitsCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Profile Visits',
                data: visitCounts,
                backgroundColor: 'rgba(108, 99, 255, 0.2)',
                borderColor: 'rgba(108, 99, 255, 1)',
                borderWidth: 2,
                tension: 0.4,
                pointBackgroundColor: 'white',
                pointBorderColor: 'rgba(108, 99, 255, 1)',
                pointBorderWidth: 2,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });

    // Detailed visits chart in modal
    const detailedVisitsCtx = document.getElementById('detailedVisitsChart').getContext('2d');
    new Chart(detailedVisitsCtx, {
        type: 'bar',
        data: {
            labels: dates,
            datasets: [{
                label: 'Daily Visits',
                data: visitCounts,
                backgroundColor: 'rgba(108, 99, 255, 0.7)',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Link clicks chart
    const linkClicksCtx = document.getElementById('linkClicksChart').getContext('2d');

    <?php
            // Prepare data for link clicks chart
            $platforms = [];
            $clickCounts = [];
            
            if ($linkClicks) {
                mysqli_data_seek($linkClicks, 0);
                while ($row = $linkClicks->fetch_assoc()) {
                    $platforms[] = ucfirst($row['platform']);
                    $clickCounts[] = $row['click_count'];
                }
            }
            ?>

    const platforms = <?php echo json_encode($platforms); ?>;
    const clickCounts = <?php echo json_encode($clickCounts); ?>;

    new Chart(linkClicksCtx, {
        type: 'doughnut',
        data: {
            labels: platforms,
            datasets: [{
                data: clickCounts,
                backgroundColor: [
                    'rgba(108, 99, 255, 0.7)',
                    'rgba(255, 101, 132, 0.7)',
                    'rgba(40, 199, 111, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });

    // Country chart data preparation
    <?php
        $countries = [];
        $countryVisits = [];

        if ($countryData) {
            mysqli_data_seek($countryData, 0);
            while ($row = $countryData->fetch_assoc()) {
                $countries[] = $row['country'];
                $countryVisits[] = $row['visit_count'];
            }
        }
        ?>

    const countries = <?php echo json_encode($countries); ?>;
    const countryVisits = <?php echo json_encode($countryVisits); ?>;

    // City chart data preparation
    <?php
        $cities = [];
        $cityVisits = [];

        if ($cityData) {
            mysqli_data_seek($cityData, 0);
            while ($row = $cityData->fetch_assoc()) {
                $cities[] = $row['city'] . ', ' . $row['country'];
                $cityVisits[] = $row['visit_count'];
            }
        }
        ?>

    const cities = <?php echo json_encode($cities); ?>;
    const cityVisits = <?php echo json_encode($cityVisits); ?>;

    // Device chart data preparation
    <?php
        $devices = [];
        $deviceVisits = [];

        if ($deviceData) {
            mysqli_data_seek($deviceData, 0);
            while ($row = $deviceData->fetch_assoc()) {
                $devices[] = $row['device_type'];
                $deviceVisits[] = $row['visit_count'];
            }
        }
        ?>

    const devices = <?php echo json_encode($devices); ?>;
    const deviceVisits = <?php echo json_encode($deviceVisits); ?>;

    // Browser chart data preparation
    <?php
        $browsers = [];
        $browserVisits = [];

        if ($browserData) {
            mysqli_data_seek($browserData, 0);
            while ($row = $browserData->fetch_assoc()) {
                $browsers[] = $row['browser'];
                $browserVisits[] = $row['visit_count'];
            }
        }
        ?>

    const browsers = <?php echo json_encode($browsers); ?>;
    const browserVisits = <?php echo json_encode($browserVisits); ?>;

    // Initialize country chart
    if (document.getElementById('countryChart')) {
        const countryCtx = document.getElementById('countryChart').getContext('2d');
        new Chart(countryCtx, {
            type: 'bar',
            data: {
                labels: countries,
                datasets: [{
                    label: 'Visits by Country',
                    data: countryVisits,
                    backgroundColor: 'rgba(108, 99, 255, 0.7)',
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // Initialize city chart
    if (document.getElementById('cityChart')) {
        const cityCtx = document.getElementById('cityChart').getContext('2d');
        new Chart(cityCtx, {
            type: 'bar',
            data: {
                labels: cities,
                datasets: [{
                    label: 'Visits by City',
                    data: cityVisits,
                    backgroundColor: 'rgba(255, 101, 132, 0.7)',
                    borderRadius: 5
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // Initialize device chart
    if (document.getElementById('deviceChart')) {
        const deviceCtx = document.getElementById('deviceChart').getContext('2d');
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: devices,
                datasets: [{
                    data: deviceVisits,
                    backgroundColor: [
                        'rgba(108, 99, 255, 0.7)',
                        'rgba(40, 199, 111, 0.7)',
                        'rgba(255, 159, 64, 0.7)',
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Initialize browser chart
    if (document.getElementById('browserChart')) {
        const browserCtx = document.getElementById('browserChart').getContext('2d');
        new Chart(browserCtx, {
            type: 'pie',
            data: {
                labels: browsers,
                datasets: [{
                    data: browserVisits,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.7)',
                        'rgba(255, 101, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)',
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(153, 102, 255, 0.7)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
});
       </script>