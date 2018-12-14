<?php

use ZermeloAPI\Cache;

/**
 * This Zermelo API class was developed by wvanbreukelen, 2015
 * This project does not have any connections/is related to Zermelo itself!
 *
 * It contains all methods you can use to easily start your own app development
 */
class ZermeloAPI
{

	/**
	 * The school to use by the API
	 * @var string
	 */
	protected $school;

	/**
	 * Token caching instance to use
	 * @var object ZermeloAPI\Cache
	 */
	protected $cache;
	
	/**
	 * Using HTTPS or not
	 * @var boolean
	 */
	private $secure;

	protected $token;

	/**
	 * Allow double hours to be listed in the grid, default is false
	 */
	const ALLOW_DOUBLE_HOURS = false;

	/**
	 * Construct a new Zermelo instance, by any given school
	 * 
	 * @param string $school The school you want to add
	 */
	public function __construct($school, $secure = false, $cache = null)
	{
		$this->setSchool($school);
		$this->setSecure($secure);
		$this->setCache($cache);
	}

	/**
	 * Get a student grid, by looking forward in weeks
	 * @param  string  $id    The student id
	 * @param  integer $weeks The weeks to look forward, standard is one
	 * @return array          The parsed grid
	 */
	public function getStudentGridAhead($username, $weeks = 1)
	{
		// Process weeks settings

		if ($weeks == 1)
		{
			$start = strtotime('monday this week midnight');
			$end = strtotime('saturday this week');
		} else {
			if ($weeks == 2)
			{
				$start = strtotime('next week monday midnight');
				$end = strtotime('next week saturday');
			} else {
				$start = strtotime('+' . $weeks . ' weeks monday midnight');
				$end = strtotime('+' . $weeks . ' weeks saturday');
			}
		}
		
		// Receive and return the student grid

		return $this->getStudentGrid($username, $start, $end);
	}

	/**
	 * Get a student grid
	 * @param  string $id    The student id
	 * @param  string $start The start timestamp of the grid
	 * @param  string $end   The stop timestamp of the grid
	 * @return array         The grid itself or nothing on a fail
	 */
	public function getStudentGrid($username, $start = null, $end = null)
	{
		// Set the start times if they are not set

		if (is_null($start)) $start = strtotime('last monday', strtotime('tomorrow'));
		if (is_null($end)) $end = strtotime('next saturday', strtotime('tomorrow'));

		// Load the access token out of the cache

		$token = $this->getCache()->getToken($username);

		// Call the API

		$raw = $this->callApi("api/v2/appointments", array('access_token' => $token, 'start' => $start, 'end' => $end, 'user' => '~me'));

		// Process the results

		$json = json_decode($raw, true)['response'];

		if ($this->validateData($json))
		{
			$grid = $this->optimizeGrid($json['data']);

			return $grid;
		}

		return array();
	}
	
	/**
	 * Get a school grid (grid for the whole school)
	 * @param  string $id    The student id
	 * @param  string $start The start timestamp of the grid
	 * @param  string $end   The stop timestamp of the grid
	 * @return array         The grid itself or nothing on a fail
	 */
	public function getSchoolGrid($username, $start = null, $end = null)
	{
		// Set the start times if they are not set

		if (is_null($start)) $start = strtotime('last monday', strtotime('tomorrow'));
		if (is_null($end)) $end = strtotime('next saturday', strtotime('tomorrow'));

		// Load the access token out of the cache

		$token = $this->getCache()->getToken($username);

		// Call the API

		$raw = $this->callApi("api/v2/appointments", array('access_token' => $token, 'start' => $start, 'end' => $end, 'branch' => 'damstede'));

		// Process the results

		$json = json_decode($raw, true)['response'];

		if ($this->validateData($json))
		{
			// $grid = $this->optimizeGrid($json['data']);
			$grid = $json["data"];

			return $grid;
		}

		return array();
	}

	/**
	 * Get all classes of a specific school subject in a grid
	 * @param  string $grid    The containing grid
	 * @param  string $subject The school subject
	 * @return array
	 */
	public function getClasses($grid, $subject = '')
	{
		return $this->getGridPortion($grid, 'subjects', $subject);
	}

	/**
	 * Resolves all classes of a specific teacher in a grid
	 * @param  string $grid    The containing grid
	 * @param  string $teacher The teacher
	 * @return array
	 */
	public function resolveTeacherClasses($grid, $teacher)
	{
		return $this->getGridPortion($grid, 'teachers', $teacher);
	}

