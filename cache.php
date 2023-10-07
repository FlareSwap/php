<?php

	// Cache XML feeds.
	

	// includes
	include "appconf.php";
	
	// global 
	$dblink 		= false;
	$debugEnabled	= false;
	$action			= "";
	
	
	
	// db connect
	function dbConnect()
	{
		global $dbHost;
		global $dbUsername;
		global $dbPassword;
		global $dbName;
		
		// connect to db
		$dblink = @mysql_connect($dbHost, $dbUsername, $dbPassword, true);
		if( false == $dblink ) 
			return false;
			
		// set character set
		@mysqli_query($dblink,  "SET character_set_results=utf8");
		mb_language( "uni" );
		mb_internal_encoding( "UTF-8" );				

		// select db
		$select_db = @mysqli_select_db($dblink, $dbName);
		if( false == $select_db ) 
		{
			@mysqli_close($dblink);
			return false;
		}
		
		// set character set
		@mysqli_query($dblink,  "set names 'utf8'");
		@mysqli_query($dblink,  "SET character_set_client=utf8");
		@mysqli_query($dblink,  "SET character_set_connection=utf8");	
		
		return $dblink;
	}
	
	
	// db is feed updated
	function dbIsFeedUpdated( $name, $countryID, $pause )
	{
		global $db;
		
		if( empty( $countryID ) )
			$countryID = "NULL";
		
		// verify if feed exists
		if( $countryID != "NULL" )
			$query  = "SELECT `lastQuery` FROM `feeds` WHERE `name`='" . $name . "' AND `countryID`=" . $countryID;
		else
			$query  = "SELECT `lastQuery` FROM `feeds` WHERE `name`='" . $name . "' AND `countryID` IS NULL";
			
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query feeds from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{		
			mysqli_free_result( $result );
			return false;
		}
		
		$row = mysqli_fetch_array( $result );
		mysqli_free_result( $result );
		
		// inside pause period
		if( ( time() - strtotime( $row["lastQuery"] ) ) <= $pause )
			return true;
			
		return false;
	}
	
	
	// get match status
	function getMatchScore( $leagueId, $matchId )
	{
		global $db;
		
		$query = "SELECT * FROM `matchstatus` WHERE `leagueID`='" . $leagueId . "' AND `matchID`='" . $matchId . "'";
		
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query match status from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			return false;
		}
		
		$row = mysqli_fetch_array( $result );
		mysqli_free_result( $result );
		
		return $row;	
	}
	
	
	// get odd status
	function getOddStatus( $leagueId, $matchId )
	{
		global $db;
		
		$query = "SELECT * FROM `oddstatus` WHERE `leagueID`='" . $leagueId . "' AND `matchID`='" . $matchId . "'";
		
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query odd status from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			return false;
		}
		
		$row = mysqli_fetch_array( $result );
		mysqli_free_result( $result );
		
		return $row;	
	}
	
	
	// get odd status (2)
	function getOddStatus2( $leagueId, $matchId, $bookerName )
	{
		global $db;
		
		$query = "SELECT * FROM `oddstatus2` WHERE `leagueID`='" . $leagueId . "' AND `matchID`='" . $matchId . "' AND `bookerName`='" . mysqli_real_escape_string($mysqli_link,  $bookerName ) . "'";
		
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query odd status (2) from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			return false;
		}
		
		$row = mysqli_fetch_array( $result );
		mysqli_free_result( $result );
		
		return $row;	
	}
	
	
	// db feed get
	function dbFeedGet( $name )
	{
		global $db;
		
		$query = "SELECT * FROM `feeds` WHERE `name`='" . $name . "'";
		
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query feeds from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		return $result;
	}
	
	
	// db feed past copy
	function dbFeedCopyDaily( $name, $date )
	{
		global $db;
		
		$query = "SELECT *, DATE_FORMAT(`lastUpdate`, '%Y-%m-%d') AS `curDay` FROM `feeds` WHERE `name`='" . $name . "' AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')<>'" . $date . "'";
		
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query feeds from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		while( $feed = mysqli_fetch_array( $result ) )
		{
			if( !dbInsertFeedDaily( $name, $feed["countryID"], $feed["value"], $feed["curDay"] ) )
				logError( "Failed to insert past feed in db" );
		}
		
		mysqli_free_result( $result );
		
		return true;
	}
	
	
	// match status add
	function dbMatchStatusAdd( $leagueId, $leagueName, $matchId, $team1, $team2, $matchDate, $time, $score1, $score2, $scoreHT, $matchStatus, $goals1, $cards1, $goals2, $cards2, $matchTime )
	{
		global $db;
		
		// verify if match exists
		$query = "SELECT * FROM `matchstatus` WHERE `leagueID`='" . $leagueId . "' AND `matchID`='" . $matchId . "'";
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query match status from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// get ro date
		$dateConvert 	= $matchDate . " " . $time;
		$timeConvert 	= strtotime( $dateConvert . ' UTC' );
		$roDate 		= date( "d.m.Y", $timeConvert );		
		$roTime 		= date( "H:i", $timeConvert );		
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `matchstatus` (`leagueID`, `leagueName`, `matchID`, `team1`, `team2`, `date`, `time`, `ro_date`, `ro_time`, `status`, `score1`, `score2`, `scoreHT`, `goals1`, `cards1`, `goals2`, `cards2`, `match_time`, `lastUpdate`) VALUES('" . 
						$leagueId . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', '" . 
						$matchId . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $team1 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $team2 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $matchDate ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $time ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $roDate ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $roTime ) . "', '" . 						
						mysqli_real_escape_string($mysqli_link,  $matchStatus ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $score1 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $score2 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $scoreHT ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $goals1 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $cards1 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $goals2 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $cards2 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $matchTime ) . "', '" . 						
						$date . "')";
			
			$result = @mysqli_query($db,  $query);
			if( false == $result )
			{
				logError( "Failed to insert match status " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
				return false;
			}			
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `matchstatus` SET `date`='" . mysqli_real_escape_string($mysqli_link,  $matchDate ) . 
					  "', `time`='" . mysqli_real_escape_string($mysqli_link,  $time ) . 
					  "', `ro_date`='" . mysqli_real_escape_string($mysqli_link,  $roDate ) . 
					  "', `ro_time`='" . mysqli_real_escape_string($mysqli_link,  $roTime ) . 					  
					  "', `status`='" . mysqli_real_escape_string($mysqli_link,  $matchStatus ) . 
					  "', `score1`='" . mysqli_real_escape_string($mysqli_link,  $score1 ) . 
					  "', `score2`='" . mysqli_real_escape_string($mysqli_link,  $score2 ) . 
					  "', `scoreHT`='" . mysqli_real_escape_string($mysqli_link,  $scoreHT ) . 
					  "', `goals1`='" . mysqli_real_escape_string($mysqli_link,  $goals1 ) . 
					  "', `cards1`='" . mysqli_real_escape_string($mysqli_link,  $cards1 ) . 
					  "', `goals2`='" . mysqli_real_escape_string($mysqli_link,  $goals2 ) . 
					  "', `cards2`='" . mysqli_real_escape_string($mysqli_link,  $cards2 ) . 		
					  "', `match_time`='" . mysqli_real_escape_string($mysqli_link,  $matchTime ) . 						  
					  "', `lastUpdate`='" . $date . "' ".
					  "WHERE `id`=" . $row["id"];
					  
			$result = @mysqli_query($db,  $query);
			if( false == $result )
			{
				logError( "Failed to update match status " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
				return false;
			}
		}
		
		return true;			
	}
	
	
	// odd status add
	function dbOddStatusAdd( $leagueId, $leagueName, $matchId, $bookerName, $team1, $team2, $matchDate, $time, $odds1, $odds2, $oddsX, $high1, $high2, $highX, $dir1, $dir2, $dirX )
	{
		global $db;
		global $debugEnabled;
		
		// verify if odd exists
		$query = "SELECT * FROM `oddstatus` WHERE `leagueID`='" . $leagueId . "' AND `matchID`='" . $matchId . "'";
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query odd status from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// get ro date
		$dateConvert 	= $matchDate . " " . $time;
		$timeConvert 	= strtotime( $dateConvert . ' UTC' );
		$roDate 		= date( "d.m.Y", $timeConvert );			
		$roTime 		= date( "H:i", $timeConvert );	
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `oddstatus` (`leagueID`, `leagueName`, `matchID`, `bookerName`, `team1`, `team2`, `date`, `time`, `ro_date`, `ro_time`, `odds1`, `odds2`, `oddsX`, `lastUpdate`) VALUES('" . 
						$leagueId . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', '" . 
						$matchId . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $bookerName ) . "', '" . 						
						mysqli_real_escape_string($mysqli_link,  $team1 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $team2 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $matchDate ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $time ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $roDate ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $roTime ) . "', '" . 							
						mysqli_real_escape_string($mysqli_link,  $odds1 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $odds2 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $oddsX ) . "', '" .
						$date . "')";
			
			if( $debugEnabled )
				echo $query . "\n";
			
			$result = @mysqli_query($db,  $query);
			if( false == $result )
			{
				logError( "Failed to insert odd status " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
				return false;
			}			
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `oddstatus` SET `date`='" . mysqli_real_escape_string($mysqli_link,  $matchDate ) . 
					  "', `time`='" . mysqli_real_escape_string($mysqli_link,  $time ) . 		
					  "', `ro_date`='" . mysqli_real_escape_string($mysqli_link,  $roDate ) . 
					  "', `ro_time`='" . mysqli_real_escape_string($mysqli_link,  $roTime ) . 					  
					  "', `bookerName`='" . mysqli_real_escape_string($mysqli_link,  $bookerName ) . 
					  "', `odds1Latest`='" . mysqli_real_escape_string($mysqli_link,  $odds1 ) . 
					  "', `oddsXLatest`='" . mysqli_real_escape_string($mysqli_link,  $oddsX ) . 
					  "', `odds2Latest`='" . mysqli_real_escape_string($mysqli_link,  $odds2 ) . 
					  "', `high1`='" . mysqli_real_escape_string($mysqli_link,  $high1 ) . 
					  "', `high2`='" . mysqli_real_escape_string($mysqli_link,  $high2 ) . 
					  "', `highX`='" . mysqli_real_escape_string($mysqli_link,  $highX ) . 
					  "', `dir1`='" . mysqli_real_escape_string($mysqli_link,  $dir1 ) . 
					  "', `dir2`='" . mysqli_real_escape_string($mysqli_link,  $dir2 ) . 	
					  "', `dirX`='" . mysqli_real_escape_string($mysqli_link,  $dirX ) . 						  
					  "', `lastUpdate`='" . $date . "' ".
					  "WHERE `id`=" . $row["id"];
			
			if( $debugEnabled )
				echo $query . "\n";
			
			$result = @mysqli_query($db,  $query);
			if( false == $result )
			{
				logError( "Failed to update odd status " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
				return false;
			}
		}
		
		return true;		
	}
	
	
	// odd status add (2)
	function dbOddStatusAdd2( $leagueId, $leagueName, $matchId, $bookerName, $team1, $team2, $matchDate, $time, $odds1, $odds2, $oddsX, $high1, $high2, $highX, $dir1, $dir2, $dirX )
	{
		global $db;
		global $debugEnabled;
		
		// verify if odd exists
		$query = "SELECT * FROM `oddstatus2` WHERE `leagueID`='" . $leagueId . "' AND `matchID`='" . $matchId . "' AND `bookerName`='" . $bookerName . "'";
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query odd status (2) from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// get ro date
		$dateConvert 	= $matchDate . " " . $time;
		$timeConvert 	= strtotime( $dateConvert . ' UTC' );
		$roDate 		= date( "d.m.Y", $timeConvert );		
		$roTime 		= date( "H:i", $timeConvert );
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `oddstatus2` (`leagueID`, `leagueName`, `matchID`, `bookerName`, `team1`, `team2`, `date`, `time`, `ro_date`, `ro_time`, `odds1`, `odds2`, `oddsX`, `lastUpdate`) VALUES('" . 
						$leagueId . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', '" . 
						$matchId . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $bookerName ) . "', '" . 						
						mysqli_real_escape_string($mysqli_link,  $team1 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $team2 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $matchDate ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $time ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $roDate ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $roTime ) . "', '" . 						
						mysqli_real_escape_string($mysqli_link,  $odds1 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $odds2 ) . "', '" . 
						mysqli_real_escape_string($mysqli_link,  $oddsX ) . "', '" .
						$date . "')";
			
			if( $debugEnabled )
				echo $query . "\n";
			
			$result = @mysqli_query($db,  $query);
			if( false == $result )
			{
				logError( "Failed to insert odd status (2) " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
				return false;
			}			
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `oddstatus2` SET `date`='" . mysqli_real_escape_string($mysqli_link,  $matchDate ) . 
					  "', `time`='" . mysqli_real_escape_string($mysqli_link,  $time ) . 
					  "', `ro_date`='" . mysqli_real_escape_string($mysqli_link,  $roDate ) . 
					  "', `ro_time`='" . mysqli_real_escape_string($mysqli_link,  $roTime ) . 						  
					  "', `odds1Latest`='" . mysqli_real_escape_string($mysqli_link,  $odds1 ) . 
					  "', `oddsXLatest`='" . mysqli_real_escape_string($mysqli_link,  $oddsX ) . 
					  "', `odds2Latest`='" . mysqli_real_escape_string($mysqli_link,  $odds2 ) . 
					  "', `high1`='" . mysqli_real_escape_string($mysqli_link,  $high1 ) . 
					  "', `high2`='" . mysqli_real_escape_string($mysqli_link,  $high2 ) . 
					  "', `highX`='" . mysqli_real_escape_string($mysqli_link,  $highX ) . 
					  "', `dir1`='" . mysqli_real_escape_string($mysqli_link,  $dir1 ) . 
					  "', `dir2`='" . mysqli_real_escape_string($mysqli_link,  $dir2 ) . 	
					  "', `dirX`='" . mysqli_real_escape_string($mysqli_link,  $dirX ) . 						  
					  "', `lastUpdate`='" . $date . "' ".
					  "WHERE `id`=" . $row["id"];
			
			if( $debugEnabled )
				echo $query . "\n";
			
			$result = @mysqli_query($db,  $query);
			if( false == $result )
			{
				logError( "Failed to update odd status (2) " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
				return false;
			}
		}
		
		return true;		
	}
	
	
	// db insert league
	function dbInsertLeague( $nameIdx, $countryID, $leagueName, $leagueCup, $leagueId, $leagueSubId )
	{
		global $db;
		
		// verify if league exists
		$query = "SELECT * FROM `leagues` WHERE `feed_id`=" . $leagueId;
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query feeds from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `leagues` (`countryID`, `name`, `is_cup`, `feed_id`, `feed_sub_id`, `lastUpdate`) VALUES(" . $countryID . ", '" . mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', " . $leagueCup . ", '" . mysqli_real_escape_string($mysqli_link,  $leagueId ) . "', '" . mysqli_real_escape_string($mysqli_link,  $leagueSubId ) . "', '" . $date . "')";
			$result = @mysqli_query($db,  $query);
			if( false == $result )
			{
				logError( "Failed to insert league " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
				return false;
			}			
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// name
			if( $nameIdx == 1 && strtolower( $row["name"] ) != strtolower( $leagueName ) )
			{
				$query  = "UPDATE `leagues` SET `name`='" . mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', `lastUpdate`='" . $date . "' WHERE `id`=" . $row["id"];
				$result = @mysqli_query($db,  $query);
				if( false == $result )
				{
					logError( "Failed to update league " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
					return false;
				}
			}
			else
			// name2
			if( $nameIdx == 2 && strtolower( $row["name2"] ) != strtolower( $leagueName ) )
			{
				$query  = "UPDATE `leagues` SET `name2`='" . mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', `lastUpdate`='" . $date . "' WHERE `id`=" . $row["id"];
				$result = @mysqli_query($db,  $query);
				if( false == $result )
				{
					logError( "Failed to update league " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
					return false;
				}
			}
			else
			// name3
			if( $nameIdx == 3 && strtolower( $row["name3"] ) != strtolower( $leagueName ) )
			{
				$query  = "UPDATE `leagues` SET `name3`='" . mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', `lastUpdate`='" . $date . "' WHERE `id`=" . $row["id"];
				$result = @mysqli_query($db,  $query);
				if( false == $result )
				{
					logError( "Failed to update league " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
					return false;
				}
			}
			else
			// name4
			if( $nameIdx == 4 && strtolower( $row["name4"] ) != strtolower( $leagueName ) )
			{
				$query  = "UPDATE `leagues` SET `name4`='" . mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', `lastUpdate`='" . $date . "' WHERE `id`=" . $row["id"];
				$result = @mysqli_query($db,  $query);
				if( false == $result )
				{
					logError( "Failed to update league " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
					return false;
				}
			}
			else
			// name5
			if( $nameIdx == 5 && strtolower( $row["name5"] ) != strtolower( $leagueName ) )
			{
				$query  = "UPDATE `leagues` SET `name5`='" . mysqli_real_escape_string($mysqli_link,  $leagueName ) . "', `lastUpdate`='" . $date . "' WHERE `id`=" . $row["id"];
				$result = @mysqli_query($db,  $query);
				if( false == $result )
				{
					logError( "Failed to update league " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
					return false;
				}
			}
			
			// cup
			if( intval( $row["is_cup"] ) != intval( $leagueCup ) )
			{
				// update RSS feed
				$query  = "UPDATE `leagues` SET `feed_sub_id`='" . mysqli_real_escape_string($mysqli_link,  $leagueSubId ) . "', `is_cup`='" . mysqli_real_escape_string($mysqli_link,  $leagueCup ) . "', `lastUpdate`='" . $date . "' WHERE `id`=" . $row["id"];
				$result = @mysqli_query($db,  $query);
				if( false == $result )
				{
					logError( "Failed to update league " . $leagueName . ": " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
					return false;
				}
			}
		}
		
		return true;		
	}
	
	
	// db insert feed
	function dbInsertFeed( $name, $countryID, $feed, $tips = "" )
	{
		global $db;
		
		if( empty( $countryID ) )
			$countryID = "NULL";
		
		// verify if feed exists
		if( $countryID != "NULL" )
			$query  = "SELECT `id` FROM `feeds` WHERE `name`='" . $name . "' AND `countryID`=" . $countryID;
		else
			$query  = "SELECT `id` FROM `feeds` WHERE `name`='" . $name . "' AND `countryID` IS NULL";
			
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query feeds from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `feeds` (`name`, `countryID`, `value`, `tips`, `lastUpdate`, `lastQuery`) VALUES('" . mysqli_real_escape_string($mysqli_link,  $name ) . "', " . $countryID . ", '" . mysqli_real_escape_string($mysqli_link,  $feed ) . ", '" . mysqli_real_escape_string($mysqli_link,  $tips ) . "', '" . $date . "', '" . $date . "')";
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `feeds` SET `value`='" . mysqli_real_escape_string($mysqli_link,  $feed ) . "', `tips`='" . mysqli_real_escape_string($mysqli_link,  $tips ) . "', `lastUpdate`='" . $date . "', `lastQuery`='" . $date . "' WHERE `id`=" . $row["id"];
		}
		
		// update RSS feed
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to update " . $name . " RSS feed in db - MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		return true;
	}
	
	
	// db insert feed daily
	function dbInsertFeedDaily( $name, $countryID, $feed, $curDay )
	{
		global $db;
		
		if( empty( $countryID ) )
			$countryID = "NULL";
		
		// verify if feed exists
		if( $countryID != "NULL" )
			$query  = "SELECT `id` FROM `feeddaily` WHERE `name`='" . $name . "' AND `countryID`=" . $countryID . " AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')='" . $curDay . "'";
		else
			$query  = "SELECT `id` FROM `feeddaily` WHERE `name`='" . $name . "' AND `countryID` IS NULL AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')='" . $curDay . "'";
			
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query feed daily from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `feeddaily` (`name`, `countryID`, `value`, `lastUpdate`, `lastQuery`) VALUES('" . mysqli_real_escape_string($mysqli_link,  $name ) . "', " . $countryID . ", '" . mysqli_real_escape_string($mysqli_link,  $feed ) . "', '" . $curDay . "', '" . $date . "')";
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `feeddaily` SET `value`='" . mysqli_real_escape_string($mysqli_link,  $feed ) . "', `lastUpdate`='" . $curDay . "', `lastQuery`='" . $date . "' WHERE `id`=" . $row["id"];
		}
		
		// update RSS feed
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to update " . $name . " RSS feed in db - MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		return true;
	}
	
	
	// db insert tips daily
	function dbInsertTipsDaily( $countryID, $tips, $curDay )
	{
		global $db;
		
		if( empty( $countryID ) )
			$countryID = "NULL";
		
		// verify if feed exists
		if( $countryID != "NULL" )
			$query  = "SELECT `id` FROM `tipsdaily` WHERE `countryID`=" . $countryID . " AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')='" . $curDay . "'";
		else
			$query  = "SELECT `id` FROM `tipsdaily` WHERE `countryID` IS NULL AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')='" . $curDay . "'";
			
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query tips daily from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `tipsdaily` (`countryID`, `tips`, `lastUpdate`) VALUES(" . $countryID . ", '" . mysqli_real_escape_string($mysqli_link,  $tips ) . "', '" . $curDay . "')";
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `tipsdaily` SET `tips`='" . mysqli_real_escape_string($mysqli_link,  $tips ) . "', `lastUpdate`='" . $curDay . "' WHERE `id`=" . $row["id"];
		}
		
		// update RSS feed
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to update tips daily in db - MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		return true;
	}
	
	
	// db insert predictions daily
	function dbInsertPredictionsDaily( $countryID, $predictions, $curDay )
	{
		global $db;
		
		if( empty( $countryID ) )
			$countryID = "NULL";
		
		// verify if feed exists
		if( $countryID != "NULL" )
			$query  = "SELECT `id` FROM `predictionsdaily` WHERE `countryID`=" . $countryID . " AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')='" . $curDay . "'";
		else
			$query  = "SELECT `id` FROM `predictionsdaily` WHERE `countryID` IS NULL AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')='" . $curDay . "'";
			
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query predictions daily from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `predictionsdaily` (`countryID`, `predictions`, `lastUpdate`) VALUES(" . $countryID . ", '" . mysqli_real_escape_string($mysqli_link,  $predictions ) . "', '" . $curDay . "')";
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `predictionsdaily` SET `predictions`='" . mysqli_real_escape_string($mysqli_link,  $predictions ) . "', `lastUpdate`='" . $curDay . "' WHERE `id`=" . $row["id"];
		}
		
		// update RSS feed
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to update predictions daily in db - MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		return true;
	}
	
	
	// db insert dropping daily
	function dbInsertDroppingDaily( $countryID, $dropping, $curDay )
	{
		global $db;
		
		if( empty( $countryID ) )
			$countryID = "NULL";
		
		// verify if feed exists
		if( $countryID != "NULL" )
			$query  = "SELECT `id` FROM `droppingodds` WHERE `countryID`=" . $countryID . " AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')='" . $curDay . "'";
		else
			$query  = "SELECT `id` FROM `droppingodds` WHERE `countryID` IS NULL AND DATE_FORMAT(`lastUpdate`, '%Y-%m-%d')='" . $curDay . "'";
			
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query dropping daily from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `droppingodds` (`countryID`, `tips`, `lastUpdate`) VALUES(" . $countryID . ", '" . mysqli_real_escape_string($mysqli_link,  $dropping ) . "', '" . $curDay . "')";
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `droppingodds` SET `tips`='" . mysqli_real_escape_string($mysqli_link,  $dropping ) . "', `lastUpdate`='" . $curDay . "' WHERE `id`=" . $row["id"];
		}
		
		// update RSS feed
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to update dropping daily in db - MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		return true;
	}
	
	
	// update feed last query
	function dbUpdateFeedLastQuery( $name, $countryID )
	{
		global $db;
		
		if( empty( $countryID ) )
			$countryID = "NULL";
		
		// verify if feed exists
		if( $countryID != "NULL" )
			$query  = "SELECT `id` FROM `feeds` WHERE `name`='" . $name . "' AND `countryID`=" . $countryID;
		else
			$query  = "SELECT `id` FROM `feeds` WHERE `name`='" . $name . "' AND `countryID` IS NULL";
			
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query feeds from db: " . $query . " MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		$date = date( "Y-m-d H:i:s", time() );
		
		// no entry
		if( mysqli_num_rows( $result ) <= 0 )
		{
			mysqli_free_result( $result );
			
			// insert RSS feed
			$query  = "INSERT INTO `feeds` (`name`, `countryID`, `value`, `lastUpdate`, `lastQuery`) VALUES('" . mysqli_real_escape_string($mysqli_link,  $name ) . "', " . $countryID . ", '', '" . $date . "', '" . $date . "')";
		}
		else
		{
			$row = mysqli_fetch_array( $result );
			mysqli_free_result( $result );
			
			// update RSS feed
			$query  = "UPDATE `feeds` SET `lastQuery`='" . $date . "' WHERE `id`=" . $row["id"];
		}
		
		// update RSS feed
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to update " . $name . " RSS feed in db - MySQL error no: " . mysqli_errno( $db ) . " MySQL error: " . mysqli_error( $db ) );
			return false;
		}
		
		return true;
	}
		
		
	// load page
	function loadPage( $url, $isPost = false, $postData = false, $referer = false, $proxy = false, $userAgent = false, $cookie = false, $isHeader = 1 )
	{
		global $userAgentList;
		
		if( is_bool( $userAgent ) )
			$userAgent = $userAgentList[ rand( 0, count( $userAgentList ) - 1 ) ];
			
		// logging
		//logApp( "Load page " . $url );
			
		// perform search
		$curl = curl_init($url);
		
		// initialize curl
		curl_setopt($curl, CURLOPT_HEADER, 				$isHeader);			
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 		1);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 		1);		
		curl_setopt($curl, CURLOPT_TIMEOUT, 			900);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 		15);				
		curl_setopt($curl, CURLOPT_USERAGENT, 			$userAgent);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 		0);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 		0);			
					
		if( !is_bool( $referer ) )
			curl_setopt($curl, CURLOPT_REFERER, 		$referer);
			
		if( !is_bool( $cookie ) )
			curl_setopt($curl, CURLOPT_COOKIE, 			$cookie);				
		
		if( $isPost )
		{
			curl_setopt($curl, CURLOPT_POST, 			1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, 		$postData);				
		}
		
		if( !is_bool( $proxy ) )
		{
			curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, 	true);
			curl_setopt($curl, CURLOPT_PROXY, 				$proxy["proxy_ip"]);
			curl_setopt($curl, CURLOPT_PROXYPORT, 			$proxy["proxy_port"]);
			
			$proxyAuth = trim( $proxy["proxy_username"] . ':' . $proxy["proxy_password"] );
			if( $proxyAuth != ':' )
				curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyAuth);
		}
		
		// get data
		$data = curl_exec($curl);
		if( is_bool( $data ) )
		{
			curl_close($curl);				
			return false;
		}
		
		$httpCode = curl_getinfo( $curl,  CURLINFO_HTTP_CODE );
		
		// close curl
		curl_close($curl);
			
		// verify if 200 OK
		if( intval( $httpCode ) != 200 )
		{
			if( intval( $httpCode ) != 404 )
				logError( "Error HTTP Code: " . $httpCode . " for URL: " . $url );
				
			return false;
		}
		
		// empty page
		if( $data == "" )
			return false;
			
		// verify if gzip
		if( !is_bool( stripos( $data, "Content-Encoding: gzip" ) ) )
		{
			$pos = strrpos( $data, "\r\n\r\n" );
			if( function_exists( "gzinflate" ) && !is_bool( $pos ) )
			{
				$data = substr( $data, $pos + 4 );
				$data = @gzinflate( substr( $data, 10, -8) );
			}
		}
		else
		// raw
		{
			$pos = strrpos( $data, "\r\n\r\n" );
			if( !is_bool( $pos ) )
				$data = substr( $data, $pos + 4 );				
		}
		
		return $data;
	}
		
		
	// log app
	function logApp( $msg )
	{
		global $logFilename;
		global $action;
		
		$msg = "[" . date("Y-m-d H:i:s", time() ) . "][CACHE " . strtoupper( $action ) . "][APP] " . $msg . "\n";
		
		@file_put_contents( $logFilename, $msg, FILE_APPEND );			
	}
	
	
	// log error
	function logError( $msg )
	{
		global $logFilename;
		global $logErrorFilename;
		global $action;		
		
		$msg = "[" . date("Y-m-d H:i:s", time() ) . "][CACHE " . strtoupper( $action ) . "][ERROR] " . $msg . "\n";
	
		//@file_put_contents( $logFilename, $msg, FILE_APPEND );
		@file_put_contents( $logErrorFilename, $msg, FILE_APPEND );			
	}
	
	
	// livescore future
	function refreshLivescoreFuture()
	{
		global $db;

		// get fixtures
		$fixtures = dbFeedGet( "fixtures" );
		if( is_bool( $fixtures ) )
		{
			logError( "Failed to query fixture feeds from db" );
			return false;
		}
		
		$today = date( "Y-m-d", time() );
		
		$dayList = array();
		
		// traverse fixtures
		while( $fixture = mysqli_fetch_array( $fixtures ) )
		{
			$leagues = parseFeed( $fixture["value"] );
			if( is_bool( $leagues ) )
				continue;
				
			//echo print_r( $leagues, 1 );
				
			// traverse leagues
			foreach( $leagues as $league )
			{
				$leagueId 		= $league["id"];
				$leagueHeader	= $league["header"];
				
				foreach( $league["matches"] as $match )
				{
					$date = $match["date"];
					if( $date == $today || strtotime( $date ) < strtotime( $today ) )
						continue;
					
					// date exists
					if( isset( $dayList[ $date ] ) )
					{
						// league exists
						if( isset( $dayList[ $date ][ $leagueId ] ) )
							array_push( $dayList[ $date ][ $leagueId ]["matches"], $match["content"] );
						else
						// new league
							$dayList[ $date ][ $leagueId ] = array( "header" => $leagueHeader, "matches" => array( $match["content"] ) );
					}
					else
					// new date
					{
						$dayList[ $date ] = array();
						$dayList[ $date ][ $leagueId ] = array( "header" => $leagueHeader, "matches" => array( $match["content"] ) );
					}
				}
			}
		}
		
		// free result
		mysqli_free_result( $fixtures );

		// save livescore
		foreach( $dayList as $date => $league )
		{
			$rss = "<livescore>";
			
			// traverse leagues
			foreach( $league as $leagueId => $leagueItem )
			{
				$rss .= $leagueItem["header"];
			
				// traverse matches
				foreach( $leagueItem["matches"] as $match )
					$rss .= $match;
				
				$rss .= "</league>";				
			}
			
			$rss .= "</livescore>";
			
			// update RSS feed
			if( !dbInsertFeedDaily( "livescore", 0, $rss, $date ) )
			{		
				logError( "Failed to update future livescore RSS feed in db for date " . $date );
				continue;
			}
		}
		
		return true;
	}
	
	// parse feed
	function parseFeed( $feed )
	{
		$pos 		= 0;
		$leagues 	= array();
		
		// search league
		while(1)
		{
			if( is_bool( $pos ) )
				break;
			
			// locate league
			$pos = strpos( $feed, "<league ", $pos );
			if( is_bool( $pos ) )
				break;
			
			// get id
			$pos2 = strpos( $feed, " id=\"", $pos );
			if( is_bool( $pos2 ) )
			{
				$pos += 8;
				continue;
			}
			
			$pos3 = strpos( $feed, "\"", $pos2 + 5 );
			if( is_bool( $pos3 ) )
			{
				$pos += 8;
				continue;
			}	
			
			$id = substr( $feed, $pos2 + 5, $pos3 - $pos2 - 5 );
			
			// get header
			$pos4 = strpos( $feed, "<match ", $pos3 );
			if( is_bool( $pos4 ) )
			{
				$pos += 8;
				continue;
			}
			
			$pos5 = strpos( $feed, "</league>", $pos3 );
			if( is_bool( $pos5 ) )
			{
				$pos += 8;
				continue;
			}
			
			if( $pos4 > $pos5 )
			{
				$pos += 8;
				continue;
			}
			
			$header = substr( $feed, $pos, $pos4 - $pos );
			$league = array( "id" => $id, "header" => $header, "matches" => array() );		
			$pos 	= $pos4;
			
			//echo "LID: " . $id . " header: " . $header . "\n";
			
			// parse matches
			while(1)
			{
				// locate match
				$pos = strpos( $feed, "<match ", $pos );
				if( is_bool( $pos ) || $pos > $pos5 )
					break;
					
				// get date
				$pos2 = strpos( $feed, " date=\"", $pos );
				if( is_bool( $pos2 ) )
				{
					$pos += 7;
					continue;
				}

				$pos3 = strpos( $feed, "\"", $pos2 + 7 );
				if( is_bool( $pos3 ) )
				{
					$pos += 7;
					continue;
				}	
				
				$date = substr( $feed, $pos2 + 7, $pos3 - $pos2 - 7 );
				
				// get content
				$pos4 = strpos( $feed, "</match>", $pos3 );
				if( is_bool( $pos4 ) )
				{
					$pos += 7;
					continue;
				}	
				
				$content = substr( $feed, $pos, $pos4 - $pos + 8 );
				$pos = $pos4 + 8;

				//echo "MDATE: " . $date . " content: " . strlen($content) . "\n";
				$date = date( "Y-m-d", strtotime( $date ) );
				
				// add match
				array_push( $league["matches"], array( "date" => $date, "content" => $content ) );				
			}
			
			// add league
			array_push( $leagues, $league );
		}
		
		return $leagues;
	}
	
	
	// sync leagues
	function syncLeagues( $nameIdx, $name, $countryID, $feed, $removePrefix )
	{
		// load xml
		$xml = simplexml_load_string( $feed );
		if( is_bool( $xml ) )
		{
			logError( "Failed to parse " . $name . " XML for country " . $countryID );
			return false;
		}
		
		$children = $xml->children();
		if( is_bool( $children ) )
		{
			logError( "No XML children " . $name . " for country " . $countryID );
			return false;
		}	
		
		// traverse children
		foreach( $children as $child ) 
		{ 
			// league
			$name = $child->getName();
			if( is_bool( $name ) || strtolower( $name ) != "league" )
				continue;
				
			$attributes = $child->attributes();
			if( is_bool( $attributes ) )
				continue;
				
			$leagueName		= "";
			$leagueCup		= "0";
			$leagueId		= "";
			$leagueSubId	= "";
			
			// traverse atributes
			foreach( $attributes as $name => $value )
			{
				$value = trim( $value );
				
				if( strtolower( $name ) == "name" )
				{
					if( $removePrefix )
					{
						$pos = stripos( $value, ": " );
						if( !is_bool( $pos ) )
							$value = substr( $value, $pos + 2 );
					}
				
					$value = trim( $value );
					$leagueName = $value;
				}
				else
				if( strtolower( $name ) == "cup" )
				{
					if( strtolower( $value ) == 1 )
						$leagueCup = 1;	
				}
				else
				if( strtolower( $name ) == "id" )
				{
					$leagueId = $value;
				}
				else
				if( strtolower( $name ) == "sub_id" )
				{
					$leagueSubId = $value;
				}
			}
			
			// insert league
			if( !empty( $leagueId ) && !empty( $leagueName ) )
			{
				if( !dbInsertLeague( $nameIdx, $countryID, $leagueName, $leagueCup, $leagueId, $leagueSubId ) )
				{
					logError( "Failed to insert league " . $leagueName . " for country " . $countryID );
					continue;	
				}
			}
		 } 		
		
		return true;
	}
	
	
	// parse match status
	function parseMatchStatus( $livescoreFeed )
	{
		// sanitize feed
		$livescoreFeed = str_replace( "á", "a", $livescoreFeed );	
		$livescoreFeed = str_replace( "é", "e", $livescoreFeed );		
		$livescoreFeed = str_replace( "í", "i", $livescoreFeed );		
		$livescoreFeed = str_replace( "ó", "o", $livescoreFeed );
		$livescoreFeed = str_replace( "ú", "u", $livescoreFeed );	
		$livescoreFeed = str_replace( "ü", "u", $livescoreFeed );		
		$livescoreFeed = str_replace( "ñ", "n", $livescoreFeed );	
		
		// parse XML
		$xml = DOMDocument::loadXML( $livescoreFeed );
		//var_dump($xml);
		
		if( false == $xml )
		{
			logError( "Failed to parse match status" );
			return false;
		}
		
		$found = 0;		

		// traverse leagues
		foreach( $xml->getElementsByTagName('league') as $league )
		{
			$leagueId = $league->getAttribute("id");
			if( empty( $leagueId ) )
				continue;
				
			$leagueName = $league->getAttribute("name");
			if( empty( $leagueName ) )
				continue;
			
			// traverse matches
			foreach( $league->getElementsByTagName('match') as $match )
			{
				$matchId = $match->getAttribute("id");
				if( empty( $matchId ) )
					continue;
					
				$matchStatus = $match->getAttribute("status");
				if( empty( $matchStatus ) )
					continue;					
				
				$team1 		= "";
				$team2 		= "";
				$scoreHT	= "";
				$goals1		= array();
				$cards1		= array();
				$goals2		= array();
				$cards2		= array();
				
				$time = $match->getAttribute("time");
				if( empty( $time ) )
					continue;
				
				$date = $match->getAttribute("date");
				if( empty( $date ) )
					continue;
					
				// home
				foreach( $match->getElementsByTagName('home') as $home )
				{
					$team1  = $home->getAttribute("name");
					$score1 = $home->getAttribute("goals");
					break;
				}
				
				// away
				foreach( $match->getElementsByTagName('away') as $away )
				{
					$team2  = $away->getAttribute("name");
					$score2 = $away->getAttribute("goals");					
					break;
				}
				
				// ht
				foreach( $match->getElementsByTagName('ht') as $ht )
				{
					$scoreHT = $ht->getAttribute("score");					
					break;
				}
				
				// events
				foreach( $match->getElementsByTagName('events') as $events )
				{
					foreach( $events->getElementsByTagName('event') as $event )
					{
						$team		= strtolower( $event->getAttribute("team") );
						$type		= $event->getAttribute("type");
						$minute 	= $event->getAttribute("minute");	
						$player  	= $event->getAttribute("player");	
						
						$item = array( "type" => $type, "minute" => $minute, "player" => $player );

						// home
						if( $team == "home" )
						{
							if( $type == "yellowcard" || $type == "redcard" )
								array_push( $cards1, $item );
							else
							if( $type == "goal" )
								array_push( $goals1, $item );
						}
						else
						// away
						if( $team == "away" )
						{
							if( $type == "yellowcard" || $type == "redcard" )
								array_push( $cards2, $item );
							else
							if( $type == "goal" )
								array_push( $goals2, $item );
						}					
					}
				}
				
				$goals1	= json_encode( $goals1 );
				$cards1	= json_encode( $cards1 );
				$goals2	= json_encode( $goals2 );
				$cards2	= json_encode( $cards2 );
				
				$scoreHT = trim( $scoreHT,  "[]" );
				
				if( empty( $team1 ) || empty( $team2 ) )
					continue;

				$pos = stripos( $matchStatus, ":" );
				$matchTime = 0;
				
				if( !is_bool( $pos ) )
				{
					$matchStatus = "scheduled";
				}
				else
				if( strtolower( $matchStatus ) == "ft" )
				{
					$matchStatus = "finished";
				}
				else
				if( is_numeric( $matchStatus ) || strtolower( $matchStatus ) == "ht" || strtolower( $matchStatus ) == "pen." )
				{
					$matchTime		= $matchStatus;				
					$matchStatus 	= "live";
				}
				else
				{
					continue;
				}
				
				$found++;				
				
				if( !dbMatchStatusAdd( $leagueId, $leagueName, $matchId, $team1, $team2, $date, $time, $score1, $score2, $scoreHT, $matchStatus, $goals1, $cards1, $goals2, $cards2, $matchTime ) )
					logError( "Failed to add match status" );			
			}
		}
		
		// logging
		logApp( "Scores found: " . $found );
		
		return true;
	}	
	
	
	// cache livescore
	function cacheLivescoreFeed()
	{
		global $db;
		global $livescoreFeedRss;
		global $livescoreFeedPause;
		
		// close db
		@mysqli_close( $db );
		
		// loop
		while(1)
		{
			// logging
			logApp( "Fetching livescore RSS feed" );
			
			// load page
			$rss = loadPage( $livescoreFeedRss );
			if( is_bool( $rss ) )
			{
				// connect to db
				$db = dbConnect();
				if( false == $db )
				{
					logError( "Failed to connect to db!" );
					return false;
				}		
			
				dbUpdateFeedLastQuery( "livescore", 0 );			
				logError( "Failed to load livescore RSS feed" );				
				
				@mysqli_close( $db );				
				sleep( $livescoreFeedPause + 1 );
				continue;
			}
			
			$saveFeed = false;
			$curMin = intval( date( "i", time() ) );
			if( $curMin % 15 == 0 )
				$saveFeed = true;
			
			// save feed to file
			if( $saveFeed )
				@file_put_contents( "/var/www/vhosts/omnibet.ro/httpdocs/log/feeds/livescore/livescore.xml", $rss );

			$today = date( "Y-m-d", time() );			
			
			// connect to db
			$db = dbConnect();
			if( false == $db )
			{
				logError( "Failed to connect to db!" );
				return false;
			}	
			
			// copy past feeds
			if( !dbFeedCopyDaily( "livescore", $today ) )
				logError( "Failed to copy past livescore in db" );
			
			// update RSS feed
			if( !dbInsertFeed( "livescore", 0, $rss ) )
			{
				dbUpdateFeedLastQuery( "livescore", 0 );			
				logError( "Failed to update livescore RSS feed in db" );
				
				@mysqli_close( $db );
				sleep( $livescoreFeedPause + 1 );
				continue;
			}
			
			// parse match status
			parseMatchStatus( $rss );
			
			// close db
			@mysqli_close( $db );
			
			// sleep
			sleep( rand( $livescoreFeedPause - 7, $livescoreFeedPause + 7 ) );	
		}
		
		// logging
		logApp( "Livescore RSS feed updated!" );
		
		return true;
	}
	
	
	// cache fixtures
	function cacheFixturesFeed()
	{
		global $db;
		global $fixturesFeedRss;	
		global $fixturesFeedPause;
		
		// get countries
		$query  = "SELECT * FROM `countries` ORDER BY RAND()";
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query countries from db: " . $query );
			return false;
		}
		
		if( mysqli_num_rows( $result ) <= 0 )
		{
			// logging
			logApp( "No country available for fixtures RSS feed!" );			
			mysqli_free_result( $result );
			return true;
		}
		
		$isUpdated = false;	
		
		// fetch country feed
		while( $country = mysqli_fetch_array( $result ) )
		{
			@mysqli_close( $db );	
			
			// connect to db
			$db = dbConnect();
			if( false == $db )
			{
				logError( "Failed to connect to db!" );
				return;
			}
				
			// is feed updated
			if( dbIsFeedUpdated( "fixtures", $country["id"], $fixturesFeedPause ) )
				continue;
			
			$url = str_replace( "<COUNTRY>", $country["feed_name"], $fixturesFeedRss );
			
			@mysqli_close( $db );	
		
			// load page
			$rss = loadPage( $url );
			if( is_bool( $rss ) )
			{
				// connect to db
				$db = dbConnect();
				if( false == $db )
				{
					logError( "Failed to connect to db!" );
					return;
				}
						
				dbUpdateFeedLastQuery( "fixtures", $country["id"] );
				//logError( "Failed to load fixtures RSS feed for " . $country["feed_name"] );
				continue;
			}
			
			// connect to db
			$db = dbConnect();
			if( false == $db )
			{
				logError( "Failed to connect to db!" );
				return;
			}
			
			// update leagues
			if( !syncLeagues( 1, "fixtures", $country["id"], $rss, 1 ) )
				logError( "Failed to sync fixtures leagues for " . $country["feed_name"] . "in db" );
			
			// update RSS feed
			if( !dbInsertFeed( "fixtures", $country["id"], $rss ) )
			{
				dbUpdateFeedLastQuery( "fixtures", $country["id"] );			
				logError( "Failed to update fixtures RSS feed for " . $country["feed_name"] . "in db" );
				continue;
			}		
			
			$isUpdated = true;
		}
		
		mysqli_free_result( $result );
		
		// verify if updated
		if( $isUpdated )
		{
			// livescore future
			if( !refreshLivescoreFuture() )
				logError( "Failed to refresh livescore future" );				
		}
		
		// logging
		logApp( "Fixtures RSS feed updated!" );
		
		return true;
	}
	
	
	// cache results
	function cacheResultsFeed()
	{
		global $db;
		global $resultsFeedRss;	
		global $resultsFeedPause;
		
		// get countries
		$query  = "SELECT * FROM `countries` ORDER BY `name` ASC";
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query countries from db: " . $query );
			return false;
		}
		
		if( mysqli_num_rows( $result ) <= 0 )
		{
			// logging
			logApp( "No country available for results RSS feed!" );			
			mysqli_free_result( $result );
			return true;
		}
		
		$time 		= time();
		$curHour 	= intval( date( "H", $time ) );
		$curMin 	= intval( date( "i", $time ) );
		
		$saveFeed = false;
		if( ( $curHour % 5 ) == 0 && ( $curMin < 15 ) )
			$saveFeed = true;
		
		// fetch country feed
		while( $country = mysqli_fetch_array( $result ) )
		{		
			// is feed updated
			if( dbIsFeedUpdated( "results", $country["id"], $resultsFeedPause ) )
				continue;
				
			$url = str_replace( "<COUNTRY>", $country["feed_name"], $resultsFeedRss );
		
			@mysqli_close( $db );	
		
			// load page
			$rss = loadPage( $url );
			if( is_bool( $rss ) )
			{
				// connect to db
				$db = dbConnect();
				if( false == $db )
				{
					logError( "Failed to connect to db!" );
					return;
				}
			
				dbUpdateFeedLastQuery( "results", $country["id"] );										
				//logError( "Failed to load results RSS feed for " . $country["feed_name"] );
				continue;
			}
			
			// save feed to file
			if( $saveFeed )
				@file_put_contents( "/var/www/vhosts/omnibet.ro/httpdocs/log/feeds/results/results-" . $country["feed_name"]. ".xml", $rss );
			
			// connect to db
			$db = dbConnect();
			if( false == $db )
			{
				logError( "Failed to connect to db!" );
				return;
			}
			
			// update leagues
			if( !syncLeagues( 2, "results", $country["id"], $rss, 0 ) )
				logError( "Failed to sync results leagues for " . $country["feed_name"] . "in db" );
		
			// update RSS feed
			if( !dbInsertFeed( "results", $country["id"], $rss ) )
			{
				dbUpdateFeedLastQuery( "results", $country["id"] );					
				logError( "Failed to update results RSS feed for " . $country["feed_name"] . "in db" );
				continue;
			}		
		}
		
		mysqli_free_result( $result );
		
		// logging
		logApp( "Results RSS feed updated!" );
		
		return true;
	}
	
	
	// cache standings
	function cacheStandingsFeed()
	{
		global $db;
		global $standingsFeedRss;	
		global $standingsFeedPause;
		
		// get countries
		$query  = "SELECT * FROM `countries` ORDER BY `name` ASC";
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query countries from db: " . $query );
			return false;
		}
		
		if( mysqli_num_rows( $result ) <= 0 )
		{
			// logging
			logApp( "No country available for standings RSS feed!" );			
			mysqli_free_result( $result );
			return true;
		}	
		
		// fetch country feed
		while( $country = mysqli_fetch_array( $result ) )
		{
			// is feed updated
			if( dbIsFeedUpdated( "standings", $country["id"], $standingsFeedPause ) )
				continue;
					
			if( $country["name"] == "Netherlands" )
				$country["feed_name"] = "netherlands";
				
			$url = str_replace( "<COUNTRY>", $country["feed_name"], $standingsFeedRss );
		
			@mysqli_close( $db );	
		
			// load page
			$rss = loadPage( $url );
			if( is_bool( $rss ) )
			{
				// connect to db
				$db = dbConnect();
				if( false == $db )
				{
					logError( "Failed to connect to db!" );
					return;
				}
				
				dbUpdateFeedLastQuery( "standings", $country["id"] );					
				//logError( "Failed to load standings RSS feed for " . $country["feed_name"] );
				continue;
			}
				
			// connect to db
			$db = dbConnect();
			if( false == $db )
			{
				logError( "Failed to connect to db!" );
				return;
			}
			
			// update leagues
			if( !syncLeagues( 3, "standings", $country["id"], $rss, 0 ) )
				logError( "Failed to sync standings leagues for " . $country["feed_name"] . "in db" );
			
			// update RSS feed
			if( !dbInsertFeed( "standings", $country["id"], $rss ) )
			{
				dbUpdateFeedLastQuery( "standings", $country["id"] );				
				logError( "Failed to update standings RSS feed for " . $country["feed_name"] . "in db" );
				continue;
			}		
		}
		
		mysqli_free_result( $result );
		
		// logging
		logApp( "Standings RSS feed updated!" );
		
		return true;
	}
	
	
	// scorers standings
	function cacheScorersFeed()
	{
		global $db;
		global $scorersFeedRss;
		global $scorersFeedPause;
		
		// get countries
		$query  = "SELECT * FROM `countries` ORDER BY `name` ASC";
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query countries from db: " . $query );
			return false;
		}
		
		if( mysqli_num_rows( $result ) <= 0 )
		{
			// logging
			logApp( "No country available for scorers RSS feed!" );			
			mysqli_free_result( $result );
			return true;
		}
		
		// fetch country feed
		while( $country = mysqli_fetch_array( $result ) )
		{
			// is feed updated
			if( dbIsFeedUpdated( "scorers", $country["id"], $scorersFeedPause ) )
				continue;
				
			$url = str_replace( "<COUNTRY>", $country["feed_name"], $scorersFeedRss );
		
			@mysqli_close( $db );		
			
			// load page
			$rss = loadPage( $url );
			if( is_bool( $rss ) )
			{
				// connect to db
				$db = dbConnect();
				if( false == $db )
				{
					logError( "Failed to connect to db!" );
					return;
				}
				
				dbUpdateFeedLastQuery( "scorers", $country["id"] );		
				//logError( "Failed to load scorers RSS feed for " . $country["feed_name"] );
				continue;
			}
				
			// connect to db
			$db = dbConnect();
			if( false == $db )
			{
				logError( "Failed to connect to db!" );
				return;
			}
			
			// update leagues
			if( !syncLeagues( 4, "scorers", $country["id"], $rss, 0 ) )
				logError( "Failed to sync scorers leagues for " . $country["feed_name"] . "in db" );
				
			// update RSS feed
			if( !dbInsertFeed( "scorers", $country["id"], $rss ) )
			{
				dbUpdateFeedLastQuery( "scorers", $country["id"] );					
				logError( "Failed to update scorers RSS feed for " . $country["feed_name"] . "in db" );
				continue;
			}			
		}
		
		mysqli_free_result( $result );
		
		// logging
		logApp( "Scorers RSS feed updated!" );
		
		return true;
	}
	
	
	// get tips
	function getTips( $country, $oddFeed, $bookers )
	{
		// global $minOdds;
		global $favBookmaker;
		
		$tips = array( "home" => array(), "away" => array() );
		
		
		// parse XML
		$xml = @simplexml_load_string( $oddFeed );
		if( is_bool( $xml ) )
		{
			logError( "Failed to parse odds feed for country " . $country );	
			return false;
		}
		
		$json = @json_encode( $xml );
		if( is_bool( $json ) )
		{
			logError( "Failed to JSON encode odds feed for country " . $country );	
			return false;
		}
		
		$result = @json_decode( $json, true );
		if( is_bool( $result ) )
		{
			logError( "Failed to JSON decode odds feed for country " . $country );	
			return false;
		}
			
		if( !isset( $result["league"] ) || !is_array( $result["league"] ) )
			return $tips;
			
		$today 		= date( "d.m.Y", time() );
		$yesterday	= date( "d.m.Y", strtotime( "-1 day" ) );
		$tomorrow	= date( "d.m.Y", strtotime( "+1 day" ) );
		
		// traverse leagues
		foreach( $result["league"] as $league )
		{
			if( !isset( $league["@attributes"]["id"] ) || !isset( $league["@attributes"]["name"] ) )
				continue;
				
			// id
			$leagueId = $league["@attributes"]["id"];
			if( empty( $leagueId ) )
				continue;
				
			// name
			$leagueName = $league["@attributes"]["name"];
			if( empty( $leagueName ) )
				continue;
			
			if( !isset( $league["match"] ) || !is_array( $league["match"] ) )
				continue;
			
			// traverse matches
			foreach( $league["match"] as $match )
			{
				if( !isset( $match["@attributes"]["id"] ) || !isset( $match["@attributes"]["date"] ) || !isset( $match["@attributes"]["time"] ) )
					continue;
					
				$matchId = $match["@attributes"]["id"];
				if( empty( $matchId ) )
					continue;
					
				$date = $match["@attributes"]["date"];
				if( empty( $date ) )
					continue;		

				if( $date != $today && $date != $tomorrow && $date != $yesterday )
					continue;
				
				$team1 = "";
				$team2 = "";
				
				$time = $match["@attributes"]["time"];
				if( empty( $time ) )
					continue;
				
				// home
				if( isset( $match["home"] ) && isset( $match["home"]["@attributes"] ) && isset( $match["home"]["@attributes"]["name"] ) )
					$team1 = $match["home"]["@attributes"]["name"];
				
				// away
				if( isset( $match["away"] ) && isset( $match["away"]["@attributes"] ) && isset( $match["away"]["@attributes"]["name"] ) )
					$team2 = $match["away"]["@attributes"]["name"];
				
				if( empty( $team1 ) || empty( $team2 ) )
					continue;
				
				if( !isset( $match["odds"]["type"] ) || !is_array( $match["odds"]["type"] ) )
					continue;
					
				// odds
				if( count( $match["odds"]["type"] ) > 0 )
				{
					// type
					foreach( $match["odds"]["type"] as $type )
					{
						if( !isset( $type["@attributes"]["name"] ) )
							continue;
				
						$oddName = $type["@attributes"]["name"];
						if( $oddName != "1x2" )
							continue;
						
						if( !isset( $type["bookmaker"] ) || !is_array( $type["bookmaker"] ) )
							continue;
						
						// bookmaker
						foreach( $type["bookmaker"] as $bookmaker )
						{
							if( !isset( $bookmaker["@attributes"]["name"] ) )
								continue;
						
							$bookerName = $bookmaker["@attributes"]["name"];
							if( strtolower( $bookerName ) != strtolower( $favBookmaker ) )							
								continue;						
							
							if( !isset( $bookmaker["odd"] ) || !is_array( $bookmaker["odd"] ) )
								continue;
								
							// get affiliate link
							$affiliateLink = "";
							foreach( $bookers as $booker )
								if( strtolower( $booker["name"] ) == strtolower( $bookerName ) )
								{
									$affiliateLink = $booker["affiliateLink"];
									break;
								}
								
							if( empty( $affiliateLink ) )
							{
								//echo "no affiliate link " . $bookerName . "\n";
								continue;
							}
							
							$odd1 = "";
							$odd2 = "";
							
							// odd
							foreach( $bookmaker["odd"] as $odd )
							{
								if( !isset( $odd["@attributes"]["name"] ) || !isset( $odd["@attributes"]["value"] ) )
									continue;
								
								$oddName 	= $odd["@attributes"]["name"];
								$oddValue 	= $odd["@attributes"]["value"];
								
								if( $oddName == "1" || strtolower( $oddName ) == "home" )
									$odd1 = $oddValue;
								else
								if( $oddName == "2" || strtolower( $oddName ) == "away" )
									$odd2 = $oddValue;							
							}
							
							// odd set
							if( !empty( $odd1 ) && !empty( $odd2 ) )
							{
								$odd1 = floatval( $odd1 );
								$odd2 = floatval( $odd2 );
								
								if( $odd1 > 2.1 && $odd2 > 2.1 )
									continue;
								
								// get score
								$matchInfo = getMatchScore( $leagueId, $matchId );
								if( is_bool( $matchInfo ) )
								{
									dbMatchStatusAdd( $leagueId, $leagueName, $matchId, $team1, $team2, $date, $time, "?", "?", "", "scheduled", "", "", "", "", "" );
									$matchInfo = array( "status" => "scheduled", "score1" => "", "score2" => "" );
								}
								
								if( $odd1 < $odd2 )
									array_push( $tips["home"], array( "league" => $leagueName, "team1" => $team1, "team2" => $team2, "booker" => $bookerName, "odd" => $odd1, "odd2" => $odd2, "date" => $date, "time" => $time, "affiliateLink" => $affiliateLink, "status" => $matchInfo["status"], "score1" => $matchInfo["score1"], "score2" => $matchInfo["score2"] ) );
								else
									array_push( $tips["away"], array( "league" => $leagueName, "team1" => $team1, "team2" => $team2, "booker" => $bookerName, "odd" => $odd2, "odd2" => $odd1, "date" => $date, "time" => $time, "affiliateLink" => $affiliateLink, "status" => $matchInfo["status"], "score1" => $matchInfo["score1"], "score2" => $matchInfo["score2"] ) );							
							}
						}
					}
				}
			}
		}
		
		return $tips;
	}	
	
	
	// get predictions
	function getPredictions( $country, $oddFeed, $bookers )
	{
		global $favBookmaker;
		
		$predictions = array();
		
		// parse XML
		$xml = @simplexml_load_string( $oddFeed );
		if( is_bool( $xml ) )
		{
			logError( "Failed to parse odds feed for country " . $country );	
			return false;
		}
		
		$json = @json_encode( $xml );
		if( is_bool( $json ) )
		{
			logError( "Failed to JSON encode odds feed for country " . $country );	
			return false;
		}
		
		$result = @json_decode( $json, true );
		if( is_bool( $result ) )
		{
			logError( "Failed to JSON decode odds feed for country " . $country );	
			return false;
		}
			
		if( !isset( $result["league"] ) || !is_array( $result["league"] ) )
			return $predictions;
			
		if( !isset( $result["league"][0] ) )
			$result["league"] = array( $result["league"] );			
		
		// traverse leagues
		foreach( $result["league"] as $league )
		{
			if( !isset( $league["@attributes"]["id"] ) || !isset( $league["@attributes"]["name"] ) )
				continue;
				
			// id
			$leagueId = $league["@attributes"]["id"];
			if( empty( $leagueId ) )
				continue;
				
			// name
			$leagueName = $league["@attributes"]["name"];
			if( empty( $leagueName ) )
				continue;
			
			if( !isset( $league["match"] ) || !is_array( $league["match"] ) )
				continue;
			
			$matchList = array();
			
			// traverse matches
			foreach( $league["match"] as $match )
			{
				if( !isset( $match["@attributes"]["id"] ) || !isset( $match["@attributes"]["date"] ) || !isset( $match["@attributes"]["time"] ) )
					continue;
				
				// match id
				$matchId = $match["@attributes"]["id"];
				if( empty( $matchId ) )
					continue;
				
				// date
				$date = $match["@attributes"]["date"];
				if( empty( $date ) )
					continue;		
				
				// time
				$time = $match["@attributes"]["time"];
				if( empty( $time ) )
					continue;
				
				$team1 = "";
				$team2 = "";
				
				// home
				if( isset( $match["home"] ) && isset( $match["home"]["@attributes"] ) && isset( $match["home"]["@attributes"]["name"] ) )
					$team1 = $match["home"]["@attributes"]["name"];
				
				// away
				if( isset( $match["away"] ) && isset( $match["away"]["@attributes"] ) && isset( $match["away"]["@attributes"]["name"] ) )
					$team2 = $match["away"]["@attributes"]["name"];
				
				if( empty( $team1 ) || empty( $team2 ) )
					continue;
				
				if( !isset( $match["odds"]["type"] ) || !is_array( $match["odds"]["type"] ) )
					continue;
				
				$odds1x2 	= array( false, false, false );
				$oddsHA		= array( false );				
				$oddsDC		= array( false, false, false );
				$odds1x2FH	= array( false, false, false );
				
				// odds
				if( count( $match["odds"]["type"] ) > 0 )
				{
					// odd type
					foreach( $match["odds"]["type"] as $type )
					{		
						if( !isset( $type["@attributes"]["name"] ) )
							continue;
				
						$oddName = $type["@attributes"]["name"];
						
						if( !isset( $type["bookmaker"] ) || !is_array( $type["bookmaker"] ) )
							continue;
						
						// 1x2
						if( $oddName == "1x2" )
						{
							// bookmaker
							foreach( $type["bookmaker"] as $bookmaker )
							{		
								if( !isset( $bookmaker["@attributes"]["name"] ) )
									continue;
							
								$bookerName = $bookmaker["@attributes"]["name"];
								if( strtolower( $bookerName ) != strtolower( $favBookmaker ) )							
									continue;						
								
								if( !isset( $bookmaker["odd"] ) || !is_array( $bookmaker["odd"] ) )
									continue;
							
								// odd
								foreach( $bookmaker["odd"] as $odd )
								{
									if( !isset( $odd["@attributes"]["name"] ) || !isset( $odd["@attributes"]["value"] ) )
										continue;
									
									$oddName 	= $odd["@attributes"]["name"];
									$oddValue 	= $odd["@attributes"]["value"];
									
									if( $oddName == "1" || strtolower( $oddName ) == "home" )
										$odds1x2[0] = $oddValue;
									else
									if( $oddName == "X" || strtolower( $oddName ) == "draw" )
										$odds1x2[1] = $oddValue;											
									else
									if( $oddName == "2" || strtolower( $oddName ) == "away" )
										$odds1x2[2] = $oddValue;											
								}
								
								break;
							}
						}
						else
						// Home/Away
						if( $oddName == "Home/Away" )						
						{
							// bookmaker
							foreach( $type["bookmaker"] as $bookmaker )
							{		
								if( !isset( $bookmaker["@attributes"]["name"] ) )
									continue;
							
								$bookerName = $bookmaker["@attributes"]["name"];
								if( strtolower( $bookerName ) != strtolower( $favBookmaker ) )							
									continue;						
								
								if( !isset( $bookmaker["odd"] ) || !is_array( $bookmaker["odd"] ) )
									continue;				
								
								// odd
								foreach( $bookmaker["odd"] as $odd )
								{
									if( !isset( $odd["@attributes"]["name"] ) || !isset( $odd["@attributes"]["value"] ) )
										continue;
									
									$oddName 	= $odd["@attributes"]["name"];
									$oddValue 	= $odd["@attributes"]["value"];
									
									if( $oddName == "1" || strtolower( $oddName ) == "home" )
										$oddsHA[0] = $oddValue;
									else
									if( $oddName == "2" || strtolower( $oddName ) == "away" )
										$oddsHA[1] = $oddValue;											
								}
								
								break;
							}							
						}						
						else
						// Double Chance
						if( $oddName == "Double Chance" )						
						{
							// bookmaker
							foreach( $type["bookmaker"] as $bookmaker )
							{		
								if( !isset( $bookmaker["@attributes"]["name"] ) )
									continue;
							
								$bookerName = $bookmaker["@attributes"]["name"];
								if( strtolower( $bookerName ) != strtolower( $favBookmaker ) )							
									continue;						
								
								if( !isset( $bookmaker["odd"] ) || !is_array( $bookmaker["odd"] ) )
									continue;						
								
								// odd
								foreach( $bookmaker["odd"] as $odd )
								{
									if( !isset( $odd["@attributes"]["name"] ) || !isset( $odd["@attributes"]["value"] ) )
										continue;
									
									$oddName 	= $odd["@attributes"]["name"];
									$oddValue 	= $odd["@attributes"]["value"];
									
									if( $oddName == "1X" || strtolower( $oddName ) == "home/draw" )
										$oddsDC[0] = $oddValue;
									else
									if( $oddName == "12" || strtolower( $oddName ) == "home/away" )
										$oddsDC[1] = $oddValue;												
									else
									if( $oddName == "X2" || strtolower( $oddName ) == "draw/away" )
										$oddsDC[2] = $oddValue;										
								}
								
								break;
							}							
						}
						else
						// 1x2 1st Half
						if( $oddName == "1x2 1st Half" )						
						{
							// bookmaker
							foreach( $type["bookmaker"] as $bookmaker )
							{		
								if( !isset( $bookmaker["@attributes"]["name"] ) )
									continue;
							
								$bookerName = $bookmaker["@attributes"]["name"];
								if( strtolower( $bookerName ) != strtolower( $favBookmaker ) )							
									continue;						
								
								if( !isset( $bookmaker["odd"] ) || !is_array( $bookmaker["odd"] ) )
									continue;							
								
								// odd
								foreach( $bookmaker["odd"] as $odd )
								{
									if( !isset( $odd["@attributes"]["name"] ) || !isset( $odd["@attributes"]["value"] ) )
										continue;
									
									$oddName 	= $odd["@attributes"]["name"];
									$oddValue 	= $odd["@attributes"]["value"];
									
									if( $oddName == "1" || strtolower( $oddName ) == "home" )
										$odds1x2FH[0] = $oddValue;
									else
									if( $oddName == "X" || strtolower( $oddName ) == "draw" )
										$odds1x2FH[1] = $oddValue;												
									else
									if( $oddName == "2" || strtolower( $oddName ) == "away" )
										$odds1x2FH[2] = $oddValue;										
								}
								
								break;
							}							
						}						
					}
				}
				
				// must have 1x2
				if( is_bool( $odds1x2[0] ) || is_bool( $odds1x2[1] ) || is_bool( $odds1x2[2] ) )
					continue;
					
				$sum = floatval( $odds1x2[0] ) + floatval( $odds1x2[1] ) + floatval( $odds1x2[2] );				
					
				// home chance
				$homeChance = 0;
				if( $sum != 0 )
					$homeChance = round( (floatval( $odds1x2[2] ) * 100 )/$sum );
					
				// away chance
				$awayChance = 0;
				if( $sum != 0 )
					$awayChance = round( (floatval( $odds1x2[0] ) * 100 )/$sum );
				
				// ratios
				$homeRatio = convertOddsToRatio( $odds1x2[0] );
				$awayRatio = convertOddsToRatio( $odds1x2[2] );
				$drawRatio = convertOddsToRatio( $odds1x2[1] );
				
				if( floatval( $odds1x2[0] ) <= 1.35 )
				{
					$prediction = "HOME WIN";
				}
				else
				if( floatval( $odds1x2[2] ) <= 1.35 )
				{
					$prediction = "AWAY WIN";
				}
				else
				{
					// get match prediction
					$prediction = getMatchPrediction( $odds1x2, $oddsDC, $oddsHA, $odds1x2FH );
				}
				
				// add match status
				$matchStatus = getMatchScore( $leagueId, $matchId );
				if( is_bool( $matchStatus ) )
					dbMatchStatusAdd( $leagueId, $leagueName, $matchId, $team1, $team2, $date, $time, "?", "?", "", "scheduled", "", "", "", "", "" );
				
				// add match
				array_push( $matchList, array( "matchId" => $matchId, "date" => $date, "time" => $time, "team1" => $team1, "team2" => $team2,
											   "homeChance" => $homeChance, "awayChance" => $awayChance, "homeOdds" => $odds1x2[0], "awayOdds" => $odds1x2[2], 
											   "drawOdds" => $odds1x2[1], "homeRatio" => $homeRatio, "awayRatio" => $awayRatio,
											   "drawRatio" => $drawRatio, "prediction" => $prediction ) );
			}
			
			// add league
			if( !empty( $matchList ) )
				array_push( $predictions, array( "leagueId" => $leagueId, "leagueName" => $leagueName, "matchList" => $matchList ) );
		}
		
		return $predictions;
	}	
	
	
	// get dropping
	function getDropping( $country, $oddFeed )
	{
		global $favBookmaker;
		global $debugEnabled;
		
		$tips = array();
		
		if( $debugEnabled )
			echo "parse1\n";
		
		// parse XML
		$xml = @simplexml_load_string( $oddFeed );
		if( is_bool( $xml ) )
		{
			logError( "Failed to parse odds feed for country " . $country );	
			return false;
		}
		
		if( $debugEnabled )
			echo "parse2\n";
		
		$json = @json_encode( $xml );
		if( is_bool( $json ) )
		{
			logError( "Failed to JSON encode odds feed for country " . $country );	
			return false;
		}
		
		if( $debugEnabled )
			echo "parse3\n";
		
		//file_put_contents( "log/drop" . rand() . ".json", $json );
		
		$result = @json_decode( $json, true );
		if( is_bool( $result ) )
		{
			logError( "Failed to JSON decode odds feed for country " . $country );	
			return false;
		}
		
		if( $debugEnabled )
			echo "parse4\n";		
			
		if( !isset( $result["league"] ) || !is_array( $result["league"] ) )
			return $tips;
		
		if( !isset( $result["league"][0] ) )
			$result["league"] = array( $result["league"] );		
			
		// traverse leagues
		foreach( $result["league"] as $league )
		{
			if( !isset( $league["@attributes"]["id"] ) || !isset( $league["@attributes"]["name"] ) )
				continue;
				
			// id
			$leagueId = $league["@attributes"]["id"];
			if( empty( $leagueId ) )
				continue;
				
			// name
			$leagueName = $league["@attributes"]["name"];
			if( empty( $leagueName ) )
				continue;
			
			if( !isset( $league["match"] ) || !is_array( $league["match"] ) )
				continue;
				
			if( $debugEnabled )
				echo $leagueName . "\n";
			
			$matchList = array();
			
			// traverse matches
			foreach( $league["match"] as $match )
			{
				if( !isset( $match["@attributes"]["id"] ) || !isset( $match["@attributes"]["date"] ) || !isset( $match["@attributes"]["time"] ) )
					continue;
					
				$matchId = $match["@attributes"]["id"];
				if( empty( $matchId ) )
					continue;
					
				$date = $match["@attributes"]["date"];
				if( empty( $date ) )
					continue;
				
				$team1 = "";
				$team2 = "";
				
				$time = $match["@attributes"]["time"];
				if( empty( $time ) )
					continue;
				
				// home
				if( isset( $match["home"] ) && isset( $match["home"]["@attributes"] ) && isset( $match["home"]["@attributes"]["name"] ) )
					$team1 = $match["home"]["@attributes"]["name"];
				
				// away
				if( isset( $match["away"] ) && isset( $match["away"]["@attributes"] ) && isset( $match["away"]["@attributes"]["name"] ) )
					$team2 = $match["away"]["@attributes"]["name"];
				
				if( empty( $team1 ) || empty( $team2 ) )
					continue;
				
				if( !isset( $match["odds"]["type"] ) || !is_array( $match["odds"]["type"] ) )
					continue;
					
				// odds
				if( count( $match["odds"]["type"] ) > 0 )
				{
					// type
					foreach( $match["odds"]["type"] as $type )
					{		
						if( !isset( $type["@attributes"]["name"] ) )
							continue;
				
						$oddName = $type["@attributes"]["name"];
						if( $oddName != "1x2" )
							continue;
							
						if( $debugEnabled )
							echo "1x2\n";
						
						if( !isset( $type["bookmaker"] ) || !is_array( $type["bookmaker"] ) )
							continue;
						
						// bookmaker
						foreach( $type["bookmaker"] as $bookmaker )
						{		
							if( !isset( $bookmaker["@attributes"]["name"] ) )
							{
								if( $debugEnabled )
									echo "not set\n";
								continue;
							}
						
							// verify booker
							$bookerName = $bookmaker["@attributes"]["name"];
							
							// allowed bookers
							if( !in_array( strtolower( $bookerName ), array( strtolower( $favBookmaker ), "william hill", "bet365", "marathon", "unibet" ) ) )
								continue;							
							
							$isFavBookmaker = false;
							
							// favourite booker
							if( strtolower( $bookerName ) == strtolower( $favBookmaker ) )
								$isFavBookmaker = true;

							if( $debugEnabled )
								echo $favBookmaker . "\n";
							
							if( !isset( $bookmaker["odd"] ) || !is_array( $bookmaker["odd"] ) )
								continue;
							
							$odd1 = "";
							$odd2 = "";
							$oddX = "";
							
							// odd
							foreach( $bookmaker["odd"] as $odd )
							{
								if( !isset( $odd["@attributes"]["name"] ) || !isset( $odd["@attributes"]["value"] ) )
									continue;
								
								$oddName 	= $odd["@attributes"]["name"];
								$oddValue 	= $odd["@attributes"]["value"];
								
								if( $oddName == "1" || strtolower( $oddName ) == "home" )
									$odd1 = $oddValue;
								else
								if( $oddName == "X" || strtolower( $oddName ) == "draw" )
									$oddX = $oddValue;	
								else
								if( $oddName == "2" || strtolower( $oddName ) == "away" )
									$odd2 = $oddValue;													
							}
							
							// odd set
							if( !empty( $odd1 ) && !empty( $odd2 ) && !empty( $oddX ) )
							{
								$odd1 		= floatval( $odd1 );
								$odd2 		= floatval( $odd2 );
								$oddX 		= floatval( $oddX );
								$odd1Prev	= 0;
								$odd2Prev	= 0;
								$oddXPrev	= 0;
								
								if( $debugEnabled )
									echo "ODDS: " . $odd1 . " " . $odd2 . " " . $oddX . "\n";
								
								$high1	= 0;
								$highX	= 0;
								$high2	= 0;
								$dir1	= "";
								$dirX	= "";
								$dir2	= "";
								
								// get odds
								if( $isFavBookmaker )
									$oddInfo = getOddStatus( $leagueId, $matchId );
								else
									$oddInfo = getOddStatus2( $leagueId, $matchId, $bookerName );									
								
								if( !is_bool( $oddInfo ) )
								{
									$odd1Prev = $oddInfo["odds1"];
									$odd2Prev = $oddInfo["odds2"];
									$oddXPrev = $oddInfo["oddsX"];
									
									if( $debugEnabled )
										echo "PREV: " . $odd1Prev . " " . $odd2Prev . " " . $oddXPrev . "\n";
								
									if( $odd1Prev != $odd1 )
									{
										if( $odd1Prev < $odd1 )
											$dir1 = "up";
										else
										if( $odd1Prev > $odd1 )
											$dir1 = "down";								
											
										if( abs( $odd1Prev - $odd1 ) > 1 )
											$high1 = 1;
									}
									
									if( $odd2Prev != $odd2 )
									{
										if( $odd2Prev < $odd2 )
											$dir2 = "up";
										else
										if( $odd2Prev > $odd2 )
											$dir2 = "down";								
											
										if( abs( $odd2Prev - $odd2 ) > 1 )
											$high2 = 1;
									}									
									
									if( $oddXPrev != $oddX )
									{
										if( $oddXPrev < $oddX )
											$dirX = "up";
										else
										if( $oddXPrev > $oddX )
											$dirX = "down";								
											
										if( abs( $oddXPrev - $oddX ) > 1 )
											$highX = 1;
									}
									
									if( !empty( $high1 ) || !empty( $high2 ) || !empty( $highX ) || !empty( $dir1 ) || !empty( $dir2 ) || !empty( $dirX ) )
									{
										if( $isFavBookmaker )
											dbOddStatusAdd( $leagueId, $leagueName, $matchId, $bookerName, $team1, $team2, $date, $time, $odd1, $odd2, $oddX, $high1, $high2, $highX, $dir1, $dir2, $dirX );
										else
											dbOddStatusAdd2( $leagueId, $leagueName, $matchId, $bookerName, $team1, $team2, $date, $time, $odd1, $odd2, $oddX, $high1, $high2, $highX, $dir1, $dir2, $dirX );
									}
								}
								else
								{
									if( $isFavBookmaker )									
										dbOddStatusAdd( $leagueId, $leagueName, $matchId, $bookerName, $team1, $team2, $date, $time, $odd1, $odd2, $oddX, $high1, $high2, $highX, $dir1, $dir2, $dirX );
									else
										dbOddStatusAdd2( $leagueId, $leagueName, $matchId, $bookerName, $team1, $team2, $date, $time, $odd1, $odd2, $oddX, $high1, $high2, $highX, $dir1, $dir2, $dirX );										
								}
								
								// add match
								if( $isFavBookmaker )
								{
									if( !empty( $high1 ) || !empty( $high2 ) || !empty( $highX ) || !empty( $dir1 ) || !empty( $dir2 ) || !empty( $dirX ) )
									{								
										array_push( $matchList, array( "matchId" => $matchId, "date" => $date, "time" => $time, "team1" => $team1, "team2" => $team2,
																	   "odds1" => $odd1Prev, "oddsX" => $oddXPrev, "odds2" => $odd2Prev,
																	   "odds1Latest" => $odd1, "oddsXLatest" => $oddX, "odds2Latest" => $odd2,
																	   "high1" => $high1, "highX" => $highX, "high2" => $high2, "dir1" => $dir1, "dirX" => $dirX, "dir2" => $dir2 ) );
									}
								}
							}
						}
					}
				}
			}
						
			// add league
			if( !empty( $matchList ) )
				array_push( $tips, array( "leagueId" => $leagueId, "leagueName" => $leagueName, "matchList" => $matchList ) );							
		}
		
		return $tips;
	}	
	
	
	// convert odds to ratios
	function convertOddsToRatio( $odds )
	{
		if( round( $odds ) == $odds )
			return $odds;
			
		$odds  = round( $odds * 100 );
		$term2 = 100;
		
		if( $odds % 2 == 0 )
		{
			$odds  = $odds/2;
			$term2 = $term2/2;
		}
		
		if( $odds % 2 == 0 )
		{
			$odds  = $odds/2;
			$term2 = $term2/2;
		}

		if( $odds % 5 == 0 )
		{
			$odds  = $odds/5;
			$term2 = $term2/5;
		}	

		if( $odds % 5 == 0 )
		{
			$odds  = $odds/5;
			$term2 = $term2/5;
		}

		return strval( round( $odds ) ) . "/" . strval( round( $term2 ) );
	}
	
	
	// get match prediction
	function getMatchPrediction( $odds1x2, $oddsDC, $oddsDNB, $oddsWEH )
	{
		$type 	= "AVOID";
		$chance = false;
		$thres1	= 2.1;
		$thres2	= 1.7;
	
		// DC
		if( !is_bool( $oddsDC[0] ) && !is_bool( $oddsDC[1] ) && !is_bool( $oddsDC[2] ) )
		{
			if( floatval( $oddsDC[0] ) <= $thres1 || floatval( $oddsDC[2] ) <= $thres1 )
			{
				$sum = floatval( $oddsDC[0] ) + floatval( $oddsDC[1] ) + floatval( $oddsDC[2] );
				if( $sum != 0 )
				{
					if( floatval( $oddsDC[0] ) < floatval( $oddsDC[2] ) )
					{
						$val = round( floatval( $oddsDC[2] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "HOME DC";
							$chance	= $val;
						}
					}
					else
					{
						$val = round( floatval( $oddsDC[0] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "AWAY DC";
							$chance	= $val;
						}				
					}
				}
			}
		}
	
		if( $type != "AVOID" )
			return $type;
		// DNB
		if( !is_bool( $oddsDNB[0] ) && !is_bool( $oddsDNB[1] ) )
		{
			if( floatval( $oddsDNB[0] ) < $thres2 || floatval( $oddsDNB[1] ) < $thres2 )
			{		
				$sum = floatval( $oddsDNB[0] ) + floatval( $oddsDNB[1] );
				if( $sum != 0 )
				{
					if( floatval( $oddsDNB[0] ) < floatval( $oddsDNB[1] ) )
					{
						$val = round( floatval( $oddsDNB[1] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "HOME DNB";
							$chance	= $val;
						}
					}
					else
					{
						$val = round( floatval( $oddsDNB[0] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "AWAY DNB";
							$chance	= $val;
						}				
					}
				}
			}
		}
		
		if( $type != "AVOID" )
			return $type;
		// WEH
		if( !is_bool( $oddsWEH[0] ) && !is_bool( $oddsWEH[1] ) && !is_bool( $oddsWEH[2] ) )
		{
			if( floatval( $oddsWEH[0] ) < $thres2 || floatval( $oddsWEH[2] ) < $thres2 )
			{			
				$sum = floatval( $oddsWEH[0] ) + floatval( $oddsWEH[1] ) + floatval( $oddsWEH[2] );
				if( $sum != 0 )
				{
					if( floatval( $oddsWEH[0] ) < floatval( $oddsWEH[2] ) )
					{
						$val = round( floatval( $oddsWEH[2] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "HOME WEH";
							$chance	= $val;
						}
					}
					else
					{
						$val = round( floatval( $oddsWEH[0] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "AWAY WEH";
							$chance	= $val;
						}				
					}
				}
			}
		}
		
		if( $type != "AVOID" )
			return $type;
		// WIN
		if( !is_bool( $odds1x2[0] ) && !is_bool( $odds1x2[1] ) && !is_bool( $odds1x2[2] ) )
		{
			if( floatval( $odds1x2[0] ) < $thres2 || floatval( $odds1x2[2] ) < $thres2 )
			{			
				$sum = floatval( $odds1x2[0] ) + floatval( $odds1x2[1] ) + floatval( $odds1x2[2] );
				if( $sum != 0 )
				{
					if( floatval( $odds1x2[0] ) < floatval( $odds1x2[2] ) )
					{
						$val = round( floatval( $odds1x2[2] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "HOME WIN";
							$chance	= $val;
						}
					}
					else
					{
						$val = round( floatval( $odds1x2[0] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "AWAY WIN";
							$chance	= $val;
						}				
					}
				}
			}
		}		
		
		if( $type != "AVOID" )
			return $type;
		// WIN
		if( !is_bool( $odds1x2[0] ) && !is_bool( $odds1x2[1] ) && !is_bool( $odds1x2[2] ) )
		{
			if( floatval( $odds1x2[0] ) <= $thres1 || floatval( $odds1x2[2] ) <= $thres1 )
			{			
				$sum = floatval( $odds1x2[0] ) + floatval( $odds1x2[1] ) + floatval( $odds1x2[2] );
				if( $sum != 0 )
				{
					if( floatval( $odds1x2[0] ) < floatval( $odds1x2[2] ) )
					{
						$val = round( floatval( $odds1x2[2] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "HOME DC";
							$chance	= $val;
						}
					}
					else
					{
						$val = round( floatval( $odds1x2[0] ) * 100 )/$sum;
						if( is_bool( $chance ) || $val > $chance )
						{
							$type 	= "AWAY DC";
							$chance	= $val;
						}				
					}
				}
			}
		}	
		
		return $type;
	}
	
	
	// cache odds
	function cacheOddsFeed()
	{
		global $db;
		global $oddsFeedRss;
		global $oddsFeedPause;
		global $debugEnabled;
		
		// bookers
		$query = "SELECT A.`name`, A.`affiliateLink` FROM `bookers` A WHERE A.`affiliateLink`<>'' ORDER BY A.`id` ASC";
		$result = @mysqli_query($db,  $query);
		if( is_bool( $result ) )
		{
			// logging
			logError( "Failed to query countries from db: " . $query );
			return false;
		}
		
		$bookers = array();
		while( $row = mysqli_fetch_array( $result ) )
			array_push( $bookers, $row );
			
		mysqli_free_result( $result );
		
		// get countries
		$query  = "SELECT * FROM `countries` ORDER BY `name` ASC";
		$result = @mysqli_query($db,  $query);
		if( false == $result )
		{
			logError( "Failed to query countries from db: " . $query );
			return false;
		}
		
		if( mysqli_num_rows( $result ) <= 0 )
		{
			// logging
			logApp( "No country available for odds RSS feed!" );			
			mysqli_free_result( $result );
			return true;
		}
		
		$time 		= time();
		$curHour 	= intval( date( "H", $time ) );
		$curMin 	= intval( date( "i", $time ) );
		
		/*$saveFeed = false;
		if( ( $curHour % 4 ) == 0 && ( $curMin < 15 ) )
			$saveFeed = true;
		*/
		$saveFeed = true;
		
		// fetch country feed
		while( $country = mysqli_fetch_array( $result ) )
		{
			if( $debugEnabled )
				echo $country["feed_name"] . "\n";
			
			// is feed updated
			//if( dbIsFeedUpdated( "odds", $country["id"], $oddsFeedPause ) )
			//	continue;
				
			$url = str_replace( "<COUNTRY>", $country["feed_name"], $oddsFeedRss );
		
			@mysqli_close( $db );	

			// pause
			sleep( $oddsFeedPause );			
			
			// logging
			logApp( "Fetching odds RSS feed for " . $country["feed_name"] );
			
			// load page
			$rss = loadPage( $url );
			if( is_bool( $rss ) )
			{
				// connect to db
				$db = dbConnect();
				if( false == $db )
				{
					logError( "Failed to connect to db!" );
					return;
				}
				
				dbUpdateFeedLastQuery( "odds", $country["id"] );					
				//logError( "Failed to load odds RSS feed for " . $country["feed_name"] );
				continue;
			}
			
			// save feed to file
			if( $saveFeed )
				@file_put_contents( "/var/www/vhosts/omnibet.ro/httpdocs/log/feeds/odds/odds-" . $country["feed_name"]. ".xml", $rss );
			
			//@file_put_contents( "log/odds-" . rand() . ".xml", $rss );
				
			// connect to db
			$db = dbConnect();
			if( false == $db )
			{
				logError( "Failed to connect to db!" );
				return;
			}
			
			// update leagues
			if( !syncLeagues( 5, "odds", $country["id"], $rss, 1 ) )
				logError( "Failed to sync odds leagues for " . $country["feed_name"] . "in db" );
			
			$today = date( "Y-m-d", time() );
			
			if( $debugEnabled )
				echo "tips\n";
			
			// get tips
			$tipsData = "";
			$tips = getTips( $country["feed_name"], $rss, $bookers );
			if( !is_bool( $tips ) )
			{
				logApp( "Tips for country " . $country["feed_name"] . ": ". count( $tips["home"] ) . " " . count( $tips["away"] ) );
				if( isset( $tips["home"] ) && isset( $tips["away"] ) && ( count( $tips["home"] ) > 0 || count( $tips["away"] ) > 0 ) )
					$tipsData = json_encode( $tips );
			}
			else
			{
				logError( "Failed to get tips from RSS feed for " . $country["feed_name"] );		
			}
			
			if( $debugEnabled )
				echo "predictions\n";
			
			// get predictions
			$predictionData = "";
			$predictions = getPredictions( $country["feed_name"], $rss, $bookers );
			if( !is_bool( $predictions ) )
			{
				logApp( "Predictions for country " . $country["feed_name"] . ": ". count( $predictions ) );
				if( count( $predictions ) > 0 )
					$predictionData = json_encode( $predictions );
			}
			else
			{
				logError( "Failed to get predictions from RSS feed for " . $country["feed_name"] );		
			}	
			
			if( $debugEnabled )
				echo "dropping\n";
			
			// get dropping
			$droppingData = "";
			$dropping = getDropping( $country["feed_name"], $rss );
			if( !is_bool( $dropping ) )
			{
				logApp( "Dropping for country " . $country["feed_name"] . ": ". count( $dropping ) );
				if( count( $dropping ) > 0 )
					$droppingData = json_encode( $dropping );
			}
			else
			{
				logError( "Failed to get dropping from RSS feed for " . $country["feed_name"] );		
			}	
			
			if( $debugEnabled )
				echo "update\n";
			
			// update RSS feed
			if( !dbInsertFeed( "odds", $country["id"], $rss, $tipsData ) )
			{
				dbUpdateFeedLastQuery( "odds", $country["id"] );				
				logError( "Failed to update odds RSS feed for " . $country["feed_name"] . " in db" );
				continue;
			}			
			
			// db insert tips daily
			if( !empty( $tipsData ) )
			{
				if( !dbInsertTipsDaily( $country["id"], $tipsData, $today ) )
					logError( "Failed to update tips daily for " . $country["feed_name"] . " in db" );
			}
			
			// db insert predictions daily
			if( !empty( $predictionData ) )
			{
				if( !dbInsertPredictionsDaily( $country["id"], $predictionData, $today ) )
					logError( "Failed to update predictions daily for " . $country["feed_name"] . " in db" );
			}	
			
			// db insert dropping daily
			if( !empty( $droppingData ) )
			{
				if( !dbInsertDroppingDaily( $country["id"], $droppingData, $today ) )
					logError( "Failed to update dropping daily for " . $country["feed_name"] . " in db" );
			}				
		}
		
		mysqli_free_result( $result );
		
		// logging
		logApp( "Odds RSS feed updated!" );
		
		return true;
	}
	

	// whether process exists
	function isProcess( $action )
	{
		$processName = "/backend && php ./cache.php " . $action;
		
		// list process
		$cmd = "ps ax | grep '" . $processName . "' | grep -v 'grep' | wc -l";
		$output = array();
		$result = exec($cmd, $output, $status);
		if( $status != 0 )
			return true;
		
		if( count($output) != 1 )
			return true;
		
		$noInst = intval($output[0]);
		if( $noInst > 1 )
			return true;
			
		return false;
	}	
	
	
	// main
	function main()
	{
		global $db;
		global $argv;	
		global $action;
		
		// get action
		$action = "";
		if( !isset( $argv[1] ) && !isset( $_REQUEST["action"] ) )
			return;
			
		if( isset( $argv[1] ) )
			$action = $argv[1];
		else
			$action = $_REQUEST["action"];
			
		// verify if process running
		if( isProcess( $action ) )
		{
			logApp( "Process " . $action . " running" );
			return;
		}
			
		// logging
		logApp( "Start cache sync " . $action );
		
		// connect to db
		$db = dbConnect();
		if( false == $db )
		{
			logError( "Failed to connect to db!" );
			return;
		}
		
		// livescore		
		if( $action == "livescore" )
			cacheLivescoreFeed();
		else
		// fixtures
		if( $action == "fixtures" )
			cacheFixturesFeed();
		else
		// results
		if( $action == "results" )
			cacheResultsFeed();		
		else
		// standings
		if( $action == "standings" )
			cacheStandingsFeed();				
		else
		// scorers
		if( $action == "scorers" )
			cacheScorersFeed();
		else
		// odds
		if( $action == "odds" )
			cacheOddsFeed();
			
		// close db
		@mysqli_close( $db );		
		
		// logging
		logApp( "Cache sync completed" );		
	}
	
	
	// main
	main();

?>