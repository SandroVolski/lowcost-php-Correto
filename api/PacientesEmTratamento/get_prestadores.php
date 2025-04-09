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

// Função para log de debug
function logDebug($message) {
    $logFile = __DIR__ . '/prestadores_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

logDebug("=== INICIANDO NOVA CONSULTA DE PRESTADORES ===");

try {
    // Incluir arquivo de configuração
    include_once("../../config.php");
    logDebug("Arquivo de configuração incluído");

    // Verifica se a conexão com o banco de dados foi estabelecida
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados");
    }
    logDebug("Conexão com o banco estabelecida");

    // Query ULTRA SIMPLIFICADA: foco EXCLUSIVO no Prestador_Nome_Fantasia
    $sql = "
    SELECT 
        id, 
        Prestador_Nome_Fantasia 
    FROM 
        bd_servico.bd_empresas_empresas 
    WHERE 
        Prestador_Nome_Fantasia IS NOT NULL 
        AND TRIM(Prestador_Nome_Fantasia) != '' 
    ORDER BY 
        Prestador_Nome_Fantasia
    ";
    logDebug("SQL: $sql");

    // Executar a consulta
    $result = $conn->query($sql);
    if (!$result) {
        throw new Exception("Erro ao buscar prestadores: " . $conn->error);
    }
    
    $rowCount = $result->num_rows;
    logDebug("Encontrados $rowCount prestadores com Nome_Fantasia");

    // Estrutura de dados SIMPLIFICADA
    $prestadores = [];
    while ($row = $result->fetch_assoc()) {
        $prestadores[] = [
            'id' => $row['id'],
            'nome' => $row['Prestador_Nome_Fantasia']
        ];
    }
    
    // Verificar se a lista está vazia
    if (count($prestadores) === 0) {
        logDebug("ALERTA: Nenhum prestador com nome fantasia encontrado.");
    } else {
        logDebug("Total de " . count($prestadores) . " prestadores com Nome_Fantasia");
        
        // Log dos primeiros 5 prestadores como amostra
        for ($i = 0; $i < min(5, count($prestadores)); $i++) {
            logDebug("Amostra $i: ID=" . $prestadores[$i]['id'] . ", Nome_Fantasia=" . $prestadores[$i]['nome']);
        }
    }

    http_response_code(200);
    echo json_encode($prestadores);
    logDebug("Requisição concluída com sucesso");
    
} catch (Exception $e) {
    logDebug("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao buscar prestadores", 
        "error" => $e->getMessage()
    ]);
}

// Fechar conexão
if (isset($conn)) {
    $conn->close();
    logDebug("Conexão fechada");
}
logDebug("=== FIM DA CONSULTA ===");
?>