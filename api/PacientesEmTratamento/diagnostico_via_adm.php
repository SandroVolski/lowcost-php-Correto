<?php
// Cabeçalhos CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Ativar log de erros
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    include_once("../../config.php");
    
    // Definir porta padrão se não estiver definida
    if (!isset($port)) {
        $port = 3306;
    }
    
    // Conectar aos dois bancos de dados
    $conn_pacientes = new mysqli($host, $user, $pass, "bd_pacientestto", $port);
    $conn_servico = new mysqli($host, $user, $pass, "bd_servico", $port);
    
    if ($conn_pacientes->connect_error) {
        throw new Exception("Erro ao conectar a bd_pacientestto: " . $conn_pacientes->connect_error);
    }
    
    if ($conn_servico->connect_error) {
        throw new Exception("Erro ao conectar a bd_servico: " . $conn_servico->connect_error);
    }
    
    // Array para armazenar resultados
    $resultado = [
        'conexoes_ok' => true,
        'tabelas_encontradas' => [],
        'testes_relacao' => []
    ];
    
    // Verificar se as tabelas existem
    $check_protocolo = $conn_pacientes->query("SHOW TABLES LIKE 'Protocolo'");
    $check_via = $conn_servico->query("SHOW TABLES LIKE 'dViaadministracao'");
    
    $resultado['tabelas_encontradas']['Protocolo'] = ($check_protocolo && $check_protocolo->num_rows > 0);
    $resultado['tabelas_encontradas']['dViaadministracao'] = ($check_via && $check_via->num_rows > 0);
    
    // Se ambas as tabelas existem, vamos testar a relação
    if ($resultado['tabelas_encontradas']['Protocolo'] && $resultado['tabelas_encontradas']['dViaadministracao']) {
        // Obter todos os Protocolo_ViaAdm únicos da tabela Protocolo
        $sql_protocolo = "SELECT DISTINCT Protocolo_ViaAdm FROM Protocolo WHERE Protocolo_ViaAdm IS NOT NULL";
        $result_protocolo = $conn_pacientes->query($sql_protocolo);
        
        if ($result_protocolo) {
            // Preparar a consulta para buscar a via de administração
            $sql_via = "SELECT idviaadministracao, Via_administracao FROM dViaadministracao WHERE idviaadministracao = ?";
            $stmt_via = $conn_servico->prepare($sql_via);
            
            if ($stmt_via) {
                while ($row = $result_protocolo->fetch_assoc()) {
                    $via_adm_id = $row['Protocolo_ViaAdm'];
                    
                    // Adicionar ao resultado
                    $teste = [
                        'Protocolo_ViaAdm' => $via_adm_id,
                        'Via_administracao' => null,
                        'encontrado' => false
                    ];
                    
                    // Buscar a correspondente Via_administracao
                    $stmt_via->bind_param("i", $via_adm_id);
                    $stmt_via->execute();
                    $result_via = $stmt_via->get_result();
                    
                    if ($result_via && $result_via->num_rows > 0) {
                        $row_via = $result_via->fetch_assoc();
                        $teste['Via_administracao'] = $row_via['Via_administracao'];
                        $teste['encontrado'] = true;
                    }
                    
                    $resultado['testes_relacao'][] = $teste;
                }
                
                // Adicionar estatísticas
                $total = count($resultado['testes_relacao']);
                $encontrados = count(array_filter($resultado['testes_relacao'], function($item) {
                    return $item['encontrado'];
                }));
                
                $resultado['estatisticas'] = [
                    'total_de_vias' => $total,
                    'vias_encontradas' => $encontrados,
                    'porcentagem_sucesso' => $total > 0 ? round(($encontrados / $total) * 100, 2) : 0
                ];
                
                $stmt_via->close();
            } else {
                $resultado['erro_stmt'] = $conn_servico->error;
            }
        } else {
            $resultado['erro_consulta'] = $conn_pacientes->error;
        }
    }
    
    // Retornar o resultado em formato JSON
    echo json_encode($resultado, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'erro' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} finally {
    // Fechar conexões
    if (isset($conn_pacientes)) $conn_pacientes->close();
    if (isset($conn_servico)) $conn_servico->close();
}
?>