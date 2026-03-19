<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

putenv('LDAPTLS_REQCERT=never');

if (!isset($_SESSION['user']) || !isset($_SESSION['rol'])) {
	header("Location: https://insrv5.net/users/index.php");
	exit();
}

// --- CONEXIÓN A BD ---
$db_host = "mysql.insrv5.local";
$db_user = "user"; // <-- Asegúrate de poner tu usuario real
$db_pass = "1234"; // <-- Asegúrate de poner tu contraseña real
$db_name = "insrv5_db";

try {
	$db = new mysqli($db_host, $db_user, $db_pass, $db_name);
} catch (Exception $e) {
	die("Error de conexión a la base de datos de recursos.");
}

// --- INICIALIZACIÓN DE CONFIGURACIÓN ---
$db->query("CREATE TABLE IF NOT EXISTS configuracion_recursos (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(512) NOT NULL
)");
$db->query("INSERT IGNORE INTO configuracion_recursos (clave, valor) VALUES ('ruta_raiz', 'archivos')");

$res_conf = $db->query("SELECT valor FROM configuracion_recursos WHERE clave = 'ruta_raiz'");
$ruta_raiz_db = $res_conf->fetch_assoc()['valor'] ?? 'archivos';

if (strpos($ruta_raiz_db, '/') === 0) {
	$base_dir = rtrim($ruta_raiz_db, '/');
	if (!is_dir($base_dir)) @mkdir($base_dir, 0775, true);
} else {
	$base_dir = realpath(__DIR__ . '/' . $ruta_raiz_db);
	if (!$base_dir) {
		@mkdir(__DIR__ . '/' . $ruta_raiz_db, 0775, true);
		$base_dir = realpath(__DIR__ . '/' . $ruta_raiz_db);
	}
}

