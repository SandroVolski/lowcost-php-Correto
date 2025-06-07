<?php
// Configurações iniciais
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder a requisições OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configurar log de erros em vez de exibição
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
error_log("Iniciando processamento do update_service_simple.php");

// Função para responder com erro em formato JSON
function sendErrorResponse($message, $details = null, $statusCode = 500) {
    http_response_code($statusCode);
    echo json_encode([
        "success" => false,
        "message" => $message,
        "details" => $details
    ]);
    exit;
}

try {
    // Incluir arquivo de configuração
    include_once("../../config.php");
    
    // Verificar conexão
    if (!isset($conn) || $conn->connect_error) {
        error_log("Erro de conexão com o banco: " . ($conn->connect_error ?? "Não estabelecida"));
        sendErrorResponse("Erro de conexão com o banco de dados");
    }
    
    // Obter dados do request
    $rawData = file_get_contents("php://input");
    if (!$rawData) {
        error_log("Nenhum dado recebido no request");
        sendErrorResponse("Nenhum dado recebido", null, 400);
    }
    
    // Decodificar JSON
    $data = json_decode($rawData, true); // true para retornar como array
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Erro ao decodificar JSON: " . json_last_error_msg());
        sendErrorResponse("Dados JSON inválidos", json_last_error_msg(), 400);
    }
    
    // Verificar ID
    if (!isset($data['id']) || empty($data['id'])) {
        error_log("ID do serviço não fornecido");
        sendErrorResponse("ID do serviço não fornecido", null, 400);
    }
    
    // Verificar se o serviço existe
    $checkSql = "SELECT id FROM dServicoRelacionada WHERE id = ?";
    $stmt = $conn->prepare($checkSql);
    if (!$stmt) {
        error_log("Erro ao preparar consulta: " . $conn->error);
        sendErrorResponse("Erro interno do servidor");
    }
    
    $id = intval($data['id']);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        error_log("Serviço com ID $id não encontrado");
        sendErrorResponse("Serviço não encontrado", null, 404);
    }
    
    $stmt->close();
    
    // Campos permitidos para atualização
    $allowedFields = [
        'Cod' => 's',
        'Codigo_TUSS' => 's',
        'Codigo_Celos' => 's',
        'Descricao_Padronizada' => 's',
        'Descricao_Resumida' => 's',
        'Descricao_Comercial' => 's',
        'Descricao_Comercial_Completa' => 's',
        'Descricao_TUSS' => 's',
        'Concentracao' => 's',
        'UnidadeFracionamento' => 's',
        'Fracionamento' => 's',
        'Laboratorio' => 's',
        'Uso' => 's',
        'Revisado_Farma' => 'i',
        'Revisado_ADM' => 'i',
        'idViaAdministracao' => 'i',
        'idClasseFarmaceutica' => 'i',
        'idPrincipioAtivo' => 'i',
        'idArmazenamento' => 'i',
        'idMedicamento' => 'i',
        'idUnidadeFracionamento' => 'i',
        'idFatorConversao' => 'i',
        'idTaxas' => 'i',
        'idRegistroVisa' => 'i',
        'idTabela' => 'i',
        
        // Novos campos de Entrada
        'Unidade_Entrada' => 's',
        'Quantidade_Entrada' => 's', // Tratamos como string e convertemos para decimal no banco
        'Unidade_Entrada_Convertida' => 's',
        'Quantidade_Convertida' => 's',
        
        // Novos campos de Pagamento
        'Unidade_Pagamento_Nao_Fracionado' => 's',
        'Quantidade_Pagamento_Nao_Fracionado' => 's',
        'Unidade_Pagamento_Fracionado' => 's',
        'Quantidade_Pagamento_Fracionado' => 's'
    ];
    
    // Construir query de atualização dinamicamente
    $updateFields = [];
    $types = "";
    $values = [];
    
    foreach ($allowedFields as $field => $type) {
        if (array_key_exists($field, $data)) {
            $updateFields[] = "$field = ?";
            $types .= $type;
            
            // Formatação e conversão adequada de valores
            if ($type === 'i' && $data[$field] !== null) {
                // Converter strings para inteiros
                $values[] = intval($data[$field]);
            } 
            else if (($field === 'Quantidade_Entrada' || 
                     $field === 'Quantidade_Convertida' || 
                     $field === 'Quantidade_Pagamento_Nao_Fracionado' || 
                     $field === 'Quantidade_Pagamento_Fracionado') && 
                     $data[$field] !== null && $data[$field] !== '') {
                // Formatar valores decimais (garantir que a string seja um número válido)
                $values[] = is_numeric($data[$field]) ? $data[$field] : null;
            } 
            else {
                // Outros campos são tratados como strings
                $values[] = $data[$field];
            }
        }
    }
    
    // Verificar se há campos para atualizar
    if (empty($updateFields)) {
        error_log("Nenhum campo válido para atualização");
        sendErrorResponse("Nenhum campo válido para atualização", null, 400);
    }
    
    // Completar query e adicionar ID
    $sql = "UPDATE dServicoRelacionada SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $types .= "i"; // Tipo para o ID na cláusula WHERE
    $values[] = $id; // Adicionar ID aos valores
    
    error_log("Query SQL: $sql");
    error_log("Tipos para bind_param: $types");
    error_log("Quantidade de valores: " . count($values));
    
    // Preparar e executar a query
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar consulta de atualização: " . $conn->error);
        sendErrorResponse("Erro interno do servidor: " . $conn->error);
    }
    
    // Usar reflection para fazer o bind_param com array dinâmico
    if (!empty($values)) {
        // Começamos com a string de tipos
        $params = array($types);
        
        // Adicionamos cada valor como referência
        foreach ($values as $key => $value) {
            $params[] = &$values[$key];
        }
        
        // Chamamos bind_param com os parâmetros
        call_user_func_array(array($stmt, 'bind_param'), $params);
    }
    
    if (!$stmt->execute()) {
        error_log("Erro ao executar a atualização: " . $stmt->error);
        sendErrorResponse("Erro ao atualizar serviço: " . $stmt->error);
    }
    
    // Retornar resposta de sucesso
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Serviço atualizado com sucesso",
        "id" => $id,
        "affected_rows" => $stmt->affected_rows
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    error_log("Exceção no update_service_simple.php: " . $e->getMessage());
    sendErrorResponse("Erro interno do servidor: " . $e->getMessage());
}
?>