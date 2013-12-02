<?php


// *** Clear data cache
$cachDir = 'cached_data/*.txt';
deleteContents($cachDir);

// *** Clear image thumb cache
$imgCacheDir = '../album_libs/cache/*.jpg';
deleteContents($imgCacheDir);

// *** Clear token
$tokenDir = '../signatures/tokens.txt';
deleteContents($tokenDir);

// *** Remove Facebook cookie
include_once('logout.php');

function deleteContents($path)
{
	$pathInfoArray = pathinfo($path);
	$folder = $pathInfoArray['dirname'];
	
	// *** If the folder exists
	if (file_exists($folder)) {
	
		$filesArray = glob($path);

		if ( is_array ( $filesArray ) ) {
			if (count($filesArray) > 0) {
				foreach ($filesArray as $filename) {
					@unlink($filename);
				}
			}
		}	
	}
}

?>