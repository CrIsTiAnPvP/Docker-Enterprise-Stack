<?php
ob_start();
session_set_cookie_params(['domain' => '.insrv5.local']);
session_start();

error_reporting(0);
ini_set('display_errors', 0);
putenv('LDAPTLS_REQCERT=never'); 

if (!isset($_SESSION['user']) || !isset($_SESSION['rol'])) {
    http_response_code(401);
    exit();
}

$target_uid = isset($_GET['uid']) ? strtolower(trim($_GET['uid'])) : '';
if (empty($target_uid)) {
    die("Error: No se ha especificado un usuario.");
}

require('./fpdf/fpdf.php');

$ldap_host = "ldaps://openldap.insrv5.local:636";
$ldap_dn_base = "dc=insrv5,dc=local";
$ldap_user = "cn=visor-usuarios,dc=insrv5,dc=local";
$ldap_pass = "visorpwd";

$db_host = "mysql.insrv5.local";
$db_user = "user";
$db_pass = "1234";
$db_name = "nominas_db";

$mes_actual = date('m');
$anio_actual = date('Y');

if (isset($_GET['periodo']) && preg_match('/^(\d{2})-(\d{4})$/', $_GET['periodo'], $matches)) {
    $mes_actual = $matches[1];
    $anio_actual = $matches[2];
}

$salario_anual = 0;
$extras = [];
$total_extras = 0;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
    
    $pagas_anuales = 12;
    $stmt = $pdo->prepare("SELECT salario_base, pagas FROM nominas WHERE username = :uid");
    $stmt->execute([':uid' => $target_uid]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $salario_anual = (float)$row['salario_base'];
        $pagas_anuales = (int)($row['pagas'] ?? 12);
    }

    $stmtExtras = $pdo->prepare("SELECT concepto, importe FROM nominas_extras WHERE username = :uid AND mes = :mes AND anio = :anio");
    $stmtExtras->execute([
        ':uid' => $target_uid,
        ':mes' => $mes_actual,
        ':anio' => $anio_actual
    ]);
    while ($rowExtra = $stmtExtras->fetch(PDO::FETCH_ASSOC)) {
        $extras[] = $rowExtra;
        $total_extras += (float)$rowExtra['importe'];
    }

} catch (PDOException $e) {
    ob_end_clean();
    die("Error de BD");
}

if ($salario_anual <= 0) {
    ob_end_clean();
    die("Error: Usuario sin salario configurado.");
}

$target_nombre = $target_uid;
$target_rol = "Trabajadores";

$ldap_conn = @ldap_connect($ldap_host);
if ($ldap_conn) {
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    if (@ldap_bind($ldap_conn, $ldap_user, $ldap_pass)) {
        
        $search_grupos = ldap_search($ldap_conn, $ldap_dn_base, "(objectClass=groupOfNames)");
        $entradas_grupos = ldap_get_entries($ldap_conn, $search_grupos);
        for ($i = 0; $i < $entradas_grupos["count"]; $i++) {
            if (isset($entradas_grupos[$i]["member"])) {
                for ($j = 0; $j < $entradas_grupos[$i]["member"]["count"]; $j++) {
                    if (strpos(strtolower($entradas_grupos[$i]["member"][$j]), "uid=$target_uid") !== false) {
                        $target_rol = $entradas_grupos[$i]["cn"][0];
                    }
                }
            }
        }
        
        $search_user = ldap_search($ldap_conn, $ldap_dn_base, "(objectClass=person)");
        $entradas_user = ldap_get_entries($ldap_conn, $search_user);
        for ($i = 0; $i < $entradas_user["count"]; $i++) {
            $uid_ldap = strtolower($entradas_user[$i]["uid"][0] ?? '');
            if ($uid_ldap === $target_uid) {
                $target_nombre = $entradas_user[$i]["cn"][0] ?? $target_uid;
                break;
            }
        }
    }
    ldap_close($ldap_conn);
}

$salario_mes = $salario_anual / $pagas_anuales;
$bruto_mensual = $salario_mes + $total_extras;

$irpf = $bruto_mensual * 0.15;
$c_comunes = $bruto_mensual * 0.047;
$desempleo = $bruto_mensual * 0.0155;
$fp = $bruto_mensual * 0.001;

$total_deducciones = $irpf + $c_comunes + $desempleo + $fp;
$liquido_total = $bruto_mensual - $total_deducciones;

$meses = ['01'=>'Enero', '02'=>'Febrero', '03'=>'Marzo', '04'=>'Abril', '05'=>'Mayo', '06'=>'Junio', '07'=>'Julio', '08'=>'Agosto', '09'=>'Septiembre', '10'=>'Octubre', '11'=>'Noviembre', '12'=>'Diciembre'];
$periodo = $meses[$mes_actual] . " " . $anio_actual;

