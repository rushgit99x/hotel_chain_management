<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to super admin only
require_once 'includes/functions.php';
checkAuth('super_admin');

// Include database connection
require_once 'db_connect.php';

// Initialize variables
$success = $error = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_branch'])) {
        $branch_id = (int)$_POST['branch_id'];
        $branch_name = sanitize($_POST['branch_name']);
        $location = sanitize($_POST['location']);

        // Validate inputs
        if (empty($branch_name) || empty($location)) {
            $error = "Branch name and location are required.";
        } else {
            try {
                // Check for duplicate branch name
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM branches WHERE name = ? AND id != ?");
                $stmt->execute([$branch_name, $branch_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Branch name already exists.";
                } else {
                    $stmt = $pdo->prepare("UPDATE branches SET name = ?, location = ? WHERE id = ?");
                    $stmt->execute([$branch_name, $location, $branch_id]);
                    $success = "Branch updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error updating branch: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_branch'])) {
        $branch_id = (int)$_POST['branch_id'];
        try {
            // Check if branch has associated rooms or bookings
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE branch_id = ?");
            $stmt->execute([$branch_id]);
            $room_count = $stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE branch_id = ?");
            $stmt->execute([$branch_id]);
            $booking_count = $stmt->fetchColumn();
            if ($room_count > 0 || $booking_count > 0) {
                $error = "Cannot delete branch with existing rooms or bookings.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM branches WHERE id = ?");
                $stmt->execute([$branch_id]);
                $success = "Branch deleted successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error deleting branch: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_room'])) {
        $room_id = (int)$_POST['room_id'];
        $room_number = sanitize($_POST['room_number']);
        $room_type_id = (int)$_POST['room_type_id'];
        $status = sanitize($_POST['status']);

        // Validate inputs
        if (empty($room_number) || !in_array($status, ['available', 'occupied', 'maintenance'])) {
            $error = "Valid room number and status are required.";
        } else {
            try {
                // Check for duplicate room number within the same branch
                $stmt = $pdo->prepare("SELECT branch_id FROM rooms WHERE id = ?");
                $stmt->execute([$room_id]);
                $branch_id = $stmt->fetchColumn();
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_number = ? AND branch_id = ? AND id != ?");
                $stmt->execute([$room_number, $branch_id, $room_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Room number already exists in this branch.";
                } else {
                    $stmt = $pdo->prepare("UPDATE rooms SET room_number = ?, room_type_id = ?, status = ? WHERE id = ?");
                    $stmt->execute([$room_number, $room_type_id, $status, $room_id]);
                    $success = "Room updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error updating room: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_room'])) {
        $room_id = (int)$_POST['room_id'];
        try {
            // Check if room has associated bookings
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $booking_count = $stmt->fetchColumn();
            if ($booking_count > 0) {
                $error = "Cannot delete room with existing bookings.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
                $stmt->execute([$room_id]);
                $success = "Room deleted successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error deleting room: " . $e->getMessage();
        }
    }
}

// Fetch all branches, rooms, and room types
try {
    $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT r.*, b.name AS branch_name, rt.name AS room_type_name, rt.base_price 
                         FROM rooms r 
                         JOIN branches b ON r.branch_id = b.id 
                         LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                         ORDER BY b.name, r.room_number");
    $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->query("SELECT * FROM room_types ORDER BY name");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $branches = $rooms = $room_types = [];
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Hotels</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" loading="lazy" />
            <h2 class="sidebar__title">Super Admin</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li><a href="admin_dashboard.php" class="sidebar__link"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="create_branch.php" class="sidebar__link"><i class="ri-building-line"></i><span>Create Branch</span></a></li>
                <li><a href="create_user.php" class="sidebar__link"><i class="ri-user-add-line"></i><span>Create User</span></a></li>
                <li><a href="create_room.php" class="sidebar__link"><i class="ri-home-line"></i><span>Add Room</span></a></li>
                <li><a href="manage_rooms.php" class="sidebar__link"><i class="ri-home-gear-line"></i><span>Manage Rooms</span></a></li>
                <li><a href="create_room_type.php" class="sidebar__link"><i class="ri-home-2-line"></i><span>Manage Room Types</span></a></li>
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
            <button class="mobile-sidebar-toggle" id="mobile-sidebar-toggle">
                <i class="ri-menu-line"></i>
            </button>
            <h1 class="section__header">Manage Hotels</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" loading="lazy" />
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

        <!-- Manage Branches Section -->
        <section id="manage-branches" class="dashboard__section active">
            <h2 class="section__subheader">Manage Branches</h2>
            <div class="table__container">
                <?php if (empty($branches)): ?>
                    <p class="text-muted text-center">No branches available.</p>
                <?php else: ?>
                    <div class="table__wrapper">
                        <table class="data__table">
                            <thead>
                                <tr>
                                    <th><i class="ri-hashtag me-1"></i>ID</th>
                                    <th><i class="ri-building-line me-1"></i>Name</th>
                                    <th><i class="ri-map-pin-line me-1"></i>Location</th>
                                    <th><i class="ri-settings-3-line me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($branches as $branch): ?>
                                    <tr>
                                        <td><span class="table__badge table__badge--light"><?php echo $branch['id']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($branch['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($branch['location']); ?></td>
                                        <td>
                                            <div class="btn__group">
                                                <button type="button" class="btn btn--small btn--primary" onclick="openEditBranchModal(<?php echo $branch['id']; ?>, '<?php echo addslashes(htmlspecialchars($branch['name'])); ?>', '<?php echo addslashes(htmlspecialchars($branch['location'])); ?>')" title="Edit Branch"><i class="ri-edit-line"></i></button>
                                                <button type="button" class="btn btn--small btn--danger" onclick="confirmDelete('branch', <?php echo $branch['id']; ?>)" title="Delete Branch"><i class="ri-delete-bin-line"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Manage Rooms Section -->
        <section id="manage-rooms" class="dashboard__section">
            <h2 class="section__subheader">Manage Rooms</h2>
            <div class="table__container">
                <?php if (empty($rooms)): ?>
                    <p class="text-muted text-center">No rooms available.</p>
                <?php else: ?>
                    <div class="table__wrapper">
                        <table class="data__table">
                            <thead>
                                <tr>
                                    <th><i class="ri-hashtag me-1"></i>ID</th>
                                    <th><i class="ri-building-line me-1"></i>Branch</th>
                                    <th><i class="ri-home-line me-1"></i>Room Number</th>
                                    <th><i class="ri-home-2-line me-1"></i>Room Type</th>
                                    <th><i class="ri-money-dollar-circle-line me-1"></i>Price</th>
                                    <th><i class="ri-checkbox-circle-line me-1"></i>Status</th>
                                    <th><i class="ri-settings-3-line me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><span class="table__badge table__badge--light"><?php echo $room['id']; ?></span></td>
                                        <td><?php echo htmlspecialchars($room['branch_name']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($room['room_number']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($room['room_type_name'] ?? 'Not Assigned'); ?></td>
                                        <td><span class="table__badge table__badge--info"><?php echo isset($room['base_price']) ? '$' . number_format($room['base_price'], 2) : 'N/A'; ?></span></td>
                                        <td><?php echo htmlspecialchars($room['status']); ?></td>
                                        <td>
                                            <div class="btn__group">
                                                <button type="button" class="btn btn--small btn--primary" onclick="openEditRoomModal(<?php echo $room['id']; ?>, '<?php echo addslashes(htmlspecialchars($room['room_number'])); ?>', <?php echo $room['room_type_id'] ?? 0; ?>, '<?php echo $room['status']; ?>')" title="Edit Room"><i class="ri-edit-line"></i></button>
                                                <button type="button" class="btn btn--small btn--danger" onclick="confirmDelete('room', <?php echo $room['id']; ?>)" title="Delete Room"><i class="ri-delete-bin-line"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<!-- Edit Branch Modal -->
<div class="modal" id="editBranchModal">
    <div class="modal__dialog">
        <div class="modal__content">
            <div class="modal__header">
                <h5 class="modal__title"><i class="ri-edit-line me-2"></i>Edit Branch</h5>
                <button type="button" class="modal__close" onclick="closeModal('editBranchModal')"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal__body">
                <form method="POST" id="editBranchForm">
                    <input type="hidden" name="branch_id" id="editBranchId">
                    <div class="form__group">
                        <label for="editBranchName" class="form__label">Branch Name</label>
                        <input type="text" id="editBranchName" name="branch_name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="editBranchLocation" class="form__label">Location</label>
                        <input type="text" id="editBranchLocation" name="location" class="form__input" required>
                    </div>
                </form>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('editBranchModal')">Cancel</button>
                <button type="submit" form="editBranchForm" name="update_branch" class="btn btn--primary">
                    <i class="ri-save-line me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Room Modal -->
<div class="modal" id="editRoomModal">
    <div class="modal__dialog">
        <div class="modal__content">
            <div class="modal__header">
                <h5 class="modal__title"><i class="ri-edit-line me-2"></i>Edit Room</h5>
                <button type="button" class="modal__close" onclick="closeModal('editRoomModal')"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal__body">
                <form method="POST" id="editRoomForm">
                    <input type="hidden" name="room_id" id="editRoomId">
                    <div class="form__group">
                        <label for="editRoomNumber" class="form__label">Room Number</label>
                        <input type="text" id="editRoomNumber" name="room_number" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="editRoomType" class="form__label">Room Type</label>
                        <select id="editRoomType" name="room_type_id" class="form__select" required>
                            <option value="0">Not Assigned</option>
                            <?php foreach ($room_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form__group">
                        <label for="editRoomStatus" class="form__label">Status</label>
                        <select id="editRoomStatus" name="status" class="form__select" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('editRoomModal')">Cancel</button>
                <button type="submit" form="editRoomForm" name="update_room" class="btn btn--primary">
                    <i class="ri-save-line me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal__dialog">
        <div class="modal__content">
            <div class="modal__header">
                <h5 class="modal__title"><i class="ri-error-warning-line me-2"></i>Confirm Deletion</h5>
                <button type="button" class="modal__close" onclick="closeModal('deleteModal')"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal__body">
                <p>Are you sure you want to delete this <span id="deleteType"></span>? This action cannot be undone.</p>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <form method="POST" style="display:inline;" id="deleteForm">
                    <input type="hidden" name="branch_id" id="deleteBranchId">
                    <input type="hidden" name="room_id" id="deleteRoomId">
                    <button type="submit" name="" id="deleteButton" class="btn btn--danger">
                        <i class="ri-delete-bin-line me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* General Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Dashboard Container */
.dashboard__container {
    display: flex;
    min-height: 100vh;
    background: #f3f4f6;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: #000000;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    position: fixed;
    height: 100vh;
    z-index: 1000;
    transform: translateX(0);
}

.sidebar.collapsed {
    transform: translateX(-250px);
}

.sidebar__header {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #333333;
}

.sidebar__logo {
    width: 40px;
    height: 40px;
    margin-right: 0.5rem;
}

.sidebar__title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #ffffff;
}

.sidebar__toggle {
    background: none;
    border: none;
    cursor: pointer;
    margin-left: auto;
    font-size: 1.5rem;
    color: #3b82f6;
}

.sidebar__nav {
    padding: 1rem 0;
}

.sidebar__links {
    list-style: none;
}

.sidebar__link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #ffffff;
    text-decoration: none;
    font-size: 0.95rem;
    transition: background 0.2s ease;
}

.sidebar__link:hover {
    background: #333333;
}

.sidebar__link.active {
    background: #3b82f6;
    color: #ffffff;
}

.sidebar__link i {
    font-size: 1.25rem;
    margin-right: 0.75rem;
}

/* Main Content */
.dashboard__content {
    margin-left: 250px;
    padding: 1.5rem;
    flex-grow: 1;
    transition: margin-left 0.3s ease;
}

/* Header */
.dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.mobile-sidebar-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #3b82f6;
    cursor: pointer;
    padding: 0.5rem;
}

.section__header {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
}

.user__info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.user__avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
}

/* Form Styles */
.form__container {
    background: white;
    padding: 100%;
    width: 600px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 1.5rem;
}

.admin__form {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.form__group {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.form__label {
    font-weight: 600;
    color: #374151;
    font-size: 0.25rem;
}

.form__input,
.form__select {
    padding: 0.5rem;
    background-color: #ffffff;
    border: 2px solid #333333;
    color: #000000;
    border-radius: 8px;
    width: 100%;
    height: 40px;
    font-size: 1rem;
    transition: border-color 0.3s ease, box-shadow:0.3s ease;
}

.form__input:focus,
.form__select:focus {
    outline: 2px solid #000000;
    border-color: #333333;
    box-shadow: 0 0 0px 3px rgba(0, 0, 0);
}

/* Button Styles */
.btn {
    padding: 0.25rem;
    border: none;
    background-color: #333333;
    color: #ffffff;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-block;
    align-items: center;
    gap: 1rem;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn--primary {
    background: #3b82f6;
    color: white;
}

.btn--primary:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.btn--primary:hover:not(:disabled) {
    background: #2563eb;
}

.btn--secondary {
    background: #666633;
    color: white;
}

.btn--secondary:hover {
    background: #4b5563;
}

.btn--danger {
    background: #ef4444;
    color: white;
}

.btn--danger:hover {
    background: #dc2626;
}

.btn--small {
    padding: 0.25rem;
    background-color: #333333;
    font-size: 0.9rem;
}

.btn__group {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* Alert Styles */
.alert {
    padding: 0.25rem;
    border-radius: 1px;
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    background-color: #ffffff;
    font-weight: bold;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.alert--success {
    background: #d1fae5;
    color: #065f46;
    border: 2px solid #333333;
}

.alert--error {
    background: #fee2e2;
    color: #991b1;
    border: 2px solid #333333;
}

/* Table Styles */
.table__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 10px;
}

.table__wrapper {
    overflow-x: auto;
}

.data__table {
    width: 100%;
    border-collapse: collapse;
}

.data__table th,
.data__table td {
    padding: 10px;
    text-align: center;
    border-bottom: 2px solid #333333;
}

.data__table th {
    background: #333333;
    color: #ffffff;
    font-weight: 600;
    font-size: 0.875rem;
    text-transform: uppercase;
}

.data__table td {
    color: #1f2937;
    vertical-align: middle;
}

.data__table tr:hover {
    background: #333333;
    color: #ffffff;
}

.table__badge {
    font-size: 12px;
    font-weight: normal;
    padding: 4px 8px;
    border-radius: 12px;
    background: #e5e7eb;
    color: #374151;
}

.table__badge--light {
    background: #f3f4f6;
}

.table__badge--info {
    background: #3b82f6;
    color: white;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
    z-index: 1001;
}

.modal.active {
    display: flex;
}

.modal__dialog {
    max-width: 500px;
    width: 95%;
}

.modal__content {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.modal__header {
    background: #3b82f6;
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal__title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

.modal__close {
    background: none;
    border: none;
    color: white;
    font-size: 1.25rem;
    cursor: pointer;
}

.modal__body {
    padding: 2rem;
    color: #1f2937;
}

.modal__footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #333333;
    display: flex;
    gap: 1rem;
    justify-content: center;
}

/* General Styles */
.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.text-muted {
    color: #6b7280;
}

.text-center {
    text-align: center;
}

.me-1 {
    margin-right: 0.25rem;
}

.me-2 {
    margin-right: 0.5rem;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-250px);
    }

    .sidebar.collapsed {
        transform: translateX(-250px);
    }

    .sidebar:not(.collapsed) {
        transform: translateX(0);
    }

    .dashboard__content {
        margin-left: 0;
    }

    .mobile-sidebar-toggle {
        display: block;
    }

    .sidebar__toggle {
        display: none;
    }

    .sidebar__logo, .sidebar__title {
        display: none;
    }

    .form__container {
        padding: 1.5rem;
        max-width: 100%;
    }

    .table__container {
        width: auto;
        padding: 1rem;
    }

    .section__header {
        font-size: 1.5rem;
    }

    .section__subheader {
        font-size: 1.2rem;
    }

    .form__input,
    .form__select {
        font-size: 0.95rem;
        padding: 0.65rem;
    }

    .btn {
        padding: 0.25rem;
        font-size: 0.95rem;
    }

    .data__table th,
    .data__table td {
        padding: 0.25rem;
        font-size: 0.9rem;
    }

    .table__badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.4rem;
    }

    .btn--small {
        padding: 0.25rem;
        font-size: 0.85rem;
    }

    .modal__dialog {
        width: 95%;
    }
}

@media (max-width: 480px) {
    .dashboard__content {
        padding: 0.25rem;
    }

    .dashboard__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .user__info {
        font-size: 0.9rem;
    }

    .user__avatar {
        width: 0.25rem;
        height: 0.25rem;
    }

    .form__container {
        width: auto;
        padding: 0.25rem;
    }

    .form__label {
        font-size: 0.25rem;
    }

    .form__input,
    .form__select {
        font-size: 0.25rem;
        padding: 0.25rem;
    }

    .btn {
        padding: 0.25rem;
        font-size: 0.9rem;
    }

    .data__table th,
    .data__table td {
        font-size: 0.25rem;
        padding: 0.25rem;
    }

    .btn__group {
        flex-direction: column;
        gap: 0.25rem;
    }

    .modal__title {
        font-size: 0.25rem;
    }

    .modal__body {
        padding: 0.25rem;
    }

    .modal__footer {
        flex-direction: column;
        gap: 0.25rem;
    }
}
</style>

<script>
function openEditBranchModal(id, name, location) {
    document.getElementById('editBranchId').value = id;
    document.getElementById('editBranchName').value = name;
    document.getElementById('editBranchLocation').value = location;
    document.getElementById('editBranchModal').classList.add('active');
}

function openEditRoomModal(id, roomNumber, roomTypeId, status) {
    document.getElementById('editRoomId').value = id;
    document.getElementById('editRoomNumber').value = roomNumber;
    document.getElementById('editRoomType').value = roomTypeId;
    document.getElementById('editRoomStatus').value = status;
    document.getElementById('editRoomModal').classList.add('active');
}

function confirmDelete(type, id) {
    const modal = document.getElementById('deleteModal');
    const typeSpan = document.getElementById('deleteType');
    const deleteButton = document.getElementById('deleteButton');
    const branchIdInput = document.getElementById('deleteBranchId');
    const roomIdInput = document.getElementById('deleteRoomId');
    
    typeSpan.textContent = type;
    if (type === 'branch') {
        branchIdInput.value = id;
        roomIdInput.value = '';
        deleteButton.name = 'delete_branch';
    } else {
        roomIdInput.value = id;
        branchIdInput.value = '';
        deleteButton.name = 'delete_room';
    }
    
    modal.classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');

    // Toggle sidebar function
    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        // Update icons
        if (sidebarToggle) {
            sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line', !isCollapsed);
            sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line', isCollapsed);
        }
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-menu-line', isCollapsed);
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-close-line', !isCollapsed);
        // Adjust content margin
        const dashboardContent = document.querySelector('.dashboard__content');
        dashboardContent.style.marginLeft = window.innerWidth <= 768 && !isCollapsed ? '250px' : '0';
    }

    // Event listeners for toggles
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
    if (mobileSidebarToggle) mobileSidebarToggle.addEventListener('click', toggleSidebar);

    // Initialize sidebar state
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
        mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
        document.querySelector('.dashboard__content').style.marginLeft = '0';
    } else {
        document.querySelector('.dashboard__content').style.marginLeft = '250px';
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        const dashboardContent = document.querySelector('.dashboard__content');
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
            mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
            dashboardContent.style.marginLeft = '0';
        } else {
            sidebar.classList.remove('collapsed');
            dashboardContent.style.marginLeft = '250px';
        }
    });

    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Form validation for edit branch
    const editBranchForm = document.getElementById('editBranchForm');
    if (editBranchForm) {
        editBranchForm.addEventListener('submit', function(e) {
            const name = document.getElementById('editBranchName').value.trim();
            const location = document.getElementById('editBranchLocation').value.trim();
            if (!name || !location) {
                e.preventDefault();
                const alert = document.createElement('div');
                alert.className = 'alert alert--error';
                alert.innerHTML = '<i class="ri-error-warning-line"></i><span>Please provide a valid branch name and location.</span>';
                editBranchForm.parentNode.prepend(alert);
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }
        });
    }

    // Form validation for edit room
    const editRoomForm = document.getElementById('editRoomForm');
    if (editRoomForm) {
        editRoomForm.addEventListener('submit', function(e) {
            const roomNumber = document.getElementById('editRoomNumber').value.trim();
            if (!roomNumber) {
                e.preventDefault();
                const alert = document.createElement('div');
                alert.className = 'alert alert--error';
                alert.innerHTML = '<i class="ri-error-warning-line"></i><span>Please provide a valid room number.</span>';                editRoomForm.parentNode.prepend(alert);
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>
</body>
</html>