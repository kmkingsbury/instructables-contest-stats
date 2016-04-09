<html>
<body>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once('includes/db.inc');

# mysql connect
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdatabase);
if (mysqli_connect_errno($mysqli)) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error();
}

$cid = htmlspecialchars($_GET["cid"]); 
$order ="";
$o = "a";
if (isset($_GET["o"])){
   $o = htmlspecialchars($_GET["o"]);
}
if ($o == "t"){
   $order = " order by pid";
} elseif ($o == "v"){
   $order = " order by views desc";
} elseif ($o == "f"){
   $order = " order by favs desc";
} elseif ($o == "p"){
   $order = " order by perc desc";	
}

?>
<a href="index.php">Back to List</a>
<?php 
$query = "SELECT * FROM contests where cid='$cid' limit 1";

//print "Query: ". $query;
$res = $mysqli->query($query);
$img = "";
for ($row_no = 0; $row_no < $res->num_rows; $row_no++){
 $row = mysqli_fetch_assoc($res);
 $img = $row['contestimg']; 
}
?>
<center><img src="http://www.instructables.com<?php echo $img ?>"></center>
<table align=center cellpadding=8>
<tr>
<th>Title</th>
<th><a href="<?php echo $_SERVER["SCRIPT_NAME"] ?>?cid=<?php echo $cid ?>&o=v">Views</a></th>
<th><a href="<?php echo $_SERVER["SCRIPT_NAME"] ?>?cid=<?php echo $cid ?>&o=f">Favs</a></th>
<th><a href="<?php echo $_SERVER["SCRIPT_NAME"] ?>?cid=<?php echo $cid ?>&o=p">% Favs</a></th>

<th>Age</th>
</tr>
<?php


$query = "select contestid, stats.pid, max(stats.creation_time) as ts, publishDate, max(stats.views) as views, max(stats.favs) as favs, (max(stats.favs)/max(stats.views)) * 100 as perc, title, postimage from stats INNER JOIN entries on stats.pid = entries.postid INNER JOIN posts on entries.postid=posts.pid where contestid='$cid' group by pid $order";
//print "Query: ". $query;
$res = $mysqli->query($query);
for ($row_no = 0; $row_no < $res->num_rows; $row_no++){
    $row = mysqli_fetch_assoc($res);
    ?>
    <tr>
    <?php
       #print_r($row);
       print "<td><a href=\"detail.php?cid=".$row['pid']."\">" . $row['title'] . "</a></td>";
       print "<td align=right>" . $row['views'] . "</td>";
       print "<td align=right>" . $row['favs'] . "</td>";
       print "<td align=right>" . sprintf("%02.2f", ($row['perc'])) . "</td>";
       $datetime1 = new DateTime($row['publishDate']);
       $datenow = new DateTime("now");
       $interval = date_diff($datetime1, $datenow);
       print "<td>".$interval->format('%R%a days') . "</td>";
    ?>
    </tr>
    <?php
   }
   ?><tr><td colspan=3>&nbsp;</td><td><?php echo  $res->num_rows . " entries"; ?></td></tr>


</table>
</body>
