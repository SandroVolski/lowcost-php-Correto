<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar log detalhado de erros PHP
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once("../../config.php");
    
    // Conexão com o banco de dados de pacientes
    $conn_pacientes = new mysqli($host, $user, $pass, "bd_pacientestto", $port);
    
    if ($conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com o banco bd_pacientestto: " . $conn_pacientes->connect_error);
    }
    
    // Verificar se o ID foi fornecido
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("ID do protocolo não fornecido.");
    }
    
    $id_protocolo = intval($_GET['id']);
    
    // Verificar se a tabela Protocolo possui o campo CID
    $checkCidField = "SHOW COLUMNS FROM Protocolo LIKE 'CID'";
    $cidFieldExists = $conn_pacientes->query($checkCidField)->num_rows > 0;
    
    // Construir a consulta SQL baseada na existência do campo CID
    $sql = "SELECT
                id_protocolo,
                Servico_Codigo,
                Protocolo_Nome,
                Protocolo_Sigla,
                Protocolo_Dose_M,
                Protocolo_Dose_Total,
                Protocolo_Dias_de_Aplicacao,
                Protocolo_ViaAdm,
                Linha";
                
    // Adicionar CID à consulta se o campo existir            
    if ($cidFieldExists) {
        $sql .= ", CID";
    }
    
    $sql .= " FROM Protocolo WHERE id_protocolo = ?";
    
    $stmt = $conn_pacientes->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Erro ao preparar consulta: " . $conn_pacientes->error);
    }
    
    $stmt->bind_param("i", $id_protocolo);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["error" => "Protocolo não encontrado."]);
        exit;
    }
    
    $protocolo = $result->fetch_assoc();
    $stmt->close();
    
    // Preparar a resposta
    $response = [
        'id' => $protocolo['id_protocolo'],
        'Servico_Codigo' => $protocolo['Servico_Codigo'],
        'Protocolo_Nome' => $protocolo['Protocolo_Nome'],
        'Protocolo_Sigla' => $protocolo['Protocolo_Sigla'],
        'Protocolo_Dose_M' => $protocolo['Protocolo_Dose_M'],
        'Protocolo_Dose_Total' => $protocolo['Protocolo_Dose_Total'],
        'Protocolo_Dias_de_Aplicacao' => $protocolo['Protocolo_Dias_de_Aplicacao'],
        'Protocolo_ViaAdm' => $protocolo['Protocolo_ViaAdm'],
        'Linha' => $protocolo['Linha']
    ];
    
    // Adicionar CID à resposta se o campo existir
    if ($cidFieldExists && isset($protocolo['CID'])) {
        $response['CID'] = $protocolo['CID'];
    }
    
    // Se houver um Servico_Codigo, buscar o PrincipioAtivo do serviço
    if (!empty($protocolo['Servico_Codigo'])) {
        $sql_pa = "SELECT 
                      pa.Nome as PrincipioAtivo_Nome
                  FROM 
                      dServicoRelacionada sr
                  LEFT JOIN 
                      dPrincipioativo pa ON sr.idPrincipioAtivo = pa.idPrincipioAtivo
                  WHERE 
                      sr.Servico_Codigo = ?";
        
        $stmt_pa = $conn->prepare($sql_pa);
        if ($stmt_pa) {
            $stmt_pa->bind_param("s", $protocolo['Servico_Codigo']);
            $stmt_pa->execute();
            $result_pa = $stmt_pa->get_result();
            
            if ($result_pa->num_rows > 0) {
                $row_pa = $result_pa->fetch_assoc();
                $response['PrincipioAtivo'] = $row_pa['PrincipioAtivo_Nome'];
            }
            
            $stmt_pa->close();
        }
        
        // Buscar a via de administração
        if (!empty($protocolo['Protocolo_ViaAdm'])) {
            $sql_via = "SELECT 
                            Via_administracao
                        FROM 
                            dViaadministracao
                        WHERE 
                            idviaadministracao = ?";
            
            $stmt_via = $conn->prepare($sql_via);
            if ($stmt_via) {
                $stmt_via->bind_param("i", $protocolo['Protocolo_ViaAdm']);
                $stmt_via->execute();
                $result_via = $stmt_via->get_result();
                
                if ($result_via->num_rows > 0) {
                    $row_via = $result_via->fetch_assoc();
                    $response['Via_administracao'] = $row_via['Via_administracao'];
                }
                
                $stmt_via->close();
            }
        }
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
    error_log("Erro em get_protocolo_by_id.php: " . $e->getMessage());
} finally {
    if (isset($conn_pacientes)) {
        $conn_pacientes->close();
    }
    if (isset($conn)) {
        $conn->close();
    }
}
?>