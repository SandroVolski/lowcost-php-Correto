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
error_log("Iniciando processamento do update_service_improved.php");

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
    
    // Iniciar transação
    $conn->begin_transaction();
    
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
    
    // Log para debug
    error_log("Dados recebidos: " . print_r($data, true));
    
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
    
    // PARTE 1: Processar dados do RegistroVisa se fornecidos
    $idRegistroVisa = null;
    $hasRegistroVisaData = isset($data['RegistroVisa']) && !empty($data['RegistroVisa']);
    
    if ($hasRegistroVisaData) {
        $registroVisa = $data['RegistroVisa'];
        error_log("Processando RegistroVisa: $registroVisa");
        
        // Verificar se o RegistroVisa já existe
        $checkSql = "SELECT RegistroVisa FROM dRegistro_anvisa WHERE RegistroVisa = ?";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            error_log("Erro ao preparar consulta para verificar RegistroVisa: " . $conn->error);
            sendErrorResponse("Erro interno do servidor");
        }
        
        $checkStmt->bind_param("s", $registroVisa);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        // Definir campos para RegistroVisa
        $registroFields = [
            'Cod_Ggrem' => '',
            'PrincipioAtivo' => 'Não informado',
            'Lab' => '',
            'cnpj_lab' => '',
            'Classe_Terapeutica' => '',
            'Tipo_Porduto' => '',
            'Regime_Preco' => '',
            'Restricao_Hosp' => '',
            'Cap' => '',
            'Confaz87' => '',
            'Icms0' => '',
            'Lista' => '',
            'Status' => ''
        ];
        
        // Preencher com valores fornecidos
        foreach ($registroFields as $field => $default) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $registroFields[$field] = $data[$field];
            }
        }
        
        if ($checkResult->num_rows > 0) {
            // O RegistroVisa já existe - atualizar
            $idRegistroVisa = $registroVisa;
            $checkStmt->close();
            
            // Construir query de atualização
            $updateFields = [];
            foreach ($registroFields as $field => $value) {
                $updateFields[] = "$field = ?";
            }
            
            $sqlRegistro = "UPDATE dRegistro_anvisa SET " . implode(", ", $updateFields) . " WHERE RegistroVisa = ?";
            $stmtRegistro = $conn->prepare($sqlRegistro);
            
            if (!$stmtRegistro) {
                error_log("Erro ao preparar consulta para atualizar RegistroVisa: " . $conn->error);
                sendErrorResponse("Erro interno do servidor");
            }
            
            // Criar array de valores para bind_param
            $values = array_values($registroFields);
            $values[] = $registroVisa; // Add RegistroVisa para WHERE
            
            // Criar string de tipos (todos string)
            $types = str_repeat('s', count($values));
            
            // Bind params
            $bindParams = array();
            $bindParams[] = &$types;
            
            foreach ($values as $key => $value) {
                $bindParams[] = &$values[$key];
            }
            
            call_user_func_array(array($stmtRegistro, 'bind_param'), $bindParams);
            
            if (!$stmtRegistro->execute()) {
                error_log("Erro ao atualizar RegistroVisa: " . $stmtRegistro->error);
                sendErrorResponse("Erro ao atualizar RegistroVisa: " . $stmtRegistro->error);
            }
            
            $stmtRegistro->close();
            error_log("RegistroVisa atualizado com sucesso: $registroVisa");
        } else {
            // O RegistroVisa não existe - inserir
            $checkStmt->close();
            
            // Criar lista de campos e placeholders
            $fields = array_keys($registroFields);
            array_unshift($fields, 'RegistroVisa'); // Adicionar RegistroVisa no início
            
            $placeholders = array_fill(0, count($fields), '?');
            
            $sqlRegistro = "INSERT INTO dRegistro_anvisa (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
            $stmtRegistro = $conn->prepare($sqlRegistro);
            
            if (!$stmtRegistro) {
                error_log("Erro ao preparar consulta para inserir RegistroVisa: " . $conn->error);
                sendErrorResponse("Erro interno do servidor");
            }
            
            // Criar array de valores para bind_param
            $values = array_values($registroFields);
            array_unshift($values, $registroVisa); // Adicionar RegistroVisa no início
            
            // Criar string de tipos (todos string)
            $types = str_repeat('s', count($values));
            
            // Bind params
            $bindParams = array();
            $bindParams[] = &$types;
            
            foreach ($values as $key => $value) {
                $bindParams[] = &$values[$key];
            }
            
            call_user_func_array(array($stmtRegistro, 'bind_param'), $bindParams);
            
            if (!$stmtRegistro->execute()) {
                error_log("Erro ao inserir RegistroVisa: " . $stmtRegistro->error);
                sendErrorResponse("Erro ao inserir RegistroVisa: " . $stmtRegistro->error);
            }
            
            $idRegistroVisa = $registroVisa;
            $stmtRegistro->close();
            error_log("RegistroVisa inserido com sucesso: $registroVisa");
        }
    }
    
    // PARTE 2: Atualizar o serviço na tabela dServicoRelacionada (abordagem dinâmica)
    // Campos permitidos para atualização
    $allowedFields = [
        'Cod' => 's',
        'Codigo_TUSS' => 's',
        'codigoTUSS' => 's', // Mapeamento alternativo
        'Descricao_Apresentacao' => 's',
        'Descricao_Resumida' => 's',
        'Descricao_Comercial' => 's',
        'Concentracao' => 's',
        'UnidadeFracionamento' => 's',
        'Fracionamento' => 's',
        'Laboratorio' => 's',
        'Laboratório' => 's', // Mapeamento alternativo
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
        'idTabela' => 'i'
    ];
    
    // Se temos idRegistroVisa, adicionar ao serviço
    if ($idRegistroVisa) {
        $data['idRegistroVisa'] = $idRegistroVisa;
        $allowedFields['idRegistroVisa'] = 'i';
    }
    
    // Construir query de atualização dinamicamente
    $updateFields = [];
    $types = "";
    $values = [];
    
    foreach ($allowedFields as $field => $type) {
        // Tratar campos alternativos (mapeamentos)
        if ($field === 'codigoTUSS' && isset($data[$field]) && $data[$field] !== '') {
            $updateFields[] = "Codigo_TUSS = ?";
            $types .= $type;
            $values[] = $data[$field];
        }
        else if ($field === 'Laboratório' && isset($data[$field]) && $data[$field] !== '') {
            $updateFields[] = "Laboratorio = ?";
            $types .= $type;
            $values[] = $data[$field];
        }
        // Tratar campos regulares
        else if ($field !== 'codigoTUSS' && $field !== 'Laboratório' && isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $types .= $type;
            
            // Converter valor conforme tipo
            if ($type === 'i' && $data[$field] !== null && $data[$field] !== '') {
                $values[] = intval($data[$field]);
            } else {
                $values[] = $data[$field];
            }
        }
    }
    
    // Verificar se há campos para atualizar
    if (empty($updateFields)) {
        error_log("Nenhum campo válido para atualização na tabela dServicoRelacionada");
        sendErrorResponse("Nenhum campo válido para atualização", null, 400);
    }
    
    // Completar query e adicionar ID
    $sql = "UPDATE dServicoRelacionada SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $types .= "i"; // Tipo para o ID na cláusula WHERE
    $values[] = $id; // Adicionar ID aos valores
    
    error_log("Query SQL para dServicoRelacionada: $sql");
    error_log("Tipos para bind_param: $types");
    error_log("Quantidade de valores: " . count($values));
    
    // Preparar e executar a query
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Erro ao preparar consulta de atualização: " . $conn->error);
        sendErrorResponse("Erro interno do servidor: " . $conn->error);
    }
    
    // Usar reflection para fazer o bind_param com array dinâmico
    $bindParams = array();
    $bindParams[] = &$types;
    
    for ($i = 0; $i < count($values); $i++) {
        $bindParams[] = &$values[$i];
    }
    
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    
    if (!$stmt->execute()) {
        error_log("Erro ao executar a atualização: " . $stmt->error);
        sendErrorResponse("Erro ao atualizar serviço: " . $stmt->error);
    }
    
    // Commit a transação
    $conn->commit();
    
    // Retornar resposta de sucesso
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Serviço atualizado com sucesso",
        "id" => $id,
        "registroVisa" => $hasRegistroVisaData ? $idRegistroVisa : null,
        "affected_rows" => $stmt->affected_rows
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn) && !$conn->connect_error) {
        $conn->rollback();
    }
    
    error_log("Exceção no update_service_improved.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    sendErrorResponse("Erro interno do servidor: " . $e->getMessage());
}
?>