<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<!--
Design by Free CSS Templates
http://www.freecsstemplates.org
Released for free under a Creative Commons Attribution 2.5 License

Name       : Wild Goose  
Description: A two-column, fixed-width design with dark color scheme.
Version    : 1.0
Released   : 20110710

-->
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta name="keywords" content="" />
<meta name="description" content="" />
<meta http-equiv="content-type" content="text/html; charset=utf-8" />
<LINK REL="SHORTCUT ICON" HREF="/2012/image3525.png">
<title>CUMC 2012 AT UBC's Okanagan Campus</title>
<link href="style.css" rel="stylesheet" type="text/css" media="screen" />
<script type="text/javascript" src="scripts/mainscript.js"></script>
<script type="text/javascript"> 
<!--  									
function init(){
    loadAjaxContentbyID('menu','menu.php?pageid=registration');
/*	loadAjaxContentbyID('header','header.php?pageid=registration');*/
	loadAjaxContentbyID('footer1','footer.php?pageid=registration');
	loadAjaxContentbyID('searchit','searchit.php?pageid=registration');
	loadAjaxContentbyID('upcomingtalks','upcomingtalks.php?pageid=registration');
	loadAjaxContentbyID('essentials','essentials.php?pageid=registration');
	loadAjaxContentbyID('location','location.php?pageid=registration');
	loadAjaxContentbyID('facebookbadge','facebookbadge.php?pageid=registration');
}											
//-->
</script>
<script type="text/javascript" src="swfobject.js"></script>
        <script type="text/javascript">
            <!-- For version detection, set to min. required Flash Player version, or 0 (or 0.0.0), for no version detection. --> 
            var swfVersionStr = "10.0.0";
            <!-- To use express install, set to playerProductInstall.swf, otherwise the empty string. -->
            var xiSwfUrlStr = "playerProductInstall.swf";
            var flashvars = {};
            var params = {};
            params.quality = "high";
            params.bgcolor = "#ffffff";
            params.allowscriptaccess = "sameDomain";
            params.allowfullscreen = "true";
		 params.wmode="transparent";
            var attributes = {};
            attributes.id = "CUMC";
            attributes.name = "CUMC";
            attributes.align = "middle";
            swfobject.embedSWF(
                "CUMC.swf", "flashContent", 
                "980", "191", 
                swfVersionStr, xiSwfUrlStr, 
                flashvars, params, attributes);
			<!-- JavaScript enabled so display the flashContent div in case it is not replaced with a swf object. -->
			swfobject.createCSS("#flashContent", "display:block;text-align:left;");
        </script>
<?php
    include_once 'reg/regmoddev.php';
    $reghead = registration_head();
?>
</head>
<body onload="init();">
<div id="wrapper">
	<div id="menu">
		<!-- menu loded by Ajax-->
	</div>
	<!-- end #menu -->
	<div id="header">
		 <div id="flashContent">
        	<p>
	        	To view this page ensure that Adobe Flash Player version 
				10.0.0 or greater is installed. 
			</p>
			<script type="text/javascript"> 
				var pageHost = ((document.location.protocol == "https:") ? "https://" :	"http://"); 
				document.write("<a href='http://www.adobe.com/go/getflashplayer'><img src='" 
								+ pageHost + "www.adobe.com/images/shared/download_buttons/get_flash_player.gif' alt='Get Adobe Flash player' /></a>" ); 
			</script> 
        </div>
	   	
       	<noscript>
            <object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" width="980" height="191" id="CUMC" >
                <param name="movie" value="CUMC.swf" />
                <param name="quality" value="high" />
                <param name="bgcolor" value="#ffffff" />
                <param name="allowScriptAccess" value="sameDomain" />
                <param name="allowFullScreen" value="true" />
				

                <!--[if !IE]>-->
                <object type="application/x-shockwave-flash" data="CUMC.swf" width="980" height="191">
                    <param name="quality" value="high" />
                    <param name="bgcolor" value="#ffffff" />
                    <param name="allowScriptAccess" value="sameDomain" />
                    <param name="allowFullScreen" value="true" />
                <!--<![endif]-->
                <!--[if gte IE 6]>-->
                	<p> 
                		Either scripts and active content are not permitted to run or Adobe Flash Player version
                		10.0.0 or greater is not installed.
                	</p>
                <!--<![endif]-->
                    <a href="http://www.adobe.com/go/getflashplayer">
                        <img src="http://www.adobe.com/images/shared/download_buttons/get_flash_player.gif" alt="Get Adobe Flash Player" />
                    </a>
                <!--[if !IE]>-->
                </object>
                <!--<![endif]-->
            </object>
	    </noscript>	
	</div>
	<!-- end #header -->
	<div id="page">
	<div id="page-bgtop">
	<div id="page-bgbtm">
		<div id="content">
			<div class="post">
				<h2 class="title">Participant List</h2>
				<p class="meta"></p>
				<div class="entry">

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
            echo "<table id='registrantlist'><tr><th>Name</th><th>University</th></tr>\n";
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

				</div>
			</div>
		<div style="clear: both;">&nbsp;</div>
		</div>
		<!-- end #content -->
		<div id="sidebar">
			<ul>
				<li id="searchit">
					<!-- searchit loded by Ajax-->
				</li>
				<li id="upcomingtalks">
					<!-- upcomingtalks loded by Ajax-->
				</li>
				<li id="essentials">
					<!-- essentials loded by Ajax-->
				</li>
				<li id="location">
					<!-- location loded by Ajax-->
				</li>
				<li id="facebookbadge">
					<!-- location loded by Ajax-->
				</li>
			</ul>
		</div>
		<!-- end #sidebar -->
		<div style="clear: both;">&nbsp;</div>
	</div>
	</div>
	</div>
	<!-- end #page -->
</div>
	<div id="footer">
		<p id="footer1">
			<!-- footer loded by Ajax-->
		</p>
		<p>
			<!-- Begin Motigo Webstats counter code -->
			<a id="mws4853080" href="http://webstats.motigo.com/" target="_blank">
			<img width="80" height="15" border="0" alt="Free counter and web stats" src="http://m1.webstats.motigo.com/n80x15.gif?id=AEoNWAEkPXUD9m3I_KUtQCXRz1IQ" /></a>
			<script src="http://m1.webstats.motigo.com/c.js?id=4853080&amp;lang=EN&amp;i=3" type="text/javascript"></script>
			<!--End Motigo Webstats counter code -->
		</p>
	</div>
	<!-- end #footer -->
</body>
</html>