if (!function_exists('clean')) {
    function clean($txt) {
        return mb_convert_encoding($txt ?? '', 'ISO-8859-1', 'UTF-8');
    }
}

$safe_filename = preg_replace('/[^a-zA-Z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', $target_nombre));
$safe_filename = trim($safe_filename, '_');

class PDF extends FPDF {
    function Header() {
        $this->SetFillColor(37, 99, 235);
        $this->Rect(10, 10, 12, 12, 'F');
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 10);
        $this->Cell(12, 12, 'I5', 0, 0, 'C');
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(15, 23, 42); 
        $this->SetXY(25, 12);
        $this->Cell(0, 8, clean('Insrv5 Workspace'), 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 116, 139); 
        $this->SetXY(25, 19);
        $this->Cell(0, 5, clean('Departamento de Recursos Humanos | CIF: B-12345678'), 0, 1, 'L');
        $this->Ln(15);
    }
    function Footer() {
        $this->SetY(-20);
        $this->SetDrawColor(226, 232, 240); 
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(148, 163, 184); 
        $this->Cell(0, 4, clean('Documento generado digitalmente por Insrv5 Workspace. Uso interno.'), 0, 1, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetDrawColor(226, 232, 240);

$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 10, clean('Nómina Mensual'), 0, 1, 'L');
$pdf->Ln(2);

$pdf->SetFillColor(248, 250, 252); 
$pdf->Rect(10, $pdf->GetY(), 190, 25, 'FD');

$pdf->SetY($pdf->GetY() + 4);
$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(70, 5, clean('EMPLEADO'), 0, 0);
$pdf->Cell(70, 5, clean('DEPARTAMENTO / ROL'), 0, 0);
$pdf->Cell(40, 5, clean('PERIODO'), 0, 1);

$pdf->SetX(15);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(15, 23, 42); 
$pdf->Cell(70, 6, clean($target_nombre), 0, 0);
$pdf->Cell(70, 6, clean($target_rol), 0, 0);
$pdf->Cell(40, 6, clean($periodo), 0, 1);

$pdf->SetX(15);
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 116, 139);
$pdf->Cell(70, 5, clean('Usuario: ' . $target_uid), 0, 1);
$pdf->Ln(12);

$pdf->SetFillColor(241, 245, 249); 
$pdf->SetTextColor(71, 85, 105); 
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 10, clean(' CONCEPTO'), 'B', 0, 'L', true);
$pdf->Cell(50, 10, clean(' DEVENGOS'), 'B', 0, 'R', true);
$pdf->Cell(50, 10, clean(' DEDUCCIONES'), 'B', 1, 'R', true);

$pdf->SetTextColor(15, 23, 42); 
$pdf->SetFont('Arial', '', 10);

function fila_tabla($pdf, $concepto, $devengo, $deduccion) {
    $pdf->Cell(90, 10, clean(' ' . $concepto), 'B', 0, 'L');
    $pdf->Cell(50, 10, $devengo ? number_format($devengo, 2, ',', '.') . " EUR" : '', 'B', 0, 'R');
    $pdf->Cell(50, 10, $deduccion ? number_format($deduccion, 2, ',', '.') . " EUR" : '', 'B', 1, 'R');
}

fila_tabla($pdf, 'Salario Base', $salario_mes, null);

foreach ($extras as $extra) {
    fila_tabla($pdf, $extra['concepto'], $extra['importe'], null);
}

fila_tabla($pdf, 'Retención IRPF (15%)', null, $irpf);
fila_tabla($pdf, 'Seg. Social - Contingencias Comunes (4.70%)', null, $c_comunes);
fila_tabla($pdf, 'Seg. Social - Desempleo (1.55%)', null, $desempleo);
fila_tabla($pdf, 'Seg. Social - Formación Profesional (0.10%)', null, $fp);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(90, 10, clean(' TOTALES'), 0, 0, 'R');
$pdf->Cell(50, 10, number_format($bruto_mensual, 2, ',', '.') . " EUR", 0, 0, 'R');
$pdf->Cell(50, 10, number_format($total_deducciones, 2, ',', '.') . " EUR", 0, 1, 'R');

$pdf->Ln(8);

$pdf->SetFillColor(124, 58, 237);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(140, 14, clean(' LÍQUIDO A PERCIBIR (NETO)   '), 0, 0, 'R', true);
$pdf->SetFont('Arial', 'B', 15);
$pdf->Cell(50, 14, number_format($liquido_total, 2, ',', '.') . " EUR ", 0, 1, 'R', true);

if (ob_get_length()) ob_end_clean();
$pdf->Output('D', 'Nomina_' . $safe_filename . '_' . $anio_actual . '_' . $mes_actual . '.pdf');
exit();
?>