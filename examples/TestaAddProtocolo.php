<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');
include_once '../bootstrap.php';

use NFePHP\CTe\Tools;

$cteTools = new Tools('../config/config.json');

$aResposta = array();
$indSinc = '0'; //0=as�ncrono, 1=s�ncrono
$chave = '';
$recibo = '';

$tpAmb = 2;
if ($tpAmb == '1') :
    $pastaxml = "producao";
else :
    $pastaxml = "homologacao";
endif;

$pathCTefile = "../XML/CTe/{$pastaxml}/entradas/{$chave}.xml";
if (!$indSinc) {
    $pathProtfile = "../XML/CTe/{$pastaxml}/temporarias/201610/{$recibo}-retConsReciCTe.xml";
} else {
    $pathProtfile = "../XML/CTe/{$pastaxml}/temporarias/201610/{$recibo}-retEnviCTe.xml";
}
$saveFile = true;
$retorno = $cteTools->addProtocolo($pathCTefile, $pathProtfile, $saveFile);

//echo '<br><br><pre>';
//echo htmlspecialchars($retorno);
//echo "</pre><br>";

