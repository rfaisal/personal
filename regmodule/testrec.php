<?php
    include_once("/cumc/regdb_functions.php");

   // Connect to the cumc-registration database (write access)
    try {
        $dbh = connect_to_cumc_reg_db(true);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die($e->getMessage());
    }

        include_once 'makereceipt.php';

        $htmlreceipt =  "<html><body>\n" . 
                            makereceipt($dbh, 1628, 'fr') .
                        "</body></html>\n";

    print $htmlreceipt;

?>

