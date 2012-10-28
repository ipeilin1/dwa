<?php
class users_controller extends base_controller {

	public function __construct() {
		parent::__construct();
		echo "users_controller construct called<br><br>";
	} 
	
	public function index() {
		echo "Welcome to the users's department";
	}
	
	public function signup() {
		echo "This is the signup page";
	}
	
	public function login() {
		echo "This is the login page";
	}
	
	public function logout() {
		echo "This is the logout page";
	}
	
	public function profile($user_name = NULL) {
		
		if($user_name == NULL) {
			echo "No user specified";
		}
		else {
			echo "This is the profile for ".$user_name;
		}
	}

} # end of the class