<?php
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
    include_once("../config.php");

    // Verifica se a conexão com o banco de dados foi estabelecida
    if (!isset($conn) || $conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . ($conn->connect_error ?? "Conexão não estabelecida"));
    }

    // Verificar se o ID foi fornecido
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "ID não fornecido"]);
        exit;
    }

    $id = intval($_GET['id']);

    // Preparar a consulta SQL para buscar o serviço
    $sql = "SELECT 
        id,
        Codigo_TUSS,
        Descricao_Apresentacao,
        Descricao_Resumida,
        Descricao_Comercial,
        Concentracao,
        UnidadeFracionamento,
        Fracionamento,
        Laboratorio,
        Revisado_Farma,
        
        -- IDs dos campos relacionados
        idViaAdministracao,
        idClasseFarmaceutica,
        idPrincipioAtivo,
        idArmazenamento,
        idMedicamento,
        idUnidadeFracionamento,
        idFatorConversao,
        idTaxas,
        
        -- Campos de RegistroVisa
        Cod_Ggrem,
        Principio_Ativo,
        Lab,
        cnpj_lab,
        Classe_Terapeutica,
        Tipo_Porduto,
        Regime_Preco,
        Restricao_Hosp,
        Cap,
        Confaz87,
        Icms0,
        Lista,
        Status,
        
        -- Campos de Tabela
        tabela,
        tabela_classe,
        tabela_tipo,
        classe_Jaragua_do_sul,
        classificacao_tipo,
        finalidade,
        objetivo,
        
        -- Campos informativos
        Via_administracao,
        ClasseFarmaceutica,
        PrincipioAtivo,
        PrincipioAtivoClassificado,
        FaseUGF,
        Armazenamento,
        tipo_medicamento,
        UnidadeFracionamentoDescricao,
        Divisor,
        tipo_taxa,
        TaxaFinalidade,
        tempo_infusao
        
    FROM dServicoRelacionada WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["message" => "Serviço não encontrado"]);
        exit;
    }

    $service = $result->fetch_assoc();
    
    // Adicionar informações complementares baseadas nos IDs selecionados
    
    // Via Administração
    if (!empty($service['idViaAdministracao'])) {
        $viaQuery = "SELECT Via_administracao FROM dViaadministracao WHERE idviaadministracao = ?";
        $viaStmt = $conn->prepare($viaQuery);
        $viaStmt->bind_param("i", $service['idViaAdministracao']);
        $viaStmt->execute();
        $viaResult = $viaStmt->get_result();
        if ($viaResult->num_rows > 0) {
            $viaRow = $viaResult->fetch_assoc();
            $service['Via_administracao'] = $viaRow['Via_administracao'];
        }
        $viaStmt->close();
    }
    
    // Classe Farmacêutica
    if (!empty($service['idClasseFarmaceutica'])) {
        $classeQuery = "SELECT ClasseFarmaceutica FROM dClasseFarmaceutica WHERE id_medicamento = ?";
        $classeStmt = $conn->prepare($classeQuery);
        $classeStmt->bind_param("i", $service['idClasseFarmaceutica']);
        $classeStmt->execute();
        $classeResult = $classeStmt->get_result();
        if ($classeResult->num_rows > 0) {
            $classeRow = $classeResult->fetch_assoc();
            $service['ClasseFarmaceutica'] = $classeRow['ClasseFarmaceutica'];
        }
        $classeStmt->close();
    }
    
    // Princípio Ativo
    if (!empty($service['idPrincipioAtivo'])) {
        $principioQuery = "SELECT PrincipioAtivo, PrincipioAtivoClassificado, FaseUGF FROM dPrincipioativo WHERE idPrincipioAtivo = ?";
        $principioStmt = $conn->prepare($principioQuery);
        $principioStmt->bind_param("i", $service['idPrincipioAtivo']);
        $principioStmt->execute();
        $principioResult = $principioStmt->get_result();
        if ($principioResult->num_rows > 0) {
            $principioRow = $principioResult->fetch_assoc();
            $service['PrincipioAtivo'] = $principioRow['PrincipioAtivo'];
            $service['PrincipioAtivoClassificado'] = $principioRow['PrincipioAtivoClassificado'];
            $service['FaseUGF'] = $principioRow['FaseUGF'];
        }
        $principioStmt->close();
    }
    
    // Armazenamento
    if (!empty($service['idArmazenamento'])) {
        $armazeamentoQuery = "SELECT Armazenamento FROM dArmazenamento WHERE idArmazenamento = ?";
        $armazenamentoStmt = $conn->prepare($armazeamentoQuery);
        $armazenamentoStmt->bind_param("i", $service['idArmazenamento']);
        $armazenamentoStmt->execute();
        $armazenamentoResult = $armazenamentoStmt->get_result();
        if ($armazenamentoResult->num_rows > 0) {
            $armazenamentoRow = $armazenamentoResult->fetch_assoc();
            $service['Armazenamento'] = $armazenamentoRow['Armazenamento'];
        }
        $armazenamentoStmt->close();
    }
    
    // Tipo Medicamento
    if (!empty($service['idMedicamento'])) {
        $medicamentoQuery = "SELECT tipo_medicamento FROM dTipo_medicamento WHERE id_medicamento = ?";
        $medicamentoStmt = $conn->prepare($medicamentoQuery);
        $medicamentoStmt->bind_param("i", $service['idMedicamento']);
        $medicamentoStmt->execute();
        $medicamentoResult = $medicamentoStmt->get_result();
        if ($medicamentoResult->num_rows > 0) {
            $medicamentoRow = $medicamentoResult->fetch_assoc();
            $service['tipo_medicamento'] = $medicamentoRow['tipo_medicamento'];
        }
        $medicamentoStmt->close();
    }
    
    // Unidade de Fracionamento
    if (!empty($service['idUnidadeFracionamento'])) {
        $unidadeQuery = "SELECT UnidadeFracionamento, Descricao, Divisor FROM dUnidadeFracionamento WHERE id_unidadefracionamento = ?";
        $unidadeStmt = $conn->prepare($unidadeQuery);
        $unidadeStmt->bind_param("i", $service['idUnidadeFracionamento']);
        $unidadeStmt->execute();
        $unidadeResult = $unidadeStmt->get_result();
        if ($unidadeResult->num_rows > 0) {
            $unidadeRow = $unidadeResult->fetch_assoc();
            $service['UnidadeFracionamento'] = $unidadeRow['UnidadeFracionamento'];
            $service['UnidadeFracionamentoDescricao'] = $unidadeRow['Descricao'];
            $service['Divisor'] = $unidadeRow['Divisor'];
        }
        $unidadeStmt->close();
    }
    
    // Fator de Conversão
    if (!empty($service['idFatorConversao'])) {
        $fatorQuery = "SELECT fator FROM dFatorConversao WHERE id_fatorconversao = ?";
        $fatorStmt = $conn->prepare($fatorQuery);
        $fatorStmt->bind_param("i", $service['idFatorConversao']);
        $fatorStmt->execute();
        $fatorResult = $fatorStmt->get_result();
        if ($fatorResult->num_rows > 0) {
            $fatorRow = $fatorResult->fetch_assoc();
            $service['Fator_Conversão'] = $fatorRow['fator'];
        }
        $fatorStmt->close();
    }
    
    // Taxas
    if (!empty($service['idTaxas'])) {
        $taxasQuery = "SELECT finalidade, tipo_taxa, tempo_infusao FROM dTaxas WHERE id_taxas = ?";
        $taxasStmt = $conn->prepare($taxasQuery);
        $taxasStmt->bind_param("i", $service['idTaxas']);
        $taxasStmt->execute();
        $taxasResult = $taxasStmt->get_result();
        if ($taxasResult->num_rows > 0) {
            $taxasRow = $taxasResult->fetch_assoc();
            $service['TaxaFinalidade'] = $taxasRow['finalidade'];
            $service['tipo_taxa'] = $taxasRow['tipo_taxa'];
            $service['tempo_infusao'] = $taxasRow['tempo_infusao'];
        }
        $taxasStmt->close();
    }

    // Retornar o serviço
    http_response_code(200);
    echo json_encode($service);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao buscar o serviço", 
        "error" => $e->getMessage()
    ]);
    error_log("Erro na API get_service_by_id.php: " . $e->getMessage());
}
?>