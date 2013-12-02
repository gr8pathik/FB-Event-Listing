<?php


// *** Clear data cache
$cachDir = 'cached_data/*.txt';
deleteContents($cachDir);

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
