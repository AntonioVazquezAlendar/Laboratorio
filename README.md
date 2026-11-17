# Laboratorio clínico

Aplicación PHP 7.3 compatible, MySQL y AppServ. Arquitectura y autenticación adaptadas de PuntoVenta. Base independiente `laboratorio_clinico`.

## Abrir

http://localhost:8080/Laboratorio/public/

En instalaciones nuevas normales, la primera cuenta se crea desde el navegador del mismo servidor. Este equipo ya tiene el administrador de pruebas solicitado por el usuario. `scripts/instalar.php` prepara la estructura sin crear cuentas. El instalador de demostración de la raíz sí crea las cuentas de prueba descritas a continuación.

## Instalar una demostración en otra máquina

1. Instala PHP de 64 bits (7.3 o posterior), MySQL 8.0 o posterior y Apache/AppServ. Habilita `pdo_mysql` y `curl` en el PHP de Apache; GD permite incluir logos WebP. MariaDB no está contemplado por este instalador.
2. Copia **todo el proyecto** a `C:\AppServ\www\Laboratorio`. Conserva el nombre `Laboratorio`, utilizado por las rutas de la interfaz. Puedes omitir `.git`, el archivo `app/Config/database.local.php` y los archivos de ejecución de `storage`; el instalador prepara las carpetas necesarias.
3. Con MySQL iniciado, haz doble clic en **INSTALAR_PRUEBAS.cmd**, en la raíz. Pide servidor, usuario y contraseña de MySQL; la contraseña se escribe oculta. La cuenta MySQL necesita permisos para crear bases y tablas.
4. Crea `laboratorio_pruebas` y guarda la conexión en `app/Config/database.local.php`, incluyendo el nombre de la nueva base. No importa datos de la base del equipo de origen.
5. Abre `http://localhost/Laboratorio/public/`, o `http://localhost:8080/Laboratorio/public/` si Apache usa ese puerto. Aplica la protección Apache indicada en este documento para impedir el acceso HTTP a carpetas internas.

