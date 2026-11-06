# Estado efectivo de diligencias

La bandeja **Diligencias** muestra Todas, Pendientes (rojo), En proceso (naranja) y Realizadas (verde). La URL anterior se conserva para no romper enlaces. Cada filtro y su contador usa el mismo estado calculado y el alcance del expediente asignado al JEFE EMI.

`App\Support\DiligenciaEstado` calcula el estado al leer, sin migraciones ni actualizaciones masivas:

- Un oficio activo y no anulado, vinculado directamente a la diligencia y al mismo expediente, indica **En proceso**.
- Una respuesta recibida vinculada a ese oficio y al mismo expediente indica **Realizado**. También se respeta la respuesta registrada en el campo existente `documentos_recibidos`.
- Una citación vinculada pendiente de realización indica **En proceso**. Se admite tanto `citacion_id` como el campo opcional `citacion_ids`.
- Se requiere que todas las citaciones vinculadas estén realizadas para completar la diligencia por esa vía.
- Citaciones suspendidas, canceladas, no realizadas, reprogramadas o eliminadas no completan la diligencia. Si no queda otra actuación válida, vuelve a **Pendiente**.
- Un documento realizado o acta vinculada indica **En proceso**, sin asumir que su mera creación completa el trámite.
- Se conservan los estados manuales Realizado y Cancelado; la invalidación de citaciones sin otra evidencia válida reabre la tarea. Las canceladas no aparecen en los tres filtros de trabajo.
- Un documento de otro expediente no es evidencia válida. No se asocian oficios o citaciones únicamente por compartir expediente.

La lectura se aplica a la bandeja, al repositorio usado por la consulta modal y al listado dentro del expediente. Los datos almacenados conservan el enum existente. Las respuestas o cambios de citación posteriores se reflejan en la próxima consulta, incluso para diligencias antiguas.

Esta mejora no implementa generación automática de diligencias ni confirmación de cargos de remisión; esos cambios requieren su propia integración.
