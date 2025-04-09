<?php
// get_via_administracao.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");

    // Verifica se a conexão com o banco de dados foi estabelecida
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . ($conn->connect_error ?? "Conexão não estabelecida"));
    }

    $sql = "SELECT idviaadministracao, Via_administracao FROM dViaadministracao ORDER BY Via_administracao";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Erro na consulta: " . $conn->error);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

// Fechar a conexão
if (isset($conn)) {
    $conn->close();
}
?>