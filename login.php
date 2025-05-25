<?php
// Debug: Confirm auth.php is included
if (!file_exists('includes/auth.php')) {
    die("Error: includes/auth.php not found.");
}
include_once 'includes/auth.php';
include 'templates/header.php';

// Debug: Check if processLogin is defined
if (!function_exists('processLogin')) {
    die("Error: processLogin function is not defined. Check auth.php.");
}

$error = processLogin($pdo); // Call the login function
?>
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md mx-auto">
        <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>
        <?php if ($error): ?>
            <p class="text-red-500"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form id="loginForm" method="POST" onsubmit="return validateLoginForm()">
            <div class="mb-4">
                <label for="email" class="block text-gray-700">Email</label>
                <input type="email" id="email" name="email" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-gray-700">Password</label>
                <input type="password" id="password" name="password" class="w-full p-2 border rounded" required>
            </div>
            <button type="submit" name="login" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Login</button>
        </form>
        <p class="mt-4 text-center">Don't have an account? <a href="register.php" class="text-blue-500">Register here</a>.</p>
        <p class="mt-2 text-center">Super Admin? <a href="superadmin_register.php" class="text-purple-500">Register Super Admin</a>.</p>
    </div>
<?php include 'templates/footer.php'; ?>