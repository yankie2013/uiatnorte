# Condición de las personas involucradas

Regla común para todo UIAT NORTE: estas expresiones son equivalentes, no condiciones diferentes.

| Expresiones aceptadas (sin distinguir mayúsculas) | Valor interno existente |
| --- | --- |
| Lesionado, lesionada, herido, herida | Herido |
| Fallecido, fallecida, occiso, occisa | Fallecido |
| Ileso, ilesa | Ileso |

La condición pertenece al vínculo de la persona con el expediente (`involucrados_personas.lesion`), no a su rol de participación ni a su identidad global.

Usar `App\Support\CondicionPersona` para normalizar entradas y clasificar condiciones en nuevas funcionalidades. Registro y edición normalizan antes de guardar; el repositorio también protege las llamadas directas. No crear nuevos estados en el enum para los sinónimos. Una condición desconocida no equivale a ileso.

Para la generación automática de diligencias acordada: Herido corresponde a informe médico; Fallecido corresponde a protocolo de necropsia; Ileso no genera ninguna de esas dos solicitudes. Este documento fija el vocabulario y no activa por sí mismo la generación automática ni altera las demás reglas de documentos existentes.
