CREATE DATABASE IF NOT EXISTS redmine_db;
GRANT ALL PRIVILEGES ON redmine_db.* TO 'user'@'%';
FLUSH PRIVILEGES;

USE insrv5_db;
CREATE TABLE IF NOT EXISTS dashboard_apps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icono_svg TEXT NOT NULL,
    color_fondo VARCHAR(50) DEFAULT 'bg-white',
    roles_permitidos VARCHAR(255) NOT NULL DEFAULT 'Todos',
    creado_por VARCHAR(100),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO `dashboard_apps` (`id`, `nombre`, `descripcion`, `url`, `icono_svg`, `color_fondo`, `roles_permitidos`, `creado_por`, `fecha_creacion`) VALUES
(1, 'Redmine Tareas', 'Accede al gestor de proyectos para revisar tus tareas asignadas, crear tickets y registrar tu jornada laboral.', 'https://tareas.insrv5.local/sso.php?target=https://tareas.insrv5.local/&token={sso_token}', '<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-6 w-6\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4\" /></svg>', 'bg-emerald-50 text-emerald-600', 'Todos', 'Sistema', '2026-03-17 16:56:50'),
(2, 'Portal RRHH', 'Panel exclusivo para la gestión de nóminas, aprobación de vacaciones y administración de la plantilla.', 'rrhh_panel.php', '<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-6 w-6\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z\" /></svg>', 'bg-rose-50 text-rose-600', 'Recursos Humanos', 'Sistema', '2026-03-17 16:56:50'),
(3, 'Panel de Nóminas', 'Acceso aislado y seguro a los registros salariales. Generación de PDFs y edición de sueldos del personal.', 'https://nominas.insrv5.local/', '<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-6 w-6\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z\" /></svg>', 'bg-violet-50 text-violet-600', 'Administracion,Recursos Humanos,IT', 'Sistema', '2026-03-17 16:56:50'),
(4, 'Directorio LDAP', 'Administración de phpLDAPadmin. Gestión directa de árboles de usuarios, grupos y políticas de seguridad (ACL).', 'https://ldapadmin.insrv5.local/sso.php?target=https://ldapadmin.insrv5.local/&token={sso_token}', '<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-6 w-6\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01\" /></svg>', 'bg-indigo-50 text-indigo-600', 'IT', 'Sistema', '2026-03-17 16:56:50'),
(5, 'phpMyAdmin', 'Acceso a la base de datos relacional de la infraestructura. Gestión de esquemas y copias de seguridad.', 'https://pma.insrv5.local/sso.php?target=https://pma.insrv5.local/&token={sso_token}', '<svg xmlns=\"http://www.w3.org/2000/svg\" class=\"h-6 w-6\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4\" /></svg>', 'bg-sky-50 text-sky-600', 'IT', 'Sistema', '2026-03-17 16:56:50');

CREATE DATABASE IF NOT EXISTS nominas_db;
GRANT ALL PRIVILEGES ON nominas_db.* TO 'user'@'%';
USE nominas_db;
CREATE TABLE IF NOT EXISTS nominas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    salario_base DECIMAL(10,2) DEFAULT 0.00,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pagas INT DEFAULT 12,
    modificado_por VARCHAR(100) DEFAULT 'Sistema'
);
CREATE TABLE IF NOT EXISTS nominas_extras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    mes VARCHAR(2) NOT NULL,
    anio VARCHAR(4) NOT NULL,
    concepto VARCHAR(100) NOT NULL,
    importe DECIMAL(10,2) NOT NULL,
    registrado_por VARCHAR(100),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
FLUSH PRIVILEGES;