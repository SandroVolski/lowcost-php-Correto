<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once("../../config.php");

try {
    // Verificar conexão com o banco
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados de pacientes.");
    }
    
    // Verificar parâmetro
    if (!isset($_GET['protocolo']) || empty($_GET['protocolo'])) {
        throw new Exception("Nome do protocolo não fornecido.");
    }
    
    $protocolo = $conn_pacientes->real_escape_string($_GET['protocolo']);
    
    // Consultar o banco de dados
    $sql = "SELECT id_protocolo FROM Protocolo WHERE Protocolo_Nome = ? OR Protocolo_Sigla = ?";
    $stmt = $conn_pacientes->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn_pacientes->error);
    }
    
    $stmt->bind_param("ss", $protocolo, $protocolo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Verificar se o protocolo existe
    $exists = $result->num_rows > 0;
    
    // Construir resposta
    $response = [
        "exists" => $exists
    ];
    
    if ($exists) {
        $row = $result->fetch_assoc();
        $response["id"] = $row["id_protocolo"];
    }
    
    // Retornar resposta
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}

// Fechar conexão
if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>