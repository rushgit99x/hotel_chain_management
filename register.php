<?php
include_once 'includes/functions.php';
include 'templates/header.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $_POST['role'];

    if (!validateEmail($email)) {
        $error = "Invalid email format.";
    } elseif ($role !== 'customer' && $role !== 'travel_company') {
        $error = "Invalid role selected.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role]);
            $success = "Registration successful! <a href='login.php'>Login here</a>.";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md mx-auto">
        <h2 class="text-2xl font-bold mb-6 text-center">Register</h2>
        <?php if (isset($error)): ?>
            <p class="text-red-500"><?php echo $error; ?></p>
        <?php elseif (isset($success)): ?>
            <p class="text-green-500"><?php echo $success; ?></p>
        <?php endif; ?>
        <form id="registerForm" method="POST" onsubmit="return validateRegisterForm()">
            <div class="mb-4">
                <label for="name" class="block text-gray-700">Name</label>
                <input type="text" id="name" name="name" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-gray-700">Email</label>
                <input type="email" id="email" name="email" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-gray-700">Password</label>
                <input type="password" id="password" name="password" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="role" class="block text-gray-700">Role</label>
                <select id="role" name="role" class="w-full p-2 border rounded" required>
                    <option value="customer">Customer</option>
                    <option value="travel_company">Travel Company</option>
                </select>
            </div>
            <button type="submit" class="w-full bg-green-500 text-white p-2 rounded hover:bg-green-600">Register</button>
        </form>
    </div>
<?php include 'templates/footer.php'; ?>