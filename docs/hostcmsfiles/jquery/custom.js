jQuery(document).ready(function($){
//закрытие модального окна
$('.close_modal, .overlay').click(function (){
$('.popup, .overlay').css({'opacity':'0', 'visibility':'hidden'});
$('.popup > .fofm textarea').val('');
//сброс всех полей формы обраной связи
$(':input','.fofm').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
});

//показ модального окна
$('.callback').click(function (e){
e.preventDefault();
$('.popup, .overlay').css({'opacity':'1', 'visibility':'visible'});
});

$(".fofm").validate(
{
focusInvalid: true,
errorClass: "input_error",

submitHandler: function(form){
var str = $(form).serialize();
$.ajax({
type: "POST",
url: "/contact.php",
data: str,
success: function(msg) {
if(msg == 'ok') {
$('.cbok').css('display','block');
$('.fofm').css('display','none');
$(':input','.fofm').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
}
else {
$('.cbok').html('<p>Сообщение не отправлено, убедитесь в правильности заполнение полей.</p>');
$('.fofm').css('display','block');
}
}
});
return false;
}
});
});


jQuery(document).ready(function($){
//закрытие модального окна
$('.close_modal2, .overlay2').click(function (){
$('.popup2, .overlay2').css({'opacity':'0', 'visibility':'hidden'});
$('.popup2 > .fofm2 textarea').val('');
//сброс всех полей формы обраной связи
$(':input','.fofm2').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
});

//показ модального окна
$('.order').click(function (e){
e.preventDefault();
$('.popup2, .overlay2').css({'opacity':'1', 'visibility':'visible'});
});

$(".fofm2").validate(
{
focusInvalid: true,
errorClass: "input_error",

submitHandler: function(form){
var str = $(form).serialize();
$.ajax({
type: "POST",
url: "/contact.php",
data: str,
success: function(msg) {
if(msg == 'ok') {
$('.cbok2').css('display','block');
$('.fofm2').css('display','none');
$(':input','.fofm2').not(':button, :submit, :reset, :hidden').val('').removeAttr('checked').removeAttr('selected');
}
else {
$('.cbok2').html('<p>Сообщение не отправлено, убедитесь в правильности заполнение полей.</p>');
$('.fofm2').css('display','block');
}
}
});
return false;
}
});
});