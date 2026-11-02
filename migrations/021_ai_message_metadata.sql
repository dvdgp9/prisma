-- IA3: referencias verificadas y cobertura de consultas, sin contenido duplicado de registros.
-- Ejecutar una vez antes de desplegar IA3. No es necesaria para IA1/IA2.
ALTER TABLE ai_messages ADD COLUMN metadata_json LONGTEXT NULL;
