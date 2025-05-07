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
    $logFile = __DIR__ . '/med_servico_log.txt';
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logMessage("==== NOVA REQUISIÇÃO MEDICAMENTO ====");
    
    // Log dos dados recebidos
    $rawData = file_get_contents("php://input");
    logMessage("Dados brutos recebidos: " . $rawData);
    
    // Incluir configuração
    include_once("../../config.php");
    
    // Verificar conexão
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com banco de dados");
    }
    
    // Verificar ID do protocolo
    if (!isset($_GET['id_protocolo']) || empty($_GET['id_protocolo'])) {
        throw new Exception("ID do protocolo não fornecido");
    }
    
    $id_protocolo = intval($_GET['id_protocolo']);
    logMessage("ID do protocolo: $id_protocolo");
    
    // Verificar se o protocolo existe
    $checkProtocol = $conn_pacientes->query("SELECT id_protocolo FROM Protocolo WHERE id_protocolo = $id_protocolo");
    if ($checkProtocol->num_rows === 0) {
        throw new Exception("Protocolo não encontrado");
    }
    
    // Interpretar dados do POST
    $data = json_decode($rawData, true);
    if (!$data) {
        throw new Exception("Dados inválidos ou ausentes");
    }
    
    // Preparar dados para inserção
    $nome = isset($data['nome']) ? $conn_pacientes->real_escape_string($data['nome']) : '';
    $dose = isset($data['dose']) && $data['dose'] !== '' ? $data['dose'] : 'NULL';
    $unidade_medida = isset($data['unidade_medida']) && $data['unidade_medida'] !== '' ? "'" . $conn_pacientes->real_escape_string($data['unidade_medida']) . "'" : 'NULL';
    $via_administracao = isset($data['via_administracao']) && $data['via_administracao'] !== '' ? $data['via_administracao'] : 'NULL';
    $dias_aplicacao = isset($data['dias_aplicacao']) && $data['dias_aplicacao'] !== '' ? "'" . $conn_pacientes->real_escape_string($data['dias_aplicacao']) . "'" : 'NULL';
    $frequencia = isset($data['frequencia']) && $data['frequencia'] !== '' ? "'" . $conn_pacientes->real_escape_string($data['frequencia']) . "'" : 'NULL';
    $observacoes = isset($data['observacoes']) ? "'" . $conn_pacientes->real_escape_string($data['observacoes']) . "'" : 'NULL';
    
    // Verificar se a tabela existe
    $tableExists = $conn_pacientes->query("SHOW TABLES LIKE 'Protocolo_Servico'")->num_rows > 0;
    
    if (!$tableExists) {
        // Criar tabela se não existir
        logMessage("Tabela Protocolo_Servico não existe. Criando...");
        
        $createTable = "CREATE TABLE Protocolo_Servico (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_protocolo INT NOT NULL,
            id_servico INT DEFAULT 1,
            nome VARCHAR(100),
            dose DOUBLE NULL,
            unidade_medida VARCHAR(10) NULL,
            via_administracao INT NULL,
            dias_aplicacao VARCHAR(100) NULL,
            frequencia VARCHAR(50) NULL,
            observacoes TEXT
        )";
        
        if (!$conn_pacientes->query($createTable)) {
            throw new Exception("Erro ao criar tabela: " . $conn_pacientes->error);
        }
        
        logMessage("Tabela criada com sucesso");
    } else {
        // Verificar e modificar colunas existentes
        logMessage("Verificando estrutura da tabela Protocolo_Servico");
        
        // Garantir que dias_aplicacao seja VARCHAR
        $conn_pacientes->query("ALTER TABLE Protocolo_Servico MODIFY COLUMN dias_aplicacao VARCHAR(100)");
        
        // Verificar se colunas necessárias existem
        $columnsToCheck = [
            "nome" => "VARCHAR(100)",
            "unidade_medida" => "VARCHAR(10)",
            "frequencia" => "VARCHAR(50)"
        ];
        
        foreach ($columnsToCheck as $col => $type) {
            $check = $conn_pacientes->query("SHOW COLUMNS FROM Protocolo_Servico LIKE '$col'");
            if ($check->num_rows === 0) {
                logMessage("Adicionando coluna ausente: $col");
                $conn_pacientes->query("ALTER TABLE Protocolo_Servico ADD COLUMN $col $type");
            }
        }
    }
    
    // Inserir dados usando SQL direto
    $sql = "INSERT INTO Protocolo_Servico (
                id_protocolo, 
                id_servico, 
                nome, 
                dose, 
                unidade_medida, 
                via_administracao, 
                dias_aplicacao, 
                frequencia, 
                observacoes
            ) VALUES (
                $id_protocolo, 
                1, 
                '$nome', 
                $dose, 
                $unidade_medida, 
                $via_administracao, 
                $dias_aplicacao, 
                $frequencia, 
                $observacoes
            )";
    
    logMessage("SQL: $sql");
    
    if (!$conn_pacientes->query($sql)) {
        throw new Exception("Erro ao inserir medicamento: " . $conn_pacientes->error);
    }
    
    $new_id = $conn_pacientes->insert_id;
    logMessage("Medicamento inserido com sucesso. ID: $new_id");
    
    // Buscar o registro inserido
    $query = "SELECT * FROM Protocolo_Servico WHERE id = $new_id";
    $result = $conn_pacientes->query($query);
    $medicamento = $result->fetch_assoc();
    
    // Resposta
    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $new_id,
        'message' => 'Medicamento adicionado com sucesso',
        'data' => $medicamento
    ]);
    
} catch (Exception $e) {
    logMessage("ERRO: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    logMessage("==== FIM DA REQUISIÇÃO ====");
}
?>