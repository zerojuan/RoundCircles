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
	
	this.setUserProperties = function(){
		properties = {
			    id: data.id,
			    name: data.name,
			    developerKey: data.developerKey,
			    image: data.img,
			    url: data.url 
		};
	};
}