# Laboratorio clínico

Aplicación PHP 7.3 compatible, MySQL y AppServ. Arquitectura y autenticación adaptadas de PuntoVenta. Base independiente `laboratorio_clinico`.

## Abrir

http://localhost:8080/Laboratorio/public/

En instalaciones nuevas, la primera cuenta se crea desde el navegador del mismo servidor. Este equipo ya tiene el administrador de pruebas solicitado por el usuario. El instalador no crea cuentas ni contraseñas predeterminadas.

## Funciones de esta etapa

- Usuarios: alta, edición, desactivación, rol y restablecimiento de contraseña temporal.
- Roles: creación y edición con permisos explícitos. Un rol por usuario.
- Administrador: acceso a usuarios y bitácora, sin autorización clínica implícita.
- Contraseña temporal: cambio obligatorio antes de operar; el restablecimiento invalida las sesiones abiertas.
- Contraseña personal: cambio con comprobación de la actual; las demás sesiones se invalidan.
- Autenticación heredada: bcrypt, cookies HttpOnly/SameSite, CSRF, cinco intentos por IP cada 15 minutos y 30 minutos de inactividad.
- Bitácora paginada: accesos correctos/fallidos, cierre de sesión, cambios de usuarios y roles, consultas y operaciones de resultados. No guarda contraseñas ni hashes.
- Resultados por analito asociados a solicitud y estudio: captura numérica, cualitativa y de texto, borradores parciales, versiones con motivo, validación e informe PDF de la versión autorizada. Corregir vuelve el documento a borrador. Validaciones y entregas anteriores se conservan por versión.
- Recepción solo consulta resultados vigentes validados; los borradores requieren funciones clínicas de captura, revisión o corrección.

## Catálogos y operación

- Estudios, analitos, pacientes, médicos, compañías, precios, tipos de muestra y métodos: alta, edición, búsqueda, filtros, paginación y desactivación. Control de edición concurrente por versión.
- Estudios: muestra, método y lista ordenada de analitos. Cada analito permite unidad, tipo de resultado, decimales y texto de referencia configurado por el laboratorio. No se precargan rangos clínicos.
- Precios: MXN con dos decimales. Un precio público y un precio opcional por compañía para cada estudio; sin convenio se toma el precio público. Se rechazan duplicados y precios negativos.
- Pacientes: expediente automático opcional, nombres, apellidos, nacimiento, sexo registrado, contacto, médico y compañía. Los datos personales no se copian en el detalle de la bitácora.
- Recepción: solicitudes, cotizaciones convertibles, historial de paciente, comprobantes y órdenes de trabajo imprimibles. Los estudios conservan su configuración y precio histórico. La edición conserva los precios de estudios sin cambios salvo cambio de compañía; no permite cambiar paciente o retirar estudios con resultados.
- Resultados: captura enlazada a solicitud/estudio y consulta de versiones. Recepción captura; el responsable valida. La entrega requiere validación. Las nuevas capturas usan un campo por analito y requieren solicitud y estudio. Los resultados históricos de texto libre conservan su formato y versiones.
- Caja: turno personal, fondo inicial, ingresos por efectivo/tarjeta/transferencia, egresos en efectivo con motivo y corte imprimible. El esperado se calcula como fondo + ingresos de efectivo − egresos. Tarjetas y transferencias se muestran por separado. Un corte con diferencia exige nota y los movimientos cerrados quedan guardados.
- Los ingresos se registran manualmente; la referencia de solicitud no aplica automáticamente un pago ni calcula adeudos. El comprobante de solicitud no acredita un pago.

Las migraciones son repetibles. Los permisos nuevos se incorporan una vez a los roles iniciales y los roles personalizados los reciben desactivados. El administrador puede gestionar catálogos y solicitudes, pero conserva la separación de permisos clínicos: no valida resultados por ser administrador. El usuario de pruebas `admin` ya existe; hay pacientes ficticios de demostración y no hay movimientos reales precargados.

