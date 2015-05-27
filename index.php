<html>
<body>
<?php
require_once('db.inc');

# mysql connect
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbdatabase);
if (mysqli_connect_errno($mysqli)) {
    echo "Failed to connect to MySQL: " . $mysqli->connect_error();
}
?>
<table align=center cellpadding=8>
<tr>
<th>ID</th>
<th>Name</th>
<th>Entries</th>
<th>End Date</th>
</tr>
<?php
$res = $mysqli->query("SELECT * FROM contests ORDER BY contestends,cid DESC");
for ($row_no = 0; $row_no < $res->num_rows; $row_no++){
    $row = mysqli_fetch_assoc($res);
    ?>
    <tr>
    <?php
    print "<td>" . $row['cid'] . "</td>";
    print "<td><a href=\"detail.php?cid=".$row['cid']."\">" . $row['contestname'] . "</a></td>";
    print "<td>" . $row['contestentries'] . "</td>";
    $datetime1 = new DateTime($row['contestends']);
    $datenow = new DateTime("now");
    $interval = date_diff($datenow, $datetime1);
    print "<td>" . $row['contestends'] . " ";
    if ($datenow > $datetime1){
        print "<span style=\"color: #F00;\">";
    } else {
        print "<span style=\"color: #0F0;\">";
    }
    print $interval->format('%R%a days'); 
    print "</span></td>";
    ?>
    </tr>
    <?php
   }
?>
</table>
</body>
