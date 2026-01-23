<?php

class PdfArchiver {
    
    private $uploadRoot;

    public function __construct() {
        // Define a raiz dos uploads. 
        // Ex: /var/www/html/backend/uploads/docs
        $this->uploadRoot = __DIR__ . '/../../../../uploads/docs';
    }

    /**
     * Recebe o conteúdo binário do PDF (string) e salva no disco de forma organizada.
     */
    public function arquivar(string $conteudoBinario, string $tipoDoc, int $idProcesso): array {
        
        // 1. Organização por Ano/Mês para não superlotar pastas
        $pastaRelativa = date('Y') . '/' . date('m');
        $pastaAbsoluta = $this->uploadRoot . '/' . $pastaRelativa;

        if (!is_dir($pastaAbsoluta)) {
            mkdir($pastaAbsoluta, 0755, true);
        }

        // 2. Nome do Arquivo Único e Rastreável
        // Ex: autorizacao_compra_proc_54_20260123_153000.pdf
        $timestamp = date('Ymd_His');
        $nomeArquivo = strtolower("{$tipoDoc}_proc_{$idProcesso}_{$timestamp}.pdf");
        $caminhoCompleto = $pastaAbsoluta . '/' . $nomeArquivo;

        // 3. Salva no Disco
        $bytes = file_put_contents($caminhoCompleto, $conteudoBinario);

        if ($bytes === false) {
            throw new Exception("Falha ao escrever o arquivo PDF no disco.");
        }

        // 4. Retorna metadados para o Banco
        return [
            'caminho_relativo' => "uploads/docs/$pastaRelativa/$nomeArquivo",
            'caminho_absoluto' => $caminhoCompleto,
            'nome_arquivo'     => $nomeArquivo,
            'hash_sha256'      => hash_file('sha256', $caminhoCompleto), // Garante integridade
            'tamanho_bytes'    => $bytes
        ];
    }
}