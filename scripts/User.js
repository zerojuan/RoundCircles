function User(data){
	this.id = data.id;
	this.name = data.name;
	this.developerKey = data.developerKey;
	this.image = data.img;
	this.url = data.url;
	
	/*
	 * var properties = {
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
	};*/
}