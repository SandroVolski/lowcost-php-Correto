<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");

    // Verificar parâmetro de ID do anexo
    if (!isset($_GET['id'])) {
        throw new Exception("ID do anexo não fornecido");
    }
    
    $anexoId = intval($_GET['id']);
    
    // Consulta para buscar informações do anexo
    $sql = "SELECT nome_arquivo, tipo, caminho_arquivo FROM previa_anexos WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $anexoId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Anexo não encontrado");
    }
    
    $anexo = $result->fetch_assoc();
    
    // Verificar se o arquivo existe
    if (!file_exists($anexo['caminho_arquivo'])) {
        throw new Exception("Arquivo físico não encontrado");
    }
    
    // Enviar o arquivo para download
    header('Content-Type: ' . $anexo['tipo']);
    header('Content-Disposition: attachment; filename="' . $anexo['nome_arquivo'] . '"');
    header('Content-Length: ' . filesize($anexo['caminho_arquivo']));
    
    // Ler e enviar o arquivo
    readfile($anexo['caminho_arquivo']);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>