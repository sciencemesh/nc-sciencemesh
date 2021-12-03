//Everything will be for working with contacts
var baseUrl = OC.generateUrl('/apps/sciencemesh');
$('#test').hide(); 
$.ajax({
    url: baseUrl + '/contacts/users',
    type: 'GET',
    contentType: 'application/json',
}).done(function (response) {
    if(response === '' || response === false) {
        var element = document.getElementById("show_result");
        element.innerHTML= 'Not connection with reva';
        $('#test').show(); 
    } else {
    let token = JSON.parse(response);
    for(tokenData in token) {
        if(token.hasOwnProperty(tokenData)) {
            if(tokenData === 'accepted_users') {
                let accepted_users = token.accepted_users
                for(accept in accepted_users) {
                    let result = accepted_users[accept].mail;
                    var element = document.getElementById("show_result");
                    element.innerHTML=result;
                    $('#test').show();
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
    var providerDomain = 'cernbox.cern.ch';
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
       console.log(response)
    }).fail(function (response, code) {
        console.log(response)
        //alert('The token is invalid')
    });
};
