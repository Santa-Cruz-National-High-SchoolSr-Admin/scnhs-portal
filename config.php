<?php
/**
 * config.php — Central Configuration
 * ─────────────────────────────────────
 * All database credentials and external API URLs are defined here.
 * On InfinityFree: replace LOCAL_* values with your InfinityFree panel credentials.
 * On XAMPP: the defaults below will work as-is.
 *
 * INCLUDE THIS FILE at the top of every PHP file that needs DB or API access:
 *   require_once __DIR__ . '/config.php';   (from root)
 *   require_once dirname(__DIR__) . '/config.php';  (from admin/ subfolder)
 */

// ─────────────────────────────────────────
// Environment Detection
// ─────────────────────────────────────────
$is_production = isset($_ENV['INFINITYFREE']) || isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || (
    isset($_SERVER['HTTP_HOST']) &&
    !in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) &&
    !preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2[0-9]|3[01])\.|localhost|127\.)/', $_SERVER['HTTP_HOST'])
);

// ─────────────────────────────────────────
// Database Configuration
// ─────────────────────────────────────────
if ($is_production) {
    // ── Production Settings (Vercel / InfinityFree) ──
    // Uses Environment Variables if available, otherwise fallbacks to hardcoded
    define('DB_HOST', getenv('DB_HOST') ?: 'sql309.infinityfree.com');
    define('DB_USER', getenv('DB_USER') ?: 'if0_42429154');
    define('DB_PASS', getenv('DB_PASS') ?: 'Thegreatcyrus27');
    define('DB_NAME', getenv('DB_NAME') ?: 'if0_42429154_scnhs_db');
} else {
    // ── Local XAMPP Development Settings ──
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'enrollment_db');
}

// ─────────────────────────────────────────
// Dropout Risk Prediction (Native PHP — no external API)
// ─────────────────────────────────────────
/**
 * Predict dropout risk based on attendance and grades.
 * Returns ['dropout_risk' => 'High'|'Low', 'confidence' => 0.0–1.0]
 */
function predict_dropout_risk(float $attendance, float $grades): array {
    // Weighted score: attendance matters slightly more than grades
    $score = ($attendance * 0.55) + ($grades * 0.45);

    // Thresholds based on DepEd standards
    if ($score < 60) {
        $risk = 'High';
        $confidence = min(0.95, 1.0 - ($score / 100));
    } elseif ($score < 75) {
        $risk = 'High';
        $confidence = max(0.55, 0.85 - (($score - 60) / 30));
    } else {
        $risk = 'Low';
        $confidence = min(0.95, 0.5 + (($score - 75) / 50));
    }

    return [
        'dropout_risk' => $risk,
        'confidence'   => round($confidence, 4),
        'attendance_rate' => $attendance,
        'grades_average'  => $grades,
    ];
}

// ─────────────────────────────────────────
// Database Connection Helper
// ─────────────────────────────────────────
function get_db_connection(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

// ─────────────────────────────────────────
// Secure Session Settings (Must run before session_start)
// ─────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    if ($is_production) {
        ini_set('session.cookie_secure', 1);
    }
}

// ─────────────────────────────────────────
// Custom MySQL Session Handler (For Vercel Serverless)
// ─────────────────────────────────────────
class DatabaseSessionHandler implements SessionHandlerInterface {
    private $db;
    public function open($path, $name): bool {
        $this->db = get_db_connection();
        $this->db->query("CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data TEXT NOT NULL,
            last_accessed TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        return true;
    }
    public function close(): bool { return true; }
    public function read($id): string|false {
        $stmt = $this->db->prepare("SELECT data FROM sessions WHERE id = ?");
        $stmt->bind_param("s", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) return $row['data'];
        return '';
    }
    public function write($id, $data): bool {
        $stmt = $this->db->prepare("REPLACE INTO sessions (id, data) VALUES (?, ?)");
        $stmt->bind_param("ss", $id, $data);
        return $stmt->execute();
    }
    public function destroy($id): bool {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
        $stmt->bind_param("s", $id);
        return $stmt->execute();
    }
    public function gc($max_lifetime): int|false {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE last_accessed < DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->bind_param("i", $max_lifetime);
        $stmt->execute();
        return $stmt->affected_rows;
    }
}
if ($is_production) {
    session_set_save_handler(new DatabaseSessionHandler(), true);
}

// ─────────────────────────────────────────
// Site Settings
// ─────────────────────────────────────────
define('SITE_NAME',   'Santa Cruz National High School SHS');
define('SITE_EMAIL',  'scnhs@example.com');
define('MAX_SECTION_STUDENTS', 30);

// ─────────────────────────────────────────
// Security & Audit Helpers
// ─────────────────────────────────────────

/**
 * Generate a CSRF token for the current session.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token from a POST request.
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log an action to the audit trail.
 */
function log_audit($conn, $user_id, $role, $action) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, role, action, ip_address) VALUES (?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ssss", $user_id, $role, $action, $ip_address);
        $stmt->execute();
        $stmt->close();
    }
}