// --- FUNCIONES DE AUDITORÍA Y LIMPIEZA ---
function registrarActividad($ruta, $nombre, $usuario, $accion = 'creacion')
{
	global $db;
	$stmt = $db->prepare("INSERT INTO registro_archivos (ruta_archivo, nombre_archivo, ultimo_editor, tipo_accion) 
                          VALUES (?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE ultimo_editor = ?, tipo_accion = ?, fecha_modificacion = NOW()");
	$stmt->bind_param("ssssss", $ruta, $nombre, $usuario, $accion, $usuario, $accion);
	$stmt->execute();

	$stmtHist = $db->prepare("INSERT INTO historial_cambios (ruta_archivo, editor, accion, fecha) VALUES (?, ?, ?, NOW())");
	if ($stmtHist) {
		$stmtHist->bind_param("sss", $ruta, $usuario, $accion);
		$stmtHist->execute();
	}
}

function limpiarRastrosDB($ruta)
{
	global $db;
	$ruta_esc = $db->real_escape_string($ruta);
	$like_ruta = $db->real_escape_string($ruta . '/%');
	$db->query("DELETE FROM registro_archivos WHERE ruta_archivo = '$ruta_esc' OR ruta_archivo LIKE '$like_ruta'");
	$db->query("DELETE FROM permisos_recursos WHERE ruta = '$ruta_esc' OR ruta LIKE '$like_ruta'");
	$db->query("DELETE FROM historial_cambios WHERE ruta_archivo = '$ruta_esc' OR ruta_archivo LIKE '$like_ruta'");
}

function renombrarRastrosDB($ruta_vieja, $ruta_nueva)
{
	global $db;
	$old_esc = $db->real_escape_string($ruta_vieja);
	$new_esc = $db->real_escape_string($ruta_nueva);
	$like_old = $db->real_escape_string($ruta_vieja . '/%');
	$len_old = strlen($ruta_vieja);

	$db->query("UPDATE registro_archivos SET ruta_archivo = CONCAT('$new_esc', SUBSTRING(ruta_archivo, $len_old + 1)) WHERE ruta_archivo = '$old_esc' OR ruta_archivo LIKE '$like_old'");
	$nuevo_nombre = $db->real_escape_string(basename($ruta_nueva));
	$db->query("UPDATE registro_archivos SET nombre_archivo = '$nuevo_nombre' WHERE ruta_archivo = '$new_esc'");
	$db->query("UPDATE permisos_recursos SET ruta = CONCAT('$new_esc', SUBSTRING(ruta, $len_old + 1)) WHERE ruta = '$old_esc' OR ruta LIKE '$like_old'");
	$db->query("UPDATE historial_cambios SET ruta_archivo = CONCAT('$new_esc', SUBSTRING(ruta_archivo, $len_old + 1)) WHERE ruta_archivo = '$old_esc' OR ruta_archivo LIKE '$like_old'");
}

function obtenerInfoEdicion($ruta)
{
	global $db;
	$stmt = $db->prepare("SELECT ultimo_editor, fecha_modificacion FROM registro_archivos WHERE ruta_archivo = ?");
	$stmt->bind_param("s", $ruta);
	$stmt->execute();
	return $stmt->get_result()->fetch_assoc();
}

// --- LÓGICA DE USUARIO Y ROLES ---
$display_name = $_SESSION['user_cn'] ?? 'Usuario';
$raw_user = strtolower(str_replace(' ', '.', $display_name));
if ($raw_user === 'usuario' && isset($_SESSION['user'])) {
	$raw_user = strtolower($_SESSION['user']);
}
$usuario_activo_uid = $_SESSION['uid'] ?? $_SESSION['user'] ?? $raw_user;
$rol = $_SESSION['rol'] ?? 'Desconocido';

$rol_crudo = trim(strtolower($_SESSION['rol'] ?? ''));
if ($rol_crudo === 'recursos humanos' || $rol_crudo === 'rrhh') $rol_actual = 'RRHH';
elseif ($rol_crudo === 'administracion' || $rol_crudo === 'administración') $rol_actual = 'Administracion';
elseif ($rol_crudo === 'marketing') $rol_actual = 'Marketing';
elseif ($rol_crudo === 'it') $rol_actual = 'IT';
else $rol_actual = ucfirst(trim($_SESSION['rol'] ?? 'Todos'));

$mis_grupos = ['Todos'];
if ($rol_actual !== 'Todos') {
	$mis_grupos[] = $rol_actual;
}
$is_it = ($rol_actual === 'IT');

// --- SISTEMA DE VISIBILIDAD PURA (Desvinculado del nombre de la carpeta) ---
function check_access($path, $rol, $mis_grupos, $rutas_explicitas)
{
	if ($rol === 'IT') return true;

	$parts = explode('/', trim($path, '/'));
	if ($parts[0] === 'Todos') return true; // La carpeta Todos siempre es pública

	// Solo accedes si tienes una regla explícita en la base de datos
	foreach ($rutas_explicitas as $re) {
		if ($path === $re || strpos($path, $re . '/') === 0) return true;
	}
	return false;
}

function check_visibility($path, $rol, $mis_grupos, $rutas_explicitas)
{
	if (check_access($path, $rol, $mis_grupos, $rutas_explicitas)) return true;
	foreach ($rutas_explicitas as $re) {
		if (strpos($re . '/', $path . '/') === 0) return true;
	}
	return false;
}

function get_path_info($requested_path, $rol_actual, $mis_grupos, $rutas_explicitas, $base_dir)
{
	$requested_path = str_replace(['../', '..\\', chr(0)], '', $requested_path);
	$requested_path = trim($requested_path, '/');
	$path_parts = explode('/', $requested_path);
	$base_folder = $path_parts[0] ?: 'Todos';

	if (!check_visibility($requested_path, $rol_actual, $mis_grupos, $rutas_explicitas)) {
		$requested_path = 'Todos';
		$base_folder = 'Todos';
		$path_parts = ['Todos'];
	}

	$directorio_objetivo = $base_dir . '/' . $requested_path . '/';

	// EVITAR FATAL ERRORS SI LA CARPETA FÍSICA NO EXISTE (ej. recién renombrada)
	if (!is_dir($directorio_objetivo)) {
		$requested_path = 'Todos';
		$base_folder = 'Todos';
		$path_parts = ['Todos'];
		$directorio_objetivo = $base_dir . '/Todos/';
		if (!is_dir($directorio_objetivo)) @mkdir($directorio_objetivo, 0775, true);
	}

	return [
		'requested_path' => $requested_path,
		'base_folder' => $base_folder,
		'path_parts' => $path_parts,
		'directorio_objetivo' => $directorio_objetivo,
		'has_full_access' => check_access($requested_path, $rol_actual, $mis_grupos, $rutas_explicitas)
	];
}
