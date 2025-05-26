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
include_once 'includes/functions.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_branch'])) {
        $branch_name = sanitize($_POST['branch_name']);
        $location = sanitize($_POST['location']);
        try {
            $stmt = $pdo->prepare("INSERT INTO branches (name, location) VALUES (?, ?)");
            $stmt->execute([$branch_name, $location]);
            $success = "Branch created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating branch: " . $e->getMessage();
        }
    } elseif (isset($_POST['create_user'])) {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $branch_id = $_POST['branch_id'] ?: null;

        if ($role !== 'manager' && $role !== 'customer' && $role !== 'travel_company') {
            $error = "Invalid role selected.";
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, branch_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $password, $role, $branch_id]);
                $success = ucfirst($role) . " created successfully!";
            } catch (PDOException $e) {
                $error = "Error creating user: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['create_room'])) {
        $branch_id = $_POST['branch_id'];
        $room_type_id = $_POST['room_type_id'];
        $room_number = sanitize($_POST['room_number']);
        $status = $_POST['status'] ?: 'available';

        try {
            $stmt = $pdo->prepare("INSERT INTO rooms (branch_id, room_type_id, room_number, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$branch_id, $room_type_id, $room_number, $status]);
            $success = "Room created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating room: " . $e->getMessage();
        }
    } elseif (isset($_POST['create_room_type'])) {
        $name = sanitize($_POST['room_type_name']);
        $description = sanitize($_POST['description']);
        $base_price = floatval($_POST['base_price']);
        $image_path = sanitize($_POST['image_path']) ?: null;

        try {
            $stmt = $pdo->prepare("INSERT INTO room_types (name, description, base_price, image_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $base_price, $image_path]);
            $success = "Room type created successfully!";
        } catch (PDOException $e) {
            $error = "Error creating room type: " . $e->getMessage();
        }
    }
}

// Dashboard metrics
$total_branches = 0;
$total_users = 0;
$total_bookings = 0;
$total_rooms = 0;
$total_reservations = 0;
$total_managers = 0;
$total_customers = 0;
$total_travel_companies = 0;
$total_room_types = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM branches");
    $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role != 'super_admin'");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'manager'");
    $total_managers = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
    $total_customers = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'travel_company'");
    $total_travel_companies = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rooms");
    $total_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM room_types");
    $total_room_types = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bookings");
    $total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations");
    $total_reservations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $total_branches = $total_users = $total_bookings = $total_rooms = $total_reservations = 0;
    $total_managers = $total_customers = $total_travel_companies = $total_room_types = 0;
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
                    <a href="#overview" class="sidebar__link active" onclick="showSection('overview')">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="#create-branch" class="sidebar__link" onclick="showSection('create-branch')">
                        <i class="ri-building-line"></i>
                        <span>Create Branch</span>
                    </a>
                </li>
                <li>
                    <a href="#create-user" class="sidebar__link" onclick="showSection('create-user')">
                        <i class="ri-user-add-line"></i>
                        <span>Create User</span>
                    </a>
                </li>
                <li>
                    <a href="#create-room" class="sidebar__link" onclick="showSection('create-room')">
                        <i class="ri-home-line"></i>
                        <span>Add Room</span>
                    </a>
                </li>
                <li>
                    <a href="manage_rooms.php" class="sidebar__link">
                        <i class="ri-home-gear-line"></i>
                        <span>Manage Rooms</span>
                    </a>
                </li>
                <li>
                    <a href="#create-room-type" class="sidebar__link" onclick="showSection('create-room-type')">
                        <i class="ri-home-2-line"></i>
                        <span>Manage Room Types</span>
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
            <h1 class="section__header">Super Admin Dashboard</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Success/Error Messages -->
        <?php if (isset($error)): ?>
            <div class="alert alert--error">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php elseif (isset($success)): ?>
            <div class="alert alert--success">
                <i class="ri-check-line"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Overview Section -->
        <section id="overview" class="dashboard__section active">
            <h2 class="section__subheader">Overview</h2>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-building-2-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Branches</h3>
                        <p><?php echo $total_branches; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Rooms</h3>
                        <p><?php echo $total_rooms; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-2-line card__icon"></i>
                    <div class="card__content">
                        <h3>Room Types</h3>
                        <p><?php echo $total_room_types; ?></p>
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
                    <i class="ri-user-settings-line card__icon"></i>
                    <div class="card__content">
                        <h3>Managers</h3>
                        <p><?php echo $total_managers; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-user-heart-line card__icon"></i>
                    <div class="card__content">
                        <h3>Customers</h3>
                        <p><?php echo $total_customers; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-building-3-line card__icon"></i>
                    <div class="card__content">
                        <h3>Travel Companies</h3>
                        <p><?php echo $total_travel_companies; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-calendar-check-line card__icon"></i>
                    <div class="card__content">
                        <h3>Bookings</h3>
                        <p><?php echo $total_bookings; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-calendar-line card__icon"></i>
                    <div class="card__content">
                        <h3>Reservations</h3>
                        <p><?php echo $total_reservations; ?></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Create Branch Section -->
        <section id="create-branch" class="dashboard__section">
            <h2 class="section__subheader">Create New Branch</h2>
            <div class="form__container">
                <form method="POST" class="admin__form">
                    <div class="form__group">
                        <label for="branch_name" class="form__label">Branch Name</label>
                        <input type="text" id="branch_name" name="branch_name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="location" class="form__label">Location</label>
                        <input type="text" id="location" name="location" class="form__input" required>
                    </div>
                    <button type="submit" name="create_branch" class="btn btn--primary">
                        <i class="ri-add-line"></i>
                        Create Branch
                    </button>
                </form>
            </div>
        </section>

        <!-- Create User Section -->
        <section id="create-user" class="dashboard__section">
            <h2 class="section__subheader">Create Manager/Clerk</h2>
            <div class="form__container">
                <form method="POST" class="admin__form">
                    <div class="form__group">
                        <label for="name" class="form__label">Full Name</label>
                        <input type="text" id="name" name="name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="email" class="form__label">Email Address</label>
                        <input type="email" id="email" name="email" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="password" class="form__label">Password</label>
                        <input type="password" id="password" name="password" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="role" class="form__label">Role</label>
                        <select id="role" name="role" class="form__select" required>
                            <option value="">Select Role</option>
                            <option value="manager">Manager</option>
                            <option value="clerk">Clerk</option>

                        </select>
                    </div>
                    <div class="form__group">
                        <label for="branch_id" class="form__label">Assign to Branch</label>
                        <select id="branch_id" name="branch_id" class="form__select">
                            <option value="">Select Branch (Optional)</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
                                while ($branch = $stmt->fetch()) {
                                    echo "<option value='{$branch['id']}'>{$branch['name']} ({$branch['location']})</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading branches</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <button type="submit" name="create_user" class="btn btn--primary">
                        <i class="ri-user-add-line"></i>
                        Create User
                    </button>
                </form>
            </div>
        </section>

        <!-- Create Room Section -->
        <section id="create-room" class="dashboard__section">
            <h2 class="section__subheader">Add New Room</h2>
            <div class="form__container">
                <form method="POST" class="admin__form">
                    <div class="form__group">
                        <label for="room_branch_id" class="form__label">Branch</label>
                        <select id="room_branch_id" name="branch_id" class="form__select" required>
                            <option value="">Select Branch</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
                                while ($branch = $stmt->fetch()) {
                                    echo "<option value='{$branch['id']}'>{$branch['name']} ({$branch['location']})</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading branches</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form__group">
                        <label for="room_type_id" class="form__label">Room Type</label>
                        <select id="room_type_id" name="room_type_id" class="form__select" required>
                            <option value="">Select Room Type</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM room_types ORDER BY name");
                                while ($room_type = $stmt->fetch()) {
                                    echo "<option value='{$room_type['id']}'>{$room_type['name']} (\${$room_type['base_price']}/night)</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading room types</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form__group">
                        <label for="room_number" class="form__label">Room Number</label>
                        <input type="text" id="room_number" name="room_number" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="status" class="form__label">Status</label>
                        <select id="status" name="status" class="form__select" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <button type="submit" name="create_room" class="btn btn--primary">
                        <i class="ri-home-add-line"></i>
                        Add Room
                    </button>
                </form>
            </div>
        </section>

        <!-- Create Room Type Section -->
        <section id="create-room-type" class="dashboard__section">
            <h2 class="section__subheader">Manage Room Types</h2>
            <div class="form__container">
                <form method="POST" class="admin__form">
                    <div class="form__group">
                        <label for="room_type_name" class="form__label">Room Type Name</label>
                        <input type="text" id="room_type_name" name="room_type_name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="description" class="form__label">Description</label>
                        <textarea id="description" name="description" class="form__input" rows="4"></textarea>
                    </div>
                    <div class="form__group">
                        <label for="base_price" class="form__label">Base Price per Night ($)</label>
                        <input type="number" id="base_price" name="base_price" step="0.01" min="0" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="image_path" class="form__label">Image Path (Optional)</label>
                        <input type="text" id="image_path" name="image_path" class="form__input">
                    </div>
                    <button type="submit" name="create_room_type" class="btn btn--primary">
                        <i class="ri-home-2-line"></i>
                        Create Room Type
                    </button>
                </form>
            </div>
        </section>
    </main>
</div>

<style>
/* Additional styles for the enhanced dashboard */
.dashboard__section {
    display: none;
    animation: fadeIn 0.3s ease-in-out;
}

.dashboard__section.active {
    display: block;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
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

.form__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-width: 600px;
    margin-top: 1.5rem;
}

.admin__form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form__group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form__label {
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.form__input,
.form__select,
.form__input[type="textarea"] {
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form__input:focus,
.form__select:focus,
.form__input[type="textarea"]:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn--primary {
    background: #3b82f6;
    color: white;
}

.btn--primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn--secondary {
    background: #6b7280;
    color: white;
}

.btn--secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.btn--danger {
    background: #ef4444;
    color: white;
}

.btn--danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn--small {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.5rem;
}

.table th,
.table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.table td {
    color: #1f2937;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.alert--success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert--error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.sidebar__link.active {
    background: #3b82f6;
    color: white;
}

.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}
</style>

<script>
function showSection(sectionId) {
    const sections = document.querySelectorAll('.dashboard__section');
    sections.forEach(section => section.classList.remove('active'));
    const targetSection = document.getElementById(sectionId);
    if (targetSection) targetSection.classList.add('active');
    
    const links = document.querySelectorAll('.sidebar__link');
    links.forEach(link => link.classList.remove('active'));
    event.target.closest('.sidebar__link').classList.add('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });
});
</script>

<?php include 'templates/footer.php'; ?>