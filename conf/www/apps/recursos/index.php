<?php
require_once 'core.php';

// Crear carpetas base LDAP
$todos_los_grupos_ldap = ['Todos'];
$ldap_conn = @ldap_connect("ldaps://openldap.insrv5.local:636");
if ($ldap_conn) {
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    if (@ldap_bind($ldap_conn, "cn=visor-usuarios,dc=insrv5,dc=local", "visorpwd")) {
        $search = @ldap_search($ldap_conn, "dc=insrv5,dc=local", "(objectClass=groupOfNames)");
        if ($search) {
            $entries = ldap_get_entries($ldap_conn, $search);
            for ($i = 0; $i < $entries["count"]; $i++) {
                if (isset($entries[$i]["cn"][0])) {
                    $gn = trim($entries[$i]["cn"][0]);

                    if (strtolower($gn) === 'trabajadores') continue;

                    if (strtolower($gn) == "it") $rm = "IT";
                    elseif (strtolower($gn) == "recursos humanos" || strtolower($gn) == "rrhh") $rm = "RRHH";
                    elseif (strtolower($gn) == "marketing") $rm = "Marketing";
                    elseif (strtolower($gn) == "administracion" || strtolower($gn) == "administración") $rm = "Administracion";
                    else $rm = ucfirst($gn);

                    if (!in_array($rm, $todos_los_grupos_ldap)) {
                        $todos_los_grupos_ldap[] = $rm;
                    }
                }
            }
        }
    }
}
$base_dir = realpath(__DIR__ . '/archivos');
foreach ($todos_los_grupos_ldap as $grupo) {
    if (!is_dir($base_dir . '/' . $grupo)) @mkdir($base_dir . '/' . $grupo, 0775, true);
}

// Cargar permisos
$grupos_escaped = array_map(function ($g) use ($db) {
    return "'" . $db->real_escape_string($g) . "'";
}, $mis_grupos);
$grupos_str = implode(",", $grupos_escaped);
$uid_escaped = $db->real_escape_string($usuario_activo_uid);

$query_perms = "SELECT ruta FROM permisos_recursos WHERE (tipo_entidad = 'usuario' AND nombre_entidad = '$uid_escaped') OR (tipo_entidad = 'grupo' AND nombre_entidad IN ($grupos_str))";
$res_perms = $db->query($query_perms);
$rutas_explicitas = [];
if ($res_perms) {
    while ($r = $res_perms->fetch_assoc()) $rutas_explicitas[] = $r['ruta'];
}

// Extraer Información de Ruta
$folder_req = $_GET['folder'] ?? 'Todos';
$p_info = get_path_info($folder_req, $rol_actual, $mis_grupos, $rutas_explicitas, $base_dir);

$requested_path = $p_info['requested_path'];
$base_folder = $p_info['base_folder'];
$path_parts = $p_info['path_parts'];
$directorio_objetivo = $p_info['directorio_objetivo'];
$has_full_access = $p_info['has_full_access'];

$msg = $_GET['msg'] ?? '';
$msg_type = $_GET['type'] ?? 'success';

$colores_roles = [
    'IT' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
    'RRHH' => 'bg-rose-100 text-rose-700 border-rose-200',
    'Administracion' => 'bg-violet-100 text-violet-700 border-violet-200',
    'Marketing' => 'bg-amber-100 text-amber-700 border-amber-200'
];
$badge_class = $colores_roles[$rol_actual] ?? 'bg-slate-100 text-slate-700 border-slate-200';

$carpetas_visibles_sidebar = [];
$elementos_root = is_dir($base_dir) ? array_diff(scandir($base_dir), array('.', '..')) : [];
foreach ($elementos_root as $r) {
    if (is_dir($base_dir . '/' . $r) && check_visibility($r, $rol_actual, $mis_grupos, $rutas_explicitas)) {
        $carpetas_visibles_sidebar[] = $r;
    }
}

