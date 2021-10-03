<?php
///////
// Very simple script to clean the blankline from an RSS FEED.
$url = "https://www.bclandegem.be/?format=feed&type=rss";
 
if(isset($_GET['type'])){
   if(($_GET['type']) == "intraclub"){
      $url = "https://www.bclandegem.be/club/alle-artikels/club/intraclub?format=feed&type=rss";
   }
   else if(($_GET['type']) == "competitie") {
	   $url = "https://www.bclandegem.be/competitie/competitieverslagen?format=feed&type=rss";
   }
	   
}
 
if($lines = file($url)){
   foreach($lines as $n => $l){
     if(ord($l) != '10'){
       echo "$l";
     }
   }
}
exit;
?>