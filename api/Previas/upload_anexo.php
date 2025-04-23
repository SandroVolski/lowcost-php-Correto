<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");
    
    // Verificações básicas
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método não permitido");
    }
    
    if (!isset($_POST['previa_id'])) {
        throw new Exception("ID da prévia não fornecido");
    }
    
    $previaId = intval($_POST['previa_id']);
    
    // Verificar se a prévia existe
    $checkStmt = $conn_pacientes->prepare("SELECT id FROM previas WHERE id = ?");
    $checkStmt->bind_param("i", $previaId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception("Prévia não encontrada");
    }
    $checkStmt->close();
    
    // Verificar upload do arquivo
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erro no upload do arquivo: " . ($_FILES['arquivo']['error'] ?? 'Arquivo não enviado'));
    }
    
    // Configurar conexão para dados binários
    $conn_pacientes->set_charset('binary');
    
    // Obter informações do arquivo
    $fileName = $_FILES['arquivo']['name'];
    $fileType = $_FILES['arquivo']['type'];
    $fileSize = formatFileSize($_FILES['arquivo']['size']);
    
    // Ler o conteúdo do arquivo como dados binários
    $fileContent = file_get_contents($_FILES['arquivo']['tmp_name']);
    if ($fileContent === false) {
        throw new Exception("Falha ao ler o conteúdo do arquivo");
    }
    
    // Inserir no banco usando prepared statement com bind_param
    $stmt = $conn_pacientes->prepare(
        "INSERT INTO previa_anexos (previa_id, nome_arquivo, tamanho, tipo, arquivo_conteudo) 
         VALUES (?, ?, ?, ?, ?)"
    );
    
    // Usar método próprio para BLOB
    $stmt->bind_param("issss", $previaId, $fileName, $fileSize, $fileType, $null);
    $stmt->send_long_data(4, $fileContent); // Índice 4 = quinto parâmetro (arquivo_conteudo)
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao inserir no banco: " . $stmt->error);
    }
    
    $anexoId = $conn_pacientes->insert_id;
    $stmt->close();
    
    // Resposta de sucesso
    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode([
        "message" => "Anexo enviado com sucesso",
        "id" => $anexoId,
        "nome_arquivo" => $fileName,
        "tamanho" => $fileSize,
        "tipo" => $fileType,
        "download_url" => "/api/Previas/download_anexo.php?id=" . $anexoId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>