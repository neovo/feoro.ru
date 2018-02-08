<?php
$txtname = trim($_POST['txtname']);
$txtNameValue = trim($_POST['name_class_value']);
$txtphone = trim($_POST['txtphone']);
$txtemail = trim($_POST['txtemail']);
$txttheme = trim($_POST['txttheme']);
$txttext = trim($_POST['txttext']);
// от кого
$fromMail = 'callback@feoro.ru';
$fromName = 'FEORO.RU';
// Сюда введите Ваш email
$emailTo = 'anton.yurzanov@gmail.com';
$subject = $txttheme;
$subject = '=?utf-8?b?'. base64_encode($subject) .'?=';
$headers = "Content-type: text/plain; charset=\"utf-8\"\r\n";
$headers .= "From: ". $fromName ." <". $fromMail ."> \r\n";
// тело письма
$body = "$txttheme\n\nИмя: $txtname\nТелефон: $txtphone\nE-mail: $txtemail\nКомментарий: $txttext";
$mail = mail($emailTo, $subject, $body, $headers, '-f'. $fromMail );
echo 'ok';
?>
