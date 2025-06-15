<?php
// api/PacientesEmTratamento/debug_patient.php
// Arquivo para debugar o que está acontecendo com get_paciente_by_id.php

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$tests = [
    "timestamp" => date('Y-m-d H:i:s'),
    "base_url" => "https://api.lowcostonco.com.br/backend-php/api/PacientesEmTratamento"
];

// Teste 1: Verificar se get_paciente_by_id.php existe e responde
$patientEndpoint = "https://api.lowcostonco.com.br/backend-php/api/PacientesEmTratamento/get_paciente_by_id.php";

// Buscar um ID de paciente válido para testar
try {
    include_once("../../config.php");
    
    if (isset($conn_pacientes) && !$conn_pacientes->connect_error) {
        // Buscar qualquer paciente para teste
        $result = $conn_pacientes->query("SELECT id, Paciente_Nome FROM Pacientes LIMIT 3");
        
        $tests["pacientes_disponiveis"] = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $tests["pacientes_disponiveis"][] = [
                    "id" => $row['id'],
                    "nome" => $row['Paciente_Nome'],
                    "url_teste" => $patientEndpoint . "?id=" . $row['id']
                ];
            }
            
            // Testar o primeiro paciente
            $firstPatient = $tests["pacientes_disponiveis"][0];
            $testUrl = $firstPatient["url_teste"];
            
            $tests["teste_endpoint"] = [
                "url_testada" => $testUrl,
                "paciente_id" => $firstPatient["id"],
                "paciente_nome" => $firstPatient["nome"]
            ];
            
            // Fazer requisição para o endpoint
            $context = stream_context_create([
                "http" => [
                    "timeout" => 10,
                    "method" => "GET"
                ]
            ]);
            
            $response = @file_get_contents($testUrl, false, $context);
            
            if ($response === false) {
                $tests["teste_endpoint"]["status"] = "ERRO";
                $tests["teste_endpoint"]["error"] = "Não foi possível conectar ao endpoint";
            } else {
                $decodedResponse = json_decode($response, true);
                
                if (json_last_error() === JSON_ERROR_NONE) {
                    $tests["teste_endpoint"]["status"] = "OK";
                    $tests["teste_endpoint"]["response_keys"] = array_keys($decodedResponse);
                    
                    // Verificar se tem os campos esperados pelo React
                    $expectedFields = ['id', 'Nome', 'Paciente_Codigo', 'Nascimento', 'Sexo'];
                    $missingFields = [];
                    
                    foreach ($expectedFields as $field) {
                        if (!isset($decodedResponse[$field])) {
                            $missingFields[] = $field;
                        }
                    }
                    
                    if (empty($missingFields)) {
                        $tests["teste_endpoint"]["campos_ok"] = "Todos os campos esperados estão presentes";
                    } else {
                        $tests["teste_endpoint"]["campos_ausentes"] = $missingFields;
                    }
                    
                    // Mostrar uma amostra da resposta
                    $tests["teste_endpoint"]["sample_response"] = $decodedResponse;
                    
                } else {
                    $tests["teste_endpoint"]["status"] = "ERRO";
                    $tests["teste_endpoint"]["error"] = "Resposta não é JSON válido";
                    $tests["teste_endpoint"]["response_preview"] = substr($response, 0, 300);
                }
            }
            
        } else {
            $tests["erro"] = "Nenhum paciente encontrado na base de dados";
        }
        
    } else {
        $tests["erro"] = "Não foi possível conectar ao banco de dados";
    }
    
} catch (Exception $e) {
    $tests["erro"] = "Erro ao executar teste: " . $e->getMessage();
}

// Teste 2: Verificar se PatientContext está fazendo a chamada correta
$tests["debug_patientcontext"] = [
    "url_esperada" => "https://api.lowcostonco.com.br/backend-php/api/PacientesEmTratamento/get_paciente_by_id.php?id=[ID]",
    "metodo_esperado" => "GET",
    "headers_esperados" => [
        "Content-Type: application/json",
        "Access-Control-Allow-Origin: *"
    ]
];

// Teste 3: Verificar logs de erro do servidor
$logFile = __DIR__ . '/patient_log.txt';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    $tests["logs_servidor"] = [
        "arquivo_existe" => true,
        "ultimas_linhas" => array_slice(explode("\n", $logs), -10)
    ];
} else {
    $tests["logs_servidor"] = [
        "arquivo_existe" => false,
        "nota" => "Arquivo de log não encontrado. Não há logs de erro recentes."
    ];
}

// Recomendações baseadas nos testes
$tests["recomendacoes"] = [];

if (isset($tests["teste_endpoint"]["status"]) && $tests["teste_endpoint"]["status"] === "OK") {
    $tests["recomendacoes"][] = "✅ Endpoint get_paciente_by_id.php está respondendo corretamente";
    $tests["recomendacoes"][] = "🔍 O problema pode estar no lado do React. Verifique o console do navegador";
    $tests["recomendacoes"][] = "🔍 Verifique se PatientContext está fazendo a chamada correta";
} else {
    $tests["recomendacoes"][] = "❌ Endpoint get_paciente_by_id.php tem problemas";
    $tests["recomendacoes"][] = "🔧 Verifique a conexão com o banco de dados";
    $tests["recomendacoes"][] = "🔧 Verifique se as tabelas Pacientes existem";
}

if (isset($tests["teste_endpoint"]["campos_ausentes"])) {
    $tests["recomendacoes"][] = "⚠️ Campos ausentes na resposta: " . implode(", ", $tests["teste_endpoint"]["campos_ausentes"]);
}

echo json_encode($tests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>