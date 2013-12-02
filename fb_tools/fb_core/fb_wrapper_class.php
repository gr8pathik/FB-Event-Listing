<?php

   # ========================================================================#
   #
   #  Author:    Pathik Gandhi
   #  Version:	 1.5
   #  Purpose:   Interact with the Facebook SDK.
   #  Project:	 Facebook Graph API.	
   #  Param In:  See functions.
   #  Param Out: n/a
   #  Requires : 
   #  Ref:		 profile info :http://developers.facebook.com/docs/reference/api/user/	
   #
   # ========================================================================#

class FaceBookWrapper
{
	// *** Required settings
	private $_appId;
	private $_secretId;
	private $_redirectURL;
	//private $_primaryUserId;
	protected $_userId;
	protected $_user;
	
	// *** Facebook Object
	protected $_facebookObj;
	protected $_token;
	
	// *** Profile data
	protected $_profileDataArray = array();
	
	// *** Error handling
	protected $_errorArray = array();
	protected $_debugArray = array();
	protected $_trace = array();
	
	// *** Permissions
	private $_permissions = 'email,offline_access,user_events,friends_events,create_event,rsvp_event';
	
	// *** Memory cache
	private $_memoryCache = array();
	
	// *** Enable for automatic data cache clearing
	private $_dev = false;


	private $_isPage;
	
## _____________________________________________________________________________	
## ________                _____________________________________________________
## ________ PUBLIC METHODS _____________________________________________________
## _____________________________________________________________________________
##		
	
	public function __construct($appId, $secretId, $redirectURL, $tokenedUser=null, $publicUser=null, $cookies=true)
	{
		
		if ($this->_dev) {
			CacheManager::clearCache();
		}
					
		// *** Save these for later
		$this->_appId = $appId;
		$this->_secretId = $secretId;
		$this->_redirectURL = $redirectURL;
		

		// *** Unset facebook user id
		$user = null; 
		
		// *** include the facebook api class
		try{
			require_once ('facebook.php');
		}
		catch(Exception $o){
			error_log($o);
			print_r($o);
			exit;
		}
		
		// *** Create our Application instance.
		$this->_facebookObj = new Facebook(array(
		  'appId'  => $appId,
		  'secret' => $secretId,
		  'cookie' => $cookies,
		));	
		
		
		// *** Get the UID of the connected user (this is before we switch users)
		//$this->_primaryUserId = $this->_facebookObj->getUser();
		//echo 'uiser1:: ' . $this->_primaryUserId . '<br />';
		
		// *** Try set an access token (if applicable)
		$isOK = $this->_userAccessToken($tokenedUser);
		if (!$isOK) {
			$this->_errorArray[] = 'User\'s token not authenticated.';
			$this->_debugArray[] = 'User\'s token not authenticated. Run fb_tools/auth_user.php.';	
		}
		
		// *** Get the UID of the connected user.
		$this->_user = $this->_facebookObj->getUser();

	
		
		if (USE_PAGE_ID == false) {	

			// *** If we want to check out our profile	
			$this->_userId = $this->_user;
		} else {	
		
			// *** Or, if we wish to act on behalf of a public profile
			$this->_userId = $publicUser;
		}	

			
		if (CACHE_ENABLED) {
			//check timestamp, remove files if need be.
			CacheManager::manageCache(CACHE_MINUTES, $this->_userId);
		}
		
		$this->_profileDataArray = $this->_getUserProfileRaw();

		// *** 
		if (USE_PAGE_ID) {
			$this->_isPage = true;
		}

	}
	
	## --------------------------------------------------------
	
	public function __destruct() 
	{
		if (isset($this->_memoryCache)) {
			unset($this->_memoryCache);
		}
	}


	/*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-* 
	 *	Login / Logout Methods
	 * 	
	 */
	
	public function getLoginURL()
	# Return the login URL link
	{
		$loginUrl = $this->_facebookObj->getLoginUrl(
				array(
					'scope'         => $this->_permissions,
					'redirect_uri'  => $this->_redirectURL
				)
		);		
		
		return $loginUrl;
	}
	
	## --------------------------------------------------------
	
	public function loginRedirect()
	# Automatically redirect you to the facebook login page
	{
		if (!$this->_user) {				
			header("Location:{$this->_facebookObj->getLoginUrl(array('scope' => $this->_permissions, 'redirect_uri'  => $this->_redirectURL))}");
			exit;
		}
	}
	
	## --------------------------------------------------------
	
	public function getLogoutURL()
	# Return the logout URL link. Doesn't remove the cookie
	{
		$logoutUrl  = $this->_facebookObj->getLogoutUrl();
		return $logoutUrl;
	}
	
	## --------------------------------------------------------
	
	public function logout()
	{
		// *** Remove the cookie
		if (isset($_SERVER['HTTP_COOKIE'])) {
			$cookies = explode(';', $_SERVER['HTTP_COOKIE']);
			foreach($cookies as $cookie) {
				$parts = explode('=', $cookie);
				$name = trim($parts[0]);
				setcookie($name, '', time()-1000);
				setcookie($name, '', time()-1000, '/');
			}
		}
	}
	
