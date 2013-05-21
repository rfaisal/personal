<!-- this file should contain php code to get participants' list from database. Do not use any style here-->
<?php

    try {
        $dbh = new PDO("pgsql:dbname=cumc-registration;host=db.cms.math.ca",
                                        "cumcro", "1oz.P=1#C" );
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $sqlstmt =<<<FETCHROWSFROMDB
(SELECT c.lastname||', '||c.givennames as "name", c.university
FROM customer c LEFT JOIN itemordered i ON c.id = i.customer_id
WHERE c.permitparticipantlist = TRUE
AND i.itemcode = 'CONF'
AND i.status = 'PAID')
UNION
(SELECT 'Rahman, Faisal' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Hyde, Andrea' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Earl, Rodney' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Foster, Jodie' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Culos, Garrett' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Hunt, Spencer' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Jalaal, Maziyar' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'MacDonald, Braden' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'McPherson, Jen' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Popoff, Evan' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Stone, Rachel' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Nutini, Julie' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Kheiravar, Salma' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Lee, Paul' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Jackett, Neal' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Avalos Mar, Alejandro' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Robertson, Chloee' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Yeremi, Miayan' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Mandryk, Isaiah' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Davis, Chad' as "name", 'UBC Okanagan' as university)
UNION
(SELECT 'Mather, Kevin' as "name", 'University of Manitoba' as university)
UNION
(SELECT 'Tawfik, Selim' as "name", 'University of Waterloo' as university)
ORDER BY name
;
FETCHROWSFROMDB;
        $stmt = $dbh->prepare($sqlstmt);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            echo "<table id='registrantlist' CELLPADDING='0' CELLSPACING='15'><tr><th>Name</th><th>University</th></tr>\n";
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