#!/bin/bash

# Parar servicio
sudo systemctl stop slapd

# Eliminar configuración existente
sudo rm -rf /var/lib/ldap/*
sudo rm -rf /etc/ldap/slapd.d/*

# Crear configuración mínima
sudo slapadd -n 0 -l - <<EOF
dn: cn=config
objectClass: olcGlobal
cn: config
olcArgsFile: /var/run/slapd/slapd.args
olcPidFile: /var/run/slapd/slapd.pid

dn: cn=module,cn=config
objectClass: olcModuleList
cn: module
olcModulepath: /usr/lib/ldap
olcModuleload: back_mdb.so

dn: cn=schema,cn=config
objectClass: olcSchemaConfig
cn: schema

dn: olcDatabase={1}mdb,cn=config
objectClass: olcDatabaseConfig
objectClass: olcMdbConfig
olcDatabase: {1}mdb
olcDbDirectory: /var/lib/ldap
olcSuffix: dc=proyecto,dc=local
olcRootDN: cn=admin,dc=proyecto,dc=local
olcRootPW: proyecto123
olcDbIndex: objectClass eq
EOF

# Asignar permisos
sudo chown -R openldap:openldap /var/lib/ldap
sudo chown -R openldap:openldap /etc/ldap/slapd.d

# Iniciar servicio
sudo systemctl start slapd
sudo systemctl enable slapd