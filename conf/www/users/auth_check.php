<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['rol'])) {
    http_response_code(401);
    exit();
}

$rol_exigido = isset($_GET['req_role']) ? $_GET['req_role'] : 'IT';

if ($_SESSION['rol'] === $rol_exigido || $_SESSION['rol'] === 'IT') {
    http_response_code(200);
    exit();
} else {
    http_response_code(403);
    exit();
}