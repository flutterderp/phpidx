<?php
date_default_timezone_set('UTC');

define('CACHE_PATH', __DIR__ . '/cache/');
define('CONFIG_FILE', __DIR__ . '/phpidx-config.json');

/**
 * @package phpidx
 * @author michael@hendrixweb.net
 * @copyright 2021 Otterly Useless (Attribution-ShareAlike 4.0 International (CC BY-SA 4.0))
 * @version 0.1.5
 * @link https://middleware.idxbroker.com/docs/api/overview.php
 */

class PhpIdx
{
	protected $apiversion;
	protected $accesskey;
	protected $baseurl;
	protected $cache_time;
	protected $ch;
	protected $component;
	protected $headers;
	protected $method;
	protected $uxtime;
	public $statelist;

	function __construct(string $cmpnt = '')
	{
		$config = json_decode(file_get_contents(CONFIG_FILE), false);

		$this->accesskey  = $config->PHPIDXConfig->accesskey;
		$this->apiversion = $config->PHPIDXConfig->apiversion;
		$this->baseurl    = $config->PHPIDXConfig->baseurl;
		$this->method     = 'GET';
		$this->cache_time = $config->PHPIDXConfig->cache_time; // default fifteen minute cache (15 * 60)
		$this->ch         = curl_init();
		$this->uxtime     = time();
		$this->component  = $cmpnt ? $cmpnt . '/' : 'clients/';
		$this->baseurl    = $this->baseurl . $this->component;
		$this->statelist  = (array) $this->getStateList();

		if(file_exists(CACHE_PATH) !== true)
		{
			mkdir(CACHE_PATH, 0755);

			touch(CACHE_PATH);
		}
	}

	function __destruct()
	{
		curl_close($this->ch);
	}

	/**
	 * Function to build an associative array of states and their abbreviations
	 *
	 * @since 0.1.5
	 */
	function getStateList()
	{
		$list = json_decode(file_get_contents(__DIR__ . '/state-abbreviations.json'), true);

		return $list;
	}

	/**
	 * Fetch response
	 *
	 * @param $url
	 * @since 0.1.0
	 */
	protected function fetchResponse(string $url = '/')
	{
		$cache_file  = hash('sha256', $this->endpoint) . '.json';
		$fetch_cache = file_get_contents(CACHE_PATH . $cache_file);

		if(($fetch_cache !== false) && ((filemtime($cache_file) + $this->cache_time) > time()))
		{
			// Use our cached results
			$response = json_decode($fetch_cache, true);
		}
		else
		{
			$response = curl_exec($this->ch);
			$code     = curl_getinfo($this->ch, CURLINFO_HTTP_CODE);

			if($code >= 200 || $code < 300)
			{
				$response          = json_decode($response, true);
				$response['error'] = $code;
			}
			else
			{
				$response = array();
				$response['error'] = $code;
			}

			// Write to a cache file
			/* $to_file = array('headers' => $response_headers, 'body' => json_decode($response_body));
			$to_file = json_encode($to_file); */
			$to_file = json_encode($response);

			file_put_contents(CACHE_PATH . $cache_file, $to_file);
			touch($cache_file, time());
		}

		return $response;
	}

	/**
	 * Sets the endpoint to use in the cURL request
	 *
	 * @param endpoint
	 * @since 0.1.5
	 */
	function setEndpoint($endpoint = '/')
	{
		$this->endpoint = filter_var($endpoint, FILTER_SANITIZE_URL);
	}

	/**
	 * Sets cURL options
	 *
	 * @param accesskey
	 * @since 0.1.5
	 */
	protected function setOptions(string $accesskey = '', string $request_type = 'GET', array $addtl_headers = array())
	{
		// $http_header[] = 'Accept: application/json';
		$http_header[] = 'Accept-Encoding: gzip, deflate';
		$http_header[] = 'Cache-Control: no-cache';
		$http_header[] = 'Connection: keep-alive';
		// $http_header[] = 'User-Agent: PHP-CLI/' . PHP_VERSION;
		$http_header[] = 'Content-Type: application/x-www-form-urlencoded';
		$http_header[] = 'accesskey: ' . $this->accesskey;
		$http_header[] = 'outputtype: json';
		$http_header[] = 'apiversion: ' . $this->apiversion;

		if(!empty($addtl_headers))
		{
			$http_header = array_merge($http_header, $addtl_headers);
		}

		curl_setopt($this->ch, CURLOPT_URL, $this->baseurl . $this->endpoint);
		curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $request_type);
		curl_setopt($this->ch, CURLOPT_ENCODING, '');
		curl_setopt($this->ch, CURLOPT_HEADER, false);
		curl_setopt($this->ch, CURLOPT_HTTPHEADER, $http_header);
		curl_setopt($this->ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
		curl_setopt($this->ch, CURLOPT_MAXREDIRS, 10);
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($this->ch, CURLOPT_TIMEOUT, 60);
		curl_setopt($this->ch, CURLOPT_USERAGENT, 'PHP-CLI/' . PHP_VERSION);
		// curl_setopt($this->ch, CURLOPT_VERBOSE, true);
		// curl_setopt($this->ch, CURLOPT_POST, (strtoupper($request_type === 'POST') ? true : false));
	}

