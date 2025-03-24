<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once("../config.php");

// Verifica se o ID foi passado na requisição
if (!isset($_GET['id'])) {
    echo json_encode(["error" => "ID não fornecido"]);
    exit;
}

$id = $_GET['id'];

// Consulta com JOINs para trazer os dados relacionados
$sql = "SELECT 
    s.id,
    s.Codigo_TUSS,
    s.Descricao_Apresentacao,
    s.Descricao_Resumida,
    s.Descricao_Comercial,
    s.Concentracao,
    s.UnidadeFracionamento,
    s.Fracionamento,
    s.Laboratorio,
    s.Revisado,

    -- Registro Visa
    r.Cod_Ggrem,
    r.PrincipioAtivo AS RegistroVisaPrincipioAtivo,
    r.Lab,
    r.cnpj_lab,
    r.Classe_Terapeutica,
    r.Tipo_Produto,
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
    f.Fator_Conversao,

    -- Taxas
    x.id_taxas,
    x.tipo_taxa,
    x.finalidade AS TaxaFinalidade,
    x.tempo_infusao

FROM dservicorelacionada s
LEFT JOIN dregistro_anvisa r ON s.idRegistroVisa = r.RegistroVisa
LEFT JOIN dtabela t ON s.idTabela = t.id_tabela
LEFT JOIN dviaadministracao v ON s.idViaAdministracao = v.idviaadministracao
LEFT JOIN dclassefarmaceutica c ON s.idClasseFarmaceutica = c.id_medicamento
LEFT JOIN dprincipioativo p ON s.idPrincipioAtivo = p.idPrincipioAtivo
LEFT JOIN darmazenamento a ON s.idArmazenamento = a.idArmazenamento
LEFT JOIN dtipo_medicamento m ON s.idMedicamento = m.id_medicamento
LEFT JOIN dunidadefracionamento u ON s.idUnidadeFracionamento = u.id_unidadefracionamento
LEFT JOIN dfatorconversao f ON s.idFatorConversao = f.id_fatorconversao
LEFT JOIN dtaxas x ON s.idTaxas = x.id_taxas
WHERE s.id = ?";

// Prepara a consulta
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["error" => "Erro ao preparar a consulta: " . $conn->error]);
    exit;
}

// Vincula o parâmetro ID
$stmt->bind_param("i", $id);

// Executa a consulta
$stmt->execute();
$result = $stmt->get_result();

$details = $result->fetch_assoc();

// Retornando os detalhes em JSON
echo json_encode($details);

$stmt->close();
$conn->close();
?>
