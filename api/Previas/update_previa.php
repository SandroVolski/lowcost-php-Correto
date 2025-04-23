<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");

    // Iniciar transação
    $conn_pacientes->begin_transaction();

    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['id'])) {
        throw new Exception("Dados inválidos ou ID da prévia não fornecido");
    }
    
    // Preparar a atualização da prévia
    $sql = "UPDATE previas SET
        guia = ?, 
        protocolo = ?, 
        cid = ?, 
        data_solicitacao = ?, 
        parecer = ?, 
        peso = ?, 
        altura = ?, 
        parecer_guia = ?, 
        inconsistencia = ?, 
        data_parecer_registrado = ?, 
        tempo_analise = ?
    WHERE id = ?";
    
    $stmt = $conn_pacientes->prepare($sql);
    
    // Formatação de data: converter de DD/MM/YYYY para YYYY-MM-DD para MySQL
    $dataSolicitacao = NULL;
    if (isset($data['data_solicitacao']) && !empty($data['data_solicitacao'])) {
        $dateParts = explode('/', $data['data_solicitacao']);
        if (count($dateParts) === 3) {
            $dataSolicitacao = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
        }
    }
    
    $dataParecerRegistrado = NULL;
    if (isset($data['data_parecer_registrado']) && !empty($data['data_parecer_registrado'])) {
        $dateParts = explode('/', $data['data_parecer_registrado']);
        if (count($dateParts) === 3) {
            $dataParecerRegistrado = $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
        }
    }
    
    $stmt->bind_param(
        "ssssddsssi",
        $data['guia'],
        $data['protocolo'],
        $data['cid'],
        $dataSolicitacao,
        $data['parecer'],
        $data['peso'],
        $data['altura'],
        $data['parecer_guia'],
        $data['inconsistencia'],
        $dataParecerRegistrado,
        $data['tempo_analise'],
        $data['id']
    );
    
    $stmt->execute();
    
    // Atualizar ciclos/dias (opcionalmente)
    if (isset($data['ciclos_dias']) && is_array($data['ciclos_dias'])) {
        // Primeiro, excluir os ciclos existentes
        $deleteQuery = "DELETE FROM previa_ciclos_dias WHERE previa_id = ?";
        $deleteStmt = $conn_pacientes->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $data['id']);
        $deleteStmt->execute();
        
        // Depois, inserir os novos
        $cicloSql = "INSERT INTO previa_ciclos_dias (
            previa_id, 
            ciclo, 
            dia, 
            protocolo, 
            is_full_cycle
        ) VALUES (?, ?, ?, ?, ?)";
        
        $cicloStmt = $conn_pacientes->prepare($cicloSql);
        
        foreach ($data['ciclos_dias'] as $cicloDia) {
            $isFullCycle = isset($cicloDia['fullCycle']) ? (int)$cicloDia['fullCycle'] : 0;
            
            $cicloStmt->bind_param(
                "isssi",
                $data['id'],
                $cicloDia['ciclo'],
                $cicloDia['dia'],
                $cicloDia['protocolo'],
                $isFullCycle
            );
            
            $cicloStmt->execute();
        }
    }
    
    // Commit da transação
    $conn_pacientes->commit();
    
    http_response_code(200);
    echo json_encode([
        "message" => "Prévia atualizada com sucesso",
        "id" => $data['id']
    ]);
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn_pacientes) && !$conn_pacientes->connect_error) {
        $conn_pacientes->rollback();
    }
    
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>