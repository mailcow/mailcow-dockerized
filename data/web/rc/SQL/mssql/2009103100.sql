-- Updates from version 0.3.1

ALTER TABLE [dbo].[messages] ADD CONSTRAINT [FK_messages_user_id]
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO

ALTER TABLE [dbo].[cache] ADD CONSTRAINT [FK_cache_user_id]
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO

ALTER TABLE [dbo].[contacts] ADD CONSTRAINT [FK_contacts_user_id]
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO

ALTER TABLE [dbo].[identities] ADD CONSTRAINT [FK_identities_user_id] 
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO

ALTER TABLE [dbo].[identities] ADD [changed] [datetime] NULL 
GO

CREATE TABLE [dbo].[contactgroups] (
	[contactgroup_id] [int] IDENTITY (1, 1) NOT NULL ,
	[user_id] [int] NOT NULL ,
	[changed] [datetime] NOT NULL ,
	[del] [char] (1) COLLATE Latin1_General_CI_AI NOT NULL ,
	[name] [varchar] (128) COLLATE Latin1_General_CI_AI NOT NULL
) ON [PRIMARY] 
GO

CREATE TABLE [dbo].[contactgroupmembers] (
	[contactgroup_id] [int] NOT NULL ,
	[contact_id] [int] NOT NULL ,
	[created] [datetime] NOT NULL
) ON [PRIMARY] 
GO

ALTER TABLE [dbo].[contactgroups] WITH NOCHECK ADD 
	CONSTRAINT [PK_contactgroups_contactgroup_id] PRIMARY KEY CLUSTERED 
	(
		[contactgroup_id]
	)  ON [PRIMARY] 
GO

ALTER TABLE [dbo].[contactgroupmembers] WITH NOCHECK ADD 
	CONSTRAINT [PK_contactgroupmembers_id] PRIMARY KEY CLUSTERED 
	(
		[contactgroup_id], [contact_id]
	)  ON [PRIMARY] 
GO

ALTER TABLE [dbo].[contactgroups] ADD 
	CONSTRAINT [DF_contactgroups_user_id] DEFAULT (0) FOR [user_id],
	CONSTRAINT [DF_contactgroups_changed] DEFAULT (getdate()) FOR [changed],
	CONSTRAINT [DF_contactgroups_del] DEFAULT ('0') FOR [del],
	CONSTRAINT [DF_contactgroups_name] DEFAULT ('') FOR [name],
	CONSTRAINT [CK_contactgroups_del] CHECK ([del] = '1' or [del] = '0')
GO

CREATE  INDEX [IX_contactgroups_user_id] ON [dbo].[contacts]([user_id]) ON [PRIMARY]
GO

ALTER TABLE [dbo].[contactgroupmembers] ADD 
	CONSTRAINT [DF_contactgroupmembers_contactgroup_id] DEFAULT (0) FOR [contactgroup_id],
	CONSTRAINT [DF_contactgroupmembers_contact_id] DEFAULT (0) FOR [contact_id],
	CONSTRAINT [DF_contactgroupmembers_created] DEFAULT (getdate()) FOR [created]
GO

ALTER TABLE [dbo].[contactgroupmembers] ADD CONSTRAINT [FK_contactgroupmembers_contactgroup_id]
    FOREIGN KEY ([contactgroup_id]) REFERENCES [dbo].[contactgroups] ([contactgroup_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO

CREATE TRIGGER [contact_delete_member] ON [dbo].[contacts]
    AFTER DELETE AS
    DELETE FROM [dbo].[contactgroupmembers]
    WHERE [contact_id] IN (SELECT [contact_id] FROM deleted)
GO

ALTER TABLE [dbo].[contactgroups] ADD CONSTRAINT [FK_contactgroups_user_id]
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO
