<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

include_once("../../config.php");

try {
    // Verificar conexão com o banco bd_servico
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de serviço.");
    }
    
    // Parâmetros de pesquisa (opcional)
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000; // Limitar quantidade por padrão para evitar sobrecarga
    
    // Construir a consulta SQL
    $sql = "SELECT 
                SUBCAT as codigo, 
                DESCRICAO as descricao
            FROM 
                bd_cid10_subcategoria";
    
    // Adicionar filtro de pesquisa se fornecido
    if (!empty($search)) {
        $search = $conn->real_escape_string($search);
        $sql .= " WHERE SUBCAT LIKE '%$search%' OR DESCRICAO LIKE '%$search%'";
    }
    
    // Ordenar e limitar os resultados
    $sql .= " ORDER BY SUBCAT ASC LIMIT $limit";
    
    // Executar a consulta
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception("Erro ao executar consulta: " . $conn->error);
    }
    
    // Coletar os resultados
    $cids = [];
    while ($row = $result->fetch_assoc()) {
        $cids[] = [
            'codigo' => $row['codigo'],
            'descricao' => $row['descricao']
        ];
    }
    
    // Retornar os resultados
    echo json_encode($cids);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage()
    ]);
}

// Fechar conexão
if (isset($conn)) {
    $conn->close();
}
?>