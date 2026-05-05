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

putenv('LDAPTLS_REQCERT=never');

if (!isset($_SESSION['user']) || !isset($_SESSION['rol'])) {
    header("Location: index.php");
    exit();
}

$raw_user = htmlspecialchars($_SESSION['user']);
$sso_token = session_id();

$rol_crudo = trim(strtolower($_SESSION['rol']));
if ($rol_crudo === 'recursos humanos' || $rol_crudo === 'rrhh') $rol_actual = 'RRHH';
elseif ($rol_crudo === 'administracion' || $rol_crudo === 'administración') $rol_actual = 'Administracion';
elseif ($rol_crudo === 'marketing') $rol_actual = 'Marketing';
elseif ($rol_crudo === 'it') $rol_actual = 'IT';
else $rol_actual = ucfirst(trim($_SESSION['rol']));

if (isset($_SESSION['user_cn']) && !empty($_SESSION['user_cn'])) {
    $display_name = htmlspecialchars($_SESSION['user_cn']);
} else {
    $display_name = ucfirst(substr($raw_user, 0, 1)) . '. ' . ucfirst(substr($raw_user, 1));
    if (strlen($raw_user) <= 3) $display_name = ucfirst($raw_user);
}

$colores_roles = [
    'IT' => 'bg-indigo-100 text-indigo-800 border-indigo-200',
    'RRHH' => 'bg-rose-100 text-rose-800 border-rose-200',
    'Administracion' => 'bg-blue-100 text-blue-800 border-blue-200',
    'Marketing' => 'bg-amber-100 text-amber-800 border-amber-200'
];
$badge_class = $colores_roles[$rol_actual] ?? 'bg-emerald-100 text-emerald-800 border-emerald-200';

function getUserIP()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return $_SERVER['REMOTE_ADDR'];
}

$client_ip = trim(getUserIP());
$vpn_prefixes = ['192.168.', '10.', '127.0.0.1'];

$is_vpn_connected = false;
foreach ($vpn_prefixes as $prefix) {
    if (strpos($client_ip, $prefix) === 0) {
        $is_vpn_connected = true;
        break;
    }
}

$db_host = "mysql.insrv5.local";
$db_user = "user";
$db_pass = "1234";
$db_name = "insrv5_db";

$apps_disponibles = [];
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $stmt = $pdo->query("SELECT * FROM dashboard_apps ORDER BY id ASC");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $roles_app = array_map('trim', explode(',', $row['roles_permitidos']));
        if (in_array('Todos', $roles_app) || in_array($rol_actual, $roles_app) || $rol_actual === 'IT') {
            $apps_disponibles[] = $row;
        }
    }
} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
}

