<?php
// Importa a Interface (Sobe 1 nível para 'Documentos' e entra em 'Interfaces')
require_once __DIR__ . '/../Interfaces/DocumentoInterface.php';

// Assume que o autoload do Composer já foi carregado no index.php ou bootstrap.php
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Mpdf\Mpdf;
use Symfony\Component\Mime\Email;

class DocumentoEngine {
    private $mailer;
    private $storageRoot;

    // Recebe o Mailer já configurado (veremos isso no Service)
    public function __construct($mailer) {
        $this->mailer = $mailer;
        // Caminho onde os PDFs serão salvos (Ajuste para seu servidor real)
        $this->storageRoot = '/var/www/html/storage/docs'; 
    }

    public function processar(DocumentoInterface $doc, $idProcesso, $emailDestino) {
        // 1. Configura o Twig para ler a pasta DO MÓDULO
        $caminhoArquivoTwig = $doc->getCaminhoTemplate();
        
        if (!file_exists($caminhoArquivoTwig)) {
            throw new Exception("Template Twig não encontrado: $caminhoArquivoTwig");
        }

        $pastaDoModulo = dirname($caminhoArquivoTwig); // Ex: .../modulos/AutorizacaoCompra/Documentos
        $nomeArquivo = basename($caminhoArquivoTwig);  // Ex: autorizacao.html.twig

        // O Loader aponta exatamente para a pasta do módulo
        $loader = new FilesystemLoader($pastaDoModulo);
        $twig = new Environment($loader);

        // 2. Renderiza o HTML
        $html = $twig->render($nomeArquivo, $doc->getDados());

        // 3. Prepara diretórios de salvamento (Ano / Processo / Tipo)
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

        // 5. Envia o E-mail (HTML no corpo + PDF anexo)
        $email = (new Email())
            ->from('sistema@suaempresa.com.br') // Idealmente viria de config
            ->to($emailDestino)
            ->subject($doc->getAssunto())
            ->html($html)
            ->attachFromPath($caminhoPdfCompleto, 'Documento_Oficial.pdf');

        $this->mailer->send($email);

        return $caminhoPdfCompleto;
    }
}