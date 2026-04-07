// ==========================================
// MODALES DE ALERTA PERSONALIZADOS (Estilo App)
// ==========================================
function customConfirm(msg, title = "Atención") {
    return new Promise((resolve) => {
        document.getElementById('confirmTitle').innerText = title;
        document.getElementById('confirmMsg').innerHTML = msg; 
        document.getElementById('modalCustomConfirm').classList.remove('hidden');

        document.getElementById('btnConfirmYes').onclick = () => {
            document.getElementById('modalCustomConfirm').classList.add('hidden');
            resolve(true);
        };
        document.getElementById('btnConfirmNo').onclick = () => {
            document.getElementById('modalCustomConfirm').classList.add('hidden');
            resolve(false);
        };
    });
}

function customAlert(msg, title = "Aviso") {
  document.getElementById("alertTitle").innerText = title;
  document.getElementById("alertMsg").innerText = msg;
  document.getElementById("modalCustomAlert").classList.remove("hidden");
}

function cerrarCustomAlert() {
  document.getElementById("modalCustomAlert").classList.add("hidden");
}

// ==========================================
// FUNCIONES DE LOS MODALES BÁSICOS (Archivos)
// ==========================================
function abrirModalInput(tipo, nameAtributo) {
  document.getElementById("modalInputTitle").innerText = "Crear " + tipo;
  const input = document.getElementById("modalInputField");
  input.name = nameAtributo;
  input.value = "";
  document.getElementById("modalInputHidden").value = "";
  document.getElementById("modalInput").classList.remove("hidden");
  setTimeout(() => input.focus(), 100);
}

function abrirModalRenombrar(oldName) {
  document.getElementById("modalInputTitle").innerText = "Renombrar";
  const input = document.getElementById("modalInputField");
  input.name = "rename_new";
  input.value = oldName;
  document.getElementById("modalInputHidden").value = oldName;
  document.getElementById("modalInput").classList.remove("hidden");
  setTimeout(() => input.focus(), 100);
}

function cerrarModalInput() {
  document.getElementById("modalInput").classList.add("hidden");
}

function abrirModalBorrar(itemName) {
  document.getElementById("inputBorrar").value = itemName;
  document.getElementById("textoBorrar").innerText = itemName;
  document.getElementById("modalBorrar").classList.remove("hidden");
}

function cerrarModalBorrar() {
  document.getElementById("modalBorrar").classList.add("hidden");
}

// ==========================================
// CONFIGURACIÓN AVANZADA (IT)
// ==========================================
async function abrirModalConfig() {
  const res = await fetch("api.php?ajax_action=get_config");
  const data = await res.json();
  document.getElementById("inputRutaRaiz").value = data.ruta;
  document.getElementById("modalConfig").classList.remove("hidden");
}

function cerrarModalConfig() {
  document.getElementById("modalConfig").classList.add("hidden");
}

async function guardarRutaRaiz() {
  const ruta = document.getElementById("inputRutaRaiz").value;
  const fd = new FormData();
  fd.append("ruta_raiz", ruta);
  const res = await fetch("api.php?ajax_action=update_config", {
    method: "POST",
    body: fd,
  });
  if ((await res.json()).success) window.location.reload();
}

async function crearDepartamento() {
  const nombre = document.getElementById("inputNuevoDept").value;
  if (!nombre) return;
  const fd = new FormData();
  fd.append("subaction", "create");
  fd.append("nombre", nombre);
  const res = await fetch("api.php?ajax_action=manage_root", {
    method: "POST",
    body: fd,
  });
  if ((await res.json()).success) window.location.reload();
}

// Nuevos Modales para Gestión de Departamentos
let currentDeptName = "";

function prepararRenombrarDept(nombre) {
  currentDeptName = nombre;
  document.getElementById("deptInputField").value = nombre;
  cerrarModalConfig();
  document.getElementById("modalDeptInput").classList.remove("hidden");
}

