<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

putenv('LDAPTLS_REQCERT=never');

$display_name = $_SESSION['user_cn'] ?? 'Usuario';
$raw_user = strtolower(str_replace(' ', '.', $display_name));
$usuario_activo_uid = $_SESSION['uid'] ?? $_SESSION['user'] ?? $raw_user;
$rol = $_SESSION['rol'] ?? 'Desconocido';

$colores_roles = [
    'IT' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
    'Recursos Humanos' => 'bg-rose-100 text-rose-700 border-rose-200',
    'RRHH' => 'bg-rose-100 text-rose-700 border-rose-200',
    'Administracion' => 'bg-violet-100 text-violet-700 border-violet-200',
    'Marketing' => 'bg-amber-100 text-amber-700 border-amber-200'
];
$badge_class = $colores_roles[$rol] ?? 'bg-slate-100 text-slate-700 border-slate-200';

$ldap_host = "ldaps://openldap.insrv5.local:636";
$ldap_dn_base = "dc=insrv5,dc=local";
$ldap_user = "cn=visor-usuarios,dc=insrv5,dc=local";
$ldap_pass = "visorpwd";

$db_host = "mysql.insrv5.local";
$db_user = "user";
$db_pass = "1234";
$db_name = "nominas_db";

$datos_bd = [];
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $stmt = $pdo->query("SELECT username, salario_base, pagas, fecha_creacion, fecha_actualizacion, modificado_por FROM nominas");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $datos_bd[strtolower($row['username'])] = $row;
    }
} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
}

$lista_usuarios = [];
$mapa_roles = [];

