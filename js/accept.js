
document.getElementById('elem').onclick = function () { 
    console.log('clicked');
    var full = document.getElementById('token').value
    var parts = full.split('@')
    var token = parts[0]
    var providerDomain = parts[1]
    var data = 'providerDomain=' + encodeURIComponent(providerDomain) +
  '&token=' + encodeURIComponent(token);

    var baseUrl = OC.generateUrl('/apps/sciencemesh');
    $.ajax({
        url: baseUrl + '/contacts/accept',
        type: 'POST',
        contentType: 'application/x-www-form-urlencoded',
        data: data
    }).done(function (response) {
      
       if(response === '' || response === false) {
            var element = document.getElementById("test_error");
            element.innerHTML= 'No connection with reva';
            console.log("Result:", test);
        } else {
            let result = JSON.parse(response);
            if(result.hasOwnProperty('message')) {
                let test = result.message;
                console.log("Result:", test);
                var element = document.getElementById("test_error");
                element.innerHTML=test || 'Success';

                $('#provider').hide();
                $('#display_name').hide();
            } else {
                console.log(result)
            }
        }
     
    }).fail(function (response, code) {
        console.log(response)
        //alert('The token is invalid')
    });
};
