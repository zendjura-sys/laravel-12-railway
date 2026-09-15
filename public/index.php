<?php

// INJEÇÃO DE EMERGÊNCIA: Forçar o PHP a cuspir os erros fatais na tela
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// MODO ESPIÃO: Se acessarmos a URL com "?socorro=1", ele lê o log e para.
if (isset($_GET['socorro'])) {
    $arquivo = __DIR__ . '/../storage/logs/laravel.log';
    if (file_exists($arquivo)) {
        echo "<h2 style='font-family:sans-serif;'>Logs Internos do Laravel:</h2>";
        echo "<pre style='background:#1e1e1e; color:#0f0; padding:20px; border-radius:8px;'>";
        readfile($arquivo);
        echo "</pre>";
    } else {
        echo "<h2 style='font-family:sans-serif; color:red;'>O arquivo de log está vazio ou não foi gerado.</h2>";
    }
    exit;
}

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());
