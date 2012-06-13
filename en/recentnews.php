<?php 
	include("submysql.php");
	
	$pageid=$_GET["pageno"]?$_GET["pageno"]:1;
	$eventPerPage=5;
	$totalEvents=0;
	
	$myconnection=sub_connect_select("cumc2012");
	
	$sql_select_quary = "SELECT RecentNews.EventID, RecentNews.Title, RecentNews.`Desc`, RecentNews.Date FROM RecentNews WHERE RecentNews.`Status` = 1 ORDER BY RecentNews.Date DESC";
	$sql_count_quary = "SELECT count(RecentNews.EventID) as TotalNews FROM RecentNews WHERE RecentNews.`Status` = 1";
	
	$result=sub_query($sql_count_quary, $myconnection);
	while ($row = mysqli_fetch_array($result)) {
		$totalEvents = $row['TotalNews'];
	}
	$totalPages=ceil($totalEvents/$eventPerPage);
	if($pageid>$totalPages) $pageid=$totalPages;
	if($pageid<1) $pageid=1;
	
	$result=sub_query($sql_select_quary, $myconnection);
	$count=1;
?>
	<h2 class="title">What's New? <br />CUMC 2012 Updates</h2>
	<?php if($totalPages>1) {?><p class="meta" style="color: #808080; text-align: right;" >Pages: <?php for ( $i = 0; $i < $totalPages; $i++){ ?> <a <?php if($i+1==$pageid){?> style="color: #FFFFFF; font-size: 15px;"<?php } else {?> style="color: #808080" <?php }?> onclick="loadAjaxContentbyID('recentnews','recentnews.php?pageno=<?php echo ($i+1);?>');" href="#research"><?php echo ($i+1); }?></a></p> <?php }?>
<?php
	while ($row = mysqli_fetch_array($result)) {
		if($count>($pageid -1)*$eventPerPage && $count<=$pageid*$eventPerPage) { //not efficient change later
			$EventID =  $row['EventID'];
			$Title =  $row['Title']; 
			$Desc =  $row['Desc'];
			$Date =  $row['Date'];
?>
			<div class="entry">
				<div class="title"><h1><?php echo $Title; ?></h1></div>
				<p><span class="date"><?php echo date("F d, Y", strtotime($Date)); ?></span></p>
				<p><?php echo $Desc; ?></p>
			</div>
<?php
		}
		$count++;
	}
?>
	<?php if($totalPages>1) {?><p class="meta" style="color: #808080; text-align: right;" >Pages: <?php for ( $i = 0; $i < $totalPages; $i++){ ?> <a <?php if($i+1==$pageid){?> style="color: #FFFFFF; font-size: 15px;"<?php } else {?> style="color: #808080" <?php }?> onclick="loadAjaxContentbyID('recentnews','recentnews.php?pageno=<?php echo ($i+1);?>');" href="#research"><?php echo ($i+1); }?></a></p> <?php }?>												
