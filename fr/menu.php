<?php 
 $pageid=$_GET["pageid"]?$_GET["pageid"]:"index";
?>	
<span style="color: #000000;">&nbsp;&nbsp;<a href="http://cumc.math.ca/2012/en/"  style="color: #000000; text-decoration: none;">English</a>&nbsp;|&nbsp;<a href="http://cumc.math.ca/2012/fr/"  style="color: #000000; text-decoration: none;"><strong>Fran&ccedil;ais</strong></a></span>									
<ul>
			<li <?php if($pageid == "index") echo "class=\"current_page_item\" "?> ><a href="index.html">Accueil</a></li>
			<li <?php if($pageid == "about") echo "class=\"current_page_item\" "?> ><a href="about.html">&Agrave; propos</a></li>
			<li <?php if($pageid == "schedule") echo "class=\"current_page_item\" "?> ><a href="schedule.html">Horaire</a></li>
			<li <?php if($pageid == "speakers") echo "class=\"current_page_item\" "?> ><a href="speakers.html">Orateurs</a></li>
			<li <?php if($pageid == "registration") echo "class=\"current_page_item\" "?> ><a href="registration.php">Inscription</a></li>
			<li <?php if($pageid == "activities") echo "class=\"current_page_item\" "?> ><a href="activities.html">Activit&eacute;s</a></li>
			<li <?php if($pageid == "sponsors") echo "class=\"current_page_item\" "?> ><a href="sponsors.html">Commanditaires</a></li>
			<li <?php if($pageid == "faq") echo "class=\"current_page_item\" "?> ><a href="faq.html">FAQ</a></li>
</ul>