<?php

/**
 * @package phpidx
 * @author michael@hendrixweb.net
 * @copyright 2014 Michael Hendrix (Attribution-ShareAlike 4.0 International (CC BY-SA 4.0))
 * @version 0.1.3
 */

class PhpIdx
{
	protected $component;
	protected $headers;
	protected $baseurl		= 'https://api.idxbroker.com/';
	protected $method			= 'GET';
	protected $apiversion	= '1.2.1';
	
	function __construct($cmpnt = '', $accesskey = '')
	{
		$this->component	= $cmpnt ? $cmpnt . '/' : 'clients/';
		$this->baseurl		= $this->baseurl . $this->component;
		$this->headers		= array(
			'Content-Type: application/x-www-form-urlencoded',
			'accesskey: ' . $accesskey,
			'outputtype: json',
			'apiversion: ' . $this->apiversion,
		);
	}
	
	/**
	 * Fetch response
	 *
	 * @param $url
	 * @since 0.1.0
	 */
	protected function fetchResponse($url)
	{
		$handle = curl_init();
		curl_setopt($handle, CURLOPT_URL, $url);
		curl_setopt($handle, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
		
		$response	= curl_exec($handle);
		$code			= curl_getinfo($handle, CURLINFO_HTTP_CODE);
		
		if($code >= 200 || $code < 300)
			$response = json_decode($response, true);
		else
		{
			$response = array();
			$response['error'] = $code;
		}
		
		return $response;
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
		$url		= $this->baseurl . 'listcomponents';
		$items	= $this->fetchResponse($url);
		
		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
			return $items;
		else
			return false;
	}
	
	/**
	 * Listmethods
	 * 
	 * A simple method for listing all available methods in the current API component. This method will also list which request methods (GET, PUT, POST, or DELETE) are supported by each method.
	 * @since 0.1.2
	 */
	function listmethods()
	{
		$url		= $this->baseurl . 'listmethods';
		$items	= $this->fetchResponse($url);
		
		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
			return $items;
		else
			return false;
	}
	
	/**
	 * Featured
	 * 
	 * Fetches featured (active) properties
	 * @since 0.1.0
	 */
	function activeProperties($type = '')
	{
		// type options: featured, supplemental, historical
		$url		= $this->baseurl . $type . '?propStatus[0]=Active&per=10&srt=newest';
		$items	= $this->fetchResponse($url);
		
		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
			return $items;
		else
			return false;
	}
	
	/**
	 * Widgetsrc
	 * 
	 * Gather all the URLs for javascript widgets on the user's account. These widgets can then be placed on the user's main site via the included URLs.
	 * @since 0.1.3
	 */
	function widgets()
	{
		$url		= $this->baseurl . 'widgetsrc';
		$items	= $this->fetchResponse($url);
		
		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
			return $items;
		else
			return false;
	}
	
	/**
	 * Cities
	 * 
	 * Returns cities available in each of a client's city lists
	 * @since 0.1.2
	 */
	function cities($id = '')
	{
		$id			= $id ? '/' . $id : '';
		$url		= $this->baseurl . 'cities' . $id;
		$items	= $this->fetchResponse($url);
		
		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
			return $items;
		else
			return false;
	}
	
	/**
	 * Agents
	 * 
	 * A method to view agent information on multi-user accounts.
	 * @since 0.1.3
	 */
	function agents($id = 0)
	{
		$filter	= $id ? '?filterField=agentID&filterValue=' . $id : '';
		$url		= $this->baseurl . 'agents' . $filter;
		$items	= $this->fetchResponse($url);
		
		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
			return $items;
		else
			return false;
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
		$url		= $this->baseurl . 'searchfields/' . $idx . '?filterField=parentPtID&filterValue=' . $parent;
		$items	= $this->fetchResponse($url);
		
		if(is_array($items) && ($items['error'] >= 200 || $items['error'] < 300))
			return $items;
		else
			return false;
	}
}