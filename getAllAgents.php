<?php 

include("connection_android.php");
	$db = new dbObj();
	$connection =  $db->getConnstring(); 


$deviceName = "" ;


$sql_location = mysqli_query($connection,"SELECT * FROM users");
while ($row_location = mysqli_fetch_array($sql_location)){
    
    $deviceName = $deviceName.',"'.$row_location["id"].'"';
    
}

$state_list = '['.$deviceName.']';
        
        echo $state_list;



 ?>