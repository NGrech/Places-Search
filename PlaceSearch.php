
<?php

    set_time_limit(0);
    include 'vsm.php';

    $query = htmlspecialchars($_POST["query"]);
    $lng = htmlspecialchars($_POST["lng"]);
    $lat = htmlspecialchars($_POST["lat"]);
    $radius = htmlspecialchars($_POST["radius"]);
    
    //--- VARS ----
    
    $placeDetails = array();
    $tfidfText = array();
    $eval = TRUE;
    
    //evaluation code -----------------
    echo("<pre>");
    $lat = "-33.8670522";
    $lng = "151.1957362";
    $radius = "200";
    $type = array("restaurant");
    $Userquery = "i am hungry and want food";
    ///-------------------------------
    
    define("BASEURL", "https://maps.googleapis.com/maps/api/place/nearbysearch/json?");
    define("KEY", "AIzaSyCkuZNXcgYFcY96ERvNCzT0YItEar64P8I");
    define("DETAIL", "https://maps.googleapis.com/maps/api/place/details/json?placeid=");
    define("SLEEP", 10);
    define("LIMIT", 20);
    
    
    
    if(!$eval){
        getPlaces();
        $ranks = rank($tfidfText, $Userquery." ".implode(" ", $type));
        $ranks = modifyRank($ranks);
        arsort($ranks);
    }else{
        echo("<pre>");
        $placeNames= array("Sidney","London", "Slieam");
        $lats = array("-33.8670522", );
        $lngs = array("151.1957362");
        $evalTypes = array(array("cafe", "restaurant","food", "meal_delivery", "meal_takeaway"), array("bar", "night_club","casino"), array("lodging") );
        $evalQueries = array("find me a restaurant with good food", "Night club with a cheap bar", "a good hotel");
        
        for ($i=0; $i < 3; $i++) { 
            print_r($placeNames[$i])."<br>";
                echo("<pre>");
                $lat = $lats[$i];
                $lng = $lngs[$i] ;
                $type = $evalTypes[$i];
                $Userquery = $evalQueries[$i];
                $radius = "200";
                getPlaces();
                $ranks = rank($tfidfText, $Userquery." ".implode(" ", $type));
                $ranks = modifyRank($ranks);
                arsort($ranks);
                
                
        }
    }
 
 
    //FUNCTIONS------------------------------------------------------------------
 
    //function to generate the query string 
    function generateURL($nextpage=""){
        global $lng, $lat, $radius;
        $location = $lat.",".$lng;
        
        $newUrl = BASEURL."location=".$location."&radius=".$radius."&key=".KEY;
        
        if(!empty($nextpage)){
            $newUrl = $newUrl."&pagetoken=".$nextpage;
        } 
        return $newUrl;
    }
    
    //function to handle getting all the places and their details within the radius
    function getPlaces(){
        global $placeDetails, $type, $tfidfText;
        $url = generateURL();
        $results = runQuery($url);
        NPextract($results->results);
        $n = count($placeDetails);
        
        //near by searches
        while(isset($results->next_page_token)){
            if($n >= LIMIT){break;}
            $url = generateURL($results->next_page_token);
            $results = runQuery($url);
            NPextract($results->results);
            $n = count($placeDetails);
        }
        

        foreach ($placeDetails as $id => $info) {
            if(count($type)>0){
                if(count( array_intersect($type,$placeDetails[$id]["types"]))<=0){
                    unset($placeDetails[$id]);
                    unset($tfidfText[$id]);
                    continue;
                }
            }

            $durl = DETAIL.$id."&key=".KEY;
            $results = runQuery($durl);
            Dextract($results->result); 
        }
        

    }
    
    //function to run search query and handle queries per second limit
    function runQuery($url){
        
        $x = 1;
        $ret = file_get_contents($url);
        $results = json_decode($ret);
        while($results->status != "OK"){

            if($x > 5){throw new Exception('Over Limit');}
            usleep(SLEEP*$x);
            $x += 1;
            $ret = file_get_contents($url);
            $results = json_decode($ret);
        }
        return $results;
        
    }
    
    
    //function to extract details from near by search 
    function NPextract($results){
        global $placeDetails,$tfidfText;
        
        foreach ($results as $result) {
            $id = $result->place_id;
            $placeDetails[$id] = array();
            $placeDetails[$id]["name"] = $result->name;
            $placeDetails[$id]["lat"] = $result->geometry->location->lat;
            $placeDetails[$id]["lng"] = $result->geometry->location->lng;
            $placeDetails[$id]["types"] = $result->types;
            
            $tfidfText[$id] = $result->name." ".implode(" ", $result->types);

        }
    }

    function Dextract($result){
        global $placeDetails, $tfidfText;
        
        $placeDetails[$result->place_id]["address"] = $result->formatted_address;
        $tfidfText[$result->place_id] = $tfidfText[$result->place_id]." ".$result->formatted_address;
        if(isset($result->international_phone_number)){
             $placeDetails[$result->place_id]["phoneNumber"] = $result->international_phone_number;
        }
        if(isset($result->opening_hours->open_now)){
            if(!$result->opening_hours->open_now){
                unset($placeDetails[$result->place_id]);
                unset($tfidfText[$result->place_id]);
                return;
            }
            $placeDetails[$result->place_id]["open"] = $result->opening_hours->open_now;
        }
        if(isset($result->rating)){
            $placeDetails[$result->place_id]["rating"] = $result->rating;
        }
        
        if(isset($result->website)){
            $placeDetails[$result->place_id]["website"] = $result->website;
        }
        if(isset($result->url)){
            $placeDetails[$result->place_id]["mapsURL"] = $result->url;
        }
        if(isset($result->reviews)){
            $placeDetails[$result->place_id]["reviews"] = array();
            foreach ($result->reviews as $review) {
                array_push($placeDetails[$result->place_id]["reviews"], $review->text);
                $tfidfText[$result->place_id] = $tfidfText[$result->place_id]." ".$review->text;
            }
        }
        
    }

    //queries google maps api and returns the deails of a specified place 
    function getDetail($place_id){
        $url = DETAIL.$place_id."&key=".KEY;
        $result = file_get_contents($url);
        return json_decode($newResult); 
    }
    
    function modifyRank($ranks){
        global $placeDetails;
        foreach ($ranks as $id => $rank) {
            $ranks[$id] = ($ranks[$id] + NormManhattenDist($id))/2;
            
            if(isset($placeDetails[$id]["rating"])){
                $ranks[$id] = ($ranks[$id] +floatval($placeDetails[$id]["rating"])/5.0)/2; 
            }
             
        }
        return $ranks;
        
    }
    
    function NormManhattenDist($locid){
        global $placeDetails, $lng, $lat, $radius;
        $x1 = floatval($lat);
        $x2 = floatval($placeDetails[$locid]["lat"]);
        $y1 = floatval($lng);
        $y2 = floatval($placeDetails[$locid]["lng"]);
        return 1-(abs($x1-$x2)+abs($y1-$y2));
    }
    
    
    //-----------------------------------------------------------
    function evalGoogle(){
        global $lng, $lat, $radius, $Userquery;
        $location =  $lat.",".$lng;
        $q = urlencode($Userquery);
        $gurl = "https://maps.googleapis.com/maps/api/place/textsearch/json?query=".$q."&location=".$location."&radius=".$radius."&key=".KEY;
        print_r($gurl)."<Br>"."<Br>";
        $results = runQuery($gurl);
        print_r($results);
        
        $googleResults = array();
        $rn = 1;
        foreach ($results as $result) {
            $id = $result->place_id;
            if(isset($result->opening_hours->open_now)){
                $googleResults[$rn] = array("id" => $id, "open"=>$result->opening_hours->open_now, "name"=>$result->name);
            }else{
                 $googleResults[$i] = array("id" => $id, "open"=>"NA","name"=>$result->name);
            }
            $rn++;
        }
        return $googleResults;

        
    }
    
?>
