<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to managers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Get manager's branch_id and branch name
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.branch_id, b.name AS branch_name 
                      FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE u.id = ? AND u.role = 'manager'");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_id = $result['branch_id'] ?? 0;
$branch_name = $result['branch_name'] ?? 'Unknown Branch';

if (!$branch_id) {
    $db_error = "No branch assigned to this manager.";
}

// Initialize metrics
$total_invoiced = 0;
$total_paid = 0;
$outstanding_balance = 0;
$recent_invoices = [];
$recent_payments = [];

try {
    // Total invoiced amount (from invoices linked to group bookings for this branch)
    $stmt = $pdo->prepare("
        SELECT SUM(i.amount) as total
        FROM invoices i
        JOIN group_bookings gb ON i.group_booking_id = gb.id
        WHERE gb.hotel_id = ?
    ");
    $stmt->execute([$branch_id]);
    $total_invoiced = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total paid amount (from payments linked to reservations or group bookings for this branch)
    $stmt = $pdo->prepare("
        SELECT SUM(p.amount) as total
        FROM payments p
        WHERE p.status = 'completed'
        AND (p.reservation_id IN (SELECT id FROM reservations WHERE hotel_id = ?)
             OR p.group_booking_id IN (SELECT id FROM group_bookings WHERE hotel_id = ?))
    ");
    $stmt->execute([$branch_id, $branch_id]);
    $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Outstanding balance (pending or overdue invoices)
    $stmt = $pdo->prepare("
        SELECT SUM(i.amount) as total
        FROM invoices i
        JOIN group_bookings gb ON i.group_booking_id = gb.id
        WHERE gb.hotel_id = ? AND i.status IN ('pending', 'overdue')
    ");
    $stmt->execute([$branch_id]);
    $outstanding_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Recent invoices (last 30 days)
    $stmt = $pdo->prepare("
        SELECT i.id, i.amount, i.status, i.due_date, cp.company_name
        FROM invoices i
        JOIN group_bookings gb ON i.group_booking_id = gb.id
        JOIN company_profiles cp ON gb.company_id = cp.id
        WHERE gb.hotel_id = ? AND i.issued_at >= CURDATE() - INTERVAL 30 DAY
        ORDER BY i.issued_at DESC
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent payments (last 30 days)
    $stmt = $pdo->prepare("
        SELECT p.id, p.amount, p.payment_method, p.card_last_four, p.status, p.created_at
        FROM payments p
        WHERE p.status = 'completed'
        AND (p.reservation_id IN (SELECT id FROM reservations WHERE hotel_id = ?)
             OR p.group_booking_id IN (SELECT id FROM group_bookings WHERE hotel_id = ?))
        AND p.created_at >= CURDATE() - INTERVAL 30 DAY
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$branch_id, $branch_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $total_invoiced = $total_paid = $outstanding_balance = 0;
    $recent_invoices = $recent_payments = [];
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Manager Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="manager_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="occupancy_reports.php" class="sidebar__link">
                        <i class="ri-bar-chart-line"></i>
                        <span>Occupancy Reports</span>
                    </a>
                </li>
                <li>
                    <a href="financial_reports.php" class="sidebar__link">
                        <i class="ri-money-dollar-circle-line"></i>
                        <span>Financial Reports</span>
                    </a>
                </li>
                <li>
                    <a href="projected_occupancy.php" class="sidebar__link">
                        <i class="ri-calendar-2-line"></i>
                        <span>Projected Occupancy</span>
                    </a>
                </li>
                <li>
                    <a href="daily_reports.php" class="sidebar__link">
                        <i class="ri-file-chart-line"></i>
                        <span>Daily Reports</span>
                    </a>
                </li>
                <li>
                    <a href="billing_summary.php" class="sidebar__link active">
                        <i class="ri-wallet-line"></i>
                        <span>Billing Summary</span>
                    </a>
                </li>
                <li>
                    <a href="manage_bookings.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="clerk_profile.php" class="sidebar__link">
                        <i class="ri-user-settings-line"></i>
                        <span>Manage Clerks</span>
                    </a>
                </li>
                <li>
                    <a href="manager_settings.php" class="sidebar__link">
                        <i class="ri-settings-3-line"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="sidebar__link">
                        <i class="ri-logout-box-line"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?> - Billing Summary</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Manager'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Billing Summary Section -->
        <section id="billing-summary" class="dashboard__section active">
            <h2 class="section__subheader">Billing Overview</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-file-invoice-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Invoiced</h3>
                        <p>$<?php echo number_format($total_invoiced, 2); ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-money-dollar-circle-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Paid</h3>
                        <p>$<?php echo number_format($total_paid, 2); ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-wallet-line card__icon"></i>
                    <div class="card__content">
                        <h3>Outstanding Balance</h3>
                        <p>$<?php echo number_format($outstanding_balance, 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices -->
            <h2 class="section__subheader">Recent Invoices (Last 30 Days)</h2>
            <?php if (empty($recent_invoices)): ?>
                <p>No recent invoices found.</p>
            <?php else: ?>
                <table class="billing__table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Company</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_invoices as $invoice): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($invoice['id']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['company_name']); ?></td>
                                <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($invoice['status']); ?></td>
                                <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Recent Payments -->
            <h2 class="section__subheader">Recent Payments (Last 30 Days)</h2>
            <?php if (empty($recent_payments)): ?>
                <p>No recent payments found.</p>
            <?php else: ?>
                <table class="billing__table">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Card Last Four</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['id']); ?></td>
                                <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['card_last_four'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($payment['status']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($payment['created_at']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>
</div>

<style>
/* Styles for the billing summary page */
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
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.overview__card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.card__icon {
    font-size: 2.5rem;
    color: #3b82f6;
    background: #eff6ff;
    padding: 1rem;
    border-radius: 50%;
    min-width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
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

.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.dashboard__section.active {
    display: block;
}

.error {
    color: red;
    margin-bottom: 1rem;
}

.billing__table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.billing__table th,
.billing__table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.billing__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #1f2937;
}

.billing__table tr:hover {
    background: #f3f4f6;
}

/* Sidebar styles */
.sidebar__link.active {
    background: #3b82f6;
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });
});
</script>

<?php include 'templates/footer.php'; ?>