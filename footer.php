</main>

<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div>
                <div class="footer-brand">Parfum Luxe</div>
                <p class="footer-description">
                    ร้านน้ำหอมแบรนด์เนมของแท้ 100% นำเข้าจากต่างประเทศ
                    คุณภาพดี ราคาถูก พร้อมส่งทั่วประเทศ
                </p>
                <div class="d-flex gap-md">
                    <a href="#" style="color: var(--primary);"><i class="fab fa-facebook fa-lg"></i></a>
                    <a href="#" style="color: var(--primary);"><i class="fab fa-instagram fa-lg"></i></a>
                    <a href="#" style="color: var(--primary);"><i class="fab fa-line fa-lg"></i></a>
                </div>
            </div>

            <div>
                <h4>ลิงก์ด่วน</h4>
                <ul class="footer-links">
                    <li><a href="/">หน้าแรก</a></li>
                    <li><a href="/products.php">สินค้าทั้งหมด</a></li>
                    <li><a href="/promotions.php">โปรโมชั่น</a></li>
                    <li><a href="/cart.php">ตะกร้าสินค้า</a></li>
                </ul>
            </div>

            <div>
                <h4>บัญชีผู้ใช้</h4>
                <ul class="footer-links">
                    <li><a href="/profile.php">โปรไฟล์</a></li>
                    <li><a href="/profile.php?tab=orders">ประวัติการสั่งซื้อ</a></li>
                    <li><a href="/login.php">เข้าสู่ระบบ</a></li>
                    <li><a href="/register.php">สมัครสมาชิก</a></li>
                </ul>
            </div>

            <div>
                <h4>ติดต่อเรา</h4>
                <ul class="footer-links">
                    <li><i class="fas fa-phone"></i> 0980181956</li>
                    <li><i class="fas fa-envelope"></i> ParfumLuxe@gmail.com</li>
                    <li><i class="fas fa-map-marker-alt"></i> กรุงเทพมหานคร</li>
                    <li><i class="fas fa-clock"></i> 09:00 - 21:00 น.</li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy;
                <?= date('Y') ?> Parfum Luxe. All rights reserved.
                &bull; <a href="/privacy.php" style="color: var(--gray-400);">นโยบายความเป็นส่วนตัว</a>
                &bull; <a href="/terms.php" style="color: var(--gray-400);">ข้อกำหนดการใช้บริการ</a>
            </p>
        </div>
    </div>
</footer>

<script>
    // Mobile menu toggle
    function toggleMobileMenu() {
        document.getElementById('navbarMenu').classList.toggle('active');
    }

    // Auto hide alerts
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>
</body>

</html>