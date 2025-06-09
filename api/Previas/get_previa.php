<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");

    // Verificar parâmetro de ID da prévia
    if (!isset($_GET['id'])) {
        throw new Exception("ID da prévia não fornecido");
    }
    
    $previaId = intval($_GET['id']);
    
    // MODIFICADO: Consulta para buscar detalhes da prévia incluindo os nomes dos usuários
    $sql = "SELECT p.*, 
           uc.nome AS nome_usuario_criacao,
           ua.nome AS nome_usuario_alteracao
    FROM previas p
    LEFT JOIN usuarios uc ON p.usuario_criacao_id = uc.id
    LEFT JOIN usuarios ua ON p.usuario_alteracao_id = ua.id
    WHERE p.id = ?";
    
    $stmt = $conn_pacientes->prepare($sql);
    $stmt->bind_param("i", $previaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Prévia não encontrada"]);
        exit;
    }
    
    $previa = $result->fetch_assoc();
    
    echo json_encode($previa);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>