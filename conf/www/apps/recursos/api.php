<?php
require_once 'core.php';

$base_dir = realpath(__DIR__ . '/archivos');
if (!$base_dir) {
	@mkdir(__DIR__ . '/archivos', 0775, true);
	$base_dir = realpath(__DIR__ . '/archivos');
}

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

// ==========================================
// 1. ENDPOINTS AJAX
// ==========================================
if (isset($_GET['ajax_action'])) {
	header('Content-Type: application/json');
	$action = $_GET['ajax_action'];

	if ($action === 'get_config' && $is_it) {
		$res = $db->query("SELECT valor FROM configuracion_recursos WHERE clave = 'ruta_raiz'");
		echo json_encode(['ruta' => $res->fetch_assoc()['valor'] ?? 'archivos']);
		exit;
	}
	if ($action === 'update_config' && $_SERVER['REQUEST_METHOD'] === 'POST' && $is_it) {
		$nueva_ruta = trim($_POST['ruta_raiz'] ?? '');
		if ($nueva_ruta) {
			$stmt = $db->prepare("UPDATE configuracion_recursos SET valor = ? WHERE clave = 'ruta_raiz'");
			$stmt->bind_param("s", $nueva_ruta);
			$stmt->execute();
			echo json_encode(['success' => true]);
		} else {
			echo json_encode(['success' => false]);
		}
		exit;
	}
	if ($action === 'manage_root' && $_SERVER['REQUEST_METHOD'] === 'POST' && $is_it) {
		$subaction = $_POST['subaction'] ?? '';
		$nombre = preg_replace('/[^a-zA-Z0-9_.-]/', '_', trim($_POST['nombre'] ?? ''));

		if ($subaction === 'create' && $nombre) {
			if (!is_dir($base_dir . '/' . $nombre)) {
				mkdir($base_dir . '/' . $nombre, 0775);
				registrarActividad($nombre, $nombre, $display_name, 'creacion');
				echo json_encode(['success' => true]);
				exit;
			}
		} elseif ($subaction === 'delete' && $nombre) {
			$path = $base_dir . '/' . $nombre;
			if (is_dir($path)) {
				if (@rmdir($path)) {
					limpiarRastrosDB($nombre);
					registrarActividad($nombre, $nombre, $display_name, 'eliminacion');
					echo json_encode(['success' => true]);
					exit;
				} else {
					echo json_encode(['success' => false, 'error' => 'La carpeta departamental debe estar vacía para eliminarla.']);
					exit;
				}
			}
		} elseif ($subaction === 'rename' && $nombre) {
			$nuevo = preg_replace('/[^a-zA-Z0-9_.-]/', '_', trim($_POST['nuevo'] ?? ''));
			if ($nuevo && is_dir($base_dir . '/' . $nombre) && !is_dir($base_dir . '/' . $nuevo)) {
				rename($base_dir . '/' . $nombre, $base_dir . '/' . $nuevo);
				renombrarRastrosDB($nombre, $nuevo);
				registrarActividad($nuevo, $nuevo, $display_name, 'renombrado');
				echo json_encode(['success' => true]);
				exit;
			}
		}
		echo json_encode(['success' => false, 'error' => 'Acción inválida o nombre duplicado.']);
		exit;
	}

	if ($action === 'search_user') {
		$q = trim($_GET['q'] ?? '');
		$results = [];
		$ldap_conn = @ldap_connect("ldaps://openldap.insrv5.local:636");
		if ($ldap_conn && strlen($q) > 1) {
			ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
			if (@ldap_bind($ldap_conn, "cn=visor-usuarios,dc=insrv5,dc=local", "visorpwd")) {
				$sr = @ldap_search($ldap_conn, "dc=insrv5,dc=local", "(|(uid=*$q*)(cn=*$q*))");
				if ($sr) {
					$entries = ldap_get_entries($ldap_conn, $sr);
					for ($i = 0; $i < $entries["count"]; $i++) {
						if (isset($entries[$i]["uid"][0])) {
							$results[] = ['uid' => $entries[$i]["uid"][0], 'cn' => $entries[$i]["cn"][0] ?? $entries[$i]["uid"][0]];
						}
					}
				}
			}
		}
		echo json_encode($results);
		exit;
	}

	$ruta_solicitada = $_REQUEST['ruta'] ?? '';
	$partes_req = explode('/', trim($ruta_solicitada, '/'));
	$base_folder_req = $partes_req[0] ?: 'Todos';

	array_pop($partes_req);
	$parent_path = implode('/', $partes_req);

	if (empty($parent_path)) {
		$can_manage_perms = $is_it;
	} else {
		$can_manage_perms = check_access($parent_path, $rol_actual, $mis_grupos, $rutas_explicitas);
	}
	if ($base_folder_req === 'Todos') {
		$can_manage_perms = false;
	}

	// AQUI SE OBTIENE EL NOMBRE DEL USUARIO DESDE LDAP
    if ($action === 'get_perms') {
        if (!$can_manage_perms) { echo json_encode([]); exit; }
        
        $perms = [];
        
        // Novedad: Calcular ruta actual y TODAS sus carpetas padre para extraer la herencia
        $partes_ruta = explode('/', trim($ruta_solicitada, '/'));
        $rutas_a_buscar = [];
        $ruta_acumulada = '';
        foreach ($partes_ruta as $p) {
            $ruta_acumulada .= ($ruta_acumulada === '' ? $p : '/' . $p);
            $rutas_a_buscar[] = "'" . $db->real_escape_string($ruta_acumulada) . "'";
        }
        $rutas_in = implode(',', $rutas_a_buscar);

        // Novedad: Consultar en la BD todos los niveles
        $res = $db->query("SELECT id, tipo_entidad, nombre_entidad, ruta FROM permisos_recursos WHERE ruta IN ($rutas_in) ORDER BY LENGTH(ruta) ASC");

        $ldap_conn = @ldap_connect("ldaps://openldap.insrv5.local:636");
        $ldap_bind = false;
        if ($ldap_conn) {
            ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            $ldap_bind = @ldap_bind($ldap_conn, "cn=visor-usuarios,dc=insrv5,dc=local", "visorpwd");
        }

        if ($res) {
            while($row = $res->fetch_assoc()) { 
                // Novedad: Identificar si el permiso pertenece a esta carpeta o si baja desde el padre
                $row['heredado'] = ($row['ruta'] !== $ruta_solicitada);

                if ($row['tipo_entidad'] === 'usuario' && $ldap_bind) {
                    $uid = $row['nombre_entidad'];
                    $sr = @ldap_search($ldap_conn, "dc=insrv5,dc=local", "(uid=$uid)");
                    if ($sr) {
                        $entries = ldap_get_entries($ldap_conn, $sr);
                        if ($entries["count"] > 0 && isset($entries[0]["cn"][0])) {
                            $cn = $entries[0]["cn"][0];
                            $row['display_name'] = "$cn ($uid)";
                        } else {
                            $row['display_name'] = $uid;
                        }
                    } else {
                        $row['display_name'] = $uid;
                    }
                } else {
                    $row['display_name'] = $row['nombre_entidad'];
                }
                $perms[] = $row; 
            }
        }
        echo json_encode($perms); exit;
    }

    if ($action === 'add_perm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $tipo = $_POST['tipo'] ?? ''; $nombre = $_POST['nombre'] ?? '';
        if (!$can_manage_perms) { echo json_encode(['success' => false, 'error' => 'No autorizado']); exit; }
        if ($ruta_solicitada && $tipo && $nombre) {
            $stmt = $db->prepare("INSERT IGNORE INTO permisos_recursos (ruta, tipo_entidad, nombre_entidad) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $ruta_solicitada, $tipo, $nombre);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else { echo json_encode(['success' => false]); }
        exit;
    }

    if ($action === 'remove_perm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_permiso = $_POST['id'] ?? 0;
        $stmt_r = $db->prepare("SELECT ruta FROM permisos_recursos WHERE id = ?");
        $stmt_r->bind_param("i", $id_permiso);
        $stmt_r->execute();
        $res_r = $stmt_r->get_result()->fetch_assoc();
        $ruta_perm = $res_r['ruta'] ?? '';

        // Novedad de Seguridad: Para borrar un permiso, debes tener acceso a la carpeta a la que fue asignado.
        // (Por ejemplo, si Laura está en IT/Laura, NO podrá borrar un permiso heredado desde IT).
        $safe_to_del = empty($ruta_perm) ? $is_it : check_access($ruta_perm, $rol_actual, $mis_grupos, $rutas_explicitas);

        if (!$safe_to_del) { 
            echo json_encode(['success' => false, 'error' => "No tienes derechos administrativos sobre la carpeta padre (/$ruta_perm) para borrar este permiso."]); 
            exit; 
        }

        $stmt = $db->prepare("DELETE FROM permisos_recursos WHERE id = ?");
        $stmt->bind_param("i", $id_permiso);
        $stmt->execute();
        echo json_encode(['success' => true]); exit;
    }

	if ($action === 'add_perm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
		$tipo = $_POST['tipo'] ?? '';
		$nombre = $_POST['nombre'] ?? '';
		if (!$can_manage_perms) {
			echo json_encode(['success' => false, 'error' => 'No autorizado']);
			exit;
		}
		if ($ruta_solicitada && $tipo && $nombre) {
			$stmt = $db->prepare("INSERT IGNORE INTO permisos_recursos (ruta, tipo_entidad, nombre_entidad) VALUES (?, ?, ?)");
			$stmt->bind_param("sss", $ruta_solicitada, $tipo, $nombre);
			$stmt->execute();
			echo json_encode(['success' => true]);
		} else {
			echo json_encode(['success' => false]);
		}
		exit;
	}

	if ($action === 'remove_perm' && $_SERVER['REQUEST_METHOD'] === 'POST') {
		$id_permiso = $_POST['id'] ?? 0;
		$stmt_r = $db->prepare("SELECT ruta FROM permisos_recursos WHERE id = ?");
		$stmt_r->bind_param("i", $id_permiso);
		$stmt_r->execute();
		$res_r = $stmt_r->get_result()->fetch_assoc();
		$ruta_perm = $res_r['ruta'] ?? '';

		$p_parts = explode('/', trim($ruta_perm, '/'));
		array_pop($p_parts);
		$p_parent = implode('/', $p_parts);
		$safe_to_del = empty($p_parent) ? $is_it : check_access($p_parent, $rol_actual, $mis_grupos, $rutas_explicitas);

		if (!$safe_to_del) {
			echo json_encode(['success' => false, 'error' => 'No autorizado']);
			exit;
		}

		$stmt = $db->prepare("DELETE FROM permisos_recursos WHERE id = ?");
		$stmt->bind_param("i", $id_permiso);
		$stmt->execute();
		echo json_encode(['success' => true]);
		exit;
	}
}

// ==========================================
// 2. DESCARGAS
// ==========================================
if (isset($_GET['download'])) {
	$folder = $_GET['folder'] ?? 'Todos';
	$p_info = get_path_info($folder, $rol_actual, $mis_grupos, $rutas_explicitas, $base_dir);
	$file_to_dl = basename($_GET['download']);
	$file_path = $p_info['directorio_objetivo'] . $file_to_dl;

	if (is_file($file_path) && check_access($p_info['requested_path'] . '/' . $file_to_dl, $rol_actual, $mis_grupos, $rutas_explicitas)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
		readfile($file_path);
		exit;
	}
	header("Location: index.php?folder=" . urlencode($folder) . "&msg=error&type=error");
	exit;
}

// ==========================================
// 3. PROCESAMIENTO DE FORMULARIOS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$current_folder = $_POST['current_folder'] ?? 'Todos';
	$p_info = get_path_info($current_folder, $rol_actual, $mis_grupos, $rutas_explicitas, $base_dir);

	$req_path = $p_info['requested_path'];
	$dir_obj = $p_info['directorio_objetivo'];
	$has_full_access = $p_info['has_full_access'];
	$base_folder = $p_info['base_folder'];

	$msg = '';
	$msg_type = '';

	if ($has_full_access) {

		if (isset($_FILES['archivo'])) {
			$file = $_FILES['archivo'];
			if ($file['error'] === UPLOAD_ERR_OK) {
				$safe_fn = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($file['name']));
				if (move_uploaded_file($file['tmp_name'], $dir_obj . $safe_fn)) {
					registrarActividad($req_path . '/' . $safe_fn, $safe_fn, $display_name, 'subida');
					$msg = "Archivo subido correctamente.";
					$msg_type = "success";
				} else {
					$msg = "Error al subir archivo al servidor.";
					$msg_type = "error";
				}
			} else {
				$msg = "El archivo excede el tamaño máximo o está corrupto.";
				$msg_type = "error";
			}
		}

		if (isset($_POST['delete_item'])) {
			$item = basename($_POST['delete_item']);
			$rel_path = $req_path . '/' . $item;
			$path = $dir_obj . $item;

			$item_info = obtenerInfoEdicion($rel_path);
			$item_owner = $item_info['ultimo_editor'] ?? '';
			$is_owner = ($item_owner === $display_name);

			$can_delete = ($base_folder === 'Todos') ? ($is_it || $is_owner) : true;

			if ($can_delete) {
				if (is_dir($path)) {
					if (@rmdir($path)) {
						limpiarRastrosDB($rel_path);
						registrarActividad($rel_path, $item, $display_name, 'eliminacion');
						$msg = "Carpeta eliminada.";
						$msg_type = "success";
					} else {
						$msg = "La carpeta debe estar vacía para eliminarla.";
						$msg_type = "error";
					}
				} elseif (is_file($path)) {
					if (@unlink($path)) {
						limpiarRastrosDB($rel_path);
						registrarActividad($rel_path, $item, $display_name, 'eliminacion');
						$msg = "Archivo eliminado.";
						$msg_type = "success";
					} else {
						$msg = "Error de permisos en el servidor al eliminar.";
						$msg_type = "error";
					}
				}
			} else {
				$msg = "No tienes permiso para eliminar esto.";
				$msg_type = "error";
			}
		}

		if (isset($_POST['new_folder'])) {
			$nf = preg_replace('/[^a-zA-Z0-9_.-]/', '_', trim($_POST['new_folder']));
			if ($nf) {
				if (!file_exists($dir_obj . $nf)) {
					if (mkdir($dir_obj . $nf, 0775)) {
						registrarActividad($req_path . '/' . $nf, $nf, $display_name, 'creacion');
						$msg = "Carpeta creada.";
						$msg_type = "success";
					} else {
						$msg = "Error del servidor al crear la carpeta.";
						$msg_type = "error";
					}
				} else {
					$msg = "Ya existe un elemento con ese nombre.";
					$msg_type = "error";
				}
			} else {
				$msg = "Nombre de carpeta inválido.";
				$msg_type = "error";
			}
		}

		if (isset($_POST['new_file'])) {
			$nf = preg_replace('/[^a-zA-Z0-9_.-]/', '_', trim($_POST['new_file']));
			if ($nf) {
				if (!file_exists($dir_obj . $nf)) {
					if (touch($dir_obj . $nf)) {
						registrarActividad($req_path . '/' . $nf, $nf, $display_name, 'creacion');
						$msg = "Archivo creado.";
						$msg_type = "success";
					} else {
						$msg = "Error del servidor al crear el archivo.";
						$msg_type = "error";
					}
				} else {
					$msg = "Ya existe un elemento con ese nombre.";
					$msg_type = "error";
				}
			} else {
				$msg = "Nombre de archivo inválido.";
				$msg_type = "error";
			}
		}

		if (isset($_POST['rename_old']) && isset($_POST['rename_new'])) {
			$old = basename($_POST['rename_old']);
			$new = preg_replace('/[^a-zA-Z0-9_.-]/', '_', trim($_POST['rename_new']));
			$rel_old = $req_path . '/' . $old;
			$rel_new = $req_path . '/' . $new;

			$item_info = obtenerInfoEdicion($rel_old);
			$is_owner = (($item_info['ultimo_editor'] ?? '') === $display_name);

			$can_rename = ($base_folder === 'Todos') ? ($is_it || $is_owner) : true;

			if ($old && $new && file_exists($dir_obj . $old) && $can_rename) {
				if (!file_exists($dir_obj . $new)) {
					rename($dir_obj . $old, $dir_obj . $new);
					renombrarRastrosDB($rel_old, $rel_new);
					registrarActividad($rel_new, $new, $display_name, 'renombrado');
					$msg = "Renombrado con éxito.";
					$msg_type = "success";
				} else {
					$msg = "El nombre de destino ya está en uso.";
					$msg_type = "error";
				}
			} else {
				$msg = "No tienes permiso para renombrar esto o el nombre es inválido.";
				$msg_type = "error";
			}
		}
	} else {
		$msg = "Permiso denegado. No tienes acceso completo a esta carpeta.";
		$msg_type = "error";
	}

	header("Location: index.php?folder=" . urlencode($req_path) . "&msg=" . urlencode($msg) . "&type=" . urlencode($msg_type));
	exit;
}
