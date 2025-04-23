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

    // Verificar parâmetro de ID da prévia
    if (!isset($_GET['previa_id'])) {
        throw new Exception("ID da prévia não fornecido");
    }
    
    $previaId = intval($_GET['previa_id']);
    
    // Consulta para buscar ciclos/dias da prévia
    $sql = "SELECT * FROM previa_ciclos_dias WHERE previa_id = ? ORDER BY id";
    
    $stmt = $conn_pacientes->prepare($sql);
    $stmt->bind_param("i", $previaId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $ciclosDias = [];
    while ($row = $result->fetch_assoc()) {
        // Converter is_full_cycle para boolean para facilitar uso no frontend
        $row['fullCycle'] = (bool)$row['is_full_cycle'];
        $ciclosDias[] = $row;
    }
    
    echo json_encode($ciclosDias);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>