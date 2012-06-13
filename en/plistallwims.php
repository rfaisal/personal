<!-- this file should contain php code to get participants' list from database. Do not use any style here-->
<?php
$count=0;
    try {
        $dbh = new PDO("pgsql:dbname=cumc-registration;host=db.cms.math.ca",
                                        "cumcro", "1oz.P=1#C" );
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlstmt =<<<FETCHROWSFROMDB
SELECT c.lastname||', '||c.givennames as "name", c.university, c.email,
c.willbe19, c.dietaryrestrictions, c.tshirtsize, c.givingtalk, c.talklength,
c.permitparticipantlist, c.language
FROM customer c LEFT JOIN itemordered i ON c.id = i.customer_id
WHERE i.itemcode = 'WDINN'
AND i.status = 'PAID'
ORDER BY c.lastname, c.givennames;
FETCHROWSFROMDB;
        $stmt = $dbh->prepare($sqlstmt);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "<table id='registrantlist' CELLPADDING='0' CELLSPACING='15'><tr><th>Name</th><th>University</th><th>Email</th><th>willbe19</th><th>dietaryrestrictions</th><th>tshirtsize</th><th>givingtalk</th><th>talklength</th><th>permitparticipantlist</th><th>language</th></tr>\n";
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "  <tr><td>" . htmlspecialchars($r['name']) . "</td>\n";
                echo "      <td>" . htmlspecialchars($r['university']) . "</td>\n";
				 echo "      <td>" . htmlspecialchars($r['email']) . "</td>\n";
				 echo "      <td>" . ($r['willbe19']?"Yes":"No") . "</td>\n";
				 echo "      <td>" . htmlspecialchars($r['dietaryrestrictions']) . "</td>\n";
				 echo "      <td>" . htmlspecialchars($r['tshirtsize']) . "</td>\n";
				 echo "      <td>" . ($r['givingtalk']?"Yes":"No")  . "</td>\n";
				 echo "      <td>" . htmlspecialchars($r['talklength']) . "</td>\n";
				 echo "      <td>" . ($r['permitparticipantlist']?"Yes":"No")  . "</td>\n";
				 echo "      <td>" . htmlspecialchars($r['language']) . "</td></tr>\n";
            	$count++;
            }
            echo "</table>\n";
        } else {
            echo "<p>No registrants on file at this time.</p>\n";
        }
    } catch(PDOException $e) {
        die("PDO Error: ". $e->getMessage());
    }
echo "<br /> <br />Total=".$count;
?>