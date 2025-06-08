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
    // Verificar a conexão com o banco de dados
    if ($conn_pacientes->connect_error) {
        http_response_code(500);
        echo json_encode(["error" => "Falha na conexão com o banco de dados: " . $conn_pacientes->connect_error]);
        exit;
    }
    // Iniciar transação
    $conn_pacientes->begin_transaction();
    // Obter dados do corpo da requisição
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!$data || !isset($data['id'])) {
        throw new Exception("Dados inválidos ou ID da prévia não fornecido");
    }
    
    // Preparar a atualização da prévia com o novo campo finalizacao
    $sql = "UPDATE previas SET
        guia = ?, 
        protocolo = ?, 
        cid = ?, 
        data_emissao_guia = ?,
        data_encaminhamento_af = ?,
        data_solicitacao = ?, 
        parecer = ?, 
        peso = ?, 
        altura = ?, 
        parecer_guia = ?, 
        finalizacao = ?,
        inconsistencia = ?, 
        data_parecer_registrado = ?, 
        tempo_analise = ?
    WHERE id = ?";
    
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
    
    // Validação para parecer_guia
    $parecerGuia = NULL;
    if (isset($data['parecer_guia']) && !empty($data['parecer_guia'])) {
        if (in_array($data['parecer_guia'], ['Favorável', 'Favorável com Inconsistência', 'Inconclusivo', 'Desfavorável'])) {
            $parecerGuia = $data['parecer_guia'];
        }
    }

    // NOVO: Validação para finalizacao
    $finalizacao = NULL;
    if (isset($data['finalizacao']) && !empty($data['finalizacao'])) {
        if (in_array($data['finalizacao'], ['Favorável', 'Favorável com Inconsistência', 'Inconclusivo', 'Desfavorável'])) {
            $finalizacao = $data['finalizacao'];
        }
    }
    
    // Validação para inconsistencia
    $inconsistencia = NULL;
    if (isset($data['inconsistencia']) && !empty($data['inconsistencia'])) {
        if (in_array($data['inconsistencia'], ['Completa', 'Dados Faltantes', 'Requer Análise', 'Informações Inconsistentes'])) {
            $inconsistencia = $data['inconsistencia'];
        }
    }
    
    // Bind dos parâmetros incluindo o novo campo finalizacao
    $stmt->bind_param(
        "ssssssssddsssssi",
        $data['guia'],
        $data['protocolo'],
        $data['cid'],
        $dataEmissaoGuia,
        $dataEncaminhamentoAF,
        $dataSolicitacao,
        $data['parecer'],
        $data['peso'],
        $data['altura'],
        $parecerGuia,
        $finalizacao,          // NOVO CAMPO
        $inconsistencia,
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
    echo json_encode(["error" => "Erro detalhado: " . $e->getMessage()]);
}
if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>