	/**
	 * Resolves all cancelled classes in a grid
	 * @param  string $grid The containing grid
	 * @return array
	 */
	public function resolveCancelledClasses($grid)
	{
		return $this->getGridPortion($grid, 'cancelled', 1);
	}
	
	/**
	 * Resolves all moved classes in a grid
	 * @param  string $grid The containing grid
	 * @return array
	 */
	public function resolveMovedClasses($grid)
	{
		return $this->getGridPortion($grid, 'moved', 1);
	}

	/**
	 * Get announcements, by looking forward in weeks
	 * @param  string  $id    The student id
	 * @param  integer $weeks The weeks to look forward
	 * @return array         The announcements
	 */
	public function getAnnouncementsAhead($id, $weeks = 1)
	{
		if ($weeks == 1)
		{
			$start = strtotime('next monday');
			$end = strtotime('next saturday');
		} else {
			$start = strtotime('+' . $weeks . ' weeks monday');
			$end = strtotime('+' . $weeks . ' weeks saturday');
		}

		return $this->getAnnouncements($id, $start, $end);
	}

	/**
	 * Get all of the user announcements
	 * @param  string $id    The student id
	 * @param  string $start The start timestamp
	 * @param  string $end   The end timestamp
	 * @return array         The announcements
	 */
	public function getAnnouncements($id, $start = null, $end = null)
	{
		// Set the start times if they are not set

		if (is_null($start)) $start = strtotime('last monday', strtotime('tomorrow'));
		if (is_null($end)) $end = strtotime('last saturday', strtotime('tomorrow'));

		// Load the access token out of the cache

		$token = $this->getCache()->getToken($id);

		// Call the API

		$raw = $this->callApi("api/v2/announcements", array('access_token' => $token, 'start' => $start, 'end' => $end));

		// Process the results

		$json = json_decode($raw, true)['response'];

		if ($this->validateData($json))
		{
			return $json['data'];
		}

		return null;
	}

	/**
	 * Grab a access token from the Zermelo API
	 * @param  string  $username Username
	 * @param  string  $password User password
	 * @param  boolean $saveToken Automatically save to access token to a cache file
	 * @return mixed         	 The token itself
	 */
	public function grabAccessToken($username, $password, $saveToken = true)
	{
		//$tokenID = $this->resolveTokenID($username, $password);
		
		// Check if token is already saved
		if ($token = $this->getCache()->getToken($username))
		{
			return $token;
		}
		
		// Get authorization code
		$raw = $this->callApiPostHeaders("api/v3/oauth", array(
			'username' => $username, 
			'password' => $password,
			'client_id' => 'OAuthPage', 
			'redirect_uri' => '/main/----success----', 
			'scope' => '', // must be empty
			'state' => '', // must be empty
			'response_type' => 'code'
		));


		if (!preg_match('/----success----\?code=([a-zA-Z0-9-]+)/', $raw, $matches) || count($matches) < 2) 
		{
			return;
		}

		$code = str_replace(' ', '', $matches[1]);

		// Grab access token from authorization code
		$raw = $this->callApiPost("api/v3/oauth/token", array('grant_type' => 'authorization_code', 'code' => $code));
		if (strpos($raw, 'Error report') !== false)
		{
			throw new \Exception("Unable to grab access token for user " . $username . ", please see raw data using print_r");
		}

		$json = json_decode($raw, true);

		// Check if response is a valid token
		if (empty($json['access_token']))
		{
			throw new \Exception("Unable to grab access token for user " . $username);
		}
		
		// Save token to cache
		if ($saveToken)
		{
			$this->getCache()->saveToken($username, $json['access_token']);
		}
		
		// Return token
		return $json['access_token'];
	}

	/**
	 * Invalidate a access token from the Zermelo API
	 * @param  string $id The student id
	 * @return bool
	 */
	public function invalidateAccessToken($id)
	{
		$token = $this->getCache()->getToken($id);

		$raw = $this->callApiPost("/api/v2/oauth/logout", array('access_token' => $token));

		if (strlen($raw) === 0)
		{
			return true;
		}

		return false;
	}

	/**
	 * Set the school to use
	 * @param string $school School to use
	 */
	public function setSchool($school)
	{
		$this->school = strtolower($school);
	}

	/**
	 * Set the secure state of the application
	 * @param string $secure The state boolean
	 */
	public function setSecure($secure)
	{
		$this->secure = $secure;
	}