function cerrarModalDeptInput() {
  document.getElementById("modalDeptInput").classList.add("hidden");
  abrirModalConfig();
}

async function confirmarRenombrarDept(e) {
  e.preventDefault();
  const nuevo = document.getElementById("deptInputField").value.trim();
  if (!nuevo || nuevo === currentDeptName) return cerrarModalDeptInput();

  const fd = new FormData();
  fd.append("subaction", "rename");
  fd.append("nombre", currentDeptName);
  fd.append("nuevo", nuevo);
  const res = await fetch("api.php?ajax_action=manage_root", {
    method: "POST",
    body: fd,
  });
  const data = await res.json();
  if (data.success) window.location.reload();
  else customAlert(data.error, "Error");
}

function prepararBorrarDept(nombre) {
  currentDeptName = nombre;
  document.getElementById("textoBorrarDept").innerText = nombre;
  cerrarModalConfig();
  document.getElementById("modalDeptBorrar").classList.remove("hidden");
}

function cerrarModalDeptBorrar() {
  document.getElementById("modalDeptBorrar").classList.add("hidden");
  abrirModalConfig();
}

async function confirmarBorrarDept(e) {
  e.preventDefault();
  const fd = new FormData();
  fd.append("subaction", "delete");
  fd.append("nombre", currentDeptName);
  const res = await fetch("api.php?ajax_action=manage_root", {
    method: "POST",
    body: fd,
  });
  const data = await res.json();
  if (data.success) window.location.reload();
  else customAlert(data.error, "Error");
}

// ==========================================
// GESTIÓN DE PERMISOS Y FILTROS
// ==========================================
let currentPermRuta = "";
let isFromConfig = false;

function abrirModalPermisosDesdeConfig(ruta) {
  isFromConfig = true;
  cerrarModalConfig();
  abrirModalPermisos(ruta);
}

function volverAConfig() {
  cerrarModalPermisos();
  abrirModalConfig();
  isFromConfig = false;
}

function abrirModalPermisos(ruta) {
  currentPermRuta = ruta;
  document.getElementById("permRutaLabel").innerText = "/" + ruta;

  if (isFromConfig) {
    document.getElementById("btnVolverConfig").classList.remove("hidden");
  } else {
    document.getElementById("btnVolverConfig").classList.add("hidden");
  }

  document.getElementById("modalPermisos").classList.remove("hidden");
  cargarPermisos();
}

function cerrarModalPermisos() {
  document.getElementById("modalPermisos").classList.add("hidden");
  isFromConfig = false;
}

function togglePermInput() {
  const tipo = document.getElementById("permTipo").value;
  if (tipo === "grupo") {
    document.getElementById("permInputGrupo").classList.remove("hidden");
    document.getElementById("permInputUsuario").classList.add("hidden");
  } else {
    document.getElementById("permInputGrupo").classList.add("hidden");
    document.getElementById("permInputUsuario").classList.remove("hidden");
  }
}

async function searchLdapUser(query) {
  const resultsDiv = document.getElementById("searchResults");
  if (query.length < 2) {
    resultsDiv.classList.add("hidden");
    return;
  }

  const res = await fetch(`api.php?ajax_action=search_user&q=${query}`);
  const data = await res.json();

  resultsDiv.innerHTML = "";
  if (data.length > 0) {
    data.forEach((user) => {
      const div = document.createElement("div");
      div.className =
        "px-3 py-2 hover:bg-slate-50 cursor-pointer text-sm flex justify-between border-b border-slate-100";
      div.innerHTML = `<span class="font-semibold text-slate-700">${user.cn}</span><span class="text-xs text-slate-400">${user.uid}</span>`;
      div.onclick = () => {
        agregarPermisoDirecto("usuario", user.uid);
        document.getElementById("searchUser").value = "";
        resultsDiv.classList.add("hidden");
      };
      resultsDiv.appendChild(div);
    });
  } else {
    resultsDiv.innerHTML =
      '<div class="px-3 py-2 text-sm text-slate-400">Sin resultados</div>';
  }
  resultsDiv.classList.remove("hidden");
}

