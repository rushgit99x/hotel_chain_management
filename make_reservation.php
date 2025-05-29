<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to customers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

// Include database connection and functions
require_once 'db_connect.php';
include_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Define room occupancy limits
$room_occupancy_limits = [
    'single' => 2,
    'double' => 4,
    'suite' => 6
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $branch_id = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $room_type = filter_input(INPUT_POST, 'room_type', FILTER_SANITIZE_SPECIAL_CHARS);
    $check_in_date = filter_input(INPUT_POST, 'check_in_date', FILTER_DEFAULT);
    $check_out_date = filter_input(INPUT_POST, 'check_out_date', FILTER_DEFAULT);
    $occupants = filter_input(INPUT_POST, 'occupants', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_number = filter_input(INPUT_POST, 'card_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_expiry = filter_input(INPUT_POST, 'card_expiry', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_cvc = filter_input(INPUT_POST, 'card_cvc', FILTER_SANITIZE_SPECIAL_CHARS);
    $cardholder_name = filter_input(INPUT_POST, 'cardholder_name', FILTER_SANITIZE_SPECIAL_CHARS);

    // Basic validation
    if (!$branch_id) {
        $errors[] = "Please select a valid branch.";
    }
    if (!in_array($room_type, ['single', 'double', 'suite'])) {
        $errors[] = "Please select a valid room type.";
    }
    if (!$check_in_date || !DateTime::createFromFormat('Y-m-d', $check_in_date)) {
        $errors[] = "Please provide a valid check-in date.";
    }
    if (!$check_out_date || !DateTime::createFromFormat('Y-m-d', $check_out_date)) {
        $errors[] = "Please provide a valid check-out date.";
    }
    if ($check_in_date && $check_out_date && $check_in_date >= $check_out_date) {
        $errors[] = "Check-out date must be after check-in date.";
    }
    if ($check_in_date && $check_in_date < date('Y-m-d')) {
        $errors[] = "Check-in date cannot be in the past.";
    }

    // Enhanced occupancy validation based on room type
    if (!$occupants || $occupants < 1) {
        $errors[] = "Please provide a valid number of occupants (minimum 1).";
    } elseif ($room_type && isset($room_occupancy_limits[$room_type])) {
        $max_occupants = $room_occupancy_limits[$room_type];
        if ($occupants > $max_occupants) {
            $room_type_display = ucfirst($room_type);
            $errors[] = "Maximum occupancy for {$room_type_display} room is {$max_occupants} guests.";
        }
    }

    // Payment method validation
    if (!in_array($payment_method, ['credit_card', 'without_credit_card'])) {
        $errors[] = "Invalid payment method selected.";
    }

    // Credit card validation only if payment method is credit_card
    if ($payment_method === 'credit_card') {
        // Cardholder name validation
        if (!$cardholder_name || strlen(trim($cardholder_name)) < 2) {
            $errors[] = "Please provide a valid cardholder name.";
        } elseif (!preg_match('/^[a-zA-Z\s\-\.\']+$/', $cardholder_name)) {
            $errors[] = "Cardholder name contains invalid characters.";
        }

        // Card number validation
        if (!$card_number) {
            $errors[] = "Please provide a card number.";
        } else {
            $clean_card_number = preg_replace('/[\s\-]/', '', $card_number);
            if (!preg_match('/^\d{13,19}$/', $clean_card_number)) {
                $errors[] = "Please provide a valid card number (13-19 digits).";
            } else {
                if (!validateCardNumberLuhn($clean_card_number)) {
                    $errors[] = "Please provide a valid card number.";
                }
                $card_type = getCardType($clean_card_number);
                if (!$card_type) {
                    $errors[] = "Card type not supported.";
                }
            }
        }

        // Expiry date validation
        if (!$card_expiry) {
            $errors[] = "Please provide an expiry date.";
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
            $errors[] = "Please provide a valid expiry date (MM/YY format).";
        } else {
            list($month, $year) = explode('/', $card_expiry);
            $current_year = date('y');
            $current_month = date('m');
            if ($year < $current_year || ($year == $current_year && $month < $current_month)) {
                $errors[] = "Card has expired.";
            }
            if ($year > ($current_year + 10)) {
                $errors[] = "Invalid expiry date.";
            }
        }

        // CVC validation
        if (!$card_cvc) {
            $errors[] = "Please provide a CVC code.";
        } elseif (!preg_match('/^\d{3,4}$/', $card_cvc)) {
            $errors[] = "Please provide a valid CVC (3-4 digits).";
        } else {
            $card_type = isset($clean_card_number) ? getCardType($clean_card_number) : '';
            if ($card_type === 'amex' && strlen($card_cvc) !== 4) {
                $errors[] = "American Express cards require a 4-digit CVC.";
            } elseif ($card_type !== 'amex' && strlen($card_cvc) !== 3) {
                $errors[] = "Please provide a 3-digit CVC.";
            }
        }
    }

    // Check room availability
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT r.id
                FROM rooms r
                JOIN room_types rt ON r.room_type_id = rt.id
                WHERE r.branch_id = ? 
                AND rt.name = ? 
                AND r.status = 'available'
                AND r.id NOT IN (
                    SELECT room_id 
                    FROM bookings 
                    WHERE branch_id = ? 
                    AND status IN ('pending', 'confirmed')
                    AND (
                        (check_in <= ? AND check_out >= ?) 
                        OR (check_in >= ? AND check_in <= ?)
                    )
                )
                LIMIT 1
            ");
            $stmt->execute([
                $branch_id,
                ucfirst($room_type),
                $branch_id,
                $check_out_date,
                $check_in_date,
                $check_in_date,
                $check_out_date
            ]);
            $available_room = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$available_room) {
                $errors[] = "No rooms available for the selected dates and type.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // Process reservation
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert reservation
            $stmt = $pdo->prepare("
                INSERT INTO reservations (user_id, hotel_id, room_type, check_in_date, check_out_date, occupants, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $branch_id, htmlspecialchars($room_type), htmlspecialchars($check_in_date), htmlspecialchars($check_out_date), $occupants]);
            $reservation_id = $pdo->lastInsertId();

            // Insert booking
            $stmt = $pdo->prepare("
                INSERT INTO bookings (user_id, room_id, branch_id, check_in, check_out, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $available_room['id'], $branch_id, htmlspecialchars($check_in_date), htmlspecialchars($check_out_date)]);

            // Calculate amount for payment
            $stmt = $pdo->prepare("
                SELECT base_price 
                FROM room_types 
                WHERE name = ?
            ");
            $stmt->execute([ucfirst($room_type)]);
            $base_price = $stmt->fetch(PDO::FETCH_ASSOC)['base_price'] ?? 100.00;
            
            $days = (strtotime($check_out_date) - strtotime($check_in_date)) / (60 * 60 * 24);
            $amount = $base_price * $days;

            // Insert payment based on payment method
            if ($payment_method === 'credit_card') {
                // Insert into payments table
                $card_last_four = substr($clean_card_number ?? '', -4);
                $stmt = $pdo->prepare("
                    INSERT INTO payments (user_id, reservation_id, amount, payment_method, card_last_four, cardholder_name, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$user_id, $reservation_id, $amount, htmlspecialchars($payment_method), $card_last_four, htmlspecialchars($cardholder_name ?? '')]);
            } else {
                // Insert into pending_payments table
                $stmt = $pdo->prepare("
                    INSERT INTO pending_payments (user_id, reservation_id, amount, payment_method, status, created_at)
                    VALUES (?, ?, ?, 'invoice', 'pending', NOW())
                ");
                $stmt->execute([$user_id, $reservation_id, $amount]);
            }

            $pdo->commit();
            $success = "Reservation created successfully! Reservation ID: $reservation_id";
            if ($payment_method === 'without_credit_card') {
                $success .= " Please complete payment before 7 PM to confirm your reservation.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to create reservation: " . $e->getMessage();
        }
    }
}

// Helper functions for card validation
function validateCardNumberLuhn($number) {
    $sum = 0;
    $alternate = false;
    
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = intval($number[$i]);
        
        if ($alternate) {
            $digit *= 2;
            if ($digit > 9) {
                $digit = ($digit % 10) + 1;
            }
        }
        
        $sum += $digit;
        $alternate = !$alternate;
    }
    
    return ($sum % 10 == 0);
}

function getCardType($number) {
    $patterns = [
        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
        'mastercard' => '/^5[1-5][0-9]{14}$/',
        'amex' => '/^3[47][0-9]{13}$/',
        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/'
    ];
    
    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $number)) {
            return $type;
        }
    }
    
    return false;
}

