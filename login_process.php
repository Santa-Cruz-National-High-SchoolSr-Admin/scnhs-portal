<?php
session_start();
require_once 'config.php';
$conn = get_db_connection();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        header("Location: login.php?error=" . urlencode("Please fill in all fields."));
        exit;
    }

    // Basic Rate Limiting
    if (!isset($_SESSION['login_attempts'])) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_attempt'] = time();
    }
    if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['first_attempt']) < 900) {
        header("Location: login.php?error=" . urlencode("Too many failed attempts. Try again in 15 minutes."));
        exit;
    }
    if ((time() - $_SESSION['first_attempt']) >= 900) {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['first_attempt'] = time();
    }

    // Helper function to upgrade password hashes
    function upgrade_hash_if_needed($conn, $table, $id, $password, $db_password) {
        if ($password === $db_password && !password_get_info($db_password)['algo']) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE $table SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $id);
            $stmt->execute();
        }
    }

    // 1. Check STUDENTS Table
    $stmt = $conn->prepare("SELECT id, lrn, first_name, last_name, password, status FROM students WHERE lrn = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($student = $result->fetch_assoc()) {
        if (password_verify($password, $student['password']) || $password === $student['password']) {
            if ($student['status'] === 'pending') {
                header("Location: login.php?error=" . urlencode("Your enrollment is currently under review by an administrator."));
                exit;
            } elseif ($student['status'] === 'rejected') {
                header("Location: login.php?error=" . urlencode("Your enrollment was rejected. Please contact the administrator."));
                exit;
            }

            upgrade_hash_if_needed($conn, 'students', $student['id'], $password, $student['password']);
            
            $_SESSION['login_attempts'] = 0;
            session_regenerate_id(true);
            $_SESSION['student_id']   = $student['id'];
            $_SESSION['student_lrn']  = $student['lrn'];
            $_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
            $_SESSION['login_time']   = time();
            
            log_audit($conn, $student['lrn'], 'student', 'Successful login via Unified Portal');
            header("Location: student/dashboard.php");
            exit;
        }
    }
    $stmt->close();

    // 2. Check TEACHERS Table
    $stmt = $conn->prepare("SELECT id, employee_id, first_name, last_name, department, password FROM teachers WHERE employee_id = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($teacher = $result->fetch_assoc()) {
        if (password_verify($password, $teacher['password']) || $password === $teacher['password']) {
            upgrade_hash_if_needed($conn, 'teachers', $teacher['id'], $password, $teacher['password']);
            
            $_SESSION['login_attempts'] = 0;
            session_regenerate_id(true);
            $_SESSION['teacher_id']   = $teacher['id'];
            $_SESSION['employee_id']  = $teacher['employee_id'];
            $_SESSION['teacher_name'] = $teacher['first_name'] . ' ' . $teacher['last_name'];
            $_SESSION['department']   = $teacher['department'];
            $_SESSION['login_time']   = time();
            
            log_audit($conn, $teacher['employee_id'], 'teacher', 'Successful login via Unified Portal');
            header("Location: teacher/dashboard.php");
            exit;
        }
    }
    $stmt->close();

    // 3. Check ACCOUNTS Table (Admin, Registrar, Superadmin)
    $stmt = $conn->prepare("SELECT id, username, password, role FROM accounts WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($account = $result->fetch_assoc()) {
        if (password_verify($password, $account['password']) || $password === $account['password']) {
            upgrade_hash_if_needed($conn, 'accounts', $account['id'], $password, $account['password']);
            
            $_SESSION['login_attempts'] = 0;
            session_regenerate_id(true);
            $_SESSION['user_id'] = $account['id'];
            $_SESSION['username'] = $account['username'];
            $_SESSION['role'] = $account['role'];
            $_SESSION['login_time'] = time();

            log_audit($conn, $account['id'], $account['role'], 'Successful login via Unified Portal');

            // Redirect based on role in accounts table
            if ($account['role'] === 'superadmin') {
                header("Location: admin/superadmin_dashboard.php");
            } elseif ($account['role'] === 'registrar') {
                header("Location: registrar/dashboard.php");
            } else {
                header("Location: admin/dashboard.php");
            }
            exit;
        }
    }
    $stmt->close();

    // If no match found in any table or incorrect password
    $_SESSION['login_attempts']++;
    log_audit($conn, $username, 'unknown', 'Failed login attempt via Unified Portal');
    header("Location: login.php?error=" . urlencode("Invalid credentials. Please try again."));
    exit;
}

$conn->close();
header("Location: login.php");
exit;
