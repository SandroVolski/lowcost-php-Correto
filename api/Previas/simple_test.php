<?php
// simple_test.php - Teste simples para diagnosticar problemas

// Habilitar exibição de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'current_dir' => getcwd(),
    'script_path' => __FILE__,
    'tests' => []
];

// Teste 1: Verificar se o arquivo config existe
$configPath = "../../config.php";
$response['tests']['config_file'] = [
    'path' => $configPath,
    'exists' => file_exists($configPath),
    'readable' => file_exists($configPath) ? is_readable($configPath) : false
];

if (file_exists($configPath)) {
    try {
        // Teste 2: Incluir config
        include_once($configPath);
        $response['tests']['config_include'] = ['success' => true];
        
        // Teste 3: Verificar variável de conexão
        $response['tests']['connection_var'] = [
            'conn_pacientes_exists' => isset($conn_pacientes),
            'is_mysqli' => isset($conn_pacientes) ? ($conn_pacientes instanceof mysqli) : false
        ];
        
        if (isset($conn_pacientes) && $conn_pacientes instanceof mysqli) {
            // Teste 4: Verificar conexão
            $response['tests']['connection'] = [
                'connect_error' => $conn_pacientes->connect_error,
                'ping' => $conn_pacientes->ping(),
                'server_info' => $conn_pacientes->server_info,
                'charset' => $conn_pacientes->character_set_name()
            ];
            
            if (!$conn_pacientes->connect_error && $conn_pacientes->ping()) {
                // Teste 5: Verificar tabelas
                $tables = ['previas', 'pacientes', 'usuarios', 'previa_anexos'];
                $tableResults = [];
                
                foreach ($tables as $table) {
                    $result = $conn_pacientes->query("SHOW TABLES LIKE '$table'");
                    $tableResults[$table] = [
                        'exists' => $result && $result->num_rows > 0,
                        'error' => $conn_pacientes->error ?: null
                    ];
                    
                    // Se a tabela existe, contar registros
                    if ($result && $result->num_rows > 0) {
                        $countResult = $conn_pacientes->query("SELECT COUNT(*) as total FROM $table");
                        if ($countResult) {
                            $count = $countResult->fetch_assoc();
                            $tableResults[$table]['count'] = $count['total'];
                        }
                    }
                }
                
                $response['tests']['tables'] = $tableResults;
                
                // Teste 6: Query simples na tabela previas
                if ($tableResults['previas']['exists'] && $tableResults['pacientes']['exists']) {
                    try {
                        $sql = "SELECT p.id, p.paciente_id, pac.Nome 
                               FROM previas p 
                               INNER JOIN pacientes pac ON p.paciente_id = pac.id 
                               LIMIT 1";
                        
                        $result = $conn_pacientes->query($sql);
                        
                        $response['tests']['simple_query'] = [
                            'success' => $result !== false,
                            'num_rows' => $result ? $result->num_rows : 0,
                            'error' => $conn_pacientes->error ?: null
                        ];
                        
                        if ($result && $result->num_rows > 0) {
                            $response['tests']['simple_query']['sample_data'] = $result->fetch_assoc();
                        }
                        
                    } catch (Exception $e) {
                        $response['tests']['simple_query'] = [
                            'success' => false,
                            'error' => $e->getMessage()
                        ];
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        $response['tests']['config_include'] = [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
} else {
    $response['tests']['config_include'] = [
        'success' => false,
        'error' => 'Config file not found'
    ];
}

// Informações do sistema
$response['system_info'] = [
    'php_extensions' => [
        'mysqli' => extension_loaded('mysqli'),
        'json' => extension_loaded('json')
    ],
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown'
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>