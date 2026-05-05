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
                        $msg = "Contraseña incorrecta.";
                    }
                } else {
                    $msg = "El usuario no existe.";
                }
            } else {
                $msg = "Error de búsqueda: " . ldap_error($ldap_conn);
            }
        } else {
            $msg = "Error de Service Account. ". ldap_error($ldap_conn);
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
    <title>Acceso - Insrv5 Workspace</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-mesh {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(30, 64, 175, 0.3) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(79, 70, 229, 0.3) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.3) 0px, transparent 50%),
                radial-gradient(at 0% 100%, rgba(37, 99, 235, 0.3) 0px, transparent 50%);
        }
        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        .float-animation { animation: float 4s ease-in-out infinite; }
    </style>
</head>
<body class="bg-mesh min-h-screen flex items-center justify-center p-4 selection:bg-indigo-500/30">

    <div class="max-w-md w-full relative">
        <!-- Decorative blobs -->
        <div class="absolute -top-12 -left-12 w-24 h-24 bg-indigo-500/20 rounded-full blur-3xl float-animation"></div>
        <div class="absolute -bottom-12 -right-12 w-32 h-32 bg-blue-500/20 rounded-full blur-3xl float-animation" style="animation-delay: -2s"></div>

        <div class="glass rounded-[2rem] shadow-2xl overflow-hidden relative">
            
            <div class="p-8 sm:p-10">
                <div class="text-center mb-10">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-2xl bg-gradient-to-tr from-indigo-600 to-blue-500 text-white mb-6 shadow-xl shadow-indigo-500/20 rotate-3 hover:rotate-0 transition-transform duration-300">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <h2 class="text-3xl font-bold text-white tracking-tight">Insrv5 Workspace</h2>
                    <p class="text-indigo-200/60 text-sm mt-2 font-medium">Accede a tus herramientas corporativas</p>
                </div>

                <?php if (!empty($msg)): ?>
                    <div class="mb-6 animate-in fade-in slide-in-from-top-4 duration-300">
                        <div class="bg-rose-500/10 border border-rose-500/20 rounded-xl p-4 flex items-center gap-3">
                            <div class="shrink-0 w-8 h-8 bg-rose-500/20 rounded-lg flex items-center justify-center text-rose-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-rose-200"><?php echo $msg; ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm" class="space-y-6">
                    <div class="space-y-2">
                        <label for="user" class="block text-sm font-semibold text-indigo-100 ml-1">Usuario o Email</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-indigo-400 group-focus-within:text-indigo-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>
                            <input type="text" id="user" name="user" required placeholder="ej: user@insrv5.net" 
                                class="w-full pl-11 pr-4 py-3.5 bg-white/5 border border-white/10 rounded-2xl focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 transition-all outline-none text-white placeholder:text-white/20 shadow-inner" />
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label for="pwd" class="block text-sm font-semibold text-indigo-100 ml-1">Contraseña</label>
                        <div class="relative group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-indigo-400 group-focus-within:text-indigo-300 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input type="password" id="pwd" name="pwd" required placeholder="••••••••" 
                                class="w-full pl-11 pr-12 py-3.5 bg-white/5 border border-white/10 rounded-2xl focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500/50 transition-all outline-none text-white placeholder:text-white/20 shadow-inner" />
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-4 flex items-center text-white/30 hover:text-white/60 transition-colors">
                                <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" id="submitBtn"
                            class="w-full bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-500 hover:to-blue-500 text-white font-bold py-4 px-6 rounded-2xl transition-all duration-300 flex justify-center items-center shadow-lg shadow-indigo-600/25 active:scale-[0.98] group">
                            <span id="btnText">Iniciar Sesión</span>
                            <div id="loadingSpinner" class="hidden w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin ml-3"></div>
                            <svg id="btnIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-2 group-hover:translate-x-1 transition-transform" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="bg-white/5 px-8 py-5 border-t border-white/10 text-center">
                <p class="text-sm text-indigo-200/40">
                    ¿Problemas de acceso? <a href="mailto:soporte@insrv5.local" class="text-indigo-400 hover:text-indigo-300 hover:underline font-semibold transition-colors decoration-indigo-400/30 underline-offset-4">Soporte IT</a>
                </p>
            </div>

        </div>

        <p class="text-center mt-8 text-white/20 text-xs font-medium tracking-widest uppercase">
            &copy; 2026 Insrv5 Technologies SL
        </p>

    </div>

    <script>
        function togglePassword() {
            const pwd = document.getElementById('pwd');
            const icon = document.getElementById('eye-icon');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />';
            } else {
                pwd.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }

        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('loadingSpinner');
            const icon = document.getElementById('btnIcon');

            btn.disabled = true;
            btnText.innerText = 'Verificando...';
            spinner.classList.remove('hidden');
            icon.classList.add('hidden');
        });
    </script>

</body>
</html>