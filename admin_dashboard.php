<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to super admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';

// Placeholder queries for dashboard metrics
$total_hotels = 0;
$total_users = 0;
$total_bookings = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM hotels");
    $total_hotels = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bookings");
    $total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (PDOException $e) {
    // Handle database errors silently for now
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Super Admin</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="admin_dashboard.php" class="sidebar__link active">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="manage_hotels.php" class="sidebar__link">
                        <i class="ri-building-line"></i>
                        <span>Manage Hotels</span>
                    </a>
                </li>
                <li>
                    <a href="manage_users.php" class="sidebar__link">
                        <i class="ri-user-line"></i>
                        <span>Manage Users</span>
                    </a>
                </li>
                <li>
                    <a href="manage_bookings.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="sidebar__link">
                        <i class="ri-bar-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="sidebar__link">
                        <i class="ri-settings-3-line"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a href="auth.php?logout=1" class="sidebar__link">
                        <i class="ri-logout-box-line"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Super Admin Dashboard</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section class="dashboard__overview">
            <h2 class="section__subheader">Overview</h2>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-building-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Hotels</h3>
                        <p><?php echo $total_hotels; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-user-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Users</h3>
                        <p><?php echo $total_users; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-calendar-check-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Bookings</h3>
                        <p><?php echo $total_bookings; ?></p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<?php include 'templates/footer.php'; ?>