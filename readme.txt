=== Tema Bellas Artes - Sistema de Gestión de Escalafón ===
Contributors: Bellas Artes Institución Universitaria del Valle
Tested up to: WordPress 5.0+
Version: 1.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: gestión-facultad, gestión-documentos, universidad, escalafón-docente, seguridad-mejorada

== Descripción ==

Sistema de Gestión de Escalafón - Tema personalizado de WordPress para la gestión integral de documentos académicos y portfolios de la facultad en Bellas Artes Institución Universitaria del Valle.

Este tema proporciona un sistema completo de gestión académica con las siguientes características principales:

=== Sistema de Autenticación Mejorado ===
* Protección CSRF - Protección contra ataques de falsificación de solicitud entre sitios
* Limitación de Intentos - 5 intentos por IP cada 15 minutos
* Bloqueo de Cuenta - Bloqueo automático después de 3 intentos fallidos
* Advertencias Progresivas - "Quedan 2 intentos", "Queda 1 intento"
* Sin Sesiones Persistentes - Seguro para computadoras públicas universitarias
* Mensajes Genéricos - No revela existencia de usuarios

=== Sistema de Gestión de Documentos ===
* 6 Tipos de Documentos Académicos:
  - Estudios de Pre-Grado
  - Estudios de Post-Grado
  - Publicaciones Académicas
  - Cursos de Actualización
  - Creación Artística
  - Asistencia a Eventos
* Formatos Soportados: PDF, JPEG, PNG
* Visualización Integrada - Previsualizador de PDF y visor de imágenes
* Estados de Validación - Pendiente, Válido, Inválido
* Carga Local o URL - Soporte para archivos locales o enlaces externos

=== Sistema de Roles Universitarios ===

1. Administrador del Sistema (system_admin)
   - Acceso completo al sistema y funciones administrativas
   - Gestión de usuarios y roles
   - Supervisión de seguridad
   - Funciones de prueba y desarrollo
   - Menú: Profesores + Administración

2. Coordinador Académico (academic_coordinator)
   - Supervisión de la facultad
   - Validación de documentos académicos
   - Desbloqueo de cuentas de usuarios
   - Revisión de portfolios académicos
   - Menú: Profesores

3. Facultad/Profesor (faculty)
   - Gestión de documentos personales
   - Actualización de perfil académico
   - Carga de documentos académicos
   - Seguimiento de estado de validación
   - Menú: Mi Perfil + Mis Documentos

4. Facultad Invitada (guest_faculty)
   - Acceso limitado a documentos
   - Gestión básica de perfil
   - Acceso temporal al sistema
   - Menú: Mi Perfil + Mis Documentos

5. Estudiante/Visitante (subscriber)
   - Solo lectura
   - Sin acceso al sistema
   - Información pública únicamente

=== Flujo de Validación de Documentos ===
1. Carga de Facultad → Documento marcado como "Pendiente de Validación" ⏳
2. Revisión del Coordinador → Puede aprobar o rechazar documentos
3. Estado Actualizado → Documento marcado como "Válido" ✅ o "Inválido" ❌
4. Registro de Auditoría → Historial completo de validaciones

== Instalación ==

=== Requisitos del Sistema ===
* WordPress 4.7 o superior
* PHP 7.0 o superior (recomendado PHP 7.4+)
* MySQL 5.6 o superior
* Servidor web (Apache/Nginx)
* Memoria PHP mínima: 128MB

=== Pasos de Instalación ===

1. Subir el Tema:
   Copiar la carpeta del tema a: /wp-content/themes/bellasartes

2. Activar el Tema:
   WordPress Admin → Apariencia → Temas → Activar "Bellas Artes Theme"

3. Configuración Automática:
   - El tema crea automáticamente todas las páginas necesarias
   - Los roles universitarios se configuran automáticamente
   - Las páginas del sistema se generan con shortcodes

4. Crear Usuarios:
   WordPress Admin → Usuarios → Añadir Nuevo
   Asignar rol apropiado: System Administrator, Academic Coordinator, o Faculty/Professor

== Características del Sistema ==

=== Seguridad Empresarial ===

Protección de Inicio de Sesión:
* Protección CSRF mediante WordPress nonces
* Limitación de tasa por IP - 5 intentos cada 15 minutos
* Bloqueo de cuenta después de 3 intentos fallidos
* Advertencias progresivas al usuario
* Registro de actividad de intentos fallidos
* Desbloqueo administrativo con un clic

