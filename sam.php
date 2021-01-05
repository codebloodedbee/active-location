<?php
  
// Connect to database
include("../connection_all.php");
$db = new dbObj();
$connection =  $db->getConnstring();
$connect_relay =  $db->getConnstring();


//   Connect to android database
 $db_a = new dbObj_a();
 $connection_a =  $db_a->getConnstring_a();


require("../sendgrid-php/sendgrid-php.php");

$mydate = gmdate("Y-m-d", time() + 3600*(date("I")));
$request_method=$_SERVER["REQUEST_METHOD"];

switch($request_method){

      case 'POST':
      // redirect to function and execute command
      process_action();
      break;

      default:     
    // Invalid Request Method
    header("HTTP/1.0 405 Method Not Allowed");
    break;
  }       
   
function randomString($length=6){
    $str="";
    $characters =array_merge(range('0', '9'));
    $max = count($characters)-1;
    for ($i=0; $i < $length; $i++) { 
      $rand=mt_rand(0,$max);
      $str.=$characters[$rand];
    }
    return $str;
}

function distance($lat1, $lon1, $lat2, $lon2, $unit) {
  if (($lat1 == $lat2) && ($lon1 == $lon2)) {
    return 0;
  }
  else {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    $unit = strtoupper($unit);

    if ($unit == "K") {
      return ($miles * 1.609344);
    } else if ($unit == "N") {
      return ($miles * 0.8684);
    } else {
      return $miles;
    }
  }
}

