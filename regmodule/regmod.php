<?php
# todo list:
#
#   - more fully implement language choice

// *********************************************************

function registration_init($parms) {
    $regcontext = array();
    foreach ($parms as $k => $v) {
        if ($k != '') {
            $regcontext[$k] = $v;   // copy from parms to regcontext
        }
    }
    // make sure debug variable is defined, default to false
    if (!isset($regcontext['debug'])) $regcontext['debug'] = false;
    // make sure URI variable is defined, default to current URL minus QUERY_STRING
    if (!isset($regcontext['URI'])) {
        $x = explode('?',$_SERVER['REQUEST_URI']);
        $regcontext['URI'] = $x[0];
    }
    // set year to first directory if possible
    if (!isset($regcontext['year'])) {
        $x = explode('/',$_SERVER['REQUEST_URI']);
        $regcontext['year'] = $x[1];
    }
    if (!preg_match('/^[0-9]{4}$/', $regcontext['year'])) registration_syserror('Cannot determine year');
    // set lang to second directory if possible
    if (!isset($regcontext['lang'])) {
        $x = explode('/',$_SERVER['REQUEST_URI']);
        $regcontext['lang'] = $x[2];
    }
    if (!preg_match('/^(en|fr)$/', $regcontext['lang'])) registration_syserror('Cannot determine language');

    include_once("/cumc/regdb_functions.php");

    try {
        $regcontext['dbh'] = connect_to_cumc_reg_db(true);

        $regcontext['dbh']->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
    catch(PDOException $e)
        {
        registration_syserror("Error in database connection: ". $e->getMessage());
        }

    if (!$regcontext['dbh']) {
        registration_syserror("Error in database connection. No more info.");
    }

    return $regcontext;
}

function registration_syserror($message) {
    $syserror = <<<SYSERROR57
<div style="background-color:#000;color:#f00;font-size:125%;padding:1em;margin-bottom:4em;">
    <p style="font-weight:bold; font-size:125%">System Error</p>
    <p>$message</p>
</div>
SYSERROR57;
    die($syserror);
}

// **********************************************************************

function setup_gettext(&$regcontext) {
    $regcontext['olddomain'] = textdomain(NULL);
    $regcontext['oldlocale'] = setlocale(LC_ALL, 0);

    bindtextdomain('regmod', '/cumc/docroot/' . $regcontext['year'] . '/regmodule/translations');
    bind_textdomain_codeset('regmod','UTF-8');
    textdomain('regmod');
    switch ($regcontext['lang']) {
        case 'fr':
            $wantlocale = 'fr_CA.UTF-8';
            break;
        case 'en':
        default:
            $wantlocale = 'en_CA.UTF-8';
            break;
    }

    if (!setlocale(LC_ALL,$wantlocale))
        registration_syserror("Cannot set locale to $wantlocale");
#    echo "\n<!-- textdomain is now " . textdomain(NULL) . "\n     locale is now " .
#            setlocale(LC_ALL, 0) . "\n\\>\n";
}
function restore_gettext(&$regcontext) {
    setlocale(LC_ALL,$regcontext['oldlocale']);
    textdomain($regcontext['olddomain']);
}

function registration_head(&$regcontext) {

    setup_gettext($regcontext);

    standard_head_elements($regcontext);

    if (isset($_REQUEST['PPreturn'])) {     # Are we returning from PayPal?
        $regcontext['mode'] = 'PPreturn';
        $regcontext['PPreturn'] = $_REQUEST['PPreturn'];
        reghead_ppreturn($regcontext);
        return;
    }

    if (isset($_REQUEST['sname'])) {    # Are we receiving the form filled in?
        $errors = serverside_validation($regcontext);
        if ($errors == '') {
            save_to_db($regcontext);
#           generate the confirmation page with paypal hidden fields, return to us, etc.
            $regcontext['mode'] = 'Confirmation';
            reghead_confirmation($regcontext);
            return;
        }
        $regcontext['errors'] = $errors;
    }

    # Then we must need to display the form
    $regcontext['mode'] = 'Form';
    reghead_showform($regcontext);
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
    foreach (array('gname' => _('given name'),'sname' => _('last name'),'email' => _('email address'),'city' => _('city'), 'province' => _('province'), 'giveatalk' => _('talk information (or choose No)'), 'tshirt' => _('T-shirt selection'), 'preflang' => _('preferred language')) as $tf => $msgstring) {
        $fv = (isset($_REQUEST[$tf]) ? $_REQUEST[$tf] : '');
        if ($fv == '') {
            array_push($errors,sprintf( _('You need to provide your %s'), $msgstring));
        }
    }
    if (isset($_REQUEST['email']) && !preg_match('/^[A-Za-z0-9][^\@\,]*\@[^\@\.\,]+(\.[^\@\.\,]+)+$/', $_REQUEST['email'])) {
/// TRANSLATORS: this is an error message
        array_push($errors,_('The email address you provided is not in a valid format'));
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
        registration_syserror("No token - cannot save to database!");
    }
    $token = $_REQUEST['token'];

    $dbh = $regcontext['dbh'];

    # apr 5, 2012 - sjl.  We are getting a bot submitting lots of bogus forms with firstname = lastname.
    # so I will just eat those.
    if ($_REQUEST['sname'] == $_REQUEST['gname']) {
        print "<div style='background-color:#000; font-size:125%'>Thank you.</div></body></html>\n";
        exit(0);
    }


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

        $custvals = array(
                'lastname' => $_REQUEST['sname'], 
                'givennames' => $_REQUEST['gname'],
                'university' => 
                        (isset($_REQUEST['university']) 
                                && $_REQUEST['university'] != ''
                            ? $_REQUEST['university'] 
                            : NULL),
                'city' => 
                        (isset($_REQUEST['city']) && $_REQUEST['city'] != '' 
                            ? $_REQUEST['city'] 
                            : NULL),
                'province' => 
                        (isset($_REQUEST['province']) && $_REQUEST['province'] != '' 
                            ? $_REQUEST['province'] 
                            : NULL),
                'dietaryrestrictions' =>
                        (isset($_REQUEST['dietary']) && $_REQUEST['dietary'] != '' 
                            ? $_REQUEST['dietary'] 
                            : NULL),
                'tshirtsize' =>
                        (isset($_REQUEST['tshirt']) && $_REQUEST['tshirt'] != '' 
                            ? $_REQUEST['tshirt'] 
                            : NULL),
                'talklength' =>
                        (isset($_REQUEST['giveatalk']) 
                                && $_REQUEST['giveatalk'] != '' 
                                && $_REQUEST['giveatalk'] != 'N'
                        ? $_REQUEST['giveatalk']
                        : 0),
                'email' =>
                        (isset($_REQUEST['email']) && $_REQUEST['email'] != '' 
                            ? $_REQUEST['email'] 
                            : NULL),
                'regtoken' => $token,
                'lang' => 
                        (isset($_REQUEST['preflang']) && $_REQUEST['preflang'] != ''
                            ? $_REQUEST['preflang']
                            : NULL),
                'registration_ip_address' =>
                        (isset($_SERVER['REMOTE_ADDR']) 
                                && $_SERVER['REMOTE_ADDR'] != ''
                            ? $_SERVER['REMOTE_ADDR']
                            : NULL),
                'willbe19' =>
                        (isset($_REQUEST['atleast19']) 
                                && $_REQUEST['atleast19'] != '' 
                            ? true 
                            : false),
                'givingtalk' =>
                        (isset($_REQUEST['giveatalk']) 
                                && $_REQUEST['giveatalk'] != ''
                            ? ($_REQUEST['giveatalk'] == 'N' ? false : true)
                            : NULL),
                'permitparticipantlist' =>
                        (isset($_REQUEST['listpermission']) 
                                && $_REQUEST['listpermission'] != '' 
                            ? true 
                            : false)
                );

// dump array
    if (0) {
    $da = ''; $ix = 0;
    foreach ($custvals as $x) {
        print "index $ix: ";
        if (is_null($x)) { echo "NULL";
        } elseif ($x == false) { echo "false - $x";
        } elseif ($x == true) { echo "true - $x";
        } else { echo $x; }
        print "<br>\n";
        $ix++;
    }
    registration_syserror($da);
    exit(0);
    }
// print "Preparing...";

        $activh = $dbh->prepare("SELECT * FROM activities ORDER BY sortval;");
        $activh->execute();

        $style = '';

        if (!$customerid) {
            $sqlstmt = <<<INSERTCUST
INSERT INTO customer
(whenregistered, lastname, givennames, university, city, province, 
 dietaryrestrictions, tshirtsize, talklength, 
 email, regtoken, "language", registration_ip_address,
 willbe19, givingtalk, permitparticipantlist)
VALUES ( now(), :lastname, :givennames, :university, :city, :province,
    :dietaryrestrictions, :tshirtsize, :talklength,
    :email, :regtoken, :lang, :registration_ip_address,
    :willbe19, :givingtalk, :permitparticipantlist )
RETURNING id;
INSERTCUST;

            $stmt = $dbh->prepare($sqlstmt);

            bindArrayValue($stmt,$custvals,false);
            if ($stmt->execute() && $stmt->rowCount()) {
                $fetchid = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($fetchid && isset($fetchid['id']) && $fetchid['id'] != '') {
                    $newcustid = $fetchid['id'];
                } else { registration_syserror("Did not get an id back after insert!"); }
            } else { registration_syserror("Could not execute the INSERT!"); }
      
            # customer inserted, now need to INSERT the itemordered row(s)
            while ($act = $activh->fetch(PDO::FETCH_ASSOC)) {
                if (!$act['availablenow']) continue;   // skip anything no longer available
                $icode = $act['itemcode']; $cost = $act['cost'];

// print "<!-- found activity " . $icode . "-->\n";

                if ($act['choices'] == 'T'          // force anything that can't be disabled
                    || (preg_match('/T/',$act['choices'])  // enable those that are selected
                        && (isset($_REQUEST['ACT'.$icode]) && $_REQUEST['ACT'.$icode] == 'on'))) {
// print "<!-- saving itemordered record for activity " . $icode . "-->\n";

                    $status = 'UNPAID';
                    $sqlstmt = <<<INSERTITEM
INSERT INTO itemordered
(customer_id, itemcode, "cost", status)
values ($newcustid, '$icode', $cost, '$status');
INSERTITEM;
                    $stmt = $dbh->prepare($sqlstmt);
                    $stmt->execute();
                } else {
// print "<!-- not saved.  choices is " . $act['choices'] . " and REQ is " .
//    (isset($_REQUEST['ACT'.$icode]) ? $_REQUEST['ACT'.$icode] : 'NULL') . "-->\n";
                }
            }
            $customerid = $newcustid;
            $style = "created new";
        } else {
            # update existing customer record
            $sqlstmt = <<<UPDATECUST
UPDATE customer SET
whenregistered = now(),
lastname = :lastname,
givennames = :givennames, 
university = :university, 
city = :city, 
province = :province, 
dietaryrestrictions = :dietaryrestrictions, 
tshirtsize = :tshirtsize, 
talklength = :talklength, 
email = :email, 
regtoken = :regtoken,
"language" = :lang,
registration_ip_address = :registration_ip_address,
willbe19 = :willbe19,
givingtalk = :givingtalk, 
permitparticipantlist = :permitparticipantlist
WHERE
id = $customerid and regtoken = '$token';
UPDATECUST;
            $stmt = $dbh->prepare($sqlstmt);
            bindArrayValue($stmt,$custvals,false);

            if (!$stmt->execute()) {
                registration_syserror("Could not execute the customer UPDATE!");
            }


            
            # customer updated, now need to update the itemordered row(s)
            while ($act = $activh->fetch(PDO::FETCH_ASSOC)) {
                $icode = $act['itemcode']; $cost = $act['cost'];
                # is it already on file?
                $stmt = $dbh->prepare("select * from itemordered where customer_id = $customerid and itemcode = '$icode'");
                $stmt->execute();
                $preexisting = ($stmt->rowCount() > 0) ? true : false;

                if ($act['choices'] == 'T'
                    || (preg_match('/T/',$act['choices'])
                        && (isset($_REQUEST['ACT'.$icode]) && $_REQUEST['ACT'.$icode] == 'on'))) {
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
            $style = 'updated existing';
        }

    } catch (PDOException $e) {                 # GENERAL catchall exception handling
        registration_syserror("Exception caught ". $e->__toString());
    }

    $regcontext['customerid'] = $customerid;

    $getch = $dbh->prepare("SELECT * FROM customer WHERE id = $customerid");
    $getch->execute();
    $c = $getch->fetch(PDO::FETCH_ASSOC);
    $sj = "[CUMC-REG] $style unpaid customer " . $c['givennames'] . " " . $c['lastname'] . " ($customerid)";
    mail('esg@cms.math.ca', $sj, $sj);

    # this stores the token in the browser so that if user cancels or completes paypal
    # we can give context
    setcookie('cumcregistration',$token. '|' . $customerid);

}

function reghead_confirmation(&$regcontext) {

    $dbh = $regcontext['dbh'];
?>
    <style type="text/css">
    button#paybutton {
        background: url(/<?php echo $regcontext['year']; ?>/regmodule/paypalbtn-dormant-<?php echo $regcontext['lang']; ?>.gif) no-repeat;
        border: none;
        padding:0px;
        height:51px;
        width:144px;
    }
    button#paybutton:hover {
        background: url(/<?php echo $regcontext['year']; ?>/regmodule/paypalbtn-active-<?php echo $regcontext['lang']; ?>.gif) no-repeat;
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
                    'preflang' => $regcontext['lang'],
                    'email' => '',
                    'city' => '',
                    'province' => '',
                    'atleast19' => '',
                    'dietary' => '',
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
                    $dfv['giveatalk'] = ($reload['givingtalk']
                                            ? $reload['talklength'] 
                                            : ($reload['givingtalk'] == false
                                                ? 'N' : NULL));
                    $dfv['tshirt'] = $reload['tshirtsize'];
                    $dfv['listpermission'] = ($reload['permitparticipantlist'] ? 'checked' : '');
                    $dfv['atleast19'] = ($reload['willbe19'] ? 'checked' : '');
#                    $dfv['gender'] = $reload['female'];

                    $stmt = $dbh->prepare("SELECT * FROM itemordered where customer_id = ? and status != 'CANC'");
                    $stmt->execute(array($reload['id']));
                    while ($io = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $dfv['ACT' . $io['itemcode']] = 'checked';
                    }
                } else {
                    registration_syserror("Cannot reload from token " . $_REQUEST['tk']);
                }
            } else {
                registration_syserror("Cannot locate token ". $_REQUEST['tk']);
            }
        } catch (PDOException $e) {
            registration_syserror("Exception caught ". $e->__toString());
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
            foreach ($_REQUEST as $k => $v) {
                if (substr($k,0,3) != 'ACT') continue;
                $dfv[$k] = ($_REQUEST[$k] == 'on' ? 'checked' : '');
            }
            $dfv['listpermission'] = ($_REQUEST['listpermission'] == 'on' ? 'checked' : '');
            $dfv['atleast19'] = ($_REQUEST['atleast19'] == 'on' ? 'checked' : '');
            # DDLBs
            if (isset($_REQUEST['province'])) $dfv['province'] = $_REQUEST['province'];
            if (isset($_REQUEST['tshirt'])) $dfv['tshirt'] = $_REQUEST['tshirt'];
            if (isset($_REQUEST['giveatalk']) && $_REQUEST['giveatalk']!='') {
                $dfv['giveatalk'] = $_REQUEST['giveatalk'];
            } else {
                $dfv['giveatalk'] = NULL;
            }
#            if (isset($_REQUEST['gender'])) $dfv['gender'] = ($_REQUEST['gender']=='M' ? false : ($_REQUEST['gender']=='F'?true :  NULL));
        } else {

            // Get the list of activities and the default boolean
            $activh = $dbh->prepare("SELECT * FROM activities WHERE availablenow IS TRUE AND choices LIKE '%T%';");
            $activh->execute();
            while ($act = $activh->fetch(PDO::FETCH_ASSOC)) {
                $dfv['ACT' . $act['itemcode']] = (substr($act['choices'],0,1) == 'T' ? 'checked' : '');
            }
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

<?php $emsg = _("CUMC Registration:") . "\\n" . _('You need to provide your %s'); ?>

        // required fields check //
        if($("#gname").val() == '') {
            alert("<?php echo sprintf($emsg, _('given name')); ?>");
            $("#gname").focus();
            return false;
        }
        if($("#sname").val() == '') {
            alert("<?php echo sprintf($emsg, _('last name')); ?>");
            $("#sname").focus();
            return false;
        }
        if($("#email").val() == '') {
            alert("<?php echo sprintf($emsg, _('email address')); ?>");
            $("#email").focus();
            return false;
        }
        if($("#city").val() == '') {
            alert("<?php echo sprintf($emsg, _('city')); ?>");
            $("#city").focus();
            return false;
        }
        if($("#province").val() == '') {
            alert("<?php echo sprintf($emsg, _('province')); ?>");
            $("#province").focus();
            return false;
        }
        if($("#preflang").val() == '') {
            alert("<?php echo sprintf($emsg, _('preferred language')); ?>");
            $("#preflang").focus();
            return false;
        }
        if($("#giveatalk").val() == '') {
            alert("<?php echo sprintf($emsg, _('talk information')); ?>");
            $("#giveatalk").focus();
            return false;
        }
        if($("#tshirt").val() == '') {
            alert("<?php echo sprintf($emsg, _('T-shirt selection')); ?>");
            $("#tshirt").focus();
            return false;
        }
        // value validation
        var emailre = new RegExp('^[A-Za-z0-9][^\@\,]*\@[^\@\.\,]+(\.[^\@\.\,]+)+$');
        if(!($("#email").val().match(emailre))) {
            alert("<?php echo _("CUMC Registration:") . "\\n" . _('The email address you provided is not in a valid format') . '.'; ?>");
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

function standard_head_elements(&$regcontext) {
?>
    <script type="text/javascript" src="/<?php echo $regcontext['year']; ?>/js/jquery-1.7.1.min.js"></script>

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
    $div = '';

    if ($regcontext['mode'] == 'Form') {
        $div = regdiv_showform($regcontext);
    } elseif ($regcontext['mode'] == 'Confirmation') {
        $div = regdiv_confirmation($regcontext);
    } elseif ($regcontext['mode'] == 'PPreturn') {
        $div = regdiv_ppreturn($regcontext);
    } else {
        registration_syserror("Unknown mode '".$regcontext['mode']."'");
    }
    echo $div;
    restore_gettext($regcontext);
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

    <p style="font-size=115%; color:#ff0; font-weight:bold"><?php echo _("You cancelled online payment for your registration.");?></p>
    <p style="font-weight:bold"><?php echo sprintf(_("However, you are still stored in our database as customer number %d."), $customerid); ?></p>
    <p>
    <?php echo sprintf(_("If you wish, you can <a href='%s'>return to the registration form</a> to make changes or <a href='%s'>try again to pay</a> through PayPal."), $tokenlink, $tokenlink); ?>
    </p>

    <p><?php echo sprintf(_("If you are having difficulty completing your transaction through PayPal, you may wish to <a href='mailto:%s?Subject=Payment for cust number %d' style='font-weight:bold'>email us</a>."), _("registrations@cumc.math.ca"), $customerid); ?></p>

    <?php    } elseif ($regcontext['PPreturn'] == 'complete') { ?>

    <p style="font-size=115%; font-weight:bold"><?php echo _("Thank you for registering.");?></p>
    <p><?php echo _("You will receive a receipt in your email as soon as PayPal confirms your payment (typically within ten minutes).");?></p>
    <p><?php echo sprintf(_("For your information, your customer number on our system is %d."), $customerid); ?></p>

    <p><?php echo sprintf(_("If you have any further questions regarding payment or registration, you may wish to <a href='mailto:%s' style='font-weight:bold'>email us</a>."),_("registrations@cumc.math.ca"));?> </p>

    <?php    } else { registration_syserror("PPreturn value of " . $regcontext['PPreturn'] . " is not understood."); }                        
    } else {

        # could be cancel or complete
        if ($regcontext['PPreturn'] == 'cancel') {
            print "<p>" . _("You cancelled online payment for your registration.") . "</p>\n";
            print "<p><a href='" . $uri . "'>" . _("Start over") . "</a>.</p>\n";
        } elseif ($regcontext['PPreturn'] == 'complete') {
            print "<p>" . _("Thank you for registering.  You should receive a receipt in your email shortly.") . "</p>\n";
        } else {
            registration_syserror("PPreturn value of " . $regcontext['PPreturn'] . " is not understood.");
        }        
    }


}

