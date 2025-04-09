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

    // Definir finalidades de tratamento pré-definidas
    // Você pode adaptar isso para buscar de uma tabela no banco de dados, se disponível
    $finalidades = [
        ["id" => 1, "nome" => "Adjuvante"],
        ["id" => 2, "nome" => "Neoadjuvante"],
        ["id" => 3, "nome" => "Paliativa"],
        ["id" => 4, "nome" => "Curativa"],
        ["id" => 5, "nome" => "Manutenção"],
        ["id" => 6, "nome" => "Não se aplica"]
    ];

    http_response_code(200);
    echo json_encode($finalidades);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao buscar finalidades de tratamento", 
        "error" => $e->getMessage()
    ]);
    error_log("Erro na API get_finalidades_tratamento.php: " . $e->getMessage());
}
?>