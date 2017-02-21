-- Updates from version 0.8-rc

ALTER TABLE [dbo].[contacts] DROP CONSTRAINT [DF_contacts_email]
GO
ALTER TABLE [dbo].[contacts] ALTER COLUMN [email] [varchar] (8000) COLLATE Latin1_General_CI_AI NOT NULL
GO
ALTER TABLE [dbo].[contacts] ADD CONSTRAINT [DF_contacts_email] DEFAULT ('') FOR [email]
GO

-- Updates from version 0.8

ALTER TABLE [dbo].[cache] DROP COLUMN [cache_id]
GO
ALTER TABLE [dbo].[users] DROP COLUMN [alias]
GO
CREATE INDEX [IX_identities_email] ON [dbo].[identities]([email],[del]) ON [PRIMARY]
GO
