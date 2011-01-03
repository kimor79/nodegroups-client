<?php

/**

Copyright (c) 2010, Kimo Rosenbaum and contributors
All rights reserved.

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:
    * Redistributions of source code must retain the above copyright
      notice, this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright
      notice, this list of conditions and the following disclaimer in the
      documentation and/or other materials provided with the distribution.
    * Neither the name of the owner nor the names of its contributors
      may be used to endorse or promote products derived from this
      software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER BE LIABLE FOR ANY
DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

**/

class NodegroupsClient {

	private $config = array();

	private $config_defaults = array(
		'host' => 'localhost',
		'ssl' => array(
			'cainfo' => '/etc/ssl/cacert.pem',
			'verifypeer' => '1',
		),
		'uri_prefix' => '/api',
		'use_ssl' => '',
		'useragent' => 'NodegroupsClient/INSERT_VERSION_HERE',
	);

	private $config_file = '/usr/local/etc/nodegroups_client.ini';

	protected $error = '';

	public function __construct() {
		if(is_file($this->config_file)) {
			$config_parsed = @parse_ini_file($this->config_file, true);

			if($config_parsed === false) {
				throw new Exception('Error parsing configuration file');
			}
		}

		foreach($this->config_defaults as $key => $value) {
			if(array_key_exists($key, $config_parsed)) {
				$this->config[$key] = $config_parsed[$key];
			} else {
				$this->config[$key] = $value;
			}
		}
	}

	/**
	 * Get a configuration value
	 * @param string $key
	 * @param string $sub
	 * @return mixed
	 */
	public function getConfig($key = '', $sub = '') {
		if(array_key_exists($key, $this->config)) {
			if(!empty($sub)) {
				if(is_array($this->config[$key])) {
					if(array_key_exists($sub, $this->config[$key])) {
						return $this->config[$key][$sub];
					}
				}
			}

			return $this->config[$key];
		}

		return '';
	}

	/**
	 * Get the most recent error
	 * @return string
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * Get nodes from an expression
	 * @param string $expression
	 * @return array
	 */
	public function getNodesFromExpression($expression) {
		// Add a space so as not to trigger the file upload
		// when expression begins with '@'
		$data = $this->queryPost('r/get_nodes_from_expression.php', array(
			'expression' => ' ' . $expression,
		));

		if(is_array($data)) {
			if(array_key_exists('records', $data)) {
				return $data['records'];
			} else {
				$this->error = 'Records field not in API output';
				return false;
			}
		}

		return false;
	}

	/**
	 * Get nodes from a nodegroup
	 * @param string $nodegroup
	 * @return array
	 */
	public function getNodesFromNodegroup($nodegroup) {
		$data = $this->queryGet('r/get_nodes_from_nodegroup.php', array(
			'nodegroup' => $nodegroup,
		));

		if(is_array($data)) {
			if(array_key_exists('records', $data)) {
				return $data['records'];
			} else {
				$this->error = 'Records field not in API output';
				return false;
			}
		}

		return false;
	}

	/**
	 * Make a GET query
	 * @param string uri
	 * @param array uri parameters
	 * @return mixed
	 */
	public function queryGet($uri = '', $params = array()) {
		$url = 'http';

		if($this->getConfig('use_ssl')) {
			$url .= 's';
		}

		$url .= '://' . $this->getConfig('host') . '/' . $this->getConfig('uri_prefix');
		$url .= '/v1/' . $uri . '?outputFormat=json';

		if(!empty($params)) {
			$t_params = array();

			foreach($params as $key => $value) {
				$t_params[] = $key . '=' . rawurlencode($value);
			}

			$url .= implode('&', $t_params);
		}

		$curlopts = array(
			CURLOPT_CAINFO => $this->getConfig('ssl', 'cainfo'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => $this->getConfig('ssl', 'verifypeer'),
			CURLOPT_USERAGENT => $this->getConfig('useragent'),
			CURLOPT_URL => $url,
		);

		$ch = curl_init();
		curl_setopt_array($ch, $curlopts);

		$j_data = curl_exec($ch);

		if(curl_errno($ch)) {
			$this->error = curl_error($ch);
			curl_close($ch);
			return false;
		}

		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($http_code != '200') {
			$this->error = 'API returned HTTP code: ' . $http_code;
			curl_close($ch);
			return false;
		}

		$data = json_decode($j_data, true);

		if(!is_array($data)) {
			$this->error = 'API returned invalid JSON';
			return false;
		}

		return $data;
	}

	/**
	 * Make a POST query
	 * @param string uri
	 * @param array uri parameters
	 * @return mixed
	 */
	public function queryPost($uri = '', $params = array()) {
		$url = 'http';

		if($this->getConfig('use_ssl')) {
			$url .= 's';
		}

		$url .= '://' . $this->getConfig('host') . '/' . $this->getConfig('uri_prefix');
		$url .= '/v1/' . $uri . '?outputFormat=json';

		$curlopts = array(
			CURLOPT_CAINFO => $this->getConfig('ssl', 'cainfo'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS => $params,
			CURLOPT_SSL_VERIFYPEER => $this->getConfig('ssl', 'verifypeer'),
			CURLOPT_USERAGENT => $this->getConfig('useragent'),
			CURLOPT_URL => $url,
		);

		$ch = curl_init();
		curl_setopt_array($ch, $curlopts);

		$j_data = curl_exec($ch);

		if(curl_errno($ch)) {
			$this->error = curl_error($ch);
			curl_close($ch);
			return false;
		}

		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($http_code != '200') {
			$this->error = 'API returned HTTP code: ' . $http_code;
			curl_close($ch);
			return false;
		}

		$data = json_decode($j_data, true);

		if(!is_array($data)) {
			$this->error = 'API returned invalid JSON';
			return false;
		}

		return $data;
	}

	/**
	 * Set a configuration value
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	public function setConfig($key, $value) {
		if(array_key_exists($key, $this->config_defaults)) {
			if(is_array($value)) {
				if(!is_array($this->config_defaults[$key])) {
					return false;
				}
			}

			$this->config[$key] = $value;
			return true;
		}

		return false;
	}
}

?>
