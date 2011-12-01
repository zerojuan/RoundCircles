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
		var persons = [];

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

		//sets the word into its past tense form
		function makePastTense(str){
			if(isVowel(str.charAt(str.length - 1))){
				return str = str + 'd';
			}else{
				return str = str + 'ed';
			}
		}

		//creates phrase
		function makePhrase(str){
			if(isVowel(str.charAt(0))){
				return str = 'an ' + str;
			}else{
				return str = 'a ' + str;
			}
		}

		//checks letter if vowel
		function isVowel(ltr){
			var vowels = 'aeiou';
			return ((vowels.indexOf(ltr)) > -1);
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
							fields:"id, image, displayName, url"},
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
			var activityType = '';
			var objectType = '';
			var attachment = '';
			var displayName = '';
			
			$.each(result.items, function(key, value){
				if(value.actor.id == id){ //checks if the post is from user
					activityType = value.verb; //gets the type of activity
					
					//console.log("User post: " + JSON.stringify(value.object));
					
					if(value.object.attachments){
						displayName = (value.object.content != null) ? value.object.content : ''; //gets the user's post
						attachment = value.object.attachments['0'];
						
						if(attachment.displayName){
							displayName += "<br><br><a href='"+ attachment.url + "'>" + attachment.displayName + "</a>";
							objectType = attachment.objectType;
						//}else{
							if(value.object.attachments['1'] != null){
								attachment = value.object.attachments['1'];
								displayName += "<br><br><img src='"+ attachment.fullImage.url + "'/>";
							}
						}
					}else{
						displayName = value.object.content;
						objectType = 'status';
					}
	
					displayName = "<i>" + makePastTense(activityType) + "</i> " + makePhrase(objectType) + " <b>" + displayName + "</b>";
					columns += "<div style='padding:10px;margin:0 auto;width:900px;border:1px;border-color:gray;border-style:solid;'>" + displayName + "</div>";
				}

			});
			
			//element = "#" + id + " tbody";
			element = "#" + id;
			if(columns == null || columns == ''){
				columns = "<div style='padding:10px;margin:0 auto;width:900px;border:1px;border-color:gray;border-style:solid;'><i>has no public posts...</i></div>";
			}
			$(element).append(columns);			
		}

		function showUserImg(data){
			var thumb = "<img src='"+ resizeImage(data, 30) + "'/>";
			return thumb;
		}
		
		function retrieveUsers(){
			var userIDs = ['101805484443568050673', '115885542344045398939', '108551811075711499995', '112351852201222657844', '100911896071656032025']; 
			
			console.log(userIDs);
			
			$.each(userIDs, function(key, value){	
				if(value != null){
					requestUserData(value, function(data){
						//add them to the user list
						persons.push(new User({
							id: data.id,
							image: data.image.url,
							name: data.displayName,
							url: data.url
						}));
						
						
						$("#activityUrls").append("<div class='activity' id='"+ data.id +"' style='padding:10px;width:1000px;border:1px;border-color:black;border-style:solid;'>"
								+ "<div class='usrName'>" + data.displayName + "</div>" 
								+ "<div>" + showUserImg(data.image.url) +"</div>"  
								+ "</div>");
						//get the activities of this user		
						requestActivities(data.displayName, function(result){
								showResults(result, data.id);
							});	
					});			
				}
			});
		}

		function showActivity(){
			retrieveUsers();
			
			//console.log(persons);	
			
			$.each(persons, function(key, value){
				var msg = value.id; //+ ", " + value.name + ", " value.image;
				console.log(msg);
				});
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
	<div id="activityUrls" class="activities" style="padding: 10px; margin: 0 auto; width:1100px;"></div>
	
  <?php
  }
?>

</body>
</html>