Pendientes: integración de cobros con solicitudes, cancelaciones, reembolsos, referencias estructuradas por edad/sexo, operación de muestras y envío electrónico.

## Selección de catálogos en ventana

Los campos relacionados abren una ventana con búsqueda por código o nombre, lista de registros activos y páginas de 25 opciones. Se puede seleccionar por doble clic, por el botón Elegir de una fila o seleccionando una fila y pulsando Elegir seleccionado. También admite flechas y Enter; Escape o Cancelar cierran sin modificar la selección actual.

El selector muestra los detalles permitidos por el rol, como nacimiento o teléfono del paciente. Quien solo tenga acceso al selector recibe código y nombre, sin datos personales adicionales. La búsqueda de pacientes y médicos admite nombre y apellidos juntos.

Si el usuario puede editar ese catálogo, aparece Crear. El alta se realiza dentro de la misma ventana con el formulario completo; Guardar y seleccionar crea un registro activo y lo asigna al campo de origen. Los errores mantienen el formulario para corregirlos. Se pueden abrir selectores anidados, por ejemplo para crear un médico durante el alta de un paciente, sin perder los datos de la solicitud.

Agregar estudio y Agregar analito utilizan el mismo selector. Los registros ya agregados se identifican y no se vuelven a añadir. Los permisos de creación y los datos elegibles se comprueban en el servidor.

## Captura por analito e informes PDF

En Recepción → Captura de resultados, abre una solicitud registrada y elige Capturar resultado en su estudio. Los campos siguen el orden, tipos, unidades, decimales, método y texto de referencia guardados en la solicitud. El texto de referencia se configura en Catálogos → Analitos; no se calcula automáticamente ni se interpreta el resultado.

Puedes guardar valores pendientes como borrador. Para validar, todos los analitos deben tener un valor. Los números admiten signo, punto o coma decimal y los decimales configurados; no se redondean silenciosamente. Las correcciones crean una versión nueva y exigen motivo y nueva validación. Las unidades y referencias no cambian al modificar posteriormente el catálogo.

El responsable valida desde Resultados. La versión vigente validada muestra **Descargar informe PDF**; también puede descargarlo quien tenga permiso de consulta. La descarga se registra en bitácora y no registra automáticamente la entrega. Los borradores, versiones sustituidas y resultados históricos sin datos estructurados no emiten el nuevo informe.

El PDF es por estudio e incluye logo e identidad históricos del laboratorio, paciente, expediente, nacimiento, sexo registrado, médico, muestra, método, analitos, valores, unidades, referencias, observaciones, capturista, validador, fechas, versión y paginación. El nombre del validador no constituye una firma digital. Si una unidad o referencia no está configurada, se indica expresamente. Los antiguos resultados de texto libre siguen consultables.

La generación se hace localmente en PHP con TCPDF 6.11.3, incluido bajo app/ThirdParty/tcpdf con su licencia y fuentes Unicode DejaVu Sans. No se envían datos a servicios externos ni se guardan PDFs clínicos en carpetas públicas. Requiere PHP cURL; GD permite incorporar logos WebP. El entorno Apache actual tiene ambas extensiones habilitadas. El documento solo se entrega tras comprobar sesión, permisos, estado y versión.

Para revisar la paginación con datos ficticios:

```powershell
php -d extension_dir=C:\AppServ\php7\ext -d extension=php_curl.dll tests/pdf_layout.php
```

La prueba HTTP utiliza el Python empaquetado con Codex para inspeccionar el PDF. Puede indicarse otra ruta compatible mediante LAB_PDF_PYTHON. El archivo output/pdf/informe_demo_validado.pdf es una muestra ficticia de verificación, no un informe de un paciente real.

## Menú e identidad

El botón Menú permite mostrar y ocultar la navegación en escritorio. En celular abre un panel lateral que se cierra al elegir una sección, pulsar fuera o usar Escape; conserva el foco del teclado dentro del panel.