function process_action()
  {
    global $connection;
    global $connection_a;
    $data = json_decode(file_get_contents('php://input'), true);
    $command = $_POST["command"]; 

if ($command == 'login'){
  $username=strtolower($_POST["username"]);
  $password=($_POST["password"]);
  $deviceToken = $_POST["device_token"];
  $deviceType = "android";

  //restrict access to android users only
  $sql_login = mysqli_query($connection,"SELECT * FROM users WHERE username='$username' AND activated='1' AND (android_credit>='1' OR android_credit_used>='1') LIMIT 1");
  while ($row_login = mysqli_fetch_array($sql_login)){   
    $accountId=$row_login["account_id"];
    $storeID=$row_login["store_id"];
    $accountName=$row_login["first_name"];
    $lastName=$row_login["last_name"];
    $dbusername=$row_login["username"];
    $dbusertype=$row_login["user_type"];
    $dbpassword=$row_login["password"];
    $address=$row_login["address"];
    $phoneNum=$row_login["phone_num"];
    $iosCredit=$row_login["android_credit"];
    $iosCreditUsed=$row_login["android_credit_used"];
    $activated=$row_login["activated"];
    $createdAt=$row_login["date_created"];
    $activation_pin=$row_login["activation_pin"];
  }
      
  //pull device details
  $sql_device = mysqli_query($connection,"SELECT * FROM device_tag_register WHERE account_id='$accountId' ORDER BY protection_end_date DESC LIMIT 1");
  while ($row_device = mysqli_fetch_array($sql_device)){    
    $deviceName=$row_device["reg_device"];
    $deviceCap=$row_device["reg_device_capacity"];
    $deviceIMEI=$row_device["reg_imei"];
    $deviceSub=$row_device["subscription"];
    $devicePack=$row_device["package"];
    $deviceStart=$row_device["protection_start_date"];
    $deviceEnd=$row_device["protection_end_date"];
  }   

  if($sql_device){
    // read password and verify hash.
    if($username==$dbusername && password_verify($password, $dbpassword)){
      //Check if token exist and register device token
      $sql_chk_token=mysqli_query($connection,"SELECT * FROM push_notifications WHERE device_token='$deviceToken' LIMIT 1");
      if(!$sql_chk_token->num_rows > 0){
        //Store values in database 
        $sql_push_notify = mysqli_query($connection,"INSERT INTO push_notifications (account_id, device_token, device_type) VALUES ('$accountId', '$deviceToken','$deviceType')");
      }      
      //pull store details
      $sql_store = mysqli_query($connection,"SELECT * FROM store WHERE store_id='$storeID'");
      while ($row_store = mysqli_fetch_array($sql_store))
      {    
        $storename=$row_store["store_name"];
        $storeemail=$row_store["store_email"];
        $insuremail=$row_store["insurance_email"];
        $insureby=$row_store["insurance_company"];
      }   

      $response=array(
      'status' => true,
      'message' =>'Login successful',
      'data' => array(
        'account_id'=> $accountId,
        'store_id'=> $storeID,
        'first_name'=> $accountName,
          'last_name'=> $lastName,
          'username'=> $dbusername,
          'user_type'=> $dbusertype,
          'address'=> $address,
          'phone_num'=> $phoneNum,
          'ios_credit'=> $iosCredit,
          'ios_credit_used'=> $iosCreditUsed,
          'device_name'=> $deviceName,
          'device_capacity'=> $deviceCap,
          'device_imei'=> $deviceIMEI,
          'device_subscription'=> $deviceSub,
          'device_package'=> $devicePack,
          'registered_date'=> $deviceStart,
          'expiry_date'=> $deviceEnd,
          'store_name'=> $storename,
          'store_email'=> $storeemail,
          'insurance_name'=> $insureby,
          'insurance_email'=> $insuremail,
          'account_status'=> $activated,
          'date_created'=> $createdAt,
          'activation_pin'=> $activation_pin,
          
          )
      );
      //Check and Update Expiry
      $query_expiry = mysqli_query($connection,"SELECT protection_end_date FROM device_tag_register WHERE current_state='Activated' AND account_id='$accountId'");
      while($row_expiry = mysqli_fetch_array($query_expiry)){
        $subEnd=$row_expiry["protection_end_date"];
        if ($mydate > $subEnd){
          //update record to expired
          $sql_update = mysqli_query($connection,"UPDATE device_tag_register SET current_state='Inactive', status_label='label-danger' WHERE account_id='$accountId'"); 
        }
      }
    }else{
      $response=array(
      'status' => false,
      'message' =>'Login failed'
      );
    }
  }
}

if ($command == 'agent_login'){
      $username=strtolower($_POST["username"]);
      $password=($_POST["password"]);
    
      $sql_login = mysqli_query($connection,"SELECT * FROM users WHERE username='$username' LIMIT 1 ");
      while ($row_login = mysqli_fetch_array($sql_login))
      {   
         $dbemail =$row_login["username"];
         $dbpassword=$row_login["password"];
          $firstName=$row_login["first_name"];
          $lastName=$row_login["last_name"];
          $userId=$row_login["user_id"];
          $longitude=$row_login["longitude"];
          $latitude=$row_login["latitude"];
         
      }
      // Free result set
      mysqli_free_result($sql_login);
      
        // Free result set
         mysqli_free_result($sql_device);

      // read password and verify hash.
     // if($username==$dbemail && password_verify($password, $dbpassword)){
     if($username==$dbemail && $password == $lastName && $dbemail != NULL){
      
   
      $response=array(
      'status' => true,
      'message' =>'Login successful',
      'data' => array(
          'first_name'=> $firstName,
          'last_name'=> $lastName,
          'user_id'=> $userId,
            'first_name'=> $firstName,
            'longitude'=> $longitude,
            'latitude'=> $latitude,
        
          
            )
      );
     }else{
      $response=array(
      'status' => false,
      'message' =>'Incorrect Password or Invalid Email',
      
      );
    }
    
}

if ($command == 'agent_login_new'){
      $username=strtolower($_POST["username"]);
      $password=($_POST["password"]);

  $myLat=$_POST["lat"];
  $myLong=$_POST["long"];

  $sql_login = mysqli_query($connection_a,"SELECT * FROM mbe_promoter WHERE username='$username' AND status='1' LIMIT 1");
  while ($row_login = mysqli_fetch_array($sql_login)){    
    $userId=$row_login["mbe_id"];
    $storeId=$row_login["store_id"];
    $userName=$row_login["firstname"]." ".$row_login["lastname"] ;
    $userEmail=$row_login["username"];
    $userPass=$row_login["password"];
    $userTitle=$row_login["title"];
    $latitude=$row_login["latitude"];
    $longitude=$row_login["longitude"];
    $userLevel=$row_login["account_level"];
  }
  //Query database to get store name
  $sql_store_name = mysqli_query($connection_a,"SELECT store_name FROM store WHERE store_id='$storeId'");
  while ($row_store_name = mysqli_fetch_array($sql_store_name)){    
      $storeName=$row_store_name["store_name"];
  }
  mysqli_free_result($sql_store_name);
  // Free result set
  mysqli_free_result($sql_login);    
  // read password and verify hash.
  if (password_verify($password, $userPass)){
    //update location if empty
    if ($latitude==0){
      mysqli_query($connection_a,"UPDATE mbe_promoter SET latitude='$myLat', longitude='$myLong' WHERE username='$username'");
    }
    $_SESSION["user_id"]=$userId;
    $_SESSION["store_id"]=$storeId;
    $_SESSION["store_name"]=$storeName;
    $_SESSION["user_name"]=$userName;
    $_SESSION["user_email"]=$userEmail;
    $_SESSION["user_title"]=$userTitle;
    $_SESSION["user_level"]=$userLevel;
    
    $response=array(
      'status' => true,
      'message' =>'Login successful',
      'data' => array(
          
          'user_id'=> $userId,
          'store_id'=> $storeId,
          'store_name'=> $storeName,
            'email'=> $userEmail,
            'name'=> $userName,
            
        
          
            )
      );
    
    
    
  }else{
   $response=array(
      'status' => false,
      'message' =>'Incorrect Password or Invalid Email',
      
      );    
  }   
    
     
}

if ($command == 'check_price'){
      $device_name = ($_POST["device_name"]);
      $variant = ($_POST["variant"]);

      $sql_var = mysqli_query($connection,"SELECT * FROM samsung_prices WHERE device_name='$device_name'");
      while ($row_var = mysqli_fetch_array($sql_var))
      {   
         $SLD =$row_var["SLD"];
         $UPGRADE =$row_var["UPGRADE"];
         $SLD_UPGRADE=$row_var["SLD_UPGRADE"];
         $SPP=$row_var["SPP"]; 
         $SLDUA=$row_var["SLDUA"];
         $SLDA=$row_var["SLDA"];
         $SUP=$row_var["SUP"]; 
         $SUU=$row_var["SUU"];
         $SAP=$row_var["SAP"];          
      }
      
      if($variant == "SLD"){
        $variant_price = $SLD;          
      }
      elseif($variant == "SLD_UPGRADE"){
        $variant_price = $SUU;
      }
      elseif($variant == "UPGRADE"){
        $variant_price = $SUP;
      }
      elseif($variant == "SPP"){
        $variant_price = $SPP;
      }
      elseif($variant == "SLDUA"){
        $variant_price = $SLDUA;
      }
      elseif($variant == "SLDA"){
        $variant_price = $SLDA;
      }
      elseif($variant == "SUP"){
        $variant_price = $SUP;
      }
      elseif($variant == "SUU"){
        $variant_price = $SUU;
      }
      elseif($variant == "SAP"){
        $variant_price = $SAP;
      }
    
      if($SLD){
      $response=array(
      'status' => true,
      'message' =>'Device value found',
      'data' => array(
          'variant_price'=> number_format($variant_price,2),
         
            )
      );
     }else{
      $response=array(
      'status' => false,
      'message' =>'Device Not Found'
      );
    }    
}

if ($command == 'register_'){      
    $fname=strtolower($_POST["first_name"]);
    $lname=strtolower($_POST["last_name"]);
    $email=strtolower($_POST["email"]);
    $phone=($_POST["phone"]);
    $address=strtolower($_POST["address"]);
    $pincode=($_POST["pincode"]);
    $password1=($_POST["password1"]);
    $password2=($_POST["password2"]);
    $deviceManufacturer=($_POST["device_manufacturer"]);
    $deviceModel=($_POST["device_model"]);
    $deviceCap=($_POST["device_capacity"]);
    $deviceIMEI=($_POST["device_imei"]);
    $deviceCost= $_POST["device_cost"];
      
    //Check if language is selected
    if ($to_lan == ""){ 
        $to_lan ="en";
    }
    
    //change case
    $fname= ucfirst($fname);
    $lname= ucfirst($lname);
    $address=ucwords($address);
    $country = str_replace("'", "\'", $country);
    
    //Remove space character 
    $deviceIMEI = preg_replace( '/\s+/', '', $deviceIMEI );
    $fname = preg_replace( '/\s+/', '', $fname );
    $lname = preg_replace( '/\s+/', '', $lname );
    $email = preg_replace( '/\s+/', '', $email );

//Check if Device is listed and valid
$sql_chk_device=mysqli_query($connection,"SELECT * FROM samsung_prices WHERE device_model_number='$deviceModel' AND price!='0' AND SLD!='0' LIMIT 1");
if($sql_chk_device->num_rows > 0){
  mysqli_free_result($sql_chk_device);

//Check if IMEI is in use
$sql_chk_imeiused=mysqli_query($connection,"SELECT * FROM device_tag_register WHERE reg_imei='$deviceIMEI' LIMIT 1");
if(!$sql_chk_imeiused->num_rows > 0){
    mysqli_free_result($sql_chk_imeiused);

//Check if Pin is valid and not used

$sql_chk_pin=mysqli_query($connection,"SELECT * FROM registration_pin WHERE pin_code='$pincode' AND pin_type='android' AND pin_used='0' LIMIT 1");
while ($row_chk_pin = mysqli_fetch_array($sql_chk_pin)){
  $bundle_chk = strtoupper($row_chk_pin["bundle"]);
}
if($sql_chk_pin->num_rows > 0){
    mysqli_free_result($sql_chk_pin);
    
    //Check if email is valid
    if(eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $email)){
      
      //Check if Email has been used
      $sql_chk_email=mysqli_query($connection,"SELECT * FROM users WHERE username='$email' LIMIT 1");
      if(!$sql_chk_email->num_rows > 0){
         mysqli_free_result($sql_chk_email);
         
         //Check if password match
         if ($password1 !== $password2) {
           $response=array(
         'status' => false,
         'message' =>'Password mismatch'
         );
         }else{
          if (($bundle_chk=="SLDUA") OR ($bundle_chk=="SLDA") OR ($bundle_chk=="SLD_UPGRADE") OR ($bundle_chk=="UPGRADE") OR ($bundle_chk=="SAP") OR ($bundle_chk=="SUP") OR ($bundle_chk=="SUU")){ 
            //enroll code
            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://devfinapi.sentinelock.com/v1/post/",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => array('command' => 'enrollDevice','requestApikey' => 'lGwQ4BUjeTgpc&kx5b6NHunFML38','deviceIMEI' => $deviceIMEI),
            ));
            $response = curl_exec($curl);
            curl_close($curl);
            $character = json_decode($response);
            $checkStatus = $character->statusCode;

            if ($checkStatus==2000000){  
              $lockState="unlock";        
              include("registration_action.php");        
            }else{
              $response=array(
                'status' => false,
                'message' =>'Device enrollment failed... Please try again!'
                );
            }
          }else{
            $lockState="none"; 
            include("registration_action.php");  
          }
        }
      //Check if Email has been used   
      }else{
        $response=array(
        'status' => false,
        'message' =>'Email already used'
        );
      }
    //Check if email is valid  
    }else{
    $response=array(
   'status' => false,
   'message' =>'Email validation failed'
   );
   }   

//Check if Pin is valid and not used
}else{
   $response=array(
  'status' => false,
  'message' =>'Pin validation failed'
   );
}

//Check if IMEI is in use
}else{
   $response=array(
  'status' => false,
  'message' =>'Sorry, this device IMEI has been used!'
   );
}

}else{
 $response=array(
'status' => false,
'message' =>'Sorry, your device is not listed for this offer!'
 );
}

}

