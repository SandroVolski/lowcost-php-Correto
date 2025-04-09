<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
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
    $logFile = "$logDir/delete_servico_protocolo_log.txt";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    include_once("../../config.php");
    
    logMessage("Iniciando delete_servico_protocolo.php");
    
    // Conexão com o banco de dados
    $conn = new mysqli($host, $user, $pass, "bd_pacientestto", $port);
    
    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }
    
    logMessage("Conexão estabelecida");
    
    // Verificar parâmetros
    if (!isset($_GET['id_protocolo']) || empty($_GET['id_protocolo'])) {
        throw new Exception("ID do protocolo não fornecido.");
    }
    
    if (!isset($_GET['id_servico']) || empty($_GET['id_servico'])) {
        throw new Exception("ID do serviço não fornecido.");
    }
    
    $id_protocolo = intval($_GET['id_protocolo']);
    $id_servico = intval($_GET['id_servico']);
    
    logMessage("Excluindo serviço ID=$id_servico do protocolo ID=$id_protocolo");
    
    // Verificar se a tabela existe
    $tableExists = $conn->query("SHOW TABLES LIKE 'Protocolo_Servico'")->num_rows > 0;
    
    if (!$tableExists) {
        throw new Exception("Tabela Protocolo_Servico não existe.");
    }
    
    // Tentar excluir o registro - primeiro pela chave primária 'id'
    $sql = "DELETE FROM Protocolo_Servico WHERE id = ? AND id_protocolo = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro na preparação da consulta: " . $conn->error);
    }
    
    $stmt->bind_param("ii", $id_servico, $id_protocolo);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        logMessage("Serviço excluído com sucesso");
        
        http_response_code(200);
        echo json_encode([
            "message" => "Serviço removido com sucesso",
            "id_protocolo" => $id_protocolo,
            "id_servico" => $id_servico
        ]);
        exit;
    }
    
    logMessage("Nenhum registro afetado. Verificando outras possibilidades...");
    
    // Se não encontrou pela chave primária, tentar pelo campo id_servico
    $sql = "DELETE FROM Protocolo_Servico WHERE id_servico = ? AND id_protocolo = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id_servico, $id_protocolo);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        logMessage("Serviço excluído com sucesso (via id_servico)");
        
        http_response_code(200);
        echo json_encode([
            "message" => "Serviço removido com sucesso",
            "id_protocolo" => $id_protocolo,
            "id_servico" => $id_servico
        ]);
        exit;
    }
    
    logMessage("Serviço não encontrado para exclusão");
    http_response_code(404);
    echo json_encode(["error" => "Serviço não encontrado"]);
    
} catch (Exception $e) {
    logMessage("Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
    logMessage("Finalizado delete_servico_protocolo.php");
}
?>