	## --------------------------------------------------------
	
	public function getLogInOutLink()
	# Show link depending on the users login/logout status
	{
		if ($this->_user) {
		  return $this->_facebookObj->getLogoutUrl();
		} else {
		  return $this->_facebookObj->getLoginUrl();
		}		
	}
	
	## --------------------------------------------------------
	
	public function isLoggedIn()
	# Test if user is logged in and authenticated
	{
		if ($this->_user) {
			return true;
		} else {
			return false;
		}
	}
	
	
	/*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-* 
	 *	Facebook Get Info Methods
	 * 	
	 */
		
	public function saveProfileImage($path, $quality = 100)
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jun 11
	#	Purpose:	Save profile image
	#	Params in:
	#	Params out:
	#	Notes:	
	#	Used in:	n/a
	#
	{		
		if ($this->_user) {
			try {
				$data = file_get_contents('http://graph.facebook.com/' . $this->_userId . '/picture');
				$im = imagecreatefromstring($data);
				imagejpeg($im, $path, $quality);
				if (TRACE) {$this->_trace[] = 'saveProfileImage'; }
			} catch (FacebookApiException $e) {
				$this->_errorArray[] = 'Could not save profile image.';
				$this->_debugArray[] = $e;
				return false;
			}					
		}
	}
	
	## --------------------------------------------------------
	
	public function getProfileImageURL($size='square')
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jun 11
	#	Purpose:	Get the profile image URL
	#	Params in: (str) $size: square, small, normal, large
	#	Params out:
	#	Notes:	
	#	Used in:	n/a
	#
	{
		$size = $this->_resolveImageSize($size);
		
		if ($this->_user) {
			try {
				//if (TRACE) {$this->_trace[] = 'getProfileImageURL'; }
				return 'http://graph.facebook.com/' . $this->_userId . '/picture?type=' . $size;
			} catch (FacebookApiException $e) {
				$this->_errorArray[] = 'Could not get profile image URL.';
				$this->_debugArray[] = $e;
				return false;
			}			
		}	
	}
	
	## --------------------------------------------------------
	
	public function getAlbumNames($includeProfileAlbum = false)
	#
	#	Author:		Jarrod Oberto
	#	Date:		June 11
	#	Purpose:	Get an array of album names
	#	Params in:	(bool) $includeProfileAlbum: if set to true, the users profile pictures album will be returned, too.
	#	Params out:	(array) Associate array of album id's / names
	#	Notes:	
	#	Used in:	FB Album Gallery
	#
	{
		// *** Get album data
		$albumsData = $this->_getAlbumDataRaw();

		$albumNamesArray = array();
		
		if (count($albumsData['data']) > 0) {
			
			// *** Loop through album data
			foreach ($albumsData['data'] as $album) {
				
				// *** Test if we want to include the Profile Pictures album
				if (($includeProfileAlbum || strtolower($album['name']) != 'profile pictures')) {
				
					$albumNamesArray[$album['id']] = $album['name'];
				}
			}
		}
		unset($albumsData);
		return $albumNamesArray;
	}		
	
	## --------------------------------------------------------
		
	public function getAlbumId($albumName)
	#
	#	Author:		Jarrod Oberto
	#	Date:		June 11
	#	Purpose:	Get the id of an album
	#	Params in:  (str) $albumName: the name of the album to return the id for.
	#	Params out:	Returns an albums id.
	#	Notes:	
	#	Used in:	FB Album Gallery
	#
	{
		// *** Test if already an id
		if ($this->_testIfId($albumName)) {
			return $albumName;
		}

		$id = 0;
		
		// *** Get album names
		$albumNamesArray = $this->getAlbumNames();

		if (count($albumNamesArray) > 0) {

			// *** Loop through each album
			foreach ($albumNamesArray as $albumId => $value) {
				
				// *** If the name already exists...
				if (strtolower($albumName) == strtolower($value)) {

					// *** ...return the id
					return $albumId;
				}
			}
		}	
		
		unset($albumNamesArray);
		return $id;
	}	
		
	## --------------------------------------------------------
	
	public function getAlbumDataRaw($albumName = '')
	#	Used in:	FB Album Gallery
	#	
	{
		if ($albumName == '') {
		
			// *** Get all the data
			return $this->_getAlbumDataRaw();
		} else {
			$dataArray = $this->_getAlbumDataRaw();

			if ($dataArray['data']) {
				foreach ($dataArray['data'] as $key => $albumArray) {

					if ($albumArray['name'] == $albumName || $albumArray['id'] == $albumName) { # Fix. Added album id check (v1.3)

						return $albumArray;
					}
				} 
			}
		}
	}
	
	## --------------------------------------------------------
	
	public function getAlbumPhotoDataRaw($albumName, $limit=150, $offset=0) 
	#	Used in:	FB Album Gallery
	#			
	{
		return $this->_getAlbumPhotoDataRaw($albumName, $limit, $offset);
	}
	
	## --------------------------------------------------------
	
