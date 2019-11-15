<?php

/**
This code will do 'view' and 'create' operations for players.
*/

include_once("../rest.php");

//make an object to process REST requests
$request = new RestRequest();

//get the request variables
$vars = $request->getRequestVariables();

//connect to the database
$db = new PDO("pgsql:dbname=postgres host=localhost password=314dev user=dev");

//view player
if($request->isGet())
{	
	//get the username
	$username = $vars["username"];

	//create a query
	$sql = "select name, email, rank, username, phone from player where username = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$username]);
	$results = $statement->fetch(PDO::FETCH_ASSOC);
	
	// calculate win/loss ratios
	$sql = "select count(winner)/count(match_view) as match_win_percentage from match_view where winner = ? or loser = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$username, $username]);
	$match_win = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($match_win == NULL)
	{
		$match_win["match_win_percentage"] = NULL;
	}
	
	$results = array_merge($results, $match_win);
	
	$sql = "select count(winner)/count(game) as game_win_percentage from game where winner = ? or loser = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$username, $username]);
	$game_win = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($game_win == NULL)
	{
		$game_win["game_win_percentage"] = NULL;
	}
	
	$results = array_merge($results, $game_win);
	
	$sql = "select avg(won - lost) as winning_margin from match_view where winner = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$username]);
	$win_margin = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($win_margin == NULL)
	{
		$win_margin["winning_margin"] = NULL;
	}
	
	$results = array_merge($results, $win_margin);
	
	$sql = "select avg(won - lost) as losing_margin from match_view where loser = ?";
	$statement = $db->prepare($sql);
	$statement->execute([$username]);
	$loss_margin = $statement->fetch(PDO::FETCH_ASSOC);
	
	if ($loss_margin == NULL)
	{
		$loss_margin["losing_margin"] = NULL;
	}
	
	$results = array_merge($results, $loss_margin);
	
	if ($results["username"] == NULL)
	{
		$results = "User does not exist";
	}
}
//create player
elseif($request->isPost())
{
	//get the request variables
	$name = $vars["name"];
	$email = $vars["email"];
	$phone = $vars["phone"];
	$username = $vars["username"];
	$password = $vars["password"];
	
	$sql = "select max(rank) as rank from player";
	$statement = $db->prepare($sql);
	$statement->execute();
	$rank = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
	$rank = $rank + 1;
	
	$phone = validate_phone($phone);
	$email = validate_email($email);
	
	try
	{
		$db->beginTransaction();

		//create an insert statement
		$sql = "insert into player (name, email, phone, username, password, rank) values (?, ?, ?, ?, ?, ?)";

		//prepare the statement
		$statement = $db->prepare($sql);

		//run the statement
		$statement->execute([$name, $email, $phone, $username, $password, $rank]);

		$db->commit();

		$results = array("error_text" => "");
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}
}
//delete player
elseif($request->isDelete())
{
	//get the username
	$username = $vars["username"];
	try
	{
		// see if the player exists
		$sql = "select username from player where username = ?";
		$statement = $db->prepare($sql);
		$statement->execute([$username]);
		$exists = $statement->fetch(PDO::FETCH_ASSOC);
		
		if ($exists == null)
		{
			$results = array("error_text" => "player does not exist");
		}
		else
		{
			//get the player's rank
			$sql = "select rank from player where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$username]);
			$rank = $statement->fetch(PDO::FETCH_ASSOC);
			$rank = $rank["rank"];
		
			$db->beginTransaction();
			
			//delete the player
			$sql = "delete from player where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$username]);
			$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
			
			//get the last rank
			$sql = "select max(rank) as last_rank from player";
			$statement = $db->prepare($sql);
			$statement->execute();
			$last_rank = $statement->fetch(PDO::FETCH_ASSOC);
			$last_rank = $last_rank["last_rank"];
			
			// change the ranks of other players
			while ($rank < $last_rank)
			{
				//update the rank of the next player
				$sql = "update player set rank= ? where rank = ?";
				$statement = $db->prepare($sql);
				$statement->execute([$rank, ($rank + 1)]);
				$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
				
				$rank++;
			}
			$results = array("error_text" => "");
			$db->commit();
		}
	}
	catch(PDOException $e)
	{
		$results = array("error_text" => $e->getMessage());
	}
}
//update player
elseif($request->isPut())
{
	$name = $vars["name"];
	$email = $vars["email"];
	$phone = $vars["phone"];
	$username = $vars["username"];
	$rank = $vars["rank"];
	
	try
	{
		$db->beginTransaction();
		
		if ($name != null)
		{
			//update the name of the next player
			$sql = "update player set name= ? where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$name, $username]);
			$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
		}
		
		if ($email != null)
		{
			$email = validate_email($email);
			
			//update the email of the next player
			$sql = "update player set email= ? where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$email, $username]);
			$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
		}
		
		if ($phone != null)
		{
			$phone = validate_phone($phone);
			//update the phone of the player
			$sql = "update player set phone= ? where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$phone, $username]);
			$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
		}
		
		if ($rank != null)
		{
			// get the player's old rank
			$sql = "select rank from player where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$username]);
			$old_rank = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
			
			$sql = "select max(rank) as rank from player";
			$statement = $db->prepare($sql);
			$statement->execute();
			$temp_rank = $statement->fetch(PDO::FETCH_ASSOC)["rank"];
			$temp_rank = $temp_rank + 1;
			
			//move the rank of the player so that it does not conflict with other player ranks
			$sql = "update player set rank = ? where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$temp_rank, $username]);
			$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
			
			// update other player's ranks
			
			if ($old_rank < $rank)
			{
				for ($i = $old_rank; $i < $rank; $i++)
				{
					$sql = "update player set rank = ? where rank = ? and not username = ?";
					$statement = $db->prepare($sql);
					$statement->execute([$i, ($i + 1), $username]);
					$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
				}
			}
			else
			{
				for ($i = $old_rank; $i > $rank; $i--)
				{
					$sql = "update player set rank = ? where rank = ? and not username = ?";
					$statement = $db->prepare($sql);
					$statement->execute([$i, ($i - 1), $username]);
					$player_exists = $statement->fetch(PDO::FETCH_ASSOC);
				}
			}
			
			//update the rank of the player
			$sql = "update player set rank = ? where username = ?";
			$statement = $db->prepare($sql);
			$statement->execute([$rank, $username]);
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

function validate_email($email)
{
	// Remove all illegal characters from email
	$email = filter_var($email, FILTER_SANITIZE_EMAIL);

	// Validate e-mail
	if (filter_var($email, FILTER_VALIDATE_EMAIL)) 
	{
		return $email;
	} 
	else
	{
		throw new PDOException('invalid email');
	}
}

function validate_phone($phone)
{
	//eliminate every char except 0-9
	$justNums = preg_replace("/[^0-9]/", '', $phone);

	//eliminate leading 1 if its there
	if (strlen($justNums) == 11) $justNums = preg_replace("/^1/", '',$justNums);

	//if we have 10 digits left, it's probably valid.
	if (strlen($justNums) == 10)
	{
		return $phone;
	}
	else
	{
		throw new PDOException('invalid phone number');
	}
}

echo(json_encode($results));
?>
