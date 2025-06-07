<?php
// Configurações CORS simplificadas - coloque no topo absoluto do arquivo
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

// Se for uma requisição OPTIONS, responda imediatamente
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Definir tipo de conteúdo
header("Content-Type: application/json; charset=UTF-8");

// Habilitar exibição de erros para debug (remova em produção)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Incluir arquivo de configuração
include_once("../../config.php");

// Parâmetro para carregar todos os dados de uma vez
$loadAll = isset($_GET['loadAll']) && $_GET['loadAll'] === 'true';

// Parâmetros de paginação e ordenação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 500;
$offset = ($page - 1) * $limit;

// Parâmetros de ordenação
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';
$orderBy = isset($_GET['orderBy']) ? $_GET['orderBy'] : 'id';

// Termo de pesquisa e tipo
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$searchType = isset($_GET['searchType']) ? $_GET['searchType'] : 'auto';

// Detectar automaticamente o tipo de pesquisa se for 'auto'
if ($searchType === 'auto' && !empty($searchTerm)) {
    // Se contém apenas números (e possivelmente pontos), é um código
    if (preg_match('/^[0-9.]+$/', $searchTerm)) {
        $searchType = 'code';
    } 
    // Se contém letras, é um princípio ativo
    else {
        $searchType = 'active';
    }
}

// Lista de campos permitidos para ordenação e suas colunas correspondentes na consulta SQL
$allowedFields = [
    'id' => 's.id',
    'Cod' => 's.Cod',
    'Codigo_TUSS' => 's.Codigo_TUSS',
    'Codigo_Celos' => 's.Codigo_Celos', // Adicionado
    'Descricao_Padronizada' => 's.Descricao_Padronizada',
    'Descricao_Resumida' => 's.Descricao_Resumida',
    'Descricao_Comercial' => 's.Descricao_Comercial',
    'Descricao_Comercial_Completa' => 's.Descricao_Comercial_Completa', // Novo campo
    'Descricao_TUSS' => 's.Descricao_TUSS', // Novo campo
    'Concentracao' => 's.Concentracao',
    'UnidadeFracionamento' => 's.UnidadeFracionamento',
    'Fracionamento' => 's.Fracionamento',
    'Laboratorio' => 's.Laboratorio',
    'Uso' => 's.Uso',
    'Revisado_Farma' => 's.Revisado_Farma',
    'Revisado_ADM' => 's.Revisado_ADM',
    // Registro Visa
    'RegistroVisa' => 'r.RegistroVisa',
    'Cod_Ggrem' => 'r.Cod_Ggrem',
    'PrincipioAtivo' => 'r.PrincipioAtivo',
    'Lab' => 'r.Lab',
    'cnpj_lab' => 'r.cnpj_lab',
    'Classe_Terapeutica' => 'r.Classe_Terapeutica',
    'Tipo_Porduto' => 'r.Tipo_Porduto',
    'Regime_Preco' => 'r.Regime_Preco',
    'Restricao_Hosp' => 'r.Restricao_Hosp',
    'Cap' => 'r.Cap',
    'Confaz87' => 'r.Confaz87',
    'Icms0' => 'r.Icms0',
    'Lista' => 'r.Lista',
    'Status' => 'r.Status',
    // Tabela
    'tabela' => 't.tabela',
    'tabela_classe' => 't.tabela_classe',
    'tabela_tipo' => 't.tabela_tipo',
    'classe_Jaragua_do_sul' => 't.classe_Jaragua_do_sul',
    'classificacao_tipo' => 't.classificacao_tipo',
    'finalidade' => 't.finalidade',
    'objetivo' => 't.objetivo',
    // Via Administração
    'Via_administracao' => 'v.Via_administracao',
    // Classe Farmacêutica
    'ClasseFarmaceutica' => 'c.ClasseFarmaceutica',
    // Princípio Ativo
    'PrincipioAtivo' => 'p.PrincipioAtivo',
    'PrincipioAtivoClassificado' => 'p.PrincipioAtivoClassificado',
    'FaseUGF' => 'p.FaseUGF',
    // Armazenamento
    'Armazenamento' => 'a.Armazenamento',
    // Medicamento
    'tipo_medicamento' => 'm.tipo_medicamento',
    // Unidade Fracionamento
    'UnidadeFracionamentoDescricao' => 'u.Descricao',
    'Divisor' => 'u.Divisor',
    // Fator Conversão
    'id_fatorconversao' => 'f.id_fatorconversao',
    // Taxas
    'id_taxas' => 'x.id_taxas',
    'tipo_taxa' => 'x.tipo_taxa',
    'TaxaFinalidade' => 'x.finalidade',
    'tempo_infusao' => 'x.tempo_infusao'
];

