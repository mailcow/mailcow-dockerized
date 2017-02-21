-- Updates from version 0.6

CREATE TABLE [dbo].[dictionary] (
    [user_id] [int] ,
    [language] [varchar] (5) COLLATE Latin1_General_CI_AI NOT NULL ,
    [data] [text] COLLATE Latin1_General_CI_AI NOT NULL 
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO
CREATE  UNIQUE INDEX [IX_dictionary_user_language] ON [dbo].[dictionary]([user_id],[language]) ON [PRIMARY]
GO

CREATE TABLE [dbo].[searches] (
	[search_id] [int] IDENTITY (1, 1) NOT NULL ,
	[user_id] [int] NOT NULL ,
	[type] [tinyint] NOT NULL ,
	[name] [varchar] (128) COLLATE Latin1_General_CI_AI NOT NULL ,
	[data] [text] COLLATE Latin1_General_CI_AI NOT NULL 
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

ALTER TABLE [dbo].[searches] WITH NOCHECK ADD 
	CONSTRAINT [PK_searches_search_id] PRIMARY KEY CLUSTERED 
	(
		[search_id]
	) ON [PRIMARY] 
GO

ALTER TABLE [dbo].[searches] ADD 
	CONSTRAINT [DF_searches_user] DEFAULT (0) FOR [user_id],
	CONSTRAINT [DF_searches_type] DEFAULT (0) FOR [type],
GO

CREATE UNIQUE INDEX [IX_searches_user_type_name] ON [dbo].[searches]([user_id],[type],[name]) ON [PRIMARY]
GO

ALTER TABLE [dbo].[searches] ADD CONSTRAINT [FK_searches_user_id]
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO

DROP TABLE [dbo].[messages]
GO
CREATE TABLE [dbo].[cache_index] (
	[user_id] [int] NOT NULL ,
	[mailbox] [varchar] (128) COLLATE Latin1_General_CI_AI NOT NULL ,
	[changed] [datetime] NOT NULL ,
	[valid] [char] (1) COLLATE Latin1_General_CI_AI NOT NULL ,
	[data] [text] COLLATE Latin1_General_CI_AI NOT NULL 
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

CREATE TABLE [dbo].[cache_thread] (
	[user_id] [int] NOT NULL ,
	[mailbox] [varchar] (128) COLLATE Latin1_General_CI_AI NOT NULL ,
	[changed] [datetime] NOT NULL ,
	[data] [text] COLLATE Latin1_General_CI_AI NOT NULL 
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

CREATE TABLE [dbo].[cache_messages] (
	[user_id] [int] NOT NULL ,
	[mailbox] [varchar] (128) COLLATE Latin1_General_CI_AI NOT NULL ,
	[uid] [int] NOT NULL ,
	[changed] [datetime] NOT NULL ,
	[data] [text] COLLATE Latin1_General_CI_AI NOT NULL ,
	[flags] [int] NOT NULL
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

ALTER TABLE [dbo].[cache_index] WITH NOCHECK ADD 
	 PRIMARY KEY CLUSTERED 
	(
		[user_id],[mailbox]
	) ON [PRIMARY] 
GO

ALTER TABLE [dbo].[cache_thread] WITH NOCHECK ADD 
	 PRIMARY KEY CLUSTERED 
	(
		[user_id],[mailbox]
	) ON [PRIMARY] 
GO

ALTER TABLE [dbo].[cache_messages] WITH NOCHECK ADD 
	 PRIMARY KEY CLUSTERED 
	(
		[user_id],[mailbox],[uid]
	) ON [PRIMARY] 
GO

ALTER TABLE [dbo].[cache_index] ADD 
	CONSTRAINT [DF_cache_index_changed] DEFAULT (getdate()) FOR [changed],
	CONSTRAINT [DF_cache_index_valid] DEFAULT ('0') FOR [valid]
GO

CREATE  INDEX [IX_cache_index_user_id] ON [dbo].[cache_index]([user_id]) ON [PRIMARY]
GO

ALTER TABLE [dbo].[cache_thread] ADD 
	CONSTRAINT [DF_cache_thread_changed] DEFAULT (getdate()) FOR [changed]
GO

CREATE  INDEX [IX_cache_thread_user_id] ON [dbo].[cache_thread]([user_id]) ON [PRIMARY]
GO

ALTER TABLE [dbo].[cache_messages] ADD 
	CONSTRAINT [DF_cache_messages_changed] DEFAULT (getdate()) FOR [changed],
	CONSTRAINT [DF_cache_messages_flags] DEFAULT (0) FOR [flags]
GO

CREATE  INDEX [IX_cache_messages_user_id] ON [dbo].[cache_messages]([user_id]) ON [PRIMARY]
GO

ALTER TABLE [dbo].[cache_index] ADD CONSTRAINT [FK_cache_index_user_id]
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO

ALTER TABLE [dbo].[cache_thread] ADD CONSTRAINT [FK_cache_thread_user_id]
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO

ALTER TABLE [dbo].[cache_messages] ADD CONSTRAINT [FK_cache_messages_user_id]
    FOREIGN KEY ([user_id]) REFERENCES [dbo].[users] ([user_id])
    ON DELETE CASCADE ON UPDATE CASCADE
GO
