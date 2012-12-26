<?php
# todo list:
#
#   - more fully implement language choice

function registration_head() {
    $regcontext = array('debug'=>true);
    $x = explode('?',$_SERVER['REQUEST_URI']);
    $regcontext['URI'] = $x[0];

    standard_head_elements();

    include_once("/cumc/regdb_functions.php");

    try {
        $regcontext['dbh'] = connect_to_cumc_reg_db(true);

        $regcontext['dbh']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    catch(PDOException $e)
        {
        die("Error in database connection: ". $e->getMessage());
        }

    if (!$regcontext['dbh']) {
        die("Error in database connection. No more info.");
    }

    # Are we returning from PayPal?
    if (isset($_REQUEST['PPreturn'])) {
        $regcontext['mode'] = 'PPreturn';
        $regcontext['PPreturn'] = $_REQUEST['PPreturn'];
        reghead_ppreturn($regcontext);
        return $regcontext;
    }

    # Are we receiving the form filled in?
    if (isset($_REQUEST['sname'])) {
        $errors = serverside_validation($regcontext);
        if ($errors == '') {
            save_to_db($regcontext);
#           generate the confirmation page with paypal hidden fields, return to us, etc.
            $regcontext['mode'] = 'Confirmation';
            reghead_confirmation($regcontext);
            return $regcontext;
        }
        $regcontext['errors'] = $errors;
    }

    # Then we must need to display the form
    $regcontext['mode'] = 'Form';
    reghead_showform($regcontext);

    return $regcontext;
}

function reghead_ppreturn(&$regcontext) {
}

function serverside_validation(&$regcontext) {
    # trim text fields
    $errors = array();
    foreach (array('sname','gname','university','email','city','dietary') as $tf) {
        $_REQUEST[$tf] = trim((isset($_REQUEST[$tf]) ? $_REQUEST[$tf] : ''));
    }
    # required to be non-blank
    foreach (array('gname' => 'given name','sname' => 'last name','email' => 'email address','city' => 'city', 'province' => 'province', 'giveatalk' => 'talk information (or choose No)', 'tshirt' => 'T-shirt selection', 'preflang' => 'preferred language') as $tf => $msgstring) {
        $fv = (isset($_REQUEST[$tf]) ? $_REQUEST[$tf] : '');
        if ($fv == '') {
            array_push($errors,'You need to provide your ' . $msgstring);
        }
    }
    if (isset($_REQUEST['email']) && !preg_match('/^[A-Za-z0-9][^\@\,]*\@[^\@\.\,]+(\.[^\@\.\,]+)+$/', $_REQUEST['email'])) {
        array_push($errors,'The email address you provided is not in a valid format');
    }
    if (count($errors)) {
        $ebuf = "<ul id='errlist'>\n";
        foreach ($errors as $e) {
            $ebuf .= "<li>" . $e . "\n";
        }
        $ebuf .= "</ul>\n";
        return $ebuf;
    } else {
        return '';
    }
}

function createToken() {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
    $newtoken = '';

    for ($length = 24; $length--; ) {
        $newtoken .= $characters[mt_rand(0, strlen($characters)-1)];
    }

    return $newtoken;
}

function save_to_db(&$regcontext) {
    if (!isset($_REQUEST['token']) || $_REQUEST['token'] == '') {
        die("No token - cannot save to database!");
    }
    $token = $_REQUEST['token'];

    $dbh = $regcontext['dbh'];

    # look for token record
    $customerid = NULL;
    try {   # general exception catcher
        if (isset($token) && $token != '') {
            # there is a token parameter, but is the token in the db?
            $stmt = $dbh->prepare("SELECT * FROM customer where regtoken = ? limit 1");
            if ($stmt->execute(array($token)) && $stmt->rowCount()) {
                $loaded = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($loaded) {
                    # yes, the token is in the db.  So we should be free to update
                    # this record.  save the id.
                    $customerid = $loaded['id'];
                }
            }
        }
        # now, if ($customerid) then we will UPDATE, else we will CREATE


        $custvals = array($_REQUEST['sname'], $_REQUEST['gname'],
                (isset($_REQUEST['university']) && $_REQUEST['university'] != ''
                        ? $_REQUEST['university'] 
                        : NULL),
                (isset($_REQUEST['city']) && $_REQUEST['city'] != '' 
                        ? $_REQUEST['city'] 
                        : NULL),
                (isset($_REQUEST['province']) && $_REQUEST['province'] != '' 
                        ? $_REQUEST['province'] 
                        : NULL),
#                (isset($_REQUEST['gender']) && $_REQUEST['gender'] != ''
#                        ? ($_REQUEST['gender'] == 'F' ? 'true' : 'false')
#                        : NULL),
                (isset($_REQUEST['atleast19']) && $_REQUEST['atleast19'] != '' 
                        ? 'true' 
                        : 'false'),
                (isset($_REQUEST['dietary']) && $_REQUEST['dietary'] != '' 
                        ? $_REQUEST['dietary'] 
                        : NULL),
                (isset($_REQUEST['tshirt']) && $_REQUEST['tshirt'] != '' 
                        ? $_REQUEST['tshirt'] 
                        : NULL),
                (isset($_REQUEST['giveatalk']) && $_REQUEST['giveatalk'] != ''
                        ? ($_REQUEST['giveatalk'] == 'N' ? 'false' : 'true')
                        : NULL),
                (isset($_REQUEST['giveatalk']) && $_REQUEST['giveatalk'] != '' 
                                && $_REQUEST['giveatalk'] != 'N'
                        ? $_REQUEST['giveatalk']
                        : 0),
                (isset($_REQUEST['listpermission']) 
                                && $_REQUEST['listpermission'] != '' 
                        ? 'true' 
                        : 'false'),
                (isset($_REQUEST['email']) && $_REQUEST['email'] != '' 
                        ? $_REQUEST['email'] 
                        : NULL),
                $token,
                (isset($_REQUEST['preflang']) && $_REQUEST['preflang'] != ''
                        ? $_REQUEST['preflang']
                        : NULL),
                );

        $itemoptions = array( array('conf','CONF',80), 
                            array('openingbanquet','OBANQ',0),     
                            array('closingbanquet','CBANQ',0),
                            array('womensdinner','WDINN',0) );

        if (!$customerid) {
            $sqlstmt = <<<INSERTCUST
INSERT INTO customer
(whenregistered, lastname, givennames, university, city, province, willbe19,
 dietaryrestrictions, tshirtsize, givingtalk, talklength, permitparticipantlist,
 email, regtoken, "language")
VALUES ( now(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
RETURNING id;
INSERTCUST;

            $stmt = $dbh->prepare($sqlstmt);

            if ($stmt->execute($custvals) && $stmt->rowCount()) {
                $fetchid = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($fetchid && isset($fetchid['id']) && $fetchid['id'] != '') {
                    $newcustid = $fetchid['id'];
                } else { die("Did not get an id back after insert!"); }
            } else { die("Could not execute the INSERT!"); }
      
            # customer inserted, now need to INSERT the itemordered row(s)
            foreach ($itemoptions as $item) {
                $nm = $item[0]; $icode = $item[1]; $cost = $item[2];
                if ($nm == 'conf' || (isset($_REQUEST[$nm]) && $_REQUEST[$nm] == 'on')) {
                    $status = 'UNPAID'; #($cost > 0 ? 'UNPAID' : 'PAID');
                    $sqlstmt = <<<INSERTITEM
INSERT INTO itemordered
(customer_id, itemcode, "cost", status)
values ($newcustid, '$icode', $cost, '$status');
INSERTITEM;
                    $stmt = $dbh->prepare($sqlstmt);
                    $stmt->execute();
                }
            }
            $customerid = $newcustid;
        } else {
            # update existing customer record
            $sqlstmt = <<<UPDATECUST
UPDATE customer SET
whenregistered = now(),
lastname = ?,
givennames = ?, 
university = ?, 
city = ?, 
province = ?, 
willbe19 = ?,
dietaryrestrictions = ?, 
tshirtsize = ?, 
givingtalk = ?, 
talklength = ?, 
permitparticipantlist = ?,
email = ?, 
regtoken = ?,
"language" = ?
WHERE
id = $customerid and regtoken = '$token';
UPDATECUST;
            $stmt = $dbh->prepare($sqlstmt);

            if (!$stmt->execute($custvals)) {
                die("Could not execute the customer UPDATE!");
            }
            
            # customer updated, now need to update the itemordered row(s)
            foreach ($itemoptions as $item) {
                $nm = $item[0]; $icode = $item[1]; $cost = $item[2];
                # is it already on file?
                $stmt = $dbh->prepare("select * from itemordered where customer_id = $customerid and itemcode = '$icode'");
                $stmt->execute();
                $preexisting = ($stmt->rowCount() > 0) ? true : false;

                if ($nm == 'conf'
                        || (isset($_REQUEST[$nm]) && $_REQUEST[$nm] == 'on')) {
                    # it should be on file
                    if (!$preexisting) {
                        # write a new row
                        $status = 'UNPAID'; #($cost > 0 ? 'UNPAID' : 'PAID');
                        $sqlstmt = <<<INSERTITEM233
INSERT INTO itemordered
(customer_id, itemcode, "cost", status)
values ($customerid, '$icode', $cost, '$status');
INSERTITEM233;
                        $stmt = $dbh->prepare($sqlstmt);
                        $stmt->execute();
                    }
                } else {
                    # it should not be on file
                    if ($preexisting) {
                        # delete it
                        $stmt = $dbh->prepare("delete from itemordered where customer_id = $customerid and itemcode = '$icode'");
                        $stmt->execute();
                    }
                }
            }
        }

    } catch (PDOException $e) {                 # GENERAL catchall exception handling
        die("Exception caught ". $e->__toString());
    }

    $regcontext['customerid'] = $customerid;

    # this stores the token in the browser so that if user cancels or completes paypal
    # we can give context
    setcookie('cumcregistration',$token. '|' . $customerid);

}

function reghead_confirmation(&$regcontext) {

    $dbh = $regcontext['dbh'];
?>
    <style type="text/css">
    button#paybutton {
        background: url(images/btn_paynownone.gif) no-repeat;
        border: none;
        padding:0px;
        height:30px;
        width:112px;
    }
    button#paybutton:hover {
        background: url(images/btn_paynowwhite.gif) no-repeat;
    }
    </style>
<?php
}

function reghead_showform(&$regcontext) {

    $dbh = $regcontext['dbh'];

    # define the default field settings
    $dfv = array(   'sname' => '',
                    'gname' => '',
                    'university' => '',
                    'listpermission' => '',
                    'preflang' => 'en',
                    'email' => '',
                    'city' => '',
                    'province' => '',
                    'atleast19' => '',
                    'dietary' => '',
                    'openingbanquet' => 'checked',
                    'closingbanquet' => 'checked',
                    'womensdinner' => '',
                    'giveatalk' => NULL,
                    'token' => '',
                    'tshirt' => '');

    # if we are given a valid inbound token, read from DB and override those settings
    if(isset($_REQUEST['tk'])){

        try {
            $stmt = $dbh->prepare("SELECT * FROM customer where regtoken = ? limit 1");
            if ($stmt->execute(array($_REQUEST['tk'])) && $stmt->rowCount()) {
                $reload = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($reload) {
                    $dfv['token'] = $_REQUEST['tk'];
                    $dfv['sname'] = $reload['lastname'];
                    $dfv['gname'] = $reload['givennames'];
                    $dfv['university'] = $reload['university'];
                    $dfv['preflang'] = $reload['language'];
                    $dfv['email'] = $reload['email'];
                    $dfv['city'] = $reload['city'];
                    $dfv['province'] = $reload['province'];
                    $dfv['dietary'] = $reload['dietaryrestrictions']; #$reload['dietaryrestrictions'];
                    $dfv['giveatalk'] = ($reload['givingtalk'] ? $reload['talklength'] : $reload['givingtalk']);
                    $dfv['tshirt'] = $reload['tshirtsize'];
                    $dfv['listpermission'] = ($reload['permitparticipantlist'] ? 'checked' : '');
                    $dfv['atleast19'] = ($reload['willbe19'] ? 'checked' : '');
#                    $dfv['gender'] = $reload['female'];

                    $stmt = $dbh->prepare("SELECT * FROM itemordered where customer_id = ? and itemcode = ? and status != 'CANC'");
                    if ($stmt->execute(array($reload['id'],'OBANQ')) 
                        &&  $stmt->rowCount()) {
                        $dfv['openingbanquet'] = 'checked';
                    } else {
                        $dfv['openingbanquet'] = '';
                    }
                    if ($stmt->execute(array($reload['id'],'CBANQ')) 
                        &&  $stmt->rowCount()) {
                        $dfv['closingbanquet'] = 'checked';
                    } else {
                        $dfv['closingbanquet'] = '';
                    }
                    if ($stmt->execute(array($reload['id'],'WDINN')) 
                        &&  $stmt->rowCount()) {
                        $dfv['womensdinner'] = 'checked';
                    } else {
                        $dfv['womensdinner'] = '';
                    }


                } else {
                    die("Cannot reload from token " . $_REQUEST['tk']);
                }
            } else {
                die("Cannot locate token ". $_REQUEST['tk']);
            }
        } catch (PDOException $e) {
            die("Exception caught ". $e->__toString());
        }
    } else {

        # if we are passed various field values, allow override of those fields
        if (isset($_REQUEST['token'])) 
            {
            $dfv['token'] = $_REQUEST['token'];
            # text
            if (isset($_REQUEST['sname'])) $dfv['sname'] = $_REQUEST['sname'];
            if (isset($_REQUEST['gname'])) $dfv['gname'] = $_REQUEST['gname'];
            if (isset($_REQUEST['university'])) $dfv['university'] = $_REQUEST['university'];
            if (isset($_REQUEST['email'])) $dfv['email'] = $_REQUEST['email'];
            if (isset($_REQUEST['preflang'])) $dfv['preflang'] = $_REQUEST['preflang'];
            if (isset($_REQUEST['city'])) $dfv['city'] = $_REQUEST['city'];
            if (isset($_REQUEST['dietary'])) $dfv['dietary'] = $_REQUEST['dietary'];
            # checkboxes
            $dfv['openingbanquet'] = ($_REQUEST['openingbanquet'] == 'on' ? 'checked' : '');
            $dfv['closingbanquet'] = ($_REQUEST['closingbanquet'] == 'on' ? 'checked' : '');
            $dfv['womensdinner'] = ($_REQUEST['womensdinner'] == 'on' ? 'checked' : '');
            $dfv['listpermission'] = ($_REQUEST['listpermission'] == 'on' ? 'checked' : '');
            $dfv['atleast19'] = ($_REQUEST['atleast19'] == 'on' ? 'checked' : '');
            # DDLBs
            if (isset($_REQUEST['province'])) $dfv['province'] = $_REQUEST['province'];
            if (isset($_REQUEST['tshirt'])) $dfv['tshirt'] = $_REQUEST['tshirt'];
            if (isset($_REQUEST['giveatalk'])) $dfv['giveatalk'] = ($_REQUEST['giveatalk']=='N'?false :  $_REQUEST['giveatalk']);
#            if (isset($_REQUEST['gender'])) $dfv['gender'] = ($_REQUEST['gender']=='M' ? false : ($_REQUEST['gender']=='F'?true :  NULL));
        }
    }

    if ($dfv['token'] == '') {              # create a new token if we don't have one.
            $dfv['token'] = createToken();
    }

    # save default field values for use in the <div> later
    $regcontext['dfv'] = $dfv;

?>
    <script type="text/javascript">

    $(document).ready(function(){
        talkemailupdater();
    });
    function talkchanged() {
        talkemailupdater();
    }
    function talkemailupdater() {
        var gt = $("#giveatalk option:selected").val();
        if (gt == '20' || gt == '45') {
            $("#talkemailnotice").show();
        } else {
            $("#talkemailnotice").hide();
        }
    }
    function form_submitted() {
        if (validate_form()) {
            return true;
        }
        return false;
    }

    function validate_form() {
        // clean text in fields //
        var textfields = ['gname','sname','university','email','city','dietary'];
        for (var i = 0; i < textfields.length; i++) {
            var fn = textfields[i];
            $("#" + fn).val($.trim($("#" + fn).val()));
        }

        // required fields check //
        if($("#gname").val() == '') {
            alert('CUMC Registration:\nYou need to enter your given name.');
            $("#gname").focus();
            return false;
        }
        if($("#sname").val() == '') {
            alert('CUMC Registration:\nYou need to enter your surname (last/family name).');
            $("#sname").focus();
            return false;
        }
        if($("#email").val() == '') {
            alert('CUMC Registration:\nYou need to provide your email address.');
            $("#email").focus();
            return false;
        }
        if($("#city").val() == '') {
            alert('CUMC Registration:\nYou need to provide your city.');
            $("#city").focus();
            return false;
        }
        if($("#preflang").val() == '') {
            alert('CUMC Registration:\nYou need to provide your preferred language.');
            $("#preflang").focus();
            return false;
        }
        if($("#province").val() == '') {
            alert('CUMC Registration:\nYou need to provide your province.');
            $("#province").focus();
            return false;
        }
        if($("#giveatalk").val() == '') {
            alert('CUMC Registration:\nYou need to declare if you will give a talk or not.');
            $("#giveatalk").focus();
            return false;
        }
        if($("#tshirt").val() == '') {
            alert('CUMC Registration:\nPlease make a T-shirt selection.');
            $("#tshirt").focus();
            return false;
        }
        // value validation
        var emailre = new RegExp('^[A-Za-z0-9][^\@\,]*\@[^\@\.\,]+(\.[^\@\.\,]+)+$');
        if(!($("#email").val().match(emailre))) {
            alert("CUMC Registration:\nThe email address you provided is not valid.");
            $("#email").focus();
            return false;
        }
        return true; // validation successful
    }
    </script>

    <style type="text/css">
    table#confopt th {
       border-bottom: 1px solid #ccc;
    }
    table#confopt td p {
        margin: 0px;
        padding: 0px;
        line-height: normal;
    }
    div#reperrors {
        background-color: #900;
        border: 3px double #f00;
        padding: 1em;
        color: #fff;
    }
    div#talkemailnotice {
        background-color: #ff0;
        color: #000;
        padding: 2px;
        border: 1px solid #000;
    }
    div#talkemailnotice a {
        color: #00f;
    }
    p#responserqdlegend {
        text-align:right;
        color: #f00;
        line-height:normal;
        padding-bottom: 0px;
        margin-bottom:0px;
    }
    span.responsereqd {
        color: #f00;
        font-weight:bold;
        padding-left:6px;
    }
    </style>

