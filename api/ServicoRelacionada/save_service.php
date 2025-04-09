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

// Ativar relatório de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once("../../config.php");

    // Verifica se a conexão com o banco de dados foi estabelecida
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . ($conn->connect_error ?? "Conexão não estabelecida"));
    }

    // Obter dados brutos
    $rawData = file_get_contents("php://input");
    if (!$rawData) {
        throw new Exception("Nenhum dado recebido");
    }

    // Converter para JSON
    $data = json_decode($rawData);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg() . ", Raw data: " . substr($rawData, 0, 100));
    }

    // Log dos dados recebidos (remova em produção)
    error_log("Dados recebidos: " . print_r($data, true));

    // Verificar se os dados necessários foram recebidos
    if (!isset($data->Codigo_TUSS) || !isset($data->Descricao_Apresentacao)) {
        http_response_code(400);
        echo json_encode([
            "message" => "Dados incompletos. Código TUSS e Descrição são obrigatórios.",
            "received_data" => $data
        ]);
        exit;
    }

    // Preparar a consulta SQL para inserir dados
    $sql = "INSERT INTO dServicoRelacionada (
        Codigo_TUSS, 

        Descricao_Apresentacao, 
        Descricao_Resumida, 
        Descricao_Comercial, 
        Concentracao, 
        UnidadeFracionamento, 
        Fracionamento, 
        Laboratorio, 
        Revisado_Farma,
        idRegistroVisa, 
        idTabela, 
        idViaAdministracao, 
        idClasseFarmaceutica, 
        idPrincipioAtivo, 
        idArmazenamento, 
        idMedicamento, 
        idUnidadeFracionamento, 
        idFatorConversao, 
        idTaxas
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    // Converter valores nulos ou vazios para NULL no banco de dados
    $codigoTUSS = $data->Codigo_TUSS;
    $cod_Ggrem = isset($data->Cod_Ggrem) && $data->Cod_Ggrem !== "" ? $data->Cod_Ggrem : null;
    $descricaoApresentacao = $data->Descricao_Apresentacao;
    $descricaoResumida = isset($data->Descricao_Resumida) && $data->Descricao_Resumida !== "" ? $data->Descricao_Resumida : null;
    $descricaoComercial = isset($data->Descricao_Comercial) && $data->Descricao_Comercial !== "" ? $data->Descricao_Comercial : null;
    $concentracao = isset($data->Concentracao) && $data->Concentracao !== "" ? $data->Concentracao : null;
    $unidadeFracionamento = isset($data->UnidadeFracionamento) && $data->UnidadeFracionamento !== "" ? $data->UnidadeFracionamento : null;
    $fracionamento = isset($data->Fracionamento) && $data->Fracionamento !== "" ? $data->Fracionamento : null;
    $laboratorio = isset($data->Laboratorio) && $data->Laboratorio !== "" ? $data->Laboratorio : null;
    $revisado_Farma = isset($data->Revisado_Farma) && $data->Revisado_Farma !== "" ? (int)$data->Revisado_Farma : 0;
    $idRegistroVisa = isset($data->idRegistroVisa) && $data->idRegistroVisa !== "" ? (int)$data->idRegistroVisa : null;
    $idTabela = isset($data->idTabela) && $data->idTabela !== "" ? (int)$data->idTabela : null;
    $idViaAdministracao = isset($data->idViaAdministracao) && $data->idViaAdministracao !== "" ? (int)$data->idViaAdministracao : null;
    $idClasseFarmaceutica = isset($data->idClasseFarmaceutica) && $data->idClasseFarmaceutica !== "" ? (int)$data->idClasseFarmaceutica : null;
    $idPrincipioAtivo = isset($data->idPrincipioAtivo) && $data->idPrincipioAtivo !== "" ? (int)$data->idPrincipioAtivo : null;
    $idArmazenamento = isset($data->idArmazenamento) && $data->idArmazenamento !== "" ? (int)$data->idArmazenamento : null;
    $idMedicamento = isset($data->idMedicamento) && $data->idMedicamento !== "" ? (int)$data->idMedicamento : null;
    $idUnidadeFracionamento = isset($data->idUnidadeFracionamento) && $data->idUnidadeFracionamento !== "" ? (int)$data->idUnidadeFracionamento : null;
    $idFatorConversao = isset($data->idFatorConversao) && $data->idFatorConversao !== "" ? (int)$data->idFatorConversao : null;
    $idTaxas = isset($data->idTaxas) && $data->idTaxas !== "" ? (int)$data->idTaxas : null;

    $bindResult = $stmt->bind_param(
        "ssssssssiiiiiiiiiii",
        $codigoTUSS,
        //$cod_Ggrem,
        $descricaoApresentacao,
        $descricaoResumida,
        $descricaoComercial,
        $concentracao,
        $unidadeFracionamento,
        $fracionamento,
        $laboratorio,
        $revisado_Farma,
        $idRegistroVisa,
        $idTabela,
        $idViaAdministracao,
        $idClasseFarmaceutica,
        $idPrincipioAtivo,
        $idArmazenamento,
        $idMedicamento,
        $idUnidadeFracionamento,
        $idFatorConversao,
        $idTaxas
    );

    if (!$bindResult) {
        throw new Exception("Erro ao vincular parâmetros: " . $stmt->error);
    }

    // Executar a consulta
    if ($stmt->execute()) {
        $lastId = $conn->insert_id;
        http_response_code(201);
        echo json_encode(["message" => "Serviço criado com sucesso", "id" => $lastId]);
    } else {
        throw new Exception("Erro ao executar a consulta: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        "message" => "Não foi possível criar o serviço", 
        "error" => $e->getMessage(),
        "details" => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
        "sql_error" => $conn->error ?? "Não disponível",
        "request_data" => $data ?? null
    ]);
    error_log("Erro na API save_service.php: " . $e->getMessage());
}
?>