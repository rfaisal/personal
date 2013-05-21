<?php
//      cumcIPN3055.php
//
// This code is based in large part on the Kangaroo "ipn820.php" script.
//
// It "listens" for notifications from PayPal following completion of a payment (or
// refund or reversal).  The script does all the expected data validation, then stores
// the transaction, processes the payment in the cumc-registration database, and generates
// and emails a receipt to the customer.
//

IPNhandler();

##############################################################
function mail_ipncheck($listner, $subj, $prologue) {
    $to_address = 'esg-ipnchecks+cumc@cms.math.ca'; // Report results of ipn check here
    $subject = "[CUMC-IPN] " . $subj . ' (' . $_POST['txn_id'] . ')';
    $body = $prologue . "\n" . $listner->getTextReport();
    return mail($to_address, $subject, $body);
}

function dbout($txt) {
    $ipn = isset($_POST['txn_id']) ? $_POST['txn_id'] : 'no txn_id';
    error_log( '(' . $ipn . ') ' . $txt);
}

################################################################
function IPNhandler() {
    $debug_mode = false;                        // compares to sandbox if true

    include_once("/cumc/regdb_functions.php");

    // Tell PHP to log (low level) errors to /tmp/ipn_errors.log
    ini_set('log_errors', true);
    ini_set('error_log', '/tmp/cumc_ipn_errors.log');

    // Instantiate the IPN listener.
    include('ipnlistener3055.php');
    $listener = new IpnListener();

    // Tell the IPN listener whether to use the PayPal test sandbox
    // based on the value of $debug_mode, which is set in config.cfg.php
    $listener->use_sandbox = $debug_mode ? true : false;

    $prologue = '';
    dbout('verifying');
    // Try to confirm this IPN POST with PayPal.
    $verified = false;
    try {
        $listener->requirePostMethod();
        $verified = $listener->processIpn();
    } catch (Exception $e) {
        dbout($e->getMessage());
        exit(0);
    }

    if (!$verified) {
        $prologue .= "\nListener refused to validate the IPN data received." .
                    "\nIPN not stored in DB.";
        mail_ipncheck($listener, 'Anomaly detected', $prologue);
        exit(0);
    }

    // We know that The IPN came from PayPal and arrived intact.
    dbout('paypal verification succeeded');
    // Connect to the cumc-registration database (write access)
    try {
        $dbh = connect_to_cumc_reg_db(true);
        $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        dbout("Cannot connect to cumc database");
        mail_ipncheck($listener, 'Database error', "Error in database connection: ". $e->getMessage());
        exit(0);
    }

    // Make sure we aren't about to save a duplicate.
    $sth = $dbh->prepare("SELECT txn_id FROM paypal_ipn WHERE txn_id = ?");
    $sth->execute(array($_POST['txn_id']));
    if ($sth->rowCount() > 0) {
        dbout("duplicate IPN detected");
        $prologue .= "\nThis transaction id (" . $_POST['txn_id'] . ") already exists in paypal_ipn." .
                    "\nDuplicate IPN not stored in DB.";
        mail_ipncheck($listener, 'Anomaly detected', $prologue);
        exit(0);
    }

    // Store IPN information into the database.
    $fields = preg_split("/\s+/", "txn_type business charset custom residence_country
                                   test_ipn txn_id first_name last_name payer_id
                                   payer_email mc_currency mc_fee mc_gross
                                   num_cart_items payer_status payment_date
                                   payment_status payment_type reason_code
                                   parent_txn_id");
    $placeholders = array();
    $values = array();
    foreach ($fields as $f) {
      array_push($placeholders, '?');
      array_push($values, isset($_POST[$f]) ? $_POST[$f] : NULL);
    }
    $fieldlist = join(", ", $fields);
    $placeholderlist = join(", ", $placeholders);
    $sth = $dbh->prepare("INSERT INTO paypal_ipn ($fieldlist)
                            VALUES ($placeholderlist)");
    $sth->execute($values);
    dbout("stored in paypal_ipn table");

    $ipn_id = $_POST['txn_id'];

    $txn_id = $ipn_id;
    $pstatus = $_POST['payment_status'];
    $prologue .= "\nStored IPN " . $txn_id . " to paypal_ipn.";
    $prologue .= "\nPayment status is " . $pstatus . ".";
    $prologue .= "\nPayer: " . (isset($_POST['first_name']) ? $_POST['first_name'] : '') .
        (isset($_POST['first_name']) ? ' ' . $_POST['last_name'] : '') .
        (isset($_POST['payer_email']) ? ' (email: ' . $_POST['payer_email'] . ')' : '');

    // DO VARIOUS VALIDATION CHECKS TO SATISFY OUR BUSINESS LOGIC

    // 1. Make sure the payment status is "Completed", "Refunded" or "Reversed"
    $is_payment = $pstatus == 'Completed' ? 1 : 0;
    $is_refund = $pstatus == 'Refunded' ? 1 : 0;
    $is_reversal = $pstatus == 'Reversed' ? 1 : 0;

    if (! in_array($pstatus, array('Completed', 'Refunded', 'Reversed'))) { 
        $prologue .= "\nPayment status value not recognized (" . $pstatus . ")";
        mail_ipncheck($listener, 'Anomaly detected', $prologue);
        dbout("unrecognized payment status: " . $pstatus);
        exit(0);
    }

    // 2. Make sure seller email matches primary PayPal account email.
    $good_email = 'payments@cms.math.ca';
    if ($_POST['receiver_email'] != $good_email) {
        $prologue .= "\nReceiver email should be " . $good_email .
                        " but IPN is paying " . $_POST['receiver_email'] . " instead.";
        mail_ipncheck($listener, 'Anomaly detected', $prologue);
        dbout("payee email check failed!");
        exit(0);
    }

    // 3. Make sure the currency code matches
    if ($_POST['mc_currency'] != 'CAD') {
        $prologue .= "\nCurrency should be CAD but IPN is paying in " .
                        $_POST['mc_currency'] . " instead.";
        mail_ipncheck($listener, 'Anomaly detected', $prologue);
        dbout("currency check failed (not CAD)");
        exit(0);
    }


    // Parse the "custom" string.  It should contain a non-empty list of row ids
    // for the itemordered table, separated by hyphens.  All such rows must be for the
    // same customer else exception.
    $custom = $_POST['custom'];     // e.g. CUMC2012-403-404-405
    $ccut = explode( '-', $custom, 2); // throw away the ident prefix CUMC2012-
    $cids = explode( '-', $ccut[1]); // parse item ids
        
    // 4. Make sure cart gave same number of items as identified in custom tag
    $nci = 1;
    while (isset($_POST['mc_gross_' . $nci])) $nci++;
    $nci--;
    if (count($cids) != $nci) {
        $prologue .= "\nCustom (" . $_POST['custom'] . ") has " . count($cids) .
            " items but cart reports " . $nci . " items.";
        mail_ipncheck($listener, 'Anomaly detected', $prologue);
        dbout("cart size check failed");
        exit(0);
    } 
    
    // for each item id we are passed, validate it
    $customerid = NULL;
    $erm = NULL;
    $subtotal_gross = 0.0;
    $sth = $dbh->prepare('SELECT * FROM itemordered WHERE id = ?');
    foreach ($cids as $k => $itemid) {
        // 5. Item ID given must be nonblank
        if (strlen($itemid) < 1) {
            $erm = "Blank item id passed via custom tag " . $custom;
            break;
        }
        $sth->execute(array($itemid));
        // 6. Item ID given must be found in the DB
        if ($sth->rowCount() < 1) {
            $erm = "Cannot find item id " . $itemid . " in the database (custom = " . $custom . ").";
            break;
        }
        $io = $sth->fetch(PDO::FETCH_ASSOC);
        // 7. customer id of all items must match (and should be remembered)
        if (!$customerid) {
            $customerid = $io['customer_id'];
        } else {
            if ($customerid != $io['customer_id']) {
                $erm = "Custom (" . $custom . ") has items owned by more than one customer.";
                break;
            }
        }
        // 8. Cost of individual items must match
        $postfield = sprintf('mc_gross_%d', 1 + $k);
        if ($_POST[$postfield] != sprintf('%0.2f', $io['cost'])) {
            $erm = "Item " . $itemid . " (" . $io['itemcode'] . ") costs " . $io['cost'] . " but IPN specifies " . $_POST[$postfield] . " for it.";
            break;
        }
        // 9a. status field must be UNPAID for Payments
        if ($is_payment && $io['status'] != 'UNPAID') {
            $erm = "Item " . $itemid . " is not flagged UNPAID (it is " . $io['status'] . ")." .
                "\nSince this IPN is a PAYMENT, we cannot process it.";
            break;
        }
        // 9b. status field must be PAID for reversals and refunds
        if (($is_reversal || $is_refund) && $io['status'] != 'PAID') {
            $erm = "Item " . $itemid . " is not flagged PAID (it is " . $io['status'] . ")." .
                "\nSince this IPN is a refund or reversal, we cannot process it.";
            break;
        }
        $subtotal_gross += $io['cost'];
    }
    if ($erm) {
        mail_ipncheck($listener, 'Anomaly detected', $prologue . "\n" . $erm);
        dbout("anomaly detected: " . $erm);
        exit(0);
    }

    // 10. Check that the gross amount matches up with the item costs we've stored
    $gross_amount = (float) $_POST['mc_gross']; // note that this is -ve for refunds, reversals
    if ($is_reversal) $gross_amount += (float) $_POST['mc_fee'];    // odd that reversals don't include this in mc_gross but refunds do.
    $diff = abs(abs($gross_amount) - $subtotal_gross);  // How well do the db and ipn compare?
    if ($diff > 0.002) {
        $prologue .= sprintf("\nGross total of IPN (%0.2f) does not match total cost of items identified (%0.2f).", abs($gross_amount), $subtotal_gross);
        mail_ipncheck($listener, 'Anomaly detected', "\n" . $prologue);
        dbout("item cost mismatches payment total");
        break;
    }

    // Passes our checks.  Now we can store/process this payment.
    $rectype = ($is_payment ? 'PAYMENT'
                            : ($is_refund ? 'REFUND'
                                          : 'REVERSAL'));

    $sql = <<<INSERTPAYMENT
        INSERT INTO payment
                    (customer_id, "when", rectype, amount, ipn_txn_id)
            VALUES  (?,           now(),  ?,       ?,      ?)
            RETURNING id
INSERTPAYMENT;
    $sth = $dbh->prepare($sql);
    $sth->execute(array($customerid, $rectype, $gross_amount, $txn_id));
    $pmt = $sth->fetch(PDO::FETCH_ASSOC);
    $pmtid = $pmt['id'];       // payment id for use in paymentxorder table

    $prologue .= "\nSaved record to payment table, row " . $pmtid . ".";

    $sth = $dbh->prepare("select givennames||' '||lastname as \"name\" from customer where id = ?");
    $sth->execute(array((int) $customerid));
    $cust = $sth->fetch(PDO::FETCH_ASSOC);
    
    $prologue .= "\nCustomer id is " . $customerid . " (" . $cust['name'] . ").";

    # STORE/BREAK CROSS LINKS
    $linktype = ($is_payment ?  "PAID" : "UNPAID");
    
    $sth = $dbh->prepare('INSERT INTO paymentxorder (payment_id, itemordered_id, linktype) values (?, ?, ?)');
    $sths = $dbh->prepare('UPDATE itemordered SET status = ? WHERE id = ?');
    foreach ($cids as $k => $itemid) {
        $sth->execute(array((int) $pmtid, (int) $itemid, $linktype));
        $sths->execute(array($linktype, (int) $itemid));
        $prologue .= "\nUpdated status of item " . $itemid . " to " . $linktype .
                        " and crosslinked to payment record.";
    }
    dbout("saved '" . $rectype . "' record to payment table and linked it to the items");

    # get customer info
    $sthc = $dbh->prepare('SELECT * FROM customer WHERE id = ?');
    $sthc->execute(array((int) $customerid));
    $c = $sthc->fetch(PDO::FETCH_ASSOC);

    if ($is_payment) {
        # clear out the regtoken if there is one for this customer
        # (aka. no further changes)
        $sthc = $dbh->prepare('UPDATE customer SET regtoken = NULL WHERE id = ?');
        $sthc->execute(array((int) $customerid));
        dbout("cleared token");
    }

    # generate receipt, email it to the customer email (not the paypal email)
    # use language setting
    if ($is_payment) {
        include_once 'makereceipt.php';
        $htmlreceipt =  "<html><body>\n" . 
                            makereceipt($dbh, $pmtid, NULL) .
                        "</body></html>\n";

        dbout("generated a receipt");

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
        dbout('mailed the receipt');
        $prologue .= "\nReceipt sent to " . $c['email'];
    }


    $subj = ($is_payment ? 'Payment received from'
                            : ($is_refund ? 'Refund issued to'
                                          : 'Reversal issued for'));
    $subj .= ' ' . $c['givennames'] . ' ' . $c['lastname'];
    mail_ipncheck($listener, $subj, "\n" . $prologue);

    dbout('Finished processing. ' . $subj );

}


?>
