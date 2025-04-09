<?php
// Configurações de cabeçalho para permitir CORS e definir o tipo de resposta
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar relatório de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Incluir arquivo de configuração - caminho atualizado
    include_once("../../config.php");

    // Verifica se as conexões com os bancos de dados foram estabelecidas
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados.");
    }

    // Verificar se o ID foi fornecido
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "ID do paciente não fornecido"]);
        exit;
    }

    $id = intval($_GET['id']);

    // Iniciar transação
    $conn_pacientes->begin_transaction();

    // Preparar a query para excluir o paciente
    $sql = "DELETE FROM Pacientes WHERE id = ?";

    $stmt = $conn_pacientes->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn_pacientes->error);
    }

    $stmt->bind_param("i", $id);

    // Executar a consulta
    if (!$stmt->execute()) {
        throw new Exception("Erro ao excluir paciente: " . $stmt->error);
    }

    // Verificar se alguma linha foi afetada
    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode(["message" => "Paciente não encontrado"]);
        exit;
    }

    // Commitar a transação
    $conn_pacientes->commit();

    http_response_code(200);
    echo json_encode(["message" => "Paciente excluído com sucesso"]);

    $stmt->close();
    
} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn_pacientes) && !$conn_pacientes->connect_error) {
        $conn_pacientes->rollback();
    }
    
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao excluir paciente", 
        "error" => $e->getMessage()
    ]);
    error_log("Erro na API delete_paciente.php: " . $e->getMessage());
}

// Fechar conexões
if (isset($conn)) {
    $conn->close();
}
if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>