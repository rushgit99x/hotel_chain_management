<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to super admin only
require_once 'includes/functions.php';
checkAuth('super_admin');

// Include database connection
require_once 'db_connect.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_branch'])) {
        $branch_id = (int)$_POST['branch_id'];
        $branch_name = sanitize($_POST['branch_name']);
        $location = sanitize($_POST['location']);
        try {
            $stmt = $pdo->prepare("UPDATE branches SET name = ?, location = ? WHERE id = ?");
            $stmt->execute([$branch_name, $location, $branch_id]);
            $success = "Branch updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating branch: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_branch'])) {
        $branch_id = (int)$_POST['branch_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
            $stmt->execute([$branch_id]);
            $success = "Branch deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting branch: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_room'])) {
        $room_id = (int)$_POST['room_id'];
        $room_number = sanitize($_POST['room_number']);
        $type = $_POST['type'];
        $price = floatval($_POST['price']);
        try {
            $stmt = $pdo->prepare("UPDATE rooms SET room_number = ?, type = ?, price = ? WHERE id = ?");
            $stmt->execute([$room_number, $type, $price, $room_id]);
            $success = "Room updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating room: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_room'])) {
        $room_id = (int)$_POST['room_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
            $stmt->execute([$room_id]);
            $success = "Room deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting room: " . $e->getMessage();
        }
    }
}

// Fetch all branches and rooms
try {
    $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT r.*, b.name AS branch_name FROM rooms r JOIN branches b ON r.branch_id = b.id ORDER BY b.name, r.room_number");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
                <li><a href="manage_hotels.php" class="sidebar__link active"><i class="ri-building-line"></i><span>Manage Hotels</span></a></li>
                <li><a href="manage_users.php" class="sidebar__link"><i class="ri-user-line"></i><span>Manage Users</span></a></li>
                <li><a href="manage_bookings.php" class="sidebar__link"><i class="ri-calendar-check-line"></i><span>Manage Bookings</span></a></li>
                <li><a href="reports.php" class="sidebar__link"><i class="ri-bar-chart-line"></i><span>Reports</span></a></li>
                <li><a href="settings.php" class="sidebar__link"><i class="ri-settings-3-line"></i><span>Settings</span></a></li>
                <li><a href="logout.php" class="sidebar__link"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Manage Hotels</h1>
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
        <?php elseif (isset($success)): ?>
            <div class="alert alert--success">
                <i class="ri-check-line"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <section id="manage-branches" class="dashboard__section active">
            <h2 class="section__subheader">Manage Branches</h2>
            <div class="table__container">
                <table class="data__table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $branch): ?>
                            <tr>
                                <td><?php echo $branch['id']; ?></td>
                                <td><?php echo htmlspecialchars($branch['name']); ?></td>
                                <td><?php echo htmlspecialchars($branch['location']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="branch_id" value="<?php echo $branch['id']; ?>">
                                        <input type="text" name="branch_name" value="<?php echo htmlspecialchars($branch['name']); ?>" required>
                                        <input type="text" name="location" value="<?php echo htmlspecialchars($branch['location']); ?>" required>
                                        <button type="submit" name="update_branch" class="btn btn--primary">Update</button>
                                        <button type="submit" name="delete_branch" class="btn btn--danger" onclick="return confirm('Are you sure you want to delete this branch?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section id="manage-rooms" class="dashboard__section">
            <h2 class="section__subheader">Manage Rooms</h2>
            <div class="table__container">
                <table class="data__table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Branch</th>
                            <th>Room Number</th>
                            <th>Type</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td><?php echo $room['id']; ?></td>
                                <td><?php echo htmlspecialchars($room['branch_name']); ?></td>
                                <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($room['type']); ?></td>
                                <td>$<?php echo number_format($room['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($room['status']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="room_id" value="<?php echo $room['id']; ?>">
                                        <input type="text" name="room_number" value="<?php echo htmlspecialchars($room['room_number']); ?>" required>
                                        <select name="type" required>
                                            <option value="single" <?php echo $room['type'] == 'single' ? 'selected' : ''; ?>>Single</option>
                                            <option value="double" <?php echo $room['type'] == 'double' ? 'selected' : ''; ?>>Double</option>
                                            <option value="suite" <?php echo $room['type'] == 'suite' ? 'selected' : ''; ?>>Suite</option>
                                        </select>
                                        <input type="number" name="price" step="0.01" min="0" value="<?php echo $room['price']; ?>" required>
                                        <button type="submit" name="update_room" class="btn btn--primary">Update</button>
                                        <button type="submit" name="delete_room" class="btn btn--danger" onclick="return confirm('Are you sure you want to delete this room?');">Delete</button>
                                    </form>
                                </td>
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
.btn--danger {
    background: #ef4444;
    color: white;
}
.btn--danger:hover {
    background: #dc2626;
}
</style>

<script>
function showSection(sectionId) {
    const sections = document.querySelectorAll('.dashboard__section');
    sections.forEach(section => section.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');

    const links = document.querySelectorAll('.sidebar__link');
    links.forEach(link => link.classList.remove('active'));
    document.querySelector(`.sidebar__link[href="#${sectionId}"]`)?.classList.add('active');
}

document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});
</script>

<?php include 'templates/footer.php'; ?>