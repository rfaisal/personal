<?php
//         makereceipt.php
// CUMC receipt generator for a given payment id, language
//
// Receipt returned as an HTML string in UTF-8


function makereceipt($dbh, $pmtid, $lang) {
        // Get the payment record
        $sthc = $dbh->prepare('SELECT * FROM payment WHERE id = ?');
        $sthc->execute(array((int) $pmtid));
        $p = $sthc->fetch(PDO::FETCH_ASSOC);
        $txn_id = $p['ipn_txn_id'];
        if ($p['rectype'] != 'PAYMENT') die("Payment record $pmtid is not a PAYMENT.  No receipt can be generated.");

        // Get the customer record
        $sthc = $dbh->prepare('SELECT * FROM customer WHERE id = ?');
        $sthc->execute(array((int) $p['customer_id']));
        $c = $sthc->fetch(PDO::FETCH_ASSOC);

        // use the customer's language unless otherwise demanded
        if (!isset($lang) || ($lang != 'en' && $lang != 'fr')) {
            $lang = $c['language'];     
        }

        // set domain for translation strings
        $olddomain = textdomain(NULL);
        bindtextdomain('regmod', '/cumc/docroot/2012/regmodule/translations');
        bind_textdomain_codeset('regmod','UTF-8');
        textdomain('regmod');

        // Choose locale matching customer or specified language
        $oldlocale = setlocale(LC_ALL, 0);
        setlocale(LC_ALL, ($lang == 'fr' ? 'fr_CA' : 'en_CA') . ".UTF-8");

        // Get the IPN data if possible
        $ipn = NULL;
        if (isset($txn_id)) {
            $sthc = $dbh->prepare('SELECT * FROM paypal_ipn WHERE txn_id = ?');
            $sthc->execute(array($txn_id));
            if ($sthc->rowCount() > 0) {
                $ipn = $sthc->fetch(PDO::FETCH_ASSOC);
            }
        }

        // localized payment date
        $paydate = strftime('%d %b %Y',strtotime($p['when']));

        if (isset($ipn)) {
            $paidby = isset($ipn['first_name']) ? $ipn['first_name'] : '';
            if (isset($ipn['last_name']) && $ipn['last_name'] != '') {
                if ($paidby != '') $paidby .= ' ';
                $paidby .= $ipn['last_name'];
            }
            if (isset($ipn['payer_email']) && $ipn['payer_email'] != '') {
                if ($paidby != '') $paidby .= "<br>\n&nbsp;&nbsp;";
                $paidby .= "<a href='mailto:" . $ipn['payer_email'] . "'>" . $ipn['payer_email'] . "</a>";
            }
            if ($paidby != '') $paidby = "&nbsp;&nbsp;" . $paidby;
        } else {
            $paidby = 'No payer information';
        }

        $reginfo = $c['givennames'] . ' ' . $c['lastname'] . "&nbsp;&nbsp;<br>\n";
        $reginfo .= (isset($c['university']) && $c['university'] != '' ? $c['university'] . "&nbsp;&nbsp<br>\n" : '');
        $reginfo .= "<a href='mailto:" . $c['email'] . "'>" . $c['email'] . "</a>&nbsp;&nbsp;\n";

        $lineitems = '';

        $sql = <<<FINDITEMS57
SELECT i.itemcode, i.cost
  FROM paymentxorder x
  LEFT JOIN itemordered i
    ON x.itemordered_id = i.id
  WHERE x.payment_id = ?
    AND x.linktype = 'PAID'
  ORDER BY i.id;
FINDITEMS57;
        $sthc = $dbh->prepare($sql);
        $sthc->execute(array((int)$pmtid));
        $activh = $dbh->prepare("SELECT * FROM activities WHERE itemcode = ?");

        while ($i = $sthc->fetch(PDO::FETCH_ASSOC)) {
            $activh->execute(array($i['itemcode']));
            if ($activh->rowCount() < 1)
                die("No such activity as item code " . $i['itemcode']);
            $act = $activh->fetch(PDO::FETCH_ASSOC);
            $desc = $act['name_' . $lang];
            $cost = money_format('%n', $i['cost']);
            $lineitems .= <<<LINEITEM295
     <tr>
      <td>$desc</td>
      <td>&nbsp;</td><td align="center">1</td><td>&nbsp;</td>
      <td align="right">$cost</td>
     </tr>
LINEITEM295;
        }
        $billtotal = money_format('%n',$p['amount']);

        $htmlreceipt = "<a href='http://" . _('cumc.math.ca') .
            "' title='" . _('CUMC web site') . "'><img border=0 src='http://" .
            _('cumc.math.ca') . "/2012/regmodule/receipt-banner-$lang.jpg' alt='" .
            _('[banner image - CUMC]') . "'></a>\n";
        $htmlreceipt .= <<<H98
<table width="765">
 <tr valign="bottom">
  <td style="font-size:125%; font-weight:bold; padding-bottom:20px; padding-top:20px">
H98;
        $htmlreceipt .= _("Payment Receipt") . <<<H103
  </td>
  <td align="right" style="padding-bottom:20px">$paydate</td>
 </tr>
 <tr valign="top">
  <td style="font-weight:bold">
H103;
        $htmlreceipt .= _("PayPal transaction number") . "</td><td align='right' style='font-weight:bold'>" . _("CUMC payment number") . <<<H110
  </td>
 </tr>
 <tr>
  <td style="color:#666; padding-bottom:20px">$txn_id</td>
  <td align="right" style="color:#666; padding-bottom:20px">$pmtid</td>
 </tr>
 <tr valign="top">
  <td style="font-weight:bold">
H110;
        $htmlreceipt .= _("Paid by:") . "</td><td style='font-weight:bold' align='right'>" . _("CUMC Participant:") . <<<H120
  </td>
 </tr>
 <tr valign="top">
  <td>$paidby</td>
  <td align="right">$reginfo</td>
 </tr>
 <tr>
  <td colspan="2" align="Center" style="padding-top:20px; padding-bottom:10px">
    <table cellspacing="0" cellpadding="4" style="border: 1px solid #000">
     <tr style="background-color: #ddf;">
      <th style="text-align:left; border-bottom: 1px dotted #000">
H120;
        $htmlreceipt .= _('Registered for') . <<<H133
     </th>
      <th style="border-bottom: 1px dotted #000">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</th>
      <th style="border-bottom: 1px dotted #000">
H133;
        $htmlreceipt .= _("Quantity") . <<<H138
      </th>
      <th style="border-bottom: 1px dotted #000">&nbsp;&nbsp;&nbsp;&nbsp;</th>
      <th style="text-align:right; border-bottom: 1px dotted #000">
H138;
        $htmlreceipt .= _("Price") . <<<H143
      </th>
     </tr>
     $lineitems
     <tr style="background-color: #ddf;">
      <td colspan="4" align="right" style="border-top: 1px dotted #000">
H143;
        $htmlreceipt .= _("Total:") . <<<H150
&nbsp;</td>
      <td align="right" style="border-top: 1px dotted #000; font-weight:bold">$billtotal</td>
     </tr>
    </table>
  </td>
 </tr>
</table>
H150;
        $htmlreceipt .= '<p>' .
            sprintf(_("<p>Registrations are processed by the <a href='http://%s'>%s</a>. This transaction typically appears on your statement as \"PayPal&nbsp;*CANADIANMAT\"."),
                        _("cms.math.ca"), _("Canadian Mathematical Society")) . "\n";

        $htmlreceipt .= sprintf(_("<p>For registration and payment enquiries, please email <a href='mailto:%s'>%s</a>.</p>"), _("registration@cumc.math.ca"), _("registration@cumc.math.ca")) . "\n";

        $htmlreceipt .= "<p style='font-size:125%; font-weight:bold'>" .
                _("Thank you for registering!") . "</p>\n";

        // restore locale, domain to original value
        $oldlocale = setlocale(LC_ALL, $oldlocale);
        $olddomain = textdomain($olddomain);

        return $htmlreceipt;
}

function icode_lookup($icode) {
    if ($icode == 'CONF') { return _('Conference');
    } elseif ($icode == 'OBANQ') { return _('Opening Banquet');
    } elseif ($icode == 'WDINN') { return _('Dinner for Women in Math and Science');
    } elseif ($icode == 'CBANQ') { return _('Closing Banquet');
    } else { return $icode;
    }
}


?>
