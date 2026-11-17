# Laboratorio clínico

## Platform
Web local en AppServ, PHP y MySQL. Base independiente de PuntoVenta.

## Alcance confirmado
Reutilizar la arquitectura y el módulo de usuarios de PuntoVenta. Un rol por usuario, roles configurables y cuentas desactivables. Separar administración de accesos y autorización clínica. Contraseñas temporales, cambio obligatorio, bitácora de accesos y operaciones. Resultados con versiones, motivo de corrección, validación y entrega autorizada.

## Etapa actual
Login, usuarios, roles y permisos; catálogos de estudios, analitos, pacientes, médicos, compañías, precios en MXN, tipos de muestra y métodos. Alta, edición, desactivación, búsqueda y paginación. Estudios con analitos ordenados, muestra y método; precio público por estudio y precio opcional por compañía.

Recepción: nuevo ingreso, captura de resultados, entrega autorizada, cotización, orden de trabajo, edición de solicitud, comprobante de solicitud e historial por paciente. Recepción puede capturar resultados pero no validarlos; el responsable del laboratorio valida. Comprobantes y órdenes de trabajo se imprimen desde el navegador. Las solicitudes conservan la configuración y precio histórico de los estudios; las correcciones de resultados crean versiones nuevas y requieren nueva validación.

Caja: turnos personales, fondo inicial, ingresos manuales en efectivo/tarjeta/transferencia, egresos de efectivo con motivo y corte con importe esperado, contado y diferencia. Los movimientos no se editan ni eliminan. Los anticipos y abonos de solicitudes crean un ingreso vinculado en el turno propio; los ingresos manuales son otros movimientos.

Pendientes: referencias estructuradas por edad/sexo, manejo operativo de muestras y envío electrónico de resultados. Se cargaron catálogos de demostración con códigos DEMO-, precios ilustrativos y personas ficticias. No se han cargado pacientes reales ni rangos de referencia.

## Cobros de solicitudes
Anticipos, abonos, liquidaciones, saldo pendiente y recibos por pago. Pago e ingreso de caja se guardan juntos, con prevención de duplicados y sobrepagos. Los pagos se asignan al turno personal abierto, separando efectivo de tarjetas y transferencias. El efectivo recibido y el cambio se distinguen del importe aplicado. Recepción consulta; Caja y Administrador cobran. Los cortes y recibos mantienen datos históricos.

## Selectores de catálogo
Ventana modal con búsqueda, paginación, selección por doble clic o botón Elegir. El alta desde el selector usa los permisos del catálogo y selecciona el registro al guardar. Permite selectores anidados y conserva los datos del formulario original.

## Resultados por analito e informe
Captura numérica, cualitativa y de texto por analito de la solicitud. Guarda borradores parciales y exige completarlos antes de validar. Conserva el esquema, valores y contexto de cada versión. PDF por estudio, solo de la versión vigente validada, con logo, paciente, referencias textuales configuradas, responsable y paginación. Los antiguos resultados de texto libre se conservan. No se generan interpretaciones clínicas ni firmas digitales.

## Identidad y navegación
Menú hamburguesa plegable en escritorio y celular. Administrador configura nombre, logo, colores y datos de contacto del laboratorio. La identidad se refleja en el acceso y los documentos; los nuevos documentos conservan su identidad histórica.

## Decisiones abiertas
Nombre comercial, identidad gráfica, sucursales y catálogo de estudios.

## Cancelaciones y devoluciones
En **Caja → Cancelaciones y devoluciones** se revisan las peticiones por estado, con paginación. Desde una solicitud o **Cobros de solicitudes** se pide la cancelación o la devolución parcial/total de un pago, con motivo obligatorio. Recepción y Caja pueden solicitar; solo una cuenta con `autorizar_anulaciones`, `administrar_accesos` y `ver_cobros` puede aprobar o rechazar desde su propia sesión, también con motivo. No se comparte la contraseña del administrador.

Autorizar una cancelación marca la solicitud como cancelada, conserva sus documentos e historial, elimina el saldo por cobrar y bloquea edición, nuevos cobros, captura, corrección, validación, entrega y generación de informes de resultados. Las impresiones de la solicitud llevan aviso de cancelación. La cancelación no entrega dinero: muestra el importe por devolver y las devoluciones se solicitan por separado. También se puede cancelar una cotización desde su detalle.

Una devolución aprobada la registra Caja o Administrador con `devolver_pagos`, `ver_cobros`, `operar_caja` y `registrar_egresos`, en su propio turno abierto, confirmando la entrega. Se conserva el medio del pago original: efectivo reduce el efectivo esperado; tarjeta/transferencia suma egresos no efectivos sin alterar ingresos brutos. El sistema registra la devolución; las reversiones bancarias se realizan por los canales del laboratorio. Si falta efectivo, se bloquea la devolución hasta disponer de fondo suficiente. No se modifican el pago original ni sus cortes cerrados.

Las peticiones pendientes y aprobadas reservan el importe del pago. Rechazarlas libera esa reserva. Los bloqueos transaccionales impiden exceder el pago con peticiones simultáneas; reintentar la ejecución no duplica el egreso, incluso tras cerrar el turno. Se conserva solicitante, autorizador, ejecutor, motivos, fechas, pago y movimiento. El recibo de devolución guarda identidad del laboratorio y responsable al ejecutarse. En solicitudes vigentes, devolver vuelve a generar saldo pendiente; en canceladas se conserva saldo por cobrar cero y disminuye lo pendiente por devolver.
