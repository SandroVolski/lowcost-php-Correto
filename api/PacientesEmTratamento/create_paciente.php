<?php
// Configurações de cabeçalho para permitir CORS e definir o tipo de resposta
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
    $logFile = __DIR__ . '/patient_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logError("=== INÍCIO DO PROCESSAMENTO ===");
    
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

    // Verificar se os dados essenciais foram fornecidos
    if (!isset($data['Nome']) || empty($data['Nome'])) {
        http_response_code(400);
        echo json_encode(["message" => "Nome do paciente é obrigatório"]);
        exit;
    }

    // Obter IDs de operadora e prestador se nomes foram fornecidos
    $operadoraId = null;
    $prestadorId = null;
    
    // Verificar estrutura da tabela Pacientes
    logError("Verificando estrutura da tabela Pacientes");
    $tableInfo = $conn_pacientes->query("DESCRIBE Pacientes");
    $colunas = [];
    
    if ($tableInfo) {
        while ($col = $tableInfo->fetch_assoc()) {
            $colunas[] = $col['Field'];
            logError("Coluna: " . $col['Field'] . ", Tipo: " . $col['Type']);
        }
    } else {
        logError("ERRO: Não foi possível obter estrutura da tabela: " . $conn_pacientes->error);
    }

    // Resolver ID da operadora se disponível
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
    
    // Resolver ID do prestador se disponível
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

    // Processar datas
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
    $codigo = isset($data['Paciente_Codigo']) ? $data['Paciente_Codigo'] : '';
    $nome = $data['Nome'];
    $sexo = isset($data['Sexo']) ? $data['Sexo'] : '';
    $cid = isset($data['CID']) ? $data['CID'] : '';

    // Usar query direta em vez de prepared statement com bind_param
    // Isso evita problemas com NULL em bind_param
    $sql = "INSERT INTO Pacientes (
        Operadora,
        Prestador,
        Codigo,
        Paciente_Nome,
        Data_Nascimento,
        Sexo,
        Data_Inicio_Tratamento,
        Cid_Diagnostico
    ) VALUES (";
    
    $sql .= $operadoraId === null ? "NULL, " : "$operadoraId, ";
    $sql .= $prestadorId === null ? "NULL, " : "$prestadorId, ";
    $sql .= !empty($codigo) ? "'$codigo', " : "NULL, ";
    $sql .= "'$nome', ";
    $sql .= $nascimento === null ? "NULL, " : "'$nascimento', ";
    $sql .= !empty($sexo) ? "'$sexo', " : "NULL, ";
    $sql .= $dataInicioTratamento === null ? "NULL, " : "'$dataInicioTratamento', ";
    $sql .= !empty($cid) ? "'$cid'" : "NULL";
    $sql .= ")";
    
    logError("SQL: " . $sql);

    // Executar a consulta direta
    if (!$conn_pacientes->query($sql)) {
        throw new Exception("Erro ao inserir paciente: " . $conn_pacientes->error);
    }

    // Obter o ID do paciente inserido
    $patientId = $conn_pacientes->insert_id;
    logError("Paciente inserido com sucesso. ID: " . $patientId);

    http_response_code(201);
    echo json_encode([
        "message" => "Paciente criado com sucesso",
        "id" => $patientId
    ]);
    
    logError("=== FIM DO PROCESSAMENTO ===");
    
} catch (Exception $e) {
    logError("ERRO: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        "message" => "Erro ao criar paciente", 
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