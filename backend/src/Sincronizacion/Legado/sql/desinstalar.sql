-- Quita del SQL Server del legado todo lo que instaló `instalar.sql` (ADR-042).
IF OBJECT_ID('dbo.trg_sync_boleto', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_boleto;
GO
IF OBJECT_ID('dbo.trg_sync_encomienda', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_encomienda;
GO
IF OBJECT_ID('dbo.trg_sync_encomienda_bitacora', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_encomienda_bitacora;
GO
IF OBJECT_ID('dbo.trg_sync_salida', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_salida;
GO
IF OBJECT_ID('dbo.trg_sync_factura_generada', 'TR') IS NOT NULL DROP TRIGGER dbo.trg_sync_factura_generada;
GO
IF OBJECT_ID('dbo.sync_cambio', 'U') IS NOT NULL DROP TABLE dbo.sync_cambio;
GO
IF OBJECT_ID('dbo.sync_config', 'U') IS NOT NULL DROP TABLE dbo.sync_config;
GO
