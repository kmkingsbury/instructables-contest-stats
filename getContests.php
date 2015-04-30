<?php
require_once('db.inc');

# mysql connect
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdatabase);
if (mysqli_connect_errno($mysqli)) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error();
}


# Create curl resource 
$ch = curl_init(); 

# Set url 
curl_setopt($ch, CURLOPT_URL, "http://www.instructables.com/contest"); 

# Return the transfer as a string 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

#contains the output string 
$output = curl_exec($ch); 
#var_dump($output);


$conteststart = '/<div id="opened-contests" class="contest-landing-section">(.*?)<div id="previous-contests" class="contest-landing-section">/s';
#$contestjudge = '<div id="judged-contests" class="contest-landing-section">';
#$contestend   = '<div id="recently-closed-contests" class="contest-landing-section">';
#$end =          '<div id="previous-contests" class="contest-landing-section">';
#<div class="contest-preview"><a ><img /></a><div class="cover"><div class="cover-inner"><h1>This contest is open for entries. Closes midnight Jun 22, 2015</h1></div>



preg_match($conteststart, $output, $matches);
$block = $matches[1];
#print "Matches: ";
#print_r($matches[1]);

#Get All:
$contestblock = '/<div class="contest-preview"><a.*?title="(.*?)".*?href="(.*?)".*?<h1>(.*?)<\/h1>.*?(?:<\/div>){4}/s';
preg_match_all($contestblock, $block, $contests);
#print_R($contests);


$contestlookup = array();
$res = $mysqli->query("SELECT cid, contesturl FROM contests ORDER BY cid DESC");
for ($row_no = 0; $row_no < $res->num_rows; $row_no++){
    $row = mysqli_fetch_assoc($res);
    $contestlookup["'".$row['contesturl']."'"] = $row['cid'];
}
print_R($contestlookup);


