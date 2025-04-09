<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

// Função para registrar erros em um arquivo de log
function logMessage($message) {
    $logFile = __DIR__ . '/add_protocolo_simple_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("=== NOVA REQUISIÇÃO ===");
logMessage("Método HTTP: " . $_SERVER['REQUEST_METHOD']);

try {
    // 1. Incluir arquivo de configuração
    if (!file_exists("../../config.php")) {
        throw new Exception("Arquivo config.php não encontrado");
    }
    
    include_once("../../config.php");
    logMessage("Config.php incluído com sucesso");
    
    // 2. Estabelecer conexão com o banco de dados
    $conn_pacientes = new mysqli($host, $user, $pass, "bd_pacientestto", $port);
    
    if ($conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com o banco bd_pacientestto: " . $conn_pacientes->connect_error);
    }
    logMessage("Conexão com bd_pacientestto estabelecida");
    
    // 3. Obter dados da requisição
    $postRaw = file_get_contents("php://input");
    logMessage("Dados brutos recebidos: " . $postRaw);
    
    $postData = json_decode($postRaw, true);
    
    if (!$postData) {
        throw new Exception("Não foi possível decodificar JSON ou nenhum dado recebido");
    }
    logMessage("Dados JSON decodificados com sucesso");
    
    // 4. Verificar campos obrigatórios
    if (!isset($postData['Protocolo_Nome']) || empty($postData['Protocolo_Nome']) ||
        !isset($postData['Protocolo_Sigla']) || empty($postData['Protocolo_Sigla'])) {
        throw new Exception("Campos obrigatórios não fornecidos (Nome e Sigla)");
    }
    
    // 5. Inserção básica - apenas com os campos obrigatórios
    $sql = "INSERT INTO Protocolo (Protocolo_Nome, Protocolo_Sigla) VALUES (?, ?)";
    $stmt = $conn_pacientes->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn_pacientes->error);
    }
    
    $nome = $postData['Protocolo_Nome'];
    $sigla = $postData['Protocolo_Sigla'];
    
    $stmt->bind_param("ss", $nome, $sigla);
    logMessage("Consulta preparada. Tentando executar com Nome: $nome, Sigla: $sigla");
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar inserção: " . $stmt->error);
    }
    
    $id_protocolo = $conn_pacientes->insert_id;
    logMessage("Inserção bem-sucedida. ID: $id_protocolo");
    
    // 6. Preparar resposta
    $response = [
        'success' => true,
        'message' => 'Protocolo inserido com sucesso',
        'id' => $id_protocolo,
        'id_protocolo' => $id_protocolo,
        'Protocolo_Nome' => $nome,
        'Protocolo_Sigla' => $sigla
    ];
    
    http_response_code(201); // Created
    echo json_encode($response);
    logMessage("Resposta enviada com sucesso: " . json_encode($response));
    
} catch (Exception $e) {
    logMessage("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) {
        $stmt->close();
    }
    if (isset($conn_pacientes)) {
        $conn_pacientes->close();
        logMessage("Conexão fechada");
    }
    logMessage("=== FIM DA REQUISIÇÃO ===\n");
}
?>