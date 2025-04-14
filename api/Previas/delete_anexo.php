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
    
    // Buscar informações do anexo antes de excluir
    $sqlFetch = "SELECT caminho_arquivo FROM previa_anexos WHERE id = ?";
    $stmtFetch = $conn->prepare($sqlFetch);
    $stmtFetch->bind_param("i", $anexoId);
    $stmtFetch->execute();
    $resultFetch = $stmtFetch->get_result();
    
    if ($resultFetch->num_rows === 0) {
        throw new Exception("Anexo não encontrado");
    }
    
    $anexo = $resultFetch->fetch_assoc();
    
    // Excluir o arquivo físico
    if (file_exists($anexo['caminho_arquivo'])) {
        if (!unlink($anexo['caminho_arquivo'])) {
            // Logamos o erro, mas continuamos o processo
            error_log("Não foi possível excluir o arquivo: " . $anexo['caminho_arquivo']);
        }
    }
    
    // Excluir o registro no banco de dados
    $sql = "DELETE FROM previa_anexos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $anexoId);
    $stmt->execute();
    
    http_response_code(200);
    echo json_encode(["message" => "Anexo excluído com sucesso"]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>