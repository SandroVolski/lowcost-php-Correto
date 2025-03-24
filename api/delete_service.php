<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once("../config.php");

// Verifica se o ID foi fornecido
if (!isset($_GET['id'])) {
    http_response_code(400); // Bad Request
    echo json_encode(["message" => "ID não fornecido."]);
    exit;
}

$id = intval($_GET['id']); // Converte o ID para inteiro

// Verifica se o ID é válido
if ($id <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(["message" => "ID inválido."]);
    exit;
}

// Prepara a query de exclusão
$sql = "DELETE FROM dServicoRelacionada WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

// Executa a query
if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(["message" => "Serviço deletado com sucesso."]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Erro ao deletar serviço."]);
}

$stmt->close();
$conn->close();
?>