<?php

/**
 * Enhanced Footer for W5OBM
 * File: /include/footer.php
 * Purpose: Professional footer with comprehensive links and information
 * Design: Following W5OBM Modern Website Design Guidelines
 */

// Get current year for copyright
$current_year = date('Y');

// Get Active Members count from database
$footer_active_members = 0;
if (isset($conn) && $conn) {
    $result = $conn->query("SELECT COUNT(*) AS total FROM members WHERE status = 'Active'");
    if ($result && $row = $result->fetch_assoc()) {
        $footer_active_members = (int)$row['total'];
    }
}

// Calculate Years of Service
$founding_year = 1998;
$footer_years_of_service = $current_year - $founding_year;

// Check if user is logged in for conditional content
$isLoggedIn = isAuthenticated();
$isAdmin = $isLoggedIn ? isAdmin(getCurrentUserId()) : false;
?>

<style>
    .w5obm-footer .footer-logo {
        max-width: 120px;
        width: 100%;
        height: auto;
        object-fit: contain;
    }

    .w5obm-footer .footer-logo-section {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-wrap: nowrap;
    }

    .w5obm-footer .footer-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
        align-items: center;
    }

    .w5obm-footer .footer-badge {
        max-height: 60px;
        width: auto;
        height: auto;
        object-fit: contain;
        transition: transform 0.2s ease;
    }

    .w5obm-footer .footer-badge:hover {
        transform: translateY(-2px);
    }

    @media (min-width: 992px) {
        .w5obm-footer .footer-badge {
            max-height: 72px;
        }
    }
</style>

