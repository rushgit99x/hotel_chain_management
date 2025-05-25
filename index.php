<?php include 'templates/header.php'; ?>
    <div class="text-center">
        <h1 class="text-4xl font-bold mb-8">Welcome to Hotel Chain Management</h1>
        <?php if (!isset($_SESSION['user_id'])): ?>
            <div class="space-x-4">
                <a href="login.php" class="bg-blue-500 text-white px-6 py-3 rounded hover:bg-blue-600">Login</a>
                <a href="register.php" class="bg-green-500 text-white px-6 py-3 rounded hover:bg-green-600">Register (Customer/Travel Company)</a>
                <a href="superadmin_register.php" class="bg-purple-500 text-white px-6 py-3 rounded hover:bg-purple-600">Register Super Admin</a>
            </div>
        <?php else: ?>
            <p class="text-xl">Access your dashboard:</p>
            <?php if ($_SESSION['role'] == 'super_admin'): ?>
                <a href="admin_portal.php" class="bg-blue-500 text-white px-6 py-3 rounded hover:bg-blue-600">Super Admin Portal</a>
            <?php elseif ($_SESSION['role'] == 'manager'): ?>
                <a href="manager_portal.php" class="bg-blue-500 text-white px-6 py-3 rounded hover:bg-blue-600">Manager Portal</a>
            <?php else: ?>
                <a href="customer_dashboard.php" class="bg-blue-500 text-white px-6 py-3 rounded hover:bg-blue-600">Dashboard</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php include 'templates/footer.php'; ?>