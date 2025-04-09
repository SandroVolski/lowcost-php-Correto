<?php
// Script para diagnóstico das estruturas das tabelas
header("Content-Type: text/html; charset=UTF-8");

// Iniciar saída HTML para melhor visualização
echo '<!DOCTYPE html>
<html>
<head>
    <title>Diagnóstico de Banco de Dados</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2, h3 { color: #333; }
        .error { color: red; }
        .success { color: green; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
    </style>
</head>
<body>
    <h1>Diagnóstico de Estrutura de Banco de Dados</h1>';

// Incluir o arquivo de configuração
require_once('config.php');

// Função para mostrar a estrutura de uma tabela
function showTableStructure($conn, $database, $table) {
    echo "<h3>Estrutura da tabela: $database.$table</h3>";
    
    // Obter a estrutura da tabela
    $query = "DESCRIBE $database.$table";
    $result = $conn->query($query);
    
    if (!$result) {
        echo "<p class='error'>Erro ao consultar a estrutura da tabela: " . $conn->error . "</p>";
        return;
    }
    
    if ($result->num_rows > 0) {
        echo "<table>
                <tr>
                    <th>Campo</th>
                    <th>Tipo</th>
                    <th>Nulo</th>
                    <th>Chave</th>
                    <th>Padrão</th>
                    <th>Extra</th>
                </tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>" . htmlspecialchars($row['Field']) . "</td>
                    <td>" . htmlspecialchars($row['Type']) . "</td>
                    <td>" . htmlspecialchars($row['Null']) . "</td>
                    <td>" . htmlspecialchars($row['Key']) . "</td>
                    <td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>
                    <td>" . htmlspecialchars($row['Extra']) . "</td>
                  </tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Nenhuma coluna encontrada na tabela.</p>";
    }
    
    // Verificar os primeiros registros
    $sampleQuery = "SELECT * FROM $database.$table LIMIT 3";
    $sampleResult = $conn->query($sampleQuery);
    
    if (!$sampleResult) {
        echo "<p class='error'>Erro ao consultar registros de amostra: " . $conn->error . "</p>";
        return;
    }
    
    if ($sampleResult->num_rows > 0) {
        echo "<h4>Amostra de Dados (3 primeiros registros):</h4>";
        echo "<table><tr>";
        
        // Obter os nomes das colunas
        $fields = $sampleResult->fetch_fields();
        foreach ($fields as $field) {
            echo "<th>" . htmlspecialchars($field->name) . "</th>";
        }
        echo "</tr>";
        
        // Reset o ponteiro do resultado
        $sampleResult->data_seek(0);
        
        // Obter os dados
        while ($row = $sampleResult->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Nenhum registro encontrado na tabela.</p>";
    }
}

try {
    // Verificar tabelas no bd_pacientestto
    echo "<h2>Banco de Dados: bd_pacientestto</h2>";
    
    // Tabela Pacientes
    $pacientesExists = $conn_pacientes->query("SHOW TABLES LIKE 'Pacientes'")->num_rows > 0;
    
    if ($pacientesExists) {
        echo "<p class='success'>Tabela 'Pacientes' encontrada!</p>";
        showTableStructure($conn_pacientes, 'bd_pacientestto', 'Pacientes');
    } else {
        echo "<p class='error'>Tabela 'Pacientes' não encontrada!</p>";
        
        // Mostrar todas as tabelas disponíveis
        $result = $conn_pacientes->query("SHOW TABLES");
        if ($result->num_rows > 0) {
            echo "<p>Tabelas disponíveis em bd_pacientestto:</p><ul>";
            while ($row = $result->fetch_row()) {
                echo "<li>" . htmlspecialchars($row[0]) . "</li>";
            }
            echo "</ul>";
        }
    }
    
    // Verificar tabelas no bd_servico
    echo "<h2>Banco de Dados: bd_servico</h2>";
    
    // Tabela bd_producaom_operadoras
    $operadorasExists = $conn->query("SHOW TABLES LIKE 'bd_producaom_operadoras'")->num_rows > 0;
    
    if ($operadorasExists) {
        echo "<p class='success'>Tabela 'bd_producaom_operadoras' encontrada!</p>";
        showTableStructure($conn, 'bd_servico', 'bd_producaom_operadoras');
    } else {
        echo "<p class='error'>Tabela 'bd_producaom_operadoras' não encontrada!</p>";
    }
    
    // Tabela bd_empresas_empresas
    $empresasExists = $conn->query("SHOW TABLES LIKE 'bd_empresas_empresas'")->num_rows > 0;
    
    if ($empresasExists) {
        echo "<p class='success'>Tabela 'bd_empresas_empresas' encontrada!</p>";
        showTableStructure($conn, 'bd_servico', 'bd_empresas_empresas');
    } else {
        echo "<p class='error'>Tabela 'bd_empresas_empresas' não encontrada!</p>";
    }
    
    // Sugestão de query SQL para o endpoint get_pacientes.php
    echo "<h2>Sugestão de Query SQL</h2>";
    echo "<p>Após analisar as estruturas das tabelas, recomendamos usar a seguinte query SQL no endpoint get_pacientes.php:</p>";
    echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo "-- Esta é uma sugestão que deve ser ajustada com base nas colunas reais
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
    p.Cid_Diagnostico AS CID
FROM 
    bd_pacientestto.Pacientes p
LEFT JOIN 
    bd_servico.bd_producaom_operadoras o ON p.Operadora_Id = o.id
LEFT JOIN 
    bd_servico.bd_empresas_empresas e ON p.Prestador_Id = e.id
ORDER BY 
    p.Paciente_Nome
LIMIT ?, ?";
    echo "</pre>";
    
    // Mostrar o erro específico encontrado e sugestão
    echo "<h2>Erro Identificado</h2>";
    echo "<p class='error'>O erro 'Unknown column 'p.Prestador_Id' in 'on clause'' indica que a coluna 'Prestador_Id' não existe na tabela 'Pacientes' ou tem um nome diferente.</p>";
    
    echo "<p>Para corrigir, verifique o nome correto da coluna de prestador na tabela Pacientes e ajuste o JOIN na consulta SQL:</p>";
    echo "<pre style='background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo "-- Exemplo de correção (ajuste os nomes das colunas conforme necessário)
LEFT JOIN 
    bd_servico.bd_empresas_empresas e ON p.NOME_CORRETO_DA_COLUNA_PRESTADOR = e.id";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>Erro durante o diagnóstico</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

// Fechar conexões
if (isset($conn)) {
    $conn->close();
}
if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}

echo '</body></html>';
?>