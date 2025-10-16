<?php
// ======= arranque de sesión + guardas =======
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

// Si NO hay login válido, redirige
if (empty($_SESSION['idusuario'])) {
  header('Location: ../login.php');
  exit;
}

// Valores seguros para evitar notices
$sesNombre = htmlspecialchars($_SESSION['nombre'] ?? 'Usuario', ENT_QUOTES, 'UTF-8');
$sesImagen = $_SESSION['imagen'] ?? 'default.png'; // pon un default en /files/usuarios/default.png
$sesImagen = htmlspecialchars($sesImagen, ENT_QUOTES, 'UTF-8');

// Helper: devuelve true si el flag está activo (1)
function flag($k){ return !empty($_SESSION[$k]) && (int)$_SESSION[$k] === 1; }
?>
<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>MiniMarket | www.crisdava.com</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <!-- CSS -->
    <link rel="stylesheet" href="../public/css/bootstrap.min.css">
    <link rel="stylesheet" href="../public/css/font-awesome.css">
    <link rel="stylesheet" href="../public/css/AdminLTE.min.css">
    <link rel="stylesheet" href="../public/css/_all-skins.min.css">
    <link rel="apple-touch-icon" href="../public/img/apple-touch-icon.png">
    <link rel="shortcut icon" href="../public/img/favicon.ico">

    <!-- DATATABLES -->
    <link rel="stylesheet" href="../public/datatables/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../public/datatables/buttons.dataTables.min.css"/>
    <link rel="stylesheet" href="../public/datatables/responsive.dataTables.min.css"/>

    <link rel="stylesheet" href="../public/css/bootstrap-select.min.css">
  </head>
  <body class="hold-transition skin-yellow sidebar-mini">
    <div class="wrapper">

      <header class="main-header">
        <a href="escritorio.php" class="logo">
          <span class="logo-mini"><b>AD</b>Ventas</span>
          <span class="logo-lg"><b>Ferretería Neko</b></span>
        </a>

        <nav class="navbar navbar-static-top" role="navigation">
          <a href="#" class="sidebar-toggle" data-toggle="offcanvas" role="button">
            <span class="sr-only">Navegación</span>
          </a>
          <div class="navbar-custom-menu">
            <ul class="nav navbar-nav">
              <li class="dropdown user user-menu">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                  <img src="../files/usuarios/<?= $sesImagen ?>" class="user-image" alt="User Image">
                  <span class="hidden-xs"><?= $sesNombre ?></span>
                </a>
                <ul class="dropdown-menu">
                  <li class="user-header">
                    <img src="../files/usuarios/<?= $sesImagen ?>" class="img-circle" alt="User Image">
                    <p>
                      www.crisdava.com - Desarrollando Software
                      <small>https://www.youtube.com/@CristianDavila2002</small>
                    </p>
                  </li>
                  <li class="user-footer">
                    <div class="pull-right">
                      <a href="../ajax/usuario.php?op=salir" class="btn btn-default btn-flat">Cerrar</a>
                    </div>
                  </li>
                </ul>
              </li>
            </ul>
          </div>
        </nav>
      </header>

      <aside class="main-sidebar">
        <section class="sidebar">
          <ul class="sidebar-menu">
            <li class="header"></li>

            <?php if (flag('escritorio')): ?>
              <li id="mEscritorio"><a href="escritorio.php"><i class="fa fa-tasks"></i> <span>Escritorio</span></a></li>
            <?php endif; ?>

            <?php if (flag('almacen')): ?>
              <li id="mAlmacen" class="treeview">
                <a href="#"><i class="fa fa-laptop"></i><span>Almacén</span><i class="fa fa-angle-left pull-right"></i></a>
                <ul class="treeview-menu">
                  <li id="lArticulos"><a href="articulo.php"><i class="fa fa-circle-o"></i> Artículos</a></li>
                  <li id="lCategorias"><a href="categoria.php"><i class="fa fa-circle-o"></i> Categorías</a></li>
                </ul>
              </li>
            <?php endif; ?>

            <?php if (flag('compras')): ?>
              <li id="mCompras" class="treeview">
                <a href="#"><i class="fa fa-th"></i><span>Compras</span><i class="fa fa-angle-left pull-right"></i></a>
                <ul class="treeview-menu">
                  <li id="lIngresos"><a href="ingreso.php"><i class="fa fa-circle-o"></i> Ingresos</a></li>
                  <li id="lProveedores"><a href="proveedor.php"><i class="fa fa-circle-o"></i> Proveedores</a></li>
                </ul>
              </li>
            <?php endif; ?>

            <?php if (flag('ventas')): ?>
              <li id="mVentas" class="treeview">
                <a href="#"><i class="fa fa-shopping-cart"></i><span>Ventas</span><i class="fa fa-angle-left pull-right"></i></a>
                <ul class="treeview-menu">
                  <li id="lVentas"><a href="venta.php"><i class="fa fa-circle-o"></i> Ventas</a></li>
                  <li id="lClientes"><a href="cliente.php"><i class="fa fa-circle-o"></i> Clientes</a></li>
                </ul>
              </li>
            <?php endif; ?>

            <?php if (flag('acceso')): ?>
              <li id="mAcceso" class="treeview">
                <a href="#"><i class="fa fa-folder"></i> <span>Acceso</span><i class="fa fa-angle-left pull-right"></i></a>
                <ul class="treeview-menu">
                  <li id="lUsuarios"><a href="usuario.php"><i class="fa fa-circle-o"></i> Usuarios</a></li>
                  <li id="lPermisos"><a href="permiso.php"><i class="fa fa-circle-o"></i> Permisos</a></li>
                </ul>
              </li>
            <?php endif; ?>

            <?php if (flag('consultac')): ?>
              <li id="mConsultaC" class="treeview">
                <a href="#"><i class="fa fa-bar-chart"></i><span>Consulta Compras</span><i class="fa fa-angle-left pull-right"></i></a>
                <ul class="treeview-menu">
                  <li id="lConsulasC"><a href="comprasfecha.php"><i class="fa fa-circle-o"></i> Consulta Compras</a></li>
                </ul>
              </li>
            <?php endif; ?>

            <?php if (flag('consultav')): ?>
              <li id="mConsultaV" class="treeview">
                <a href="#"><i class="fa fa-bar-chart"></i><span>Consulta Ventas</span><i class="fa fa-angle-left pull-right"></i></a>
                <ul class="treeview-menu">
                  <li id="lConsulasV"><a href="ventasfechacliente.php"><i class="fa fa-circle-o"></i> Consulta Ventas</a></li>
                </ul>
              </li>
            <?php endif; ?>

          </ul>
        </section>
      </aside>
