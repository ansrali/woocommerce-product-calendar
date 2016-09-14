(function ($) {


	    	
	$(document).ready(function () {

        var dateMin = new Date();
        var weekDays = AddWeekDays(+4);

        dateMin.setDate(dateMin.getDate() + weekDays);

        var natDays = [
/*          [1, 1, 'uk'],
          [12, 25, 'uk'],
          [12, 26, 'uk']*/
        ];
        
        $('#deliveryDate').datepicker(
        {
            /*inline: true,*/
            beforeShowDay: noWeekendsOrHolidays,
            /*altField: '#txtCollectionDate',*/
            /*showOn: "both",*/
            dateFormat: "dd-mm-yy",
            firstDay: 1,
            changeFirstDay: false,
            minDate: dateMin
        });
    
		jQuery('.single_add_to_cart_button').click(function ( event ) {
		
			deliveryDate = jQuery('#deliveryDate').val();
	        if( deliveryDate == '' ) { 
	                    alert('Please select the delivery date.'); 
	                    event.preventDefault(); 
			}

			/* If all values are proper, then send AJAX request */
			jQuery.ajax({
				url: WCMA_Ajax.ajaxurl,
				type: "POST",
				data: {
					//action name
					action: 'add_user_custom_deliveryDate',
					deliveryDate: jQuery('#deliveryDate').val()
				},
				async: false,
				success: function ( data ) {
					/* console.log( 'success' + data ); */
				}
			});
	    });
	    
		$("#button_pressed").on("change", function () {
			$.post(
				WCMA_Ajax.ajaxurl, {
					action               : 'process_reques',
					id                   : $(this).val(),
					wc_multiple_addresses: WCMA_Ajax.wc_multiple_addresses
				}, function (response) {}
			);
			return false;
		});


		function noWeekendsOrHolidays(date) {
		    var noWeekend = $.datepicker.noWeekends(date);
		    if (noWeekend[0]) {
		        return nationalDays(date);
		    } else {
		        return noWeekend;
		    }
		}
		function nationalDays(date) {
		    for (i = 0; i < natDays.length; i++) {
		        if (date.getMonth() == natDays[i][0] - 1 && date.getDate() == natDays[i][1]) {
		            return [false, natDays[i][2] + '_day'];
		        }
		    }
		    return [true, ''];
		}
		function AddWeekDays(weekDaysToAdd) {
		    var daysToAdd = 0
		    var mydate = new Date()
		    var day = mydate.getDay()
		    weekDaysToAdd = weekDaysToAdd - (5 - day)
		    if ((5 - day) < weekDaysToAdd || weekDaysToAdd == 1) {
		        daysToAdd = (5 - day) + 2 + daysToAdd
		    } else { // (5-day) >= weekDaysToAdd
		        daysToAdd = (5 - day) + daysToAdd
		    }
		    while (weekDaysToAdd != 0) {
		        var week = weekDaysToAdd - 5
		        if (week > 0) {
		            daysToAdd = 7 + daysToAdd
		            weekDaysToAdd = weekDaysToAdd - 5
		        } else { // week < 0
		            daysToAdd = (5 + week) + daysToAdd
		            weekDaysToAdd = weekDaysToAdd - (5 + week)
		        }
		    }

		    return daysToAdd;
		}
		   
		   
	});
	

     
})(jQuery);



