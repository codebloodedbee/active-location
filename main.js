 // Maps access token goes here
 var key = 'pk.6250a9b31a4b81dc89efb6b135e66695';

 // Add layers that we need to the map
 var streets = L.tileLayer.Unwired({key: key, scheme: "streets"});




 function getAllAgent()
 {
    
    
    //document.getElementById("statediv1").innerHTML = "Loading...";
    var xhttp;  
   xhttp = new XMLHttpRequest();
   xhttp.onreadystatechange = function() { 
   if (this.readyState == 4 && this.status == 200) {
       
    
      xy = this.responseText;
   }
 };
 xhttp.open("GET", "getAllAgents.php", true);
 xhttp.send();
      
     
 }
 function getUserLatLong()
 {
    
    
    
    var xhttp;  
   xhttp = new XMLHttpRequest();
   xhttp.onreadystatechange = function() { 
   if (this.readyState == 4 && this.status == 200) {
       
    
      xy = this.responseText;
   }
 };
 xhttp.open("GET", "getUserLatLong.php", true);
 xhttp.send();
      
     
 }

 // Initialize the map
 var map = L.map('map', {
     center: [9.0546462, 7.2542709], // Map loads with this location as center
     zoom: 7,
     scrollWheelZoom: false,
     layers: [streets] // Show 'streets' by default
 });

 // Add the 'scale' control
 L.control.scale().addTo(map);

 // Add the 'layers' control
 L.control.layers({
     "Streets": streets
 }).addTo(map);

 // Add a 'marker'

 // create marker for all agents .. 

  var agents = ["BMW", "Volvo", "Saab", "Ford", "Fiat", "Audi"];
//  agents = xy;
 for (i = 0; i < agents.length; i++) { 
    //text += agents[i] + "<br>";
  }
 
 var marker1 = L.marker([6.621789, 3.375910]).addTo(map);

 marker1.on('click', function(e) {
     alert(e.latlng);
     marker1.bindPopup("<b>Hello world!</b><br>I am a popup.").openPopup();
 });


 

 


 // Add a 'circle'
 var circle = L.circle([39.73, -104.997], {
     color: 'red',
     fillColor: '#fff',
     fillOpacity: 0.5,
     radius: 500
 }).addTo(map);

 // Add a 'polygon'
 var polygon = L.polygon([
     [39.726, -104.980],
     [39.734, -104.982],
     [39.739, -104.971]
 ]).addTo(map);