	public function getFriendId($friendName)
	#
	#	Author:		Jarrod Oberto
	#	Date:		
	#	Purpose:	
	#	Params in:  
	#	Params out:	
	#	Notes:	
	#	Used in:	FB Simple API
	#
	{
		// *** Test if already an id
		if ($this->_testIfId($friendName)) {
			return $friendName;
		}

		$id = 0;
		
		// *** Get friend names
		$friendDataArray = $this->_getFriendsRaw();
	
		if (count($friendDataArray['data']) > 0) {

			// *** Loop through each friend
			foreach ($friendDataArray['data'] as $value) {
				
				// *** If the name already exists...
				if (strtolower($friendName) == strtolower($value['name'])) {

					// *** ...return the id
					return $value['id'];
				}
			}
		}	
		
		unset($friendDataArray);
		return $id;
	}		
	
	
	/*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-* 
	 *	Facebook Update/Add Methods
	 * 	
	 */
	
	public function addStatus($status)
	#	Used in:	FB Simple APi
	#			
	{
	
		if ($this->_user) {
			
			try {
				
				$dataArray = array(
					'message' => $status
					);					
				
				if ($this->_isPage && REPLY_AS_PAGE) {
					$dataArray['access_token'] = $this->_getPageToken();
				}					
								
				$publishStream = $this->_facebookObj->api('/' . $this->_userId . '/feed', 'post', $dataArray);
				
				if (TRACE) {$this->_trace[] = 'addStatus'; }
				return true;
			} catch (FacebookApiException $e) {
				$this->_errorArray[] = 'Could not set status.';
				$this->_debugArray[] = $e;
				return false;
			}
		}
	}
		
	## --------------------------------------------------------
	
	public function addLink($status, $link='', $image='', $name='', $description='', $clearCacheFile=true)
	#	Used in:	FB Simple APi
	#	Notes:		$image HAS to be a URL		
	{
	/*
	$attachment = array('message' => $message, 'access_token' => $ACCESS_TOKEN);

	$result = $facebook->api('/'.$PAGE_ID.'/feed', 'post', $attachment);
*/

		if ($this->_user) {
			
			try {


				$dataArray = array(
					'message' => $status, 
					'link'    => $link,
					'picture' => $image,
					'name'    => $name,
					'description'=> $description
					);

				if ($this->_isPage && REPLY_AS_PAGE) {
					$dataArray['access_token'] = $this->_getPageToken();
				}	

				$publishStream = $this->_facebookObj->api('/' . $this->_userId . '/feed', 'post', $dataArray);
				
				if ($clearCacheFile) { 	cacheManager::clearCache('_getStatus'); }
				if ($clearCacheFile) { 	cacheManager::clearCache('_getFeed'); }
				if (TRACE) {$this->_trace[] = 'addStatus'; }
				return true;
			} catch (FacebookApiException $e) {
				$this->_errorArray[] = 'Could not set status.';
				$this->_debugArray[] = $e;
				return false;
			}
		}
	}	
	
	## --------------------------------------------------------
	
	public function addComment($objId, $comment, $clearCacheFile=true)
	#
	#	Author:		Jarrod Oberto
	#	Date:
	#	Purpose:	
	#	Params in:	(int) $objId:
	#				(str) $comment:
	#				(bo0l) $clearCacheFile: If we don't clear this file from the
	#					we won't be able to immediatley display the comment as
	#					it won't exist in the cache!
	#	Params out:
	#	Notes:	
	#	Permission:	publish_stream
	#	Used in:	FB Album Gallery
	#
	{

		if ($this->_user) {
			try {
				
				$dataArray = array(
					'message' => $comment
					);						
				
				if ($this->_isPage && REPLY_AS_PAGE) {
					$dataArray['access_token'] = $this->_getPageToken();
				}	
				
				$id = $this->_facebookObj->api('/' . $objId. '/comments', 'post', $dataArray);
				if ($clearCacheFile) { 	cacheManager::clearCache('_getAlbumPhotoDataRaw'); }
				if (TRACE) {$this->_trace[] = 'addComment'; }
				return $id;
			} catch (FacebookApiException $e) {
				$this->_errorArray[] = 'Could not set status.';
				$this->_debugArray[] = $e;
				return false;
			}
		}		
	}
	
	## --------------------------------------------------------
	
	public function addEvent($name, $location='', $start='', $end='', $description='', $image='', $clearCacheFile=true)
	#
	#	Author:		Jarrod Oberto
	#	Date:
	#	Purpose:	
	#	Params in:	(int) $objId:
	#				(str) $comment:
	#				(bo0l) $clearCacheFile: If we don't clear this file from the
	#					we won't be able to immediatley display the comment as
	#					it won't exist in the cache!
	#	Params out:
	#	Notes:		If left the param $image incase I enable this in the future
	#				as it can be used - but it's not documented!
	#	Permission:	create_event
	#	Used in:	FB Album Gallery / Events
	#
	{

		if ($this->_user) {
			try {
				
				$dataArray = array(
					'name' => $name,
					'location' => $location,
					'start_time' => $start,
					'end_time' => $end,
					'description'=> $description
					);	
				
				if ($this->_isPage && REPLY_AS_PAGE) {
					$dataArray['access_token'] = $this->_getPageToken();
				}	
				
				
				$id = $this->_facebookObj->api('/' . $objId. '/events', 'post', $dataArray);
				if ($clearCacheFile) { 	cacheManager::clearCache('_getEvents'); }
				if (TRACE) {$this->_trace[] = 'addComment'; }
				return $id;
			} catch (FacebookApiException $e) {
				$this->_errorArray[] = 'Could not set status.';
				$this->_debugArray[] = $e;
				return false;
			}
		}		
	}
	
