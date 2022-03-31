<?php

/**
 * Acesso ao conciliador de cartões da Exato Soluções (exatosolucoes.com.br)
 * @author Lucas Junqueira <lucas@exatosolucoes.com.br>
 * @version 1.0
 */
class ConciliadorExato {
	
	/**
	 * acesso aos webservices
	 */
	private $ws;
	
	/**
	 * usuário de acesso ao webservice
	 */
	private $usuario;
	
	/**
	 * Construtor do acesso à conciliação de cartões.
	 * @param string $url endereço de acesso ao webservice
	 * @param string $us usuário de acesso
	 */
	public function __construct($url, $us)
	{
		// criando o acesso aos webservices
		$this->ws = new WebserviceExato($url, $us);
		$this->usuario = $us;
	}
	
	/**
	 * Requisita a conciliação de registros de venda ou pagamento.
	 * @param string $texto o texto da requisição (json ou xml)
	 * @param string $chave a chave de 32 caracteres do usuário
	 * @param string $cliente o identificador do cliente
	 * @param string $tipo o tipo de requisição ("v" para venda, "p" para pagamento)
	 * @param string $formato o formato do texto da requisição ("json" ou "xml")
	 * @return array array associativo com a resposta, incluindo o código de erro "e" e uma mensagem explicativa "msg"
	 */
	public function requisitar($texto, $chave, $cliente, $tipo, $formato = 'json')
	{
		// preparando variáveis
		$tipo = mb_strtolower($tipo) == 'p' ? 'p' : 'v';
		$k = $this->usuario . substr($texto, 0, 32) . substr($texto, -32);
		
		// chamando o webservice
		$resp = $this->ws->requisitar('vdk-cartoes/conciliacao', $chave, $k, [
			't' => $tipo, 
			'c' => $cliente, 
			'compreq' => 's', 
			'req' => base64_encode(gzencode($texto)), 
			'forreq' => mb_strtolower($tipo) == 'xml' ? 'xml' : 'json', 
			'forresp' => mb_strtolower($tipo) == 'xml' ? 'xml' : 'json', 
		]);
		
		// ajustando erro de resposta
		switch($resp['e']) {
			case 1:
				$resp['msg'] = 'falha ao conectar à base de dados';
				break;
			case 2:
				$resp['msg'] = 'erro no texto da requisição';
				break;
			case 3:
				$resp['msg'] = 'erro no cabeçalho da requisição';
				break;
			case 4:
				$resp['msg'] = 'erro no cabeçalho da requisição';
				break;
			case 5:
				$resp['msg'] = 'o estabelecimento não foi localizado';
				break;
			case 6:
				$resp['msg'] = 'não há informações de adquirentes no período';
				break;
			case 7:
				$resp['msg'] = 'não há registros na requisição';
				break;
			case 8:
				$resp['msg'] = 'cliente não localizado';
				break;
		}
		
		// retornando a resposta
		return ($resp);
	}
	
	/**
	 * Recupera o log da última requisição.
	 * @return array o log da operação
	 */
	public function recLog()
	{
		return ($this->ws->recLog());
	}
}

/**
 * Acesso a webservices da Exato Soluções (exatosolucoes.com.br)
 * @author Lucas Junqueira <lucas@exatosolucoes.com.br>
 * @version 1.0
 */
class WebserviceExato {
	
	/**
	 * endereço de acesso aos serviços
	 */
	private $url;
	
	/**
	 * usuário da requisição
	 */
	private $usuario;
	
	/**
	 * log de requisição
	 */
	private $log = [ ];
	
	/**
	 * Construtor do acesso aos webservices.
	 * @param string $url endereço de acesso aos webservices
	 * @param string $us usuário de acesso
	 */
	public function __construct($url, $us)
	{
		$this->url = $url;
		$this->usuario = $us;
	}
	
