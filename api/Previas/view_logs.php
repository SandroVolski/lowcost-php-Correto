<?php
// view_logs.php - Visualizar logs de erro do PHP

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'logs' => []
];

// Possíveis localizações de logs
$logPaths = [
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log',
    '/var/log/php_errors.log',
    '/var/log/httpd/error_log',
    ini_get('error_log'),
    '/tmp/php_errors.log'
];

// Adicionar log do diretório atual
$logPaths[] = dirname(__FILE__) . '/error.log';
$logPaths[] = dirname(__FILE__) . '/php_errors.log';

foreach ($logPaths as $logPath) {
    if ($logPath && file_exists($logPath) && is_readable($logPath)) {
        $response['logs'][$logPath] = [
            'exists' => true,
            'size' => filesize($logPath),
            'last_modified' => date('Y-m-d H:i:s', filemtime($logPath))
        ];
        
        // Ler últimas 50 linhas do log
        $lines = [];
        $file = file($logPath);
        if ($file) {
            $lines = array_slice($file, -50);
            // Filtrar apenas linhas relacionadas ao nosso script
            $filteredLines = array_filter($lines, function($line) {
                return strpos($line, 'DEBUG PREVIAS') !== false || 
                       strpos($line, 'previas') !== false ||
                       strpos($line, 'get_all_previas') !== false;
            });
            
            $response['logs'][$logPath]['recent_lines'] = array_values($filteredLines);
            $response['logs'][$logPath]['total_lines'] = count($lines);
        }
    } else {
        $response['logs'][$logPath] = [
            'exists' => false,
            'reason' => !file_exists($logPath) ? 'not_found' : 'not_readable'
        ];
    }
}

// Informações sobre configuração de logs
$response['php_error_config'] = [
    'log_errors' => ini_get('log_errors'),
    'error_log' => ini_get('error_log'),
    'display_errors' => ini_get('display_errors'),
    'error_reporting' => error_reporting()
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>