<?php
// Cabeçalhos CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Responder imediatamente às solicitações OPTIONS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ativar log de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Função para logging personalizado
function logDebug($message) {
    error_log("[" . date('Y-m-d H:i:s') . "] " . $message);
}

try {
    // Incluir as configurações - já estabelece conexões em $conn e $conn_pacientes
    include_once("../../config.php");
    
    // IMPORTANTE: Definir $database para compatibilidade
    $database = $dbname_servico;
    
    // Cria o diretório de logs se ele não existir
    $log_dir = "../logs";
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    // Iniciar arquivo de log detalhado para verificação
    $logfile = @fopen("$log_dir/via_adm_log.txt", "w");
    if ($logfile === false) {
        // Se não conseguir criar no diretório de logs, usa o diretório atual
        $logfile = @fopen("via_adm_log.txt", "w");
    }
    
    if ($logfile) {
        fwrite($logfile, "ID_PROTOCOLO\tPROTOCOLO_NOME\tPROTOCOLO_VIAADM\tVIA_ADMINISTRACAO\n");
    }
    
    // Usar conexões já estabelecidas em config.php
    if (!isset($conn_pacientes) || $conn_pacientes->connect_error) {
        throw new Exception("Erro ao conectar ao banco bd_pacientestto: " . 
            (isset($conn_pacientes) ? $conn_pacientes->connect_error : "Conexão não inicializada"));
    }
    logDebug("Conexão com bd_pacientestto estabelecida");
    fwrite($logfile, "# Conexão com bd_pacientestto estabelecida\n");
    
    if (!isset($conn) || $conn->connect_error) {
        logDebug("Aviso: Problema com a conexão ao banco bd_servico");
        fwrite($logfile, "# AVISO: Problema com a conexão ao banco bd_servico\n");
    } else {
        logDebug("Conexão com bd_servico estabelecida");
        fwrite($logfile, "# Conexão com bd_servico estabelecida\n");
    }
    
    // Verificar se a coluna CID existe
    $checkCID = $conn_pacientes->query("SHOW COLUMNS FROM Protocolo LIKE 'CID'");
    $cidExists = ($checkCID && $checkCID->num_rows > 0);
    
    // Consulta principal para obter os protocolos
    // IMPORTANTE: Troque "id_protocolo" por "id AS id_protocolo" se o nome da coluna for apenas "id"
    // Verifique a estrutura da sua tabela
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
                
    // Adicionar CID à consulta se existir
    if ($cidExists) {
        $sql .= ", CID";
    }
    
    $sql .= " FROM Protocolo ORDER BY Protocolo_Nome";
    logDebug("SQL Protocolo: " . $sql);
    fwrite($logfile, "# SQL: {$sql}\n");
            
    $result = $conn_pacientes->query($sql);
    
    if (!$result) {
        throw new Exception("Erro na consulta de protocolos: " . $conn_pacientes->error);
    }
    
    $protocolos = [];
    
    // Contadores para estatísticas
    $total_protocolos = 0;
    $protocolos_com_via = 0;
    $vias_encontradas = 0;
    
    // Verificar a estrutura da tabela dViaadministracao antes de preparar a consulta
    $checkViaTable = false;
    if ($conn && !$conn->connect_error) {
        $check_via_table = $conn->query("SHOW TABLES LIKE 'dViaadministracao'");
        $checkViaTable = ($check_via_table && $check_via_table->num_rows > 0);
        if (!$checkViaTable) {
            logDebug("Aviso: Tabela dViaadministracao não encontrada no banco bd_servico");
            fwrite($logfile, "# AVISO: Tabela dViaadministracao não encontrada no banco bd_servico\n");
        } else {
            fwrite($logfile, "# Tabela dViaadministracao encontrada no banco bd_servico\n");
        }
    }
    
    // Preparar a consulta de via de administração apenas se a tabela existir
    $stmt_via = null;
    if ($conn && !$conn->connect_error && $checkViaTable) {
        // Primeiro verificar as colunas da tabela
        $check_via_cols = $conn->query("SHOW COLUMNS FROM dViaadministracao");
        $via_cols = [];
        while ($col = $check_via_cols->fetch_assoc()) {
            $via_cols[] = $col['Field'];
        }
        
        fwrite($logfile, "# Colunas encontradas na tabela dViaadministracao: " . implode(", ", $via_cols) . "\n");
        
        // Verificar se as colunas necessárias existem
        if (in_array('idviaadministracao', $via_cols) && in_array('Via_administracao', $via_cols)) {
            $sql_via = "SELECT Via_administracao FROM dViaadministracao WHERE idviaadministracao = ?";
            $stmt_via = $conn->prepare($sql_via);
            if (!$stmt_via) {
                logDebug("Aviso: Falha ao preparar consulta de via de administração: " . $conn->error);
                fwrite($logfile, "# ERRO: Falha ao preparar consulta de via de administração: {$conn->error}\n");
            } else {
                fwrite($logfile, "# Consulta de via de administração preparada com sucesso\n");
            }
        } else {
            logDebug("Aviso: Colunas necessárias não encontradas na tabela dViaadministracao");
            fwrite($logfile, "# AVISO: Colunas necessárias não encontradas na tabela dViaadministracao\n");
        }
    }
    
    while ($row = $result->fetch_assoc()) {
        $total_protocolos++;
        
        // Dados básicos do protocolo
        $protocolo = [
            'id' => $row['id_protocolo'],
            'id_protocolo' => $row['id_protocolo'], // Adicionar para compatibilidade
            'Servico_Codigo' => $row['Servico_Codigo'],
            'Protocolo_Nome' => $row['Protocolo_Nome'],
            'Protocolo_Sigla' => $row['Protocolo_Sigla'],
            'Protocolo_Dose_M' => $row['Protocolo_Dose_M'],
            'Protocolo_Dose_Total' => $row['Protocolo_Dose_Total'],
            'Protocolo_Dias_de_Aplicacao' => $row['Protocolo_Dias_de_Aplicacao'],
            'Protocolo_ViaAdm' => $row['Protocolo_ViaAdm'],
            'Linha' => $row['Linha']
        ];
        
        // Adicionar CID se existir
        if ($cidExists && isset($row['CID'])) {
            $protocolo['CID'] = $row['CID'];
        } else {
            $protocolo['CID'] = '';
        }
        
        // Buscar Via de Administração se estiver disponível
        if ($stmt_via && !empty($row['Protocolo_ViaAdm'])) {
            $protocolos_com_via++;
            $via_adm_id = $row['Protocolo_ViaAdm'];
            
            $stmt_via->bind_param("i", $via_adm_id);
            $stmt_via->execute();
            $result_via = $stmt_via->get_result();
            
            if ($result_via && $result_via->num_rows > 0) {
                $row_via = $result_via->fetch_assoc();
                $via_nome = $row_via['Via_administracao'];
                $protocolo['Via_administracao'] = $via_nome;
                $vias_encontradas++;
                
                // Log detalhado para via encontrada
                fwrite($logfile, "{$row['id_protocolo']}\t{$row['Protocolo_Nome']}\t{$via_adm_id}\t{$via_nome}\n");
                logDebug("Via encontrada para ID {$via_adm_id}: {$via_nome}");
            } else {
                $protocolo['Via_administracao'] = '';
                
                // Log para via não encontrada
                fwrite($logfile, "{$row['id_protocolo']}\t{$row['Protocolo_Nome']}\t{$via_adm_id}\tNÃO ENCONTRADO\n");
                logDebug("Nenhuma via encontrada para ID {$via_adm_id}");
            }
        } else {
            $protocolo['Via_administracao'] = '';
            if (!empty($row['Protocolo_ViaAdm'])) {
                fwrite($logfile, "{$row['id_protocolo']}\t{$row['Protocolo_Nome']}\t{$row['Protocolo_ViaAdm']}\tSEM CONSULTA\n");
            }
        }
        
        // Definimos um valor vazio para PrincipioAtivo por enquanto
        $protocolo['PrincipioAtivo'] = '';
        
        // Adicionar o protocolo ao array
        $protocolos[] = $protocolo;
    }
    
    // Adicionar estatísticas ao log
    $porcentagem = ($protocolos_com_via > 0) ? round(($vias_encontradas / $protocolos_com_via) * 100, 2) : 0;
    fwrite($logfile, "\n# ESTATÍSTICAS:\n");
    fwrite($logfile, "# Total de protocolos: {$total_protocolos}\n");
    fwrite($logfile, "# Protocolos com ViaAdm: {$protocolos_com_via}\n");
    fwrite($logfile, "# Vias encontradas: {$vias_encontradas} ({$porcentagem}%)\n");
    
    // Fechar os statements
    if ($stmt_via) $stmt_via->close();
    
    // Retornar os dados como JSON
    echo json_encode($protocolos);
    logDebug("Retornados " . count($protocolos) . " protocolos com sucesso");
    fwrite($logfile, "# Retornados " . count($protocolos) . " protocolos com sucesso\n");
    
} catch (Exception $e) {
    // Em caso de erro, retornar código 500 e mensagem de erro
    http_response_code(500);
    echo json_encode([
        "error" => $e->getMessage(),
        "trace" => $e->getTraceAsString()
    ]);
    logDebug("ERRO: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    if (isset($logfile)) {
        fwrite($logfile, "# ERRO: " . $e->getMessage() . "\n");
    }
} finally {
    // Fechar arquivo de log
    if (isset($logfile) && $logfile) {
        fwrite($logfile, "# Processamento finalizado\n");
        fclose($logfile);
    }
    
    // Não fechar as conexões principais, já que foram estabelecidas pelo config.php
    // Elas serão fechadas automaticamente no fim do script
}
?>