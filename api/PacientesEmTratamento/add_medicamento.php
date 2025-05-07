<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para logging
function logMessage($message) {
    $logFile = __DIR__ . '/med_debug.txt';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logMessage("==== NOVA REQUISIÇÃO MEDICAMENTO ====");
    
    // Carregar configuração
    include_once("../../config.php");
    
    // Verificar conexão
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com banco bd_pacientestto");
    }
    
    // Receber dados
    $rawData = file_get_contents("php://input");
    logMessage("Dados recebidos: " . $rawData);
    
    $data = json_decode($rawData, true);
    if (!$data) {
        throw new Exception("Dados JSON inválidos");
    }
    
    // Verificar ID do protocolo
    if (!isset($data['id_protocolo']) || empty($data['id_protocolo'])) {
        throw new Exception("ID do protocolo é obrigatório");
    }
    
    $id_protocolo = intval($data['id_protocolo']);
    
    // Verificar se o protocolo existe
    $check_protocolo = $conn_pacientes->query("SELECT id_protocolo FROM Protocolo WHERE id_protocolo = $id_protocolo");
    if ($check_protocolo->num_rows == 0) {
        throw new Exception("Protocolo não encontrado com ID: $id_protocolo");
    }
    
    // Verificar medicamento
    if (!isset($data['medicamento']) || !is_array($data['medicamento'])) {
        throw new Exception("Dados do medicamento são obrigatórios");
    }
    
    $med = $data['medicamento'];
    
    // Verificar coluna dias_aplicacao
    $check_column = $conn_pacientes->query("SHOW COLUMNS FROM Protocolo_Servico LIKE 'dias_aplicacao'");
    if ($check_column && $check_column->num_rows > 0) {
        $column_info = $check_column->fetch_assoc();
        logMessage("Tipo atual da coluna dias_aplicacao: " . $column_info['Type']);
        
        // Se não for VARCHAR, alterar
        if (strpos(strtoupper($column_info['Type']), 'VARCHAR') === false && 
            strpos(strtoupper($column_info['Type']), 'TEXT') === false) {
            logMessage("Alterando tipo da coluna dias_aplicacao para VARCHAR(100)");
            if (!$conn_pacientes->query("ALTER TABLE Protocolo_Servico MODIFY COLUMN dias_aplicacao VARCHAR(100)")) {
                logMessage("ERRO ao alterar coluna: " . $conn_pacientes->error);
            }
        }
    }
    
    // Inserir medicamento - Abordagem direta com query SQL
    $nome = isset($med['nome']) ? $conn_pacientes->real_escape_string($med['nome']) : '';
    $dose = isset($med['dose']) && $med['dose'] !== '' ? $med['dose'] : 'NULL';
    $unidade_medida = isset($med['unidade_medida']) && $med['unidade_medida'] !== '' ? $med['unidade_medida'] : 'NULL';
    $via_adm = isset($med['via_adm']) && $med['via_adm'] !== '' ? $med['via_adm'] : 'NULL';
    $dias_adm = isset($med['dias_adm']) && $med['dias_adm'] !== '' ? "'" . $conn_pacientes->real_escape_string($med['dias_adm']) . "'" : 'NULL';
    $frequencia = isset($med['frequencia']) && $med['frequencia'] !== '' ? "'" . $conn_pacientes->real_escape_string($med['frequencia']) . "'" : 'NULL';
    
    // Construir SQL com valores diretamente na query
    $sql = "INSERT INTO Protocolo_Servico (id_protocolo, nome, dose, unidade_medida, via_administracao, dias_aplicacao, frequencia) 
           VALUES ($id_protocolo, '$nome', $dose, $unidade_medida, $via_adm, $dias_adm, $frequencia)";
    
    logMessage("SQL: $sql");
    
    if (!$conn_pacientes->query($sql)) {
        throw new Exception("Erro ao inserir medicamento: " . $conn_pacientes->error);
    }
    
    $med_id = $conn_pacientes->insert_id;
    logMessage("Medicamento inserido com sucesso. ID: $med_id");
    
    // Resposta
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $med_id,
        'message' => 'Medicamento adicionado com sucesso'
    ]);
    
} catch (Exception $e) {
    logMessage("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    logMessage("==== FIM DA REQUISIÇÃO ====");
}
?>