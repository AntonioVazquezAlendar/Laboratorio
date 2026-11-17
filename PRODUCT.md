# Laboratorio clínico

## Platform
Web local en AppServ, PHP y MySQL. Base independiente de PuntoVenta.

## Alcance confirmado
Reutilizar la arquitectura y el módulo de usuarios de PuntoVenta. Un rol por usuario, roles configurables y cuentas desactivables. Separar administración de accesos y autorización clínica. Contraseñas temporales, cambio obligatorio, bitácora de accesos y operaciones. Resultados con versiones, motivo de corrección, validación y entrega autorizada.

## Etapa actual
Login, usuarios, roles y permisos; catálogos de estudios, analitos, pacientes, médicos, compañías, precios en MXN, tipos de muestra y métodos. Alta, edición, desactivación, búsqueda y paginación. Estudios con analitos ordenados, muestra y método; precio público por estudio y precio opcional por compañía.

Recepción: nuevo ingreso, captura de resultados, entrega autorizada, cotización, orden de trabajo, edición de solicitud, comprobante de solicitud e historial por paciente. Recepción puede capturar resultados pero no validarlos; el responsable del laboratorio valida. Comprobantes y órdenes de trabajo se imprimen desde el navegador. Las solicitudes conservan la configuración y precio histórico de los estudios; las correcciones de resultados crean versiones nuevas y requieren nueva validación.

Caja: turnos personales, fondo inicial, ingresos manuales en efectivo/tarjeta/transferencia, egresos de efectivo con motivo y corte con importe esperado, contado y diferencia. Los movimientos no se editan ni eliminan. La referencia de solicitud en un movimiento no aplica automáticamente pagos o saldos a solicitudes.

Pendientes: vinculación automática de cobros y saldos, cancelaciones/reembolsos, referencias estructuradas por edad/sexo, manejo operativo de muestras y envío electrónico de resultados. Se cargaron catálogos de demostración con códigos DEMO-, precios ilustrativos y personas ficticias. No se han cargado pacientes reales ni rangos de referencia.

## Selectores de catálogo
Ventana modal con búsqueda, paginación, selección por doble clic o botón Elegir. El alta desde el selector usa los permisos del catálogo y selecciona el registro al guardar. Permite selectores anidados y conserva los datos del formulario original.

## Resultados por analito e informe
Captura numérica, cualitativa y de texto por analito de la solicitud. Guarda borradores parciales y exige completarlos antes de validar. Conserva el esquema, valores y contexto de cada versión. PDF por estudio, solo de la versión vigente validada, con logo, paciente, referencias textuales configuradas, responsable y paginación. Los antiguos resultados de texto libre se conservan. No se generan interpretaciones clínicas ni firmas digitales.

## Identidad y navegación
Menú hamburguesa plegable en escritorio y celular. Administrador configura nombre, logo, colores y datos de contacto del laboratorio. La identidad se refleja en el acceso y los documentos; los nuevos documentos conservan su identidad histórica.

## Decisiones abiertas
Nombre comercial, identidad gráfica, sucursales y catálogo de estudios.
