<?php

/**
 * Exception thrown where information can't be found.
 *
 */
class PokemonGTSException extends Exception {

	public function __construct($message){
		parent::__construct($message);
	}

}

/**
 * 
 * Pokemon Global Trade Station data provider.
 *
 */
class PokemonGTS {
	
	/**
	 * The url providing the trades.
	 */
	const URL_TRADES_LIST = 'https://3ds.pokemon-gl.com/frontendApi/mypage/getGtsTradeList';
	
	/**
	 * The url providing the user account data. 
	 */
	const URL_ACCOUNTDATA_LIST = 'https://3ds.pokemon-gl.com/user/%s/gts/';
	
	/**
	 * The url using as referer for trades http request.
	 */
	const REFERER_ACCOUNTDATA_LIST = 'https://3ds.pokemon-gl.com/user/%s/gts/';
	
	/**
	 * Date format in trades data.
	 */
	const DATE_FORMAT = 'Y/m/d H:i:s';
	
	/**
	 * The user profil id. 
	 */
	private $profilId;
	/**
	 * User account id.
	 */
	private $userAccountId;
	/**
	 * User save data id.
	 */
	private $userSaveDataId;
	
	/**
	 * The id of language that will be used in trades data.
	 */
	private static $LANGUAGE_ID = 2;
	
	//language ids
	const LANGUAGE_JAPONESE = 1;
	const LANGUAGE_ENGLISH = 2;
	const LANGUAGE_FRENCH = 3;
	const LANGUAGE_ITALIAN = 4;
	const LANGUAGE_GERMAN = 5;
	
	
	/**
	 * Constructor
	 * 
	 * @param string $profilId
	 * 	The user pokemon global link profil id
	 *
	 * @param string $userAccountId
	 * 	The user account id. If null, it will try to retrieve it when it is needed.
	 * 	
	 * @param string $userSaveDataId
	 * 	The user save data id. If null, it will try to retrieve it when it is needed.
	 */
	public function __construct(string $profilId, string $userAccountId=null, string $userSaveDataId=null) {
		$this->profilId = $profilId;
		$this->userAccountId = $userAccountId;
		$this->userSaveDataId = $userSaveDataId;
	}
	
	/**
	 * The user account id.
	 * @param string $userAccountId
	 * 	User account id.
	 */
	public function setUserAccoundId(string $userAccountId) {
		$this->userAccountId = $userAccountId;
	}
	
	/**
	 * The user save data id.
	 * @param string $userSaveDataId
	 * 	User save data id.
	 */
	public function setUserSaveDataId(string $userSaveDataId) {
		$this->userSaveDataId = $userSaveDataId;
	}
	
	
	/**
	 * Get the user account id.
	 * @return string The user account id.
	 */
	public function getUserAccoundId() {
		return $this->userAccountId;
	}
	
	/**
	 * Get the user save data id.
	 * @return string The user save data id.
	 */
	public function setUserSaveDataId() {
		return $this->userSaveDataId;
	}
	
	/**
	 * try to retrieve the user account in the tags script from the html of the user 
	 * pokemon global link account url.
	 * Throw a PokemonGTSException if informations could not be found.
	 * 
	 * @return array Array with "accountId" key and saveDataId key.
	 */
	public function getUserAccountData() {
		$userAccountData = array();
		
		$url = sprintf(self::URL_ACCOUNTDATA_LIST, $this->profilId);
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$result = curl_exec($curl);

		curl_close($curl);

		//search for the user account id
		$matches = array();
		preg_match('`var\s+USERS_ACCOUNT_ID\s*=\s*\'(.*)\'`', $result, $matches);
		$this->userAccountId = count($matches) ? $matches[1] : null;

		//search for the user savedata id
		$matches = array();
		preg_match('`var\s+USERS_SAVEDATA_ID\s*=\s*\'(.*)\'`', $result, $matches);
		$this->userSaveDataId = count($matches) ? $matches[1] : null;

		$userAccountData["accountId"] = $this->userAccountId;
		$userAccountData["saveDataId"] = $this->userSaveDataId;
		
		if(!$this->isUserDataKnown())
		{
			throw new PokemonGTSException(sprintf("User data cannot be loaded on %s for user %s. Please check that your profile is public on the Pokemon Global Link.",
													$url, $this->profilId));
		}
		
		return $userAccountData;
	}
	
	/**
	 * Get trades list of the user.
	 * 
	 * @param int $count
	 * 	Number of trades to return.$this
	 * @param int $page
	 * 	Page trades to get (start is 1).
	 */
	public function getTradesList(int $count=1, int $page=1) {
		if(!$this->isUserDataKnown()) {
			$this->getUserAccountData();
		}
		
		$data = array(
			'languageId' => self::$LANGUAGE_ID,
			'memberSavedataIdCode' => $this->profilId,
			'accountId' => $this->userAccountId,
			'savedataId' => $this->userSaveDataId,
			'count' => $count,
			'page' => $page,
			'mypageTab' => 'gts',
			'timeStamp' => date_timestamp_get(date_create()),
		);
		
		$postvars = '';
		foreach($data as $key=>$value) {
			$postvars .= $key . "=" . $value . "&";
		}
		
		$curl = curl_init(self::URL_TRADES_LIST);
		
		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
			'Host: 3ds.pokemon-gl.com',
			'Connection: keep-alive',
			'Accept: application/json',
			'pragma: no-cache',
			'Origin: http://3ds.pokemon-gl.com',
			'X-Requested-With: XMLHttpRequest',
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
			sprintf('Referer: '.self::REFERER_ACCOUNTDATA_LIST, $this->profilId),
			'Accept-Encoding: gzip,deflate,sdch',
		));
		
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postvars);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		
		$result = json_decode(curl_exec($curl));
		
		
		return $result; 
	}
	
	/**
	 * Return the last trade of the user.
	 * @return object Last user trade.
	 */
	public function getLatestTrade() {
		return $this->getTradesList()->tradeList[0];
	}

	/**
	 * Get user trades from a time specified in timestamp.
	 * 
	 * @param int $timestamp The timestamp from which trades are returned. 
	 * 
	 * @return object[] The user trades made after the timestamp in parameter. 
	 */
	public function getTradesFromDate($timestamp = null) {
		$tradesPerPage = 5;
		$page = 1;
		
		$result = array();
		
		do {

			$tradesResult = $this->getTradesList($tradesPerPage, $page);
			$trades = $tradesResult->tradeList;
			
			foreach($trades as $trade){
				$tradeTimestamp = DateTime::createFromFormat(self::DATE_FORMAT, $trade->tradeDate)->getTimestamp();

				if($tradeTimestamp > $timestamp) {
					$result[] = $trade;
				}
				else {
					break;
				}
			}

			$page++;

		} while($page * $tradesPerPage < $tradesResult->totalCount && $tradeTimestamp > $timestamp);
		
		return $result;
	}
	
	/**
	 * Get all user trades.
	 *
	 * @return object[] All user trades.
	 */
	public function getTradesFromDate($timestamp = null) {
		return $this->getTradesFromDate(0);
	}
	
	/**
	 * Set the language that will be used for trades data.
	 * 
	 * @param int $languageId
	 */
	public static function set_language($languageId)
	{
		self::$LANGUAGE_ID = $languageId;
	}
	
	/**
	 * Check if user data are setted.
	 * 
	 * @return boolean True if all user data is setted else false.
	 */
	private function isUserDataKnown()
	{
		return !empty($this->userAccountId) && !empty($this->userSaveDataId);
	}

}