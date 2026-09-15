<?php
// Script puro para invadir a pasta storage e ler o log oculto
$arquivo = __DIR__ . '/../storage/logs/laravel.log';

if (file_exists($arquivo)) {
    echo "<h2 style='font-family:sans-serif;'>Erro Interno do Laravel (Últimas linhas):</h2>";
    echo "<pre style='background:#1e1e1e; color:#00ff00; padding:20px; border-radius:8px; overflow-x:auto;'>";
    
    $linhas = file($arquivo);
    $ultimas_linhas = array_slice($linhas, -50); // Pega só o final para não travar
    
    foreach ($ultimas_linhas as $linha) {
        echo htmlspecialchars($linha);
    }
    
    echo "</pre>";
} else {
    echo "<h2 style='font-family:sans-serif; color:red;'>O arquivo laravel.log não existe. O PHP está morrendo antes de conseguir abrir a boca.</h2>";
}
