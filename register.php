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
            $success = "Registration successful! <a href='login.php' style='color: #e91e63; font-weight: 600; text-decoration: underline;'>Login here</a>.";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<style>
    * {
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #e91e63 0%, #f06292 50%, #e91e63 100%);
        min-height: 100vh;
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: auto;
    }

    /* Background decoration */
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="20" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="80" cy="40" r="1" fill="white" opacity="0.1"/><circle cx="40" cy="80" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
        pointer-events: none;
        z-index: 0;
    }

    .background-elements {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        overflow: hidden;
        pointer-events: none;
        z-index: 1;
    }

    .floating-icon {
        position: absolute;
        opacity: 0.1;
        animation: floatSlow 20s ease-in-out infinite;
    }

    .floating-icon:nth-child(1) {
        top: 10%;
        left: 10%;
        animation-delay: 0s;
    }

    .floating-icon:nth-child(2) {
        top: 20%;
        right: 15%;
        animation-delay: 5s;
    }

    .floating-icon:nth-child(3) {
        bottom: 30%;
        left: 20%;
        animation-delay: 10s;
    }

    .floating-icon:nth-child(4) {
        bottom: 15%;
        right: 10%;
        animation-delay: 15s;
    }

    .floating-icon:nth-child(5) {
        top: 60%;
        left: 5%;
        animation-delay: 8s;
    }

    .floating-icon:nth-child(6) {
        top: 70%;
        right: 25%;
        animation-delay: 12s;
    }

    @keyframes floatSlow {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        25% { transform: translateY(-20px) rotate(5deg); }
        50% { transform: translateY(-40px) rotate(0deg); }
        75% { transform: translateY(-20px) rotate(-5deg); }
    }

    .register-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 24px;
        padding: 3rem;
        width: 100%;
        max-width: 480px;
        margin: 2rem auto;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 
                    0 0 0 1px rgba(255, 255, 255, 0.2);
        position: relative;
        animation: slideUp 0.6s ease-out;
        z-index: 10;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .hotel-logo {
        text-align: center;
        margin-bottom: 2rem;
        position: relative;
    }

    .logo-icon {
        width: 80px;
        height: 80px;
        margin: 0 auto 1rem;
        padding: 20px;
        background: linear-gradient(135deg, rgba(233, 30, 99, 0.1), rgba(240, 98, 146, 0.1));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
        border: 2px solid rgba(233, 30, 99, 0.2);
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-10px); }
    }

    .hotel-name {
        font-size: 2.5rem;
        font-weight: 700;
        background: linear-gradient(135deg, #e91e63, #f06292);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.5rem;
        letter-spacing: -0.5px;
    }

    .hotel-tagline {
        color: #6b7280;
        font-size: 0.95rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 2px;
    }

    .register-title {
        font-size: 1.75rem;
        font-weight: 600;
        text-align: center;
        color: #1f2937;
        margin-bottom: 2rem;
    }

    .error-message {
        background: linear-gradient(135deg, #fce4ec, #f8bbd9);
        color: #ad1457;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border: 1px solid #f48fb1;
        font-weight: 500;
        animation: shake 0.5s ease-in-out;
    }

    .success-message {
        background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
        color: #2e7d32;
        padding: 1rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        border: 1px solid #81c784;
        font-weight: 500;
        animation: slideDown 0.5s ease-out;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .form-group {
        margin-bottom: 1.5rem;
        position: relative;
    }

    .form-label {
        display: block;
        color: #374151;
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .form-input, .form-select {
        width: 100%;
        padding: 1rem 1.25rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.8);
        box-sizing: border-box;
    }

    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: #e91e63;
        box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        background: white;
        transform: translateY(-1px);
    }

    .form-input:hover, .form-select:hover {
        border-color: #d1d5db;
    }

    .form-select {
        cursor: pointer;
    }

    .register-button {
        width: 100%;
        padding: 1.25rem;
        background: linear-gradient(135deg, #e91e63, #f06292);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        position: relative;
        overflow: hidden;
    }

    .register-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .register-button:hover::before {
        left: 100%;
    }

    .register-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 15px 35px rgba(233, 30, 99, 0.4);
    }

    .register-button:active {
        transform: translateY(0);
    }

    .links-section {
        margin-top: 2rem;
        text-align: center;
        padding-top: 1.5rem;
        border-top: 1px solid #e5e7eb;
    }

    .auth-link {
        display: block;
        margin: 0.75rem 0;
        color: #6b7280;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.3s ease;
        padding: 0.5rem;
        border-radius: 8px;
    }

    .auth-link:hover {
        color: #e91e63;
        background: rgba(233, 30, 99, 0.05);
        transform: translateX(2px);
    }

    .login-link {
        color: #e91e63;
    }

    /* Role selection styling */
    .role-options {
        display: flex;
        gap: 1rem;
        margin-top: 0.5rem;
    }

    .role-option {
        flex: 1;
        position: relative;
    }

    .role-radio {
        opacity: 0;
        position: absolute;
    }

    .role-label {
        display: block;
        padding: 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.8);
        font-weight: 500;
    }

    .role-radio:checked + .role-label {
        border-color: #e91e63;
        background: rgba(233, 30, 99, 0.1);
        color: #e91e63;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(233, 30, 99, 0.2);
    }

    .role-label:hover {
        border-color: #d1d5db;
        transform: translateY(-1px);
    }

    /* Responsive design */
    @media (max-width: 480px) {
        body {
            padding: 1rem;
        }
        
        .register-container {
            margin: 1rem auto;
            padding: 2rem;
            max-width: calc(100% - 2rem);
        }
        
        .hotel-name {
            font-size: 2rem;
        }
        
        .register-title {
            font-size: 1.5rem;
        }

        .role-options {
            flex-direction: column;
            gap: 0.75rem;
        }
    }

    /* Extra small screens */
    @media (max-width: 360px) {
        .register-container {
            padding: 1.5rem;
            margin: 0.5rem auto;
        }
    }

    /* Larger screens - prevent form from being too wide */
    @media (min-width: 1200px) {
        .register-container {
            max-width: 420px;
        }
    }

    /* Loading animation for form submission */
    .register-button.loading {
        background: #9ca3af;
        cursor: not-allowed;
    }

    .register-button.loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 20px;
        height: 20px;
        margin: -10px 0 0 -10px;
        border: 2px solid transparent;
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Ensure perfect centering on all screen sizes */
    .main-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        width: 100%;
        padding: 2rem 1rem;
        box-sizing: border-box;
    }
