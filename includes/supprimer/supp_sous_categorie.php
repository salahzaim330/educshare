<?php
session_start();
require '.../db.php';
require '.../auth.php';

if(!isset($_GET['id']) || empty($_GET['id'])){
    die ('id non recupere');
}
$id=$_GET['id'];

$s = $connexion->prepare('select * from sous_Categorie where id_s_categorie=:id_s_categorie');
$s->execute(['id_s_categorie'=>$id]);
$scategorie = $s->fetch(PDO::FETCH_ASSOC);

if(!$scategorie){
    die ('sous categorie non trouve!');
}

$del = $connexion->prepare('delete from sous_Categorie where id_s_categorie=:id_s_categorie');
$del->execute(['id_s_categorie'=>$id]);

if($del->rowCount() == 0){
    die ('erreur de suppression');
}

header('Location:.../gestion.php');
exit;