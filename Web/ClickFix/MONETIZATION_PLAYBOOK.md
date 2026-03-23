# ClickFix Monetization Playbook

Documento complementario:
- `ADVERTISING_PLAN.md`: ejecucion de anuncios, patrocinios, inventario, governance y rollout.

## Objetivo
Convertir ClickFix en un producto sostenible con ingresos recurrentes, sin degradar la utilidad de seguridad.

## Principio clave
Monetiza por **valor operativo** (detección premium, workflows, inteligencia y soporte), no por bloquear funciones básicas de protección.

## 1) Líneas de ingreso recomendadas (ordenadas por impacto)

### A. Suscripción SaaS (principal)
- `Free`: uso personal básico (detección + panel público).
- `Pro`: analistas individuales.
- `Team`: equipos SOC pequeños.
- `Enterprise`: empresas/MSSP con SLA y soporte.

Ejemplo inicial de pricing:
- `Free`: 0 EUR
- `Pro`: 9 EUR/mes
- `Team`: 49 EUR/mes (hasta 5 usuarios)
- `Enterprise`: desde 299 EUR/mes

### B. Licencias API/Extensión (ya soportado técnicamente)
- Vender claves de licencia por tier (`basic`, `premium`, `enterprise`).
- Limitar RPM, features y acceso a scoring premium por tier.

### C. Servicios profesionales
- Threat hunting puntual.
- Hardening de navegadores para empresas.
- Integración en SOC/SIEM.
- Formación in-company.

### D. Donaciones
- Canal útil para comunidad y early adopters.
- Ya tienes soporte para PayPal/Ko-fi/Stripe.

### E. Patrocinios/anuncios (secundario)
- Úsalo solo en zona pública/no autenticada.
- Evita contaminar panel operativo para no romper UX.

## 2) Qué vender exactamente

## Free (adquisición)
- Detección base ClickFix.
- Dashboard público.
- Desistimientos y comunidad.

## Premium (conversión)
- Score config premium firmado.
- Mayor cobertura de fuentes de inteligencia.
- Reportes automáticos avanzados (diario/semanal/mensual con destinatarios múltiples).
- Gestión avanzada de listas y acciones masivas.
- Investigaciones y colaboración de equipo.
- Mensajería masiva e individual a usuarios de extensión.

## Enterprise (margen alto)
- Multi-tenant / múltiples entornos.
- SLA + soporte prioritario.
- Integración con SIEM/webhooks privados.
- Reglas personalizadas + onboarding.

## 3) Implementación inmediata con lo que ya tienes

Tu proyecto ya incluye base técnica para monetizar:
- API de token/licencia.
- Endpoint de config premium firmado.
- Variables de entorno para donaciones/anuncios.

Checklist mínimo:
1. Definir tiers y límites reales (`RPM`, módulos, soporte).
2. Generar y gestionar licencias por cliente.
3. Activar CTA en `index.php` y panel público:
   - `Empezar Premium`
   - `Solicitar demo`
4. Añadir página simple de planes + checkout (Stripe recomendado).
5. Medir conversión embudo: visita -> registro -> trial -> pago.

## 4) Configuración rápida (entorno)

Variables útiles en `.env.security`:

```env
CLICKFIX_MONETIZATION_ENABLED=1
CLICKFIX_DONATION_PAYPAL_URL=https://...
CLICKFIX_DONATION_KOFI_URL=https://...
CLICKFIX_DONATION_STRIPE_URL=https://...
CLICKFIX_ADSENSE_ENABLED=0
CLICKFIX_ADSENSE_CLIENT=ca-pub-XXXXXXXXXXXX
CLICKFIX_ADSENSE_SLOT=1234567890

# Licencias de ejemplo: KEY:TIER:RPM
CLICKFIX_API_LICENSE_KEYS=CFX-XXXX-XXXX:basic:120,CFX-YYYY-YYYY:premium:600
```

Recomendación:
- Mantén `ADSENSE` desactivado al inicio y prioriza suscripción/licencias.
- Usa anuncios solo si no perjudican confianza y rendimiento.

## 5) KPIs de negocio que debes seguir

- `MRR`: ingresos recurrentes mensuales.
- `Conversion rate`: usuarios free -> pago.
- `Churn`: cancelaciones mensuales.
- `ARPA`: ingreso medio por cuenta.
- `LTV/CAC`: valor de cliente vs coste de adquisición.
- `Time-to-value`: tiempo hasta primer valor real (ej. primer bloqueo útil).

## 6) Go-To-Market (90 días)

## Fase 1 (0-30 días)
- Activar página de planes.
- Cerrar primeros 5-10 pagos (`Pro`/`Team`).
- Publicar 2-3 casos reales (sin datos sensibles).

## Fase 2 (31-60 días)
- Partner con freelancers SOC/MSSP pequeños.
- Paquete `Team` sólido + trial de 14 días.
- Mejorar onboarding en menos de 10 minutos.

## Fase 3 (61-90 días)
- Cerrar 1-3 clientes enterprise.
- Añadir SLA + soporte prioritario.
- Preparar canal B2B outbound (LinkedIn/email).

## 7) Riesgos y mitigación

- Riesgo: competir por precio con clones.
  - Mitigación: ventaja operativa (datasets, pipeline, actualización continua).
- Riesgo: baja conversión.
  - Mitigación: trial guiado + casos de uso claros + paywall bien definido.
- Riesgo: rechazo por anuncios.
  - Mitigación: anuncios solo en público, nunca en vista SOC.

## 8) Regla estratégica

El dinero fuerte no vendrá de anuncios.  
Vendrá de:
- Licencias + suscripción.
- Soporte enterprise.
- Servicios profesionales de alto valor.

---

Si quieres, el siguiente paso es que te prepare también el documento `PRICING.md` con tabla final de planes, límites exactos por tier y texto comercial listo para publicar.