	## --------------------------------------------------------
	
	public function getUserId()
	{
		return $this->_userId;
	}

	## --------------------------------------------------------

	private function _getPageToken($save=false)
	#	If we save it, we can retrienve it with this: 
	#			$token = $this->_readAccessToken(PUBLIC_USER);
	{
	
		$accountsArray = $this->_getAccountsRaw();
					
		if ($accountsArray['data']) {
			foreach($accountsArray['data'] as $account){
				if($account['id'] == PUBLIC_USER){

					$accessToken = $account['access_token'];
					
					if ($save) {
						$this->_saveAccessToken(PUBLIC_USER, $accessToken);
					}
					return $accessToken;
				}
			}
		}
	}	
	
	## --------------------------------------------------------
	
	public function _customApiCall($api, $useCurrentUser=true, $name='_customApiCall')
	{
		if ($useCurrentUser) {
			$call = '/' . $this->_userId . '/' . $api;
			return $this->_getAPI($call, $name);	
		} else {
			return $this->_getAPI($api, $name);		
		}
	}		
		
	/*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-* 
	 *	Error Handling Methods
	 * 	
	 */
	
	public function getLastError()
	{
		
	}
	
	## --------------------------------------------------------
	
	public function getErrors()
	{
		return $this->_errorArray;
	}
	
	## --------------------------------------------------------
	
	public function getError()
	{
		if (isset($this->_errorArray[0])) {
			return  $this->_errorArray[0];
		} else {
			return '';
		}
	}
	
	## --------------------------------------------------------
	
	public function getDebugErrors()
	{
		return $this->_debugArray;
	}
	
	## --------------------------------------------------------
	
	public function getTrace()
	{
		return $this->_trace;
	}	
	

## _____________________________________________________________________________	
## ________                 ____________________________________________________
## ________ PRIVATE METHODS ____________________________________________________
## _____________________________________________________________________________
##	
	
	/*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-* 
	 *	FB API CALLS
	 * 	
	 */	
		
	protected function _getUserProfileRaw()
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jun 11
	#	Purpose:	Get user profile information through FB API
	#	Params in:
	#	Params out:	(array) Facebook data
	#	Notes:	
	#	Used in:	All
	#
	{	
		$name = '_getUserProfileRaw';
		$api = '/' . $this->_userId;
		return $this->_getAPI($api, $name);				
	}	
	
	## --------------------------------------------------------

public function _getFriendProfile($id='574641862')
{
# 574641862
# 500303954
		$name = '_getFriendProfilex';
		$api = '/' . $id . '/';
echo 'vv';
		return $this->_getAPI($api, $name);

}

	## --------------------------------------------------------
	
	protected function _getAlbumPhotoDataRaw($albumName, $limit=999, $offset=0, $useCache=true)
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jun 11
	#	Purpose:	Get info on all the photos within an album such as comments
	#	Params in:	(str) $albumName: the name of the album to get photo info on
	#	Params out:	(array) Facebook data
	#	Notes:	
	#	Used in:	FB Album Gallery
	#
	{
		$albumId = $this->getAlbumId($albumName);
	
		$name = '_getAlbumPhotoDataRaw';
		$api = '/' . $albumId . '/photos?limit=' . $limit . '&offset=' . $offset  . '&date_format=U' ;
		return $this->_getAPI($api, $name, $albumId);			
	}		
	
	## --------------------------------------------------------
	
	protected function _getAlbumDataRaw()
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jun 11
	#	Purpose:	Get RAW data on albums, such as the album names, descriptions, etc
	#	Params in:
	#	Params out:	(array) Facebook data
	#	Notes:	
	#	Used in:	FB Album Gallery
	#
	{	
		// *** Make cache file unique for different users
		$fileKey = $this->_userId;
		$fileKey = Helper::makeFilename($fileKey);		
		
		$name = '_getAlbumDataRaw-' . $fileKey;
		$api = '/' . $this->_userId . '/albums';
		return $this->_getAPI($api, $name);			
	}		
	
	## --------------------------------------------------------
	
	protected function _getFriendsRaw() 
	#	FB Simple API
	{	
		$name = '_getFriendsRaw';
		$api = '/' . $this->_userId . '/friends';
		return $this->_getAPI($api, $name);		
	}	
	
	## --------------------------------------------------------
	
	protected function _getStatusRaw()
	#	requires: read_stream
	#	FB Simple API
	{
		$name = '_getStatus';
		$api = '/' . $this->_userId . '/statuses?date_format=U';
		return $this->_getAPI($api, $name);
	}
	
