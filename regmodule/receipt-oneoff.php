<?php
$pmtid = 0;     // set one of these to zero (0)
$custid = 138;


    include_once("rf");


    // Connect to the cumc-registration database (write access)
    try {
        $dbh = connect_to_cumc_reg_db(true);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        die("Cannot connect to cumc database: " . $e->getMessage());
        exit(0);
    }

    if ($pmtid < 1) {
        $sh = $dbh->prepare("select id from payment where customer_id = ?");
        $sh->execute(array($custid));
        $p = $sh->fetch(PDO::FETCH_ASSOC);
        $pmtid = $p['id'];
    }

    $sh = $dbh->prepare("select customer.* from payment join customer on payment.customer_id = customer.id where payment.id = ?");
    $sh->execute(array($pmtid));
    $c = $sh->fetch(PDO::FETCH_ASSOC);
    $custid = $c['id'];

        include_once 'makereceipt.php';

        $htmlreceipt =  "<html><body>\n" . 
                            makereceipt($dbh, $pmtid, NULL) .
                        "</body></html>\n";


        include 'Mail.php';
        include 'Mail/mime.php';

        // determine language for the email message
        $lang = $c['language'];

        $hdrs = array(
                      'From'    => ($lang == 'fr'
                                        ? 'inscription@ccem.math.ca'
                                        : 'registration@cumc.math.ca'),
                      'Subject' => ($lang == 'fr'
                                        ? "Reçu pour votre inscription au CCÉM"
                                        : 'Receipt for your registration at CUMC'),
                      'Bcc' => "receipts@cumc.math.ca",
                      );

        $mime = new Mail_mime(array('eol' => "\n",
                                    'head_charset' => 'utf-8',
                                    'text_charset' => 'utf-8',
                                    'html_charset' => 'utf-8'
                                    ));

        $mime->setHTMLBody($htmlreceipt);

        $body = $mime->get();
        $hdrs = $mime->headers($hdrs);

        $mail =& Mail::factory('mail','-fbounces@cumc.math.ca');
        $mail->send($c['email'], $hdrs, $body);
//        $mail->send('larocque@cms.math.ca', $hdrs, $body);
print "finished " . $c['givennames'] . ' ' . $c['lastname'] . ".\n";

?>