Características de Seguridad:
✓ Sin dependencias de plugins externos
✓ Usa solo funcionalidad del núcleo de WordPress
✓ Validación de entrada mejorada
✓ Mensajes de error genéricos
✓ Registro de actividad de inicio de sesión
✓ Gestión segura de sesiones

=== Gestión de Documentos ===

Tipos de Documentos Soportados:
* Pre-Grado - Títulos de licenciatura
* Post-Grado - Maestrías, doctorados, especializaciones
* Publicaciones - Artículos académicos, libros, investigaciones
* Actualización - Cursos, seminarios, talleres
* Creación Artística - Obras, presentaciones, exposiciones
* Asistencia - Congresos, simposios, eventos académicos

Metadatos del Documento:
* Tipo de documento
* Nombre descriptivo
* Año de obtención/publicación
* Archivo adjunto o URL externa
* Estado de validación
* Usuario propietario

Flujo de Trabajo:
1. Profesor carga documento → Estado: Pendiente
2. Coordinador revisa → Aprueba/Rechaza
3. Estado actualizado → Válido/Inválido
4. Profesor ve estado → Puede ver retroalimentación

== Interfaz de Usuario ==

=== Para Profesores ===
* Mi Perfil - Actualizar información personal y académica
* Mis Documentos - Ver, crear, editar documentos propios
* Crear Documento - Formulario de carga con metadatos
* Ver Documento - Visualización con estado de validación
* Eliminar Documento - Solo documentos no validados

=== Para Coordinadores ===
* Profesores - Lista de toda la facultad
* Ver Profesor - Perfil completo y documentos del profesor
* Validar Documento - Aprobar o rechazar con un clic
* Desbloquear Cuentas - Restaurar acceso a usuarios bloqueados

=== Para Administradores ===
* Panel de Administración - Acceso completo a WordPress
* Gestión de Usuarios - Crear, editar, eliminar usuarios
* Supervisión de Seguridad - Widget con estadísticas
* Funciones de Prueba - Crear usuarios de prueba
* Gestión de Roles - Asignar roles universitarios

== Interfaz de Administración ==

=== Lista de Usuarios Mejorada ===

Columnas Adicionales:
* Estado de Seguridad - Muestra cuentas bloqueadas
* Rol Universitario - Muestra rol apropiado
* Intentos Fallidos - Contador de intentos

Filtros:
* Filtrar por rol universitario
* Ver solo cuentas bloqueadas
* Ver solo usuarios activos

Acciones Rápidas:
* Desbloquear Cuenta - Un clic para restaurar acceso
* Acciones Masivas - Desbloquear múltiples cuentas
* Asignar Rol - Cambiar rol universitario

=== Widget de Seguridad ===
Panel de control con:
* Total de usuarios en el sistema
* Cuentas actualmente bloqueadas
* Enlace rápido a usuarios bloqueados
* Funciones de prueba para administradores

== Funciones de Prueba ==

=== Crear Usuario de Prueba ===
Shortcode: [create_test_user username="testprof" email="test@universidad.edu" password="pass123" role="faculty"]

Parámetros:
* username - Nombre de usuario (por defecto: testuser)
* email - Correo electrónico (por defecto: test@example.com)
* password - Contraseña (por defecto: testpass123)
* role - Rol universitario (por defecto: faculty)

=== Simular Intentos Fallidos ===
Shortcode: [simulate_attempts]
Simula 3 intentos fallidos de inicio de sesión para probar el bloqueo de cuenta.

=== Asignar Rol Universitario ===
Shortcode: [assign_university_role user_id=123 new_role=faculty]

== Guía de Uso ==

=== Para Profesores ===

Primer Inicio de Sesión:
1. Navegar a la página principal del sitio
2. Ingresar credenciales proporcionadas por el administrador
3. Completar información de perfil en "Mi Perfil"
4. Comenzar a cargar documentos académicos

Cargar un Documento:
1. Ir a "Mis Documentos"
2. Clic en "Crear nuevo documento"
3. Seleccionar tipo de documento
4. Ingresar nombre y año
5. Subir archivo (PDF, JPEG, PNG) o proporcionar URL
6. Enviar formulario
7. Documento aparece como "Pendiente de Validación"

Gestionar Documentos:
* Ver: Clic en "Ver Detalle" para visualizar
* Eliminar: Solo disponible para documentos no validados
* Estado: Monitorear estado de validación

=== Para Coordinadores Académicos ===

