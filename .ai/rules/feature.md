---
paths:
  - 'tests/Feature/**'
---

# Feature

## Storage::fake('public') en todo test que borre un Vehiculo o Producto
Los hooks `deleting` de `Vehiculo` y `Producto` hacen `deleteDirectory('vehiculos/{id}' | 'productos/{id}')` sobre el disco `public`. La base de tests es otra, pero el disco NO: sin `Storage::fake('public')`, borrar el producto 1 en un test borra las fotos reales de la semilla con id 1 (ya pasó con `productos/1`). Si el test borra un modelo con galería, fakeá el disco (beforeEach o dentro del test).
