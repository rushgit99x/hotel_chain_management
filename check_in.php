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
$reservation = null;
$available_rooms = [];

// Handle reservation search
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_reservation'])) {
    $search_type = $_POST['search_type'] ?? '';
    $search_value = trim($_POST['search_value'] ?? '');

    if (empty($search_value)) {
        $errors[] = "Please enter a valid email or reservation ID.";
    } else {
        try {
            if ($search_type === 'email') {
                $stmt = $pdo->prepare("
                    SELECT b.id, b.user_id, b.room_id, b.check_in, b.check_out, b.status, u.email, u.name
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    WHERE u.email = ? AND b.branch_id = ? AND b.status = 'pending' AND b.check_in = CURDATE()
                ");
                $stmt->execute([$search_value, $branch_id]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT b.id, b.user_id, b.room_id, b.check_in, b.check_out, b.status, u.email, u.name
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    WHERE b.id = ? AND b.branch_id = ? AND b.status = 'pending' AND b.check_in = CURDATE()
                ");
                $stmt->execute([(int)$search_value, $branch_id]);
            }
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$reservation) {
                $errors[] = "No pending reservation found for today.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error searching reservation: " . $e->getMessage();
        }
    }
}

// Handle check-in with reservation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in_reservation'])) {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $room_id = (int)($_POST['room_id'] ?? 0);

    if ($booking_id <= 0 || $room_id <= 0) {
        $errors[] = "Invalid booking or room selection.";
    } else {
        try {
            // Verify booking exists and is pending
            $stmt = $pdo->prepare("SELECT id, status FROM bookings WHERE id = ? AND branch_id = ? AND status = 'pending'");
            $stmt->execute([$booking_id, $branch_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                $errors[] = "Invalid or non-pending booking.";
            } else {
                // Verify room is available
                $stmt = $pdo->prepare("SELECT id, status FROM rooms WHERE id = ? AND branch_id = ? AND status = 'available'");
                $stmt->execute([$room_id, $branch_id]);
                $room = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$room) {
                    $errors[] = "Selected room is not available.";
                } else {
                    // Update booking status to confirmed
                    $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
                    $stmt->execute([$booking_id]);

                    // Update room status to occupied
                    $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                    $stmt->execute([$room_id]);

                    $success = "Customer checked in successfully. Room assigned and key issued.";
                    // Note: RFID/smart lock integration would be handled here (e.g., via external API)
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Error processing check-in: " . $e->getMessage();
        }
    }
}

// Handle walk-in customer check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in_walkin'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $check_in = $_POST['check_in'] ?? '';
    $check_out = $_POST['check_out'] ?? '';
    $room_id = (int)($_POST['room_id'] ?? 0);

    if (empty($name) || empty($email) || empty($check_in) || empty($check_out) || $room_id <= 0) {
        $errors[] = "All fields are required for walk-in check-in.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (strtotime($check_in) < strtotime(date('Y-m-d')) || strtotime($check_out) <= strtotime($check_in)) {
        $errors[] = "Invalid check-in or check-out date.";
    } else {
        try {
            // Check if email exists, if not create new customer
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $password = password_hash('default123', PASSWORD_BCRYPT); // Temporary password
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role, name, created_at) VALUES (?, ?, 'customer', ?, NOW())");
                $stmt->execute([$email, $password, $name]);
                $user_id = $pdo->lastInsertId();
            } else {
                $user_id = $user['id'];
            }

            // Verify room is available
            $stmt = $pdo->prepare("SELECT id, status FROM rooms WHERE id = ? AND branch_id = ? AND status = 'available'");
            $stmt->execute([$room_id, $branch_id]);
            $room = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$room) {
                $errors[] = "Selected room is not available.";
            } else {
                // Create new booking
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (user_id, room_id, branch_id, check_in, check_out, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'confirmed', NOW())
                ");
                $stmt->execute([$user_id, $room_id, $branch_id, $check_in, $check_out]);

                // Update room status to occupied
                $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $stmt->execute([$room_id]);

                $success = "Walk-in customer checked in successfully. Room assigned and key issued.";
                // Note: RFID/smart lock integration would be handled here
            }
        } catch (PDOException $e) {
            $errors[] = "Error processing walk-in check-in: " . $e->getMessage();
        }
    }
}

// Fetch available rooms for the branch
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.room_number, rt.name AS room_type
        FROM rooms r
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE r.branch_id = ? AND r.status = 'available'
    ");
    $stmt->execute([$branch_id]);
    $available_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Error fetching available rooms: " . $e->getMessage();
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
                    <a href="check_in.php" class="sidebar__link active">
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
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?> - Check-In</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="check-in" class="dashboard__section active">
            <h2 class="section__subheader">Customer Check-In</h2>

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

            <!-- Search Reservation -->
            <div class="form__container">
                <h3>Search Reservation</h3>
                <form method="POST" action="check_in.php">
                    <div class="form__group">
                        <label for="search_type">Search By:</label>
                        <select name="search_type" id="search_type">
                            <option value="email">Customer Email</option>
                            <option value="reservation_id">Reservation ID</option>
                        </select>
                    </div>
                    <div class="form__group">
                        <label for="search_value">Enter Email or ID:</label>
                        <input type="text" name="search_value" id="search_value" required>
                    </div>
                    <button type="submit" name="search_reservation" class="btn btn-primary">Search</button>
                </form>
            </div>

            <!-- Reservation Check-In -->
            <?php if ($reservation): ?>
                <div class="form__container">
                    <h3>Reservation Details</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($reservation['name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($reservation['email']); ?></p>
                    <p><strong>Check-In:</strong> <?php echo htmlspecialchars($reservation['check_in']); ?></p>
                    <p><strong>Check-Out:</strong> <?php echo htmlspecialchars($reservation['check_out']); ?></p>
                    <form method="POST" action="check_in.php">
                        <input type="hidden" name="booking_id" value="<?php echo $reservation['id']; ?>">
                        <div class="form__group">
                            <label for="room_id">Assign Room:</label>
                            <select name="room_id" id="room_id" required>
                                <option value="">Select Room</option>
                                <?php foreach ($available_rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>">
                                        <?php echo htmlspecialchars($room['room_number'] . ' (' . $room['room_type'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="check_in_reservation" class="btn btn-primary">Check-In Customer</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Walk-In Customer -->
            <div class="form__container">
                <h3>Walk-In Customer</h3>
                <form method="POST" action="check_in.php">
                    <div class="form__group">
                        <label for="name">Customer Name:</label>
                        <input type="text" name="name" id="name" required>
                    </div>
                    <div class="form__group">
                        <label for="email">Customer Email:</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form__group">
                        <label for="check_in">Check-In Date:</label>
                        <input type="date" name="check_in" id="check_in" required>
                    </div>
                    <div class="form__group">
                        <label for="check_out">Check-Out Date:</label>
                        <input type="date" name="check_out" id="check_out" required>
                    </div>
                    <div class="form__group">
                        <label for="room_id_walkin">Assign Room:</label>
                        <select name="room_id" id="room_id_walkin" required>
                            <option value="">Select Room</option>
                            <?php foreach ($available_rooms as $room): ?>
                                <option value="<?php echo $room['id']; ?>">
                                    <?php echo htmlspecialchars($room['room_number'] . ' (' . $room['room_type'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="check_in_walkin" class="btn btn-primary">Check-In Walk-In</button>
                </form>
            </div>
        </section>
    </main>
</div>

<style>
/* Styles for the check-in page */
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
    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });
});
</script>

<?php include 'templates/footer.php'; ?>