<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar log de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para logging
function logMessage($message) {
    $logDir = "../logs";
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = "$logDir/update_servico_log.txt";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    include_once("../../config.php");
    
    logMessage("============= NOVA REQUISIÇÃO =============");
    logMessage("Iniciando update_servico_protocolo.php");
    
    // Log dos dados recebidos
    $rawData = file_get_contents("php://input");
    logMessage("Dados brutos recebidos: " . $rawData);
    
    // Conexão com o banco de dados - usando conexão do config.php 
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com banco bd_pacientestto");
    }
    $conn = $conn_pacientes;
    
    logMessage("Conexão estabelecida");
    
    // Verificar ID do protocolo
    if (!isset($_GET['id_protocolo']) || empty($_GET['id_protocolo'])) {
        throw new Exception("ID do protocolo não fornecido.");
    }
    
    // Verificar ID do serviço
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("ID do serviço não fornecido.");
    }
    
    $id_protocolo = intval($_GET['id_protocolo']);
    $id_servico = intval($_GET['id']);
    
    logMessage("ID do protocolo: $id_protocolo, ID do serviço: $id_servico");
    
    // IMPORTANTE: Descobrir a coluna ID da tabela Protocolo
    logMessage("Verificando estrutura da tabela Protocolo...");
    $protocoloStructure = $conn->query("DESCRIBE Protocolo");
    $protocolo_pk_column = "id_protocolo"; // Valor padrão, que sabemos que está correto
    
    if ($protocoloStructure) {
        while ($row = $protocoloStructure->fetch_assoc()) {
            if ($row['Key'] == 'PRI') {
                $protocolo_pk_column = $row['Field'];
                logMessage("Coluna de chave primária na tabela Protocolo: " . $protocolo_pk_column);
                break;
            }
        }
    }
    
    // Verificar a estrutura da tabela Protocolo_Servico para identificar a coluna correta do ID do protocolo
    logMessage("Verificando estrutura da tabela Protocolo_Servico");
    $tableStructure = $conn->query("DESCRIBE Protocolo_Servico");
    
    if (!$tableStructure) {
        logMessage("Erro ao verificar estrutura da tabela: " . $conn->error);
        throw new Exception("Erro ao verificar estrutura da tabela: " . $conn->error);
    }
    
    $existingColumns = [];
    $protocolo_id_column = null; // Coluna que armazena o ID do protocolo
    $foreign_keys = [];
    
    while ($column = $tableStructure->fetch_assoc()) {
        $columnName = $column['Field'];
        $existingColumns[] = strtolower($columnName);
        
        // Procurar possíveis nomes para a coluna do ID do protocolo
        if (strpos(strtolower($columnName), 'protocolo') !== false || 
            strpos(strtolower($columnName), 'protocol') !== false) {
            $foreign_keys[] = $columnName;
        }
    }
    
    logMessage("Colunas existentes: " . implode(", ", $existingColumns));
    logMessage("Possíveis colunas de protocolo: " . implode(", ", $foreign_keys));
    
    // Verificar FOREIGN KEYS para identificar a coluna que referencia a tabela Protocolo
    $fkQuery = "
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'bd_pacientestto' 
        AND TABLE_NAME = 'Protocolo_Servico' 
        AND REFERENCED_TABLE_NAME = 'Protocolo'
    ";
    
    $fkResult = $conn->query($fkQuery);
    
    if ($fkResult && $fkResult->num_rows > 0) {
        $fkRow = $fkResult->fetch_assoc();
        $protocolo_id_column = $fkRow['COLUMN_NAME'];
        logMessage("Coluna FK encontrada via INFORMATION_SCHEMA: $protocolo_id_column");
    } else {
        // Se não encontrou pela FK, tenta deduzir por convenções de nomenclatura
        if (in_array('id_protocolo', $existingColumns)) {
            $protocolo_id_column = 'id_protocolo';
        } elseif (in_array('protocolo_id', $existingColumns)) {
            $protocolo_id_column = 'protocolo_id';
        } elseif (in_array('protocolo', $existingColumns)) {
            $protocolo_id_column = 'protocolo';
        } elseif (!empty($foreign_keys)) {
            $protocolo_id_column = $foreign_keys[0]; // Pega o primeiro encontrado
        } else {
            logMessage("ERRO: Não foi possível identificar a coluna para o ID do protocolo");
            throw new Exception("Não foi possível identificar a coluna para o ID do protocolo");
        }
        
        logMessage("Coluna para ID do protocolo deduzida: $protocolo_id_column");
    }
    
    // Verificar se o protocolo existe usando a coluna correta
    $checkProtocolQuery = "SELECT $protocolo_pk_column FROM Protocolo WHERE $protocolo_pk_column = $id_protocolo";
    logMessage("Verificando existência do protocolo: $checkProtocolQuery");
    $checkProtocol = $conn->query($checkProtocolQuery);
    
    if (!$checkProtocol || $checkProtocol->num_rows === 0) {
        logMessage("Erro: Protocolo $id_protocolo não encontrado");
        throw new Exception("Protocolo não encontrado");
    }
    
    // Verificar se o serviço existe e obter detalhes sobre o registro
    $checkQuery = "SELECT * FROM Protocolo_Servico WHERE id = $id_servico";
    logMessage("Executando consulta: $checkQuery");
    $checkServico = $conn->query($checkQuery);
    
    if (!$checkServico || $checkServico->num_rows === 0) {
        logMessage("Erro: Serviço $id_servico não encontrado");
        throw new Exception("Serviço não encontrado");
    } else {
        $servicoAtual = $checkServico->fetch_assoc();
        logMessage("Serviço encontrado: " . json_encode($servicoAtual));
    }
    
    // Interpretar dados do PUT
    $data = json_decode($rawData, true);
    
    if (!$data) {
        throw new Exception("Dados inválidos ou ausentes");
    }
    
    // Verificar se a tabela tem todas as colunas necessárias
    $columnsToCheck = [
        "Servico_Codigo" => "VARCHAR(100)",
        "Dose" => "VARCHAR(100)",
        "Dose_M" => "VARCHAR(100)",
        "Dose_Total" => "VARCHAR(100)",
        "Dias_de_Aplic" => "VARCHAR(100)",
        "Via_de_Adm" => "VARCHAR(50)",
        "observacoes" => "TEXT"
    ];
    
    foreach ($columnsToCheck as $column => $type) {
        // Verificar se a coluna já existe (ignorando maiúsculas/minúsculas)
        if (!in_array(strtolower($column), $existingColumns)) {
            logMessage("Adicionando coluna $column à tabela");
            $addColumn = "ALTER TABLE Protocolo_Servico ADD COLUMN $column $type";
            try {
                if (!$conn->query($addColumn)) {
                    logMessage("Aviso: Erro ao adicionar coluna $column: " . $conn->error);
                }
            } catch (Exception $e) {
                // Ignorar erros de coluna duplicada, mas registrar outros erros
                if (strpos($e->getMessage(), "Duplicate column") === false) {
                    logMessage("Erro ao adicionar coluna $column: " . $e->getMessage());
                } else {
                    logMessage("Coluna $column já existe. Ignorando.");
                }
            }
        } else {
            logMessage("Coluna $column já existe. Ignorando.");
        }
    }
    
    // Preparar dados para atualização com tratamento mais seguro (todos como strings)
    $servico_codigo = isset($data['Servico_Codigo']) ? $conn->real_escape_string($data['Servico_Codigo']) : '';
    $dose = isset($data['Dose']) && $data['Dose'] !== '' ? $conn->real_escape_string($data['Dose']) : null;
    $dose_m = isset($data['Dose_M']) && $data['Dose_M'] !== '' ? $conn->real_escape_string($data['Dose_M']) : null;
    $dose_total = isset($data['Dose_Total']) && $data['Dose_Total'] !== '' ? $conn->real_escape_string($data['Dose_Total']) : null;
    $dias_aplic = isset($data['Dias_de_Aplic']) && $data['Dias_de_Aplic'] !== '' ? $conn->real_escape_string($data['Dias_de_Aplic']) : null;
    $via_adm = isset($data['Via_de_Adm']) ? $conn->real_escape_string($data['Via_de_Adm']) : '';
    $observacoes = isset($data['observacoes']) ? $conn->real_escape_string($data['observacoes']) : '';
    
    // Log dos valores que serão usados na atualização
    logMessage("Valores para atualização:");
    logMessage("Servico_Codigo: $servico_codigo");
    logMessage("Dose: " . ($dose === null ? "NULL" : $dose));
    logMessage("Dose_M: " . ($dose_m === null ? "NULL" : $dose_m));
    logMessage("Dose_Total: " . ($dose_total === null ? "NULL" : $dose_total));
    logMessage("Dias_de_Aplic: " . ($dias_aplic === null ? "NULL" : $dias_aplic));
    logMessage("Via_de_Adm: $via_adm");
    logMessage("observacoes: $observacoes");
    
    // Construir query SQL explícita com tratamento adequado para campos NULL
    $updateFields = [];
    
    if ($servico_codigo !== '') {
        $updateFields[] = "Servico_Codigo = '$servico_codigo'";
    }
    
    if ($dose !== null) {
        $updateFields[] = "Dose = '$dose'";
    } else if (isset($data['Dose']) && $data['Dose'] === '') {
        $updateFields[] = "Dose = NULL";
    }
    
    if ($dose_m !== null) {
        $updateFields[] = "Dose_M = '$dose_m'";
    } else if (isset($data['Dose_M']) && $data['Dose_M'] === '') {
        $updateFields[] = "Dose_M = NULL";
    }
    
    if ($dose_total !== null) {
        $updateFields[] = "Dose_Total = '$dose_total'";
    } else if (isset($data['Dose_Total']) && $data['Dose_Total'] === '') {
        $updateFields[] = "Dose_Total = NULL";
    }
    
    if ($dias_aplic !== null) {
        $updateFields[] = "Dias_de_Aplic = '$dias_aplic'";
    } else if (isset($data['Dias_de_Aplic']) && $data['Dias_de_Aplic'] === '') {
        $updateFields[] = "Dias_de_Aplic = NULL";
    }
    
    if ($via_adm !== '') {
        $updateFields[] = "Via_de_Adm = '$via_adm'";
    } else if (isset($data['Via_de_Adm']) && $data['Via_de_Adm'] === '') {
        $updateFields[] = "Via_de_Adm = NULL";
    }
    
    if ($observacoes !== '') {
        $updateFields[] = "observacoes = '$observacoes'";
    } else if (isset($data['observacoes']) && $data['observacoes'] === '') {
        $updateFields[] = "observacoes = NULL";
    }
    
    // Verificar se há campos para atualizar
    if (empty($updateFields)) {
        logMessage("Aviso: Nenhum campo válido para atualização");
        http_response_code(400);
        echo json_encode(["error" => "Nenhum campo válido para atualização"]);
        exit;
    }
    
    $updateFieldsStr = implode(", ", $updateFields);
    
    // Usar o nome correto da coluna do ID do protocolo na cláusula WHERE
    $debugQuery = "UPDATE Protocolo_Servico SET $updateFieldsStr WHERE id = $id_servico";
    
    // Adicionar a condição com o ID do protocolo apenas se a coluna existir
    if ($protocolo_id_column) {
        $debugQuery .= " AND $protocolo_id_column = $id_protocolo";
    }
    
    logMessage("Query de atualização: " . $debugQuery);
    
    // Usar query direta para evitar problemas com bind_param e valores NULL
    $updateResult = $conn->query($debugQuery);
    
    if (!$updateResult) {
        logMessage("Erro na execução da query: " . $conn->error);
        throw new Exception("Erro ao atualizar serviço: " . $conn->error);
    }
    
    if ($conn->affected_rows > 0) {
        logMessage("Serviço atualizado com sucesso. Linhas afetadas: " . $conn->affected_rows);
    } else {
        logMessage("Aviso: Nenhuma linha afetada. Valores idênticos ou erro silencioso.");
    }
    
    // Buscar dados atualizados
    $query = "SELECT * FROM Protocolo_Servico WHERE id = $id_servico";
    $result = $conn->query($query);
    
    if (!$result) {
        logMessage("Erro ao buscar dados atualizados: " . $conn->error);
        throw new Exception("Erro ao buscar dados atualizados: " . $conn->error);
    }
    
    $updatedData = $result->fetch_assoc();
    logMessage("Dados atualizados: " . json_encode($updatedData));
    
    http_response_code(200);
    echo json_encode([
        "message" => "Serviço atualizado com sucesso",
        "data" => $updatedData,
        "affected_rows" => $conn->affected_rows,
        "protocol_column_used" => $protocolo_id_column
    ]);
    
} catch (Exception $e) {
    logMessage("Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
    logMessage("Finalizado update_servico_protocolo.php");
}
?>