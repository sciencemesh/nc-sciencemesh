//Everything will be for working with contacts

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
