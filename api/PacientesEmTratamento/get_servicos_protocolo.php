<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
    $logDir = "../logs";
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $logFile = "$logDir/servicos_protocolo_log.txt";
    $timestamp = date("Y-m-d H:i:s");
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    include_once("../../config.php");
    
    logMessage("Iniciando get_servicos_protocolo.php");
    
    // Conexão com o banco de dados
    $conn = new mysqli($host, $user, $pass, "bd_pacientestto", $port);
    
    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }
    
    logMessage("Conexão estabelecida");
    
    // Verificar ID do protocolo
    if (!isset($_GET['id_protocolo']) || empty($_GET['id_protocolo'])) {
        throw new Exception("ID do protocolo não fornecido.");
    }
    
    $id_protocolo = intval($_GET['id_protocolo']);
    logMessage("ID do protocolo: $id_protocolo");
    
    // Verificar se a tabela existe
    $tableExists = $conn->query("SHOW TABLES LIKE 'Protocolo_Servico'")->num_rows > 0;
    
    // Criar tabela se não existir
    if (!$tableExists) {
        logMessage("Tabela Protocolo_Servico não existe. Criando...");
        $createTable = "CREATE TABLE Protocolo_Servico (
            id INT AUTO_INCREMENT PRIMARY KEY,
            id_protocolo INT NOT NULL,
            id_servico INT NOT NULL,
            Servico_Codigo VARCHAR(100),
            Dose DOUBLE,
            Dose_M DOUBLE,
            Dose_Total DOUBLE,
            Dias_de_Aplic INT,
            Via_de_Adm VARCHAR(50),
            observacoes TEXT
        )";
        
        if (!$conn->query($createTable)) {
            throw new Exception("Erro ao criar tabela: " . $conn->error);
        }
        
        logMessage("Tabela criada com sucesso");
    } else {
        // Verificar se as colunas existem e adicionar se necessário
        $columnsToCheck = [
            "Servico_Codigo" => "VARCHAR(100)",
            "Dose" => "DOUBLE",
            "Dose_M" => "DOUBLE",
            "Dose_Total" => "DOUBLE",
            "Dias_de_Aplic" => "INT",
            "Via_de_Adm" => "VARCHAR(50)",
            "observacoes" => "TEXT"
        ];
        
        foreach ($columnsToCheck as $column => $type) {
            $checkColumn = $conn->query("SHOW COLUMNS FROM Protocolo_Servico LIKE '$column'");
            if ($checkColumn->num_rows === 0) {
                logMessage("Adicionando coluna $column à tabela");
                $addColumn = "ALTER TABLE Protocolo_Servico ADD COLUMN $column $type";
                if (!$conn->query($addColumn)) {
                    logMessage("Aviso: Erro ao adicionar coluna $column: " . $conn->error);
                }
            }
        }
    }
    
    // Buscar serviços com todos os campos
    logMessage("Executando consulta para obter serviços com todos os campos");
    
    // Tentar buscar todos os serviços associados ao protocolo
    // No get_servicos_protocolo.php, adicione este SQL mais completo
    $sql = "SELECT 
        id,
        id_protocolo,
        id_servico,
        Servico_Codigo,
        nome,
        Dose,
        dose,
        Dose_M,
        Dose_Total,
        Dias_de_Aplic,
        dias_aplicacao,
        Via_de_Adm,
        via_administracao,
        frequencia,
        unidade_medida,
        observacoes
        FROM Protocolo_Servico 
        WHERE id_protocolo = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        // Em caso de erro (talvez alguma coluna não exista), tentar consulta mais simples
        logMessage("Erro na consulta completa. Tentando consulta simples.");
        $sql = "SELECT * FROM Protocolo_Servico WHERE id_protocolo = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Erro ao preparar consulta: " . $conn->error);
        }
    }
    
    $stmt->bind_param("i", $id_protocolo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $servicos = [];
    while ($row = $result->fetch_assoc()) {
        $servicos[] = $row;
    }
    
    logMessage("Encontrados " . count($servicos) . " serviços para o protocolo $id_protocolo");
    
    echo json_encode($servicos);
    
} catch (Exception $e) {
    logMessage("Erro: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
    logMessage("Finalizado get_servicos_protocolo.php");
}
?>