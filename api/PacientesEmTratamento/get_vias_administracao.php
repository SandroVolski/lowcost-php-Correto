<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar log de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once("../../config.php");
    
    // Definir porta padrão se não estiver definida
    if (!isset($port)) {
        $port = 3306; // Porta padrão MySQL
    }
    
    // Estabelecer conexão com o banco de serviços
    $conn_servico = new mysqli($host, $user, $pass, "bd_servico", $port);
    
    if ($conn_servico->connect_error) {
        throw new Exception("Erro ao conectar ao banco bd_servico: " . $conn_servico->connect_error);
    }
    
    // Consulta para buscar todas as vias de administração
    $sql = "SELECT idviaadministracao as id, Via_administracao as nome FROM dViaadministracao ORDER BY Via_administracao";
    $result = $conn_servico->query($sql);
    
    if (!$result) {
        throw new Exception("Erro na consulta de vias de administração: " . $conn_servico->error);
    }
    
    $vias = array();
    
    while ($row = $result->fetch_assoc()) {
        $vias[] = [
            'id' => $row['id'],
            'nome' => $row['nome']
        ];
    }
    
    // Retornar os dados como JSON
    echo json_encode($vias);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
    error_log("Erro em get_vias_administracao.php: " . $e->getMessage());
} finally {
    if (isset($conn_servico)) {
        $conn_servico->close();
    }
}
?>