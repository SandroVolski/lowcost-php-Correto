<?php
// Sempre enviar cabeçalhos CORS primeiro, antes de qualquer saída
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Max-Age: 3600"); // Cache preflight por 1 hora

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Apenas retornar os cabeçalhos e status HTTP 200
    header("Content-Type: application/json; charset=UTF-8");
    http_response_code(200);
    exit;
}

// Após tratar OPTIONS, definir Content-Type para outras requisições
header("Content-Type: application/json; charset=UTF-8");

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
    error_log("Dados recebidos para atualização: " . print_r($data, true));

    // Verificar se o ID foi fornecido
    if (!isset($data->id) || empty($data->id)) {
        throw new Exception("ID do serviço não fornecido");
    }

    // IMPORTANTE: Remoção da validação obrigatória de Codigo_TUSS e Descricao_Apresentacao
    // Agora apenas o ID é obrigatório para atualização, como deve ser

    // Variável para armazenar o ID do RegistroVisa (se existir)
    $idRegistroVisa = null;

    // Verificar se temos dados de RegistroVisa para atualizar
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
            // O RegistroVisa já existe, vamos atualizá-lo
            $idRegistroVisa = $data->RegistroVisa;
            $checkStmt->close();
            
            // Atualizar na tabela dregistro_anvisa
            $sqlRegistro = "UPDATE dRegistro_anvisa SET
                Cod_Ggrem = ?,
                PrincipioAtivo = ?,
                Lab = ?,
                cnpj_lab = ?,
                Classe_Terapeutica = ?,
                Tipo_Porduto = ?,
                Regime_Preco = ?,
                Restricao_Hosp = ?,
                Cap = ?,
                Confaz87 = ?,
                Icms0 = ?,
                Lista = ?,
                Status = ?
            WHERE RegistroVisa = ?";

            $stmtRegistro = $conn->prepare($sqlRegistro);
            if (!$stmtRegistro) {
                throw new Exception("Erro ao preparar a consulta para atualizar o RegistroVisa: " . $conn->error);
            }

            // Converter valores vazios para strings vazias (não NULL) para campos NOT NULL
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
            $registroVisa = $data->RegistroVisa;

            $bindResult = $stmtRegistro->bind_param(
                "ssssssssssssss",
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
                $status,
                $registroVisa
            );

            if (!$bindResult) {
                throw new Exception("Erro ao vincular parâmetros para atualizar o RegistroVisa: " . $stmtRegistro->error);
            }

            // Executar a consulta para atualizar o registro
            if (!$stmtRegistro->execute()) {
                throw new Exception("Erro ao atualizar o RegistroVisa: " . $stmtRegistro->error);
            }
            
            $stmtRegistro->close();
        } else {
            $checkStmt->close();
            
            // Inserir novo registro na tabela dregistro_anvisa
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

    // Agora atualizar na tabela dservicorelacionada
    $sql = "UPDATE dServicoRelacionada SET
        Cod = ?,
        Codigo_TUSS = ?,
        Descricao_Apresentacao = ?,
        Descricao_Resumida = ?,
        Descricao_Comercial = ?,
        Concentracao = ?,
        UnidadeFracionamento = ?,
        Fracionamento = ?,
        Laboratorio = ?,
        Uso = ?,
        Revisado_Farma = ?,
        Revisado_ADM = ?,
        idViaAdministracao = ?,
        idClasseFarmaceutica = ?,
        idPrincipioAtivo = ?,
        idArmazenamento = ?,
        idMedicamento = ?,
        idUnidadeFracionamento = ?,
        idFatorConversao = ?,
        idTaxas = ?,
        idRegistroVisa = ?,
        idTabela = ?
    WHERE id = ?";
    
    // Verificar quantidade de parâmetros:
    // - 9 strings (Cod até Laboratorio)
    // - 12 inteiros (Revisado até id na cláusula WHERE)
    // Total: 21 parâmetros
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    // Converter valores nulos ou vazios para NULL no banco de dados
    $cod = isset($data->Cod) && $data->Cod !== "" ? $data->Cod : null;

    // CORREÇÃO: mapear corretamente o campo codigoTUSS para Codigo_TUSS
    $codigoTUSS = isset($data->codigoTUSS) && $data->codigoTUSS !== "" ? $data->codigoTUSS : 
                 (isset($data->Codigo_TUSS) && $data->Codigo_TUSS !== "" ? $data->Codigo_TUSS : null);

    $descricaoApresentacao = isset($data->Descricao_Apresentacao) && $data->Descricao_Apresentacao !== "" ? $data->Descricao_Apresentacao : null;
    $descricaoResumida = isset($data->Descricao_Resumida) && $data->Descricao_Resumida !== "" ? $data->Descricao_Resumida : null;
    $descricaoComercial = isset($data->Descricao_Comercial) && $data->Descricao_Comercial !== "" ? $data->Descricao_Comercial : null;
    $concentracao = isset($data->Concentracao) && $data->Concentracao !== "" ? $data->Concentracao : null;
    $unidadeFracionamento = isset($data->UnidadeFracionamento) && $data->UnidadeFracionamento !== "" ? $data->UnidadeFracionamento : null;
    $fracionamento = isset($data->Fracionamento) && $data->Fracionamento !== "" ? $data->Fracionamento : null;
    
    // CORREÇÃO: mapear corretamente o campo Laboratório (com acento)
    $laboratorio = isset($data->Laboratorio) && $data->Laboratorio !== "" ? $data->Laboratorio : 
                  (isset($data->Laboratório) && $data->Laboratório !== "" ? $data->Laboratório : null);
    
    $uso = isset($data->Uso) && $data->Uso !== "" ? $data->Uso : null;

    $revisado_Farma = isset($data->Revisado_Farma) && $data->Revisado_Farma !== "" ? intval($data->Revisado_Farma) : 0;
    $revisado_ADM = isset($data->Revisado_ADM) && $data->Revisado_ADM !== "" ? intval($data->Revisado_ADM) : 0;
    
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
    $id = intval($data->id);
    
    // Verificar o tipo de idRegistroVisa
    if (!is_null($idRegistroVisa)) {
        error_log("idRegistroVisa: $idRegistroVisa (tipo: " . gettype($idRegistroVisa) . ")");
        
        // Se idRegistroVisa for uma string, converta-a para inteiro se necessário
        if (is_string($idRegistroVisa) && is_numeric($idRegistroVisa)) {
            $idRegistroVisa = intval($idRegistroVisa);
            error_log("idRegistroVisa convertido para inteiro: $idRegistroVisa");
        }
    } else {
        error_log("idRegistroVisa é NULL");
    }
    
    // Definir explicitamente a string de tipos, um caractere por vez
    $types = '';
    $types .= 's'; // $cod (string)
    $types .= 's'; // $codigoTUSS (string)
    $types .= 's'; // $descricaoApresentacao (string)
    $types .= 's'; // $descricaoResumida (string)
    $types .= 's'; // $descricaoComercial (string)
    $types .= 's'; // $concentracao (string)
    $types .= 's'; // $unidadeFracionamento (string)
    $types .= 's'; // $fracionamento (string)
    $types .= 's'; // $laboratorio (string)
    $types .= 's'; // $laboratorio (string)
    $types .= 'i'; // $revisado (integer)
    $types .= 'i'; // $revisado (integer)
    $types .= 'i'; // $idViaAdministracao (integer)
    $types .= 'i'; // $idClasseFarmaceutica (integer)
    $types .= 'i'; // $idPrincipioAtivo (integer)
    $types .= 'i'; // $idArmazenamento (integer)
    $types .= 'i'; // $idMedicamento (integer)
    $types .= 'i'; // $idUnidadeFracionamento (integer)
    $types .= 'i'; // $idFatorConversao (integer)
    $types .= 'i'; // $idTaxas (integer)
    $types .= 'i'; // $idRegistroVisa (integer)
    $types .= 'i'; // $idTabela (integer)
    $types .= 'i'; // $id (integer)
    
    // Verificar explicitamente o comprimento da string de tipos
    error_log("String de tipos: $types");
    error_log("Comprimento da string de tipos: " . strlen($types));
    error_log("Número de parâmetros: 21");
    
    // Para depuração, vamos contar o número de argumentos passados
    error_log("Contagem de argumentos passados para bind_param: " . count([
        $cod,
        $codigoTUSS,
        $descricaoApresentacao,
        $descricaoResumida,
        $descricaoComercial,
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
        $id
    ]));
    
    // Verificar e imprimir todos os valores antes de fazer o bind
    error_log("Valores para bind_param: " . json_encode([
        'cod' => $cod,
        'codigoTUSS' => $codigoTUSS,
        'descricaoApresentacao' => $descricaoApresentacao,
        'descricaoResumida' => $descricaoResumida,
        'descricaoComercial' => $descricaoComercial,
        'concentracao' => $concentracao,
        'unidadeFracionamento' => $unidadeFracionamento,
        'fracionamento' => $fracionamento,
        'laboratorio' => $laboratorio,
        'uso' => $uso,
        'revisado_Farma' => $revisado_Farma,
        'revisado_ADM' => $revisado_ADM,
        'idViaAdministracao' => $idViaAdministracao,
        'idClasseFarmaceutica' => $idClasseFarmaceutica,
        'idPrincipioAtivo' => $idPrincipioAtivo,
        'idArmazenamento' => $idArmazenamento,
        'idMedicamento' => $idMedicamento,
        'idUnidadeFracionamento' => $idUnidadeFracionamento,
        'idFatorConversao' => $idFatorConversao,
        'idTaxas' => $idTaxas,
        'idRegistroVisa' => $idRegistroVisa,
        'idTabela' => $idTabela,
        'id' => $id
    ]));

    $bindResult = $stmt->bind_param(
        $types, 
        $cod,
        $codigoTUSS,
        $descricaoApresentacao,
        $descricaoResumida,
        $descricaoComercial,
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
        $id
    );

    if (!$bindResult) {
        throw new Exception("Erro ao vincular parâmetros: " . $stmt->error);
    }

    // Executar a consulta
    if (!$stmt->execute()) {
        throw new Exception("Erro ao executar a consulta: " . $stmt->error);
    }

    // Verificar se a atualização afetou alguma linha
    if ($stmt->affected_rows === 0) {
        // Não lançamos exceção aqui, pois pode ser que não houve alterações nos dados
        error_log("Nenhum registro foi atualizado. Pode ser que não houve alterações nos dados.");
    }
    
    // Commit a transação
    $conn->commit();
    
    http_response_code(200);
    echo json_encode([
        "message" => "Serviço atualizado com sucesso", 
        "id" => $id,
        "registroVisa" => $hasRegistroVisaData ? $idRegistroVisa : null,
        "affected_rows" => $stmt->affected_rows
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    // Rollback em caso de erro
    if (isset($conn) && !$conn->connect_error) {
        $conn->rollback();
    }
    
    // Garantir que os cabeçalhos CORS sejam enviados mesmo em caso de erro
    header("Access-Control-Allow-Origin: *");
    
    http_response_code(503);
    echo json_encode([
        "message" => "Não foi possível atualizar o serviço", 
        "error" => $e->getMessage(),
        "details" => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
        "sql_error" => isset($conn) ? $conn->error : "Não disponível",
        "request_data" => isset($data) ? $data : null
    ]);
    error_log("Erro na API update_service.php: " . $e->getMessage());
}
?>