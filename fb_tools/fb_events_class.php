<?php

   # ========================================================================#
   #
   #  Author:    Pathik Gandhi
   #  Version:	 1.2
   #  Purpose:   Provides album gallery functionality
   #  Project:	 Facebook Graph API	
   #  Param In:  See functions.
   #  Param Out: n/a
   #  Requires : 
   #
   # ========================================================================#

require_once('fb_core/fb_wrapper_class.php');

class FacebookEvents extends FaceBookWrapper
{
	
## _____________________________________________________________________________	
## ________                _____________________________________________________
## ________ PUBLIC METHODS _____________________________________________________
## _____________________________________________________________________________
##	
	private $_eventData = array();
	private $_eventPass = array();
	
	public function __construct($appId, $secretId, $redirectURL, $userId = 'me', $publicUserIs=null, $cookies = true) {
		parent::__construct($appId, $secretId, $redirectURL, $userId, $publicUserIs, $cookies);
	}
	
	## --------------------------------------------------------
	public function getEvents()
	{
		$events = $this->_getEventsRaw();
		$this->_eventData = $events;
		return $this->getEventData();
	}
	
	public function getEventData()
	{
		foreach ($this->_eventData as $evdata)
		{
			$this->_eventPass[$evdata['name']] = $evdata['fql_result_set'];
		}
		
		return $this->_eventPass;
	}


	public function getEventImage($eventId, $size = 'large')
	{
		$event_image = $this->_getEventImageRaw($eventId);
		echo '<pre>';
		print_r($event_image);
		exit;
		return $event_image;
	}	
	
	public function getAccountsRaw(){
		print_r($this->_getAccountsRaw());
	}
	## --------------------------------------------------------
	
	private function _is_windows_server()
	#	Purpose:	Check if server is Windows
	{
		return in_array(strtolower(PHP_OS), array("win32", "windows", "winnt"));
	}
	
	## --------------------------------------------------------
}
?>