// Fetch branches and room types
try {
    $stmt = $pdo->query("SELECT id, name, location FROM branches");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT name, description, base_price FROM room_types");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $branches = [];
    $room_types = [];
}

// Get customer details for header
try {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $customer_name = $user['name'] ?? 'Customer';
    $customer_email = $user['email'] ?? 'Unknown';
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $customer_name = 'Customer';
    $customer_email = 'Unknown';
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Reservation - Customer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Customer Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="customer_dashboard.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_dashboard.php' ? 'active' : ''; ?>">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="make_reservation.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'make_reservation.php' ? 'active' : ''; ?>">
                        <i class="ri-calendar-check-line"></i>
                        <span>Make Reservation</span>
                    </a>
                </li>
                <li>
                    <a href="customer_manage_reservations.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_reservations.php' ? 'active' : ''; ?>">
                        <i class="ri-calendar-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="group_bookings.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'group_bookings.php' ? 'active' : ''; ?>">
                        <i class="ri-group-line"></i>
                        <span>Group Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="residential_suites.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'residential_suites.php' ? 'active' : ''; ?>">
                        <i class="ri-home-heart-line"></i>
                        <span>Residential Suites</span>
                    </a>
                </li>
                <li>
                    <a href="additional_services.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'additional_services.php' ? 'active' : ''; ?>">
                        <i class="ri-service-line"></i>
                        <span>Additional Services</span>
                    </a>
                </li>
                <li>
                    <a href="billing_payments.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'billing_payments.php' ? 'active' : ''; ?>">
                        <i class="ri-wallet-line"></i>
                        <span>Billing & Payments</span>
                    </a>
                </li>
                <li>
                    <a href="check_in_out.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'check_in_out.php' ? 'active' : ''; ?>">
                        <i class="ri-hotel-line"></i>
                        <span>Check-In/Out</span>
                    </a>
                </li>
                <li>
                    <a href="customer_profile.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_profile.php' ? 'active' : ''; ?>">
                        <i class="ri-settings-3-line"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'active' : ''; ?>">
                        <i class="ri-logout-box-line"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Make a New Reservation</h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($customer_email); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section class="reservation__section">
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="reservation__form">
                <div class="form__group">
                    <label for="branch_id">Select Branch</label>
                    <select id="branch_id" name="branch_id" required>
                        <option value="">Select a branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo isset($branch_id) && $branch_id == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name'] . ' - ' . $branch['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form__group">
                    <label for="room_type">Room Type</label>
                    <select id="room_type" name="room_type" required onchange="updateOccupancyLimit()">
                        <option value="">Select room type</option>
                        <?php foreach ($room_types as $type): ?>
                            <option value="<?php echo strtolower($type['name']); ?>" <?php echo isset($room_type) && $room_type == strtolower($type['name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name'] . ' ($' . number_format($type['base_price'], 2) . '/night)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form__help">Room occupancy limits: Single (2), Double (4), Suite (6)</small>
                </div>

                <div class="form__group">
                    <label for="check_in_date">Check-in Date</label>
                    <input type="date" id="check_in_date" name="check_in_date" value="<?php echo isset($check_in_date) ? htmlspecialchars($check_in_date) : ''; ?>" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form__group">
                    <label for="check_out_date">Check-out Date</label>
                    <input type="date" id="check_out_date" name="check_out_date" value="<?php echo isset($check_out_date) ? htmlspecialchars($check_out_date) : ''; ?>" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                </div>

                <div class="form__group">
                    <label for="occupants">Number of Occupants</label>
                    <input type="number" id="occupants" name="occupants" min="1" max="6" value="<?php echo isset($occupants) ? htmlspecialchars($occupants) : 1; ?>" required>
                    <small class="form__help" id="occupancy-help">Maximum occupancy depends on room type</small>
                </div>

                <div class="form__group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required onchange="togglePaymentFields()">
                        <option value="credit_card" <?php echo isset($payment_method) && $payment_method === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="without_credit_card" <?php echo isset($payment_method) && $payment_method === 'without_credit_card' ? 'selected' : ''; ?>>Without Credit Card</option>
                    </select>
                    <small class="form__help" id="payment-warning" style="display: none; color: #dc2626;">
                        You must complete payment before 7 PM to confirm your reservation.
                    </small>
                </div>

                <div id="credit_card_fields" style="display: <?php echo isset($payment_method) && $payment_method === 'credit_card' ? 'block' : 'none'; ?>;">
                    <div class="form__group">
                        <label for="cardholder_name">Cardholder Name</label>
                        <input type="text" id="cardholder_name" name="cardholder_name" placeholder="John Doe" value="<?php echo isset($cardholder_name) ? htmlspecialchars($cardholder_name) : ''; ?>" <?php echo isset($payment_method) && $payment_method === 'credit_card' ? 'required' : ''; ?>>
                        <small class="form__help">Name as it appears on the card</small>
                    </div>
                    
                    <div class="form__group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" value="<?php echo isset($card_number) ? htmlspecialchars($card_number) : ''; ?>" maxlength="19" <?php echo isset($payment_method) && $payment_method === 'credit_card' ? 'required' : ''; ?>>
                        <small class="form__help">Enter 13-19 digit card number</small>
                    </div>
                    
                    <div class="form__row">
                        <div class="form__group">
                            <label for="card_expiry">Expiry Date</label>
                            <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" value="<?php echo isset($card_expiry) ? htmlspecialchars($card_expiry) : ''; ?>" maxlength="5" <?php echo isset($payment_method) && $payment_method === 'credit_card' ? 'required' : ''; ?>>
                        </div>
                        <div class="form__group">
                            <label for="card_cvc">CVC</label>
                            <input type="text" id="card_cvc" name="card_cvc" placeholder="123" value="<?php echo isset($card_cvc) ? htmlspecialchars($card_cvc) : ''; ?>" maxlength="4" <?php echo isset($payment_method) && $payment_method === 'credit_card' ? 'required' : ''; ?>>
                            <small class="form__help">3-4 digits on back of card</small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit__button">Make Reservation</button>
            </form>
        </section>
    </main>
</div>

<style>
/* General Dashboard Styles */
.dashboard__container {
    display: flex;
    min-height: 100vh;
    background: #f3f4f6;
}

.dashboard__content {
    flex: 1;
    padding: 2rem;
}

.section__header {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.reservation__section {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.reservation__form {
    display: grid;
    gap: 1.5rem;
}

.form__group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form__group label {
    font-size: 1rem;
    font-weight: 500;
    color: #1f2937;
}

.form__group input,
.form__group select {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    width: 100%;
    box-sizing: border-box;
}

.form__group input:focus,
.form__group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form__help {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.form__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.submit__button {
    background: #3b82f6;
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
}

.submit__button:hover {
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
    border: 1px solid #fecaca;
}

.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.error ul {
    margin: 0;
    padding-left: 1.5rem;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: #1f2937;
    color: white;
    transition: width 0.3s ease;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar.collapsed .sidebar__title,
.sidebar.collapsed .sidebar__link span {
    display: none;
}

.sidebar__header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar__logo {
    width: 40px;
    height: 40px;
}

.sidebar__title {
    font-size: 1.25rem;
    font-weight: 600;
}

.sidebar__toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
}

.sidebar__nav {
    padding: 1rem;
}

.sidebar__links {
    list-style: none;
    padding: 0;
}

.sidebar__link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    transition: background 0.2s ease;
}

.sidebar__link:hover,
.sidebar__link.active {
    background: #3b82f6;
}

.sidebar__link i {
    font-size: 1.25rem;
}

/* Header Styles */
.dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.user__info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user__avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        width: 80px;
    }
    
    .sidebar__title,
    .sidebar__link span {
        display: none;
    }
    
    .dashboard__content {
        padding: 1rem;
    }
    
    .form__row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const roomOccupancyLimits = {
        'single': 2,
        'double': 4,
        'suite': 6
    };

    // Toggle sidebar
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line');
    });

    // Update occupancy limit based on room type
    window.updateOccupancyLimit = function() {
        const roomType = document.getElementById('room_type').value;
        const occupantsInput = document.getElementById('occupants');
        const occupancyHelp = document.getElementById('occupancy-help');
        
        if (roomType && roomOccupancyLimits[roomType]) {
            const maxOccupants = roomOccupancyLimits[roomType];
            occupantsInput.setAttribute('max', maxOccupants);
            occupancyHelp.textContent = `Maximum occupancy for ${roomType} room: ${maxOccupants} guests`;
            
            if (parseInt(occupantsInput.value) > maxOccupants) {
                occupantsInput.value = maxOccupants;
            }
        } else {
            occupantsInput.setAttribute('max', '6');
            occupancyHelp.textContent = 'Maximum occupancy depends on room type';
        }
    };

    // Toggle payment fields and warning message
    window.togglePaymentFields = function() {
        const paymentMethod = document.getElementById('payment_method').value;
        const creditCardFields = document.getElementById('credit_card_fields');
        const paymentWarning = document.getElementById('payment-warning');
        const cardInputs = creditCardFields.querySelectorAll('input');

        if (paymentMethod === 'credit_card') {
            creditCardFields.style.display = 'block';
            paymentWarning.style.display = 'none';
            cardInputs.forEach(input => input.setAttribute('required', ''));
        } else {
            creditCardFields.style.display = 'none';
            paymentWarning.style.display = 'block';
            cardInputs.forEach(input => input.removeAttribute('required'));
        }
    };

    // Function to reset form to default values
    function resetFormToDefault() {
        const form = document.querySelector('.reservation__form');
        if (form) {
            // Reset all form fields to default values
            form.reset();
            
            // Set specific default values
            document.getElementById('branch_id').value = '';
            document.getElementById('room_type').value = '';
            document.getElementById('check_in_date').value = '';
            document.getElementById('check_out_date').value = '';
            document.getElementById('occupants').value = '1';
            document.getElementById('payment_method').value = 'credit_card';
            
            // Clear credit card fields
            document.getElementById('cardholder_name').value = '';
            document.getElementById('card_number').value = '';
            document.getElementById('card_expiry').value = '';
            document.getElementById('card_cvc').value = '';
            
            // Reset min date for check-in to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('check_in_date').setAttribute('min', today);
            
            // Reset min date for check-out to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('check_out_date').setAttribute('min', tomorrow.toISOString().split('T')[0]);
            
            // Reset occupancy limit display
            const occupancyHelp = document.getElementById('occupancy-help');
            occupancyHelp.textContent = 'Maximum occupancy depends on room type';
            document.getElementById('occupants').setAttribute('max', '6');
            
            // Show credit card fields by default and hide payment warning
            document.getElementById('credit_card_fields').style.display = 'block';
            document.getElementById('payment-warning').style.display = 'none';
            
            // Set required attributes for credit card fields
            const cardInputs = document.getElementById('credit_card_fields').querySelectorAll('input');
            cardInputs.forEach(input => input.setAttribute('required', ''));
            
            console.log('Form reset to default values');
        }
    }

    // Check if there's a success message and reset form after successful reservation
    const successMessage = document.querySelector('.success');
    if (successMessage) {
        // Add a small delay to allow user to see the success message
        setTimeout(function() {
            resetFormToDefault();
            
            // Optional: Show a brief notification that form has been reset
            const resetNotification = document.createElement('div');
            resetNotification.innerHTML = '<small style="color: #059669; font-style: italic;">Form has been reset for your next reservation</small>';
            resetNotification.style.cssText = 'margin-top: 10px; padding: 5px; background: #ecfdf5; border-radius: 4px; border-left: 3px solid #10b981;';
            
            successMessage.appendChild(resetNotification);
            
            // Remove the reset notification after 3 seconds
            setTimeout(function() {
                if (resetNotification.parentNode) {
                    resetNotification.remove();
                }
            }, 3000);
            
        }, 2000); // Wait 2 seconds before resetting
        
        // Hide the success message after 5 seconds total
        setTimeout(function() {
            if (successMessage.parentNode) {
                // Add fade out animation
                successMessage.style.transition = 'opacity 0.5s ease-out';
                successMessage.style.opacity = '0';
                
                // Remove the element after fade out
                setTimeout(function() {
                    if (successMessage.parentNode) {
                        successMessage.remove();
                    }
                }, 500);
            }
        }, 5000); // Wait 5 seconds before hiding success message
    }

    // Card number formatting
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            if (value.length > 19) {
                value = value.substring(0, 19);
            }
            e.target.value = value;
        });
    }

    // Expiry date formatting
    const cardExpiryInput = document.getElementById('card_expiry');
    if (cardExpiryInput) {
        cardExpiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }

    // CVC input - numbers only
    const cardCvcInput = document.getElementById('card_cvc');
    if (cardCvcInput) {
        cardCvcInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '');
        });
    }

    // Date validation
    const checkInDate = document.getElementById('check_in_date');
    const checkOutDate = document.getElementById('check_out_date');
    
    if (checkInDate && checkOutDate) {
        checkInDate.addEventListener('change', function() {
            const checkInDate = new Date(this.value);
            const minCheckOut = new Date(checkInDate);
            minCheckOut.setDate(minCheckOut.getDate() + 1);
            checkOutDate.min = minCheckOut.toISOString().split('T')[0];
            
            if (checkOutDate.value && new Date(checkOutDate.value) <= checkInDate) {
                checkOutDate.value = '';
            }
        });
    }

    // Form validation before submission
    const reservationForm = document.querySelector('.reservation__form');
    if (reservationForm) {
        reservationForm.addEventListener('submit', function(e) {
            const errors = [];
            const paymentMethod = document.getElementById('payment_method').value;
            
            // Check dates
            const checkIn = new Date(checkInDate.value);
            const checkOut = new Date(checkOutDate.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (checkIn < today) {
                errors.push('Check-in date cannot be in the past');
            }
            
            if (checkOut <= checkIn) {
                errors.push('Check-out date must be after check-in date');
            }
            
            // Check occupancy limits
            const roomType = document.getElementById('room_type').value;
            const occupants = parseInt(document.getElementById('occupants').value);
            
            if (roomType && roomOccupancyLimits[roomType] && occupants > roomOccupancyLimits[roomType]) {
                errors.push(`Maximum occupancy for ${roomType} room is ${roomOccupancyLimits[roomType]} guests`);
            }
            
            // Credit card validation only if payment method is credit_card
            if (paymentMethod === 'credit_card') {
                const creditCardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                const creditCardExpiry = document.getElementById('card_expiry').value;
                const creditCardCvc = document.getElementById('card_cvc').value;
                const creditCardHolderName = document.getElementById('cardholder_name').value.trim();
                
                if (!creditCardHolderName || creditCardHolderName.length < 2) {
                    errors.push('Please provide a valid cardholder name');
                }
                
                if (!creditCardNumber || !/^\d{13,19}$/.test(creditCardNumber)) {
                    errors.push('Please provide a valid credit card number');
                }
                
                if (!creditCardExpiry || !/^(0[1-9]|1[0-2])\/\d{2}$/.test(creditCardExpiry)) {
                    errors.push('Please provide a valid expiry date (MM/YY)');
                } else {
                    const [month, year] = creditCardExpiry.split('/');
                    const currentYear = new Date().getFullYear() % 100;
                    const currentMonth = new Date().getMonth() + 1;
                    
                    if (parseInt(year) < currentYear || 
                        (parseInt(year) === currentYear && parseInt(month) < currentMonth)) {
                        errors.push('Credit card has expired');
                    }
                }
                
                if (!creditCardCvc || !/^\d{3,4}$/.test(creditCardCvc)) {
                    errors.push('Please provide a valid CVC (3-4 digits)');
                }
            }
            
            // Display errors if any
            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
            }
        });
    }

    // Initialize states
    updateOccupancyLimit();
    togglePaymentFields();
});
</script>
</body>
</html>