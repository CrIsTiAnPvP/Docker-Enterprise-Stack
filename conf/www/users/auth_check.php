<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['rol'])) {
    http_response_code(401);
    exit();
}

$rol_exigido_str = isset($_GET['req_role']) ? $_GET['req_role'] : 'IT';
$roles_permitidos = explode(',', $rol_exigido_str); 
$rol_usuario = $_SESSION['rol'];

if (in_array('Trabajador', $roles_permitidos) || $rol_usuario === 'IT' || in_array($rol_usuario, $roles_permitidos)) {
    http_response_code(200);
    exit();
} else {
    http_response_code(403);
    exit();
}