<?php
include 'includes/functions.php';
include 'templates/header.php';
checkAuth();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_booking'])) {
    $room_id = $_POST['room_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $branch_id = $_POST['branch_id'];

    if (isRoomAvailable($pdo, $room_id, $check_in, $check_out)) {
        $stmt = $pdo->prepare("INSERT INTO bookings (user_id, room_id, branch_id, check_in, check_out) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $room_id, $branch_id, $check_in, $check_out]);
        $success = "Booking created successfully!";
    } else {
        $error = "Room is not available for the selected dates.";
    }
}
?>
    <h1 class="text-3xl font-bold mb-6">Customer Dashboard</h1>
    <?php if (isset($error)): ?>
        <p class="text-red-500"><?php echo $error; ?></p>
    <?php elseif (isset($success)): ?>
        <p class="text-green-500"><?php echo $success; ?></p>
    <?php endif; ?>

    <!-- Book a Room -->
    <div class="mt-8 bg-white p-6 rounded shadow-md">
        <h2 class="text-xl font-bold mb-4">Book a Room</h2>
        <form method="POST">
            <div class="mb-4">
                <label for="branch_id" class="block text-gray-700">Branch</label>
                <select id="branch_id" name="branch_id" class="w-full p-2 border rounded" required>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM branches");
                    while ($branch = $stmt->fetch()) {
                        echo "<option value='{$branch['id']}'>{$branch['name']} ({$branch['location']})</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="room_id" class="block text-gray-700">Room</label>
                <select id="room_id" name="room_id" class="w-full p-2 border rounded" required>
                    <?php
                    $stmt = $pdo->query("SELECT r.id, r.room_number, r.type, r.price, b.name AS branch_name 
                                         FROM rooms r 
                                         JOIN branches b ON r.branch_id = b.id 
                                         WHERE r.status = 'available'");
                    while ($room = $stmt->fetch()) {
                        echo "<option value='{$room['id']}'>{$room['room_number']} ({$room['type']}, \${$room['price']}/night, {$room['branch_name']})</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="check_in" class="block text-gray-700">Check-In Date</label>
                <input type="date" id="check_in" name="check_in" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="check_out" class="block text-gray-700">Check-Out Date</label>
                <input type="date" id="check_out" name="check_out" class="w-full p-2 border rounded" required>
            </div>
            <button type="submit" name="create_booking" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Book Room</button>
        </form>
    </div>

    <!-- View Bookings -->
    <div class="mt-8 bg-white p-6 rounded shadow-md">
        <h2 class="text-xl font-bold mb-4">Your Bookings</h2>
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border p-2">Room</th>
                    <th class="border p-2">Branch</th>
                    <th class="border p-2">Check-In</th>
                    <th class="border p-2">Check-Out</th>
                    <th class="border p-2">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->prepare("SELECT b.*, r.room_number, br.name AS branch_name 
                                      FROM bookings b 
                                      JOIN rooms r ON b.room_id = r.id 
                                      JOIN branches br ON b.branch_id = br.id 
                                      WHERE b.user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                while ($booking = $stmt->fetch()) {
                    echo "<tr>
                            <td class='border p-2'>{$booking['room_number']}</td>
                            <td class='border p-2'>{$booking['branch_name']}</td>
                            <td class='border p-2'>{$booking['check_in']}</td>
                            <td class='border p-2'>{$booking['check_out']}</td>
                            <td class='border p-2'>{$booking['status']}</td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php include 'templates/footer.php'; ?>