function agregarPermiso() {
  const tipo = document.getElementById("permTipo").value;
  const val = document.getElementById("permValGrupo").value;
  agregarPermisoDirecto(tipo, val);
}

async function agregarPermisoDirecto(tipo, nombre) {
  const formData = new FormData();
  formData.append("ruta", currentPermRuta);
  formData.append("tipo", tipo);
  formData.append("nombre", nombre);
  await fetch("api.php?ajax_action=add_perm", {
    method: "POST",
    body: formData,
  });
  cargarPermisos();
}

async function eliminarPermiso(id, isInherited = false, rutaPadre = "") {
    if (isInherited) {
        const msg = `
            <div class="space-y-3">
                <p>Este permiso se hereda de la carpeta superior:</p>
                <div class="bg-blue-50 text-blue-700 font-mono text-sm px-3 py-2 rounded-lg border border-blue-200 text-center font-bold break-all shadow-inner">
                    ${rutaPadre}
                </div>
                <p>Si lo eliminas, borrarás el acceso de este grupo/usuario en esa carpeta principal y afectará a <strong class="text-slate-700 font-bold">TODAS</strong> sus subcarpetas.</p>
                <p class="font-semibold text-rose-600 mt-2">¿Estás completamente seguro de que deseas eliminar este permiso?</p>
            </div>
        `;
        const confirmed = await customConfirm(msg, "Advertencia de Herencia");
        if (!confirmed) return;
    }

    const formData = new FormData();
    formData.append("id", id);
    const res = await fetch("api.php?ajax_action=remove_perm", {
        method: "POST",
        body: formData,
    });
    const data = await res.json();

    if (!data.success && data.error) {
        customAlert(data.error, "Bloqueo de Seguridad");
    }
    cargarPermisos();
}

function aplicarFiltroPermisos(tipo) {
  const btnAll = document.getElementById("btnFiltroAll");
  const btnGrupo = document.getElementById("btnFiltroGrupo");
  const btnUsuario = document.getElementById("btnFiltroUsuario");

  if (btnAll) {
    [btnAll, btnGrupo, btnUsuario].forEach((b) => {
      b.classList.remove("bg-white", "shadow", "text-slate-800");
      b.classList.add("text-slate-500");
    });

    const activeBtn = document.getElementById(
      "btnFiltro" +
        (tipo === "all" ? "All" : tipo === "grupo" ? "Grupo" : "Usuario"),
    );
    activeBtn.classList.remove("text-slate-500");
    activeBtn.classList.add("bg-white", "shadow", "text-slate-800");
  }

  const items = document.querySelectorAll(".permiso-item");
  items.forEach((item) => {
    if (tipo === "all" || item.dataset.tipo === tipo) {
      item.classList.remove("hidden");
      item.classList.add("flex");
    } else {
      item.classList.remove("flex");
      item.classList.add("hidden");
    }
  });
}