// Verificar se o campo de ordenação é permitido, caso contrário, usar o padrão
$orderByField = isset($allowedFields[$orderBy]) ? $allowedFields[$orderBy] : 's.id';

// Parte comum da consulta SQL
$sqlBase = "SELECT DISTINCT 
    s.id,
    s.Cod,
    s.Codigo_TUSS,
    s.Codigo_Celos,                  
    s.Descricao_Padronizada,
    s.Descricao_Resumida,
    s.Descricao_Comercial,
    s.Descricao_Comercial_Completa,  
    s.Descricao_TUSS,                
    s.Concentracao,
    s.UnidadeFracionamento,
    s.Fracionamento,
    s.Laboratorio,
    s.Uso,
    s.Revisado_Farma,            
    s.Revisado_ADM, 
    s.idPrincipioAtivo,
    -- Novos campos para Entrada e Pagamento
    s.Unidade_Entrada,
    s.Quantidade_Entrada,
    s.Unidade_Entrada_Convertida,
    s.Quantidade_Convertida,
    s.Unidade_Pagamento_Nao_Fracionado,
    s.Quantidade_Pagamento_Nao_Fracionado,
    s.Unidade_Pagamento_Fracionado,
    s.Quantidade_Pagamento_Fracionado,
    -- Registro Visa
    r.RegistroVisa,
    r.Cod_Ggrem,
    r.PrincipioAtivo,
    r.Lab,
    r.cnpj_lab,
    r.Classe_Terapeutica,
    r.Tipo_Porduto,
    r.Regime_Preco,
    r.Restricao_Hosp,
    r.Cap,
    r.Confaz87,
    r.Icms0,
    r.Lista,
    r.Status,
    -- Tabela
    t.tabela,
    t.tabela_classe,
    t.tabela_tipo,
    t.classe_Jaragua_do_sul,
    t.classificacao_tipo,
    t.finalidade,
    t.objetivo,
    -- Via Administração
    v.Via_administracao,
    -- Classe Farmacêutica
    c.ClasseFarmaceutica,
    -- Princípio Ativo
    p.PrincipioAtivo,
    p.PrincipioAtivoClassificado,
    p.FaseUGF,
    -- Armazenamento
    a.Armazenamento,
    -- Medicamento
    m.tipo_medicamento,
    -- Unidade Fracionamento
    u.UnidadeFracionamento,
    u.Descricao AS UnidadeFracionamentoDescricao,
    u.Divisor,
    -- Fator Conversão
    f.id_fatorconversao,
    -- Taxas
    x.id_taxas,
    x.tipo_taxa,
    x.finalidade AS TaxaFinalidade,
    x.tempo_infusao
FROM dServicoRelacionada s
LEFT JOIN dRegistro_anvisa r ON s.idRegistroVisa = r.RegistroVisa
LEFT JOIN dTabela t ON s.idTabela = t.id_tabela
LEFT JOIN dViaadministracao v ON s.idViaAdministracao = v.idviaadministracao
LEFT JOIN dClasseFarmaceutica c ON s.idClasseFarmaceutica = c.id_medicamento
LEFT JOIN dPrincipioativo p ON s.idPrincipioAtivo = p.idPrincipioAtivo
LEFT JOIN dArmazenamento a ON s.idArmazenamento = a.idArmazenamento
LEFT JOIN dTipo_medicamento m ON s.idMedicamento = m.id_medicamento
LEFT JOIN dUnidadeFracionamento u ON s.idUnidadeFracionamento = u.id_unidadefracionamento
LEFT JOIN dFatorConversao f ON s.idFatorConversao = f.id_fatorconversao
LEFT JOIN dTaxas x ON s.idTaxas = x.id_taxas";

