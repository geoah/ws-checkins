<?php

require_once('includes/parsecsv.lib.php');

/**
 * IMDB CheckIn Tracker.
 *
 * @version 1.0, July 21, 2013
 * @copyright Websthetics <info@websthetics.gr>
 * @license http://mozilla.org/MPL/2.0/
 * 
 * @author Apostolos Kritikos <akritiko@gmail.com>
 * 
 * Class that analyzes the checkin history of an IMDB user and produces
 * statistics. 
 *
 * LICENSE
 * 
 * This Source Code Form is subject to the terms of the Mozilla Public 
 * License, v. 2.0. If a copy of the MPL was not distributed with this 
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 * 
 */
 
class imdbCheckInTracker
{
	/** IMDB user's checkin CSV url */
	private $checkinURL;
	/** IMDB user's ID */
	private $imdbUserID;
	/** File name on the server */
	private $filename;
	/** CSV handler */
	private $csv;
	/** Month name to number associative array */
	private $months = array(
		"Jan" => "1",
		"Feb" => "2",
		"Mar" => "3",
		"Apr" => "4",
		"May" => "5",
		"Jun" => "6",
		"Jul" => "7",
		"Aug" => "8",
		"Sep" => "9",
		"Oct" => "10",
		"Nov" => "11",
		"Dec" => "12"
	);
	
	/** Number of checkins in total */
	private $nofElementsSeen;
	
	/** 
	 * Associative array that holds the Elements seen 
	 * and their respective URLs 
	 */
	private $elementsSeen;
	
	/** Number of TV series episodes seen */
	private $nofTVEpisodesSeen;
	
	/** 
	 * Number of TV series seen (to be separated from
	 * specific episodes 
	 */
	private $nofTVSeriesSeen;
	
	/** Number of movies seen */
	private $nofMoviesSeen;
	
	/** IMDB ratings average */
	private $IMDBRatingAverage;
	
	/** IMDB ratings min */
	private $IMDBRatingMin;
	
	/** IMDB ratings max */
	private $IMDBRatingMax;
	
	/** User's rating average */
	private $UserRatingAverage;
	
	/** User's rating min */
	private $UserRatingMin;
	
	/** User's rating max */
	private $UserRatingMax;
	
	/** TV series episodes' genre stats */
	private $tvGenreStats; 
	
	/** Movies' genre stats */
	private $movieGenreStats; 
	
	/** Total genre stats */
	private $allGenreStats; 
	
	/** TV episodes year stats */
	private $tvYearStats; 
	
	/** Movies year stats */
	private $movieYearStats; 
	
	/** All year stats */
	private $allYearStats; 
	
	/** 
	 * Constructor. It creates an instance of @link imdbCheckInTracker based on 
	 * the IMDB user's ID. Apart from initializing the attributes of the class 
	 * constructor also configures the CSV parsing settings. Currently the CSV 
	 * data are being read in reverse order and are being sorted based on the 
	 * CSV's "position" field.
	 */
	public function __construct($userID) {
		$this->checkinURL = "http://www.imdb.com/list/export?list_id=checkins&amp;author_id=" . $userID;
		$this->imdbUserID = $userID;
		$this->filename = "data/".$this->imdbUserID.".csv";
		
		$this->nofElementsSeen = 0;
		$this->elementsSeen = array();
		$this->nofTVEpisodesSeen = 0;
		$this->nofTVSeriesSeen = 0;
		$this->nofMoviesSeen = 0;
		$this->IMDBRatingAverage = 0.0;
		$this->IMDBRatingMin = 999.9;
		$this->IMDBRatingMax = 0.0;
		$this->tvGenreStats = array(); 
		$this->movieGenreStats = array(); 
		$this->allGenreStats = array(); 
		$this->tvYearStats = array(); 
		$this->movieYearStats = array(); 
		$this->allYearStats = array(); 
		
		//Download user's scheckin list from IMDB to CSV format 
	 	//REUSED FROM:
	 	//@link http://stackoverflow.com/questions/3938534/download-file-to-server-from-url
		file_put_contents($this->filename, fopen($this->checkinURL, 'r'));	
		
		//CSV Parser initialization and settings setup
		$this->csv = new parseCSV();
		$this->csv->sort_by = "position";
		$this->csv->sort_reverse = true;
	}
	
	/** 
	 * Analyzes the checkin CSV and produces statistics 
	 */
	public function analyze() {
		if($this->isValid()) { //Checks if the user exists and has a valid checkin history CSV
			$this->calculateStatistics();
		} else {
			unlink($this->filename);
			die("Wrong IMDB user or IMDB user's checkin URL! Please try again.");
		}
	}
	
