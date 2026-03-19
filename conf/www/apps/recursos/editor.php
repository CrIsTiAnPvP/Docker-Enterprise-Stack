<?php
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

putenv('LDAPTLS_REQCERT=never');

if (!isset($_SESSION['user']) || !isset($_SESSION['rol'])) {
    header("Location: https://insrv5.net/users/index.php");
    exit();
}

$display_name = $_SESSION['user_cn'] ?? 'Usuario';
$raw_user = strtolower(str_replace(' ', '.', $display_name));
if ($raw_user === 'usuario' && isset($_SESSION['user'])) {
    $raw_user = strtolower($_SESSION['user']);
}
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

$rol_crudo = trim(strtolower($_SESSION['rol'] ?? ''));
if ($rol_crudo === 'recursos humanos' || $rol_crudo === 'rrhh') $rol_actual = 'RRHH';
elseif ($rol_crudo === 'administracion' || $rol_crudo === 'administración') $rol_actual = 'Administracion';
elseif ($rol_crudo === 'marketing') $rol_actual = 'Marketing';
elseif ($rol_crudo === 'it') $rol_actual = 'IT';
else $rol_actual = ucfirst(trim($_SESSION['rol'] ?? 'Todos'));

$requested_path = $_GET['folder'] ?? 'Todos';
$requested_path = str_replace(chr(0), '', $requested_path); 
$requested_path = str_replace(['../', '..\\'], '', $requested_path);
$requested_path = trim($requested_path, '/');

$path_parts = explode('/', $requested_path);
$base_folder = $path_parts[0] ?: 'Todos';

$is_it_or_owner = ($rol_actual === 'IT' || $rol_actual === $base_folder);

if (!$is_it_or_owner) {
    die("No tienes permiso para editar archivos en esta carpeta.");
}

$file_name = isset($_GET['file']) ? basename($_GET['file']) : '';
$base_dir = realpath(__DIR__ . '/archivos');
$file_path = $base_dir . '/' . $requested_path . '/' . $file_name;

