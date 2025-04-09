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
    
    // Verificar se a tabela tem coluna CID
    $hasColumn = function ($tableName, $columnName) use ($conn) {
        $query = "SHOW COLUMNS FROM $tableName LIKE '$columnName'";
        $result = $conn->query($query);
        return $result && $result->num_rows > 0;
    };
    
    $hasCidColumn = $hasColumn('Protocolo', 'CID');
    logMessage("Tabela tem coluna CID? " . ($hasCidColumn ? "Sim" : "Não"));
    
    // Extrair e converter dados
    $protocolo_nome = isset($data['Protocolo_Nome']) ? $conn->real_escape_string($data['Protocolo_Nome']) : null;
    $protocolo_sigla = isset($data['Protocolo_Sigla']) ? $conn->real_escape_string($data['Protocolo_Sigla']) : null;
    $servico_codigo = isset($data['Servico_Codigo']) ? $conn->real_escape_string($data['Servico_Codigo']) : null;
    $protocolo_dose_m = isset($data['Protocolo_Dose_M']) ? $conn->real_escape_string($data['Protocolo_Dose_M']) : null;
    $protocolo_dose_total = isset($data['Protocolo_Dose_Total']) ? $conn->real_escape_string($data['Protocolo_Dose_Total']) : null;
    $protocolo_dias_aplicacao = isset($data['Protocolo_Dias_de_Aplicacao']) ? $conn->real_escape_string($data['Protocolo_Dias_de_Aplicacao']) : null;
    $protocolo_viaadm = isset($data['Protocolo_ViaAdm']) ? $conn->real_escape_string($data['Protocolo_ViaAdm']) : null;
    $linha = isset($data['Linha']) ? $conn->real_escape_string($data['Linha']) : null;
    $cid = $hasCidColumn && isset($data['CID']) ? $conn->real_escape_string($data['CID']) : null;
    
    // Validar campos obrigatórios
    if (empty($protocolo_nome) || empty($protocolo_sigla)) {
        logMessage("Campos obrigatórios faltando");
        throw new Exception("Nome e Sigla são campos obrigatórios");
    }
    
    // Construir query de atualização
    $updateParts = [];
    
    if ($protocolo_nome !== null) {
        $updateParts[] = "Protocolo_Nome = '$protocolo_nome'";
    }
    
    if ($protocolo_sigla !== null) {
        $updateParts[] = "Protocolo_Sigla = '$protocolo_sigla'";
    }
    
    if ($servico_codigo !== null) {
        $updateParts[] = "Servico_Codigo = '$servico_codigo'";
    }
    
    if ($protocolo_dose_m !== null) {
        $updateParts[] = "Protocolo_Dose_M = '$protocolo_dose_m'";
    } else if (isset($data['Protocolo_Dose_M'])) {
        $updateParts[] = "Protocolo_Dose_M = NULL";
    }
    
    if ($protocolo_dose_total !== null) {
        $updateParts[] = "Protocolo_Dose_Total = '$protocolo_dose_total'";
    } else if (isset($data['Protocolo_Dose_Total'])) {
        $updateParts[] = "Protocolo_Dose_Total = NULL";
    }
    
    if ($protocolo_dias_aplicacao !== null) {
        $updateParts[] = "Protocolo_Dias_de_Aplicacao = '$protocolo_dias_aplicacao'";
    } else if (isset($data['Protocolo_Dias_de_Aplicacao'])) {
        $updateParts[] = "Protocolo_Dias_de_Aplicacao = NULL";
    }
    
    if ($protocolo_viaadm !== null) {
        $updateParts[] = "Protocolo_ViaAdm = '$protocolo_viaadm'";
    } else if (isset($data['Protocolo_ViaAdm'])) {
        $updateParts[] = "Protocolo_ViaAdm = NULL";
    }
    
    if ($linha !== null) {
        $updateParts[] = "Linha = '$linha'";
    } else if (isset($data['Linha'])) {
        $updateParts[] = "Linha = NULL";
    }
    
    if ($hasCidColumn && $cid !== null) {
        $updateParts[] = "CID = '$cid'";
    } else if ($hasCidColumn && isset($data['CID'])) {
        $updateParts[] = "CID = NULL";
    }
    
    if (empty($updateParts)) {
        logMessage("Nenhum campo válido para atualização");
        throw new Exception("Nenhum campo válido para atualização");
    }
    
    $updateStr = implode(", ", $updateParts);
    $updateQuery = "UPDATE Protocolo SET $updateStr WHERE $id_column = $id_protocolo";
    
    logMessage("Query: $updateQuery");
    
    $updateResult = $conn->query($updateQuery);
    
    if (!$updateResult) {
        logMessage("Erro na atualização: " . $conn->error);
        throw new Exception("Erro ao atualizar protocolo: " . $conn->error);
    }
    
    logMessage("Protocolo atualizado. Linhas afetadas: " . $conn->affected_rows);
    
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
        "affected_rows" => $conn->affected_rows
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