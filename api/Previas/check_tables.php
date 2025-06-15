<?php
// check_tables.php - Verificar estrutura das tabelas existentes

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once("../../config.php");

$response = [
    'timestamp' => date('Y-m-d H:i:s'),
    'database_name' => '',
    'all_tables' => [],
    'table_structures' => []
];

try {
    // Obter nome do banco
    $result = $conn_pacientes->query("SELECT DATABASE() as db_name");
    if ($result) {
        $row = $result->fetch_assoc();
        $response['database_name'] = $row['db_name'];
    }
    
    // Listar todas as tabelas
    $result = $conn_pacientes->query("SHOW TABLES");
    if ($result) {
        while ($row = $result->fetch_array()) {
            $tableName = $row[0];
            $response['all_tables'][] = $tableName;
            
            // Obter estrutura de cada tabela
            $structure = $conn_pacientes->query("DESCRIBE $tableName");
            if ($structure) {
                $columns = [];
                while ($col = $structure->fetch_assoc()) {
                    $columns[] = [
                        'field' => $col['Field'],
                        'type' => $col['Type'],
                        'null' => $col['Null'],
                        'key' => $col['Key'],
                        'default' => $col['Default'],
                        'extra' => $col['Extra']
                    ];
                }
                $response['table_structures'][$tableName] = $columns;
            }
        }
    }
    
    // Verificar se existe alguma tabela que pode conter dados de pacientes
    $possiblePatientTables = [];
    foreach ($response['all_tables'] as $table) {
        if (stripos($table, 'pacient') !== false || 
            stripos($table, 'patient') !== false ||
            stripos($table, 'pessoa') !== false ||
            stripos($table, 'user') !== false) {
            $possiblePatientTables[] = $table;
        }
    }
    
    $response['possible_patient_tables'] = $possiblePatientTables;
    
    // Verificar se a tabela previas tem informações de paciente
    if (in_array('previas', $response['all_tables'])) {
        $sample = $conn_pacientes->query("SELECT * FROM previas LIMIT 2");
        if ($sample) {
            $sampleData = [];
            while ($row = $sample->fetch_assoc()) {
                $sampleData[] = $row;
            }
            $response['previas_sample'] = $sampleData;
        }
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>