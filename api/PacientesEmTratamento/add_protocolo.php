<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para logging
function logMessage($message) {
    $logFile = __DIR__ . '/super_debug.txt';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Ativar tratamento de erros explícito
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logMessage("ERROR [$errno]: $errstr in $errfile:$errline");
    return true;
});

try {
    logMessage("==== NOVA REQUISIÇÃO ====");
    
    // Log dos dados recebidos
    $rawData = file_get_contents("php://input");
    logMessage("Dados recebidos: " . $rawData);
    
    // Decodificar JSON com tratamento de erro
    $data = json_decode($rawData, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
        logMessage("Erro ao decodificar JSON: " . json_last_error_msg());
        throw new Exception("Dados JSON inválidos: " . json_last_error_msg());
    }
    
    // Verificar campos obrigatórios
    if (empty($data['Protocolo_Nome'])) {
        throw new Exception("Nome do protocolo é obrigatório");
    }
    
    if (empty($data['Protocolo_Sigla'])) {
        throw new Exception("Descrição do protocolo é obrigatória");
    }
    
    // Incluir configuração
    $config_path = dirname(__FILE__) . "/../../config.php";
    logMessage("Caminho do config: " . $config_path);
    
    if (!file_exists($config_path)) {
        throw new Exception("Arquivo de configuração não encontrado: " . $config_path);
    }
    
    include_once($config_path);
    
    // Verificar conexão
    if (!isset($conn_pacientes)) {
        throw new Exception("Variável de conexão não definida após incluir config.php");
    }
    
    if ($conn_pacientes->connect_error) {
        throw new Exception("Erro na conexão com o banco: " . $conn_pacientes->connect_error);
    }
    
    logMessage("Conexão estabelecida");
    
    // PARTE 1: Inserir apenas o protocolo (sem medicamentos)
    // Preparar query básica
    $sql = "INSERT INTO Protocolo (Protocolo_Nome, Protocolo_Sigla) VALUES (?, ?)";
    logMessage("SQL simples: " . $sql);
    
    // Preparar statement
    $stmt = $conn_pacientes->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $conn_pacientes->error);
    }
    
    // Bind parameters
    $nome = $data['Protocolo_Nome'];
    $sigla = $data['Protocolo_Sigla'];
    
    if (!$stmt->bind_param("ss", $nome, $sigla)) {
        throw new Exception("Erro no bind_param: " . $stmt->error);
    }
    
    // Executar
    logMessage("Executando query...");
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar query: " . $stmt->error);
    }
    
    $id_protocolo = $conn_pacientes->insert_id;
    logMessage("Protocolo inserido com sucesso, ID: " . $id_protocolo);
    
    $stmt->close();
    
    // PARTE 2: Atualizar com campos adicionais (se a tabela tiver as colunas)
    try {
        logMessage("Verificando se podemos adicionar campos adicionais...");
        
        // Verificar colunas disponíveis
        $colunas_result = $conn_pacientes->query("DESCRIBE Protocolo");
        if (!$colunas_result) {
            throw new Exception("Erro ao verificar colunas: " . $conn_pacientes->error);
        }
        
        $colunas = [];
        while ($row = $colunas_result->fetch_assoc()) {
            $colunas[] = $row['Field'];
        }
        
        logMessage("Colunas disponíveis: " . implode(", ", $colunas));
        
        // Verificar se existem as colunas para os campos adicionais
        $update_fields = [];
        $update_values = [];
        $update_types = "";
        
        if (in_array("CID", $colunas) && isset($data['CID'])) {
            $update_fields[] = "CID = ?";
            $update_values[] = $data['CID'];
            $update_types .= "s";
        }
        
        if (in_array("Intervalo_Ciclos", $colunas) && isset($data['Intervalo_Ciclos'])) {
            $update_fields[] = "Intervalo_Ciclos = ?";
            $update_values[] = intval($data['Intervalo_Ciclos']);
            $update_types .= "i";
        }
        
        if (in_array("Ciclos_Previstos", $colunas) && isset($data['Ciclos_Previstos'])) {
            $update_fields[] = "Ciclos_Previstos = ?";
            $update_values[] = intval($data['Ciclos_Previstos']);
            $update_types .= "i";
        }
        
        if (in_array("Linha", $colunas) && isset($data['Linha'])) {
            $update_fields[] = "Linha = ?";
            $update_values[] = intval($data['Linha']);
            $update_types .= "i";
        }
        
        // Se tiver campos para atualizar
        if (count($update_fields) > 0) {
            $update_sql = "UPDATE Protocolo SET " . implode(", ", $update_fields) . " WHERE id_protocolo = ?";
            $update_values[] = $id_protocolo;
            $update_types .= "i";
            
            logMessage("SQL de atualização: " . $update_sql);
            
            $update_stmt = $conn_pacientes->prepare($update_sql);
            if (!$update_stmt) {
                throw new Exception("Erro ao preparar update: " . $conn_pacientes->error);
            }
            
            // Bind parameters dinâmico
            $update_bind_params = array($update_types);
            foreach ($update_values as $key => $value) {
                $update_bind_params[] = &$update_values[$key];
            }
            
            if (!call_user_func_array(array($update_stmt, 'bind_param'), $update_bind_params)) {
                throw new Exception("Erro no bind_param do update: " . $update_stmt->error);
            }
            
            if (!$update_stmt->execute()) {
                throw new Exception("Erro ao executar update: " . $update_stmt->error);
            }
            
            logMessage("Protocolo atualizado com campos adicionais");
            $update_stmt->close();
        }
    } catch (Exception $e) {
        // Continuar mesmo se houver erro na atualização
        logMessage("AVISO: Erro ao atualizar campos adicionais: " . $e->getMessage());
    }
    
    // PARTE 3: Inserir medicamentos (se houver)
    try {
        // PROCESSAR MEDICAMENTOS, SE HOUVER
        if (isset($data['medicamentos']) && is_array($data['medicamentos']) && count($data['medicamentos']) > 0) {
            logMessage("Processando " . count($data['medicamentos']) . " medicamentos");
            
            // Primeiro, certifique-se de que a coluna dias_aplicacao é VARCHAR
            $conn->query("ALTER TABLE Protocolo_Servico MODIFY COLUMN dias_aplicacao VARCHAR(100)");
            
            foreach ($data['medicamentos'] as $index => $med) {
                if (!isset($med['nome']) || empty($med['nome'])) {
                    logMessage("Medicamento #$index sem nome, ignorando");
                    continue;
                }
                
                logMessage("Processando medicamento #$index: " . $med['nome']);
                
                // Usar variáveis para cada campo
                $nome = $conn->real_escape_string($med['nome']);
                $dose = isset($med['dose']) && $med['dose'] !== '' ? $med['dose'] : 'NULL';
                $unidade_medida = isset($med['unidade_medida']) && $med['unidade_medida'] !== '' ? $med['unidade_medida'] : 'NULL';
                $via_adm = isset($med['via_adm']) && $med['via_adm'] !== '' ? $med['via_adm'] : 'NULL';
                $dias_adm = isset($med['dias_adm']) && $med['dias_adm'] !== '' ? "'" . $conn->real_escape_string($med['dias_adm']) . "'" : 'NULL';
                $frequencia = isset($med['frequencia']) && $med['frequencia'] !== '' ? "'" . $conn->real_escape_string($med['frequencia']) . "'" : 'NULL';
                
                // Criar SQL direto
                if ($dose === 'NULL') $dose = 'NULL';
                if ($unidade_medida === 'NULL') $unidade_medida = 'NULL';
                if ($via_adm === 'NULL') $via_adm = 'NULL';
                
                $sql = "INSERT INTO Protocolo_Servico 
                        (id_protocolo, nome, dose, unidade_medida, via_administracao, dias_aplicacao, frequencia) 
                        VALUES 
                        ($newId, '$nome', $dose, $unidade_medida, $via_adm, $dias_adm, $frequencia)";
                
                logMessage("SQL Medicamento: $sql");
                
                if (!$conn->query($sql)) {
                    logMessage("Erro ao inserir medicamento: " . $conn->error);
                } else {
                    $med_id = $conn->insert_id;
                    logMessage("Medicamento inserido com sucesso, ID: $med_id");
                }
            }
        }
    } catch (Exception $e) {
        // Continuar mesmo se houver erro nos medicamentos
        logMessage("AVISO: Erro ao processar medicamentos: " . $e->getMessage());
    }
    
    // Resposta de sucesso
    $response = [
        'id' => $id_protocolo,
        'id_protocolo' => $id_protocolo,
        'message' => 'Protocolo adicionado com sucesso',
        'Protocolo_Nome' => $nome,
        'Protocolo_Sigla' => $sigla,
        'success' => true
    ];
    
    // Adicionar campos adicionais à resposta
    if (isset($data['CID'])) $response['CID'] = $data['CID'];
    if (isset($data['Intervalo_Ciclos'])) $response['Intervalo_Ciclos'] = $data['Intervalo_Ciclos'];
    if (isset($data['Ciclos_Previstos'])) $response['Ciclos_Previstos'] = $data['Ciclos_Previstos'];
    if (isset($data['Linha'])) $response['Linha'] = $data['Linha'];
    
    // Adicionar contador de medicamentos
    if (isset($data['medicamentos'])) {
        $response['medicamentos_count'] = count($data['medicamentos']);
    }
    
    logMessage("Enviando resposta de sucesso");
    http_response_code(201);
    echo json_encode($response);
    
} catch (Exception $e) {
    logMessage("ERRO FATAL: " . $e->getMessage());
    logMessage("STACK TRACE: " . $e->getTraceAsString());
    
    // Retornar erro ao cliente
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(), 
        "details" => "Verifique o arquivo super_debug.txt para mais informações"
    ]);
} finally {
    logMessage("==== FIM DA REQUISIÇÃO ====");
}
?>