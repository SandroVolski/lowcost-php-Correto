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

// Ativar relatório de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once("../config.php");

    // Verifica se a conexão com o banco de dados foi estabelecida
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . ($conn->connect_error ?? "Conexão não estabelecida"));
    }

    // Consulta SQL para obter as opções de tabela
    $sql = "SELECT 
                id_tabela, 
                tabela, 
                tabela_classe, 
                tabela_tipo, 
                classe_Jaragua_do_sul, 
                classificacao_tipo, 
                finalidade, 
                objetivo 
            FROM dTabela 
            ORDER BY tabela ASC";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Erro ao executar a consulta: " . $conn->error);
    }

    $data = array();
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    http_response_code(200);
    echo json_encode($data);

    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array("message" => "Erro ao buscar as tabelas", "error" => $e->getMessage()));
    error_log("Erro na API get_tabela.php: " . $e->getMessage());
}
?>