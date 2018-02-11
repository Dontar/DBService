<?php
use DB\Factory as F;
require "../vendor/autoload.php";


$db = F::connect("firebird://SYSDBA:masterkey@prio.estate-control.com/Realestate");

$filter = [
	"is_manager" => 0,
	"is_object_manager" => 0,
	"email" => "%@%.com"
];

$where = F::where(F::filter($filter)
	->and("is_manager")->eq()
	->and("is_object_manager")->eq()
	)->or(
		F::Filter($filter)->and("email")->like()
	);
// $where = F::filter($filter)
// 	->and("is_manager")->eq()
// 	->and("is_object_manager")->eq()
// 	->or("email")->like();

$users = $db->select("SELECT * FROM view_user WHERE $where");




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
	<p>
		<?php echo $where ?>
	</p>
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
