<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar log detalhado de erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para registrar logs detalhados
function logDiagnostic($message) {
    $logFile = __DIR__ . '/diagnostic_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logDiagnostic("===== NOVO DIAGNÓSTICO INICIADO =====");
logDiagnostic("Método HTTP: " . $_SERVER['REQUEST_METHOD']);

try {
    // Verificar configurações do PHP
    $phpInfo = [
        'version' => phpversion(),
        'max_execution_time' => ini_get('max_execution_time'),
        'memory_limit' => ini_get('memory_limit'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'display_errors' => ini_get('display_errors')
    ];
    logDiagnostic("Configurações PHP: " . json_encode($phpInfo));

    // Testar inclusão de config.php
    logDiagnostic("Tentando incluir config.php...");
    if (file_exists("../../config.php")) {
        include_once("../../config.php");
        logDiagnostic("config.php incluído com sucesso");
        
        // Verificar se as variáveis de conexão estão definidas
        $configVars = [
            'host' => isset($host) ? 'definido' : 'indefinido',
            'user' => isset($user) ? 'definido' : 'indefinido',
            'pass' => isset($pass) ? 'definido' : 'indefinido (ou vazio)',
            'database' => isset($database) ? 'definido' : 'indefinido',
            'port' => isset($port) ? $port : 'indefinido'
        ];
        logDiagnostic("Variáveis de config: " . json_encode($configVars));
    } else {
        logDiagnostic("ERRO: config.php não encontrado");
        throw new Exception("O arquivo config.php não foi encontrado");
    }

    // Testar conexão principal
    logDiagnostic("Tentando conectar ao banco de dados principal...");
    $conn = new mysqli($host, $user, $pass, $database, $port);
    
    if ($conn->connect_error) {
        logDiagnostic("ERRO: Falha na conexão principal: " . $conn->connect_error);
        throw new Exception("Erro de conexão com o banco principal: " . $conn->connect_error);
    }
    logDiagnostic("Conexão com banco principal estabelecida com sucesso");
    
    // Testar conexão com bd_pacientestto
    logDiagnostic("Tentando conectar ao banco bd_pacientestto...");
    $conn_pacientes = new mysqli($host, $user, $pass, "bd_pacientestto", $port);
    
    if ($conn_pacientes->connect_error) {
        logDiagnostic("ERRO: Falha na conexão com bd_pacientestto: " . $conn_pacientes->connect_error);
        throw new Exception("Erro de conexão com bd_pacientestto: " . $conn_pacientes->connect_error);
    }
    logDiagnostic("Conexão com bd_pacientestto estabelecida com sucesso");
    
    // Verificar se a tabela Protocolo existe
    $checkTableSQL = "SHOW TABLES LIKE 'Protocolo'";
    $tableResult = $conn_pacientes->query($checkTableSQL);
    if ($tableResult->num_rows == 0) {
        logDiagnostic("ERRO: Tabela 'Protocolo' não encontrada");
        throw new Exception("A tabela 'Protocolo' não existe no banco de dados bd_pacientestto");
    }
    logDiagnostic("Tabela 'Protocolo' encontrada");
    
    // Verificar estrutura da tabela Protocolo
    $describeTableSQL = "DESCRIBE Protocolo";
    $structureResult = $conn_pacientes->query($describeTableSQL);
    $columns = [];
    
    if ($structureResult) {
        while ($row = $structureResult->fetch_assoc()) {
            $columns[] = [
                'Field' => $row['Field'],
                'Type' => $row['Type'],
                'Null' => $row['Null'],
                'Key' => $row['Key'],
                'Default' => $row['Default']
            ];
        }
        logDiagnostic("Estrutura da tabela Protocolo: " . json_encode($columns));
    } else {
        logDiagnostic("ERRO: Não foi possível obter estrutura da tabela Protocolo");
    }
    
    // Testar uma inserção simples
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        logDiagnostic("Recebendo dados do POST...");
        $postRaw = file_get_contents('php://input');
        logDiagnostic("Dados brutos recebidos: " . $postRaw);
        
        $postData = json_decode($postRaw, true);
        
        if ($postData) {
            logDiagnostic("Dados JSON decodificados: " . json_encode($postData));
            
            // Testar inserção simples com dados mínimos
            $testInsertSQL = "INSERT INTO Protocolo (Protocolo_Nome, Protocolo_Sigla) VALUES (?, ?)";
            $testName = "TEST_" . date('YmdHis');
            $testSigla = "TST" . mt_rand(100, 999);
            
            $testStmt = $conn_pacientes->prepare($testInsertSQL);
            
            if ($testStmt) {
                $testStmt->bind_param("ss", $testName, $testSigla);
                
                logDiagnostic("Tentando inserir protocolo de teste: $testName, $testSigla");
                
                if ($testStmt->execute()) {
                    $testInsertId = $conn_pacientes->insert_id;
                    logDiagnostic("Inserção de teste bem-sucedida. ID: $testInsertId");
                    
                    // Remover registro de teste
                    $deleteTestSQL = "DELETE FROM Protocolo WHERE id = ?";
                    $deleteStmt = $conn_pacientes->prepare($deleteTestSQL);
                    if ($deleteStmt) {
                        $deleteStmt->bind_param("i", $testInsertId);
                        $deleteStmt->execute();
                        logDiagnostic("Registro de teste removido");
                        $deleteStmt->close();
                    }
                } else {
                    logDiagnostic("ERRO: Falha na inserção de teste: " . $testStmt->error);
                }
                
                $testStmt->close();
            } else {
                logDiagnostic("ERRO: Falha ao preparar consulta de teste: " . $conn_pacientes->error);
            }
            
            // Tentar executar procedimento normal com os dados recebidos
            logDiagnostic("Tentando inserir com os dados recebidos...");
            
            // Verificar dados mínimos
            if (isset($postData['Protocolo_Nome']) && isset($postData['Protocolo_Sigla'])) {
                // Determinar campos a serem inseridos
                $fields = [
                    "Protocolo_Nome", 
                    "Protocolo_Sigla"
                ];
                
                // Adicionar campos opcionais se fornecidos
                $optionalFields = [
                    "Servico_Codigo", 
                    "Protocolo_Dose_M", 
                    "Protocolo_Dose_Total", 
                    "Protocolo_Dias_de_Aplicacao", 
                    "Protocolo_ViaAdm", 
                    "Linha",
                    "CID"
                ];
                
                $insertFields = $fields;
                $insertValues = [];
                $types = "ss"; // Começar com string, string para Nome e Sigla
                
                $values = [
                    $postData['Protocolo_Nome'],
                    $postData['Protocolo_Sigla']
                ];
                
                // Adicionar campos opcionais
                foreach ($optionalFields as $field) {
                    if (isset($postData[$field]) && $postData[$field] !== '') {
                        $insertFields[] = $field;
                        $values[] = $postData[$field];
                        
                        // Determinar tipo de campo
                        if (in_array($field, ["Protocolo_Dose_M", "Protocolo_Dose_Total"])) {
                            $types .= "d"; // double
                        } elseif (in_array($field, ["Protocolo_Dias_de_Aplicacao", "Protocolo_ViaAdm", "Linha"])) {
                            $types .= "i"; // integer
                        } else {
                            $types .= "s"; // string
                        }
                    }
                }
                
                // Construir consulta SQL
                $insertSQL = "INSERT INTO Protocolo (" . implode(", ", $insertFields) . ") VALUES (";
                $placeholders = array_fill(0, count($insertFields), "?");
                $insertSQL .= implode(", ", $placeholders) . ")";
                
                logDiagnostic("SQL de inserção: " . $insertSQL);
                logDiagnostic("Tipos de dados: " . $types);
                logDiagnostic("Valores: " . json_encode($values));
                
                $insertStmt = $conn_pacientes->prepare($insertSQL);
                
                if ($insertStmt) {
                    // Criar array de referências para bind_param
                    $bindParams = array();
                    $bindParams[] = $types;
                    
                    for ($i = 0; $i < count($values); $i++) {
                        $bindParams[] = &$values[$i];
                    }
                    
                    logDiagnostic("Tentando fazer bind dos parâmetros");
                    call_user_func_array(array($insertStmt, 'bind_param'), $bindParams);
                    
                    if ($insertStmt->execute()) {
                        $insertId = $conn_pacientes->insert_id;
                        logDiagnostic("Inserção bem-sucedida. ID: $insertId");
                        
                        // Retornar sucesso
                        $response = [
                            'success' => true,
                            'message' => 'Protocolo inserido com sucesso',
                            'id' => $insertId,
                            'id_protocolo' => $insertId
                        ];
                    } else {
                        logDiagnostic("ERRO: Falha na inserção: " . $insertStmt->error);
                        throw new Exception("Falha na inserção: " . $insertStmt->error);
                    }
                    
                    $insertStmt->close();
                } else {
                    logDiagnostic("ERRO: Falha ao preparar consulta: " . $conn_pacientes->error);
                    throw new Exception("Falha ao preparar consulta: " . $conn_pacientes->error);
                }
            } else {
                logDiagnostic("ERRO: Dados mínimos não fornecidos (Nome e Sigla)");
                throw new Exception("Dados mínimos não fornecidos (Nome e Sigla)");
            }
        } else {
            logDiagnostic("ERRO: Não foi possível decodificar JSON");
            throw new Exception("Não foi possível decodificar JSON do corpo da requisição");
        }
    } else {
        // Se for GET, apenas realizar diagnóstico sem tentar inserir
        $response = [
            'success' => true,
            'message' => 'Diagnóstico concluído com sucesso',
            'php_info' => $phpInfo,
            'database_connection' => [
                'main' => 'success',
                'pacientes' => 'success'
            ],
            'table_structure' => $columns
        ];
        logDiagnostic("Diagnóstico concluído para requisição GET");
    }

    echo json_encode($response);

} catch (Exception $e) {
    logDiagnostic("ERRO FATAL: " . $e->getMessage());
    $errorResponse = [
        'success' => false,
        'error' => $e->getMessage()
    ];
    http_response_code(500);
    echo json_encode($errorResponse);
} finally {
    if (isset($conn)) {
        $conn->close();
        logDiagnostic("Conexão principal fechada");
    }
    if (isset($conn_pacientes)) {
        $conn_pacientes->close();
        logDiagnostic("Conexão bd_pacientestto fechada");
    }
    logDiagnostic("===== DIAGNÓSTICO FINALIZADO =====\n");
}
?>