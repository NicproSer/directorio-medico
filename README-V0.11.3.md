# SVCP Directorio Médico v0.11.3

## Mejoras del módulo de pagos

- Calcula y conserva el equivalente en USD del monto realmente transferido, usando la tasa BCV guardada para la fecha de operación.
- El panel de pagos muestra monto recibido en Bs., equivalente pagado en USD, precio institucional en USD y equivalente institucional requerido en Bs.
- Al aprobar un pago, el administrador dispone de un campo «Monto que falta (Bs.)», precargado con la diferencia entre el equivalente institucional requerido y el monto reportado. Puede ajustar el valor o colocar cero.
- Se conserva el saldo pendiente en bolívares y su equivalente en USD calculado con la tasa histórica del reporte.
- La notificación de aprobación al médico informa cuánto se reconoció en USD y, cuando corresponda, el saldo pendiente.
- El CSV incorpora equivalente pagado en USD y saldo pendiente en ambas monedas.

## Criterio de cálculo

`equivalente_pagado_usd = monto_transferido_bs / tasa_bcv_guardada`

`saldo_sugerido_bs = max(0, equivalente_institucional_bs - monto_transferido_bs)`

`saldo_pendiente_usd = saldo_pendiente_bs / tasa_bcv_guardada`

La tasa utilizada es la instantánea guardada al registrar el pago; las actualizaciones posteriores del BCV no alteran el reporte histórico.

## Consideración operativa

Registrar un saldo pendiente no equivale a cobrarlo ni conciliarlo automáticamente. El administrador debe verificarlo y coordinar su pago. La aprobación mantiene el comportamiento existente de activar la membresía; el saldo queda documentado en el reporte y en los metadatos del médico.
