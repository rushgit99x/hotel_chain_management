<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');

require_once 'db_connect.php';

try {
    // Total revenue from bookings (assuming price from rooms table)
    $stmt = $pdo->query("SELECT SUM(r.price * DATEDIFF(b.check_out, b.check_in)) as total_revenue 
                         FROM bookings b 
                         JOIN rooms r ON b.room_id = r.id 
                         WHERE b.status = 'confirmed'");
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

    // Occupancy rate (confirmed bookings vs total rooms)
    $stmt = $pdo->query("SELECT COUNT(*) as confirmed_bookings FROM bookings WHERE status = 'confirmed'");
    $confirmed_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['confirmed_bookings'] ?? 0;
    $stmt = $pdo->query("SELECT COUNT(*) as total_rooms FROM rooms");
    $total_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['total_rooms'] ?? 1;
    $occupancy_rate = ($confirmed_bookings / $total_rooms) * 100;

    // Bookings by branch
    $stmt = $pdo->query("SELECT b.name, COUNT(bo.id) as booking_count 
                         FROM branches b 
                         LEFT JOIN bookings bo ON b.id = bo.branch_id 
                         GROUP BY b.id, b.name");
    $bookings_by_branch = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
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
                <li><a href="admin_dashboard.php" class="sidebar__link"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="#create-branch" class="sidebar__link" onclick="showSection('create-branch')"><i class="ri-building-line"></i><span>Create Branch</span></a></li>
                <li><a href="#create-user" class="sidebar__link" onclick="showSection('create-user')"><i class="ri-user-add-line"></i><span>Create User</span></a></li>
                <li><a href="#create-room" class="sidebar__link" onclick="showSection('create-room')"><i class="ri-home-line"></i><span>Add Room</span></a></li>
                <li><a href="manage_hotels.php" class="sidebar__link"><i class="ri-building-line"></i><span>Manage Hotels</span></a></li>
                <li><a href="manage_users.php" class="sidebar__link"><i class="ri-user-line"></i><span>Manage Users</span></a></li>
                <li><a href="manage_bookings.php" class="sidebar__link"><i class="ri-calendar-check-line"></i><span>Manage Bookings</span></a></li>
                <li><a href="reports.php" class="sidebar__link active"><i class="ri-bar-chart-line"></i><span>Reports</span></a></li>
                <li><a href="settings.php" class="sidebar__link"><i class="ri-settings-3-line"></i><span>Settings</span></a></li>
                <li><a href="logout.php" class="sidebar__link"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Reports</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <?php if (isset($error)): ?>
            <div class="alert alert--error">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <section id="reports" class="dashboard__section active">
            <h2 class="section__subheader">Key Metrics</h2>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-money-dollar-circle-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Revenue</h3>
                        <p>$<?php echo number_format($total_revenue, 2); ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-line card__icon"></i>
                    <div class="card__content">
                        <h3>Occupancy Rate</h3>
                        <p><?php echo number_format($occupancy_rate, 2); ?>%</p>
                    </div>
                </div>
            </div>

            <h2 class="section__subheader">Bookings by Branch</h2>
            <div class="table__container">
                <table class="data__table">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Booking Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings_by_branch as $branch): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($branch['name']); ?></td>
                                <td><?php echo $branch['booking_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<style>
.table__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 1.5rem;
}
.data__table {
    width: 100%;
    border-collapse: collapse;
}
.data__table th, .data__table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.data__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}
.overview__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}
.overview__card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}
.card__icon {
    font-size: 2.5rem;
    color: #3b82f6;
    background: #eff6ff;
    padding: 1rem;
    border-radius: 50%;
}
.card__content h3 {
    font-size: 1rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}
.card__content p {
    font-size: 2rem;
    font-weight: bold;
    color: #1f2937;
}
</style>

<script>
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});
</script>

<?php include 'templates/footer.php'; ?>