if (!is_file($file_path)) {
    die("El archivo no existe.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file_content'])) {
    file_put_contents($file_path, $_POST['file_content']);

    $db = new mysqli("mysql.insrv5.local", "user", "1234", "insrv5_db");
    $current_editor = $_SESSION['user_cn'] ?? $_SESSION['user'];
    $db_path = $requested_path . '/' . $file_name;
    
    $stmt = $db->prepare("INSERT INTO registro_archivos (ruta_archivo, nombre_archivo, ultimo_editor) 
                          VALUES (?, ?, ?) 
                          ON DUPLICATE KEY UPDATE ultimo_editor = ?, fecha_modificacion = NOW()");
    $stmt->bind_param("ssss", $db_path, $file_name, $current_editor, $current_editor);
    $stmt->execute();

    header("Location: index.php?folder=" . urlencode($requested_path) . "&msg=saved");
    exit();
}

$content = file_get_contents($file_path);

$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
$languages = [
    'php' => 'php', 
    'sql' => 'sql', 
    'html' => 'html', 
    'css' => 'css',
    'js' => 'javascript', 
    'json' => 'json', 
    'sh' => 'sh', 
    'xml' => 'xml',
    'yaml' => 'yaml', 
    'md' => 'markdown',
    'txt' => 'text',
    'csv' => 'text',
    'conf' => 'sh',
    'env' => 'sh'
];
$default_mode = $languages[$ext] ?? 'text';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Código - <?php echo htmlspecialchars($file_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.36.2/ace.js"></script>
    <style> 
        body { font-family: 'Inter', sans-serif; } 
        #editor { position: absolute; top: 0; right: 0; bottom: 0; left: 0; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen text-slate-800 relative flex flex-col">

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm shrink-0">
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
                            <span class="text-xs text-slate-500 mt-1"><?php echo htmlspecialchars($usuario_activo_uid); ?>@insrv5.net</span>
                        </div>
                        <span class="ml-2 px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($rol); ?>
                        </span>
                    </div>
                    <a href="https://insrv5.net/logout" class="flex items-center text-slate-500 hover:text-red-600 transition-colors font-medium text-sm group">
                        <span class="hidden sm:block mr-2 group-hover:underline">Salir</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 w-full flex-1 flex flex-col">
        
        <a href="index.php?folder=<?php echo urlencode($requested_path); ?>" class="inline-flex items-center text-sm font-medium text-slate-500 hover:text-amber-600 mb-6 transition-colors group">
            <svg class="mr-2 h-4 w-4 transform group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Volver a /<?php echo htmlspecialchars($requested_path); ?>
        </a>

        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center mb-6 gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900 flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 border border-amber-100 flex items-center justify-center font-bold text-xl shadow-inner">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.25 9.75L16.5 12l-2.25 2.25m-4.5 0L7.5 12l2.25-2.25M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" /></svg>
                    </div>
                    Editor de Código
                </h1>
                <p class="mt-2 text-slate-500">Editando: <span class="font-mono text-indigo-600 font-semibold"><?php echo htmlspecialchars($file_name); ?></span></p>
            </div>
            
            <div class="flex flex-wrap items-center gap-4 bg-white p-2 rounded-xl border border-slate-200 shadow-sm">
                
                <div class="flex items-center gap-2 pl-2">
                    <label for="langSelect" class="text-xs font-bold text-slate-400 uppercase tracking-wider">Sintaxis</label>
                    <select id="langSelect" class="bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block px-3 py-1.5 outline-none font-semibold transition-colors cursor-pointer hover:bg-slate-100">
                        <option value="php" <?php echo $default_mode === 'php' ? 'selected' : ''; ?>>PHP</option>
                        <option value="sql" <?php echo $default_mode === 'sql' ? 'selected' : ''; ?>>SQL</option>
                        <option value="html" <?php echo $default_mode === 'html' ? 'selected' : ''; ?>>HTML</option>
                        <option value="css" <?php echo $default_mode === 'css' ? 'selected' : ''; ?>>CSS</option>
                        <option value="javascript" <?php echo $default_mode === 'javascript' ? 'selected' : ''; ?>>JavaScript</option>
                        <option value="json" <?php echo $default_mode === 'json' ? 'selected' : ''; ?>>JSON</option>
                        <option value="xml" <?php echo $default_mode === 'xml' ? 'selected' : ''; ?>>XML</option>
                        <option value="sh" <?php echo $default_mode === 'sh' ? 'selected' : ''; ?>>Bash / Shell</option>
                        <option value="markdown" <?php echo $default_mode === 'markdown' ? 'selected' : ''; ?>>Markdown</option>
                        <option value="yaml" <?php echo $default_mode === 'yaml' ? 'selected' : ''; ?>>YAML</option>
                        <option value="text" <?php echo $default_mode === 'text' ? 'selected' : ''; ?>>Texto Plano</option>
                    </select>
                </div>

                <div class="w-px h-6 bg-slate-200"></div>

                <div class="flex items-center gap-2">
                    <label for="themeSelect" class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tema</label>
                    <select id="themeSelect" class="bg-slate-50 border border-slate-200 text-slate-700 text-sm rounded-lg focus:ring-indigo-500 focus:border-indigo-500 block px-3 py-1.5 outline-none font-semibold transition-colors cursor-pointer hover:bg-slate-100">
                        <optgroup label="Oscuros">
                            <option value="monokai">Monokai</option>
                            <option value="dracula">Dracula</option>
                            <option value="twilight">Twilight</option>
                            <option value="tomorrow_night">Tomorrow Night</option>
                        </optgroup>
                        <optgroup label="Claros">
                            <option value="github">GitHub</option>
                            <option value="chrome">Chrome</option>
                            <option value="eclipse">Eclipse</option>
                            <option value="textmate">TextMate</option>
                        </optgroup>
                    </select>
                </div>

                <div class="w-px h-6 bg-slate-200"></div>

                <button type="submit" form="editorForm" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-lg text-sm font-semibold shadow-sm transition-all flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" /></svg>
                    Guardar
                </button>
            </div>
        </div>

        <form id="editorForm" method="POST" class="flex-1 flex flex-col mb-8">
            <textarea id="hiddenContent" name="file_content" class="hidden"></textarea>
            
            <div class="relative flex-1 rounded-xl overflow-hidden shadow-xl border border-slate-300 min-h-[500px]">
                <div id="editor"></div>
            </div>
        </form>
    </main>

    <script>
        var editor = ace.edit("editor");

        var savedTheme = localStorage.getItem('insrv5_editor_theme') || 'dracula';

        editor.setTheme("ace/theme/" + savedTheme);
        document.getElementById('themeSelect').value = savedTheme;

        document.getElementById('themeSelect').addEventListener('change', function() {
            var selectedTheme = this.value;
            editor.setTheme("ace/theme/" + selectedTheme);
            localStorage.setItem('insrv5_editor_theme', selectedTheme); 
        });

        editor.setShowPrintMargin(false);     
        editor.setFontSize(15);               
    
        var fileContent = <?php echo json_encode($content, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        editor.session.setValue(fileContent);

        var currentLang = document.getElementById('langSelect').value;
        editor.session.setMode("ace/mode/" + currentLang);
        document.getElementById('langSelect').addEventListener('change', function() {
            editor.session.setMode("ace/mode/" + this.value);
        });
        document.getElementById('editorForm').addEventListener('submit', function() {
            document.getElementById('hiddenContent').value = editor.getValue();
        });
    </script>
</body>
</html>