</style>

<div class="background-elements">
    <!-- Floating Hotel Icons -->
    <svg class="floating-icon" width="60" height="60" viewBox="0 0 24 24" fill="white">
        <path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V6H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z"/>
    </svg>
    <svg class="floating-icon" width="50" height="50" viewBox="0 0 24 24" fill="white">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
    </svg>
    <svg class="floating-icon" width="55" height="55" viewBox="0 0 24 24" fill="white">
        <path d="M19 7h-3V6a4 4 0 0 0-8 0v1H5a1 1 0 0 0-1 1v11a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V8a1 1 0 0 0-1-1zM10 6a2 2 0 0 1 4 0v1h-4V6zm8 13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V9h2v1a1 1 0 0 0 2 0V9h4v1a1 1 0 0 0 2 0V9h2v10z"/>
    </svg>
    <svg class="floating-icon" width="45" height="45" viewBox="0 0 24 24" fill="white">
        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
    </svg>
    <svg class="floating-icon" width="40" height="40" viewBox="0 0 24 24" fill="white">
        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
    </svg>
    <svg class="floating-icon" width="48" height="48" viewBox="0 0 24 24" fill="white">
        <path d="M14 6V4h-4v2h4zM4 8v11h16V8H4zm16-2c1.11 0 2 .89 2 2v11c0 1.11-.89 2-2 2H4c-1.11 0-2-.89-2-2V8c0-1.11.89-2 2-2h16z"/>
    </svg>
</div>

<div class="main-container">
    <div class="register-container">
        <div class="hotel-logo">
            <div class="logo-icon">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#e91e63" stroke-width="2">
                    <path d="M3 21h18"/>
                    <path d="M5 21V7l8-4v18"/>
                    <path d="M19 21V11l-6-4"/>
                    <path d="M9 9v.01"/>
                    <path d="M9 12v.01"/>
                    <path d="M9 15v.01"/>
                    <path d="M9 18v.01"/>
                </svg>
            </div>
            <h1 class="hotel-name">VELO</h1>
            <p class="hotel-tagline">Resort & Spa</p>
        </div>
        
        <h2 class="register-title">Create Account</h2>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php elseif (isset($success)): ?>
            <div class="success-message">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form id="registerForm" method="POST" onsubmit="return validateRegisterForm()">
            <div class="form-group">
                <label for="name" class="form-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    Full Name
                </label>
                <input type="text" id="name" name="name" class="form-input" required 
                       placeholder="Enter your full name">
            </div>
            
            <div class="form-group">
                <label for="email" class="form-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px;">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    Email Address
                </label>
                <input type="email" id="email" name="email" class="form-input" required 
                       placeholder="Enter your email address">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px;">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <circle cx="12" cy="16" r="1"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    Password
                </label>
                <input type="password" id="password" name="password" class="form-input" required 
                       placeholder="Create a secure password">
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 8px;">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <path d="M20 8v6M23 11l-3 3-3-3"/>
                    </svg>
                    Account Type
                </label>
                <div class="role-options">
                    <div class="role-option">
                        <input type="radio" id="customer" name="role" value="customer" class="role-radio" checked>
                        <label for="customer" class="role-label">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom: 0.5rem;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <br>Customer
                        </label>
                    </div>
                    <div class="role-option">
                        <input type="radio" id="travel_company" name="role" value="travel_company" class="role-radio">
                        <label for="travel_company" class="role-label">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-bottom: 0.5rem;">
                                <path d="M14 6V4h-4v2h4zM4 8v11h16V8H4zm16-2c1.11 0 2 .89 2 2v11c0 1.11-.89 2-2 2H4c-1.11 0-2-.89-2-2V8c0-1.11.89-2 2-2h16z"/>
                            </svg>
                            <br>Travel Company
                        </label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="register-button" id="registerBtn">
                Create Your Account
            </button>
        </form>
        
        <div class="links-section">
            <a href="login.php" class="auth-link login-link">
                Already have an account? Sign in here →
            </a>
        </div>
    </div>
</div>

<script>
function validateRegisterForm() {
    const name = document.getElementById('name').value;
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    const role = document.querySelector('input[name="role"]:checked');
    const registerBtn = document.getElementById('registerBtn');
    
    if (!name || !email || !password || !role) {
        alert('Please fill in all fields.');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters long.');
        return false;
    }
    
    // Add loading state
    registerBtn.classList.add('loading');
    registerBtn.textContent = 'Creating Account...';
    
    return true;
}

// Add subtle animations on input focus
document.querySelectorAll('.form-input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-2px)';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
    });
});

// Role selection animation
document.querySelectorAll('.role-radio').forEach(radio => {
    radio.addEventListener('change', function() {
        document.querySelectorAll('.role-label').forEach(label => {
            label.style.transform = 'translateY(0)';
        });
        if (this.checked) {
            this.nextElementSibling.style.transform = 'translateY(-2px)';
        }
    });
});
</script>

<?php include 'templates/footer.php'; ?>