<?php

/**
This code will do 'view' and 'create' operations for challenges.
*/

include_once("../rest.php");

//make an object to process REST requests
$request = new RestRequest();

//get the request variables
$vars = $request->getRequestVariables();

//connect to the database
$db = new PDO("pgsql:dbname=postgres host=localhost password=314dev user=dev");

//view challenge
if($request->isGet())
{	
	try
	{	
		$player = NULL;
		$challenger = NULL;
		
		if (isset($vars["challenger"]))
		{
			$challenger = $vars["challenger"];
		}
		else if (isset($vars["player"]))
		{
			$player = $vars["player"];
		}
		else
		{
			throw new PDOException('challenges require a player or challenger to view');
		}
		
		if ($player != null)
		{
			$player = player_exists($player, $db);
			
			$results["challenges"] = [];
			
			// get every challenge that the player is involved in
			$sql = "select * from challenge where challenger = ? or challengee = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$player, $player]);
			$results["challenges"] = $statement->fetchAll(PDO::FETCH_ASSOC);
		}
		else if ($challenger != null)
		{
			$challenger = player_exists($challenger, $db);
			
			// get the challenger's rank
			$sql = "select rank from player where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$challenger]);
			$challenger_rank = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
			
			// get every possible challenge that the challenger can make
			
			$sql = "select name, email, rank, phone, username from player where rank = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$challenger_rank - 1]);
			$challengee_one = $statement->fetch(PDO::FETCH_ASSOC);
			
			$sql = "select name, email, rank, phone, username from player where rank = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$challenger_rank - 2]);
			$challengee_two = $statement->fetch(PDO::FETCH_ASSOC);
			
			$sql = "select name, email, rank, phone, username from player where rank = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$challenger_rank - 3]);
			$challengee_three = $statement->fetch(PDO::FETCH_ASSOC);
			
			$candidates = [];
			$index = 0;
			
			if (!accepted_challenge($challengee_one["username"], $db) && $challengee_one != null)
			{
				$candidates[$index] = $challengee_one;
				$index++;
			}
			
			if (!accepted_challenge($challengee_two["username"], $db) && $challengee_two != null)
			{
				$candidates[$index] = $challengee_two;
				$index++;
			}
			
			if (!accepted_challenge($challengee_three["username"], $db) && $challengee_three != null)
			{
				$candidates[$index] = $challengee_three;
				$index++;
			}
			
			$results["candidates"] = $candidates;
		}
		else
		{
			$results["candidates"] = "none";
		}
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}
}
//create challenge
elseif($request->isPost())
{	
	try
	{
		if (isset($vars["challenger"]))
		{
			$challenger = $vars["challenger"];
		}
		else 
		{
			throw new PDOException('missing challenger username');
		}
		if (isset($vars["challengee"]))
		{
			$challengee = $vars["challengee"];
		}
		else 
		{
			throw new PDOException('missing challengee username');
		}
		if (isset($vars["scheduled"]))
		{
			$scheduled = $vars["scheduled"];
		}
		else 
		{
			throw new PDOException('missing scheduled time');
		}
		
		player_exists($challengee, $db);
		player_exists($challenger, $db);
		
		$sql = "select rank from player where username = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$challengee]);
		$rank = $statement->fetch(PDO::FETCH_ASSOC);
		$challengee_rank = $rank["rank"];
		
		$sql = "select rank from player where username = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$challenger]);
		$rank = $statement->fetch(PDO::FETCH_ASSOC);
		$challenger_rank = $rank["rank"];
		
		if (($challengee_rank - $challenger_rank) > 3)
		{
			throw new PDOException('Challengee rank cannot be more than 3 higher than the challenger');
		}
		
		if (($challengee_rank - $challenger_rank) < 1)
		{
			throw new PDOException('Challengee rank cannot be lower than challenger rank');
		}
		
		if (accepted_challenge($challengee, $db))
		{
			throw new PDOException('Challengee has already accepted a challenge');
		}

		if (accepted_challenge($challenger, $db))
		{
			throw new PDOException('Challengee rank cannot be lower than challenger rank');
		}
		
		$db->beginTransaction();
		
		$issued = date();

		//create an insert statement
		$sql = "insert into challenge (challenger, challengee, scheduled, issued) values (?, ?, ?, ?)";
		$statement = $db->prepare($sql);
		$statement->execute([$challenger, $challengee, $scheduled, $issued]);
		
		$db->commit();

		$results = array("error_text" => "");
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}
}
//delete challenge
elseif($request->isDelete())
{	
	try
	{
		if (isset($vars["challenger"]))
		{
			$challenger = $vars["challenger"];
		}
		else 
		{
			throw new PDOException('missing challenger username');
		}
		if (isset($vars["challengee"]))
		{
			$challengee = $vars["challengee"];
		}
		else 
		{
			throw new PDOException('missing challengee username');
		}
		if (isset($vars["scheduled"]))
		{
			$scheduled = $vars["scheduled"];
		}
		else 
		{
			throw new PDOException('missing scheduled time');
		}
		
		$challenger = player_exists($challenger, $db);
		$challengee = player_exists($challengee, $db);
		
		$db->beginTransaction();
		
		// delete the challenge
		$sql = "delete from challenge where challengee = ? and challenger = ? and scheduled = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$challengee, $challenger, $scheduled]);
		$exists = $statement->fetch(PDO::FETCH_ASSOC);
		
		$db->commit();
		
		$results = array("error_text" => "");
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}
}
//update challenge
elseif($request->isPut())
{
	try
	{
		$challengee = null;
		$challenger = null;
		$scheduled = null;
		$accepted = null;
		
		if (isset($vars["challenger"]))
		{
			$challenger = $vars["challenger"];
		}
		else 
		{
			throw new PDOException('missing challenger username');
		}
		if (isset($vars["challengee"]))
		{
			$challengee = $vars["challengee"];
		}
		else 
		{
			throw new PDOException('missing challengee username');
		}
		if (isset($vars["scheduled"]))
		{
			$scheduled = $vars["scheduled"];
		}
		else 
		{
			throw new PDOException('missing scheduled time');
		}
		if (isset($vars["accepted"]))
		{
			$accepted = $vars["accepted"];
		}
		else 
		{
			throw new PDOException('missing accepted time');
		}		
		
		$challenger = player_exists($challenger, $db);
		$challengee = player_exists($challengee, $db);
		
		$db->beginTransaction();
		
		// accept the challenge
		$sql = "update challenge accepted= ? where challenger = ? and challengee = ? and scheduled= ?";
		$statement = $db->prepare($sql);
		$statement->execute([$accepted, $challenger, $challengee, $scheduled]);
		$result = $statement->fetch(PDO::FETCH_ASSOC);
		
		// delete all other challenges
		$sql = "delete from challenge where (challenger = ? or challengee = ?) and accepted = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$challenger, $challengee, null]);
		$result = $statement->fetch(PDO::FETCH_ASSOC);
		
		$db->commit();
		
		$results = array("error_text" => "");
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}
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

function accepted_challenge($player, $db)
{
	// check to see if a user exists
	$sql = "select username from player where username = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$player]);
	$result = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($result == null)
	{
		return false;
	}
	
	$sql = "select acceoted from challenge where (challenger = ? or challengee = ?) and not accepted = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$player, $player, null]);
	$result = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($result == null)
	{
		return false;
	}
	else
	{
		return true;
	}
}

echo(json_encode($results));
?>