if ($command == 'profile_update'){
$accountId = $_POST["account_id"];      
$myfname = ucfirst($_POST["first_name"]);
$mylname = ucfirst($_POST["last_name"]);
$myphone = $_POST["phone"];
$myaddress = $_POST["address"];
      
$sql_update = mysqli_query($connection,"UPDATE users SET first_name='$myfname', last_name='$mylname', phone_num='$myphone', address='$myaddress', date_updated=NOW() WHERE account_id='$accountId'");
if ($sql_update) {
   $response=array(
  'status' => true,
  'message' =>'Profile update successful'
   );
   //log activity in activity log
    $sql_log = "INSERT INTO user_activity_log (account_id, device_name, action_taken, log_status) VALUES ('$accountId', 'NULL', 'Profile Update', 'Successful')";
      mysqli_query($connection, $sql_log);
      mysqli_free_result($sql_log);
} else {
   $response=array(
  'status' => false,
  'message' =>'Profile update failed'
   );
    //log activity in activity log
    $sql_log = "INSERT INTO user_activity_log (account_id, device_name, action_taken, log_status) VALUES ('$accountId', 'NULL', 'Profile Update', 'Failed')";
    mysqli_query($connection, $sql_log);
    mysqli_free_result($sql_log);
  }
  mysqli_free_result($sql_update);
}

if ($command == 'password_update'){
$accountId = $_POST["account_id"];     
$myoldpass = $_POST["oldpassword"];
$mypass = $_POST["newpassword"];
$mypass2 = $_POST["newpassword2"];

$sql_pwd_chk=mysqli_query($connection,"SELECT * FROM users WHERE account_id='$accountId' AND activated='1' LIMIT 1");
while ($row_pwd_chk = mysqli_fetch_array($sql_pwd_chk)){  
  $dbpassword=$row_pwd_chk["password"];
}
mysqli_free_result($sql_pwd_chk);
// read password and verify hash
if(password_verify($myoldpass, $dbpassword)){
  if ($mypass !== $mypass2) {
    $response=array(
  'status' => false,
  'message' =>'Password mismatch'
   );
  }else{
    $newpasswd_hash=password_hash($mypass, PASSWORD_BCRYPT);
    $sql_pwdupdate = mysqli_query($connection,"UPDATE users SET password='$newpasswd_hash', date_updated=NOW() WHERE account_id='$accountId'");
    if ($sql_pwdupdate) {
      $response=array(
     'status' => true,
     'message' =>'Password update successful'
     );
      //log activity in activity log
      $sql_log = "INSERT INTO user_activity_log (account_id, device_name, action_taken, log_status) VALUES ('$accountId', 'NULL', 'Password Update', 'Successful')";
      mysqli_query($connection, $sql_log);
      mysqli_free_result($sql_log);
    } else {
      $response=array(
     'status' => false,
     'message' =>'Password update failed'
     );
      //log activity in activity log
    $sql_log = "INSERT INTO user_activity_log (account_id, device_name, action_taken, log_status) VALUES ('$accountId', 'NULL', 'Password Update', 'Failed')";
    mysqli_query($connection, $sql_log);
    mysqli_free_result($sql_log);
    }
    mysqli_free_result($sql_pwdupdate);
  }
}else{
  $response=array(
  'status' => false,
  'message' =>'Current password error'
  );         
}
} 

if ($command == 'register---'){
    
    $firstName=strtolower($_POST["first_name"]);
    $lastName=strtolower($_POST["last_name"]);
    $email=strtolower($_POST["email"]);
    $phone=$_POST["phone"];
    $storeName=$_POST["store_name"];
    $latitude=$_POST["latitude"];
    $longitude=$_POST["longitude"];
    
    $password1=($_POST["password1"]);
    $password2=($_POST["password2"]);
    
    $getDateTime = gmdate("Y-m-d H:i:s", time()+3600*(date("I")));
$getDate = gmdate("Y-m-d", time()+3600*(date("I")));
    
    $getDateTime = gmdate("Y-m-d H:i:s", time()+3600*(date("I")));
    
    $newpasswd_hash=password_hash($password1, PASSWORD_BCRYPT); 
    
    $sql_store = mysqli_query($connection_a,"SELECT * FROM store WHERE store_name = '$storeName' ");
    while ($row_store = mysqli_fetch_array($sql_store)){  
      $storeId=$row_store["store_id"];
      $regionId=$row_store["region_id"];
    }
    mysqli_free_result($sql_store);

    $sql_check_email = mysqli_query($connection,"SELECT * FROM users WHERE username = '$email'  ");
    
     while ($row_check_email = mysqli_fetch_array($sql_check_email)){  
      $em=$row_check_email["username"];
      
    }
    
    mysqli_free_result($sql_check_email);

    if ($em){
        $response=array(
        'status' => false,
        'xy' => $em,
        'xy1' => $last,
        'message' =>'User Registered already'
        );    
    
    
    
    } else {
    
    $sql_check_store = mysqli_query($connection,"SELECT * FROM users WHERE store_id = '$storeId' ");
     while ($row_check_store = mysqli_fetch_array($sql_check_store)){  $em1=$row_check_store["username"];}

    if(false){
        
        $response=array(
        'status' => false,
        'message' =>'Store has been taken already'
         );  
        
       
        
    } else {
    
    
        
        $sql_register = mysqli_query($connection,"INSERT INTO users(store_id, job_title, first_name, last_name, username, password, phone_num, latitude, longitude, current_state, created_at) VALUES ('$storeId','MBE','$firstName','$lastName','$email','$newpasswd_hash','$phone','$latitude','$longitude','1', '$getDateTime')");
        
        
        mysqli_free_result($sql_register);
        
        if($sql_register){
         
            $response=array(
          'status' => true,
          'message' =>'Successfull',
          'store_id' =>$storeId);
          
        }else{
          $response=array(
          'status' => false,
          'message' =>'Not successfull'
          );         
            }
        }

}

}

if ($command == 'password_reset'){
$useremail=($_POST["email"]);

//generate new pass,

/*function randomString($length=6){
  $str="";
  $characters =array_merge(range('a','n'), range('p','z'), range('1', '9'));
  $max = count($characters)-1;
  for ($i=0; $i < $length; $i++) { 
    $rand=mt_rand(0,$max);
    $str.=$characters[$rand];
  }
  return $str;
}*/
    
//confirm email
$sql_email=mysqli_query($connection,"SELECT * FROM users WHERE username='$useremail' AND activated='1' AND (android_credit>='1' OR android_credit_used>='1') LIMIT 1");
while ($row_email = mysqli_fetch_array($sql_email)){    
  $accountUsername=$row_email["username"];
   $accountName=$row_email["first_name"];
}
  
if($sql_email){
   mysqli_free_result($sql_email);
   
   //update user and email user
   $newpasswd=123456; 
   $newpasswd_hash=password_hash($newpasswd, PASSWORD_BCRYPT);        
   $sql_update_pwd = mysqli_query($connection,"UPDATE users SET password='$newpasswd_hash' WHERE username='$useremail'");
   if ($sql_update_pwd) {
      //Send email
    $sender = "Sentinel";
    $mailer = "support@sentinelock.com";
    $subject = "Sentinel Android Password Reset";
    $message = "Hello $accountName,<br><br>You requested for a password reset and here is your new login details:<br><br>Username: " . $accountUsername . "<br>Password: " . $newpasswd . "<br><br><a href=\"https://sentinelock.com/web/android/\">Click here to login</a> or use the mobile app.<br><br>Please change your password from your profile settings once you login.<br><br>Regards,<br>Sentinel Support Team";

    // Send mail using sendgrid
    $emailsend = new \SendGrid\Mail\Mail(); 
    $emailsend->setFrom("$mailer", "$sender");
    $emailsend->setSubject("$subject");
    $emailsend->addTo("$useremail", "$accountName");
    $emailsend->addContent(
        "text/html", "$message"
    );
    $sendgrid = new \SendGrid('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');
    $responseMail = $sendgrid->send($emailsend);
      
      $response=array(
     'status' => true,
     'message' =>'Password reset successful'
     ); 
     
     //log activity in activity log
    $sql_log = "INSERT INTO user_activity_log (account_id, device_name, action_taken, log_status) VALUES ('$accountId', 'NULL', 'Password Reset', 'Successful')";
      mysqli_query($connection, $sql_log);
      mysqli_free_result($sql_log);

    } else {
    $response=array(
     'status' => false,
     'message' =>'Password reset error... Please try again!'
     ); 
    //log activity in activity log
    $sql_log = "INSERT INTO user_activity_log (account_id, device_name, action_taken, log_status) VALUES ('$accountId', 'NULL', 'Password Reset', 'Failed')";
    mysqli_query($connection, $sql_log);
    mysqli_free_result($sql_log); 
           
  }
}else{
$response=array(
     'status' => false,
     'message' =>'Password reset failed... User email not found or account is disabled!'
     ); 
  //log activity in activity log
  $sql_log = "INSERT INTO user_activity_log (account_id, device_name, action_taken, log_status) VALUES ('$accountId', 'NULL', 'Password Reset', 'Failed')";
  mysqli_query($connection, $sql_log);
  mysqli_free_result($sql_log);       
}   
}

