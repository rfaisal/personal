<?php
	require_once('auth.php');
	
	
?>


<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1" />
<title>Member Index</title>
<link href="loginmodule.css" rel="stylesheet" type="text/css" />
</head>
<body>
<h1>Welcome <?php echo $_SESSION['SESS_FIRST_NAME'];?></h1>
<p>This is a password protected area only accessible to members. <a href="logout.php">Logout</a></p>

<?php 
	
	include("../submysql.php");
		
	$myconnection=sub_connect_select("cumc2012");
	if($_POST["Submit"]){
		$sdate=$_POST["istoday"]?"CURDATE()":"'".$_POST["sdate"]."'";
		$sql_quary = "
		INSERT INTO RecentNews 
			(RecentNews.`User`,
			RecentNews.Title,
			RecentNews.`Desc`,
			RecentNews.Date,
			RecentNews.`Status`)
			VALUES ('$_SESSION[SESS_FIRST_NAME]','$_POST[stitle]','$_POST[sdesc]',$sdate,1)";
		$result=sub_query($sql_quary, $myconnection);
	}

	if($_POST["DeleteId"]){
		$sdate=$_POST["istoday"]?"CURDATE()":"'".$_POST["sdate"]."'";
		$sql_quary = "
		UPDATE RecentNews
			SET RecentNews.`Status`=0
			WHERE RecentNews.EventID=$_POST[DeleteId]";
		$result=sub_query($sql_quary, $myconnection);
	}
	
	
	$sql_quary = "SELECT RecentNews.EventID, RecentNews.Title, RecentNews.`Desc`, RecentNews.Date FROM RecentNews  WHERE RecentNews.`Status` = 1 ORDER BY RecentNews.Date DESC";
	$result=sub_query($sql_quary, $myconnection);
	
?>

<form id="loginForm" name="loginForm" method="post" action="admin.php">
  <table width="300" border="0" cellpadding="2" cellspacing="0">
    <tr>
      <th>Title </th>
      <td><input name="stitle" type="text" class="textfield" id="title" size="150" /></td>
    </tr>
    <tr>
      <th>Desc </th>
      <td><TEXTAREA NAME="sdesc" class="textfield" COLS=110 ROWS=20></TEXTAREA></td>
    </tr>
    <tr>
      <th width="124">Date</th>
      <td width="168"><input name="sdate" type="text" class="textfield" id="login" />YYYY-MM-DD e.g.,2011-06-29 (Must follow). Or Use Today <input name="istoday" type="checkbox" class="textfield"  /></td>
    </tr>
    <tr>
      <td>&nbsp;</td>
      <td><input type="submit" name="Submit" value="Add Event" /></td>
    </tr>
  </table>
 </form>
  <table border="2">
    <tr>
	  <th></th>
	  <th>Date</th>
      <th>Title</th>
      <th>Desc</th>
    </tr>
<?php
	
	while ($row = mysqli_fetch_array($result)) {
			$EventID =  $row['EventID'];
			$Title =  $row['Title']; 
			$Desc =  $row['Desc'];
			$Date =  $row['Date'];
?>
			<form method="post" action="admin.php">
			<tr>
			  <th><input type="submit" name="delete" value="Delete" /><input type="hidden" name="DeleteId" value="<?php echo $EventID; ?>" /></th>
			  <th><?php echo date("F d, Y", strtotime($Date)); ?></th>
			  <th><?php echo $Title; ?></th>
			  <th><?php echo $Desc; ?></th>
			</tr>
			</form>
<?php	}

?>
</table>

</body>
</html>
