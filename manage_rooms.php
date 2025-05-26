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
    if (isset($_POST['edit_room'])) {
        $room_id = $_POST['room_id'];
        $branch_id = $_POST['branch_id'];
        $room_type_id = $_POST['room_type_id'];
        $room_number = sanitize($_POST['room_number']);
        $status = $_POST['status'];

        try {
            $stmt = $pdo->prepare("UPDATE rooms SET branch_id = ?, room_type_id = ?, room_number = ?, status = ? WHERE id = ?");
            $stmt->execute([$branch_id, $room_type_id, $room_number, $status, $room_id]);
            $success = "Room updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating room: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_room'])) {
        $room_id = $_POST['room_id'];
        try {
            // Check for existing bookings
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $booking_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

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

// Fetch room data for editing
$edit_room = null;
if (isset($_GET['edit_room_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, b.name AS branch_name, rt.name AS room_type_name
            FROM rooms r
            LEFT JOIN branches b ON r.branch_id = b.id
            LEFT JOIN room_types rt ON r.room_type_id = rt.id
            WHERE r.id = ?
        ");
        $stmt->execute([$_GET['edit_room_id']]);
        $edit_room = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching room details: " . $e->getMessage();
    }
}

include 'templates/header.php';
?>

<!-- Add Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">

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
            <h1 class="section__header">Manage Rooms</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <div class="container-fluid px-4">
            <!-- Success/Error Messages -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Manage Rooms Section -->
            <section id="manage-rooms" class="dashboard__section active">
                <?php if ($edit_room): ?>
                    <!-- Edit Room Form -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h3 class="card-title mb-0">
                                <i class="bi bi-pencil-square me-2"></i>Edit Room
                            </h3>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="row g-3">
                                <input type="hidden" name="room_id" value="<?php echo $edit_room['id']; ?>">
                                
                                <div class="col-md-6">
                                    <label for="edit_room_branch_id" class="form-label">Branch</label>
                                    <select id="edit_room_branch_id" name="branch_id" class="form-select" required>
                                        <option value="">Select Branch</option>
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
                                            while ($branch = $stmt->fetch()) {
                                                $selected = $branch['id'] == $edit_room['branch_id'] ? 'selected' : '';
                                                echo "<option value='{$branch['id']}' $selected>{$branch['name']} ({$branch['location']})</option>";
                                            }
                                        } catch (PDOException $e) {
                                            echo "<option value=''>Error loading branches</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="edit_room_type_id" class="form-label">Room Type</label>
                                    <select id="edit_room_type_id" name="room_type_id" class="form-select" required>
                                        <option value="">Select Room Type</option>
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM room_types ORDER BY name");
                                            while ($room_type = $stmt->fetch()) {
                                                $selected = $room_type['id'] == $edit_room['room_type_id'] ? 'selected' : '';
                                                echo "<option value='{$room_type['id']}' $selected>{$room_type['name']} (\${$room_type['base_price']}/night)</option>";
                                            }
                                        } catch (PDOException $e) {
                                            echo "<option value=''>Error loading room types</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="edit_room_number" class="form-label">Room Number</label>
                                    <input type="text" id="edit_room_number" name="room_number" class="form-control" value="<?php echo htmlspecialchars($edit_room['room_number']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select id="edit_status" name="status" class="form-select" required>
                                        <option value="available" <?php echo $edit_room['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                        <option value="occupied" <?php echo $edit_room['status'] == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                        <option value="maintenance" <?php echo $edit_room['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    </select>
                                </div>
                                
                                <div class="col-12">
                                    <div class="d-flex gap-2">
                                        <button type="submit" name="edit_room" class="btn btn-primary">
                                            <i class="bi bi-save me-1"></i>Update Room
                                        </button>
                                        <a href="manage_rooms.php" class="btn btn-secondary">
                                            <i class="bi bi-arrow-left me-1"></i>Cancel
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Room List Table -->
                    <div class="card shadow-sm">
                        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">
                                <i class="bi bi-house-gear me-2"></i>Room Management
                            </h3>
                            <span class="badge bg-light text-dark">
                                <?php
                                try {
                                    $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM rooms");
                                    $total_rooms = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                    echo $total_rooms . ' Total Rooms';
                                } catch (PDOException $e) {
                                    echo '0 Rooms';
                                }
                                ?>
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped mb-0">
                                    <thead class="table-dark">
                                        <tr>
                                            <th scope="col">
                                                <i class="bi bi-hash me-1"></i>ID
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-building me-1"></i>Branch
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-house me-1"></i>Room Type
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-door-open me-1"></i>Room Number
                                            </th>
                                            <th scope="col">
                                                <i class="bi bi-activity me-1"></i>Status
                                            </th>
                                            <th scope="col" class="text-center">
                                                <i class="bi bi-gear me-1"></i>Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        try {
                                            $stmt = $pdo->query("
                                                SELECT r.*, b.name AS branch_name, rt.name AS room_type_name
                                                FROM rooms r
                                                LEFT JOIN branches b ON r.branch_id = b.id
                                                LEFT JOIN room_types rt ON r.room_type_id = rt.id
                                                ORDER BY r.id
                                            ");
                                            $room_count = 0;
                                            while ($room = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                $room_count++;
                                                $status_class = '';
                                                $status_icon = '';
                                                
                                                switch($room['status']) {
                                                    case 'available':
                                                        $status_class = 'success';
                                                        $status_icon = 'bi-check-circle-fill';
                                                        break;
                                                    case 'occupied':
                                                        $status_class = 'warning';
                                                        $status_icon = 'bi-person-fill';
                                                        break;
                                                    case 'maintenance':
                                                        $status_class = 'danger';
                                                        $status_icon = 'bi-tools';
                                                        break;
                                                    default:
                                                        $status_class = 'secondary';
                                                        $status_icon = 'bi-question-circle';
                                                }
                                                
                                                echo "<tr>";
                                                echo "<td><span class='badge bg-light text-dark'>" . htmlspecialchars($room['id']) . "</span></td>";
                                                echo "<td><strong>" . htmlspecialchars($room['branch_name'] ?? 'Unknown Branch') . "</strong></td>";
                                                echo "<td>" . htmlspecialchars($room['room_type_name'] ?? 'Unknown Type') . "</td>";
                                                echo "<td><span class='badge bg-info text-white'>" . htmlspecialchars($room['room_number']) . "</span></td>";
                                                echo "<td><span class='badge bg-{$status_class}'><i class='{$status_icon} me-1'></i>" . ucfirst(htmlspecialchars($room['status'])) . "</span></td>";
                                                echo "<td class='text-center'>";
                                                echo "<div class='btn-group btn-group-sm' role='group'>";
                                                echo "<a href='manage_rooms.php?edit_room_id={$room['id']}' class='btn btn-outline-primary' title='Edit Room'>";
                                                echo "<i class='bi bi-pencil-square'></i>";
                                                echo "</a>";
                                                echo "<button type='button' class='btn btn-outline-danger' onclick='confirmDelete({$room['id']})' title='Delete Room'>";
                                                echo "<i class='bi bi-trash'></i>";
                                                echo "</button>";
                                                echo "</div>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                            
                                            if ($room_count == 0) {
                                                echo "<tr>";
                                                echo "<td colspan='6' class='text-center py-5'>";
                                                echo "<div class='text-muted'>";
                                                echo "<i class='bi bi-house-x display-1 d-block mb-3'></i>";
                                                echo "<h5>No Rooms Found</h5>";
                                                echo "<p>Start by adding some rooms to your hotel branches.</p>";
                                                echo "</div>";
                                                echo "</td>";
                                                echo "</tr>";
                                            }
                                        } catch (PDOException $e) {
                                            echo "<tr><td colspan='6' class='text-center text-danger py-4'>";
                                            echo "<i class='bi bi-exclamation-triangle me-2'></i>";
                                            echo "Error loading rooms: " . htmlspecialchars($e->getMessage());
                                            echo "</td></tr>";
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0">Are you sure you want to delete this room? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" style="display:inline;" id="deleteForm">
                    <input type="hidden" name="room_id" id="deleteRoomId">
                    <button type="submit" name="delete_room" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete Room
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Custom styles to blend with existing design */
.dashboard__container {
    display: flex;
    min-height: 100vh;
}

.dashboard__content {
    flex: 1;
    background: #f8f9fa;
    padding: 0;
}

.dashboard__header {
    background: white;
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #dee2e6;
    margin-bottom: 0;
}

.card {
    border: none;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 2rem;
}

.card-header {
    border-bottom: 1px solid rgba(255,255,255,0.1);
    padding: 1.25rem 1.5rem;
}

.table th {
    border-top: none;
    font-weight: 600;
    letter-spacing: 0.025em;
    font-size: 0.875rem;
    text-transform: uppercase;
}

.table td {
    vertical-align: middle;
    padding: 1rem 0.75rem;
}

.btn-group-sm > .btn {
    padding: 0.375rem 0.5rem;
}

.badge {
    font-size: 0.75rem;
    font-weight: 500;
}

/* Status-specific styling */
.badge.bg-success {
    background-color: #28a745 !important;
}

.badge.bg-warning {
    background-color: #ffc107 !important;
    color: #212529 !important;
}

.badge.bg-danger {
    background-color: #dc3545 !important;
}

/* Hover effects */
.table-hover tbody tr:hover {
    background-color: rgba(0,0,0,.035);
}

.btn-outline-primary:hover,
.btn-outline-danger:hover {
    transform: translateY(-1px);
    transition: transform 0.2s ease;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard__header {
        padding: 1rem;
    }
    
    .container-fluid {
        padding: 0 1rem;
    }
    
    .btn-group .btn {
        margin-bottom: 0.25rem;
    }
}
</style>

<!-- Add Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Sidebar toggle functionality
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});

// Delete confirmation function
function confirmDelete(roomId) {
    document.getElementById('deleteRoomId').value = roomId;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php include 'templates/footer.php'; ?>