	## --------------------------------------------------------
	
	protected function _getFeedRaw()
	#	requires: read_stream
	#	FB Simple API
	{
		$name = '_getFeed';
		$api = '/' . $this->_userId . '/feed?date_format=U';
		return $this->_getAPI($api, $name);
	}	
	
	## --------------------------------------------------------
	
	protected function _getRequests()
	#	TO BE COMPLETED
	{
		$name = '_getRequests';
		$api = '/' . $this->_userId . '/invitations';
		return $this->_getAPI($api, $name);
	}	
	
	## --------------------------------------------------------
	
	protected function _getMail_legacy()
	#	FACEBOOK ARE IN THE MIDDLE OF CHANGING THIS - this is the current stable
	#		but is soon to be superseded
	{
		$name = '_getMail';
		$api = '/' . $this->_userId . '/inbox';
		return $this->_getAPI($api, $name);
	}	
	
	public function _getMail()
	#	NEW MAIL API  - currently not stable
	{
		$name = '_mailbox_folder';
		//$api = '/' . $this->_userId . '/threads';

		return $this->_getAPI($api, $name);
	}		
	
	
	## --------------------------------------------------------
	
	protected function _getEventsRaw($dateFormat='U')
	#	requires:	user_events - for non-public events
	#				friends_events - for non-public events of the user's friends
	#	FB Simple API
	{
		/*$name = '_getEvents';
		$api = '/' . $this->_userId . '/events?date_format=' . $dateFormat;
		return $this->_getAPI($api, $name);*/
		$multiQuery = '{
         "event_list":"SELECT eid, name FROM event WHERE eid IN (SELECT eid FROM event_member WHERE uid = me())",
         "event_rsvp":"SELECT eid, rsvp_status FROM event_member WHERE uid = me()"
         }';
 
