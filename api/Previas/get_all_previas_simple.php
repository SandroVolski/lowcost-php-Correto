<?php
// get_all_previas_simple.php - Versão que funciona sem a tabela pacientes

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Função para retornar erro JSON
function returnError($message, $code = 500) {
    http_response_code($code);
    echo json_encode(["error" => $message]);
    exit;
}

try {
    include_once("../../config.php");
    
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        returnError("Erro na conexão com o banco");
    }

    // Parâmetros
    $search = $_GET['search'] ?? '';
    $searchType = $_GET['search_type'] ?? 'protocol';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 50;
    $offset = ($page - 1) * $limit;
    
    // Construir condições WHERE baseadas nos filtros
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        switch ($searchType) {
            case 'protocol':
                $whereConditions[] = "protocolo LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
                break;
            case 'cid':
                $whereConditions[] = "cid LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
                break;
            case 'status_parecer':
                $whereConditions[] = "parecer_guia LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
                break;
            case 'status_finalizacao':
                $whereConditions[] = "finalizacao LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
                break;
            case 'patient_name':
                // Se não temos tabela pacientes, vamos ignorar este filtro por enquanto
                break;
        }
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // Query simples apenas da tabela previas
    $sql = "SELECT 
        id,
        paciente_id,
        COALESCE(numero_sequencial, 0) as numero_sequencial,
        COALESCE(codigo_composto, '') as codigo_composto,
        COALESCE(guia, '') as guia,
        COALESCE(protocolo, '') as protocolo,
        COALESCE(cid, '') as cid,
        data_emissao_guia,
        data_encaminhamento_af,
        data_solicitacao,
        COALESCE(parecer, '') as parecer,
        COALESCE(peso, 0) as peso,
        COALESCE(altura, 0) as altura,
        COALESCE(parecer_guia, '') as parecer_guia,
        COALESCE(finalizacao, '') as finalizacao,
        COALESCE(inconsistencia, '') as inconsistencia,
        data_parecer_registrado,
        COALESCE(tempo_analise, 0) as tempo_analise,
        data_criacao,
        data_atualizacao,
        COALESCE(usuario_criacao_id, 0) as usuario_criacao_id,
        COALESCE(usuario_alteracao_id, 0) as usuario_alteracao_id
    FROM previas
    $whereClause
    ORDER BY data_criacao DESC
    LIMIT ? OFFSET ?";
    
    // Adicionar parâmetros de paginação
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn_pacientes->prepare($sql);
    
    if (!$stmt) {
        returnError("Erro ao preparar query: " . $conn_pacientes->error);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        returnError("Erro ao executar query: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    
    $previas = [];
    while ($row = $result->fetch_assoc()) {
        // Formatear datas para exibição
        if ($row['data_emissao_guia']) {
            $row['data_emissao_guia_formatada'] = date('d/m/Y', strtotime($row['data_emissao_guia']));
        }
        if ($row['data_encaminhamento_af']) {
            $row['data_encaminhamento_af_formatada'] = date('d/m/Y', strtotime($row['data_encaminhamento_af']));
        }
        if ($row['data_solicitacao']) {
            $row['data_solicitacao_formatada'] = date('d/m/Y', strtotime($row['data_solicitacao']));
        }
        if ($row['data_criacao']) {
            $row['data_criacao_formatada'] = date('d/m/Y H:i', strtotime($row['data_criacao']));
        }
        if ($row['data_atualizacao']) {
            $row['data_atualizacao_formatada'] = date('d/m/Y H:i', strtotime($row['data_atualizacao']));
        }
        
        // Adicionar dados fictícios de paciente até encontrarmos a fonte real
        $row['paciente_nome'] = 'Paciente ' . $row['paciente_id'];
        $row['paciente_codigo'] = 'COD' . str_pad($row['paciente_id'], 6, '0', STR_PAD_LEFT);
        $row['paciente_operadora'] = 'N/D';
        $row['paciente_nascimento'] = null;
        $row['paciente_nascimento_formatada'] = 'N/D';
        $row['paciente_sexo'] = 'N/D';
        $row['nome_usuario_criacao'] = 'N/D';
        $row['nome_usuario_alteracao'] = 'N/D';
        $row['qtd_anexos'] = 0;
        
        $previas[] = $row;
    }
    
    $stmt->close();
    
    // Query para contar o total de registros
    $countSql = "SELECT COUNT(*) as total FROM previas $whereClause";
    
    $countStmt = $conn_pacientes->prepare($countSql);
    
    if (!empty($whereConditions)) {
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
    
    // Calcular informações de paginação
    $totalPages = ceil($totalRecords / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    echo json_encode([
        'data' => $previas,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_records' => (int)$totalRecords,
            'per_page' => $limit,
            'has_next_page' => $hasNextPage,
            'has_prev_page' => $hasPrevPage
        ],
        'note' => 'Versão simplificada - tabela pacientes não encontrada'
    ]);
    
} catch (Exception $e) {
    returnError("Erro interno: " . $e->getMessage());
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>