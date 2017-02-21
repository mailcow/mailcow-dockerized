-- Updates from version 0.7

ALTER TABLE [dbo].[contacts] DROP CONSTRAINT [DF_contacts_email]
GO
ALTER TABLE [dbo].[contacts] ALTER COLUMN [email] [text] COLLATE Latin1_General_CI_AI NOT NULL
GO
ALTER TABLE [dbo].[contacts] ADD CONSTRAINT [DF_contacts_email] DEFAULT ('') FOR [email]
GO
