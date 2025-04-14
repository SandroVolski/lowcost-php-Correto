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
    if (!isset($_GET['previa_id'])) {
        throw new Exception("ID da prévia não fornecido");
    }
    
    $previaId = intval($_GET['previa_id']);
    
    // Consulta para buscar anexos da prévia
    $sql = "SELECT id, previa_id, nome_arquivo, tamanho, tipo, data_upload FROM previa_anexos WHERE previa_id = ? ORDER BY data_upload DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $previaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $anexos = [];
    while ($row = $result->fetch_assoc()) {
        // Adicionar URLs para download e preview
        $row['download_url'] = "/api/previas/download_anexo.php?id=" . $row['id'];
        $anexos[] = $row;
    }
    
    echo json_encode($anexos);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>