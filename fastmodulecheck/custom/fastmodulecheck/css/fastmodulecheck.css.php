<?php
// Informer le navigateur que le fichier doit être interprété comme du CSS
header("Content-Type: text/css");
?>

#hiddenTable_fastmodulecheck {
    display: flex;
    position: absolute;
    top: 50;
    background-color: gray;
    z-index: 4000;
}
.table_fast_title {
    display: flex;
    flex-wrap: no-wrap;
    justify-content: space-between;
}
.table_fast_value {
    display: flex;
    flex-wrap: nowrap;
    justify-content: space-between;
    align-items: center;
    justify-content: center;
}
.table_fast_value td {
    margin: 0 10px 0 10px;
}