<!-- this file should contain php code to get participants' list from database. Do not use any style here-->
<?php

    try {
        $dbh = new PDO("pgsql:dbname=cumc-registration;host=db.cms.math.ca",
                                        "cumcro", "1oz.P=1#C" );
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlstmt =<<<FETCHROWSFROMDB
SELECT c.lastname||', '||c.givennames as "name", c.university
FROM customer c LEFT JOIN itemordered i ON c.id = i.customer_id
WHERE c.permitparticipantlist = TRUE
AND i.itemcode = 'CONF'
AND i.status = 'PAID'
ORDER BY c.lastname, c.givennames;
FETCHROWSFROMDB;
        $stmt = $dbh->prepare($sqlstmt);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "<table id='registrantlist' CELLPADDING='0' CELLSPACING='15'><tr><th>Nom</th><th>Universit&eacute;</th></tr>\n";
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "  <tr><td>" . htmlspecialchars($r['name']) . "</td>\n";
                echo "      <td>" . htmlspecialchars($r['university']) . "</td>\n";
            }
            echo "</table>\n";
        } else {
            echo "<p>No registrants on file at this time.</p>\n";
        }
    } catch(PDOException $e) {
        die("PDO Error: ". $e->getMessage());
    }

?>