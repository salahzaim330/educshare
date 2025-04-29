<?php
session_start();
require_once '../../auth/db.php';
require_once '../../auth/auth.php';

if(!isset($_GET['id']) || empty($_GET['id'])){
    die ('id non recupere');
}
$id=$_GET['id'];

$s = $connexion->prepare('select * from Categorie where id_categorie=:id_categorie');
$s->execute(['id_categorie'=>$id]);
$categorie = $s->fetch(PDO::FETCH_ASSOC);

if(!$categorie){
    die ('categorie non trouve!');
}

$del = $connexion->prepare('delete from Categorie where id_categorie=:id_categorie');
$del->execute(['id_categorie'=>$id]);

if($del->rowCount() == 0){
    die ('erreur de suppression');
}

header('Location:../gestion/gestion.php');
exit;