<?php
// Configurações de cabeçalho para permitir CORS e definir o tipo de resposta
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
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
    if (!isset($conn) || $conn->connect_error || !isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados.");
    }

    // Verificar se o ID foi fornecido
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "ID do paciente não fornecido"]);
        exit;
    }

    $id = intval($_GET['id']);

    // Query para buscar os dados do paciente específico - com os nomes corretos das colunas
    $sql = "
    SELECT 
        p.id,
        o.Nome_Fantasia AS Operadora,
        e.Prestador_Nome,
        e.Prestador_Nome_Fantasia,
        p.Codigo AS Paciente_Codigo,
        p.Paciente_Nome AS Nome,
        p.Data_Nascimento AS Nascimento,
        TIMESTAMPDIFF(YEAR, p.Data_Nascimento, CURDATE()) AS Idade,
        p.Sexo,
        p.Data_Inicio_Tratamento,
        p.Cid_Diagnostico AS CID,
        '' AS Indicao_Clinica,
        '' AS T,
        '' AS N,
        '' AS M,
        '' AS Estadio,
        '' AS Finalidade,
        '' AS CRM_Medico,
        '' AS Local_das_Metastases
    FROM 
        bd_pacientestto.Pacientes p
    LEFT JOIN 
        bd_servico.bd_producaom_operadoras o ON p.Operadora = o.id
    LEFT JOIN 
        bd_servico.bd_empresas_empresas e ON p.Prestador = e.id
    WHERE 
        p.id = ?
    ";

    // Preparar e executar a consulta
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["message" => "Paciente não encontrado"]);
        exit;
    }

    $paciente = $result->fetch_assoc();
    
    // Formatar a data de nascimento se não for nula
    if (!empty($paciente['Nascimento'])) {
        $date = new DateTime($paciente['Nascimento']);
        $paciente['Nascimento'] = $date->format('d/m/Y');
    }
    
    // Formatar a data de início de tratamento se não for nula
    if (!empty($paciente['Data_Inicio_Tratamento'])) {
        $date = new DateTime($paciente['Data_Inicio_Tratamento']);
        $paciente['Data_Inicio_Tratamento'] = $date->format('d/m/Y');
    }

    http_response_code(200);
    echo json_encode($paciente);

    $stmt->close();
    
} catch (Exception $e) {
    error_log("Erro em get_paciente_by_id.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao buscar o paciente", 
        "error" => $e->getMessage()
    ]);
}

// Fechar conexões
if (isset($conn)) {
    $conn->close();
}
if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>