<?php
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid']; $cid=(int)($_POST['id']??0);
try{
  $c=$pdo->prepare("SELECT 1 FROM conversacion WHERE id=? AND (comprador_id=? OR vendedor_id=?)"); $c->execute([$cid,$uid,$uid]); if(!$c->fetch()) throw new Exception('No autorizado');
  $cont=trim($_POST['contenido']??''); if($cont===''){ $cont='(adjunto)'; }
  $ins=$pdo->prepare("INSERT INTO mensaje (conversacion_id,autor_id,contenido,tiene_adjuntos) VALUES (?,?,?,FALSE) RETURNING id");
  $ins->execute([$cid,$uid,$cont]); $mid=$ins->fetchColumn();

  if(!empty($_FILES['adj']['name'][0])){
    @mkdir(__DIR__.'/uploads',0775,true);
    $n=count($_FILES['adj']['name']);
    for($i=0;$i<$n;$i++){
      if($_FILES['adj']['error'][$i]===UPLOAD_ERR_OK){
        $tmp=$_FILES['adj']['tmp_name'][$i];
        $ext=pathinfo($_FILES['adj']['name'][$i],PATHINFO_EXTENSION);
        $file='uploads/MSG_'.date('Ymd_His').'_'.$_SESSION['uid'].'_'.$i.'.'.preg_replace('/[^a-z0-9]+/i','',$ext);
        if(move_uploaded_file($tmp,__DIR__.'/'.$file)){
          $pdo->prepare("INSERT INTO mensaje_adjunto (mensaje_id,url,orden) VALUES (?,?,?)")->execute([$mid,$file,$i+1]);
        }
      }
    }
    $pdo->prepare("UPDATE mensaje SET tiene_adjuntos=TRUE WHERE id=?")->execute([$mid]);
  }
  $pdo->prepare("UPDATE conversacion SET actualizada_en=now() WHERE id=?")->execute([$cid]);
  header("Location: chat.php?id=".$cid); exit;
}catch(Throwable $e){ error_log('[MarketGO][chatSend] '.$e->getMessage()); header("Location: chat.php?id=".$cid."&error=1"); exit; }
