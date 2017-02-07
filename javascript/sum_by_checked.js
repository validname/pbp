
function reset_checkboxes(form, reset_checkbox)
{
	var all_true = true;
	var all_false = true;
	var set = false;

	// check states
	for(var i = 0; i < form.length; i++) {
		var e = form.elements[i];
		if( e.type == "checkbox" ) {
			if( e.checked == true )
				all_false = false;
			else
				all_true = false;
		}
	}

	if( all_false == true )	// set checkboxes if empty
		set = true;
	else
		set = false; 

	// setting  
	for(var i = 0; i < form.length; i++)
	{
		var e = form.elements[i];
		if( e.type == "checkbox" ) {
			e.checked = set;
		}
	}
	reset_checkbox.checked = set;
	calc_sum(form);
}

function calc_sum(form)
{
	var sum = 0.0; 
	for(var i = 0; i < form.length; i++)
	{
		var e = form.elements[i];
		if( e.type == "checkbox" && e.checked == true ) {
			sum += parseFloat(e.value)*100;
		}
	}
	//округление
	form.checked_sum.value = sum/100;
}