<?php

}

function standard_head_elements() {
?>
    <script type="text/javascript" src="/2012/js/jquery-1.7.1.min.js"></script>

    <style type="text/css">
    fieldset {
        margin: 1em 0px 1em 0px;
    }
    fieldset legend {
        font-weight:bold;
    }
    span#totalcomp {
        border-top: 1px solid #ccc;
    }
    input:focus, select:focus, textarea:focus {
        background-color: #BFB570;
    }
    </style>

    <?php
}

############################################################
#                           DIV 
############################################################


function registration_div(&$regcontext) {
    if ($regcontext['mode'] == 'Form') {
        return regdiv_showform($regcontext);
    } elseif ($regcontext['mode'] == 'Confirmation') {
        return regdiv_confirmation($regcontext);
    } elseif ($regcontext['mode'] == 'PPreturn') {
        return regdiv_ppreturn($regcontext);
    } else {
        die("Unknown mode '".$regcontext['mode']."'");
    }
}

function regdiv_ppreturn(&$regcontext) {
    $dbh=$regcontext['dbh'];
    $token = ''; $customerid = '';
    $x = explode('?',$_SERVER['REQUEST_URI']);
    $uri = $x[0];
    if (isset($_COOKIE) && isset($_COOKIE['cumcregistration'])) {
        $c = explode('|',$_COOKIE['cumcregistration']);
        $token = $c[0]; $customerid = $c[1];
        # see if these are legitimate
        $stmt = $dbh->prepare('select * from customer where id = ? and regtoken = ?');
        $stmt->execute(array($customerid,$token));
        if ($stmt->rowCount() < 1) {
            $token = '';
            $customerid = '';
        }
    }
    if ($token != '') {
        $tokenlink = $uri . "?tk=" . $token;
        # could be cancel or complete
        if ($regcontext['PPreturn'] == 'cancel') {  ?>

    <p style="font-size=115%; color:#ff0; font-weight:bold">You cancelled online payment for your registration.</p>
    <p style="font-weight:bold">However, you are still stored in our database
    as customer # <?php echo $customerid ?>.</p>
    <p>
    If you wish, you can
    <a href='<?php echo $tokenlink; ?>'>return to the registration form</a> to make changes or 
    <a href='<?php echo $tokenlink; ?>'>try again to pay</a> through PayPal.
    </p>

    <p>If you are having difficulty completing your transaction through PayPal, you may wish to <a href="mailto:registration@cumc.math.ca?Subject=Payment for cust number <?php echo $customerid ?>" style="font-weight:bold">email us</a>.</p>

    <?php    } elseif ($regcontext['PPreturn'] == 'complete') { ?>

    <p style="font-size=115%; font-weight:bold">Thank you for registering.</p>
    <p>You will receive a receipt in your email as soon as PayPal confirms your payment (typically within ten minutes).</p>
    <p>For your information, your customer number on our system is <?php echo $customerid ?>.</p>

    <p>If you have any further questions regarding payment or registration, you may wish to <a href="mailto:registration@cumc.math.ca" style="font-weight:bold">email us</a>.</p>

    <?php    } else { die("PPreturn value of " . $regcontext['PPreturn'] . " is not understood."); }                        
    } else {

        # could be cancel or complete
        if ($regcontext['PPreturn'] == 'cancel') {
            print "<p>You cancelled online payment for your registration.</p>\n";
            print "<p><a href='" . $uri . "'>Start over</a>.</p>\n";
        } elseif ($regcontext['PPreturn'] == 'complete') {
            print "<p>Thank you for registering.  You should receive a receipt in your email shortly.</p>\n";
        } else {
            die("PPreturn value of " . $regcontext['PPreturn'] . " is not understood.");
        }        
    }


}

