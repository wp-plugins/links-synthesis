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