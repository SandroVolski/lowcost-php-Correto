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

    // Parâmetros de paginação (opcional)
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = ($page - 1) * $limit;

    // Query corrigida usando os nomes reais das colunas na tabela Pacientes
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
    ORDER BY 
        p.Paciente_Nome
    LIMIT ?, ?
    ";

    error_log("SQL gerado: $sql");

    // Verificar se já existem pacientes na tabela
    $checkSql = "SELECT COUNT(*) as count FROM bd_pacientestto.Pacientes";
    $checkResult = $conn_pacientes->query($checkSql);
    $rowCount = 0;
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $rowCount = $checkResult->fetch_assoc()['count'];
    }
    
    error_log("Número de pacientes encontrados: $rowCount");
    
    /* Se não há pacientes, criar alguns para teste
    if ($rowCount == 0) {
        error_log("Nenhum paciente encontrado. Criando dados de exemplo...");
        
        // Exemplo 1
        $insertSql = "INSERT INTO bd_pacientestto.Pacientes 
            (Paciente_Nome, Operadora, Prestador, Codigo, Data_Nascimento, Sexo, Cid_Diagnostico, Data_Inicio_Tratamento) 
            VALUES ('Maria Silva', 1, 2, 1001, '1980-05-15', 'F', 'C50.9', '2023-01-10')";
        $conn_pacientes->query($insertSql);
        
        // Exemplo 2
        $insertSql = "INSERT INTO bd_pacientestto.Pacientes 
            (Paciente_Nome, Operadora, Prestador, Codigo, Data_Nascimento, Sexo, Cid_Diagnostico, Data_Inicio_Tratamento) 
            VALUES ('José Santos', 2, 3, 1002, '1975-08-22', 'M', 'C61', '2023-02-15')";
        $conn_pacientes->query($insertSql);
        
        // Exemplo 3
        $insertSql = "INSERT INTO bd_pacientestto.Pacientes 
            (Paciente_Nome, Operadora, Prestador, Codigo, Data_Nascimento, Sexo, Cid_Diagnostico, Data_Inicio_Tratamento) 
            VALUES ('Ana Oliveira', 1, 8, 1003, '1990-11-30', 'F', 'C18.9', '2023-03-05')";
        $conn_pacientes->query($insertSql);
        
        error_log("Dados de exemplo criados com sucesso.");
    }*/

    // Preparar e executar a consulta
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Erro ao preparar a consulta: " . $conn->error);
    }

    $stmt->bind_param("ii", $offset, $limit);
    $stmt->execute();
    $result = $stmt->get_result();

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

    /* Se ainda não temos pacientes, criar dados fictícios
    if (count($pacientes) == 0) {
        error_log("Nenhum paciente encontrado após a consulta. Retornando dados fictícios.");
        
        $pacientes = [
            [
                "id" => 1,
                "Operadora" => "Unimed",
                "Prestador_Nome" => "Hospital São Lucas",
                "Prestador_Nome_Fantasia" => "São Lucas",
                "Paciente_Codigo" => "P001",
                "Nome" => "Maria Silva",
                "Nascimento" => "15/05/1980",
                "Idade" => 43,
                "Sexo" => "F",
                "Data_Inicio_Tratamento" => "10/01/2023",
                "CID" => "C50.9",
                "Finalidade" => "Quimioterapia"
            ],
            [
                "id" => 2,
                "Operadora" => "SulAmérica",
                "Prestador_Nome" => "Clínica Oncológica",
                "Prestador_Nome_Fantasia" => "OncoMed",
                "Paciente_Codigo" => "P002",
                "Nome" => "José Santos",
                "Nascimento" => "22/08/1975",
                "Idade" => 48,
                "Sexo" => "M",
                "Data_Inicio_Tratamento" => "15/02/2023",
                "CID" => "C61",
                "Finalidade" => "Radioterapia"
            ],
            [
                "id" => 3,
                "Operadora" => "Bradesco Saúde",
                "Prestador_Nome" => "Instituto de Oncologia",
                "Prestador_Nome_Fantasia" => "OncoInstituto",
                "Paciente_Codigo" => "P003",
                "Nome" => "Ana Oliveira",
                "Nascimento" => "30/11/1990",
                "Idade" => 33,
                "Sexo" => "F",
                "Data_Inicio_Tratamento" => "05/03/2023",
                "CID" => "C18.9",
                "Finalidade" => "Adjuvante"
            ]
        ];
    }*/

    // Retornar dados
    http_response_code(200);
    echo json_encode($pacientes);

    if (isset($stmt)) {
        $stmt->close();
    }
    
} catch (Exception $e) {
    error_log("Erro em get_pacientes.php: " . $e->getMessage());
    
    /* Em caso de erro, retornar dados fictícios para evitar travamento da interface
    $dadosExemplo = [
        [
            "id" => 1,
            "Operadora" => "Unimed",
            "Prestador_Nome" => "Hospital São Lucas",
            "Prestador_Nome_Fantasia" => "São Lucas",
            "Paciente_Codigo" => "P001",
            "Nome" => "Maria Silva",
            "Nascimento" => "15/05/1980",
            "Idade" => 43,
            "Sexo" => "F",
            "Data_Inicio_Tratamento" => "10/01/2023",
            "CID" => "C50.9",
            "Finalidade" => "Quimioterapia"
        ],
        [
            "id" => 2,
            "Operadora" => "SulAmérica",
            "Prestador_Nome" => "Clínica Oncológica",
            "Prestador_Nome_Fantasia" => "OncoMed",
            "Paciente_Codigo" => "P002",
            "Nome" => "José Santos",
            "Nascimento" => "22/08/1975",
            "Idade" => 48,
            "Sexo" => "M",
            "Data_Inicio_Tratamento" => "15/02/2023",
            "CID" => "C61",
            "Finalidade" => "Radioterapia"
        ]
    ];*/
    
    http_response_code(200); // Erro tratado, retornando 200 com dados fictícios
    echo json_encode($dadosExemplo);
}

// Fechar conexões
if (isset($conn)) {
    $conn->close();
}
if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>