		$params = array(
		     'method' => 'fql.multiquery',
		     'queries' => $multiQuery,
		     'callback' => '');
		return $this->_facebookObj->api($params);
	}	
	
	protected function _getEventImageRaw($eventId, $size = 'large')
	#	requires:	user_events - for non-public events
	#				friends_events - for non-public events of the user's friends
	#	FB Simple API
	{
		$multiQuery = '{
         "event_list":"SELECT eid, name FROM event WHERE eid IN (SELECT eid FROM event_member WHERE uid = me())",
         "event_rsvp":"SELECT eid, rsvp_status FROM event_member WHERE uid = me()"
         }';
 
		$params = array(
		     'method' => 'fql.multiquery',
		     'queries' => $multiQuery,
		     'callback' => '');
		//Create Query
		/*$params = array(
			array(
			    'method' => 'fql.query',
			    'query' => 'SELECT eid, name FROM event WHERE eid IN (SELECT eid FROM event_member WHERE uid = me())'
			),
			array(
			    'method' => 'fql.query',
			    'query' => 'SELECT eid, rsvp_status FROM event_member WHERE uid = me()'
			)
		);*/
		return $this->_facebookObj->api($params);
	}
	
	/**
 * Get an event from Facebook graph
 * @param type $eid
 * @param type $showguests
 * @param type $limit
 * @return type 
 */
    protected function getEvent($eid, $showguests=true, $limit=20) {

        //$this->setAccessToken($this->token);
        $batch = array(
            array("method" => "GET",
                "name" => "get-events",
                "omit_response_on_success" => false,
                "relative_url" => "$eid?"//.urlencode('&date_format=U')
            )
            ,
            array("method" => "GET",
                "relative_url" => "method/fql.query?query=SELECT+name,venue,host,creator,pic,pic_small,description,location,start_time,end_time+FROM+event+WHERE+eid+=" . $eid
            )
        );


        if ($showguests) {

            $batch[] = array("method" => "GET",
                "relative_url" => "method/fql.query?query=SELECT+name,username,id,url,pic,pic_square+FROM+profile+WHERE+id+IN+(select+uid+from+event_member+where+eid=$eid+AND+rsvp_status='attending'+LIMIT+$limit)"
            );
        }

        $batch = json_encode($batch);
		echo $batch;
        $eventa = $this->_facebookObj->api('/?batch=' . $batch, 'POST');

        return $eventa;
    }
    
	## --------------------------------------------------------
	
	protected function _getAccountsRaw()
	#	requires:	manage_pages
	{
		$name = '_getAccountsRaw';
		$api = '/' . $this->_user . '/accounts'; #FIX
		return $this->_getAPI($api, $name);		
	}
	
	## --------------------------------------------------------
	
	protected function _getAPI($api, $name, $dataId=0, $useCache=true)
	{
		// *** Try from memory
		if (MEMORY_CACHE_ENABLED) {
			if (isset($this->_memoryCache[$name][$dataId])) {
				if (TRACE) {$this->_trace('memory', $name); }
				return $this->_memoryCache[$name][$dataId];
			}
		}	
		
		// *** Try from cache
		if (CACHE_ENABLED && $useCache) {
			$data = CacheManager::readFromCache($name, $dataId);
			if ($data) {		
				
				if (MEMORY_CACHE_ENABLED) {
					$this->_memoryCache[$name][$dataId] = $data;	
				}
				
				if (TRACE) {$this->_trace('file', $name); }
				return $data;
			}
		}
		
		// *** Try from FB
		if ($this->_user) {
			try {		

				$data = $this->_facebookObj->api($api); 
		
				if (MULTI_USER) {
					$name = $this->_userId . $name;
				}

				if (MEMORY_CACHE_ENABLED) {
					$this->_memoryCache[$name][$dataId] = $data;	
				}
				
				if (CACHE_ENABLED) {
					CacheManager::writeToCache($name, $data, $dataId);
				}

				if (TRACE) {$this->_trace('api', $name); }
				return $data;
			} catch (FacebookApiException $e) {
				$this->_errorArray[] = 'Could not get ' . $name . ' data.';
				$this->_debugArray[] = $e;		
				return false;
			}
		}			
	}	

	/*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-*-* 
	 *	OTHER
	 * 	
	 */		

	private function _trace($path, $name)
	{
		$this->_trace[$path][] = $name;
	}
	
	## --------------------------------------------------------
	
	protected function _testIfId($value)
	# helper
	{
		if ((strlen($value) >= 10 && strlen($value) <= 20) && is_numeric($value)) {
			return true;
		} else {
			return false;
		}
	}
	
	## --------------------------------------------------------
	
	protected function _resolveImageSize($size)
	{
		$size = strtolower($size);
		switch ($size) {
			case 'square':
				$returnSize = 'square';
				break;
			case 'small':
				$returnSize = 'small';
				break;
			case 'normal':
				$returnSize = 'normal';
				break;
			case 'large':
				$returnSize = 'large';
				break;

			default:
				$returnSize = 'normal';
				break;
		}
		
		return $returnSize;
	}	
	
	## --------------------------------------------------------
	
	private function _userAccessToken($tokenedUser)
	#
	#	Author:		Jarrod Oberto
	#	Date:		July 11
	#	Purpose:	Login to facebook with the current token
	#	Params in:	(str) $tokenedUser: The user of the token you want
	#	Params out:	(bool) true is successful, else false
	#	Notes:	
	#
	{		
		if (isset($tokenedUser) && $tokenedUser != 'me') {
			
			/*
			 *	If a token is specified, change to that token
			 * 
			 */
					
			$token = '';
			
			if (!MULTI_USER) {
				$tokenedUser = 'primary';
			}
			
			
			// *** Get the token from file
			$tokenArray = $this->_readAccessToken($tokenedUser);

			if (isset($tokenArray['token'])) {
				$token = trim($tokenArray['token']);
			}
			
			if (isset($tokenArray['id'])) {
				$userId = $tokenArray['id'];
			}			
			
			if ($token != '' && $userId == $tokenedUser) {
				try{
					
					// *** Try to log in with this token
					if ($this->_facebookObj->setAccessToken($token)) {
						$this->_token = $token;
						unset($tokenArray);
						return true;
					}
				} catch (FacebookApiException $e) {
					$this->_errorArray[] = 'Could not set access token.';
					$this->_debugArray[] = $e;
				}	
			} else {
				$this->_debugArray[] = 'User token not set.';				
			}
		} else {
			
			/*
			 *	Else, keep using the current token.
			 *	This path is taken when authorising a user
			 * 
			 */			
			
			$this->_debugArray[] = 'Tokened user not specified. Using current token.';
			return true; # return true as this is valid
		}
		return false;
	}
		
	## --------------------------------------------------------
	
	private function _readAccessToken($tokenedUser)
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jul 11
	#	Purpose:	Read token from file
	#	Params in:	(str) $tokenedUser: The user of the token you want
	#	Params out: (array) Array of token id and token
	#	Notes:	
	#
	{
		if (USE_CUSTOM_DATA_STORE) {

			return $this->_customReadAccessToken($tokenedUser);
		}
		
		$filename = str_replace('//','/',dirname(__FILE__).'/') .'..//signatures/tokens.txt';
		
		$jsonData = '';
		$dataArray = array();
		$tokenArray = array();
		
		if (file_exists($filename)) {
			
			$jsonData = file_get_contents($filename);
			$dataArray = json_decode($jsonData, true);

			reset($dataArray);
								
			if (isset($dataArray[$tokenedUser])) { 
				$tokenArray['token'] = $dataArray[$tokenedUser];
				$tokenArray['id'] = $tokenedUser;	
			}
					
			unset($dataArray);
			return $tokenArray;
		} else {
			
			return false;
		}
	}
	
	## --------------------------------------------------------
	
	protected function _saveAccessToken($id, $token)
	{
		
		if (USE_CUSTOM_DATA_STORE) {

			return $this->_customSaveAccessToken($id, $token);
		}

		try{		

			$jsonData = '';
			
			// *** Set the token file filename
			$filename = dirname(__FILE__) . '/../signatures/tokens.txt';

			// *** If this a single user account I.E., user is "primary". THe MULTI_USER overrides the single user
			if (strtolower(AUTH_USER) == 'primary' && MULTI_USER == false) {
				
				// *** Set the id to "primary" instead of the user id
				$id = 'primary';
			}
				
			// *** See if the file already exists
			if (file_exists($filename)) {
				
				// *** If so, get the file contents so we can add to it
				$jsonData = file_get_contents($filename, $jsonData);

				// *** Decode the json contents into an array
				$currentDataArray = json_decode($jsonData, true);
	
			} else {

				// *** Else, just create a new array to add our data to
				$currentDataArray = array();
			}
					
			// *** Add our id and token to our array
			$currentDataArray[$id] = $token;

			
			// *** JSON encode
			$jsonData = json_encode($currentDataArray);

			// *** Get the path
			$pathInfo = pathinfo($filename)	;			
			
			// *** Make sure it writable
			if (is_writable($pathInfo['dirname'])) {
				
				// *** Save array contents
				file_put_contents($filename, $jsonData);
				
				return true;
			} else {
				$this->_debugArray[] = 'Directory not writable.';	
				return false;
			}
			
		} catch (FacebookApiException $e) {
			$this->_errorArray[] = 'Could not save access token.';
			$this->_debugArray[] = $e;
			return false;
		}	
	}
	
	
	## --------------------------------------------------------

	private function _customReadAccessToken($id)
	{

		// *** DB select
		
	}
	
	## --------------------------------------------------------
	
	private function _customSaveAccessToken($id, $token)
	{

		// *** DB insert/update
		
	}	

	## --------------------------------------------------------
	
}


   # ========================================================================#
   #
   #  Author:    Jarrod Oberto
   #  Version:	 1.0
   #  Date:      Jun 11
   #  Purpose:   Helper functions.
   #  Project:	 Facebook Graph API.	
   #  Param In:  See functions.
   #  Param Out: n/a
   #  Requires : 
   #
   # ========================================================================#