// Condição WHERE para pesquisa com base no tipo de pesquisa
if (!empty($searchTerm)) {
    $searchTermLower = strtolower($searchTerm);
    $searchTermWildcard = '%' . $searchTermLower . '%';
    
    switch($searchType) {
        case 'code':
            // Pesquisar apenas em campos de código
            $sqlBase .= " WHERE (
                LOWER(s.Cod) = ? OR
                LOWER(s.Cod) LIKE ? OR
                LOWER(s.Codigo_TUSS) = ? OR
                LOWER(s.Codigo_TUSS) LIKE ? OR
                LOWER(s.Codigo_Celos) = ? OR
                LOWER(s.Codigo_Celos) LIKE ?
            )";
            break;
            
        case 'active':
            // Pesquisar apenas em campos de princípio ativo principal (dprincipioativo)
            $sqlBase .= " WHERE (
                (p.PrincipioAtivo IS NOT NULL AND LOWER(p.PrincipioAtivo) LIKE ?) OR
                (p.PrincipioAtivoClassificado IS NOT NULL AND LOWER(p.PrincipioAtivoClassificado) LIKE ?)
            )";
            break;
            
        case 'active_visa':
            // Pesquisar apenas no campo PrincipioAtivo do Registro Visa
            $sqlBase .= " WHERE (
                r.PrincipioAtivo IS NOT NULL AND LOWER(r.PrincipioAtivo) LIKE ?
            )";
            break;
            
        case 'description':
            // Pesquisar apenas em descrições
            $sqlBase .= " WHERE (
                LOWER(s.Descricao_Padronizada) LIKE ? OR 
                LOWER(s.Descricao_Resumida) LIKE ? OR
                LOWER(s.Descricao_Comercial) LIKE ? OR
                LOWER(s.Descricao_Comercial_Completa) LIKE ? OR
                LOWER(s.Descricao_TUSS) LIKE ?
            )";
            break;
            
        case 'auto':
            // Se contém apenas números (e possivelmente pontos), é um código
            if (preg_match('/^[0-9.]+$/', $searchTerm)) {
                $sqlBase .= " WHERE (
                    LOWER(s.Cod) = ? OR
                    LOWER(s.Cod) LIKE ? OR
                    LOWER(s.Codigo_TUSS) = ? OR
                    LOWER(s.Codigo_TUSS) LIKE ? OR
                    LOWER(s.Codigo_Celos) = ? OR
                    LOWER(s.Codigo_Celos) LIKE ?
                )";
            } 
            // Se contém letras, pesquisar em ambos os campos de Princípio Ativo
            else {
                $sqlBase .= " WHERE (
                    (p.PrincipioAtivo IS NOT NULL AND LOWER(p.PrincipioAtivo) LIKE ?) OR
                    (p.PrincipioAtivoClassificado IS NOT NULL AND LOWER(p.PrincipioAtivoClassificado) LIKE ?) OR
                    (r.PrincipioAtivo IS NOT NULL AND LOWER(r.PrincipioAtivo) LIKE ?)
                )";
            }
            break;
            
        default: // 'all'
            // Pesquisar em todos os campos
            $sqlBase .= " WHERE (
                LOWER(s.Cod) = ? OR
                LOWER(s.Cod) LIKE ? OR
                LOWER(s.Codigo_TUSS) LIKE ? OR
                LOWER(s.Codigo_Celos) LIKE ? OR
                LOWER(s.Descricao_Padronizada) LIKE ? OR 
                LOWER(s.Descricao_Resumida) LIKE ? OR
                LOWER(s.Descricao_Comercial) LIKE ? OR
                LOWER(s.Descricao_Comercial_Completa) LIKE ? OR
                LOWER(s.Descricao_TUSS) LIKE ? OR
                (p.PrincipioAtivo IS NOT NULL AND LOWER(p.PrincipioAtivo) LIKE ?) OR
                (p.PrincipioAtivoClassificado IS NOT NULL AND LOWER(p.PrincipioAtivoClassificado) LIKE ?) OR
                (r.PrincipioAtivo IS NOT NULL AND LOWER(r.PrincipioAtivo) LIKE ?)
            )";
            break;
    }
}

