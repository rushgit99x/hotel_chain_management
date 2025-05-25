<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Chain Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="assets/js/script.js"></script>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body class="bg-gray-100">
    <nav class="bg-blue-600 p-4 text-white">
        <div class="container mx-auto flex justify-between">
            <a href="index.php" class="text-xl font-bold">Hotel Chain</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div>
                    <span>Welcome, <?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    <a href="logout.php" class="ml-4 bg-red-500 px-3 py-2 rounded hover:bg-red-600">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </nav>
    <div class="container mx-auto p-8">