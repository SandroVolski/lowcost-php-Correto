<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");

    // Verificar parâmetro de ID
    if (!isset($_GET['id'])) {
        throw new Exception("ID do anexo não fornecido");
    }
    
    $anexoId = intval($_GET['id']);
    
    // Verificar se o anexo existe
    $sqlCheck = "SELECT id FROM previa_anexos WHERE id = ?";
    $stmtCheck = $conn_pacientes->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $anexoId);
    $stmtCheck->execute();
    $resultCheck = $stmtCheck->get_result();
    
    if ($resultCheck->num_rows === 0) {
        throw new Exception("Anexo não encontrado");
    }
    
    // Excluir o registro no banco de dados (o conteúdo do arquivo será excluído junto)
    $sql = "DELETE FROM previa_anexos WHERE id = ?";
    $stmt = $conn_pacientes->prepare($sql);
    $stmt->bind_param("i", $anexoId);
    $stmt->execute();
    
    http_response_code(200);
    echo json_encode(["message" => "Anexo excluído com sucesso"]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>