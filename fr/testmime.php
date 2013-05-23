<?php

include 'Mail.php';
include 'Mail/mime.php';

$text = 'Text version of email';
$html = '<html><body>HTML version of email</body></html>';
$file = '/tmp/btn_paynowCC_LG.gif';
$crlf = "\n";
$hdrs = array(
              'From'    => 'you@yourdomain.com',
              'Subject' => 'Test mime message'
              );

$mime = new Mail_mime(array('eol' => $crlf));

$mime->setTXTBody($text);
$mime->setHTMLBody($html);
$mime->addAttachment($file, 'image/gif');

$body = $mime->get();
$hdrs = $mime->headers($hdrs);

$mail =& Mail::factory('mail');
$mail->send('larocque@cms.math.ca', $hdrs, $body);

?>
