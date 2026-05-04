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

if (isset($_SESSION['user']) && isset($_SESSION['rol'])) {
    if (isset($_GET['return'])) {
        $sso_token = session_id();
        $return_url = $_GET['return'];
        $parsed_url = parse_url($return_url);
        $sso_jump = $parsed_url['scheme'] . "://" . $parsed_url['host'] . "/sso?token=" . $sso_token . "&target=" . urlencode($return_url);
        header("Location: " . $sso_jump);
        exit();
    }
    header("Location: dashboard.php");
    exit();
}

$ldap_server = "ldaps://openldap.insrv5.local:636";
$ldap_base_dn = "dc=insrv5,dc=local"; 

$ldap_service_dn = "cn=visor-usuarios,dc=insrv5,dc=local";
$ldap_service_pwd = "visorpwd";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_input = trim($_POST['user']); 
    $pwd = $_POST['pwd'];

    if (strpos($login_input, '@') !== false) {
        $uid_to_search = explode('@', $login_input)[0];
    } else {
        $uid_to_search = $login_input;
    }

    $ldap_conn = ldap_connect($ldap_server);
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

    if ($ldap_conn) {
        $bind_service = @ldap_bind($ldap_conn, $ldap_service_dn, $ldap_service_pwd);

        if ($bind_service) {
            $search_filter = "(|(uid=$uid_to_search)(mail=$login_input))";
            $result = @ldap_search($ldap_conn, $ldap_base_dn, $search_filter);
            
            if ($result) {
                $entries = ldap_get_entries($ldap_conn, $result);

                if ($entries["count"] > 0) {
                    $user_real_dn = $entries[0]["dn"];
                    $user_uid = $entries[0]["uid"][0];
                    $user_cn = $entries[0]["cn"][0];

                    if (@ldap_bind($ldap_conn, $user_real_dn, $pwd)) {
                        $_SESSION['user'] = $user_uid;
                        $_SESSION['user_cn'] = $user_cn;
                        
                        @ldap_bind($ldap_conn, $ldap_service_dn, $ldap_service_pwd);
                        
                        $rol = "Trabajador"; 
                        $group_filter = "(member=$user_real_dn)";
                        
                        $group_result = @ldap_search($ldap_conn, $ldap_base_dn, $group_filter);
                        
                        if ($group_result !== false) {
                            $group_entries = ldap_get_entries($ldap_conn, $group_result);
                            if ($group_entries["count"] > 0) {
                                for ($i = 0; $i < $group_entries["count"]; $i++) {
                                    if (isset($group_entries[$i]["cn"][0])) {
                                        $gn = strtolower($group_entries[$i]["cn"][0]);
                                        if ($gn == "it") $rol = "IT";
                                        if ($gn == "recursos humanos" || $gn == "rrhh") $rol = "RRHH";
                                        if ($gn == "marketing") $rol = "Marketing";
                                        if ($gn == "administracion" || $gn == "administración") $rol = "Administracion";
                                    }
                                }
                            }
                        }
                        
                        $_SESSION['rol'] = $rol;
                        if (isset($_GET['return'])) {
                            $sso_token = session_id();
                            $return_url = $_GET['return'];
                            $parsed_url = parse_url($return_url);
                            
                            $sso_jump = $parsed_url['scheme'] . "://" . $parsed_url['host'] . "/sso.php?token=" . $sso_token . "&target=" . urlencode($return_url);
                            
                            header("Location: " . $sso_jump);
                        } else {
                            header("Location: dashboard.php");
                        }
                        exit();

                    } else {
                        $msg = "<h3 style='color:red'>Contraseña incorrecta.</h3>";
                    }
                } else {
                    $msg = "<h3 style='color:red'>El usuario no existe.</h3>";
                }
            } else {
                $msg = "<h3 style='color:red'>Error de búsqueda: " . ldap_error($ldap_conn) . "</h3>";
            }
        } else {
            $msg = "<h3 style='color:red'>Error de Service Account. ". ldap_error($ldap_conn) ."</h3>";
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
    <title>Acceso - Insrv5</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .msg-container h3 {
            font-size: 0.875rem;
            margin: 0;
            padding: 0.75rem;
            border-radius: 0.375rem;
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            text-align: center;
        }
    </style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
        
        <div class="p-8">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-600 mb-4 ring-8 ring-blue-50/50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-slate-800">Portal de Empleados</h2>
                <p class="text-slate-500 text-sm mt-1">Introduce tus credenciales de Insrv5</p>
            </div>

            <?php if (!empty($msg)): ?>
                <div class="msg-container mb-6">
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label for="user" class="block text-sm font-medium text-slate-700 mb-1">Usuario o Email</label>
                    <input type="text" id="user" name="user" required placeholder="ej: user@insrv5.net" 
                        class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none text-slate-700 bg-slate-50 focus:bg-white shadow-sm" />
                </div>
                
                <div>
                    <label for="pwd" class="block text-sm font-medium text-slate-700 mb-1">Contraseña</label>
                    <input type="password" id="pwd" name="pwd" required placeholder="••••••••" 
                        class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all outline-none text-slate-700 bg-slate-50 focus:bg-white shadow-sm" />
                </div>

                <div class="pt-2">
                    <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 flex justify-center items-center shadow-md hover:shadow-lg focus:ring-4 focus:ring-blue-500/50">
                        Iniciar Sesión
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </form>
        </div>
        
        <div class="bg-slate-50 px-8 py-4 border-t border-slate-100 text-center">
            <p class="text-sm text-slate-500">
                ¿Problemas de acceso? Contacta con el <a href="#" class="text-blue-600 hover:text-blue-700 hover:underline font-medium transition-colors">departamento de IT</a>.
            </p>
        </div>

    </div>

</body>
</html>