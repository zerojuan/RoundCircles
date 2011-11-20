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
  $activityUrls = array();
  
  foreach($activities['items'] as $activity) {
    // These fields are currently filtered through the PHP sanitize filters.
    // See http://www.php.net/manual/en/filter.filters.sanitize.php
    $url = filter_var($activity['url'], FILTER_VALIDATE_URL);
    $title = filter_var($activity['title'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $content = filter_var($activity['object']['content'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
    $activityMarkup .= "<div class='activity'><a href='$url'>$title</a><div>$content</div></div>";
    array_push($activityUrls, $url); // get urls and store in array
  }
  //print_r($activityUrls);
  
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
	<script type="text/javascript" src="scripts/User.js"></script>
	<script type="text/javascript">
		//set a user object
		var user = new User({
			id: '<?php echo $me['id']; ?>',
			developerKey: '<?php echo $DEVELOPER_KEY; ?>',
			url: '<?php echo $url; ?>',
			image: '<?php echo $img; ?>',
			name: '<?php echo $name; ?>'
		});

		//storage for user info
		var persons = new Array();

		//sets image size since the default is 50
		function resizeImage(url, size){
			if(url && size){
				var eqIndex = url.indexOf('=');
				if(eqIndex > -1)
					return url = url.substring(0, eqIndex+1) + size.toString();
				else
					return url = url + "?sz=" +size.toString()
			}				
		}
		
		function showLoggedInUser(data){
			var thumb = "<img src='"+ resizeImage(data.image.url, 30) + "'/>";
			var logout = "<a class='logout' href='?logout'>Logout</a>";
			$("#header-user").html("<div>" + user.name + "&nbsp;" + logout + "&nbsp </div> <div>" + thumb + "</div>");
		}
		
		function showSearchResults(data){
			console.log(data);
		}
		
		//gets user data through parameter userId
		function requestUserData(userId, callback){
			$.ajax({url:"https://www.googleapis.com/plus/v1/people/"+userId,
						data:{key:user.developerKey,
							prettyprint:false,
							fields:"displayName,image,tagline,url"},
						success: callback,
						cache:true,
						dataType:"jsonp"});
		}
		
		function requestSearchUsers(query, callback){
			$.ajax({url:"https://www.googleapis.com/plus/v1/people",
						data:{key:user.developerKey,
							prettyprint:false,
							query:query,
							maxResults: 10},
						success: callback,
						cache:true,
						dataType:"jsonp"});
		}
		
		function requestPlusOnersFromActivity(activityId, callback){
			$.ajax({url:"https://www.googleapis.com/plus/v1/activities/"+activityId+"/people/plusoners",
					data:{key:user.developerKey,
						maxResults: 20,
						prettyprint:false},
					success: callback,
					cache: true,
					dataType:"jsonp"
					});
		}

		function requestActivities(username, callback){
			$.ajax({url:"https://www.googleapis.com/plus/v1/activities",
					data:{key:user.developerKey,
						maxResults:20,
						prettyprint:false,
						query:username},
					success: callback,
					cache:true,
					dataType:"jsonp"});
		}

		function showResults(result, id){
			console.log(result);
			var columns = '';
			
			$.each(result.items, function(key, value){
				var displayName = '(No Title)';
				if(value.object.attachments){
					displayName = value.object.attachments['0'].displayName;
				}else{
					displayName = value.title;
				}			
				columns += "<tr><td>"+ displayName +"</td></tr>";

			});
			
			element = "#" + id + " tbody";
			$(element).append(columns);			
		}

		function showUserImg(data){
			var thumb = "<img src='"+ resizeImage(data, 30) + "'/>";
			return thumb;
		}
		
		function retrieveUsers(){
			var activities = <?php echo json_encode($activities); ?>; 
			
			console.log(activities);
			
			//look for users who reshared or +1d 
			$.each(activities.items, function(key, value){
				var displayName = '(No title)'
				
				if(value.object.actor){
					displayName = value.object.actor.image.url;
					//add them to the user list
					persons.push(new User({
						id: value.object.actor.id,
						image: displayName,
						name: value.object.actor.displayName,
						url: value.object.actor.url
					}));
				}				
			});
			
		}

		function showActivity(){
			retrieveUsers();
			
			$.each(persons, function(key, value){
				$("#activityUrls").append("<table class='activity' id='"+ value.id +"' style='border:1px;border-color:black;border-style:solid;'>"
								+ "<tr><th class='usrName'>" + value.name + "</th></tr>" 
								+ "<tr><td>" + showUserImg(value.image) +"</td></tr>"  
								+ "</tbody></table>");
				requestActivities(value.name, function(data){
						showResults(data, value.id);
					}); //uses search from API
				});
			console.log(persons);
		}
		
		//called when the document is ready, this initializes jQuery
		$(function(){
			requestUserData(user.id, showLoggedInUser);

			showActivity();

			$(".view-people").click(function(e){
				//cancel default behaviour
				e.preventDefault();

				requestPlusOnersFromActivity(e.target.id, function(data){
					if(data.items.length > 0){
						$(e.target).next().html("<p><b> &nbsp Plus oned by </b> "+ data.items.length +" people</p>"); //value is not hyperlink
					}else{
						$(e.target).next().html("<p><b> No one plus oned this </b></p>");
					}
					console.log(data);
				});
			});
			
			$("#other").click(function(){
				var input = "'" + $("#query").val() + "'";
				requestSearchUsers(input, showSearchResults);					
			});
		});		
	</script>
</head>
<body>

<?php
  if(isset($authUrl)) {
  ?>
	<header><h1>Round Circles</h1></header>
    <div class='box'> <a class='login' href='<?php print $authUrl ?>'>Connect Me!</a> </div>
  <?php
  } else {
  ?>
	<div id="header">
		<div class="header-wrapper">
			<div class="header-btn">
				<a class="header-btn-target" href="/" title="Round Circles">
					<span class="app-icon">Round Circles</span>
				</a>
			</div>
			<div id="header-user">
			</div>
		</div>
	</div>
	<input type="text" id="query"></input>
	<a href="#" id="other">Get Others</a>
	<div id="otherContainer"></div>
	<div id="activityUrls" class="activities"></div>
	
  <?php
  }
?>

</body>
</html>