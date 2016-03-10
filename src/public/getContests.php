<?php
$debug  = 0;
# Set for dates, required.
date_default_timezone_set('America/Chicago');

# Get "variables"
require_once('includes/db.inc');

# mysql connect
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdatabase);
if (mysqli_connect_errno($mysqli)) {
echo "Failed to connect to MySQL: " . $mysqli->connect_error();
}

# https://market.mashape.com/devru/instructables
# Setup the Mashables Request:
#curl --get --include 'https://devru-instructables.p.mashape.com/list?limit=500' \
#  -H 'X-Mashape-Key: xxx' \
#  -H 'Accept: application/json'
$ch_api = curl_init();
curl_setopt($ch_api, CURLOPT_HTTPHEADER, array(
'X-Mashape-Key: '.$mashable_key,
'Accept: application/json'
));


# Loop through the API get x of the recent entries.
$origlimitperquery = 100;
$limitperquery = $origlimitperquery; // Don't overload the API
$iterate = 1; // Limit * iteate = total records
$offset = 0; // temp variable used in loop.
$start = 0;
$apilookup = array();

function createpost($e, $listorentity = 0){
global $mysqli;

# insert new post
$featured = 0;
if ($mysqli->real_escape_string($e->{'featured'}) == true){
$featured = 1;
}

#$r = (1 == $v) ? 'Yes' : 'No';
$instructableType = (property_exists($e,'instructableType')) ? $mysqli->real_escape_string($e->{'instructableType'}) : '';
$channel = (property_exists($e,'channel')) ?  $mysqli->real_escape_string($e->{'channel'}) : '';
$category = (property_exists($e,'category'))? $mysqli->real_escape_string($e->{'category'}) : '';


$query = "insert into posts (piid, url, title, author, postimage, instructableType, featured, channel, category, publishDate) values (".
"'".$mysqli->real_escape_string($e->{'id'}) . "', ".
"'".$mysqli->real_escape_string($e->{'url'}) . "', ".
"'". $mysqli->real_escape_string($e->{'title'}) . "', ";
if ($listorentity == 0){
# from list
$query .= "'". $mysqli->real_escape_string($e->{'author'}) . "', ";
} else {
# from details
$query .= "'". $mysqli->real_escape_string($e->{'author'}->{'screenName'}) . "', ";
}
$query .= "'". $mysqli->real_escape_string($e->{'imageUrl'}) . "', ".
"'". $instructableType . "', ".
"". $featured . ", ".
"'". $channel . "', ".
"'". $category . "', ".
"'". $mysqli->real_escape_string(date('Y-m-d',strtotime($e->{'publishDate'}))). "')";
if (! $mysqli->query($query)){
printf("Error: %s\n", $mysqli->sqlstate);
printf("Errormessage: %s\n", $mysqli->error);
}

return $mysqli->insert_id;

}

