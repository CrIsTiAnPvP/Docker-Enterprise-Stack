<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

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

$target_uid = isset($_GET['uid']) ? strtolower(trim($_GET['uid'])) : '';

if (empty($target_uid)) {
    header("Location: index.php");
    exit();
}

if ($target_uid === strtolower($usuario_activo_uid) || $target_uid === strtolower(str_replace(' ', '.', $usuario_activo_uid))) {
    $error_critico = "Por políticas de seguridad, no tienes permisos para modificar tu propia nómina.";
}

$ldap_host = "ldaps://openldap.insrv5.local:636";
$ldap_dn_base = "dc=insrv5,dc=local";
$ldap_user = "cn=visor-usuarios,dc=insrv5,dc=local";
$ldap_pass = "visorpwd";

$db_host = "mysql.insrv5.local";
$db_user = "user";
$db_pass = "1234";
$db_name = "nominas_db";

$target_nombre = $target_uid;
$ldap_conn = @ldap_connect($ldap_host);
if ($ldap_conn) {
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    if (@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
        $search = ldap_search($ldap_conn, $ldap_dn_base, "(uid=$target_uid)");
        $entradas = ldap_get_entries($ldap_conn, $search);
        if ($entradas["count"] > 0) {
            $target_nombre = $entradas[0]["cn"][0] ?? $target_uid;
        }
    }
    ldap_close($ldap_conn);
}

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
}

$mensaje_exito = '';
$mensaje_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($error_critico)) {
    $nuevo_salario = filter_input(INPUT_POST, 'salario', FILTER_VALIDATE_FLOAT);
    $nuevas_pagas = filter_input(INPUT_POST, 'pagas', FILTER_VALIDATE_INT);

    if ($nuevo_salario !== false && $nuevo_salario >= 0 && in_array($nuevas_pagas, [12, 14])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO nominas (username, salario_base, pagas, modificado_por) 
                VALUES (:uid, :salario, :pagas, :editor) 
                ON DUPLICATE KEY UPDATE 
                salario_base = :salario, 
                pagas = :pagas,
                modificado_por = :editor
            ");
            $stmt->execute([
                ':uid' => $target_uid,
                ':salario' => $nuevo_salario,
                ':pagas' => $nuevas_pagas,
                ':editor' => $display_name
            ]);
            $mensaje_exito = "El salario y las pagas se han actualizado correctamente.";
        } catch (PDOException $e) {
            $mensaje_error = "Error al guardar: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "Por favor, introduce una cantidad válida y selecciona 12 o 14 pagas.";
    }
}

$salario_actual = '';
$pagas_actuales = 12;
try {
    $stmt = $pdo->prepare("SELECT salario_base, pagas FROM nominas WHERE username = :uid");
    $stmt->execute([':uid' => $target_uid]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $salario_actual = $row['salario_base'];
        $pagas_actuales = $row['pagas'] ?? 12;
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Nómina - Insrv5 Workspace</title>
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
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        
        <a href="index.php" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-violet-600 mb-6 transition-colors group">
            <svg class="mr-2 h-4 w-4 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver al listado
        </a>

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 sm:p-8 border-b border-slate-100 bg-slate-50/50 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-violet-100 text-violet-600 flex items-center justify-center font-bold text-xl shrink-0">
                    <?php echo strtoupper(substr($target_nombre, 0, 1)); ?>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-slate-900">Modificar Salario Base</h2>
                    <p class="text-sm text-slate-500 mt-1">Empleado: <strong class="text-slate-700"><?php echo htmlspecialchars($target_nombre); ?></strong> (<?php echo htmlspecialchars($target_uid); ?>)</p>
                </div>
            </div>

            <div class="p-6 sm:p-8">
                
                <?php if (isset($error_critico)): ?>
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex items-start gap-3 mb-6">
                        <svg class="h-5 w-5 text-rose-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <h3 class="text-sm font-semibold text-rose-800">Acción denegada</h3>
                            <p class="text-sm text-rose-600 mt-1"><?php echo $error_critico; ?></p>
                        </div>
                    </div>
                <?php else: ?>

                    <?php if ($mensaje_exito): ?>
                        <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex items-start gap-3 mb-6">
                            <svg class="h-5 w-5 text-emerald-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm font-medium text-emerald-800"><?php echo $mensaje_exito; ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($mensaje_error): ?>
                        <div class="bg-rose-50 border border-rose-200 rounded-xl p-4 flex items-start gap-3 mb-6">
                            <svg class="h-5 w-5 text-rose-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-sm font-medium text-rose-800"><?php echo $mensaje_error; ?></p>
                        </div>
                    <?php endif; ?>

                    <form action="editar_nomina.php?uid=<?php echo urlencode($target_uid); ?>" method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="salario" class="block text-sm font-semibold text-slate-700 mb-2">Salario Bruto Anual (€)</label>
                                <div class="relative rounded-lg shadow-sm">
                                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                                        <span class="text-slate-400 font-medium">€</span>
                                    </div>
                                    <input type="number" name="salario" id="salario" step="0.01" min="0" required 
                                        class="block w-full rounded-xl border border-slate-200 pl-10 pr-4 py-3 text-slate-900 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 transition-all outline-none"
                                        placeholder="Ej: 28500.00" value="<?php echo htmlspecialchars($salario_actual); ?>">
                                </div>
                            </div>
                            
                            <div>
                                <label for="pagas" class="block text-sm font-semibold text-slate-700 mb-2">Distribución (Pagas)</label>
                                <select name="pagas" id="pagas" class="block w-full rounded-xl border border-slate-200 px-4 py-3 text-slate-900 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 transition-all outline-none bg-white">
                                    <option value="12" <?php if($pagas_actuales == 12) echo 'selected'; ?>>12 Pagas (Prorrateadas)</option>
                                    <option value="14" <?php if($pagas_actuales == 14) echo 'selected'; ?>>14 Pagas (+ Verano y Navidad)</option>
                                </select>
                            </div>
                        </div>

                        <p class="mb-6 text-xs text-slate-500">El sistema dividirá el Salario Bruto Anual entre el número de pagas para calcular la base mensual en el PDF.</p>

                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                            <a href="index.php" class="px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 rounded-xl transition-colors">Cancelar</a>
                            <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-violet-600 hover:bg-violet-700 rounded-xl shadow-sm transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                                </svg>
                                Guardar Cambios
                            </button>
                        </div>
                    </form>

                <?php endif; ?>

            </div>
        </div>
    </main>

</body>
</html>