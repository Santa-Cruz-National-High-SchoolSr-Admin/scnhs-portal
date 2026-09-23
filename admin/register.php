<?php
session_start();
$conn = new mysqli("localhost", "root", "", "enrollment_db");

// Check database connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);

    // Check if passwords match
    if ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } else {
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Check if username already exists
        $check_stmt = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_stmt->store_result();

        if ($check_stmt->num_rows > 0) {
            $error = "Username already exists. Choose another one.";
        } else {
            // Insert new admin into the database
            $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashed_password);

            if ($stmt->execute()) {
                $success = "Account created successfully!";
            } else {
                $error = "Error creating account. Please try again.";
            }
            $stmt->close();
        }
        $check_stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | SCNHS Admin Portal</title>
    <meta name="description" content="Create an admin account for Santa Cruz National High School admin portal.">
    <link rel="icon" href="../image/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="login.css">
</head>
<body>

    <!-- Animated background particles -->
    <div class="bg-particles" id="bgParticles"></div>

    <main class="login-shell">

        <!-- LEFT — Branding Panel -->
        <section class="brand-panel" aria-label="School branding">
            <div class="brand-panel__bg"></div>
            <div class="brand-panel__content">
                <div class="brand-panel__badge">
                    <img src="../image/logo.png" alt="SCNHS Logo" class="brand-panel__logo" width="110" height="110">
                    <div class="brand-panel__ring"></div>
                </div>
                <p class="brand-panel__eyebrow">Santa Cruz National High School</p>
                <h1 class="brand-panel__title">Admin<br><span>Portal</span></h1>
                <div class="brand-panel__divider"></div>
                <p class="brand-panel__desc">Create your admin credentials<br>to access the management system.</p>
            </div>
        </section>

        <!-- RIGHT — Register Form Panel -->
        <section class="form-panel" aria-label="Registration form">
            <div class="form-panel__inner">

                <a href="../login.php" class="back-link" id="backLink">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Login</span>
                </a>

                <div class="form-header">
                    <h2 class="form-header__title">Create Account</h2>
                    <p class="form-header__sub">Register a new admin account</p>
                </div>

                <!-- Success message -->
                <?php if (!empty($success)): ?>
                <div class="alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?> <a href="../login.php" style="color: #15803d; font-weight: 700;">Login here &rarr;</a></span>
                </div>
                <?php endif; ?>

                <!-- Error message -->
                <?php if (!empty($error)): ?>
                <div class="alert-error show" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <!-- Register Form -->
                <form method="POST" id="registerForm">

                    <div class="field">
                        <label for="username" class="field__label">Username</label>
                        <div class="field__input-wrap">
                            <i class="fas fa-user field__icon"></i>
                            <input type="text" id="username" name="username"
                                   placeholder="Choose a username" required autocomplete="username"
                                   class="field__input"
                                   value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                            <div class="field__focus-ring"></div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="password" class="field__label">Password</label>
                        <div class="field__input-wrap">
                            <i class="fas fa-lock field__icon"></i>
                            <input type="password" id="password" name="password"
                                   placeholder="Create a password" required autocomplete="new-password"
                                   class="field__input">
                            <button type="button" class="field__toggle" id="togglePassword" title="Show/hide password">
                                <i class="fas fa-eye" id="toggle-icon-1"></i>
                            </button>
                            <div class="field__focus-ring"></div>
                        </div>
                    </div>

                    <div class="field">
                        <label for="confirm_password" class="field__label">Confirm Password</label>
                        <div class="field__input-wrap">
                            <i class="fas fa-shield-alt field__icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   placeholder="Confirm your password" required autocomplete="new-password"
                                   class="field__input">
                            <button type="button" class="field__toggle" id="toggleConfirm" title="Show/hide password">
                                <i class="fas fa-eye" id="toggle-icon-2"></i>
                            </button>
                            <div class="field__focus-ring"></div>
                        </div>
                    </div>

                    <button type="submit" class="btn-login" id="btnRegister">
                        <span class="btn-login__text">Create Account</span>
                        <i class="fas fa-user-plus btn-login__arrow"></i>
                        <div class="btn-login__shine"></div>
                    </button>

                </form>

                <div class="form-divider">
                    <hr><span>or</span><hr>
                </div>

                <a href="../login.php" class="link-home" id="loginLink">
                    <i class="fas fa-sign-in-alt"></i>
                    Already have an account? Sign In
                </a>

                <footer class="form-footer">
                    <p>Authorized access only &mdash; SCNHS &copy; 2025</p>
                </footer>

            </div>
        </section>

    </main>

    <script>
        // Password toggles
        function setupToggle(btnId, inputId, iconId) {
            document.getElementById(btnId).addEventListener('click', function() {
                const input = document.getElementById(inputId);
                const icon  = document.getElementById(iconId);
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                icon.classList.toggle('fa-eye', !isHidden);
                icon.classList.toggle('fa-eye-slash', isHidden);
            });
        }
        setupToggle('togglePassword', 'password', 'toggle-icon-1');
        setupToggle('toggleConfirm', 'confirm_password', 'toggle-icon-2');

        // Floating particles
        (function createParticles() {
            const container = document.getElementById('bgParticles');
            for (let i = 0; i < 30; i++) {
                const dot = document.createElement('span');
                dot.className = 'particle';
                dot.style.left = Math.random() * 100 + '%';
                dot.style.top = Math.random() * 100 + '%';
                dot.style.width = dot.style.height = (Math.random() * 4 + 1) + 'px';
                dot.style.animationDelay = (Math.random() * 8) + 's';
                dot.style.animationDuration = (Math.random() * 12 + 8) + 's';
                container.appendChild(dot);
            }
        })();

        // Entrance animations
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.field, .btn-login, .form-divider, .link-home, .form-footer')
                .forEach(function(el, i) {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(16px)';
                    setTimeout(function() {
                        el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, 200 + i * 80);
                });
        });
    </script>
</body>
</html>