async function cargarPermisos() {
  const list = document.getElementById("listaPermisos");
  const filterContainer = document.getElementById("permFilterContainer");

  list.innerHTML =
    '<p class="text-sm text-slate-400 italic animate-pulse">Cargando permisos...</p>';
  filterContainer.innerHTML = "";

  const res = await fetch(
    `api.php?ajax_action=get_perms&ruta=${currentPermRuta}`,
  );
  const data = await res.json();

  list.innerHTML = "";

  if (data.length === 0) {
    list.innerHTML =
      '<p class="text-sm text-slate-400 italic">No hay permisos explícitos asignados a esta carpeta.</p>';
  } else {
    let hasGrupos = data.some((p) => p.tipo_entidad === "grupo");
    let hasUsuarios = data.some((p) => p.tipo_entidad === "usuario");

    if (hasGrupos && hasUsuarios) {
      filterContainer.innerHTML = `
                <div class="flex justify-center mb-4">
                    <div class="bg-slate-100 p-1 rounded-lg inline-flex">
                        <button id="btnFiltroAll" onclick="aplicarFiltroPermisos('all')" class="px-4 py-1.5 text-xs font-bold rounded-md bg-white shadow text-slate-800 transition-all">Ambos</button>
                        <button id="btnFiltroGrupo" onclick="aplicarFiltroPermisos('grupo')" class="px-4 py-1.5 text-xs font-bold rounded-md text-slate-500 hover:text-slate-700 transition-all">Grupos</button>
                        <button id="btnFiltroUsuario" onclick="aplicarFiltroPermisos('usuario')" class="px-4 py-1.5 text-xs font-bold rounded-md text-slate-500 hover:text-slate-700 transition-all">Usuarios</button>
                    </div>
                </div>
            `;
    }

    data.forEach((p) => {
      const isGroup = p.tipo_entidad === "grupo";
      const icon = isGroup
        ? '<svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM7 10a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z" /></svg>'
        : '<svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0ZM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7Z" /></svg>';
      const badgeClass = isGroup
        ? "bg-indigo-50 text-indigo-700 border border-indigo-100"
        : "bg-emerald-50 text-emerald-700 border border-emerald-100";

      const nombreMostrar = p.display_name ? p.display_name : p.nombre_entidad;

      const isInherited = p.heredado;
      const inheritedBadge = isInherited
        ? `<span class="text-[9px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200 ml-2" title="Heredado de /${p.ruta}">Heredado</span>`
        : "";
      const onClickAction = isInherited
        ? `eliminarPermiso(${p.id}, true, '${p.ruta}')`
        : `eliminarPermiso(${p.id}, false)`;

      list.innerHTML += `
                <div class="permiso-item flex justify-between items-center bg-white border border-slate-200 p-3 rounded-lg shadow-sm" data-tipo="${p.tipo_entidad}">
                    <div class="flex items-center gap-3">
                        <div class="${badgeClass} p-2 rounded-lg">${icon}</div>
                        <div>
                            <div class="flex items-center">
                                <p class="text-sm font-bold text-slate-800">${nombreMostrar}</p>
                                ${inheritedBadge}
                            </div>
                            <p class="text-[10px] uppercase font-bold text-slate-400 mt-0.5">${p.tipo_entidad}</p>
                        </div>
                    </div>
                    <button onclick="${onClickAction}" class="text-rose-400 hover:text-white hover:bg-rose-500 bg-rose-50 p-2 rounded-lg transition-colors border border-rose-100 hover:border-rose-500" title="Eliminar Permiso">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16" /></svg>
                    </button>
                </div>
            `;
    });
  }
}

// CONTROL DE LA TECLA ESCAPE
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    if (
      !document.getElementById("modalCustomAlert").classList.contains("hidden")
    ) {
      cerrarCustomAlert();
    } else if (
      !document
        .getElementById("modalCustomConfirm")
        .classList.contains("hidden")
    ) {
      document.getElementById("btnConfirmNo").click();
    } else if (
      !document.getElementById("modalDeptInput").classList.contains("hidden")
    ) {
      cerrarModalDeptInput();
    } else if (
      !document.getElementById("modalDeptBorrar").classList.contains("hidden")
    ) {
      cerrarModalDeptBorrar();
    } else if (
      !document.getElementById("modalPermisos").classList.contains("hidden")
    ) {
      if (isFromConfig) volverAConfig();
      else cerrarModalPermisos();
    } else if (
      !document.getElementById("modalConfig").classList.contains("hidden")
    ) {
      cerrarModalConfig();
    } else if (
      !document.getElementById("modalInput").classList.contains("hidden")
    ) {
      cerrarModalInput();
    } else if (
      !document.getElementById("modalBorrar").classList.contains("hidden")
    ) {
      cerrarModalBorrar();
    }
  }
});
