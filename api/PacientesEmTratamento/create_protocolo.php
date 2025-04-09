// Arquivo: api/create_protocolo.php
<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar log detalhado de erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once("../../config.php");

    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . ($conn->connect_error ?? "Conexão não estabelecida"));
    }

    // Iniciar transação
    $conn->begin_transaction();

    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents("php://input"));
    
    if (!$data) {
        throw new Exception("Dados inválidos ou não fornecidos");
    }

    // Verificar campos obrigatórios
    if (!isset($data->Protocolo_Nome) || !isset($data->Protocolo_Sigla)) {
        throw new Exception("Campos obrigatórios não fornecidos");
    }

    $sql = "INSERT INTO Protocolo (
                Servico_Codigo,
                Protocolo_Nome,
                Protocolo_Sigla,
                Protocolo_Dose_M,
                Protocolo_Dose_Total,
                Protocolo_Dias_de_Aplicacao,
                Protocolo_ViaAdm,
                Linha
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    // Tratamento para valores nulos ou vazios
    $servicoCodigo = isset($data->Servico_Codigo) && !empty($data->Servico_Codigo) ? (int)$data->Servico_Codigo : null;
    $protocoloNome = $data->Protocolo_Nome;
    $protocoloSigla = $data->Protocolo_Sigla;
    $protocoloDoseM = isset($data->Protocolo_Dose_M) && !empty($data->Protocolo_Dose_M) ? $data->Protocolo_Dose_M : null;
    $protocoloDoseTotal = isset($data->Protocolo_Dose_Total) && !empty($data->Protocolo_Dose_Total) ? (int)$data->Protocolo_Dose_Total : null;
    $protocoloDiasAplicacao = isset($data->Protocolo_Dias_de_Aplicacao) && !empty($data->Protocolo_Dias_de_Aplicacao) ? $data->Protocolo_Dias_de_Aplicacao : null;
    $protocoloViaAdm = isset($data->Protocolo_ViaAdm) && !empty($data->Protocolo_ViaAdm) ? (int)$data->Protocolo_ViaAdm : null;
    $linha = isset($data->Linha) && !empty($data->Linha) ? (int)$data->Linha : null;

    $stmt->bind_param(
        "isssiiii", 
        $servicoCodigo, 
        $protocoloNome, 
        $protocoloSigla, 
        $protocoloDoseM, 
        $protocoloDoseTotal,
        $protocoloDiasAplicacao,
        $protocoloViaAdm,
        $linha
    );

    if (!$stmt->execute()) {
        throw new Exception("Erro ao inserir protocolo: " . $stmt->error);
    }

    $newId = $conn->insert_id;

    // Commit a transação
    $conn->commit();

    // Retornar sucesso
    http_response_code(201);
    echo json_encode([
        "message" => "Protocolo criado com sucesso",
        "id" => $newId
    ]);

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn) && !$conn->connect_error) {
        $conn->rollback();
    }
    
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    error_log("Erro em create_protocolo.php: " . $e->getMessage());
}

if (isset($conn)) {
    $conn->close();
}
?>