<?php
// Script para verificar e criar a tabela dfatorconversao se não existir

header("Content-Type: text/html; charset=UTF-8");

// Ativar log detalhado de erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once("../config.php");

    // Verificar se a conexão está configurada
    if (!isset($conn)) {
        throw new Exception("Variável de conexão não definida. Verifique o arquivo config.php");
    }

    // Verificar se a conexão com o banco de dados foi estabelecida
    if ($conn->connect_error) {
        throw new Exception("Erro de conexão com o banco de dados: " . $conn->connect_error);
    }

    echo "<h1>Verificando tabela dfatorconversao</h1>";

    // Verificar se a tabela existe
    $checkTableQuery = "SHOW TABLES LIKE 'dfatorconversao'";
    $tableExists = $conn->query($checkTableQuery);
    
    if ($tableExists->num_rows == 0) {
        echo "<p>Tabela dfatorconversao não existe. Criando tabela...</p>";
        
        // Criar a tabela
        $createTableQuery = "CREATE TABLE dfatorconversao (
            id_fatorconversao INT AUTO_INCREMENT PRIMARY KEY,
            fator VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($createTableQuery) === TRUE) {
            echo "<p>Tabela dfatorconversao criada com sucesso!</p>";
            
            // Inserir dados iniciais
            $insertDataQuery = "INSERT INTO dfatorconversao (id_fatorconversao, fator) VALUES 
                (0, '0'),
                (1, '1'),
                (2, '1'),
                (3, '1'),
                (4, '1')";
                
            if ($conn->query($insertDataQuery) === TRUE) {
                echo "<p>Dados iniciais inseridos com sucesso!</p>";
            } else {
                echo "<p>Erro ao inserir dados iniciais: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>Erro ao criar tabela: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>Tabela dfatorconversao já existe.</p>";
        
        // Verificar estrutura da tabela
        $checkColumnsQuery = "SHOW COLUMNS FROM dfatorconversao LIKE 'id_fatorconversao'";
        $idColumnExists = $conn->query($checkColumnsQuery);
        
        $checkFatorQuery = "SHOW COLUMNS FROM dfatorconversao LIKE 'fator'";
        $fatorColumnExists = $conn->query($checkFatorQuery);
        
        if ($idColumnExists->num_rows == 0 || $fatorColumnExists->num_rows == 0) {
            echo "<p>A estrutura da tabela dfatorconversao está incorreta. Recriando tabela...</p>";
            
            // Dropar a tabela existente
            $dropTableQuery = "DROP TABLE dfatorconversao";
            if ($conn->query($dropTableQuery) === TRUE) {
                echo "<p>Tabela dfatorconversao existente removida.</p>";
                
                // Criar a tabela com a estrutura correta
                $createTableQuery = "CREATE TABLE dfatorconversao (
                    id_fatorconversao INT AUTO_INCREMENT PRIMARY KEY,
                    fator VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
                
                if ($conn->query($createTableQuery) === TRUE) {
                    echo "<p>Tabela dfatorconversao recriada com sucesso!</p>";
                    
                    // Inserir dados iniciais
                    $insertDataQuery = "INSERT INTO dfatorconversao (id_fatorconversao, fator) VALUES 
                        (0, '0'),
                        (1, '1'),
                        (2, '1'),
                        (3, '1'),
                        (4, '1')";
                        
                    if ($conn->query($insertDataQuery) === TRUE) {
                        echo "<p>Dados iniciais inseridos com sucesso!</p>";
                    } else {
                        echo "<p>Erro ao inserir dados iniciais: " . $conn->error . "</p>";
                    }
                } else {
                    echo "<p>Erro ao recriar tabela: " . $conn->error . "</p>";
                }
            } else {
                echo "<p>Erro ao remover tabela existente: " . $conn->error . "</p>";
            }
        } else {
            echo "<p>A estrutura da tabela dfatorconversao está correta.</p>";
            
            // Mostrar os dados atuais
            $selectDataQuery = "SELECT id_fatorconversao, fator FROM dfatorconversao ORDER BY id_fatorconversao";
            $result = $conn->query($selectDataQuery);
            
            if ($result->num_rows > 0) {
                echo "<p>Dados atuais na tabela:</p>";
                echo "<table border='1'>";
                echo "<tr><th>id_fatorconversao</th><th>fator</th></tr>";
                
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $row["id_fatorconversao"] . "</td>";
                    echo "<td>" . $row["fator"] . "</td>";
                    echo "</tr>";
                }
                
                echo "</table>";
            } else {
                echo "<p>Não há dados na tabela. Inserindo dados iniciais...</p>";
                
                // Inserir dados iniciais
                $insertDataQuery = "INSERT INTO dfatorconversao (id_fatorconversao, fator) VALUES 
                    (0, '0'),
                    (1, '1'),
                    (2, '1'),
                    (3, '1'),
                    (4, '1')";
                    
                if ($conn->query($insertDataQuery) === TRUE) {
                    echo "<p>Dados iniciais inseridos com sucesso!</p>";
                } else {
                    echo "<p>Erro ao inserir dados iniciais: " . $conn->error . "</p>";
                }
            }
        }
    }

    echo "<p>Verificação e ajustes concluídos com sucesso!</p>";

} catch (Exception $e) {
    echo "<h2>Erro:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}

// Fechar a conexão
if (isset($conn)) {
    $conn->close();
}
?>