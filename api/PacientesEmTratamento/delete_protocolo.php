<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
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

// Log personalizado para debugging
function logDebug($message) {
    error_log("[DELETE_PROTOCOLO] " . date('Y-m-d H:i:s') . " - " . $message);
}

try {
    logDebug("Iniciando processo de exclusão de protocolo");
    
    include_once("../../config.php");
    
    // Conexão com o banco de dados de pacientes
    $conn_pacientes = new mysqli($host, $user, $pass, "bd_pacientestto", $port);
    
    if ($conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com o banco bd_pacientestto: " . $conn_pacientes->connect_error);
    }
    
    // Verificar se o ID foi fornecido
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("ID do protocolo não fornecido.");
    }
    
    $id_protocolo = intval($_GET['id']);
    logDebug("ID do protocolo a ser excluído: " . $id_protocolo);
    
    // Verificar estrutura da tabela para confirmar o nome da coluna primária
    $checkTableStruct = "SHOW COLUMNS FROM Protocolo";
    $tableStructResult = $conn_pacientes->query($checkTableStruct);
    $primaryKeyField = "id"; // Valor padrão
    
    logDebug("Verificando estrutura da tabela Protocolo...");
    while ($col = $tableStructResult->fetch_assoc()) {
        logDebug("Coluna encontrada: " . $col['Field'] . " - Chave: " . $col['Key']);
        if ($col['Key'] == 'PRI') {
            $primaryKeyField = $col['Field'];
            logDebug("Chave primária identificada: " . $primaryKeyField);
            break;
        }
    }
    
    // Verificar se o protocolo existe usando o nome correto da coluna
    $checkSql = "SELECT * FROM Protocolo WHERE " . $primaryKeyField . " = ?";
    $checkStmt = $conn_pacientes->prepare($checkSql);
    $checkStmt->bind_param("i", $id_protocolo);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        logDebug("Protocolo não encontrado com ID: " . $id_protocolo);
        http_response_code(404);
        echo json_encode(["error" => "Protocolo não encontrado."]);
        exit;
    } else {
        $protocolo = $checkResult->fetch_assoc();
        logDebug("Protocolo encontrado: " . json_encode($protocolo));
    }
    
    $checkStmt->close();
    
    // Iniciar transação
    $conn_pacientes->begin_transaction();
    logDebug("Transação iniciada");
    
    // Verificar todas as tabelas de relacionamento possíveis
    $relationTables = ['Protocolo_Servico', 'Paciente_Protocolo'];
    foreach ($relationTables as $tableName) {
        $checkRelTable = "SHOW TABLES LIKE '{$tableName}'";
        $relTableExists = $conn_pacientes->query($checkRelTable)->num_rows > 0;
        
        if ($relTableExists) {
            logDebug("Tabela de relacionamento encontrada: " . $tableName);
            // Determinar qual nome de coluna usar
            $fkColumnName = "id_protocolo"; // Nome padrão da chave estrangeira
            
            $delRelSql = "DELETE FROM {$tableName} WHERE {$fkColumnName} = ?";
            $delRelStmt = $conn_pacientes->prepare($delRelSql);
            $delRelStmt->bind_param("i", $id_protocolo);
            
            if (!$delRelStmt->execute()) {
                throw new Exception("Erro ao excluir relacionamentos em {$tableName}: " . $delRelStmt->error);
            }
            
            $rowsAffected = $delRelStmt->affected_rows;
            logDebug("Relacionamentos excluídos de {$tableName}: {$rowsAffected} registros");
            $delRelStmt->close();
        } else {
            logDebug("Tabela de relacionamento {$tableName} não existe");
        }
    }
    
    // Excluir o protocolo usando o nome correto da coluna
    $delSql = "DELETE FROM Protocolo WHERE " . $primaryKeyField . " = ?";
    logDebug("SQL de exclusão: " . $delSql . " com ID: " . $id_protocolo);
    
    $delStmt = $conn_pacientes->prepare($delSql);
    $delStmt->bind_param("i", $id_protocolo);
    
    if (!$delStmt->execute()) {
        throw new Exception("Erro ao excluir protocolo: " . $delStmt->error);
    }
    
    $rowsAffected = $delStmt->affected_rows;
    logDebug("Protocolo excluído: " . $rowsAffected . " registros afetados");
    
    if ($rowsAffected === 0) {
        $conn_pacientes->rollback();
        logDebug("Nenhum registro afetado pela exclusão, fazendo rollback");
        http_response_code(404);
        echo json_encode([
            "error" => "Não foi possível excluir o protocolo. Nenhum registro afetado.",
            "id_used" => $id_protocolo,
            "key_field" => $primaryKeyField
        ]);
        exit;
    }
    
    $delStmt->close();
    
    // Commit a transação
    $conn_pacientes->commit();
    logDebug("Transação confirmada com sucesso");
    
    http_response_code(200);
    echo json_encode([
        "message" => "Protocolo excluído com sucesso.",
        "rows_affected" => $rowsAffected,
        "id" => $id_protocolo
    ]);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn_pacientes) && $conn_pacientes->connect_errno === 0) {
        $conn_pacientes->rollback();
        logDebug("Rollback realizado devido a erro");
    }
    
    logDebug("ERRO: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
    error_log("Erro em delete_protocolo.php: " . $e->getMessage());
} finally {
    if (isset($conn_pacientes) && $conn_pacientes->connect_errno === 0) {
        $conn_pacientes->close();
        logDebug("Conexão fechada");
    }
    if (isset($conn) && $conn->connect_errno === 0) {
        $conn->close();
    }
    
    logDebug("Processamento finalizado");
}
?>