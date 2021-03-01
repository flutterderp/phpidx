<?php
/**
 * @package phpidx
 * @author michael@hendrixweb.net
 * @copyright 2021 Otterly Useless (Attribution-ShareAlike 4.0 International (CC BY-SA 4.0))
 * @version 0.1.3
 * @link https://middleware.idxbroker.com/docs/api/overview.php
 */

class PhpIdx
{
	protected $apiversion;
	protected $accesskey;
	protected $baseurl;
	protected $ch;
	protected $component;
	protected $headers;
	protected $method;
	protected $uxtime;

	function __construct(string $cmpnt = '', string $accesskey = '')
	{
		$this->apiversion = '1.7.0';
		$this->accesskey  = $accesskey;
		$this->baseurl    = 'https://api.idxbroker.com/';
		$this->method     = 'GET';
		$this->ch         = curl_init();
		$this->uxtime     = time();
		$this->component  = $cmpnt ? $cmpnt . '/' : 'clients/';
		$this->baseurl    = $this->baseurl . $this->component;
	}

	function __destruct()
	{
		curl_close($this->ch);
	}

	/**
	 * Fetch response
	 *
	 * @param $url
	 * @since 0.1.0
	 */
	protected function fetchResponse(string $url = '/')
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

		return $response;
	}

	/**
	 * Sets the endpoint to use in the cURL request
	 *
	 * @param endpoint
	 */
	function setEndpoint($endpoint = '/')
	{
		$this->endpoint = filter_var($endpoint, FILTER_SANITIZE_URL);
	}

	/**
	 * Sets cURL options
	 *
	 * @param accesskey
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
		$url   = $this->baseurl . 'widgetsrc';

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
