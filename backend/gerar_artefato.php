<?php
require 'vendor/autoload.php';

use PhpOffice\PhpWord\TemplateProcessor;

// 1. Recebe os dados (JSON) do Front-end
$input = file_get_contents('php://input');
$dados = json_decode($input, true);

if (!$dados) {
    http_response_code(400);
    echo json_encode(['erro' => 'Sem dados']);
    exit;
}

// Configuração
$templateFile = __DIR__ . '/templates/solicitacao_cotacao.docx';
$outputFolder = __DIR__ . '/output/';
$nomeArquivoSaida = 'cotacao_' . $dados['id_processo'] . '_' . time() . '.docx';

try {
    // 2. Carrega o Template
    $templateProcessor = new TemplateProcessor($templateFile);

    // 3. Substitui as variáveis simples
    $templateProcessor->setValue('data_atual', date('d/m/Y'));
    $templateProcessor->setValue('id_solicitacao', $dados['id_processo']);
    $templateProcessor->setValue('nome_fornecedor', $dados['fornecedor']);

    // 4. Substitui linhas de uma tabela (Clonagem de linhas)
    // Supondo que 'itens' seja um array de arrays vindo do JS
    if (!empty($dados['itens'])) {
        // Clona a linha que contém a variável 'item_nome' tantas vezes quantos itens houver
        $templateProcessor->cloneRow('item_nome', count($dados['itens']));

        foreach ($dados['itens'] as $index => $item) {
            $i = $index + 1; // O PHPWord usa índice base-1 para substituição após clonar
            $templateProcessor->setValue('item_nome#' . $i, htmlspecialchars($item['nome']));
            $templateProcessor->setValue('item_qtd#' . $i,  htmlspecialchars($item['qtd']));
            $templateProcessor->setValue('item_desc#' . $i, htmlspecialchars($item['desc']));
        }
    }

    // 5. Salva o arquivo
    $caminhoCompleto = $outputFolder . $nomeArquivoSaida;
    $templateProcessor->saveAs($caminhoCompleto);

    // 6. Retorna o link para download (ou caminho relativo)
    echo json_encode([
        'sucesso' => true,
        'arquivo' => $nomeArquivoSaida,
        'mensagem' => 'Artefato gerado com sucesso!'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['erro' => $e->getMessage()]);
}
?>