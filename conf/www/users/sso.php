<?php
if (isset($_GET['token']) && isset($_GET['target'])) {
    $target = filter_var($_GET['target'], FILTER_SANITIZE_URL);
    if (strpos($target, '.insrv5.local') === false && strpos($target, '.insrv5.net') === false) {
        die("Destino no permitido.");
    }

    session_id($_GET['token']);
    session_set_cookie_params(['domain' => '.insrv5.local']);
    session_start();
    
    session_write_close();

    header("Location: " . $target);
    exit();
} else {
    header("Location: https://insrv5.net/users/index.php");
    exit();
}