function fetchsinglepostbyidsapi($instructablesid, $pid, $createdbpost){
global $ch_api, $mysqli, $last, $apilookup;
$url = "https://devru-instructables.p.mashape.com/json-api/showInstructable?id=".$instructablesid;
if ($debug == 1){  print "URL: " . $url;}

# Set url
curl_setopt($ch_api, CURLOPT_URL, $url);

# Return the transfer as a string
curl_setopt($ch_api, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch_api, CURLOPT_CONNECTTIMEOUT, 10);

#contains the output string
$output = curl_exec($ch_api);
$json_api = json_decode($output, false);
$e = $json_api;
print "json_api for $url:\n";
if (!is_object($e)) {
//error array
print "Err!";
exit();
}
#var_dump($json_api);
#exit;
$apilookup[$e->{'url'}] = $e;
if ($createdbpost == true){
$pid = createpost($e,1);
$apilookup[$e->{'url'}]->{'pid'} = $pid;
}

$views = $mysqli->real_escape_string($e->{'views'});
$favs = (property_exists($e, 'favorites'))? $mysqli->real_escape_string($e->{'favorites'}) : 0;

$e->{'urlString'} = htmlentities($e->{'urlString'}, ENT_QUOTES, "UTF-8");;
#add it to our array, so don't refetch
$apilookup["/id/". $e->{'urlString'} ."/"] = $e;
$apilookup["/id/". $e->{'urlString'} ."/"]->{'pid'} = $pid;
print "Adding URL:". "/id/". $e->{'urlString'} ."/ -> $pid \n";


# Exists and new, update stats.
# print "insert into stats (pid, views, favs, creation_time) values ($pid, $views, $fav, NOW())";
if (! $mysqli->query("insert into stats (pid, views, favs, creation_time) values (" .$pid.", '".$views ."', '".$favs."', NOW())")){
printf("Error: %s\n", $mysqli->sqlstate);
printf("Errormessage: %s\n", $mysqli->error);
}

return "/id/". $e->{'urlString'} ."/";
}
# this is a function, used at end for not found entries;
function fetchpostsapi($start, $limitperquery, $offset, $iterate){
# Loop
global $ch_api, $mysqli, $last, $apilookup, $debug;


for ($i =$start; $i<$iterate; $i++){



$url = "https://devru-instructables.p.mashape.com/list?limit=".$limitperquery."&offset=". $offset."&sort=recent";
$offset += $limitperquery;
if ($debug == 1){  print "URL: " . $url;}

# Set url
curl_setopt($ch_api, CURLOPT_URL, $url);

# Return the transfer as a string
curl_setopt($ch_api, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch_api, CURLOPT_CONNECTTIMEOUT, 10);

#contains the output string
$output = curl_exec($ch_api);
$json_api = json_decode($output, false);

$last = "";
$pid = 0;
#var_dump($json_api);
#print sizeof($json_api->{'items'}) . "\n";
for ($j = 0; $j < sizeof($json_api->{'items'}); $j++){
$e = $json_api->{'items'}[$j];
if ($debug == 1){ print "ID: " . $e->{'url'} . "\n";}
$apilookup[$e->{'url'}] = $e;

#id, url, title, author, publishDate, imageUrl
$last = $e->{'url'};
$views = $mysqli->real_escape_string($e->{'views'});
$favs = (property_exists($e, 'favorites'))? $mysqli->real_escape_string($e->{'favorites'}) : 0;
$pid = -1;
$res = $mysqli->query("select * from posts where url='".$mysqli->real_escape_string($e->{'url'})."' limit 1");
$row = mysqli_fetch_assoc($res);
if ($res->num_rows == 0){
$pid = createpost($e);
print "New Post: $pid \n";
} else {
$pid = $row['pid'];
print "Existing: $pid \n";
}
$apilookup[$e->{'url'}]->{'pid'} = $pid;
# Exists and new, update stats.
# print "insert into stats (pid, views, favs, creation_time) values ($pid, $views, $fav, NOW())";
if (! $mysqli->query("insert into stats (pid, views, favs, creation_time) values (" .$pid.", '".$views ."', '".$favs."', NOW())")){
printf("Error: %s\n", $mysqli->sqlstate);
printf("Errormessage: %s\n", $mysqli->error);
}

} // end for $j
print "Size: " . sizeof($json_api->{'items'}) . "\n";
} // end for start offset loop
return $last;
}

function fetchrawhtmlpost($url){
global $ch, $mysqli;

# set url
curl_setopt($ch, CURLOPT_URL, "http://www.instructables.com" . $url);

#return the transfer as a string
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

# contains the output string
$body = curl_exec($ch);
preg_match('/LogHit\(\'([A-Z0-9]+)\',\'[A-Z0-9]\',\'[A-Z0-9]+\'\);/s', $body, $m);
$piid = $m[1];

# query db first
$res = $mysqli->query("select * from posts where piid='".$mysqli->real_escape_string($piid)."' limit 1");
$row = mysqli_fetch_assoc($res);
if ($res->num_rows == 0){
return fetchsinglepostbyidsapi($piid, -1, true);
} else {
$pid = $row['pid'];
$apilookup["/id/". $row['url'] ."/"] = new stdClass();
$apilookup["/id/". $row['url'] ."/"]->{'pid'} = $pid;
print "Raw Existing: $pid \n";
return $apilookup["/id/". $row['url'] ."/"];
}

//return fetchsinglepostbyidsapi($piid, -1, true);
}


