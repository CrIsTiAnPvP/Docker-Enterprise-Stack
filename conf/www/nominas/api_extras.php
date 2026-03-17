<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();
error_reporting(0);
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$db_host = "mysql.insrv5.local";
$db_user = "user";
$db_pass = "1234";
$db_name = "nominas_db";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'];
$mes_actual = date('m');
$anio_actual = date('Y');

if ($metodo === 'GET' && isset($_GET['uid'])) {
    $uid = strtolower(trim($_GET['uid']));
    $stmt = $pdo->prepare("SELECT id, concepto, importe, registrado_por FROM nominas_extras WHERE username = :uid AND mes = :mes AND anio = :anio ORDER BY fecha_registro DESC");
    $stmt->execute([':uid' => $uid, ':mes' => $mes_actual, ':anio' => $anio_actual]);
    $extras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'extras' => $extras]);
    exit;
}

if ($metodo === 'POST') {
    $uid = strtolower(trim($_POST['uid'] ?? ''));
    $concepto = trim($_POST['concepto'] ?? '');
    $importe = filter_var($_POST['importe'] ?? 0, FILTER_VALIDATE_FLOAT);
    $editor = $_SESSION['user_cn'] ?? 'Sistema';

    if ($uid && $concepto && $importe > 0) {
        $stmt = $pdo->prepare("INSERT INTO nominas_extras (username, mes, anio, concepto, importe, registrado_por) VALUES (:uid, :mes, :anio, :concepto, :importe, :editor)");
        $stmt->execute([
            ':uid' => $uid,
            ':mes' => $mes_actual,
            ':anio' => $anio_actual,
            ':concepto' => $concepto,
            ':importe' => $importe,
            ':editor' => $editor
        ]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    }
    exit;
}

if ($metodo === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    $id = intval($data['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM nominas_extras WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
    }
    exit;
}
?>