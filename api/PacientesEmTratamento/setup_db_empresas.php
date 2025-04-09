<?php
header("Content-Type: text/plain");
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";
$user = "root";
$pass = "251103";

echo "Iniciando configuração...\n\n";

try {
    // Conectar ao MySQL sem selecionar banco
    $conn = new mysqli($host, $user, $pass);
    echo "Conexão com MySQL estabelecida.\n";
    
    // Verificar se bd_empresas existe
    $result = $conn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'bd_empresas'");
    $bd_empresas_exists = ($result && $result->num_rows > 0);
    
    if (!$bd_empresas_exists) {
        echo "Banco bd_empresas não existe. Criando...\n";
        if ($conn->query("CREATE DATABASE bd_empresas")) {
            echo "Banco bd_empresas criado com sucesso!\n";
        } else {
            throw new Exception("Falha ao criar banco bd_empresas: " . $conn->error);
        }
    } else {
        echo "Banco bd_empresas já existe.\n";
    }
    
    // Selecionar o banco bd_empresas
    $conn->select_db("bd_empresas");
    
    // Verificar se a tabela medicos existe
    $result = $conn->query("SHOW TABLES LIKE 'medicos'");
    $medicos_table_exists = ($result && $result->num_rows > 0);
    
    if (!$medicos_table_exists) {
        echo "Tabela medicos não existe. Criando...\n";
        $sql = "CREATE TABLE medicos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            crm INT NOT NULL UNIQUE,
            nome VARCHAR(100) NOT NULL,
            especialidade VARCHAR(100),
            data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql)) {
            echo "Tabela medicos criada com sucesso!\n";
        } else {
            throw new Exception("Falha ao criar tabela medicos: " . $conn->error);
        }
    } else {
        echo "Tabela medicos já existe.\n";
    }
    
    // Adicionar médicos com os CRMs do seu banco
    echo "\nInserindo médicos com CRMs correspondentes...\n";
    
    $medicos_data = [
        ['crm' => 4524, 'nome' => 'Dr. Carlos Silva', 'especialidade' => 'Oncologia'],
        ['crm' => 4870, 'nome' => 'Dra. Ana Ferreira', 'especialidade' => 'Oncologia'],
        ['crm' => 10735, 'nome' => 'Dr. Ricardo Souza', 'especialidade' => 'Oncologia'],
        ['crm' => 15221, 'nome' => 'Dra. Mariana Costa', 'especialidade' => 'Ortopedia'],
        ['crm' => 35828, 'nome' => 'Dr. João Paulo', 'especialidade' => 'Gastroenterologia'],
        ['crm' => 4931, 'nome' => 'Dra. Fernanda Lima', 'especialidade' => 'Oncologia'],
        ['crm' => 22678, 'nome' => 'Dr. Roberto Santos', 'especialidade' => 'Oncologia'],
        ['crm' => 9744, 'nome' => 'Dr. Antônio Oliveira', 'especialidade' => 'Hematologia'],
        ['crm' => 3578, 'nome' => 'Dra. Beatriz Almeida', 'especialidade' => 'Oncologia'],
        ['crm' => 24732, 'nome' => 'Dr. Lucas Mendes', 'especialidade' => 'Ginecologia'],
        ['crm' => 5439, 'nome' => 'Dr. Marcelo Costa', 'especialidade' => 'Urologia'],
        ['crm' => 12532, 'nome' => 'Dra. Patrícia Góes', 'especialidade' => 'Oncologia'],
        ['crm' => 18577, 'nome' => 'Dr. Rafael Martins', 'especialidade' => 'Oncologia'],
        ['crm' => 27939, 'nome' => 'Dra. Daniela Lopes', 'especialidade' => 'Oncologia']
    ];
    
    $insertCount = 0;
    foreach ($medicos_data as $medico) {
        $stmt = $conn->prepare("INSERT IGNORE INTO medicos (crm, nome, especialidade) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $medico['crm'], $medico['nome'], $medico['especialidade']);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo "Médico inserido: CRM " . $medico['crm'] . " - " . $medico['nome'] . "\n";
                $insertCount++;
            }
        } else {
            echo "Aviso: Falha ao inserir médico CRM " . $medico['crm'] . ": " . $stmt->error . "\n";
        }
    }
    
    echo "\nTotal de médicos inseridos: " . $insertCount . "\n";
    
    // Verificar a conexão cruzada
    echo "\nTestando consulta cruzada...\n";
    
    // Conectar ao banco principal
    $conn_pacientes = new mysqli($host, $user, $pass, "bd_pacientestto");
    
    if ($conn_pacientes->connect_error) {
        throw new Exception("Falha na conexão com bd_pacientestto: " . $conn_pacientes->connect_error);
    }
    
    // Obter alguns CRMs
    $crms_result = $conn_pacientes->query("SELECT DISTINCT CRM_Medico FROM Pacientes_Em_Tratamento WHERE CRM_Medico IS NOT NULL AND CRM_Medico > 0 LIMIT 5");
    
    if ($crms_result && $crms_result->num_rows > 0) {
        echo "CRMs encontrados na tabela Pacientes_Em_Tratamento:\n";
        
        while ($crm_row = $crms_result->fetch_assoc()) {
            $crm = $crm_row['CRM_Medico'];
            echo "- CRM: " . $crm;
            
            // Buscar na tabela médicos
            $medico_result = $conn->query("SELECT nome FROM medicos WHERE crm = $crm");
            
            if ($medico_result && $medico_result->num_rows > 0) {
                $medico_row = $medico_result->fetch_assoc();
                echo " → Médico encontrado: " . $medico_row['nome'] . "\n";
            } else {
                echo " → MÉDICO NÃO ENCONTRADO!\n";
            }
        }
    } else {
        echo "Nenhum CRM encontrado na tabela Pacientes_Em_Tratamento.\n";
    }
    
    echo "\nConfigurações concluídas!\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage();
}
?>