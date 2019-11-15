<?php

/**
This code will do 'view' and 'create' operations for matches.
*/

include_once("../rest.php");

//make an object to process REST requests
$request = new RestRequest();

//get the request variables
$vars = $request->getRequestVariables();

//connect to the database
$db = new PDO("pgsql:dbname=postgres host=localhost password=314dev user=dev");

//view match
if($request->isGet())
{	
	
	//get the username
	$username = $vars["username"];
	$played = $vars["played"];

	player_exists($username, $db);
	
	if ($played == null)
	{
		//create a query
		$sql = "select * from game where winner = ? or loser = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$username, $username]);
		$results[] = $statement->fetch(PDO::FETCH_ASSOC);
		
		
	}
	else
	{
		
		$sql = "select * from game where winner = ? or loser = ? and played = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$username, $username, $played]);
		$results[] = $statement->fetch(PDO::FETCH_ASSOC);
		
	}
	
	
	
}
//create match
elseif($request->isPost())
{
	//get the request variables
	$games = $vars["games"];
	
	try
	{
		$db->beginTransaction();

		foreach ($games as $game)
		{
			$winner = $game["winner"];
			$loser = $game["loser"];
			$played = $game["played"];
			$won = $game["won"];
			$lost = $game["lost"];
			
			player_exists($winner, $db);
			player_exists($loser, $db);
			
			//create an insert statement
			$sql = "insert into game (winner, loser, played, won, lost) values (?, ?, ?, ?, ?)";
			$statement = $db->prepare($sql);
			$statement->execute([$winner, $loser, $played, $won, $lost]);
		}
		
		// get the winner and loser
		$sql = "select winner from match_view where played = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$played]);
		$winner = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
		
		$sql = "select loser from match_view where played = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$played]);
		$loser = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
		
		// get winner and loser ranks
		$sql = "select rank from player where username = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$winner]);
		$winner_rank = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
		
		$sql = "select rank from player where username = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$loser]);
		$loser_rank = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
			
		if ($winner_rank < $loser_rank)
		{
			$sql = "select max(rank) as rank from player";
			$statement = $db->prepare($sql);
			$statement->execute();
			$temp_rank = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
			$temp_rank = $temp_rank + 1;
			
			//move the rank of the player so that it does not conflict with other player ranks
			$sql = "update player set rank = ? where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$temp_rank, $winner]);
			$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
			
			// update other player's ranks
			
			for ($i = $winner_rank; $i > $loser_rankrank; $i--)
			{
				$sql = "update player set rank = ? where rank = ? and not username = ?";
				$statement = $db->prepare($sql);
				$statement->execute([$i, ($i - 1), $winner]);
				$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
			}
			
			//update the rank of the player
			$sql = "update player set rank = ? where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$loser_rank, $winner]);
			$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
		}
		
		$db->commit();

		$results = array("error_text" => "");
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}
}
//delete match
elseif($request->isDelete())
{
	//get the username
	$player1 = $vars["player1"];
	$player2 = $vars["player2"];
	$played = $vers["played"];
	
	try
	{
		player_exists($player1);
		player_exists($player2);
		
		$db->beginTransaction();
			
		//delete the player
		$sql = "delete from game where (winner = ? or loser = ?) and (winner = ? or loser = ?) and played = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$player1, $player1, $player2, $player2, $played]);
		$match_exists = $statement->fetch(PDO::FETCH_ASSOC);
			
		$results = array("error_text" => "");
		$db->commit();
		
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}
}
//update match
elseif($request->isPut())
{
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

echo(json_encode($results));
?>
