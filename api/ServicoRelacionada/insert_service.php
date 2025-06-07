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

    // Iniciar transação
    $conn->begin_transaction();

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
    if (!isset($data->Cod) || empty($data->Cod)) {
        throw new Exception("Dados incompletos. Código é obrigatório.");
    }

    // Variável para armazenar o ID do RegistroVisa (se existir)
    $idRegistroVisa = null;

    // Verificar se temos dados de RegistroVisa para inserir
    $hasRegistroVisaData = isset($data->RegistroVisa) && !empty($data->RegistroVisa);
    
    if ($hasRegistroVisaData) {
        // Verificar primeiro se o RegistroVisa já existe
        $checkSql = "SELECT RegistroVisa FROM dRegistro_anvisa WHERE RegistroVisa = ?";
        $checkStmt = $conn->prepare($checkSql);
        if (!$checkStmt) {
            throw new Exception("Erro ao preparar a consulta para verificar RegistroVisa: " . $conn->error);
        }
        
        $checkStmt->bind_param("s", $data->RegistroVisa);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            // O RegistroVisa já existe, não precisamos inserir novamente
            $idRegistroVisa = $data->RegistroVisa;
            $checkStmt->close();
        } else {
            $checkStmt->close();
            
            // Inserir na tabela dregistro_anvisa primeiro
            $sqlRegistro = "INSERT INTO dRegistro_anvisa (
                RegistroVisa,
                Cod_Ggrem,
                PrincipioAtivo,
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
                Status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmtRegistro = $conn->prepare($sqlRegistro);
            if (!$stmtRegistro) {
                throw new Exception("Erro ao preparar a consulta para o RegistroVisa: " . $conn->error);
            }

            // Converter valores vazios para strings vazias (não NULL) para campos NOT NULL
            $registroVisa = $data->RegistroVisa;
            $codGgrem = isset($data->Cod_Ggrem) && $data->Cod_Ggrem !== "" ? $data->Cod_Ggrem : '';
            $principioAtivo = isset($data->PrincipioAtivo) && $data->PrincipioAtivo !== "" ? $data->PrincipioAtivo : 'Não informado';
            $lab = isset($data->Lab) && $data->Lab !== "" ? $data->Lab : '';
            $cnpjLab = isset($data->cnpj_lab) && $data->cnpj_lab !== "" ? $data->cnpj_lab : '';
            $classeTerapeutica = isset($data->Classe_Terapeutica) && $data->Classe_Terapeutica !== "" ? $data->Classe_Terapeutica : '';
            $tipoProduto = isset($data->Tipo_Porduto) && $data->Tipo_Porduto !== "" ? $data->Tipo_Porduto : '';
            $regimePreco = isset($data->Regime_Preco) && $data->Regime_Preco !== "" ? $data->Regime_Preco : '';
            $restricaoHosp = isset($data->Restricao_Hosp) && $data->Restricao_Hosp !== "" ? $data->Restricao_Hosp : '';
            $cap = isset($data->Cap) && $data->Cap !== "" ? $data->Cap : '';
            $confaz87 = isset($data->Confaz87) && $data->Confaz87 !== "" ? $data->Confaz87 : '';
            $icms0 = isset($data->Icms0) && $data->Icms0 !== "" ? $data->Icms0 : '';
            $lista = isset($data->Lista) && $data->Lista !== "" ? $data->Lista : '';
            $status = isset($data->Status) && $data->Status !== "" ? $data->Status : '';

            $bindResult = $stmtRegistro->bind_param(
                "ssssssssssssss",
                $registroVisa,
                $codGgrem,
                $principioAtivo,
                $lab,
                $cnpjLab,
                $classeTerapeutica,
                $tipoProduto,
                $regimePreco,
                $restricaoHosp,
                $cap,
                $confaz87,
                $icms0,
                $lista,
                $status
            );

            if (!$bindResult) {
                throw new Exception("Erro ao vincular parâmetros do RegistroVisa: " . $stmtRegistro->error);
            }

            // Executar a consulta para inserir o registro
            if (!$stmtRegistro->execute()) {
                throw new Exception("Erro ao inserir o RegistroVisa: " . $stmtRegistro->error);
            }

            // Obter o ID do RegistroVisa recém-inserido
            $idRegistroVisa = $registroVisa;
            
            $stmtRegistro->close();
        }
    }

    // Agora inserir na tabela dservicorelacionada
    // Atualizando a query para incluir os novos campos
    $sql = "INSERT INTO dServicoRelacionada (
        Cod,                         
        Codigo_TUSS,                 
        Codigo_Celos,                
        Descricao_Padronizada,      
        Descricao_Resumida,          
        Descricao_Comercial,         
        Descricao_Comercial_Completa,
        Descricao_TUSS,              
        Concentracao,                
        UnidadeFracionamento,        
        Fracionamento,               
        Laboratorio,                 
        Uso,                         
        Revisado_Farma,              
        Revisado_ADM,                
        idViaAdministracao,          
        idClasseFarmaceutica,        
        idPrincipioAtivo,            
        idArmazenamento,             
        idMedicamento,               
        idUnidadeFracionamento,      
        idFatorConversao,            
        idTaxas,                     
        idRegistroVisa,              
        idTabela,                    
        -- Novos campos
        Unidade_Entrada,
        Quantidade_Entrada,
        Unidade_Entrada_Convertida,
        Quantidade_Convertida,
        Unidade_Pagamento_Nao_Fracionado,
        Quantidade_Pagamento_Nao_Fracionado,
        Unidade_Pagamento_Fracionado,
        Quantidade_Pagamento_Fracionado
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    // Converter valores nulos ou vazios para NULL no banco de dados
    $cod = $data->Cod;
    $codigoTUSS = isset($data->Codigo_TUSS) && $data->Codigo_TUSS !== "" ? $data->Codigo_TUSS : null;
    $codigoCelos = isset($data->Codigo_Celos) && $data->Codigo_Celos !== "" ? $data->Codigo_Celos : null;
    $descricaoPadronizada = isset($data->Descricao_Padronizada) && $data->Descricao_Padronizada !== "" ? $data->Descricao_Padronizada : null;
    $descricaoResumida = isset($data->Descricao_Resumida) && $data->Descricao_Resumida !== "" ? $data->Descricao_Resumida : null;
    $descricaoComercial = isset($data->Descricao_Comercial) && $data->Descricao_Comercial !== "" ? $data->Descricao_Comercial : null;
    $descricaoComercialCompleta = isset($data->Descricao_Comercial_Completa) && $data->Descricao_Comercial_Completa !== "" ? $data->Descricao_Comercial_Completa : null;
    $descricaoTUSS = isset($data->Descricao_TUSS) && $data->Descricao_TUSS !== "" ? $data->Descricao_TUSS : null;
    $concentracao = isset($data->Concentracao) && $data->Concentracao !== "" ? $data->Concentracao : null;
    $unidadeFracionamento = isset($data->UnidadeFracionamento) && $data->UnidadeFracionamento !== "" ? $data->UnidadeFracionamento : null;
    $fracionamento = isset($data->Fracionamento) && $data->Fracionamento !== "" ? $data->Fracionamento : null;
    $laboratorio = isset($data->Laboratorio) && $data->Laboratorio !== "" ? $data->Laboratorio : null;
    $uso = isset($data->Uso) && $data->Uso !== "" ? $data->Uso : null;
    $revisado_Farma = isset($data->Revisado_Farma) && $data->Revisado_Farma !== "" ? intval($data->Revisado_Farma) : 0;
    $revisado_ADM = isset($data->Revisado_ADM) && $data->Revisado_ADM !== "" ? $data->Revisado_ADM : null;
    
    // IDs dos campos relacionados
    $idViaAdministracao = isset($data->idViaAdministracao) && $data->idViaAdministracao !== "" ? intval($data->idViaAdministracao) : null;
    $idClasseFarmaceutica = isset($data->idClasseFarmaceutica) && $data->idClasseFarmaceutica !== "" ? intval($data->idClasseFarmaceutica) : null;
    $idPrincipioAtivo = isset($data->idPrincipioAtivo) && $data->idPrincipioAtivo !== "" ? intval($data->idPrincipioAtivo) : null;
    $idArmazenamento = isset($data->idArmazenamento) && $data->idArmazenamento !== "" ? intval($data->idArmazenamento) : null;
    $idMedicamento = isset($data->idMedicamento) && $data->idMedicamento !== "" ? intval($data->idMedicamento) : null;
    $idUnidadeFracionamento = isset($data->idUnidadeFracionamento) && $data->idUnidadeFracionamento !== "" ? intval($data->idUnidadeFracionamento) : null;
    $idFatorConversao = isset($data->idFatorConversao) && $data->idFatorConversao !== "" ? intval($data->idFatorConversao) : null;
    $idTaxas = isset($data->idTaxas) && $data->idTaxas !== "" ? intval($data->idTaxas) : null;
    $idTabela = isset($data->idTabela) && $data->idTabela !== "" ? intval($data->idTabela) : null;
    
    $unidadeEntrada = isset($data->Unidade_Entrada) ? $data->Unidade_Entrada : '';
    $quantidadeEntrada = isset($data->Quantidade_Entrada) ? $data->Quantidade_Entrada : null;
    $unidadeEntradaConvertida = isset($data->Unidade_Entrada_Convertida) ? $data->Unidade_Entrada_Convertida : '';
    $quantidadeConvertida = isset($data->Quantidade_Convertida) ? $data->Quantidade_Convertida : null;
    $unidadePagamentoNaoFracionado = isset($data->Unidade_Pagamento_Nao_Fracionado) ? $data->Unidade_Pagamento_Nao_Fracionado : '';
    $quantidadePagamentoNaoFracionado = isset($data->Quantidade_Pagamento_Nao_Fracionado) ? $data->Quantidade_Pagamento_Nao_Fracionado : null;
    $unidadePagamentoFracionado = isset($data->Unidade_Pagamento_Fracionado) ? $data->Unidade_Pagamento_Fracionado : '';
    $quantidadePagamentoFracionado = isset($data->Quantidade_Pagamento_Fracionado) ? $data->Quantidade_Pagamento_Fracionado : null;

    
    // Criar string de tipos para os parâmetros
    // 16 strings + 9 inteiros = 25 tipos
    $types = str_repeat('s', 16) . str_repeat('i', 9) . str_repeat('s', 8); // 16 strings + 9 inteiros + 8 novas strings = 33 tipos

    error_log("String de tipos atualizada: $types");
    error_log("Comprimento da string de tipos atualizada: " . strlen($types));
    error_log("Contagem de parâmetros esperada: 33"); // Agora são 33 parâmetros
    
    // Verificar comprimento da string de tipos
    if (strlen($types) !== 33) {
        error_log("ERRO: O comprimento da string de tipos não corresponde ao número esperado de parâmetros!");
    }
    
    // Verificar o tipo de idRegistroVisa
    if (!is_null($idRegistroVisa)) {
        error_log("idRegistroVisa: $idRegistroVisa (tipo: " . gettype($idRegistroVisa) . ")");
        
        // Se idRegistroVisa for uma string, converta-a para inteiro
        if (is_string($idRegistroVisa)) {
            $idRegistroVisa = intval($idRegistroVisa);
            error_log("idRegistroVisa convertido para inteiro: $idRegistroVisa");
        }
    } else {
        error_log("idRegistroVisa é NULL");
    }
    
    $bindResult = $stmt->bind_param(
        $types, 
        $cod,
        $codigoTUSS,
        $codigoCelos,
        $descricaoPadronizada,
        $descricaoResumida,
        $descricaoComercial,
        $descricaoComercialCompleta,
        $descricaoTUSS,
        $concentracao,
        $unidadeFracionamento,
        $fracionamento,
        $laboratorio,
        $uso,
        $revisado_Farma,
        $revisado_ADM,
        $idViaAdministracao,
        $idClasseFarmaceutica,
        $idPrincipioAtivo,
        $idArmazenamento,
        $idMedicamento,
        $idUnidadeFracionamento,
        $idFatorConversao,
        $idTaxas,
        $idRegistroVisa,
        $idTabela,
        // Novos campos
        $unidadeEntrada,
        $quantidadeEntrada,
        $unidadeEntradaConvertida,
        $quantidadeConvertida,
        $unidadePagamentoNaoFracionado,
        $quantidadePagamentoNaoFracionado,
        $unidadePagamentoFracionado,
        $quantidadePagamentoFracionado
    );

    if (!$bindResult) {
        throw new Exception("Erro ao vincular parâmetros: " . $stmt->error);
    }

    // Executar a consulta
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar a consulta: " . $stmt->error);
    }

    $lastId = $conn->insert_id;
    
    // Commit a transação
    $conn->commit();
    
    http_response_code(201);
    echo json_encode([
        "message" => "Serviço criado com sucesso", 
        "id" => $lastId,
        "cod" => $cod, // Inclui o código na resposta
        "registroVisa" => $hasRegistroVisaData ? $idRegistroVisa : null
    ]);
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn) && !$conn->connect_error) {
        $conn->rollback();
    }
    
    http_response_code(503);
    echo json_encode([
        "message" => "Não foi possível criar o serviço", 
        "error" => $e->getMessage(),
        "details" => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
        "sql_error" => isset($conn) ? $conn->error : "Não disponível",
        "request_data" => $data ?? null
    ]);
    error_log("Erro na API insert_service.php: " . $e->getMessage());
}
?>