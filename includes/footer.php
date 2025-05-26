<?php
echo '<link rel="stylesheet" href="css/footer.css">';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<footer class="footer">
    <div class="footer-content">
        <div class="footer-section">
            <h3>About Us</h3>
            <p>My E-Commerce Store offers quality products at affordable prices. Shop with confidence!</p>
        </div>
        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul class="footer-links">
                <li><a href="index.php">Home</a></li>
                <!-- <li><a href="products.php">Products</a></li>
                <li><a href="cart.php">Cart</a></li> -->
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="profile.php">Profile</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Contact Us</h3>
            <p>Email: support@myecommercestore.com</p>
            <p>Phone: +880 1521721630</p>
        </div>
        <div class="footer-section">
            <h3>Follow Us</h3>
            <div class="social-links">
                <a href="https://www.facebook.com/share/169Wz4uMBt/" class="social-link" style="display: inline-block; width: 40px; height: 40px;">
                    <img src="images/facebook-color.svg" alt="Facebook" style="object-fit: contain; width: 100%; height: 100%;">
                </a>
                <a href="#" class="social-link"><img src="assets/twitter-icon.png" alt="Twitter"></a>
                <a href="#" class="social-link"><img src="assets/instagram-icon.png" alt="Instagram"></a>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <?php echo date('Y'); ?> My E-Commerce Store. All rights reserved.</p>
    </div>
</footer>
</body>

</html>