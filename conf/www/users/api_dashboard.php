<?php
$host = $_SERVER['HTTP_HOST'] ?? '';
$cookie_domain = (strpos($host, 'insrv5.net') !== false) ? '.insrv5.net' : '.insrv5.local';
session_set_cookie_params([
    'domain' => $cookie_domain,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

error_reporting(0);
header('Content-Type: application/json');

if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'IT') {
    echo json_encode(['success' => false, 'error' => 'No tienes permisos de administrador IT']);
    exit;
}

$db_host = "mysql.insrv5.local";
$db_user = "user";
$db_pass = "1234";
$db_name = "insrv5_db";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la Base de Datos']);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'POST') {
    $id = intval($_POST['app_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $icono = trim($_POST['icono'] ?? '');
    $color = trim($_POST['color'] ?? 'bg-blue-50 text-blue-600');
    $roles = isset($_POST['roles']) ? implode(',', $_POST['roles']) : 'Todos';
    $requiere_vpn = isset($_POST['requiere_vpn']) ? 1 : 0;
    
    $creador = $_SESSION['user_cn'] ?? 'IT Admin';

    if ($nombre && $url && $icono) {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE dashboard_apps SET nombre = ?, descripcion = ?, url = ?, icono_svg = ?, color_fondo = ?, roles_permitidos = ?, requiere_vpn = ? WHERE id = ?");
            $stmt->execute([$nombre, $descripcion, $url, $icono, $color, $roles, $requiere_vpn, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO dashboard_apps (nombre, descripcion, url, icono_svg, color_fondo, roles_permitidos, requiere_vpn, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nombre, $descripcion, $url, $icono, $color, $roles, $requiere_vpn, $creador]);
        }
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Por favor, rellena todos los campos obligatorios.']);
    }
    exit;
}

if ($metodo === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = intval($data['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM dashboard_apps WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
    exit;
}
?>