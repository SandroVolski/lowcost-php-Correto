<?php
// Configurações CORS para permitir todos os métodos
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Este é apenas um arquivo de teste para confirmar se o CORS está funcionando
echo json_encode([
    "message" => "UPDATE endpoint funcionando corretamente!",
    "method" => $_SERVER['REQUEST_METHOD'],
    "received_data" => file_get_contents("php://input")
]);
?>