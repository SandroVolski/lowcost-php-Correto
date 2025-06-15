<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");
    
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        http_response_code(500);
        echo json_encode(["error" => "Erro na conexão com o banco"]);
        exit;
    }
    
    $patientId = $_GET['id'] ?? null;
    
    if (!$patientId) {
        http_response_code(400);
        echo json_encode(["error" => "ID do paciente é obrigatório"]);
        exit;
    }
    
    // Query para buscar paciente na tabela Pacientes (maiúsculo)
    $sql = "SELECT 
        id,
        Paciente_Nome as Nome,
        Codigo as Paciente_Codigo,
        Data_Nascimento as Nascimento,
        Sexo,
        COALESCE(Operadora, 0) as Operadora,
        COALESCE(Prestador, 0) as Prestador,
        COALESCE(Cid_Diagnostico, '') as Cid_Diagnostico,
        Data_Inicio_Tratamento
    FROM Pacientes 
    WHERE id = ?";
    
    $stmt = $conn_pacientes->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Erro ao preparar query: " . $conn_pacientes->error]);
        exit;
    }
    
    $stmt->bind_param("i", $patientId);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Erro ao executar query: " . $stmt->error]);
        exit;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Paciente não encontrado"]);
        exit;
    }
    
    $patient = $result->fetch_assoc();
    
    // Formatear datas se existirem
    if ($patient['Nascimento']) {
        $patient['Nascimento_Formatado'] = date('d/m/Y', strtotime($patient['Nascimento']));
    }
    if ($patient['Data_Inicio_Tratamento']) {
        $patient['Data_Inicio_Tratamento_Formatado'] = date('d/m/Y', strtotime($patient['Data_Inicio_Tratamento']));
    }
    
    // Buscar informações adicionais de prévias deste paciente
    $previasCountSql = "SELECT COUNT(*) as total_previas FROM previas WHERE paciente_id = ?";
    $previasStmt = $conn_pacientes->prepare($previasCountSql);
    $previasStmt->bind_param("i", $patientId);
    $previasStmt->execute();
    $previasResult = $previasStmt->get_result();
    $previasCount = $previasResult->fetch_assoc();
    
    $patient['total_previas'] = $previasCount['total_previas'];
    
    // Buscar última prévia
    $lastPreviaSql = "SELECT id, protocolo, data_criacao, parecer_guia, finalizacao 
                      FROM previas 
                      WHERE paciente_id = ? 
                      ORDER BY data_criacao DESC 
                      LIMIT 1";
    $lastPreviaStmt = $conn_pacientes->prepare($lastPreviaSql);
    $lastPreviaStmt->bind_param("i", $patientId);
    $lastPreviaStmt->execute();
    $lastPreviaResult = $lastPreviaStmt->get_result();
    
    if ($lastPreviaResult->num_rows > 0) {
        $patient['ultima_previa'] = $lastPreviaResult->fetch_assoc();
    } else {
        $patient['ultima_previa'] = null;
    }
    
    $stmt->close();
    $previasStmt->close();
    $lastPreviaStmt->close();
    
    echo json_encode($patient);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Erro interno: " . $e->getMessage()]);
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>