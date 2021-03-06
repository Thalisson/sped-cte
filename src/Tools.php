<?php

namespace NFePHP\CTe;

/**
 * Classe principal para a comunicação com a SEFAZ
 *
 * @category  Library
 * @package   nfephp-org/sped-cte
 * @copyright 2009-2016 NFePHP
 * @license   http://www.gnu.org/licenses/lesser.html LGPL v3
 * @link      http://github.com/nfephp-org/sped-cte for the canonical source repository
 * @author    Roberto L. Machado <linux.rlm at gmail dot com>
 *
 *        CONTRIBUIDORES (em ordem alfabetica):
 *
 *          Maison K. Sakamoto <maison.sakamoto at gmail do com>
 */
//use NFePHP\Common\Base\BaseTools;
use NFePHP\CTe\BaseTools;
use NFePHP\Common\LotNumber\LotNumber;
use NFePHP\Common\Strings\Strings;
use NFePHP\Common\Files;
use NFePHP\Common\Exception;
use NFePHP\CTe\Auxiliar\Response;
use NFePHP\CTe\Auxiliar\IdentifyCTe;
use NFePHP\Common\Dom\ValidXsd;
use NFePHP\Common\Dom\Dom;
use NFePHP\Common\DateTime\DateTime;
use NFePHP\Extras;

if (!defined('NFEPHP_ROOT')) {
    define('NFEPHP_ROOT', dirname(dirname(__FILE__)));
}

class Tools extends BaseTools {

    /**
     * urlPortal
     * Instância do WebService
     * @var string
     */
    protected $urlPortal = 'http://www.portalfiscal.inf.br/cte';

    /**
     * errrors
     * @var string
     */
    public $erros = array();
    protected $modelo = '57';

    public function printCTe() {
        
    }

    public function mailCTe() {
        
    }

    /**
     * assina
     * @param string $xml
     * @param boolean $saveFile
     * @return string
     * @throws Exception\RuntimeException
     */
    public function assina($xml = '', $saveFile = false) {
        return $this->assinaDoc($xml, 'cte', 'infCte', $saveFile);
    }

