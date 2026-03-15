<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

if (strpos($_SERVER['HTTP_HOST'], 'insrv5.net') !== false) {
    header("Location: https://tareas.insrv5.local/logout-clean.php");
    exit();
} else {
    header("Location: https://insrv5.net/users/index.php");
    exit();
}