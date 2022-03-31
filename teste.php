<?php

// carregando a biblioteca do conciliador
require_once('ConciliadorExato.php');

// definindo valores (fornecidos pela Exato Soluções)
const URLWS = 'url do serviço';
const USWS = 'nome de usuário';
const CHWS = 'chave do usuário';
const CLCONC = 'identificador do cliente';

// criando conciliador
$conc = new \ConciliadorExato(URLWS, USWS);

// carregando o texto de conciliação
$texto = file_get_contents('requisicao.json');

// recuperando dados da conciliação de vendas (para pagamentos, alterar a varável "tipo" para "p")
$resp = $conc->requisitar($texto, CHWS, CLCONC, 'v');

// conferindo a resposta da requisição
echo('<h2>Log</h2><textarea style="width:100%; min-height:200px;">'.print_r($conc->recLog(), true).'</textarea><br />');
echo('<h2>Resposta</h2><textarea style="width:100%; min-height:200px;">'.print_r($resp, true).'</textarea><br />');

// em caso de sucesso, o resultado da conciliação pode ser acessado em $resp['evt'][0]
if ($resp['e'] == 0) {
	$json = json_decode($resp['evt'][0], true);
	echo('<h2>Resultado da requisição</h2><textarea style="width:100%; min-height:200px;">' . $json['arq'] . '</textarea>');
}