	/**
	 * Faz uma chamada a um webservice.
	 * @param string $rota a rota do serviço
	 * @param string $chave a chave de 32 caracteres do usuário
	 * @param string $k o texto a ser usado na formação da variável "k" (sem a chave)
	 * @param array $vars array associativo com as variáveis usadas na requisição ("r", "u" e "k" são adicionadas automaticamente)
	 * @param bool $evtcomp retornar os eventos da resposta compactados (b64+gz)? (padrão: false)
	 * @return array array associativo com a resposta, incluindo o código de erro "e" e uma mensagem explicativa "msg"
	 */
	public function requisitar($rota, $chave, $k, $vars, $evtcomp = false)
	{
		// preparando log/erro
		$erro = 0;
		$this->log = [ ];
		$this->adLog('início da requisição');
		
		// validando rota
		if (strpos($rota, '/') === false) {
			$this->adLog('a rota indicada (' . $rota . ') é inválida');
			$erro = -10;
		} else {
			// seguindo a requisição
			$this->adLog('rota definida como ' . $rota);
			
			// criando chave
			$k = md5($chave . $k);
			$this->adLog('chave de acesso definida como ' . $k);

			// repassando valores
			$vars['r'] = $rota;
			$vars['u'] = $this->usuario;
			$vars['k'] = $k;
			if (isset($vars['fr'])) unset($vars['fr']);

			// preparando chamada
			$ch = curl_init();

			// definindo a url
			curl_setopt($ch, CURLOPT_URL, $this->url);

			// retornando texto ao invés de exibir a resposta
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			// incluindo os valores
			$valores = [ ];
			foreach ($vars as $key=>$value) $valores[] = $key . '=' . urlencode($value);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $valores));

			// acessando o webservice
			$this->adLog('conectando ao webservice em ' . $this->url);
			$resposta = curl_exec($ch);

			// fechando a conexão
			$this->adLog('resposta do webservice recebida');
			curl_close($ch);
			
			// conferindo a resposta
			if ($resposta === false) {
				// requisição sem sucesso
				$this->adLog('erro ao acessar o webservice');
				$erro = -11;
			} else {
				// validando
				$json = json_decode($resposta, true);
				if (json_last_error() == JSON_ERROR_NONE) {
					// resposta válida?
					if (isset($json['e'])) {
						$erro = $json['e'];
						$ret = $json;
						$this->adLog('resposta do webservice validada');
					} else {
						$erro = -13;
						$this->adLog('resposta do webservice corrompida (falta "e")');
					}
				} else {
					// resposta corrompida
					$this->adLog('resposta do webservice corrompida');
					$erro = -12;
				}
			}
		}
		
		// finalizando
		$this->adLog('fim da requisição');
		if ($erro == 0) {
			// retornar resposta do webservice
			$ret['e'] = 0;
			$ret['msg'] = 'requisição finalizada com sucesso';
			// eventos compactados?
			if (!$evtcomp) {
				foreach ($ret['evt'] as $k => $v) $ret['evt'][$k] = gzdecode(base64_decode($v));
			}
			return ($ret);
		} else {
			// retornar erro da requisição
			$ret = [ 'e' => $erro ];
			switch ($erro) {
				case -1:
					$ret['msg'] = 'rota de webservice não indicada';
					break;
				case -2:
					$ret['msg'] = 'rota de webservice inválida';
					break;
				case -3:
					$ret['msg'] = 'rota de webservice não localizada';
					break;
				case -4:
					$ret['msg'] = 'chave de validação incorreta ou falta de variável essencial';
					break;
				case -10:
					$ret['msg'] = 'rota inválida';
					break;
				case -11:
					$ret['msg'] = 'erro no acesso ao webservice';
					break;
				case -12:
					$ret['msg'] = 'resposta do webservice corrompida';
					break;
				case -13:
					$ret['msg'] = 'resposta do webservice corrompida (falta "e")';
					break;
				default:
					$ret['msg'] = 'erro específico do serviço requisitado, consulte o material de referência';
					break;
			}
			return ($ret);
		}
	}
	
	/**
	 * Recupera o log da última requisição.
	 * @return array o log da operação
	 */
	public function recLog()
	{
		return ($this->log);
	}
	
	/**
	 * Adiciona uma entrada ao log da requisição.
	 * @param string $texto o texto a adicionar
	 */
	private function adLog($texto)
	{
		$this->log[] = date('d/m/Y H:i:s') . ' => ' . $texto;
	}
	
}