<?php
require_once 'google-api/apiClient.php';
require_once 'google-api/contrib/apiPlusService.php';
require_once 'api_config.php';

session_start();

$client = new apiClient();
$client->setApplicationName('Google+ PHP Starter Application');
// Visit https://code.google.com/apis/console?api=plus to generate your
// client id, client secret, and to register your redirect uri.
$client->setClientId($CLIENT_ID);
$client->setClientSecret($CLIENT_SECRET);
$client->setRedirectUri($REDIRECT_URI);
$client->setDeveloperKey($DEVELOPER_KEY);
$plus = new apiPlusService($client);

if (isset($_REQUEST['logout'])) {
  unset($_SESSION['access_token']);
}

if (isset($_GET['code'])) {
  $client->authenticate();
  $_SESSION['access_token'] = $client->getAccessToken();
  header('Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
}

if (isset($_SESSION['access_token'])) {
  $client->setAccessToken($_SESSION['access_token']);
}

if ($client->getAccessToken()) {
  $me = $plus->people->get('me');

  // These fields are currently filtered through the PHP sanitize filters.
  // See http://www.php.net/manual/en/filter.filters.sanitize.php
  $url = filter_var($me['url'], FILTER_VALIDATE_URL);
  $img = filter_var($me['image']['url'], FILTER_VALIDATE_URL);
  $name = filter_var($me['displayName'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
  $personMarkup = "<a rel='me' href='$url'>$name</a><div><img src='$img?sz=82'></div>";

  $optParams = array('maxResults' => 100);
  $activities = $plus->activities->listActivities('me', 'public', $optParams);
  $activityMarkup = '';
  foreach($activities['items'] as $activity) {
    // These fields are currently filtered through the PHP sanitize filters.
    // See http://www.php.net/manual/en/filter.filters.sanitize.php
    $url = filter_var($activity['url'], FILTER_VALIDATE_URL);
    $title = filter_var($activity['title'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $content = filter_var($activity['object']['content'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $activityMarkup .= "<div class='activity'><a href='$url'>$title</a><div>$content</div></div>";
  }

  // The access token may have been updated lazily.
  $_SESSION['access_token'] = $client->getAccessToken();
} else {
  $authUrl = $client->createAuthUrl();
}
?>
<!doctype html>
<html>
<head>
	<link rel='stylesheet' href='style.css' />
	<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
	<script type="text/javascript">
		/*var me = {
			id: '<?php echo $me['id']; ?>',
			developerKey: '<?php echo $DEVELOPER_KEY; ?>',
			url: '<?php echo $url; ?>',
			image: '<?php echo $img; ?>',
			name: '<?php echo $name; ?>'
		};
		*/
	
		function User(data){
	        var properties = {
	            id: data.id,
	            name: data.name,
	            developerKey: data.developerKey,
	            image: data.img,
	            url: data.url                
	        };

	        this.getUserProperties = function(){
				return properties;
	        };
	    }
	    
	    var user = new User({
		    id: '<?php echo $me['id']; ?>', 
		    name: '<?php echo $name; ?>', 
		    developerKey: '<?php echo $DEVELOPER_KEY; ?>', 
		    image: '<?php echo $img; ?>', 
		    url: '<?php echo $url; ?>'
		    });

		
		//called when the document is ready, this initializes jQuery
		$(function(){
			$("a").click(function(){
				//call Google+ api using jQuery ajax
				$.ajax({url:"https://www.googleapis.com/plus/v1/people/"+user.id,
						type:"GET",
						data:{key:user.developerKey,
							prettyprint:false,
							fields:"displayName,image,tagline,url"},
						success: function(data){ 
									console.log(data); 
									var str = '';
									$.each(data, function(key, value){
										str += value;
									});
									$("#container").html(str); 
								},
						cache:true,
						dataType:"jsonp"})					
			});
		});		

	</script>
</head>
<body>
<header><h1>Round Circles</h1></header>
<div class="box">
<a href="#">Click me</a>
<br/>

<div id="container"></div>




<?php //if(isset($personMarkup)): ?>
<div class="me"><?php //print $personMarkup ?></div>
<?php //endif ?>

<?php //if(isset($activityMarkup)): ?>
<div class="activities">Your Activities: <?php //print $activityMarkup ?></div>
<?php //endif ?>


<?php
  if(isset($authUrl)) {
    print "<a class='login' href='$authUrl'>Connect Me!</a>";
  } else {
   print "<a class='logout' href='?logout'>Logout</a>";
  }
?>


</div>
</body>
</html>