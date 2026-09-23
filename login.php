<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Portal Login | Santa Cruz NHS</title>
    <link rel="icon" href="image/SCNHS.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --brand-blue: #1456D9;
            --brand-dark: #0F3FA8;
            --brand-accent: #e8a800;
            --bg-color: #f5f7fc;
            --text-dark: #1a1f36;
            --text-mid: #4a5568;
            --border-color: #d1d9e6;
            --radius-md: 12px;
            --radius-lg: 20px;
        }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: var(--text-dark);
            overflow: hidden;
            position: relative;
        }
        .bg-pattern {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(var(--brand-blue) 1px, transparent 1px);
            background-size: 30px 30px;
            opacity: 0.05;
            z-index: 0;
        }
        .login-wrapper {
            position: relative;
            z-index: 1;
            display: flex;
            width: 100%;
            max-width: 960px;
            background: #fff;
            border-radius: var(--radius-lg);
            box-shadow: 0 12px 40px rgba(20, 86, 217, 0.15);
            overflow: hidden;
            margin: 1.5rem;
        }
        .login-brand {
            flex: 1;
            background: linear-gradient(135deg, var(--brand-blue), var(--brand-dark));
            padding: 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: #fff;
            position: relative;
        }
        .login-brand::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: url('image/bg.png') center/cover;
            opacity: 0.15;
            mix-blend-mode: overlay;
            animation: slowPan 20s linear infinite alternate;
        }
        @keyframes slowPan {
            0% { transform: scale(1); }
            100% { transform: scale(1.05); }
        }
        .login-brand > * { position: relative; z-index: 2; animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .brand-logo-wrap {
            width: 130px;
            height: 130px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .brand-logo-wrap img { 
            width: 100%; height: 100%; object-fit: contain; 
            filter: drop-shadow(0 4px 15px rgba(0,0,0,0.2)); 
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .login-brand h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2.2rem;
            font-weight: 800;
            line-height: 1.2;
            margin: 0 0 1rem;
        }
        .login-brand h1 span { color: var(--brand-accent); }
        .login-brand p {
            font-size: 1rem;
            color: rgba(255,255,255,0.85);
            line-height: 1.6;
            margin: 0;
        }
        .login-brand h1 { animation-delay: 0.1s; }
        .login-brand p { animation-delay: 0.2s; }
        .login-form-panel {
            flex: 1;
            padding: 3.5rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #fff;
        }
        .login-form-panel > * { animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .form-header { margin-bottom: 2rem; animation-delay: 0.3s; }
        .error-msg { animation-delay: 0.35s; }
        form .form-group:nth-child(1) { animation-delay: 0.4s; }
        form .form-group:nth-child(2) { animation-delay: 0.5s; }
        .btn-submit { animation-delay: 0.6s; }
        .back-link { animation-delay: 0.7s; }
        @keyframes fadeUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        .form-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.7rem;
            font-weight: 700;
            margin: 0 0 0.4rem;
            color: var(--text-dark);
        }
        .form-header p {
            color: var(--text-mid);
            margin: 0;
            font-size: 0.95rem;
        }
        .error-msg {
            background: #fef2f2;
            color: #dc2626;
            padding: 0.85rem 1rem;
            border-radius: 8px;
            border-left: 4px solid #dc2626;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.4rem;
        }
        .input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-wrap i.icon {
            position: absolute;
            left: 1rem;
            color: var(--text-light);
            font-size: 1.1rem;
        }
        .input-wrap input {
            width: 100%;
            padding: 0.9rem 1rem 0.9rem 2.8rem;
            border: 1.5px solid var(--border-color);
            border-radius: var(--radius-md);
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            color: var(--text-dark);
            transition: all 0.2s ease;
            outline: none;
            background: #fff;
            box-sizing: border-box;
        }
        .input-wrap input:focus {
            border-color: var(--brand-blue);
            box-shadow: 0 0 0 4px rgba(20, 86, 217, 0.1);
        }
        .input-wrap input:focus + i.icon {
            color: var(--brand-blue);
        }
        .btn-toggle-pwd {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            padding: 0.2rem;
            font-size: 1rem;
            transition: color 0.2s;
        }
        .btn-toggle-pwd:hover { color: var(--brand-blue); }
        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: var(--brand-blue);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
            box-shadow: 0 4px 12px rgba(20, 86, 217, 0.25);
        }
        .btn-submit:hover {
            background: var(--brand-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(20, 86, 217, 0.35);
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            color: var(--text-mid);
            font-size: 0.9rem;
            font-weight: 500;
            text-decoration: none;
            margin-top: 2rem;
            transition: color 0.2s;
        }
        .back-link:hover { color: var(--brand-blue); }
        
        @media (max-width: 768px) {
            .login-wrapper { flex-direction: column; margin: 0; border-radius: 0; min-height: 100vh; }
            .login-brand { padding: 2rem; flex: none; }
            .login-brand h1 { font-size: 1.8rem; }
            .login-form-panel { padding: 2rem; }
            .brand-logo-wrap { width: 60px; height: 60px; margin-bottom: 1rem; }
        }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    <div class="login-wrapper">
        <div class="login-brand">
            <div class="brand-logo-wrap">
                <img src="image/SCNHS.png" alt="SCNHS Logo">
            </div>
            <h1>Santa Cruz NHS<br><span>Unified Portal</span></h1>
            <p>Access your dashboard. The system will automatically detect if you are a Student, Teacher, or Administrator based on your credentials.</p>
        </div>
        
        <div class="login-form-panel">
            <div class="form-header">
                <h2>Welcome Back</h2>
                <p>Please sign in to continue</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">
                <div class="form-group">
                    <label for="username">ID Number or Username</label>
                    <div class="input-wrap">
                        <input type="text" id="username" name="username" placeholder="e.g. 101234567890 or Juan" required autocomplete="username">
                        <i class="fas fa-user icon"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                        <i class="fas fa-lock icon"></i>
                        <button type="button" class="btn-toggle-pwd" aria-label="Toggle password visibility" onclick="togglePwd()">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    Sign In <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Return to Homepage
            </a>
        </div>
    </div>

    <script>
        function togglePwd() {
            const pwdInput = document.getElementById('password');
            const eyeIcon = document.getElementById('eyeIcon');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                eyeIcon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pwdInput.type = 'password';
                eyeIcon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