class Helper
{
	
	public static function makeFilename($string)

	/*
     *	Author:		Jarrod Oberto
     *  Date:		21-September-09
     *	Purpose: 	generate a standardised filename for naming and reading images
	 *	Requires:   normalize method
     *	Params in:	The unformatted filename
     *	Params out:	The formatted filename E.G.,
	 *					X-Men Origins: Wolverine = x-men_origins:_wolverine
	 *					L'invité = linvite
     *
     */
	{
		// *** Trim
		$string = trim($string);

		// *** Replace spaces with underscores
		$string = str_replace(' ', '_', $string);

		// *** Replace double underscores with a single underscore
		$string = str_replace('__', '_', $string);

		// *** Replace '&' (ampersand) with the word 'and'
		$string = str_replace('&', 'and', $string);

		// *** Convert acsented chars to their english equivelant.
		$string = self::normalize($string);

		// *** Remove dirty chars (keep: alphas nums _ : -)
		$string = preg_replace('/[^a-zA-Z0-9_:-]/', '', $string);

		// *** Make string lowercase
		$string = strtolower($string);

		return $string;
	}	
	
	## --------------------------------------------------------
	
	public static function normalize ($string)

	/*
     *	Author:		allixsenos
     *  Date:		22-September-09 (implmented)
	 *  Ref:		http://uk3.php.net/strtr
     *	Purpose: 	Convert acsented chars to their english equivelant.
     *	Params in:	The string to normalize
     *	Params out:	The normalized string E.G.,
	 *					L'invité = l'invite
     *
     */
	{
		$table = array(
			'Š'=>'S', 'š'=>'s', '�?'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', '�?'=>'c', 'Ć'=>'C', 'ć'=>'c',
			'À'=>'A', '�?'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
			'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', '�?'=>'I', 'Î'=>'I', '�?'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
			'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', '�?'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
			'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
			'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
			'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
			'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
		);

		return strtr($string, $table);
	}	
	
}

   # ========================================================================#
   #
   #  Author:    Jarrod Oberto
   #  Version:	 1.0
   #  Date:      Jun 11
   #  Purpose:   Manage the cached files.
   #  Project:	 Facebook Graph API.	
   #  Param In:  See functions.
   #  Param Out: n/a
   #  Requires : 
   #
   # ========================================================================#



class CacheManager
{
	
	private static $_timestamp;
	private static $_cacheMinutes;
	private static $_cacheId;
	
	public static function manageCache($cacheMinutes = 120, $userId='')
	{
		
		if (MULTI_USER) {
			self::$_timestamp = dirname(__FILE__) . '/' . CACHE_FOLDER . '/' . $userId . '_timestamp.txt';
			self::$_cacheId = $userId;
		} else {
			self::$_timestamp = dirname(__FILE__) . '/' . CACHE_FOLDER . '/timestamp.txt';
		}		

		self::$_cacheMinutes = $cacheMinutes;
		$cacheTime = $cacheMinutes * 60; 
		
		// Serve from the cache if it is younger than $cachetime
		if (file_exists(self::$_timestamp) && (time() - $cacheTime > filemtime(self::$_timestamp))) {		
			
			/*
			 *	If $cacheTime time expires, delete the cached contents 
			 * 
  			 */

			// *** delete all files in the folder	
			CacheManager::clearCache();
			
		} else {
			


		}
	}	
	
