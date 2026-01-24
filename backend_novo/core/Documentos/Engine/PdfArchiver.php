<?php
class PdfArchiver {
    private $uploadRoot;

    public function __construct() {
        // Caminho Físico (Docker)
        $this->uploadRoot = '/var/www/html/public/uploads/docs';

        if (!is_dir($this->uploadRoot)) {
            mkdir($this->uploadRoot, 0777, true);
        }
    }

    public function arquivar(string $conteudoBinario, string $tipoDoc, int $idProcesso): array {
        
        // --- MUDANÇA AQUI: Estrutura Ano / ID Processo ---
        // Ex: 2026/15/arquivo.pdf
        $pastaRelativa = date('Y') . '/' . $idProcesso;
        $pastaAbsoluta = $this->uploadRoot . '/' . $pastaRelativa;

        // Cria a pasta do processo se não existir
        if (!is_dir($pastaAbsoluta)) {
            if (!mkdir($pastaAbsoluta, 0777, true)) {
                 throw new Exception("Falha ao criar pasta do processo: $pastaAbsoluta");
            }
        }

        $timestamp = date('Ymd_His');
        $nomeArquivo = strtolower("{$tipoDoc}_{$timestamp}.pdf"); // Nome mais limpo
        $caminhoCompleto = $pastaAbsoluta . '/' . $nomeArquivo;

        // Salva e Valida
        $bytes = file_put_contents($caminhoCompleto, $conteudoBinario);
        
        if ($bytes === false) {
             throw new Exception("Erro de permissão ao salvar em: $caminhoCompleto");
        }

        // Permissão de leitura pública
        chmod($caminhoCompleto, 0666); 

        return [
            // Caminho para o Banco de Dados (Link Web)
            'caminho_relativo' => "public/uploads/docs/$pastaRelativa/$nomeArquivo",
            'caminho_absoluto' => $caminhoCompleto,
            'nome_arquivo'     => $nomeArquivo,
            'hash_sha256'      => hash_file('sha256', $caminhoCompleto),
            'tamanho_bytes'    => $bytes
        ];
    }
}