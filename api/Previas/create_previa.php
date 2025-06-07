<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

    if (!$data || !isset($data['paciente_id'])) {
        throw new Exception("Dados inválidos ou ID do paciente não fornecido");
    }

    // Obter o próximo número sequencial para este paciente
    $seqQuery = "SELECT MAX(numero_sequencial) as max_seq FROM previas WHERE paciente_id = ?";
    $seqStmt = $conn_pacientes->prepare($seqQuery);
    $seqStmt->bind_param("i", $data['paciente_id']);
    $seqStmt->execute();
    $seqResult = $seqStmt->get_result();
    $seqRow = $seqResult->fetch_assoc();

    $numeroSequencial = ($seqRow['max_seq'] ?? 0) + 1;
    $codigoComposto = $data['paciente_id'] . '-' . str_pad($numeroSequencial, 3, '0', STR_PAD_LEFT);

    // Preparar a inserção da prévia com os novos campos
    $sql = "INSERT INTO previas (
        paciente_id, 
        numero_sequencial, 
        codigo_composto, 
        guia, 
        protocolo, 
        cid, 
        data_emissao_guia,
        data_encaminhamento_af,
        data_solicitacao, 
        parecer, 
        peso, 
        altura, 
        parecer_guia, 
        inconsistencia, 
        data_parecer_registrado, 
        tempo_analise
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn_pacientes->prepare($sql);

    // Função helper para converter data DD/MM/YYYY para YYYY-MM-DD
    function convertDateToMysql($dateString) {
        if (!$dateString || empty($dateString)) {
            return NULL;
        }
        $dateParts = explode('/', $dateString);
        if (count($dateParts) === 3) {
            return $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0];
        }
        return NULL;
    }

    // Converter as datas para formato MySQL
    $dataEmissaoGuia = convertDateToMysql($data['data_emissao_guia'] ?? '');
    $dataEncaminhamentoAF = convertDateToMysql($data['data_encaminhamento_af'] ?? '');
    $dataSolicitacao = convertDateToMysql($data['data_solicitacao'] ?? '');
    $dataParecerRegistrado = convertDateToMysql($data['data_parecer_registrado'] ?? '');

    // CORREÇÃO: Definir inconsistencia como NULL se estiver vazio
    $inconsistencia = NULL;
    if (isset($data['inconsistencia']) && !empty($data['inconsistencia'])) {
        if (in_array($data['inconsistencia'], ['Completa', 'Dados Faltantes', 'Requer Análise', 'Informações Inconsistentes'])) {
            $inconsistencia = $data['inconsistencia'];
        }
    }

    $stmt->bind_param(
        "iissssssssddsssi",
        $data['paciente_id'],
        $numeroSequencial,
        $codigoComposto,
        $data['guia'],
        $data['protocolo'],
        $data['cid'],
        $dataEmissaoGuia,
        $dataEncaminhamentoAF,
        $dataSolicitacao,
        $data['parecer'],
        $data['peso'],
        $data['altura'],
        $data['parecer_guia'],
        $inconsistencia,
        $dataParecerRegistrado,
        $data['tempo_analise']
    );

    $stmt->execute();
    $previaId = $conn_pacientes->insert_id;

    // Inserir ciclos/dias da prévia
    if (isset($data['ciclos_dias']) && is_array($data['ciclos_dias'])) {
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
                $previaId,
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

    http_response_code(201);
    echo json_encode([
        "message" => "Prévia criada com sucesso",
        "id" => $previaId,
        "codigo_composto" => $codigoComposto,
        "numero_sequencial" => $numeroSequencial
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