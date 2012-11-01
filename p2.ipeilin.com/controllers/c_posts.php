<?php

class posts_controller extends base_controller {

	public function __construct() {
		parent::__construct();
		
		# Make sure user is logged in if they want to use anything in this controller
		if(!$this->user) {
			die("Members only. <a href='/users/login'>Login</a>");
		}
		
	}
	
	public function add() {
	
		# Setup view
		$this->template->content = View::instance('v_posts_add');
		$this->template->title   = "Add a new post";
			
		# Render template
		echo $this->template;
	
	}
	
	public function p_add() {
			
		# Associate this post with this user
		$_POST['user_id']  = $this->user->user_id;
		
		# Unix timestamp of when this post was created / modified
		$_POST['created']  = Time::now();
		$_POST['modified'] = Time::now();
		
		# Insert
		# Note we didn't have to sanitize any of the $_POST data because we're using the insert method which does it for us
		DB::instance(DB_NAME)->insert('posts', $_POST);
		
		# Quick and dirty feedback
		echo "Your post has been added. <a href='/posts/add'>Add another?</a>";
	
	}
	
	public function index() {

	# Set up view
	$this->template->content = View::instance('v_posts_index');
	$this->template->title   = "Posts";
	
	# Build our query
	$q = "SELECT * 
		FROM posts
		JOIN users USING (user_id)";
	
	# Run our query, grabbing all the posts and joining in the users	
	$posts = DB::instance(DB_NAME)->select_rows($q);
	
	# Pass data to the view
	$this->template->content->posts = $posts;
	
	# Render view
	echo $this->template;
	
	}
	
	
	
}