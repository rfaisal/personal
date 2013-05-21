<?php 
 $pageid=$_GET["pageid"]?$_GET["pageid"]:"index";
?>												
<ul>
			<li <?php if($pageid == "index") echo "class=\"current_page_item\" "?> ><a href="index.html">Home</a></li>
			<li <?php if($pageid == "about") echo "class=\"current_page_item\" "?> ><a href="about.html">About</a></li>
			<li <?php if($pageid == "schedule") echo "class=\"current_page_item\" "?> ><a href="schedule.html">Schedule</a></li>
			<li <?php if($pageid == "speakers") echo "class=\"current_page_item\" "?> ><a href="speakers.html">Speakers</a></li>
			<li <?php if($pageid == "registration") echo "class=\"current_page_item\" "?> ><a href="registration.html">Registration</a></li>
			<li <?php if($pageid == "sponsors") echo "class=\"current_page_item\" "?> ><a href="sponsors.html">Sponsors</a></li>
			<li <?php if($pageid == "faq") echo "class=\"current_page_item\" "?> ><a href="faq.html">FAQ </a></li>
</ul>