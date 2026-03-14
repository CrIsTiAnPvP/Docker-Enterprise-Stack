<?php
// Mantenemos la cookie general (aunque el salto la reforzará)
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

if (!isset($_SESSION['user']) || !isset($_SESSION['rol'])) {
    header("Location: index.php");
    exit();
}

$raw_user = htmlspecialchars($_SESSION['user']);
$rol = $_SESSION['rol'];

// NUEVO: Generamos el token seguro basado en el ID de la sesión actual
// Esto nos servirá para pasar la llave a sso.php
$sso_token = session_id();


$display_name = ucfirst(substr($raw_user, 0, 1)) . '. ' . ucfirst(substr($raw_user, 1));
if (strlen($raw_user) <= 3) $display_name = ucfirst($raw_user);

$badge_class = "bg-slate-100 text-slate-800 border-slate-200"; 
if ($rol == 'IT') $badge_class = "bg-indigo-100 text-indigo-800 border-indigo-200";
elseif ($rol == 'RRHH') $badge_class = "bg-rose-100 text-rose-800 border-rose-200";
elseif ($rol == 'Marketing') $badge_class = "bg-amber-100 text-amber-800 border-amber-200";
elseif ($rol == 'Administracion' || $rol == 'Administración') $badge_class = "bg-blue-100 text-blue-800 border-blue-200";
else $badge_class = "bg-emerald-100 text-emerald-800 border-emerald-200"; 

function getUserIP() {
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
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Insrv5 Workspace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
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
                            <span class="text-xs text-slate-500 mt-1"><?php echo $raw_user; ?>@insrv5.local</span>
                        </div>
                        <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $badge_class; ?>">
                            <?php echo $rol; ?>
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
        <div class="mb-10">
            <h1 class="text-3xl font-bold text-slate-900">¡Hola, <?php echo $display_name; ?>! 👋</h1>
            <p class="mt-2 text-slate-500 text-lg">Bienvenido a tu panel de control.</p>
        </div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            <a href="<?php echo $is_vpn_connected ? 'https://tareas.insrv5.local/sso.php?target=https://tareas.insrv5.local/&token=' . $sso_token : '#'; ?>" <?php if(!$is_vpn_connected) echo 'onclick="showVpnModal(event)"'; ?> 
               class="block group bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-500 group-hover:text-white transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                    </div>
                    <h3 class="ml-4 text-xl font-semibold text-slate-800 group-hover:text-emerald-600 transition-colors">Redmine Tareas</h3>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Accede al gestor de proyectos para revisar tus tareas asignadas, crear tickets y registrar tu jornada laboral.
                </p>
            </a>

            <?php if ($rol == 'RRHH' || $rol == 'IT'): ?>
            <a href="<?php echo $is_vpn_connected ? 'rrhh_panel.php' : '#'; ?>" <?php if(!$is_vpn_connected) echo 'onclick="showVpnModal(event)"'; ?> 
               class="block group bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center group-hover:bg-rose-500 group-hover:text-white transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="ml-4 text-xl font-semibold text-slate-800 group-hover:text-rose-600 transition-colors">Portal RRHH</h3>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Panel exclusivo para la gestión de nóminas, aprobación de vacaciones y administración de la plantilla.
                </p>
            </a>
            <?php endif; ?>

            <?php if ($rol == 'IT'): ?>
            <a href="<?php echo $is_vpn_connected ? 'https://ldapadmin.insrv5.local/sso.php?target=https://ldapadmin.insrv5.local/&token=' . $sso_token : '#'; ?>" <?php if(!$is_vpn_connected) echo 'onclick="showVpnModal(event)"'; ?> 
               class="block group bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                        </svg>
                    </div>
                    <h3 class="ml-4 text-xl font-semibold text-slate-800 group-hover:text-indigo-600 transition-colors">Directorio LDAP</h3>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Administración de phpLDAPadmin. Gestión directa de árboles de usuarios, grupos y políticas de seguridad (ACL).
                </p>
            </a>

            <a href="<?php echo $is_vpn_connected ? 'https://pma.insrv5.local/sso.php?target=https://pma.insrv5.local/&token=' . $sso_token : '#'; ?>" <?php if(!$is_vpn_connected) echo 'onclick="showVpnModal(event)"'; ?> 
               class="block group bg-white rounded-2xl border border-slate-200 p-6 shadow-sm hover:shadow-xl hover:-translate-y-1 transition-all duration-300">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center group-hover:bg-sky-500 group-hover:text-white transition-colors duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                        </svg>
                    </div>
                    <h3 class="ml-4 text-xl font-semibold text-slate-800 group-hover:text-sky-600 transition-colors">phpMyAdmin</h3>
                </div>
                <p class="text-slate-500 text-sm leading-relaxed">
                    Acceso a la base de datos relacional de la infraestructura. Gestión de esquemas y copias de seguridad.
                </p>
            </a>
            <?php endif; ?>

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
                                        Para acceder a los recursos internos de Insrv5 necesitas estar conectado a la <strong class="text-slate-700">VPN Corporativa</strong> o encontrarte físicamente en la oficina. Por motivos de seguridad, tu conexión actual ha sido bloqueada.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                        <a href="guia_vpn.php" class="inline-flex w-full justify-center rounded-lg bg-blue-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500 sm:ml-3 sm:w-auto transition-colors">
                            Descargar VPN / Ayuda
                        </a>
                        <button type="button" onclick="closeVpnModal()" class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-3 py-2 text-sm font-semibold text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:mt-0 sm:w-auto transition-colors">
                            Cerrar
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        function showVpnModal(event) {
            event.preventDefault(); // Evita que navegue a #
            document.getElementById('vpnModal').classList.remove('hidden');
        }

        function closeVpnModal() {
            document.getElementById('vpnModal').classList.add('hidden');
        }
    </script>

</body>
</html>