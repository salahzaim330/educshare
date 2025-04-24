<?php
session_start();
require '.../db.php';
require '.../auth.php';

if(!isset($_GET['id']) || empty($_GET['id'])){
    die ('id non recupere');
}
$id=$_GET['id'];

$s = $connexion->prepare('select * from Etudiant where id=:id');
$s->execute(['id'=>$id]);
$etudiant = $s->fetch(PDO::FETCH_ASSOC);

if(!$etudiant){
    die ('etudiant non trouve!');
}

$del = $connexion->prepare('delete from Etudiant where id=:id');
$del->execute(['id'=>$id]);

if($del->rowCount() == 0){
    die ('erreur de suppression');
}

header('Location:.../gestion.php');
exit;