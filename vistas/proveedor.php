<?php
// vistas/proveedor.php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/_requires_auth.php'; // redirige a ../login.php si no hay sesión
require 'header.php';

$canCompras = !empty($_SESSION['compras']) && (int)$_SESSION['compras'] === 1;
?>

<?php if ($canCompras): ?>
<!--Contenido-->
<div class="content-wrapper">
  <!-- Main content -->
  <section class="content">
    <div class="row">
      <div class="col-md-12">
        <div class="box">
          <div class="box-header with-border">
            <h1 class="box-title">
              Proveedor
              <button class="btn btn-success" id="btnagregar" onclick="mostrarform(true)">
                <i class="fa fa-plus-circle"></i> Agregar
              </button>
              <a href="../reportes/rptproveedores.php" target="_blank">
                <button class="btn btn-info"><i class="fa fa-clipboard"></i> Reporte</button>
              </a>
            </h1>
            <div class="box-tools pull-right"></div>
          </div>

          <!-- LISTADO -->
          <div class="panel-body table-responsive" id="listadoregistros">
            <table id="tbllistado" class="table table-striped table-bordered table-condensed table-hover">
              <thead>
                <th>Opciones</th>
                <th>Nombre</th>
                <th>Documento</th>
                <th>Número</th>
                <th>Teléfono</th>
                <th>Email</th>
              </thead>
              <tbody></tbody>
              <tfoot>
                <th>Opciones</th>
                <th>Nombre</th>
                <th>Documento</th>
                <th>Número</th>
                <th>Teléfono</th>
                <th>Email</th>
              </tfoot>
            </table>
          </div>

          <!-- FORMULARIO -->
          <div class="panel-body" style="height: 100%;" id="formularioregistros">
            <form name="formulario" id="formulario" method="POST" autocomplete="off">
              <input type="hidden" name="idpersona" id="idpersona">
              <input type="hidden" name="tipo_persona" id="tipo_persona" value="Proveedor">

              <!-- Fila 1: Tipo Doc + Nro Doc (lado a lado) -->
              <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <label>Tipo Documento:</label>
                <select class="form-control selectpicker" name="tipo_documento" id="tipo_documento" required>
                  <option value="RUC">RUC</option>
                </select>
              </div>

              <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <label>Número Documento:</label>
                <input type="text" class="form-control" name="num_documento" id="num_documento"
                       placeholder="Documento" required>
                <small id="ayuda_doc" class="text-muted">RUC: 11 dígitos</small>
              </div>

              <!-- Fila 2: Nombre (autocompletado) + Dirección (autocompletada) -->
              <div class="form-group col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <label>Nombre (autocompletado):</label>
                <input type="text" class="form-control" name="nombre" id="nombre"
                       placeholder="Nombre del proveedor" readonly required>
              </div>

              <div class="form-group col-lg-6 col-md-6 col-sm-12 col-xs-12">
                <label>Dirección (autocompletada):</label>
                <input type="text" class="form-control" name="direccion" id="direccion"
                       placeholder="Dirección" readonly>
              </div>

              <!-- Fila 3: Teléfono + Email -->
              <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <label>Teléfono:</label>
                <input type="text" class="form-control" name="telefono" id="telefono"
                       placeholder="Teléfono (9 dígitos)" maxlength="9" inputmode="numeric" pattern="\d{9}" required>
                <small class="text-muted">Solo números, exactamente 9 dígitos.</small>
              </div>

              <div class="form-group col-lg-3 col-md-3 col-sm-6 col-xs-12">
                <label>Email:</label>
                <input type="email" class="form-control" name="email" id="email" maxlength="50" placeholder="Email">
              </div>

              <div class="form-group col-lg-12 col-md-12 col-sm-12 col-xs-12">
                <button class="btn btn-primary" type="submit" id="btnGuardar">
                  <i class="fa fa-save"></i> Guardar
                </button>
                <button class="btn btn-danger" onclick="cancelarform()" type="button">
                  <i class="fa fa-arrow-circle-left"></i> Cancelar
                </button>
              </div>
            </form>
          </div>
          <!-- /FORMULARIO -->

        </div><!-- /.box -->
      </div><!-- /.col -->
    </div><!-- /.row -->
  </section><!-- /.content -->
</div><!-- /.content-wrapper -->
<!--Fin-Contenido-->
<?php else: ?>
  <?php require 'noacceso.php'; ?>
<?php endif; ?>

<?php
require 'footer.php';
?>
<script type="text/javascript" src="scripts/proveedor.js"></script>
<?php
ob_end_flush();
