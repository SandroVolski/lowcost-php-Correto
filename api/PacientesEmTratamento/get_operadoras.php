<?php
// Configurações de cabeçalho para permitir CORS e definir o tipo de resposta
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar relatório de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Incluir arquivo de configuração - caminho atualizado
    include_once("../../config.php");

    // Verifica se a conexão com o banco de dados foi estabelecida
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados.");
    }

    // Query para buscar as operadoras
    $sql = "
    SELECT 
        id, 
        Nome_Fantasia as nome
    FROM 
        bd_servico.bd_producaom_operadoras
    ORDER BY 
        Nome_Fantasia
    ";

    // Executar a consulta
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Erro ao buscar operadoras: " . $conn->error);
    }

    // Coletar os resultados
    $operadoras = [];
    while ($row = $result->fetch_assoc()) {
        $operadoras[] = $row;
    }

    http_response_code(200);
    echo json_encode($operadoras);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao buscar operadoras", 
        "error" => $e->getMessage()
    ]);
    error_log("Erro na API get_operadoras.php: " . $e->getMessage());
}

// Fechar conexão
if (isset($conn)) {
    $conn->close();
}
?>