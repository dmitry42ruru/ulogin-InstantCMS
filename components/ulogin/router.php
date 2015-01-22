<?php

    function routes_ulogin(){

	    $routes[] = array(
		    '_uri'  => '/^ulogin\/index$/i',
		    'do' => 'index',
	    );

	    $routes[] = array(
		    '_uri'  => '/^ulogin\/login$/i',
		    'do' => 'login',
	    );

	    $routes[] = array(
		    '_uri'  => '/^ulogin\/delete_account$/i',
		    'do' => 'delete_account',
	    );

        return $routes;

    }

?>
