# OpenRouter API — Referencia para Prisma AI Inbox

> Verificado el 10 de junio de 2026 contra la documentación oficial de OpenRouter.

## Modelo elegido

- **ID exacto**: `google/gemini-3.1-flash-lite`
- **Precio**: $0.25 / 1M tokens entrada · $1.50 / 1M tokens salida
- **Contexto**: 1M tokens
- **Soporta**: structured outputs, multimodal, niveles de "thinking" ajustables
- Optimizado para extracción de datos y baja latencia — encaja con el caso de uso (analizar notas de reunión). Coste estimado por nota: < $0.001.

## Endpoint

```
POST https://openrouter.ai/api/v1/chat/completions
```

### Headers

| Header | Valor | Obligatorio |
|---|---|---|
| `Authorization` | `Bearer <OPENROUTER_API_KEY>` | Sí |
| `Content-Type` | `application/json` | Sí |
| `HTTP-Referer` | URL de la app (atribución) | No |
| `X-OpenRouter-Title` | Nombre de la app (atribución) | No |

## Request con structured outputs (lo que usará `api/ai-inbox.php`)

```json
{
  "model": "google/gemini-3.1-flash-lite",
  "messages": [
    { "role": "system", "content": "<instrucciones + lista de apps del usuario>" },
    { "role": "user", "content": "<nota en bruto>" }
  ],
  "response_format": {
    "type": "json_schema",
    "json_schema": {
      "name": "inbox_items",
      "strict": true,
      "schema": {
        "type": "object",
        "properties": {
          "items": {
            "type": "array",
            "items": {
              "type": "object",
              "properties": {
                "tipo": { "type": "string", "enum": ["mejora", "tarea"], "description": "mejora = request de una app; tarea = tarea rápida personal" },
                "app_id": { "type": ["integer", "null"], "description": "ID de la app de la lista proporcionada, o null si no está claro" },
                "title": { "type": "string" },
                "description": { "type": "string" },
                "priority": { "type": "string", "enum": ["low", "medium", "high", "critical"] },
                "subtasks": { "type": "array", "items": { "type": "string" } },
                "reasoning": { "type": "string", "description": "Explicación breve en español de por qué se clasificó así" }
              },
              "required": ["tipo", "app_id", "title", "description", "priority", "subtasks", "reasoning"],
              "additionalProperties": false
            }
          }
        },
        "required": ["items"],
        "additionalProperties": false
      }
    }
  }
}
```

Notas clave de la doc oficial:
- Usar siempre `strict: true` y `additionalProperties: false` para que el modelo cumpla el schema exactamente.
- Añadir `description` a las propiedades mejora la precisión del modelo.
- Existe un plugin "Response Healing" para requests no-streaming que repara JSON imperfecto (capa extra de seguridad; opcional).
- Modelos sin soporte de structured outputs devuelven error explícito; schemas malformados devuelven error de validación.

## Respuesta

Formato compatible OpenAI: el JSON generado está en `choices[0].message.content` (string JSON → hacer `json_decode`). Errores siguen convenciones HTTP estándar (401 key inválida, 4xx validación, 5xx proveedor) con cuerpo `{"error": {...}}`.

## Implementación en PHP (hosting compartido)

- cURL estándar: `curl_init` + `CURLOPT_POSTFIELDS` con el JSON anterior; `CURLOPT_TIMEOUT` ≈ 60s.
- La API key se guarda cifrada en BD con `includes/encryption.php` (AES-256-CBC, mismo patrón que SMTP) y solo se descifra en servidor. Nunca se expone al frontend.

Fuentes: [Quickstart](https://openrouter.ai/docs/quickstart) · [Structured Outputs](https://openrouter.ai/docs/guides/features/structured-outputs) · [Ficha del modelo](https://openrouter.ai/google/gemini-3.1-flash-lite)
