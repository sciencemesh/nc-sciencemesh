document.addEventListener("DOMContentLoaded", function(event) {
  document.getElementById('elem').onclick = function () {
      console.log('clicked');
      var full = document.getElementById('token-input').value
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


          var element = document.getElementById("test_error");
          $("#test_error").show();
          if (response === '' || response === false) {
              element.innerHTML = 'Something goes wrong: No connection with reva';
          } else if(response.startsWith('Accepted invite from')){
              document.getElementById('token').value = '';
              alert(response);
          } else {
              let result = JSON.parse(response);
              if (result.hasOwnProperty('message')) {
                  let test = result.message;
                  element.innerHTML = test || 'Success';
                  
                  $('#provider').hide();
                  $('#display_name').hide();
              } else {
                  console.log(result)
              }
          }
          
          
          setTimeout(() => {$("#test_error").hide()},5000);
          
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
  
    checkQueryString();
});