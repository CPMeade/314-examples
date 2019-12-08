<?php

/**
A class to wrap REST requests
*/
class RestRequest
{
	const REQ = 'REQUEST_METHOD';
	const GET = 'GET';
	const POST = 'POST';
	const PUT = 'PUT';
	const DEL = 'DELETE';

	private $requestType;

	/**
	Initialize the Rest Request
	*/
	function __construct()
	{
		$this->requestType = $_SERVER[self::REQ];
	}

	/**
	Returns the request variables
	*/
	function getRequestVariables()
	{
		$vars = null;

		//find the get variables
		if($this->isGet())
		{
			$vars = $_GET;
		}
		//otherwise decode the post, put, or delete vars
		else if ($this->isPost())
		{
			$vars = $_POST;
		}
		else if ($this->isPut())
		{
			$vars = $_PUT;
		}
		else if ($this->isDelete())
		{
			$vars = $_DELETE;
		}
		
		return $vars;
	}

	/**
	Returns the request type
	*/
	function getRequestType()
	{
		return $this->requestType;
	}

	/**
	Returns true if the request is GET
	*/
	function isGet()
	{
		return $this->requestType === self::GET;
	}

	/**
	Returns true if the request is POST
	*/
	function isPost()
	{
		return $this->requestType === self::POST;
	}

	/**
	Returns true if the request is PUT
	*/
	function isPut()
	{
		return $this->requestType === self::PUT;
	}

	/**
	Returns true if the request is DELETE
	*/
	function isDelete()
	{
		return $this->requestType === self::DEL;
	}
}

