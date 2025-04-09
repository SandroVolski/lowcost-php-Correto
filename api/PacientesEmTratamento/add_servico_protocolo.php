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

// Ativar log de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para logging
function logMessage($message) {
    $logFile = __DIR__ . '/servico_log.txt';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    include_once("../../config.php");
    
    logMessage("============= NOVA REQUISIÇÃO =============");
    logMessage("Iniciando add_servico_protocolo.php");
    
    // Log dos dados recebidos
    $rawData = file_get_contents("php://input");
    logMessage("Dados brutos recebidos: " . $rawData);
    
    // Conexão com o banco de dados - usando conexão do config.php
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com banco bd_pacientestto");
    }
    $conn = $conn_pacientes;
    
    logMessage("Conexão estabelecida");
    
    // Verificar ID do protocolo
    if (!isset($_GET['id_protocolo']) || empty($_GET['id_protocolo'])) {
        throw new Exception("ID do protocolo não fornecido.");
    }
    
    $id_protocolo = intval($_GET['id_protocolo']);
    logMessage("ID do protocolo: $id_protocolo");
    
    // DESCOBRIR NOME DA COLUNA PK NA TABELA PROTOCOLO
    logMessage("Verificando estrutura da tabela Protocolo...");
    $table_structure = $conn->query("DESCRIBE Protocolo");
    $id_column = "id_protocolo"; // valor padrão
    
    if ($table_structure) {
        while ($row = $table_structure->fetch_assoc()) {
            if ($row['Key'] == 'PRI') {
                $id_column = $row['Field'];
                logMessage("Coluna de chave primária identificada: " . $id_column);
                break;
            }
        }
    }
    
    // Verificar se o protocolo existe usando o nome correto da coluna
    $checkProtocol = $conn->query("SELECT $id_column FROM Protocolo WHERE $id_column = $id_protocolo");
    if ($checkProtocol->num_rows === 0) {
        throw new Exception("Protocolo não encontrado");
    }
    
    // Interpretar dados do POST
    $data = json_decode($rawData, true);
    
    if (!$data) {
        throw new Exception("Dados inválidos ou ausentes");
    }
    
    // Preparar dados para inserção
    $id_servico = isset($data['id_servico']) && !empty($data['id_servico']) ? intval($data['id_servico']) : 1;
    $servico_codigo = isset($data['Servico_Codigo']) ? $conn->real_escape_string($data['Servico_Codigo']) : '';
    $dose = isset($data['Dose']) && $data['Dose'] !== '' ? $data['Dose'] : null;
    $dose_m = isset($data['Dose_M']) && $data['Dose_M'] !== '' ? $data['Dose_M'] : null;
    $dose_total = isset($data['Dose_Total']) && $data['Dose_Total'] !== '' ? $data['Dose_Total'] : null;
    $dias_aplic = isset($data['Dias_de_Aplic']) && $data['Dias_de_Aplic'] !== '' ? $data['Dias_de_Aplic'] : null;
    $via_adm = isset($data['Via_de_Adm']) ? $conn->real_escape_string($data['Via_de_Adm']) : '';
    $observacoes = isset($data['observacoes']) ? $conn->real_escape_string($data['observacoes']) : '';
    
    // ** NOVA ABORDAGEM **
    // Verificar se a tabela Protocolo_Servico existe
    $tableExists = $conn->query("SHOW TABLES LIKE 'Protocolo_Servico'")->num_rows > 0;
    
    if (!$tableExists) {
        // Criar tabela nova se não existir
        logMessage("Tabela Protocolo_Servico não existe. Criando...");
        
        $createTable = "CREATE TABLE Protocolo_Servico (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_protocolo INT NOT NULL,
            id_servico INT NOT NULL,
            Servico_Codigo VARCHAR(100),
            Dose VARCHAR(100) NULL,
            Dose_M VARCHAR(100) NULL,
            Dose_Total VARCHAR(100) NULL,
            Dias_de_Aplic VARCHAR(100) NULL,
            Via_de_Adm VARCHAR(50),
            observacoes TEXT
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception("Erro ao criar tabela: " . $conn->error);
        }
        
        logMessage("Tabela criada com sucesso. Usando coluna id_protocolo.");
        $protocolo_id_column = 'id_protocolo';
    } else {
        // Analisar estrutura da tabela existente
        logMessage("Tabela Protocolo_Servico já existe. Verificando colunas...");
        
        // Verificar estrutura da tabela existente
        $result = $conn->query("DESCRIBE Protocolo_Servico");
        $colunas = [];
        while ($row = $result->fetch_assoc()) {
            $colunas[] = $row['Field'];
            logMessage("Coluna encontrada: " . $row['Field']);
        }
        
        // Procurar qual coluna é usada para o ID do protocolo
        $possiveis_colunas = ['id_protocolo', 'protocolo_id', 'idprotocolo', 'protocolo'];
        $protocolo_id_column = null;
        
        foreach ($possiveis_colunas as $coluna) {
            if (in_array($coluna, $colunas)) {
                $protocolo_id_column = $coluna;
                logMessage("Coluna para ID do protocolo identificada: $coluna");
                break;
            }
        }
        
        // Se não encontrou, tentar adicionar a coluna
        if (!$protocolo_id_column) {
            logMessage("Nenhuma coluna adequada para ID do protocolo encontrada.");
            
            // Verificar se pode adicionar coluna id_protocolo
            if (!$conn->query("ALTER TABLE Protocolo_Servico ADD id_protocolo INT NOT NULL AFTER id")) {
                throw new Exception("Não foi possível identificar ou criar coluna para ID do protocolo: " . $conn->error);
            }
            
            logMessage("Adicionada coluna id_protocolo à tabela");
            $protocolo_id_column = 'id_protocolo';
        }
    }
    
    // Montar a consulta usando a coluna identificada para o ID do protocolo
    $colunas = [$protocolo_id_column, 'id_servico', 'Servico_Codigo', 'Dose', 'Dose_M', 'Dose_Total', 'Dias_de_Aplic', 'Via_de_Adm', 'observacoes'];
    $placeholders = array_fill(0, count($colunas), '?');
    
    $sql = "INSERT INTO Protocolo_Servico (" . implode(", ", $colunas) . ") VALUES (" . implode(", ", $placeholders) . ")";
    
    logMessage("SQL preparado: " . $sql);
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar query: " . $conn->error);
    }
    
    // Preparar dados para vinculação
    $params = [$id_protocolo, $id_servico, $servico_codigo, $dose, $dose_m, $dose_total, $dias_aplic, $via_adm, $observacoes];
    
    // CORREÇÃO: Certifique-se de que o número de tipos corresponde ao número de parâmetros
    $tipos = str_repeat('s', count($params)); // Inicialmente todos como strings
    $tipos[0] = 'i'; // Primeiro parâmetro (id_protocolo) como inteiro
    $tipos[1] = 'i'; // Segundo parâmetro (id_servico) como inteiro
    
    logMessage("Tipos de parâmetros: " . $tipos . " (total: " . strlen($tipos) . ")");
    logMessage("Número de parâmetros: " . count($params));
    
    // Método mais seguro para bind_param
    $bind_params = array($tipos);
    foreach ($params as $key => $value) {
        $bind_params[] = &$params[$key]; // Importante usar referência (&)
    }
    
    // Aplicar parâmetros
    call_user_func_array(array($stmt, 'bind_param'), $bind_params);
    
    logMessage("Executando query de inserção com os seguintes valores:");
    logMessage("- $protocolo_id_column: $id_protocolo");
    logMessage("- id_servico: $id_servico");
    logMessage("- Servico_Codigo: $servico_codigo");
    logMessage("- Dose: $dose");
    logMessage("- Dose_M: $dose_m");
    logMessage("- Dose_Total: $dose_total");
    logMessage("- Dias_de_Aplic: $dias_aplic");
    logMessage("- Via_de_Adm: $via_adm");
    logMessage("- observacoes: $observacoes");
    
    if (!$stmt->execute()) {
        throw new Exception("Erro ao inserir serviço: " . $stmt->error);
    }
    
    $newId = $conn->insert_id;
    logMessage("Serviço inserido com sucesso. ID: $newId");
    
    // Preparar resposta
    $response = [
        'id' => $newId,
        'id_protocolo' => $id_protocolo,
        'id_servico' => $id_servico,
        'Servico_Codigo' => $servico_codigo,
        'Dose' => $dose,
        'Dose_M' => $dose_m,
        'Dose_Total' => $dose_total,
        'Dias_de_Aplic' => $dias_aplic,
        'Via_de_Adm' => $via_adm,
        'observacoes' => $observacoes
    ];
    
    http_response_code(201);
    echo json_encode($response);
    
} catch (Exception $e) {
    logMessage("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
    logMessage("Finalizado add_servico_protocolo.php");
}
?>