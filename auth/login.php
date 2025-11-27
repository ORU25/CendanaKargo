<?php
session_start();
include '../config/database.php';

if (isset($_SESSION['user_id'])) {
    // Kalau role systemOwner
    if ($_SESSION['role'] == 'systemOwner') {
        header("Location: ../dashboard/systemOwner/?already_logined");
        exit;
    }
    // Kalau role superAdmin
    elseif ($_SESSION['role'] == 'superAdmin') {
        header("Location: ../dashboard/superadmin/?already_logined");
        exit;
    }
    // Kalau role admin
    elseif ($_SESSION['role'] == 'admin') {
        header("Location: ../dashboard/admin/?already_logined");
        exit;
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        header("Location: login?error=invalid");
        exit;
    }
    
    // Verify Cloudflare Turnstile CAPTCHA
    require_once '../config/env.php';
    
    $captchaToken = isset($_POST['cf-turnstile-response']) ? trim($_POST['cf-turnstile-response']) : '';
    
    if (empty($captchaToken)) {
        header("Location: login?error=captcha_required");
        exit;
    }
    
    // Verify with Cloudflare
    $secretKey = env('TURNSTILE_SECRET_KEY');
    
    if (empty($secretKey)) {
        error_log('TURNSTILE_SECRET_KEY not set in .env');
        header("Location: login?error=server_error");
        exit;
    }
    
    $verifyUrl = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $verifyData = [
        'secret' => $secretKey,
        'response' => $captchaToken,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($verifyData),
            'timeout' => 10
        ]
    ];
    
    $context = stream_context_create($options);
    $response = @file_get_contents($verifyUrl, false, $context);
    
    if ($response === false) {
        header("Location: login?error=captcha_verify_error");
        exit;
    }
    
    $result = json_decode($response);
    
    if (!$result->success) {
        header("Location: login?error=captcha_failed");
        exit;
    }
    
    // CAPTCHA verified, proceed with login
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Gunakan Prepared Statement (SECURE)
    $sql = "SELECT * FROM user WHERE username = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        die('Prepare failed: ' . htmlspecialchars($conn->error));
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        if (isset($user['id_cabang']) && $user['id_cabang'] != null) {
            // Gunakan Prepared Statement untuk query cabang juga
            $sqlCabang = "SELECT nama_cabang FROM kantor_cabang WHERE id = ?";
            $stmtCabang = $conn->prepare($sqlCabang);
            
            if ($stmtCabang !== false) {
                $stmtCabang->bind_param("i", $user['id_cabang']);
                $stmtCabang->execute();
                $resultCabang = $stmtCabang->get_result();
                
                if ($row = $resultCabang->fetch_assoc()) {
                    $_SESSION['id_cabang'] = $user['id_cabang'];
                    $_SESSION['cabang'] = $row['nama_cabang'];
                }
                $stmtCabang->close();
            }
        }

        if ($user['role'] == 'systemOwner') {
            header("Location: ../dashboard/systemOwner/");
            exit;
        } elseif ($user['role'] == 'superAdmin') {
            header("Location: ../dashboard/superadmin/");
            exit;
        } elseif ($user['role'] == 'admin') {
            header("Location: ../dashboard/admin/");
            exit;
        }
    } else {
        header("Location: login?error=invalid");
    }
    
    $stmt->close();
}

?>
<?php
$title = "Login - Cendana Kargo";
include '../templates/header.php';
?>
<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="card shadow p-4" style="width: 350px; border-top: 5px solid #dc3545;">
        <a href="../" class="mx-auto">
            <img src="../assets/logo.jpg" alt="Logo Cendana Lintas Kargo" width="100px" />
        </a>
        <h3 class="text-center text-danger mb-4 fw-bold">Login</h3>
        <form action="login" method="post" id="loginForm">
            <?php 
            if (isset($_GET['error'])) {
                $type = "danger";
                $message = "";
                
                switch($_GET['error']) {
                    case 'invalid':
                        $message = "Invalid username or password.";
                        break;
                    case 'captcha_required':
                        $message = "Please complete the CAPTCHA verification.";
                        break;
                    case 'captcha_failed':
                        $message = "CAPTCHA verification failed. Please try again.";
                        break;
                    case 'captcha_verify_error':
                        $message = "Unable to verify CAPTCHA. Please try again.";
                        break;
                    case 'server_error':
                        $message = "Server configuration error. Please contact administrator.";
                        break;
                    default:
                        $message = "An error occurred. Please try again.";
                }
                
                if ($message) {
                    include '../components/alert.php';
                }
            }
            ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">
            <div class="mb-3">
                <label for="username" class="form-label fw-semibold">Username</label>
                <input type="text" class="form-control" id="username" name="username" placeholder="Masukkan username"
                    required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label fw-semibold">Password</label>
                <input type="password" class="form-control" id="password" name="password"
                    placeholder="Masukkan password" required>
            </div>
            
            <!-- Cloudflare Turnstile CAPTCHA -->
            <div class="mb-3 d-flex justify-content-center">
                <div class="cf-turnstile" 
                     data-sitekey="0x4AAAAAACAg1S8rwiBokGwN" 
                     data-callback="onLoginCaptchaSuccess"
                     data-theme="light"></div>
            </div>
            
            <div class="d-grid">
                <button type="submit" id="loginBtn" class="btn btn-danger fw-bold" disabled style="opacity: 0.5; cursor: not-allowed;">
                    Login
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Cloudflare Turnstile callback for login
let isLoginCaptchaVerified = false;
let isSubmitting = false;

window.onLoginCaptchaSuccess = function(token) {
    isLoginCaptchaVerified = true;
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.disabled = false;
    loginBtn.style.opacity = '1';
    loginBtn.style.cursor = 'pointer';
    
    // Hide verification message
    const verifyMsg = loginBtn.parentElement.nextElementSibling;
    if (verifyMsg && verifyMsg.tagName === 'P') {
        verifyMsg.style.display = 'none';
    }
};

// Form validation and loading state
document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (!isLoginCaptchaVerified) {
        e.preventDefault();
        alert('Please complete the CAPTCHA verification first.');
        return false;
    }
    
    // Prevent double submission
    if (isSubmitting) {
        e.preventDefault();
        return false;
    }
    
    // Show loading state
    isSubmitting = true;
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.disabled = true;
    loginBtn.style.opacity = '0.6';
    loginBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Logging in...';
    
    // Form will submit normally after this
    return true;
});
</script>

<?php
include '../templates/footer.php';
?>