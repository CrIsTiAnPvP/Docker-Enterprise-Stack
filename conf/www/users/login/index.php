<?php
session_start();

$ldap_server = "openldap.insrv5.local";
$ldap_dn = "dc=insrv5,dc=local";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$user = $_POST['user'];
	$pwd = $_POST['pwd'];

	$ldap_conn = ldap_connect($ldap_server);
	ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);

	if ($ldap_conn) {
		$ldap_user_dn = "cn=$user,dc=insrv5,dc=local";

		$bind = @ldap_bind($ldap_conn, $ldap_user_dn, $pwd);

		if ($bind) {
			$_SESSION['user'] = $user;
			$msg = "<h3 style='color:green'>¡Login Correcto en LDAP ($user)!</h3>";
		} else {
			$msg = "<h3 style='color:red'>Error de autenticación. Usuario o contraseña incorrectos. ($user : $pwd)</h3>";
		}
		ldap_close($ldap_conn);
	} else {
		$msg = "<h3 style='color:red'>No se pudo conectar al servidor LDAP.</h3>";
	}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Insrv5 Users Login</title>
</head>
<body>
	<h1>Insrv5 Users Access</h1>
	<?php echo $msg; ?>
	<form method="POST" action="">
		<div>
			<label for="user">User:</label>
			<input type="text" id="user" name="user" required />
		</div>
		<div>
			<label for="pwd">Password:</label>
			<input type="password" id="pwd" name="pwd" required />
		</div>
		<div>
			<input type="submit" value="Login" />
		</div>
	</form>
</body>
</html>