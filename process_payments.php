<?php
// Configure session settings
ini_set('session.gc_maxlifetime', 7200); // 2 hours
session_set_cookie_params(7200);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Initialize variables
$errors = [];
$success = '';
$invoice = null;

// Debug session state
$debug_session = false; // Set to true to enable session debugging
if ($debug_session && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clerk')) {
    $errors[] = "Authentication failed. Session details: " . json_encode($_SESSION, JSON_PRETTY_PRINT);
}

// Restrict access to clerks only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clerk') {
    if ($debug_session) {
        // Display error instead of redirecting for debugging
        $errors[] = "Please log in as a clerk to access this page.";
    } else {
        header("Location: login.php");
        exit();
    }
}

// Get clerk's branch_id and branch name
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT u.branch_id, b.name AS branch_name 
                          FROM users u 
                          LEFT JOIN branches b ON u.branch_id = b.id 
                          WHERE u.id = ? AND u.role = 'clerk'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $branch_id = $result['branch_id'] ?? 0;
    $branch_name = $result['branch_name'] ?? 'Unknown Branch';

    if (!$branch_id) {
        $errors[] = "No branch assigned to this clerk.";
    }
} catch (PDOException $e) {
    $errors[] = "Error fetching user data: " . $e->getMessage();
}

// Handle invoice search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_invoice'])) {
    $search_type = $_POST['search_type'] ?? '';
    $search_value = trim($_POST['search_value'] ?? '');

    if (empty($search_value)) {
        $errors[] = "Please enter a valid email or invoice ID.";
    } else {
        try {
            $query = "
                SELECT i.id, i.user_id, i.reservation_id, i.amount, i.status, u.email, u.name, 
                       b.check_in, b.check_out, r.room_number, rt.name AS room_type
                FROM invoices i
                JOIN users u ON i.user_id = u.id
                JOIN bookings b ON i.reservation_id = b.id
                JOIN rooms r ON b.room_id = r.id
                JOIN room_types rt ON r.room_type_id = rt.id
                WHERE %s AND b.branch_id = ? AND i.status = '1'
            ";
            if ($search_type === 'email') {
                $query = sprintf($query, "u.email = ?");
                $stmt = $pdo->prepare($query);
                $stmt->execute([$search_value, $branch_id]);
            } else {
                $query = sprintf($query, "i.id = ?");
                $stmt = $pdo->prepare($query);
                $stmt->execute([(int)$search_value, $branch_id]);
            }
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) {
                $errors[] = "No pending invoice found.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error searching invoice: " . $e->getMessage();
        }
    }
}

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $user_id = (int)($_POST['user_id'] ?? 0);
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0.00);
    $payment_method = $_POST['payment_method'] ?? '';
    $card_last_four = $_POST['card_last_four'] ?? null;

    if ($invoice_id <= 0 || $user_id <= 0 || $reservation_id <= 0 || $amount <= 0) {
        $errors[] = "Invalid invoice, user, or amount.";
    } elseif (!in_array($payment_method, ['cash', 'credit_card'])) {
        $errors[] = "Invalid payment method.";
    } elseif ($payment_method === 'credit_card' && (empty($card_last_four) || !preg_match('/^\d{4}$/', $card_last_four))) {
        $errors[] = "Invalid credit card number (last four digits required).";
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Verify invoice exists and is pending
            $stmt = $pdo->prepare("SELECT id, status FROM invoices WHERE id = ? AND status = '1'");
            $stmt->execute([$invoice_id]);
            $invoice_check = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice_check) {
                $errors[] = "Invalid or non-pending invoice.";
                $pdo->rollBack();
            } else {
                // Update invoice status to completed
                $stmt = $pdo->prepare("UPDATE invoices SET status = '0' WHERE id = ?");
                $stmt->execute([$invoice_id]);

                // Insert payment record
                $stmt = $pdo->prepare("
                    INSERT INTO payments (user_id, reservation_id, amount, payment_method, card_last_four, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'completed', NOW())
                ");
                $stmt->execute([$user_id, $reservation_id, $amount, $payment_method, $card_last_four]);

                $pdo->commit();
                $success = "Payment processed successfully.";
                $invoice = null; // Clear invoice to reset form
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Error processing payment: " . $e->getMessage();
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
                    <a href="billing_statements.php" class="sidebar__link">
                        <i class="ri-wallet-line"></i>
                        <span>Billing Statements</span>
                    </a>
                </li>
                <li>
                    <a href="process_payments.php" class="sidebar__link active">
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
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?> - Process Payments</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="process-payments" class="dashboard__section active">
            <h2 class="section__subheader">Process Payment</h2>

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

            <!-- Search Invoice -->
            <div class="form__container">
                <h3>Search Invoice</h3>
                <form method="POST" action="process_payments.php">
                    <div class="form__group">
                        <label for="search_type">Search By:</label>
                        <select name="search_type" id="search_type">
                            <option value="email">Customer Email</option>
                            <option value="invoice_id">Invoice ID</option>
                        </select>
                    </div>
                    <div class="form__group">
                        <label for="search_value">Enter Email or ID:</label>
                        <input type="text" name="search_value" id="search_value" required>
                    </div>
                    <button type="submit" name="search_invoice" class="btn btn-primary">Search</button>
                </form>
            </div>

            <!-- Process Payment -->
            <?php if ($invoice): ?>
                <div class="form__container">
                    <h3>Invoice Details</h3>
                    <p><strong>Invoice ID:</strong> <?php echo htmlspecialchars($invoice['id']); ?></p>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($invoice['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($invoice['email']); ?></p>
                    <p><strong>Room:</strong> <?php echo htmlspecialchars($invoice['room_number'] . ' (' . $invoice['room_type'] . ')'); ?></p>
                    <p><strong>Check-In:</strong> <?php echo htmlspecialchars($invoice['check_in']); ?></p>
                    <p><strong>Check-Out:</strong> <?php echo htmlspecialchars($invoice['check_out']); ?></p>
                    <p><strong>Amount Due:</strong> $<?php echo number_format($invoice['amount'], 2); ?></p>
                    <form method="POST" action="process_payments.php">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $invoice['user_id']; ?>">
                        <input type="hidden" name="reservation_id" value="<?php echo $invoice['reservation_id']; ?>">
                        <input type="hidden" name="amount" value="<?php echo $invoice['amount']; ?>">
                        <div class="form__group">
                            <label for="payment_method">Payment Method:</label>
                            <select name="payment_method" id="payment_method" required onchange="toggleCardInput()">
                                <option value="cash">Cash</option>
                                <option value="credit_card">Credit Card</option>
                            </select>
                        </div>
                        <div class="form__group" id="card_input" style="display: none;">
                            <label for="card_last_four">Credit Card Last Four Digits:</label>
                            <input type="text" name="card_last_four" id="card_last_four" maxlength="4" pattern="\d{4}">
                        </div>
                        <button type="submit" name="process_payment" class="btn btn-primary">Process Payment</button>
                    </form>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<style>
/* Styles for the process payments page */
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

h3 {
    font-size: 1.2rem;
    font-weight: 600;
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

    // Toggle credit card input based on payment method
    window.toggleCardInput = function() {
        const paymentMethod = document.getElementById('payment_method').value;
        const cardInput = document.getElementById('card_input');
        cardInput.style.display = paymentMethod === 'credit_card' ? 'block' : 'none';
    };
});
</script>

<?php include 'templates/footer.php'; ?>