<?php
require_once 'google-api/apiClient.php';
require_once 'google-api/contrib/apiPlusService.php';

require_once 'api_config.php';
require_once 'db_config.php';

// Include ezSQL libraries
include_once "ezsql/ez_sql_core.php";
include_once "ezsql/ez_sql_mysql.php";

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
  
  // Initialise database object and establish a connection
  // at the same time - db_user / db_password / db_name / db_host
  $db = new ezSQL_mysql($DB_USER, $DB_PASSWORD, $DB_NAME, $DB_HOST);
  
  
  $user_count = $db->get_var("SELECT count(*) FROM users WHERE user_id = '".$me[id]."'");
  if($user_count > 0){ //if user exists
	$stalkees = $db->get_results("SELECT stalkee FROM stalkees WHERE stalker = '".$me[id]."'");
  }else{ //if you are a new user, initialize your record in the database
	$db->query("INSERT INTO users (user_id, name) VALUES ('".$me[id]."','".$me[displayName]."')");	
	$db->query("INSERT INTO stalkees (stalker,stalkee) VALUES ('".$me[id]."','101805484443568050673')");
	$db->query("INSERT INTO stalkees (stalker,stalkee) VALUES ('".$me[id]."','115885542344045398939')");	
	$db->query("INSERT INTO stalkees (stalker,stalkee) VALUES ('".$me[id]."','108551811075711499995')");	
	$db->query("INSERT INTO stalkees (stalker,stalkee) VALUES ('".$me[id]."','112351852201222657844')");
	$db->query("INSERT INTO stalkees (stalker,stalkee) VALUES ('".$me[id]."','100911896071656032025')");

	$stalkees = $db->get_results("SELECT stalkee FROM stalkees WHERE stalker = '".$me[id]."'");
  }
  
  // These fields are currently filtered through the PHP sanitize filters.
  // See http://www.php.net/manual/en/filter.filters.sanitize.php
  $url = filter_var($me['url'], FILTER_VALIDATE_URL);
  $img = filter_var($me['image']['url'], FILTER_VALIDATE_URL);
  $name = filter_var($me['displayName'], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH);
 
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
<script
	src="http://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"
	type="text/javascript"></script>
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

		//sets search-form events
		function searchFormEvents(){
			$('.menu > li').bind('mouseover', function(){
				$(this).find('div').css('visibility', 'visible');
			});
			
			$('.menu > li').bind('mouseout', function(){
				$(this).find('div').css('visibility', 'hidden');
			});
		}

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

		//checks character count of string
		function countChar(str){
			var tag = 'close';
			var html = '';
			var count = 0;
			var ctr = 0;
			
			if(str.length > 140){
				while(count < 140){
					if(tag == 'close'){
						if(str.charAt(ctr) == '<'){
							tag = 'open';
							html += (str.charAt(ctr) + "/");
						}else{
							count++;
						}
					}
					
					if(str.charAt(ctr) == '>'){
							tag = 'close';
							html += (str.charAt(ctr - 1) + str.charAt(ctr));
					}
					
					ctr++;
				}
				
				return str = str.substring(0, ctr) + "..." + html;
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
			var element = "#" + id + " div.usr-posts:last-child";
			
			$.each(result.items, function(key, value){
				activityType = value.verb; //gets the type of activity
				
				if(value.actor.id == id){ //checks if the post is from user
					
					//console.log("User post: " + JSON.stringify(value.object));
					
					if(value.object.attachments){
						displayName = (value.object.content != null) ? countChar(value.object.content) : ''; //gets the user's post
						attachment = value.object.attachments['0'];
						
						if(attachment.displayName){
							displayName += "<br><br><a href='"+ attachment.url + "'>" + attachment.displayName + "</a>";
							objectType = attachment.objectType;
							
							if(value.object.attachments['1'] != null){
								attachment = value.object.attachments['1'];
								displayName += "<br><br><img class='post-img' src='"+ attachment.fullImage.url + "'/>";
							}
						}
					}else{
						displayName = countChar(value.object.content);
						objectType = 'status';
					}
				}else if(displayName == ''){
					displayName = " on " + value.actor.displayName + "\'s " + activityType + "<br><br><a href='" + value.object.url + "'>" + countChar(value.object.content) + "</a>";
	
					if(value.object.plusoners.totalItems > 0){
						objectType = 'plusone';
					}else if(value.object.replies.totalItems > 0){
						objectType = 'comment';
					}else if(value.object.resharers.totalItems > 0){
						objectType = 'reshare';
					}
				}
				displayName = "<i>" + makePastTense(activityType) + "</i> " + makePhrase(objectType) + " <b>" + displayName + "</b>" + "<br><br><i class='published-date'>" + new Date(value.published).toDateString() + "</i>";
				columns += "<div class='posts'>" + displayName + "</div>";

				displayName = '';
			});
			
			//element = "#" + id + " tbody";
			if(columns == null || columns == ''){
				columns = "<div class='posts'><i>has no public posts...</i></div>";
			}
			$(element).append(columns);			
		}

		function showUserImg(data){
			var thumb = "<img src='"+ resizeImage(data, 30) + "'/>";
			return thumb;
		}
		
		function retrieveUsers(){
			var userIDs = [
				<?php
					//convert the stalkee's ids into javascript
					$str = "";
					foreach($stalkees as $stalkeeId){
						$str .= "'".$stalkeeId->stalkee."',";
					}
					$str = substr($str,0,-1);
					echo $str;
				?>]; 
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
						
						
						$("#activity-urls").append("<div class='activity' id='"+ data.id +"'>"
								+ "<div class='usr-info'><div class='usr-name'>" + data.displayName + showUserImg(data.image.url) + "</div></div>" 
								+ "<div class='usr-posts'></div></div>");
						//get the activities of this user		
						requestActivities(data.displayName, function(result){
								showResults(result, data.id);
							});	
					});			
				}
			});
		}
		
		//called when the document is ready, this initializes jQuery
		$(function(){
			searchFormEvents();
			
			requestUserData(user.id, showLoggedInUser);
			
			retrieveUsers();

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
	<header>
		<h1>Round Circles</h1>
	</header>
	<div class='box'>
		<a class='login' href='<?php print $authUrl ?>'>Connect Me!</a>
	</div>
	<?php
  } else {
  ?>
	<div id="header">
		<div class="header-wrapper">
			<div class="header-btn">
				<a class="header-btn-target" href="#" title="Round Circles"> <span
					class="app-icon"><img src='images/round-circles.png'
						alt='Round Circles' />
				</span> </a>
			</div>
			<ul class="menu">
				<li><a href="#">menu</a>
					<div id="search-form">
						<input type="text" id="query"></input> <a href="#" id="other">Get
							Others</a>
					</div></li>
			</ul>
			<div id="header-user"></div>
		</div>
	</div>
	<div id="other-container"></div>
	<div id="activity-urls" class="activities"></div>

	<?php
  }
?>

</body>
</html>