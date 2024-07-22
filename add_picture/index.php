<?php
session_start()  ; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
 <body>
    
<div class="element_1">



<form>   

<div>
    <label for="file-input"><img width="50" height="50" src="https://img.icons8.com/external-xnimrodx-lineal-xnimrodx/50/external-dowload-big-data-xnimrodx-lineal-xnimrodx.png" alt="external-dowload-big-data-xnimrodx-lineal-xnimrodx"></label>
    
</div>


<input type="file" name="" id="file-input"  class="class1"   /><br />
 

<div>
<label for="submit-button"><img width="50" height="50" src="https://img.icons8.com/external-tanah-basah-basic-outline-tanah-basah/50/external-send-folder-folder-tanah-basah-basic-outline-tanah-basah.png" alt="external-send-folder-folder-tanah-basah-basic-outline-tanah-basah"></label>
    
</div>

<input class="class1" type="submit" value="Envoyer" id="submit-button" class="class3" onclick="disip()" />
</form>
<script>
function disip() 
{
    document.getElementById("submit-button").style.display="none"; 
  
}
 

</script>
<div id="upload-progress" ></div>
<script type="text/javascript" src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
<script type="text/javascript" src="upload.js"></script>

<a href="uploads/" class="class4"><div><img width="50" height="50" src="https://img.icons8.com/android/50/picture.png" alt="picture"/></div></a>
</div>



<div id="bg_black" onclick="bg_black()"></div>
<style>
    .class1{
        display:none ; 
    }
</style>


<style>
    .element_1{
        width : 30%; 
        top: 35px; 
        text-align:center ; 
        margin:auto  ; 
        border:1px solid rgba(0,0,0,0.9); 
        border-radius: 15px;
        position:absolute ; 
background-color:white ; 
z-index: 2;
left:35%;
    }
    .element_1 div {
        padding:7px ; 
    }
    #bg_black{
z-index: 1;

        background-color:rgba(0,0,0,0.9) ; 
        width : 99%;
        height : 99% ; 
        position:absolute ; 
        top:0 ; 
    }
</style>
<link href="https://fonts.googleapis.com/css?family=Anton&display=swap" rel="stylesheet">


<script>
    function bg_black() {
        window.location.href = "../index.php";
    }
</script>
</body>
</html>