$last = fetchpostsapi($start, $limitperquery, $offset, $iterate);
$offset += ($limitperquery*($iterate- $start));

#print "Last:\n";
#var_dump($apilookup[$last]);



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
$contestlookup["'".$mysqli->real_escape_string($row['contesturl'])."'"] = $row['cid'];
}
print_R($contestlookup);

# Big Loop for Each Contest, pulls all entries into an array/queue.
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
#insert = new Contest
if (!$mysqli->query("Insert into contests (contestname, contesturl, contestends, creation_time) values ('" . $contests[1][$i]."', '".$contests[2][$i]."', '". $dpat. "', NOW())")){
  printf("Error: %s\n", $mysqli->sqlstate);
	printf("Errormessage: %s\n", $mysqli->error);
}

if ($notify == true){
 mail ( $email , "New Contest" , "New Contest: ".$contests[1][$i].", Ends: $dpat");
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
      #print "M: ".sizeof($m);
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

# This looks at the pages and finds the entries
foreach ($entriespage as $k => $v){
$body = "";
if (strcmp($k, '1') == 0 || strcmp($k, '2') == 0 ) {
     $body = $v;
    } else {
 # Have to get additional pages
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
$url = htmlentities($entries[1][$j], ENT_QUOTES, "UTF-8");;
  $img = $entries[2][$j];
      $queue[$url] = $img;
}
}


print "Entry Queue: ".count($queue) ." \n";
print "Entry Queue: ";
print_R($queue);
print "\n";

# Get the entries we have for the particular contest
# Later if the post doesn't exist for the contest
# then it is a new entry to the contest and an entries
# record is made
$elook = array();
$res = $mysqli->query("select postid from entries where contestid=\"$cid\"");
for ($row_no = 0; $row_no < $res->num_rows; $row_no++){
  $row = mysqli_fetch_assoc($res);
    $elook['p'.$row['postid']] = 1;
}
#print "Elook:\n";
#print_r($elook);


# Now iterate through the post/entry queue
# look for it in the entry lookup (elook), add if new entry
# look for it in the posts table to see if we have details
# otherwise add to details queue for additional API query.
$unknownpostqueue = array();
$statsqueue = array();
foreach ($queue as $url => $image){

#find: INSERT OR UPDATE
if ($debug == 1){ print "URL: ".$url."\n" ;}

$inapi = true;
$pid =-1;
if (! array_key_exists($url,$apilookup) ){
  # not found in apilookup, set flag to either find post or update stats
  $inapi = false;
} else {
	$pid = $apilookup[$url]->{'pid'};
	"pid: $pid\n";
}

if ($inapi == false){
  //not in api, maybe in posts, if so add to statsqueue:
  $res = $mysqli->query("select url,piid,pid from posts where url='".$mysqli->real_escape_string($url)."' limit 1");
	$row = mysqli_fetch_assoc($res);

	if ($res->num_rows == 0){
    #print "Not found";
	  $unknownpostqueue["$url"] = -1;
	} else {
	  $pid = $row['pid'];
	  print "Existing Post: $pid\n";
    # Should do statsqueue but that queries for way more info than we
    # need at 1 instructable per request, so easier to just do via the
    # regular List API.
    $unknownpostqueue["$url"] = -1;
	}
}

#print "PID: $pid and Array:" .array_key_exists('p'.$pid,$elook) ."\n";
# if in the elook then it is a contest entry, if not there add it
if ($pid != -1 && ! array_key_exists('p'.$pid,$elook) ){
  //not in api, maybe in posts, if so add to statsqueue:
  $res = $mysqli->query("select url,piid,pid from posts where url='".$mysqli->real_escape_string($url)."' limit 1");
  $row = mysqli_fetch_assoc($res);
  if ($res->num_rows > 0){
     $pid = $row['pid'];
     print "Existing Post: $pid - adding elook key\n";
		        }
	     if (! $mysqli->query("insert into entries (postid, contestid) values (".
	        "'". $pid . "', ".
             	"'". $cid . "' ".
             	")")){
		   printf("Error: %s\n", $mysqli->sqlstate);
		   printf("Errormessage: %s\n", $mysqli->error);
            } else {
		$elook['p'.$pid] = 1;
	    }
        }
    } //end queue
    # Examine our Queuse:
    #print "Stats Queue: ". sizeof($statsqueue)."\n";
    print "New Api Queue: ". sizeof($unknownpostqueue)."\n";
    #print_r(array_keys($apilookup));

    # Go through the not found entries queue, but change api limits;

    $newlimitperquery = 10;
    if ($limitperquery != $newlimitperquery){
      $iterate = floor($origlimitperquery/$newlimitperquery);
      $limitperquery = $newlimitperquery;
      $start = $iterate - 1; //minus due to the ++Start;
      $offset = $origlimitperquery;
    }

    $loopcount = 0;
    $loopmax = 10;
    while (sizeof($unknownpostqueue) > 0 && $loopcount < $loopmax){
      # fetch next offset block
      ++$start;
      $iterate = $start+1;
      print "Searching: Queuesize: ".sizeof($unknownpostqueue). " start:". $start. " iterate:". $iterate . " offset: ".$offset . " limit: ". $limitperquery."\n";
      #var_dump($unknownpostqueue);
      # this is a function, used at end for not found entries;

      $last = fetchpostsapi($start, $limitperquery, $offset, $iterate);
      $offset += ($limitperquery*($iterate- $start));

      # look for our entries
      $index = -1;
      foreach ($unknownpostqueue as $url => $v){
        ++$index;

        if ($debug == 1){ print "URL: ".$url."\n" ;}
  	    $res = $mysqli->query("select pid from posts where url='".$mysqli->real_escape_string($url)."' limit 1");
        $row = mysqli_fetch_assoc($res);

        if ($res->num_rows > 0){
  	      //found
  	      print "found splice out - ";
          array_splice($unknownpostqueue, $index, 1);

          # if in the elook then it is a contest entry, if not there add it
          if (! array_key_exists('p'.$row['pid'],$elook) ){
		

  	        if (!	   $mysqli->query("insert into entries (postid, contestid) values (".
  	          "'". $pid . "', ".
              "'". $cid . "' ".
              ")")){
  		          printf("Error: %s\n", $mysqli->sqlstate);
  		          printf("Errormessage: %s\n", $mysqli->error);
              } else {
		$elook['p'.$pid] = 1;
		print "added key\n";
	      }
          } else {
		print "key exists\n";
	  }	

          continue; // exit our foreach
        }
      } //end foreach
      $loopcount++;

    } //end while

    # any not found, get page, find log hit
    $index = -1;
    foreach ($unknownpostqueue as $url => $v){
      ++$index;
      $res = $mysqli->query("select url,piid,pid from posts where url='".$mysqli->real_escape_string($url)."' limit 1");
      $row = mysqli_fetch_assoc($res);

      if ($res->num_rows == 0){
        print "Not found in posts, fetch raw";
        $object = fetchrawhtmlpost($url);
        array_splice($unknownpostqueue, $index, 1);
	if (! array_key_exists('p'.$object->{'pid'},$elook) ){
                

                if (!      $mysqli->query("insert into entries (postid, contestid) values (".
                  "'". $object->{'pid'} . "', ".
              "'". $cid . "' ".
              ")")){
                          printf("Error: %s\n", $mysqli->sqlstate);
                          printf("Errormessage: %s\n", $mysqli->error);
              } else {
                $elook['p'.$object->{'pid'}] = 1;
                print "added key\n";
              }
          } else {
                print "key exists\n";
          }
      }
    }

    # Go through the stats queue:
    while (sizeof($statsqueue) > 0){
      $url ="";
      $index = -1;
      foreach ($statsqueue as $instructablesid => $pid){
        ++$index;
        $url = fetchsinglepostbyidsapi($instructablesid, $pid);

        if (array_key_exists($url,$apilookup) ){
          print "Was inserted:\n";
        #  var_dump($apilookup[$url]);
        } else {
          print "Not found! $url\n";
        }
        array_splice($statsqueue, $index, 1);

      } // end foreach
    } // end while
}
curl_close($ch);
