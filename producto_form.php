<?php // producto_form.php 
session_start(); if(!isset($_SESSION['uid'])){ header("Location: index.php"); exit; }
require_once "db.php";
error_reporting(E_ALL); ini_set('display_errors',1); ini_set('log_errors',1);
function debugPF($m,$c=[]){ error_log('[MarketGO][prodForm] '.$m.(empty($c)?'':' '.json_encode($c))); }

$pdo=(new DB())->pdo(); $uid=$_SESSION['uid'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if($_SERVER['REQUEST_METHOD']==='POST'){
  try{
    $id = (int)($_POST['id']??0);
    $tipo = $_POST['tipo']==='servicio' ? 'servicio':'producto';
    $data = [
      ':vendedor'=>$uid,
      ':codigo'=>trim($_POST['codigo']??''),
      ':nombre'=>trim($_POST['nombre']??''),
      ':desc'=>trim($_POST['descripcion']??''),
      ':tipo'=>$tipo,
      ':precio'=>floatval($_POST['precio']??0),
      ':disp'=>$_POST['disponibilidad']??'disponible',
      ':cat'=>trim($_POST['categoria']??''),
      ':ciudad'=>trim($_POST['ciudad']??''),
      ':estado'=>'activo',
      ':horario'=> $tipo==='servicio' ? trim($_POST['horario_atencion']??'') : null,
      ':img'=>null
    ];
    if(!$data[':codigo']||!$data[':nombre']||!$data[':desc']||!$data[':cat']) throw new Exception('Completa los campos obligatorios.');
    if($tipo==='servicio' && !$data[':horario']) throw new Exception('El servicio requiere horario de atención.');

    $lat = isset($_POST['lat'])?floatval($_POST['lat']):null;
    $lon = isset($_POST['lon'])?floatval($_POST['lon']):null;

    if($id>0){
      $sql="UPDATE producto SET codigo=:codigo,nombre=:nombre,descripcion=:desc,tipo=:tipo,precio=:precio,disponibilidad=:disp,categoria=:cat,ciudad=:ciudad,horario_atencion=:horario, imagen_portada_url=COALESCE(:img,imagen_portada_url), actualizado_en=now()".
           ($lat!==null&&$lon!==null ? ", geom=ST_SetSRID(ST_MakePoint(:lon,:lat),4326)" : "").
           " WHERE id=:id AND vendedor_id=:v";
      $dataUpd=$data+[':id'=>$id,':v'=>$uid];
      if($lat!==null&&$lon!==null){ $dataUpd[':lat']=$lat; $dataUpd[':lon']=$lon; }
      $upd=$pdo->prepare($sql); $upd->execute($dataUpd);
    } else {
      $sql="INSERT INTO producto (vendedor_id,codigo,nombre,descripcion,tipo,precio,disponibilidad,categoria,ciudad,geom,horario_atencion,estado,fecha_publicacion,imagen_portada_url)
            VALUES (:vendedor,:codigo,:nombre,:desc,:tipo,:precio,:disp,:cat,:ciudad,".($lat!==null&&$lon!==null?"ST_SetSRID(ST_MakePoint(:lon,:lat),4326)":"NULL").",:horario,:estado,now(),:img)
            RETURNING id";
      $ins=$pdo->prepare($sql);
      $dataIns=$data; if($lat!==null&&$lon!==null){ $dataIns[':lat']=$lat; $dataIns[':lon']=$lon; }
      $ins->execute($dataIns); $id=$ins->fetchColumn();
    }

    // SUBIR imágenes (1 a 5)
    $fileNames = [];
    if(!empty($_FILES['fotos']['name'][0])){
      @mkdir(__DIR__.'/uploads',0775,true);
      $n = count($_FILES['fotos']['name']);
      $orden=1;
      for($i=0;$i<$n && $orden<=5;$i++){
        if($_FILES['fotos']['error'][$i]===UPLOAD_ERR_OK){
          $orig = $_FILES['fotos']['name'][$i];  // para inspección por nombre
          $tmp  = $_FILES['fotos']['tmp_name'][$i];
          $ext  = pathinfo($orig,PATHINFO_EXTENSION);
          $file = 'uploads/'.date('Ymd_His').'_'.$uid.'_'.$i.'.'.preg_replace('/[^a-z0-9]+/i','',$ext);
          if(move_uploaded_file($tmp,__DIR__.'/'.$file)){
            $pdo->prepare("INSERT INTO producto_imagen (producto_id,url,orden) VALUES (?,?,?) ON CONFLICT (producto_id,orden) DO UPDATE SET url=EXCLUDED.url")->execute([$id,$file,$orden]);
            if($orden===1){ $pdo->prepare("UPDATE producto SET imagen_portada_url=? WHERE id=?")->execute([$file,$id]); }
            $fileNames[] = strtolower($orig);
            $orden++;
          }
        }
      }
    }

    // DETECCIÓN automática (texto + nombres de archivo)
    $black = [
      'arma','armas','pistola','revólver','revolver','rifle','escopeta','municion','munición',
      'bala','silenciador','explosivo','c4','granada','dinamita','bomba',
      'droga','drogas','marihuana','cannabis','cocaína','cocaina','anfetamina','lsd','opio',
      'porn','pornografía','pornografia','sexo','prostitucion','prostitución',
      'ácido','acido','ácido sulfúrico','acido sulfurico','ácido muriático','acido muriatico'
    ];
    $texto = strtolower($data[':nombre'].' '.$data[':desc'].' '.$data[':cat']);
    $hit = null;
    foreach($black as $w){
      if(strpos($texto,$w)!==false){ $hit = "Posible contenido prohibido (texto): $w"; break; }
      if(!$hit){
        foreach($fileNames as $fn){ if(strpos($fn,$w)!==false){ $hit="Posible contenido prohibido (foto: nombre archivo): $w"; break; } }
      }
      if($hit) break;
    }
    if($hit){
      // registrar incidencia y forzar oculto (aplica a productos y servicios)
      $pdo->prepare("INSERT INTO incidencia (producto_id, origen, descripcion, estado) VALUES (?, 'auto', ?, 'abierta')")->execute([$id, $hit]);
      $pdo->prepare("UPDATE producto SET estado='oculto', actualizado_en=now() WHERE id=?")->execute([$id]); // <-- fuerza oculto
    }

    header("Location: producto_detalle.php?id=".$id); exit;
  }catch(Throwable $e){ debugPF('save fail',['e'=>$e->getMessage()]); $err=urlencode($e->getMessage()); header("Location: producto_form.php".($id?("?id=$id&error=$err"):("?error=$err"))); exit; }
}

$prod=null; $imgs=[];

if($id){
  $s=$pdo->prepare("SELECT * FROM producto WHERE id=? AND vendedor_id=?"); $s->execute([$id,$uid]); $prod=$s->fetch();
  $im=$pdo->prepare("SELECT * FROM producto_imagen WHERE producto_id=? ORDER BY orden"); $im->execute([$id]); $imgs=$im->fetchAll();
}

$cats = $pdo->query("SELECT DISTINCT categoria FROM producto WHERE categoria IS NOT NULL AND categoria<>'' ORDER BY 1 ASC")->fetchAll(PDO::FETCH_COLUMN);
$catsBase = ['Electrónica','Hogar','Servicios','Moda','Deportes','Aficiones','Mascotas','Vehículos','Jardín','Muebles','Computación','Teléfonos','Audio','Belleza','Libros','Juguetes','Herramientas'];
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title><?= $id?'Editar':'Nueva' ?> publicación</title>
<style>
:root{--primary:#2F80ED;--bg:#F6F8FC;--card:#fff;--shadow:0 8px 24px rgba(21,34,50,.08)}
*{box-sizing:border-box}html,body{margin:0}body{font-family:system-ui,Segoe UI,Roboto;background:var(--bg);color:#17212B}
.topbar{position:sticky;top:0;background:#fff;box-shadow:var(--shadow);z-index:20}
.topbar__inner{max-width:1100px;margin:auto;padding:10px 20px;display:flex;align-items:center;gap:10px}
.topbar__inner a{ text-decoration:none; color:#17212B; padding:8px 12px; border-radius:999px; border:1px solid #e6ecf5 }
.topbar__inner .brand{font-weight:800;margin-right:auto}
.container{max-width:900px;margin:16px auto;padding:0 20px}
.card{background:#fff;border-radius:16px;box-shadow:var(--shadow);border:1px solid #eef2f7}
.card h3{margin:0;padding:14px 16px;border-bottom:1px solid #eef2f7}
.section{padding:14px 16px}
label{display:block;margin-bottom:10px;font-weight:600}
input,select,textarea{width:100%;padding:10px 12px;border:1px solid #E0E7F0;border-radius:10px}
.btn{border:0;border-radius:12px;padding:10px 14px;font-weight:700;cursor:pointer}
.btn-primary{background:var(--primary);color:#fff}
.alert{margin:12px 0;padding:10px;border-radius:10px;background:#ffecec;color:#9b1c1c;border:1px solid #ffd4d4}
.grid{display:grid;gap:12px;grid-template-columns:1fr 1fr}
</style>
</head><body>
<header class="topbar">
  <div class="topbar__inner">
    <div class="brand">MarketGO</div>
    <a href="catalogo.php">Catálogo</a>
    <a href="panel.php">Mis publicaciones</a>
    <a href="chat_list.php">Mis chats</a>
  </div>
</header>

<div class="container">
  <div class="card">
    <h3><?= $id?'Editar':'Nueva' ?> publicación</h3>
    <div class="section">
      <?php if(!empty($_GET['error'])): ?><div class="alert"><?= htmlspecialchars($_GET['error']) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
        <div class="grid">
          <label>Código * <input name="codigo" required value="<?= htmlspecialchars($prod['codigo']??'') ?>"></label>
          <label>Tipo *
            <select name="tipo" required>
              <?php $t=$prod['tipo']??'producto'; ?>
              <option value="producto" <?= $t==='producto'?'selected':'' ?>>Producto</option>
              <option value="servicio" <?= $t==='servicio'?'selected':'' ?>>Servicio</option>
            </select>
          </label>
          <label>Nombre * <input name="nombre" required value="<?= htmlspecialchars($prod['nombre']??'') ?>"></label>
          <label>Categoría * 
            <input list="catlist" name="categoria" required value="<?= htmlspecialchars($prod['categoria']??'') ?>">
            <datalist id="catlist">
              <?php foreach($catsBase as $c) echo "<option value=\"".htmlspecialchars($c)."\">"; ?>
              <?php foreach($cats as $c) echo "<option value=\"".htmlspecialchars($c)."\">"; ?>
            </datalist>
          </label>
          <label>Precio * <input type="number" step="0.01" name="precio" required value="<?= htmlspecialchars($prod['precio']??'') ?>"></label>
          <label>Disponibilidad
            <select name="disponibilidad">
              <?php $d=$prod['disponibilidad']??'disponible'; ?>
              <option value="disponible" <?= $d==='disponible'?'selected':'' ?>>Disponible</option>
              <option value="no_disponible" <?= $d==='no_disponible'?'selected':'' ?>>No disponible</option>
              <option value="bajo_pedido" <?= $d==='bajo_pedido'?'selected':'' ?>>Bajo pedido</option>
            </select>
          </label>
          <label>Ciudad <input name="ciudad" value="<?= htmlspecialchars($prod['ciudad']??'') ?>"></label>
          <label>Horario de atención (solo servicios)
            <input name="horario_atencion" value="<?= htmlspecialchars($prod['horario_atencion']??'') ?>">
          </label>
        </div>
        <label>Descripción * <textarea name="descripcion" required><?= htmlspecialchars($prod['descripcion']??'') ?></textarea></label>

        <div class="grid">
          <label>Fotos (hasta 5) <input type="file" name="fotos[]" accept="image/*" multiple></label>
          <div>
            <label>Ubicación
              <div style="display:flex;gap:8px">
                <input id="lat" name="lat" placeholder="lat" value="">
                <input id="lon" name="lon" placeholder="lon" value="">
                <button class="btn" type="button" onclick="getGeo()">📍</button>
              </div>
            </label>
          </div>
        </div>

        <?php if($imgs): ?>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0">
            <?php foreach($imgs as $im): ?>
              <img src="<?= htmlspecialchars($im['url']) ?>" style="width:120px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #eee">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div style="display:flex;gap:8px;justify-content:flex-end">
          <a class="btn" href="panel.php">Cancelar</a>
          <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function getGeo(){
  if(!navigator.geolocation) return alert('Geolocalización no soportada');
  navigator.geolocation.getCurrentPosition(p=>{
    document.getElementById('lat').value=p.coords.latitude;
    document.getElementById('lon').value=p.coords.longitude;
  }, e=>alert('Geo error: '+e.message), {enableHighAccuracy:true,timeout:8000});
}
</script>
</body></html>
