// NEW CODE!

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
       console.log("DONE!");
       if(response === '' || response === false) {
            console.log("IF!");
            var element = document.getElementById("test_error");
            element.innerHTML= 'No connection with reva';
            console.log("Result:", test);
        } else {
            console.log("ELSE!");
            let result = JSON.parse(response);
            if(result.hasOwnProperty('message')) {
              console.log("IF 2!");
                let test = result.message;
                console.log("Result:", test);
                var element = document.getElementById("test_error");
                const response = test || 'Success';
                element.innerHTML= response;
                alert(response);


                $('#provider').hide();
                $('#display_name').hide();
            } else {
              console.log("ELSE 2!");
                console.log(result)
            }
        }
     
    }).fail(function (response, code) {
        console.log(response)
        //alert('The token is invalid')
    });
};
function checkQueryString() {
  const params = new Proxy(new URLSearchParams(window.location.search), {
    get: (searchParams, prop) => searchParams.get(prop),
  });
  if ((typeof params.token == 'string') && (params.token.length > 0) &&
    (typeof params.providerDomain == 'string') && (params.providerDomain.length > 0)) {
    document.getElementById('token').value = `${params.token}@${params.providerDomain}`;
  }
}
// ...
checkQueryString();