	/**
	 * Prints the user's check in list. Used mainly for debugging
	 * reasons but it produces a rather pleasing result nevertheless. 
	 * 
	 * @todo to be offered as a paginated table option!
	 */
	public function printUserCheckInHistory() {
		$this->csv->auto($this->filename);
?>	
		<style type="text/css" media="screen">
			table { background-color: #BBB; }
			th { background-color: #EEE; }
			td { background-color: #FFF; }
			h2 { text-align: center; }
			table { width: 100%; }
		</style>
		<table border="0" cellspacing="1" cellpadding="3">
			<h2>
				CheckIn history of IMDB user<br>
				<font face="Courier"><?php echo $this->imdbUserID; ?></font>
			</h2>
			<tr>
				<?php foreach ($this->csv->titles as $value): ?>
				<th><?php echo $value; ?></th>
				<?php endforeach; ?>
			</tr>
			<?php foreach ($this->csv->data as $key => $row) : ?>
			<tr>
				<?php foreach ($row as $value) : ?> 
				<td><?php echo $value; ?></td>
				<?php endforeach; ?>
			</tr>
			<?php endforeach; ?>
		</table>
<?php
	}
	
	/** 
	 * Checks if the CSV file is valid.
	 * @return bool
	 */
	private function isValid() {
		$csv = new parseCSV();
		$csv->auto($this->filename);
		
		if(sizeof($csv->titles)>1) {
			return true;
		} else {
			return false;
		}
	}

	
	/** 
	 * Loops through the rows of the CSV file, calculates
	 * the statistics and stores the results to appropriates
	 * attribute variables.
	 */
	private function calculateStatistics() {
		$this->csv->auto($this->filename);
		$rowCount = 0;
		$imdbRatingSum = 0;
		
		foreach ($this->csv->data as $key => $row): 
			//print_r($row);
			
			//Calculates the number of elements seen by the user
			if($rowCount == 0) {
				$this->nofElementsSeen = $key;
			}
			//Creates an associative array with the elements seen
			//by the user in form of "Title" => "URL". 
			$this->elementsSeen[$row["Title"]] = $row["URL"];
			
			//Calculates the number of TV episodes seen by the user
			if($row["Title type"] == "TV Episode") {
				$this->nofTVEpisodesSeen++;
			}
			
			//Calculates the number of movies seen by the user
			if($row["Title type"] == "Feature Film") {
				$this->nofMoviesSeen++;
			}
			
			//Calculates the number of TV series seen by the user
			//TODO: when calculate the series we should remove the duplicates!
			if($row["Title type"] == "TV Series") {
				$this->nofTVSeriesSeen++;
			}
			
			//Calculates IMDB rating sum
			$imdbRatingSum = $imdbRatingSum + $row["IMDb Rating"];
			
			//Calculate IMDB rating max
			if($row["IMDb Rating"] > $this->IMDBRatingMax) {
				$this->IMDBRatingMax = $row["IMDb Rating"];
			}
			
			//Calculate IMDB rating min
			if($row["IMDb Rating"] < $this->IMDBRatingMin) {
				$this->IMDBRatingMin = $row["IMDb Rating"];
			}
			
			//Calculate genre statistics
			$genres = explode(", ",$row["Genres"]); 
			if($row["Title type"] == "TV Episode") {
				//TV genre statistics
				$this->calculateGenreStatistics($this->tvGenreStats, $genres);
			} else if($row["Title type"] == "Feature Film") {
				//Movie genre statistics
				$this->calculateGenreStatistics($this->movieGenreStats, $genres);
			}
			
			//All genre statistics. What it does, is that combines TV and Movies associative arrays
			$this->allGenreStats = $this->mergeAssociativeArrays($this->tvGenreStats, $this->movieGenreStats);
			
			//Calculate year statistics
			if($row["Title type"] == "TV Episode") {
				//TV genre statistics
				$this->calculateYearStatistics($this->tvYearStats, $row["Year"]);
			} else if($row["Title type"] == "Feature Film") {
				//Movie genre statistics
				$this->calculateYearStatistics($this->movieYearStats, $row["Year"]);
			}
			
			//All genre statistics. What it does, is that combines TV and Movies associative arrays
			$this->allYearStats = $this->mergeAssociativeArrays($this->tvYearStats, $this->movieYearStats);
			
			$rowCount++;
		endforeach;
		
		//Calculates IMDB rating average (all elements seen)
		$this->IMDBRatingAverage = $imdbRatingSum / $this->nofElementsSeen;
	}
	
	/**
	 * Calculates Genre Statistics. Stores genres and their 
	 * frequencies to an associative array. 
	 * 
	 * @param $genreArray
	 * @param $genres
	 */
	private function calculateGenreStatistics(&$genreArray, $genres) {
		for($i = 0; $i < sizeof($genres); $i++) {
			if(array_key_exists($genres[$i], $genreArray)) {
				$genreArray[$genres[$i]] = ($genreArray[$genres[$i]] +1 );
			} else {
				$genreArray[$genres[$i]] = 1;
				
			}
		}
	}
	
	/**
	 * Calculates Genre Statistics. Stores genres and their 
	 * frequencies to an associative arrays. 
	 * 
	 * @param $yearArray
	 * @param $year
	 */
	private function calculateYearStatistics(&$yearArray, $year) {
		if(array_key_exists($year, $yearArray)) {
			$yearArray[$year] = ($yearArray[$year] +1 );
		} else {
			$yearArray[$year] = 1;
			
		}
	}
	
	/** 
	 * Merges two associative arrays to one summing up the frequencies for 
	 * identical keys.
	 * 
	 * @param $array1 
	 * @param $array2 
	 */
	private function mergeAssociativeArrays($array1, $array2) {
		$sums = array();
		foreach (array_keys($array1 + $array2) as $key) {
		    $sums[$key] = (isset($array1[$key]) ? $array1[$key] : 0) + (isset($array2[$key]) ? $array2[$key] : 0);
		}
		return $sums;
	}
	
	/**
	 * Transforms an IMDB generated date to a date with 
	 * YYYYMMDD format.
	 * 
	 * @return string (date in YYYYMMDD format)
	 */
	private function imdbDateToDate($imdbDate) {
		$arrDate = explode(" ",$imdbDate);
		return $arrDate[4] . $this->months[$arrDate[1]] . $arrDate[2];
	}
	
	/**
	 * Returns the year part of an IMDB date.
	 * 
	 * @return string
	 */
	private function getYear($imdbDate) {
		$arrDate = explode(" ",$imdbDate);
		return $arrDate[4];
	}
	
	/**
	 * Returns the month part of an IMDB date. 
	 * 
	 * @return string
	 */
	private function getMonth($imdbDate) {
		$arrDate = explode(" ",$imdbDate);
		return $this->months[$arrDate[1]];
	}
	
	/**
	 * Returns the day part of an IMDB date. 
	 * 
	 * @return string
	 */
	private function getDay($imdbDate) {
		$arrDate = explode(" ",$imdbDate);
		return $arrDate[2];
	}
	
	//GETTERS: @todo add phpdoc!
	
	public function getCheckinURL() { 
		return $this->checkinURL; 
	} 
	
	public function getImdbUserID() { 
		return $this->imdbUserID; 
	}
	 
	public function getFilename() { 
		return $this->filename; 
	} 
	
	public function getNofElementsSeen() { 
		return $this->nofElementsSeen; 
	} 
	
	public function getElementsSeen() {
		return $this->elementsSeen; 
	}
	 
	public function getNofTVEpisodesSeen() { 
		return $this->nofTVEpisodesSeen; 
	}
		 
	public function getNofTVSeriesSeen() { 
		return $this->nofTVSeriesSeen; 
	} 
	
	public function getNofMoviesSeen() { 
		return $this->nofMoviesSeen; 
	} 
	
	public function getIMDBRatingAverage() { 
		return $this->IMDBRatingAverage; 
	}
	 
	public function getIMDBRatingMin() { 
		return $this->IMDBRatingMin; 
	}
	 
	public function getIMDBRatingMax() { 
		return $this->IMDBRatingMax; 
	} 
	
	public function getUserRatingAverage() { 
		return $this->UserRatingAverage; 
	} 
	
	public function getUserRatingMin() { 
		return $this->UserRatingMin; } 
	
	public function getUserRatingMax() { 
		return $this->UserRatingMax; 
	} 
	
	public function getTvGenreStats() { 
		return $this->tvGenreStats; 
	} 
	
	public function getMovieGenreStats() { 
		return $this->movieGenreStats; 
	} 
	
	public function getAllGenreStats() { 
		return $this->allGenreStats; 
	} 
	
	public function getTvYearStats() { 
		return $this->tvYearStats; 
	} 
	
	public function getMovieYearStats() { 
		return $this->movieYearStats; 
	} 
	
	public function getAllYearStats() { 
		return $this->allYearStats; 
	} 
}
?>