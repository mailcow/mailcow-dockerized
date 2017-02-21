-- Updates from version 0.4.2

DROP INDEX [IX_users_username]
GO
CREATE UNIQUE INDEX [IX_users_username] ON [dbo].[users]([username],[mail_host]) ON [PRIMARY]
GO
ALTER TABLE [dbo].[contacts] ALTER COLUMN [email] [varchar] (255) COLLATE Latin1_General_CI_AI NOT NULL
GO
