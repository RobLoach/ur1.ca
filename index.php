<?php /* index.php ( lilURL implementation ) */

require_once 'includes/conf.php'; // <- site-specific settings
require_once 'includes/lilurl.php'; // <- lilURL class file
require_once 'Net/DNSBL/SURBL.php'; // <- URL blacklisting
require_once 'Net/DNSBL.php'; // <- URL blacklisting

$msg = '';

// if the form has been submitted
if ( isset($_POST['longurl']) )
{
	// This is a write transaction, use the master database
	$lilurl = new lilURL( READ_WRITE );

	// escape bad characters from the user's url
	$longurl = trim(mysql_escape_string($_POST['longurl']));

	// set the protocol to not ok by default
	$protocol_ok = false;
	
	// if there's a list of allowed protocols, 
	// check to make sure that the user's url uses one of them
	if ( count($allowed_protocols) )
	{
		foreach ( $allowed_protocols as $ap )
		{
			if ( strtolower(substr($longurl, 0, strlen($ap))) == strtolower($ap) )
			{
				$protocol_ok = true;
				break;
			}
		}
	}
	else // if there's no protocol list, screw all that
	{
		$protocol_ok = true;
	}
		
	$hostname = strtolower(parse_url($longurl, PHP_URL_HOST));
	
	$surbl = new Net_DNSBL_SURBL();
	
        // Check the user's IP address against SpamHaus

	$dnsbl = new Net_DNSBL();

	if ($dnsbl->isListed($_SERVER['REMOTE_ADDR']))
        {
		$msg = '<p class="error">Your computer is blacklisted; cannot make ur1s!</p>';
	}
	elseif (in_array($hostname, $redirectors))
        {
		$msg = '<p class="error">Already shortened!</p>';
	}      
	elseif ($surbl->isListed($longurl))
	{
		$msg = '<p class="error">Blacklisted URL!</p>';
	}
	elseif ($dnsbl->isListed($hostname))
	{
		$msg = '<p class="error">Blacklisted Host!</p>';
        }				     
	elseif ( !$protocol_ok )
	{
		$msg = '<p class="error">Invalid protocol!</p>';
	}
	elseif ( $lilurl->add_url($longurl) ) // add the url to the database
	{
		if ( REWRITE ) // mod_rewrite style link
		{
			$url = 'http://'.$_SERVER['SERVER_NAME'].'/'.$lilurl->get_id($longurl);
		}
		else // regular GET style link
		{
			$url = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'].'?id='.$lilurl->get_id($longurl);
		}

		$msg = '<p class="success">Your ur1 is: <a href="'.$url.'">'.$url.'</a></p>';
	}
	else
	{
		$msg = '<p class="error">Creation of your ur1 failed for some reason.</p>';
	}
}
else // if the form hasn't been submitted, look for an id to redirect to
{

	// This is a read transaction, use the slave database
	$lilurl = new lilURL( READ_ONLY );

	if ( isSet($_GET['id']) ) // check GET first
	{
		$id = mysql_escape_string($_GET['id']);
	}
	elseif ( REWRITE ) // check the URI if we're using mod_rewrite
	{
		$explodo = explode('/', $_SERVER['REQUEST_URI']);
		$id = mysql_escape_string($explodo[count($explodo)-1]);
	}
	else // otherwise, just make it empty
	{
		$id = '';
	}
	
	// if the id isn't empty and it's not this file, redirect to it's url
	if ( $id != '' && $id != basename($_SERVER['PHP_SELF']) )
	{
		$location = $lilurl->get_url($id);
		
		if ( $location != -1 )
		{
		    $surbl = new Net_DNSBL_SURBL();
	    	    $dnsbl = new Net_DNSBL();
		    
		    if ($surbl->isListed($location) ||
		        $dnsbl->isListed(parse_url($location, PHP_URL_HOST)))
		    {
		        // 410 Gone
			// XXX: cache this result
			header("HTTP/1.1 410 Gone");
		        $msg = '<p class="error">Blacklisted URL!</p>';
		    } else {
			header('Location: '.$location);
		    }
		}
		else
		{
			$msg = '<p class="error">Sorry, but that ur1 isn\'t in our database.</p>';
		}
	}
}

// print the form

?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
     "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html>

	<head>
		<title><?php echo PAGE_TITLE; ?></title>
		
		<style type="text/css">
		body {
			font: .8em, "Trebuchet MS", Verdana, Arial, Helvetica, sans-serif;
			text-align: center;
			color: #333;
			background-color: #fff;
			margin-top: 5em;
		}
		
		h1 {
			font-size: 2em;
			padding: 0;
			margin: 0;
		}

		form {
			width: 28em;
			background-color: #eee;
			border: 1px solid #ccc;
			margin-left: auto;
			margin-right: auto;
			padding: 1em;
		}

		fieldset {
			border: 0;
			margin: 0;
			padding: 0;
		}
		
		a {
			color: #09c;
			text-decoration: none;
			font-weight: bold;
		}

		a:visited {
			color: #07a;
		}

		a:hover {
			color: #c30;
		}

		.error, .success {
			font-size: 1.2em;
			font-weight: bold;
		}
		
		.error {
			color: #ff0000;
		}
		
		.success {
			color: #000;
		}
		
		p.license {
			margin-left: auto;
			margin-right: auto;
			font-size: 1em;
			font-weight: bold;
			width: 300px;
		}
		
		</style>

	</head>
	
	<body onload="document.getElementById('longurl').focus()">
		
		<h1><?php echo PAGE_TITLE; ?></h1>
		
		<?php echo $msg; ?>
		
		<form action="/" method="post">
		
			<fieldset>
				<label for="longurl">Enter a long URL:</label>
				<input type="text" name="longurl" id="longurl" />
				<input type="submit" name="submit" id="submit" value="Make it an ur1!" />
			</fieldset>
		
		</form>

		<p class="license">
	           <a href="http://ur1.ca/">ur1</a> is an <a href="http://ur1.ca/o">Open Service</a> from <a href="http://status.net/">StatusNet Inc.</a>,
		   powered by <a href="http://ur1.ca/p">lilURL</a>.
	           Full <a href="/ur1-source.tar.gz">source</a> available under the terms of the
	           <a href="http://ur1.ca/k">GNU General Public License</a>.
	        </p>
	        <p class="license">
		   <a href="/ur1.ca.txt.gz">UR1 Database</a> is available in
		   <a href="http://ur1.ca/q">tab-separated values</a> format. To the extent possible
	           under law, StatusNet Inc.
	           has <a href="http://ur1.ca/n">waived</a>
		   all copyright, moral rights, database rights, and any other rights that might
		   be asserted over the UR1 Database.
		</p>
	        <p class="license">
		   By using ur1.ca you agree to our <a href="/policy.txt">terms of use</a>.
		</p>
	        <p class="license">
		   Please send email to <strong>abuse</strong> <em>at</em> <strong>ur1</strong> <em>dot</em> <strong>ca</strong>
		   to report abuse of this service. We take spam seriously and will do our best to respond quickly.
		</p>
	</body>

</html>
		