$elementos = is_dir($directorio_objetivo) ? array_diff(scandir($directorio_objetivo), array('.', '..')) : [];
$carpetas = [];
$archivos = [];
foreach ($elementos as $el) {
    if (!check_visibility($requested_path . '/' . $el, $rol_actual, $mis_grupos, $rutas_explicitas)) continue;
    if (is_dir($directorio_objetivo . $el)) $carpetas[] = $el;
    else $archivos[] = $el;
}

function formatearTamano($bytes)
{
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    elseif ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' b';
}

function obtenerIconoArchivo($ext)
{
    $ext = strtolower($ext);
    $svg_pdf = '<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9ZM10.5 13.5h3m-3 2.25h3" />';
    $svg_db = '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />';
    $svg_tabla = '<path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0 1 12 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125m-9.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25" />';
    $svg_codigo = '<path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9.75L16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0 0 20.25 18V6A2.25 2.25 0 0 0 18 3.75H6A2.25 2.25 0 0 0 3.75 6v12A2.25 2.25 0 0 0 6 20.25Z" />';
    $svg_img = '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />';
    $svg_zip = '<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />';

    if (in_array($ext, ['pdf'])) return ['bg' => 'bg-rose-50', 'text' => 'text-rose-600', 'svg' => $svg_pdf];
    if (in_array($ext, ['sql', 'db', 'sqlite'])) return ['bg' => 'bg-sky-50', 'text' => 'text-sky-600', 'svg' => $svg_db];
    if (in_array($ext, ['xls', 'xlsx', 'csv'])) return ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'svg' => $svg_tabla];
    if (in_array($ext, ['php', 'html', 'js', 'css', 'json', 'sh', 'txt'])) return ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'svg' => $svg_codigo];
    if (in_array($ext, ['png', 'jpg', 'jpeg', 'svg'])) return ['bg' => 'bg-orange-50', 'text' => 'text-orange-500', 'svg' => $svg_img];
    if (in_array($ext, ['zip', 'rar', 'tar', 'gz'])) return ['bg' => 'bg-violet-50', 'text' => 'text-violet-600', 'svg' => $svg_zip];

    return ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'svg' => $svg_pdf];
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestor de Recursos - Insrv5 Workspace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen text-slate-800 relative">

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">I5</div>
                    <span class="text-xl font-bold tracking-tight text-slate-900">Insrv5 Workspace</span>
                </div>
                <div class="flex items-center space-x-6">
                    <div class="hidden md:flex items-center text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span> Red Interna / VPN
                    </div>
                    <div class="flex items-center space-x-3 bg-slate-50 py-1.5 px-3 rounded-full border border-slate-100 hidden sm:flex">
                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold">
                            <?php echo strtoupper(substr($display_name, 0, 1)); ?>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-semibold leading-none text-slate-700"><?php echo htmlspecialchars($display_name); ?></span>
                            <span class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($usuario_activo_uid); ?>@insrv5.net</span>
                        </div>
                        <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($rol_actual); ?>
                        </span>
                    </div>
                    <a href="https://insrv5.net/users/logout.php" class="flex items-center text-slate-500 hover:text-red-600 transition-colors font-medium text-sm group">
                        <span class="hidden sm:block mr-2 group-hover:underline">Salir</span>
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="https://insrv5.net/users/dashboard.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-amber-600 mb-6 transition-colors group">
            <svg class="mr-2 h-4 w-4 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg> Volver al Dashboard
        </a>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44z" />
                        </svg>
                    </div> Gestor de Recursos
                </h1>
                <p class="mt-2 text-slate-500">Sube, descarga y comparte archivos con tu departamento.</p>
            </div>

            <?php if ($is_it): ?>
                <button onclick="abrirModalConfig()" class="bg-slate-800 text-white px-4 py-2 rounded-lg text-sm font-semibold flex items-center gap-2 hover:bg-slate-700 transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Configuración Avanzada
                </button>
            <?php endif; ?>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <aside class="w-full lg:w-64 shrink-0">
                <div class="bg-white rounded-2xl border border-slate-200 p-4 shadow-sm sticky top-24">
                    <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4 px-2">Departamentos</h2>
                    <nav class="space-y-1">
                        <?php foreach ($carpetas_visibles_sidebar as $carpeta):
                            $activa = ($carpeta === $base_folder);
                            $clase_activa = $activa ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-slate-600 hover:bg-slate-50';
                        ?>
                            <a href="?folder=<?php echo urlencode($carpeta); ?>" class="flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all <?php echo $clase_activa; ?>">
                                <svg class="w-5 h-5 <?php echo $activa ? 'text-indigo-500' : 'text-slate-400'; ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z" />
                                </svg>
                                <?php echo htmlspecialchars($carpeta); ?>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                </div>
            </aside>

            <div class="flex-1 flex flex-col gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">

                    <div class="flex items-center gap-2 text-sm text-slate-500 font-medium mb-4">
                        <?php
                        $ruta_acum = '';
                        foreach ($path_parts as $index => $part):
                            if (!$part) continue;
                            $ruta_acum .= ($index === 0 ? $part : '/' . $part);
                        ?>
                            <a href="?folder=<?php echo urlencode($ruta_acum); ?>" class="hover:text-indigo-600 transition-colors">
                                <?php echo htmlspecialchars($part); ?>
                            </a>
                            <?php if ($index < count($path_parts) - 1): ?>
                                <span class="text-slate-300">/</span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-slate-100 pb-4 mb-4 gap-4">
                        <h2 class="text-2xl font-bold text-slate-800">Directorio Actual</h2>

                        <?php if ($has_full_access): ?>
                            <div class="flex flex-wrap gap-2">
                                <button onclick="abrirModalInput('Carpeta', 'new_folder')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 border border-slate-200 cursor-pointer relative z-20">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 0 1 2-2h6l2 2h6a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
                                    </svg> Nueva Carpeta
                                </button>
                                <button onclick="abrirModalInput('Archivo', 'new_file')" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-semibold transition-colors flex items-center gap-2 border border-slate-200 cursor-pointer relative z-20">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 13h6m-3-3v6m5 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z" />
                                    </svg> Nuevo Archivo
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($msg): ?>
                        <div class="p-4 mb-6 rounded-xl text-sm font-medium <?php echo $msg_type === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'; ?>">
                            <?php echo htmlspecialchars($msg); ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">

                        <?php if (count($path_parts) > 1):
                            $parent_parts = $path_parts;
                            array_pop($parent_parts);
                            $parent_path_str = implode('/', $parent_parts);
                        ?>
                            <a href="?folder=<?php echo urlencode($parent_path_str); ?>" class="border border-dashed border-slate-300 rounded-xl p-4 flex items-center gap-3 hover:bg-slate-50 transition-colors">
                                <div class="w-10 h-10 bg-slate-100 text-slate-500 rounded-lg flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                                    </svg>
                                </div>
                                <span class="font-semibold text-slate-600">Subir un nivel</span>
                            </a>
                        <?php endif; ?>

                        <?php foreach ($carpetas as $carpeta):
                            $item_path = $requested_path . '/' . $carpeta;
                            $has_item_access = check_access($item_path, $rol_actual, $mis_grupos, $rutas_explicitas);
                            $info = obtenerInfoEdicion($item_path);
                            $editor_carpeta = $info['ultimo_editor'] ?? 'Sistema';

                            $is_owner = ($editor_carpeta === $display_name);

                            $can_manage_item = ($base_folder === 'Todos') ? ($is_it || $is_owner) : $has_full_access;
                            $show_item_permissions = ($is_it || $has_item_access) && ($base_folder !== 'Todos');
                        ?>
                            <div class="group relative bg-white rounded-xl border border-slate-200 p-4 hover:border-indigo-300 transition-all flex flex-col justify-between h-full min-h-[100px]">
                                <a href="?folder=<?php echo urlencode($item_path); ?>" class="absolute inset-0 z-0 rounded-xl"></a>

                                <div class="flex justify-between items-start relative z-10 pointer-events-none">
                                    <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-500 flex items-center justify-center shrink-0 transition-colors group-hover:bg-indigo-100">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z" />
                                        </svg>
                                    </div>

                                    <div class="opacity-0 group-hover:opacity-100 flex gap-1 transition-opacity bg-white/90 rounded p-1 pointer-events-auto">
                                        <?php if ($show_item_permissions): ?>
                                            <button onclick="abrirModalPermisos('<?php echo htmlspecialchars($item_path, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-blue-600 bg-white border border-slate-100 shadow-sm rounded" title="Permisos">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 0 1 2 2m4 0a6 6 0 0 1-7.743 5.743L11 17H9v2H7v2H4a1 1 0 0 1-1-1v-2.586a1 1 0 0 1 .293-.707l5.964-5.964A6 6 0 1 1 21 9z" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($can_manage_item): ?>
                                            <button onclick="abrirModalRenombrar('<?php echo htmlspecialchars($carpeta, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-indigo-600 bg-white border border-slate-100 shadow-sm rounded" title="Renombrar">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 1 1 3.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button onclick="abrirModalBorrar('<?php echo htmlspecialchars($carpeta, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-rose-600 bg-white border border-slate-100 shadow-sm rounded" title="Eliminar">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="font-semibold text-sm text-slate-800 truncate mt-2 relative z-10 pointer-events-none">
                                    <?php echo htmlspecialchars($carpeta); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($archivos as $archivo):
                            $item_path = $requested_path . '/' . $archivo;
                            $has_item_access = check_access($item_path, $rol_actual, $mis_grupos, $rutas_explicitas);
                            $info = obtenerInfoEdicion($item_path);
                            $editor = $info['ultimo_editor'] ?? 'Sistema';

                            $is_owner = ($editor === $display_name);

                            $fecha = isset($info['fecha_modificacion']) ? date("d/m H:i", strtotime($info['fecha_modificacion'])) : date("d/m H:i", filemtime($directorio_objetivo . $archivo));
                            $ext = pathinfo($archivo, PATHINFO_EXTENSION);
                            $icono = obtenerIconoArchivo($ext);
                            $peso = formatearTamano(filesize($directorio_objetivo . $archivo));
                            $es_editable = in_array(strtolower($ext), ['php', 'sql', 'html', 'css', 'js', 'json', 'sh', 'txt', 'md', 'xml', 'csv', 'yaml']);

                            $can_manage_item = ($base_folder === 'Todos') ? ($is_it || $is_owner) : $has_full_access;
                            $show_item_permissions = ($is_it || $has_item_access) && ($base_folder !== 'Todos');
                        ?>
                            <div class="group relative bg-white rounded-xl border border-slate-200 p-4 hover:border-indigo-300 transition-all flex flex-col justify-between h-full min-h-[120px]">
                                <a href="api.php?download=<?php echo urlencode($archivo); ?>&folder=<?php echo urlencode($requested_path); ?>" class="absolute inset-0 z-0 rounded-xl"></a>

                                <div class="flex justify-between items-start relative z-10 pointer-events-none">
                                    <div class="w-10 h-10 rounded-lg <?php echo $icono['bg'] . ' ' . $icono['text']; ?> flex items-center justify-center shrink-0">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <?php echo $icono['svg']; ?>
                                        </svg>
                                    </div>
                                    <div class="opacity-0 group-hover:opacity-100 flex gap-1 transition-opacity bg-white/90 rounded p-1 pointer-events-auto">
                                        <a href="api.php?download=<?php echo urlencode($archivo); ?>&folder=<?php echo urlencode($requested_path); ?>" class="p-1.5 text-slate-400 hover:text-indigo-600 bg-white border border-slate-100 shadow-sm rounded" title="Descargar">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                        </a>

                                        <?php if ($show_item_permissions): ?>
                                            <button onclick="abrirModalPermisos('<?php echo htmlspecialchars($item_path, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-blue-600 bg-white border border-slate-100 shadow-sm rounded" title="Permisos">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 0 1 2 2m4 0a6 6 0 0 1-7.743 5.743L11 17H9v2H7v2H4a1 1 0 0 1-1-1v-2.586a1 1 0 0 1 .293-.707l5.964-5.964A6 6 0 1 1 21 9z" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($can_manage_item): ?>
                                            <?php if ($es_editable): ?>
                                                <a href="editor.php?folder=<?php echo urlencode($requested_path); ?>&file=<?php echo urlencode($archivo); ?>" class="p-1.5 text-slate-400 hover:text-emerald-600 bg-white border border-slate-100 shadow-sm rounded" title="Editar">
                                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                </a>
                                            <?php endif; ?>
                                            <button onclick="abrirModalRenombrar('<?php echo htmlspecialchars($archivo, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-amber-600 bg-white border border-slate-100 shadow-sm rounded" title="Renombrar">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 1 1 3.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button onclick="abrirModalBorrar('<?php echo htmlspecialchars($archivo, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-rose-600 bg-white border border-slate-100 shadow-sm rounded" title="Eliminar">
                                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="mt-2 relative z-10 pointer-events-none">
                                    <p class="font-semibold text-sm text-slate-800 truncate" title="<?php echo htmlspecialchars($archivo); ?>">
                                        <?php echo htmlspecialchars($archivo); ?>
                                    </p>
                                    <div class="flex justify-between items-center mt-1">
                                        <p class="text-[10px] text-slate-400">Por: <span class="text-indigo-500 font-medium"><?php echo $editor; ?></span> • <?php echo $fecha; ?></p>
                                        <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded"><?php echo $peso; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($has_full_access): ?>
                        <div class="mt-8 pt-6 border-t border-slate-100 relative z-20">
                            <form method="POST" action="api.php" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-center gap-4">
                                <input type="hidden" name="current_folder" value="<?php echo htmlspecialchars($requested_path); ?>">
                                <div class="w-full">
                                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">Subir archivo a este directorio</label>
                                    <input type="file" name="archivo" required class="block w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 outline-none bg-slate-50 border border-slate-200 rounded-xl transition-all">
                                </div>
                                <button type="submit" class="w-full sm:w-auto mt-6 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm">Subir</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <div id="modalInput" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <form method="POST" action="api.php">
                <input type="hidden" name="current_folder" value="<?php echo htmlspecialchars($requested_path); ?>">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-slate-900 mb-4" id="modalInputTitle">Acción</h3>
                    <input type="hidden" name="rename_old" id="modalInputHidden" value="">
                    <input type="text" name="" id="modalInputField" required class="w-full px-4 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white transition-colors">
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="cerrarModalInput()" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">Aceptar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalBorrar" class="fixed inset-0 z-[110] hidden flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <form method="POST" action="api.php">
                <input type="hidden" name="current_folder" value="<?php echo htmlspecialchars($requested_path); ?>">
                <div class="p-6 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 mb-4">
                        <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Confirmar Eliminación</h3>
                    <p class="text-sm text-slate-500 mt-2">¿Seguro que deseas eliminar <strong id="textoBorrar" class="text-rose-600 break-all"></strong>? Esta acción es irreversible.</p>
                    <input type="hidden" name="delete_item" id="inputBorrar" value="">
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-center gap-3 border-t border-slate-100">
                    <button type="button" onclick="cerrarModalBorrar()" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 shadow-sm">Eliminar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalConfig" class="fixed inset-0 z-[120] hidden flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Configuración Avanzada
                </h3>
                <button type="button" onclick="cerrarModalConfig()" class="text-slate-400 hover:text-slate-600"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg></button>
            </div>
            <div class="p-6 overflow-y-auto flex-1 space-y-6">

                <div>
                    <h4 class="text-sm font-bold text-slate-800 mb-2">Ruta Base del Sistema</h4>
                    <div class="flex gap-2">
                        <input type="text" id="inputRutaRaiz" class="flex-1 bg-white border border-slate-300 text-sm rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500 font-mono">
                        <button onclick="guardarRutaRaiz()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm">Guardar</button>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <h4 class="text-sm font-bold text-slate-800 mb-2">Gestión de Carpetas Raíz</h4>
                    <div class="flex gap-2 mb-4">
                        <input type="text" id="inputNuevoDept" placeholder="Nombre de la nueva carpeta raíz..." class="flex-1 bg-white border border-slate-300 text-sm rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500">
                        <button onclick="crearDepartamento()" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm">Crear</button>
                    </div>

                    <div class="bg-slate-50 border border-slate-200 rounded-lg overflow-hidden">
                        <ul class="divide-y divide-slate-200" id="listaDepartamentosConfig">
                            <?php foreach ($elementos_root as $r):
                                if (!is_dir($base_dir . '/' . $r)) continue;
                            ?>
                                <li class="p-3 flex justify-between items-center hover:bg-slate-100 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-6l-2-2H5a2 2 0 0 0-2 2z" />
                                        </svg>
                                        <span class="font-semibold text-sm text-slate-700"><?php echo htmlspecialchars($r); ?></span>
                                    </div>
                                    <div class="flex gap-1">
                                        <?php if ($r !== 'Todos'): ?>
                                            <button onclick="abrirModalPermisosDesdeConfig('<?php echo htmlspecialchars($r, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-blue-600 bg-white border border-slate-200 rounded shadow-sm" title="Permisos">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 0 1 2 2m4 0a6 6 0 0 1-7.743 5.743L11 17H9v2H7v2H4a1 1 0 0 1-1-1v-2.586a1 1 0 0 1 .293-.707l5.964-5.964A6 6 0 1 1 21 9z" />
                                                </svg>
                                            </button>
                                            <button onclick="prepararRenombrarDept('<?php echo htmlspecialchars($r, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-amber-600 bg-white border border-slate-200 rounded shadow-sm" title="Renombrar">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 1 1 3.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                                </svg>
                                            </button>
                                            <button onclick="prepararBorrarDept('<?php echo htmlspecialchars($r, ENT_QUOTES); ?>')" class="p-1.5 text-slate-400 hover:text-rose-600 bg-white border border-slate-200 rounded shadow-sm" title="Eliminar">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-[10px] uppercase font-bold text-slate-400 bg-slate-100 px-2 py-1 rounded">Sistema</span>
                                        <?php endif; ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="modalDeptInput" class="fixed inset-0 z-[130] hidden flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <form onsubmit="confirmarRenombrarDept(event)">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-slate-900 mb-4">Renombrar Departamento</h3>
                    <input type="text" id="deptInputField" required class="w-full px-4 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white transition-colors">
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-end gap-3 border-t border-slate-100">
                    <button type="button" onclick="cerrarModalDeptInput()" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalDeptBorrar" class="fixed inset-0 z-[130] hidden flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <form onsubmit="confirmarBorrarDept(event)">
                <div class="p-6 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 mb-4">
                        <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" />
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900">Borrar Departamento</h3>
                    <p class="text-sm text-slate-500 mt-2">¿Seguro que deseas eliminar el departamento raíz <strong id="textoBorrarDept" class="text-rose-600 break-all"></strong>? Se perderán sus permisos.</p>
                </div>
                <div class="bg-slate-50 px-6 py-4 flex justify-center gap-3 border-t border-slate-100">
                    <button type="button" onclick="cerrarModalDeptBorrar()" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm">Cancelar</button>
                    <button type="submit" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 shadow-sm">Eliminar</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalPermisos" class="fixed inset-0 z-[130] hidden flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-6 border-b border-slate-100 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <button type="button" id="btnVolverConfig" onclick="volverAConfig()" class="hidden text-slate-400 hover:text-indigo-600 transition-colors" title="Volver a Configuración">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                    </button>
                    <h3 class="text-lg font-bold text-slate-900">Gestión de Permisos</h3>
                </div>
                <button type="button" onclick="cerrarModalPermisos()" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto flex-1">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Ruta seleccionada:</p>
                <div class="bg-slate-50 p-2 rounded-lg border border-slate-200 font-mono text-sm text-indigo-600 mb-6 truncate" id="permRutaLabel"></div>

                <h4 class="text-sm font-bold text-slate-800 mb-3">Agregar Permiso Extra</h4>
                <div class="flex gap-2 mb-3 relative z-30">
                    <select id="permTipo" class="bg-slate-50 border border-slate-300 text-sm rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500" onchange="togglePermInput()">
                        <option value="grupo">Grupo Departamental</option>
                        <option value="usuario">Usuario Concreto</option>
                    </select>
                </div>

                <div id="permInputGrupo" class="mb-4 flex gap-2 relative z-20">
                    <select id="permValGrupo" class="flex-1 bg-slate-50 border border-slate-300 text-sm rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="IT">IT</option>
                        <option value="RRHH">RRHH</option>
                        <option value="Administracion">Administración</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Todos">Todos (Toda la empresa)</option>
                    </select>
                    <button type="button" onclick="agregarPermiso()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-semibold shadow-sm">Agregar</button>
                </div>

                <div id="permInputUsuario" class="mb-4 hidden relative z-20">
                    <div class="relative">
                        <input type="text" id="searchUser" placeholder="Buscar usuario en LDAP..." class="w-full bg-slate-50 border border-slate-300 text-sm rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-indigo-500" onkeyup="searchLdapUser(this.value)">
                        <div id="searchResults" class="absolute z-10 w-full bg-white border border-slate-200 mt-1 rounded-lg shadow-lg hidden max-h-40 overflow-y-auto"></div>
                    </div>
                </div>

                <h4 class="text-sm font-bold text-slate-800 mb-3 mt-6 border-t pt-4">Niveles de Acceso</h4>

                <div id="permFilterContainer"></div>

                <div id="listaPermisos" class="space-y-2 relative z-10">
                </div>
            </div>
        </div>
    </div>

    <div id="modalCustomConfirm" class="fixed inset-0 z-[150] hidden flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="p-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 mb-4">
                    <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900" id="confirmTitle">Confirmar</h3>
                <div class="text-sm text-slate-500 mt-4 text-left bg-slate-50 p-4 rounded-xl border border-slate-100" id="confirmMsg"></div>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex justify-center gap-3 border-t border-slate-100">
                <button type="button" id="btnConfirmNo" class="px-4 py-2 rounded-xl text-sm font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-sm transition-colors">Cancelar</button>
                <button type="button" id="btnConfirmYes" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 shadow-sm transition-colors">Eliminar Permiso</button>
            </div>
        </div>
    </div>

    <div id="modalCustomAlert" class="fixed inset-0 z-[150] hidden flex items-center justify-center p-4 bg-slate-900/75 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="p-6 text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-rose-100 mb-4">
                    <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900" id="alertTitle">Error</h3>
                <p class="text-sm text-slate-500 mt-2" id="alertMsg"></p>
            </div>
            <div class="bg-slate-50 px-6 py-4 flex justify-center border-t border-slate-100">
                <button type="button" onclick="cerrarCustomAlert()" class="px-6 py-2 rounded-xl text-sm font-semibold text-white bg-slate-800 hover:bg-slate-700 shadow-sm transition-colors">Entendido</button>
            </div>
        </div>
    </div>

    <script src="main.js"></script>
</body>

</html>