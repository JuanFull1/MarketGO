<?php
// usuarios_admin.php — Crear moderadores y activar/suspender cuentas
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php"; error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$rol=$pdo->prepare("SELECT rol_sistema FROM perfil_usuario WHERE id=? AND estado='activo'"); $rol->execute([$uid]); $r=$rol->fetchColumn();
if($r!=='administrador') die('No autorizado');

if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    if(($_POST['op']??'')==='crear_mod'){
      $nombre=trim($_POST['nombre']); $apellido=trim($_POST['apellido']); $correo=trim($_POST['correo']);
      $user=trim($_POST['username']); $pass=$_POST['password'];
      if(!$nombre||!$apellido||!$correo||!$user||!$pass) throw new Exception('Completa todos los campos');
      if(!filter_var($correo,FILTER_VALIDATE_EMAIL)) throw new Exception('Correo inválido');
      $hash=password_hash($pass,PASSWORD_DEFAULT);
      $pdo->prepare("INSERT INTO perfil_usuario (nombre,apellido,correo,username,password_hash,estado,es_comprador,es_vendedor,rol_sistema) VALUES (?,?,?,?,?,'activo',FALSE,FALSE,'moderador')")
          ->execute([$nombre,$apellido,$correo,$user,$hash]);
    }
    if(($_POST['op']??'')==='toggle'){
      $id=$_POST['id']; $estado=$_POST['estado]()
