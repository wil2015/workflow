<?php
namespace App\Modulos\OrdemDeCompra\Integracao;

use Exception;
use SimpleXMLElement;

class OrdemCompraSeniorClient
{
    private const ENDPOINT = 'http://200.145.62.23:8080/g5-senior-services/sapiens_Synccom_senior_g5_co_mcm_cpr_ordemcompra';

    public function gravarOrdemCompra(array $autorizacao, array $itens): array
    {
        $xmlEnvio = $this->montarEnvelope($autorizacao, $itens);
        $xmlRetorno = $this->enviarSoap($xmlEnvio);
        $retorno = $this->interpretarRetorno($xmlRetorno);

        return [
            'sucesso' => $retorno['sucesso'],
            'mensagem' => $retorno['mensagem'],
            'num_ocp' => $retorno['num_ocp'],
            'xml_envio' => $xmlEnvio,
            'xml_retorno' => $xmlRetorno,
        ];
    }

    private function montarEnvelope(array $autorizacao, array $itens): string
    {
        $user = getenv('SENIOR_USER') ?: getenv('senior_user') ?: 'portal.coordenador';
        $password = getenv('SENIOR_PASSWORD') ?: getenv('senior_password') ?: 'rolling05';
        $encryption = getenv('SENIOR_ENCRYPTION') ?: getenv('encryption') ?: '0';

        $produtosXml = '';
        foreach (array_values($itens) as $index => $item) {
            $produtosXml .= '
                    <produtos>
                        <seqIpo>' . ($index + 1) . '</seqIpo>
                        <tnsPro>90400</tnsPro>
                        <codPro>' . $this->xml((string)$item['id_item']) . '</codPro>
                        <qtdPed>' . $this->formatarDecimal($item['quantidade'], 4) . '</qtdPed>
                        <uniMed>UN</uniMed>
                        <preUni>' . $this->formatarDecimal($item['valor_cotado'], 2) . '</preUni>
                        <preFix>S</preFix>
                    </produtos>';
        }

        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ser="http://services.senior.com.br">
    <soapenv:Header/>
    <soapenv:Body>
        <ser:GravarOrdensCompra>
            <user>' . $this->xml($user) . '</user>
            <password>' . $this->xml($password) . '</password>
            <encryption>' . $this->xml($encryption) . '</encryption>
            <parameters>
                <dadosGerais>
                    <codEmp>1</codEmp>
                    <codFil>1</codFil>
                    <tnsPro>90400</tnsPro>
                    <datEmi>' . date('d/m/Y') . '</datEmi>
                    <codFor>' . (int)$autorizacao['id_fornecedor_senior'] . '</codFor>
                    <codCpg>01</codCpg>
                    <codFpg>0</codFpg>
                    <temPar>N</temPar>
                    <somFre>N</somFre>
                    <cifFob>X</cifFob>
                    <prcOcp>20</prcOcp>
                    <ideExt>1234</ideExt>
                    <obsOcp>Via workflow de compras.</obsOcp>' . $produtosXml . '
                </dadosGerais>
                <tipoProcessamento>1</tipoProcessamento>
                <fechaOC>2</fechaOC>
                <identificadorSistema>123456</identificadorSistema>
            </parameters>
        </ser:GravarOrdensCompra>
    </soapenv:Body>
</soapenv:Envelope>';
    }

    private function enviarSoap(string $xml): string
    {
        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ""',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init(self::ENDPOINT);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $xml,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 60,
            ]);
            $retorno = curl_exec($ch);
            $erro = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($retorno === false || $retorno === '') {
                throw new Exception('Falha ao chamar webservice Senior: ' . ($erro ?: 'sem resposta.'));
            }
            if ($status >= 400) {
                throw new Exception("Webservice Senior retornou HTTP {$status}: {$retorno}");
            }

            return (string)$retorno;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $xml,
                'timeout' => 60,
            ],
        ]);
        $retorno = file_get_contents(self::ENDPOINT, false, $context);
        if ($retorno === false || $retorno === '') {
            throw new Exception('Falha ao chamar webservice Senior.');
        }

        return (string)$retorno;
    }

    private function interpretarRetorno(string $xmlRetorno): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlRetorno);
        if (!$xml instanceof SimpleXMLElement) {
            return [
                'sucesso' => false,
                'mensagem' => 'Retorno XML invalido do Senior.',
                'num_ocp' => null,
            ];
        }

        $mensagens = $xml->xpath('//*[translate(local-name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "mensagemretorno"]');
        $numeros = $xml->xpath('//*[translate(local-name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "numocp"]');
        $tiposRetorno = $xml->xpath('//*[translate(local-name(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz") = "tiporetorno"]');

        $mensagem = isset($mensagens[0]) ? trim((string)$mensagens[0]) : '';
        $numOcp = isset($numeros[0]) ? trim((string)$numeros[0]) : '';
        $tipoRetorno = isset($tiposRetorno[0]) ? strtolower(trim((string)$tiposRetorno[0])) : '';

        $temErro = $tipoRetorno !== '' && in_array($tipoRetorno, ['0', 'erro', 'error', 'e'], true);
        if ($temErro || $numOcp === '' || !preg_match('/^\d+$/', $numOcp)) {
            return [
                'sucesso' => false,
                'mensagem' => $mensagem,
                'num_ocp' => null,
            ];
        }

        return [
            'sucesso' => true,
            'mensagem' => $mensagem,
            'num_ocp' => (int)$numOcp,
        ];
    }

    private function xml(string $valor): string
    {
        return htmlspecialchars($valor, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    private function formatarDecimal($valor, int $casas): string
    {
        return number_format((float)$valor, $casas, '.', '');
    }
}
