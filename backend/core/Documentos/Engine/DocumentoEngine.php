<?php
// CARREGA O AUTOLOAD DO COMPOSER (Garante que Twig/mPDF funcionem)
namespace App\Core\Documentos\Engine; // Acompanha a estrutura de pastas

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Mpdf\Mpdf;
use Symfony\Component\Mime\Email;
use App\Core\Documentos\Interfaces\DocumentoInterface; // Importando a interface do seu próprio projeto
use Exception;

class DocumentoEngine {
    private $mailer;
    private $storageRoot;

    public function __construct($mailer) {
        $this->mailer = $mailer;
        // Caminho onde salva os PDFs
        $this->storageRoot = '/var/www/html/storage/docs'; 
    }

    // $enviarEmail = false por padrão para não travar se o SMTP falhar
    public function processar(DocumentoInterface $doc, $idProcesso, $emailDestino = null, $enviarEmail = false) {
        
        // 1. Configura Twig
        $caminhoArquivoTwig = $doc->getCaminhoTemplate();
        if (!file_exists($caminhoArquivoTwig)) {
            throw new Exception("Template não encontrado: $caminhoArquivoTwig");
        }

        $pastaDoModulo = dirname($caminhoArquivoTwig); 
        $nomeArquivo = basename($caminhoArquivoTwig);  

        $loader = new FilesystemLoader($pastaDoModulo);
        $twig = new Environment($loader);

        // 2. Renderiza HTML
        $html = $twig->render($nomeArquivo, $doc->getDados());

        // 3. Cria Pastas
        $ano = date('Y');
        $tipoDoc = $doc->getNomePastaStorage();
        $pathDir = "{$this->storageRoot}/{$ano}/{$idProcesso}/{$tipoDoc}";

        if (!is_dir($pathDir)) mkdir($pathDir, 0777, true);

        // 4. Gera PDF (mPDF)
        $nomePdf = $doc->getNomeArquivoBase() . '_' . date('YmdHis') . '.pdf';
        $caminhoPdfCompleto = "{$pathDir}/{$nomePdf}";

        $mpdf = new Mpdf(['mode' => 'utf-8', 'format' => 'A4']);
        $mpdf->WriteHTML($html);
        $mpdf->Output($caminhoPdfCompleto, \Mpdf\Output\Destination::FILE);

        // 5. Envio de E-mail (Opcional)
        if ($enviarEmail && !empty($emailDestino)) {
            try {
                $email = (new Email())
                    ->from('compras@unesp.br') 
                    ->to($emailDestino)
                    ->subject($doc->getAssunto())
                    ->html($html)
                    ->attachFromPath($caminhoPdfCompleto, 'Documento_Oficial.pdf');

                $this->mailer->send($email);
            } catch (Exception $e) {
                // Loga erro mas não para o processo
                error_log("Erro ao enviar email: " . $e->getMessage());
            }
        }

        return $caminhoPdfCompleto;
    }
}