function regdiv_confirmation(&$regcontext) {
?>

<form id="confirmform" method='post' action='https://www.<?php echo ($regcontext['debug'] == false ? '' : 'sandbox.'); ?>paypal.com/cgi-bin/webscr'>
    <input type="hidden" name="charset" value="ISO-8859-1">
    <input type="hidden" name="cmd" value="_cart">
    <input type="hidden" name="upload" value="1">
    <input type="hidden" name="business" value="payments@cms.math.ca">
    <input type="hidden" name="currency_code" value="CAD">

    <input type="hidden" name="no_note" value="1">
    <input type="hidden" name="cancel_return" value="http://cumc.math.ca<?php echo $_SERVER['REQUEST_URI']; ?>?PPreturn=cancel">
    <input type="hidden" name="rm" value="1">
    <input type="hidden" name="return" value="http://cumc.math.ca<?php echo $_SERVER['REQUEST_URI']; ?>?PPreturn=complete">
    <input type="hidden" name="cbt" value="Return to CUMC web site">

    <input type="hidden" name="notify_url" value="http://cumc.math.ca/2012/en/reg/cumcIPN3055.php">

    <input type="hidden" name="lc" value="<?php echo ($_REQUEST['preflang'] == 'fr' ? 'FR' : 'US'); ?>">  <!-- language code = currency! -->
    <input type="hidden" name="no_shipping" value="1">

    <input type="hidden" name="country" value="CA">

    <input type="hidden" name="cpp_header_image" value="https://cms.math.ca/images/cumc-paypal-banner.jpg">
    <input type="hidden" name="cpp_headerback_color" value="ffffff">
    <input type="hidden" name="cpp_headerborder_color" value="ffffff">

<?php
       # generate a list of items for display on the paypal invoice
    try {
        $items = array();
        $total = 0;
        $customerid = $regcontext['customerid'];
        $stmt = $regcontext['dbh']->prepare("select * from itemordered where customer_id = " .
                    $customerid . " and itemcode = ? and status = 'UNPAID'");
        $invoiceitems = array(  array('CONF','CUMC 2012 Conference'),
                                array('OBANQ','Opening Banquet'),
                                array('WDINN',"Dinner for Women in Math and Science"),
                                array('CBANQ','Closing Banquet') );
        $itemsordered = array();
        $itemcount = 0;
        foreach ($invoiceitems as $item) {
            $icode = $item[0]; $name = $item[1];
            $stmt->execute(array($icode));
            if ($stmt->rowCount() > 0) {
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                $cost = $res['cost'];
                array_push($itemsordered,$res['id']);
                $itemcount++;
                print "<input type='hidden' name='item_name_$itemcount' value=\"" .
                    htmlspecialchars($name) . "\">\n";
                print "<input type='hidden' name='amount_$itemcount' value='$cost'>\n";
                $total += $cost;
            }
        }
        $custom = implode('-',$itemsordered);
        $invoice = $custom;
        print "<input type='hidden' name='custom' value='$custom'>\n";

        if (!$regcontext['debug']) {
            print "<input type='hidden' name='invoice' value='CUMC2012-$invoice'>\n";
        }

    } catch (PDOException $e) {
        die("Exception caught ". $e->__toString());
    }
?>
    <input type="hidden" name="email" value="<?php print $_REQUEST['email'] ?>">
    <input type="hidden" name="last_name" value="<?php print htmlspecialchars($_REQUEST['sname']) ?>">
    <input type="hidden" name="first_name" value="<?php print htmlspecialchars($_REQUEST['gname']) ?>">
    <input type="hidden" name="city" value="<?php print htmlspecialchars($_REQUEST['city']) ?>">
<?php if ($_REQUEST['province'] != '--') {
    print "<input type='hidden' name='country' value='CA'>\n";
} ?>

<fieldset>
<legend>Payment</legend>
    <p align="center" style="font-weight:bold; color:#f66">Payment for registration is only accepted online.</p>

<table align="center" style="margin-top:1em; margin-bottom:1em;">
  <tr valign="middle">
    <td align="left" style="padding-right:2.5em">Your total: <span id="payamount">$ 80</span></td>
    <td>
        <button type="submit" id="paybutton">
        </button>
        </td>
  </tr>
</table>

<p style="line-height:normal"><img src="images/PayPal_mark_60x38.gif" align="left" style="margin:0px 1em 0.5em 0px">
<span style="font-weight:bold">The CUMC uses <a href='https://www.paypal.com/row/cgi-bin/webscr?cmd=xpt/cps/bizui/WhatIsPayPal-outside' target='_blank' style='color:#fff'>PayPal</a> for payment processing.</span>  You will need either a credit card or a PayPal account to complete your registration.  Payments are made to the <i>Canadian Mathematical Society</i>.  You will be emailed a receipt once your payment is processed (usually within ten minutes).</p>
    <p><i>Note: You do not need to register an account on PayPal to use a credit card via PayPal.</i></p>
</fieldset>
</form>

<p>For registration and payment enquiries, please email <a href="mailto:registration@cumc.math.ca">registration@cumc.math.ca</a>.</p>

<?php
}
function reqd() {
    echo "<span class='responsereqd'>*</span>";
}

