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

    // Verificar parâmetro de ID do paciente
    if (!isset($_GET['paciente_id'])) {
        throw new Exception("ID do paciente não fornecido");
    }
    
    $pacienteId = intval($_GET['paciente_id']);
    
    // Consulta para buscar todas as prévias do paciente
    $sql = "SELECT p.*, 
           COUNT(pa.id) AS qtd_anexos
    FROM previas p
    LEFT JOIN previa_anexos pa ON p.id = pa.previa_id
    WHERE p.paciente_id = ?
    GROUP BY p.id
    ORDER BY p.data_criacao DESC";
    
    $stmt = $conn_pacientes->prepare($sql);
    $stmt->bind_param("i", $pacienteId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $previas = [];
    while ($row = $result->fetch_assoc()) {
        $previas[] = $row;
    }
    
    echo json_encode($previas);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>