function regdiv_confirmation(&$regcontext) {
    $dbh = $regcontext['dbh'];
?>

<form id="confirmform" method='post' action='https://www.<?php echo ($regcontext['debug'] == false ? '' : 'sandbox.'); ?>paypal.com/cgi-bin/webscr'>
    <input type="hidden" name="charset" value="UTF-8">
    <input type="hidden" name="cmd" value="_cart">
    <input type="hidden" name="upload" value="1">
    <input type="hidden" name="business" value="cumcpayments@cms.math.ca">
    <input type="hidden" name="currency_code" value="CAD">

    <input type="hidden" name="no_note" value="1">
    <input type="hidden" name="cancel_return" value="http://<?php echo _('cumc.math.ca') . $_SERVER['REQUEST_URI']; ?>?PPreturn=cancel">
    <input type="hidden" name="rm" value="1">
    <input type="hidden" name="return" value="http://<?php echo _('cumc.math.ca') . $_SERVER['REQUEST_URI']; ?>?PPreturn=complete">
    <input type="hidden" name="cbt" value="<?php echo _('Return to CUMC web site'); ?>">

    <input type="hidden" name="notify_url" value="http://<?php echo _('cumc.math.ca') . '/' . $regcontext['year']; ?>/regmodule/cumcIPN3055.php">

    <input type="hidden" name="lc" value="<?php echo ($regcontext['lang'] == 'fr' ? 'fr_CA' : 'en_CA'); ?>">  <!-- language code = currency! -->
    <input type="hidden" name="no_shipping" value="1">

    <input type="hidden" name="country" value="CA">

    <input type="hidden" name="cpp_header_image" value="https://cms.math.ca/images/paypalbanners/cumc-<?php echo $regcontext['lang']; ?>.jpg">
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

        $activh = $dbh->prepare("SELECT * FROM activities WHERE availablenow IS TRUE ORDER BY sortval;");
        $activh->execute();

        $itemsordered = array();
        $itemcount = 0;

        while ($act = $activh->fetch(PDO::FETCH_ASSOC)) {
            $icode = $act['itemcode'];
            $name = $act['name_' . $regcontext['lang']];

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
        $custom = 'CUMC' . $regcontext['year'] . '-' . implode('-',$itemsordered);
        $invoice = $custom;
        print "<input type='hidden' name='custom' value='$custom'>\n";

        if (!$regcontext['debug']) {
            print "<input type='hidden' name='invoice' value='$invoice'>\n";
        }

    } catch (PDOException $e) {
        registration_syserror("Exception caught ". $e->__toString());
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
<legend><?php echo _("Payment");?></legend>
    <p align="center" style="font-weight:bold; color:#f66"><?php echo _("Payment for registration is only accepted online.");?></p>

<table align="center" style="margin-top:1em; margin-bottom:1em;">
  <tr valign="middle">
    <td align="left" style="padding-right:2.5em"><?php echo _("Your total:");?> <span id="payamount" style="white-space:nowrap"><?php echo money_format('%n',$total); ?></span></td>
    <td>
        <button type="submit" id="paybutton" alt="<?php echo _('Click to proceed to PayPal');?>" title="<?php echo _('Click to proceed to PayPal');?>">
        </button>
        </td>
  </tr>
</table>

<p style="line-height:normal"><img src="/<?php echo $regcontext['year']; ?>/regmodule/PayPal_mark_60x38.gif" align="left" style="margin:0px 1em 0.5em 0px">
<span style="font-weight:bold"><?php echo _("The CUMC uses PayPal for payment processing.");?></span>
    <?php echo _("You will need either a credit card or a PayPal account to complete your registration.  Payments are made to the <i>Canadian Mathematical Society</i>.  You will be emailed a receipt once your payment is processed (usually within ten minutes).");?></p>
    <p><i><?php echo _("Note: You do not need to register an account on PayPal to use a credit card via PayPal.");?></i></p>
</fieldset>
</form>

<p><? echo sprintf(_("For registration and payment enquiries, please email <a href='mailto:%s'>%s</a>."), _("registration@cumc.math.ca"), _("registration@cumc.math.ca"));?></p>

<?php
}
function reqd() {
    echo "<span class='responsereqd'>*</span>";
}

############################################################################
function regdiv_showform(&$regcontext) {
    $dfv = $regcontext['dfv'];
    $dbh = $regcontext['dbh'];
?>

<p style="line-height:normal">

    <p>
        <?php echo _("<i>Note:</i> Online payment is required at the time of registration."); ?>
    </p>

<?php
    if (isset($regcontext['errors']) && $regcontext['errors'] != '') {
        echo "\n<div id='reperrors'><span style='color:#fff;font-weight:bold'>" .
            _("Form error:") . "</span><br>\n" . $regcontext['errors'] . "\n</div>\n";
    }
?>

<form id="regform" onsubmit="return form_submitted();" method='post' action="<?php echo $regcontext['URI']; ?>">
<input type="hidden" id='token' name='token' value="<?php echo htmlspecialchars($dfv['token']); ?>">


<p id='responserqdlegend'><?php reqd(); echo " = " . _("Response required"); ?></p>
<fieldset>
<legend><?php echo _("Participant Information");?></legend>
<table align="center">
<tr><td colspan="2" align="center" style="color:#ff0; font-weight:bold">
    <?php echo _("Please use proper capitalization (e.g.. use 'Euler', not 'EULER' or 'euler').<br>Your badge will be printed exactly as you enter it here.");?>
    </td></tr>

<tr><td colspan="2" style="background-color:#4D4D80; border:3px outset #4D4D80; padding: 6px;">
    <p style="line-height:normal; margin:0px; padding:0px; text-align:right; font-size:85%; color:#ff0"><?php echo _("Your Badge");?></p>
<table align="center">
<tr><td style="padding:0.5em 0px 0px 0px"><?php echo _("Given name:"); reqd(); ?></td>
    <td style="padding:0.5em 0px 0px 0px"><?php echo _("Last name:"); reqd(); ?></td>
    </tr>
<tr><td style="padding:0px 0px 0.5em 0px">
        <input type="text" id='gname' name='gname' value="<?php echo htmlspecialchars($dfv['gname']); ?>" /></td>
    <td style="padding:0px 0px 0.5em 0px">
    <input type="text" value="<?php echo htmlspecialchars($dfv['sname']); ?>" id='sname' name='sname' style="width:250px"/></td></tr>

<tr><td colspan="2" style="padding:0.5em 0px 0px 0px"><?php echo _("Name of University or Institution:"); ?></td></tr>
<tr><td colspan="2" style="padding:0px 0px 0.5em 0px">
        <input type="text" id='university' name='university' style="width:400px"  value="<?php echo htmlspecialchars($dfv['university']); ?>"/>
    </td></tr>

<!--    <tr><td colspan="2" align="center" style="font-style:italic; color:#ff0">The information above will appear on your badge.</td></tr> -->
</table></td></tr>


<tr><td colspan="2" style="padding:0.5em 0px 0.5em 0px"><?php echo _("The CUMC web site includes a publicly-viewable list of names of participants.") . '<br>'; ?>
    <input type="checkbox" name="listpermission" id='listpermission'  <?php echo $dfv['listpermission']; echo '>' . _("I give my permission to list my name and university."); ?>
    </td></tr>

<tr><td colspan="2" style="padding:0.5em 0px 0px 0px"><?php echo _('Email:'); reqd(); ?></td></tr>
<tr><td colspan="2" style="padding:0px 0px 0.5em 0px">
    <input type="text" id='email' name='email'  style="width:400px" value="<?php echo htmlspecialchars($dfv['email']); ?>"/></td></tr>

<tr><td style="padding:0.5em 0px 0px 0px"><?php echo _('City:'); reqd(); ?></td><td style="padding:0.5em 0px 0px 0px"><?php echo _('Province:'); reqd(); ?></td></tr>
<tr><td style="padding:0px 0px 0.5em 0px">
        <input type="text" id='city' name='city'  style="width:250px"  value="<?php echo htmlspecialchars($dfv['city']); ?>"/></td>
    <td style="padding:0px 0px 0.5em 0px"><select id='province' name='province'> 
        <option value='' <?php echo ($dfv['province'] == '' ? 'selected' : '');
            echo '>' . _("[ choose ]"); ?></option>
        <option value='BC' <?php echo ($dfv['province'] == 'BC' ? 'selected' : '') ?>>
            <?php echo _('British Columbia');?></option>
        <option value='AB' <?php echo ($dfv['province'] == 'AB' ? 'selected' : '') ?>>
            <?php echo _('Alberta');?></option>
        <option value='SK' <?php echo ($dfv['province'] == 'SK' ? 'selected' : '') ?>>
            <?php echo _('Saskatchewan');?></option>
        <option value='MB' <?php echo ($dfv['province'] == 'MB' ? 'selected' : '') ?>>
            <?php echo _('Manitoba');?></option>
        <option value='ON' <?php echo ($dfv['province'] == 'ON' ? 'selected' : '') ?>>
            <?php echo _('Ontario');?></option>
        <option value='QC' <?php echo ($dfv['province'] == 'QC' ? 'selected' : '') ?>>
            <?php echo _('Quebec');?></option>
        <option value='NB' <?php echo ($dfv['province'] == 'NB' ? 'selected' : '') ?>>
            <?php echo _('New Brunswick');?></option>
        <option value='NS' <?php echo ($dfv['province'] == 'NS' ? 'selected' : '') ?>>
            <?php echo _('Nova Scotia');?></option>
        <option value='PE' <?php echo ($dfv['province'] == 'PE' ? 'selected' : '') ?>>
            <?php echo _('Prince Edward Island');?></option>
        <option value='NL' <?php echo ($dfv['province'] == 'NL' ? 'selected' : '') ?>>
            <?php echo _('Newfoundland and Labrador');?></option>
        <option value='NU' <?php echo ($dfv['province'] == 'NU' ? 'selected' : '') ?>>
            <?php echo _('Nunavut');?></option>
        <option value='NT' <?php echo ($dfv['province'] == 'NT' ? 'selected' : '') ?>>
            <?php echo _('Northwest Territories');?></option>
        <option value='YT' <?php echo ($dfv['province'] == 'YT' ? 'selected' : '') ?>>
            <?php echo _('Yukon');?></option>
        <option value='--' <?php echo ($dfv['province'] == '--' ? 'selected' : '') ?>>
            <?php echo _('(outside Canada)');?></option>
    </select></td></tr>

<tr><td style="padding:0.5em 0px 0px 0px"><?php echo _('Age:'); ?></td>
    <td style="padding:0.5em 0px 0px 0px"><?php echo _('Preferred Language:'); reqd(); ?></td></tr>
<tr valign="top"><td style="padding:0px 0.75em 0.5em 0px">
        <input type="checkbox" name="atleast19" id='atleast19' <?php echo $dfv['atleast19']; echo '>' . _("When the conference begins, I will be at least 19 years old."); ?></td>
    <td style="padding:0px 0px 0.5em 0px">
        <select id='preflang' name='preflang'> 
            <option value='' <?php echo ($dfv['preflang'] != 'en' && $dfv['preflang'] != 'fr' ? 'selected' : ''); echo '>' . _("[ choose ]"); ?></option>
            <option value='en' <?php echo ($dfv['preflang'] == 'en' ? 'selected' : '') ?>>English</option>
            <option value='fr' <?php echo ($dfv['preflang']== 'fr' ? 'selected' : '') ?>>Français</option>
        </select>
        </td></tr>

<tr><td colspan="2" style="padding:0.5em 0px 0px 0px"><?php echo _('Dietary Restrictions (if any):');?></td></tr>
<tr><td colspan="2"  style="padding:0px 0px 0.5em 2em">
    <textarea id='dietary' name='dietary' style="width:400px; height:50px" wrap="physical"><?php echo htmlspecialchars($dfv['dietary']); ?></textarea>
    </td></tr>
</table>
</fieldset>

<fieldset>
<legend><?php echo _("Conference Options"); ?></legend>
<table align="center">
<tr><td colspan="2" style="padding:0.5em 0px 0.5em 0px">

    <table align="center" id="confopt" cellspacing=0>
        <tr><th><?php echo _("I wish to attend:");?></th><th style="padding-left:1.25em; text-align:right"><?php echo _("Cost");?></th><td></td></tr>

<?php
    $activh = $dbh->prepare("SELECT * FROM activities WHERE availablenow IS TRUE ORDER BY SORTVAL;");
    $activh->execute();

    while ($act = $activh->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td><p><input type='checkbox' name='ACT" . $act['itemcode'] .
                "' id='ACT" . $act['itemcode'] . "' ";
        if ($act['choices'] == 'T') {
            echo " checked disabled ";
        } elseif ($act['choices'] == 'F') {
            echo " disabled ";
        } else {
            echo $dfv['ACT' . $act['itemcode']];
        }
        echo ">" . $act['name_' . $regcontext['lang']];
        echo "</p>\n";
        if (isset($act['note_' . $regcontext['lang']])
                && $act['note_' . $regcontext['lang']] != '') {
            echo "<p style='padding: 0px 0px 4px 1.5em; color:#ff0; font-style:italic'>\n";
            echo $act['note_' . $regcontext['lang']] . "</p>\n";
        }
        echo "</td><td style='padding-left:1.25em; text-align:right; white-space:nowrap'>" .
                money_format('%n',$act['cost']) . "</td></tr>\n";
    }
?>

    </table>
    </td></tr>

<tr><td colspan="2" style="padding:0.5em 0px 0px 0px"><?php echo _("Participants are invited to give a talk of either 20 minutes or 45 minutes.") . '<br>' .
        _("Do you want to give a talk?"); reqd(); ?></td></tr>
<tr><td colspan="2" style="padding:0px 0px 0.5em 2em">
    <select id='giveatalk' name='giveatalk' onchange="talkchanged();"> 
        <!-- <?php echo $dfv['giveatalk']; ?> -->
        <option value='' <?php echo (is_null($dfv['giveatalk']) || $dfv['giveatalk'] == '' ? 'selected' : '') ?>>
            <?php echo _("[ choose ]"); ?></option>
        <option value='N' <?php echo ($dfv['giveatalk'] == 'N' ? 'selected' : '') ?>>
            <?php echo _("No, I won't give a talk");?></option>
        <option value='20' <?php echo ($dfv['giveatalk'] == 20 ? 'selected' : '') ?>>
            <?php echo _("Yes, I will give a 20 minute talk");?></option>
        <option value='45' <?php echo ($dfv['giveatalk'] == 45 ? 'selected' : '') ?>>
            <?php echo _("Yes, I will give a 45 minute talk");?></option>
    </select><br>
    <div id="talkemailnotice">
        <?php echo _("Please follow <a href='AbstractSubmissionGuidelines.pdf' target='_blank'>this guide</a> (PDF) to submit your talk's abstract and title to the organizing committee.");?>
    </div>
    </td></tr>


<tr><td colspan="2" style="padding:0.5em 0px 0px 0px"><?php echo _("Specify the style and size of the Conference T-shirt you prefer:"); reqd(); ?></td></tr>
<tr><td colspan="2"  style="padding:0px 0px 0.5em 2em">
    <select id='tshirt' name='tshirt'> 
        <option value='' <?php echo (is_null($dfv['tshirt']) || $dfv['tshirt'] == '' ? 'selected' : '') ?>><?php echo _("[ choose ]");?></option>
        <option value='W:S' <?php echo ($dfv['tshirt'] == 'W:S' ? 'selected' : '') ?>><?php echo _("(Women's) S");?></option>
        <option value='W:M' <?php echo ($dfv['tshirt'] == 'W:M' ? 'selected' : '') ?>><?php echo _("(Women's) M");?></option>
        <option value='W:L' <?php echo ($dfv['tshirt'] == 'W:L' ? 'selected' : '') ?>><?php echo _("(Women's) L");?></option>
        <option value='W:XL' <?php echo ($dfv['tshirt'] == 'W:XL' ? 'selected' : '') ?>><?php echo _("(Women's) XL");?></option>
        <option value='M:S' <?php echo ($dfv['tshirt'] == 'M:S' ? 'selected' : '') ?>><?php echo _("(Men's) S");?></option>
        <option value='M:M' <?php echo ($dfv['tshirt'] == 'M:M' ? 'selected' : '') ?>><?php echo _("(Men's) M");?></option>
        <option value='M:L' <?php echo ($dfv['tshirt'] == 'M:L' ? 'selected' : '') ?>><?php echo _("(Men's) L");?></option>
        <option value='M:XL' <?php echo ($dfv['tshirt'] == 'M:XL' ? 'selected' : '') ?>><?php echo _("(Men's) XL");?></option>
    </select>
    </tr></tr>
</table>
</fieldset>

<p style="text-align:right"><input type="submit" id="submitbutton" name="submitbutton" value="--&gt; <?php echo _("Continue"); ?> --&gt;"></p>

</form>

<p style="text-align:left"><?php echo sprintf(_("For registration and payment enquiries, please email <a href='mailto:%s'>%s</a>."), _("registration@cumc.math.ca"), _("registration@cumc.math.ca"));?></p>

<?php
}

function bindArrayValue($req, $array, $typeArray = false)
{
    if(is_object($req) && ($req instanceof PDOStatement))
    {
        foreach($array as $key => $value)
        {
            if($typeArray)
                $req->bindValue(":$key",$value,$typeArray[$key]);
            else
            {
                if(is_int($value))
                    $param = PDO::PARAM_INT;
                elseif(is_bool($value))
                    $param = PDO::PARAM_BOOL;
                elseif(is_null($value))
                    $param = PDO::PARAM_INT; // not PARAM_NULL;
                elseif(is_string($value))
                    $param = PDO::PARAM_STR;
                else 
                    $param = FALSE;
                   
                if($param) { 
                    if (!($req->bindValue(":$key",$value,$param))) die("cannot bind :$key ($value)");
                }
            }
        }
    }
}

?>