$ldap_grupos = ['Todos', 'Trabajador'];
if ($rol_actual === 'IT') {
    $ldap_host = "ldaps://openldap.insrv5.local:636";
    $ldap_dn_base = "dc=insrv5,dc=local";
    $ldap_user = "cn=visor-usuarios,dc=insrv5,dc=local";
    $ldap_pass = "visorpwd";

    $ldap_conn = @ldap_connect($ldap_host);
    if ($ldap_conn) {
        ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

        if (@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
            $search_grupos = @ldap_search($ldap_conn, $ldap_dn_base, "(objectClass=groupOfNames)");
            if ($search_grupos) {
                $entradas_grupos = ldap_get_entries($ldap_conn, $search_grupos);
                for ($i = 0; $i < $entradas_grupos["count"]; $i++) {
                    if (isset($entradas_grupos[$i]["cn"][0])) {

                        $gn_raw = $entradas_grupos[$i]["cn"][0];
                        $gn_lower = strtolower($gn_raw);

                        if ($gn_lower == "it") $rol_mapeado = "IT";
                        elseif ($gn_lower == "recursos humanos" || $gn_lower == "rrhh") $rol_mapeado = "RRHH";
                        elseif ($gn_lower == "marketing") $rol_mapeado = "Marketing";
                        elseif ($gn_lower == "administracion" || $gn_lower == "administración") $rol_mapeado = "Administracion";
                        else $rol_mapeado = ucfirst($gn_raw);

                        if (!in_array($rol_mapeado, $ldap_grupos)) {
                            $ldap_grupos[] = $rol_mapeado;
                        }
                    }
                }
            }
        }
        ldap_close($ldap_conn);
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Insrv5 Workspace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-slate-50 min-h-screen text-slate-800">

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">

                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">I5</div>
                    <span class="text-xl font-bold tracking-tight text-slate-900">Insrv5 Workspace</span>
                </div>

                <div class="flex items-center space-x-6">
                    <?php if ($is_vpn_connected): ?>
                        <div class="hidden md:flex items-center text-xs font-semibold text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-full border border-emerald-200">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                            Red Interna / VPN
                        </div>
                    <?php else: ?>
                        <div class="hidden md:flex items-center text-xs font-semibold text-rose-700 bg-rose-50 px-3 py-1.5 rounded-full border border-rose-200">
                            <span class="w-2 h-2 rounded-full bg-rose-500 mr-2"></span>
                            Red Externa
                        </div>
                    <?php endif; ?>

                    <div class="flex items-center space-x-3 bg-slate-50 py-1.5 px-3 rounded-full border border-slate-100 hidden sm:flex">
                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-semibold leading-none text-slate-700"><?php echo $display_name; ?></span>
                            <span class="text-xs text-slate-500 mt-1"><?php echo $raw_user; ?>@insrv5.net</span>
                        </div>
                        <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $badge_class; ?>">
                            <?php echo $rol_actual; ?>
                        </span>
                    </div>

                    <a href="logout.php" class="flex items-center text-slate-500 hover:text-red-600 transition-colors font-medium text-sm group">
                        <span class="hidden sm:block mr-2 group-hover:underline">Salir</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">¡Hola, <?php echo $display_name; ?>! 👋</h1>
                <p class="mt-2 text-slate-500 text-lg">Bienvenido a tu panel de control.</p>
            </div>

            <?php if ($rol_actual === 'IT'): ?>
                <button onclick="abrirModalFormulario()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl text-sm font-semibold transition-all shadow-sm flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Añadir Aplicación
                </button>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <?php if (empty($apps_disponibles)): ?>
                <div class="col-span-full py-12 text-center bg-white rounded-2xl border border-slate-200 border-dashed">
                    <p class="text-slate-500">No tienes aplicaciones asignadas a tu rol en este momento.</p>
                </div>
            <?php endif; ?>

            <?php
            foreach ($apps_disponibles as $app):
                $app_url = $app['url'];

                $parsed_path = parse_url($app_url, PHP_URL_PATH) ?? '';
                if (substr($parsed_path, -4) === '/sso' && strpos($app_url, 'token=') === false) {
                    $app_url .= '?token=' . $sso_token;
                }
                $app_url = str_replace('{sso_token}', $sso_token, $app_url);

                // NUEVA LÓGICA DE VPN
                $requiere_vpn = (bool)($app['requiere_vpn'] ?? 1); // 1 por defecto por seguridad
                $can_access = !$requiere_vpn || $is_vpn_connected; // Se puede acceder si no pide VPN o si estamos dentro

                $final_href = $can_access ? htmlspecialchars($app_url) : '#';
                $onclick_attr = !$can_access ? 'onclick="showVpnModal(event)"' : '';

                $color_classes = explode(' ', $app['color_fondo']);
                $bg_base = $color_classes[0] ?? 'bg-blue-50';
                $text_base = $color_classes[1] ?? 'text-blue-600';

                $hover_bg = str_replace('bg-', 'group-hover:bg-', $bg_base);
                $hover_bg = preg_replace('/-\d+/', '-500', $hover_bg);
                $hover_text = str_replace('text-', 'group-hover:text-', $text_base);
            ?>

                <div class="relative block group bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col h-full">

                    <?php if ($rol_actual === 'IT'): ?>
                        <div class="absolute top-4 right-4 flex space-x-2 opacity-0 group-hover:opacity-100 transition-opacity z-10">
                            <button onclick="editarApp(<?php echo htmlspecialchars(json_encode($app)); ?>)" class="text-slate-400 hover:text-indigo-600 p-1.5 bg-white rounded-md shadow-sm border border-slate-100" title="Editar Aplicación">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                </svg>
                            </button>
                            <button onclick="borrarApp(<?php echo $app['id']; ?>)" class="text-slate-400 hover:text-rose-600 p-1.5 bg-white rounded-md shadow-sm border border-slate-100" title="Eliminar Aplicación">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo $final_href; ?>" <?php echo $onclick_attr; ?> class="block h-full relative">
                        <?php if ($requiere_vpn && !$is_vpn_connected): ?>
                            <div class="absolute top-0 right-0 p-1 bg-rose-50 text-rose-500 rounded-lg" title="Requiere VPN">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 rounded-xl <?php echo htmlspecialchars($bg_base . ' ' . $text_base); ?> flex items-center justify-center <?php echo $hover_bg; ?> group-hover:text-white transition-colors duration-300 [&>svg]:w-6 [&>svg]:h-6 [&>svg]:shrink-0">
                                <?php echo $app['icono_svg']; ?>
                            </div>
                            <h3 class="ml-4 text-xl font-semibold text-slate-800 <?php echo $hover_text; ?> transition-colors pr-6">
                                <?php echo htmlspecialchars($app['nombre']); ?>
                            </h3>
                        </div>
                        <p class="text-slate-500 text-sm leading-relaxed">
                            <?php echo htmlspecialchars($app['descripcion'] ?? ''); ?>
                        </p>
                    </a>
                </div>

            <?php endforeach; ?>

        </div>
    </main>

    <div id="vpnModal" class="fixed inset-0 z-[100] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="fixed inset-0 bg-slate-900/75 transition-opacity backdrop-blur-sm"></div>
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100">
                    <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                <h3 class="text-xl font-semibold leading-6 text-slate-900" id="modal-title">Acceso Restringido</h3>
                                <div class="mt-2">
                                    <p class="text-sm text-slate-500">
                                        Esta aplicación requiere estar conectado a la <strong class="text-slate-700">VPN Corporativa</strong> o encontrarte físicamente en la oficina. Por motivos de seguridad, tu conexión ha sido bloqueada.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <a href="guia_vpn.php" class="inline-flex w-full justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto transition-colors">Descargar VPN / Ayuda</a>
                        <button type="button" onclick="closeVpnModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-colors">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($rol_actual === 'IT'): ?>

        <div id="modalCrearApp" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-xl overflow-hidden flex flex-col max-h-[95vh]">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <h3 id="modalAppTitle" class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Añadir Aplicación al SSO
                    </h3>
                    <button type="button" onclick="cerrarModalFormulario()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-6 overflow-y-auto">
                    <form id="formNuevaApp" class="space-y-5">
                        <input type="hidden" name="app_id" id="formAppId" value="">

                        <div class="grid grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre de la Aplicación</label>
                                <input type="text" name="nombre" id="formAppNombre" required placeholder="Ej: Portainer" class="w-full px-4 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white transition-all">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Descripción Breve</label>
                                <input type="text" name="descripcion" id="formAppDesc" required placeholder="Gestiona los contenedores Docker..." class="w-full px-4 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white transition-all">
                            </div>
                            <div class="col-span-2">
                                <label class="block text-sm font-semibold text-slate-700 mb-1">Enlace / URL destino</label>
                                <input type="text" name="url" id="formAppUrl" required placeholder="https://app.insrv5.local/sso" class="w-full px-4 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 bg-slate-50 focus:bg-white transition-all">

                                <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-lg flex gap-3 items-start">
                                    <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <div class="text-xs text-amber-800">
                                        <strong>Autenticación SSO:</strong> Si la app requiere login, la URL debe terminar en <code class="bg-amber-100 text-amber-900 px-1.5 py-0.5 rounded font-mono font-bold">/sso</code>.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 flex items-center justify-between">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700">Requiere conexión VPN</label>
                                <p class="text-xs text-slate-500 mt-0.5">Actívalo para bloquear el acceso desde redes externas públicas.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="requiere_vpn" id="formAppVpn" value="1" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Color Corporativo</label>
                            <select name="color" id="formAppColor" class="w-full px-4 py-2.5 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 bg-white">
                                <option value="bg-blue-50 text-blue-600">Azul (General)</option>
                                <option value="bg-orange-50 text-orange-600">Naranja (AWS / Proxmox)</option>
                                <option value="bg-emerald-50 text-emerald-600">Verde (Finanzas / Logs)</option>
                                <option value="bg-lime-50 text-lime-600">Lima (Monitorización)</option>
                                <option value="bg-violet-50 text-violet-600">Violeta (RRHH / SSO)</option>
                                <option value="bg-fuchsia-50 text-fuchsia-600">Fucsia (Diseño / Creativo)</option>
                                <option value="bg-rose-50 text-rose-600">Rojo (Seguridad / Admin)</option>
                                <option value="bg-pink-50 text-pink-600">Rosa (Tickets / Ayuda)</option>
                                <option value="bg-amber-50 text-amber-600">Ámbar (Marketing / Alertas)</option>
                                <option value="bg-sky-50 text-sky-600">Celeste (Bases de datos)</option>
                                <option value="bg-cyan-50 text-cyan-600">Cyan (Docker / Nube)</option>
                                <option value="bg-teal-50 text-teal-600">Teal (Redes / Comunicaciones)</option>
                                <option value="bg-slate-100 text-slate-700">Gris Neutral</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Diseño del Icono</label>
                            <div class="flex items-start gap-4">
                                <div id="previewBoxWrapper" class="w-14 h-14 rounded-xl border border-slate-200 flex items-center justify-center bg-blue-50 text-blue-600 shrink-0 transition-colors">
                                    <div id="previewIconoContainer" class="w-7 h-7">
                                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                        </svg>
                                    </div>
                                </div>

                                <div class="flex-1 space-y-2">
                                    <textarea name="icono" id="formAppIcono" required rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-xl outline-none focus:ring-2 focus:ring-indigo-500 text-xs font-mono text-slate-600 bg-slate-50 focus:bg-white hidden"></textarea>

                                    <button type="button" onclick="toggleGaleria()" class="w-full bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-semibold py-2.5 rounded-xl transition-colors flex justify-center items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Abrir Galería de Iconos
                                    </button>
                                </div>
                            </div>

                            <div id="galeriaIconos" class="hidden mt-3 p-4 bg-slate-50 border border-slate-200 rounded-xl">
                                <div class="flex gap-2 mb-4">
                                    <input type="text" id="buscadorIconoInput" placeholder="Busca por marca (ej. docker, github, debian) o elige uno común..." class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm outline-none focus:ring-2 focus:ring-indigo-500">
                                    <button type="button" onclick="buscarMarcaOnline()" class="bg-indigo-100 text-indigo-700 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-indigo-200 transition-colors shrink-0">Buscar Marca</button>
                                </div>

                                <p class="text-xs font-semibold text-slate-500 mb-2 uppercase tracking-wide">Iconos Comunes</p>
                                <div id="gridIconosComunes" class="grid grid-cols-6 sm:grid-cols-8 gap-2 max-h-40 overflow-y-auto p-1">
                                </div>
                                <p id="msgBuscadorIcono" class="text-xs text-slate-500 mt-3 text-center"></p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Permisos (Fetcheado de LDAP)</label>
                            <div class="grid grid-cols-2 gap-2 bg-slate-50 p-4 rounded-xl border border-slate-200">
                                <?php foreach ($ldap_grupos as $grupo): ?>
                                    <label class="flex items-center space-x-3 text-sm cursor-pointer group">
                                        <input type="checkbox" name="roles[]" value="<?php echo htmlspecialchars($grupo); ?>" class="checkbox-rol rounded w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300" <?php echo ($grupo === 'Todos') ? 'checked' : ''; ?>>
                                        <span class="text-slate-700 group-hover:text-indigo-700 font-medium transition-colors"><?php echo htmlspecialchars($grupo); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-xl shadow-sm transition-all flex justify-center items-center gap-2">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                            </svg>
                            Guardar Aplicación
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div id="modalBorrarApp" class="fixed inset-0 z-[110] hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="fixed inset-0 bg-slate-900/75 transition-opacity backdrop-blur-sm"></div>
            <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-100">
                        <div class="bg-white px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-rose-100 sm:mx-0 sm:h-10 sm:w-10">
                                    <svg class="h-6 w-6 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                    <h3 class="text-xl font-semibold leading-6 text-slate-900" id="modal-title">Eliminar Aplicación</h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-slate-500">
                                            ¿Estás absolutamente seguro de que deseas <strong class="text-rose-600">eliminar</strong> esta aplicación? Esta acción la borrará del dashboard para todos los usuarios y <strong>no se puede deshacer</strong>.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="button" onclick="confirmarBorrado()" id="btnConfirmDelete" class="inline-flex w-full justify-center rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-500 sm:ml-3 sm:w-auto transition-colors">
                                Sí, eliminar aplicación
                            </button>
                            <button type="button" onclick="cerrarModalBorrar()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-colors">
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            const formColorSelect = document.getElementById('formAppColor');
            const previewBoxWrapper = document.getElementById('previewBoxWrapper');

            function actualizarColorPreview() {
                previewBoxWrapper.className = 'w-14 h-14 rounded-xl border border-slate-200 flex items-center justify-center shrink-0 transition-colors ' + formColorSelect.value;
            }
            formColorSelect.addEventListener('change', actualizarColorPreview);

            const iconosComunes = [{
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>',
                    title: 'Peligro / Alerta'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>',
                    title: 'Lápiz / Editar'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/></svg>',
                    title: 'Base de Datos'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 17.25v-.228a4.5 4.5 0 00-1.21-3.111c-.46-.47-.967-.896-1.517-1.267v-.03a4.5 4.5 0 00-1.21-3.11C17.353 9.034 16.846 8.608 16.296 8.237v-.03a4.5 4.5 0 00-1.21-3.111c-.46-.47-.967-.896-1.517-1.267.045-.045.09-.09.135-.136A4.5 4.5 0 0015 1.5H9a4.5 4.5 0 00-3.198 1.284c.045.045.09.09.135.136-.55.37-1.057.796-1.517 1.267A4.5 4.5 0 003.21 7.296v.03c-.55.37-1.057.796-1.517 1.267a4.5 4.5 0 00-1.21 3.111v.03c-.55.37-1.057.796-1.517 1.267a4.5 4.5 0 00-1.21 3.111v.228a4.5 4.5 0 001.21 3.111c.46.47.967.896 1.517 1.267v.03a4.5 4.5 0 001.21 3.111c.55.37 1.057.796 1.517 1.267A4.5 4.5 0 009 22.5h6a4.5 4.5 0 003.198-1.284c.46-.47.967-.896 1.517-1.267v-.03a4.5 4.5 0 001.21-3.111c.46-.47.967-.896 1.517-1.267v-.03a4.5 4.5 0 001.21-3.111z" /></svg>',
                    title: 'Servidor / Rack'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15a4.5 4.5 0 004.5 4.5H18a3.75 3.75 0 001.332-7.257 3 3 0 00-3.758-3.848 5.25 5.25 0 00-10.233 2.33A4.502 4.502 0 002.25 15z" /></svg>',
                    title: 'Nube'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
                    title: 'Ajustes'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>',
                    title: 'Documento'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" /></svg>',
                    title: 'Seguridad'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" /></svg>',
                    title: 'Gráficas'
                },
                {
                    svg: '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>',
                    title: 'Usuario'
                }
            ];

            function cargarIconosComunes() {
                const grid = document.getElementById('gridIconosComunes');
                grid.innerHTML = '';
                iconosComunes.forEach(icono => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'w-10 h-10 bg-white border border-slate-200 rounded-lg flex items-center justify-center hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-600 transition-colors text-slate-600 shadow-sm mx-auto';
                    btn.title = icono.title;
                    btn.innerHTML = icono.svg.replace('<svg ', '<svg class="w-6 h-6 pointer-events-none" ');
                    btn.onclick = () => aplicarIcono(icono.svg);
                    grid.appendChild(btn);
                });
            }

            function toggleGaleria() {
                const galeria = document.getElementById('galeriaIconos');
                if (galeria.classList.contains('hidden')) {
                    galeria.classList.remove('hidden');
                    cargarIconosComunes();
                } else {
                    galeria.classList.add('hidden');
                }
            }

            function aplicarIcono(rawSvg) {
                let safeSvg = rawSvg;
                if (!safeSvg.includes('stroke="currentColor"')) {
                    safeSvg = safeSvg.replace('<svg ', '<svg fill="currentColor" ');
                }

                document.getElementById('formAppIcono').value = safeSvg;
                document.getElementById('previewIconoContainer').innerHTML = safeSvg.replace('<svg ', '<svg class="w-7 h-7" ');
                document.getElementById('galeriaIconos').classList.add('hidden');
                document.getElementById('formAppIcono').classList.remove('hidden');
            }

            async function buscarMarcaOnline() {
                const input = document.getElementById('buscadorIconoInput').value.trim().toLowerCase().replace(/\s+/g, '');
                const msg = document.getElementById('msgBuscadorIcono');

                if (!input) {
                    msg.innerHTML = '<span class="text-amber-600">Escribe una marca primero.</span>';
                    return;
                }

                msg.innerHTML = '<span class="text-indigo-600 animate-pulse">Buscando en SimpleIcons...</span>';

                try {
                    const res = await fetch(`https://cdn.jsdelivr.net/npm/simple-icons@v10/icons/${input}.svg`);
                    if (res.ok) {
                        const textSvg = await res.text();
                        aplicarIcono(textSvg);
                        msg.innerHTML = '<span class="text-emerald-600">¡Icono encontrado y aplicado!</span>';
                    } else {
                        msg.innerHTML = '<span class="text-rose-600">Marca no encontrada. Intenta: docker, github, phpmyadmin...</span>';
                    }
                } catch (e) {
                    msg.innerHTML = '<span class="text-rose-600">Error de red buscando el icono.</span>';
                }
            }

            document.getElementById('buscadorIconoInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    buscarMarcaOnline();
                }
            });

            function abrirModalFormulario() {
                document.getElementById('modalAppTitle').innerHTML = '<svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg> Añadir Aplicación al SSO';
                document.getElementById('formNuevaApp').reset();
                document.getElementById('formAppId').value = '';
                document.querySelectorAll('.checkbox-rol').forEach(cb => cb.checked = (cb.value === 'Todos'));

                // Reset VPN toggle to checked by default for security
                document.getElementById('formAppVpn').checked = true;

                document.getElementById('previewIconoContainer').innerHTML = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-7 h-7"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>';
                actualizarColorPreview();
                document.getElementById('modalCrearApp').classList.remove('hidden');
            }

            function cerrarModalFormulario() {
                document.getElementById('modalCrearApp').classList.add('hidden');
            }

            function editarApp(app) {
                document.getElementById('modalAppTitle').innerHTML = '<svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg> Editar Aplicación';

                document.getElementById('formAppId').value = app.id;
                document.getElementById('formAppNombre').value = app.nombre;
                document.getElementById('formAppDesc').value = app.descripcion;
                document.getElementById('formAppUrl').value = app.url;
                document.getElementById('formAppColor').value = app.color_fondo;
                document.getElementById('formAppIcono').value = app.icono_svg;

                // Set VPN toggle based on database value
                document.getElementById('formAppVpn').checked = (app.requiere_vpn == 1);

                document.getElementById('previewIconoContainer').innerHTML = app.icono_svg.replace('<svg ', '<svg class="w-7 h-7" ');
                document.getElementById('formAppIcono').classList.remove('hidden');
                actualizarColorPreview();

                const rolesApp = app.roles_permitidos.split(',');
                document.querySelectorAll('.checkbox-rol').forEach(cb => {
                    cb.checked = rolesApp.includes(cb.value);
                });

                document.getElementById('modalCrearApp').classList.remove('hidden');
            }

            document.getElementById('formNuevaApp').addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                fetch('api_dashboard.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) location.reload();
                        else alert(data.error || 'Error al guardar.');
                    }).catch(() => alert('Error de conexión con el servidor.'));
            });

            let appToDeleteId = null;

            function borrarApp(id) {
                appToDeleteId = id;
                document.getElementById('modalBorrarApp').classList.remove('hidden');
            }

            function cerrarModalBorrar() {
                appToDeleteId = null;
                document.getElementById('modalBorrarApp').classList.add('hidden');
            }

            function confirmarBorrado() {
                if (!appToDeleteId) return;

                const btn = document.getElementById('btnConfirmDelete');
                const originalText = btn.innerHTML;
                btn.innerHTML = 'Eliminando...';
                btn.disabled = true;

                fetch('api_dashboard.php', {
                        method: 'DELETE',
                        body: JSON.stringify({
                            id: appToDeleteId
                        }),
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) location.reload();
                        else {
                            alert(data.error || 'Error al eliminar');
                            cerrarModalBorrar();
                            btn.innerHTML = originalText;
                            btn.disabled = false;
                        }
                    }).catch(() => {
                        alert('Error de conexión.');
                        cerrarModalBorrar();
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    });
            }
        </script>
    <?php endif; ?>

    <script>
        function showVpnModal(event) {
            event.preventDefault();
            document.getElementById('vpnModal').classList.remove('hidden');
        }

        function closeVpnModal() {
            document.getElementById('vpnModal').classList.add('hidden');
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const vpnModal = document.getElementById('vpnModal');
                if (vpnModal && !vpnModal.classList.contains('hidden')) {
                    closeVpnModal();
                }
                const modalCrearApp = document.getElementById('modalCrearApp');
                if (modalCrearApp && !modalCrearApp.classList.contains('hidden')) {
                    if (typeof cerrarModalFormulario === 'function') {
                        cerrarModalFormulario();
                    }
                }
                const modalBorrarApp = document.getElementById('modalBorrarApp');
                if (modalBorrarApp && !modalBorrarApp.classList.contains('hidden')) {
                    if (typeof cerrarModalBorrar === 'function') {
                        cerrarModalBorrar();
                    }
                }
            }
        });
    </script>

</body>

</html>