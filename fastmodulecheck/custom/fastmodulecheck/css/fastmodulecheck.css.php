<?php
// Informer le navigateur que le fichier doit être interprété comme du CSS
header("Content-Type: text/css");
?>

#hiddenTable_fastmodulecheck {
    display: flex;
    position: absolute;
    width: 15vw;
    top: 50;
    background-color: gray;
    z-index: 4000;
}
.table_fast_title {
    display: flex;
    flex-wrap: no-wrap;
    justify-content: space-around;
}
.table_fast_value {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
}
.table_fast_value td {
    width: 50%;
    margin: 0 10px 0 30px;
    text-align: start;
}
div.login_block_other {
    max-width: 500px !important;
}