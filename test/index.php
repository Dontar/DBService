<?php
use DB\Factory;
require "../vendor/autoload.php";


$db = Factory::connect("firebird://SYSDBA:masterkey@prio.estate-control.com/Realestate");

// $users = $db->select("select rr.rdb\$relation_name from rdb\$relations rr where rr.rdb\$system_flag = 0");
$users = $db->select("select * from \"USER\"");

?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta http-equiv="X-UA-Compatible" content="ie=edge">
	<title>DB Service</title>
</head>
<body>
<table>
	<?php foreach($users as $user) { ?>
	<tr>
		<?php foreach($user as $value) { ?>
		<td><?php echo $value; ?></td>
		<?php } ?>
	</tr>
	<?php } ?>
</table>
</body>
</html>
