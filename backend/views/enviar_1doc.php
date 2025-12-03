<?php
// backend/views/enviar_1doc.php

// --- 1. DADOS DE DEMONSTRAÇÃO (MOCK) ---
// Simulamos 5 Fornecedores e 5 Itens
$fornecedores = [
    'Kalunga Comércio', 
    'Dell Computadores', 
    'Amazon Business', 
    'Kabum Informatica', 
    'Magazine Luiza Corp'
];

$itens = [
    'Notebook Dell Latitude 5420 (Core i7, 16GB)',
    'Monitor 27" 4K Ultra HD',
    'Mouse Sem Fio Logitech MX Master 3',
    'Teclado Mecânico Keychron K2',
    'Suporte Articulado para Monitor a Gás'
];

// Gera matriz de preços simulada
$grade = [];
$totalGeral = 0;

foreach ($itens as $item) {
    $menorPreco = null;
    $vencedor = null;
    $precosItem = [];

    // Gera preço para cada fornecedor
    foreach ($fornecedores as $forn) {
        // Preço aleatório entre 100 e 5000 para simular variação
        // Usamos hash do nome para manter o mesmo valor se der F5 (pseudo-aleatório fixo)
        $seed = crc32($item . $forn);
        srand($seed);
        $preco = rand(15000, 800000) / 100; // Entre R$ 150,00 e R$ 8.000,00
        
        $precosItem[$forn] = $preco;

        // Lógica de Menor Preço
        if ($menorPreco === null || $preco < $menorPreco) {
            $menorPreco = $preco;
            $vencedor = $forn;
        }
    }

    $grade[] = [
        'produto' => $item,
        'precos' => $precosItem,
        'melhor_preco' => $menorPreco,
        'ganhador' => $vencedor
    ];
    
    $totalGeral += $menorPreco;
}
?>

<style>
    .grade-wrapper { overflow-x: auto; margin-top: 15px; border: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    .table-grade { width: 100%; border-collapse: collapse; font-size: 13px; min-width: 900px; }
    .table-grade th, .table-grade td { padding: 10px; border: 1px solid #eee; text-align: center; }
    
    /* Cabeçalho */
    .table-grade thead th { background-color: #f8f9fa; color: #333; font-weight: 600; position: sticky; top: 0; }
    .col-prod { text-align: left !important; width: 250px; background: #fff; position: sticky; left: 0; z-index: 2; border-right: 2px solid #ddd !important; }
    
    /* Célula Vencedora (Verde) */
    .winner { background-color: #d4edda; color: #155724; font-weight: bold; border: 2px solid #c3e6cb !important; }
    
    /* Célula Perdedora (Texto mais claro) */
    .loser { color: #666; }

    .total-box { margin-top: 20px; padding: 15px; background: #e9ecef; text-align: right; font-size: 16px; border-radius: 4px;}
    .badge-1doc { background: #009688; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; margin-left: 10px; }
</style>

<div class="modal-header">
    <h2>Grade Comparativa de Preços <span class="badge-1doc">Integração 1Doc</span></h2>
    <p>O sistema classificou automaticamente as melhores ofertas por item.</p>
</div>

<div style="padding: 10px;">
    
    <div class="grade-wrapper">
        <table class="table-grade">
            <thead>
                <tr>
                    <th class="col-prod">Item / Produto</th>
                    <th style="width: 80px;">Melhor Oferta</th>
                    <?php foreach ($fornecedores as $f): ?>
                        <th><?= $f ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($grade as $linha): ?>
                <tr>
                    <td class="col-prod">
                        <?= $linha['produto'] ?><br>
                        <small style="color: #28a745;">Venceu: <?= $linha['ganhador'] ?></small>
                    </td>
                    
                    <td style="background:#f1f1f1; font-weight:bold;">
                        R$ <?= number_format($linha['melhor_preco'], 2, ',', '.') ?>
                    </td>

                    <?php foreach ($fornecedores as $f): 
                        $preco = $linha['precos'][$f];
                        $isWinner = ($preco === $linha['melhor_preco']);
                    ?>
                        <td class="<?= $isWinner ? 'winner' : 'loser' ?>">
                            R$ <?= number_format($preco, 2, ',', '.') ?>
                            <?php if ($isWinner): ?>
                                <br><span style="font-size:10px">★ MENOR</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="total-box">
        <b>Total Previsto da Compra (Melhores Preços):</b> 
        R$ <?= number_format($totalGeral, 2, ',', '.') ?>
    </div>

</div>

<div class="modal-footer">
    <button class="btn-cancel" id="btn-fechar-grade">Voltar</button>
    <button class="btn-save" id="btn-enviar-1doc" style="background-color: #009688;">
        Gerar Minuta e Enviar para 1Doc
    </button>
</div>

<script>
(function() {
    // Botão Fechar
    document.getElementById('btn-fechar-grade').addEventListener('click', function() {
        document.getElementById('modal-overlay').classList.add('hidden');
        document.getElementById('modal-body').innerHTML = '';
    });

    // Botão de Ação (Simulação)
    document.getElementById('btn-enviar-1doc').addEventListener('click', function() {
        if (!confirm('Confirma a escolha destes fornecedores e o envio para assinatura digital na 1Doc?')) return;
        
        const btn = this;
        btn.disabled = true;
        btn.innerText = "Enviando para 1Doc...";

        // Simula delay de rede
        setTimeout(() => {
            alert('Sucesso! \n\nO documento de homologação foi gerado e enviado para a plataforma 1Doc.\nID do Protocolo: 1DOC-2025-9988');
            document.getElementById('modal-overlay').classList.add('hidden');
            // Opcional: window.location.reload();
        }, 2000);
    });
})();
</script>