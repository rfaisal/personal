function loadAjaxContentbyID(id,fileName){
	var ajaxRequest;  // The variable that makes Ajax possible!
	var targetID;
	targetID=document.getElementById(id);
	try{
		// Opera 8.0+, Firefox, Safari
		ajaxRequest = new XMLHttpRequest();
	} catch (e){
		// Internet Explorer Browsers
		try{
			ajaxRequest = new ActiveXObject("Msxml2.XMLHTTP");
		} catch (e) {
			try{
				ajaxRequest = new ActiveXObject("Microsoft.XMLHTTP");
			} catch (e){
				// Something went wrong
				alert("Your browser Does Not Support Ajax! You Will not be able to view this Website!");
				return false;
			}
		}
	}
	ajaxRequest.onreadystatechange = function(){
		if(ajaxRequest.readyState == 4){
			targetID.innerHTML = ajaxRequest.responseText;
		}
	}
	ajaxRequest.open("GET", fileName, true);
	ajaxRequest.send(null); 
	return false;
}