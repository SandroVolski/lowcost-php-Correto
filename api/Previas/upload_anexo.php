<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");

    // Verificar se é um POST com upload de arquivo
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Método não permitido");
    }
    
    // Verificar se o ID da prévia foi fornecido
    if (!isset($_POST['previa_id'])) {
        throw new Exception("ID da prévia não fornecido");
    }
    
    $previaId = intval($_POST['previa_id']);
    
    // Verificar se a prévia existe
    $checkQuery = "SELECT id FROM previas WHERE id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $previaId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        throw new Exception("Prévia não encontrada");
    }
    
    // Verificar se foi enviado um arquivo
    if (!isset($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Erro no upload do arquivo: " . ($_FILES['arquivo']['error'] ?? 'Arquivo não enviado'));
    }
    
    // Criar diretório para armazenar os anexos se não existir
    $uploadDir = '../../uploads/previas/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Gerar nome único para o arquivo
    $fileName = $_FILES['arquivo']['name'];
    $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
    $uniqueName = uniqid('previa_' . $previaId . '_') . '.' . $fileExt;
    $targetFilePath = $uploadDir . $uniqueName;
    
    // Mover o arquivo para o diretório de uploads
    if (!move_uploaded_file($_FILES['arquivo']['tmp_name'], $targetFilePath)) {
        throw new Exception("Falha ao mover o arquivo enviado");
    }
    
    // Registrar o anexo no banco de dados
    $sql = "INSERT INTO previa_anexos (
        previa_id, 
        nome_arquivo, 
        tamanho, 
        tipo, 
        caminho_arquivo
    ) VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    // Formatação do tamanho do arquivo
    $fileSize = formatFileSize($_FILES['arquivo']['size']);
    
    $stmt->bind_param(
        "issss",
        $previaId,
        $fileName,
        $fileSize,
        $_FILES['arquivo']['type'],
        $targetFilePath
    );
    
    $stmt->execute();
    $anexoId = $conn->insert_id;
    
    // Função para formatar o tamanho do arquivo
    function formatFileSize($bytes) {
        if ($bytes === 0) return '0 Bytes';
        
        $k = 1024;
        $sizes = ['Bytes', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    http_response_code(201);
    echo json_encode([
        "message" => "Anexo enviado com sucesso",
        "id" => $anexoId,
        "nome_arquivo" => $fileName,
        "tamanho" => $fileSize,
        "tipo" => $_FILES['arquivo']['type']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>