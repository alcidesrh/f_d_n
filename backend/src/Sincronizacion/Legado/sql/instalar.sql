-- Sincronización temporal con FDN (ADR-042): lo que se instala en el SQL Server
-- del legado. Compatible con SQL Server 2012. Se puede repetir.
--
-- * sync_config: el interruptor (lo escribe FDN; el bundle del legado lo lee).
-- * sync_cambio: qué filas de boleto, encomienda, salida y factura_generada
--   cambiaron y qué clientes se crearon, para que FDN los traiga. Lo llenan
--   los triggers.
-- * trg_sync_*: anotan el id de lo que cambió; no tocan la lógica del legado.
-- * trg_sync_proteger_*: lo que nació en FDN es de FDN. Rechazan que el legado
--   anule o reasigne un boleto o una encomienda que vino de FDN
--   (identificador_web 'FDN:…'): se hace en FDN y la sincronización lo trae.
-- * Todos ignoran lo que escribe FDN (su sesión lleva CONTEXT_INFO 'FDNSYNC'):
--   un cambio no vuelve al sistema de donde salió, y la sincronización sí
--   puede anular allá lo que se anuló aquí.

IF OBJECT_ID('dbo.sync_config', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.sync_config (
        id INT NOT NULL PRIMARY KEY,
        activo BIT NOT NULL DEFAULT 0,
        cambiado_en DATETIME NULL
    );
    INSERT INTO dbo.sync_config (id, activo) VALUES (1, 0);
END
GO

-- Dónde está FDN y con qué clave se llama (los escribe la instalación desde el
-- entorno de FDN). si_falla: 'permitir' (vender aunque FDN no responda; defecto) o 'bloquear'.
IF COL_LENGTH('dbo.sync_config', 'fdn_url') IS NULL
    ALTER TABLE dbo.sync_config ADD fdn_url NVARCHAR(255) NULL, fdn_clave NVARCHAR(255) NULL,
        si_falla VARCHAR(10) NOT NULL CONSTRAINT df_sync_config_si_falla DEFAULT 'permitir';
GO

-- El id de sync_cambio sale de una SECUENCIA, no de IDENTITY: un INSERT en una
-- tabla con IDENTITY dentro del trigger cambiaría @@IDENTITY, que es lo que lee
-- el driver del legado (PDO sqlsrv) como id de la fila que acaba de insertar.
-- Con IDENTITY, una factura nueva tomaba el id de su fila de sync_cambio.
IF OBJECT_ID('dbo.sync_cambio_id', 'SO') IS NULL
    CREATE SEQUENCE dbo.sync_cambio_id AS BIGINT START WITH 1 INCREMENT BY 1;
GO

-- Instalaciones anteriores con IDENTITY: se aparta la tabla vieja y, creada la
-- nueva, se copian sus filas (lo pendiente no se pierde).
IF OBJECT_ID('dbo.sync_cambio', 'U') IS NOT NULL AND COLUMNPROPERTY(OBJECT_ID('dbo.sync_cambio'), 'id', 'IsIdentity') = 1
BEGIN
    DROP INDEX idx_sync_cambio_pendiente ON dbo.sync_cambio;
    EXEC sp_rename 'dbo.sync_cambio', 'sync_cambio_viejo';
END
GO

IF OBJECT_ID('dbo.sync_cambio', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.sync_cambio (
        id BIGINT NOT NULL PRIMARY KEY CONSTRAINT df_sync_cambio_id DEFAULT (NEXT VALUE FOR dbo.sync_cambio_id),
        tabla VARCHAR(30) NOT NULL,
        registro_id BIGINT NOT NULL,
        creado DATETIME NOT NULL DEFAULT GETDATE(),
        procesado DATETIME NULL,
        intentos INT NOT NULL DEFAULT 0,
        error NVARCHAR(MAX) NULL
    );
    CREATE INDEX idx_sync_cambio_pendiente ON dbo.sync_cambio (procesado, id);
END
GO

IF OBJECT_ID('dbo.sync_cambio_viejo', 'U') IS NOT NULL
BEGIN
    INSERT INTO dbo.sync_cambio (tabla, registro_id, creado, procesado, intentos, error)
        SELECT tabla, registro_id, creado, procesado, intentos, error FROM dbo.sync_cambio_viejo ORDER BY id;
    DROP TABLE dbo.sync_cambio_viejo;
END
GO

IF OBJECT_ID('dbo.trg_sync_boleto', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_boleto;
GO
CREATE TRIGGER dbo.trg_sync_boleto ON dbo.boleto AFTER INSERT, UPDATE AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    INSERT INTO dbo.sync_cambio (tabla, registro_id) SELECT 'boleto', id FROM inserted;
END
GO

IF OBJECT_ID('dbo.trg_sync_cliente', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_cliente;
GO
CREATE TRIGGER dbo.trg_sync_cliente ON dbo.cliente AFTER INSERT AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    INSERT INTO dbo.sync_cambio (tabla, registro_id) SELECT 'cliente', id FROM inserted;
END
GO

IF OBJECT_ID('dbo.trg_sync_encomienda', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_encomienda;
GO
CREATE TRIGGER dbo.trg_sync_encomienda ON dbo.encomienda AFTER INSERT, UPDATE AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    INSERT INTO dbo.sync_cambio (tabla, registro_id) SELECT 'encomienda', id FROM inserted;
END
GO

IF OBJECT_ID('dbo.trg_sync_encomienda_bitacora', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_encomienda_bitacora;
GO
CREATE TRIGGER dbo.trg_sync_encomienda_bitacora ON dbo.encomienda_bitacora AFTER INSERT AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    INSERT INTO dbo.sync_cambio (tabla, registro_id) SELECT DISTINCT 'encomienda', encomienda_id FROM inserted;
END
GO

IF OBJECT_ID('dbo.trg_sync_salida', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_salida;
GO
CREATE TRIGGER dbo.trg_sync_salida ON dbo.salida AFTER INSERT, UPDATE AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    INSERT INTO dbo.sync_cambio (tabla, registro_id) SELECT 'salida', id FROM inserted;
END
GO

IF OBJECT_ID('dbo.trg_sync_factura_generada', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_factura_generada;
GO
CREATE TRIGGER dbo.trg_sync_factura_generada ON dbo.factura_generada AFTER INSERT, UPDATE AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    INSERT INTO dbo.sync_cambio (tabla, registro_id) SELECT 'factura_generada', id FROM inserted;
END
GO

-- Lo que nació en FDN es de FDN (ADR-042): el legado no lo anula ni lo
-- reasigna. Si lo intenta, THROW deshace la transacción entera y el legado
-- recibe el error 51001 (boleto), 51002 (su factura) o 51003 (encomienda).

-- Un boleto 'FDN:' que pasa a anulado (4) o reasignado (5), o un boleto que
-- nace de reasignar uno 'FDN:' (reasignado_id). Se mira el identificador_web
-- de antes del cambio: quitarle el prefijo en el mismo UPDATE no lo libra.
IF OBJECT_ID('dbo.trg_sync_proteger_boleto', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_proteger_boleto;
GO
CREATE TRIGGER dbo.trg_sync_proteger_boleto ON dbo.boleto AFTER INSERT, UPDATE AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    DECLARE @id BIGINT = NULL;
    IF UPDATE(estado_id)
        SELECT TOP 1 @id = d.id FROM inserted i JOIN deleted d ON d.id = i.id
         WHERE d.identificador_web LIKE 'FDN:%' AND i.estado_id IN (4, 5) AND ISNULL(d.estado_id, 0) <> i.estado_id;
    IF @id IS NULL AND UPDATE(reasignado_id)
        SELECT TOP 1 @id = o.id FROM inserted i
          JOIN dbo.boleto o ON o.id = i.reasignado_id
          LEFT JOIN deleted d ON d.id = i.id
         WHERE o.identificador_web LIKE 'FDN:%' AND ISNULL(d.reasignado_id, 0) <> i.reasignado_id;
    IF @id IS NULL RETURN;
    DECLARE @m NVARCHAR(400) = N'El boleto ' + CAST(@id AS NVARCHAR(20))
        + N' se vendió en el sistema nuevo (FDN): anúlelo o reasígnelo allá.';
    THROW 51001, @m, 1;
END
GO

-- La factura_generada de un boleto 'FDN:' que se anula (fecha_anulacion).
IF OBJECT_ID('dbo.trg_sync_proteger_factura_generada', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_proteger_factura_generada;
GO
CREATE TRIGGER dbo.trg_sync_proteger_factura_generada ON dbo.factura_generada AFTER UPDATE AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    IF NOT UPDATE(fecha_anulacion) RETURN;
    DECLARE @id BIGINT = NULL;
    SELECT TOP 1 @id = b.id FROM inserted i JOIN deleted d ON d.id = i.id
      JOIN dbo.boleto b ON b.factura_generada_id = i.id
     WHERE d.fecha_anulacion IS NULL AND i.fecha_anulacion IS NOT NULL AND b.identificador_web LIKE 'FDN:%';
    IF @id IS NULL RETURN;
    DECLARE @m NVARCHAR(400) = N'La factura del boleto ' + CAST(@id AS NVARCHAR(20))
        + N' es del sistema nuevo (FDN): anúlela allá.';
    THROW 51002, @m, 1;
END
GO

-- Una encomienda 'FDN:' con un evento anulada (6) o cancelada (7).
IF OBJECT_ID('dbo.trg_sync_proteger_encomienda', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_proteger_encomienda;
GO
CREATE TRIGGER dbo.trg_sync_proteger_encomienda ON dbo.encomienda_bitacora AFTER INSERT AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    DECLARE @id BIGINT = NULL;
    SELECT TOP 1 @id = e.id FROM inserted i JOIN dbo.encomienda e ON e.id = i.encomienda_id
     WHERE i.estado_id IN (6, 7) AND e.identificador_web LIKE 'FDN:%';
    IF @id IS NULL RETURN;
    DECLARE @m NVARCHAR(400) = N'La encomienda ' + CAST(@id AS NVARCHAR(20))
        + N' se recibió en el sistema nuevo (FDN): anúlela allá.';
    THROW 51003, @m, 1;
END
GO
