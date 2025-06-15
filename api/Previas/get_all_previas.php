<?php
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
    $searchType = $_GET['search_type'] ?? 'patient_name';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 50;
    $offset = ($page - 1) * $limit;
    
    // Construir condições WHERE baseadas nos filtros
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        switch ($searchType) {
            case 'patient_name':
                $whereConditions[] = "pac.Paciente_Nome LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
                break;
            case 'protocol':
                $whereConditions[] = "p.protocolo LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
                break;
            case 'cid':
                $whereConditions[] = "p.cid LIKE ?";
                $params[] = "%$search%";
                $types .= 's';
                break;
            case 'status_parecer_favoravel':
                $whereConditions[] = "p.parecer_guia = 'Favorável'";
                break;
            case 'status_parecer_favoravel_inconsistencia':
                $whereConditions[] = "p.parecer_guia = 'Favorável com Inconsistência'";
                break;
            case 'status_parecer_inconclusivo':
                $whereConditions[] = "p.parecer_guia = 'Inconclusivo'";
                break;
            case 'status_parecer_desfavoravel':
                $whereConditions[] = "p.parecer_guia = 'Desfavorável'";
                break;
            case 'status_finalizacao_favoravel':
                $whereConditions[] = "p.finalizacao = 'Favorável'";
                break;
            case 'status_finalizacao_favoravel_inconsistencia':
                $whereConditions[] = "p.finalizacao = 'Favorável com Inconsistência'";
                break;
            case 'status_finalizacao_inconclusivo':
                $whereConditions[] = "p.finalizacao = 'Inconclusivo'";
                break;
            case 'status_finalizacao_desfavoravel':
                $whereConditions[] = "p.finalizacao = 'Desfavorável'";
                break;
        }
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // Query completa com a tabela Pacientes (maiúsculo)
    $sql = "SELECT 
        p.id,
        p.paciente_id,
        COALESCE(p.numero_sequencial, 0) as numero_sequencial,
        COALESCE(p.codigo_composto, '') as codigo_composto,
        COALESCE(p.guia, '') as guia,
        COALESCE(p.protocolo, '') as protocolo,
        COALESCE(p.cid, '') as cid,
        p.data_emissao_guia,
        p.data_encaminhamento_af,
        p.data_solicitacao,
        COALESCE(p.parecer, '') as parecer,
        COALESCE(p.peso, 0) as peso,
        COALESCE(p.altura, 0) as altura,
        COALESCE(p.parecer_guia, '') as parecer_guia,
        COALESCE(p.finalizacao, '') as finalizacao,
        COALESCE(p.inconsistencia, '') as inconsistencia,
        p.data_parecer_registrado,
        COALESCE(p.tempo_analise, 0) as tempo_analise,
        p.data_criacao,
        p.data_atualizacao,
        COALESCE(p.usuario_criacao_id, 0) as usuario_criacao_id,
        COALESCE(p.usuario_alteracao_id, 0) as usuario_alteracao_id,
        COALESCE(pac.Paciente_Nome, 'N/D') as paciente_nome,
        COALESCE(pac.Codigo, '') as paciente_codigo,
        pac.Data_Nascimento as paciente_nascimento,
        COALESCE(pac.Sexo, '') as paciente_sexo,
        COALESCE(uc.nome, 'N/D') AS nome_usuario_criacao,
        COALESCE(ua.nome, 'N/D') AS nome_usuario_alteracao,
        COALESCE(COUNT(pa.id), 0) AS qtd_anexos
    FROM previas p
    INNER JOIN Pacientes pac ON p.paciente_id = pac.id
    LEFT JOIN usuarios uc ON p.usuario_criacao_id = uc.id
    LEFT JOIN usuarios ua ON p.usuario_alteracao_id = ua.id
    LEFT JOIN previa_anexos pa ON p.id = pa.previa_id
    $whereClause
    GROUP BY p.id
    ORDER BY p.data_criacao DESC
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
        if ($row['paciente_nascimento']) {
            $row['paciente_nascimento_formatada'] = date('d/m/Y', strtotime($row['paciente_nascimento']));
        }
        
        // Manter compatibilidade com código existente
        $row['Nome'] = $row['paciente_nome'];
        $row['Paciente_Codigo'] = $row['paciente_codigo'];
        $row['Nascimento'] = $row['paciente_nascimento'];
        $row['Sexo'] = $row['paciente_sexo'];
        $row['Operadora'] = 'N/D'; // Não disponível na tabela Pacientes
        
        $previas[] = $row;
    }
    
    $stmt->close();
    
    // Query para contar o total de registros
    $countSql = "SELECT COUNT(DISTINCT p.id) as total
    FROM previas p
    INNER JOIN Pacientes pac ON p.paciente_id = pac.id
    $whereClause";
    
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
        'success' => true
    ]);
    
} catch (Exception $e) {
    returnError("Erro interno: " . $e->getMessage());
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>