if ($command == 'loan_initiated'){
    
$loanRefIdCall=randomString(); 
$loanRefId="3003".$loanRefIdCall;

$lastName = $_POST["last_name"]; 
$firstName = $_POST["first_name"];
$phoneNumber1 = $_POST["phone_number_1"];
$phoneNumber2 = $_POST["phone_number_2"];
$address = $_POST["address"];
$email = $_POST["email"];

$refName1 = $_POST["ref_name_1"];
$refPhone1 = $_POST["ref_phone_1"];
$refName2 = $_POST["ref_name_2"];
$refPhone2 = $_POST["ref_phone_2"];

$store = $_POST["store"]; 
$loanAmount = $_POST["loan_amount"]; 
$loanPeriod = $_POST["loan_duration"];


//$LoanEquity = $_POST["loan_equity"]; 


$loanEquity = 0.3 * $loanAmount;


$loanCollected = $loanAmount - $loanEquity;

$loanInterest = $loanCollected * 0.07 * $loanPeriod ;

$loanToBePaidBack = $loanCollected + $loanInterest;


$loanPerMonth = $loanToBePaidBack/$loanPeriod;

// number_format($loanPerMonth,2);


    $current_time = date("Y-m-d",time()); // Getting Current Date & Time
 // print $current_time; // Current Date & Time Printing for display purpose
  $future_timestamp = strtotime("+1 month");  // Getting timestamp of 1 month from now
  $nextDueDate = date("Y-m-d",+$future_timestamp); //  Getting Future Date & Time of 1 month from now

  if ($loanPeriod === "3 months")
  {
    $future_timestamp1 = strtotime("+3 month");  // Getting timestamp of 1 month from now
  $completionDate = date("Y-m-d",+$future_timestamp1); //  Getting Future Date & Time of 1 month from now

  } else if ($loanPeriod === "6 months")
  {
    $future_timestamp2 = strtotime("+6 month");  // Getting timestamp of 1 month from now
  $completionDate = date("Y-m-d",+$future_timestamp2); //  Getting Future Date & Time of 1 month from now

  } else if ($loanPeriod === "12 months")
  {
    $future_timestamp3 = strtotime("+12 month");  // Getting timestamp of 1 month from now
  $completionDate = date("Y-m-d",+$future_timestamp3); //  Getting Future Date & Time of 1 month from now

  }
  else {

    $future_timestamp3 = strtotime("+12 month");  // Getting timestamp of 1 month from now
  $completionDate = date("Y-m-d",+$future_timestamp3); //  Getting Future Date & Time of 1 month from now


  }

//Store values in database 



  $sql_tracker = mysqli_query($connection,"INSERT INTO loan( reference_id, last_name, first_name, phone_number_1, phone_number_2, email, residential_address, ref_name_1,
ref_phone_1, ref_name_2, ref_phone_2, store, source, loan_amount, minimum_equity, paid_equity, balance, duration, monthly_payment,
 next_due_date, completion_date) 
VALUES ('$loanRefId', '$lastName', '$firstName', '$phoneNumber1', '$phoneNumber2','$email', '$address', '$refName1', '$refPhone1', '$refName2', '$refPhone2', '$store',  
'mobile app', '$loanAmount', '$loanEquity', '0', '$loanAmount', '$loanPeriod', '$loanPerMonth', '$nextDueDate', '$completionDate')");
$err = mysqli_error($connect_devfin);
  
  if ($sql_tracker) {
    mysqli_free_result($sql_tracker);
      $response=array(
      'status' => true,
      'ref_id' => $loanRefId,
      'loan_equity' => $loanEquity,
      'monthly_payment' => $loanPerMonth,
      'completion_date' => $completionDate,
      'message' =>'Successful'
      );
  }else{
      $response=array(
      'status' => false,
      'message' =>'Failed',
      'error' =>$err
      
      );
   }
}