$ldap_conn = @ldap_connect($ldap_host);
if ($ldap_conn) {
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    if (@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {

        $search_grupos = ldap_search($ldap_conn, $ldap_dn_base, "(objectClass=groupOfNames)");
        $entradas_grupos = ldap_get_entries($ldap_conn, $search_grupos);

        for ($i = 0; $i < $entradas_grupos["count"]; $i++) {
            $nombre_grupo = $entradas_grupos[$i]["cn"][0] ?? 'Desconocido';
            if (isset($entradas_grupos[$i]["member"])) {
                for ($j = 0; $j < $entradas_grupos[$i]["member"]["count"]; $j++) {
                    $member_dn = $entradas_grupos[$i]["member"][$j];
                    if (preg_match('/uid=([^,]+)/i', $member_dn, $matches)) {
                        $uid_miembro = strtolower($matches[1]);
                        $mapa_roles[$uid_miembro] = $nombre_grupo;
                    }
                }
            }
        }

        $search_usuarios = ldap_search($ldap_conn, $ldap_dn_base, "(objectClass=person)");
        $entradas_usuarios = ldap_get_entries($ldap_conn, $search_usuarios);

        for ($i = 0; $i < $entradas_usuarios["count"]; $i++) {
            $uid = $entradas_usuarios[$i]["uid"][0] ?? '';
            $nombre = $entradas_usuarios[$i]["cn"][0] ?? 'Sin Nombre';
            $email = $entradas_usuarios[$i]["mail"][0] ?? 'Sin Email';

            if (!empty($uid)) {
                $uid_lower = strtolower($uid);
                $tiene_nomina = isset($datos_bd[$uid_lower]);

                $salario = $tiene_nomina ? $datos_bd[$uid_lower]['salario_base'] : null;
                $pagas = $tiene_nomina ? ($datos_bd[$uid_lower]['pagas'] ?? 12) : 12;
                $fecha_creacion = $tiene_nomina ? $datos_bd[$uid_lower]['fecha_creacion'] : null;
                $fecha_mod = $tiene_nomina ? $datos_bd[$uid_lower]['fecha_actualizacion'] : null;
                $editor = $tiene_nomina ? $datos_bd[$uid_lower]['modificado_por'] : null;
                $rol_usuario = $mapa_roles[$uid_lower] ?? 'Trabajadores';

                $lista_usuarios[] = [
                    'uid' => $uid,
                    'nombre' => $nombre,
                    'email' => $email,
                    'rol' => $rol_usuario,
                    'tiene_nomina' => $tiene_nomina,
                    'salario' => $salario,
                    'pagas' => $pagas,
                    'fecha_creacion' => $fecha_creacion,
                    'fecha_mod' => $fecha_mod,
                    'editor' => $editor
                ];
            }
        }
    }
    ldap_close($ldap_conn);
}

usort($lista_usuarios, function ($a, $b) {
    return strcmp($a['nombre'], $b['nombre']);
});

$meses_es = ['01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'];
$mes_actual_texto = $meses_es[date('m')] . ' ' . date('Y');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nóminas - Insrv5 Workspace</title>
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
                        <span class="w-2 h-2 rounded-full bg-emerald-500 mr-2 animate-pulse"></span>
                        Red Interna / VPN
                    </div>
                    <div class="flex items-center space-x-3 bg-slate-50 py-1.5 px-3 rounded-full border border-slate-100 hidden sm:flex">
                        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm font-semibold leading-none text-slate-700"><?php echo htmlspecialchars($display_name); ?></span>
                            <span class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($raw_user); ?>@insrv5.net</span>
                        </div>
                        <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($rol); ?>
                        </span>
                    </div>
                    <a href="https://insrv5.net/users/logout.php" class="flex items-center text-slate-500 hover:text-red-600 transition-colors font-medium text-sm group">
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
        <a href="https://insrv5.net/users/index.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-violet-600 mb-6 transition-colors group">
            <svg class="mr-2 h-4 w-4 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver al Dashboard
        </a>

        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    Gestión de Nóminas
                </h1>
                <p class="mt-2 text-slate-500">Administra los salarios y genera los documentos de la plantilla.</p>
            </div>
            <div class="w-full md:w-96 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <input type="text" id="buscador" placeholder="Buscar empleado o rol..." class="block w-full pl-10 pr-3 py-2.5 border border-slate-200 rounded-xl bg-white shadow-sm focus:ring-2 focus:ring-violet-500 focus:border-violet-500 transition-all text-sm outline-none placeholder:text-slate-400">
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50/80">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Empleado</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Departamento / Rol</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Nómina Actual</th>
                            <th class="px-6 py-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Última Modificación</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tablaUsuarios" class="divide-y divide-slate-100 bg-white"></tbody>
                </table>
            </div>
            <div id="noResultados" class="hidden text-center py-12">
                <svg class="mx-auto h-12 w-12 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">No se encontraron empleados</h3>
                <p class="mt-1 text-sm text-slate-500">Prueba a usar un nombre o rol diferente.</p>
            </div>
        </div>
    </main>

    <div id="modalExtras" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Extras de <?php echo $mes_actual_texto; ?></h3>
                    <p class="text-sm text-slate-500" id="modalExtraNombre">Cargando...</p>
                </div>
                <button onclick="cerrarModal()" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6 overflow-y-auto flex-1">
                <form id="formAñadirExtra" class="flex gap-3 items-end mb-6 bg-amber-50/50 p-4 rounded-xl border border-amber-100">
                    <input type="hidden" id="extraUid" name="uid">
                    <div class="flex-1">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Concepto</label>
                        <select id="extraConcepto" name="concepto" required class="block w-full rounded-lg border border-slate-200 py-2 px-3 text-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-200 outline-none bg-white">
                            <option value="">Selecciona opción...</option>
                            <option value="Horas Extras">Horas Extras</option>
                            <option value="Dietas">Dietas</option>
                            <option value="Desplazamientos">Desplazamientos</option>
                            <option value="Plus Productividad">Plus Productividad</option>
                        </select>
                    </div>
                    <div class="w-32">
                        <label class="block text-xs font-semibold text-slate-600 mb-1">Importe (€)</label>
                        <input type="number" id="extraImporte" name="importe" step="0.01" min="0.01" required class="block w-full rounded-lg border border-slate-200 py-2 px-3 text-sm focus:border-amber-500 focus:ring-2 focus:ring-amber-200 outline-none">
                    </div>
                    <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white p-2 rounded-lg transition-colors h-[38px] w-[38px] flex items-center justify-center shadow-sm" title="Añadir">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </button>
                </form>

                <div>
                    <h4 class="text-sm font-semibold text-slate-900 mb-3 border-b border-slate-100 pb-2">Extras registrados este mes:</h4>
                    <ul id="listaExtras" class="space-y-2">
                        <li class="text-center text-sm text-slate-400 py-4 italic">Cargando datos...</li>
                    </ul>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end">
                <button onclick="cerrarModal()" class="px-5 py-2.5 text-sm font-semibold text-slate-600 bg-white border border-slate-200 hover:bg-slate-50 rounded-xl transition-colors shadow-sm">
                    Cerrar panel
                </button>
            </div>
        </div>
    </div>

    <div id="modalHistorial" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 transition-opacity">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Descargar Nómina</h3>
                    <p class="text-sm text-slate-500" id="modalHistorialNombre">Empleado</p>
                </div>
                <button onclick="cerrarModalHistorial()" class="text-slate-400 hover:text-slate-600 transition-colors p-1">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-6">
                <form action="descargar_pdf.php" method="GET" target="_blank">
                    <input type="hidden" name="uid" id="historialUid">

                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-slate-600 mb-1">Periodo de la Nómina</label>
                        <select name="periodo" id="historialPeriodo" class="block w-full rounded-lg border border-slate-200 py-2.5 px-3 text-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-200 outline-none bg-white">
                        </select>
                    </div>

                    <button type="submit" onclick="cerrarModalHistorial()" class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold py-2.5 rounded-xl transition-colors shadow-sm flex justify-center items-center gap-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Generar PDF
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const usuarios = <?= json_encode($lista_usuarios) ?>;
        const usuarioActivo = <?= json_encode($usuario_activo_uid) ?>.toLowerCase();

        const tbody = document.getElementById('tablaUsuarios');
        const buscador = document.getElementById('buscador');
        const noResultados = document.getElementById('noResultados');

        const formatearMoneda = (cantidad) => new Intl.NumberFormat('es-ES', {
            style: 'currency',
            currency: 'EUR'
        }).format(cantidad);

        const formatearFecha = (fechaObj) => {
            if (!fechaObj) return '';
            const f = new Date(fechaObj);
            return f.toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        };

        const obtenerColorRol = (rol) => {
            const roles = {
                'IT': 'bg-indigo-100 text-indigo-700 border-indigo-200',
                'Recursos Humanos': 'bg-rose-100 text-rose-700 border-rose-200',
                'Administracion': 'bg-violet-100 text-violet-700 border-violet-200',
                'Marketing': 'bg-amber-100 text-amber-700 border-amber-200',
                'Trabajadores': 'bg-slate-100 text-slate-700 border-slate-200'
            };
            return roles[rol] || 'bg-slate-100 text-slate-700 border-slate-200';
        };

        function renderizarTabla(lista) {
            tbody.innerHTML = '';
            if (lista.length === 0) {
                noResultados.classList.remove('hidden');
                return;
            }
            noResultados.classList.add('hidden');

            lista.forEach(user => {
                const esMismoUsuario = (user.uid.toLowerCase() === usuarioActivo || user.nombre.toLowerCase().replace(' ', '.') === usuarioActivo);

                const estadoHtml = user.tiene_nomina ?
                    `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span> Configurada
                       </span>` :
                    `<div title="Falta salario base en BD" class="flex justify-center cursor-help group">
                        <div class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-50 text-rose-700 border border-rose-200 group-hover:bg-rose-100 transition-colors">
                            <svg class="h-3.5 w-3.5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            Pendiente
                        </div>
                       </div>`;

                const salarioHtml = user.tiene_nomina && user.salario !== null ?
                    `<span class="text-sm font-semibold text-slate-700">${formatearMoneda(user.salario)}</span><br><span class="text-xs text-slate-400 font-medium">(${user.pagas} pagas)</span>` :
                    `<span class="text-sm text-slate-400 italic">No asignada</span>`;

                const modificacionHtml = user.tiene_nomina && user.fecha_mod ?
                    `<div class="text-xs text-slate-600 font-medium">${formatearFecha(user.fecha_mod)}</div>
                       <div class="text-xs text-slate-400">por ${user.editor || 'Sistema'}</div>` :
                    `<span class="text-sm text-slate-400 italic">-</span>`;

                let botonExtrasHtml = '';
                if (esMismoUsuario || !user.tiene_nomina) {
                    botonExtrasHtml = `<span title="No disponible" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-300 bg-slate-50 cursor-not-allowed mr-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                    </span>`;
                } else {
                    botonExtrasHtml = `<button onclick="abrirModal('${user.uid}', '${user.nombre}')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-amber-500 hover:text-white hover:bg-amber-500 transition-all mr-2" title="Añadir Extras este Mes">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                    </button>`;
                }

                const botonEditarHtml = esMismoUsuario ?
                    `<span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-300 bg-slate-50 cursor-not-allowed mr-2"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg></span>` :
                    `<a href="editar_nomina.php?uid=${user.uid}" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-all mr-2" title="Editar Base"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg></a>`;

                const botonDescargarHtml = !user.tiene_nomina ?
                    `<span class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-300 bg-slate-50 cursor-not-allowed"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg></span>` :
                    `<button onclick="abrirModalHistorial('${user.uid}', '${user.nombre}', '${user.fecha_creacion}')" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 transition-all" title="Ver Histórico / Descargar PDF">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    </button>`;

                const fila = `
                    <tr class="hover:bg-slate-50 transition-colors duration-150 group">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="h-9 w-9 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm shrink-0">${user.nombre.charAt(0).toUpperCase()}</div>
                                <div class="ml-4">
                                    <div class="text-sm font-semibold text-slate-900">${user.nombre}</div>
                                    <div class="text-sm text-slate-500">${user.email}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold border ${obtenerColorRol(user.rol)}">${user.rol}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">${salarioHtml}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">${estadoHtml}</td>
                        <td class="px-6 py-4 whitespace-nowrap">${modificacionHtml}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            ${botonExtrasHtml}
                            ${botonEditarHtml}
                            ${botonDescargarHtml}
                        </td>
                    </tr>`;
                tbody.insertAdjacentHTML('beforeend', fila);
            });
        }

        renderizarTabla(usuarios);

        buscador.addEventListener('input', (e) => {
            const t = e.target.value.toLowerCase();
            renderizarTabla(usuarios.filter(u => u.nombre.toLowerCase().includes(t) || u.rol.toLowerCase().includes(t) || u.email.toLowerCase().includes(t)));
        });

        /* --- MODAL DE EXTRAS --- */
        const modal = document.getElementById('modalExtras');
        const listaUl = document.getElementById('listaExtras');

        function abrirModal(uid, nombre) {
            document.getElementById('extraUid').value = uid;
            document.getElementById('modalExtraNombre').innerText = nombre;
            modal.classList.remove('hidden');
            cargarExtras(uid);
        }

        function cerrarModal() {
            modal.classList.add('hidden');
            document.getElementById('formAñadirExtra').reset();
        }

        function cargarExtras(uid) {
            listaUl.innerHTML = '<li class="text-center text-sm text-slate-400 py-4 italic animate-pulse">Cargando...</li>';
            fetch(`api_extras.php?uid=${uid}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        renderizarListaExtras(data.extras);
                    } else {
                        listaUl.innerHTML = `<li class="text-center text-sm text-rose-500 py-4">Error al cargar datos</li>`;
                    }
                });
        }

        function renderizarListaExtras(extras) {
            if (extras.length === 0) {
                listaUl.innerHTML = '<li class="text-center text-sm text-slate-400 py-4 bg-slate-50 rounded-lg border border-slate-100 border-dashed">No hay extras registrados este mes.</li>';
                return;
            }
            listaUl.innerHTML = '';
            extras.forEach(ext => {
                listaUl.innerHTML += `
                    <li class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-xl shadow-sm hover:border-amber-300 transition-colors group">
                        <div class="flex flex-col">
                            <span class="text-sm font-bold text-slate-700">${ext.concepto}</span>
                            <span class="text-xs text-slate-400">Añadido por ${ext.registrado_por}</span>
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="text-sm font-bold text-emerald-600">+${formatearMoneda(ext.importe)}</span>
                            <button onclick="borrarExtra(${ext.id})" class="text-slate-300 hover:text-rose-500 transition-colors p-1" title="Eliminar">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>
                    </li>`;
            });
        }

        document.getElementById('formAñadirExtra').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const botonSubmit = this.querySelector('button[type="submit"]');
            botonSubmit.innerHTML = `<svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>`;

            fetch('api_extras.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.reset();
                        cargarExtras(formData.get('uid'));
                    } else {
                        alert(data.error || 'Ocurrió un error al guardar');
                    }
                })
                .finally(() => {
                    botonSubmit.innerHTML = `<svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>`;
                });
        });

        function borrarExtra(id) {
            if (confirm('¿Seguro que quieres eliminar este concepto?')) {
                fetch('api_extras.php', {
                    method: 'DELETE',
                    body: JSON.stringify({
                        id: id
                    }),
                    headers: {
                        'Content-Type': 'application/json'
                    }
                }).then(() => cargarExtras(document.getElementById('extraUid').value));
            }
        }

        const modalHistorial = document.getElementById('modalHistorial');

        function abrirModalHistorial(uid, nombre, fechaCreacionStr) {
            document.getElementById('historialUid').value = uid;
            document.getElementById('modalHistorialNombre').innerText = nombre;

            const selectPeriodo = document.getElementById('historialPeriodo');
            selectPeriodo.innerHTML = '';

            const fechaInicio = new Date(fechaCreacionStr || new Date());
            const startYear = fechaInicio.getFullYear();
            const startMonth = fechaInicio.getMonth();

            const hoy = new Date();
            const currentYear = hoy.getFullYear();
            const currentMonth = hoy.getMonth();

            const nombresMeses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

            for (let y = currentYear; y >= startYear; y--) {
                let mStart = (y === currentYear) ? currentMonth : 11;
                let mEnd = (y === startYear) ? startMonth : 0;

                for (let m = mStart; m >= mEnd; m--) {
                    const mesValue = String(m + 1).padStart(2, '0');
                    const texto = `${nombresMeses[m]} ${y}`;
                    selectPeriodo.innerHTML += `<option value="${mesValue}-${y}">${texto}</option>`;
                }
            }

            modalHistorial.classList.remove('hidden');
        }

        function cerrarModalHistorial() {
            modalHistorial.classList.add('hidden');
        }
    </script>
</body>

</html>