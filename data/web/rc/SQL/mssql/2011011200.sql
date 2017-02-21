-- Updates from version 0.5.x

ALTER TABLE [dbo].[contacts] ADD [words] [text] COLLATE Latin1_General_CI_AI NULL 
GO
CREATE INDEX [IX_contactgroupmembers_contact_id] ON [dbo].[contactgroupmembers]([contact_id]) ON [PRIMARY]
GO
DELETE FROM [dbo].[messages]
GO
DELETE FROM [dbo].[cache]
GO
