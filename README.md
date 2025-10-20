# 🛒 Plataforma Web Interna de Gestión

Proyecto de 2º de ASIR  
Desarrollo de una intranet para la gestión de usuarios, incidencias, inventario y nóminas dentro de una organización simulada.

---

## 📋 Descripción del proyecto

El objetivo de este proyecto es crear una **plataforma web interna** que permita la gestión centralizada de distintos procesos administrativos (usuarios, incidencias, inventario, nóminas, etc.), desplegada sobre un **servidor Linux** con servicios web y base de datos.

El sistema está diseñado para un entorno empresarial cerrado, con acceso seguro mediante autenticación LDAP/AD y conexión HTTPS.

Todos los sistemas van a ser creados con **vagrant** y **ansible**

---

## 🧱 Arquitectura del sistema

**Componentes principales:**

- 🖥️ **Servidor Web:** Nginx
- 🗄️ **Base de Datos:** MySQL
- 🧩 **Backend:** Flask
- 🔐 **Autenticación:** LDAP
- 💾 **Copias de seguridad automáticas:** Scripts Bash (cron)
- 🧰 **Seguridad:** firewall, permisos de acceso
- 🌐 **Networking** VPN, DNS, DHCP
**Esquema general:**

