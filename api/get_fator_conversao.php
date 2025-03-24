<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Ativar log detalhado de erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log para diagnóstico
error_log("API get_fator_conversao.php foi chamada");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../config.php");

    // Verificar se a conexão está configurada
    if (!isset($conn)) {
        throw new Exception("Variável de conexão não definida. Verifique o arquivo config.php");
    }

    // Verificar se a conexão com o banco de dados foi estabelecida
    if ($conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . $conn->connect_error);
    }

    // Verificar se a tabela existe
    $checkTableQuery = "SHOW TABLES LIKE 'dfatorconversao'";
    $tableExists = $conn->query($checkTableQuery);
    
    if ($tableExists->num_rows == 0) {
        // Se a tabela não existir, retornar dados estáticos em vez de erro
        error_log("Tabela dfatorconversao não existe no banco de dados");
        
        $staticData = [
            ["id_fatorconversao" => 0, "fator" => "0"],
            ["id_fatorconversao" => 1, "fator" => "1"],
            ["id_fatorconversao" => 2, "fator" => "1"],
            ["id_fatorconversao" => 3, "fator" => "1"]
        ];
        
        http_response_code(200);
        echo json_encode($staticData);
        exit;
    }

    // Verificar a estrutura da tabela
    $checkColumnsQuery = "SHOW COLUMNS FROM dfatorconversao LIKE 'id_fatorconversao'";
    $idColumnExists = $conn->query($checkColumnsQuery);
    
    $checkFatorQuery = "SHOW COLUMNS FROM dfatorconversao LIKE 'fator'";
    $fatorColumnExists = $conn->query($checkFatorQuery);
    
    if ($idColumnExists->num_rows == 0 || $fatorColumnExists->num_rows == 0) {
        // Se as colunas necessárias não existirem, retornar dados estáticos
        error_log("Colunas necessárias não existem na tabela dfatorconversao");
        
        $staticData = [
            ["id_fatorconversao" => 0, "fator" => "0"],
            ["id_fatorconversao" => 1, "fator" => "1"],
            ["id_fatorconversao" => 2, "fator" => "1"],
            ["id_fatorconversao" => 3, "fator" => "1"]
        ];
        
        http_response_code(200);
        echo json_encode($staticData);
        exit;
    }

    // Se chegou até aqui, a tabela e as colunas existem, então prosseguir com a consulta
    $sql = "SELECT id_fatorconversao, fator FROM dfatorconversao ORDER BY id_fatorconversao";
    
    error_log("Executando consulta: " . $sql);
    
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Erro na consulta: " . $conn->error);
    }

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    error_log("Consulta executada com sucesso, retornando " . count($data) . " registros");
    
    http_response_code(200);
    echo json_encode($data);
    
} catch (Exception $e) {
    error_log("Erro em get_fator_conversao.php: " . $e->getMessage());
    
    // Em caso de erro, retornar dados estáticos em vez de erro 500
    $staticData = [
        ["id_fatorconversao" => 0, "fator" => "0"],
        ["id_fatorconversao" => 1, "fator" => "1"],
        ["id_fatorconversao" => 2, "fator" => "1"],
        ["id_fatorconversao" => 3, "fator" => "1"]
    ];
    
    http_response_code(200);
    echo json_encode($staticData);
}

// Fechar a conexão
if (isset($conn)) {
    $conn->close();
}
?>