<?php
// get_principio_ativo.php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../config.php");

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . ($conn->connect_error ?? "Conexão não estabelecida"));
    }

    $sql = "SELECT idPrincipioAtivo, PrincipioAtivo FROM dPrincipioativo ORDER BY PrincipioAtivo";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Erro na consulta: " . $conn->error);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        // Tratar casos especiais
        if ($row['idPrincipioAtivo'] == 4096) {
            $row['idPrincipioAtivo'] = 5000;
        } else if ($row['idPrincipioAtivo'] == 4097) {
            $row['idPrincipioAtivo'] = 2222;
        } else if ($row['idPrincipioAtivo'] == 4098) {
            $row['idPrincipioAtivo'] = 2223;
        } else if ($row['idPrincipioAtivo'] == 4099) {
            $row['idPrincipioAtivo'] = 4000;
        } else if ($row['idPrincipioAtivo'] == 4100) {
            $row['idPrincipioAtivo'] = 2224;
        }
        
        $data[] = $row;
    }

    echo json_encode($data);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn)) {
    $conn->close();
}
?>