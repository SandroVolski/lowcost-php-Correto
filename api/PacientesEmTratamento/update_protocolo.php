<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder às solicitações OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para logging
function logMessage($message) {
    $logDir = "../logs";
    if (!file_exists($logDir) && !is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = "$logDir/update_protocolo_log.txt";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logMessage("============= NOVA REQUISIÇÃO =============");
    logMessage("Iniciando update_protocolo.php");
    
    // Incluir configuração e usar a conexão existente
    include_once("../../config.php");
    
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        logMessage("Erro de conexão com bd_pacientestto");
        throw new Exception("Erro de conexão com banco de dados");
    }
    
    $conn = $conn_pacientes; // Usar a conexão existente
    logMessage("Conexão estabelecida via config.php");
    
    // Receber o ID - aceita tanto id quanto id_protocolo como parâmetros
    $id_param = isset($_GET['id']) ? $_GET['id'] : (isset($_GET['id_protocolo']) ? $_GET['id_protocolo'] : null);
    
    if (!$id_param) {
        logMessage("Parâmetro de ID não fornecido");
        throw new Exception("ID do protocolo não fornecido");
    }
    
    $id_protocolo = intval($id_param);
    logMessage("ID a ser atualizado: $id_protocolo");
    
    // Obter dados
    $rawData = file_get_contents("php://input");
    logMessage("Dados brutos: $rawData");
    
    $data = json_decode($rawData, true);
    if (!$data) {
        logMessage("Dados inválidos ou ausentes");
        throw new Exception("Dados inválidos ou ausentes");
    }
    
    // Determinar o nome da coluna de ID primária
    logMessage("Verificando estrutura da tabela Protocolo...");
    $result = $conn->query("DESCRIBE Protocolo");
    
    if (!$result) {
        logMessage("Erro ao obter estrutura da tabela: " . $conn->error);
        throw new Exception("Erro ao obter estrutura da tabela: " . $conn->error);
    }
    
    $id_column = 'id_protocolo'; // Valor padrão mais provável com base nos erros anteriores
    while ($row = $result->fetch_assoc()) {
        logMessage("Coluna encontrada: {$row['Field']} | Chave: {$row['Key']}");
        if ($row['Key'] === 'PRI') {
            $id_column = $row['Field'];
            logMessage("Coluna ID primária encontrada: $id_column");
            break;
        }
    }
    
    // Verificar se o protocolo existe
    $checkQuery = "SELECT * FROM Protocolo WHERE $id_column = $id_protocolo";
    logMessage("Verificando protocolo: $checkQuery");
    
    $result = $conn->query($checkQuery);
    
    if (!$result) {
        logMessage("Erro ao verificar protocolo: " . $conn->error);
        throw new Exception("Erro ao verificar protocolo: " . $conn->error);
    }
    
    if ($result->num_rows === 0) {
        logMessage("Protocolo não encontrado");
        throw new Exception("Protocolo com ID $id_protocolo não encontrado");
    }
    
    $existingProtocol = $result->fetch_assoc();
    logMessage("Protocolo atual: " . json_encode($existingProtocol));
    
    // Verificar colunas existentes na tabela
    $colunas_result = $conn->query("DESCRIBE Protocolo");
    if (!$colunas_result) {
        throw new Exception("Erro ao verificar colunas: " . $conn->error);
    }
    
    $colunas = [];
    while ($row = $colunas_result->fetch_assoc()) {
        $colunas[] = $row['Field'];
    }
    
    logMessage("Colunas disponíveis: " . implode(", ", $colunas));
    
    // Extrair e converter dados
    $update_fields = [];
    $update_values = [];
    $update_types = "";
    
    // Campos obrigatórios
    if (isset($data['Protocolo_Nome']) && !empty($data['Protocolo_Nome'])) {
        $update_fields[] = "Protocolo_Nome = ?";
        $update_values[] = $data['Protocolo_Nome'];
        $update_types .= "s";
    } else {
        logMessage("Nome do protocolo é obrigatório");
        throw new Exception("Nome do protocolo é obrigatório");
    }
    
    if (isset($data['Protocolo_Sigla']) && !empty($data['Protocolo_Sigla'])) {
        $update_fields[] = "Protocolo_Sigla = ?";
        $update_values[] = $data['Protocolo_Sigla'];
        $update_types .= "s";
    } else {
        logMessage("Sigla do protocolo é obrigatória");
        throw new Exception("Sigla do protocolo é obrigatória");
    }
    
    // Campos opcionais
    if (in_array("Servico_Codigo", $colunas) && isset($data['Servico_Codigo'])) {
        $update_fields[] = "Servico_Codigo = ?";
        $update_values[] = $data['Servico_Codigo'];
        $update_types .= "s";
    }
    
    if (in_array("Protocolo_Dose_M", $colunas) && isset($data['Protocolo_Dose_M'])) {
        $update_fields[] = "Protocolo_Dose_M = ?";
        $update_values[] = $data['Protocolo_Dose_M'];
        $update_types .= "s";
    }
    
    if (in_array("Protocolo_Dose_Total", $colunas) && isset($data['Protocolo_Dose_Total'])) {
        $update_fields[] = "Protocolo_Dose_Total = ?";
        $update_values[] = $data['Protocolo_Dose_Total'];
        $update_types .= "s";
    }
    
    if (in_array("Protocolo_Dias_de_Aplicacao", $colunas) && isset($data['Protocolo_Dias_de_Aplicacao'])) {
        $update_fields[] = "Protocolo_Dias_de_Aplicacao = ?";
        $update_values[] = $data['Protocolo_Dias_de_Aplicacao'];
        $update_types .= "s";
    }
    
    if (in_array("Protocolo_ViaAdm", $colunas) && isset($data['Protocolo_ViaAdm'])) {
        $update_fields[] = "Protocolo_ViaAdm = ?";
        $update_values[] = $data['Protocolo_ViaAdm'];
        $update_types .= "s";
    }
    
    if (in_array("Linha", $colunas) && isset($data['Linha'])) {
        $update_fields[] = "Linha = ?";
        $update_values[] = intval($data['Linha']);
        $update_types .= "i";
    }
    
    // Campos adicionais presentes no novo código
    if (in_array("Intervalo_Ciclos", $colunas) && isset($data['Intervalo_Ciclos'])) {
        $update_fields[] = "Intervalo_Ciclos = ?";
        $update_values[] = intval($data['Intervalo_Ciclos']);
        $update_types .= "i";
    }
    
    if (in_array("Ciclos_Previstos", $colunas) && isset($data['Ciclos_Previstos'])) {
        $update_fields[] = "Ciclos_Previstos = ?";
        $update_values[] = intval($data['Ciclos_Previstos']);
        $update_types .= "i";
    }
    
    if (in_array("CID", $colunas) && isset($data['CID'])) {
        $update_fields[] = "CID = ?";
        $update_values[] = $data['CID'];
        $update_types .= "s";
    }
    
    if (empty($update_fields)) {
        logMessage("Nenhum campo válido para atualização");
        throw new Exception("Nenhum campo válido para atualização");
    }
    
    $updateStr = implode(", ", $update_fields);
    $updateQuery = "UPDATE Protocolo SET $updateStr WHERE $id_column = ?";
    
    // Adicionar o ID do protocolo ao final dos parâmetros
    $update_values[] = $id_protocolo;
    $update_types .= "i";
    
    logMessage("Query: $updateQuery");
    logMessage("Tipos de parâmetros: $update_types");
    
    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        logMessage("Erro ao preparar query: " . $conn->error);
        throw new Exception("Erro ao preparar query: " . $conn->error);
    }
    
    // Bind parameters dinâmico
    $bind_params = array($update_types);
    foreach ($update_values as $key => $value) {
        $bind_params[] = &$update_values[$key];
    }
    
    if (!call_user_func_array(array($stmt, 'bind_param'), $bind_params)) {
        logMessage("Erro no bind_param: " . $stmt->error);
        throw new Exception("Erro no bind_param: " . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        logMessage("Erro na execução: " . $stmt->error);
        throw new Exception("Erro na execução: " . $stmt->error);
    }
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    
    logMessage("Protocolo atualizado. Linhas afetadas: " . $affected_rows);
    
    // Buscar dados atualizados
    $selectQuery = "SELECT * FROM Protocolo WHERE $id_column = $id_protocolo";
    $selectResult = $conn->query($selectQuery);
    
    if (!$selectResult) {
        logMessage("Erro ao buscar dados atualizados: " . $conn->error);
        throw new Exception("Erro ao buscar dados atualizados");
    }
    
    $updatedData = $selectResult->fetch_assoc();
    
    // Responder com sucesso
    http_response_code(200);
    echo json_encode([
        "message" => "Protocolo atualizado com sucesso",
        "data" => $updatedData,
        "affected_rows" => $affected_rows
    ]);
    
    logMessage("Operação concluída com sucesso");
    
} catch (Exception $e) {
    logMessage("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    // Não fechar a conexão, pois foi obtida do config.php
    logMessage("Script finalizado");
}
?>