<?php
header('Content-Type: text/html; charset=utf-8');



$tmp = $_GET["firstname"];
// Соединяемся, выбираем базу данных
$link = mysqli_connect('localhost', 'root', '')
    or die('ei saa connect: ' . mysql_error());
mysqli_select_db($link,'moodle') or die('ei saa valida db');
$myArray = array();
// Выполняем SQL-запрос
$query ="SELECT firstname,lastname,finalgrade,itemname,mdl_grade_grades.userid,mdl_user.id FROM mdl_grade_grades,mdl_user,mdl_user_enrolments,mdl_grade_items WHERE firstname like '$tmp%' AND mdl_user.id=mdl_user_enrolments.userid AND mdl_grade_grades.userid=mdl_user.id AND mdl_grade_items.id=mdl_grade_grades.itemid";
//$query1 = "SELECT firstname,lastname FROM mdl_user,mdl_user_enrolments WHERE firstname like '$tmp%' AND mdl_user.id=mdl_user_enrolments.userid";
$result = mysqli_query($link,$query) or die('Запрос не удался: ' . mysqli_error());
    while($row = $result->fetch_array(MYSQL_ASSOC)) {
            $myArray[] = $row;
    }
    echo json_encode($myArray);

// Освобождаем память от результата
mysqli_free_result($result);

// Закрываем соединение
mysqli_close($link);
?>
