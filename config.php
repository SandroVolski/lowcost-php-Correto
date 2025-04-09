<?php
// Configurações do servidor - AMBIENTE LOCAL
$host = "191.252.1.143"; //191.252.1.143 / localhost
$user = "douglas"; //douglas / root
$pass = "Douglas193"; //Douglas193 / 251103
$port = "3306"; // //Porta do servidor é 3306

// Configurar modo de erro para mysqli
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ativar log detalhado de erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Conexões para múltiplos bancos de dados
try {
    // Conexão com banco de dados bd_servico
    $dbname_servico = "bd_servico";
    $conn = new mysqli($host, $user, $pass, $dbname_servico, $port);
    
    // Verificando a conexão
    if ($conn->connect_error) {
        throw new Exception("Falha na conexão com bd_servico: " . $conn->connect_error);
    }
    
    // Configurar charset para UTF-8
    $conn->set_charset("utf8");
    
    // Conexão com banco de dados bd_pacientestto
    $dbname_pacientes = "bd_pacientestto";
    $conn_pacientes = new mysqli($host, $user, $pass, $dbname_pacientes, $port);
    
    // Verificando a conexão
    if ($conn_pacientes->connect_error) {
        throw new Exception("Falha na conexão com bd_pacientestto: " . $conn_pacientes->connect_error);
    }
    
    // Configurar charset para UTF-8
    $conn_pacientes->set_charset("utf8");
    
} catch (Exception $e) {
    // Logar o erro
    error_log("Erro de conexão com banco de dados: " . $e->getMessage());
    
    // Retornar erro como JSON
    header("Content-Type: application/json");
    echo json_encode(["error" => "Falha na conexão com o banco", "details" => $e->getMessage()]);
    exit;
}
?>