	/**
	 * Validate the grid data received from the Zermelo API
	 * @param  array $data The grid data to validate
	 * @return bool        Successfull or not
	 */
	protected function validateData($data)
	{
		$httpStatus = $data['status'];

		if ($httpStatus != '200')
		{
			if ($httpStatus == '401')
			{
				throw new Exception("Cannot get data, access token is invalid!");

				return false;
			} else {
				throw new Exception("Data validation error: " . $data['message']);

				return false;
			}
		}

		return true;
	}

	/**
	 * Optimizes a grid
	 * @param  array  $grid The grid to optimize
	 * @return arrau        The optimized grid
	 */
	protected function optimizeGrid(array $grid = array())
	{
		$grid = $this->sortGrid($grid);

		foreach ($grid as $id => $row)
		{
			$grid[$id]['start_date'] = date('d/m/Y G:i', $row['start']);
			$grid[$id]['end_date'] = date('d/m/Y G:i', $row['end']);

			$grid[$id]['hour'] = round(($grid[$id]['start'] - strtotime(date('d-m-Y', $grid[$id]['start']) . ' 8:30')) / 3600);

			if ($grid[$id]['hour'] == 0) $grid[$id]['hour'] = 1;

			$i = 0;

			while ($i < 8)
			{
				$timeid = 8 + $i;

				if ($grid[$id]['hour'] == 1 && date('G:i', $grid[$id]['start']) != $i . ':30')
				{
					$grid[$id]['hour'] = $grid[$id]['hour'] + 1;
				} else {
					$i = 8;
				}

				$i = $i + 1;
			}
		}

		return $grid;
	}

	/**
	 * Sort a grid by timestamp
	 * @param  array  $grid The grid to sort
	 * @return array        The sorted grid
	 */
	protected function sortGrid(array $grid = array())
	{
		$timestamps = array();
		
		foreach ($grid as $key => $node)
		{
			if (in_array($node['start'], $timestamps))
			{
				if (self::ALLOW_DOUBLE_HOURS == false)
				{
					unset($grid[$key]);
				} else {
					$timestamps[$key] = $node['start'];
					if ($node['cancelled'] == true)
					{
						unset($grid[$key]);
					}
				}
			} else {
				$timestamps[$key] = $node['start'];
			}
		}

		array_multisort($timestamps, SORT_ASC, $grid);

		return $grid;
	}

	protected function getGridPortion($grid, $identifier, $search)
	{
		$classes = array();

		foreach ($grid as $class)
		{
			if (isset($class[$identifier]))
			{
				if (is_array($class[$identifier]) && in_array($search, $class[$identifier]))
				{
					$classes[] = $class;
				} else if ($class[$identifier] == $search) {
					$classes[] = $class;
				}
			}
		}

		return $classes;
	}

	/**
	 * Get the Zermelo API base URL
	 * @param  string $uri The uri
	 * @return string      The base URL
	 */
	protected function getBaseUrl($uri = "")
	{
		return "https://" . $this->school . ".zportal.nl/" . $uri;
	}

	/**
	 * Set the Zermelo API cache instance of token caching
	 * @param object $cache ZermeloAPI\Cache
	 */
	public function setCache($cache)
	{
		if (!$cache instanceof ZermeloAPI\Cache)
		{
			$cache = new ZermeloAPI\Cache;
		}

		$this->cache = $cache;
		
		if (strlen($this->cache->getFileLocation()) < 1 || is_null($this->cache->getFileLocation()))
		{
		    throw new Exception("File location is not set! Current file location: " . $this->cache->getFileLocation());
		}
	}

	/**
	 * Get the Zermelo API caching instance
	 * @return object ZermeloAPI\Cache
	 */
	public function getCache()
	{
		if ($this->cache instanceof ZermeloAPI\Cache)
		{
			return $this->cache;
		}

		throw new Exception("Cannot get cache instance, cache variable is not an instance of ZermeloAPI\\Cache");
	}

	/**
	 * Cleans out the whole cache, excepts a true boolean for verification
	 * @param  boolean $cacheVerfifierBool Excepts a true boolean for verification
	 * @return mixed Success or not
	 */
	public function cleanCache($cacheVerfifierBool = false)
	{
		// The cache verifier boolean makes sure that the cache is not accidently deleted

		$this->getCache()->cleanCache($cacheVerfifierBool);
	}
	
	/**
	 * Format a grid to XML format
	 * @param  array $grid The grid array itself
	 * @return string The XML code
	 */
	public function formatXML($grid, $rootElement = '<grid><grid/>')
	{
		return $this->formatXMLPortion($array, $rootElement);
	}
	
