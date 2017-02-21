CREATE TABLE [dbo].[cache_shared] (
	[cache_key] [varchar] (255) COLLATE Latin1_General_CI_AI NOT NULL ,
	[created] [datetime] NOT NULL ,
	[data] [text] COLLATE Latin1_General_CI_AI NOT NULL 
) ON [PRIMARY] TEXTIMAGE_ON [PRIMARY]
GO

ALTER TABLE [dbo].[cache_shared] ADD 
	CONSTRAINT [DF_cache_shared_created] DEFAULT (getdate()) FOR [created]
GO

CREATE  INDEX [IX_cache_shared_cache_key] ON [dbo].[cache_shared]([cache_key]) ON [PRIMARY]
GO

CREATE  INDEX [IX_cache_shared_created] ON [dbo].[cache_shared]([created]) ON [PRIMARY]
GO