    public function sefazEnvia(
    $aXml, $tpAmb = '2', $idLote = '', &$aRetorno = array(), $indSinc = 0, $compactarZip = false
    ) {
        $sxml = $aXml;
        if (empty($aXml)) {
            $msg = "Pelo menos uma NFe deve ser informada.";
            throw new Exception\InvalidArgumentException($msg);
        }
        if (is_array($aXml)) {
            if (count($aXml) > 1) {
                //multiplas cte, não pode ser sincrono
                $indSinc = 0;
            }
            $sxml = implode("", $sxml);
        }
        $sxml = preg_replace("/<\?xml.*\?>/", "", $sxml);
        $siglaUF = $this->aConfig['siglaUF'];

        if ($tpAmb == '') {
            $tpAmb = $this->aConfig['tpAmb'];
        }
        if ($idLote == '') {
            $idLote = LotNumber::geraNumLote(15);
        }
        //carrega serviço
        $servico = 'CteRecepcao';
        $this->zLoadServico(
                'cte', $servico, $siglaUF, $tpAmb
        );

        if ($this->urlService == '') {
            $msg = "O envio de lote não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }

        // Montagem dos dados da mensagem SOAP
        $dados = "<cteDadosMsg xmlns=\"$this->urlNamespace\">"
                . "<enviCTe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<idLote>$idLote</idLote>"
                . "$sxml"
                . "</enviCTe>"
                . "</cteDadosMsg>";

        // Envia dados via SOAP
        $retorno = $this->oSoap->send(
                $this->urlService, $this->urlNamespace, $this->urlHeader, $dados, $this->urlMethod
        );

//        if ($compactarZip) {
//            $gzdata = base64_encode(gzencode($cons, 9, FORCE_GZIP));
//            $body = "<cteDadosMsgZip xmlns=\"$this->urlNamespace\">$gzdata</cteDadosMsgZip>";
//            $method = $this->urlMethod."Zip";
//        }

        $lastMsg = $this->oSoap->lastMsg;
        $this->soapDebug = $this->oSoap->soapDebug;
        //salva mensagens
        $filename = "$idLote-enviCTe.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $lastMsg);
        $filename = "$idLote-retEnviCTe.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $retorno);
        //tratar dados de retorno

        $aRetorno = Response::readReturnSefaz($servico, $retorno);
        //caso o envio seja recebido com sucesso mover a NFe da pasta
        //das assinadas para a pasta das enviadas
        return (string) $retorno;
    }

    public function addProtocolo($pathCTefile = '', $pathProtfile = '', $saveFile = false) {
        //carrega a CTe
        $doccte = new Dom();
        $doccte->loadXMLFile($pathCTefile);
        $nodecte = $doccte->getNode('CTe', 0);

        if ($nodecte == '') {
            $msg = "O arquivo indicado como CTe não é um xml de CTe!";
            throw new Exception\RuntimeException($msg);
        }
        if ($doccte->getNode('Signature') == '') {
            $msg = "A CTe não está assinada!";
            throw new Exception\RuntimeException($msg);
        }
        //carrega o protocolo
        $docprot = new Dom();
        $docprot->loadXMLFile($pathProtfile);
        $nodeprots = $docprot->getElementsByTagName('protCTe');
        if ($nodeprots->length == 0) {
            $msg = "O arquivo indicado não contem um protocolo de autorização!";
            throw new Exception\RuntimeException($msg);
        }
        //carrega dados da CTe
        $tpAmb = $doccte->getNodeValue('tpAmb');
        $anomes = date(
                'Ym', DateTime::convertSefazTimeToTimestamp($doccte->getNodeValue('dhEmi'))
        );


        $infCTe = $doccte->getElementsByTagName("infCte");

        $nCTe = $doccte->getElementsByTagName("ide");



        foreach ($nCTe as $nCTe) :
            $nCT = $nCTe->getElementsByTagName("nCT")->item(0)->nodeValue;
        endforeach;

        foreach ($infCTe as $infCTe) :
            $versao = $infCTe->getAttribute('versao');
            $chaveId = $infCTe->getAttribute("Id");
        endforeach;
        $chaveCTe = preg_replace('/[^0-9]/', '', $chaveId);
        $nct = $doccte->getElementsByTagName("nCT");
        $digValueCTe = $doccte->getNodeValue('DigestValue');


        //carrega os dados do protocolo
        for ($i = 0; $i < $nodeprots->length; $i++) {
            $nodeprot = $nodeprots->item($i);
            $protver = $nodeprot->getAttribute("versao");
            $chaveProt = $nodeprot->getElementsByTagName("chCTe")->item(0)->nodeValue;
            $digValueProt = ($nodeprot->getElementsByTagName("digVal")->length) ? $nodeprot->getElementsByTagName("digVal")->item(0)->nodeValue : '';
            $infProt = $nodeprot->getElementsByTagName("infProt")->item(0);
            if ($digValueCTe == $digValueProt && $chaveCTe == $chaveProt) {
                break;
            }
        }
//        if ($digValueCTe != $digValueProt) {
//            $msg = "Inconsistência! O DigestValue da CTe não combina com o"
//                    . " do digVal do protocolo indicado!";
//            throw new Exception\RuntimeException($msg);
//        }
//        if ($chaveCTe != $chaveProt) {
//            $msg = "O protocolo indicado pertence a outra CTe. Os números das chaves não combinam !";
//            throw new Exception\RuntimeException($msg);
//        }
//        
//        
        //cria a CTe processada com a tag do protocolo
        $proccte = new \DOMDocument('1.0', 'utf-8');
        $proccte->formatOutput = false;
        $proccte->preserveWhiteSpace = false;
        //cria a tag cteProc
        $cteProc = $proccte->createElement('cteProc');
        $proccte->appendChild($cteProc);
        //estabele o atributo de versão
        $cteProcAtt1 = $cteProc->appendChild($proccte->createAttribute('versao'));
        $cteProcAtt1->appendChild($proccte->createTextNode($protver));
        //estabelece o atributo xmlns
        $cteProcAtt2 = $cteProc->appendChild($proccte->createAttribute('xmlns'));
        $cteProcAtt2->appendChild($proccte->createTextNode($this->urlPortal));
        //inclui a tag CTe
        $node = $proccte->importNode($nodecte, true);
        $cteProc->appendChild($node);
        //cria tag protCTe
        $protCTe = $proccte->createElement('protCTe');
        $cteProc->appendChild($protCTe);
        //estabele o atributo de versão
        $protCTeAtt1 = $protCTe->appendChild($proccte->createAttribute('versao'));
        $protCTeAtt1->appendChild($proccte->createTextNode($versao));
        //cria tag infProt
        $nodep = $proccte->importNode($infProt, true);
        $protCTe->appendChild($nodep);
        //salva o xml como string em uma variável
        $procXML = $proccte->saveXML();
        //remove as informações indesejadas
        $procXML = Strings::clearProt($procXML);
        if ($saveFile) {
            $filename = "" . $nCT . "-protCTe.xml";
            $this->zGravaFile(
                    'cte', $tpAmb, $filename, $procXML, 'enviadas' . DIRECTORY_SEPARATOR . 'aprovadas', $anomes
            );
        }
        return $procXML;
    }

    public function sefazConsultaRecibo($recibo = '', $tpAmb = '2', &$aRetorno = array()) {
        if ($recibo == '') {
            $msg = "Deve ser informado um recibo.";
            throw new Exception\InvalidArgumentException($msg);
        }
        if ($tpAmb == '') {
            $tpAmb = $this->aConfig['tpAmb'];
        }
        $siglaUF = $this->aConfig['siglaUF'];
        //carrega serviço
        $servico = 'CteRetRecepcao';
        $this->zLoadServico(
                'cte', $servico, $siglaUF, $tpAmb
        );
        if ($this->urlService == '') {
            $msg = "A consulta de NFe não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }
        $cons = "<consReciCTe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<tpAmb>$tpAmb</tpAmb>"
                . "<nRec>$recibo</nRec>"
                . "</consReciCTe>";
        //validar mensagem com xsd
        //if (! $this->validarXml($cons)) {
        //    $msg = 'Falha na validação. '.$this->error;
        //    throw new Exception\RuntimeException($msg);
        //}
        //montagem dos dados da mensagem SOAP
        $body = "<cteDadosMsg xmlns=\"$this->urlNamespace\">$cons</cteDadosMsg>";

        //envia a solicitação via SOAP
        $retorno = $this->oSoap->send(
                $this->urlService, $this->urlNamespace, $this->urlHeader, $body, $this->urlMethod
        );
        $lastMsg = $this->oSoap->lastMsg;
        $this->soapDebug = $this->oSoap->soapDebug;
        //salva mensagens
        $filename = "$recibo-consReciCTe.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $lastMsg);
        $filename = "$recibo-retConsReciCTe.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $retorno);
        //tratar dados de retorno
        $aRetorno = Response::readReturnSefaz($servico, $retorno);
        //podem ser retornados nenhum, um ou vários protocolos
        //caso existam protocolos protocolar as NFe e movelas-las para a
        //pasta enviadas/aprovadas/anomes
        return (string) $retorno;
    }

    public function sefazConsultaChave($chave = '', $tpAmb = '2', &$aRetorno = array()) {
        $chNFe = preg_replace('/[^0-9]/', '', $chave);
        if (strlen($chNFe) != 44) {
            $msg = "Uma chave de 44 dígitos da NFe deve ser passada.";
            throw new Exception\InvalidArgumentException($msg);
        }
        if ($tpAmb == '') {
            $tpAmb = $this->aConfig['tpAmb'];
        }
        $cUF = substr($chNFe, 0, 2);
        $siglaUF = $this->zGetSigla($cUF);
        //carrega serviço
        $servico = 'CteConsultaProtocolo';
        $this->zLoadServico(
                'cte', $servico, $siglaUF, $tpAmb
        );
        if ($this->urlService == '') {
            $msg = "A consulta de NFe não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }
        $cons = "<consSitCTe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<tpAmb>$tpAmb</tpAmb>"
                . "<xServ>CONSULTAR</xServ>"
                . "<chCTe>$chNFe</chCTe>"
                . "</consSitCTe>";
        //validar mensagem com xsd
        //if (! $this->validarXml($cons)) {
        //    $msg = 'Falha na validação. '.$this->error;
        //    throw new Exception\RuntimeException($msg);
        //}
        //montagem dos dados da mensagem SOAP
        $body = "<cteDadosMsg xmlns=\"$this->urlNamespace\">$cons</cteDadosMsg>";
        //envia a solicitação via SOAP
        $retorno = $this->oSoap->send(
                $this->urlService, $this->urlNamespace, $this->urlHeader, $body, $this->urlMethod
        );
        $lastMsg = $this->oSoap->lastMsg;
        $this->soapDebug = $this->oSoap->soapDebug;
        //salva mensagens
        $filename = "$chNFe-consSitCTe.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $lastMsg);
        $filename = "$chNFe-retConsSitNFe.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $retorno);
        //tratar dados de retorno
        $aRetorno = Response::readReturnSefaz($servico, $retorno);
        return (string) $retorno;
    }

     /**
     * addCancelamento
     * Adiciona a tga de cancelamento a uma NFe já autorizada
     * NOTA: não é requisito da SEFAZ, mas auxilia na identificação das NFe que foram canceladas
     *
     * @param  string $pathNFefile
     * @param  string $pathCancfile
     * @param  bool   $saveFile
     * @return string
     * @throws Exception\RuntimeException
     */
    public function addCancelamento($pathNFefile = '', $pathCancfile = '', $saveFile = false)
    {
        $procXML = '';
        //carrega a NFe
        $doccte = new Dom();
        $doccte->loadXMLFile($pathNFefile);
        $nodecte = $doccte->getNode('CTe', 0);
        if ($nodecte == '') {
            $msg = "O arquivo indicado como CTe não é um xml de CTe!";
            throw new Exception\RuntimeException($msg);
        }
        $proCTe = $doccte->getNode('protCTe');
        if ($proCTe == '') {
            $msg = "O CTe não está protocolada ainda!!";
            throw new Exception\RuntimeException($msg);
        }
        $chaveCTe = $proCTe->getElementsByTagName('chCTe')->item(0)->nodeValue;
        //$nProtNFe = $proNFe->getElementsByTagName('nProt')->item(0)->nodeValue;
        $tpAmb = $doccte->getNodeValue('tpAmb');
        $anomes = date(
            'Ym',
            DateTime::convertSefazTimeToTimestamp($doccte->getNodeValue('dhEmi'))
        );
        //carrega o cancelamento
        //pode ser um evento ou resultado de uma consulta com multiplos eventos
        $doccanc = new Dom();
        $doccanc->loadXMLFile($pathCancfile);
        $retEvento = $doccanc->getElementsByTagName('retEventoCTe')->item(0);
        $eventos = $retEvento->getElementsByTagName('infEvento');
        foreach ($eventos as $evento) {
            //evento
            $cStat = $evento->getElementsByTagName('cStat')->item(0)->nodeValue;
            $tpAmb = $evento->getElementsByTagName('tpAmb')->item(0)->nodeValue;
            $chaveEvento = $evento->getElementsByTagName('chCTe')->item(0)->nodeValue;
            $tpEvento = $evento->getElementsByTagName('tpEvento')->item(0)->nodeValue;
            //$nProtEvento = $evento->getElementsByTagName('nProt')->item(0)->nodeValue;
            //verifica se conferem os dados
            //cStat = 135 ==> evento homologado
            //cStat = 136 ==> vinculação do evento à respectiva NF-e prejudicada
            //cStat = 155 ==> Cancelamento homologado fora de prazo
            //tpEvento = 110111 ==> Cancelamento
            //chave do evento == chave da NFe
            //protocolo do evneto ==  protocolo da NFe
            if (($cStat == '135' || $cStat == '136' || $cStat == '155')
                && $tpEvento == '110111'
                && $chaveEvento == $chaveCTe
            ) {
                $proCTe->getElementsByTagName('cStat')->item(0)->nodeValue = '101';
                $proCTe->getElementsByTagName('xMotivo')->item(0)->nodeValue = 'Cancelamento de CT-e homologado';
                $procXML = $doccte->saveXML();
                //remove as informações indesejadas
                $procXML = Strings::clearProt($procXML);
                if ($saveFile) {
                    $filename = "$chaveCTe-protCTe.xml";
                    $this->zGravaFile(
                        'cte',
                        $tpAmb,
                        $filename,
                        $procXML,
                        'canceladas',
                        $anomes
                    );
                }
                break;
            }
        }
        return (string) $procXML;
    }
    
    
    
    public function sefazStatus($siglaUF = '', $tpAmb = '2', &$aRetorno = array()) {
        if ($tpAmb == '') {
            $tpAmb = $this->aConfig['tpAmb'];
        }
        if ($siglaUF == '') {
            $siglaUF = $this->aConfig['siglaUF'];
        }
        //carrega serviço
        $servico = 'CteStatusServico';
        $this->zLoadServico(
                'cte', $servico, $siglaUF, $tpAmb
        );
        if ($this->urlService == '') {
            $msg = "O status não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }
        $cons = "<consStatServCte xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<tpAmb>$tpAmb</tpAmb>"
                . "<xServ>STATUS</xServ></consStatServCte>";
        //valida mensagem com xsd
        //validar mensagem com xsd
        //if (! $this->validarXml($cons)) {
        //    $msg = 'Falha na validação. '.$this->error;
        //    throw new Exception\RuntimeException($msg);
        //}
        //montagem dos dados da mensagem SOAP
        $body = "<cteDadosMsg xmlns=\"$this->urlNamespace\">$cons</cteDadosMsg>";
        //consome o webservice e verifica o retorno do SOAP
        $retorno = $this->oSoap->send(
                $this->urlService, $this->urlNamespace, $this->urlHeader, $body, $this->urlMethod
        );
        $lastMsg = $this->oSoap->lastMsg;
        $this->soapDebug = $this->oSoap->soapDebug;
        $datahora = date('Ymd_His');
        $filename = $siglaUF . "_" . "$datahora-consStatServCte.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $lastMsg);
        $filename = $siglaUF . "_" . "$datahora-retConsStatServCte.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $retorno);
        //tratar dados de retorno
        $aRetorno = Response::readReturnSefaz($servico, $retorno);
        return (string) $retorno;
    }

    public function sefazInutiliza(
    $nSerie = '1', $nIni = '', $nFin = '', $xJust = '', $tpAmb = '2', &$aRetorno = array(), $salvarMensagens = true
    ) {
        $nSerie = (integer) $nSerie;
        $nIni = (integer) $nIni;
        $nFin = (integer) $nFin;
        $xJust = Strings::cleanString($xJust);
        $this->zValidParamInut($xJust, $nSerie, $nIni, $nFin);
        if ($tpAmb == '') {
            $tpAmb = $this->aConfig['tpAmb'];
        }
        // Identificação do serviço
        $servico = 'CteInutilizacao';
        //monta serviço
        $siglaUF = $this->aConfig['siglaUF'];
        //carrega serviço
        $servico = 'CteInutilizacao';
        $this->zLoadServico(
                'cte', $servico, $siglaUF, $tpAmb
        );
        if ($this->urlService == '') {
            $msg = "A inutilização não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }
        //montagem dos dados da mensagem SOAP
        $cnpj = $this->aConfig['cnpj'];
        $sAno = (string) date('y');
        $sSerie = str_pad($nSerie, 3, '0', STR_PAD_LEFT);
        $sInicio = str_pad($nIni, 9, '0', STR_PAD_LEFT);
        $sFinal = str_pad($nFin, 9, '0', STR_PAD_LEFT);
        //limpa os caracteres indesejados da justificativa
        $xJust = Strings::cleanString($xJust);
        // Identificador da TAG a ser assinada formada com Código da UF +
        // precedida do literal “ID”
        // 41 posições
        $id = 'ID' . $this->urlcUF . $cnpj . '57' . $sSerie . $sInicio . $sFinal;
        // Montagem do corpo da mensagem
        $dXML = "<inutCTe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "<infInut Id=\"$id\">"
                . "<tpAmb>$tpAmb</tpAmb>"
                . "<xServ>INUTILIZAR</xServ>"
                . "<cUF>$this->urlcUF</cUF>"
                . "<ano>$sAno</ano>"
                . "<CNPJ>$cnpj</CNPJ>"
                . "<mod>57</mod>"
                . "<serie>$nSerie</serie>"
                . "<nCTIni>$nIni</nCTIni>"
                . "<nCTFin>$nFin</nCTFin>"
                . "<xJust>$xJust</xJust>"
                . "</infInut></inutCTe>";
        //assina a solicitação de inutilização
        $signedMsg = $this->oCertificate->signXML($dXML, 'infInut');
        $signedMsg = Strings::clearXml($signedMsg, true);
        $body = "<cteDadosMsg xmlns=\"$this->urlNamespace\">$signedMsg</cteDadosMsg>";
        //envia a solicitação via SOAP
        $retorno = $this->oSoap->send(
                $this->urlService, $this->urlNamespace, $this->urlHeader, $body, $this->urlMethod
        );
        $lastMsg = $this->oSoap->lastMsg;
        $this->soapDebug = $this->oSoap->soapDebug;
        //salva mensagens
        if ($salvarMensagens) {
            $filename = "$sAno-$this->modelo-$sSerie-" . $sInicio . "_" . $sFinal . "-inutCTe.xml";
            $this->zGravaFile('cte', $tpAmb, $filename, $lastMsg);
            $filename = "$sAno-$this->modelo-$sSerie-" . $sInicio . "_" . $sFinal . "-retInutCTe.xml";
            $this->zGravaFile('cte', $tpAmb, $filename, $retorno);
        }
        //tratar dados de retorno
        $aRetorno = Response::readReturnSefaz($servico, $retorno);
        if ($aRetorno['cStat'] == '102') {
            $retorno = $this->zAddProtMsg('ProcInutCTe', 'inutCTe', $signedMsg, 'retInutCTe', $retorno);
            if ($salvarMensagens) {
                $filename = "$sAno-$this->modelo-$sSerie-" . $sInicio . "_" . $sFinal . "-procInutCTe.xml";
                $this->zGravaFile('cte', $tpAmb, $filename, $retorno, 'inutilizadas');
            }
        }
        return (string) $retorno;
    }

    public function sefazCancela($chCTe = '', $tpAmb = '2', $xJust = '', $nProt = '', &$aRetorno = array()) {
        $chCTe = preg_replace('/[^0-9]/', '', $chCTe);
        $nProt = preg_replace('/[^0-9]/', '', $nProt);
        $xJust = Strings::cleanString($xJust);
        //validação dos dados de entrada
        if (strlen($chCTe) != 44) {
            $msg = "Uma chave de CTe válida não foi passada como parâmetro $chCTe.";
            throw new Exception\InvalidArgumentException($msg);
        }
        if ($nProt == '') {
            $msg = "Não foi passado o numero do protocolo!!";
            throw new Exception\InvalidArgumentException($msg);
        }
        if (strlen($xJust) < 15 || strlen($xJust) > 255) {
            $msg = "A justificativa deve ter pelo menos 15 digitos e no máximo 255!!";
            throw new Exception\InvalidArgumentException($msg);
        }
        $siglaUF = $this->zGetSigla(substr($chCTe, 0, 2));

        //estabelece o codigo do tipo de evento CANCELAMENTO
        $tpEvento = '110111';
        $descEvento = 'Cancelamento';
        $nSeqEvento = 1;
        //monta mensagem
        $tagAdic = "<evCancCTe>"
                . "<descEvento>$descEvento</descEvento>"
                . "<nProt>$nProt</nProt>"
                . "<xJust>$xJust</xJust>"
                . "</evCancCTe>";
        $retorno = $this->zSefazEvento($siglaUF, $chCTe, $tpAmb, $tpEvento, $nSeqEvento, $tagAdic);
        $aRetorno = $this->aLastRetEvent;
        return $retorno;
    }

    public function enviaMail($pathXml = '', $aMails = array(), $templateFile = '', $comPdf = false, $pathPdf = '') {
        $mail = new Mail($this->aMailConf);
        // Se não for informado o caminho do PDF, monta um através do XML
        /*
          if ($comPdf && $this->modelo == '55' && $pathPdf == '') {
          $docxml = Files\FilesFolders::readFile($pathXml);
          $danfe = new Extras\Danfe($docxml, 'P', 'A4', $this->aDocFormat['pathLogoFile'], 'I', '');
          $id = $danfe->montaDANFE();
          $pathPdf = $this->aConfig['pathNFeFiles']
          . DIRECTORY_SEPARATOR
          . $this->ambiente
          . DIRECTORY_SEPARATOR
          . 'pdf'
          . DIRECTORY_SEPARATOR
          . $id . '-danfe.pdf';
          $pdf = $danfe->printDANFE($pathPdf, 'F');
          }
         *
         */
        if ($mail->envia($pathXml, $aMails, $comPdf, $pathPdf) === false) {
            throw new Exception\RuntimeException('Email não enviado. ' . $mail->error);
        }
        return true;
    }

    /**
     * zSefazEvento
     * @param string $siglaUF
     * @param string $chCTe
     * @param string $tpAmb
     * @param string $tpEvento
     * @param string $nSeqEvento
     * @param string $tagAdic
     * @return string
     * @throws Exception\RuntimeException
     * @internal function zLoadServico (Common\Base\BaseTools)
     */
    protected function zSefazEvento(
    $siglaUF = '', $chCTe = '', $tpAmb = '2', $tpEvento = '', $nSeqEvento = '1', $tagAdic = ''
    ) {
        if ($tpAmb == '') {
            $tpAmb = $this->aConfig['tpAmb'];
        }
        //carrega serviço
        $servico = 'CteRecepcaoEvento';
        $this->zLoadServico(
                'cte', $servico, $siglaUF, $tpAmb
        );
        if ($this->urlService == '') {
            $msg = "A recepção de eventos não está disponível na SEFAZ $siglaUF!!!";
            throw new Exception\RuntimeException($msg);
        }
        $aRet = $this->zTpEv($tpEvento);
        $aliasEvento = $aRet['alias'];
        $descEvento = $aRet['desc'];
        $cnpj = $this->aConfig['cnpj'];
        $dhEvento = (string) str_replace(' ', 'T', date('Y-m-d H:i:s'));
//        $dhEvento = (string) str_replace(' ', 'T', date('Y-m-d H:i:sP'));
        $sSeqEvento = str_pad($nSeqEvento, 2, "0", STR_PAD_LEFT);
        $eventId = "ID" . $tpEvento . $chCTe . $sSeqEvento;
        $cOrgao = $this->urlcUF;
        if ($siglaUF == 'AN') {
            $cOrgao = '91';
        }
        $mensagem = "<infEvento Id=\"$eventId\">"
                . "<cOrgao>$cOrgao</cOrgao>"
                . "<tpAmb>$tpAmb</tpAmb>"
                . "<CNPJ>$cnpj</CNPJ>"
                . "<chCTe>$chCTe</chCTe>"
                . "<dhEvento>$dhEvento</dhEvento>"
                . "<tpEvento>$tpEvento</tpEvento>"
                . "<nSeqEvento>$nSeqEvento</nSeqEvento>"
                //. "<nSeqEvento>$sSeqEvento</nSeqEvento>"
                . "<detEvento versaoEvento=\"$this->urlVersion\">"
                . "$tagAdic"
                . "</detEvento>"
                . "</infEvento>";

        $cons = "<eventoCTe xmlns=\"$this->urlPortal\" versao=\"$this->urlVersion\">"
                . "$mensagem"
                . "</eventoCTe>";

        $signedMsg = $this->oCertificate->signXML($cons, 'infEvento');
        $signedMsg = preg_replace("/<\?xml.*\?>/", "", $signedMsg);

        //$signedMsg = Strings::clearXml($signedMsg, true);
//        if (! $this->zValidMessage($signedMsg, 'cte', 'envEvento', $this->urlVersion)) {
//            $msg = 'Falha na validação. '.$this->error;
//            throw new Exception\RuntimeException($msg);
//        }
//        $filename = "../cancelamento.xml";
//        $xml = file_get_contents($filename);
        //$body = "<cteDadosMsg xmlns=\"$this->urlNamespace\">$xml</cteDadosMsg>";
        $body = "<cteDadosMsg xmlns=\"$this->urlNamespace\">$signedMsg</cteDadosMsg>";

        $retorno = $this->oSoap->send(
                $this->urlService, $this->urlNamespace, $this->urlHeader, $body, $this->urlMethod
        );
        $lastMsg = $this->oSoap->lastMsg;
        $this->soapDebug = $this->oSoap->soapDebug;
        //salva mensagens
        $filename = "$chCTe-$aliasEvento-envEvento.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $lastMsg);
        $filename = "$chCTe-$aliasEvento-retEnvEvento.xml";
        $this->zGravaFile('cte', $tpAmb, $filename, $retorno);
        //tratar dados de retorno
        $this->aLastRetEvent = Response::readReturnSefaz($servico, $retorno);
        if ($this->aLastRetEvent['cStat'] == '128') {
            if ($this->aLastRetEvent['evento'][0]['cStat'] == '135' ||
                    $this->aLastRetEvent['evento'][0]['cStat'] == '136' ||
                    $this->aLastRetEvent['evento'][0]['cStat'] == '155'
            ) {
                $pasta = 'eventos'; //default
                if ($aliasEvento == 'CanCTe') {
                    $pasta = 'canceladas';
                    $filename = "$chCTe-$aliasEvento-procEvento.xml";
                } elseif ($aliasEvento == 'CCe') {
                    $pasta = 'cartacorrecao';
                    $filename = "$chCTe-$aliasEvento-$nSeqEvento-procEvento.xml";
                }
                $retorno = $this->zAddProtMsg('procEventoCTe', 'evento', $signedMsg, 'retEvento', $retorno);
                $this->zGravaFile('cte', $tpAmb, $filename, $retorno, $pasta);
            }
        }
        return (string) $retorno;
    }

    /**
     * zAddProtMsg
     *
     * @param  string $tagproc
     * @param  string $tagmsg
     * @param  string $xmlmsg
     * @param  string $tagretorno
     * @param  string $xmlretorno
     * @return string
     */
    protected function zAddProtMsg($tagproc, $tagmsg, $xmlmsg, $tagretorno, $xmlretorno) {
        $doc = new Dom();
        $doc->loadXMLString($xmlmsg);
        $nodedoc = $doc->getNode($tagmsg, 0);
        $procver = $nodedoc->getAttribute("versao");
        $procns = $nodedoc->getAttribute("xmlns");

        $doc1 = new Dom();
        $doc1->loadXMLString($xmlretorno);
        $nodedoc1 = $doc1->getNode($tagretorno, 0);

        $proc = new \DOMDocument('1.0', 'utf-8');
        $proc->formatOutput = false;
        $proc->preserveWhiteSpace = false;
        //cria a tag nfeProc
        $procNode = $proc->createElement($tagproc);
        $proc->appendChild($procNode);
        //estabele o atributo de versão
        $procNodeAtt1 = $procNode->appendChild($proc->createAttribute('versao'));
        $procNodeAtt1->appendChild($proc->createTextNode($procver));
        //estabelece o atributo xmlns
        $procNodeAtt2 = $procNode->appendChild($proc->createAttribute('xmlns'));
        $procNodeAtt2->appendChild($proc->createTextNode($procns));
        //inclui a tag inutNFe
        $node = $proc->importNode($nodedoc, true);
        $procNode->appendChild($node);
        //inclui a tag retInutNFe
        $node = $proc->importNode($nodedoc1, true);
        $procNode->appendChild($node);
        //salva o xml como string em uma variável
        $procXML = $proc->saveXML();
        //remove as informações indesejadas
        $procXML = Strings::clearProt($procXML);
        return $procXML;
    }

    /**
     * zTpEv
     * @param string $tpEvento
     * @return array
     * @throws Exception\RuntimeException
     */
    private function zTpEv($tpEvento = '') {
        //montagem dos dados da mensagem SOAP
        switch ($tpEvento) {
            case '110110':
                //CCe
                $aliasEvento = 'CCe';
                $descEvento = 'Carta de Correcao';
                break;
            case '110111':
                //cancelamento
                $aliasEvento = 'CancNFe';
                $descEvento = 'Cancelamento';
                break;
            case '110140':
                //EPEC
                //emissão em contingência EPEC
                $aliasEvento = 'EPEC';
                $descEvento = 'EPEC';
                break;
            case '111500':
            case '111501':
                //EPP
                //Pedido de prorrogação
                $aliasEvento = 'EPP';
                $descEvento = 'Pedido de Prorrogacao';
                break;
            case '111502':
            case '111503':
                //ECPP
                //Cancelamento do Pedido de prorrogação
                $aliasEvento = 'ECPP';
                $descEvento = 'Cancelamento de Pedido de Prorrogacao';
                break;
            case '210200':
                //Confirmacao da Operacao
                $aliasEvento = 'EvConfirma';
                $descEvento = 'Confirmacao da Operacao';
                break;
            case '210210':
                //Ciencia da Operacao
                $aliasEvento = 'EvCiencia';
                $descEvento = 'Ciencia da Operacao';
                break;
            case '210220':
                //Desconhecimento da Operacao
                $aliasEvento = 'EvDesconh';
                $descEvento = 'Desconhecimento da Operacao';
                break;
            case '210240':
                //Operacao não Realizada
                $aliasEvento = 'EvNaoRealizada';
                $descEvento = 'Operacao nao Realizada';
                break;
            default:
                $msg = "O código do tipo de evento informado não corresponde a "
                        . "nenhum evento estabelecido.";
                throw new Exception\RuntimeException($msg);
        }
        return array('alias' => $aliasEvento, 'desc' => $descEvento);
    }

    private function zValidParamInut($xJust, $nSerie, $nIni, $nFin) {
        $msg = '';
        $nL = strlen($xJust);

        // Valida dos dados de entrada
        if ($nIni == '' || $nFin == '' || $xJust == '') {
            $msg = "Não foi passado algum dos parametos necessários"
                    . "inicio=$nIni fim=$nFin justificativa=$xJust.";
        } elseif (strlen($nSerie) == 0 || strlen($nSerie) > 3) {
            $msg = "O campo serie está errado: $nSerie. Corrija e refaça o processo!!";
        } elseif (strlen($nIni) < 1 || strlen($nIni) > 9) {
            $msg = "O campo numero inicial está errado: $nIni. Corrija e refaça o processo!!";
        } elseif (strlen($nFin) < 1 || strlen($nFin) > 9) {
            $msg = "O campo numero final está errado: $nFin. Corrija e refaça o processo!!";
        } elseif ($nL < 15 || $nL > 255) {
            $msg = "A justificativa tem que ter entre 15 e 255 caracteres, encontrado $nL. "
                    . "Corrija e refaça o processo!!";
        }

        if ($msg != '') {
            throw new Exception\InvalidArgumentException($msg);
        }
    }

    /**
     * validarXml
     * Valida qualquer xml do sistema NFe com seu xsd
     * NOTA: caso não exista um arquivo xsd apropriado retorna false
     * @param string $xml path ou conteudo do xml
     * @return boolean
     */
    public function validarXml($xml = '') {
        $aResp = array();
        $schem = IdentifyCTe::identificar($xml, $aResp);
        if ($schem == '') {
            return true;
        }
        $xsdFile = $aResp['Id'] . '_v' . $aResp['versao'] . '.xsd';
        $xsdPath = NFEPHP_ROOT . DIRECTORY_SEPARATOR .
                'schemas' .
                DIRECTORY_SEPARATOR .
                $this->aConfig['schemesCTe'] .
                DIRECTORY_SEPARATOR .
                $xsdFile;
        if (!is_file($xsdPath)) {
            $this->erros[] = "O arquivo XSD $xsdFile não foi localizado.";
            return false;
        }
        if (!ValidXsd::validar($aResp['xml'], $xsdPath)) {
            $this->erros[] = ValidXsd::$errors;
            return false;
        }
        return true;
    }

    public function sefazCartaCorrecao(
    $siglaUF = '', $tpAmb = '2', $cnpj = '', $chave = '', $nSeqEvento = '1', $grupoAlterado = '', $campoAlterado = '', $valorAlterado = '', $nroItemAlterado = '01', &$aRetorno = array()
    ) {
        $chCTe = preg_replace('/[^0-9]/', '', $chave);

        //validação dos dados de entrada
        if (strlen($chCTe) != 44) {
            $msg = "Uma chave de CTe válida não foi passada como parâmetro $chCTe.";
            throw new Exception\InvalidArgumentException($msg);
        }
        if ($siglaUF == '' || $cnpj == '' || $chave == '' ||
                $grupoAlterado == '' || $campoAlterado == '' || $valorAlterado == ''
        ) {
            $msg = "Preencha os campos obrigatórios!";
            throw new Exception\InvalidArgumentException($msg);
        }

        //estabelece o codigo do tipo de evento CARTA DE CORRECAO
        $tpEvento = '110110';
        $descEvento = 'Carta de Correcao';

        //monta mensagem
        $tagAdic = "<evCCeCTe>"
                . "<descEvento>$descEvento</descEvento>"
                . "<infCorrecao>"
                . "<grupoAlterado>$grupoAlterado</grupoAlterado>"
                . "<campoAlterado>$campoAlterado</campoAlterado>"
                . "<valorAlterado>$valorAlterado</valorAlterado>"
                . "<nroItemAlterado>$nroItemAlterado</nroItemAlterado>"
                . "</infCorrecao>"
                . "<xCondUso>"
                . "A Carta de Correcao e disciplinada pelo Art. 58-B do "
                . "CONVENIO/SINIEF 06/89: Fica permitida a utilizacao de carta de "
                . "correcao, para regularizacao de erro ocorrido na emissao de "
                . "documentos fiscais relativos a prestacao de servico de transporte, "
                . "desde que o erro nao esteja relacionado com: I - as variaveis que "
                . "determinam o valor do imposto tais como: base de calculo, "
                . "aliquota, diferenca de preco, quantidade, valor da prestacao;II - "
                . "a correcao de dados cadastrais que implique mudanca do emitente, "
                . "tomador, remetente ou do destinatario;III - a data de emissao ou "
                . "de saida."
                . "</xCondUso>"
                . "</evCCeCTe>";
        $retorno = $this->zSefazEvento($siglaUF, $chCTe, $tpAmb, $tpEvento, $nSeqEvento, $tagAdic);
        $aRetorno = $this->aLastRetEvent;
        return $retorno;
    }

}
