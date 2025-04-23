<?php
// Desativar buffers e compressão que podem afetar dados binários
ini_set('zlib.output_compression', 'Off');
if (ob_get_level()) ob_end_clean();

// Headers CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");
    
    // Verificar parâmetro
    if (!isset($_GET['id'])) {
        throw new Exception("ID do anexo não fornecido");
    }
    
    $anexoId = intval($_GET['id']);
    
    // Configurar MySQL para transferência binária
    $conn_pacientes->set_charset('binary');
    
    // Buscar anexo
    $stmt = $conn_pacientes->prepare(
        "SELECT nome_arquivo, tipo, arquivo_conteudo FROM previa_anexos WHERE id = ?"
    );
    $stmt->bind_param("i", $anexoId);
    $stmt->execute();
    
    // Vincular resultados
    $stmt->bind_result($nomeArquivo, $tipoArquivo, $conteudoArquivo);
    
    // Obter resultado
    if (!$stmt->fetch()) {
        throw new Exception("Anexo não encontrado");
    }
    
    // Fechar conexões
    $stmt->close();
    $conn_pacientes->close();
    
    // Limpar buffers
    if (ob_get_level()) ob_end_clean();
    
    // Prevenir cache
    header("Expires: 0");
    header("Pragma: no-cache");
    header("Cache-Control: no-store, no-cache, must-revalidate");
    
    // Headers para visualização inline
    header("Content-Type: $tipoArquivo");
    header("Content-Disposition: inline; filename=\"$nomeArquivo\"");
    header("Content-Length: " . strlen($conteudoArquivo));
    header("Content-Transfer-Encoding: binary");
    
    // Saída do conteúdo binário
    echo $conteudoArquivo;
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => $e->getMessage()]);
}
?>