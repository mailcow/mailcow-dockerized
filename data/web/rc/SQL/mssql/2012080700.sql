-- Updates from version 0.8

ALTER TABLE [dbo].[cache] DROP COLUMN [cache_id]
GO
ALTER TABLE [dbo].[users] DROP COLUMN [alias]
GO
CREATE INDEX [IX_identities_email] ON [dbo].[identities]([email],[del]) ON [PRIMARY]
GO
