<?php

/**
This code will create login sessions.
*/

include_once("../rest.php");

//make an object to process REST requests
$request = new RestRequest();

//get the request variables
$vars = $request->getRequestVariables();

//connect to the database
$db = new PDO("pgsql:dbname=postgres host=localhost password=314dev user=dev");

//view session
if($request->isGet())
{	

	session_start();
	
	$username = null;
	
	if (isset($_SESSION["username"]))
	{
		$username = $_SESSION["username"];
	}
	
	$results = array("username" => $username);
	
}
//create session
elseif($request->isPost())
{	
	try
	{
		$username = null;
		$password = null;
		if (isset($vars["username"]))
		{
			$username = $vars["username"];
		}
		else
		{
			throw new PDOException('username is required to log in');
		}
		if (isset($vars["password"]))
		{
			$password = $vars["password"];
		}
		else
		{
			throw new PDOException('password is required to log in');
		}
	
		player_exists($username, $db);
	
		verify_password($username, $password, $db); 	//-> verify given password
		session_start(); 	//-> create session id
		$_SESSION['username'] = $username;
		
		
		$results = array("error_text" => "");
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}

}
//delete session
elseif($request->isDelete())
{
	// delete the session id
	session_start();
	session_destroy();
	$results = array("error_text" => "");
}
//update session
elseif($request->isPut())
{
	// shouldn't ever happen
	$results = array("result" => "update");
}

function player_exists($username, $db)
{
	// check to see if a user exists
	$sql = "select username from player where username = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$username]);
	$result = $statement->fetch(PDO::FETCH_ASSOC);
	
	// Validate username
	if ($result != null) 
	{
		return $username;
	} 
	else
	{
		throw new PDOException('player does not exist');
	}
}

function verify_password($username, $password, $db)
{
	// check to see if the user has the given password
	$sql = "select password from player where username = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$username]);
	$db_password = $statement->fetch(PDO::FETCH_ASSOC)["password"];
	
	// Validate password
	if (password_verify($password, $db_password)) 
	{
		return $password;
	} 
	else
	{
		throw new PDOException('incorrect password');
	}
}

echo(json_encode($results));
?>
