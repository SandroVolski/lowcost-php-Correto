<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    include_once("../../config.php");
    
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        http_response_code(500);
        echo json_encode(["error" => "Erro na conexão com o banco"]);
        exit;
    }
    
    // Parâmetros
    $search = $_GET['search'] ?? '';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(10, intval($_GET['limit']))) : 50;
    $offset = ($page - 1) * $limit;
    
    // Construir condições WHERE
    $whereConditions = [];
    $params = [];
    $types = '';
    
    if (!empty($search)) {
        $whereConditions[] = "(Paciente_Nome LIKE ? OR Codigo LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $types .= 'ss';
    }
    
    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }
    
    // Query principal
    $sql = "SELECT 
        p.id,
        p.Paciente_Nome as Nome,
        p.Codigo as Paciente_Codigo,
        p.Data_Nascimento as Nascimento,
        p.Sexo,
        COALESCE(p.Operadora, 0) as Operadora,
        COALESCE(p.Prestador, 0) as Prestador,
        COALESCE(p.Cid_Diagnostico, '') as Cid_Diagnostico,
        p.Data_Inicio_Tratamento,
        COUNT(pr.id) as total_previas,
        MAX(pr.data_criacao) as ultima_previa_data
    FROM Pacientes p
    LEFT JOIN previas pr ON p.id = pr.paciente_id
    $whereClause
    GROUP BY p.id
    ORDER BY p.Paciente_Nome ASC
    LIMIT ? OFFSET ?";
    
    // Adicionar parâmetros de paginação
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    $stmt = $conn_pacientes->prepare($sql);
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(["error" => "Erro ao preparar query: " . $conn_pacientes->error]);
        exit;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["error" => "Erro ao executar query: " . $stmt->error]);
        exit;
    }
    
    $result = $stmt->get_result();
    
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        // Formatear datas
        if ($row['Nascimento']) {
            $row['Nascimento_Formatado'] = date('d/m/Y', strtotime($row['Nascimento']));
        }
        if ($row['Data_Inicio_Tratamento']) {
            $row['Data_Inicio_Tratamento_Formatado'] = date('d/m/Y', strtotime($row['Data_Inicio_Tratamento']));
        }
        if ($row['ultima_previa_data']) {
            $row['Ultima_Previa_Formatada'] = date('d/m/Y H:i', strtotime($row['ultima_previa_data']));
        }
        
        $patients[] = $row;
    }
    
    $stmt->close();
    
    // Query para contar total
    $countSql = "SELECT COUNT(*) as total FROM Pacientes $whereClause";
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
    
    // Calcular paginação
    $totalPages = ceil($totalRecords / $limit);
    $hasNextPage = $page < $totalPages;
    $hasPrevPage = $page > 1;
    
    echo json_encode([
        'data' => $patients,
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
    http_response_code(500);
    echo json_encode(["error" => "Erro interno: " . $e->getMessage()]);
}

if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>