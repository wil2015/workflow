<?php
// Arquivo: backend_novo/core/Documentos/Engine/DocumentoEngine.php

// --- CORREÇÃO DE OURO ---
// Garante que o Autoload do Composer esteja carregado, não importa quem chamou este arquivo.
// __DIR__ = Engine
// ../     = Documentos
// ../../  = core
// ../../../ = raiz (onde está a pasta vendor)
require_once __DIR__ . '/../../../vendor/autoload.php';

// Importa a Interface
require_once __DIR__ . '/../Interfaces/DocumentoInterface.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Mpdf\Mpdf;
use Symfony\Component\Mime\Email;

class DocumentoEngine {
    private $mailer;
    private $storageRoot;

    public function __construct($mailer) {
        $this->mailer = $mailer;
        // Caminho físico (ajuste se necessário)
        $this->storageRoot = '/var/www/html/storage/docs'; 
    }

    public function processar(DocumentoInterface $doc, $idProcesso, $emailDestino) {
        // 1. Configura o Twig para ler a pasta DO MÓDULO
        $caminhoArquivoTwig = $doc->getCaminhoTemplate();
        
        if (!file_exists($caminhoArquivoTwig)) {
            // Dica de Debug: Mostra onde ele tentou procurar
            throw new Exception("Template Twig não encontrado: $caminhoArquivoTwig");
        }

        $pastaDoModulo = dirname($caminhoArquivoTwig); 
        $nomeArquivo = basename($caminhoArquivoTwig);  

        // AQUI OCORRIA O ERRO: Agora o FilesystemLoader será encontrado!
        $loader = new FilesystemLoader($pastaDoModulo);
        $twig = new Environment($loader);

        // 2. Renderiza o HTML
        $html = $twig->render($nomeArquivo, $doc->getDados());

        // 3. Prepara diretórios
        $ano = date('Y');
        $tipoDoc = $doc->getNomePastaStorage();
        $pathDir = "{$this->storageRoot}/{$ano}/{$idProcesso}/{$tipoDoc}";

        if (!is_dir($pathDir)) {
            mkdir($pathDir, 0777, true);
        }

        // 4. Gera o PDF com mPDF
        $nomePdf = $doc->getNomeArquivoBase() . '_' . date('YmdHis') . '.pdf';
        $caminhoPdfCompleto = "{$pathDir}/{$nomePdf}";

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($caminhoPdfCompleto, \Mpdf\Output\Destination::FILE);

        // 5. Envia o E-mail
        $email = (new Email())
            ->from('compras@unesp.br') 
            ->to($emailDestino)
            ->subject($doc->getAssunto())
            ->html($html)
            ->attachFromPath($caminhoPdfCompleto, 'Documento_Oficial.pdf');

        $this->mailer->send($email);

        return $caminhoPdfCompleto;
    }
}