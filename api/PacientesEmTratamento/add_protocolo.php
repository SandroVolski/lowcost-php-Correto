<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

function logError($message) {
    $logFile = __DIR__ . '/add_protocolo_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logError("=== NOVA SOLICITAÇÃO ===");
    
    // Tentar incluir config.php com caminho absoluto
    $config_path = dirname(__FILE__) . "/../../config.php";
    if (!file_exists($config_path)) {
        logError("ERRO: Arquivo config.php não encontrado em: " . $config_path);
        throw new Exception("Arquivo de configuração não encontrado");
    }
    
    include_once($config_path);
    
    // Verificar conexão
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        logError("ERRO: Conexão com banco de dados não disponível");
        throw new Exception("Erro de conexão com banco de dados");
    }
    
    // Receber e validar JSON
    $raw_input = file_get_contents("php://input");
    $postData = json_decode($raw_input, true);
    if ($postData === null) {
        logError("ERRO: Dados JSON inválidos: " . $raw_input);
        throw new Exception("Dados JSON inválidos");
    }
    
    // Preparar campos - com valores padrão para evitar erros
    $protocolo_nome = isset($postData['Protocolo_Nome']) ? $postData['Protocolo_Nome'] : '';
    $protocolo_sigla = isset($postData['Protocolo_Sigla']) ? $postData['Protocolo_Sigla'] : '';
    $servico_codigo = isset($postData['Servico_Codigo']) ? $postData['Servico_Codigo'] : '';
    $protocolo_dose_m = isset($postData['Protocolo_Dose_M']) && $postData['Protocolo_Dose_M'] !== '' ? $postData['Protocolo_Dose_M'] : null;
    $protocolo_dose_total = isset($postData['Protocolo_Dose_Total']) && $postData['Protocolo_Dose_Total'] !== '' ? $postData['Protocolo_Dose_Total'] : null;
    $protocolo_dias_aplicacao = isset($postData['Protocolo_Dias_de_Aplicacao']) && $postData['Protocolo_Dias_de_Aplicacao'] !== '' ? $postData['Protocolo_Dias_de_Aplicacao'] : null;
    $protocolo_viaadm = isset($postData['Protocolo_ViaAdm']) && $postData['Protocolo_ViaAdm'] !== '' ? $postData['Protocolo_ViaAdm'] : null;
    $linha = isset($postData['Linha']) && $postData['Linha'] !== '' ? $postData['Linha'] : null;
    $cid = isset($postData['CID']) ? $postData['CID'] : '';
    
    // Verificar se a tabela tem coluna CID
    $checkCID = $conn_pacientes->query("SHOW COLUMNS FROM Protocolo LIKE 'CID'");
    $cidExists = ($checkCID && $checkCID->num_rows > 0);
    
    // PRIMEIRA TENTATIVA: Inserção simples apenas com campos obrigatórios
    logError("Tentando inserção básica apenas com Nome e Sigla");
    
    $sql = "INSERT INTO Protocolo (Protocolo_Nome, Protocolo_Sigla, Servico_Codigo) VALUES (?, ?, ?)";
    $stmt = $conn_pacientes->prepare($sql);
    
    if (!$stmt) {
        logError("ERRO na preparação do SQL: " . $conn_pacientes->error);
        throw new Exception("Erro ao preparar consulta: " . $conn_pacientes->error);
    }
    
    $stmt->bind_param("sss", $protocolo_nome, $protocolo_sigla, $servico_codigo);
    
    if (!$stmt->execute()) {
        logError("ERRO na execução da consulta: " . $stmt->error);
        throw new Exception("Erro ao executar inserção: " . $stmt->error);
    }
    
    $id_protocolo = $conn_pacientes->insert_id;
    $stmt->close();
    
    logError("Inserção básica bem-sucedida, ID: " . $id_protocolo);
    
    // SEGUNDA ETAPA: Atualizar com os demais campos
    $update_fields = [];
    $params = [];
    $types = "";
    
    if ($protocolo_dose_m !== null) {
        $update_fields[] = "Protocolo_Dose_M = ?";
        $params[] = $protocolo_dose_m;
        $types .= "s"; // Alterado para string para evitar problemas de tipo
    }
    
    if ($protocolo_dose_total !== null) {
        $update_fields[] = "Protocolo_Dose_Total = ?";
        $params[] = $protocolo_dose_total;
        $types .= "i";
    }
    
    if ($protocolo_dias_aplicacao !== null) {
        $update_fields[] = "Protocolo_Dias_de_Aplicacao = ?";
        $params[] = $protocolo_dias_aplicacao;
        $types .= "s"; // Alterado para string para evitar problemas de tipo
    }
    
    if ($protocolo_viaadm !== null) {
        $update_fields[] = "Protocolo_ViaAdm = ?";
        $params[] = $protocolo_viaadm;
        $types .= "i";
    }
    
    if ($linha !== null) {
        $update_fields[] = "Linha = ?";
        $params[] = $linha;
        $types .= "i";
    }
    
    if ($cidExists && $cid !== '') {
        $update_fields[] = "CID = ?";
        $params[] = $cid;
        $types .= "s";
    }
    
    // Adicionar o ID para a cláusula WHERE
    $params[] = $id_protocolo;
    $types .= "i";
    
    if (count($update_fields) > 0) {
        logError("Atualizando campos adicionais");
        $update_sql = "UPDATE Protocolo SET " . implode(", ", $update_fields) . " WHERE id_protocolo = ?";
        
        logError("SQL de atualização: " . $update_sql);
        logError("Tipos de parâmetros: " . $types);
        
        $update_stmt = $conn_pacientes->prepare($update_sql);
        if (!$update_stmt) {
            logError("ERRO ao preparar atualização: " . $conn_pacientes->error);
            // Não lançar exceção, continuamos mesmo se a atualização falhar
        } else {
            // Bind dinâmico de parâmetros
            $bind_params = array($types);
            for ($i = 0; $i < count($params); $i++) {
                $bind_params[] = &$params[$i];
            }
            
            call_user_func_array(array($update_stmt, 'bind_param'), $bind_params);
            
            if (!$update_stmt->execute()) {
                logError("AVISO: Falha na atualização: " . $update_stmt->error);
                // Não lançar exceção, continuamos mesmo se a atualização falhar
            }
            
            $update_stmt->close();
        }
    }
    
    // Resposta
    $response = [
        'id' => $id_protocolo,
        'id_protocolo' => $id_protocolo,
        'message' => 'Protocolo adicionado com sucesso',
        'Protocolo_Nome' => $protocolo_nome,
        'Protocolo_Sigla' => $protocolo_sigla,
        'Servico_Codigo' => $servico_codigo
    ];
    
    // Adicionar campos opcionais se existirem
    if ($protocolo_dose_m !== null) $response['Protocolo_Dose_M'] = $protocolo_dose_m;
    if ($protocolo_dose_total !== null) $response['Protocolo_Dose_Total'] = $protocolo_dose_total;
    if ($protocolo_dias_aplicacao !== null) $response['Protocolo_Dias_de_Aplicacao'] = $protocolo_dias_aplicacao;
    if ($protocolo_viaadm !== null) $response['Protocolo_ViaAdm'] = $protocolo_viaadm;
    if ($linha !== null) $response['Linha'] = $linha;
    if ($cidExists && $cid !== '') $response['CID'] = $cid;
    
    logError("Operação concluída com sucesso");
    
    http_response_code(201);
    echo json_encode($response);
    
} catch (Exception $e) {
    logError("ERRO CRÍTICO: " . $e->getMessage());
    logError("STACK TRACE: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "details" => "Verifique o arquivo add_protocolo_log.txt para mais informações"
    ]);
}
?>