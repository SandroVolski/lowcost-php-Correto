<?php
// Nome do arquivo: update_servico_protocolo.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, POST, PATCH, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Log diretamente na raiz do diretório do arquivo
function debugLog($message) {
    $logFile = __DIR__ . "/super_debug_servico.txt";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

debugLog("********************* ARQUIVO ACESSADO *********************");
debugLog("MÉTODO: " . $_SERVER['REQUEST_METHOD']);
debugLog("URI: " . $_SERVER['REQUEST_URI']);
debugLog("GET: " . json_encode($_GET));

// Responder às OPTIONS imediatamente
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $rawData = file_get_contents("php://input");
    debugLog("DADOS RECEBIDOS: " . $rawData);
    
    include_once("../../config.php");
    debugLog("CONFIG INCLUÍDO");
    
    if (!isset($conn_pacientes)) {
        throw new Exception("Erro: conexão não disponível após inclusão do config.php");
    }
    
    // Pegar ID do serviço e protocolo
    $id_servico = isset($_GET['id']) ? intval($_GET['id']) : null;
    $id_protocolo = isset($_GET['id_protocolo']) ? intval($_GET['id_protocolo']) : null;
    
    if (!$id_servico) {
        throw new Exception("ID do serviço não fornecido");
    }
    
    debugLog("ID SERVIÇO: $id_servico | ID PROTOCOLO: $id_protocolo");
    
    // Verificar existência do registro
    $check = $conn_pacientes->query("SELECT * FROM Protocolo_Servico WHERE id = $id_servico");
    if (!$check || $check->num_rows === 0) {
        throw new Exception("Serviço não encontrado");
    }
    
    $data = json_decode($rawData, true);
    if (!$data) {
        throw new Exception("Dados inválidos");
    }
    
    // Campos para atualização (simplificados)
    $updates = [];
    
    if (isset($data['nome'])) {
        $nome = $conn_pacientes->real_escape_string($data['nome']);
        $updates[] = "nome = '$nome'";
    }
    
    if (isset($data['dose'])) {
        $dose = $data['dose'] !== '' ? $data['dose'] : 'NULL';
        $updates[] = "dose = $dose";
    }
    
    if (isset($data['unidade_medida'])) {
        $unidade = $data['unidade_medida'] !== '' ? 
            "'" . $conn_pacientes->real_escape_string($data['unidade_medida']) . "'" : 'NULL';
        $updates[] = "unidade_medida = $unidade";
    }
    
    if (isset($data['via_administracao']) || isset($data['via_adm'])) {
        $via = isset($data['via_administracao']) ? $data['via_administracao'] : $data['via_adm'];
        $via = $via !== '' ? $via : 'NULL';
        $updates[] = "via_administracao = $via";
    }
    
    if (isset($data['dias_aplicacao']) || isset($data['dias_adm'])) {
        $dias = isset($data['dias_aplicacao']) ? $data['dias_aplicacao'] : $data['dias_adm'];
        $dias = $dias !== '' ? "'" . $conn_pacientes->real_escape_string($dias) . "'" : 'NULL';
        $updates[] = "dias_aplicacao = $dias";
    }
    
    if (isset($data['frequencia'])) {
        $frequencia = $data['frequencia'] !== '' ? "'" . $conn_pacientes->real_escape_string($data['frequencia']) . "'" : 'NULL';
        $updates[] = "frequencia = $frequencia";
    }
    
    // Se não houver campos para atualizar
    if (empty($updates)) {
        throw new Exception("Nenhum campo válido para atualização");
    }
    
    // Query sem verificação de protocolo para simplificar
    $sql = "UPDATE Protocolo_Servico SET " . implode(", ", $updates) . " WHERE id = $id_servico";
    debugLog("SQL: $sql");
    
    $result = $conn_pacientes->query($sql);
    if (!$result) {
        throw new Exception("Erro ao executar SQL: " . $conn_pacientes->error);
    }
    
    $affected = $conn_pacientes->affected_rows;
    debugLog("LINHAS AFETADAS: $affected");
    
    // Buscar dados após atualização
    $new_data = $conn_pacientes->query("SELECT * FROM Protocolo_Servico WHERE id = $id_servico");
    $updated = $new_data->fetch_assoc();
    
    // Resposta de sucesso
    echo json_encode([
        'success' => true,
        'message' => 'Serviço atualizado com sucesso',
        'data' => $updated,
        'affected_rows' => $affected
    ]);
    
    debugLog("RESPOSTA ENVIADA COM SUCESSO");
    
} catch (Exception $e) {
    debugLog("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>