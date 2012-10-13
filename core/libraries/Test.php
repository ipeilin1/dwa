<?php

require_once(CORE.'vendors/simpletest/autorun.php');

class Test {
		
	# Paths to search for tests	
	protected static $paths;
	
	# How we identify the test files
	protected static $test_postfix = "_Test.php";
	
		
	/*-------------------------------------------------------------------------------------------------
	
	-------------------------------------------------------------------------------------------------*/
	public static function run() {
	
		# All the directories we'll look for tests
		$dirs = array(
			CORE.'/libraries/',
			APP_PATH.'/controllers/',
			APP_PATH.'/libraries/',
		);	
		
		# Loop through the directories
		foreach($dirs as $dir) {
		
			foreach(glob($dir."*".$test_postfix) as $file) {
				
				# Run the test file
				require_once($file);
				
			}
			
		}
		
		
		
	}
		

} # end class

?>