if ($command == 'store_activity'){
    
$loanRefIdCall=randomString(); 
$loanRefId="3003".$loanRefIdCall;

$userId = $_POST["user_id"]; 
$sales = $_POST["sales"]; 
$footfall = $_POST["footfall"]; 
$timeFrame = $_POST["time_frame"];
$comment = $_POST["comment"];

$getDateTime = gmdate("Y-m-d H:i:s", time()+3600*(date("I")));
$getDate = gmdate("Y-m-d", time()+3600*(date("I")));


//Store values in database 

  $sql_store_activity = mysqli_query($connection,"INSERT INTO store_activity( user_id, footfall, sales, time_frame,comment created_at, date_count) 
VALUES ('$userId','$footfall','$sales', '$timeFrame','$comment', '$getDateTime', '$getDate')");
$err = mysqli_error($connection);
  
  if ($sql_store_activity) {
    mysqli_free_result($sql_store_activity);
      $response=array(
      'status' => true,
      
      'message' =>'Successful'
      );
  }else{
      $response=array(
      'status' => false,
      'message' =>'Failed',
      'error' =>$err
      
      );
   }
}

if ($command == 'attendance'){
    

$userId = $_POST["user_id"];
$longitude = $_POST["longitude"]; 
$latitude = $_POST["latitude"]; 

$getDateTime = gmdate("Y-m-d H:i:s", time()+3600*(date("I")));
$getDate = gmdate("Y-m-d", time()+3600*(date("I")));

// $date = $_POST["date"]; 
// $time = $_POST["time"]; 
$remarks = $_POST["remarks"]; 


//Store values in database 

  $sql_attendance = mysqli_query($connection,"INSERT INTO users_location( user_id, longitude, latitude, time, date, remarks) 
VALUES ('$userId','$longitude', '$latitude', '$getDateTime','$getDate','$remarks')");
$err = mysqli_error($connection);
  
  if ($sql_attendance) {
    mysqli_free_result($sql_attendance);
      $response=array(
      'status' => true,
      
      'message' =>'Successful'
      );
  }else{
      $response=array(
      'status' => false,
      'message' =>'Failed',
      'error' =>$err,
      
      );
   }
}

if ($command == 'clock_in'){
    
$sessionId=randomString(); 
$sessionId="999".$sessionId;
    

$userId = $_POST["user_id"];
$checkInLongitude = $_POST["longitude"]; 
$checkInLatitude = $_POST["latitude"]; 

$getDateTime = gmdate("Y-m-d H:i:s", time()+3600*(date("I")));
$getDate = gmdate("Y-m-d", time()+3600*(date("I")));



// INSERT INTO attendance(user_id, clock_in_latitude, clock_in_longitude, clock_in, clock_in_time, clock_in_date) VALUES ('$userId','$checkInLongitude','$checkInLatitude','$getDateTime', '$getDate');

//Store values in database 

  $sql_attendance = mysqli_query($connection,"INSERT INTO attendance(user_id,session_id, clock_in_latitude, clock_in_longitude, clock_in, clock_in_time, clock_in_date) VALUES ('$userId','$sessionId','$checkInLatitude','$checkInLongitude','1','$getDateTime', '$getDate')");
$err = mysqli_error($connection);
  
  if ($sql_attendance) {
    mysqli_free_result($sql_attendance);
      $response=array(
      'status' => true,
      'session_id' => $sessionId,
      
      'message' =>'Successful'
      );
  }else{
      $response=array(
      'status' => false,
      'message' =>'Failed',
      'error' =>$err,
      
      );
   }
}

if ($command == 'clock_out'){
    
$userId = $_POST["user_id"];
$sessionId=$_POST["session_id"];
$checkOutLongitude = $_POST["longitude"]; 
$checkOutLatitude = $_POST["latitude"]; 

$getDateTime = gmdate("Y-m-d H:i:s", time()+3600*(date("I")));
$getDate = gmdate("Y-m-d", time()+3600*(date("I")));


//Store values in database 

  $sql_attendance = mysqli_query($connection,"UPDATE attendance SET clock_out_latitude='$checkOutLatitude',clock_out_longitude='$checkOutLongitude', clock_out='1', clock_out_time='$getDateTime', clock_out_date='$getDate' WHERE session_id='$sessionId'");
$err = mysqli_error($connection);
  
  if ($sql_attendance) {
    mysqli_free_result($sql_attendance);
      $response=array(
      'status' => true,
      'session_id' => $sessionId,
      
      'message' =>'Successful'
      );
  }else{
      $response=array(
      'status' => false,
      'message' =>'Failed',
      'error' =>$err,
      
      );
   }
}

if ($command == 'sales'){

$agentId = $_POST["agent_id"]; 
$price = $_POST["price"];
$category = $_POST["category"];
$deviceImei = $_POST["device_imei"];
$deviceModel = $_POST["device_model"];
$accessoriesUniqueCode = $_POST["accessories_unique_code"];
$accessoriesType = $_POST["accessories_type"];
$sentinelVariant = $_POST["sentinel_variant"];

$current_time = date("Y-m-d",time()); // Getting Current Date & Time
// print $current_time; // Current Date & Time Printing for display purpose
//Store values in database 



$sql_sales = mysqli_query($connection,"INSERT INTO sr_sales(agent_id, category, device_model, device_imei, price, accessories_type, accessories_unique_code) 
VALUES ('$agentId', '$category', '$deviceModel', '$deviceImei', '$price', '$accessoriesType', '$accessoriesUniqueId')");
//$err = mysqli_error($connect_devfin);
  
  if ($sql_sales) {
    mysqli_free_result($sql_store_activity);
      $response=array(
      'status' => true,
      'message' =>'Successful'
      );
  }else{
      $response=array(
      'status' => false,
      'message' =>'Failed',
      'error' =>$err
      
      );
   }
}

if ($command == 'sales_register'){

$userId = $_POST["user_id"]; 
$price = $_POST["price"];
$category = $_POST["parent_category"];
$parentId =  $_POST["parent_category"];
$deviceImei = $_POST["device_imei"];
$deviceModel = $_POST["device_model"];
$accessoriesUniqueCode = $_POST["accessories_unique_code"];
$accessoriesType = $_POST["accessories_type"];

$sentinelVariant = $_POST["sentinel_variant"];

$serialNum = $_POST["device_imei"];

$subCategoryId = $_POST["sub_id"];

$getDateTime = gmdate("Y-m-d H:i:s", time()+3600*(date("I")));


$getDate = gmdate("Y-m-d", time()+3600*(date("I")));

$current_time = date("Y-m-d",time()); // Getting Current Date & Time

$sql_user = mysqli_query($connection,"SELECT * FROM users WHERE user_id = '$userId' ");
 while ($row_user = mysqli_fetch_array($sql_user)){  
     
  $storeId = $row_user["store_id"];
  $userStore = $row_user["store_id"];
 
 
}

$sql_store = mysqli_query($connection_a,"SELECT * FROM store WHERE store_id = '$storeId' ");
 while ($row_store = mysqli_fetch_array($sql_store)){  
     
  $companyId = $row_store["company_id"];
}




$sql_item = mysqli_query($connection,"SELECT * FROM item_database WHERE (item_name = '$deviceModel' or item_name = '$accessoriesType' or item_name = '$sentinelVariant') AND parent_category_id = '$category' AND sub_category_id = '$subCategoryId'  ");
 while ($row_item = mysqli_fetch_array($sql_item)){  
     $itemId=$row_item["item_id"];
  $itemName=$row_item["item_name"];
   $parentCategoryId=$row_item["parent_category_id"];
  $subCategoryId=$row_item["sub_category_id"];
   $itemCode=$row_item["item_code"];
   $itemPrice=$row_item["item_price"];
   $itemIncentive=$row_item["item_incentive"];
 
}
mysqli_free_result($sql_store);

//Date
  $mytime = gmdate("Y-m-d H:i:s", time() + 3600*(date("I")));
  $mydate = gmdate("Y-m-d", time() + 3600*(date("I")));

  //Check category and apply conditions
  
  //accessories 
  if ($parentId==1){
    //if Accessories, deplete stock and register sales
    //get current stock in the store
    $sql_stock = mysqli_query($connection,"SELECT * FROM item_inventory WHERE store_id='$userStore' AND parent_category_id='$parentId' AND item_id='$itemId' LIMIT 1");
    while ($row_stock = mysqli_fetch_array($sql_stock)){
      $invId=$row_stock["inv_id"];
      $stockLevel=$row_stock["quantity"];
    }
    //check stock level
    $stockLevel=$stockLevel-$itemQty;
    if ($stockLevel>=0){
      //deplete stock and register sales     
      $sql_update_stock = mysqli_query($connection,"UPDATE item_inventory SET quantity='$stockLevel' WHERE inv_id='$invId'");
      if ($sql_update_stock===true) {
        mysqli_query($connect_relay,"INSERT INTO sales_register (company_id,store_id,user_id,item_name,item_code,parent_category_id,sub_category_id,item_quantity,item_price,item_serial_number,receipt_value,item_incentive,created_at,date_count) VALUES ('$companyId','$userStore','$userId','$itemName','$itemCode','$parentId','$subId','$itemQty','$itemPrice','$serialNum','$receiptVal','$totalIncentive','$mytime','$mydate')");
        
        $isSuccess = true; 
        $comment = "Sales reported successfully!.";
        
        //$_SESSION["response1"]="Sales reported successfully!.";
      } else {
          
          $isSuccess = false; 
        $comment = "Sales report failed!";
        
      }
    }else{
      //Stop
      
      
      
      //$_SESSION["response2"]="Low stock level for $itemName. Sales report failed!";
    }
  }

  //Check for sentiflex and apply conditions
  if ($parentId==4){
    //if Sentiflex, check if sales has been made to sentiflex, check if duplicate and register sales
    $sql_sentiflex=mysqli_query($connect_devfin,"SELECT * FROM transaction_users_live WHERE device_imei='$serialNum' LIMIT 1");
    if($sql_sentiflex->num_rows > 0){
      //check duplicate report and continue
      $sql_chk_entry=mysqli_query($connection,"SELECT * FROM sales_register WHERE item_serial_number='$serialNum' AND parent_category_id='$parentId' AND current_state='Active' LIMIT 1");
      if(!$sql_chk_entry->num_rows > 0){
        $sql_reg_sales = mysqli_query($connect_relay,"INSERT INTO sales_register (company_id,store_id,user_id,item_name,item_code,parent_category_id,sub_category_id,item_quantity,item_price,item_serial_number,receipt_value,item_incentive,created_at,date_count) VALUES ('$companyId','$userStore','$userId','$itemName','$itemCode','$parentId','$subId','$itemQty','$itemPrice','$serialNum','$receiptVal','$totalIncentive','$mytime','$mydate')");
        if ($sql_reg_sales===true) {
            
             $isSuccess = true; 
        $comment = "Sales reported successfully!";
            
         
        }else{
          $isSuccess = false; 
        $comment ="Sales report failed!";
        }
      }else{
        $isSuccess = false; 
        $comment ="Duplicate sales report detected. Sales report failed!";
      }
    }else{
      //stop, sales has not been reported
      $isSuccess = false; 
        $comment ="Error! Unregistered sentiflex sales. Sales report failed!";
    }
  }
  
  //Check for sentinel and apply conditions
  if ($parentId==5){
    //if Sentinel, check if sales has been made to sentinel, check if duplicate and register sales
    //check if item is android or ios
    $chkItem=strtolower($itemName);
    if(substr($chkItem,0,6)=="iphone"){
      //check ios database to confirm valid sales
      $sql_sentinel_ios=mysqli_query($connect_ios,"SELECT * FROM device_tag_register WHERE reg_imei='$serialNum' LIMIT 1");
      if($sql_sentinel_ios->num_rows > 0){
        //check duplicate report and continue
        $sql_chk_entry=mysqli_query($connect_relay,"SELECT * FROM sales_register WHERE item_serial_number='$serialNum' AND parent_category_id='$parentId' AND current_state='Active' LIMIT 1");
        if(!$sql_chk_entry->num_rows > 0){
          $sql_reg_sales = mysqli_query($connect_relay,"INSERT INTO sales_register (company_id,store_id,user_id,item_name,item_code,parent_category_id,sub_category_id,item_quantity,item_price,item_serial_number,receipt_value,item_incentive,created_at,date_count) VALUES ('$companyId','$userStore','$userId','$itemName','$itemCode','$parentId','$subId','$itemQty','$itemPrice','$serialNum','$receiptVal','$totalIncentive','$mytime','$mydate')");
          if ($sql_reg_sales===true) {
            $_SESSION["response1"]="Sales reported successfully!";
          }else{
            $_SESSION["response2"]="Sales report failed!";
          }
        }else{
          $_SESSION["response2"]="Duplicate sales report detected. Sales report failed!";
        }
      }else{
        //stop, sales has not been reported
        $_SESSION["response2"]="Error! Unregistered sentinel iOS activation. Sales report failed!";
      }
    }else{
      //check android database to confirm valid sales
      $sql_sentinel_android=mysqli_query($connect_android,"SELECT * FROM device_tag_register WHERE reg_imei='$serialNum' LIMIT 1");
      if($sql_sentinel_android->num_rows > 0){
        //check duplicate report and continue
        $sql_chk_entry=mysqli_query($connect_relay,"SELECT * FROM sales_register WHERE item_serial_number='$serialNum' AND parent_category_id='$parentId' AND current_state='Active' LIMIT 1");
        if(!$sql_chk_entry->num_rows > 0){
          $sql_reg_sales = mysqli_query($connect_relay,"INSERT INTO sales_register (company_id,store_id,user_id,item_name,item_code,parent_category_id,sub_category_id,item_quantity,item_price,item_serial_number,receipt_value,item_incentive,created_at,date_count) VALUES ('$companyId','$userStore','$userId','$itemName','$itemCode','$parentId','$subId','$itemQty','$itemPrice','$serialNum','$receiptVal','$totalIncentive','$mytime','$mydate')");
          if ($sql_reg_sales===true) {
            $_SESSION["response1"]="Sales reported successfully!";
          }else{
            $_SESSION["response2"]="Sales report failed!";
          }
        }else{
          $_SESSION["response2"]="Duplicate sales report detected. Sales report failed!";
        }
      }else{
        //stop, sales has not been reported
        $_SESSION["response2"]="Error! Unregistered sentinel Android activation. Sales report failed!";
      }
    }     
  }

  //Register sales for others without condition
  if (($parentId==2) OR ($parentId==3) OR ($parentId==6)){
    $sql_reg_sales = mysqli_query($connect_relay,"INSERT INTO sales_register (company_id,store_id,user_id,item_name,item_code,parent_category_id,sub_category_id,item_quantity,item_price,item_serial_number,receipt_value,item_incentive,created_at,date_count) VALUES ('$companyId','$userStore','$userId','$itemName','$itemCode','$parentId','$subId','$itemQty','$itemPrice','$serialNum','$receiptVal','$totalIncentive','$mytime','$mydate')");
    if ($sql_reg_sales===true) {
      $isSuccess = true; 
        $comment ="Sales reported successfully!";
    }else{
      $isSuccess = true; 
        $comment ="Sales report failed!";
    }
  }
//End


//$sql_sales = mysqli_query($connection,"INSERT INTO sales_register( company_id, store_id, user_id, item_name, item_code, parent_category_id, sub_category_id, item_quantity, item_price, item_serial_number, receipt_value, receipt_number, item_incentive, current_state, created_at, date_count ) VALUES ('$companyId','$storeId','$userId','$itemName','$itemCode','$parentCategoryId','$subCategoryId','1','$itemPrice','$deviceImei','$price','$receiptNumber','$itemIncentive','1', '$getDateTime', '$getDate') ");
//$err = mysqli_error($connect_devfin);
  
  if ($sql_sales) {
    
      $response=array(
      'status' => true,
      'message' =>'Successful'
      );
  }else{
      $response=array(
      'status' => false,
      'message' =>'Failed',
      'error' =>$err
      
      );
   }
}

if ($command == 'loan_equity'){

$loanRefId = $_POST["loan_ref_id"]; 
$LoanEquity = $_POST["loan_equity"]; 

//Load load information
  $sql_loan = mysqli_query($connection,"SELECT * FROM loan_ WHERE reference_id='$loanRefId' "); 
  while($row_loan = mysqli_fetch_array($sql_loan)){
      $loanAmount = $row_loan["loan_amount"];
      $loanPeriod = $row_loan["loan_period"];      
  }







$LoanEquity = 0.3 * $loanAmount;



$loanCollected = $loanAmount - $LoanEquity;

$loanInterest = $loanCollected * 0.07 * $loanPeriod ;

$loanToBePaidBack = $loanCollected + $loanInterest;


$loanPerMonth = $loanToBePaidBack/$loanPeriod;

// number_format($loanPerMonth,2);


//Store values in database 
  $sql_tracker = mysqli_query($connection,"INSERT INTO loan_ (account_id) VALUES ('$accountId')");
  
  if ($sql_tracker) {
    mysqli_free_result($sql_tracker);
      $response=array(
      'status' => true,
      'message' =>'Successful'
      );
  }else{
      $response=array(
      'status' => false,
      'message' =>'Failed'
      );
   }
}

if($command == 'populate_item_list'){
    
    $code = $_POST["code"]; 
    
    
    //$deviceManufacturer = $_POST["device_manufacturer"];
 $deviceName = '';
    // $curDeviceName = '';
    
    
    $sql_get_value = mysqli_query($connection,"SELECT DISTINCTROW item_name FROM item_database WHERE parent_category_id='$code' ");
while ($row_get_value = mysqli_fetch_array($sql_get_value)){
   
        $deviceName = $deviceName.$row_get_value["item_name"]. ',';
            
        
    
}
    
     if($deviceName != null){
    


  $response=array(
  'status' => true,
  'items' => $deviceName,
  'isAvailable' => true,
  'message' =>'Success'
  );

}else{
    
    $response=array(
  'status' => false,
  'message' =>'No device found.'
  );
    
    
}
    
    
}

if($command == 'populate_sub_item_list'){
    
    $subCategory = $_POST["sub_category"]; 
    
    
    //get sub category id 
    
    $sql_get_value = mysqli_query($connection,"SELECT * FROM item_sub_category WHERE sub_category_name='$subCategory' ");
while ($row_get_value = mysqli_fetch_array($sql_get_value)){
    
    //sub
        $subId = $row_get_value["sub_id"];
}
    
    
 $deviceName = '';
   
    
    $sql_get_value = mysqli_query($connection,"SELECT DISTINCTROW item_name FROM item_database WHERE sub_category_id='$subId' ");
    while ($row_get_value = mysqli_fetch_array($sql_get_value)){
   
        $deviceName = $deviceName.$row_get_value["item_name"]. ',';
            
        }
    
     if($deviceName != null){
    


  $response=array(
  'status' => true,
  'items' => $deviceName,
  'isAvailable' => true,
  'message' =>'Success'
  );

}else{
    
    $response=array(
  'status' => false,
  'message' =>'No device found.'
  );
    
    
}
    
    
}

if($command == 'populate_all_item_list'){
    
    $accessoriesCode = 1; 
    $newDeviceCode = 2; 
    $matrixPreownedCode = 3; 
    $sentiflexCode = 4; 
    $sentinelCode = 5; 
    $tradeInCode = 6; 
    
    $code = $_POST["code"]; 
    
    $aItem = '';
    $ndItem = '';
    $mpItem = '';
    $sfItem = '';
    $sItem = '';
    $tiItem = '';
    
    $aSub = '';
    $ndSub = '';
    $mpSub = '';
    $sfSub = '';
    $sSub = '';
    $tiSub = '';
   
        $sql_get_value_a = mysqli_query($connection,"SELECT DISTINCTROW item_name FROM item_database WHERE parent_category_id='$accessoriesCode' ");
        while ($row_get_value_a = mysqli_fetch_array($sql_get_value_a)){
        $aItem = $aItem.$row_get_value_a["item_name"]. ',';
        }
        
        $sql_get_value_nd = mysqli_query($connection,"SELECT DISTINCTROW item_name FROM item_database WHERE parent_category_id='$newDeviceCode' ");
        while ($row_get_value_nd = mysqli_fetch_array($sql_get_value_nd)){
        $ndItem = $ndItem.$row_get_value_nd["item_name"]. ',';
        }
        
        $sql_get_value_mp = mysqli_query($connection,"SELECT DISTINCTROW item_name FROM item_database WHERE parent_category_id='$matrixPreownedCode' ");
        while ($row_get_value_mp = mysqli_fetch_array($sql_get_value_mp)){
        $mpItem = $mpItem.$row_get_value_mp["item_name"]. ',';
        }
        
        $sql_get_value_sf = mysqli_query($connection,"SELECT DISTINCTROW item_name FROM item_database WHERE parent_category_id='$sentiflexCode' ");
        while ($row_get_value_sf = mysqli_fetch_array($sql_get_value_sf)){
        $sfItem = $sfItem.$row_get_value_sf["item_name"]. ',';
        }
        
        $sql_get_value_s = mysqli_query($connection,"SELECT DISTINCTROW item_name FROM item_database WHERE parent_category_id='$sentinelCode' ");
        while ($row_get_value_s = mysqli_fetch_array($sql_get_value_s)){
        $sItem = $sItem.$row_get_value_s["item_name"]. ',';
        }
        
        $sql_get_value_ti = mysqli_query($connection,"SELECT DISTINCTROW item_name FROM item_database WHERE parent_category_id='$tradeInCode' ");
        while ($row_get_value_ti = mysqli_fetch_array($sql_get_value_ti)){
        $tiItem = $tiItem.$row_get_value_ti["item_name"]. ',';
        }
        
        
        
        //sub-category
        
        $sql_get_value_a_ = mysqli_query($connection,"SELECT DISTINCTROW sub_category_name FROM item_sub_category WHERE parent_category_id='$accessoriesCode' ");
        while ($row_get_value_a_ = mysqli_fetch_array($sql_get_value_a_)){
        $aSub = $aSub.$row_get_value_a_["sub_category_name"]. ',';
        }
        
        $sql_get_value_nd_ = mysqli_query($connection,"SELECT DISTINCTROW sub_category_name FROM item_sub_category WHERE parent_category_id='$newDeviceCode' ");
        while ($row_get_value_nd_ = mysqli_fetch_array($sql_get_value_nd_)){
        $ndSub = $ndSub.$row_get_value_nd_["sub_category_name"]. ',';
        }
        
        $sql_get_value_mp_ = mysqli_query($connection,"SELECT DISTINCTROW sub_category_name FROM item_sub_category WHERE parent_category_id='$matrixPreownedCode' ");
        while ($row_get_value_mp_ = mysqli_fetch_array($sql_get_value_mp_)){
        $mpSub = $mpSub.$row_get_value_mp_["sub_category_name"]. ',';
        }
        
        
         $sql_get_value_sf_ = mysqli_query($connection,"SELECT DISTINCTROW sub_category_name FROM item_sub_category WHERE parent_category_id='$sentiflexCode' ");
        while ($row_get_value_sf_ = mysqli_fetch_array($sql_get_value_sf_)){
        $sfSub = $sfSub.$row_get_value_sf_["sub_category_name"]. ',';
        }
        
         $sql_get_value_s_ = mysqli_query($connection,"SELECT DISTINCTROW sub_category_name FROM item_sub_category WHERE parent_category_id='$sentinelCode' ");
        while ($row_get_value_s_ = mysqli_fetch_array($sql_get_value_s_)){
        $sSub = $sSub.$row_get_value_s_["sub_category_name"]. ',';
        }
        
        $sql_get_value_ti_ = mysqli_query($connection,"SELECT DISTINCTROW sub_category_name FROM item_sub_category WHERE parent_category_id='$tradeInCode' ");
        while ($row_get_value_ti_ = mysqli_fetch_array($sql_get_value_ti_)){
        $tiSub = $tiSub.$row_get_value_ti_["sub_category_name"]. ',';
        }    
        
        // store list 
        
        $storeName = '';
    
    
    
         $sql_get_value_store = mysqli_query($connection_a,"SELECT DISTINCTROW store_name FROM store");
        while ($row_get_value_store = mysqli_fetch_array($sql_get_value_store)){
   
        $storeName = $storeName.$row_get_value_store["store_name"]. ',';
            
        
    
        }
        
      
        
       
        
       
        
        
    
     if($aItem != null){
    


  $response=array(
  'status' => true,
  'store_list' =>$storeName,
  'accessories_items' => $aItem,
  'new_device_items' => $ndItem,
  'matrix_preowned_items' => $mpItem,
  'sentinel_items' => $sItem,
  'sentiflex_items' => $sfItem,
  'trade_in_items' => $tiItem,
  
  'accessories_sub' => $aSub,
  'new_device_sub' => $ndSub,
  'matrix_preowned_sub' => $mpSub,
  'sentinel_sub' => $sSub,
  'sentiflex_sub' => $sfSub,
  'trade_in_sub' => $tiSub,
  
  'items' => $deviceName,
  'isAvailable' => true,
  'message' =>'Success'
  );

}else{
    
    $response=array(
  'status' => false,
  'message' =>'No device found.'
  );
    
    
}
    
    
}

if($command == 'populate_store_list'){
    
    
    
    
 $deviceName = '';
    
    
    
    $sql_get_value = mysqli_query($connection_a,"SELECT DISTINCTROW store_name FROM store");
while ($row_get_value = mysqli_fetch_array($sql_get_value)){
   
        $deviceName = $deviceName.$row_get_value["store_name"]. ',';
            
        
    
}
    
     if($deviceName != null){
    


  $response=array(
  'status' => true,
  'store_list' => $deviceName,
  'isAvailable' => true,
  'message' =>'Success'
  );

}else{
    
    $response=array(
  'status' => false,
  'message' =>'No device found.',
  'conn' =>$connection_a->err
  );
    
    
}
    
    
}

if($command == 'get_dashboard_data'){
    
    $userId = $_POST["user_id"];
   // $userId = 119;
    
    $mytime = gmdate("Y-m-d H:i:s", time() + 3600*(date("I")));
$mydate = gmdate("Y-m-d", time() + 3600*(date("I")));

//$thisMonth = date("Y-m-01");
//$curDate = gmdate("Y-m-d", time() + 3600*(date("I")));
//$thisDay = date("d");
//$lastMonth = date("Y-n-j", strtotime("first day of previous month"));

$thisMonth = gmdate("Y-m-01", time() + 3600*(date("I")));
$curDate = gmdate("Y-m-d", time() + 3600*(date("I")));
$thisDay = gmdate("d", time() + 3600*(date("I")));
$lastMonth = gmdate("Y-n-j", strtotime("first day of previous month"));
$lastMonthDay = gmdate("Y-n-j", strtotime("last day of previous month"));

//convert previous month
$convlastMonth = strtotime($lastMonth);
//add number of days to conversion
$curlastMonth = $convlastMonth + (86400*($thisDay-1));
//change date format
$curlastMonth = new DateTime("@$curlastMonth");  // convert UNIX timestamp to PHP DateTime
$curlastMonth = $curlastMonth->format('Y-m-d');

    
    
    //start 
    
    // today sales
    
    $sql_day_sales=mysqli_query($connection,"SELECT SUM(item_quantity) AS allDayReport FROM sales_register WHERE current_state='Active' AND date_count='$mydate' AND user_id='$userId'");
            while ($row_day_sales = mysqli_fetch_array($sql_day_sales)){
              $dayTotal=$row_day_sales["allDayReport"];
            }
            
     // all sales so far     
    $sql_all_sales=mysqli_query($connection,"SELECT SUM(item_quantity) AS allReport FROM sales_register WHERE current_state='Active' AND user_id='$userId'");
            while ($row_all_sales = mysqli_fetch_array($sql_all_sales)){
              $allTotal=$row_all_sales["allReport"];
        }
        
    // total sales amount
    
    $sql_mytotal=mysqli_query($connection,"SELECT item_incentive FROM sales_register WHERE current_state='Active' AND user_id='$userId'");
            $totalSum= 0;
            while ($row_mytotal = mysqli_fetch_array($sql_mytotal)){
              $totalSum+=$row_mytotal["item_incentive"];
            }
    // accessories sales
    
    $sql_sales_acc=mysqli_query($connection,"SELECT SUM(item_quantity) AS accAllReport FROM sales_register WHERE parent_category_id='1' AND current_state='Active' AND user_id='$userId'");
            while ($row_sales_acc = mysqli_fetch_array($sql_sales_acc)){
              $accTotal=$row_sales_acc["accAllReport"];
            }
    
    // today Accessories sales
    
    $sql_day_acc=mysqli_query($connection,"SELECT SUM(item_quantity) AS accDayReport FROM sales_register WHERE parent_category_id='1' AND current_state='Active' AND date_count='$mydate' AND user_id='$userId'");
            while ($row_day_acc = mysqli_fetch_array($sql_day_acc)){
              $accDay=$row_day_acc["accDayReport"];
            }
            
            
    // preowenre sales
    
    $sql_preowned_sales="SELECT * FROM sales_register WHERE parent_category_id='3' AND current_state='Active' AND user_id='$userId'";
            if ($result_preowned_sales=mysqli_query($connection,$sql_preowned_sales)){
               // Return the number of rows in result set
               $rowcount_preowned_sales=mysqli_num_rows($result_preowned_sales);
               
            }
            // Free result set
    mysqli_free_result($result_preowned_sales);
    
    // today preowned sales.
    
    $sql_day_ps="SELECT * FROM sales_register WHERE parent_category_id='3' AND current_state='Active' AND date_count='$mydate' AND user_id='$userId'";
            if ($result_day_ps=mysqli_query($connection,$sql_day_ps)){
              // Return the number of rows in result set
              $rowcount_day_ps=mysqli_num_rows($result_day_ps);
             
            }
            
    // new device 
    
    $sql_sales_newD="SELECT * FROM sales_register WHERE parent_category_id='2' AND current_state='Active' AND user_id='$userId'";
            if ($result_sales_newD=mysqli_query($connection,$sql_sales_newD)){
               // Return the number of rows in result set
               $rowcount_sales_newD=mysqli_num_rows($result_sales_newD);
               
            }
            // Free result set
            mysqli_free_result($result_sales_newD);
            
            $sql_day_newD="SELECT * FROM sales_register WHERE parent_category_id='2' AND current_state='Active' AND date_count='$mydate' AND user_id='$userId'";
            if ($result_day_newD=mysqli_query($connection,$sql_day_newD)){
              // Return the number of rows in result set
              $rowcount_day_newD=mysqli_num_rows($result_day_newD);
             
            }
    // sentiflex 
    
    $sql_sentinel="SELECT * FROM sales_register WHERE parent_category_id='4' AND current_state='Active' AND user_id='$userId'";
            if ($result_sentinel=mysqli_query($connection,$sql_sentinel)){
               // Return the number of rows in result set
               $rowcount_sentinelf=mysqli_num_rows($result_sentinel);
               
            }         
            
            $sql_day_sen="SELECT * FROM sales_register WHERE parent_category_id='4' AND current_state='Active' AND date_count='$mydate' AND user_id='$userId'";
            if ($result_day_sen=mysqli_query($connection,$sql_day_sen)){
              // Return the number of rows in result set
              $rowcount_day_senf=mysqli_num_rows($result_day_sen);
             
    }
    
    // sentinel
    
    $sql_sentinel="SELECT * FROM sales_register WHERE parent_category_id='5' AND current_state='Active' AND user_id='$userId'";
            if ($result_sentinel=mysqli_query($connection,$sql_sentinel)){
               // Return the number of rows in result set
               $rowcount_sentinel=mysqli_num_rows($result_sentinel);
               
            }         
            
            $sql_day_sen="SELECT * FROM sales_register WHERE parent_category_id='5' AND current_state='Active' AND date_count='$mydate' AND user_id='$userId'";
            if ($result_day_sen=mysqli_query($connection,$sql_day_sen)){
              // Return the number of rows in result set
              $rowcount_day_sen=mysqli_num_rows($result_day_sen);
              
            }
            
            // trade in
            $sql_trade_in="SELECT * FROM sales_register WHERE parent_category_id='6' AND current_state='Active' AND user_id='$userId'";
            if ($result_trade_in=mysqli_query($connection,$sql_trade_in)){
               // Return the number of rows in result set
               $rowcount_trade_in=mysqli_num_rows($result_trade_in);
               
            }       
            
              $sql_day_tra="SELECT * FROM sales_register WHERE parent_category_id='6' AND current_state='Active' AND date_count='$mydate' AND user_id='$userId'";
            if ($result_day_tra=mysqli_query($connection,$sql_day_tra)){
              // Return the number of rows in result set
              $rowcount_day_tra=mysqli_num_rows($result_day_tra);
              
            }
            
    // this month
    
    $sql_thismonth=mysqli_query($connection,"SELECT SUM(item_quantity) AS thisMonthReport FROM sales_register WHERE current_state='Active' AND user_id='$userId' AND date_count BETWEEN '$thisMonth' AND '$curDate'");
            while ($row_thismonth = mysqli_fetch_array($sql_thismonth)){
              $thismonth=$row_thismonth["thisMonthReport"];
            }
            

            //This month incentive
            $sql_mythism=mysqli_query($connection,"SELECT item_incentive FROM sales_register WHERE current_state='Active' AND user_id='$userId' AND date_count BETWEEN '$thisMonth' AND '$curDate'");
            $thismSum= 0;
            while ($row_mythism = mysqli_fetch_array($sql_mythism)){
              $thismSum+=$row_mythism["item_incentive"];
            }
            

            //Get Last month
            $sql_lastMonth=mysqli_query($connection,"SELECT SUM(item_quantity) AS lastMonthReport FROM sales_register WHERE current_state='Active' AND user_id='$userId' AND date_count BETWEEN '$lastMonth' AND '$lastMonthDay'");
            while ($row_lastMonth = mysqli_fetch_array($sql_lastMonth)){
              $lastmonth=$row_lastMonth["lastMonthReport"];
            }
            

            //Last month incentive
            $sql_mylastm=mysqli_query($connection,"SELECT item_incentive FROM sales_register WHERE current_state='Active' AND user_id='$userId' AND date_count BETWEEN '$lastMonth' AND '$lastMonthDay'");
            $lastmSum= 0;
            while ($row_mylastm = mysqli_fetch_array($sql_mylastm)){
              $lastmSum+=$row_mylastm["item_incentive"];
            }
            
    
    
    
    
    // ennd l
    
    //
    
        
  
     if(true){
    


  $response=array(
  'status' => true,
 
  'day_total' => $dayTotal,
  'all_total' => $allTotal,
  
  'total_sum' => $totalSum,
  
  'accessories_day' => $accDay,
  'accessories_total' => $accTotal,
  
  'preowned_day' => $rowcount_preowned_sales,
  'preowned_total' => $rowcount_day_ps,
  
  'newdevice_day' => $rowcount_day_newD,
  'newdevice_total' => $rowcount_sales_newD,
  
  //sentiflex
  'sentinel_day' => $rowcount_day_senf,
  'sentinel_total' => $rowcount_sentinelf,
  
  //sentinel
  'sentinel_day' => $rowcount_day_sen,
  'sentinel_total' => $rowcount_sentinel,
  
  
  //trade-in
  'trade_in' => $rowcount_trade_in,
  'trade_in_today' => $rowcount_trade_in,
  
  //fsfgfsfd
  'this_month' => $thismonth,
  'this_month_incentive' => $thismSum,
  
  'last_month' => $lastmonth,
  'last_month_incentive' => $lastmSum,
  
  
  
  
  'message' =>'Success'
  );

}else{
    
    $response=array(
  'status' => false,
  'message' =>'No device found.'
  );
    
    
}
    
    
}

if ($command == 'question'){
      
      
      if(true){

      $response=array(
      'questions' => [array(
          'question'=> "How old are you",
          'choices'=> ["24","45"],
          'correctAnswerIndex'=> "2",
          'explanation'=> "Subtract the current year from your date of birth"
         
            ),
            
        array(
          'question'=> "How old are you",
          'choices'=> ["24","45","26"],
          'correctAnswerIndex'=> "2",
          'explanation'=> "Subtract the current year from your date of birth"
         
            )
            ]
      );
     }else{
      $response=array(
      'status' => false,
      'message' =>'Device Not Found'
      );
    }   
}

header('Content-Type: application/json');
echo json_encode($response);
}
?>