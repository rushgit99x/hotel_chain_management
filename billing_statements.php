<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to clerks only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clerk') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Get clerk's branch_id and branch name
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.branch_id, b.name AS branch_name 
                      FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE u.id = ? AND u.role = 'clerk'");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_id = $result['branch_id'] ?? 0;
$branch_name = $result['branch_name'] ?? 'Unknown Branch';

if (!$branch_id) {
    $db_error = "No branch assigned to this clerk.";
}

// Initialize variables
$errors = [];
$success = '';
$booking = null;
$billing = null;

// Handle booking search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_booking'])) {
    $search_type = $_POST['search_type'] ?? '';
    $search_value = trim($_POST['search_value'] ?? '');

    if (empty($search_value)) {
        $errors[] = "Please enter a valid email or booking ID.";
    } else {
        try {
            if ($search_type === 'email') {
                $stmt = $pdo->prepare("
                    SELECT b.id, b.user_id, b.room_id, b.check_in, b.check_out, b.status, u.email, u.name, r.room_number, rt.name AS room_type, rt.base_price
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    JOIN rooms r ON b.room_id = r.id
                    JOIN room_types rt ON r.room_type_id = rt.id
                    WHERE u.email = ? AND b.branch_id = ? AND b.status = 'confirmed'
                ");
                $stmt->execute([$search_value, $branch_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT b.id, b.user_id, b.room_id, b.check_in, b.check_out, b.status, u.email, u.name, r.room_number, rt.name AS room_type, rt.base_price
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    JOIN rooms r ON b.room_id = r.id
                    JOIN room_types rt ON r.room_type_id = rt.id
                    WHERE b.id = ? AND b.branch_id = ? AND b.status = 'confirmed'
                ");
                $stmt->execute([(int)$search_value, $branch_id]);
            }
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$booking) {
                $errors[] = "No active booking found for billing.";
            } else {
                // Calculate room charges
                $check_in = new DateTime($booking['check_in']);
                $check_out = new DateTime($booking['check_out']);
                $nights = $check_in->diff($check_out)->days;
                $room_charges = $nights * $booking['base_price'];
                $billing = [
                    'room_charges' => $room_charges,
                    'service_charges' => 0.00, // Initial value, updated by clerk
                    'total' => $room_charges
                ];
            }
        } catch (PDOException $e) {
            $errors[] = "Error searching booking: " . $e->getMessage();
        }
    }
}

// Handle billing statement generation and invoice saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invoice'])) {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $service_charges = (float)($_POST['service_charges'] ?? 0.00);
    $total_amount = (float)($_POST['total_amount'] ?? 0.00);

    if ($booking_id <= 0 || $user_id <= 0 || $total_amount <= 0) {
        $errors[] = "Invalid booking or total amount.";
    } elseif ($service_charges < 0) {
        $errors[] = "Service charges cannot be negative.";
    } else {
        try {
            // Verify booking exists and is confirmed
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND branch_id = ? AND status = 'confirmed'");
            $stmt->execute([$booking_id, $branch_id]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = "Invalid or non-confirmed booking.";
            } else {
                // Insert invoice into invoices table
                $stmt = $pdo->prepare("
                    INSERT INTO invoices (user_id, reservation_id, amount, status, created_at)
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$user_id, $booking_id, $total_amount]);

                $success = "Billing statement generated and invoice saved successfully.";
                $booking = null; // Clear booking to reset form
                $billing = null;
            }
        } catch (PDOException $e) {
            $errors[] = "Error generating invoice: " . $e->getMessage();
        }
    }
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Clerk Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="clerk_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="check_in.php" class="sidebar__link">
                        <i class="ri-login-box-line"></i>
                        <span>Check-In Customers</span>
                    </a>
                </li>
                <li>
                    <a href="check_out.php" class="sidebar__link">
                        <i class="ri-logout-box-line"></i>
                        <span>Check-Out Customers</span>
                    </a>
                </li>
                <li>
                    <a href="manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="room_availability.php" class="sidebar__link">
                        <i class="ri-home-line"></i>
                        <span>Room Availability</span>
                    </a>
                </li>
                <li>
                    <a href="create_customer.php" class="sidebar__link">
                        <i class="ri-user-add-line"></i>
                        <span>Create Customer</span>
                    </a>
                </li>
                <li>
                    <a href="billing_statements.php" class="sidebar__link active">
                        <i class="ri-wallet-line"></i>
                        <span>Billing Statements</span>
                    </a>
                </li>
                <li>
                    <a href="process_payments.php" class="sidebar__link">
                        <i class="ri-money-dollar-circle-line"></i>
                        <span>Process Payments</span>
                    </a>
                </li>
                <li>
                    <a href="clerk_settings.php" class="sidebar__link">
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
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?> - Billing Statements</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="billing-statements" class="dashboard__section active">
            <h2 class="section__subheader">Generate Billing Statement</h2>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <!-- Search Booking -->
            <div class="form__container">
                <h3>Search Booking</h3>
                <form method="POST" action="billing_statements.php">
                    <div class="form__group">
                        <label for="search_type">Search By:</label>
                        <select name="search_type" id="search_type">
                            <option value="email">Customer Email</option>
                            <option value="booking_id">Booking ID</option>
                        </select>
                    </div>
                    <div class="form__group">
                        <label for="search_value">Enter Email or ID:</label>
                        <input type="text" name="search_value" id="search_value" required>
                    </div>
                    <button type="submit" name="search_booking" class="btn btn-primary">Search</button>
                </form>
            </div>

            <!-- Billing Statement -->
            <?php if ($booking && $billing): ?>
                <div class="form__container">
                    <h3>Billing Statement</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($booking['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($booking['email']); ?></p>
                    <p><strong>Room:</strong> <?php echo htmlspecialchars($booking['room_number'] . ' (' . $booking['room_type'] . ')'); ?></p>
                    <p><strong>Check-In:</strong> <?php echo htmlspecialchars($booking['check_in']); ?></p>
                    <p><strong>Check-Out:</strong> <?php echo htmlspecialchars($booking['check_out']); ?></p>
                    <p><strong>Room Charges:</strong> $<?php echo number_format($billing['room_charges'], 2); ?></p>
                    <form method="POST" action="billing_statements.php">
                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $booking['user_id']; ?>">
                        <input type="hidden" name="total_amount" id="total_amount" value="<?php echo $billing['total']; ?>">
                        <div class="form__group">
                            <label for="service_charges">Optional Service Charges (e.g., restaurant, laundry):</label>
                            <input type="number" name="service_charges" id="service_charges" step="0.01" min="0" value="0.00" onchange="updateTotal()">
                        </div>
                        <div class="form__group">
                            <label for="total_amount_display">Total Amount:</label>
                            <input type="text" id="total_amount_display" value="$<?php echo number_format($billing['total'], 2); ?>" readonly>
                        </div>
                        <button type="submit" name="generate_invoice" class="btn btn-primary">Generate Invoice</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<style>
/* Styles for the billing statements page */
.form__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.form__group {
    margin-bottom: 1rem;
}

.form__group label {
    display: block;
    font-size: 1rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.form__group input,
.form__group select {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
}

.form__group input[readonly] {
    background: #f3f4f6;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #2563eb;
}

.error, .success {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.error {
    background: #fee2e2;
    color: #dc2626;
}

.success {
    background: #dcfce7;
    color: #15803d;
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

/* Sidebar styles */
.sidebar__link.active {
    background: #3b82f6;
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });

    // Update total amount when service charges change
    window.updateTotal = function() {
        const serviceCharges = parseFloat(document.getElementById('service_charges').value) || 0;
        const roomCharges = <?php echo $billing ? $billing['room_charges'] : 0; ?>;
        const total = roomCharges + serviceCharges;
        document.getElementById('total_amount_display').value = '$' + total.toFixed(2);
        document.getElementById('total_amount').value = total.toFixed(2);
    };
});
</script>

<?php include 'templates/footer.php'; ?>