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

	protected $config = array(
		'uri' => array(
			'ro' => 'http://localhost/api/v1',
			'rw' => 'http://localhost/api/v1',
		),
	);

	protected $curl_opts = array();
	protected $error = '';
	protected $headers = array();
	protected $iheaders = array();
	protected $raw_headers = array();

	public function __construct($options = array()) {
		if(array_key_exists('config_file', $options)) {
			$file = $options['config_file'];

			if(!is_null($file)) {
				$this->parseConfigFile($file);
			}

			unset($options['config_file']);
		} else {
			$file = '/usr/local/etc/nodegroups_client/config.ini';
			if(is_file($file)) {
				$this->parseConfigFile($file);
			}
		}

		$this->config = $this->setDetails($this->config, $options);

		$confopts = $this->getConfig('php');
		if(is_array($confopts)) {
			foreach($confopts as $opt => $value) {
				if(substr($opt, 0, 8) == 'CURLOPT_') {
					$c_opt = constant($opt);
					$this->curl_opts[$c_opt] = $value;
				}
			}
		}
	}

	/**
	 * Get the most recent error
	 * @return string
	 */
	public function error() {
		return $this->error;
	}

	/**
	 * Get a config param
	 * @param string $param
	 * @param string $sub
	 * @return mixed
	 */
	public function getConfig($param = '', $sub = '') {
		if(array_key_exists($param, $this->config)) {
			if(empty($sub)) {
				return $this->config[$param];
			} else {
				if(array_key_exists($sub,
						$this->config[$param])) {
					return $this->config[$param][$sub];
				}
			}
		}

		return NULL;
	}

	/**
	 * Get response header(s) from the most recent call
	 * @param string header (optional)
	 * @return mixed
	 */
	public function getHeader($header = '') {
		if(empty($header)) {
			return $this->headers;
		}

		if(array_key_exists($header, $this->headers)) {
			return $this->headers[$header];
		}

		$iheader = strtolower($header);
		if(array_key_exists($iheader, $this->iheaders)) {
			return $this->iheaders[$iheader];
		}

		return NULL;
	}

	/**
	 * Get nodegroup details
	 * @param string $nodegroup
	 * @return mixed
	 */
	public function getNodegroup($nodegroup) {
		$data = $this->queryGet('ro',
			'r/get_nodegroup.php', array(
			'nodegroup' => $nodegroup));

		if(is_array($data)) {
			if(array_key_exists('details', $data)) {
				return $data['details'];
			} else {
				$this->error =
					'Details field not in API output';
				return false;
			}
		}

		return false;
	}

	/**
	 * Get nodegroups from node
	 * @param string $node
	 * @param string $app
	 * @return array
	 */
	public function getNodegroupsFromNode($node, $app = '') {
		$opts = array(
			'node' => $node,
		);

		if($app) {
			$opts['app'] = $app;
			$opts['sortDir'] = 'asc';
			$opts['sortField'] = 'order';
		}

		$data = $this->queryGet('ro',
			'r/list_nodegroups_from_nodes.php', $opts);
		if(is_array($data)) {
			if(array_key_exists('records', $data)) {
				$nodegroups = array();
				while(list($junk, $nodegroup) =
						each($data['records'])) {
					$nodegroups[] = $nodegroup['nodegroup'];
				}

				return $nodegroups;
			} else {
				$this->error =
					'Records field not in API output';
				return false;
			}
		}

		return false;
	}

	/**
	 * Get nodes from an expression
	 * @param string $expression
	 * @return array
	 */
	public function getNodesFromExpression($expression) {
		// Add a space so as not to trigger the file upload
		// when expression begins with '@'
		$data = $this->queryPost('ro', 'r/list_nodes.php', array(
			'expression' => ' ' . $expression,
		));

		if(is_array($data)) {
			if(array_key_exists('records', $data)) {
				$nodes = array();
				while(list($junk, $node) =
						each($data['records'])) {
					$nodes[] = $node['node'];
				}

				return $nodes;
			} else {
				$this->error =
					'Records field not in API output';
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
		$data = $this->queryGet('ro', 'r/list_nodes.php', array(
			'nodegroup' => $nodegroup,
		));

		if(is_array($data)) {
			if(array_key_exists('records', $data)) {
				$nodes = array();
				while(list($junk, $node) =
						each($data['records'])) {
					$nodes[] = $node['node'];
				}

				return $nodes;
			} else {
				$this->error =
					'Records field not in API output';
				return false;
			}
		}

		return false;
	}

	/**
	 * Get the raw headers from the most recent call
	 * @return array
	 */
	public function getRawHeaders() {
		return $this->raw_headers;
	}

	/**
	 * Parse a config file
	 * @param string $file
	 */
	protected function parseConfigFile($file) {
		if(!is_file($file)) {
			throw new Exception('No such file: ' . $file);
		}

		$data = @parse_ini_file($file, true);

		if(empty($data)) {
			throw new Exception('Unable to parse config file');
		}

		$this->config = $this->setDetails($this->config, $data);
	}

	/**
	 * Make a GET query
	 * @param string $type ro/rw
	 * @param string $path
	 * @param array $params uri parameters
	 * @return mixed
	 */
	public function queryGet($type = 'ro', $path = '', $params = array()) {
		$url = sprintf("%s/%s?outputFormat=json",
			rtrim($this->getConfig('uri', $type)),
			ltrim($path, '/'));

		$url_params = array();

		foreach($params as $key => $value) {
			if(is_array($value)) {
				foreach($value as $t_value) {
					$url_params[] = sprintf("%s[]=%s",
						$key,
						rawurlencode($t_value));
					}
			} else {
				$url_params[] = sprintf("%s=%s", $key,
					rawurlencode($value));
			}
		}

		if(!empty($url_params)) {
			$url .= '&' . implode('&', $url_params);
		}

		$opts = array(
			CURLOPT_HEADERFUNCTION => array(&$this, 'readHeader'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_URL => $url
		);

		$this->headers = array();
		$this->iheaders = array();
		$this->raw_headers = array();

		$ch = curl_init();
		curl_setopt_array($ch, $this->curl_opts);
		curl_setopt_array($ch, $opts);

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
			curl_close($ch);
			return false;
		}

		curl_close($ch);
		return $data;
	}

	/**
	 * Make a POST query
	 * @param string $type ro/rw
	 * @param string $path
	 * @param array $post POST parameters
	 * @param array $get GET parameters
	 * @return mixed
	 */
	public function queryPost($type = 'ro', $path = '',
			$post = array(), $get = array()) {
		$url = sprintf("%s/%s?outputFormat=json",
			rtrim($this->getConfig('uri', $type)),
			ltrim($path, '/'));

		$url_params = array();

		foreach($get as $key => $value) {
			if(is_array($value)) {
				foreach($value as $t_value) {
					$url_params[] = sprintf("%s[]=%s",
						$key,
						rawurlencode($t_value));
					}
			} else {
				$url_params[] = sprintf("%s=%s", $key,
					rawurlencode($value));
			}
		}

		if(!empty($url_params)) {
			$url .= '&' . implode('&', $url_params);
		}

		$opts = array(
			CURLOPT_HEADERFUNCTION => array(&$this, 'readHeader'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_POSTFIELDS => $post,
			CURLOPT_URL => $url,
		);

		$this->headers = array();
		$this->iheaders = array();
		$this->raw_headers = array();

		$ch = curl_init();
		curl_setopt_array($ch, $this->curl_opts);
		curl_setopt_array($ch, $opts);

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
			curl_close($ch);
			return false;
		}

		curl_close($ch);
		return $data;
	}

	/**
	 * Callback for curl's HEADERFUNCTION
	 * @param object $ch
	 * @param string $header
	 * @return int
	 */
	protected function readHeader($ch, $header) {
		$this->raw_headers[] = $header;

		if(strpos($header, ': ') != 0) {
			list($key, $value) = explode(': ', $header, 2);

			$value = trim($value);
			if($value !== '') {
				$this->headers[$key] = $value;

				$ikey = strtolower($key);
				$this->iheaders[$ikey] = $value;
			}
		}

		return strlen($header);
	}

	/**
	 * set $details with overrides
	 * @param array $defaults
	 * @param array $overrides
	 * @return array
	 */
	public function setDetails($defaults = array(), $overrides = array()) {
		$details = $defaults;

		foreach(array_merge($overrides, $defaults) as $key => $junk) {
			if(!array_key_exists($key, $overrides)) {
				continue;
			}

			if(!array_key_exists($key, $defaults)) {
				$details[$key] = $overrides[$key];
				continue;
			}

			if(is_array($defaults[$key])) {
				if(!is_array($overrides[$key])) {
					unset($details[$key]);
					continue;
				}

				$details[$key] = $this->setDetails(
					$details[$key], $overrides[$key]);
			} else {
				$details[$key] = $overrides[$key];
			}
		}

		return $details;
	}
}

?>
