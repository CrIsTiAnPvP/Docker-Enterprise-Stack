<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

$scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? 'https';
$host = $_SERVER['HTTP_HOST'];

if (isset($_GET['target']) && !empty($_GET['target'])) {
    $target = filter_var($_GET['target'], FILTER_SANITIZE_URL);
    if (strpos($target, '.insrv5.local') === false && strpos($target, '.insrv5.net') === false) {
        die("Destino no permitido.");
    }
} else {
    $target = $scheme . "://" . $host . "/";
}

if (isset($_SESSION['user'])) {
    header("Location: " . $target);
    exit();
}

if (isset($_GET['token']) && !empty($_GET['token'])) {
    session_write_close();
    session_id($_GET['token']);
    session_start();
    
    setcookie(session_name(), session_id(), [
        'expires' => time() + (86400 * 30),
        'path' => '/',
        'domain' => '.insrv5.local',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    session_write_close();
    header("Location: " . $target);
    exit();
}

$login_url = "https://insrv5.net/users/index.php?return=" . urlencode($target);
header("Location: " . $login_url);
exit();
?>