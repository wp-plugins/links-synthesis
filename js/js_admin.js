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
	changeURL(oldURL, jQuery("#newURL"+idLigne).val(), idPost, idLigne) ; 
}

function annul_modifyURL(idLigne) {
	jQuery("#url"+idLigne).show();
	jQuery("#change"+idLigne).hide();
}

function recheckURL(idLink) {
	var arguments = {
		action: 'recheckURL', 
		id : idLink
	} 
	//POST the data and append the results to the results div
	jQuery.post(ajaxurl, arguments, function(response) {
		window.location.href=window.location.href ; 
	}).error(function(x,e) { 
		if (x.status==0){
			//Offline
		} else if (x.status==500){
			recheckURL(idLink) ; 
		} else {
			alert("Error "+x.status) ; 
		}
	});   
}

function ignoreURL(idLink) {
	var arguments = {
		action: 'ignoreURL', 
		id : idLink
	} 
	//POST the data and append the results to the results div
	jQuery.post(ajaxurl, arguments, function(response) {
		window.location.href=window.location.href ; 
	}).error(function(x,e) { 
		if (x.status==0){
			//Offline
		} else if (x.status==500){
			ignoreURL(idLink) ; 
		} else {
			alert("Error "+x.status) ; 
		}
	});   
}

function doNotIgnoreURL(idLink) {
	var arguments = {
		action: 'doNotIgnoreURL', 
		id : idLink
	} 
	//POST the data and append the results to the results div
	jQuery.post(ajaxurl, arguments, function(response) {
		window.location.href=window.location.href ; 
	}).error(function(x,e) { 
		if (x.status==0){
			//Offline
		} else if (x.status==500){
			doNotIgnoreURL(idLink) ; 
		} else {
			alert("Error "+x.status) ; 
		}
	});   
}

function changeURL(oldURL, newURL, idPost, idLink) {
	var arguments = {
		action: 'changeURL', 
		idPost : idPost,
		id : idLink,
		oldURL : oldURL,
		newURL : newURL
	} 
	//POST the data and append the results to the results div
	jQuery.post(ajaxurl, arguments, function(response) {
		if (response=="") {
			window.location.href=window.location.href ; 
		} else {
			alert(response) ; 
		}
	}).error(function(x,e) { 
		if (x.status==0){
			//Offline
		} else if (x.status==500){
			changeURL(oldURL, newURL, idPost, idLink) ; 
		} else {
			alert("Error "+x.status) ; 
		}
	});   
}

function forceAnalysisLS() {
	jQuery('#forceAnalysisLS').attr('disabled', 'disabled');
	jQuery('#stopAnalysisLS').removeAttr('disabled');
	jQuery('#wait_analysisLS').show() ;
	var arguments = {
		action: 'forceAnalysisLinks'
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		if ((""+response+ "").indexOf("PROGRESS POSTS - ") !=-1) {
			if (jQuery('#forceAnalysisLS').is(":disabled")) {
				jQuery('#table_links_synthesis').html(response) ;
				forceAnalysisLS() ; 
			}
		} else {
			jQuery('#forceAnalysisLS').removeAttr('disabled');
			jQuery('#stopAnalysisLS').attr('disabled', 'disabled');
			jQuery('#table_links_synthesis').html(response) ;
			jQuery('#wait_analysisLS').hide() ;
		}
	});
}

function stopAnalysisLS() {
	jQuery('#forceAnalysisLS').removeAttr('disabled');
	jQuery('#stopAnalysisLS').attr('disabled', 'disabled');
	jQuery('#wait_analysisLS').hide() ;
	
	var arguments = {
		action: 'stopAnalysisLinks'
	} 
	jQuery.post(ajaxurl, arguments, function(response) {
		// nothing
	});
}