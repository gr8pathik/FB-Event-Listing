<?php

/*******************************************************************************

	This is a working demo of the basics. To get this running of your setup, 
	please make sure you do the following:
 
		1) Update the config.php with your settings.
		2) Replace the album name "photos" with an album name or id of one of 
			your albums.
 

 ******************************************************************************/


	require_once('fb_tools/config.php');
	require_once('fb_tools/fb_events_class.php'); 

	$fbObj = new FacebookEvents(APP_ID, SECRET_ID, REDIRECT_URL, AUTH_USER, PAGE_ID);
	
		
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Events</title>
		<meta charset="utf-8" />
		<style> a { text-decoration: none; } </style>
	</head>
	<body>
		<?php //echo $fbObj->getAccountsRaw();
		/*echo '<pre>';
		print_r($fbObj->getEvents());
		echo '<pre>';
		print_r($fbObj->getDebugErrors());
		echo '</pre>';
		echo '<pre>';
		print_r($fbObj->getErrors());
		echo '</pre>'; 
		exit;*/ ?>
		<?php if($fbObj->isLoggedIn()){ ?>
			<?php $eventsArray = $fbObj->getEvents(); ?>
			<b>Your Events</b><br>
			<?php if(!empty($eventsArray)){ ?>
			<ol>
				<?php for ($ev=0;$ev<count($eventsArray);$ev++) { ?>
				<li id="<?php echo $eventsArray[$ev]['id'];?>">
					<div style="float:left">
						<img src="<?php echo $fbObj->getEventImage($eventsArray[$ev]['id']);?>" title="<?php echo $eventsArray[$ev]['name'];?>" />
					</div>
					<div style="float:left">
						<b><?php echo $eventsArray[$ev]['name'];?></b><br />
						at <i><?php echo $eventsArray[$ev]['location'];?></i><br />					
						on <i><?php echo $eventsArray[$ev]['start_time'];?></i><br />
						you are <i><?php echo $eventsArray[$ev]['rsvp_status'];?></i> this event.<br />
					</div>
					<div style="clear:float"></div>
				</li>
				<?php } ?>
			</ol>
			<?php } else { ?>
				<i>You have no Events.</i>
			<?php } ?>
		<?php } else {	?>
			<a href="fb_tools/auth_user.php" title="Login With Facebook"><img src="images/facebook_login_button.png" title="Login With Facebook" /></a>
		<?php } ?>
	</body>
</html>