	/**
	 * Clients
	 *
	 * IDX Client level API for accessing client properties, links, agents, offices, and search information.
	 * endpoint: https://api.idxbroker.com/clients/[...]
	 */

	/**
	 * Listcomponents
	 *
	 * This is a simple, access anywhere, method for getting a list of all API components available.
	 * @since 0.1.2
	 */
	function listcomponents()
	{
		$url = $this->baseurl . 'listcomponents';

		$this->setEndpoint($url);
		$this->setOptions();

		$items = $this->fetchResponse();

		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
		{
			return $items;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Listmethods
	 *
	 * A simple method for listing all available methods in the current API component. This method will also list which request methods (GET, PUT, POST, or DELETE) are supported by each method.
	 * @since 0.1.2
	 */
	function listmethods()
	{
		$url = $this->baseurl . 'listmethods';

		$this->setEndpoint($url);
		$this->setOptions();

		$items = $this->fetchResponse();

		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
		{
			return $items;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Featured
	 *
	 * Fetches featured (active) properties
	 * @since 0.1.0
	 */
	function activeProperties(string $type = '', int $limit = 25, int $offset = 0)
	{
		// type options: featured, supplemental, historical
		$query_data                  = array();
		/* $query_data['propStatus[0]'] = 'Active';
		$query_data['per']           = 10;
		$query_data['srt']           = 'newset'; */
		$query_data['limit']         = $limit;
		$query_data['offset']        = 0;

		$query = http_build_query($query_data);
		$url   = $type . '?' . $query;

		$this->setEndpoint($url);
		$this->setOptions();

		$items = $this->fetchResponse();

		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
		{
			return $items;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Widgetsrc
	 *
	 * Gather all the URLs for javascript widgets on the user's account. These widgets can then be placed on the user's main site via the included URLs.
	 * @since 0.1.3
	 */
	function widgets()
	{
		$url = $this->baseurl . 'widgetsrc';

		$this->setEndpoint($url);
		$this->setOptions();

		$items = $this->fetchResponse();

		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
		{
			return $items;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Cities
	 *
	 * Returns cities available in each of a client's city lists
	 * @since 0.1.2
	 */
	function cities($id = '')
	{
		$id    = $id ? '/' . $id : '';
		$url   = $this->baseurl . 'cities' . $id;

		$this->setEndpoint($url);
		$this->setOptions();

		$items = $this->fetchResponse();

		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
		{
			return $items;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Agents
	 *
	 * A method to view agent information on multi-user accounts.
	 * @since 0.1.3
	 */
	function agents($id = 0)
	{
		$filter = $id ? '?filterField=agentID&filterValue=' . $id : '';
		$url    = $this->baseurl . 'agents' . $filter;

		$this->setEndpoint($url);
		$this->setOptions();

		$items  = $this->fetchResponse();

		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
		{
			return $items;
		}
		else
		{
			return false;
		}
	}

	/**
	 * MLS
	 *
	 * Client level API for accessing MLS information.
	 * endpoint: https://api.idxbroker.com/mls/[...]
	 */

	/**
	 * Searchfields
	 *
	 * All the fields in a given MLS that are currently allowed to be searched according to MLS guidelines.
	 * @since 0.1.2
	 */
	function searchFields($idx = '', $parent = 1)
	{
		$url   = $this->baseurl . 'searchfields/' . $idx . '?filterField=parentPtID&filterValue=' . $parent;

		$this->setEndpoint($url);
		$this->setOptions();

		$items = $this->fetchResponse();

		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
		{
			return $items;
		}
		else
		{
			return false;
		}
	}
}
