<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para logging detalhado
function logInfo($message) {
    $logFile = __DIR__ . '/detailed_debug.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logInfo("=== DIAGNÓSTICO INICIADO ===");
    
    // ETAPA 1: Verificar se conseguimos acessar o arquivo de configuração
    logInfo("Tentando incluir config.php...");
    $config_path = dirname(__FILE__) . "/../../config.php";
    logInfo("Caminho do config: " . $config_path);
    logInfo("O arquivo existe? " . (file_exists($config_path) ? "SIM" : "NÃO"));
    
    include_once($config_path);
    logInfo("Config.php incluído sem erros");
    
    // ETAPA 2: Verificar conexões de banco de dados
    logInfo("Verificando conexões:");
    logInfo("conn_pacientes está definido? " . (isset($conn_pacientes) ? "SIM" : "NÃO"));
    if (isset($conn_pacientes)) {
        logInfo("conn_pacientes tem erro? " . ($conn_pacientes->connect_error ? "SIM: ".$conn_pacientes->connect_error : "NÃO"));
    }
    
    // ETAPA 3: Verificar estrutura da tabela
    logInfo("Verificando estrutura da tabela Protocolo...");
    if (isset($conn_pacientes) && !$conn_pacientes->connect_error) {
        $result = $conn_pacientes->query("DESCRIBE Protocolo");
        if ($result) {
            logInfo("Estrutura da tabela Protocolo:");
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row;
                logInfo("Coluna: " . $row['Field'] . ", Tipo: " . $row['Type'] . ", Null: " . $row['Null'] . ", Key: " . $row['Key']);
            }
        } else {
            logInfo("ERRO ao verificar estrutura: " . $conn_pacientes->error);
        }
    }
    
    // ETAPA 4: Verificar dados recebidos
    $raw_input = file_get_contents("php://input");
    logInfo("Dados brutos recebidos: " . $raw_input);
    
    $jsonData = json_decode($raw_input, true);
    if ($jsonData === null) {
        logInfo("ERRO: JSON inválido recebido");
    } else {
        logInfo("JSON válido decodificado com " . count($jsonData) . " campos");
        foreach ($jsonData as $key => $value) {
            logInfo("Campo: $key, Valor: " . (is_array($value) ? json_encode($value) : $value));
        }
    }
    
    // RESULTADO DO DIAGNÓSTICO
    $diagnostico = [
        "status" => "success",
        "timestamp" => date("Y-m-d H:i:s"),
        "config_exists" => file_exists($config_path),
        "db_connection" => isset($conn_pacientes) && !$conn_pacientes->connect_error,
        "message" => "Diagnóstico concluído - verifique o arquivo detailed_debug.txt para detalhes"
    ];
    
    logInfo("=== DIAGNÓSTICO CONCLUÍDO ===");
    echo json_encode($diagnostico);
    
} catch (Exception $e) {
    logInfo("EXCEÇÃO: " . $e->getMessage());
    logInfo("STACK TRACE: " . $e->getTraceAsString());
    
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "log_file" => "detailed_debug.txt"
    ]);
}
?>