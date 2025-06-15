<?php
// test_api.php - Script para testar a conexão e estrutura do banco

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

function testConnection() {
    try {
        // Testar inclusão do config
        if (!file_exists("../../config.php")) {
            throw new Exception("Arquivo config.php não encontrado");
        }
        
        include_once("../../config.php");
        
        if (!isset($conn_pacientes)) {
            throw new Exception("Variável \$conn_pacientes não foi definida no config.php");
        }
        
        if ($conn_pacientes->connect_error) {
            throw new Exception("Erro na conexão: " . $conn_pacientes->connect_error);
        }
        
        // Testar se as tabelas existem
        $tables = ['previas', 'pacientes', 'usuarios', 'previa_anexos'];
        $existingTables = [];
        
        foreach ($tables as $table) {
            $result = $conn_pacientes->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                $existingTables[] = $table;
            }
        }
        
        // Contar registros na tabela previas (se existir)
        $previasCount = 0;
        if (in_array('previas', $existingTables)) {
            $result = $conn_pacientes->query("SELECT COUNT(*) as total FROM previas");
            if ($result) {
                $row = $result->fetch_assoc();
                $previasCount = $row['total'];
            }
        }
        
        // Contar registros na tabela pacientes (se existir)
        $pacientesCount = 0;
        if (in_array('pacientes', $existingTables)) {
            $result = $conn_pacientes->query("SELECT COUNT(*) as total FROM pacientes");
            if ($result) {
                $row = $result->fetch_assoc();
                $pacientesCount = $row['total'];
            }
        }
        
        return [
            'success' => true,
            'message' => 'Conexão estabelecida com sucesso',
            'database_info' => [
                'server_info' => $conn_pacientes->server_info,
                'charset' => $conn_pacientes->character_set_name(),
                'existing_tables' => $existingTables,
                'missing_tables' => array_diff($tables, $existingTables),
                'previas_count' => $previasCount,
                'pacientes_count' => $pacientesCount
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];
    }
}

// Executar teste
$result = testConnection();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>