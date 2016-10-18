<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

require_once '../bootstrap.php';
use NFePHP\CTe\Tools;
$cteTools = new Tools('../config/config.json');

$aResposta = array();

$tpAmb = $cteTools ->aConfig['tpAmb'];
if ($tpAmb == '1') :
    $pastaxml = "producao";
else :
    $pastaxml = "homologacao";
endif;

$indSinc = '0'; //0=asíncrono, 1=síncrono
$chave = '';
$recibo = '';

$pathProtfile = "{$cteTools ->aConfig['pathCTeFiles']}/{$pastaxml}/temporarias/201610/{$chave}-CancNFe-retEnvEvento.xml";
$pathNFefile = "{$cteTools ->aConfig['pathCTeFiles']}/{$pastaxml}/enviadas/aprovadas/201610/{$chave}-protCTe.xml";
$saveFile = true;
$retorno = $cteTools->addCancelamento($pathNFefile, $pathProtfile, $saveFile);

echo '<br><br><PRE>';
echo htmlspecialchars($retorno);
echo '</PRE><BR>';
echo "<br>";