	## --------------------------------------------------------
	
	public static function clearCache($cacheName='', $force=false)
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jul 11
	#	Purpose:	Delete files in the cache dir
	#	Params in:
	#	Params out:
	#	Notes:	
	#
	{

		if (!CACHE_DELETABLE && !$force) {
			return false;
		}
		
		$cachDir = dirname(__FILE__) . '/' . CACHE_FOLDER;
		
		// *** Select single cache file to remove
		if ($cacheName != '') {
			
			if (MULTI_USER) {
				$cacheName = self::$_cacheId . $cacheName;
			}

			$cachDir = $cachDir . '/' . $cacheName . '.txt';
			
			@unlink($cachDir);
			
		} else {
			
			// *** Delete the entire cache
			
			$cachDir = $cachDir . '/*.txt';

			/*
			 * Glob on windows may need a '/' at the begining of the path!
			 * http://www.php.net/manual/en/function.glob.php#49483
			 * 
			 */
			$winFix = '';
			if (self::_is_windows_server()) {
				$winFix = "/";
			}

			$filesArray = glob($cachDir);

			if ( is_array ( $filesArray ) ) {
				if (count($filesArray) > 0) {
					foreach ($filesArray as $filename) {

						if (MULTI_USER && strpos($filename, self::$_cacheId) !== false) {
							unlink($filename);
						} 
						else if (!MULTI_USER) {
							unlink($filename);
						}
					}
				}
			}
					
		}
	}
	
	## --------------------------------------------------------
	
	public static function writeToCache($file, $data, $dataId = 0) 
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jul 11
	#	Purpose:	Write cache data
	#	Params in:	(str) $file: the file name (no extension)
	#				(array) $data: array of data to cache (write)
	#				(mixed) $dataId: an id to identify the array element 
	#	Params out: 
	#	Notes:	
	#
	{
		
		try{		

			$jsonData = '';
			
			$filename = dirname(__FILE__) . '/' . CACHE_FOLDER . '/' . $file . '.txt';
			
			
			if (file_exists($filename)) {
				$jsonData = file_get_contents($filename, $jsonData);

				$currentDataArray = json_decode($jsonData, true);
			} else {
				$currentDataArray = array();
			}

			$currentDataArray[$dataId] = $data;

			$jsonData = json_encode($currentDataArray);


			$pathInfo = pathinfo($filename)	;			
			if (is_writable($pathInfo['dirname'])) {
				file_put_contents($filename, $jsonData);
				CacheManager::writeCacheTimeStamp();
				return true;
			} else {
				$this->_debugArray[] = 'Directory not writable.';	
				return false;
			}
			
		} catch (FacebookApiException $e) {
			$this->_errorArray[] = 'Could not cache data.';
			$this->_debugArray[] = $e;
			return false;
		}	
	}	
	
	## --------------------------------------------------------
	
	public static function writeCacheTimeStamp()
	#
	#	Author:		Jarrod Oberto
	#	Date:		July 11
	#	Purpose:	Writes a file used as a timestamp. The data in the file is 
	#				not important (although it does show the date) it's the 
	#				actual files date/time that is read.	
	#	Params in:
	#	Params out:
	#	Notes:	
	#
	{
		//$timestamp = dirname(__FILE__) . '/' . CACHE_FOLDER . '/timestamp.txt';
		$timestamp = self::$_timestamp;
		
		
		if (!file_exists($timestamp)) {
			$currentTime = date("F j, Y, g:i a");
			$expireTime = 'Expiry set to: '. self::$_cacheMinutes . ' minutes. (' . self::$_cacheMinutes / 60 . ' hrs)';
			@file_put_contents($timestamp,$currentTime . "\n" . $expireTime);
		}
		
	}
	
	## --------------------------------------------------------
	
	public static function readFromCache($file, $dataId=0)
	#
	#	Author:		Jarrod Oberto
	#	Date:		Jul 11
	#	Purpose:	Read a file from the cache
	#	Params in:	(str) $file: the file name (no extension)
	#				(mixed) $dataId: an id to identify the array element 
	#	Params out:
	#	Notes:	
	#
	{
		$filename = dirname(__FILE__) . '/' . CACHE_FOLDER . '/' . $file . '.txt';
		
		$jsonData = '';
		$dataArray = array();
	
		
		if (file_exists($filename)) {
			$jsonData = file_get_contents($filename);

			$dataArray = json_decode($jsonData, true);

			unset($jsonData);
			if (isset($dataArray[$dataId])) { 
		
				return $dataArray[$dataId];
				
			} else {
				return false;
			}
			
		} else {
			
			return false;
		}		
	}
	
	## --------------------------------------------------------

	public static function _is_windows_server()
	#	Purpose:	Check if server is Windows
	{
		return in_array(strtolower(PHP_OS), array("win32", "windows", "winnt"));
	}	
		
	## --------------------------------------------------------

}
?>
