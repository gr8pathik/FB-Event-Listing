<?php
   # ========================================================================#
   #
   #  Author:    Pathik Gandhi
   #  Version:	 1.0
   #  Purpose:   Authenticate a user for offline access
   #  Project:	 Facebook Graph API	
   #  Param In:  See functions.
   #  Param Out: n/a
   #  Requires : 
   #
   # ========================================================================#



require_once('config.php');
require_once('fb_core/fb_wrapper_class.php');

class FacebookAuthUser extends FaceBookWrapper
{
	
	public function __construct($appId, $secretId, $redirectURL, $userId = 'me', $publicUserIs=null, $cookies = true) 
	{
		// *** Clear the cache, else we authenticate any cached users.
		CacheManager::clearCache('', true);
		
		// *** Call parent constructor
		parent::__construct($appId, $secretId, $redirectURL, $userId, $publicUserIs, $cookies);
	}
	
	## --------------------------------------------------------
	
	public function saveAccessToken()
	#
	#	Author:		Pathik Gandhi
	#	Purpose:	Pulic interface for _saveAccessToken()
	#	Params in:	N/A
	#	Params out: (bool) true/false depending on the result
	#	Notes:	
	#
	# 
	{
		$profileId = $this->_profileDataArray['id'];
		return $this->_saveAccessToken($profileId, $this->_facebookObj->getAccessToken($profileId));
		
	}	
		
	## --------------------------------------------------------
	
}
	
	
	//$fbObj = new FacebookAuthUser(APP_ID, SECRET_ID, REDIRECT_URL_AUTH, AUTH_USER);
	$fbObj = new FacebookAuthUser(APP_ID, SECRET_ID, REDIRECT_URL_AUTH);

	if ($fbObj->isLoggedIn()) {
		if ($fbObj->saveAccessToken()) {
			$fbObj->logout();
			if (@count($_GET) == 0) {
				
				echo 'I don\'t think you were logged out of Facebook? Log out and please try again.';
				include_once 'fb_core/reset.php';
			} else {
				echo "User Authenticated.";
				header('Location:../list_events.php');
			}
		}
		else {
			echo "no Access Token";
		}
		
		
	} else {
		
		$fbObj->loginRedirect();
	}
	
	
?>
