
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar log detalhado de erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once("../../config.php");

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . ($conn->connect_error ?? "Conexão não estabelecida"));
    }

    // Obter linhas de tratamento da tabela Linha_Tratamento
    $sql = "SELECT id_linha_tratamento as id, Linha_Codigo as codigo, Linha_Descricao as descricao FROM Linha_Tratamento ORDER BY Linha_Codigo";
    
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Erro ao executar a consulta: " . $conn->error);
    }
    
    $linhas = [];
    
    while ($row = $result->fetch_assoc()) {
        $linhas[] = $row;
    }
    
    // Se não houver linhas cadastradas, criar uma resposta padrão
    if (empty($linhas)) {
        $linhas = [
            ["id" => 1, "codigo" => 1, "descricao" => "Primeira Linha"],
            ["id" => 2, "codigo" => 2, "descricao" => "Segunda Linha"],
            ["id" => 3, "codigo" => 3, "descricao" => "Terceira Linha"],
            ["id" => 4, "codigo" => 4, "descricao" => "Quarta Linha"],
            ["id" => 5, "codigo" => 5, "descricao" => "Quinta Linha"]
        ];
    }
    
    echo json_encode($linhas);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    error_log("Erro em get_linhas_tratamento.php: " . $e->getMessage());
}

if (isset($conn)) {
    $conn->close();
}
?>