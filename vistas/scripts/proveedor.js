var tabla;

// === Utilidad: debounce ===
function debounce(fn, wait) {
  let t;
  return function() {
    clearTimeout(t);
    const args = arguments, ctx = this;
    t = setTimeout(function(){ fn.apply(ctx, args); }, wait || 400);
  };
}

// === Máscaras y autocompletado (RENIEC / SUNAT) ===
function setupDocMask() {
  const tipo = document.getElementById('tipo_documento').value;
  const ndoc = document.getElementById('num_documento');
  const ayuda = document.getElementById('ayuda_doc');
  const nombre = document.getElementById('nombre');
  const direccion = document.getElementById('direccion');

  if (tipo === 'DNI') {
    ndoc.maxLength = 8;
    ndoc.setAttribute('pattern', '\\d{8}');
    ayuda.textContent = 'DNI: 8 dígitos';
    nombre.readOnly = true;
    direccion.readOnly = true;
  } else if (tipo === 'RUC') {
    ndoc.maxLength = 11;
    ndoc.setAttribute('pattern', '\\d{11}');
    ayuda.textContent = 'RUC: 11 dígitos';
    nombre.readOnly = true;
    direccion.readOnly = true;
  } else { // CÉDULA u otros
    ndoc.removeAttribute('pattern');
    ndoc.maxLength = 20;
    ayuda.textContent = 'Documento libre';
    nombre.readOnly = false;
    direccion.readOnly = false;
  }
}

const consultaDoc = debounce(async function() {
  const tipo = document.getElementById('tipo_documento').value;
  const ndoc = document.getElementById('num_documento').value.trim();
  const nombre = document.getElementById('nombre');
  const direccion = document.getElementById('direccion');

  // Normaliza: solo dígitos en el número
  document.getElementById('num_documento').value = ndoc.replace(/\D+/g, '');

  if (tipo === 'DNI' && /^\d{8}$/.test(ndoc)) {
    nombre.value = 'Consultando RENIEC…';
    direccion.value = '';
    try {
      const url = `../ajax/reniec.php?dni=${encodeURIComponent(ndoc)}`;
      const res = await fetch(url, { headers: {'X-Requested-With':'fetch'}, cache:'no-store' });
      const data = await res.json();
      if (!res.ok || data.success === false) throw new Error(data.message || 'RENIEC respondió con error');
      const nom = [data.nombres, data.apellidos].filter(Boolean).join(' ').trim();
      nombre.value = nom || '';
      if (!nombre.value) nombre.value = '(sin nombre)';
    } catch (e) {
      console.error('RENIEC fail:', e);
      nombre.value = 'No se pudo consultar RENIEC';
    }
  }

  if (tipo === 'RUC' && /^\d{11}$/.test(ndoc)) {
    nombre.value = 'Consultando SUNAT…';
    direccion.value = '';
    try {
      const url = `../ajax/sunat.php?ruc=${encodeURIComponent(ndoc)}`;
      const res = await fetch(url, { headers: {'X-Requested-With':'fetch'}, cache:'no-store' });
      const data = await res.json();
      if (!res.ok || data.success === false) throw new Error(data.message || 'SUNAT respondió con error');
      nombre.value = (data.razon_social || '').trim() || '(sin razón social)';
      direccion.value = (data.direccion || data.domicilio_fiscal || '').trim();
    } catch (e) {
      console.error('SUNAT fail:', e);
      nombre.value = 'No se pudo consultar SUNAT';
      direccion.value = '';
    }
  }
}, 450);

// === Teléfono: solo dígitos y 9 máximo ===
function setupTelefonoRules() {
  const tel = document.getElementById('telefono');
  tel.addEventListener('input', function() {
    this.value = this.value.replace(/\D+/g, '').slice(0, 9);
  });
}

