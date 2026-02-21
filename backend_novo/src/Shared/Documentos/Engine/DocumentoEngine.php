<?php

declare(strict_types=1);

namespace Shared\Documentos\Engine;

use Exception;
use Mpdf\Mpdf;
use Shared\Documentos\Interfaces\DocumentoInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class DocumentoEngine
{
    private $mailer;
    private string $storageRoot;

    public function __construct($mailer)
    {
        $this->mailer = $mailer;
        $this->storageRoot = '/var/www/html/storage/docs';
    }

    public function processar(
        DocumentoInterface $doc,
        $idProcesso,
        ?string $emailDestino = null,
        bool $enviarEmail = false
    ): string {
        // 1. Configura Twig
        $caminhoArquivoTwig = $doc->getCaminhoTemplate();
        if (!file_exists($caminhoArquivoTwig)) {
            throw new Exception("Template nao encontrado: $caminhoArquivoTwig");
        }

        $pastaDoModulo = dirname($caminhoArquivoTwig);
        $nomeArquivo = basename($caminhoArquivoTwig);

        $loader = new FilesystemLoader($pastaDoModulo);
        $twig = new Environment($loader);

        // 2. Renderiza HTML
        $html = $twig->render($nomeArquivo, $doc->getDados());

        // 3. Cria pastas
        $ano = date('Y');
        $tipoDoc = $doc->getNomePastaStorage();
        $pathDir = "{$this->storageRoot}/{$ano}/{$idProcesso}/{$tipoDoc}";

        if (!is_dir($pathDir)) {
            mkdir($pathDir, 0777, true);
        }

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
                error_log("Erro ao enviar email: " . $e->getMessage());
            }
        }

        return $caminhoPdfCompleto;
    }
}
