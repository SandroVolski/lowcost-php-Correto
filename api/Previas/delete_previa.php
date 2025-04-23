<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");

    // Verificar parâmetro de ID
    if (!isset($_GET['id'])) {
        throw new Exception("ID da prévia não fornecido");
    }
    
    $previaId = intval($_GET['id']);
    
    // Iniciar transação
    $conn_pacientes->begin_transaction();
    
    // Excluir os ciclos/dias relacionados
    $sqlCiclos = "DELETE FROM previa_ciclos_dias WHERE previa_id = ?";
    $stmtCiclos = $conn_pacientes->prepare($sqlCiclos);
    $stmtCiclos->bind_param("i", $previaId);
    $stmtCiclos->execute();
    
    // Excluir os anexos relacionados (incluindo arquivos físicos)
    $sqlAnexosFetch = "SELECT id, caminho_arquivo FROM previa_anexos WHERE previa_id = ?";
    $stmtAnexosFetch = $conn_pacientes->prepare($sqlAnexosFetch);
    $stmtAnexosFetch->bind_param("i", $previaId);
    $stmtAnexosFetch->execute();
    $resultAnexos = $stmtAnexosFetch->get_result();
    
    while ($row = $resultAnexos->fetch_assoc()) {
        // Excluir o arquivo físico se existir
        if (file_exists($row['caminho_arquivo'])) {
            unlink($row['caminho_arquivo']);
        }
    }
    
    $sqlAnexos = "DELETE FROM previa_anexos WHERE previa_id = ?";
    $stmtAnexos = $conn_pacientes->prepare($sqlAnexos);
    $stmtAnexos->bind_param("i", $previaId);
    $stmtAnexos->execute();
    
    // Excluir a prévia
    $sql = "DELETE FROM previas WHERE id = ?";
    $stmt = $conn_pacientes->prepare($sql);
    $stmt->bind_param("i", $previaId);
    $stmt->execute();
    
    // Verificar se alguma linha foi afetada
    if ($stmt->affected_rows === 0) {
        throw new Exception("Prévia não encontrada");
    }
    
    // Commit da transação
    $conn_pacientes->commit();
    
    http_response_code(200);
    echo json_encode(["message" => "Prévia excluída com sucesso"]);
    
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