############################################################################
function regdiv_showform(&$regcontext) {
    $dfv = $regcontext['dfv'];

    if (isset($regcontext['errors']) && $regcontext['errors'] != '') {
        echo "\n<div id='reperrors'><span style='color:#fff;font-weight:bold'>Form error:</span><br>\n" . $regcontext['errors'] . "\n</div>\n";
    }
?>

<p><i>Note:</i> Online payment is required at the time of registration.</p>

<form id="regform" onsubmit="return form_submitted();" method='post' action="<?php echo $regcontext['URI']; ?>">
<input type="hidden" id='token' name='token' value="<?php echo htmlspecialchars($dfv['token']); ?>">


<p id='responserqdlegend'><?php reqd(); ?> = Response required</p>
<fieldset>
<legend>Participant Information</legend>
<table align="center">
<tr><td colspan="2" align="center" style="color:#ff0; font-weight:bold">
    Please use proper capitalization (e.g.. use "Euler", not "EULER" or "euler").<br>
    Your badge will be printed exactly as you enter it here.
    </td></tr>

<tr><td colspan="2" style="background-color:#4D4D80; border:3px outset #4D4D80; padding: 6px;">
    <p style="line-height:normal; margin:0px; padding:0px; text-align:right; font-size:85%; color:#ff0">Your Badge</p>
<table align="center">
<tr><td style="padding:0.5em 0px 0px 0px">Given name(s):<?php reqd(); ?></td>
    <td style="padding:0.5em 0px 0px 0px">Surname:<?php reqd(); ?></td>
    </tr>
<tr><td style="padding:0px 0px 0.5em 0px">
        <input type="text" id='gname' name='gname' value="<?php echo htmlspecialchars($dfv['gname']); ?>" /></td>
    <td style="padding:0px 0px 0.5em 0px">
    <input type="text" value="<?php echo htmlspecialchars($dfv['sname']); ?>" id='sname' name='sname' style="width:250px"/></td></tr>

<tr><td colspan="2" style="padding:0.5em 0px 0px 0px">Name of University or Institution:</td></tr>
<tr><td colspan="2" style="padding:0px 0px 0.5em 0px">
        <input type="text" id='university' name='university' style="width:400px"  value="<?php echo htmlspecialchars($dfv['university']); ?>"/>
    </td></tr>

<!--    <tr><td colspan="2" align="center" style="font-style:italic; color:#ff0">The information above will appear on your badge.</td></tr> -->
</table></td></tr>


<tr><td colspan="2" style="padding:0.5em 0px 0.5em 0px">The CUMC 2012 web site includes a publicly-viewable list of names of participants.<br>
    <input type="checkbox" name="listpermission" id='listpermission'  <?php echo $dfv['listpermission']; ?>>I give my permission to list my name and university.
    </td></tr>

<tr><td colspan="2" style="padding:0.5em 0px 0px 0px">Email:<?php reqd(); ?></td></tr>
<tr><td colspan="2" style="padding:0px 0px 0.5em 0px">
    <input type="text" id='email' name='email'  style="width:400px" value="<?php echo htmlspecialchars($dfv['email']); ?>"/></td></tr>

<tr><td style="padding:0.5em 0px 0px 0px">City:<?php reqd(); ?></td><td style="padding:0.5em 0px 0px 0px">Province:<?php reqd(); ?></td></tr>
<tr><td style="padding:0px 0px 0.5em 0px">
        <input type="text" id='city' name='city'  style="width:250px"  value="<?php echo htmlspecialchars($dfv['city']); ?>"/></td>
    <td style="padding:0px 0px 0.5em 0px"><select id='province' name='province'> 
        <option value='' <?php echo ($dfv['province'] == '' ? 'selected' : '') ?>>
            [ choose ]</option>
        <option value='BC' <?php echo ($dfv['province'] == 'BC' ? 'selected' : '') ?>>
            British Columbia</option>
        <option value='AB' <?php echo ($dfv['province'] == 'AB' ? 'selected' : '') ?>>
            Alberta</option>
        <option value='SK' <?php echo ($dfv['province'] == 'SK' ? 'selected' : '') ?>>
            Saskatchewan</option>
        <option value='MB' <?php echo ($dfv['province'] == 'MB' ? 'selected' : '') ?>>
            Manitoba</option>
        <option value='ON' <?php echo ($dfv['province'] == 'ON' ? 'selected' : '') ?>>
            Ontario</option>
        <option value='QC' <?php echo ($dfv['province'] == 'QC' ? 'selected' : '') ?>>
            Quebec</option>
        <option value='NB' <?php echo ($dfv['province'] == 'NB' ? 'selected' : '') ?>>
            New Brunswick</option>
        <option value='NS' <?php echo ($dfv['province'] == 'NS' ? 'selected' : '') ?>>
            Nova Scotia</option>
        <option value='PE' <?php echo ($dfv['province'] == 'PE' ? 'selected' : '') ?>>
            Prince Edward Island</option>
        <option value='NL' <?php echo ($dfv['province'] == 'NL' ? 'selected' : '') ?>>
            Newfoundland and Labrador</option>
        <option value='NU' <?php echo ($dfv['province'] == 'NU' ? 'selected' : '') ?>>
            Nunavut</option>
        <option value='NT' <?php echo ($dfv['province'] == 'NT' ? 'selected' : '') ?>>
            Northwest Territories</option>
        <option value='YT' <?php echo ($dfv['province'] == 'YT' ? 'selected' : '') ?>>
            Yukon</option>
        <option value='--' <?php echo ($dfv['province'] == '--' ? 'selected' : '') ?>>
            (outside Canada)</option>
    </select></td></tr>

<tr><td style="padding:0.5em 0px 0px 0px">Age:</td>
    <td style="padding:0.5em 0px 0px 0px">Preferred Language:<?php reqd(); ?></td></tr>
<tr valign="top"><td style="padding:0px 0.75em 0.5em 0px">
        <input type="checkbox" name="atleast19" id='atleast19' <?php echo $dfv['atleast19']; ?>>On July 11, 2012, I will be at least 19 years old.</td>
    <td style="padding:0px 0px 0.5em 0px">
        <select id='gender' name='preflang'> 
            <option value='' <?php echo ($dfv['preflang'] != 'en' && $dfv['preflang'] != 'fr' ? 'selected' : '') ?>>[ choose ]</option>
            <option value='en' <?php echo ($dfv['preflang'] == 'en' ? 'selected' : '') ?>>English</option>
            <option value='M' <?php echo ($dfv['preflang']== 'fr' ? 'selected' : '') ?>>Français</option>
        </select>
        </td></tr>

<tr><td colspan="2" style="padding:0.5em 0px 0px 0px">Dietary restrictions (if any):</td></tr>
<tr><td colspan="2"  style="padding:0px 0px 0.5em 2em">
    <textarea id='dietary' name='dietary' style="width:400px; height:50px" wrap="physical"><?php echo htmlspecialchars($dfv['dietary']); ?></textarea>
    </td></tr>
</table>
</fieldset>

<fieldset>
<legend>Conference Options</legend>
<table align="center">
<tr><td colspan="2" style="padding:0.5em 0px 0.5em 0px">
    <table align="center" id="confopt" cellspacing=0>
        <tr><th>I wish to attend:</th><th style="padding-left:1.25em; text-align:right">Cost</th><td></td></tr>
        <tr><td><p><input type="checkbox" checked name="cumc2012" id="cumc2012" disabled>CUMC 2012 Conference</p></td><td style="padding-left:1.25em; text-align:right">$ 80</td><td><i>(early bird price)</i></td></tr>
        <tr><td><p><input type="checkbox" name="openingbanquet" id="openingbanquet" <?php echo $dfv['openingbanquet']; ?>>Opening Banquet</p></td><td style="padding-left:1.25em; text-align:right"><i>incl.</i></td></tr>
        <tr valign="top" id="womensdinnerrow"><td><p><input type="checkbox" name="womensdinner" id="womensdinner" <?php echo $dfv['womensdinner']; ?>>Dinner for Women in Math and Science</p>
            <p style="padding: 0px 0px 4px 1.5em; color:#ff0; font-style:italic">
                (Open to women and women-identified participants only.)
                </p>
            </td>
            <td style="padding-left:1.25em; text-align:right"><p><i>incl.</i></p></td></tr>
        <tr><td><p><input type="checkbox" name="closingbanquet" id="closingbanquet" <?php echo $dfv['closingbanquet']; ?>>Closing Banquet</p></td><td style="padding-left:1.25em; text-align:right"><i>incl.</i></td></tr>
        <tr><td align="right">Total:</td><td style="padding-left:1.25em; text-align:right"><span id="totalcomp">$ 80</totalcomp></td></tr>
    </table>
    </td></tr>

<tr><td colspan="2" style="padding:0.5em 0px 0px 0px">Participants are invited to give a talk of either 20 minutes or 45 minutes.<br>
        Do you want to give a talk?<?php reqd(); ?></td></tr>
<tr><td colspan="2" style="padding:0px 0px 0.5em 2em">
    <select id='giveatalk' name='giveatalk' onchange="talkchanged();"> 
        <!-- <?php echo $dfv['giveatalk']; ?> -->
        <option value='' <?php echo (is_null($dfv['giveatalk']) ? 'selected' : '') ?>>
            [ choose ]</option>
        <option value='N' <?php echo (!is_null($dfv['giveatalk']) && $dfv['giveatalk'] == false ? 'selected' : '') ?>>
            No, I won't give a talk</option>
        <option value='20' <?php echo ($dfv['giveatalk'] == 20 ? 'selected' : '') ?>>
            Yes, I will give a 20 minute talk</option>
        <option value='45' <?php echo ($dfv['giveatalk'] == 45 ? 'selected' : '') ?>>
            Yes, I will give a 45 minute talk</option>
    </select><br>
    <div id="talkemailnotice">
        Please follow <a href="AbstractSubmissionGuidelines.pdf" target="_blank">this guide</a> (PDF) to submit your talk's abstract and title to the organizing committee.
    </div>
    </td></tr>


<tr><td colspan="2" style="padding:0.5em 0px 0px 0px">Specify the style and size of the Conference T-shirt you prefer:<?php reqd(); ?></td></tr>
<tr><td colspan="2"  style="padding:0px 0px 0.5em 2em">
    <select id='tshirt' name='tshirt'> 
        <option value='' <?php echo (is_null($dfv['tshirt']) || $dfv['tshirt'] == '' ? 'selected' : '') ?>>[ choose ]</option>
        <option value='W:S' <?php echo ($dfv['tshirt'] == 'W:S' ? 'selected' : '') ?>>(Women's) S</option>
        <option value='W:M' <?php echo ($dfv['tshirt'] == 'W:M' ? 'selected' : '') ?>>(Women's) M</option>
        <option value='W:L' <?php echo ($dfv['tshirt'] == 'W:L' ? 'selected' : '') ?>>(Women's) L</option>
        <option value='W:XL' <?php echo ($dfv['tshirt'] == 'W:XL' ? 'selected' : '') ?>>(Women's) XL</option>
        <option value='M:S' <?php echo ($dfv['tshirt'] == 'M:S' ? 'selected' : '') ?>>(Men's) S</option>
        <option value='M:M' <?php echo ($dfv['tshirt'] == 'M:M' ? 'selected' : '') ?>>(Men's) M</option>
        <option value='M:L' <?php echo ($dfv['tshirt'] == 'M:L' ? 'selected' : '') ?>>(Men's) L</option>
        <option value='M:XL' <?php echo ($dfv['tshirt'] == 'M:XL' ? 'selected' : '') ?>>(Men's) XL</option>
    </select>
    </tr></tr>
</table>
</fieldset>

<p style="text-align:right"><input type="submit" id="submitbutton" name="submitbutton" value="--&gt; Continue --&gt;"></p>

</form>

<p>For registration and payment enquiries, please email <a href="mailto:registration@cumc.math.ca">registration@cumc.math.ca</a>.</p>

<?php
}

?>
