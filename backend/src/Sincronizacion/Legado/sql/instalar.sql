-- Sincronización temporal con FDN (ADR-042): lo que se instala en el SQL Server
-- del legado. Compatible con SQL Server 2012. Se puede repetir.
--
-- * sync_config: el interruptor (lo escribe FDN; el bundle del legado lo lee).
-- * sync_cambio: qué filas de boleto, encomienda, salida y factura_generada
--   cambiaron, para que FDN las traiga. Lo llenan los triggers.
-- * Los triggers no tocan la lógica del legado: solo anotan el id. Ignoran lo
--   que escribe FDN (su sesión lleva CONTEXT_INFO 'FDNSYNC'), para que un
--   cambio no vuelva al sistema de donde salió.

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
-- entorno de FDN). si_falla: 'bloquear' (no vender si FDN no responde) o 'permitir'.
IF COL_LENGTH('dbo.sync_config', 'fdn_url') IS NULL
    ALTER TABLE dbo.sync_config ADD fdn_url NVARCHAR(255) NULL, fdn_clave NVARCHAR(255) NULL,
        si_falla VARCHAR(10) NOT NULL CONSTRAINT df_sync_config_si_falla DEFAULT 'bloquear';
GO

IF OBJECT_ID('dbo.sync_cambio', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.sync_cambio (
        id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
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

IF OBJECT_ID('dbo.trg_sync_boleto', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_boleto;
GO
CREATE TRIGGER dbo.trg_sync_boleto ON dbo.boleto AFTER INSERT, UPDATE AS
BEGIN
    SET NOCOUNT ON;
    IF SUBSTRING(CONTEXT_INFO(), 1, 7) = 0x46444E53594E43 RETURN;
    INSERT INTO dbo.sync_cambio (tabla, registro_id) SELECT 'boleto', id FROM inserted;
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
