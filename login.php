<?php
// Start session
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'sneakysheets';
$username = 'root';
$password = '';

// Database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? $_POST['remember'] : false;

    // Validate form data
    $errors = [];

    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    }

    // If no errors, proceed with login
    if (empty($errors)) {
        // Prepare select statement - FIXED: Changed 'id' to 'user_id'
        $stmt = $pdo->prepare("SELECT user_id, name, email, password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Password is correct, start session 
            $_SESSION['user_id'] = $user['user_id']; // This was line 46
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];

            // Set remember me cookie if checked
            if ($remember) {
                setcookie('remember_email', $email, time() + (86400 * 30), "/"); // 30 days
            }

            // Redirect to USER DASHBOARD 
            header("Location:user/index.php");
            exit();
        } else {
            $errors[] = "Invalid email or password";
        }
    }

    // If there are errors, store them in session
    $_SESSION['login_errors'] = $errors;
}

// Check if user is already logged in 
if (isset($_SESSION['user_id'])) {
    header("Location: user/index.php");
    exit();
}

// Get any login errors
$login_errors = isset($_SESSION['login_errors']) ? $_SESSION['login_errors'] : [];
unset($_SESSION['login_errors']);

// Get remembered email
$remembered_email = isset($_COOKIE['remember_email']) ? $_COOKIE['remember_email'] : '';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SneakyPLay</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- External CSS -->
    <link rel="stylesheet" href="assets/css/login.css">
    <link rel="icon" type="image/png" href="assets/image/logo.png">

</head>

<body>
    <!-- Navigation Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">SneakyPlay</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Login Content -->
    <div class="container mt-5 pt-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="auth-container">
                    <div class="auth-header text-center">
                        <h2 class="auth-title">Welcome Back</h2>
                        <p class="auth-subtitle">Sign in to your SneakyPlay account</p>
                    </div>

                    <div class="auth-body">
                        <!-- Show login errors if any -->
                        <?php if (!empty($login_errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($login_errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Login Form -->
                        <form id="login-form" class="auth-form" action="login.php" method="POST">
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="login-email" name="email"
                                    placeholder="name@example.com" required
                                    value="<?php echo htmlspecialchars($remembered_email); ?>">
                                <label for="login-email">Email address</label>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="login-password" name="password"
                                    placeholder="Password" required>
                                <label for="login-password">Password</label>
                                <i class="fas fa-eye password-toggle" onclick="togglePassword('login-password')"></i>
                                <div class="invalid-feedback">Password must be at least 6 characters.</div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="remember-me" name="remember"
                                    <?php echo !empty($remembered_email) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="remember-me">
                                    Remember me
                                </label>
                            </div>

                            <button type="submit" class="auth-button w-100">Sign In</button>
                        </form>

                        <div class="divider mt-4">
                            <span>or continue with</span>
                        </div>

                        <div class="social-login mt-4">
                            <a href="#" class="social-btn">
                                <i class="fab fa-google"></i>
                                <span>Google</span>
                            </a>
                            <a href="#" class="social-btn">
                                <i class="fab fa-facebook-f"></i>
                                <span>Facebook</span>
                            </a>
                            <a href="#" class="social-btn">
                                <i class="fab fa-twitter"></i>
                                <span>Twitter</span>
                            </a>
                        </div>
                    </div>

                    <div class="auth-footer text-center mt-4">
                        <p>Don't have an account? <a href="register.php">Sign up</a></p>
                        <p class="mt-2"><a href="#" class="text-decoration-none">Forgot your password?</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- External JavaScript -->
    <script src="assets/js/login.js"></script>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>


</html>