// ====== DataTables y CRUD ======
function init(){
  mostrarform(false);
  listar();

  $("#formulario").on("submit",function(e){
    e.preventDefault();

    const tel = document.getElementById('telefono').value.trim();
    if (!/^\d{9}$/.test(tel)) {
      bootbox.alert('El teléfono debe tener exactamente 9 dígitos.');
      return;
    }
    const tipo = document.getElementById('tipo_documento').value;
    const ndoc = document.getElementById('num_documento').value.trim();
    if (tipo === 'DNI' && !/^\d{8}$/.test(ndoc)) { bootbox.alert('DNI inválido. Debe tener 8 dígitos.'); return; }
    if (tipo === 'RUC' && !/^\d{11}$/.test(ndoc)) { bootbox.alert('RUC inválido. Debe tener 11 dígitos.'); return; }

    guardaryeditar();	
  });

  $('#mCompras').addClass("treeview active");
  $('#lProveedores').addClass("active");

  setupDocMask();
  setupTelefonoRules();

  // disparamos búsqueda con input y blur, y al cambiar el tipo
  document.getElementById('tipo_documento').addEventListener('change', function(){
    setupDocMask();
    consultaDoc();
  });
  document.getElementById('num_documento').addEventListener('input', consultaDoc);
  document.getElementById('num_documento').addEventListener('blur', consultaDoc);
}

function limpiar(){
  $("#nombre").val("");
  $("#num_documento").val("");
  $("#direccion").val("");
  $("#telefono").val("");
  $("#email").val("");
  $("#idpersona").val("");
}

function mostrarform(flag){
  limpiar();
  if (flag){
    $("#listadoregistros").hide();
    $("#formularioregistros").show();
    $("#btnGuardar").prop("disabled",false);
    $("#btnagregar").hide();
  } else {
    $("#listadoregistros").show();
    $("#formularioregistros").hide();
    $("#btnagregar").show();
  }
}

function cancelarform(){ limpiar(); mostrarform(false); }

function listar(){
  tabla=$('#tbllistado').dataTable({
    "lengthMenu": [ 5, 10, 25, 75, 100],
    "aProcessing": true,
    "aServerSide": true,
    dom: '<Bl<f>rtip>',
    buttons: ['copyHtml5','excelHtml5','csvHtml5','pdf'],
    "ajax": {
      url: '../ajax/persona.php?op=listarp',
      type : "get",
      dataType : "json",
      error: function(e){ console.log(e.responseText); }
    },
    "language": {
      "lengthMenu": "Mostrar : _MENU_ registros",
      "buttons": { "copyTitle": "Tabla Copiada",
        "copySuccess": { _: '%d líneas copiadas', 1: '1 línea copiada' } }
    },
    "bDestroy": true,
    "iDisplayLength": 5,
    "order": [[ 0, "desc" ]]
  }).DataTable();
}

function guardaryeditar()
{
  $("#btnGuardar").prop("disabled",true);
  var formData = new FormData($("#formulario")[0]);

  $.ajax({
    url: "../ajax/persona.php?op=guardaryeditar",
    type: "POST",
    data: formData,
    contentType: false,
    processData: false,
    success: function(datos){
      bootbox.alert(datos);
      mostrarform(false);
      tabla.ajax.reload();
    },
    complete: function(){ $("#btnGuardar").prop("disabled",false); }
  });
  limpiar();
}

function mostrar(idpersona)
{
  $.post("../ajax/persona.php?op=mostrar",{idpersona : idpersona}, function(data, status){
    data = JSON.parse(data);		
    mostrarform(true);

    $("#nombre").val(data.nombre);
    $("#tipo_documento").val(data.tipo_documento);
    $("#tipo_documento").selectpicker('refresh');
    $("#num_documento").val(data.num_documento);
    $("#direccion").val(data.direccion);
    $("#telefono").val(data.telefono);
    $("#email").val(data.email);
    $("#idpersona").val(data.idpersona);
  })
}

function eliminar(idpersona)
{
  bootbox.confirm("¿Está Seguro de eliminar el proveedor?", function(result){
    if(result){
      $.post("../ajax/persona.php?op=eliminar", {idpersona : idpersona}, function(e){
        bootbox.alert(e);
        tabla.ajax.reload();
      });	
    }
  })
}

init();
