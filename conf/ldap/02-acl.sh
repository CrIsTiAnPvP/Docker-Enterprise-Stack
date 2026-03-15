#!/bin/bash

echo "Inyectando reglas de seguridad (ACL) para la cuenta de servicio..."

ldapmodify -Y EXTERNAL -H ldapi:/// <<EOF
dn: olcDatabase={1}mdb,cn=config
changetype: modify
replace: olcAccess
olcAccess: {0}to attrs=userPassword by self write by anonymous auth by * none
olcAccess: {1}to dn.base="" by * read
olcAccess: {2}to * by dn.exact="uid=visor-usuarios,ou=usuarios,dc=insrv5,dc=local" read by * break
EOF

echo "ACLs inyectadas correctamente."