El administrador encuentra **Sistema → Identidad del laboratorio**. Puede cambiar nombre comercial, razón social, RFC, contacto, dirección, logo PNG/JPG/WebP (hasta 2 MB y 4096 × 4096), tres colores y mensajes de encabezado/pie. La vista previa no cambia la configuración hasta guardar. El color del texto se calcula automáticamente para fondos claros u oscuros.

La identidad se aplica al encabezado, login, navegación y documentos nuevos. Las solicitudes y los cortes nuevos guardan una copia de los datos del laboratorio para conservarlos en sus impresiones históricas. Los logos anteriores se mantienen disponibles para esos documentos. La configuración se guarda en la tabla empresa y los logos en storage/uploads/logos, accesibles únicamente por la ruta pública controlada de imagen.

## Base y configuración

`app/Config/database.local.php` contiene la conexión local de MySQL y se excluye de Git. Reutiliza la conexión local de AppServ, pero nunca selecciona la base de PuntoVenta. Puede sobrescribirse con LAB_DB_HOST, LAB_DB_USER, LAB_DB_PASSWORD y LAB_DB_NAME (esta última debe comenzar por laboratorio_).

Instalación repetible, sin borrar ni reemplazar datos o permisos existentes:

```powershell
php -d extension_dir=C:\AppServ\php7\ext -d extension=php_pdo_mysql.dll scripts/instalar.php
```

Solo public debe ser accesible por HTTP. En este equipo se añadió al final de `C:/AppServ/Apache24/conf/httpd.conf`:

```apache
<Directory "C:/AppServ/www/Laboratorio">
    AllowOverride FileInfo Options AuthConfig
</Directory>
```

La configuración pasó `httpd.exe -t` y el servicio Apache24 se reinició con elevación. La comprobación HTTP confirmó que las rutas internas devuelven 403 y que el acceso público sigue disponible. Para repetir la comprobación:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/comprobar_servidor.ps1
```

La interfaz usa `public/index.php?api=...` para funcionar también sin reescritura de rutas. Cuando las reglas Apache estén activas, la API admite además `public/api/...`.

## Verificación

```powershell
python tests/acceso_http.py
node --check public/assets/js/admin.js
```

La prueba HTTP crea una base aleatoria laboratorio_test_*, inicia un servidor local temporal y elimina únicamente esa base al terminar. No crea cuentas ni consume intentos en la base real. Cubre permisos, login y contraseñas temporales, catálogos y relaciones, validaciones, tarifas e importes, snapshots históricos, recepción, resultados enlazados, Caja y corte, migraciones repetibles, auditoría y bloqueo por intentos fallidos.

## Datos de demostración

Se cargaron 30 estudios, 57 analitos, 6 tipos de muestra, 7 métodos, 3 compañías ficticias, 5 médicos ficticios, 10 pacientes ficticios y 120 precios ilustrativos en MXN (público y tres convenios por estudio).

Los códigos comienzan por DEMO- y los nombres identifican los ejemplos. No son pacientes reales ni tarifas comerciales recomendadas. Unidades, decimales, métodos, composición de perfiles y requisitos de muestra requieren configuración del laboratorio; no se cargan rangos de referencia ni resultados clínicos. Las asociaciones de métodos usan Pendiente de configurar (Demo).

La carga es voluntaria y repetible; conserva los registros existentes, incluidas las modificaciones que se hagan a los ejemplos, sin crear solicitudes ni movimientos de caja:

```powershell
php -d extension_dir=C:\AppServ\php7\ext -d extension=php_pdo_mysql.dll scripts/cargar_demo.php
```

El manifiesto está en scripts/datos_demo.json. Se consultaron como referencia informativa los catálogos de [biometría hemática](https://www.mayocliniclabs.com/test-catalog/Overview/42120) y [TSH](https://prd1.mayocliniclabs.com/test-catalog/Overview/800096) de Mayo Clinic Laboratories; no se importaron sus procedimientos, volúmenes ni valores de referencia.