// Adicionar a cláusula ORDER BY
$sqlBase .= " ORDER BY $orderByField $order";

// Preparar a consulta com ou sem limite
if ($loadAll) {
    // Consulta sem limitação para carregar todos os dados
    $sql = $sqlBase;
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(["error" => "Erro ao preparar a consulta: " . $conn->error]);
        exit;
    }
    
    // Vincular parâmetros de pesquisa se houver termo de pesquisa
    if (!empty($searchTerm)) {
        switch($searchType) {
            case 'code':
                $stmt->bind_param("ssssss", 
                    $searchTermLower,      // Cod exato
                    $searchTermWildcard,   // Cod LIKE
                    $searchTermLower,      // Codigo_TUSS exato
                    $searchTermWildcard,   // Codigo_TUSS LIKE
                    $searchTermLower,      // Codigo_Celos exato
                    $searchTermWildcard    // Codigo_Celos LIKE
                );
                break;
                
            case 'active':
                $stmt->bind_param("ss", 
                    $searchTermWildcard,   // PrincipioAtivo
                    $searchTermWildcard    // PrincipioAtivoClassificado
                );
                break;
                
            case 'active_visa':
                $stmt->bind_param("s", 
                    $searchTermWildcard    // PrincipioAtivo (Registro Visa)
                );
                break;
                
            case 'description':
                $stmt->bind_param("sssss", 
                    $searchTermWildcard,   // Descricao_Padronizada
                    $searchTermWildcard,   // Descricao_Resumida
                    $searchTermWildcard,   // Descricao_Comercial
                    $searchTermWildcard,   // Descricao_Comercial_Completa
                    $searchTermWildcard    // Descricao_TUSS
                );
                break;
                
            case 'auto':
                // Se contém apenas números, pesquisar por códigos
                if (preg_match('/^[0-9.]+$/', $searchTerm)) {
                    $stmt->bind_param("ssssss", 
                        $searchTermLower,      // Cod exato
                        $searchTermWildcard,   // Cod LIKE
                        $searchTermLower,      // Codigo_TUSS exato
                        $searchTermWildcard,   // Codigo_TUSS LIKE
                        $searchTermLower,      // Codigo_Celos exato
                        $searchTermWildcard    // Codigo_Celos LIKE
                    );
                } 
                // Se contém letras, pesquisar princípio ativo em ambas as tabelas
                else {
                    $stmt->bind_param("sss", 
                        $searchTermWildcard,   // PrincipioAtivo
                        $searchTermWildcard,   // PrincipioAtivoClassificado
                        $searchTermWildcard    // PrincipioAtivo (Registro Visa)
                    );
                }
                break;
                
            default: // 'all'
                $stmt->bind_param("ssssssssssss", 
                    $searchTermLower,      // Cod exato
                    $searchTermWildcard,   // Cod LIKE
                    $searchTermWildcard,   // Codigo_TUSS
                    $searchTermWildcard,   // Codigo_Celos
                    $searchTermWildcard,   // Descricao_Padronizada
                    $searchTermWildcard,   // Descricao_Resumida
                    $searchTermWildcard,   // Descricao_Comercial
                    $searchTermWildcard,   // Descricao_Comercial_Completa
                    $searchTermWildcard,   // Descricao_TUSS
                    $searchTermWildcard,   // PrincipioAtivo
                    $searchTermWildcard,   // PrincipioAtivoClassificado
                    $searchTermWildcard    // PrincipioAtivo (Registro Visa)
                );
                break;
        }
    }
} else {
    // Consulta com paginação
    $sql = $sqlBase . " LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        echo json_encode(["error" => "Erro ao preparar a consulta: " . $conn->error]);
        exit;
    }
    
    // Vincular parâmetros de pesquisa e paginação
    if (!empty($searchTerm)) {
        switch($searchType) {
            case 'code':
                $stmt->bind_param("ssssssii", 
                    $searchTermLower,      // Cod exato
                    $searchTermWildcard,   // Cod LIKE
                    $searchTermLower,      // Codigo_TUSS exato
                    $searchTermWildcard,   // Codigo_TUSS LIKE
                    $searchTermLower,      // Codigo_Celos exato
                    $searchTermWildcard,   // Codigo_Celos LIKE
                    $limit, 
                    $offset
                );
                break;
                
            case 'active':
                $stmt->bind_param("ssii", 
                    $searchTermWildcard,   // PrincipioAtivo
                    $searchTermWildcard,   // PrincipioAtivoClassificado
                    $limit, 
                    $offset
                );
                break;
                
            case 'active_visa':
                $stmt->bind_param("sii", 
                    $searchTermWildcard,   // PrincipioAtivo (Registro Visa)
                    $limit, 
                    $offset
                );
                break;
                
            case 'description':
                $stmt->bind_param("sssssii", 
                    $searchTermWildcard,   // Descricao_Padronizada
                    $searchTermWildcard,   // Descricao_Resumida
                    $searchTermWildcard,   // Descricao_Comercial
                    $searchTermWildcard,   // Descricao_Comercial_Completa
                    $searchTermWildcard,   // Descricao_TUSS
                    $limit, 
                    $offset
                );
                break;
                
            case 'auto':
                // Se contém apenas números, pesquisar por códigos
                if (preg_match('/^[0-9.]+$/', $searchTerm)) {
                    $stmt->bind_param("ssssssii", 
                        $searchTermLower,      // Cod exato
                        $searchTermWildcard,   // Cod LIKE
                        $searchTermLower,      // Codigo_TUSS exato
                        $searchTermWildcard,   // Codigo_TUSS LIKE
                        $searchTermLower,      // Codigo_Celos exato
                        $searchTermWildcard,   // Codigo_Celos LIKE
                        $limit, 
                        $offset
                    );
                } 
                // Se contém letras, pesquisar princípio ativo em ambas as tabelas
                else {
                    $stmt->bind_param("sssii", 
                        $searchTermWildcard,   // PrincipioAtivo
                        $searchTermWildcard,   // PrincipioAtivoClassificado
                        $searchTermWildcard,   // PrincipioAtivo (Registro Visa)
                        $limit, 
                        $offset
                    );
                }
                break;
                
            default: // 'all'
                $stmt->bind_param("ssssssssssssii", 
                    $searchTermLower,      // Cod exato
                    $searchTermWildcard,   // Cod LIKE
                    $searchTermWildcard,   // Codigo_TUSS
                    $searchTermWildcard,   // Codigo_Celos
                    $searchTermWildcard,   // Descricao_Padronizada
                    $searchTermWildcard,   // Descricao_Resumida
                    $searchTermWildcard,   // Descricao_Comercial
                    $searchTermWildcard,   // Descricao_Comercial_Completa
                    $searchTermWildcard,   // Descricao_TUSS
                    $searchTermWildcard,   // PrincipioAtivo
                    $searchTermWildcard,   // PrincipioAtivoClassificado
                    $searchTermWildcard,   // PrincipioAtivo (Registro Visa)
                    $limit, 
                    $offset
                );
                break;
        }
    } else {
        // Somente paginação, sem pesquisa
        $stmt->bind_param("ii", $limit, $offset);
    }
}

// Executa a consulta
$stmt->execute();
$result = $stmt->get_result();

// Verifica erros na execução
if (!$result) {
    echo json_encode(["error" => "Erro ao executar a consulta: " . $stmt->error]);
    exit;
}

// Processa os resultados
$services = [];
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

// Retornando apenas os dados
echo json_encode($services);

$stmt->close();
$conn->close();
?>