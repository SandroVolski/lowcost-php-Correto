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

// Ativar log de erros em arquivo para depuração
error_log("Iniciando get_pacientes.php");

try {
    // Incluir arquivo de configuração - caminho atualizado
    include_once("../../config.php");

    // Verifica se as conexões com os bancos de dados foram estabelecidas
    if (!isset($conn) || $conn->connect_error || !isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados.");
    }

    // Parâmetros de pesquisa e paginação
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = ($page - 1) * $limit;
    
    // Novos parâmetros de pesquisa
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
    $searchType = isset($_GET['type']) ? $_GET['type'] : 'nome';
    
    // Construir a cláusula WHERE baseada no tipo de pesquisa
    $whereClause = '';
    $params = [];
    $types = '';
    
    if (!empty($searchTerm)) {
        switch ($searchType) {
            case 'nome':
                $whereClause = "WHERE p.Paciente_Nome LIKE ?";
                $searchParam = "%$searchTerm%";
                $params[] = $searchParam;
                $types .= "s";
                break;
            case 'codigo':
                $whereClause = "WHERE p.Codigo LIKE ?";
                $searchParam = "%$searchTerm%";
                $params[] = $searchParam;
                $types .= "s";
                break;
            case 'cid':
                $whereClause = "WHERE p.Cid_Diagnostico LIKE ?";
                $searchParam = "%$searchTerm%";
                $params[] = $searchParam;
                $types .= "s";
                break;
            case 'operadora':
                $whereClause = "WHERE o.Nome_Fantasia LIKE ?";
                $searchParam = "%$searchTerm%";
                $params[] = $searchParam;
                $types .= "s";
                break;
            case 'prestador':
                $whereClause = "WHERE (e.Prestador_Nome LIKE ? OR e.Prestador_Nome_Fantasia LIKE ?)";
                $searchParam = "%$searchTerm%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= "ss";
                break;
            default:
                // Pesquisa em múltiplos campos
                $whereClause = "WHERE (p.Paciente_Nome LIKE ? OR p.Codigo LIKE ? OR p.Cid_Diagnostico LIKE ?)";
                $searchParam = "%$searchTerm%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= "sss";
        }
    }

    // Adicionar parâmetros de paginação
    $params[] = $offset;
    $params[] = $limit;
    $types .= "ii";

    // Query com WHERE dinâmico
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
        '' AS Finalidade,
        '' AS Crm_Nome
    FROM 
        bd_pacientestto.Pacientes p
    LEFT JOIN 
        bd_servico.bd_producaom_operadoras o ON p.Operadora = o.id
    LEFT JOIN 
        bd_servico.bd_empresas_empresas e ON p.Prestador = e.id
    $whereClause
    ORDER BY 
        p.Paciente_Nome
    LIMIT ?, ?
    ";

    error_log("SQL gerado: $sql");
    error_log("Parâmetros: " . implode(", ", $params));

    // Preparar e executar a consulta
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    // Bind dos parâmetros dinâmicos
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    // Calcular o total de pacientes para fins de paginação
    $countSql = "SELECT COUNT(*) as total FROM bd_pacientestto.Pacientes p 
                 LEFT JOIN bd_servico.bd_producaom_operadoras o ON p.Operadora = o.id
                 LEFT JOIN bd_servico.bd_empresas_empresas e ON p.Prestador = e.id
                 $whereClause";
                 
    $countStmt = $conn->prepare($countSql);
    
    if ($countStmt) {
        // Bind dos parâmetros de pesquisa (sem os de paginação)
        if (!empty($searchTerm)) {
            $countParams = array_slice($params, 0, -2);
            $countTypes = substr($types, 0, -2);
            if (!empty($countParams)) {
                $countStmt->bind_param($countTypes, ...$countParams);
            }
        }
        
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $totalRecords = $countResult->fetch_assoc()['total'];
        $countStmt->close();
    } else {
        $totalRecords = 0;
    }

    // Coletar os resultados
    $pacientes = [];
    while ($row = $result->fetch_assoc()) {
        // Formatar a data de nascimento se não for nula
        if (!empty($row['Nascimento'])) {
            $date = new DateTime($row['Nascimento']);
            $row['Nascimento'] = $date->format('d/m/Y');
        }
        
        // Formatar a data de início de tratamento se não for nula
        if (!empty($row['Data_Inicio_Tratamento'])) {
            $date = new DateTime($row['Data_Inicio_Tratamento']);
            $row['Data_Inicio_Tratamento'] = $date->format('d/m/Y');
        }
        
        $pacientes[] = $row;
    }

    // Retornar dados com metadados de paginação
    http_response_code(200);
    echo json_encode([
        'data' => $pacientes,
        'meta' => [
            'total' => (int)$totalRecords,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalRecords / $limit)
        ]
    ]);

    if (isset($stmt)) {
        $stmt->close();
    }
    
} catch (Exception $e) {
    error_log("Erro em get_pacientes.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao buscar pacientes", 
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