	/**
	 * Format a grid to JSON format
	 * @param   array $grid The grid array itself
	 * @return  string The JSON code
	 */
	public function formatJSON($grid)
	{
		if (is_array($grid))
		{
			return json_encode($grid);
		}
		
		throw new Exception("Cannot format grid to JSON format. Please give an {array} as a variable type, not a {" . gettype($grid) . "}!");
	}
	
	/**
	 * Format a portion of a array to XML format
	 * @param  string $array The array to convert
	 * @param  object $xml   The optionally existing XML object
	 * @return string        The converted XML code
	 */
	protected function formatXMLPortion($array, $xml = null)
	{
		$xml = new SimpleXMLElement('<grid><grid/>');
		
		foreach ($array as $i => $v)
		{
			// Check if nested array
			if (is_array($x))
			{
			       // Nested array
				$this->formatXMLPortion($v, $i, $xml->addChild($i));
			} else {
				// Is not nested, add directly to XML object
				$xml->addChild($i, $v);
			}
		}
		
		// Convert the XML object to actual XML code and return the results
		return $xml->asXML();
	}

	private function resolveTokenID($username, $password) {
		return sha1($this->school . ':' . $username . ':' . $password . '_' . floor(time()/9000));
	}

	/**
	 * Call the API by using the HTTP GET method
	 * @param  string $uri        Uri to interact
	 * @param  array  $datafields The datafields
	 * @return string             The raw results from the API
	 */
	private function callApi($uri, $datafields = array())
	{
		// Get the Zermelo HTTP base url
		$url = $this->getBaseUrl($uri);

		// Parse a HTTP data string
		$data = $this->parseHttpDataString($datafields);

		$ch = curl_init();

		// Set the url with the attached data string
		curl_setopt($ch, CURLOPT_URL, $url . $data);
		
		// Disable cache
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);

		// Using HTTPS
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->secure);

		// Return the results, essential for the whole application
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Execute the curl request
		$result = curl_exec($ch);

		curl_close($ch);

		return $result;
	}

	/**
	 * Call the API by using the HTTP POST method
	 * @param  string $uri        Uri to interact
	 * @param  array  $datafields The datafields
	 * @return string             The raw results from the API
	 */
	private function callApiPost($uri, $datafields = array())
	{
		// Get the Zermelo HTTP base url
		$url = $this->getBaseUrl($uri);

		// Trim the HTTP data string to convert it to an POST string
		$data = rtrim(ltrim($this->parseHttpDataString($datafields), '?'), '&');

		$ch = curl_init();

		// Set the url
		curl_setopt($ch, CURLOPT_URL, $url);
		
		// Disable cache
		curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);

		// Amount of POST parameters to send
		curl_setopt($ch, CURLOPT_POST, count($datafields));

		// Set the POST parameters to their assigned values
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		// Using HTTPS
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->secure);

		// Return the results, essential for the whole application
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// Execute the curl request
		$result = curl_exec($ch);

		curl_close($ch);

		return $result;
	}

	/**
	 * Call the API by using the HTTP POST method and return response headers
	 * @param  string $uri        Uri to interact
	 * @param  array  $datafields The datafields
	 * @return string             The raw results from the API
	 */
	private function callApiPostHeaders($uri, $datafields = array())
	{
		// Get the Zermelo HTTP base url
		$url = $this->getBaseUrl($uri);
		// Trim the HTTP data string to convert it to an POST string
		$data = rtrim(ltrim($this->parseHttpDataString($datafields), '?'), '&');
		$ch = curl_init();
		// Set the url
		curl_setopt($ch, CURLOPT_URL, $url);
		// Amount of POST parameters to send
		curl_setopt($ch, CURLOPT_POST, count($datafields));
		// Set the POST parameters to their assigned values
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
		curl_setopt($ch, CURLOPT_HEADER, true);
		// Using HTTPS
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->secure);
		// Return the results, essential for the whole application
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// Execute the curl request
		$result = curl_exec($ch);
		$curl_info = curl_getinfo($ch);
		$headers = substr($result, 0, $curl_info["header_size"]);
		curl_close($ch);
		return $result;
	}

	/**
	 * Parse a HTTP data string for GET and POST request from a datafields array
	 * @param  array  $datafields The datafields arrau
	 * @return string             The parsed data string
	 */
	private function parseHttpDataString(array $datafields = array())
	{
		$string = "?";

		foreach ($datafields as $key => $value)
		{
			$string .= $key . "=" . $value . "&";
		}

		return $string;
	}
}