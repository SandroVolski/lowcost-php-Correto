<?php
// Configurações de cabeçalho para permitir CORS e definir o tipo de resposta
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
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

// Função para log
function logError($message) {
    $logFile = __DIR__ . '/patient_update_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logError("=== INÍCIO DA ATUALIZAÇÃO DE PACIENTE ===");
    
    // Incluir arquivo de configuração
    include_once("../../config.php");

    // Verifica se as conexões com os bancos de dados foram estabelecidas
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro de conexão com o banco de pacientes.");
    }
    logError("Conexão com banco de pacientes estabelecida");
    
    if (!isset($conn) || $conn->connect_error) {
        logError("Aviso: Conexão com banco de serviço não disponível. Alguns dados podem não ser resolvidos.");
    } else {
        logError("Conexão com banco de serviço estabelecida");
    }

    // Obter dados brutos
    $rawData = file_get_contents("php://input");
    if (!$rawData) {
        throw new Exception("Nenhum dado recebido");
    }
    logError("Dados recebidos: " . $rawData);

    // Converter para JSON
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Erro ao decodificar JSON: " . json_last_error_msg());
    }

    // Verificar se o ID foi fornecido
    if (!isset($data['id']) || empty($data['id'])) {
        http_response_code(400);
        echo json_encode(["message" => "ID do paciente não fornecido"]);
        exit;
    }

    $id = intval($data['id']);
    logError("ID do paciente a atualizar: " . $id);
    
    // Verificar primeiro se o paciente existe
    $checkSql = "SELECT id FROM Pacientes WHERE id = $id";
    $checkResult = $conn_pacientes->query($checkSql);
    
    if (!$checkResult || $checkResult->num_rows === 0) {
        logError("Paciente com ID $id não encontrado");
        throw new Exception("Paciente não encontrado com ID: $id");
    }
    logError("Paciente encontrado com ID: " . $id);

    // Obter IDs de operadora e prestador se nomes foram fornecidos
    $operadoraId = null;
    $prestadorId = null;
    
    if (isset($data['Operadora']) && !empty($data['Operadora']) && isset($conn) && !$conn->connect_error) {
        try {
            $sqlOperadora = "SELECT id FROM bd_producaom_operadoras WHERE Nome_Fantasia = ?";
            $stmtOperadora = $conn->prepare($sqlOperadora);
            if ($stmtOperadora) {
                $stmtOperadora->bind_param("s", $data['Operadora']);
                $stmtOperadora->execute();
                $resultOperadora = $stmtOperadora->get_result();
                
                if ($resultOperadora->num_rows > 0) {
                    $operadoraId = $resultOperadora->fetch_assoc()['id'];
                    logError("Operadora ID resolvido: " . $operadoraId);
                } else {
                    logError("Operadora não encontrada para: " . $data['Operadora']);
                }
                
                $stmtOperadora->close();
            }
        } catch (Exception $e) {
            logError("Erro ao resolver operadora: " . $e->getMessage());
        }
    }
    
    if (isset($data['Prestador']) && !empty($data['Prestador']) && isset($conn) && !$conn->connect_error) {
        try {
            $sqlPrestador = "SELECT id FROM bd_empresas_empresas WHERE Prestador_Nome = ? OR Prestador_Nome_Fantasia = ?";
            $stmtPrestador = $conn->prepare($sqlPrestador);
            if ($stmtPrestador) {
                $stmtPrestador->bind_param("ss", $data['Prestador'], $data['Prestador']);
                $stmtPrestador->execute();
                $resultPrestador = $stmtPrestador->get_result();
                
                if ($resultPrestador->num_rows > 0) {
                    $prestadorId = $resultPrestador->fetch_assoc()['id'];
                    logError("Prestador ID resolvido: " . $prestadorId);
                } else {
                    logError("Prestador não encontrado para: " . $data['Prestador']);
                }
                
                $stmtPrestador->close();
            }
        } catch (Exception $e) {
            logError("Erro ao resolver prestador: " . $e->getMessage());
        }
    }

    // Converter formato de data DD/MM/AAAA para AAAA-MM-DD se for fornecida
    $nascimento = null;
    if (isset($data['Nascimento']) && !empty($data['Nascimento'])) {
        $dateFormats = ['d/m/Y', 'Y-m-d'];
        $parsedDate = false;
        
        foreach ($dateFormats as $format) {
            $dateObj = DateTime::createFromFormat($format, $data['Nascimento']);
            if ($dateObj !== false) {
                $nascimento = $dateObj->format('Y-m-d');
                $parsedDate = true;
                break;
            }
        }
        
        if (!$parsedDate) {
            logError("Formato de data de nascimento inválido: " . $data['Nascimento']);
        }
    }
    
    // Converter formato de data para data de início de tratamento
    $dataInicioTratamento = null;
    if (isset($data['Data_Inicio_Tratamento']) && !empty($data['Data_Inicio_Tratamento'])) {
        $dateFormats = ['d/m/Y', 'Y-m-d'];
        $parsedDate = false;
        
        foreach ($dateFormats as $format) {
            $dateObj = DateTime::createFromFormat($format, $data['Data_Inicio_Tratamento']);
            if ($dateObj !== false) {
                $dataInicioTratamento = $dateObj->format('Y-m-d');
                $parsedDate = true;
                break;
            }
        }
        
        if (!$parsedDate) {
            logError("Formato de data de início de tratamento inválido: " . $data['Data_Inicio_Tratamento']);
        }
    }

    // Extrair dados do paciente
    $codigo = isset($data['Paciente_Codigo']) ? $data['Paciente_Codigo'] : null;
    $nome = isset($data['Nome']) ? $data['Nome'] : null;
    $sexo = isset($data['Sexo']) ? $data['Sexo'] : null;
    $cid = isset($data['CID']) ? $data['CID'] : null;

    // Construir a query de atualização manualmente para lidar com NULL adequadamente
    $updateFields = [];
    
    if (isset($data['Operadora'])) {
        $updateFields[] = "Operadora = " . ($operadoraId === null ? "NULL" : $operadoraId);
    }
    
    if (isset($data['Prestador'])) {
        $updateFields[] = "Prestador = " . ($prestadorId === null ? "NULL" : $prestadorId);
    }
    
    if (isset($data['Paciente_Codigo'])) {
        $updateFields[] = "Codigo = " . (empty($codigo) ? "NULL" : "'$codigo'");
    }
    
    if (isset($data['Nome'])) {
        $updateFields[] = "Paciente_Nome = " . (empty($nome) ? "NULL" : "'$nome'");
    }
    
    if (isset($data['Nascimento'])) {
        $updateFields[] = "Data_Nascimento = " . ($nascimento === null ? "NULL" : "'$nascimento'");
    }
    
    if (isset($data['Sexo'])) {
        $updateFields[] = "Sexo = " . (empty($sexo) ? "NULL" : "'$sexo'");
    }
    
    if (isset($data['Data_Inicio_Tratamento'])) {
        $updateFields[] = "Data_Inicio_Tratamento = " . ($dataInicioTratamento === null ? "NULL" : "'$dataInicioTratamento'");
    }
    
    if (isset($data['CID'])) {
        $updateFields[] = "Cid_Diagnostico = " . (empty($cid) ? "NULL" : "'$cid'");
    }
    
    // Verificar se há campos para atualizar
    if (empty($updateFields)) {
        logError("Aviso: Nenhum campo válido para atualização");
        http_response_code(400);
        echo json_encode(["message" => "Nenhum campo válido para atualização"]);
        exit;
    }
    
    // Construir a query final
    $sql = "UPDATE Pacientes SET " . implode(", ", $updateFields) . " WHERE id = $id";
    logError("SQL de atualização: " . $sql);
    
    // Executar a atualização
    if (!$conn_pacientes->query($sql)) {
        logError("Erro ao executar query: " . $conn_pacientes->error);
        throw new Exception("Erro ao atualizar paciente: " . $conn_pacientes->error);
    }
    
    if ($conn_pacientes->affected_rows > 0) {
        logError("Paciente atualizado com sucesso. Linhas afetadas: " . $conn_pacientes->affected_rows);
        http_response_code(200);
        echo json_encode([
            "message" => "Paciente atualizado com sucesso",
            "id" => $id
        ]);
    } else {
        logError("Nenhuma linha afetada na atualização. Possíveis dados idênticos.");
        http_response_code(200);
        echo json_encode([
            "message" => "Nenhuma alteração feita no paciente",
            "id" => $id
        ]);
    }
    
    logError("=== FIM DA ATUALIZAÇÃO DE PACIENTE ===");
    
} catch (Exception $e) {
    logError("ERRO: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao atualizar paciente", 
        "error" => $e->getMessage()
    ]);
}

// Fechar conexões
if (isset($conn)) {
    $conn->close();
}
if (isset($conn_pacientes)) {
    $conn_pacientes->close();
}
?>