También puedes ejecutarlo desde PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\INSTALAR_PRUEBAS.ps1
# Para otra instalación de PHP o un nombre de base diferente:
powershell -NoProfile -ExecutionPolicy Bypass -File .\INSTALAR_PRUEBAS.ps1 -Php "D:\AppServ\php8\php.exe" -BaseDeDatos laboratorio_pruebas_2
```

| Usuario | Rol | Contraseña temporal inicial |
|---|---|---|
| `admin` | Administrador | `Admin1.1` |
| `recepcion` | Recepción | `Admin1.1` |
| `caja` | Caja | `Admin1.1` |
| `laboratorista` | Laboratorista | `Admin1.1` |
| `responsable` | Responsable del laboratorio | `Admin1.1` |

Todos deben cambiar su contraseña temporal al iniciar sesión, incluido el administrador. Caja puede cobrar y ejecutar devoluciones autorizadas; el administrador las autoriza; el responsable valida resultados.

La base incluye los catálogos ficticios del proyecto (30 estudios, 57 analitos, 10 pacientes, 5 médicos, 3 compañías, 6 muestras, 7 métodos y 120 precios), dos solicitudes y una cotización. No se generan cobros, devoluciones ni resultados clínicos al instalar.

Si se repite, una instalación terminada conserva sus registros y contraseñas. Si la base ya existe sin la marca de este instalador, se detiene antes de modificarla; usa otro nombre. Una carga interrumpida puede reanudarse en su propia base marcada. La prueba automatizada `python tests/instalacion_pruebas.py` usa una copia y bases temporales, y comprueba que la conexión de este proyecto permanezca intacta.

Para uso por consola sin el asistente de Windows, `instalar_pruebas.php` admite las variables `LAB_DB_HOST`, `LAB_DB_USER`, `LAB_DB_PASSWORD` y `LAB_DB_NAME`. `--sin-configurar` prepara la demostración sin cambiar el archivo de conexión del proyecto. No publiques estos instaladores por HTTP; la raíz debe conservar sus reglas de acceso.

## Alternativa: importar directamente con HeidiSQL

El archivo **LABORATORIO_PRUEBAS_HEIDISQL.sql**, en la raíz, contiene la estructura completa y la misma demostración del instalador anterior, con los cinco usuarios y contraseña temporal `Admin1.1`. Se generó a partir de una base temporal nueva; no contiene registros de la base operativa de origen.

1. Conéctate desde HeidiSQL a un servidor **MySQL 8.0 o posterior**, con permiso de crear bases y tablas.
2. Abre `LABORATORIO_PRUEBAS_HEIDISQL.sql` mediante **Archivo → Cargar archivo SQL**, usando UTF-8.
3. Ejecuta el archivo completo con **F9**. Crea y selecciona `laboratorio_pruebas`; no es necesario crearla previamente. Si ya existe, detente en el primer error. No actives continuar/ignorar errores; para otra instalación cambia las líneas `CREATE DATABASE` y `USE` a un nombre nuevo que comience con `laboratorio_`.
4. El SQL prepara MySQL; la aplicación necesita apuntar a esa base. En el equipo destino, crea o ajusta `app/Config/database.local.php` con las credenciales locales:

```php
<?php
return array(
    'host' => '127.0.0.1',
    'username' => 'TU_USUARIO_MYSQL',
    'password' => 'TU_CONTRASENA_MYSQL',
    'database' => 'laboratorio_pruebas'
);
```

Después abre la aplicación e inicia sesión con cualquiera de las cinco cuentas anteriores. La contraseña de MySQL es independiente de la contraseña `Admin1.1` de los usuarios del laboratorio. `python tests/sql_heidisql.py` verifica la importación en una base aislada. `python scripts/generar_sql_pruebas.py` regenera el archivo desde una demostración temporal, conservando la conexión del proyecto.

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
- Los anticipos y abonos se registran desde Cobros de solicitudes y actualizan saldo y caja juntos. El comprobante de solicitud no acredita un pago; cada pago tiene su propio recibo.

Las migraciones son repetibles. Los permisos nuevos se incorporan una vez a los roles iniciales y los roles personalizados los reciben desactivados. El administrador puede gestionar catálogos y solicitudes, pero conserva la separación de permisos clínicos: no valida resultados por ser administrador. El usuario de pruebas `admin` ya existe; hay pacientes ficticios de demostración y no hay movimientos reales precargados.

Pendientes: referencias estructuradas por edad/sexo, operación de muestras y envío electrónico.

## Anticipos, abonos y saldos

**Caja → Cobros de solicitudes** permite buscar por folio, expediente o paciente, filtrar pendientes/pagadas y consultar total, pagado, saldo e historial. Recepción ve los saldos desde la solicitud y puede consultar los recibos; registrar cobros requiere ver_cobros, cobrar, operar_caja y registrar_ingresos. Los roles iniciales Caja y Administrador pueden cobrar. Recepción recibe consulta financiera, sin permisos de operación de caja adicionales.

Para cobrar, abre un turno personal y selecciona una solicitud registrada. Se registra un importe positivo hasta el saldo pendiente, con efectivo, tarjeta o transferencia. El primer pago parcial es anticipo, los siguientes son abonos y el que cubre el saldo es liquidación. Las cotizaciones deben convertirse en solicitud antes de cobrar.

El efectivo recibido puede ser mayor al importe aplicado; el cambio se calcula y solo el importe aplicado entra al efectivo esperado. Tarjeta y transferencia se registran en el turno y en el total de cobros vinculados, sin sumarse al efectivo físico.

El pago, su único movimiento de ingreso, el saldo y las versiones de solicitud/turno se guardan en una sola transacción. Una clave por intento evita cobros duplicados, incluso al reintentar tras cerrar el turno. Se rechazan cambios de versión, sobrepagos y el uso de turnos ajenos o cerrados. Los recibos conservan folio, paciente, responsable, identidad del laboratorio, importe, método, cambio y saldo al momento del pago. Se imprimen desde el historial de pagos.

Una solicitud con cobros no cambia de paciente ni reduce su total por debajo de lo pagado. Agregar estudios puede generar un saldo adicional. Los cortes cerrados mantienen su resumen, aunque después se reciba otro abono en un turno nuevo.

Los ingresos manuales anteriores se conservan y no se asignan automáticamente por texto a una solicitud. Los pagos no se editan ni se borran; las devoluciones se registran mediante peticiones autorizadas y egresos vinculados.

La prueba de integración incluye dos procesos PHP concurrentes para demostrar que no se cobra más que el saldo y que cada pago genera un solo ingreso.

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

## Cancelaciones y devoluciones
En **Caja → Cancelaciones y devoluciones** se revisan las peticiones por estado, con paginación. Desde una solicitud o **Cobros de solicitudes** se pide la cancelación o la devolución parcial/total de un pago, con motivo obligatorio. Recepción y Caja pueden solicitar; solo una cuenta con `autorizar_anulaciones`, `administrar_accesos` y `ver_cobros` puede aprobar o rechazar desde su propia sesión, también con motivo. No se comparte la contraseña del administrador.

Autorizar una cancelación marca la solicitud como cancelada, conserva sus documentos e historial, elimina el saldo por cobrar y bloquea edición, nuevos cobros, captura, corrección, validación, entrega y generación de informes de resultados. Las impresiones de la solicitud llevan aviso de cancelación. La cancelación no entrega dinero: muestra el importe por devolver y las devoluciones se solicitan por separado. También se puede cancelar una cotización desde su detalle.

Una devolución aprobada la registra Caja o Administrador con `devolver_pagos`, `ver_cobros`, `operar_caja` y `registrar_egresos`, en su propio turno abierto, confirmando la entrega. Se conserva el medio del pago original: efectivo reduce el efectivo esperado; tarjeta/transferencia suma egresos no efectivos sin alterar ingresos brutos. El sistema registra la devolución; las reversiones bancarias se realizan por los canales del laboratorio. Si falta efectivo, se bloquea la devolución hasta disponer de fondo suficiente. No se modifican el pago original ni sus cortes cerrados.

Las peticiones pendientes y aprobadas reservan el importe del pago. Rechazarlas libera esa reserva. Los bloqueos transaccionales impiden exceder el pago con peticiones simultáneas; reintentar la ejecución no duplica el egreso, incluso tras cerrar el turno. Se conserva solicitante, autorizador, ejecutor, motivos, fechas, pago y movimiento. El recibo de devolución guarda identidad del laboratorio y responsable al ejecutarse. En solicitudes vigentes, devolver vuelve a generar saldo pendiente; en canceladas se conserva saldo por cobrar cero y disminuye lo pendiente por devolver.
