function toogleMetatag(idElement) {
	if (jQuery("#idmht_"+idElement).attr('class')=="meta_close") {
		jQuery("#idmht_"+idElement).attr('class', "meta_open"); 
		jQuery("#sm_mht_"+idElement).hide(); 
		jQuery("#h_mht_"+idElement).show(); 
	} else {
		jQuery("#idmht_"+idElement).attr('class', "meta_close"); 
		jQuery("#sm_mht_"+idElement).show(); 
		jQuery("#h_mht_"+idElement).hide(); 
	}
	return false ; 
}

function modifyURL(idLigne) {
	jQuery("#url"+idLigne).hide();
	jQuery("#change"+idLigne).show();
}

function modifyURL2(oldURL, idLigne, idPost) {
	changeURL(oldURL, jQuery("#newURL"+idLigne).val(), idPost) ; 
}

function annul_modifyURL(idLigne) {
	jQuery("#url"+idLigne).show();
	jQuery("#change"+idLigne).hide();
}

function recheckURL(oldURL) {
	var arguments = {
		action: 'recheckURL', 
		oldURL : oldURL
	} 
	//POST the data and append the results to the results div
	jQuery.post(ajaxurl, arguments, function(response) {
		window.location.href=window.location.href ; 
	}).error(function(x,e) { 
		if (x.status==0){
			//Offline
		} else if (x.status==500){
			recheckURL(oldURL) ; 
		} else {
			alert("Error "+x.status) ; 
		}
	});   
}

function changeURL(oldURL, newURL, idPost) {
	var arguments = {
		action: 'changeURL', 
		idPost : idPost,
		oldURL : oldURL,
		newURL : newURL
	} 
	//POST the data and append the results to the results div
	jQuery.post(ajaxurl, arguments, function(response) {
		if (response=="ok") {
			window.location.href=window.location.href ; 
		} else {
			alert(response) ; 
		}
	}).error(function(x,e) { 
		if (x.status==0){
			//Offline
		} else if (x.status==500){
			changeURL(oldURL, newURL) ; 
		} else {
			alert("Error "+x.status) ; 
		}
	});   
}