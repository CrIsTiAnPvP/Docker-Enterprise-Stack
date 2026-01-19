# 🛒 Plataforma Web Interna de Gestión

Proyecto de 2º de ASIR  
Desarrollo de una intranet para la gestión de usuarios, incidencias, inventario y nóminas dentro de una organización simulada.

---

## 📋 Descripción del proyecto

El objetivo de este proyecto es crear una **plataforma web interna** que permita la gestión centralizada de distintos procesos administrativos (usuarios, incidencias, inventario, nóminas, etc.), desplegada sobre un **servidor Linux** con servicios web y base de datos.

El sistema está diseñado para un entorno empresarial cerrado, con acceso seguro mediante autenticación LDAP/AD y conexión HTTPS.

Todos los sistemas van a ser creados con **docker** (quizás Kubernetes)

---

## 🧱 Arquitectura del sistema

**Componentes principales:**

- 🖥️ **Servidor Web:** Nginx, Apache
- 🗄️ **Base de Datos:** MySQL
- 🧩 **Backend:** PHP
- 🔐 **Autenticación:** LDAP
- 💾 **Copias de seguridad automáticas:** Scripts Bash (cron)
- 🧰 **Seguridad:** firewall, permisos de acceso
- 🌐 **Networking** VPN, DNS, DHCP

## 🏗️ Arquitectura del Proyecto

Este proyecto está organizado en varias zonas de red para garantizar **seguridad**, **escalabilidad** y **gestión remota segura**.

---

## 🌍 Zonas de Red

| Zona     | Descripción                                    |
|----------|------------                                    |
| Internet | Acceso de usuarios finales                     |
| DMZ      | Servicios expuestos (Nginx, DNS)               |
| LAN      | Servicios internos (PHP, MySQL, LDAP, Backup)  |
| VPN      | Acceso administrativo seguro                   |

---

## 📐 Esquema de Arquitectura

```text
🌍 INTERNET
   |
   v
[ Usuario ]
   |
   |  HTTP/HTTPS (insrv5.net)
   v
[ DNS ]
   |
   v
┌─────────────┐
│    Nginx    │  (Reverse Proxy)
└─────────────┘
        |
        v
┌──────────────────┐
│   App PHP        │
│                  │
│  - Login         │
│  - Sesiones      │
│  - Lógica        │
└──────────────────┘
     |            |
     |            |
     v            v
┌──────────┐   ┌──────────┐
│  MySQL   │   │  LDAP    │
└──────────┘   └──────────┘
     |            |
     +------------+
           |
           v
     ┌──────────────┐
     │   BACKUP     │
     └──────────────┘
           ^
           |
       ┌────────┐
       │  VPN   │  (Gestión segura)
       └────────┘
           |    \
           |     \
           v      v
        MySQL    LDAP
```

---

## 🔐 Flujo de Funcionamiento

### Acceso de usuarios

1. El usuario accede a `http://insrv5.net`
2. Se resuelve el dominio mediante **DNS**
3. El tráfico llega a **Nginx**
4. Nginx actúa como **reverse proxy** hacia la aplicación **PHP**
5. PHP valida:
   - Credenciales → LDAP
   - Sesiones y datos → MySQL

---

## 🔑 Autenticación

| Servicio | Uso                            |
|----------|-----                           |
| LDAP     | Autenticación de usuarios      |
| MySQL    | Datos de aplicación y sesiones |

---

## 💾 Sistema de Backups

El servidor de backup recibe datos desde:

- MySQL
- LDAP

El acceso al sistema de backup está restringido a través de la VPN.

---

## 🔒 Acceso por VPN (Administración)

La VPN permite acceso seguro para:

| Servicio | Acceso VPN |
|----------|------------|
| MySQL    | ✅         |
| LDAP     | ✅         |
| Backup   | ✅         |
| PHP      | ❌         |
| Nginx    | ❌         |

---

## 🔌 Puertos y Protocolos

| Servicio | Puerto | Protocolo |
|----------|--------|-----------|
| HTTP     | 80     | TCP       |
| HTTPS    | 443    | TCP       |
| DNS      | 53     | UDP/TCP   |
| MySQL    | 3306   | TCP       |
| LDAP     | 389 / 636 | TCP    |

---

## 🛡️ Medidas de Seguridad

- Nginx actúa como única puerta de entrada
- Servicios críticos aislados en la LAN
- Gestión remota exclusivamente por VPN
- Backups centralizados
- Autenticación centralizada mediante LDAP