for ($i=0; $i < sizeof($contests[0]); $i++){
    #For Each:
    $contests[1][$i] = $mysqli->real_escape_string($contests[1][$i]);
    $contests[2][$i] = $mysqli->real_escape_string($contests[2][$i]);


    #Iterate 
    # 1 = title 2 = url 3= date
    print $contests[1][$i] ." - " . $contests[2][$i] . " = ". $contests[3][$i] . "\n";

    $datepattern = '/(\w+ \d{1,2}, \d{4})/';
    preg_match($datepattern, $contests[3][$i], $m);
    $dpat= date('Y-m-d',strtotime($m[1]));
    $dpat = $mysqli->real_escape_string($dpat);
    $cid = 0;
    if (array_key_exists("'".$contests[2][$i]."'", $contestlookup)) {
       $cid = $contestlookup["'".$contests[2][$i]."'"];
       printf("Found: %d\n", $cid);
    } else {
       #insert
       if (!$mysqli->query("Insert into contests (contestname, contesturl, contestends, creation_time) values ('" . $contests[1][$i]."', '".$contests[2][$i]."', '". $dpat. "', NOW())")){ 
      	  printf("Error: %s\n", $mysqli->sqlstate);
	  printf("Errormessage: %s\n", $mysqli->error);
       }
       printf("Affected rows (UPDATE): %d\n", $mysqli->affected_rows);	   
       $contestlookup[$contests[2][$i]] = $mysqli->insert_id;
       $cid = $mysqli->insert_id;
    }   

    #open Contest, get Entries:
    # set url
    curl_setopt($ch, CURLOPT_URL, "http://www.instructables.com" . $contests[2][$i]);

    #return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    # contains the output string
    $body = curl_exec($ch);    

    $queue = array();


    preg_match('/<span class="statsbox_number" id="entry-count">(\d+)<\/span>/s',$body, $c); 
    $count = $c[1];
    print "Stat Entries: " . $count . "\n";
    $mysqli->query("UPDATE contests SET contestentries=$count WHERE cid = $cid");
    printf("Affected rows (UPDATE): %d\n", $mysqli->affected_rows);    

    $entriespage = array('1' => $body);


    if (preg_match('/<a href=".*?\/entries\/\d+\/" id="load-more">.*?Load More Entries\s+<\/div>/s', $body)){
        print "Found LOAD MORE\n";
        #http://www.instructables.com/contest/3dprintingcontestxxl/entries/2/  
	#<div class="pagination">\s+<ul>\s+<li><a href="/contest/3dprintingcontestxxl/entries/">1</a></li>
	#<li class="disabled"><a>2</a></li>
	#</ul>
	curl_setopt($ch, CURLOPT_URL, "http://www.instructables.com" .$contests[2][$i]. "entries/2/");
	$body = curl_exec($ch);
	$entriespage['2'] = $body;
	preg_match('/<div class="pagination">\s+<ul>(.*?)<\/ul>/s', $body, $m);
	print "M: ".sizeof($m);
	preg_match_all('/<li.*?><a.*?>(\d+)<\/a><\/li>/s',$m[1], $pages);
	print "Pages:";
	print_R($pages);
	for ($j=0; $j < sizeof($pages[0]); $j++){	
	 if ($pages[1][$j] <= 2) { continue; } 
	 $suffix = $contests[2][$i]."/entries/". $pages[1][$j]."/";
	 $entriespage[$suffix] = 1;
	}
    }	
    print "Entry Pages:  ";
    print count($entriespage);
    print "\n";

    foreach ($entriespage as $k => $v){
    	$body = "";    
    	if (strcmp($k, '1') == 0 || strcmp($k, '2') == 0 ) { 
  	  $body = $v;
	} else {
	  curl_setopt($ch, CURLOPT_URL, "http://www.instructables.com" . $k);

	  #return the transfer as a string
	  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	  # contains the output string
    	  $body = curl_exec($ch);
	}
        $pat =  '/<div class\s*=\s*\'entries\'>(.*?<\/div>)\s+<\/div>/s';	
    	preg_match($pat, $body, $blocks); 
    	#print_R($blocks[1]);

    	preg_match_all('/<div class="thumbnail">\s+<a href="(.*?)"><img src="(.*?)"\/>.*?<a class="author" href=".*?">(.*?)<\/a>\s+<\/div>.*?<\/a>\s+<\/div>/s', $blocks[1], $entries);
    	#print "Entries: " . count($entries) . "\n";
    	#print_R($entries);

    	for ($j=0; $j < sizeof($entries[0]); $j++){    
            $url = $entries[1][$j];
    	    $img = $entries[2][$j];
	    $queue[$url] = $img;
        }	     
    }
    print "Entry Queue: ".count($queue) ." \n";
    print "Entry Queue: ";
    print_R($queue);
    print "\n";

    foreach ($queue as $url => $image){        

        #find: INSERT OR UPDATE
	$res = $mysqli->query("select * from posts where posturl='".$mysqli->real_escape_string($url)."' limit 1");
	$row = mysqli_fetch_assoc($res);		
	

	# set url
	curl_setopt($ch, CURLOPT_URL, "http://www.instructables.com" . $url);
	$url = 	$mysqli->real_escape_string($url);
	#return the transfer as a string
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

	# contains the output string
	$body = curl_exec($ch);
	
	preg_match('/<meta itemprop="datePublished" content="(.*?)" \/>/s', $body, $d);
	$datepub = $mysqli->real_escape_string($d[1]);

	preg_match('/<meta itemprop="interactionCount" content="favorites:(\d+)" \/>/s', $body, $f);
	$fav = $mysqli->real_escape_string($f[1]);

	preg_match('/<meta itemprop="interactionCount" content="views:(\d+)" \/>/s', $body, $vi);
	$views = $mysqli->real_escape_string($vi[1]);

	preg_match('/<meta property="og:title"  content="(.*?)"\/>/s', $body, $t);
	$title = $mysqli->real_escape_string($t[1]);

	preg_match('/<span class="author" itemprop="author">(.*?)<\/span>/s', $body, $a);
	$author = $mysqli->real_escape_string($a[1]);

	$pid =0;
	if ($res->num_rows == 0){
	 #insert
	 $mysqli->query("insert into posts (posturl, title, postimage, author, posted) values ('".
	 	 $url . "', ".
		 "'". $title . "', ".
		 "'". $image . "', ".
 		 "'". $author . "', ".	
		 "'". $datepub . "')");
	 $pid = $mysqli->insert_id;		 
	 print "New Post: $pid \n"; 
	} else {
	 $pid = $row['pid'];
	 print "Existing Post: $pid\n";
	}
	
	if (! $mysqli->query("insert into stats (pid, views, favs, creation_time) values ($pid, $views, $fav, NOW())")){
 	
	  printf("Error: %s\n", $mysqli->sqlstate);
          printf("Errormessage: %s\n", $mysqli->error);
       }	 

    }
}
curl_close($ch); 


