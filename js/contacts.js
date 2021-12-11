//Everything will be for working with contacts
var baseUrl = OC.generateUrl('/apps/sciencemesh');
$('#test').hide(); 
$.ajax({
    url: baseUrl + '/contacts/users',
    type: 'GET',
    contentType: 'application/json',
}).done(function (response) {
    if(response === '' || response === false) {
        var element = document.getElementById("test_error");
        element.innerHTML= 'Not connection with reva';
        //$('#test').show(); 
    } else {
    let token = JSON.parse(response);
    for(tokenData in token) {
        if(token.hasOwnProperty(tokenData)) {
            if(tokenData === 'accepted_users') {
                let accepted_users = token.accepted_users
                for(accept in accepted_users) {
                    let result = accepted_users[accept].mail;

                    console.log(accepted_users[accept].id.idp)
                  
                    var element = document.getElementById("show_result");
                    element.innerHTML=result;
                    $('#test').show();

                    var name = accepted_users[accept].display_name

                    var element_name = document.getElementById("display_name");
                    element_name.innerHTML=name;

                    var provider = accepted_users[accept].id.idp

                    var element_provider = document.getElementById("provider");
                    element_provider.innerHTML=provider;
                }
            }
        } 
    }
}
}).fail(function (response, code) {
    console.log(response)
    //alert('The token is invalid')
});
document.getElementById('elem').onclick = function () { 
    console.log('clicked');
    var providerDomain = document.getElementById('providerDomain').value;
    var token = document.getElementById('token').value

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
            element.innerHTML= 'Not connection with reva';
        } else {
            let result = JSON.parse(response);
            if(result.hasOwnProperty('message')) {
                let test = result.message
                var element = document.getElementById("test_error");
                element.innerHTML=test;

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