Revisar Profesores:
1. Ir a menú "Profesores"
2. Ver lista de toda la facultad
3. Usar filtros para buscar profesores específicos
4. Clic en "Ver Profesor" para ver perfil completo

Validar Documentos:
1. Seleccionar profesor de la lista
2. Ver documentos pendientes de validación
3. Clic en "Ver Detalle" del documento
4. Revisar contenido del documento
5. Clic en "Documento Válido" o "Documento Inválido"
6. Estado actualizado automáticamente

Desbloquear Cuentas:
1. Ir a WordPress Admin → Usuarios
2. Filtrar por "Cuentas Bloqueadas"
3. Clic en "Unlock Account" para cada usuario
4. O usar acción masiva para múltiples usuarios

=== Para Administradores del Sistema ===

Crear Nuevos Usuarios:
1. WordPress Admin → Usuarios → Añadir Nuevo
2. Completar información básica
3. Asignar rol universitario apropiado:
   - System Administrator
   - Academic Coordinator
   - Faculty/Professor
   - Guest Faculty
4. Enviar credenciales al usuario

Monitorear Seguridad:
* Revisar widget de seguridad en panel
* Ver cuentas bloqueadas
* Analizar intentos fallidos
* Desbloquear cuentas según necesidad

Gestión de Roles:
* Cambiar roles según evolución de responsabilidades
* Asignar múltiples coordinadores académicos
* Gestionar acceso de facultad invitada

== Seguridad y Cumplimiento ==

=== Diseño para Entorno Universitario ===
✓ Seguro para computadoras públicas
✓ Sin sesiones persistentes (se cierra al cerrar navegador)
✓ Protección contra ataques de fuerza bruta
✓ Registro de auditoría de actividades
✓ Control de acceso basado en roles
✓ Protección de datos sensibles

=== Mejores Prácticas Implementadas ===
* Validación de entrada en todos los formularios
* Sanitización de salida en todas las vistas
* Nonces de WordPress para protección CSRF
* Uso de funciones preparadas para consultas SQL
* Validación de tipos de archivo MIME
* Límites de tamaño de archivo configurables

== Solución de Problemas ==

=== Cuenta Bloqueada ===
Problema: "Cuenta bloqueada. Contacte al administrador"
Solución: Contactar a un Coordinador Académico o Administrador del Sistema para desbloquear la cuenta.

=== Demasiados Intentos ===
Problema: "Demasiados intentos. Intente nuevamente en 15 minutos"
Solución: Esperar 15 minutos antes de intentar nuevamente, o cambiar a una red diferente.

=== Error al Subir Documento ===
Problema: "El archivo supera el tamaño máximo"
Solución: Reducir el tamaño del archivo o contactar al administrador para aumentar el límite.

=== Páginas No Aparecen ===
Problema: Las páginas del sistema no se crearon automáticamente
Solución: Desactivar y reactivar el tema para regenerar las páginas requeridas.

== Soporte ==

Para soporte técnico, solicitudes de personalización o problemas del sistema:

Contactar:
* Departamento de TI - Bellas Artes Institución Universitaria del Valle
* Email: soporte@bellasartes.edu.co
* Tel: [Número de contacto]

Documentación Adicional:
* Manual de usuario disponible internamente
* Videos tutoriales en intranet universitaria
* Sesiones de capacitación disponibles bajo solicitud

== Registro de Cambios ==

= 1.2 =
* ✨ Sistema de seguridad de inicio de sesión mejorado
* ✨ Roles universitarios personalizados
* ✨ Bloqueo de cuenta después de intentos fallidos
* ✨ Advertencias progresivas de seguridad
* ✨ Interfaz de administración mejorada
* ✨ Widget de supervisión de seguridad
* ✨ Filtros de rol universitario
* ✨ Funciones de prueba para administradores
* 🔧 Protección CSRF mejorada
* 🔧 Limitación de tasa por IP
* 🔧 Registro de actividad de inicio de sesión

= 1.0 =
* 🎉 Lanzamiento inicial
* Sistema de gestión de documentos de facultad
* Autenticación de usuarios y perfiles
* Flujo de trabajo de carga y validación de documentos
* Tema personalizado basado en estándares de WordPress

== Créditos ==

Desarrollado para:
Bellas Artes Institución Universitaria del Valle

Framework:
WordPress - Sistema de gestión de contenido

Características Clave:
* Sistema sin dependencias de plugins
* Usa solo funcionalidad del núcleo de WordPress
* Seguridad de nivel empresarial
* Diseñado específicamente para entornos universitarios