<footer class="w5obm-footer">
    <div class="footer-main">
        <div class="container">
            <div class="row">
                <!-- Club Information Column -->
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="footer-section">
                        <div class="footer-logo-section">
                            <img src="<?= BASE_URL ?>images/badges/club_logo.png" alt="W5OBM Logo" class="footer-logo" loading="lazy">
                            <div class="footer-callsign">W5OBM</div>
                        </div>

                        <h5 class="footer-title">Olive Branch Amateur Radio Club</h5>
                        <p class="footer-description">
                            Making our community "Radio Active" since 1998.
                            Serving Mississippi and the Mid-South region with education,
                            emergency communications, and amateur radio fellowship.
                        </p>

                        <div class="footer-stats">
                            <div class="stat-item">
                                <span class="stat-number"><?= (int)$footer_active_members ?>+</span>
                                <span class="stat-label">Active Members</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number"><?= (int)$footer_years_of_service ?></span>
                                <span class="stat-label">Years Strong</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">100%</span>
                                <span class="stat-label">ARRL Affiliated</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Links Column -->
                <div class="col-lg-2 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="footer-section">
                        <h5 class="footer-title">Quick Links</h5>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i>Home</a></li>
                            <li><a href="<?= BASE_URL ?>aboutus.php"><i class="fas fa-users"></i>About Us</a></li>
                            <li><a href="<?= BASE_URL ?>membership/membership_app.php"><i class="fas fa-user-plus"></i>Join W5OBM</a></li>
                            <li><a href="<?= BASE_URL ?>events.php"><i class="fas fa-calendar"></i>Events</a></li>
                            <li><a href="<?= BASE_URL ?>hamlicensing.php"><i class="fas fa-graduation-cap"></i>Education</a></li>
                            <li><a href="<?= BASE_URL ?>emergency.php"><i class="fas fa-shield-alt"></i>Emergency</a></li>
                            <li><a href="<?= BASE_URL ?>contactus.php"><i class="fas fa-envelope"></i>Contact</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Amateur Radio Resources Column -->
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="footer-section">
                        <h5 class="footer-title">Amateur Radio</h5>
                        <ul class="footer-links">
                            <li><a href="<?= BASE_URL ?>repeaters.php"><i class="fas fa-broadcast-tower"></i>W5OBM Repeaters</a></li>
                            <li><a href="<?= BASE_URL ?>weeklynets.php"><i class="fas fa-microphone"></i>Weekly Nets</a></li>
                            <li><a href="<?= BASE_URL ?>ARRL_Field_Day.php"><i class="fas fa-campground"></i>Field Day</a></li>
                            <li><a href="<?= BASE_URL ?>references.php"><i class="fas fa-book"></i>References</a></li>
                            <li><a href="https://www.arrl.org" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i>ARRL</a></li>
                            <li><a href="https://www.fcc.gov" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i>FCC</a></li>
                            <li><a href="https://www.qrz.com" target="_blank" rel="noopener"><i class="fas fa-search"></i>QRZ.com</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Contact & Meeting Info Column -->
                <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="footer-section">
                        <h5 class="footer-title">Get Connected</h5>

                        <div class="contact-info">
                            <div class="contact-item">
                                <i class="fas fa-radio text-primary"></i>
                                <div>
                                    <strong>Weekly Net</strong><br>
                                    Thursday 8:30 PM<br>
                                    147.255 MHz ( +600 offset, PL 79.7)
                                </div>
                            </div>

                            <div class="contact-item">
                                <i class="fas fa-calendar-alt text-success"></i>
                                <div>
                                    <strong>Club Meetings</strong><br>
                                    3rd Thursday 7:00 PM Testing at 6:00 PM<br>
                                    Fairhaven Fire Department
                                </div>
                            </div>

                            <div class="contact-item">
                                <i class="fas fa-envelope text-info"></i>
                                <div>
                                    <strong>Email</strong><br>
                                    <a href="mailto:secretary@w5obm.com">secretary@w5obm.com</a>
                                </div>
                            </div>

                            <div class="contact-item">
                                <i class="fas fa-map-marker-alt text-warning"></i>
                                <div>
                                    <strong>Location</strong><br>
                                    13701 Center Hill Rd
                                    Olive Branch, MS 38654
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Admin/Member Links Section -->
            <?php if ($isLoggedIn): ?>
                <div class="row border-top border-secondary pt-4">
                    <div class="col-12" data-aos="fade-up" data-aos-delay="500">
                        <div class="footer-member-section">
                            <h6 class="text-warning"><i class="fas fa-user-shield me-2"></i>Member Area</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="footer-links horizontal">
                                        <li><a href="<?= BASE_URL ?>authentication/dashboard.php"><i class="fas fa-tachometer-alt"></i>Dashboard</a></li>
                                        <li><a href="<?= BASE_URL ?>members/edit_profile.php"><i class="fas fa-user-edit"></i>Profile</a></li>
                                        <li><a href="<?= BASE_URL ?>members/member_directory.php"><i class="fas fa-users"></i>Member Directory</a></li>
                                        <li><a href="<?= BASE_URL ?>crm/"><i class="fas fa-store"></i>Marketplace</a></li>
                                    </ul>
                                </div>

                                <?php if ($isAdmin): ?>
                                    <div class="col-md-6">
                                        <h6 class="text-danger"><i class="fas fa-tools me-2"></i>Admin Tools</h6>
                                        <ul class="footer-links horizontal">
                                            <li><a href="<?= BASE_URL ?>administration/"><i class="fas fa-cogs"></i>Administration</a></li>
                                            <li><a href="<?= BASE_URL ?>events/dashboard.php"><i class="fas fa-calendar-plus"></i>Events</a></li>
                                            <li><a href="<?= BASE_URL ?>administration/users/"><i class="fas fa-users-cog"></i>Members</a></li>
                                            <li><a href="<?= BASE_URL ?>weekly_nets/"><i class="fas fa-microphone"></i>Nets</a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 text-center text-lg-start">
                    <p class="footer-copyright">
                        &copy; <?= $current_year ?> KD5BS Robert Stroud.  All rights reserved.<br>
                        
                    </p>
                </div>

                <div class="col-lg-6 text-center text-lg-end">
                    <div class="footer-badges">
                        <img src="<?= BASE_URL ?>images/badges/arrl.png" alt="ARRL Affiliated" class="footer-badge" title="ARRL Affiliated Club" loading="lazy">
                        <img src="<?= BASE_URL ?>images/badges/fcc-seal-rgb-2020-large.png" alt="FCC Licensed" class="footer-badge" title="FCC Part 97 Licensed" loading="lazy">
                        <img src="<?= BASE_URL ?>images/badges/VE-patch.jpg" alt="ARES" class="footer-badge" title="Amateur Radio Emergency Service" loading="lazy">
                    </div>

                    <div class="footer-legal">
                        <a href="<?= BASE_URL ?>documents/privacy-policy.php">Privacy Policy</a>
                        <span class="separator">|</span>
                        <a href="<?= BASE_URL ?>documents/terms-of-use.php">Terms of Use</a>
                        <span class="separator">|</span>
                        <a href="<?= BASE_URL ?>documents/sitemap.php">Site Map</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Display session-based toast messages -->
<?php if (isset($_SESSION['toast'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toastData = <?= json_encode($_SESSION['toast']) ?>;
            showToast(
                toastData.type || 'info',
                toastData.title || 'Information',
                toastData.message || '',
                toastData.theme || '',
                toastData.actions || []
            );
        });
        <?php unset($_SESSION['toast']); ?>
    </script>
<?php endif; ?>
</script>

<!-- jQuery (required for some components) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>

<!-- Bootstrap JavaScript Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- DataTables JavaScript -->
<script src="https://cdn.datatables.net/2.3.0/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.3.0/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.0/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/3.0.0/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/3.0.0/js/responsive.bootstrap5.min.js"></script>

<!-- Font Awesome -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>

<!-- W5OBM Toast Notifications -->
<script src="https://w5obm.com/js/toast-notifications.js"></script>

<script>
    // Initialize Bootstrap components when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'));
        var dropdownList = dropdownElementList.map(function(dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl);
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
        var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl);
        });

        console.log('Bootstrap components initialized');
    });
</script>

<script>
    // Global DataTables guideline initialization for standard tables
    $(document).ready(function() {
        if ($('#recentTransactionsTable').length) {
            $('#recentTransactionsTable').DataTable({
                order: [
                    [0, 'desc']
                ],
                pageLength: 25,
                lengthChange: false,
                responsive: true
            });
        }
    });
</script>