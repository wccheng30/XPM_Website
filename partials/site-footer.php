<?php
/**
 * Shared public footer + scripts.
 * Optional before include: $xpm_load_main_js = false (pages that don't use main.js)
 */
$xpm_load_main_js = isset($xpm_load_main_js) ? $xpm_load_main_js : true;
$xpm_asset_version = isset($xpm_asset_version) ? $xpm_asset_version : '20261005-2';
?>
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="assets/images/XPMLogo.png" alt="Xpress Point Management Services Logo">
                    </div>
                    <p>Empowering organizations with comprehensive HR services and training solutions since 2010.</p>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/in/icol-chua-18248623/" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                        <a href="https://www.facebook.com/XpressPointManagementServices" target="_blank" rel="noopener noreferrer" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com/xpresspoint_mlk/" target="_blank" rel="noopener noreferrer" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://api.whatsapp.com/send?phone=601120816113" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>

                <div class="footer-section">
                    <h4>Explore</h4>
                    <ul>
                        <li><a href="index.html#about">About Us</a></li>
                        <li><a href="index.html#services">Services</a></li>
                        <li><a href="programmes.php">Training Programmes</a></li>
                        <li><a href="facility-booking.php">Book a Facility</a></li>
                        <li><a href="jobs.php">Careers</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="my-bookings.php">My Bookings</a></li>
                        <li><a href="trainers-page.php">PUM Trainers</a></li>
                        <li><a href="index.html#testimonials">Testimonials</a></li>
                        <li><a href="index.html#contact">Contact</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h4>Contact</h4>
                    <ul>
                        <li><i class="fas fa-location-dot"></i> No.6, Jalan Berkat 12, Taman Malim Jaya, 75250 Melaka</li>
                        <li><i class="fas fa-phone"></i> <a href="tel:+60167721113">016 772 1113</a></li>
                        <li><i class="fas fa-envelope"></i> <a href="mailto:hr@xpresspointservices.com">hr@xpresspointservices.com</a></li>
                    </ul>
                    <div class="footer-help">
                        <i class="fas fa-circle-info"></i>
                        Found an error or issue? <a href="index.html#contact">Contact us</a> and we'll fix it promptly.
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Xpress Point Management Services. All rights reserved.</p>
                <div class="footer-links">
                    <a href="privacy-policy.php">Privacy Policy</a>
                    <a href="terms-of-service.php">Terms of Service</a>
                    <a href="cookie-policy.php">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

<?php if ($xpm_load_main_js): ?>
    <script src="assets/js/main.js?v=<?php echo $xpm_asset_version; ?>"></script>
<?php endif; ?>
    <script src="assets/js/motion.js?v=<?php echo $xpm_asset_version; ?>"></script>
    <script src="assets/js/xpm-dialog.js?v=20261006-5"></script>
